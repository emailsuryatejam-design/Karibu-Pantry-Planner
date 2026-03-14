<?php
/**
 * One-time migration: Add piece_weight and is_pantry_staple to items table
 * DELETE THIS FILE after running
 */
require_once __DIR__ . '/config.php';
$db = getDB();

header('Content-Type: application/json');

try {
    // Check if columns already exist
    $cols = $db->query("SHOW COLUMNS FROM items")->fetchAll(PDO::FETCH_COLUMN);
    $results = [];

    if (!in_array('piece_weight', $cols)) {
        $db->exec("ALTER TABLE items ADD COLUMN piece_weight DECIMAL(10,3) DEFAULT NULL AFTER stock_qty");
        $results[] = 'Added piece_weight column';
    } else {
        $results[] = 'piece_weight already exists';
    }

    if (!in_array('is_pantry_staple', $cols)) {
        $db->exec("ALTER TABLE items ADD COLUMN is_pantry_staple TINYINT(1) DEFAULT 0 AFTER piece_weight");
        $results[] = 'Added is_pantry_staple column';
    } else {
        $results[] = 'is_pantry_staple already exists';
    }

    echo json_encode(['success' => true, 'results' => $results]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
