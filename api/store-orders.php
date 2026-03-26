<?php
/**
 * Store Orders API — shows requisitions from the storekeeper's perspective
 * Reads from requisitions/requisition_lines tables (same data as store dashboard)
 */
require_once __DIR__ . '/../auth.php';
$user = requireRole(['storekeeper', 'admin']);
$db = getDB();
$kitchenId = $user['kitchen_id'] ?? null;

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

// Self-healing: ensure is_staple column exists
try { $db->query("SELECT is_staple FROM requisition_lines LIMIT 0"); }
catch (Exception $e) { $db->exec("ALTER TABLE requisition_lines ADD COLUMN is_staple TINYINT(1) DEFAULT 0"); }

// Map store-orders statuses to requisition statuses
$statusMap = [
    'pending'   => 'submitted',
    'fulfilled' => 'fulfilled',
    'received'  => 'received',
    'closed'    => 'closed',
];

switch ($action) {

    // ── List orders ──
    case 'list':
        $status = $_GET['status'] ?? 'all';

        // Only show submitted/fulfilled/received (not draft/processing)
        $validStatuses = ['submitted', 'fulfilled', 'received'];

        $sql = "SELECT r.id, r.req_date AS order_date, r.meals, r.status, r.has_dispute,
                    r.notes, r.created_by, r.reviewed_by, r.created_at, r.updated_at,
                    r.supplement_number,
                    u.name AS chef_name,
                    (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND status != 'rejected') AS total_items
                FROM requisitions r
                LEFT JOIN users u ON u.id = r.created_by
                WHERE r.kitchen_id = ?
                AND r.status IN ('submitted','processing','fulfilled','received','closed')";
        $params = [$kitchenId];

        if ($status !== 'all' && isset($statusMap[$status])) {
            $sql .= ' AND r.status = ?';
            $params[] = $statusMap[$status];
        }

        $sql .= ' ORDER BY r.req_date DESC, r.session_number ASC, r.supplement_number ASC LIMIT 50';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Remap status names for frontend: submitted/processing → pending
        foreach ($orders as &$o) {
            if ($o['status'] === 'submitted' || $o['status'] === 'processing') $o['status'] = 'pending';
        }
        unset($o);

        // Get counts per status for this kitchen
        $countStmt = $db->prepare("SELECT status, COUNT(*) AS count FROM requisitions
            WHERE kitchen_id = ? AND status IN ('submitted','processing','fulfilled','received','closed') GROUP BY status");
        $countStmt->execute([$kitchenId]);
        $counts = $countStmt->fetchAll();

        $statusCounts = ['all' => 0, 'pending' => 0, 'fulfilled' => 0, 'received' => 0, 'closed' => 0];
        foreach ($counts as $c) {
            $mapped = $c['status'] === 'submitted' ? 'pending' : $c['status'];
            $statusCounts[$mapped] = (int)$c['count'];
            $statusCounts['all'] += (int)$c['count'];
        }

        jsonResponse(['orders' => $orders, 'counts' => $statusCounts]);
        break;

    // ── Get order detail ──
    case 'get':
        $orderId = (int)($_GET['id'] ?? 0);
        if (!$orderId) jsonError('Order ID required');

        $order = $db->prepare("SELECT r.id, r.req_date AS order_date, r.meals, r.status, r.has_dispute,
                r.notes, r.created_by, r.reviewed_by, r.created_at, r.updated_at,
                r.supplement_number,
                u.name AS chef_name
            FROM requisitions r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.id = ?");
        $order->execute([$orderId]);
        $order = $order->fetch();
        if (!$order) jsonError('Order not found', 404);

        // Remap status
        if ($order['status'] === 'submitted') $order['status'] = 'pending';

        // Get aggregated lines (items grouped from requisition_lines)
        // Include status so frontend can show removed lines separately
        $lines = $db->prepare("SELECT rl.id, rl.item_id, rl.item_name, rl.uom,
                rl.order_qty AS requested_qty,
                rl.fulfilled_qty,
                rl.received_qty,
                rl.status AS line_status,
                IFNULL(rl.is_staple, 0) AS is_staple,
                i.code AS item_code, i.category AS item_category
            FROM requisition_lines rl
            LEFT JOIN items i ON i.id = rl.item_id
            WHERE rl.requisition_id = ?
            ORDER BY FIELD(rl.status, 'rejected', 'pending', 'approved', 'adjusted') DESC, rl.id");
        $lines->execute([$orderId]);

        jsonResponse(['order' => $order, 'lines' => $lines->fetchAll()]);
        break;

    // ── Mark order as sent (storekeeper sends items to kitchen) ──
    case 'mark_sent':
        requireMethod('POST');

        $orderId = (int)($input['order_id'] ?? 0);
        $lines = $input['lines'] ?? [];
        if (!$orderId) jsonError('Order ID required');

        // Update fulfilled qty per line and set status to approved (skip rejected/removed lines)
        $stmt = $db->prepare("UPDATE requisition_lines SET fulfilled_qty = ?, status = 'approved' WHERE id = ? AND requisition_id = ? AND status != 'rejected'");
        $lineDetails = [];
        foreach ($lines as $line) {
            $fulfilledQty = (float)($line['fulfilled_qty'] ?? 0);
            if ($fulfilledQty < 0) $fulfilledQty = 0; // Prevent negative
            $stmt->execute([
                $fulfilledQty,
                (int)$line['id'],
                $orderId
            ]);
            $lineDetails[] = ['id' => (int)$line['id'], 'fulfilled_qty' => $fulfilledQty];
        }

        // Mark requisition as fulfilled
        $db->prepare("UPDATE requisitions SET status = 'fulfilled', reviewed_by = ?, updated_at = NOW() WHERE id = ?")->execute([$user['id'], $orderId]);

        auditLog('mark_sent', 'requisitions', $orderId, null, ['lines' => $lineDetails]);
        jsonResponse(['updated' => true]);
        break;

    // ── Add notes to order ──
    case 'add_notes':
        requireMethod('POST');
        $orderId = (int)($input['order_id'] ?? 0);
        $notes = $input['notes'] ?? '';
        if (!$orderId) jsonError('Order ID required');

        $db->prepare('UPDATE requisitions SET notes = ?, updated_at = NOW() WHERE id = ?')->execute([$notes, $orderId]);
        jsonResponse(['updated' => true]);
        break;

    // ── Remove a line item from requisition (storekeeper) ──
    case 'remove_line':
        requireMethod('POST');
        $lineId = (int)($input['line_id'] ?? 0);
        $orderId = (int)($input['order_id'] ?? 0);
        if (!$lineId || !$orderId) jsonError('Line ID and Order ID required');

        // Verify the requisition belongs to this kitchen (allow submitted, processing, fulfilled)
        $check = $db->prepare("SELECT r.status FROM requisitions r WHERE r.id = ? AND r.kitchen_id = ?");
        $check->execute([$orderId, $kitchenId]);
        $reqStatus = $check->fetchColumn();
        if (!$reqStatus) jsonError('Order not found', 404);
        if (!in_array($reqStatus, ['submitted', 'processing', 'fulfilled'])) jsonError('Cannot modify received/closed orders');

        // Get current line info for audit
        $lineStmt = $db->prepare("SELECT item_name, order_qty, uom, status FROM requisition_lines WHERE id = ? AND requisition_id = ?");
        $lineStmt->execute([$lineId, $orderId]);
        $lineInfo = $lineStmt->fetch();
        if (!$lineInfo) jsonError('Line not found', 404);
        if ($lineInfo['status'] === 'rejected') jsonError('Line already removed');

        // Mark as rejected (removed) — keep order_qty for audit trail
        $db->prepare("UPDATE requisition_lines SET status = 'rejected', fulfilled_qty = 0, store_notes = CONCAT(IFNULL(store_notes, ''), '\n[', NOW(), '] Removed by ', ?) WHERE id = ? AND requisition_id = ?")
           ->execute([$user['name'], $lineId, $orderId]);

        auditLog('store_remove_line', 'requisition_lines', $lineId, $lineInfo, ['status' => 'rejected']);
        jsonResponse(['removed' => true, 'line_id' => $lineId]);
        break;

    // ── Restore a previously removed line item ──
    case 'restore_line':
        requireMethod('POST');
        $lineId = (int)($input['line_id'] ?? 0);
        $orderId = (int)($input['order_id'] ?? 0);
        if (!$lineId || !$orderId) jsonError('Line ID and Order ID required');

        // Verify the requisition belongs to this kitchen (allow submitted, processing, fulfilled)
        $check = $db->prepare("SELECT r.status FROM requisitions r WHERE r.id = ? AND r.kitchen_id = ?");
        $check->execute([$orderId, $kitchenId]);
        $reqStatus = $check->fetchColumn();
        if (!$reqStatus) jsonError('Order not found', 404);
        if (!in_array($reqStatus, ['submitted', 'processing', 'fulfilled'])) jsonError('Cannot modify received/closed orders');

        // Verify the line is currently rejected
        $lineStmt = $db->prepare("SELECT item_name, order_qty, status FROM requisition_lines WHERE id = ? AND requisition_id = ?");
        $lineStmt->execute([$lineId, $orderId]);
        $lineInfo = $lineStmt->fetch();
        if (!$lineInfo) jsonError('Line not found', 404);
        if ($lineInfo['status'] !== 'rejected') jsonError('Line is not removed');

        // Restore to pending
        $db->prepare("UPDATE requisition_lines SET status = 'pending', store_notes = CONCAT(IFNULL(store_notes, ''), '\n[', NOW(), '] Restored by ', ?) WHERE id = ? AND requisition_id = ?")
           ->execute([$user['name'], $lineId, $orderId]);

        auditLog('store_restore_line', 'requisition_lines', $lineId, ['status' => 'rejected'], ['status' => 'pending']);
        jsonResponse(['restored' => true, 'line_id' => $lineId]);
        break;

    // ── Add a new line item to requisition (storekeeper) ──
    case 'add_line':
        requireMethod('POST');
        $orderId = (int)($input['order_id'] ?? 0);
        $itemId = (int)($input['item_id'] ?? 0);
        $qty = (float)($input['qty'] ?? 0);
        if (!$orderId || !$itemId || $qty <= 0) jsonError('Order ID, item ID, and quantity required');

        // Verify the requisition belongs to this kitchen (allow submitted, processing, fulfilled)
        $check = $db->prepare("SELECT r.status FROM requisitions r WHERE r.id = ? AND r.kitchen_id = ?");
        $check->execute([$orderId, $kitchenId]);
        $reqStatus = $check->fetchColumn();
        if (!$reqStatus) jsonError('Order not found', 404);
        if (!in_array($reqStatus, ['submitted', 'processing', 'fulfilled'])) jsonError('Cannot modify received/closed orders');

        // Get item details
        $itemStmt = $db->prepare("SELECT id, name, uom FROM items WHERE id = ? AND is_active = 1");
        $itemStmt->execute([$itemId]);
        $item = $itemStmt->fetch();
        if (!$item) jsonError('Item not found', 404);

        // Check if item already exists in this requisition (and is not rejected)
        $existCheck = $db->prepare("SELECT id, status FROM requisition_lines WHERE requisition_id = ? AND item_id = ? AND status != 'rejected'");
        $existCheck->execute([$orderId, $itemId]);
        $existing = $existCheck->fetch();
        if ($existing) jsonError('Item already exists in this order. Adjust quantity instead.');

        // Insert new line (store-added items are staple by default)
        $ins = $db->prepare("INSERT INTO requisition_lines (requisition_id, item_id, item_name, uom, order_qty, status, store_notes, is_staple) VALUES (?, ?, ?, ?, ?, 'pending', '[Added by store]', 1)");
        $ins->execute([$orderId, $itemId, $item['name'], $item['uom'], $qty]);
        $newLineId = $db->lastInsertId();

        auditLog('store_add_line', 'requisition_lines', $newLineId, null, ['item' => $item['name'], 'qty' => $qty]);
        jsonResponse(['added' => true, 'line_id' => $newLineId, 'item_name' => $item['name'], 'uom' => $item['uom']]);
        break;

    default:
        jsonError('Unknown action');
}
