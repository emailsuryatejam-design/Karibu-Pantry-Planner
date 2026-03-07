<?php
/**
 * Karibu Pantry Planner — Rotational Set Menu API
 *
 * Actions:
 *   get_week          — full week menu for all types (admin config view)
 *   get_day            — menu for a specific day + type (requisition auto-fill)
 *   add_dish           — add a recipe to a day/type slot (admin)
 *   remove_dish        — remove a recipe from a day/type slot (admin)
 *   reorder            — update sort_order for dishes in a day/type (admin)
 *   copy_day           — copy all dishes from one day to another (admin)
 *   clear_day          — remove all dishes for a day/type (admin)
 *   search_recipes     — search recipes for the picker
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$action = $_GET['action'] ?? '';
$db = getDB();

switch ($action) {

    // ── Get full week menu (all days × all types) ──
    case 'get_week':
        requireAuth();

        try {
            $stmt = $db->query("SELECT sm.*, r.servings AS recipe_servings, r.cuisine,
                (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = sm.recipe_id) AS ingredient_count
                FROM set_menu_items sm
                LEFT JOIN recipes r ON r.id = sm.recipe_id
                WHERE sm.is_active = 1
                ORDER BY sm.day_of_week, sm.type_code, sm.sort_order, sm.recipe_name");
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Table might not exist yet — auto-create it
            $db->exec("CREATE TABLE IF NOT EXISTS set_menu_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                day_of_week TINYINT NOT NULL,
                type_code VARCHAR(50) NOT NULL,
                recipe_id INT NOT NULL,
                recipe_name VARCHAR(200) NOT NULL,
                sort_order INT DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_day_type (day_of_week, type_code),
                UNIQUE KEY uk_day_type_recipe (day_of_week, type_code, recipe_id)
            )");
            $rows = [];
        }

        // Group by day → type → dishes
        $week = [];
        foreach ($rows as $r) {
            $day = (int)$r['day_of_week'];
            $type = $r['type_code'];
            if (!isset($week[$day])) $week[$day] = [];
            if (!isset($week[$day][$type])) $week[$day][$type] = [];
            $week[$day][$type][] = $r;
        }

        jsonResponse(['week' => $week]);

    // ── Get menu for a specific day + type (for requisition auto-fill) ──
    case 'get_day':
        requireAuth();

        $dayOfWeek = (int)($_GET['day'] ?? 0);
        $typeCode = trim($_GET['type'] ?? '');
        if ($dayOfWeek < 1 || $dayOfWeek > 7) jsonError('Invalid day (1=Mon ... 7=Sun)');
        if (!$typeCode) jsonError('Type code required');

        try {
            $stmt = $db->prepare("SELECT sm.recipe_id, sm.recipe_name, sm.sort_order,
                r.servings AS recipe_servings, r.cuisine,
                (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = sm.recipe_id) AS ingredient_count
                FROM set_menu_items sm
                LEFT JOIN recipes r ON r.id = sm.recipe_id
                WHERE sm.day_of_week = ? AND sm.type_code = ? AND sm.is_active = 1
                ORDER BY sm.sort_order, sm.recipe_name");
            $stmt->execute([$dayOfWeek, $typeCode]);
            $dishes = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Table might not exist yet — return empty
            $dishes = [];
        }

        jsonResponse(['dishes' => $dishes, 'day' => $dayOfWeek, 'type' => $typeCode]);

    // ── Add a dish to a day/type slot ──
    case 'add_dish':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();

        $dayOfWeek = (int)($data['day_of_week'] ?? 0);
        $typeCode = trim($data['type_code'] ?? '');
        $recipeId = (int)($data['recipe_id'] ?? 0);
        $recipeName = trim($data['recipe_name'] ?? '');

        if ($dayOfWeek < 1 || $dayOfWeek > 7) jsonError('Invalid day');
        if (!$typeCode) jsonError('Type code required');
        if (!$recipeId) jsonError('Recipe ID required');
        if (!$recipeName) jsonError('Recipe name required');

        // Check duplicate
        $check = $db->prepare("SELECT id FROM set_menu_items WHERE day_of_week = ? AND type_code = ? AND recipe_id = ?");
        $check->execute([$dayOfWeek, $typeCode, $recipeId]);
        if ($check->fetch()) jsonError('This dish is already on this day/type');

        // Get next sort order
        $maxSort = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM set_menu_items WHERE day_of_week = ? AND type_code = ?");
        $maxSort->execute([$dayOfWeek, $typeCode]);
        $nextSort = (int)$maxSort->fetchColumn() + 1;

        $stmt = $db->prepare("INSERT INTO set_menu_items (day_of_week, type_code, recipe_id, recipe_name, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$dayOfWeek, $typeCode, $recipeId, $recipeName, $nextSort]);
        $id = (int)$db->lastInsertId();

        auditLog('set_menu_add', 'set_menu_item', $id, null, [
            'day' => $dayOfWeek, 'type' => $typeCode, 'recipe' => $recipeName
        ]);

        cacheClear('set_menu');
        jsonResponse(['added' => true, 'id' => $id]);

    // ── Remove a dish from a day/type slot ──
    case 'remove_dish':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();

        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonError('ID required');

        $db->prepare("DELETE FROM set_menu_items WHERE id = ?")->execute([$id]);

        auditLog('set_menu_remove', 'set_menu_item', $id);
        cacheClear('set_menu');
        jsonResponse(['removed' => true]);

    // ── Reorder dishes within a day/type ──
    case 'reorder':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();
        $items = $data['items'] ?? [];

        $stmt = $db->prepare("UPDATE set_menu_items SET sort_order = ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
        }

        cacheClear('set_menu');
        jsonResponse(['reordered' => true]);

    // ── Copy all dishes from one day to another ──
    case 'copy_day':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();

        $fromDay = (int)($data['from_day'] ?? 0);
        $toDay = (int)($data['to_day'] ?? 0);
        $typeCode = trim($data['type_code'] ?? '');

        if ($fromDay < 1 || $fromDay > 7 || $toDay < 1 || $toDay > 7) jsonError('Invalid day');
        if ($fromDay === $toDay) jsonError('Cannot copy to same day');

        // Get source dishes
        $src = $db->prepare("SELECT recipe_id, recipe_name, sort_order FROM set_menu_items WHERE day_of_week = ? AND type_code = ? AND is_active = 1 ORDER BY sort_order");
        if ($typeCode) {
            $src->execute([$fromDay, $typeCode]);
        } else {
            // Copy all types
            $src = $db->prepare("SELECT recipe_id, recipe_name, type_code, sort_order FROM set_menu_items WHERE day_of_week = ? AND is_active = 1 ORDER BY type_code, sort_order");
            $src->execute([$fromDay]);
        }
        $srcDishes = $src->fetchAll();

        if (empty($srcDishes)) jsonError('No dishes to copy from that day');

        $inserted = 0;
        $insertStmt = $db->prepare("INSERT IGNORE INTO set_menu_items (day_of_week, type_code, recipe_id, recipe_name, sort_order) VALUES (?, ?, ?, ?, ?)");

        foreach ($srcDishes as $d) {
            $tc = $typeCode ?: $d['type_code'];
            $insertStmt->execute([$toDay, $tc, $d['recipe_id'], $d['recipe_name'], $d['sort_order']]);
            if ($insertStmt->rowCount() > 0) $inserted++;
        }

        auditLog('set_menu_copy_day', 'set_menu', null, null, [
            'from' => $fromDay, 'to' => $toDay, 'type' => $typeCode ?: 'all', 'inserted' => $inserted
        ]);

        cacheClear('set_menu');
        jsonResponse(['copied' => true, 'inserted' => $inserted]);

    // ── Clear all dishes for a day/type ──
    case 'clear_day':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();

        $dayOfWeek = (int)($data['day_of_week'] ?? 0);
        $typeCode = trim($data['type_code'] ?? '');
        if ($dayOfWeek < 1 || $dayOfWeek > 7) jsonError('Invalid day');
        if (!$typeCode) jsonError('Type code required');

        $stmt = $db->prepare("DELETE FROM set_menu_items WHERE day_of_week = ? AND type_code = ?");
        $stmt->execute([$dayOfWeek, $typeCode]);

        auditLog('set_menu_clear', 'set_menu', null, null, ['day' => $dayOfWeek, 'type' => $typeCode]);
        cacheClear('set_menu');
        jsonResponse(['cleared' => true]);

    // ── Search recipes for picker ──
    case 'search_recipes':
        requireAuth();
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) jsonError('Search query too short');

        $stmt = $db->prepare("SELECT id, name, cuisine, servings,
            (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = recipes.id) AS ingredient_count
            FROM recipes WHERE is_active = 1 AND (name LIKE ? OR cuisine LIKE ?)
            ORDER BY name LIMIT 20");
        $stmt->execute(["%$q%", "%$q%"]);

        jsonResponse(['recipes' => $stmt->fetchAll()]);

    default:
        jsonError('Unknown action', 400);
}
