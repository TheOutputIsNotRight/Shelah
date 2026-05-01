<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();
$data = getRequestBody();
$outingId = $data['outing_id'] ?? '';

if (!$outingId) {
    jsonResponse(['error' => 'outing_id is required'], 400);
}

// Check membership and current status
$stmt = $pdo->prepare('SELECT id, invite_status, invite_approved FROM outing_members WHERE outing_id = :oid AND user_id = :uid');
$stmt->execute(['oid' => $outingId, 'uid' => $userId]);
$member = $stmt->fetch();

if (!$member) {
    jsonResponse(['error' => 'You are not invited to this outing'], 403);
}

if ($member['invite_status'] === 'accepted') {
    jsonResponse(['error' => 'You have already accepted this invite'], 400);
}

// For restricted outings, check if invite is approved
$stmt = $pdo->prepare('SELECT outing_type FROM outings WHERE id = :id');
$stmt->execute(['id' => $outingId]);
$outing = $stmt->fetch();

if ($outing['outing_type'] === 'restricted' && $member['invite_approved'] !== true && $member['invite_approved'] !== 't') {
    // Check if this is the initial invite (invited_by = creator) - those don't need approval
    $stmt2 = $pdo->prepare('SELECT creator_id FROM outings WHERE id = :id');
    $stmt2->execute(['id' => $outingId]);
    $outingData = $stmt2->fetch();

    $stmt3 = $pdo->prepare('SELECT invited_by FROM outing_members WHERE outing_id = :oid AND user_id = :uid');
    $stmt3->execute(['oid' => $outingId, 'uid' => $userId]);
    $memberData = $stmt3->fetch();

    // If invited by creator at creation time, allow acceptance
    if ($memberData['invited_by'] !== $outingData['creator_id']) {
        // Check if all accepted members have approved
        if ($member['invite_approved'] === null || $member['invite_approved'] === false || $member['invite_approved'] === 'f') {
            jsonResponse(['error' => 'Your invitation is pending approval from existing members'], 403);
        }
    }
}

$stmt = $pdo->prepare('UPDATE outing_members SET invite_status = :status WHERE outing_id = :oid AND user_id = :uid');
$stmt->execute(['status' => 'accepted', 'oid' => $outingId, 'uid' => $userId]);

jsonResponse(['message' => 'Invite accepted']);
