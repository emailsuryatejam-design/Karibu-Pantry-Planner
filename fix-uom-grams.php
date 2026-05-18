<?php
// One-time migration: normalise 'grams' → 'g' in items and requisition_lines
// Run once, then delete this file.
require_once __DIR__ . '/config.php';

$db = getDB();

$itemsFixed = $db->exec("UPDATE items SET uom = 'g' WHERE uom = 'grams'");
$linesFixed = $db->exec("UPDATE requisition_lines SET uom = 'g' WHERE uom = 'grams'");

echo "Fixed items: $itemsFixed<br>";
echo "Fixed requisition_lines: $linesFixed<br>";
echo "Done. Delete this file now.";
