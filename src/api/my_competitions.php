<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

ensure_http_method('GET');
$user = require_authenticated_user();

try {
	$pdo = get_pdo();
	ensure_required_tables($pdo);
	$stmt = $pdo->prepare('SELECT
            cr.id,
            cr.registration_status,
            cr.registered_at,
            cr.competition_id,
            cr.payment_status,
            cr.team_name,
            c.name,
            c.description,
            c.start_date,
            c.end_date,
            c.registration_deadline,
            c.max_participants,
            c.difficulty_level,
            c.prize_pool,
            c.category,
            c.rules,
            c.contact_person,
            c.banner_updated_at,
            c.created_at AS competition_created_at,
            (CASE WHEN c.banner_data IS NOT NULL THEN 1 ELSE 0 END) AS has_banner,
            (
                SELECT COUNT(*)
                FROM competition_registrations cr2
                WHERE cr2.competition_id = c.id
                    AND cr2.registration_status IN (\'pending\', \'approved\', \'waitlisted\')
            ) AS current_participants
        FROM competition_registrations cr
        INNER JOIN competitions c ON c.id = cr.competition_id
        WHERE cr.user_id = :user_id
        ORDER BY cr.registered_at DESC');

    $stmt->execute([':user_id' => $user['id']]);

    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($registrations as &$registration) {
        $registration['competition_status'] = compute_competition_status(
            $registration['start_date'],
            $registration['end_date'],
            $registration['registration_deadline']
        );
        $registration['current_participants'] = (int) $registration['current_participants'];
        $registration['bannerUrl'] = ($registration['has_banner'] ?? 0) ? 'api/competition_banner.php?id=' . $registration['competition_id'] : null;
        $versionSource = $registration['banner_updated_at'] ?? $registration['competition_created_at'] ?? null;
        $registration['bannerVersion'] = $versionSource ? strtotime($versionSource) : null;
        unset($registration['banner_updated_at']);
        unset($registration['has_banner']);
    }

    json_response(200, [
        'registrations' => $registrations,
    ]);
} catch (Throwable $e) {
    error_log('my_competitions error: ' . $e->getMessage());
    json_response(500, ['error' => 'Server error']);
}
