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

    default:
        jsonError('Unknown action');
}
