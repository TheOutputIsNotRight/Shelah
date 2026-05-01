<?php
session_start();
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

// Verify membership
$stmt = $pdo->prepare('SELECT id FROM outing_members WHERE outing_id = :oid AND user_id = :uid AND invite_status = :status');
$stmt->execute(['oid' => $outingId, 'uid' => $userId, 'status' => 'accepted']);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'You must be an accepted member'], 403);
}

// Fetch all accepted members' requirements
$stmt = $pdo->prepare('
    SELECT ur.*, u.display_name
    FROM user_requirements ur
    JOIN outing_members om ON om.outing_id = ur.outing_id AND om.user_id = ur.user_id
    JOIN users u ON u.id = ur.user_id
    WHERE ur.outing_id = :oid AND om.invite_status = :status
');
$stmt->execute(['oid' => $outingId, 'status' => 'accepted']);
$allRequirements = $stmt->fetchAll();

// Get total accepted members count
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM outing_members WHERE outing_id = :oid AND invite_status = :status');
$stmt->execute(['oid' => $outingId, 'status' => 'accepted']);
$totalMembers = $stmt->fetch()['total'];

$membersWithRequirements = count($allRequirements);

// Get location type preferences for each requirement
$requirementLocationTypes = [];
foreach ($allRequirements as $req) {
    $stmt = $pdo->prepare('SELECT location_type_id FROM requirement_location_types WHERE requirement_id = :rid');
    $stmt->execute(['rid' => $req['id']]);
    $requirementLocationTypes[$req['user_id']] = array_column($stmt->fetchAll(), 'location_type_id');
}

// Build union of all acceptable location types
$acceptableLocationTypes = [];
foreach ($requirementLocationTypes as $types) {
    $acceptableLocationTypes = array_merge($acceptableLocationTypes, $types);
}
$acceptableLocationTypes = array_unique($acceptableLocationTypes);

// Fetch all places with their location type info
$stmt = $pdo->prepare('
    SELECT p.*, lt.name as location_type_name, lt.icon as location_type_icon
    FROM places p
    LEFT JOIN location_types lt ON lt.id = p.location_type_id
');
$stmt->execute();
$allPlaces = $stmt->fetchAll();

// Get current user's requirements for distance display
$currentUserReq = null;
foreach ($allRequirements as $req) {
    if ($req['user_id'] === $userId) {
        $currentUserReq = $req;
        break;
    }
}

// Filter places
$matchingPlaces = [];

foreach ($allPlaces as $place) {
    $passes = true;

    foreach ($allRequirements as $req) {
        // Distance check
        if ($req['home_latitude'] && $req['home_longitude'] && $place['latitude'] && $place['longitude']) {
            $distance = haversineDistance(
                (float)$req['home_latitude'], (float)$req['home_longitude'],
                (float)$place['latitude'], (float)$place['longitude']
            );
            if ($req['max_distance_km'] && $distance > (int)$req['max_distance_km']) {
                $passes = false;
                break;
            }
        }

        // Popularity check
        if ($req['popularity_preference'] && $req['popularity_preference'] !== 'any') {
            if ($place['popularity'] !== $req['popularity_preference']) {
                $passes = false;
                break;
            }
        }

        // Rating check
        if ($req['min_rating'] && $place['rating']) {
            if ((float)$place['rating'] < (float)$req['min_rating']) {
                $passes = false;
                break;
            }
        }

        // Budget check
        if ($req['budget_tier'] && $req['budget_tier'] !== 'any') {
            if (budgetTierLevel($place['budget_tier']) > budgetTierLevel($req['budget_tier'])) {
                $passes = false;
                break;
            }
        }
    }

    // Location type check: place type must be in union of all users' accepted types
    // If no one specified any location types, skip this check
    if ($passes && !empty($acceptableLocationTypes)) {
        if (!in_array($place['location_type_id'], $acceptableLocationTypes)) {
            $passes = false;
        }
    }

    if ($passes) {
        // Calculate distance from current user
        $place['distance_km'] = null;
        if ($currentUserReq && $currentUserReq['home_latitude'] && $currentUserReq['home_longitude'] && $place['latitude'] && $place['longitude']) {
            $place['distance_km'] = round(haversineDistance(
                (float)$currentUserReq['home_latitude'], (float)$currentUserReq['home_longitude'],
                (float)$place['latitude'], (float)$place['longitude']
            ), 1);
        }

        $matchingPlaces[] = $place;
    }
}

// Get vote counts
foreach ($matchingPlaces as &$place) {
    $stmt = $pdo->prepare('SELECT COUNT(*) as vote_count FROM place_votes WHERE outing_id = :oid AND place_id = :pid');
    $stmt->execute(['oid' => $outingId, 'pid' => $place['id']]);
    $place['vote_count'] = (int)$stmt->fetch()['vote_count'];

    // Check if current user voted
    $stmt = $pdo->prepare('SELECT id FROM place_votes WHERE outing_id = :oid AND place_id = :pid AND user_id = :uid');
    $stmt->execute(['oid' => $outingId, 'pid' => $place['id'], 'uid' => $userId]);
    $place['user_voted'] = (bool)$stmt->fetch();
}

// Sort by vote count desc, then rating desc
usort($matchingPlaces, function($a, $b) {
    if ($b['vote_count'] !== $a['vote_count']) {
        return $b['vote_count'] - $a['vote_count'];
    }
    return (float)$b['rating'] <=> (float)$a['rating'];
});

jsonResponse([
    'places' => $matchingPlaces,
    'total_members' => (int)$totalMembers,
    'members_with_requirements' => $membersWithRequirements
]);
