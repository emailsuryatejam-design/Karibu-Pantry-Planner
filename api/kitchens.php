<?php
/**
 * Karibu Pantry Planner — Kitchens API
 */

require_once __DIR__ . '/../auth.php';

$user = requireAuth();
$action = $_GET['action'] ?? 'list';
$db = getDB();

switch ($action) {

    case 'list':
        $activeOnly = ($_GET['active'] ?? '1') === '1';
        if ($activeOnly) {
            // Use cache for default active-only list
            $kitchens = getCachedKitchens();
            jsonResponse(['kitchens' => $kitchens]);
        }
        $sql = "SELECT k.*, (SELECT COUNT(*) FROM users WHERE kitchen_id = k.id AND is_active = 1) AS user_count FROM kitchens k ORDER BY k.name";
        $kitchens = $db->query($sql)->fetchAll();
        jsonResponse(['kitchens' => $kitchens]);

    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('Kitchen ID required');
        $stmt = $db->prepare('SELECT * FROM kitchens WHERE id = ?');
        $stmt->execute([$id]);
        $kitchen = $stmt->fetch();
        if (!$kitchen) jsonError('Kitchen not found', 404);
        jsonResponse(['kitchen' => $kitchen]);

    case 'save':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();

        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $code = trim($data['code'] ?? '');
        if (!$name || !$code) jsonError('Name and code required');

        if ($id) {
            $stmt = $db->prepare('UPDATE kitchens SET name = ?, code = ? WHERE id = ?');
            $stmt->execute([$name, $code, $id]);
            cacheClear('kitchens');
            auditLog('kitchen_update', 'kitchen', $id, null, $data);
            jsonResponse(['updated' => true]);
        } else {
            $stmt = $db->prepare('INSERT INTO kitchens (name, code) VALUES (?, ?)');
            $stmt->execute([$name, $code]);
            $newId = $db->lastInsertId();
            cacheClear('kitchens');
            auditLog('kitchen_create', 'kitchen', $newId, null, $data);
            jsonResponse(['created' => true, 'id' => $newId]);
        }

    case 'toggle_active':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonError('Kitchen ID required');
        $db->prepare('UPDATE kitchens SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        cacheClear('kitchens');
        auditLog('kitchen_toggle', 'kitchen', $id);
        jsonResponse(['toggled' => true]);

    // ── Kitchen Scaling Settings ──
    case 'get_settings':
        $kid = (int)($_GET['kitchen_id'] ?? $user['kitchen_id'] ?? 0);
        if (!$kid) jsonError('Kitchen ID required');

        // Self-healing: add columns if missing
        try {
            $db->query("SELECT default_guest_count FROM kitchens LIMIT 0");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE kitchens
                ADD COLUMN default_guest_count INT DEFAULT 20,
                ADD COLUMN rounding_mode VARCHAR(20) DEFAULT 'half',
                ADD COLUMN min_order_qty DECIMAL(10,2) DEFAULT 0.5");
        }

        $stmt = $db->prepare("SELECT default_guest_count, rounding_mode, min_order_qty FROM kitchens WHERE id = ?");
        $stmt->execute([$kid]);
        $settings = $stmt->fetch();
        if (!$settings) jsonError('Kitchen not found', 404);

        jsonResponse(['settings' => [
            'default_guest_count' => (int)($settings['default_guest_count'] ?? 20),
            'rounding_mode'       => $settings['rounding_mode'] ?? 'half',
            'min_order_qty'       => (float)($settings['min_order_qty'] ?? 0.5),
        ]]);

    case 'save_settings':
        requireMethod('POST');
        requireRole(['admin']);
        $data = getJsonInput();

        $kid = (int)($data['kitchen_id'] ?? $user['kitchen_id'] ?? 0);
        if (!$kid) jsonError('Kitchen ID required');

        $defaultGuests = max(1, (int)($data['default_guest_count'] ?? 20));
        $roundingMode  = in_array($data['rounding_mode'] ?? '', ['half', 'whole', 'none']) ? $data['rounding_mode'] : 'half';
        $minOrderQty   = max(0, (float)($data['min_order_qty'] ?? 0.5));

        // Self-healing: add columns if missing
        try {
            $db->query("SELECT default_guest_count FROM kitchens LIMIT 0");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE kitchens
                ADD COLUMN default_guest_count INT DEFAULT 20,
                ADD COLUMN rounding_mode VARCHAR(20) DEFAULT 'half',
                ADD COLUMN min_order_qty DECIMAL(10,2) DEFAULT 0.5");
        }

        $stmt = $db->prepare("UPDATE kitchens SET default_guest_count = ?, rounding_mode = ?, min_order_qty = ? WHERE id = ?");
        $stmt->execute([$defaultGuests, $roundingMode, $minOrderQty, $kid]);

        cacheClear('kitchens');
        auditLog('kitchen_settings_update', 'kitchen', $kid, null, $data);
        jsonResponse(['saved' => true]);

    default:
        jsonError('Unknown action');
}
