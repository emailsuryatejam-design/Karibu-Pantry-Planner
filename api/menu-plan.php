<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];

// Ensure weekly_menu table exists
$db->exec("CREATE TABLE IF NOT EXISTS weekly_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,
    meal ENUM('lunch','dinner') NOT NULL,
    recipe_id INT NOT NULL,
    sort_order INT DEFAULT 0
)");

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

            if (!empty($dishes)) {
                $dishIds = array_column($dishes, 'id');
                $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
                $ingStmt = $db->prepare("SELECT * FROM dish_ingredients WHERE dish_id IN ($placeholders) AND is_removed = 0 ORDER BY id");
                $ingStmt->execute($dishIds);
                $allIngredients = $ingStmt->fetchAll();

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

        // Get fixed menu for this day of week (for auto-loading)
        $dayOfWeek = (int)date('w', strtotime($date));
        $fixedMenu = $db->prepare('SELECT wm.*, r.name as recipe_name, r.category, r.servings as recipe_servings FROM weekly_menu wm JOIN recipes r ON wm.recipe_id = r.id WHERE wm.day_of_week = ? AND wm.meal = ? ORDER BY wm.sort_order, r.category');
        $fixedMenu->execute([$dayOfWeek, $meal]);
        $fixedMenu = $fixedMenu->fetchAll();

        // Get all recipes for add-dish dropdown
        $recipes = $db->query('SELECT id, name, category FROM recipes ORDER BY name')->fetchAll();

        jsonResponse([
            'plan' => $plan,
            'dishes' => $dishes,
            'fixed_menu' => $fixedMenu,
            'recipes' => $recipes,
        ]);
        break;

    // ── Create plan from fixed menu (auto-load weekly rotation) ──
    case 'create_from_fixed':
        requireMethod('POST');
        $date = $input['date'] ?? todayStr();
        $meal = $input['meal'] ?? 'lunch';
        $pax = (int)($input['pax'] ?? 20);

        // Check exists
        $existing = $db->prepare('SELECT id FROM menu_plans WHERE plan_date = ? AND meal = ?');
        $existing->execute([$date, $meal]);
        if ($existing->fetch()) {
            jsonError('Plan already exists for this date and meal');
        }

        // Create plan
        $stmt = $db->prepare('INSERT INTO menu_plans (plan_date, meal, portions, created_by) VALUES (?, ?, ?, ?)');
        $stmt->execute([$date, $meal, $pax, $user['id']]);
        $planId = $db->lastInsertId();

        // Load fixed menu for this day
        $dayOfWeek = (int)date('w', strtotime($date));
        $fixed = $db->prepare('SELECT wm.recipe_id, r.name, r.category, r.servings FROM weekly_menu wm JOIN recipes r ON wm.recipe_id = r.id WHERE wm.day_of_week = ? AND wm.meal = ? ORDER BY wm.sort_order');
        $fixed->execute([$dayOfWeek, $meal]);
        $fixedItems = $fixed->fetchAll();

        // Map recipe category to course
        $catToCourse = [
            'appetizer' => 'appetizer', 'soup' => 'soup', 'salad' => 'salad',
            'main_course' => 'main_course', 'side' => 'side', 'dessert' => 'dessert',
            'beverage' => 'beverage',
        ];

        $dishStmt = $db->prepare('INSERT INTO menu_dishes (plan_id, dish_name, course, portions, recipe_id, is_default) VALUES (?, ?, ?, ?, ?, 1)');
        $ingStmt = $db->prepare('INSERT INTO dish_ingredients (dish_id, item_id, item_name, qty, final_qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)');

        foreach ($fixedItems as $item) {
            $course = $catToCourse[$item['category']] ?? 'main_course';
            $dishStmt->execute([$planId, $item['name'], $course, $pax, $item['recipe_id']]);
            $dishId = $db->lastInsertId();

            // Load recipe ingredients scaled to pax
            $recipeServings = $item['servings'] ?: 4;
            $scale = $pax / $recipeServings;

            $ings = $db->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ?');
            $ings->execute([$item['recipe_id']]);
            foreach ($ings->fetchAll() as $ing) {
                $scaledQty = round($ing['qty'] * $scale, 3);
                $ingStmt->execute([$dishId, $ing['item_id'], $ing['item_name'], $ing['qty'], $scaledQty, $ing['uom'], $ing['is_primary']]);
            }
        }

        auditLog('create_plan_fixed', 'menu_plans', $planId, null, ['date' => $date, 'meal' => $meal, 'pax' => $pax, 'dishes' => count($fixedItems)]);
        jsonResponse(['plan_id' => $planId]);
        break;

    // ── Create blank plan ──
    case 'create_plan':
        requireMethod('POST');
        $date = $input['date'] ?? todayStr();
        $meal = $input['meal'] ?? 'lunch';
        $portions = (int)($input['portions'] ?? 20);

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

    // ── Add dish (with ingredients form) ──
    case 'add_dish':
        requireMethod('POST');
        $planId = (int)($input['plan_id'] ?? 0);
        $course = $input['course'] ?? 'main_course';
        $dishName = trim($input['dish_name'] ?? '');
        $portions = (int)($input['portions'] ?? 20);
        $recipeId = $input['recipe_id'] ?? null;
        $ingredients = $input['ingredients'] ?? [];

        if (!$planId || !$dishName) jsonError('Plan ID and dish name required');

        $stmt = $db->prepare('INSERT INTO menu_dishes (plan_id, dish_name, course, portions, recipe_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$planId, $dishName, $course, $portions, $recipeId]);
        $dishId = $db->lastInsertId();

        // If recipe selected, load recipe ingredients
        if ($recipeId) {
            $recipe = $db->prepare('SELECT servings FROM recipes WHERE id = ?');
            $recipe->execute([$recipeId]);
            $recipeData = $recipe->fetch();
            $scale = $portions / ($recipeData['servings'] ?: 4);

            $ings = $db->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ?');
            $ings->execute([$recipeId]);
            $ingStmt = $db->prepare('INSERT INTO dish_ingredients (dish_id, item_id, item_name, qty, final_qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($ings->fetchAll() as $ing) {
                $scaledQty = round($ing['qty'] * $scale, 3);
                $ingStmt->execute([$dishId, $ing['item_id'], $ing['item_name'], $ing['qty'], $scaledQty, $ing['uom'], $ing['is_primary']]);
            }
        }

        // If custom ingredients provided (for non-recipe dishes)
        if (!$recipeId && !empty($ingredients)) {
            $ingStmt = $db->prepare('INSERT INTO dish_ingredients (dish_id, item_id, item_name, qty, final_qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?, 1)');
            foreach ($ingredients as $ing) {
                $ingStmt->execute([
                    $dishId,
                    $ing['item_id'] ?? null,
                    $ing['item_name'] ?? 'Unknown',
                    (float)($ing['qty'] ?? 0),
                    (float)($ing['qty'] ?? 0),
                    $ing['uom'] ?? 'kg',
                ]);
            }
        }

        auditLog('add_dish', 'menu_dishes', $dishId, null, ['dish_name' => $dishName, 'course' => $course]);
        jsonResponse(['dish_id' => $dishId]);
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

    // ── Update plan pax ──
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

    // ── Search items (for add ingredient) ──
    case 'search_items':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) jsonResponse(['items' => []]);

        $stmt = $db->prepare('SELECT id, name, code, category, uom, stock_qty FROM items WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY name LIMIT 20');
        $stmt->execute(["%$q%", "%$q%"]);
        jsonResponse(['items' => $stmt->fetchAll()]);
        break;

    // ── Get weekly menu config ──
    case 'get_weekly':
        $weeklyMenu = $db->query('SELECT wm.*, r.name as recipe_name, r.category FROM weekly_menu wm JOIN recipes r ON wm.recipe_id = r.id ORDER BY wm.day_of_week, wm.meal, wm.sort_order')->fetchAll();
        $recipes = $db->query('SELECT id, name, category FROM recipes ORDER BY category, name')->fetchAll();
        jsonResponse(['weekly_menu' => $weeklyMenu, 'recipes' => $recipes]);
        break;

    // ── Save weekly menu config ──
    case 'save_weekly':
        requireMethod('POST');
        $day = (int)($input['day_of_week'] ?? 0);
        $meal = $input['meal'] ?? 'lunch';
        $recipeIds = $input['recipe_ids'] ?? [];

        // Clear existing for this day+meal
        $db->prepare('DELETE FROM weekly_menu WHERE day_of_week = ? AND meal = ?')->execute([$day, $meal]);

        // Insert new
        $stmt = $db->prepare('INSERT INTO weekly_menu (day_of_week, meal, recipe_id, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($recipeIds as $i => $rid) {
            $stmt->execute([$day, $meal, (int)$rid, $i]);
        }

        auditLog('save_weekly_menu', 'weekly_menu', null, null, ['day' => $day, 'meal' => $meal, 'count' => count($recipeIds)]);
        jsonResponse(['saved' => true]);
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
