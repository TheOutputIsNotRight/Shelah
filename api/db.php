<?php
// Database connection helper for Neon PostgreSQL
$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection not configured']);
    exit;
}

$parsedUrl = parse_url($dbUrl);
$host = $parsedUrl['host'] ?? '';
$port = $parsedUrl['port'] ?? 5432;
$user = $parsedUrl['user'] ?? '';
$pass = $parsedUrl['pass'] ?? '';
$db = ltrim($parsedUrl['path'] ?? '', '/');

$dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
