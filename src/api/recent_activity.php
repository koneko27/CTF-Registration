<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('GET');
$user = require_authenticated_user();

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if ($limit < 1 || $limit > 2147483647) {
	$limit = 10;
}
$limit = min($limit, 50);

try {
	$pdo = get_pdo();
	$stmt = $pdo->prepare('SELECT id, activity_type, description, metadata, created_at
		FROM user_activity
		WHERE user_id = :user_id
		ORDER BY created_at DESC
		LIMIT :limit');
	$stmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
	$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
	$stmt->execute();
	$activities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	foreach ($activities as &$activity) {
		if (isset($activity['metadata']) && $activity['metadata'] !== null) {
			$decoded = json_decode($activity['metadata'], true);
			$activity['metadata'] = is_array($decoded) ? $decoded : null;
		}
		if (!empty($activity['created_at'])) {
			try {
				$tz = get_app_timezone();
				// Assume DB stores timestamps in the application timezone (WIB/Jakarta)
				// as configured in utils.php
				$createdAt = new DateTimeImmutable($activity['created_at'], $tz);
				$activity['created_at'] = $createdAt->format(DateTimeInterface::ATOM);
				$activity['created_at_epoch_ms'] = (int) ($createdAt->format('U')) * 1000;
			} catch (Throwable $e) {
				$activity['created_at'] = $activity['created_at'] ?? null;
				$activity['created_at_epoch_ms'] = null;
			}
		} else {
			$activity['created_at'] = null;
			$activity['created_at_epoch_ms'] = null;
		}
	}

	json_response(200, ['activities' => $activities]);
} catch (Throwable $e) {
	error_log('recent_activity fetch failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}

