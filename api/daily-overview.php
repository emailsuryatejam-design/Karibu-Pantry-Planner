<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$date = $_GET['date'] ?? todayStr();

// Get all items with activity for this date
// Stock from items table, ordered/received from dish_ingredients via menu plans

// 1. Get all confirmed menu plans for this date (both meals)
$plans = $db->prepare('SELECT id, meal FROM menu_plans WHERE plan_date = ?');
$plans->execute([$date]);
$plans = $plans->fetchAll();
$planIds = array_column($plans, 'id');

// 2. Get all dish ingredients for these plans
$kitchen = [];
if (!empty($planIds)) {
    $placeholders = implode(',', array_fill(0, count($planIds), '?'));
    $stmt = $db->prepare("
        SELECT di.item_id, di.item_name, di.uom, di.is_primary,
               SUM(di.final_qty) as total_qty,
               SUM(di.ordered_qty) as total_ordered,
               SUM(di.received_qty) as total_received,
               SUM(di.stock_qty) as total_stock_recorded
        FROM dish_ingredients di
        JOIN menu_dishes md ON di.dish_id = md.id
        WHERE md.plan_id IN ($placeholders)
          AND di.is_removed = 0
        GROUP BY di.item_id, di.item_name, di.uom, di.is_primary
        ORDER BY di.item_name
    ");
    $stmt->execute($planIds);
    $kitchen = $stmt->fetchAll();
}

// 3. Get grocery orders for this date
$orders = $db->prepare("
    SELECT gol.item_id, gol.item_name, gol.uom,
           SUM(gol.requested_qty) as ordered,
           SUM(gol.approved_qty) as approved,
           SUM(gol.fulfilled_qty) as fulfilled
    FROM grocery_order_lines gol
    JOIN grocery_orders go2 ON gol.order_id = go2.id
    WHERE go2.order_date = ?
    GROUP BY gol.item_id, gol.item_name, gol.uom
");
$orders->execute([$date]);
$orderItems = $orders->fetchAll();

// 4. Merge into unified item list
$itemMap = [];

foreach ($kitchen as $k) {
    $key = $k['item_id'] ?: $k['item_name'];
    if (!isset($itemMap[$key])) {
        $itemMap[$key] = [
            'item_id' => $k['item_id'],
            'name' => $k['item_name'],
            'uom' => $k['uom'],
            'kitchen_qty' => 0,
            'ordered' => 0,
            'received' => 0,
            'stock' => 0,
        ];
    }
    $itemMap[$key]['kitchen_qty'] += (float)$k['total_qty'];
    $itemMap[$key]['ordered'] += (float)($k['total_ordered'] ?? 0);
    $itemMap[$key]['received'] += (float)($k['total_received'] ?? 0);
}

foreach ($orderItems as $o) {
    $key = $o['item_id'] ?: $o['item_name'];
    if (!isset($itemMap[$key])) {
        $itemMap[$key] = [
            'item_id' => $o['item_id'],
            'name' => $o['item_name'],
            'uom' => $o['uom'],
            'kitchen_qty' => 0,
            'ordered' => 0,
            'received' => 0,
            'stock' => 0,
        ];
    }
    $itemMap[$key]['ordered'] += (float)($o['ordered'] ?? 0);
}

// 5. Get current stock for all items
$allItemIds = array_filter(array_column(array_values($itemMap), 'item_id'));
if (!empty($allItemIds)) {
    $placeholders = implode(',', array_fill(0, count($allItemIds), '?'));
    $stockStmt = $db->prepare("SELECT id, stock_qty FROM items WHERE id IN ($placeholders)");
    $stockStmt->execute($allItemIds);
    $stocks = $stockStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($itemMap as &$item) {
        if ($item['item_id'] && isset($stocks[$item['item_id']])) {
            $item['stock'] = (float)$stocks[$item['item_id']];
        }
    }
    unset($item);
}

// Convert to indexed array and sort
$items = array_values($itemMap);
usort($items, function($a, $b) { return strcmp($a['name'], $b['name']); });

// Summary
$summary = [
    'plans' => count($plans),
    'meals' => array_column($plans, 'meal'),
    'total_items' => count($items),
];

jsonResponse([
    'items' => $items,
    'summary' => $summary,
    'date' => $date,
]);
