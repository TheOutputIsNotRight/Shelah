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
$placeId = $data['place_id'] ?? '';

if (!$outingId || !$placeId) {
    jsonResponse(['error' => 'outing_id and place_id are required'], 400);
}

// Verify membership
$stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid AND invite_status = :status');
$stmt->execute(['oid' => $outingId, 'uid' => $userId, 'status' => 'accepted']);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'You must be an accepted member to vote'], 403);
}

// Toggle vote
$stmt = $pdo->prepare('SELECT id FROM place_votes WHERE outing_id = :oid AND place_id = :pid AND user_id = :uid');
$stmt->execute(['oid' => $outingId, 'pid' => $placeId, 'uid' => $userId]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare('DELETE FROM place_votes WHERE id = :id');
    $stmt->execute(['id' => $existing['id']]);
    $voted = false;
} else {
    $stmt = $pdo->prepare('INSERT INTO place_votes (outing_id, place_id, user_id) VALUES (:oid, :pid, :uid)');
    $stmt->execute(['oid' => $outingId, 'pid' => $placeId, 'uid' => $userId]);
    $voted = true;
}

// Get updated vote count
$stmt = $pdo->prepare('SELECT COUNT(*) as vote_count FROM place_votes WHERE outing_id = :oid AND place_id = :pid');
$stmt->execute(['oid' => $outingId, 'pid' => $placeId]);
$voteCount = (int)$stmt->fetch()['vote_count'];

jsonResponse(['voted' => $voted, 'vote_count' => $voteCount]);
