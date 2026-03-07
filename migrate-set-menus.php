<?php
/**
 * Karibu Pantry Planner — Rotational Set Menu Migration
 * Run once: visit /migrate-set-menus.php in browser
 *
 * Creates set_menu_items table for weekly rotational menus.
 * Maps day_of_week + type_code → list of recipes.
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Rotational Set Menu Migration ===\n\n";

// Create set_menu_items table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS set_menu_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day_of_week TINYINT NOT NULL COMMENT '1=Monday ... 7=Sunday',
        type_code VARCHAR(50) NOT NULL COMMENT 'Requisition type code e.g. breakfast, lunch, dinner',
        recipe_id INT NOT NULL,
        recipe_name VARCHAR(200) NOT NULL COMMENT 'Denormalized for display',
        sort_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_day_type (day_of_week, type_code),
        UNIQUE KEY uk_day_type_recipe (day_of_week, type_code, recipe_id)
    )");
    echo "[OK] Created set_menu_items table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "[SKIP] set_menu_items table already exists\n";
    } else {
        echo "[FAIL] " . $e->getMessage() . "\n";
    }
}

echo "\n=== Done ===\n";
echo "</pre>\n";
