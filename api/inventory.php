<?php
/**
 * Karibu Pantry Planner — Kitchen Inventory API
 * Stock levels + movement log derived from requisition activity
 */
require_once __DIR__ . '/../auth.php';

$user = requireAuth();
$db = getDB();
$kitchenId = $user['kitchen_id'] ?? null;

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? 'stock';

switch ($action) {

    // ── Current stock levels ──
    case 'stock':
        $q = trim($_GET['q'] ?? '');
        $cat = trim($_GET['category'] ?? '');
        $lowOnly = ($_GET['low'] ?? '') === '1';

        $sql = "SELECT id, name, code, category, uom, stock_qty, portion_weight, is_active FROM items WHERE is_active = 1";
        $params = [];

        if ($q) {
            $sql .= " AND (name LIKE ? OR code LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if ($cat) {
            $sql .= " AND category = ?";
            $params[] = $cat;
        }
        if ($lowOnly) {
            $sql .= " AND stock_qty > 0 AND stock_qty <= 2";
        }
        $sql .= " ORDER BY category, name";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Get categories for filter
        $cats = $db->query("SELECT DISTINCT category FROM items WHERE is_active = 1 AND category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

        // Summary stats
        $totalItems = count($items);
        $inStock = 0;
        $outOfStock = 0;
        $lowStock = 0;
        foreach ($items as $item) {
            $qty = (float)$item['stock_qty'];
            if ($qty <= 0) $outOfStock++;
            elseif ($qty <= 2) $lowStock++;
            else $inStock++;
        }

        jsonResponse([
            'items' => $items,
            'categories' => $cats,
            'stats' => [
                'total' => $totalItems,
                'in_stock' => $inStock,
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ]
        ]);
        break;

    // ── Movement log for a specific item ──
    case 'movements':
        $itemId = (int)($_GET['item_id'] ?? 0);
        if (!$itemId) jsonError('Item ID required');

        $days = (int)($_GET['days'] ?? 7);
        if ($days < 1) $days = 7;
        if ($days > 30) $days = 30;

        $since = date('Y-m-d', strtotime("-{$days} days"));

        // Get requisition line activity for this item in this kitchen
        $sql = "SELECT r.req_date, r.meals, r.status, r.supplement_number,
                    rl.order_qty, rl.fulfilled_qty, rl.received_qty, rl.unused_qty,
                    u.name AS chef_name
                FROM requisition_lines rl
                JOIN requisitions r ON r.id = rl.requisition_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE rl.item_id = ?
                AND r.kitchen_id = ?
                AND r.req_date >= ?
                AND r.status IN ('submitted','fulfilled','received','closed')
                ORDER BY r.req_date DESC, r.session_number ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute([$itemId, $kitchenId, $since]);
        $movements = $stmt->fetchAll();

        // Get item info
        $item = $db->prepare("SELECT id, name, uom, stock_qty, category FROM items WHERE id = ?");
        $item->execute([$itemId]);

        jsonResponse([
            'item' => $item->fetch(),
            'movements' => $movements,
        ]);
        break;

    // ── Adjust stock manually (storekeeper/admin) ──
    case 'adjust':
        requireMethod('POST');
        if (!in_array($user['role'], ['chef', 'storekeeper', 'admin'])) {
            jsonError('Only chefs, storekeepers and admins can adjust stock');
        }

        $itemId = (int)($input['item_id'] ?? 0);
        $adjustment = (float)($input['adjustment'] ?? 0);
        $reason = trim($input['reason'] ?? '');

        if (!$itemId) jsonError('Item ID required');
        if ($adjustment == 0) jsonError('Adjustment cannot be zero');
        if (!$reason) jsonError('Reason is required');

        $db->prepare("UPDATE items SET stock_qty = GREATEST(0, stock_qty + ?) WHERE id = ?")->execute([$adjustment, $itemId]);

        // Get updated stock
        $newStock = $db->prepare("SELECT stock_qty FROM items WHERE id = ?");
        $newStock->execute([$itemId]);
        $newQty = $newStock->fetchColumn();

        auditLog('stock_adjust', 'items', $itemId, null, [
            'adjustment' => $adjustment,
            'new_stock' => $newQty,
            'reason' => $reason,
            'kitchen_id' => $kitchenId,
        ]);

        jsonResponse(['updated' => true, 'new_stock' => (float)$newQty]);
        break;

    // ── Stock discrepancy report ──
    case 'discrepancies':
        $from = trim($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
        $to = trim($_GET['to'] ?? date('Y-m-d'));
        $itemFilter = (int)($_GET['item_id'] ?? 0);

        $sql = "SELECT al.created_at, al.user_name, al.entity_id AS item_id, al.new_value,
                    i.name AS item_name, i.category
                FROM audit_log al
                JOIN items i ON i.id = al.entity_id
                WHERE al.action = 'stock_adjust'
                AND DATE(al.created_at) BETWEEN ? AND ?";
        $params = [$from, $to];

        if ($kitchenId) {
            $sql .= " AND JSON_EXTRACT(al.new_value, '$.kitchen_id') = ?";
            $params[] = $kitchenId;
        }
        if ($itemFilter) {
            $sql .= " AND al.entity_id = ?";
            $params[] = $itemFilter;
        }
        $sql .= " ORDER BY al.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Parse JSON new_value and build clean results
        $results = [];
        $totalPositive = 0;
        $totalNegative = 0;
        foreach ($rows as $row) {
            $data = json_decode($row['new_value'], true) ?: [];
            $adj = (float)($data['adjustment'] ?? 0);
            if ($adj > 0) $totalPositive += $adj;
            else $totalNegative += $adj;
            $results[] = [
                'date' => $row['created_at'],
                'item_id' => (int)$row['item_id'],
                'item_name' => $row['item_name'],
                'category' => $row['category'],
                'adjustment' => $adj,
                'new_stock' => (float)($data['new_stock'] ?? 0),
                'reason' => $data['reason'] ?? '',
                'adjusted_by' => $row['user_name'],
            ];
        }

        jsonResponse([
            'discrepancies' => $results,
            'summary' => [
                'total_count' => count($results),
                'total_positive' => $totalPositive,
                'total_negative' => $totalNegative,
                'net_change' => $totalPositive + $totalNegative,
            ],
        ]);
        break;

    default:
        jsonError('Unknown action');
}
