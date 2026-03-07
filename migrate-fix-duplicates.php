<?php
/**
 * Karibu Pantry Planner — Fix Duplicate Requisitions & Add UNIQUE Constraint
 *
 * Run once: visit /migrate-fix-duplicates.php in browser (requires admin login)
 *
 * 1. Finds duplicate (kitchen_id, req_date, meals) groups
 * 2. Keeps the lowest-ID row per group, deletes the rest
 * 3. Adds UNIQUE KEY to prevent future duplicates
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Require admin auth for web access, allow CLI
if (php_sapi_name() !== 'cli') {
    $user = requireAuth();
    if ($user['role'] !== 'admin') {
        die('Admin access required');
    }
}

$db = getDB();

echo "<pre>\n";
echo "=== Fix Duplicate Requisitions & Add UNIQUE Constraint ===\n\n";

// Step 1: Find duplicate groups
$dupes = $db->query("
    SELECT kitchen_id, req_date, meals, GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS cnt
    FROM requisitions
    GROUP BY kitchen_id, req_date, meals
    HAVING COUNT(*) > 1
")->fetchAll();

$totalDeleted = 0;

if (empty($dupes)) {
    echo "No duplicate requisitions found. All clean!\n\n";
} else {
    echo "Found " . count($dupes) . " duplicate groups:\n\n";

    foreach ($dupes as $dupe) {
        $ids = explode(',', $dupe['ids']);
        $keepId = (int)$ids[0];
        $deleteIds = array_map('intval', array_slice($ids, 1));

        echo "  Date: {$dupe['req_date']} | Kitchen: {$dupe['kitchen_id']} | Meal: {$dupe['meals']} | Count: {$dupe['cnt']}\n";
        echo "    Keeping: #{$keepId}\n";
        echo "    Deleting: #" . implode(', #', $deleteIds) . "\n";

        if (!empty($deleteIds)) {
            $ph = implode(',', array_fill(0, count($deleteIds), '?'));
            $db->prepare("DELETE FROM requisition_dishes WHERE requisition_id IN ($ph)")->execute($deleteIds);
            $db->prepare("DELETE FROM requisition_lines WHERE requisition_id IN ($ph)")->execute($deleteIds);
            $stmt = $db->prepare("DELETE FROM requisitions WHERE id IN ($ph)");
            $stmt->execute($deleteIds);
            $deleted = $stmt->rowCount();
            $totalDeleted += $deleted;
            echo "    Deleted: {$deleted} requisition(s)\n\n";
        }
    }
    echo "Total deleted: $totalDeleted\n\n";
}

// Step 2: Add UNIQUE constraint
try {
    $db->exec("ALTER TABLE requisitions ADD UNIQUE KEY uk_kitchen_date_meals (kitchen_id, req_date, meals)");
    echo "[OK] Added UNIQUE constraint uk_kitchen_date_meals (kitchen_id, req_date, meals)\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "[SKIP] UNIQUE constraint uk_kitchen_date_meals already exists\n";
    } else {
        echo "[FAIL] " . $e->getMessage() . "\n";
    }
}

echo "\n=== Done ===\n";
echo "</pre>\n";
