<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

switch ($action) {

    // ── Get aggregated groceries for a date (all meals combined) ──
    case 'get':
        $date = $_GET['date'] ?? todayStr();

        // Get all confirmed plans for this date (lunch + dinner)
        $plans = $db->prepare("SELECT * FROM menu_plans WHERE plan_date = ? AND status = 'confirmed'");
        $plans->execute([$date]);
        $plans = $plans->fetchAll();

        if (empty($plans)) {
            // Also check draft plans
            $drafts = $db->prepare("SELECT COUNT(*) FROM menu_plans WHERE plan_date = ? AND status = 'draft'");
            $drafts->execute([$date]);
            $hasDrafts = (int)$drafts->fetchColumn() > 0;

            jsonResponse(['plans' => [], 'items' => [], 'order' => null, 'has_drafts' => $hasDrafts]);
        }

        // Aggregate all ingredients across all plans, grouped by item
        $planIds = array_column($plans, 'id');
        $placeholders = implode(',', array_fill(0, count($planIds), '?'));

        $stmt = $db->prepare("
            SELECT
                di.item_id,
                di.item_name,
                di.uom,
                SUM(di.final_qty) as total_qty,
                GROUP_CONCAT(DISTINCT md.dish_name SEPARATOR ', ') as dishes,
                i.stock_qty as current_stock
            FROM dish_ingredients di
            JOIN menu_dishes md ON di.dish_id = md.id
            LEFT JOIN items i ON di.item_id = i.id
            WHERE md.plan_id IN ($placeholders)
              AND di.is_removed = 0
            GROUP BY di.item_id, di.item_name, di.uom, i.stock_qty
            ORDER BY di.item_name
        ");
        $stmt->execute($planIds);
        $items = $stmt->fetchAll();

        // Check for existing order
        $order = $db->prepare('SELECT * FROM grocery_orders WHERE order_date = ? ORDER BY id DESC LIMIT 1');
        $order->execute([$date]);
        $order = $order->fetch();

        // If order exists, get line items with fulfilled/received status
        $orderLines = [];
        if ($order) {
            $lines = $db->prepare('SELECT * FROM grocery_order_lines WHERE order_id = ? ORDER BY id');
            $lines->execute([$order['id']]);
            $orderLines = $lines->fetchAll();
        }

        jsonResponse([
            'plans' => $plans,
            'items' => $items,
            'order' => $order,
            'order_lines' => $orderLines,
        ]);
        break;

    // ── Submit grocery order to store ──
    case 'submit_order':
        requireMethod('POST');
        $date = $input['date'] ?? todayStr();
        $items = $input['items'] ?? [];

        if (empty($items)) jsonError('No items to order');

        // Check for existing pending order
        $existing = $db->prepare("SELECT id FROM grocery_orders WHERE order_date = ? AND status IN ('pending', 'reviewing')");
        $existing->execute([$date]);
        if ($existing->fetch()) {
            jsonError('An order already exists for this date');
        }

        // Create order (no meal split — one order per day)
        $stmt = $db->prepare("INSERT INTO grocery_orders (order_date, meal, total_items, created_by) VALUES (?, 'lunch', ?, ?)");
        $stmt->execute([$date, count($items), $user['id']]);
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

        auditLog('submit_order', 'grocery_orders', $orderId, null, ['date' => $date, 'items' => count($items)]);
        jsonResponse(['order_id' => $orderId]);
        break;

    // ── Chef confirms receipt of items ──
    case 'confirm_receipt':
        requireMethod('POST');
        $orderId = (int)($input['order_id'] ?? 0);
        $lines = $input['lines'] ?? [];
        if (!$orderId) jsonError('Order ID required');

        // Update received qty per line
        $stmt = $db->prepare('UPDATE grocery_order_lines SET fulfilled_qty = ? WHERE id = ? AND order_id = ?');
        foreach ($lines as $line) {
            $stmt->execute([(float)($line['received_qty'] ?? 0), (int)$line['id'], $orderId]);
        }

        // Mark order as received by chef
        $db->prepare("UPDATE grocery_orders SET status = 'received', updated_at = NOW() WHERE id = ?")->execute([$orderId]);

        auditLog('confirm_receipt', 'grocery_orders', $orderId);
        jsonResponse(['confirmed' => true]);
        break;

    // ── Search items for manual add ──
    case 'search_items':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) jsonResponse(['items' => []]);

        $stmt = $db->prepare('SELECT id, name, code, category, uom, stock_qty FROM items WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY name LIMIT 20');
        $stmt->execute(["%$q%", "%$q%"]);
        jsonResponse(['items' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action');
}
