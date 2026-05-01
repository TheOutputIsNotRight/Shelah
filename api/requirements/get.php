<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();
$outingId = $_GET['outing_id'] ?? '';

if (!$outingId) {
    jsonResponse(['error' => 'outing_id is required'], 400);
}

// Get requirements
$stmt = $pdo->prepare('SELECT * FROM user_requirements WHERE outing_id = :oid AND user_id = :uid');
$stmt->execute(['oid' => $outingId, 'uid' => $userId]);
$requirements = $stmt->fetch();

$locationTypeIds = [];
if ($requirements) {
    $stmt = $pdo->prepare('SELECT location_type_id FROM requirement_location_types WHERE requirement_id = :rid');
    $stmt->execute(['rid' => $requirements['id']]);
    $locationTypeIds = array_column($stmt->fetchAll(), 'location_type_id');
}

// Get all location types for the form
$stmt = $pdo->prepare('SELECT * FROM location_types ORDER BY name');
$stmt->execute();
$locationTypes = $stmt->fetchAll();

jsonResponse([
    'requirements' => $requirements,
    'selected_location_type_ids' => $locationTypeIds,
    'location_types' => $locationTypes
]);
