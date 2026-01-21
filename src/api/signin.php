<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

$rateLimitKeyIP = 'signin_ip_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKeyIP, 10, 300)) {
	json_response(429, ['error' => 'Too many login attempts from this IP. Please try again later.', 'retry_after' => 300]);
}

ensure_http_method('POST');

$input = require_json_input();

$identifier = sanitize_string($input['identifier'] ?? '');
$password = (string) ($input['password'] ?? '');

if ($identifier === '' || $password === '') {
	json_response(400, ['error' => 'Email/username and password are required']);
}

if (strlen($password) > 128) {
	json_response(400, ['error' => 'Password too long']);
}

if (strlen($identifier) > 255) {
	json_response(400, ['error' => 'Identifier too long']);
}

try {
	$lockStmt = $pdo->prepare('SELECT locked_until FROM users WHERE email = :identifier OR username = :identifier LIMIT 1');
	$lockStmt->execute([':identifier' => $identifier]);
	$lockData = $lockStmt->fetch(PDO::FETCH_ASSOC);
	
	if ($lockData && $lockData['locked_until']) {
		$lockedUntil = strtotime($lockData['locked_until']);
		if ($lockedUntil > time()) {
			$minutesLeft = ceil(($lockedUntil - time()) / 60);
			json_response(429, [
				'error' => 'Account is temporarily locked due to too many failed login attempts. Please try again in ' . $minutesLeft . ' minutes.',
				'retry_after' => $lockedUntil - time()
			]);
		}
	}
} catch (Throwable $e) {
}

$rateLimitKeyAccount = 'signin_account_' . md5(strtolower($identifier));
if (!check_rate_limit($rateLimitKeyAccount, 5, 900)) {
	json_response(429, ['error' => 'Too many login attempts for this account. Please try again later.', 'retry_after' => 900]);
}

try {
	$pdo = get_pdo();
	$stmt = $pdo->prepare('SELECT id, full_name, email, username, password_hash, avatar_updated_at, CASE WHEN avatar_data IS NOT NULL THEN 1 ELSE 0 END AS has_avatar, bio, location, role, token_version, created_at, updated_at
		FROM users WHERE email = :identifier OR username = :identifier LIMIT 1');
	$stmt->execute([':identifier' => $identifier]);
	$user = $stmt->fetch();

	$storedHash = $user['password_hash'] ?? password_hash('dummy_password_for_timing', PASSWORD_DEFAULT);
	
	$passwordValid = password_verify($password, $storedHash);
	
	
	if ($user && (!$passwordValid || !$user)) {
		try {
			error_log('Failed login attempt for identifier: ' . $identifier . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
			
			$countStmt = $pdo->prepare('SELECT COUNT(*) FROM failed_login_attempts WHERE identifier = :identifier AND attempt_at > NOW() - INTERVAL \'15 minutes\'');
			$countStmt->execute([':identifier' => strtolower($identifier)]);
			$failedCount = (int)$countStmt->fetchColumn();
			
			$insertStmt = $pdo->prepare('INSERT INTO failed_login_attempts (identifier, ip_address, user_agent) VALUES (:identifier, :ip, :ua)');
			$insertStmt->execute([
				':identifier' => strtolower($identifier),
				':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
				':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
			]);
			
			if ($failedCount >= 9) { 
				$lockUntil = date('Y-m-d H:i:s', time() + 3600); 
				$lockStmt = $pdo->prepare('UPDATE users SET locked_until = :locked_until WHERE id = :user_id');
				$lockStmt->execute([':locked_until' => $lockUntil, ':user_id' => $user['id']]);
				
				error_log('Account locked for user ID ' . $user['id'] . ' due to failed login attempts');
			}
		} catch (Throwable $e) {

		}
	}
	
	usleep(random_int(100000, 300000)); 
	
	if (!$user || !$passwordValid) {
		json_response(401, ['error' => 'Invalid credentials']);
	}

	try {
		$clearStmt = $pdo->prepare('DELETE FROM failed_login_attempts WHERE identifier = :identifier');
		$clearStmt->execute([':identifier' => strtolower($identifier)]);
		
		$unlockStmt = $pdo->prepare('UPDATE users SET locked_until = NULL WHERE id = :user_id AND locked_until IS NOT NULL');
		$unlockStmt->execute([':user_id' => $user['id']]);
	} catch (Throwable $e) {

	}
	
	loginUser((int) $user['id'], (string) $user['role'], (int) ($user['token_version'] ?? 1));
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	
	record_activity((int) $user['id'], 'auth.signin', 'User signed in', ['identifier' => $identifier]);

	if (!empty($input['rememberMe'])) {
		ensure_required_tables($pdo);
		$selector = bin2hex(random_bytes(12));
		$validator = bin2hex(random_bytes(32));
		$hashedValidator = hash('sha256', $validator);
		$expiresAt = date('Y-m-d H:i:s', time() + 86400 * 30);

		$stmt = $pdo->prepare('INSERT INTO user_sessions (user_id, selector, hashed_validator, expires_at) VALUES (:uid, :sel, :val, :exp)');
		$stmt->execute([
			':uid' => $user['id'],
			':sel' => $selector,
			':val' => $hashedValidator,
			':exp' => $expiresAt
		]);

		$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
		setcookie('remember_me', $selector . ':' . $validator, [
			'expires' => time() + 86400 * 30,
			'path' => '/',
			'secure' => $isSecure,
			'httponly' => true,
			'samesite' => 'Lax'
		]);
	}

	ensure_csrf_token();

	json_response(200, [
		'message' => 'Signed in successfully',
		'user' => format_user_response($user),
		'csrf_token' => $_SESSION['csrf_token'] ?? null,
	]);
} catch (Throwable $e) {
	error_log('signin failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
