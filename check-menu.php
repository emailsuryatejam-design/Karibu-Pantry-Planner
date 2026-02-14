<?php
require_once __DIR__ . '/config.php';
$db = getDB();
header('Content-Type: text/plain');

$dayOfWeek = (int)date('w'); // 0=Sun, 1=Mon...6=Sat
$dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
echo "Today: " . $dayNames[$dayOfWeek] . " (day_of_week = $dayOfWeek)\n\n";

foreach (['lunch', 'dinner'] as $meal) {
    $stmt = $db->prepare('SELECT wm.*, r.name as recipe_name, r.category FROM weekly_menu wm JOIN recipes r ON wm.recipe_id = r.id WHERE wm.day_of_week = ? AND wm.meal = ? ORDER BY wm.sort_order');
    $stmt->execute([$dayOfWeek, $meal]);
    $items = $stmt->fetchAll();
    echo strtoupper($meal) . " (" . count($items) . " dishes):\n";
    foreach ($items as $item) {
        echo "  - [{$item['category']}] {$item['recipe_name']}\n";
    }
    echo "\n";
}
