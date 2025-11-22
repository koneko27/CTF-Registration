<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../utils.php';

$admin = require_authenticated_user(true);
$pdo = get_pdo();
ensure_required_tables($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function read_payload(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos((string) $contentType, 'application/json') !== false) {
        return require_json_input();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return $_GET;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    return require_json_input();
}

function normalize_datetime(?string $value, string $field, bool $required = false): ?string {
    $value = sanitize_string($value ?? '');
    if ($value === '') {
        if ($required) {
            json_response(400, ['error' => "Field '$field' is required"]);
        }
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        json_response(400, ['error' => "Field '$field' must be a valid date"]);
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function assert_registration_deadline_bounds(string $deadline, string $startDate, string $endDate): void {
    $deadlineTs = strtotime($deadline);
    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);

    if ($deadlineTs === false || $startTs === false || $endTs === false) {
        json_response(400, ['error' => 'Invalid date values supplied.']);
    }

    if ($deadlineTs > $endTs) {
        json_response(400, ['error' => 'Registration deadline cannot be after the competition end date.']);
    }

    if ($deadlineTs > $startTs) {
        json_response(400, ['error' => 'Registration deadline cannot be after the competition start date.']);
    }
}

try {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query('SELECT 
                    c.id,
                    c.name,
                    c.description,
                    c.start_date,
                    c.end_date,
                    c.registration_deadline,
                    c.max_participants,
                    (
                        SELECT COUNT(*)
                        FROM competition_registrations cr
                        WHERE cr.competition_id = c.id
                            AND cr.registration_status IN (\'pending\', \'approved\', \'waitlisted\')
                    ) AS current_participants,
                    c.difficulty_level,
                    c.prize_pool,
					c.category,
					c.rules,
					c.contact_person,
					c.banner_updated_at,
                    (CASE WHEN c.banner_data IS NOT NULL THEN 1 ELSE 0 END) AS has_banner,
                    c.created_at
                FROM competitions c
                ORDER BY c.created_at DESC');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$competition) {
                $competition['status'] = compute_competition_status(
                    $competition['start_date'],
                    $competition['end_date'],
                    $competition['registration_deadline']
                );
                $competition['bannerUrl'] = ($competition['has_banner'] ?? 0) ? 'api/competition_banner.php?id=' . $competition['id'] : null;
                $versionSource = $competition['banner_updated_at'] ?? $competition['created_at'] ?? null;
                $competition['bannerVersion'] = $versionSource ? strtotime($versionSource) : null;
                unset($competition['has_banner'], $competition['banner_updated_at']);
            }
            json_response(200, $rows);
            break;

        case 'POST':
            $data = read_payload();

            $name = sanitize_string($data['name'] ?? '');
            $category = sanitize_string($data['category'] ?? '');
            $description = sanitize_string($data['description'] ?? '');
            $prizePool = sanitize_string($data['prize_pool'] ?? '');
            $rules = sanitize_string($data['rules'] ?? '');
            $contact = sanitize_string($data['contact_person'] ?? '');

            if ($name === '') {
                json_response(400, ['error' => "Field 'name' is required"]);
            }
            if (strlen($name) > 255) {
                json_response(400, ['error' => "Competition name must be 255 characters or less"]);
            }
            if ($category === '') {
                json_response(400, ['error' => "Field 'category' is required"]);
            }
            if (strlen($category) > 100) {
                json_response(400, ['error' => "Category must be 100 characters or less"]);
            }
            if (strlen($description) > 10000) {
                json_response(400, ['error' => "Description must be 10000 characters or less"]);
            }
            if (strlen($rules) > 10000) {
                json_response(400, ['error' => "Rules must be 10000 characters or less"]);
            }
            if (strlen($prizePool) > 255) {
                json_response(400, ['error' => "Prize pool must be 255 characters or less"]);
            }
            if (strlen($contact) > 255) {
                json_response(400, ['error' => "Contact person must be 255 characters or less"]);
            }

            $startDate = normalize_datetime($data['start_date'] ?? null, 'start_date', true);
            $endDate = normalize_datetime($data['end_date'] ?? null, 'end_date', true);
            $deadline = normalize_datetime($data['registration_deadline'] ?? null, 'registration_deadline', true);

            if ($startDate && $endDate && strtotime($endDate) <= strtotime($startDate)) {
                json_response(400, ['error' => 'End date must be after start date']);
            }

            $maxParticipants = $data['max_participants'] ?? null;
            if ($maxParticipants !== null && $maxParticipants !== '') {
                if (!ctype_digit((string) $maxParticipants) || (int) $maxParticipants < 1) {
                    json_response(400, ['error' => 'Max participants must be a positive integer']);
                }
                $maxParticipants = (int) $maxParticipants;
            } else {
                $maxParticipants = null;
            }

            $difficulty = $data['difficulty_level'] ?? 'beginner';
            $allowedDifficulties = ['beginner', 'intermediate', 'advanced', 'expert'];
            if (!in_array($difficulty, $allowedDifficulties, true)) {
                json_response(400, ['error' => 'Invalid difficulty level']);
            }

            assert_registration_deadline_bounds($deadline, $startDate, $endDate);

            $bannerDataBase64 = $data['bannerData'] ?? null;
            if (!is_string($bannerDataBase64) || $bannerDataBase64 === '') {
                json_response(400, ['error' => 'Banner image is required.']);
            }
            $bannerMime = sanitize_string($data['bannerMime'] ?? '') ?: null;
            $bannerBinary = base64_decode($bannerDataBase64, true);
            if ($bannerBinary === false) {
                json_response(400, ['error' => 'Invalid banner data']);
            }
            if (strlen($bannerBinary) > 5 * 1024 * 1024) {
                json_response(400, ['error' => 'Banner image must be smaller than 5MB']);
            }
            $imageInfo = @getimagesizefromstring($bannerBinary);
            if ($imageInfo === false) {
                json_response(400, ['error' => 'Invalid banner image']);
            }
            $detectedMime = $imageInfo['mime'] ?? null;
            if ($detectedMime) {
                $bannerMime = $detectedMime;
            }
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if ($bannerMime && !in_array($bannerMime, $allowedMimes, true)) {
                json_response(400, ['error' => 'Unsupported banner image type']);
            }
            if (!$bannerMime) {
                $bannerMime = 'image/jpeg';
            }
            try {
                $processed = resize_image_binary($bannerBinary, $bannerMime, 1200);
            } catch (RuntimeException $e) {
                json_response(400, ['error' => $e->getMessage()]);
            }
            $bannerBinary = $processed['data'];
            $bannerMime = $processed['mime'];
            $stmt = $pdo->prepare('INSERT INTO competitions
                (name, description, start_date, end_date, registration_deadline, max_participants, difficulty_level, prize_pool, category, rules, contact_person, banner_data, banner_mime, banner_updated_at, created_at)
                VALUES (:name, :description, :start_date, :end_date, :registration_deadline, :max_participants, :difficulty_level, :prize_pool, :category, :rules, :contact_person, :banner_data, :banner_mime, :banner_updated_at, NOW())
                RETURNING id, name, description, start_date, end_date, registration_deadline, max_participants, difficulty_level, prize_pool, category, rules, contact_person, banner_updated_at, created_at, (CASE WHEN banner_data IS NOT NULL THEN 1 ELSE 0 END) AS has_banner');
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':description', $description !== '' ? $description : null, PDO::PARAM_STR);
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
            $stmt->bindValue(':registration_deadline', $deadline);
            $stmt->bindValue(':max_participants', $maxParticipants, $maxParticipants === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':difficulty_level', $difficulty);
            $stmt->bindValue(':prize_pool', $prizePool !== '' ? $prizePool : null, PDO::PARAM_STR);
            $stmt->bindValue(':category', $category);
            $stmt->bindValue(':rules', $rules !== '' ? $rules : null, PDO::PARAM_STR);
            $stmt->bindValue(':contact_person', $contact !== '' ? $contact : null, PDO::PARAM_STR);
            $stmt->bindValue(':banner_data', $bannerBinary, PDO::PARAM_LOB);
            $stmt->bindValue(':banner_mime', $bannerMime, PDO::PARAM_STR);
            $bannerUpdatedAt = date('Y-m-d H:i:s');
            $stmt->bindValue(':banner_updated_at', $bannerUpdatedAt, PDO::PARAM_STR);
            $stmt->execute();

            $competition = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($competition) {
                $competition['status'] = compute_competition_status(
                    $competition['start_date'],
                    $competition['end_date'],
                    $competition['registration_deadline']
                );
                $competition['bannerUrl'] = ($competition['has_banner'] ?? 0) ? 'api/competition_banner.php?id=' . $competition['id'] : null;
                $versionSource = $competition['banner_updated_at'] ?? $competition['created_at'] ?? null;
                $competition['bannerVersion'] = $versionSource ? strtotime($versionSource) : null;
                unset($competition['has_banner']);
                unset($competition['banner_updated_at']);
            }

            record_activity((int) $admin['id'], 'admin.competition.create', 'Created competition', ['competitionId' => $competition['id'] ?? null]);

            json_response(201, [
                'success' => true,
                'competition' => $competition,
                'message' => 'Competition created successfully',
            ]);
            break;

        case 'PUT':
            $data = read_payload();
            $id = isset($data['id']) ? (int) $data['id'] : 0;
            if ($id <= 0) {
                json_response(400, ['error' => 'Competition ID is required']);
            }

            $currentStmt = $pdo->prepare('SELECT start_date, end_date, registration_deadline FROM competitions WHERE id = :id LIMIT 1');
            $currentStmt->execute([':id' => $id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                json_response(404, ['error' => 'Competition not found']);
            }

            $fields = [];
            $params = [':id' => $id];

            if (array_key_exists('name', $data)) {
                $name = sanitize_string($data['name']);
                if ($name === '') {
                    json_response(400, ['error' => "Field 'name' cannot be empty"]);
                }
                if (strlen($name) > 255) {
                    json_response(400, ['error' => "Competition name must be 255 characters or less"]);
                }
                $fields[] = 'name = :name';
                $params[':name'] = $name;
            }

            if (array_key_exists('description', $data)) {
                $description = sanitize_string($data['description']);
                if (strlen($description) > 10000) {
                    json_response(400, ['error' => "Description must be 10000 characters or less"]);
                }
                $fields[] = 'description = :description';
                $params[':description'] = $description !== '' ? $description : null;
            }

            if (array_key_exists('max_participants', $data)) {
                $maxParticipants = $data['max_participants'];
                if ($maxParticipants === null || $maxParticipants === '') {
                    $params[':max_participants'] = null;
                } elseif (ctype_digit((string) $maxParticipants) && (int) $maxParticipants > 0) {
                    $params[':max_participants'] = (int) $maxParticipants;
                } else {
                    json_response(400, ['error' => 'Max participants must be a positive integer']);
                }
                $fields[] = 'max_participants = :max_participants';
            }

            if (array_key_exists('difficulty_level', $data)) {
                $difficulty = $data['difficulty_level'];
                $allowedDifficulties = ['beginner', 'intermediate', 'advanced', 'expert'];
                if (!in_array($difficulty, $allowedDifficulties, true)) {
                    json_response(400, ['error' => 'Invalid difficulty level']);
                }
                $params[':difficulty_level'] = $difficulty;
                $fields[] = 'difficulty_level = :difficulty_level';
            }

            if (array_key_exists('prize_pool', $data)) {
                $prize = sanitize_string($data['prize_pool']);
                if (strlen($prize) > 255) {
                    json_response(400, ['error' => "Prize pool must be 255 characters or less"]);
                }
                $params[':prize_pool'] = $prize !== '' ? $prize : null;
                $fields[] = 'prize_pool = :prize_pool';
            }

            if (array_key_exists('category', $data)) {
                $category = sanitize_string($data['category']);
                if ($category === '') {
                    json_response(400, ['error' => "Field 'category' cannot be empty"]);
                }
                if (strlen($category) > 100) {
                    json_response(400, ['error' => "Category must be 100 characters or less"]);
                }
                $params[':category'] = $category;
                $fields[] = 'category = :category';
            }

            if (array_key_exists('rules', $data)) {
                $rules = sanitize_string($data['rules']);
                if (strlen($rules) > 10000) {
                    json_response(400, ['error' => "Rules must be 10000 characters or less"]);
                }
                $params[':rules'] = $rules !== '' ? $rules : null;
                $fields[] = 'rules = :rules';
            }

            if (array_key_exists('contact_person', $data)) {
                $contact = sanitize_string($data['contact_person']);
                if (strlen($contact) > 255) {
                    json_response(400, ['error' => "Contact person must be 255 characters or less"]);
                }
                $params[':contact_person'] = $contact !== '' ? $contact : null;
                $fields[] = 'contact_person = :contact_person';
            }

            $startDate = array_key_exists('start_date', $data)
                ? normalize_datetime($data['start_date'], 'start_date', true)
                : $current['start_date'];
            $endDate = array_key_exists('end_date', $data)
                ? normalize_datetime($data['end_date'], 'end_date', true)
                : $current['end_date'];
            $registrationDeadline = array_key_exists('registration_deadline', $data)
                ? normalize_datetime($data['registration_deadline'], 'registration_deadline', true)
                : $current['registration_deadline'];

            if (!$startDate || !$endDate || !$registrationDeadline) {
                json_response(400, ['error' => 'Start date, end date, and registration deadline are required.']);
            }

            if (strtotime($endDate) <= strtotime($startDate)) {
                json_response(400, ['error' => 'End date must be after start date']);
            }

            assert_registration_deadline_bounds($registrationDeadline, $startDate, $endDate);

            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
            $params[':registration_deadline'] = $registrationDeadline;
            $fields[] = 'start_date = :start_date';
            $fields[] = 'end_date = :end_date';
            $fields[] = 'registration_deadline = :registration_deadline';

            $bannerDataBase64 = $data['bannerData'] ?? null;
            if (is_string($bannerDataBase64) && $bannerDataBase64 !== '') {
                $bannerBinary = base64_decode($bannerDataBase64, true);
                if ($bannerBinary === false) {
                    json_response(400, ['error' => 'Invalid banner data']);
                }
                if (strlen($bannerBinary) > 5 * 1024 * 1024) {
                    json_response(400, ['error' => 'Banner image must be smaller than 5MB']);
                }
                $imageInfo = @getimagesizefromstring($bannerBinary);
                if ($imageInfo === false) {
                    json_response(400, ['error' => 'Invalid banner image']);
                }
                $bannerMime = $imageInfo['mime'] ?? sanitize_string($data['bannerMime'] ?? '') ?: 'application/octet-stream';
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                if ($bannerMime && !in_array($bannerMime, $allowedMimes, true)) {
                    json_response(400, ['error' => 'Unsupported banner image type']);
                }
                if (!$bannerMime) {
                    $bannerMime = 'image/jpeg';
                }
                try {
                    $processed = resize_image_binary($bannerBinary, $bannerMime, 30);
                } catch (RuntimeException $e) {
                    json_response(400, ['error' => $e->getMessage()]);
                }
                $bannerBinary = $processed['data'];
                $bannerMime = $processed['mime'];
                $fields[] = 'banner_data = :banner_data';
                $fields[] = 'banner_mime = :banner_mime';
                $fields[] = 'banner_updated_at = NOW()';
                $params[':banner_data'] = $bannerBinary;
                $params[':banner_mime'] = $bannerMime;
            }

            if (empty($fields)) {
                json_response(400, ['error' => 'No fields to update']);
            }

            $allowedFields = ['name', 'description', 'start_date', 'end_date', 'registration_deadline', 'max_participants', 'difficulty_level', 'prize_pool', 'category', 'rules', 'contact_person', 'banner_data', 'banner_mime', 'banner_updated_at'];
            $sanitizedFields = [];
            foreach ($fields as $field) {
                $fieldName = explode(' =', $field)[0] ?? '';
                if (in_array(trim($fieldName), $allowedFields, true)) {
                    $sanitizedFields[] = $field;
                }
            }
            
            if (empty($sanitizedFields)) {
                json_response(400, ['error' => 'No valid fields to update']);
            }

            $sql = 'UPDATE competitions SET ' . implode(', ', $sanitizedFields) . ' WHERE id = :id RETURNING id, name, description, start_date, end_date, registration_deadline, max_participants, difficulty_level, prize_pool, category, rules, contact_person, banner_updated_at, created_at, (CASE WHEN banner_data IS NOT NULL THEN 1 ELSE 0 END) AS has_banner';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                if ($key === ':banner_data' && isset($params[':banner_data'])) {
                    $stmt->bindValue($key, $value, PDO::PARAM_LOB);
                } elseif ($value === null) {
                    $stmt->bindValue($key, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            $stmt->execute();
            $competition = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$competition) {
                json_response(404, ['error' => 'Competition not found']);
            }

            $competition['status'] = compute_competition_status(
                $competition['start_date'],
                $competition['end_date'],
                $competition['registration_deadline']
            );
            $competition['bannerUrl'] = ($competition['has_banner'] ?? 0) ? 'api/competition_banner.php?id=' . $competition['id'] : null;
            $versionSource = $competition['banner_updated_at'] ?? $competition['created_at'] ?? null;
            $competition['bannerVersion'] = $versionSource ? strtotime($versionSource) : null;
            unset($competition['has_banner']);
            unset($competition['banner_updated_at']);

            record_activity((int) $admin['id'], 'admin.competition.update', 'Updated competition', ['competitionId' => $competition['id'] ?? null]);

            json_response(200, ['success' => true, 'competition' => $competition]);
            break;

        case 'DELETE':
            $data = read_payload();
            $id = isset($data['id']) ? (int) $data['id'] : 0;
            if ($id <= 0) {
                json_response(400, ['error' => 'Competition ID is required']);
            }

            $stmt = $pdo->prepare('DELETE FROM competitions WHERE id = :id RETURNING id');
            $stmt->execute([':id' => $id]);
            $deleted = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$deleted) {
                json_response(404, ['error' => 'Competition not found']);
            }

            record_activity((int) $admin['id'], 'admin.competition.delete', 'Deleted competition', ['competitionId' => $id]);

            json_response(200, ['success' => true, 'message' => 'Competition deleted']);
            break;

        default:
            header('Allow: GET, POST, PUT, DELETE');
            json_response(405, ['error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    error_log('manage_competitions error: ' . $e->getMessage());
    json_response(500, ['error' => 'Server error']);
}
