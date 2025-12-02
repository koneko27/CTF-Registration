<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';

ensure_http_method('GET');

$rateLimitKey = 'admin_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKey, 30, 60)) {
	json_response(429, ['error' => 'Too many requests. Please try again later.']);
}

require_authenticated_user(true);

try {
	$pdo = get_pdo();
	ensure_required_tables($pdo);
	$stmt = $pdo->query('SELECT 
			cr.id,
			cr.user_id,
			cr.competition_id,
			cr.team_name,
			cr.registration_status,
			cr.payment_status,
			cr.registration_notes,
			cr.score,
			cr.rank,
			cr.registered_at,
			cr.updated_at,
			u.full_name AS user_name,
			u.email AS user_email,
			u.username,
			c.name AS competition_name,
			c.category AS competition_category
		FROM competition_registrations cr
		JOIN users u ON cr.user_id = u.id
		JOIN competitions c ON cr.competition_id = c.id
		ORDER BY cr.registered_at DESC');
	json_response(200, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
} catch (Throwable $e) {
	error_log('get_registrations failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
