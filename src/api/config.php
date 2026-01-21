<?php

$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl === false || $databaseUrl === '') {
	throw new RuntimeException('DATABASE_URL environment variable is required.');
}

define('DATABASE_URL', $databaseUrl);

$appUrl = getenv('APP_URL');
if (!$appUrl) {
    if (getenv('ENVIRONMENT') === 'production') {
        throw new RuntimeException('APP_URL environment variable is required in production.');
    }
    $appUrl = 'http://localhost:9000';
    error_log('WARNING: Using localhost fallback for APP_URL. Set APP_URL environment variable.');
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
