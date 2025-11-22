<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

try {
	start_secure_session();
	ensure_csrf_token();
	$user = getCurrentUser();

	$response = [
		'authenticated' => (bool) $user,
		'user' => $user ? format_user_response($user) : null,
	];

	if (!empty($_SESSION['csrf_token'])) {
		$response['csrf_token'] = $_SESSION['csrf_token'];
	}

	json_response(200, $response);
} catch (Throwable $e) {
	error_log('get_current_user failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
