<?php
// Database-backed sessions for Vercel serverless (stateless) environment
require_once __DIR__ . '/db.php';

// Auto-create sessions table on first use
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS sessions (
        token VARCHAR(128) PRIMARY KEY,
        user_id UUID REFERENCES users(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT NOW()
    )');
} catch (Exception $e) {
    // Ignore if already exists
}

// Initialize $_SESSION from cookie token
$_SESSION = [];
$token = $_COOKIE['shelah_token'] ?? null;
if ($token) {
    $stmt = $pdo->prepare('SELECT user_id FROM sessions WHERE token = :token');
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();
    if ($row) {
        $_SESSION['user_id'] = $row['user_id'];
    }
}

function createSession($userId) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO sessions (token, user_id) VALUES (:token, :uid)');
    $stmt->execute(['token' => $token, 'uid' => $userId]);
    setcookie('shelah_token', $token, [
        'expires' => time() + 86400 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => true
    ]);
    $_SESSION['user_id'] = $userId;
}

function destroySession() {
    global $pdo;
    $token = $_COOKIE['shelah_token'] ?? null;
    if ($token) {
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE token = :token');
        $stmt->execute(['token' => $token]);
    }
    setcookie('shelah_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => true
    ]);
    $_SESSION = [];
}
