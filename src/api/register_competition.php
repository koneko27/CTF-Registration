<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('POST');

$user = require_authenticated_user();

$rateLimitKey = 'register_comp_' . $user['id'];
if (!check_rate_limit($rateLimitKey, 5, 60)) {
	json_response(429, ['error' => 'Too many registration attempts. Please try again later.']);
}

$input = require_json_input();

$competitionId = isset($input['competition_id']) ? (int) $input['competition_id'] : 0;
if ($competitionId <= 0) {
	json_response(400, ['error' => 'Competition ID is required']);
}

$teamName = array_key_exists('team_name', $input) ? sanitize_string($input['team_name']) : null;
$notesRaw = array_key_exists('registration_notes', $input) ? sanitize_string($input['registration_notes']) : null;

if ($teamName === null || $teamName === '') {
	json_response(400, ['error' => 'Team name is required']);
}

if ($teamName !== null && $teamName !== '' && strlen($teamName) > 255) {
	json_response(400, ['error' => 'Team name must be 255 characters or fewer']);
}

if ($notesRaw !== null && strlen($notesRaw) > 1000) {
	json_response(400, ['error' => 'Registration notes must be 1000 characters or fewer']);
}

$pdo = get_pdo();
ensure_required_tables($pdo);

try {
	$pdo->beginTransaction();

	$competitionStmt = $pdo->prepare('SELECT id, name, start_date, end_date, registration_deadline, max_participants FROM competitions WHERE id = :id FOR UPDATE');
	$competitionStmt->execute([':id' => $competitionId]);
	$competition = $competitionStmt->fetch(PDO::FETCH_ASSOC);

	if (!$competition) {
		$pdo->rollBack();
		json_response(404, ['error' => 'Competition not found']);
	}

	$status = compute_competition_status(
		$competition['start_date'],
		$competition['end_date'],
		$competition['registration_deadline']
	);

	if (!in_array($status, ['upcoming', 'registration_open'], true)) {
		$pdo->rollBack();
		json_response(400, ['error' => 'Registration is not open for this competition']);
	}

	try {
		$deadlineDate = new DateTimeImmutable($competition['registration_deadline'], get_app_timezone());
	} catch (Throwable $e) {
		$pdo->rollBack();
		json_response(400, ['error' => 'Invalid registration deadline']);
	}

	if ($deadlineDate < new DateTimeImmutable('now', get_app_timezone())) {
		$pdo->rollBack();
		json_response(400, ['error' => 'Registration deadline has passed']);
	}

	$maxParticipants = $competition['max_participants'] !== null ? (int) $competition['max_participants'] : null;
	if ($maxParticipants !== null) {
		$countStmt = $pdo->prepare('SELECT COUNT(*) FROM competition_registrations WHERE competition_id = :competition_id AND registration_status IN (\'pending\', \'approved\', \'waitlisted\') FOR UPDATE');
		$countStmt->execute([':competition_id' => $competitionId]);
		$currentParticipants = (int) $countStmt->fetchColumn();
		if ($currentParticipants >= $maxParticipants) {
			$pdo->rollBack();
			json_response(400, ['error' => 'Competition has reached maximum participants']);
		}
	}

	$existingStmt = $pdo->prepare('SELECT id FROM competition_registrations WHERE user_id = :user_id AND competition_id = :competition_id FOR UPDATE');
	$existingStmt->execute([
		':user_id' => $user['id'],
		':competition_id' => $competitionId,
	]);
	if ($existingStmt->fetch()) {
		$pdo->rollBack();
		json_response(409, ['error' => 'You are already registered for this competition']);
	}

	if ($teamName !== null && $teamName !== '') {
		$teamCheckStmt = $pdo->prepare('SELECT id FROM competition_registrations WHERE competition_id = :competition_id AND team_name = :team_name FOR UPDATE');
		$teamCheckStmt->execute([
			':competition_id' => $competitionId,
			':team_name' => $teamName
		]);
		if ($teamCheckStmt->fetch()) {
			$pdo->rollBack();
			json_response(409, ['error' => 'Team name already taken for this competition. Please choose another name.']);
		}
	}

	$insertStmt = $pdo->prepare('INSERT INTO competition_registrations
		(user_id, competition_id, team_name, registration_notes, registration_status, payment_status, updated_at)
		VALUES (:user_id, :competition_id, :team_name, :registration_notes, :registration_status, :payment_status, NOW())
		RETURNING id, user_id, competition_id, team_name, registration_status, payment_status, registration_notes, score, rank, registered_at, updated_at');
	$insertStmt->execute([
		':user_id' => $user['id'],
		':competition_id' => $competitionId,
		':team_name' => $teamName === '' ? null : $teamName,
		':registration_notes' => $notesRaw === '' ? null : $notesRaw,
		':registration_status' => 'pending',
		':payment_status' => 'unpaid',
	]);
	$registration = $insertStmt->fetch(PDO::FETCH_ASSOC);

	$pdo->commit();

	record_activity((int) $user['id'], 'competition.register', 'Registered for competition', [
		'competitionId' => $competitionId,
		'competitionName' => $competition['name'] ?? null,
	]);

	json_response(201, [
		'success' => true,
		'message' => 'Successfully registered for competition',
		'registration' => $registration,
	]);
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	error_log('register_competition failed: ' . $e->getMessage());
	json_response(500, ['error' => 'Server error']);
}
