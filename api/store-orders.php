<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];

switch ($action) {

    // ── List orders ──
    case 'list':
        $status = $_GET['status'] ?? 'all';

        $sql = 'SELECT go.*, u.name as chef_name FROM grocery_orders go LEFT JOIN users u ON go.created_by = u.id';
        $params = [];

        if ($status !== 'all') {
            $sql .= ' WHERE go.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY go.created_at DESC LIMIT 50';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Get counts per status
        $counts = $db->query("SELECT status, COUNT(*) as count FROM grocery_orders GROUP BY status")->fetchAll();
        $statusCounts = ['all' => 0];
        foreach ($counts as $c) {
            $statusCounts[$c['status']] = (int)$c['count'];
            $statusCounts['all'] += (int)$c['count'];
        }

        jsonResponse(['orders' => $orders, 'counts' => $statusCounts]);
        break;

    // ── Get order detail ──
    case 'get':
        $orderId = (int)($_GET['id'] ?? 0);
        if (!$orderId) jsonError('Order ID required');

        $order = $db->prepare('SELECT go.*, u.name as chef_name FROM grocery_orders go LEFT JOIN users u ON go.created_by = u.id WHERE go.id = ?');
        $order->execute([$orderId]);
        $order = $order->fetch();
        if (!$order) jsonError('Order not found', 404);

        $lines = $db->prepare('SELECT * FROM grocery_order_lines WHERE order_id = ? ORDER BY id');
        $lines->execute([$orderId]);

        jsonResponse(['order' => $order, 'lines' => $lines->fetchAll()]);
        break;

    // ── Review line item (approve / adjust / reject) ──
    case 'review_line':
        requireMethod('POST');
        requireRole(['storekeeper', 'admin']);

        $lineId = (int)($input['line_id'] ?? 0);
        $lineStatus = $input['status'] ?? '';
        $approvedQty = $input['approved_qty'] ?? null;
        $notes = $input['notes'] ?? null;

        if (!$lineId || !in_array($lineStatus, ['approved', 'adjusted', 'rejected'])) {
            jsonError('Invalid line ID or status');
        }

        // Get line + order
        $line = $db->prepare('SELECT gol.*, go.status as order_status, go.id as order_id FROM grocery_order_lines gol JOIN grocery_orders go ON gol.order_id = go.id WHERE gol.id = ?');
        $line->execute([$lineId]);
        $lineData = $line->fetch();
        if (!$lineData) jsonError('Line not found');

        // Set approved qty
        if ($lineStatus === 'approved') {
            $approvedQty = $lineData['requested_qty'];
        } elseif ($lineStatus === 'adjusted') {
            if ($approvedQty === null || (float)$approvedQty <= 0) jsonError('Adjusted qty required');
            $approvedQty = (float)$approvedQty;
        } elseif ($lineStatus === 'rejected') {
            $approvedQty = 0;
        }

        $stmt = $db->prepare('UPDATE grocery_order_lines SET status = ?, approved_qty = ?, store_notes = ? WHERE id = ?');
        $stmt->execute([$lineStatus, $approvedQty, $notes, $lineId]);

        // Update order status to 'reviewing' if still pending
        if ($lineData['order_status'] === 'pending') {
            $db->prepare("UPDATE grocery_orders SET status = 'reviewing', reviewed_by = ? WHERE id = ?")->execute([$user['id'], $lineData['order_id']]);
        }

        // Check if all lines are reviewed — auto-update order status
        $remaining = $db->prepare("SELECT COUNT(*) FROM grocery_order_lines WHERE order_id = ? AND status = 'pending'");
        $remaining->execute([$lineData['order_id']]);
        $pendingCount = (int)$remaining->fetchColumn();

        if ($pendingCount === 0) {
            // All lines reviewed — determine final order status
            $statuses = $db->prepare('SELECT status FROM grocery_order_lines WHERE order_id = ?');
            $statuses->execute([$lineData['order_id']]);
            $lineStatuses = $statuses->fetchAll(PDO::FETCH_COLUMN);

            $allRejected = count(array_filter($lineStatuses, fn($s) => $s !== 'rejected')) === 0;
            $allApproved = count(array_filter($lineStatuses, fn($s) => $s !== 'approved')) === 0;

            if ($allRejected) {
                $orderStatus = 'rejected';
            } elseif ($allApproved) {
                $orderStatus = 'approved';
            } else {
                $orderStatus = 'partial';
            }

            $db->prepare('UPDATE grocery_orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$orderStatus, $lineData['order_id']]);
        }

        auditLog('review_line', 'grocery_order_lines', $lineId, null, ['status' => $lineStatus, 'qty' => $approvedQty]);
        jsonResponse(['updated' => true, 'pending_lines' => $pendingCount]);
        break;

    // ── Approve all remaining lines at once ──
    case 'approve_all':
        requireMethod('POST');
        requireRole(['storekeeper', 'admin']);

        $orderId = (int)($input['order_id'] ?? 0);
        if (!$orderId) jsonError('Order ID required');

        $db->prepare("UPDATE grocery_order_lines SET status = 'approved', approved_qty = requested_qty WHERE order_id = ? AND status = 'pending'")->execute([$orderId]);
        $db->prepare("UPDATE grocery_orders SET status = 'approved', reviewed_by = ?, updated_at = NOW() WHERE id = ?")->execute([$user['id'], $orderId]);

        auditLog('approve_all', 'grocery_orders', $orderId);
        jsonResponse(['updated' => true]);
        break;

    // ── Mark order as fulfilled ──
    case 'fulfill':
        requireMethod('POST');
        requireRole(['storekeeper', 'admin']);

        $orderId = (int)($input['order_id'] ?? 0);
        $lines = $input['lines'] ?? [];
        if (!$orderId) jsonError('Order ID required');

        // Update fulfilled qty per line
        $stmt = $db->prepare('UPDATE grocery_order_lines SET fulfilled_qty = ? WHERE id = ? AND order_id = ?');
        foreach ($lines as $line) {
            $stmt->execute([(float)($line['fulfilled_qty'] ?? 0), (int)$line['id'], $orderId]);
        }

        $db->prepare("UPDATE grocery_orders SET status = 'fulfilled', updated_at = NOW() WHERE id = ?")->execute([$orderId]);

        auditLog('fulfill_order', 'grocery_orders', $orderId);
        jsonResponse(['updated' => true]);
        break;

    // ── Add notes to order ──
    case 'add_notes':
        requireMethod('POST');
        $orderId = (int)($input['order_id'] ?? 0);
        $notes = $input['notes'] ?? '';
        if (!$orderId) jsonError('Order ID required');

        $db->prepare('UPDATE grocery_orders SET notes = ?, updated_at = NOW() WHERE id = ?')->execute([$notes, $orderId]);
        jsonResponse(['updated' => true]);
        break;

    default:
        jsonError('Unknown action');
}
