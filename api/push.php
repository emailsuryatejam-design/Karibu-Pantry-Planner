<?php
/**
 * Karibu Pantry Planner — Push Subscription API
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/push-sender.php';

$user = requireAuth();
$db = getDB();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {

    // ── Get VAPID public key ──
    case 'vapid_key':
        if (!defined('VAPID_PUBLIC_KEY')) {
            jsonError('Push notifications not configured');
        }
        jsonResponse(['key' => VAPID_PUBLIC_KEY]);

    // ── Subscribe ──
    case 'subscribe':
        requireMethod('POST');
        $endpoint = trim($input['endpoint'] ?? '');
        $p256dh = trim($input['p256dh'] ?? '');
        $authKey = trim($input['auth_key'] ?? '');

        if (!$endpoint || !$p256dh || !$authKey) {
            jsonError('Missing subscription data');
        }

        $kitchenId = $user['kitchen_id'] ?? 0;

        // Upsert: remove old subscription for this endpoint, insert new
        $db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?')->execute([$endpoint]);
        $stmt = $db->prepare('INSERT INTO push_subscriptions (user_id, kitchen_id, endpoint, p256dh, auth_key) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user['id'], $kitchenId, $endpoint, $p256dh, $authKey]);

        jsonResponse(['subscribed' => true]);

    // ── Unsubscribe ──
    case 'unsubscribe':
        requireMethod('POST');
        $endpoint = trim($input['endpoint'] ?? '');
        if ($endpoint) {
            $db->prepare('DELETE FROM push_subscriptions WHERE endpoint = ? AND user_id = ?')
               ->execute([$endpoint, $user['id']]);
        }
        jsonResponse(['unsubscribed' => true]);

    // ── Check subscription status ──
    case 'status':
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM push_subscriptions WHERE user_id = ?');
        $stmt->execute([$user['id']]);
        $count = $stmt->fetch()['count'];
        jsonResponse(['subscribed' => $count > 0, 'count' => (int)$count]);

    // ── List notifications ──
    case 'notifications':
        $kitchenId = $user['kitchen_id'] ?? 0;
        $limit = min((int)($_GET['limit'] ?? 20), 50);
        $stmt = $db->prepare('SELECT * FROM notifications WHERE (kitchen_id = ? OR user_id = ?) ORDER BY created_at DESC LIMIT ?');
        $stmt->execute([$kitchenId, $user['id'], $limit]);
        jsonResponse(['notifications' => $stmt->fetchAll()]);

    // ── Mark notification read ──
    case 'mark_read':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if ($id) {
            $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR kitchen_id = ?)')
               ->execute([$id, $user['id'], $user['kitchen_id'] ?? 0]);
        }
        jsonResponse(['ok' => true]);

    // ── Unread count ──
    case 'unread_count':
        $kitchenId = $user['kitchen_id'] ?? 0;
        $stmt = $db->prepare('SELECT COUNT(*) as count FROM notifications WHERE (kitchen_id = ? OR user_id = ?) AND is_read = 0');
        $stmt->execute([$kitchenId, $user['id']]);
        jsonResponse(['count' => (int)$stmt->fetch()['count']]);

    default:
        jsonError('Unknown action');
}
