<?php
require_once __DIR__ . '/utils.php';

ensure_http_method('POST');

if (isset($_COOKIE['remember_me'])) {
	$parts = explode(':', $_COOKIE['remember_me']);
	if (count($parts) === 2) {
		$selector = $parts[0];
		try {
			$pdo = get_pdo();
			$stmt = $pdo->prepare('DELETE FROM user_sessions WHERE selector = :selector');
			$stmt->execute([':selector' => $selector]);
		} catch (Throwable $e) {}
	}
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
	setcookie('remember_me', '', [
        'expires' => time() - 3600, 
        'path' => '/', 
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

logoutUser();

json_response(200, ['message' => 'Logged out successfully']);

