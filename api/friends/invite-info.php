<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$code = $_GET['code'] ?? '';

if (!$code) {
    jsonResponse(['error' => 'Invite code is required'], 400);
}

$stmt = $pdo->prepare('SELECT display_name FROM users WHERE invite_code = :code');
$stmt->execute(['code' => $code]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    jsonResponse(['error' => 'Invalid invite code'], 404);
}

jsonResponse(['display_name' => $targetUser['display_name']]);
