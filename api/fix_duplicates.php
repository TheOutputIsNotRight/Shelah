<?php
/**
 * Shelah — Fix Duplicates
 * 
 * Run this script once to clean up duplicate location types and places
 * that were created due to missing UNIQUE constraints.
 */

header('Content-Type: text/html; charset=utf-8');
echo '<h1>Shelah — Fixing Duplicates</h1><pre>';

$dbUrl = getenv('DATABASE_URL');
if (!$dbUrl) {
    die('ERROR: DATABASE_URL environment variable not set.');
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "✅ Database connected\n";
} catch (PDOException $e) {
    die('ERROR: ' . $e->getMessage());
}

try {
    $pdo->beginTransaction();

    echo "\n--- Fixing duplicate location types ---\n";
    // 1. Get all unique names
    $names = $pdo->query('SELECT DISTINCT name FROM location_types')->fetchAll(PDO::FETCH_COLUMN);
    
    $typesRemoved = 0;
    foreach ($names as $name) {
        // Get all IDs for this name, ordered by created_at or id so we keep the first one
        $stmt = $pdo->prepare('SELECT id FROM location_types WHERE name = ? ORDER BY id');
        $stmt->execute([$name]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($ids) > 1) {
            $primaryId = $ids[0];
            $duplicateIds = array_slice($ids, 1);
            
            // Re-assign references in places
            $placeUpdate = $pdo->prepare('UPDATE places SET location_type_id = ? WHERE location_type_id = ?');
            // Re-assign references in requirement_location_types (might have conflicts if both primary and duplicate are in the same requirement)
            $reqUpdate = $pdo->prepare('UPDATE requirement_location_types SET location_type_id = ? WHERE location_type_id = ?');
            
            foreach ($duplicateIds as $dupId) {
                $placeUpdate->execute([$primaryId, $dupId]);
                try {
                    $reqUpdate->execute([$primaryId, $dupId]);
                } catch (Exception $e) {
                    // Ignore unique constraint violation if a requirement already had both
                }
            }
            
            // Delete the duplicates
            $placeUpdate = $pdo->prepare('DELETE FROM requirement_location_types WHERE location_type_id = ?');
            foreach ($duplicateIds as $dupId) {
                 $placeUpdate->execute([$dupId]);
            }

            $placeUpdate = $pdo->prepare('DELETE FROM location_types WHERE id = ?');
            foreach ($duplicateIds as $dupId) {
                $placeUpdate->execute([$dupId]);
                $typesRemoved++;
            }
            
            echo "  ~ Merged " . count($duplicateIds) . " duplicates for '{$name}'\n";
        }
    }
    
    echo "✅ Removed {$typesRemoved} duplicate location types.\n";

    echo "\n--- Fixing duplicate places ---\n";
    $placeNames = $pdo->query('SELECT DISTINCT name FROM places')->fetchAll(PDO::FETCH_COLUMN);
    $placesRemoved = 0;
    
    foreach ($placeNames as $name) {
        $stmt = $pdo->prepare('SELECT id FROM places WHERE name = ? ORDER BY id');
        $stmt->execute([$name]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($ids) > 1) {
            $primaryId = $ids[0];
            $duplicateIds = array_slice($ids, 1);
            
            $voteUpdate = $pdo->prepare('UPDATE place_votes SET place_id = ? WHERE place_id = ?');
            
            foreach ($duplicateIds as $dupId) {
                try {
                    $voteUpdate->execute([$primaryId, $dupId]);
                } catch (Exception $e) {
                    // Ignore unique constraint violation
                }
            }
            
            // Delete votes pointing to duplicates that couldn't be updated (due to unique constraints)
            $voteUpdate = $pdo->prepare('DELETE FROM place_votes WHERE place_id = ?');
            foreach ($duplicateIds as $dupId) {
                $voteUpdate->execute([$dupId]);
            }
            
            $placeDelete = $pdo->prepare('DELETE FROM places WHERE id = ?');
            foreach ($duplicateIds as $dupId) {
                $placeDelete->execute([$dupId]);
                $placesRemoved++;
            }
            
            echo "  ~ Merged " . count($duplicateIds) . " duplicates for place '{$name}'\n";
        }
    }
    
    echo "✅ Removed {$placesRemoved} duplicate places.\n";
    
    $pdo->commit();
    echo "\n\n=============================\n";
    echo "✅ FIX COMPLETE!\n";
    echo "=============================\n";

} catch (Exception $e) {
    $pdo->rollBack();
    die("\n❌ ERROR: " . $e->getMessage() . "\n");
}
echo '</pre>';
