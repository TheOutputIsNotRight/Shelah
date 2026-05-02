<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();

$stmt = $pdo->prepare('
    SELECT o.id, o.name, o.outing_type, o.scheduled_date, o.created_at, o.creator_id,
           o.description,
           om.invite_status, om.requirements_submitted,
           u.display_name as creator_name
    FROM outings o
    JOIN outing_members om ON om.outing_id = o.id AND om.user_id = :uid
    JOIN users u ON u.id = o.creator_id
    ORDER BY o.scheduled_date ASC
');
$stmt->execute(['uid' => $userId]);
$outings = $stmt->fetchAll();

// Get member counts and first 4 members for each outing
foreach ($outings as &$outing) {
    $stmt = $pdo->prepare('
        SELECT u.id, u.display_name, om.invite_status, om.requirements_submitted
        FROM outing_members om
        JOIN users u ON u.id = om.user_id
        WHERE om.outing_id = :oid
        ORDER BY om.created_at ASC
    ');
    $stmt->execute(['oid' => $outing['id']]);
    $members = $stmt->fetchAll();
    $outing['member_count'] = count($members);
    $outing['members_preview'] = array_slice($members, 0, 4);

    $all_submitted = count($members) > 0;
    foreach ($members as $m) {
        if (!$m['requirements_submitted']) {
            $all_submitted = false;
            break;
        }
    }
    $outing['all_requirements_submitted'] = $all_submitted;
}

jsonResponse(['outings' => $outings]);
