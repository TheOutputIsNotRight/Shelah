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
    SELECT f.id as friendship_id, u.id as user_id, u.display_name, u.email, f.created_at
    FROM friendships f
    JOIN users u ON u.id = f.user_id
    WHERE f.friend_id = :uid AND f.status = :status
');
$stmt->execute(['uid' => $userId, 'status' => 'pending']);
$requests = $stmt->fetchAll();

jsonResponse(['requests' => $requests]);
