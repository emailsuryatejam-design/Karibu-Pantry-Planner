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
            $escaped = escapeLike($q);
            $sql .= " AND (name LIKE ? OR code LIKE ?)";
            $params[] = "%$escaped%";
            $params[] = "%$escaped%";
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

        // Get requisition line activity for this item in this kitchen (exclude removed lines)
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
                AND rl.status != 'rejected'
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

    // ── Adjust kitchen pantry stock (chef/storekeeper/admin) ──
    case 'adjust':
        requireMethod('POST');
        if (!in_array($user['role'], ['chef', 'storekeeper', 'admin'])) {
            jsonError('Only chefs, storekeepers and admins can adjust stock');
        }
        if (!$kitchenId) jsonError('Kitchen ID required');

        $itemId = (int)($input['item_id'] ?? 0);
        $adjustment = (float)($input['adjustment'] ?? 0);
        $reason = trim($input['reason'] ?? '');

        if (!$itemId) jsonError('Item ID required');
        if ($adjustment == 0) jsonError('Adjustment cannot be zero');
        if (!$reason) jsonError('Reason is required');

        // Validate negative adjustment won't go below zero
        if ($adjustment < 0) {
            $currentStmt = $db->prepare("SELECT qty FROM kitchen_inventory WHERE kitchen_id = ? AND item_id = ?");
            $currentStmt->execute([$kitchenId, $itemId]);
            $currentQty = (float)($currentStmt->fetchColumn() ?: 0);
            if (abs($adjustment) > $currentQty) {
                jsonError("Cannot remove " . abs($adjustment) . " — only " . $currentQty . " in stock. Adjust to -" . $currentQty . " max.");
            }
        }

        // Upsert kitchen_inventory
        $db->prepare("INSERT INTO kitchen_inventory (kitchen_id, item_id, qty) VALUES (?, ?, GREATEST(0, ?))
            ON DUPLICATE KEY UPDATE qty = GREATEST(0, qty + ?)")->execute([$kitchenId, $itemId, $adjustment, $adjustment]);

        // Get updated pantry stock
        $newStock = $db->prepare("SELECT qty FROM kitchen_inventory WHERE kitchen_id = ? AND item_id = ?");
        $newStock->execute([$kitchenId, $itemId]);
        $newQty = $newStock->fetchColumn() ?: 0;

        auditLog('stock_adjust', 'kitchen_inventory', $itemId, null, [
            'adjustment' => $adjustment,
            'new_stock' => $newQty,
            'reason' => $reason,
            'kitchen_id' => $kitchenId,
        ]);

        jsonResponse(['updated' => true, 'new_stock' => (float)$newQty]);
        break;

    // ── Kitchen pantry stock ──
    case 'kitchen_stock':
        if (!$kitchenId) jsonError('Kitchen ID required');
        $q = trim($_GET['q'] ?? '');

        $sql = "SELECT ki.item_id AS id, i.name, i.category, i.uom, ki.qty,
                    ki.updated_at
                FROM kitchen_inventory ki
                JOIN items i ON i.id = ki.item_id
                WHERE ki.kitchen_id = ? AND ki.qty > 0";
        $params = [$kitchenId];

        if ($q) {
            $sql .= " AND i.name LIKE ?";
            $params[] = "%$q%";
        }

        $sql .= " ORDER BY i.category, i.name";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Categories from results
        $cats = array_values(array_unique(array_filter(array_column($items, 'category'))));
        sort($cats);

        jsonResponse([
            'items' => $items,
            'categories' => $cats,
            'kitchen_id' => $kitchenId,
        ]);
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
                WHERE al.action = 'stock_adjust' AND al.entity IN ('items', 'kitchen_inventory')
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
