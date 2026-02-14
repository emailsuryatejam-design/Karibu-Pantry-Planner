<?php
/**
 * Karibu Pantry Planner — Database Setup
 * Run once to create tables + seed data
 * Usage: php setup.php  OR  visit /setup.php in browser
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Karibu Pantry Planner — Database Setup ===\n\n";

// ── Create Tables ──
$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        username VARCHAR(50) UNIQUE NOT NULL,
        pin VARCHAR(10) NOT NULL,
        role ENUM('chef', 'storekeeper', 'admin') NOT NULL,
        camp_id INT DEFAULT NULL,
        camp_name VARCHAR(100) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'items' => "CREATE TABLE IF NOT EXISTS items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        code VARCHAR(50) DEFAULT NULL,
        category VARCHAR(100) DEFAULT NULL,
        uom VARCHAR(20) DEFAULT 'kg',
        stock_qty DECIMAL(10,2) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'recipes' => "CREATE TABLE IF NOT EXISTS recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        category VARCHAR(50) DEFAULT 'main_course',
        cuisine VARCHAR(50) DEFAULT NULL,
        difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        prep_time INT DEFAULT NULL,
        cook_time INT DEFAULT NULL,
        servings INT DEFAULT 4,
        instructions TEXT,
        notes TEXT,
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    'recipe_ingredients' => "CREATE TABLE IF NOT EXISTS recipe_ingredients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        item_id INT DEFAULT NULL,
        item_name VARCHAR(200) NOT NULL,
        qty DECIMAL(10,3) NOT NULL,
        uom VARCHAR(20) DEFAULT 'kg',
        is_primary TINYINT(1) DEFAULT 1,
        FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
    )",

    'menu_plans' => "CREATE TABLE IF NOT EXISTS menu_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan_date DATE NOT NULL,
        meal ENUM('lunch', 'dinner') NOT NULL,
        portions INT DEFAULT 20,
        status ENUM('draft', 'confirmed') DEFAULT 'draft',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_plan (plan_date, meal)
    )",

    'menu_dishes' => "CREATE TABLE IF NOT EXISTS menu_dishes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan_id INT NOT NULL,
        dish_name VARCHAR(200) NOT NULL,
        course ENUM('appetizer','soup','salad','main_course','side','dessert','beverage') DEFAULT 'main_course',
        portions INT DEFAULT 20,
        recipe_id INT DEFAULT NULL,
        is_default TINYINT(1) DEFAULT 0,
        presentation_score DECIMAL(3,1) DEFAULT NULL,
        presentation_feedback TEXT,
        FOREIGN KEY (plan_id) REFERENCES menu_plans(id) ON DELETE CASCADE
    )",

    'dish_ingredients' => "CREATE TABLE IF NOT EXISTS dish_ingredients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dish_id INT NOT NULL,
        item_id INT DEFAULT NULL,
        item_name VARCHAR(200) NOT NULL,
        qty DECIMAL(10,3) NOT NULL,
        final_qty DECIMAL(10,3) DEFAULT NULL,
        uom VARCHAR(20) DEFAULT 'kg',
        is_primary TINYINT(1) DEFAULT 1,
        is_removed TINYINT(1) DEFAULT 0,
        stock_qty DECIMAL(10,2) DEFAULT NULL,
        ordered_qty DECIMAL(10,2) DEFAULT NULL,
        received_qty DECIMAL(10,2) DEFAULT NULL,
        FOREIGN KEY (dish_id) REFERENCES menu_dishes(id) ON DELETE CASCADE
    )",

    'grocery_orders' => "CREATE TABLE IF NOT EXISTS grocery_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_date DATE NOT NULL,
        meal ENUM('lunch', 'dinner') NOT NULL,
        status ENUM('pending', 'reviewing', 'approved', 'partial', 'rejected', 'fulfilled', 'received') DEFAULT 'pending',
        total_items INT DEFAULT 0,
        notes TEXT,
        created_by INT DEFAULT NULL,
        reviewed_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    'grocery_order_lines' => "CREATE TABLE IF NOT EXISTS grocery_order_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        item_id INT DEFAULT NULL,
        item_name VARCHAR(200) NOT NULL,
        requested_qty DECIMAL(10,2) NOT NULL,
        approved_qty DECIMAL(10,2) DEFAULT NULL,
        fulfilled_qty DECIMAL(10,2) DEFAULT NULL,
        uom VARCHAR(20) DEFAULT 'kg',
        status ENUM('pending', 'approved', 'adjusted', 'rejected') DEFAULT 'pending',
        store_notes TEXT,
        FOREIGN KEY (order_id) REFERENCES grocery_orders(id) ON DELETE CASCADE
    )",

    'audit_log' => "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        user_name VARCHAR(100) DEFAULT NULL,
        action VARCHAR(50) NOT NULL,
        entity VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        old_value JSON DEFAULT NULL,
        new_value JSON DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
];

foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        echo "  [OK] $name\n";
    } catch (PDOException $e) {
        echo "  [FAIL] $name: " . $e->getMessage() . "\n";
    }
}

// ── Migrations (alter existing tables) ──
echo "\n--- Running migrations ---\n";

$migrations = [
    "ALTER TABLE grocery_orders MODIFY COLUMN status ENUM('pending', 'reviewing', 'approved', 'partial', 'rejected', 'fulfilled', 'received') DEFAULT 'pending'",
];

foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
        echo "  [OK] Migration applied\n";
    } catch (PDOException $e) {
        echo "  [SKIP] " . $e->getMessage() . "\n";
    }
}

// ── Seed Default Users ──
echo "\n--- Seeding users ---\n";

$users = [
    ['Admin', 'admin', '1234', 'admin'],
    ['Head Chef', 'chef', '1111', 'chef'],
    ['Camp Store', 'store', '2222', 'storekeeper'],
];

foreach ($users as [$name, $username, $pin, $role]) {
    try {
        $stmt = $db->prepare('INSERT IGNORE INTO users (name, username, pin, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $username, $pin, $role]);
        echo "  [OK] $name ($username / PIN: $pin) - $role\n";
    } catch (PDOException $e) {
        echo "  [SKIP] $name: " . $e->getMessage() . "\n";
    }
}

// ── Seed Sample Items ──
echo "\n--- Seeding items ---\n";

$items = [
    ['Chicken Breast', 'CHK001', 'Meat', 'kg'],
    ['Beef Tenderloin', 'BEF001', 'Meat', 'kg'],
    ['Salmon Fillet', 'FSH001', 'Fish', 'kg'],
    ['Rice Basmati', 'GRN001', 'Grains', 'kg'],
    ['Pasta Penne', 'GRN002', 'Grains', 'kg'],
    ['Tomato', 'VEG001', 'Vegetables', 'kg'],
    ['Onion', 'VEG002', 'Vegetables', 'kg'],
    ['Garlic', 'VEG003', 'Vegetables', 'kg'],
    ['Bell Pepper', 'VEG004', 'Vegetables', 'kg'],
    ['Carrot', 'VEG005', 'Vegetables', 'kg'],
    ['Potato', 'VEG006', 'Vegetables', 'kg'],
    ['Spinach', 'VEG007', 'Vegetables', 'kg'],
    ['Broccoli', 'VEG008', 'Vegetables', 'kg'],
    ['Mushroom', 'VEG009', 'Vegetables', 'kg'],
    ['Lemon', 'FRT001', 'Fruits', 'pcs'],
    ['Olive Oil', 'OIL001', 'Oils', 'ltr'],
    ['Butter', 'DRY001', 'Dairy', 'kg'],
    ['Heavy Cream', 'DRY002', 'Dairy', 'ltr'],
    ['Cheese Parmesan', 'DRY003', 'Dairy', 'kg'],
    ['Milk', 'DRY004', 'Dairy', 'ltr'],
    ['Eggs', 'DRY005', 'Dairy', 'pcs'],
    ['Salt', 'SPC001', 'Spices', 'kg'],
    ['Black Pepper', 'SPC002', 'Spices', 'kg'],
    ['Cumin', 'SPC003', 'Spices', 'kg'],
    ['Paprika', 'SPC004', 'Spices', 'kg'],
    ['Flour', 'BKG001', 'Baking', 'kg'],
    ['Sugar', 'BKG002', 'Baking', 'kg'],
    ['Coconut Milk', 'CAN001', 'Canned', 'ltr'],
    ['Soy Sauce', 'SAU001', 'Sauces', 'ltr'],
    ['Worcestershire Sauce', 'SAU002', 'Sauces', 'ltr'],
];

foreach ($items as [$name, $code, $cat, $uom]) {
    try {
        $stmt = $db->prepare('INSERT IGNORE INTO items (name, code, category, uom, stock_qty) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $code, $cat, $uom, rand(5, 50)]);
    } catch (PDOException $e) {
        // skip duplicates
    }
}
echo "  [OK] " . count($items) . " items seeded\n";

echo "\n=== Setup Complete ===\n";
echo "\nDefault logins:\n";
echo "  Admin:       admin / 1234\n";
echo "  Chef:        chef / 1111\n";
echo "  Storekeeper: store / 2222\n";
echo "</pre>";
