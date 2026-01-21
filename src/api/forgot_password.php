<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/email_utils.php';

ensure_http_method('POST');

if (session_status() !== PHP_SESSION_ACTIVE) {
    start_secure_session();
}

// Rate limiting - 3 attempts per hour per email
$input = require_json_input();
$email = sanitize_string($input['email'] ?? '');

if ($email === '') {
	json_response(400, ['error' => 'Email is required']);
}

if (!validate_email($email)) {
	json_response(400, ['error' => 'Invalid email format']);
}

$rateLimitKey = 'forgot_password_' . md5(strtolower($email));
if (!check_rate_limit($rateLimitKey, 3, 3600)) {
	json_response(429, ['error' => 'Too many password reset requests. Please try again later.', 'retry_after' => 3600]);
}

try {
	$pdo = get_pdo();
	ensure_required_tables($pdo);

	$stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE email = :email LIMIT 1');
	$stmt->execute([':email' => $email]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($user) {
		$token = bin2hex(random_bytes(32));
		$tokenHash = hash('sha256', $token);
		$expiresAt = date('Y-m-d H:i:s', time() + ((int)(getenv('PASSWORD_RESET_EXPIRY') ?: 3600)));

		try {
			$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
				id BIGSERIAL PRIMARY KEY,
				user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
				token_hash VARCHAR(255) NOT NULL,
				expires_at TIMESTAMP NOT NULL,
				used BOOLEAN NOT NULL DEFAULT FALSE,
				created_at TIMESTAMP NOT NULL DEFAULT NOW()
			)");
			$pdo->exec("CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets (token_hash)");
		} catch (Throwable $e) {
	
		}

		$deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id AND (used = TRUE OR expires_at < NOW())');
		$deleteStmt->execute([':user_id' => $user['id']]);

		$insertStmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)');
		$insertStmt->execute([
			':user_id' => $user['id'],
			':token_hash' => $tokenHash,
			':expires_at' => $expiresAt
		]);

		$emailSent = send_password_reset_email($user['email'], $user['full_name'], $token);

		if ($emailSent) {
			record_activity((int)$user['id'], 'auth.password_reset_requested', 'Password reset email sent');
		} else {
			error_log('Failed to send password reset email to: ' . $email);
		}
	}
	
	usleep(random_int(200000, 400000));

	json_response(200, [
		'message' => 'If an account exists with this email, a password reset link has been sent. Please check your inbox.'
	]);

} catch (Throwable $e) {
	error_log('forgot_password error: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
