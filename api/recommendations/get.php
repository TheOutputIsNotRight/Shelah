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

// Score each place using weighted percentage
$scoredPlaces = [];

foreach ($allPlaces as $place) {
    $memberScores = [];

    foreach ($allRequirements as $req) {
        $budgetScore = 100;
        $distanceScore = 100;
        $ratingScore = 100;
        $popularityScore = 100;
        $locationTypeScore = 100;

        // Budget score (25%) - 100 if within tier, degrades by tier distance
        if ($req['budget_tier'] && $req['budget_tier'] !== 'any' && $place['budget_tier']) {
            $reqLevel = budgetTierLevel($req['budget_tier']);
            $placeLevel = budgetTierLevel($place['budget_tier']);
            if ($placeLevel <= $reqLevel) {
                $budgetScore = 100;
            } else {
                $diff = $placeLevel - $reqLevel;
                $budgetScore = max(0, 100 - ($diff * 35));
            }
        }

        // Distance score (25%) - 100 if within max, linear falloff to 2x max
        if ($req['home_latitude'] && $req['home_longitude'] && $place['latitude'] && $place['longitude']) {
            $distance = haversineDistance(
                (float)$req['home_latitude'], (float)$req['home_longitude'],
                (float)$place['latitude'], (float)$place['longitude']
            );
            $maxDist = (int)($req['max_distance_km'] ?: 50);
            if ($distance <= $maxDist) {
                $distanceScore = 100;
            } elseif ($distance <= $maxDist * 2) {
                $distanceScore = max(0, 100 - (($distance - $maxDist) / $maxDist * 100));
            } else {
                $distanceScore = 0;
            }
        }

        // Rating score (20%) - 100 if meets min, linear degrade below
        if ($req['min_rating'] && $place['rating']) {
            $minR = (float)$req['min_rating'];
            $placeR = (float)$place['rating'];
            if ($placeR >= $minR) {
                $ratingScore = 100;
            } else {
                $ratingScore = max(0, ($placeR / $minR) * 100);
            }
        }

        // Popularity score (15%) - 100 if matches or 'any'
        if ($req['popularity_preference'] && $req['popularity_preference'] !== 'any') {
            $popularityScore = ($place['popularity'] === $req['popularity_preference']) ? 100 : 30;
        }

        // Location type score (15%) - 100 if in preferred types or no preference
        $userTypes = $requirementLocationTypes[$req['user_id']] ?? [];
        if (!empty($userTypes)) {
            $locationTypeScore = in_array($place['location_type_id'], $userTypes) ? 100 : 20;
        }

        // Weighted total for this member
        $memberTotal = ($budgetScore * 0.25) + ($distanceScore * 0.25) + ($ratingScore * 0.20) + ($popularityScore * 0.15) + ($locationTypeScore * 0.15);
        $memberScores[] = $memberTotal;
    }

    // Group match = average of all member scores
    $matchPercentage = count($memberScores) > 0 ? round(array_sum($memberScores) / count($memberScores)) : 0;

    // Exclude places below 40%
    if ($matchPercentage < 40) continue;

    // Calculate distance from current user
    $place['distance_km'] = null;
    if ($currentUserReq && $currentUserReq['home_latitude'] && $currentUserReq['home_longitude'] && $place['latitude'] && $place['longitude']) {
        $place['distance_km'] = round(haversineDistance(
            (float)$currentUserReq['home_latitude'], (float)$currentUserReq['home_longitude'],
            (float)$place['latitude'], (float)$place['longitude']
        ), 1);
    }

    $place['match_percentage'] = $matchPercentage;

    // Color tier for badge
    if ($matchPercentage >= 90) $place['match_color'] = 'gold';
    elseif ($matchPercentage >= 80) $place['match_color'] = 'green';
    elseif ($matchPercentage >= 70) $place['match_color'] = 'blue';
    elseif ($matchPercentage >= 60) $place['match_color'] = 'purple';
    else $place['match_color'] = 'orange';

    $scoredPlaces[] = $place;
}

// Get vote counts
foreach ($scoredPlaces as &$place) {
    $stmt = $pdo->prepare('SELECT COUNT(*) as vote_count FROM place_votes WHERE outing_id = :oid AND place_id = :pid');
    $stmt->execute(['oid' => $outingId, 'pid' => $place['id']]);
    $place['vote_count'] = (int)$stmt->fetch()['vote_count'];

    $stmt = $pdo->prepare('SELECT id FROM place_votes WHERE outing_id = :oid AND place_id = :pid AND user_id = :uid');
    $stmt->execute(['oid' => $outingId, 'pid' => $place['id'], 'uid' => $userId]);
    $place['user_voted'] = (bool)$stmt->fetch();
}

// Sort by match_percentage descending
usort($scoredPlaces, function($a, $b) {
    return $b['match_percentage'] - $a['match_percentage'];
});

jsonResponse([
    'places' => $scoredPlaces,
    'total_members' => (int)$totalMembers,
    'members_with_requirements' => $membersWithRequirements
]);
