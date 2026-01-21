<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('POST');
$user = require_authenticated_user();

$rateLimitKey = 'upload_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKey, 5, 600)) { 
	json_response(429, ['error' => 'Too many upload attempts. Please try again later.']);
}

$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
$maxSize = 2 * 1024 * 1024;
if ($contentLength > $maxSize) {
	json_response(400, ['error' => 'File too large. Maximum size is 2 MB']);
}

if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
	json_response(400, ['error' => 'Avatar upload is required']);
}

$file = $_FILES['avatar'];
if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
	json_response(400, ['error' => 'Avatar upload failed']);
}

$originalName = $file['name'] ?? '';

if (strpos($originalName, "\0") !== false) {
	json_response(400, ['error' => 'Invalid file name detected']);
}

if (strpos($originalName, '..') !== false || strpos($originalName, '/') !== false || strpos($originalName, '\\') !== false) {
	json_response(400, ['error' => 'Invalid file name detected']);
}

$dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'pht', 'phar', 'inc', 'hta', 'htaccess', 'sh', 'exe', 'com', 'bat', 'cgi', 'pl', 'py', 'rb', 'java', 'jar', 'war', 'asp', 'aspx', 'jsp', 'swf'];

$nameParts = explode('.', strtolower($originalName));
foreach ($nameParts as $part) {
	if (in_array($part, $dangerousExtensions, true)) {
		json_response(400, ['error' => 'Invalid file extension detected']);
	}
}

try {
	$allowedTypes = [
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
	];
	$maxDimension = 2000; 

	if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxSize) {
		json_response(400, ['error' => 'Avatar must be between 1 byte and 2 MB']);
	}

	$finfo = finfo_open(FILEINFO_MIME_TYPE);
	$detectedType = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');
	if ($finfo) {
		finfo_close($finfo);
	}

	if (!isset($allowedTypes[$detectedType])) {
		json_response(400, ['error' => 'Unsupported image type']);
	}

	
	$fileHandle = fopen($file['tmp_name'], 'rb');
	if (!$fileHandle) {
		json_response(500, ['error' => 'Failed to read uploaded file']);
	}
	$magicBytes = fread($fileHandle, 12);
	fclose($fileHandle);

	
	$validJpeg = (substr($magicBytes, 0, 3) === "\xFF\xD8\xFF");
	$validPng = (substr($magicBytes, 0, 8) === "\x89PNG\r\n\x1a\n");
	$validWebp = (substr($magicBytes, 0, 4) === "RIFF" && substr($magicBytes, 8, 4) === "WEBP");

	if (!($validJpeg || $validPng || $validWebp)) {
		json_response(400, ['error' => 'Invalid image file. File signature does not match image format.']);
	}

	$imgInfo = @getimagesize($file['tmp_name']);
	if ($imgInfo === false) {
		json_response(400, ['error' => 'Invalid image file']);
	}

	[$width, $height] = $imgInfo;
	if ($width > $maxDimension || $height > $maxDimension) {
		json_response(400, ['error' => 'Image dimensions must not exceed 2000x2000 pixels']);
	}

	$originalBinary = file_get_contents($file['tmp_name']);
	if ($originalBinary === false) {
		json_response(500, ['error' => 'Failed to read uploaded avatar']);
	}

	try {
		$processed = resize_image_binary($originalBinary, $detectedType, 400);
	} catch (RuntimeException $e) {
		json_response(400, ['error' => $e->getMessage()]);
	}

	$avatarBinary = $processed['data'];
	$detectedType = $processed['mime'];

	$verifyInfo = @getimagesizefromstring($avatarBinary);
	if ($verifyInfo === false) {
		json_response(400, ['error' => 'Image processing failed - file may be corrupted']);
	}

	$pdo = get_pdo();
	$stmt = $pdo->prepare('UPDATE users SET avatar_data = :data, avatar_mime = :mime, avatar_updated_at = NOW(), updated_at = NOW() WHERE id = :id');
	$stmt->bindValue(':data', $avatarBinary, PDO::PARAM_LOB);
	$stmt->bindValue(':mime', $detectedType);
	$stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
	$stmt->execute();

	$updatedUser = getUserById((int) $user['id']);
	if ($updatedUser) {
		record_activity((int) $user['id'], 'profile.avatar.update', 'Updated profile avatar');
	}

	json_response(200, [
		'message' => 'Avatar uploaded successfully',
		'user' => format_user_response($updatedUser ?? []),
	]);
} catch (Throwable $e) {
	error_log('upload_avatar failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
