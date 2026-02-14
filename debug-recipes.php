<?php
require_once __DIR__ . '/config.php';
$db = getDB();

header('Content-Type: application/json');

$recipes = $db->query('SELECT id, name, category FROM recipes ORDER BY id')->fetchAll();
$weekly = $db->query('SELECT * FROM weekly_menu ORDER BY id')->fetchAll();

echo json_encode([
    'recipe_count' => count($recipes),
    'recipes' => $recipes,
    'weekly_menu_count' => count($weekly),
    'weekly_menu' => $weekly,
], JSON_PRETTY_PRINT);
