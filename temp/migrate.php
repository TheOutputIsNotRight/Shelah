<?php
require_once __DIR__ . '/../api/db.php';

try {
    $pdo->exec("ALTER TABLE outing_members ADD COLUMN votes_submitted BOOLEAN DEFAULT FALSE;");
    echo "Migration successful.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
