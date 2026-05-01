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
        PDO::ATTR_EMULATE_PREPARES => true
    ]);
    
    // Vercel + Neon PgBouncer connection pooling can sometimes return connections 
    // that are stuck in an aborted transaction state from a previous crashed request.
    // We send a raw ROLLBACK to the server to reset the connection state unconditionally.
    try {
        @$pdo->exec('ROLLBACK');
    } catch (Exception $e) {
        // Ignore "no transaction in progress" errors
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
