<?php
/**
 * Karibu Pantry Planner — Rotational Set Menu API
 *
 * Actions:
 *   get_week                  — full week menu for all types (admin config view)
 *   get_day                   — menu for a specific day + type (requisition auto-fill)
 *   get_day_with_ingredients  — menu + recipe ingredients in one call (batch, no N+1)
 *   add_dish                  — add a recipe to a day/type slot (admin)
 *   remove_dish               — remove a recipe from a day/type slot (admin)
 *   reorder                   — update sort_order for dishes in a day/type (admin)
 *   copy_day                  — copy all dishes from one day to another (admin)
 *   clear_day                 — remove all dishes for a day/type (admin)
 *   search_recipes            — search recipes for the picker
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$action = $_GET['action'] ?? '';
$db = getDB();

switch ($action) {

    // ── Get full week menu (all days × all types) ──
    case 'get_week':
        requireAuth();

        $stmt = $db->query("SELECT sm.*, r.servings AS recipe_servings, r.cuisine,
            (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = sm.recipe_id) AS ingredient_count
            FROM set_menu_items sm
            LEFT JOIN recipes r ON r.id = sm.recipe_id
            WHERE sm.is_active = 1
            ORDER BY sm.day_of_week, sm.type_code, sm.sort_order, sm.recipe_name");
        $rows = $stmt->fetchAll();

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

    // ── Get menu for a specific day + type ──
    case 'get_day':
        requireAuth();

        $dayOfWeek = (int)($_GET['day'] ?? 0);
        $typeCode = trim($_GET['type'] ?? '');
        if ($dayOfWeek < 1 || $dayOfWeek > 7) jsonError('Invalid day (1=Mon ... 7=Sun)');
        if (!$typeCode) jsonError('Type code required');

        $stmt = $db->prepare("SELECT sm.recipe_id, sm.recipe_name, sm.sort_order,
            r.servings AS recipe_servings, r.cuisine,
            (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = sm.recipe_id) AS ingredient_count
            FROM set_menu_items sm
            LEFT JOIN recipes r ON r.id = sm.recipe_id
            WHERE sm.day_of_week = ? AND sm.type_code = ? AND sm.is_active = 1
            ORDER BY sm.sort_order, sm.recipe_name");
        $stmt->execute([$dayOfWeek, $typeCode]);

        jsonResponse(['dishes' => $stmt->fetchAll(), 'day' => $dayOfWeek, 'type' => $typeCode]);

    // ── Get day menu WITH recipe ingredients (batch — eliminates N+1) ──
    case 'get_day_with_ingredients':
        $user = requireAuth();
        $kitchenId = $user['kitchen_id'] ?? null;

        $dayOfWeek = (int)($_GET['day'] ?? 0);
        $typeCode = trim($_GET['type'] ?? '');
        if ($dayOfWeek < 1 || $dayOfWeek > 7) jsonError('Invalid day (1=Mon ... 7=Sun)');
        if (!$typeCode) jsonError('Type code required');

        $stmt = $db->prepare("SELECT sm.recipe_id, sm.recipe_name, sm.sort_order,
            r.servings AS recipe_servings, r.cuisine
            FROM set_menu_items sm
            LEFT JOIN recipes r ON r.id = sm.recipe_id
            WHERE sm.day_of_week = ? AND sm.type_code = ? AND sm.is_active = 1
            ORDER BY sm.sort_order, sm.recipe_name");
        $stmt->execute([$dayOfWeek, $typeCode]);
        $dishes = $stmt->fetchAll();

        if (empty($dishes)) {
            jsonResponse(['dishes' => [], 'ingredients_by_recipe' => new \stdClass(), 'day' => $dayOfWeek, 'type' => $typeCode]);
        }

        // Batch-load all recipe ingredients in ONE query
        $recipeIds = array_unique(array_column($dishes, 'recipe_id'));
        $ph = implode(',', array_fill(0, count($recipeIds), '?'));
        $iStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
            i.name AS item_name, COALESCE(ki.qty, 0) AS stock_qty, i.portion_weight, i.order_mode, i.category
            FROM recipe_ingredients ri
            LEFT JOIN items i ON i.id = ri.item_id
            LEFT JOIN kitchen_inventory ki ON ki.item_id = ri.item_id AND ki.kitchen_id = ?
            WHERE ri.recipe_id IN ($ph)
            ORDER BY ri.recipe_id, ri.is_primary DESC, i.name");
        $iStmt->execute(array_merge([$kitchenId], array_values($recipeIds)));

        $ingredientsByRecipe = [];
        foreach ($iStmt->fetchAll() as $ing) {
            $ingredientsByRecipe[$ing['recipe_id']][] = $ing;
        }

        jsonResponse([
            'dishes' => $dishes,
            'ingredients_by_recipe' => $ingredientsByRecipe ?: new \stdClass(),
            'day' => $dayOfWeek,
            'type' => $typeCode
        ]);

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

    // ── Copy dishes from one day to another ──
    case 'copy_day':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();

        $fromDay = (int)($data['from_day'] ?? 0);
        $toDay = (int)($data['to_day'] ?? 0);
        $typeCode = trim($data['type_code'] ?? '');

        if ($fromDay < 1 || $fromDay > 7 || $toDay < 1 || $toDay > 7) jsonError('Invalid day');
        if ($fromDay === $toDay) jsonError('Cannot copy to same day');

        // Build query based on whether typeCode is specified
        if ($typeCode) {
            $src = $db->prepare("SELECT recipe_id, recipe_name, type_code, sort_order
                FROM set_menu_items WHERE day_of_week = ? AND type_code = ? AND is_active = 1
                ORDER BY sort_order");
            $src->execute([$fromDay, $typeCode]);
        } else {
            $src = $db->prepare("SELECT recipe_id, recipe_name, type_code, sort_order
                FROM set_menu_items WHERE day_of_week = ? AND is_active = 1
                ORDER BY type_code, sort_order");
            $src->execute([$fromDay]);
        }
        $srcDishes = $src->fetchAll();

        if (empty($srcDishes)) jsonError('No dishes to copy from that day');

        $inserted = 0;
        $insertStmt = $db->prepare("INSERT IGNORE INTO set_menu_items (day_of_week, type_code, recipe_id, recipe_name, sort_order) VALUES (?, ?, ?, ?, ?)");

        foreach ($srcDishes as $d) {
            $insertStmt->execute([$toDay, $d['type_code'], $d['recipe_id'], $d['recipe_name'], $d['sort_order']]);
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

        $escaped = escapeLike($q);
        $stmt = $db->prepare("SELECT id, name, cuisine, servings,
            (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = recipes.id) AS ingredient_count
            FROM recipes WHERE is_active = 1 AND (name LIKE ? OR cuisine LIKE ?)
            ORDER BY name LIMIT 20");
        $stmt->execute(["%$escaped%", "%$escaped%"]);

        jsonResponse(['recipes' => $stmt->fetchAll()]);

    default:
        jsonError('Unknown action', 400);
}
