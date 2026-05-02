<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();

// Friends count
$stmt = $pdo->prepare('SELECT COUNT(*) as c FROM friendships WHERE (user_id = :uid OR friend_id = :uid2) AND status = :s');
$stmt->execute(['uid' => $userId, 'uid2' => $userId, 's' => 'accepted']);
$friendsCount = (int)$stmt->fetch()['c'];

// Groups count
$stmt = $pdo->prepare('SELECT COUNT(*) as c FROM outing_members WHERE user_id = :uid');
$stmt->execute(['uid' => $userId]);
$groupsCount = (int)$stmt->fetch()['c'];

// Upcoming confirmed outings
$stmt = $pdo->prepare('
    SELECT o.id FROM outings o
    JOIN outing_members om ON om.outing_id = o.id AND om.user_id = :uid
    WHERE o.scheduled_date >= CURRENT_DATE
');
$stmt->execute(['uid' => $userId]);
$outingIds = $stmt->fetchAll();
$upcomingCount = 0;
foreach ($outingIds as $row) {
    $stmt2 = $pdo->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN requirements_submitted THEN 1 ELSE 0 END) as submitted FROM outing_members WHERE outing_id = :oid');
    $stmt2->execute(['oid' => $row['id']]);
    $r = $stmt2->fetch();
    if ((int)$r['total'] > 0 && (int)$r['submitted'] === (int)$r['total']) {
        $upcomingCount++;
    }
}

// Pending invites
$stmt = $pdo->prepare('SELECT COUNT(*) as c FROM outing_members WHERE user_id = :uid AND invite_status = :s');
$stmt->execute(['uid' => $userId, 's' => 'pending']);
$pendingInvites = (int)$stmt->fetch()['c'];

jsonResponse([
    'friends_count' => $friendsCount,
    'groups_count' => $groupsCount,
    'upcoming_outings_count' => $upcomingCount,
    'pending_invites_count' => $pendingInvites
]);
