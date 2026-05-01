<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../helpers.php';

$key = getenv('GOOGLE_MAPS_API_KEY');

if (!$key) {
    jsonResponse(['error' => 'Maps API key not configured'], 500);
}

echo json_encode(['key' => $key]);
