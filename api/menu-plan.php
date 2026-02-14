<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];

switch ($action) {

    // ── Get plan by date + meal ──
    case 'get':
        $date = $_GET['date'] ?? todayStr();
        $meal = $_GET['meal'] ?? 'lunch';

        $plan = $db->prepare('SELECT * FROM menu_plans WHERE plan_date = ? AND meal = ?');
        $plan->execute([$date, $meal]);
        $plan = $plan->fetch();

        $dishes = [];
        if ($plan) {
            $stmt = $db->prepare('SELECT d.*, r.name as recipe_name FROM menu_dishes d LEFT JOIN recipes r ON d.recipe_id = r.id WHERE d.plan_id = ? ORDER BY FIELD(d.course, "appetizer","soup","salad","main_course","side","dessert","beverage"), d.id');
            $stmt->execute([$plan['id']]);
            $dishes = $stmt->fetchAll();

            // Single query for all ingredients across all dishes
            if (!empty($dishes)) {
                $dishIds = array_column($dishes, 'id');
                $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
                $ingStmt = $db->prepare("SELECT * FROM dish_ingredients WHERE dish_id IN ($placeholders) ORDER BY id");
                $ingStmt->execute($dishIds);
                $allIngredients = $ingStmt->fetchAll();

                // Group by dish_id
                $ingByDish = [];
                foreach ($allIngredients as $ing) {
                    $ingByDish[$ing['dish_id']][] = $ing;
                }
                foreach ($dishes as &$dish) {
                    $dish['ingredients'] = $ingByDish[$dish['id']] ?? [];
                }
                unset($dish);
            }
        }

        // Get all recipes for dropdown — simple fast query
        $recipes = $db->query('SELECT id, name, category FROM recipes ORDER BY name')->fetchAll();

        jsonResponse([
            'plan' => $plan,
            'dishes' => $dishes,
            'recipes' => $recipes,
        ]);
        break;

    // ── Create plan ──
    case 'create_plan':
        requireMethod('POST');
        $date = $input['date'] ?? todayStr();
        $meal = $input['meal'] ?? 'lunch';
        $portions = (int)($input['portions'] ?? 20);

        // Check if already exists
        $existing = $db->prepare('SELECT id FROM menu_plans WHERE plan_date = ? AND meal = ?');
        $existing->execute([$date, $meal]);
        if ($existing->fetch()) {
            jsonError('Plan already exists for this date and meal');
        }

        $stmt = $db->prepare('INSERT INTO menu_plans (plan_date, meal, portions, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$date, $meal, $portions, $user['id']]);
        $planId = $db->lastInsertId();

        auditLog('create_plan', 'menu_plans', $planId, null, ['date' => $date, 'meal' => $meal, 'portions' => $portions]);
        jsonResponse(['plan_id' => $planId]);
        break;

    // ── Add dish ──
    case 'add_dish':
        requireMethod('POST');
        $planId = (int)($input['plan_id'] ?? 0);
        $course = $input['course'] ?? 'main_course';
        $dishName = trim($input['dish_name'] ?? '');
        $portions = (int)($input['portions'] ?? 20);
        $recipeId = $input['recipe_id'] ?? null;

        if (!$planId || !$dishName) jsonError('Plan ID and dish name required');

        $stmt = $db->prepare('INSERT INTO menu_dishes (plan_id, dish_name, course, portions, recipe_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$planId, $dishName, $course, $portions, $recipeId]);
        $dishId = $db->lastInsertId();

        auditLog('add_dish', 'menu_dishes', $dishId, null, ['dish_name' => $dishName, 'course' => $course]);
        jsonResponse(['dish_id' => $dishId]);
        break;

    // ── Load recipe ingredients into dish ──
    case 'load_recipe':
        requireMethod('POST');
        $dishId = (int)($input['dish_id'] ?? 0);
        $recipeId = (int)($input['recipe_id'] ?? 0);
        $portions = (int)($input['portions'] ?? 20);

        if (!$dishId || !$recipeId) jsonError('Dish ID and recipe ID required');

        // Get recipe servings for scaling
        $recipe = $db->prepare('SELECT servings FROM recipes WHERE id = ?');
        $recipe->execute([$recipeId]);
        $recipe = $recipe->fetch();
        $servings = $recipe['servings'] ?: 4;
        $scale = $portions / $servings;

        // Get recipe ingredients
        $ings = $db->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ?');
        $ings->execute([$recipeId]);
        $ingredients = $ings->fetchAll();

        // Clear existing ingredients for this dish
        $db->prepare('DELETE FROM dish_ingredients WHERE dish_id = ?')->execute([$dishId]);

        // Insert scaled ingredients
        $stmt = $db->prepare('INSERT INTO dish_ingredients (dish_id, item_id, item_name, qty, final_qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($ingredients as $ing) {
            $scaledQty = round($ing['qty'] * $scale, 3);
            $stmt->execute([
                $dishId,
                $ing['item_id'],
                $ing['item_name'],
                $ing['qty'],
                $scaledQty,
                $ing['uom'],
                $ing['is_primary'],
            ]);
        }

        auditLog('load_recipe', 'menu_dishes', $dishId, null, ['recipe_id' => $recipeId, 'portions' => $portions, 'scale' => $scale]);
        jsonResponse(['loaded' => count($ingredients)]);
        break;

    // ── Remove dish ──
    case 'remove_dish':
        requireMethod('POST');
        $dishId = (int)($input['dish_id'] ?? 0);
        if (!$dishId) jsonError('Dish ID required');

        $dish = $db->prepare('SELECT dish_name FROM menu_dishes WHERE id = ?');
        $dish->execute([$dishId]);
        $dishData = $dish->fetch();

        $db->prepare('DELETE FROM menu_dishes WHERE id = ?')->execute([$dishId]);
        auditLog('remove_dish', 'menu_dishes', $dishId, ['dish_name' => $dishData['dish_name'] ?? ''], null);
        jsonResponse(['removed' => true]);
        break;

    // ── Update dish portions ──
    case 'update_portions':
        requireMethod('POST');
        $dishId = (int)($input['dish_id'] ?? 0);
        $portions = (int)($input['portions'] ?? 20);
        if (!$dishId || $portions < 1) jsonError('Invalid input');

        $db->prepare('UPDATE menu_dishes SET portions = ? WHERE id = ?')->execute([$portions, $dishId]);

        // Re-scale ingredients
        $dish = $db->prepare('SELECT recipe_id FROM menu_dishes WHERE id = ?');
        $dish->execute([$dishId]);
        $dishData = $dish->fetch();

        if ($dishData['recipe_id']) {
            $recipe = $db->prepare('SELECT servings FROM recipes WHERE id = ?');
            $recipe->execute([$dishData['recipe_id']]);
            $recipeData = $recipe->fetch();
            $scale = $portions / ($recipeData['servings'] ?: 4);

            $ings = $db->prepare('SELECT id, qty FROM dish_ingredients WHERE dish_id = ? AND is_removed = 0');
            $ings->execute([$dishId]);
            $updateStmt = $db->prepare('UPDATE dish_ingredients SET final_qty = ? WHERE id = ?');
            foreach ($ings->fetchAll() as $ing) {
                $updateStmt->execute([round($ing['qty'] * $scale, 3), $ing['id']]);
            }
        }

        auditLog('update_portions', 'menu_dishes', $dishId, null, ['portions' => $portions]);
        jsonResponse(['updated' => true]);
        break;

    // ── Update plan pax (total covers) ──
    case 'update_plan_pax':
        requireMethod('POST');
        $planId = (int)($input['plan_id'] ?? 0);
        $pax = (int)($input['pax'] ?? 20);
        if (!$planId || $pax < 1) jsonError('Invalid input');

        $db->prepare('UPDATE menu_plans SET portions = ? WHERE id = ?')->execute([$pax, $planId]);
        auditLog('update_plan_pax', 'menu_plans', $planId, null, ['pax' => $pax]);
        jsonResponse(['updated' => true]);
        break;

    // ── Confirm plan ──
    case 'confirm_plan':
        requireMethod('POST');
        $planId = (int)($input['plan_id'] ?? 0);
        if (!$planId) jsonError('Plan ID required');

        $db->prepare("UPDATE menu_plans SET status = 'confirmed' WHERE id = ?")->execute([$planId]);
        auditLog('confirm_plan', 'menu_plans', $planId, ['status' => 'draft'], ['status' => 'confirmed']);
        jsonResponse(['confirmed' => true]);
        break;

    // ── Reopen plan ──
    case 'reopen_plan':
        requireMethod('POST');
        $planId = (int)($input['plan_id'] ?? 0);
        if (!$planId) jsonError('Plan ID required');

        $db->prepare("UPDATE menu_plans SET status = 'draft' WHERE id = ?")->execute([$planId]);
        auditLog('reopen_plan', 'menu_plans', $planId, ['status' => 'confirmed'], ['status' => 'draft']);
        jsonResponse(['reopened' => true]);
        break;

    // ── Add ingredient manually ──
    case 'add_ingredient':
        requireMethod('POST');
        $dishId = (int)($input['dish_id'] ?? 0);
        $itemId = $input['item_id'] ?? null;
        $qty = (float)($input['qty'] ?? 0);
        $uom = $input['uom'] ?? 'kg';

        if (!$dishId || $qty <= 0) jsonError('Dish ID and quantity required');

        // Get item name
        $itemName = $input['item_name'] ?? '';
        if ($itemId && !$itemName) {
            $item = $db->prepare('SELECT name FROM items WHERE id = ?');
            $item->execute([$itemId]);
            $itemData = $item->fetch();
            $itemName = $itemData['name'] ?? 'Unknown';
        }

        $stmt = $db->prepare('INSERT INTO dish_ingredients (dish_id, item_id, item_name, qty, final_qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $stmt->execute([$dishId, $itemId, $itemName, $qty, $qty, $uom]);

        jsonResponse(['ingredient_id' => $db->lastInsertId()]);
        break;

    // ── Search items ──
    case 'search_items':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) jsonResponse(['items' => []]);

        $stmt = $db->prepare('SELECT id, name, code, category, uom, stock_qty FROM items WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY name LIMIT 20');
        $stmt->execute(["%$q%", "%$q%"]);
        jsonResponse(['items' => $stmt->fetchAll()]);
        break;

    // ── Audit log ──
    case 'audit':
        $planId = (int)($_GET['plan_id'] ?? 0);
        if (!$planId) jsonResponse(['audit' => []]);

        $stmt = $db->prepare("SELECT a.*, md.dish_name FROM audit_log a LEFT JOIN menu_dishes md ON a.entity = 'menu_dishes' AND a.entity_id = md.id WHERE (a.entity = 'menu_plans' AND a.entity_id = ?) OR (a.entity = 'menu_dishes' AND a.entity_id IN (SELECT id FROM menu_dishes WHERE plan_id = ?)) ORDER BY a.created_at DESC LIMIT 50");
        $stmt->execute([$planId, $planId]);
        jsonResponse(['audit' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action');
}
