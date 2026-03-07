<?php
/**
 * Karibu Pantry Planner — Dish-Based Requisition Migration
 * Run once: visit /migrate-dish-requisition.php in browser
 *
 * Creates requisition_dishes table and adds source columns to requisition_lines.
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Dish-Based Requisition Migration ===\n\n";

// Create requisition_dishes table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS requisition_dishes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requisition_id INT NOT NULL,
        recipe_id INT NOT NULL,
        recipe_name VARCHAR(200) NOT NULL,
        recipe_servings INT DEFAULT 4,
        scale_factor DECIMAL(10,3) DEFAULT 1.000,
        guest_count INT DEFAULT 20,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_req_dish (requisition_id)
    )");
    echo "[OK] Created requisition_dishes table\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "[SKIP] requisition_dishes table already exists\n";
    } else {
        echo "[FAIL] " . $e->getMessage() . "\n";
    }
}

// Add source_dish_id to requisition_lines
try {
    $db->exec("ALTER TABLE requisition_lines ADD COLUMN source_dish_id INT DEFAULT NULL");
    echo "[OK] Added source_dish_id column to requisition_lines\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "[SKIP] source_dish_id column already exists\n";
    } else {
        echo "[FAIL] " . $e->getMessage() . "\n";
    }
}

// Add source_recipe_id to requisition_lines
try {
    $db->exec("ALTER TABLE requisition_lines ADD COLUMN source_recipe_id INT DEFAULT NULL");
    echo "[OK] Added source_recipe_id column to requisition_lines\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "[SKIP] source_recipe_id column already exists\n";
    } else {
        echo "[FAIL] " . $e->getMessage() . "\n";
    }
}

echo "\n=== Done ===\n";
echo "</pre>\n";
