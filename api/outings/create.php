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

$name = trim($data['name'] ?? '');
$outingType = $data['outing_type'] ?? '';
$scheduledDate = $data['scheduled_date'] ?? '';
$inviteeIds = $data['invitee_ids'] ?? [];
$description = trim($data['description'] ?? '');

if (!$name) {
    jsonResponse(['error' => 'Outing name is required'], 400);
}

if (!in_array($outingType, ['open', 'closed', 'restricted'])) {
    jsonResponse(['error' => 'Invalid outing type. Must be open, closed, or restricted'], 400);
}

if (!$scheduledDate) {
    jsonResponse(['error' => 'Scheduled date is required'], 400);
}

try {
    $pdo->beginTransaction();

    // Create outing
    try {
        $stmt = $pdo->prepare('INSERT INTO outings (name, creator_id, outing_type, scheduled_date, description) VALUES (:name, :creator_id, :outing_type, :scheduled_date, :description) RETURNING id');
        $stmt->execute([
            'name' => $name,
            'creator_id' => $userId,
            'outing_type' => $outingType,
            'scheduled_date' => $scheduledDate,
            'description' => $description ?: null
        ]);
        $outing = $stmt->fetch();
        $outingId = $outing['id'];
    } catch (Exception $e) {
        throw new Exception("Create outing failed: " . $e->getMessage());
    }

    // Add creator as accepted member
    try {
        $stmt = $pdo->prepare('INSERT INTO outing_members (outing_id, user_id, invite_status, invited_by) VALUES (:outing_id, :user_id, :status, :invited_by)');
        $stmt->execute([
            'outing_id' => $outingId,
            'user_id' => $userId,
            'status' => 'accepted',
            'invited_by' => $userId
        ]);
    } catch (Exception $e) {
        throw new Exception("Add creator failed: " . $e->getMessage());
    }

    // Add invitees
    foreach ($inviteeIds as $index => $inviteeId) {
        if ($inviteeId === $userId) continue;
        try {
            $stmt = $pdo->prepare('INSERT INTO outing_members (outing_id, user_id, invite_status, invited_by) VALUES (:outing_id, :user_id, :status, :invited_by) ON CONFLICT (outing_id, user_id) DO NOTHING');
            $stmt->execute([
                'outing_id' => $outingId,
                'user_id' => $inviteeId,
                'status' => 'pending',
                'invited_by' => $userId
            ]);
        } catch (Exception $e) {
            throw new Exception("Add invitee {$index} failed: " . $e->getMessage());
        }
    }

    $pdo->commit();

    jsonResponse(['outing_id' => $outingId, 'message' => 'Outing created successfully'], 201);

} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['error' => 'Failed to create outing: ' . $e->getMessage()], 500);
}
