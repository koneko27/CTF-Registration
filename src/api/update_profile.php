<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('POST');
$user = require_authenticated_user();
$input = require_json_input();

try {
	$pdo = get_pdo();
	$updates = [];
	$params = ['id' => $user['id']];
	$passwordChanged = false;

	$fullNameValue = $input['fullName'] ?? $input['full_name'] ?? null;
	if ($fullNameValue !== null) {
		$fullName = sanitize_string($fullNameValue);
		if (!validate_full_name($fullName)) {
			json_response(400, ['error' => 'Full name must be 1-30 characters and cannot contain special characters']);
		}
		$updates[] = 'full_name = :full_name';
		$params['full_name'] = $fullName;
	}

	$emailValue = $input['email'] ?? null;
	if ($emailValue !== null) {
		$email = sanitize_string($emailValue);
		if (!validate_email($email)) {
			json_response(400, ['error' => 'Invalid email format']);
		}
		$updates[] = 'email = :email';
		$params['email'] = $email;
	}

	$locationValue = $input['location'] ?? null;
	if ($locationValue !== null) {
		$location = sanitize_string($locationValue);
		if (mb_strlen($location, 'UTF-8') > 100) {
			json_response(400, ['error' => 'Location must be under 100 characters']);
		}
		$updates[] = 'location = :location';
		$params['location'] = $location === '' ? null : $location;
	}

	$bioValue = $input['bio'] ?? null;
	if ($bioValue !== null) {
		$bio = sanitize_string($bioValue);
		if (mb_strlen($bio, 'UTF-8') > 500) {
			json_response(400, ['error' => 'Bio must be under 500 characters']);
		}
		$updates[] = 'bio = :bio';
		$params['bio'] = $bio === '' ? null : $bio;
	}

	$currentPassword = $input['currentPassword'] ?? $input['current_password'] ?? '';
	$newPassword = $input['newPassword'] ?? $input['new_password'] ?? '';
	$confirmPassword = $input['confirmPassword'] ?? $input['confirm_password'] ?? '';

	if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
		$currentPassword = (string) $currentPassword;
		$newPassword = (string) $newPassword;
		$confirmPassword = (string) $confirmPassword;

		if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
			json_response(400, ['error' => 'Current, new, and confirm password are required to change password']);
		}

		if ($newPassword !== $confirmPassword) {
			json_response(400, ['error' => 'New password and confirmation do not match']);
		}

	if (strlen($newPassword) < 12 || strlen($newPassword) > 128) {
		json_response(400, ['error' => 'New password must be between 12 and 128 characters']);
	}

	if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?~`]/', $newPassword)) {
		json_response(400, ['error' => 'New password must include upper, lower, numeric, and special characters']);
	}

		$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
		$stmt->execute([':id' => $user['id']]);
		$stored = $stmt->fetchColumn();

		if (!$stored || !password_verify($currentPassword, $stored)) {
			json_response(403, ['error' => 'Current password is incorrect']);
		}

		$updates[] = 'password_hash = :password_hash';
		$params['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

		$updates[] = 'token_version = token_version + 1';
		$passwordChanged = true;
	}

	if (!$updates) {
		json_response(400, ['error' => 'No valid fields to update']);
	}

	$allowedFields = ['full_name', 'email', 'location', 'bio', 'password_hash', 'token_version'];
	$sanitizedUpdates = [];
	foreach ($updates as $update) {
		$fieldName = explode(' =', $update)[0] ?? '';
		if (in_array(trim($fieldName), $allowedFields, true)) {
			$sanitizedUpdates[] = $update;
		}
	}
	
	if (empty($sanitizedUpdates)) {
		json_response(400, ['error' => 'No valid fields to update']);
	}

	$needsTransaction = $passwordChanged || isset($params['email']);
	if ($needsTransaction) {
		$pdo->beginTransaction();
	}

	try {
		if (isset($params['email'])) {
			$check = $pdo->prepare('SELECT 1 FROM users WHERE email = :email AND id <> :id FOR UPDATE');
			$check->execute([':email' => $params['email'], ':id' => $user['id']]);
			if ($check->fetch()) {
				if ($needsTransaction) {
					$pdo->rollBack();
				}
				json_response(409, ['error' => 'Email already in use']);
			}
		}

		$sql = 'UPDATE users SET ' . implode(', ', $sanitizedUpdates) . ', updated_at = NOW() WHERE id = :id RETURNING token_version';
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		$newTokenVersion = $stmt->fetchColumn();

		if ($passwordChanged) {
			$deleteSessionsStmt = $pdo->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
			$deleteSessionsStmt->execute([':user_id' => $user['id']]);
			
			if ($newTokenVersion) {
				$_SESSION['token_version'] = (int)$newTokenVersion;
			}
		}

		if ($needsTransaction) {
			$pdo->commit();
		}
	} catch (Throwable $e) {
		if ($needsTransaction && $pdo->inTransaction()) {
			$pdo->rollBack();
		}
		throw $e;
	}

	$updatedUser = getUserById((int) $user['id']);
	if ($updatedUser) {
		record_activity((int) $user['id'], 'profile.update', 'Updated profile information', [
			'fields' => array_map(static fn ($field) => explode(' =', $field)[0] ?? '', $updates),
		]);
		if ($passwordChanged) {
			record_activity((int) $user['id'], 'profile.password.change', 'Changed account password');
		}
	}

	json_response(200, [
		'message' => 'Profile updated successfully',
		'user' => format_user_response($updatedUser ?? []),
	]);
} catch (Throwable $e) {
	error_log('update_profile failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
