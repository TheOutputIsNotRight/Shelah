<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();

$stmt = $pdo->prepare('
    SELECT u.id, u.display_name, u.email, f.id as friendship_id, f.created_at
    FROM friendships f
    JOIN users u ON (u.id = CASE WHEN f.user_id = :uid THEN f.friend_id ELSE f.user_id END)
    WHERE (f.user_id = :uid2 OR f.friend_id = :uid3)
    AND f.status = :status
');
$stmt->execute(['uid' => $userId, 'uid2' => $userId, 'uid3' => $userId, 'status' => 'accepted']);
$friends = $stmt->fetchAll();

jsonResponse(['friends' => $friends]);
