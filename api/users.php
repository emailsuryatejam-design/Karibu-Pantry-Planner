<?php
require_once __DIR__ . '/../auth.php';
$user = requireRole(['admin']);
$db = getDB();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

switch ($action) {

    // ── List all users ──
    case 'list':
        $stmt = $db->query('SELECT u.id, u.name, u.username, u.role, u.camp_name, u.kitchen_id, u.is_active, u.created_at, k.name AS kitchen_name FROM users u LEFT JOIN kitchens k ON k.id = u.kitchen_id ORDER BY u.is_active DESC, u.name');
        jsonResponse(['users' => $stmt->fetchAll()]);
        break;

    // ── Create user ──
    case 'create':
        requireMethod('POST');
        $name = trim($input['name'] ?? '');
        $username = trim($input['username'] ?? '');
        $pin = trim($input['pin'] ?? '');
        $role = $input['role'] ?? 'chef';

        if (!$name || !$username || !$pin) {
            jsonError('Name, username and PIN are required');
        }
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            jsonError('PIN must be exactly 4 digits');
        }
        if (!in_array($role, ['chef', 'storekeeper', 'admin'])) {
            jsonError('Invalid role');
        }

        // Check duplicate username
        $check = $db->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            jsonError('Username already exists');
        }

        $kitchenId = isset($input['kitchen_id']) ? (int)$input['kitchen_id'] : null;

        $stmt = $db->prepare('INSERT INTO users (name, username, pin, role, kitchen_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $username, $pin, $role, $kitchenId]);

        auditLog('create_user', 'users', $db->lastInsertId(), null, ['name' => $name, 'role' => $role]);
        jsonResponse(['id' => $db->lastInsertId()]);
        break;

    // ── Update user ──
    case 'update':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('User ID required');

        $fields = [];
        $params = [];

        if (isset($input['name']) && trim($input['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($input['name']);
        }
        if (isset($input['pin']) && trim($input['pin'])) {
            if (strlen(trim($input['pin'])) !== 4 || !ctype_digit(trim($input['pin']))) jsonError('PIN must be exactly 4 digits');
            $fields[] = 'pin = ?';
            $params[] = trim($input['pin']);
        }
        if (isset($input['role']) && in_array($input['role'], ['chef', 'storekeeper', 'admin'])) {
            $fields[] = 'role = ?';
            $params[] = $input['role'];
        }
        if (isset($input['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $input['is_active'] ? 1 : 0;
        }
        if (array_key_exists('kitchen_id', $input)) {
            $fields[] = 'kitchen_id = ?';
            $params[] = $input['kitchen_id'] ? (int)$input['kitchen_id'] : null;
        }

        if (empty($fields)) jsonError('Nothing to update');

        $params[] = $id;
        $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

        auditLog('update_user', 'users', $id);
        jsonResponse(['updated' => true]);
        break;

    // ── Delete user ──
    case 'delete':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('User ID required');
        if ($id === $user['id']) jsonError('Cannot delete yourself');

        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        auditLog('delete_user', 'users', $id);
        jsonResponse(['deleted' => true]);
        break;

    default:
        jsonError('Unknown action');
}
