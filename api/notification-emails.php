<?php
require_once __DIR__ . '/../auth.php';
requireRole(['admin']);
$db = getDB();
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {

    case 'list':
        $rows = $db->query("SELECT ne.*, k.name AS kitchen_name FROM notification_emails ne LEFT JOIN kitchens k ON k.id = ne.kitchen_id ORDER BY ne.is_active DESC, ne.name")->fetchAll();
        jsonResponse(['emails' => $rows]);
        break;

    case 'save':
        requireMethod('POST');
        $id        = (int)($input['id'] ?? 0);
        $name      = trim($input['name'] ?? '');
        $email     = trim($input['email'] ?? '');
        $notifyOn  = $input['notify_on'] ?? 'both';
        $kitchenId = isset($input['kitchen_id']) && $input['kitchen_id'] !== '' ? (int)$input['kitchen_id'] : null;

        if (!$name || !$email) jsonError('Name and email are required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email address');
        if (!in_array($notifyOn, ['submit','fulfill','both'])) jsonError('Invalid notify_on value');

        if ($id) {
            $db->prepare("UPDATE notification_emails SET name=?, email=?, notify_on=?, kitchen_id=? WHERE id=?")->execute([$name, $email, $notifyOn, $kitchenId, $id]);
        } else {
            $db->prepare("INSERT INTO notification_emails (name, email, notify_on, kitchen_id) VALUES (?, ?, ?, ?)")->execute([$name, $email, $notifyOn, $kitchenId]);
            $id = $db->lastInsertId();
        }
        jsonResponse(['id' => $id]);
        break;

    case 'toggle':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('ID required');
        $db->prepare("UPDATE notification_emails SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        $row = $db->prepare("SELECT is_active FROM notification_emails WHERE id=?");
        $row->execute([$id]);
        jsonResponse(['is_active' => (int)$row->fetchColumn()]);
        break;

    case 'delete':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('ID required');
        $db->prepare("DELETE FROM notification_emails WHERE id=?")->execute([$id]);
        jsonResponse(['deleted' => true]);
        break;

    default:
        jsonError('Unknown action');
}
