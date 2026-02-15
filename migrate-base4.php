<?php
/**
 * Migration: Convert recipes from base-20 to base-4 servings
 *
 * All recipes were seeded with servings=20 and ingredient quantities for 20 people.
 * This migrates to servings=4 and divides all ingredient quantities by 5.
 *
 * Run once: https://your-domain.com/migrate-base4.php
 */
require_once __DIR__ . '/config.php';
$db = getDB();

header('Content-Type: text/plain');

// Step 1: Update all recipes from servings=20 to servings=4
$updated = $db->exec("UPDATE recipes SET servings = 4 WHERE servings = 20");
echo "Recipes updated to servings=4: $updated\n";

// Step 2: Divide all recipe_ingredients qty by 5 (20/4 = 5)
$ingUpdated = $db->exec("UPDATE recipe_ingredients SET qty = ROUND(qty / 5, 3)");
echo "Recipe ingredients scaled to base-4: $ingUpdated\n";

// Step 3: Also update any existing dish_ingredients that reference these recipes
// The base `qty` column stores the recipe's original qty, so we need to update it too
// The `final_qty` is the scaled qty which will be recalculated by the frontend
$dishIngUpdated = $db->exec("UPDATE dish_ingredients SET qty = ROUND(qty / 5, 3), final_qty = ROUND(final_qty / 5, 3) WHERE qty > 0");
echo "Dish ingredients scaled: $dishIngUpdated\n";

// Step 4: Add unit_size column to grocery_order_lines if not exists
try {
    $db->exec("ALTER TABLE grocery_order_lines ADD COLUMN unit_size DECIMAL(10,2) DEFAULT NULL AFTER fulfilled_qty");
    echo "Added unit_size column to grocery_order_lines\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "unit_size column already exists\n";
    } else {
        echo "Error adding unit_size: " . $e->getMessage() . "\n";
    }
}

// Verify
$sample = $db->query("SELECT r.name, r.servings, ri.item_name, ri.qty, ri.uom FROM recipes r JOIN recipe_ingredients ri ON ri.recipe_id = r.id LIMIT 10")->fetchAll();
echo "\nSample recipes (should show base-4 quantities):\n";
foreach ($sample as $row) {
    echo "  {$row['name']} (serves {$row['servings']}): {$row['item_name']} = {$row['qty']} {$row['uom']}\n";
}

echo "\nDone! All recipes now use base-4 servings.\n";
