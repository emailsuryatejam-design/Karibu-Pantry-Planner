<?php
/**
 * Karibu Pantry Planner — Requisition Types Migration
 * Run once: visit /migrate-req-types.php in browser
 *
 * Creates requisition_types table and seeds default types.
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Requisition Types Migration ===\n\n";

// Create table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS requisition_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        code VARCHAR(30) NOT NULL UNIQUE,
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "[OK] Created requisition_types table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "[SKIP] requisition_types table already exists\n";
    } else {
        echo "[FAIL] " . $e->getMessage() . "\n";
    }
}

// Seed default types
$defaults = [
    ['Full Day', 'full_day', 1],
    ['Breakfast', 'breakfast', 2],
    ['Lunch', 'lunch', 3],
    ['Dinner', 'dinner', 4],
    ['Picnic', 'picnic', 5],
];

$insert = $db->prepare("INSERT IGNORE INTO requisition_types (name, code, sort_order) VALUES (?, ?, ?)");
foreach ($defaults as $type) {
    try {
        $insert->execute($type);
        $affected = $insert->rowCount();
        echo $affected ? "[OK] Seeded type: {$type[0]}\n" : "[SKIP] Type already exists: {$type[0]}\n";
    } catch (PDOException $e) {
        echo "[FAIL] {$type[0]} — " . $e->getMessage() . "\n";
    }
}

echo "\n=== Done ===\n";
echo "</pre>\n";
