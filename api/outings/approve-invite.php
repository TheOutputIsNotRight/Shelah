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
$candidateUserId = $data['candidate_user_id'] ?? '';
$approved = $data['approved'] ?? null;

if (!$outingId || !$candidateUserId || $approved === null) {
    jsonResponse(['error' => 'outing_id, candidate_user_id, and approved are required'], 400);
}

// Verify current user is accepted member
$stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid AND invite_status = :status');
$stmt->execute(['oid' => $outingId, 'uid' => $userId, 'status' => 'accepted']);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'You must be an accepted member to vote'], 403);
}

// Check if already voted
$stmt = $pdo->prepare('SELECT id FROM invite_approvals WHERE outing_id = :oid AND candidate_user_id = :cid AND voter_user_id = :vid');
$stmt->execute(['oid' => $outingId, 'cid' => $candidateUserId, 'vid' => $userId]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'You have already voted on this invitation'], 400);
}

try {
    $pdo->beginTransaction();

    // Record vote
    $stmt = $pdo->prepare('INSERT INTO invite_approvals (outing_id, candidate_user_id, voter_user_id, approved) VALUES (:oid, :cid, :vid, :approved)');
    $stmt->execute([
        'oid' => $outingId,
        'cid' => $candidateUserId,
        'vid' => $userId,
        'approved' => $approved ? 'true' : 'false'
    ]);

    if (!$approved) {
        // If anyone rejects, reject the invite
        $stmt = $pdo->prepare('UPDATE outing_members SET invite_approved = FALSE WHERE outing_id = :oid AND user_id = :uid');
        $stmt->execute(['oid' => $outingId, 'uid' => $candidateUserId]);
    } else {
        // Check if all accepted members have now voted yes
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM outing_members WHERE outing_id = :oid AND invite_status = :status');
        $stmt->execute(['oid' => $outingId, 'status' => 'accepted']);
        $totalAccepted = $stmt->fetch()['total'];

        $stmt = $pdo->prepare('SELECT COUNT(*) as approved FROM invite_approvals WHERE outing_id = :oid AND candidate_user_id = :cid AND approved = TRUE');
        $stmt->execute(['oid' => $outingId, 'cid' => $candidateUserId]);
        $totalApproved = $stmt->fetch()['approved'];

        if ($totalApproved >= $totalAccepted) {
            // All accepted members approved - flip invite_approved to true
            $stmt = $pdo->prepare('UPDATE outing_members SET invite_approved = TRUE WHERE outing_id = :oid AND user_id = :uid');
            $stmt->execute(['oid' => $outingId, 'uid' => $candidateUserId]);
        }
    }

    $pdo->commit();
    jsonResponse(['message' => 'Vote recorded']);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Failed to record vote'], 500);
}
