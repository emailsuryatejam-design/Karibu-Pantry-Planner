<?php
/**
 * Karibu Pantry Planner — Audit Log API
 */
require_once __DIR__ . '/../auth.php';

requireAuth();
requireRole(['admin']);

$db     = getDB();
$action = $_GET['action'] ?? 'list';

switch ($action) {

    case 'list':
        $page     = max(1, (int)($_GET['page'] ?? 1));
        $perPage  = 50;
        $offset   = ($page - 1) * $perPage;
        $entity   = $_GET['entity']   ?? '';
        $userId   = (int)($_GET['user_id'] ?? 0);
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to']   ?? '';
        $search   = trim($_GET['search'] ?? '');

        $where  = [];
        $params = [];

        if ($entity)   { $where[] = 'entity = ?';                          $params[] = $entity; }
        if ($userId)   { $where[] = 'user_id = ?';                         $params[] = $userId; }
        if ($dateFrom) { $where[] = 'DATE(created_at) >= ?';               $params[] = $dateFrom; }
        if ($dateTo)   { $where[] = 'DATE(created_at) <= ?';               $params[] = $dateTo; }
        if ($search)   { $where[] = '(action LIKE ? OR user_name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_log $whereStr");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Page of results
        $listStmt = $db->prepare("SELECT * FROM audit_log $whereStr ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $listStmt->execute($params);
        $rows = $listStmt->fetchAll();

        jsonResponse([
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int)ceil($total / $perPage)),
        ]);
        break;

    case 'entities':
        // Distinct entity types for filter dropdown
        $rows = $db->query("SELECT DISTINCT entity FROM audit_log ORDER BY entity")->fetchAll(PDO::FETCH_COLUMN);
        jsonResponse(['entities' => $rows]);
        break;

    case 'users':
        // Distinct users who have audit entries
        $rows = $db->query("SELECT DISTINCT user_id, user_name FROM audit_log WHERE user_id IS NOT NULL ORDER BY user_name")->fetchAll();
        jsonResponse(['users' => $rows]);
        break;

    default:
        jsonError('Unknown action', 400);
}
