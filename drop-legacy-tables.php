<?php
/**
 * Migration: Drop legacy grocery_orders tables
 * These tables were part of the v1 ordering system, now fully replaced by requisitions.
 * Run once: visit /migrate-drop-legacy.php in browser
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Drop Legacy Tables ===\n\n";

$tables = ['grocery_order_lines', 'grocery_orders'];

foreach ($tables as $table) {
    try {
        $db->exec("DROP TABLE IF EXISTS $table");
        echo "[OK] Dropped $table\n";
    } catch (PDOException $e) {
        echo "[FAIL] $table: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
echo "</pre>";
