<?php
/**
 * Karibu Pantry Planner — Items API
 * CRUD for items with portion_weight + order_mode
 */

require_once __DIR__ . '/../auth.php';

$user = requireAuth();
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {

    // ── List items (all roles) ──
    case 'list':
        $q = trim($_GET['q'] ?? '');
        $cat = trim($_GET['category'] ?? '');
        $activeOnly = ($_GET['active'] ?? '1') === '1';

        $sql = "SELECT id, name, code, category, uom, stock_qty, portion_weight, order_mode, piece_weight, is_pantry_staple, is_active FROM items WHERE 1=1";
        $params = [];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        if ($q) {
            $sql .= " AND (name LIKE ? OR code LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if ($cat) {
            $sql .= " AND category = ?";
            $params[] = $cat;
        }
        $sql .= " ORDER BY category, name";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Group by category
        $grouped = [];
        foreach ($items as $item) {
            $c = $item['category'] ?: 'Uncategorized';
            $grouped[$c][] = $item;
        }

        jsonResponse(['items' => $items, 'grouped' => $grouped]);

    // ── Get single item ──
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('Item ID required');
        $stmt = $db->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) jsonError('Item not found', 404);
        jsonResponse(['item' => $item]);

    // ── Categories list ──
    case 'categories':
        $cats = $db->query("SELECT DISTINCT category FROM items WHERE is_active = 1 AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(['categories' => $cats]);

    // ── Save (create/update) — admin only ──
    case 'save':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();

        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $code = trim($data['code'] ?? '');
        $category = trim($data['category'] ?? '');
        $uom = trim($data['uom'] ?? 'kg');
        $portionWeight = (float)($data['portion_weight'] ?? 0.250);
        $orderMode = in_array($data['order_mode'] ?? '', ['portion', 'direct_kg']) ? $data['order_mode'] : 'portion';
        $pieceWeight = isset($data['piece_weight']) ? (float)$data['piece_weight'] : null;
        $isPantryStaple = (int)($data['is_pantry_staple'] ?? 0);

        if (!$name) jsonError('Item name is required');
        if ($portionWeight <= 0) $portionWeight = 0.250;
        if ($pieceWeight !== null && $pieceWeight <= 0) $pieceWeight = null;

        if ($id) {
            // Update
            $stmt = $db->prepare('UPDATE items SET name = ?, code = ?, category = ?, uom = ?, portion_weight = ?, order_mode = ?, piece_weight = ?, is_pantry_staple = ? WHERE id = ?');
            $stmt->execute([$name, $code, $category, $uom, $portionWeight, $orderMode, $pieceWeight, $isPantryStaple, $id]);
            cacheClear('active_items');
            auditLog('item_update', 'item', $id, null, $data);
            jsonResponse(['updated' => true, 'id' => $id]);
        } else {
            // Create
            $stmt = $db->prepare('INSERT INTO items (name, code, category, uom, portion_weight, order_mode, piece_weight, is_pantry_staple) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $code, $category, $uom, $portionWeight, $orderMode, $pieceWeight, $isPantryStaple]);
            $newId = $db->lastInsertId();
            cacheClear('active_items');
            auditLog('item_create', 'item', $newId, null, $data);
            jsonResponse(['created' => true, 'id' => $newId]);
        }

    // ── Toggle active — admin only ──
    case 'toggle_active':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonError('Item ID required');

        $stmt = $db->prepare('UPDATE items SET is_active = NOT is_active WHERE id = ?');
        $stmt->execute([$id]);
        cacheClear('active_items');
        auditLog('item_toggle', 'item', $id);
        jsonResponse(['toggled' => true]);

    // ── Bulk update portion weights — admin only ──
    case 'bulk_update':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();
        $updates = $data['items'] ?? [];
        if (empty($updates)) jsonError('No items to update');

        $stmt = $db->prepare('UPDATE items SET portion_weight = ?, order_mode = ? WHERE id = ?');
        $count = 0;
        foreach ($updates as $item) {
            $stmt->execute([
                (float)($item['portion_weight'] ?? 0.250),
                in_array($item['order_mode'] ?? '', ['portion', 'direct_kg']) ? $item['order_mode'] : 'portion',
                (int)$item['id']
            ]);
            $count++;
        }
        cacheClear('active_items');
        auditLog('item_bulk_update', 'items', null, null, ['count' => $count]);
        jsonResponse(['updated' => $count]);

    // ── Bulk update UOM settings (piece_weight + pantry staple) — admin only ──
    case 'bulk_uom_update':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();
        $updates = $data['items'] ?? [];
        if (empty($updates)) jsonError('No items to update');

        $stmt = $db->prepare('UPDATE items SET piece_weight = ?, is_pantry_staple = ? WHERE id = ?');
        $count = 0;
        foreach ($updates as $item) {
            $pw = isset($item['piece_weight']) && $item['piece_weight'] > 0 ? (float)$item['piece_weight'] : null;
            $stmt->execute([
                $pw,
                (int)($item['is_pantry_staple'] ?? 0),
                (int)$item['id']
            ]);
            $count++;
        }
        cacheClear('active_items');
        auditLog('item_bulk_uom_update', 'items', null, null, ['count' => $count]);
        jsonResponse(['updated' => $count]);

    default:
        jsonError('Unknown action');
}
