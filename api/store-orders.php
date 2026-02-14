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

    // ── Mark order as sent (storekeeper sends items to kitchen) ──
    case 'mark_sent':
        requireMethod('POST');

        $orderId = (int)($input['order_id'] ?? 0);
        $lines = $input['lines'] ?? [];
        if (!$orderId) jsonError('Order ID required');

        // Update fulfilled qty per line
        $stmt = $db->prepare('UPDATE grocery_order_lines SET fulfilled_qty = ? WHERE id = ? AND order_id = ?');
        foreach ($lines as $line) {
            $stmt->execute([(float)($line['fulfilled_qty'] ?? 0), (int)$line['id'], $orderId]);
        }

        // Mark order as fulfilled (sent to kitchen)
        $db->prepare("UPDATE grocery_orders SET status = 'fulfilled', reviewed_by = ?, updated_at = NOW() WHERE id = ?")->execute([$user['id'], $orderId]);

        auditLog('mark_sent', 'grocery_orders', $orderId, null, ['lines' => count($lines)]);
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
