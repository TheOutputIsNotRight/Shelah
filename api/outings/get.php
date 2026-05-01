<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$outingId = $_GET['id'] ?? '';
if (!$outingId) {
    jsonResponse(['error' => 'Outing ID is required'], 400);
}

// Get outing
$stmt = $pdo->prepare('SELECT o.*, u.display_name as creator_name FROM outings o JOIN users u ON u.id = o.creator_id WHERE o.id = :id');
$stmt->execute(['id' => $outingId]);
$outing = $stmt->fetch();

if (!$outing) {
    jsonResponse(['error' => 'Outing not found'], 404);
}

$userId = $_SESSION['user_id'] ?? null;

// Access control based on outing type
if ($outing['outing_type'] === 'open') {
    // Open: anyone can see
} else {
    // Closed or Restricted: must be logged in and a member
    if (!$userId) {
        jsonResponse(['error' => 'restricted'], 403);
    }

    $stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid');
    $stmt->execute(['oid' => $outingId, 'uid' => $userId]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'restricted'], 403);
    }
}

// Get members
$stmt = $pdo->prepare('
    SELECT om.id as member_id, om.user_id, om.invite_status, om.requirements_submitted,
           om.invite_approved, om.invited_by,
           u.display_name, u.email
    FROM outing_members om
    JOIN users u ON u.id = om.user_id
    WHERE om.outing_id = :oid
    ORDER BY om.created_at ASC
');
$stmt->execute(['oid' => $outingId]);
$members = $stmt->fetchAll();

// Get current user's membership status
$currentMember = null;
if ($userId) {
    foreach ($members as $m) {
        if ($m['user_id'] === $userId) {
            $currentMember = $m;
            break;
        }
    }
}

// Get pending approval requests for restricted outings
$pendingApprovals = [];
if ($outing['outing_type'] === 'restricted' && $userId) {
    $stmt = $pdo->prepare('
        SELECT ia.id, ia.candidate_user_id, u.display_name as candidate_name
        FROM invite_approvals ia
        JOIN users u ON u.id = ia.candidate_user_id
        WHERE ia.outing_id = :oid AND ia.voter_user_id = :uid AND ia.approved IS NULL
    ');
    // Actually, approved is NOT NULL (boolean). We need to check if voter hasn't voted yet
    // Let's get candidates that the current user hasn't voted on yet
    $stmt = $pdo->prepare('
        SELECT DISTINCT om.user_id as candidate_user_id, u.display_name as candidate_name
        FROM outing_members om
        JOIN users u ON u.id = om.user_id
        WHERE om.outing_id = :oid
        AND om.invite_approved IS NULL
        AND om.invite_status = \'pending\'
        AND om.user_id != :uid
        AND NOT EXISTS (
            SELECT 1 FROM invite_approvals ia
            WHERE ia.outing_id = :oid2
            AND ia.candidate_user_id = om.user_id
            AND ia.voter_user_id = :uid2
        )
    ');
    $stmt->execute(['oid' => $outingId, 'uid' => $userId, 'oid2' => $outingId, 'uid2' => $userId]);
    $pendingApprovals = $stmt->fetchAll();
}

jsonResponse([
    'outing' => $outing,
    'members' => $members,
    'current_member' => $currentMember,
    'pending_approvals' => $pendingApprovals
]);
