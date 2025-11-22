<?php

require_once __DIR__ . '/db.php';

function json_response(int $statusCode, array $data): void {
	http_response_code($statusCode);
	header('Content-Type: application/json; charset=UTF-8');
	header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: DENY');
	header('X-XSS-Protection: 1; mode=block');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src \'self\' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src \'self\' data:; connect-src \'self\';');
	$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
	if ($isSecure) {
		header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
	}
	echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	exit;
}

function get_json_input(): array {
	$maxSize = 1024 * 1024;
	$raw = file_get_contents('php://input');
	if ($raw === false || $raw === '') {
		return [];
	}
	
	if (strlen($raw) > $maxSize) {
		throw new InvalidArgumentException('Request payload too large.');
	}

	$decoded = json_decode($raw, true);
	if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
		throw new InvalidArgumentException('Invalid JSON payload.');
	}

	return $decoded;
}

function require_json_input(): array {
	try {
		return get_json_input();
	} catch (InvalidArgumentException $e) {
		json_response(400, ['error' => $e->getMessage()]);
	}
}

function sanitize_string(?string $value): string {
	if ($value === null) {
		return '';
	}
	$value = trim($value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
	return $value;
}

function validate_full_name(string $name): bool {
	if (strlen($name) < 1 || strlen($name) > 100) {
		return false;
	}
	if (preg_match('/[<>"\']/', $name)) {
		return false;
	}
	return true;
}

function validate_username(string $username): bool {
	return preg_match('/^[A-Za-z0-9_]{3,32}$/', $username) === 1;
}

function validate_email(string $email): bool {
	return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function ensure_csrf_token(): void {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return;
	}
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
}

function verify_csrf_token(): void {
	$headers = getallheaders();
	$token = $headers['X-CSRF-Token'] ?? $_POST['csrf_token'] ?? '';
	
	if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
		json_response(403, ['error' => 'Invalid CSRF token']);
	}
}

function start_secure_session(): void {
	if (session_status() === PHP_SESSION_ACTIVE) {
		ensure_csrf_token();
		return;
	}

	session_start([
		'use_strict_mode' => 1,
		'use_cookies' => 1,
		'use_only_cookies' => 1,
		'cookie_httponly' => 1,
		'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') ? 1 : 0,
		'cookie_samesite' => 'Lax',
	]);
	ensure_csrf_token();
}

function ensure_http_method(string ...$allowed): void {
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	if (!in_array($method, $allowed, true)) {
		header('Allow: ' . implode(', ', $allowed));
		json_response(405, ['error' => 'Method Not Allowed']);
	}
}

function getCurrentUser(): ?array {
	start_secure_session();

	$userId = $_SESSION['user_id'] ?? null;
	if (!$userId) {
		return null;
	}

	$pdo = get_pdo();
	$stmt = $pdo->prepare('SELECT id, full_name, email, username, avatar_updated_at, CASE WHEN avatar_data IS NOT NULL THEN 1 ELSE 0 END AS has_avatar, bio, location, role, created_at, updated_at FROM users WHERE id = ?');
	$stmt->execute([$userId]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$user) {
		logoutUser();
		return null;
	}

	$_SESSION['user_role'] = $user['role'] ?? null;

	return $user;
}

function require_authenticated_user(bool $requireAdmin = false): array {
	$user = getCurrentUser();
	if (!$user) {
		json_response(401, ['error' => 'Unauthorized']);
	}

	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
		verify_csrf_token();
	}

	if ($requireAdmin && ($user['role'] ?? '') !== 'admin') {
		json_response(403, ['error' => 'Access denied. Admin privileges required.']);
	}

	return $user;
}

function getUserById(int $userId): ?array {
	$pdo = get_pdo();
	$stmt = $pdo->prepare('SELECT id, full_name, email, username, avatar_updated_at, CASE WHEN avatar_data IS NOT NULL THEN 1 ELSE 0 END AS has_avatar, bio, location, role, created_at, updated_at FROM users WHERE id = ?');
	$stmt->execute([$userId]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	return $user ?: null;
}

function loginUser(int $userId, string $role): void {
	start_secure_session();
	$_SESSION['user_id'] = $userId;
	$_SESSION['user_role'] = $role;
	session_regenerate_id(true);
}

function logoutUser(): void {
	start_secure_session();

	$_SESSION = [];

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		session_destroy();
	}
}

