<?php
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$userId = requireAuth();
$data = getRequestBody();

$outingId = $data['outing_id'] ?? '';
$homeLat = $data['home_latitude'] ?? null;
$homeLng = $data['home_longitude'] ?? null;
$maxDistance = $data['max_distance_km'] ?? 25;
$popularityPref = $data['popularity_preference'] ?? 'any';
$minRating = $data['min_rating'] ?? 1.0;
$maxPriceEgp = $data['max_price_egp'] ?? null;
$locationTypeIds = $data['location_type_ids'] ?? [];

if (!$outingId) {
    jsonResponse(['error' => 'outing_id is required'], 400);
}

// Verify membership
$stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid AND invite_status = :status');
$stmt->execute(['oid' => $outingId, 'uid' => $userId, 'status' => 'accepted']);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'You must be an accepted member'], 403);
}

try {
    $pdo->beginTransaction();

    // Upsert requirements
    $stmt = $pdo->prepare('
        INSERT INTO user_requirements (outing_id, user_id, home_latitude, home_longitude, max_distance_km, popularity_preference, min_rating, max_price_egp)
        VALUES (:oid, :uid, :lat, :lng, :dist, :pop, :rating, :budget)
        ON CONFLICT (outing_id, user_id) DO UPDATE SET
            home_latitude = :lat2, home_longitude = :lng2, max_distance_km = :dist2,
            popularity_preference = :pop2, min_rating = :rating2, max_price_egp = :budget2,
            updated_at = NOW()
        RETURNING id
    ');
    $stmt->execute([
        'oid' => $outingId, 'uid' => $userId,
        'lat' => $homeLat, 'lng' => $homeLng,
        'dist' => $maxDistance, 'pop' => $popularityPref,
        'rating' => $minRating, 'budget' => $maxPriceEgp,
        'lat2' => $homeLat, 'lng2' => $homeLng,
        'dist2' => $maxDistance, 'pop2' => $popularityPref,
        'rating2' => $minRating, 'budget2' => $maxPriceEgp
    ]);
    $req = $stmt->fetch();
    $requirementId = $req['id'];

    // Clear old location type preferences
    $stmt = $pdo->prepare('DELETE FROM requirement_location_types WHERE requirement_id = :rid');
    $stmt->execute(['rid' => $requirementId]);

    // Insert new location type preferences
    if (!empty($locationTypeIds)) {
        $stmt = $pdo->prepare('INSERT INTO requirement_location_types (requirement_id, location_type_id) VALUES (:rid, :ltid)');
        foreach ($locationTypeIds as $ltId) {
            $stmt->execute(['rid' => $requirementId, 'ltid' => $ltId]);
        }
    }

    // Mark requirements as submitted
    $stmt = $pdo->prepare('UPDATE outing_members SET requirements_submitted = TRUE WHERE outing_id = :oid AND user_id = :uid');
    $stmt->execute(['oid' => $outingId, 'uid' => $userId]);

    $pdo->commit();
    jsonResponse(['message' => 'Requirements saved']);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Failed to save requirements: ' . $e->getMessage()], 500);
}
