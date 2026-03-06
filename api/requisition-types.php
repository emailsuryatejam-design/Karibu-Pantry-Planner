<?php
/**
 * Karibu Pantry Planner — Requisition Types API
 *
 * Actions:
 *   list           — all active types (sorted by sort_order)
 *   list_all       — all types including inactive (admin)
 *   save           — create or update a type (admin)
 *   toggle_active  — enable/disable a type (admin)
 *   reorder        — update sort_order for all types (admin)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$action = $_GET['action'] ?? '';
$db = getDB();

switch ($action) {

    // ── List active types (any authenticated user) ──
    case 'list':
        requireAuth();

        // Try cache first
        $cached = cacheGet('requisition_types', 600);
        if ($cached) {
            jsonResponse($cached);
        }

        $stmt = $db->query("SELECT id, name, code, sort_order FROM requisition_types WHERE is_active = 1 ORDER BY sort_order, name");
        $types = $stmt->fetchAll();

        $result = ['types' => $types];
        cacheSet('requisition_types', $result);
        jsonResponse($result);

    // ── List all types including inactive (admin) ──
    case 'list_all':
        requireRole('admin');
        $stmt = $db->query("SELECT * FROM requisition_types ORDER BY sort_order, name");
        jsonResponse(['types' => $stmt->fetchAll()]);

    // ── Save (create or update) ──
    case 'save':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();

        $id   = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $code = trim($data['code'] ?? '');

        if (!$name) jsonError('Name is required');
        if (!$code) {
            // Auto-generate code from name
            $code = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
            $code = trim($code, '_');
        }

        if ($id > 0) {
            // Update
            $sortOrder = (int)($data['sort_order'] ?? 0);
            $stmt = $db->prepare("UPDATE requisition_types SET name = ?, code = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $code, $sortOrder, (int)($data['is_active'] ?? 1), $id]);
            auditLog('requisition_type_update', 'requisition_type', $id);
        } else {
            // Create — get next sort_order
            $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) FROM requisition_types")->fetchColumn();
            $stmt = $db->prepare("INSERT INTO requisition_types (name, code, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$name, $code, $maxSort + 1]);
            $id = (int)$db->lastInsertId();
            auditLog('requisition_type_create', 'requisition_type', $id);
        }

        cacheClear('requisition_types');
        jsonResponse(['saved' => true, 'id' => $id]);

    // ── Toggle active/inactive ──
    case 'toggle_active':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();
        $id = (int)($data['id'] ?? 0);
        if (!$id) jsonError('ID required');

        $db->prepare("UPDATE requisition_types SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        auditLog('requisition_type_toggle', 'requisition_type', $id);

        cacheClear('requisition_types');
        jsonResponse(['toggled' => true]);

    // ── Reorder (receive array of {id, sort_order}) ──
    case 'reorder':
        requireMethod('POST');
        requireRole('admin');
        $data = getJsonInput();
        $items = $data['items'] ?? [];

        $stmt = $db->prepare("UPDATE requisition_types SET sort_order = ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt->execute([(int)$item['sort_order'], (int)$item['id']]);
        }

        cacheClear('requisition_types');
        jsonResponse(['reordered' => true]);

    default:
        jsonError('Unknown action', 400);
}
