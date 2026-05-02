<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();

// Get or generate invite code
$stmt = $pdo->prepare('SELECT invite_code FROM users WHERE id = :uid');
$stmt->execute(['uid' => $userId]);
$row = $stmt->fetch();
$code = $row['invite_code'] ?? null;

if (!$code) {
    $code = substr(str_replace('-', '', strtolower(bin2hex(random_bytes(5)))), 0, 8);
    $stmt = $pdo->prepare('UPDATE users SET invite_code = :code WHERE id = :uid');
    $stmt->execute(['code' => $code, 'uid' => $userId]);
}

jsonResponse(['invite_code' => $code]);
