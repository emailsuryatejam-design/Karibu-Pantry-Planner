<?php
require_once __DIR__ . '/../auth.php';
requireRole(['admin']);
$db  = getDB();
$input  = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {

    // ── List all soft-deleted records ──
    case 'list':
        $data = [];

        // Recipes
        $data['recipes'] = $db->query("
            SELECT r.id, r.name, r.category, r.servings, r.deleted_at,
                   u.name AS deleted_by_name
            FROM recipes r
            LEFT JOIN users u ON u.id = r.deleted_by
            WHERE r.deleted_at IS NOT NULL
            ORDER BY r.deleted_at DESC
        ")->fetchAll();

        // Recipe Ingredients
        $data['recipe_ingredients'] = $db->query("
            SELECT ri.id, ri.item_name, ri.qty, ri.uom, ri.deleted_at,
                   r.name AS recipe_name,
                   u.name AS deleted_by_name
            FROM recipe_ingredients ri
            LEFT JOIN recipes r ON r.id = ri.recipe_id
            LEFT JOIN users u ON u.id = ri.deleted_by
            WHERE ri.deleted_at IS NOT NULL
            ORDER BY ri.deleted_at DESC
        ")->fetchAll();

        // Notification Emails
        $data['notification_emails'] = $db->query("
            SELECT ne.id, ne.name, ne.email, ne.notify_on, ne.deleted_at,
                   k.name AS kitchen_name,
                   u.name AS deleted_by_name
            FROM notification_emails ne
            LEFT JOIN kitchens k ON k.id = ne.kitchen_id
            LEFT JOIN users u ON u.id = ne.deleted_by
            WHERE ne.deleted_at IS NOT NULL
            ORDER BY ne.deleted_at DESC
        ")->fetchAll();

        // Requisition Lines
        $data['requisition_lines'] = $db->query("
            SELECT rl.id, rl.item_name, rl.order_qty, rl.uom, rl.deleted_at,
                   r.id AS requisition_id, r.meals, r.req_date,
                   k.name AS kitchen_name,
                   u.name AS deleted_by_name
            FROM requisition_lines rl
            LEFT JOIN requisitions r ON r.id = rl.requisition_id
            LEFT JOIN kitchens k ON k.id = r.kitchen_id
            LEFT JOIN users u ON u.id = rl.deleted_by
            WHERE rl.deleted_at IS NOT NULL
            ORDER BY rl.deleted_at DESC
            LIMIT 200
        ")->fetchAll();

        // Requisition Dishes
        $data['requisition_dishes'] = $db->query("
            SELECT rd.id, rd.recipe_name, rd.deleted_at,
                   r.id AS requisition_id, r.meals, r.req_date,
                   k.name AS kitchen_name,
                   u.name AS deleted_by_name
            FROM requisition_dishes rd
            LEFT JOIN requisitions r ON r.id = rd.requisition_id
            LEFT JOIN kitchens k ON k.id = r.kitchen_id
            LEFT JOIN users u ON u.id = rd.deleted_by
            WHERE rd.deleted_at IS NOT NULL
            ORDER BY rd.deleted_at DESC
            LIMIT 200
        ")->fetchAll();

        jsonResponse($data);

    // ── Restore a record ──
    case 'restore':
        requireMethod('POST');
        $type = $input['type'] ?? '';
        $id   = (int)($input['id'] ?? 0);
        if (!$id) jsonError('ID required');

        $allowed = [
            'recipes'              => 'recipes',
            'recipe_ingredients'   => 'recipe_ingredients',
            'notification_emails'  => 'notification_emails',
            'requisition_lines'    => 'requisition_lines',
            'requisition_dishes'   => 'requisition_dishes',
        ];

        if (!isset($allowed[$type])) jsonError('Invalid type');

        $table = $allowed[$type];
        $db->prepare("UPDATE `{$table}` SET deleted_at = NULL, deleted_by = NULL WHERE id = ?")
           ->execute([$id]);

        $admin = currentUser();
        auditLog('restore_' . $type, $type, $id, ['deleted_at' => 'set'], ['deleted_at' => null]);
        jsonResponse(['restored' => true, 'type' => $type, 'id' => $id]);

    default:
        jsonError('Unknown action');
}
