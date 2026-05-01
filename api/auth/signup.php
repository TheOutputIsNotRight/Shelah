<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = getRequestBody();
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$displayName = trim($data['display_name'] ?? '');

if (!$email || !$password || !$displayName) {
    jsonResponse(['error' => 'Email, password, and display name are required'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Invalid email format'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['error' => 'Password must be at least 6 characters'], 400);
}

// Check if email exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Email already registered'], 409);
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (:email, :password_hash, :display_name) RETURNING id, email, display_name, created_at');
$stmt->execute([
    'email' => $email,
    'password_hash' => $passwordHash,
    'display_name' => $displayName
]);

$user = $stmt->fetch();
$_SESSION['user_id'] = $user['id'];

jsonResponse(['user' => $user], 201);
