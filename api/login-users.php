<?php
/**
 * Public endpoint: returns staff names for a given kitchen.
 * Only exposes name, username, role — no IDs, no sensitive data.
 */
require_once __DIR__ . '/../config.php';

$kitchenId = (int)($_GET['kitchen_id'] ?? 0);
if (!$kitchenId) jsonError('Kitchen ID required');

$db = getDB();
$stmt = $db->prepare('SELECT name, username, role FROM users WHERE is_active = 1 AND kitchen_id = ? ORDER BY name');
$stmt->execute([$kitchenId]);
jsonResponse(['users' => $stmt->fetchAll()]);
