<?php
/**
 * Karibu Pantry Planner — V2 Migration
 * Run once: php migrate-v2.php  OR  visit /migrate-v2.php in browser
 *
 * Adds: kitchens, requisitions, requisition_lines, push_subscriptions, notifications
 * Alters: items (portion_weight, order_mode), users (kitchen_id),
 *         menu_plans (meal enum, kitchen_id), grocery_orders (meal enum, kitchen_id, session_number)
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Karibu Pantry Planner — V2 Migration ===\n\n";

// ── New Tables ──
echo "--- Creating new tables ---\n";

$newTables = [
    'kitchens' => "CREATE TABLE IF NOT EXISTS kitchens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) NOT NULL UNIQUE,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'requisitions' => "CREATE TABLE IF NOT EXISTS requisitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kitchen_id INT NOT NULL,
        req_date DATE NOT NULL,
        session_number INT DEFAULT 1,
        guest_count INT DEFAULT 20,
        meals VARCHAR(100) DEFAULT 'lunch',
        status ENUM('draft','submitted','processing','fulfilled','received','closed') DEFAULT 'draft',
        has_dispute TINYINT(1) DEFAULT 0,
        notes TEXT,
        created_by INT DEFAULT NULL,
        reviewed_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_req_lookup (req_date, kitchen_id, session_number)
    )",

    'requisition_lines' => "CREATE TABLE IF NOT EXISTS requisition_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        requisition_id INT NOT NULL,
        item_id INT NOT NULL,
        item_name VARCHAR(200) NOT NULL,
        meal VARCHAR(20) DEFAULT 'lunch',
        order_mode ENUM('portion','direct_kg') DEFAULT 'portion',
        portions INT DEFAULT 0,
        portion_weight DECIMAL(10,3) DEFAULT 0.250,
        required_kg DECIMAL(10,3) DEFAULT 0,
        stock_qty DECIMAL(10,2) DEFAULT 0,
        order_qty DECIMAL(10,3) DEFAULT 0,
        fulfilled_qty DECIMAL(10,3) DEFAULT NULL,
        received_qty DECIMAL(10,3) DEFAULT NULL,
        uom VARCHAR(20) DEFAULT 'kg',
        status ENUM('pending','approved','adjusted','rejected') DEFAULT 'pending',
        store_notes TEXT,
        FOREIGN KEY (requisition_id) REFERENCES requisitions(id) ON DELETE CASCADE
    )",

    'push_subscriptions' => "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        kitchen_id INT DEFAULT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(500) NOT NULL,
        auth_key VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    'notifications' => "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kitchen_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        title VARCHAR(200) NOT NULL,
        body TEXT,
        type VARCHAR(50) DEFAULT 'info',
        ref_id INT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
];

foreach ($newTables as $name => $sql) {
    try {
        $db->exec($sql);
        echo "  [OK] $name\n";
    } catch (PDOException $e) {
        echo "  [FAIL] $name: " . $e->getMessage() . "\n";
    }
}

// ── Seed Kitchens ──
echo "\n--- Seeding kitchens ---\n";

$kitchens = [
    ['Test Kitchen', 'TEST'],
    ['Lions Paw', 'LIONSPAW'],
    ['Woodlands', 'WOODLANDS'],
    ['Sametu', 'SAMETU'],
    ['River Camp', 'RIVERCAMP'],
    ['Elephant Springs', 'ELEPHANT'],
];

foreach ($kitchens as [$name, $code]) {
    try {
        $stmt = $db->prepare('INSERT IGNORE INTO kitchens (name, code) VALUES (?, ?)');
        $stmt->execute([$name, $code]);
        echo "  [OK] $name ($code)\n";
    } catch (PDOException $e) {
        echo "  [SKIP] $name: " . $e->getMessage() . "\n";
    }
}

// ── Alter existing tables ──
echo "\n--- Running ALTER TABLE migrations ---\n";

$alterations = [
    // items: portion_weight + order_mode
    "ALTER TABLE items ADD COLUMN portion_weight DECIMAL(10,3) DEFAULT 0.250",
    "ALTER TABLE items ADD COLUMN order_mode ENUM('portion','direct_kg') DEFAULT 'portion'",

    // users: kitchen_id
    "ALTER TABLE users ADD COLUMN kitchen_id INT DEFAULT NULL",

    // menu_plans: expand meal enum, add kitchen_id
    "ALTER TABLE menu_plans MODIFY COLUMN meal ENUM('full_day','breakfast','lunch','dinner','picnic') NOT NULL DEFAULT 'lunch'",
    "ALTER TABLE menu_plans ADD COLUMN kitchen_id INT DEFAULT NULL",

    // grocery_orders: expand meal enum, add kitchen_id, session_number
    "ALTER TABLE grocery_orders MODIFY COLUMN meal ENUM('full_day','breakfast','lunch','dinner','picnic') NOT NULL DEFAULT 'lunch'",
    "ALTER TABLE grocery_orders ADD COLUMN kitchen_id INT DEFAULT NULL",
    "ALTER TABLE grocery_orders ADD COLUMN session_number INT DEFAULT 1",

    // weekly_menu: expand meal enum
    "ALTER TABLE weekly_menu MODIFY COLUMN meal ENUM('full_day','breakfast','lunch','dinner','picnic') NOT NULL DEFAULT 'lunch'",
];

foreach ($alterations as $sql) {
    try {
        $db->exec($sql);
        echo "  [OK] " . substr($sql, 0, 70) . "...\n";
    } catch (PDOException $e) {
        // Column/constraint may already exist
        echo "  [SKIP] " . substr($sql, 0, 60) . "... (" . $e->getMessage() . ")\n";
    }
}

// ── Drop unique_plan index on menu_plans (allow multiple sessions) ──
echo "\n--- Dropping unique constraint on menu_plans ---\n";
try {
    // Check if index exists first
    $indexes = $db->query("SHOW INDEX FROM menu_plans WHERE Key_name = 'unique_plan'")->fetchAll();
    if (count($indexes) > 0) {
        $db->exec("ALTER TABLE menu_plans DROP INDEX unique_plan");
        echo "  [OK] Dropped unique_plan index\n";
    } else {
        echo "  [SKIP] unique_plan index does not exist\n";
    }
} catch (PDOException $e) {
    echo "  [SKIP] " . $e->getMessage() . "\n";
}

// ── Add indexes ──
echo "\n--- Adding indexes ---\n";

$indexes = [
    "ALTER TABLE menu_plans ADD INDEX idx_plan_lookup (plan_date, meal, kitchen_id)",
    "ALTER TABLE grocery_orders ADD INDEX idx_order_lookup (order_date, kitchen_id)",
    "ALTER TABLE notifications ADD INDEX idx_notif_user (user_id, is_read)",
    "ALTER TABLE requisitions ADD INDEX idx_req_status (status, kitchen_id)",
];

foreach ($indexes as $sql) {
    try {
        $db->exec($sql);
        echo "  [OK] " . substr($sql, 0, 70) . "...\n";
    } catch (PDOException $e) {
        echo "  [SKIP] " . substr($sql, 0, 60) . "... (" . $e->getMessage() . ")\n";
    }
}

// ── Set default kitchen for existing users ──
echo "\n--- Assigning default kitchen to existing users ---\n";
try {
    $testKitchen = $db->query("SELECT id FROM kitchens WHERE code = 'TEST' LIMIT 1")->fetch();
    if ($testKitchen) {
        $db->exec("UPDATE users SET kitchen_id = {$testKitchen['id']} WHERE kitchen_id IS NULL");
        echo "  [OK] Existing users assigned to Test Kitchen\n";
    }
} catch (PDOException $e) {
    echo "  [SKIP] " . $e->getMessage() . "\n";
}

// ── Update special items order_mode ──
echo "\n--- Setting direct_kg mode for milk items ---\n";
try {
    $db->exec("UPDATE items SET order_mode = 'direct_kg' WHERE name LIKE '%Milk%'");
    echo "  [OK] Milk items set to direct_kg\n";
} catch (PDOException $e) {
    echo "  [SKIP] " . $e->getMessage() . "\n";
}

// ── Update special portion weights ──
echo "\n--- Setting special portion weights ---\n";
$specialItems = [
    ['%Cashew%', 0.050],
    ['%Ground Nut%', 0.050],
];
foreach ($specialItems as [$pattern, $weight]) {
    try {
        $stmt = $db->prepare("UPDATE items SET portion_weight = ? WHERE name LIKE ?");
        $stmt->execute([$weight, $pattern]);
        echo "  [OK] $pattern → {$weight}kg/portion\n";
    } catch (PDOException $e) {
        echo "  [SKIP] $pattern: " . $e->getMessage() . "\n";
    }
}

echo "\n=== V2 Migration Complete ===\n";
echo "</pre>";
