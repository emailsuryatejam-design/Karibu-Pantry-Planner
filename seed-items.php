<?php
/**
 * One-time seed script: imports items from seed-items.json
 * Run via browser: /seed-items.php
 * Self-deletes after successful import
 */
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';
$db = getDB();

$json = file_get_contents(__DIR__ . '/seed-items.json');
$items = json_decode($json, true);
if (!$items) {
    echo json_encode(['error' => 'Failed to read seed-items.json']);
    exit;
}

// Deactivate all existing items
$db->exec('UPDATE items SET is_active = 0');

// Batch insert
$stmt = $db->prepare('INSERT INTO items (name, code, category, uom, is_active) VALUES (?, ?, ?, ?, 1)');
$count = 0;
foreach ($items as $item) {
    $stmt->execute([$item[0], $item[1], $item[2], $item[3]]);
    $count++;
}

// Clear cache
cacheClear('active_items');

// Verify
$active = $db->query('SELECT COUNT(*) FROM items WHERE is_active = 1')->fetchColumn();

echo json_encode([
    'imported' => $count,
    'active_items' => (int)$active,
    'old_deactivated' => true
]);
