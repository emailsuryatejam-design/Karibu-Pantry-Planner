<?php
require_once __DIR__ . '/../auth.php';
$user = requireRole(['admin']);
$db = getDB();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

switch ($action) {

    // ── List all users ──
    case 'list':
        $stmt = $db->query('SELECT id, name, username, role, camp_name, is_active, created_at FROM users ORDER BY is_active DESC, name');
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
        if (strlen($pin) < 4) {
            jsonError('PIN must be at least 4 digits');
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

        $stmt = $db->prepare('INSERT INTO users (name, username, pin, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $username, $pin, $role]);

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
            if (strlen(trim($input['pin'])) < 4) jsonError('PIN must be at least 4 digits');
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