function format_user_response(array $user): array {
	$hasAvatar = false;
	if (array_key_exists('has_avatar', $user)) {
		$hasAvatar = (bool) $user['has_avatar'];
	} elseif (!empty($user['avatar_data'])) {
		$hasAvatar = true;
	}

	$avatarUrl = null;
	$avatarVersion = null;
	if ($hasAvatar) {
		$avatarUrl = 'api/user_avatar.php?id=' . (int)($user['id'] ?? 0);
		$versionSource = $user['avatar_updated_at'] ?? $user['updated_at'] ?? null;
		if ($versionSource) {
			$timestamp = is_numeric($versionSource) ? (int) $versionSource : strtotime((string) $versionSource);
			$avatarVersion = $timestamp !== false ? $timestamp : null;
		}
	}

	return [
		'id' => (int)($user['id'] ?? 0),
		'fullName' => $user['full_name'] ?? $user['fullName'] ?? null,
		'email' => $user['email'] ?? null,
		'username' => $user['username'] ?? null,
		'avatarUrl' => $avatarUrl,
		'avatarVersion' => $avatarVersion,
		'bio' => $user['bio'] ?? null,
		'location' => $user['location'] ?? null,
		'role' => $user['role'] ?? null,
		'createdAt' => $user['created_at'] ?? $user['createdAt'] ?? null,
		'updatedAt' => $user['updated_at'] ?? $user['updatedAt'] ?? null,
	];
}

function record_activity(int $userId, string $type, string $description, ?array $metadata = null): void {
	try {
		$pdo = get_pdo();
		$stmt = $pdo->prepare('INSERT INTO user_activity (user_id, activity_type, description, metadata) VALUES (:user_id, :type, :description, :metadata)');
		$sanitizedDescription = sanitize_string($description);
		$stmt->execute([
			':user_id' => $userId,
			':type' => sanitize_string($type),
			':description' => $sanitizedDescription,
			':metadata' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : null,
		]);
	} catch (Throwable $e) {
		error_log('record_activity failed: ' . $e->getMessage());
	}
}

function ensure_required_tables(PDO $pdo): void {
	static $ensured = false;
	if ($ensured) {
		return;
	}

	$ddl = [
		"CREATE TABLE IF NOT EXISTS users (
			id SERIAL PRIMARY KEY,
			full_name VARCHAR(100) NOT NULL,
			email VARCHAR(255) NOT NULL UNIQUE,
			username VARCHAR(50) NOT NULL UNIQUE,
			password_hash VARCHAR(255) NOT NULL,
			role VARCHAR(20) NOT NULL DEFAULT 'user',
			avatar_data BYTEA DEFAULT NULL,
			avatar_mime VARCHAR(100) DEFAULT NULL,
			avatar_updated_at TIMESTAMP DEFAULT NULL,
			bio TEXT DEFAULT NULL,
			location VARCHAR(100) DEFAULT NULL,
			updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
			created_at TIMESTAMP NOT NULL DEFAULT NOW()
		)",
		"CREATE TABLE IF NOT EXISTS competitions (
			id SERIAL PRIMARY KEY,
			name VARCHAR(255) NOT NULL,
			description TEXT,
			start_date TIMESTAMP NOT NULL,
			end_date TIMESTAMP NOT NULL,
			registration_deadline TIMESTAMP NOT NULL,
			max_participants INTEGER DEFAULT NULL,
			difficulty_level VARCHAR(50) DEFAULT 'beginner',
			prize_pool VARCHAR(255) DEFAULT NULL,
			category VARCHAR(100) NOT NULL,
			rules TEXT,
			contact_person VARCHAR(255),
			banner_data BYTEA DEFAULT NULL,
			banner_mime VARCHAR(100) DEFAULT NULL,
			banner_updated_at TIMESTAMP DEFAULT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT NOW(),
			CHECK (difficulty_level IN ('beginner', 'intermediate', 'advanced', 'expert')),
			CHECK (end_date > start_date),
			CHECK (registration_deadline <= start_date)
		)",
		"CREATE TABLE IF NOT EXISTS competition_registrations (
			id SERIAL PRIMARY KEY,
			user_id INTEGER NOT NULL,
			competition_id INTEGER NOT NULL,
			team_name VARCHAR(255) DEFAULT NULL,
			registration_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			payment_status VARCHAR(20) DEFAULT 'unpaid',
			registration_notes TEXT DEFAULT NULL,
			score INTEGER DEFAULT 0,
			rank INTEGER DEFAULT NULL,
			registered_at TIMESTAMP NOT NULL DEFAULT NOW(),
			updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
			FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
			FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
			UNIQUE(user_id, competition_id),
			CHECK (registration_status IN ('pending', 'approved', 'rejected', 'cancelled', 'waitlisted')),
			CHECK (payment_status IN ('unpaid', 'pending', 'paid', 'refunded'))
		)",
		"CREATE TABLE IF NOT EXISTS user_activity (
			id BIGSERIAL PRIMARY KEY,
			user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
			activity_type VARCHAR(100) NOT NULL,
			description TEXT NOT NULL,
			metadata JSONB DEFAULT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT NOW()
		)",
		"CREATE INDEX IF NOT EXISTS idx_user_activity_user_created ON user_activity (user_id, created_at DESC)"
	];

	foreach ($ddl as $sql) {
		$pdo->exec($sql);
	}

	$ensured = true;
}

