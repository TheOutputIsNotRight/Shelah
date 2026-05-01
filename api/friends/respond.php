<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();
$data = getRequestBody();
$friendshipId = $data['friendship_id'] ?? '';
$action = $data['action'] ?? '';

if (!$friendshipId || !in_array($action, ['accept', 'decline'])) {
    jsonResponse(['error' => 'friendship_id and action (accept/decline) are required'], 400);
}

// Verify this is a pending request TO the current user
$stmt = $pdo->prepare('SELECT id, user_id, friend_id FROM friendships WHERE id = :id AND friend_id = :friend_id AND status = :status');
$stmt->execute(['id' => $friendshipId, 'friend_id' => $userId, 'status' => 'pending']);
$friendship = $stmt->fetch();

if (!$friendship) {
    jsonResponse(['error' => 'Friend request not found'], 404);
}

if ($action === 'accept') {
    $stmt = $pdo->prepare('UPDATE friendships SET status = :status WHERE id = :id');
    $stmt->execute(['status' => 'accepted', 'id' => $friendshipId]);
    jsonResponse(['message' => 'Friend request accepted']);
} else {
    $stmt = $pdo->prepare('DELETE FROM friendships WHERE id = :id');
    $stmt->execute(['id' => $friendshipId]);
    jsonResponse(['message' => 'Friend request declined']);
}
