<?php
require_once __DIR__ . '/utils.php';

// Manually check HTTP method without CSRF - this is a read-only public endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(405, ['error' => 'Method Not Allowed']);
}

$input = require_json_input();
$password = (string) ($input['password'] ?? '');

// Rate limit password check to prevent abuse
$rateLimitKey = 'pwd_check_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKey, 60, 60)) { // 60 checks per minute
    json_response(429, ['error' => 'Too many requests.', 'score' => 0]);
}

if ($password === '') {
    json_response(400, ['error' => 'Password is required', 'score' => 0]);
}

$score = 0;
$feedback = [];

// Length Check
if (strlen($password) >= 12) $score++;
if (strlen($password) >= 16) $score++;

// Complexity Checks
if (preg_match('/[A-Z]/', $password)) $score++;
if (preg_match('/[a-z]/', $password)) $score++;
if (preg_match('/[0-9]/', $password)) $score++;
if (preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?~`]/', $password)) $score++;

// Normalize score to 0-100 scale roughly based on 6 criteria
// Max score is 6 points -> 100%
// If score <= 2 -> Weak
// If score <= 4 -> Fair
// If score <= 5 -> Good
// If score >= 6 -> Strong

$strengthLabel = 'Weak';
$strengthColor = '#dc3545';
$percentage = 0;

if ($score <= 2) {
    $percentage = 25;
    $strengthLabel = 'Weak';
    $strengthColor = '#dc3545';
} elseif ($score <= 4) {
    $percentage = 50;
    $strengthLabel = 'Fair';
    $strengthColor = '#ffc107';
} elseif ($score <= 5) {
    $percentage = 75;
    $strengthLabel = 'Good';
    $strengthColor = '#17a2b8';
} else {
    $percentage = 100;
    $strengthLabel = 'Strong';
    $strengthColor = '#28a745';
}

json_response(200, [
    'score' => $score,
    'percentage' => $percentage,
    'label' => $strengthLabel,
    'color' => $strengthColor,
    'feedback' => $feedback
]);

