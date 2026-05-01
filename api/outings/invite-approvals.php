<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();
$outingId = $_GET['outing_id'] ?? '';

if (!$outingId) {
    jsonResponse(['error' => 'outing_id is required'], 400);
}

// Get pending approvals the current user needs to vote on
$stmt = $pdo->prepare('
    SELECT om.user_id as candidate_user_id, u.display_name as candidate_name
    FROM outing_members om
    JOIN users u ON u.id = om.user_id
    WHERE om.outing_id = :oid
    AND om.invite_approved IS NULL
    AND om.invite_status = \'pending\'
    AND NOT EXISTS (
        SELECT 1 FROM invite_approvals ia
        WHERE ia.outing_id = :oid2
        AND ia.candidate_user_id = om.user_id
        AND ia.voter_user_id = :uid
    )
');
$stmt->execute(['oid' => $outingId, 'oid2' => $outingId, 'uid' => $userId]);
$approvals = $stmt->fetchAll();

jsonResponse(['pending_approvals' => $approvals]);
