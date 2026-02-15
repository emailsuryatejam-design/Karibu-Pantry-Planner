<?php
/**
 * Migration: Add received_qty column to grocery_order_lines
 * + Add has_dispute flag to grocery_orders
 *
 * Run once: php migrate-receipt.php
 * Or visit: /migrate-receipt.php in browser
 */

require_once __DIR__ . '/config.php';
$db = getDB();

echo "<pre>\n";
echo "=== Migration: Receipt tracking + disputes ===\n\n";

// 1. Add received_qty column to grocery_order_lines (separate from fulfilled_qty)
try {
    $db->exec("ALTER TABLE grocery_order_lines ADD COLUMN received_qty DECIMAL(10,2) DEFAULT NULL AFTER fulfilled_qty");
    echo "✓ Added received_qty column to grocery_order_lines\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "· received_qty column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

// 2. Add has_dispute flag to grocery_orders
try {
    $db->exec("ALTER TABLE grocery_orders ADD COLUMN has_dispute TINYINT(1) DEFAULT 0 AFTER status");
    echo "✓ Added has_dispute column to grocery_orders\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "· has_dispute column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

// 3. Add dispute_notes to grocery_order_lines for individual line disputes
try {
    $db->exec("ALTER TABLE grocery_order_lines ADD COLUMN dispute_notes TEXT DEFAULT NULL AFTER unit_size");
    echo "✓ Added dispute_notes column to grocery_order_lines\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "· dispute_notes column already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
}

// 4. Add 'received' to status ENUM on grocery_orders
try {
    $db->exec("ALTER TABLE grocery_orders MODIFY COLUMN status ENUM('pending', 'reviewing', 'approved', 'partial', 'rejected', 'fulfilled', 'received') DEFAULT 'pending'");
    echo "✓ Added 'received' to grocery_orders status ENUM\n";
} catch (Exception $e) {
    echo "✗ Error updating ENUM: " . $e->getMessage() . "\n";
}

// 5. Fix any orders that had empty status (from before ENUM was updated)
try {
    $count = $db->exec("UPDATE grocery_orders SET status = 'received' WHERE status = '' AND has_dispute IS NOT NULL AND has_dispute >= 0 AND updated_at > created_at AND id IN (SELECT DISTINCT order_id FROM grocery_order_lines WHERE received_qty IS NOT NULL)");
    echo "✓ Fixed {$count} orders with empty status → received\n";
} catch (Exception $e) {
    echo "✗ Error fixing status: " . $e->getMessage() . "\n";
}

echo "\n=== Migration complete ===\n";
echo "</pre>\n";
