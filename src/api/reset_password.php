<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('POST');

$input = require_json_input();
$token = sanitize_string($input['token'] ?? '');
$newPassword = (string)($input['newPassword'] ?? '');
$confirmPassword = (string)($input['confirmPassword'] ?? '');

if ($token === '') {
	json_response(400, ['error' => 'Reset token is required']);
}

if ($newPassword === '' || $confirmPassword === '') {
	json_response(400, ['error' => 'Password and confirmation are required']);
}

if ($newPassword !== $confirmPassword) {
	json_response(400, ['error' => 'Passwords do not match']);
}

// Validate password strength
if (strlen($newPassword) < 12 || strlen($newPassword) > 128) {
	json_response(400, ['error' => 'Password must be between 12 and 128 characters']);
}

if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?~`]/', $newPassword)) {
	json_response(400, ['error' => 'Password must include upper, lower, numeric, and special characters']);
}

try {
	$pdo = get_pdo();
	ensure_required_tables($pdo);

	// Hash the token to look it up
	$tokenHash = hash('sha256', $token);

	// Find valid, unused, non-expired token
	$stmt = $pdo->prepare('SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.email, u.full_name 
		FROM password_resets pr 
		JOIN users u ON pr.user_id = u.id 
		WHERE pr.token_hash = :token_hash 
		LIMIT 1');
	$stmt->execute([':token_hash' => $tokenHash]);
	$reset = $stmt->fetch(PDO::FETCH_ASSOC);

	// Combine all validation checks to prevent timing attacks
	if (!$reset || $reset['used'] || strtotime($reset['expires_at']) < time()) {
		json_response(400, ['error' => 'Invalid or expired reset token']);
	}

	// All validations passed - reset the password
	$pdo->beginTransaction();

	try {
		// Update password and token version (invalidates all sessions)
		$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
		$updateStmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash, token_version = token_version + 1, updated_at = NOW() WHERE id = :user_id');
		$updateStmt->execute([
			':password_hash' => $newPasswordHash,
			':user_id' => $reset['user_id']
		]);

		// Mark token as used and delete it (defense-in-depth)
		$markUsedStmt = $pdo->prepare('DELETE FROM password_resets WHERE id = :id');
		$markUsedStmt->execute([':id' => $reset['id']]);

		// Delete all remember-me sessions for this user
		$deleteSessionsStmt = $pdo->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
		$deleteSessionsStmt->execute([':user_id' => $reset['user_id']]);

		// Commit transaction
		$pdo->commit();

		// Record activity
		record_activity((int)$reset['user_id'], 'auth.password_reset_completed', 'Password was reset successfully');

		error_log('Password reset successful for user ID: ' . $reset['user_id']);

		json_response(200, [
			'message' => 'Password reset successfully. You can now sign in with your new password.'
		]);

	} catch (Throwable $e) {
		$pdo->rollBack();
		throw $e;
	}

} catch (Throwable $e) {
	error_log('reset_password error: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
