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
	$stmt = $pdo->prepare('SELECT id, full_name, email, username, password_hash, avatar_updated_at, CASE WHEN avatar_data IS NOT NULL THEN 1 ELSE 0 END AS has_avatar, bio, location, role, created_at, updated_at
		FROM users WHERE email = :identifier OR username = :identifier LIMIT 1');
	$stmt->execute([':identifier' => $identifier]);
	$user = $stmt->fetch();

	if (!$user || !password_verify($password, $user['password_hash'])) {
		json_response(401, ['error' => 'Invalid credentials']);
	}
	
	$rateLimitKey = 'signin_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
	if (isset($_SESSION['rate_limit_' . $rateLimitKey])) {
		unset($_SESSION['rate_limit_' . $rateLimitKey]);
	}

	loginUser((int) $user['id'], (string) $user['role']);
	record_activity((int) $user['id'], 'auth.signin', 'User signed in', ['identifier' => $identifier]);

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
