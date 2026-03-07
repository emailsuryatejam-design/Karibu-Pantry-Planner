<?php
/**
 * Karibu Pantry Planner — One-Time Cleanup: Duplicate Requisitions
 *
 * Run once: visit /cleanup-old-requisitions.php in browser
 *
 * Problem: Old requisitions (created before the type system) all have
 * meals = 'lunch', causing duplicate "Lunch #1, #2, #3" tabs.
 *
 * This script:
 *   1. For each date/kitchen, keeps only ONE draft per meal type
 *   2. Deletes extra draft duplicates (and their dishes/lines)
 *   3. Shows what was cleaned up
 *
 * Safe: only deletes DRAFT requisitions. Submitted/fulfilled/received/closed are untouched.
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Cleanup Duplicate Draft Requisitions ===\n\n";

// Find all date/kitchen combos that have duplicate meal types in draft status
$stmt = $db->query("
    SELECT req_date, kitchen_id, meals, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS ids
    FROM requisitions
    WHERE status = 'draft'
    GROUP BY req_date, kitchen_id, meals
    HAVING COUNT(*) > 1
    ORDER BY req_date DESC, kitchen_id
");
$dupes = $stmt->fetchAll();

if (empty($dupes)) {
    echo "No duplicate draft requisitions found. All clean!\n";
    echo "</pre>\n";
    exit;
}

$totalDeleted = 0;

foreach ($dupes as $dupe) {
    $ids = explode(',', $dupe['ids']);
    $keepId = (int)$ids[0]; // Keep the first (oldest) one
    $deleteIds = array_map('intval', array_slice($ids, 1));

    echo "Date: {$dupe['req_date']} | Kitchen: {$dupe['kitchen_id']} | Meal: {$dupe['meals']} | Found: {$dupe['cnt']}\n";
    echo "  Keeping: #{$keepId}\n";
    echo "  Deleting: #" . implode(', #', $deleteIds) . "\n";

    if (!empty($deleteIds)) {
        $ph = implode(',', array_fill(0, count($deleteIds), '?'));

        // Delete related records first
        $db->prepare("DELETE FROM requisition_dishes WHERE requisition_id IN ($ph)")->execute($deleteIds);
        $db->prepare("DELETE FROM requisition_lines WHERE requisition_id IN ($ph)")->execute($deleteIds);
        $delStmt = $db->prepare("DELETE FROM requisitions WHERE id IN ($ph) AND status = 'draft'");
        $delStmt->execute($deleteIds);
        $deleted = $delStmt->rowCount();
        $totalDeleted += $deleted;
        echo "  Deleted: {$deleted} requisitions\n\n";
    }
}

echo "=== Total deleted: {$totalDeleted} ===\n";
echo "\nDone! You can now go back to the Order page.\n";
echo "</pre>\n";