function resize_image_binary(string $binary, string $mime, int $maxDimension = 100): array {
	$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
	if (!in_array($mime, $allowedMimes, true)) {
		throw new RuntimeException('Unsupported image type');
	}

	if (!function_exists('imagecreatefromstring')) {
		throw new RuntimeException('Image processing is not available on this server');
	}

	$image = @imagecreatefromstring($binary);
	if ($image === false) {
		throw new RuntimeException('Invalid image data');
	}

	$width = imagesx($image);
	$height = imagesy($image);
	if ($width <= 0 || $height <= 0) {
		imagedestroy($image);
		throw new RuntimeException('Invalid image dimensions');
	}

	$scale = min($maxDimension / $width, $maxDimension / $height, 1.0);
	if ($scale <= 0) {
		$scale = 1.0;
	}
	$newWidth = max(1, (int) round($width * $scale));
	$newHeight = max(1, (int) round($height * $scale));

	$target = $image;
	if ($newWidth !== $width || $newHeight !== $height) {
		$target = imagecreatetruecolor($newWidth, $newHeight);
		if ($mime !== 'image/jpeg') {
			imagealphablending($target, false);
			imagesavealpha($target, true);
		}
		imagecopyresampled($target, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
	}

	ob_start();
	$encoded = false;
	$outputMime = $mime;
	switch ($mime) {
		case 'image/jpeg':
			$encoded = imagejpeg($target, null, 80);
			break;
		case 'image/png':
			$encoded = imagepng($target, null, 7);
			break;
		case 'image/webp':
			if (function_exists('imagewebp')) {
				$encoded = imagewebp($target, null, 80);
				break;
			}
			$encoded = imagejpeg($target, null, 80);
			$outputMime = 'image/jpeg';
			break;
	}
	$data = $encoded ? ob_get_clean() : '';
	if (!$encoded) {
		ob_end_clean();
	}

	if ($target !== $image) {
		imagedestroy($target);
	}
	imagedestroy($image);

	if (!$encoded || $data === '') {
		throw new RuntimeException('Failed to encode image');
	}

	return ['data' => $data, 'mime' => $outputMime];
}

function get_app_timezone(): DateTimeZone {
	static $tz = null;
	static $initialized = false;
	if ($tz instanceof DateTimeZone) {
		if (!$initialized) {
			date_default_timezone_set($tz->getName());
			$initialized = true;
		}
		return $tz;
	}

	$tzName = getenv('APP_TIMEZONE') ?: 'Asia/Jakarta';
	try {
		$tz = new DateTimeZone($tzName);
	} catch (Throwable $e) {
		$tz = new DateTimeZone('Asia/Jakarta');
	}

	if (!$initialized) {
		date_default_timezone_set($tz->getName());
		$initialized = true;
	}

	return $tz;
}

function compute_competition_status(string $startDate, string $endDate, string $registrationDeadline): string {
	try {
		$tz = get_app_timezone();
		$now = new DateTimeImmutable('now', $tz);
		$start = new DateTimeImmutable($startDate, $tz);
		$end = new DateTimeImmutable($endDate, $tz);
		$deadline = new DateTimeImmutable($registrationDeadline, $tz);
	} catch (Throwable $e) {
		return 'upcoming';
	}

	if ($now > $end) {
		return 'completed';
	}

	if ($now >= $start && $now <= $end) {
		return 'ongoing';
	}

	if ($deadline < $now) {
		return 'registration_closed';
	}

	if ($deadline >= $now && $start > $now) {
		return 'registration_open';
	}

	return 'upcoming';
}

function check_rate_limit(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
	start_secure_session();
	$rateLimitKey = 'rate_limit_' . $key;
	$attempts = $_SESSION[$rateLimitKey] ?? [];
	$now = time();
	$attempts = array_filter($attempts, function($timestamp) use ($now, $windowSeconds) {
		return ($now - $timestamp) < $windowSeconds;
	});
	
	if (count($attempts) >= $maxAttempts) {
		return false;
	}
	
	$attempts[] = $now;
	$_SESSION[$rateLimitKey] = $attempts;
	return true;
}

function get_rate_limit_remaining(string $key): int {
	start_secure_session();
	$rateLimitKey = 'rate_limit_' . $key;
	$attempts = $_SESSION[$rateLimitKey] ?? [];
	$now = time();
	$windowSeconds = 300;
	$validAttempts = array_filter($attempts, function($timestamp) use ($now, $windowSeconds) {
		return ($now - $timestamp) < $windowSeconds;
	});
	return max(0, 5 - count($validAttempts));
}