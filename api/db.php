<?php
// Database connection helper for Neon PostgreSQL
$dsn = getenv('DATABASE_URL');
if (!$dsn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection not configured']);
    exit;
}

try {
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
