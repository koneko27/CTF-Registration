<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

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

$rateLimitKey = 'signin_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKey, 5, 300)) {
	$remaining = get_rate_limit_remaining($rateLimitKey);
	json_response(429, ['error' => 'Too many login attempts. Please try again later.', 'retry_after' => 300]);
}

try {
	$pdo = get_pdo();
	$stmt = $pdo->prepare('SELECT id, full_name, email, username, password_hash, avatar_updated_at, CASE WHEN avatar_data IS NOT NULL THEN 1 ELSE 0 END AS has_avatar, bio, location, role, token_version, created_at, updated_at
		FROM users WHERE email = :identifier OR username = :identifier LIMIT 1');
	$stmt->execute([':identifier' => $identifier]);
	$user = $stmt->fetch();

	if (!$user || !password_verify($password, $user['password_hash'])) {
		error_log('Failed login attempt for identifier: ' . $identifier . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
		json_response(401, ['error' => 'Invalid credentials']);
	}
	
	$rateLimitKey = 'signin_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
	if (isset($_SESSION['rate_limit_' . $rateLimitKey])) {
		unset($_SESSION['rate_limit_' . $rateLimitKey]);
	}

	loginUser((int) $user['id'], (string) $user['role'], (int) ($user['token_version'] ?? 1));
	// Rotate CSRF token for the new authenticated session
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	
	record_activity((int) $user['id'], 'auth.signin', 'User signed in', ['identifier' => $identifier]);

	if (!empty($input['rememberMe'])) {
		ensure_required_tables($pdo);
		$selector = bin2hex(random_bytes(12));
		$validator = bin2hex(random_bytes(32));
		$hashedValidator = hash('sha256', $validator);
		$expiresAt = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days

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
