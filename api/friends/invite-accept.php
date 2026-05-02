<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();
$body = getRequestBody();
$code = $body['code'] ?? '';

if (!$code) {
    jsonResponse(['error' => 'Invite code is required'], 400);
}

// Find user by invite code
$stmt = $pdo->prepare('SELECT id, display_name FROM users WHERE invite_code = :code');
$stmt->execute(['code' => $code]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    jsonResponse(['error' => 'Invalid invite code'], 404);
}

if ($targetUser['id'] === $userId) {
    jsonResponse(['error' => 'You cannot add yourself'], 400);
}

// Check existing friendship
$stmt = $pdo->prepare('SELECT id FROM friendships WHERE (user_id = :uid AND friend_id = :fid) OR (user_id = :fid2 AND friend_id = :uid2)');
$stmt->execute(['uid' => $userId, 'fid' => $targetUser['id'], 'fid2' => $targetUser['id'], 'uid2' => $userId]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Friend request already exists or you are already friends'], 400);
}

// Create friendship request
$stmt = $pdo->prepare('INSERT INTO friendships (user_id, friend_id, status) VALUES (:uid, :fid, :status)');
$stmt->execute(['uid' => $userId, 'fid' => $targetUser['id'], 'status' => 'pending']);

jsonResponse(['message' => 'Friend request sent to ' . $targetUser['display_name'] . '!']);
