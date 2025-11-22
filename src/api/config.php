<?php

$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl === false || $databaseUrl === '') {
	throw new RuntimeException('DATABASE_URL environment variable is required.');
}

define('DATABASE_URL', $databaseUrl);

// Prevent Host Header Injection by defining a fixed APP_URL
// In production, set this environment variable to your actual domain (e.g., https://ctf.koneko.local)
$appUrl = getenv('APP_URL');
if (!$appUrl) {
    // Fallback with validation
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Validate host contains only allowed characters to prevent basic injection/XSS
    if (!preg_match('/^[a-zA-Z0-9.:-]+$/', $host)) {
        $host = 'localhost';
    }
    $appUrl = $proto . '://' . $host;
}
define('APP_URL', rtrim($appUrl, '/'));

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
ini_set('session.cookie_secure', $isSecure ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
