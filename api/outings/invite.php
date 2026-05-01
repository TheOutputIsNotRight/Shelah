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
$inviteeId = $data['user_id'] ?? '';

if (!$outingId || !$inviteeId) {
    jsonResponse(['error' => 'outing_id and user_id are required'], 400);
}

// Verify current user is an accepted member
$stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid AND invite_status = :status');
$stmt->execute(['oid' => $outingId, 'uid' => $userId, 'status' => 'accepted']);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'You must be an accepted member to invite others'], 403);
}

// Get outing type
$stmt = $pdo->prepare('SELECT outing_type FROM outings WHERE id = :id');
$stmt->execute(['id' => $outingId]);
$outing = $stmt->fetch();

if (!$outing) {
    jsonResponse(['error' => 'Outing not found'], 404);
}

if ($outing['outing_type'] === 'closed') {
    jsonResponse(['error' => 'This outing is closed. No new members can be added.'], 400);
}

// Check if already a member
$stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid');
$stmt->execute(['oid' => $outingId, 'uid' => $inviteeId]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'This user is already a member of the outing'], 400);
}

try {
    $pdo->beginTransaction();

    if ($outing['outing_type'] === 'restricted') {
        // Add member with pending approval
        $stmt = $pdo->prepare('INSERT INTO outing_members (outing_id, user_id, invite_status, invited_by, invite_approved) VALUES (:oid, :uid, :status, :invited_by, NULL)');
        $stmt->execute([
            'oid' => $outingId,
            'uid' => $inviteeId,
            'status' => 'pending',
            'invited_by' => $userId
        ]);

        // Create approval entries for all currently accepted members (except the inviter who implicitly approves)
        $stmt = $pdo->prepare('SELECT user_id FROM outing_members WHERE outing_id = :oid AND invite_status = :status AND user_id != :inviter');
        $stmt->execute(['oid' => $outingId, 'status' => 'accepted', 'inviter' => $userId]);
        $acceptedMembers = $stmt->fetchAll();

        // The inviter auto-approves
        $stmtApproval = $pdo->prepare('INSERT INTO invite_approvals (outing_id, candidate_user_id, voter_user_id, approved) VALUES (:oid, :cid, :vid, TRUE)');
        $stmtApproval->execute(['oid' => $outingId, 'cid' => $inviteeId, 'vid' => $userId]);

        // If no other accepted members, auto-approve the invite
        if (count($acceptedMembers) === 0) {
            $stmt = $pdo->prepare('UPDATE outing_members SET invite_approved = TRUE WHERE outing_id = :oid AND user_id = :uid');
            $stmt->execute(['oid' => $outingId, 'uid' => $inviteeId]);
        }
    } else {
        // Open outing: add directly as pending invite
        $stmt = $pdo->prepare('INSERT INTO outing_members (outing_id, user_id, invite_status, invited_by) VALUES (:oid, :uid, :status, :invited_by)');
        $stmt->execute([
            'oid' => $outingId,
            'uid' => $inviteeId,
            'status' => 'pending',
            'invited_by' => $userId
        ]);
    }

    $pdo->commit();
    jsonResponse(['message' => 'Invitation sent'], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Failed to send invitation'], 500);
}
