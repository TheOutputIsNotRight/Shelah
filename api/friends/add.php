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
$email = trim($data['email'] ?? '');

if (!$email) {
    jsonResponse(['error' => 'Email is required'], 400);
}

// Find user by email
$stmt = $pdo->prepare('SELECT id, display_name FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
$friend = $stmt->fetch();

if (!$friend) {
    jsonResponse(['error' => 'No user found with that email'], 404);
}

if ($friend['id'] === $userId) {
    jsonResponse(['error' => 'You cannot add yourself as a friend'], 400);
}

// Check if friendship already exists in either direction
$stmt = $pdo->prepare('SELECT id, status FROM friendships WHERE (user_id = :uid AND friend_id = :fid) OR (user_id = :fid2 AND friend_id = :uid2)');
$stmt->execute(['uid' => $userId, 'fid' => $friend['id'], 'fid2' => $friend['id'], 'uid2' => $userId]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['status'] === 'accepted') {
        jsonResponse(['error' => 'You are already friends'], 400);
    } else {
        jsonResponse(['error' => 'A friend request already exists'], 400);
    }
}

// Create friend request
$stmt = $pdo->prepare('INSERT INTO friendships (user_id, friend_id, status) VALUES (:user_id, :friend_id, :status) RETURNING id');
$stmt->execute([
    'user_id' => $userId,
    'friend_id' => $friend['id'],
    'status' => 'pending'
]);

jsonResponse(['message' => 'Friend request sent to ' . $friend['display_name']], 201);
