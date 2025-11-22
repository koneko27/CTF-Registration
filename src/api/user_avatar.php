<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('GET');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
	http_response_code(400);
	echo 'Invalid user id';
	exit;
}

try {
	$pdo = get_pdo();
	ensure_required_tables($pdo);

	$stmt = $pdo->prepare('SELECT avatar_data, avatar_mime FROM users WHERE id = :id LIMIT 1');
	$stmt->execute([':id' => $id]);
	$avatar = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$avatar || $avatar['avatar_data'] === null) {
		http_response_code(404);
		echo 'Not found';
		exit;
	}

	$mime = $avatar['avatar_mime'] ?: 'application/octet-stream';
	$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
	if (!in_array($mime, $allowedMimes, true)) {
		http_response_code(400);
		echo 'Invalid image type';
		exit;
	}
	
	header('Content-Type: ' . $mime);
	header('Cache-Control: public, max-age=3600');
	header('X-Content-Type-Options: nosniff');
	header('Content-Security-Policy: default-src \'self\';');
	
	$data = $avatar['avatar_data'];
	if (is_resource($data)) {
		fpassthru($data);
	} else {
		echo $data;
	}
	exit;
} catch (Throwable $e) {
	error_log('user_avatar error: ' . $e->getMessage());
	http_response_code(500);
	echo 'Error';
	exit;
}


