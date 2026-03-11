<?php
/**
 * Store Orders API — shows requisitions from the storekeeper's perspective
 * Reads from requisitions/requisition_lines tables (same data as store dashboard)
 */
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();
$kitchenId = $user['kitchen_id'] ?? null;

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

// Map store-orders statuses to requisition statuses
// Store view: pending = submitted, fulfilled = fulfilled, received = received
$statusMap = [
    'pending'   => 'submitted',
    'fulfilled' => 'fulfilled',
    'received'  => 'received',
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
                    (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) AS total_items
                FROM requisitions r
                LEFT JOIN users u ON u.id = r.created_by
                WHERE r.kitchen_id = ?
                AND r.status IN ('submitted','fulfilled','received')";
        $params = [$kitchenId];

        if ($status !== 'all' && isset($statusMap[$status])) {
            $sql .= ' AND r.status = ?';
            $params[] = $statusMap[$status];
        }

        $sql .= ' ORDER BY r.req_date DESC, r.session_number ASC, r.supplement_number ASC LIMIT 50';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Remap status names for frontend: submitted → pending
        foreach ($orders as &$o) {
            if ($o['status'] === 'submitted') $o['status'] = 'pending';
        }
        unset($o);

        // Get counts per status for this kitchen
        $countStmt = $db->prepare("SELECT status, COUNT(*) AS count FROM requisitions
            WHERE kitchen_id = ? AND status IN ('submitted','fulfilled','received') GROUP BY status");
        $countStmt->execute([$kitchenId]);
        $counts = $countStmt->fetchAll();

        $statusCounts = ['all' => 0, 'pending' => 0, 'fulfilled' => 0, 'received' => 0];
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
        $lines = $db->prepare("SELECT rl.id, rl.item_id, rl.item_name, rl.uom,
                rl.order_qty AS requested_qty,
                rl.fulfilled_qty,
                rl.received_qty
            FROM requisition_lines rl
            WHERE rl.requisition_id = ?
            ORDER BY rl.id");
        $lines->execute([$orderId]);

        jsonResponse(['order' => $order, 'lines' => $lines->fetchAll()]);
        break;

    // ── Mark order as sent (storekeeper sends items to kitchen) ──
    case 'mark_sent':
        requireMethod('POST');

        $orderId = (int)($input['order_id'] ?? 0);
        $lines = $input['lines'] ?? [];
        if (!$orderId) jsonError('Order ID required');

        // Update fulfilled qty per line
        $stmt = $db->prepare('UPDATE requisition_lines SET fulfilled_qty = ? WHERE id = ? AND requisition_id = ?');
        foreach ($lines as $line) {
            $stmt->execute([
                (float)($line['fulfilled_qty'] ?? 0),
                (int)$line['id'],
                $orderId
            ]);
        }

        // Mark requisition as fulfilled
        $db->prepare("UPDATE requisitions SET status = 'fulfilled', reviewed_by = ?, updated_at = NOW() WHERE id = ?")->execute([$user['id'], $orderId]);

        auditLog('mark_sent', 'requisitions', $orderId, null, ['lines' => count($lines)]);
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

    default:
        jsonError('Unknown action');
}
