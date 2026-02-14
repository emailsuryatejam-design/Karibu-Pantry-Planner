<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];

switch ($action) {

    // ── Get ingredients for date + meal ──
    case 'get':
        $date = $_GET['date'] ?? todayStr();
        $meal = $_GET['meal'] ?? 'lunch';

        // Get plan
        $plan = $db->prepare('SELECT * FROM menu_plans WHERE plan_date = ? AND meal = ?');
        $plan->execute([$date, $meal]);
        $plan = $plan->fetch();

        if (!$plan) {
            jsonResponse(['plan' => null, 'ingredients' => [], 'order' => null]);
        }

        // Get dishes
        $dishes = $db->prepare('SELECT id, dish_name FROM menu_dishes WHERE plan_id = ?');
        $dishes->execute([$plan['id']]);
        $dishes = $dishes->fetchAll();

        // Flatten primary ingredients
        $ingredients = [];
        foreach ($dishes as $dish) {
            $ings = $db->prepare('SELECT di.*, i.stock_qty as current_stock FROM dish_ingredients di LEFT JOIN items i ON di.item_id = i.id WHERE di.dish_id = ? AND di.is_removed = 0 AND di.is_primary = 1 ORDER BY di.id');
            $ings->execute([$dish['id']]);
            foreach ($ings->fetchAll() as $ing) {
                $ing['dish_name'] = $dish['dish_name'];
                $ing['dish_id'] = $dish['id'];
                if ($ing['current_stock'] !== null) {
                    $ing['stock_qty'] = $ing['current_stock'];
                }
                $ingredients[] = $ing;
            }
        }

        // Check if there's an existing order for this date + meal
        $order = $db->prepare('SELECT * FROM grocery_orders WHERE order_date = ? AND meal = ? ORDER BY id DESC LIMIT 1');
        $order->execute([$date, $meal]);
        $order = $order->fetch();

        jsonResponse([
            'plan' => $plan,
            'dishes' => $dishes,
            'ingredients' => $ingredients,
            'order' => $order,
        ]);
        break;

    // ── Update tracking field (stock/order/received) ──
    case 'update_tracking':
        requireMethod('POST');
        $ingredientId = (int)($input['ingredient_id'] ?? 0);
        $field = $input['field'] ?? '';
        $value = $input['value'] ?? null;

        $allowedFields = ['stock_qty', 'ordered_qty', 'received_qty'];
        if (!$ingredientId || !in_array($field, $allowedFields)) {
            jsonError('Invalid field or ingredient');
        }

        $numVal = $value === '' || $value === null ? null : (float)$value;
        $stmt = $db->prepare("UPDATE dish_ingredients SET $field = ? WHERE id = ?");
        $stmt->execute([$numVal, $ingredientId]);

        jsonResponse(['updated' => true]);
        break;

    // ── Update stock balance ──
    case 'update_stock':
        requireMethod('POST');
        $itemId = (int)($input['item_id'] ?? 0);
        $qty = $input['qty'] ?? 0;
        if (!$itemId) jsonError('Item ID required');

        $numQty = (float)$qty;
        $db->prepare('UPDATE items SET stock_qty = ? WHERE id = ?')->execute([$numQty, $itemId]);

        jsonResponse(['updated' => true]);
        break;

    // ── Search items for manual add ──
    case 'search_items':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) jsonResponse(['items' => []]);

        $stmt = $db->prepare('SELECT id, name, code, category, uom, stock_qty FROM items WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY name LIMIT 20');
        $stmt->execute(["%$q%", "%$q%"]);
        jsonResponse(['items' => $stmt->fetchAll()]);
        break;

    // ── Add manual ingredient ──
    case 'add_ingredient':
        requireMethod('POST');
        $dishId = (int)($input['dish_id'] ?? 0);
        $itemId = (int)($input['item_id'] ?? 0);
        $qty = (float)($input['qty'] ?? 0);
        $uom = $input['uom'] ?? 'kg';

        if (!$dishId || !$itemId || $qty <= 0) jsonError('Invalid input');

        $item = $db->prepare('SELECT name, stock_qty FROM items WHERE id = ?');
        $item->execute([$itemId]);
        $itemData = $item->fetch();
        if (!$itemData) jsonError('Item not found');

        $stmt = $db->prepare('INSERT INTO dish_ingredients (dish_id, item_id, item_name, qty, final_qty, uom, is_primary, stock_qty) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
        $stmt->execute([$dishId, $itemId, $itemData['name'], $qty, $qty, $uom, $itemData['stock_qty']]);

        jsonResponse(['id' => $db->lastInsertId()]);
        break;

    // ── Submit order to storekeeper ──
    case 'submit_order':
        requireMethod('POST');
        $date = $input['date'] ?? todayStr();
        $meal = $input['meal'] ?? 'lunch';
        $items = $input['items'] ?? [];

        if (empty($items)) jsonError('No items to order');

        // Check for existing pending order
        $existing = $db->prepare("SELECT id FROM grocery_orders WHERE order_date = ? AND meal = ? AND status IN ('pending', 'reviewing')");
        $existing->execute([$date, $meal]);
        if ($existing->fetch()) {
            jsonError('An order already exists for this date and meal');
        }

        // Create order
        $stmt = $db->prepare('INSERT INTO grocery_orders (order_date, meal, total_items, created_by, notes) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$date, $meal, count($items), $user['id'], $input['notes'] ?? null]);
        $orderId = $db->lastInsertId();

        // Add lines
        $lineStmt = $db->prepare('INSERT INTO grocery_order_lines (order_id, item_id, item_name, requested_qty, uom) VALUES (?, ?, ?, ?, ?)');
        foreach ($items as $item) {
            $lineStmt->execute([
                $orderId,
                $item['item_id'] ?? null,
                $item['item_name'] ?? 'Unknown',
                (float)($item['qty'] ?? 0),
                $item['uom'] ?? 'kg',
            ]);
        }

        auditLog('submit_order', 'grocery_orders', $orderId, null, ['date' => $date, 'meal' => $meal, 'items' => count($items)]);
        jsonResponse(['order_id' => $orderId]);
        break;

    default:
        jsonError('Unknown action');
}
