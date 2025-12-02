<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';

$rateLimitKey = 'admin_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!check_rate_limit($rateLimitKey, 30, 60)) {
	json_response(429, ['error' => 'Too many requests. Please try again later.']);
}

$admin = require_authenticated_user(true);
$pdo = get_pdo();
ensure_required_tables($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function payment_payload(): array {
	$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
	if (stripos($contentType, 'application/json') !== false) {
		return require_json_input();
	}

	if (!empty($_POST)) {
		return $_POST;
	}

	return require_json_input();
}

try {
	switch ($method) {
		case 'GET': {
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
					c.name AS competition_name
				FROM competition_registrations cr
				JOIN users u ON cr.user_id = u.id
				JOIN competitions c ON cr.competition_id = c.id
				WHERE cr.payment_status IN (\'pending\', \'unpaid\')
				ORDER BY cr.registered_at DESC');
			json_response(200, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
		}
		case 'POST': {
			$data = payment_payload();
			$registrationId = isset($data['registration_id']) ? (int) $data['registration_id'] : 0;
			if ($registrationId <= 0) {
				json_response(400, ['error' => 'Registration ID is required']);
			}

			$paymentStatus = sanitize_string($data['payment_status'] ?? '');
			$allowed = ['unpaid', 'pending', 'paid', 'refunded'];
			if ($paymentStatus === '' || !in_array($paymentStatus, $allowed, true)) {
				json_response(400, ['error' => 'Invalid payment status']);
			}

			$registrationStatusUpdate = null;
			if ($paymentStatus === 'paid') {
				$registrationStatusUpdate = 'approved';
			} elseif ($paymentStatus === 'refunded') {
				$registrationStatusUpdate = 'cancelled';
			}

			$pdo->beginTransaction();

			$stmt = $pdo->prepare('UPDATE competition_registrations
				SET payment_status = :payment_status,
					registration_status = COALESCE(:registration_status, registration_status),
					updated_at = NOW()
				WHERE id = :id
				RETURNING id, user_id, competition_id, team_name, registration_status, payment_status, registration_notes, score, rank, registered_at, updated_at');
			$stmt->execute([
				':payment_status' => $paymentStatus,
				':registration_status' => $registrationStatusUpdate,
				':id' => $registrationId,
			]);
			$registration = $stmt->fetch(PDO::FETCH_ASSOC);

			if (!$registration) {
				$pdo->rollBack();
				json_response(404, ['error' => 'Registration not found']);
			}

			$pdo->commit();

			record_activity((int) $admin['id'], 'admin.payment.update', 'Updated registration payment status', [
				'registrationId' => $registrationId,
				'paymentStatus' => $paymentStatus,
			]);

			json_response(200, ['success' => true, 'registration' => $registration]);
		}
		default:
			header('Allow: GET, POST');
			json_response(405, ['error' => 'Method not allowed']);
	}
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	error_log('verify_payments error: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
