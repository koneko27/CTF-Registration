<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('POST');

$rateLimitKey = 'signup_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKey, 5, 3600)) {
	$remaining = get_rate_limit_remaining($rateLimitKey);
	json_response(429, ['error' => 'Too many registration attempts. Please try again later.', 'retry_after' => 3600]);
}

$input = require_json_input();

$fullName = sanitize_string($input['fullName'] ?? '');
$email = sanitize_string($input['email'] ?? '');
$username = sanitize_string($input['username'] ?? '');
$password = (string) ($input['password'] ?? '');
$confirmPassword = (string) ($input['confirmPassword'] ?? '');

if ($fullName === '' || $email === '' || $username === '' || $password === '' || $confirmPassword === '') {
	json_response(400, ['error' => 'All fields are required']);
}

if (!validate_full_name($fullName)) {
	json_response(400, ['error' => 'Full name must be 1-30 characters and cannot contain special characters']);
}

if (!validate_email($email)) {
	json_response(400, ['error' => 'Invalid email format']);
}

if (!preg_match('/@(gmail\.com|binus\.ac\.id)$/iD', $email)) {
	json_response(400, ['error' => 'Registration is restricted to @gmail.com or @binus.ac.id emails only']);
}

if (!validate_username($username)) {
	json_response(400, ['error' => 'Username must be 3-30 characters and alphanumeric/underscore only']);
}

if (strlen($password) < 12 || strlen($password) > 128) {
	json_response(400, ['error' => 'Password must be between 12 and 128 characters']);
}

if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?~`]/', $password)) {
	json_response(400, ['error' => 'Password must include upper, lower, numeric, and special characters']);
}

if ($password !== $confirmPassword) {
	json_response(400, ['error' => 'Passwords do not match']);
}

try {
	$pdo = get_pdo();
	$passwordHash = password_hash($password, PASSWORD_DEFAULT);

	$stmt = $pdo->prepare('INSERT INTO users (full_name, email, username, password_hash, created_at, updated_at)
		VALUES (:name, :email, :username, :password_hash, NOW(), NOW())
		RETURNING id');
	$stmt->execute([
		':name' => $fullName,
		':email' => $email,
		':username' => $username,
		':password_hash' => $passwordHash,
	]);

	$userId = (int) ($stmt->fetchColumn() ?: 0);
	if ($userId > 0) {
		record_activity($userId, 'auth.signup', 'Account created');
	}

	json_response(201, ['message' => 'Account created successfully']);
} catch (PDOException $e) {
	// Use generic error for ALL failures to prevent account enumeration
	if ($e->getCode() === '23505') {
		error_log('signup duplicate detected: ' . $e->getMessage());
	} else {
		error_log('signup failed: ' . $e->getMessage());
	}
	
	// Same generic error for both duplicate and other failures
	json_response(500, ['error' => 'Server error']);
}
