<?php
/**
 * Karibu Pantry Planner — Admin Reports API
 */
require_once __DIR__ . '/../auth.php';

requireAuth();
requireRole(['admin']);

$db     = getDB();
$action = $_GET['action'] ?? 'order_summary';

switch ($action) {

    // ── Order summary: orders per kitchen per period ──
    case 'order_summary':
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

        $stmt = $db->prepare("
            SELECT k.name AS kitchen_name,
                   DATE_FORMAT(r.req_date, '%Y-%m') AS month,
                   r.req_date,
                   r.meals,
                   r.status,
                   COALESCE(r.guest_count, 0) AS guest_count,
                   COUNT(r.id) AS order_count,
                   SUM(COALESCE(rl.order_qty, 0)) AS total_kg_ordered,
                   SUM(COALESCE(rl.fulfilled_qty, 0)) AS total_kg_fulfilled,
                   SUM(COALESCE(rl.unused_qty, 0)) AS total_kg_wasted
            FROM requisitions r
            LEFT JOIN kitchens k ON k.id = r.kitchen_id
            LEFT JOIN requisition_lines rl ON rl.requisition_id = r.id AND rl.deleted_at IS NULL
            WHERE r.req_date BETWEEN ? AND ?
            GROUP BY k.id, r.req_date, r.meals, r.status
            ORDER BY r.req_date DESC, k.name
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        jsonResponse(['rows' => $stmt->fetchAll()]);
        break;

    // ── Top items: most ordered ingredients ──
    case 'top_items':
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
        $limit    = (int)($_GET['limit'] ?? 20);

        $stmt = $db->prepare("
            SELECT rl.item_name,
                   rl.uom,
                   COUNT(DISTINCT rl.requisition_id) AS times_ordered,
                   SUM(COALESCE(rl.order_qty, 0))     AS total_ordered,
                   SUM(COALESCE(rl.fulfilled_qty, 0))  AS total_fulfilled,
                   SUM(COALESCE(rl.unused_qty, 0))     AS total_wasted,
                   ROUND(
                     100.0 * SUM(COALESCE(rl.unused_qty, 0))
                     / NULLIF(SUM(COALESCE(rl.fulfilled_qty, 0)), 0)
                   , 1) AS waste_pct
            FROM requisition_lines rl
            JOIN requisitions r ON r.id = rl.requisition_id
            WHERE r.req_date BETWEEN ? AND ?
              AND rl.deleted_at IS NULL
              AND rl.status != 'rejected'
            GROUP BY rl.item_name, rl.uom
            ORDER BY total_ordered DESC
            LIMIT ?
        ");
        $stmt->execute([$dateFrom, $dateTo, $limit]);
        jsonResponse(['items' => $stmt->fetchAll()]);
        break;

    // ── Waste summary: aggregate unused_qty by kitchen ──
    case 'waste_summary':
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

        $stmt = $db->prepare("
            SELECT k.name AS kitchen_name,
                   SUM(COALESCE(rl.unused_qty, 0))    AS total_wasted,
                   SUM(COALESCE(rl.fulfilled_qty, 0))  AS total_fulfilled,
                   ROUND(
                     100.0 * SUM(COALESCE(rl.unused_qty, 0))
                     / NULLIF(SUM(COALESCE(rl.fulfilled_qty, 0)), 0)
                   , 1) AS waste_pct,
                   COUNT(DISTINCT r.id) AS total_orders
            FROM requisitions r
            LEFT JOIN kitchens k ON k.id = r.kitchen_id
            LEFT JOIN requisition_lines rl ON rl.requisition_id = r.id AND rl.deleted_at IS NULL
            WHERE r.req_date BETWEEN ? AND ?
            GROUP BY k.id, k.name
            ORDER BY total_wasted DESC
        ");
        $stmt->execute([$dateFrom, $dateTo]);
        jsonResponse(['kitchens' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action', 400);
}
