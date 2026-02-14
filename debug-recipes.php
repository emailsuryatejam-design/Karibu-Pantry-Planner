<?php
require_once __DIR__ . '/config.php';
$db = getDB();

header('Content-Type: text/plain');

// Check FK constraints
try {
    $fks = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'weekly_menu' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchAll();
    echo "FK constraints: " . count($fks) . "\n";
    foreach ($fks as $fk) {
        echo "  Dropping FK: " . $fk['CONSTRAINT_NAME'] . "\n";
        $db->exec("ALTER TABLE weekly_menu DROP FOREIGN KEY " . $fk['CONSTRAINT_NAME']);
    }
} catch (Exception $e) {
    echo "FK check: " . $e->getMessage() . "\n";
}

// Drop + recreate
try {
    $db->exec("DROP TABLE IF EXISTS weekly_menu");
    echo "DROP OK\n";
    $db->exec("CREATE TABLE weekly_menu (id INT AUTO_INCREMENT PRIMARY KEY, day_of_week TINYINT NOT NULL, meal ENUM('lunch','dinner') NOT NULL, recipe_id INT NOT NULL, sort_order INT DEFAULT 0)");
    echo "CREATE OK\n";
} catch (Exception $e) {
    echo "Table error: " . $e->getMessage() . "\n";
}

// Build lookup
$allRecipes = $db->query('SELECT id, name FROM recipes')->fetchAll();
$lookup = [];
foreach ($allRecipes as $r) $lookup[strtolower(trim($r['name']))] = (int)$r['id'];
echo "Recipes: " . count($lookup) . "\n";

// Test match
$test = 'vegetable samosa with sweet chili sauce';
echo "Lookup '$test': " . ($lookup[$test] ?? 'NOT FOUND') . "\n";

// Insert one test
if (isset($lookup[$test])) {
    $db->prepare('INSERT INTO weekly_menu (day_of_week, meal, recipe_id, sort_order) VALUES (1, "lunch", ?, 0)')->execute([$lookup[$test]]);
    echo "Insert test OK\n";
}

echo "Count: " . $db->query("SELECT COUNT(*) FROM weekly_menu")->fetchColumn() . "\n";
