<?php
/**
 * Karibu Pantry Planner — Requisitions API
 * Core ordering system: portions-based + direct KG
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/push-sender.php';

$user = requireAuth();
$action = $_GET['action'] ?? 'list';
$db = getDB();
$kitchenId = $user['kitchen_id'] ?? null;

switch ($action) {

    // ── List requisitions for a date/kitchen ──
    case 'list':
        $date = $_GET['date'] ?? date('Y-m-d');
        $status = $_GET['status'] ?? '';
        $kid = (int)($_GET['kitchen_id'] ?? $kitchenId);

        $sql = "SELECT r.*, u.name AS chef_name,
                (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) AS line_count
                FROM requisitions r
                LEFT JOIN users u ON u.id = r.created_by
                WHERE r.req_date = ? AND r.kitchen_id = ?";
        $params = [$date, $kid];

        if ($status) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY r.session_number ASC, r.supplement_number ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $reqs = $stmt->fetchAll();

        jsonResponse(['requisitions' => $reqs]);

    // ── Get single requisition with lines ──
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('Requisition ID required');

        // Admin can view any requisition; others restricted to their kitchen
        if ($user['role'] === 'admin') {
            $stmt = $db->prepare("SELECT r.*, u.name AS chef_name FROM requisitions r LEFT JOIN users u ON u.id = r.created_by WHERE r.id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("SELECT r.*, u.name AS chef_name FROM requisitions r LEFT JOIN users u ON u.id = r.created_by WHERE r.id = ? AND r.kitchen_id = ?");
            $stmt->execute([$id, $kitchenId]);
        }
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found', 404);

        $lines = $db->prepare("SELECT rl.*, i.stock_qty AS current_stock FROM requisition_lines rl LEFT JOIN items i ON i.id = rl.item_id WHERE rl.requisition_id = ? ORDER BY rl.item_name");
        $lines->execute([$id]);
        $lineData = $lines->fetchAll();

        jsonResponse(['requisition' => $req, 'lines' => $lineData]);

    // ── Auto-create requisitions for all active types on a date ──
    case 'auto_create_for_date':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqDate = $data['req_date'] ?? date('Y-m-d');
        $kid = (int)($data['kitchen_id'] ?? $kitchenId);
        $guestCount = (int)($data['guest_count'] ?? 20);
        if (!$kid) jsonError('Kitchen ID required');

        // One-time self-healing: ensure missing tables exist, clean duplicates, add UNIQUE constraint
        $migrated = cacheGet('uk_migration_v4_done', 86400 * 365);
        if (!$migrated) {
            try {
                // 1. Create missing tables that older deployments might not have
                $db->exec("CREATE TABLE IF NOT EXISTS requisition_dishes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    requisition_id INT NOT NULL,
                    recipe_id INT NOT NULL,
                    recipe_name VARCHAR(200) NOT NULL,
                    recipe_servings INT DEFAULT 4,
                    scale_factor DECIMAL(10,3) DEFAULT 1.000,
                    guest_count INT DEFAULT 20,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_req_dish (requisition_id)
                )");
                $db->exec("CREATE TABLE IF NOT EXISTS requisition_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE TABLE IF NOT EXISTS set_menu_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    day_of_week TINYINT NOT NULL,
                    type_code VARCHAR(50) NOT NULL DEFAULT 'lunch',
                    recipe_id INT NOT NULL,
                    recipe_name VARCHAR(200) NOT NULL,
                    servings INT DEFAULT 4,
                    sort_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_day_type (day_of_week, type_code)
                )");

                // 1b. Ensure notifications + push_subscriptions tables exist
                $db->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    kitchen_id INT DEFAULT NULL,
                    user_id INT DEFAULT NULL,
                    title VARCHAR(200) NOT NULL,
                    body TEXT,
                    type VARCHAR(50) DEFAULT 'info',
                    ref_id INT DEFAULT NULL,
                    is_read TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $db->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    kitchen_id INT DEFAULT NULL,
                    endpoint TEXT NOT NULL,
                    p256dh VARCHAR(500) NOT NULL,
                    auth_key VARCHAR(500) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                // 2. Add supplement_number column if missing
                try {
                    $db->query("SELECT supplement_number FROM requisitions LIMIT 0");
                } catch (Exception $e2) {
                    $db->exec("ALTER TABLE requisitions ADD COLUMN supplement_number INT DEFAULT 0");
                }

                // 2b. Add unused_qty column to requisition_lines if missing
                try {
                    $db->query("SELECT unused_qty FROM requisition_lines LIMIT 0");
                } catch (Exception $e2) {
                    $db->exec("ALTER TABLE requisition_lines ADD COLUMN unused_qty DECIMAL(10,2) DEFAULT 0");
                }

                // 3. Upgrade UNIQUE constraint to include supplement_number
                // Drop old constraint if it exists, add new one
                $indexes = $db->query("SHOW INDEX FROM requisitions WHERE Key_name = 'uk_kitchen_date_meals'")->fetchAll();
                if (!empty($indexes)) {
                    // Old constraint without supplement_number — drop it
                    $db->exec("ALTER TABLE requisitions DROP INDEX uk_kitchen_date_meals");
                }
                $indexes2 = $db->query("SHOW INDEX FROM requisitions WHERE Key_name = 'uk_kitchen_date_meals_supp'")->fetchAll();
                if (empty($indexes2)) {
                    // Clean duplicates before adding constraint
                    $dupes = $db->query("SELECT kitchen_id, req_date, meals, supplement_number, GROUP_CONCAT(id ORDER BY id) AS ids, COUNT(*) AS cnt FROM requisitions GROUP BY kitchen_id, req_date, meals, supplement_number HAVING COUNT(*) > 1")->fetchAll();
                    foreach ($dupes as $dupe) {
                        $allIds = explode(',', $dupe['ids']);
                        array_shift($allIds); // keep lowest ID
                        if (!empty($allIds)) {
                            $ph = implode(',', array_map('intval', $allIds));
                            $db->exec("DELETE FROM requisition_lines WHERE requisition_id IN ($ph)");
                            $db->exec("DELETE FROM requisition_dishes WHERE requisition_id IN ($ph)");
                            $db->exec("DELETE FROM requisitions WHERE id IN ($ph)");
                        }
                    }
                    $db->exec("ALTER TABLE requisitions ADD UNIQUE KEY uk_kitchen_date_meals_supp (kitchen_id, req_date, meals, supplement_number)");
                }

                cacheSet('uk_migration_v4_done', true);
            } catch (Exception $e) {
                // Do NOT cache on failure — retry next request
                error_log('Karibu migration error: ' . $e->getMessage());
            }
        }

        // Get active types
        $types = $db->query("SELECT id, name, code, sort_order FROM requisition_types WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();

        if (empty($types)) {
            // Auto-seed default types so chefs are never blocked
            $defaults = [
                ['Breakfast', 'breakfast', 1],
                ['Lunch', 'lunch', 2],
                ['Dinner', 'dinner', 3],
            ];
            $seedStmt = $db->prepare("INSERT IGNORE INTO requisition_types (name, code, sort_order) VALUES (?, ?, ?)");
            foreach ($defaults as $d) {
                $seedStmt->execute($d);
            }
            $types = $db->query("SELECT id, name, code, sort_order FROM requisition_types WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
            if (empty($types)) jsonError('No requisition types configured. Ask admin to add types.');
            cacheClear('requisition_types');
        }

        // INSERT IGNORE: UNIQUE constraint (kitchen_id, req_date, meals, supplement_number) silently skips duplicates.
        // No need for a prior SELECT — race-condition safe.
        $created = 0;
        $insertStmt = $db->prepare("INSERT IGNORE INTO requisitions
            (kitchen_id, req_date, session_number, guest_count, meals, supplement_number, status, created_by)
            VALUES (?, ?, ?, ?, ?, 0, 'draft', ?)");

        foreach ($types as $type) {
            $insertStmt->execute([$kid, $reqDate, $type['sort_order'], $guestCount, $type['code'], $user['id']]);
            if ($insertStmt->rowCount() > 0) $created++;
        }

        if ($created > 0) {
            auditLog('requisition_auto_create', 'requisition', null, null, [
                'date' => $reqDate, 'kitchen_id' => $kid, 'created' => $created
            ]);
        }

        // Return all requisitions for this date
        $stmt = $db->prepare("SELECT r.*, u.name AS chef_name,
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) AS line_count
            FROM requisitions r LEFT JOIN users u ON u.id = r.created_by
            WHERE r.req_date = ? AND r.kitchen_id = ?
            ORDER BY r.session_number ASC, r.supplement_number ASC");
        $stmt->execute([$reqDate, $kid]);
        $reqs = $stmt->fetchAll();

        jsonResponse(['requisitions' => $reqs, 'created' => $created]);

    // ── Create supplementary order for same meal type ──
    case 'create_supplementary':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $parentId = (int)($data['parent_id'] ?? 0);
        if (!$parentId) jsonError('Parent requisition ID required');

        // Fetch parent requisition
        $parentStmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND kitchen_id = ?");
        $parentStmt->execute([$parentId, $kitchenId]);
        $parent = $parentStmt->fetch();
        if (!$parent) jsonError('Parent requisition not found');

        // Only allow supplementary if parent is not draft
        if ($parent['status'] === 'draft') jsonError('Cannot create supplementary for a draft order. Submit the original first.');

        // Find next supplement_number for this (kitchen_id, req_date, meals)
        $maxStmt = $db->prepare("SELECT COALESCE(MAX(supplement_number), 0) + 1 AS next_supp FROM requisitions WHERE kitchen_id = ? AND req_date = ? AND meals = ?");
        $maxStmt->execute([$parent['kitchen_id'], $parent['req_date'], $parent['meals']]);
        $nextSupp = (int)$maxStmt->fetch()['next_supp'];

        $insertStmt = $db->prepare("INSERT INTO requisitions
            (kitchen_id, req_date, session_number, guest_count, meals, supplement_number, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)");
        $insertStmt->execute([
            $parent['kitchen_id'], $parent['req_date'], $parent['session_number'],
            $parent['guest_count'], $parent['meals'], $nextSupp, $user['id']
        ]);
        $newId = $db->lastInsertId();

        auditLog('requisition_supplementary', 'requisition', $newId, null, [
            'parent_id' => $parentId, 'supplement_number' => $nextSupp, 'meals' => $parent['meals']
        ]);

        // Return all requisitions for this date so frontend can refresh tabs
        $allStmt = $db->prepare("SELECT r.*, u.name AS chef_name,
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) AS line_count
            FROM requisitions r LEFT JOIN users u ON u.id = r.created_by
            WHERE r.req_date = ? AND r.kitchen_id = ?
            ORDER BY r.session_number ASC, r.supplement_number ASC");
        $allStmt->execute([$parent['req_date'], $parent['kitchen_id']]);
        $allReqs = $allStmt->fetchAll();

        jsonResponse(['requisition_id' => $newId, 'requisitions' => $allReqs]);

    // ── Create new draft requisition ──
    case 'create':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqDate = $data['req_date'] ?? date('Y-m-d');
        $kid = (int)($data['kitchen_id'] ?? $kitchenId);
        $guestCount = (int)($data['guest_count'] ?? 20);
        $meals = $data['meals'] ?? 'lunch';
        if (is_array($meals)) $meals = implode(',', $meals);

        if (!$kid) jsonError('Kitchen ID required');

        // Auto session number
        $stmt = $db->prepare("SELECT COALESCE(MAX(session_number), 0) + 1 AS next_session FROM requisitions WHERE req_date = ? AND kitchen_id = ?");
        $stmt->execute([$reqDate, $kid]);
        $sessionNum = (int)$stmt->fetch()['next_session'];

        $stmt = $db->prepare("INSERT INTO requisitions (kitchen_id, req_date, session_number, guest_count, meals, status, created_by) VALUES (?, ?, ?, ?, ?, 'draft', ?)");
        $stmt->execute([$kid, $reqDate, $sessionNum, $guestCount, $meals, $user['id']]);
        $reqId = $db->lastInsertId();

        auditLog('requisition_create', 'requisition', $reqId, null, [
            'date' => $reqDate, 'kitchen_id' => $kid, 'session' => $sessionNum, 'guests' => $guestCount, 'meals' => $meals
        ]);

        jsonResponse(['requisition_id' => $reqId, 'session_number' => $sessionNum]);

    // ── Save/update lines (bulk) — legacy, kept for backward compatibility ──
    case 'save_lines':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqId = (int)($data['requisition_id'] ?? 0);
        $lines = $data['lines'] ?? [];
        if (!$reqId) jsonError('Requisition ID required');

        // Verify requisition is draft
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in draft status');

        // Delete existing lines and re-insert
        $db->prepare("DELETE FROM requisition_lines WHERE requisition_id = ?")->execute([$reqId]);

        // Batch-load all referenced items in one query to avoid N+1
        $itemIds = array_filter(array_map(fn($l) => (int)($l['item_id'] ?? 0), $lines));
        $itemMap = [];
        if ($itemIds) {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $batchStmt = $db->prepare("SELECT id, name, stock_qty, portion_weight, order_mode, uom FROM items WHERE id IN ($placeholders)");
            $batchStmt->execute(array_values($itemIds));
            foreach ($batchStmt->fetchAll() as $it) {
                $itemMap[(int)$it['id']] = $it;
            }
        }

        $insertStmt = $db->prepare("INSERT INTO requisition_lines
            (requisition_id, item_id, item_name, meal, order_mode, portions, portion_weight, required_kg, stock_qty, order_qty, uom)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $totalItems = 0;
        $totalKg = 0;

        foreach ($lines as $line) {
            $itemId = (int)$line['item_id'];
            $item = $itemMap[$itemId] ?? null;
            if (!$item) continue;

            $orderMode = $item['order_mode'];
            $portionWeight = (float)$item['portion_weight'];
            $stockQty = (float)$item['stock_qty'];
            $meal = $line['meal'] ?? 'lunch';

            if ($orderMode === 'direct_kg') {
                $requiredKg = (float)($line['direct_kg'] ?? 0);
                $portions = 0;
            } else {
                $portions = (int)($line['portions'] ?? 0);
                $requiredKg = $portions * $portionWeight;
            }

            // Round up to nearest 0.5
            $requiredKg = ceil($requiredKg * 2) / 2;

            // Order qty = required - stock (min 0), rounded up to 0.5
            $orderQty = max(0, $requiredKg - $stockQty);
            $orderQty = ceil($orderQty * 2) / 2;

            if ($requiredKg <= 0) continue;

            $insertStmt->execute([
                $reqId, $itemId, $item['name'], $meal, $orderMode,
                $portions, $portionWeight, $requiredKg, $stockQty, $orderQty, $item['uom']
            ]);

            $totalItems++;
            $totalKg += $orderQty;
        }

        auditLog('requisition_save_lines', 'requisition', $reqId, null, ['items' => $totalItems, 'total_kg' => $totalKg]);
        jsonResponse(['saved' => true, 'total_items' => $totalItems, 'total_kg' => round($totalKg, 2)]);

    // ── Submit requisition (draft → submitted) ──
    case 'submit':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or already submitted');

        // Check has lines
        $lineCount = $db->prepare("SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = ?");
        $lineCount->execute([$reqId]);
        if ((int)$lineCount->fetchColumn() === 0) jsonError('Cannot submit empty requisition');

        $db->prepare("UPDATE requisitions SET status = 'submitted', updated_at = NOW() WHERE id = ?")->execute([$reqId]);

        auditLog('requisition_submit', 'requisition', $reqId);

        // Push notification to storekeepers (non-critical — don't break submit if notifications fail)
        try {
            $kitchenName = '';
            $kStmt = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
            $kStmt->execute([$req['kitchen_id']]);
            $kRow = $kStmt->fetch();
            if ($kRow) $kitchenName = $kRow['name'];

            $mealLabel = ucfirst($req['meals'] ?? 'order');
            $suppNum = (int)($req['supplement_number'] ?? 0);
            if ($suppNum > 0) $mealLabel .= ' (' . ($suppNum + 1) . ')';
            $pushPayload = [
                'title' => 'New Requisition',
                'body'  => "{$user['name']} submitted {$mealLabel} for {$kitchenName}",
                'url'   => '/app.php?page=store-dashboard',
                'tag'   => 'req-submitted-' . $reqId,
            ];
            sendPushToKitchen((int)$req['kitchen_id'], $pushPayload, 'storekeeper', $user['id']);
            storeNotification((int)$req['kitchen_id'], null, $pushPayload['title'], $pushPayload['body'], 'requisition_submitted', $reqId);
        } catch (Exception $e) {
            error_log('Notification error on submit: ' . $e->getMessage());
        }

        jsonResponse(['submitted' => true]);

    // ── Fulfill requisition (storekeeper) ──
    case 'fulfill':
        requireMethod('POST');
        requireRole(['storekeeper', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        $fulfillLines = $data['lines'] ?? [];
        if (!$reqId) jsonError('Requisition ID required');

        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('submitted','processing')");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in submittable status');

        $updateLine = $db->prepare("UPDATE requisition_lines SET fulfilled_qty = ?, status = 'approved', store_notes = ? WHERE id = ? AND requisition_id = ?");
        foreach ($fulfillLines as $fl) {
            $updateLine->execute([
                (float)($fl['fulfilled_qty'] ?? 0),
                $fl['store_notes'] ?? null,
                (int)$fl['id'],
                $reqId
            ]);
        }

        $db->prepare("UPDATE requisitions SET status = 'fulfilled', reviewed_by = ?, updated_at = NOW() WHERE id = ?")->execute([$user['id'], $reqId]);

        auditLog('requisition_fulfill', 'requisition', $reqId);

        // Push notification to the chef who created this requisition (non-critical)
        try {
            $kitchenName = '';
            $kStmt2 = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
            $kStmt2->execute([$req['kitchen_id']]);
            $kRow2 = $kStmt2->fetch();
            if ($kRow2) $kitchenName = $kRow2['name'];

            $mealLabel = ucfirst($req['meals'] ?? 'order');
            $suppNum = (int)($req['supplement_number'] ?? 0);
            if ($suppNum > 0) $mealLabel .= ' (' . ($suppNum + 1) . ')';
            $pushPayload = [
                'title' => 'Order Fulfilled',
                'body'  => "{$mealLabel} for {$kitchenName} has been fulfilled by store",
                'url'   => '/app.php?page=day-close',
                'tag'   => 'req-fulfilled-' . $reqId,
            ];
            sendPushToKitchen((int)$req['kitchen_id'], $pushPayload, 'chef', $user['id']);
            storeNotification((int)$req['kitchen_id'], (int)$req['created_by'], $pushPayload['title'], $pushPayload['body'], 'requisition_fulfilled', $reqId);
        } catch (Exception $e) {
            error_log('Notification error on fulfill: ' . $e->getMessage());
        }

        jsonResponse(['fulfilled' => true]);

    // ── Confirm receipt (chef) ──
    case 'confirm_receipt':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        $receiptLines = $data['lines'] ?? [];
        if (!$reqId) jsonError('Requisition ID required');

        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'fulfilled'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not fulfilled');

        // Batch-load fulfilled_qty for all lines to check disputes without N+1
        $lineIds = array_map(fn($rl) => (int)$rl['id'], $receiptLines);
        $fulfilledMap = [];
        if ($lineIds) {
            $ph = implode(',', array_fill(0, count($lineIds), '?'));
            $fStmt = $db->prepare("SELECT id, fulfilled_qty FROM requisition_lines WHERE requisition_id = ? AND id IN ($ph)");
            $fStmt->execute(array_merge([$reqId], $lineIds));
            foreach ($fStmt->fetchAll() as $fl) {
                $fulfilledMap[(int)$fl['id']] = (float)$fl['fulfilled_qty'];
            }
        }

        $hasDispute = false;
        $updateLine = $db->prepare("UPDATE requisition_lines SET received_qty = ? WHERE id = ? AND requisition_id = ?");
        foreach ($receiptLines as $rl) {
            $receivedQty = (float)($rl['received_qty'] ?? 0);
            $updateLine->execute([$receivedQty, (int)$rl['id'], $reqId]);

            $fulfilledQty = $fulfilledMap[(int)$rl['id']] ?? 0;
            if (abs($fulfilledQty - $receivedQty) > 0.01) {
                $hasDispute = true;
            }
        }

        $db->prepare("UPDATE requisitions SET status = 'received', has_dispute = ?, updated_at = NOW() WHERE id = ?")->execute([$hasDispute ? 1 : 0, $reqId]);

        auditLog('requisition_receipt', 'requisition', $reqId, null, ['has_dispute' => $hasDispute]);

        jsonResponse(['confirmed' => true, 'has_dispute' => $hasDispute]);

    // ── Close day ──
    case 'close':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);

        if ($reqId) {
            // Close single — auto-set received_qty = fulfilled_qty if fulfilled
            $db->prepare("UPDATE requisition_lines rl
                JOIN requisitions r ON r.id = rl.requisition_id
                SET rl.received_qty = rl.fulfilled_qty
                WHERE r.id = ? AND r.status = 'fulfilled' AND (rl.received_qty IS NULL OR rl.received_qty = 0)")->execute([$reqId]);
            $db->prepare("UPDATE requisitions SET status = 'closed', updated_at = NOW() WHERE id = ? AND status IN ('received', 'fulfilled')")->execute([$reqId]);
        } else {
            // Close all received/fulfilled for a date
            $date = $data['date'] ?? date('Y-m-d');
            $kid = (int)($data['kitchen_id'] ?? $kitchenId);
            $db->prepare("UPDATE requisition_lines rl
                JOIN requisitions r ON r.id = rl.requisition_id
                SET rl.received_qty = rl.fulfilled_qty
                WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status = 'fulfilled' AND (rl.received_qty IS NULL OR rl.received_qty = 0)")->execute([$date, $kid]);
            $db->prepare("UPDATE requisitions SET status = 'closed', updated_at = NOW() WHERE req_date = ? AND kitchen_id = ? AND status IN ('received', 'fulfilled')")->execute([$date, $kid]);
        }

        auditLog('requisition_close', 'requisition', $reqId);
        jsonResponse(['closed' => true]);

    // ── Close with unused quantities ──
    case 'close_with_unused':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $date = $data['date'] ?? date('Y-m-d');
        $kid = (int)($data['kitchen_id'] ?? $kitchenId);
        $unusedLines = $data['unused_lines'] ?? []; // [{line_id, unused_qty}, ...]

        // Self-healing: ensure unused_qty column exists
        try {
            $db->query("SELECT unused_qty FROM requisition_lines LIMIT 0");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE requisition_lines ADD COLUMN unused_qty DECIMAL(10,2) DEFAULT 0");
        }

        $db->beginTransaction();
        try {
            // Save unused quantities and update item stock
            $updateLine = $db->prepare("UPDATE requisition_lines SET unused_qty = ? WHERE id = ?");
            $updateStock = $db->prepare("UPDATE items SET stock_qty = stock_qty + ? WHERE id = ?");

            foreach ($unusedLines as $ul) {
                $lineId = (int)($ul['line_id'] ?? 0);
                $unusedQty = max(0, (float)($ul['unused_qty'] ?? 0));
                if (!$lineId || $unusedQty <= 0) continue;

                // Get the line's item_id and verify it belongs to a received/fulfilled requisition for this kitchen/date
                $checkStmt = $db->prepare("SELECT rl.item_id, rl.received_qty, rl.fulfilled_qty FROM requisition_lines rl
                    JOIN requisitions r ON r.id = rl.requisition_id
                    WHERE rl.id = ? AND r.kitchen_id = ? AND r.req_date = ? AND r.status IN ('received', 'fulfilled')");
                $checkStmt->execute([$lineId, $kid, $date]);
                $lineRow = $checkStmt->fetch();
                if (!$lineRow) continue;

                // Use received_qty if set, otherwise fulfilled_qty
                $maxUnused = (float)$lineRow['received_qty'] ?: (float)$lineRow['fulfilled_qty'];
                if ($unusedQty > $maxUnused) $unusedQty = $maxUnused;

                $updateLine->execute([$unusedQty, $lineId]);
                $updateStock->execute([$unusedQty, (int)$lineRow['item_id']]);
            }

            // Auto-set received_qty = fulfilled_qty for fulfilled orders (skipping confirm_receipt)
            $db->prepare("UPDATE requisition_lines rl
                JOIN requisitions r ON r.id = rl.requisition_id
                SET rl.received_qty = rl.fulfilled_qty
                WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status = 'fulfilled' AND (rl.received_qty IS NULL OR rl.received_qty = 0)")->execute([$date, $kid]);

            // Close all received AND fulfilled requisitions for this date/kitchen
            $db->prepare("UPDATE requisitions SET status = 'closed', updated_at = NOW() WHERE req_date = ? AND kitchen_id = ? AND status IN ('received', 'fulfilled')")->execute([$date, $kid]);

            $db->commit();

            auditLog('requisition_close_with_unused', 'requisition', null, null, [
                'date' => $date, 'kitchen_id' => $kid, 'unused_entries' => count($unusedLines)
            ]);

            jsonResponse(['closed' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to close: ' . $e->getMessage());
        }

    // ── Update unused quantities on already-closed requisitions ──
    case 'update_unused':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        $unusedLines = $data['unused_lines'] ?? [];
        if (!$reqId) jsonError('Requisition ID required');

        // Verify requisition is closed/fulfilled and belongs to this kitchen
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('closed', 'fulfilled', 'received') AND kitchen_id = ?");
        $stmt->execute([$reqId, $kitchenId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in closeable status');

        $db->beginTransaction();
        try {
            $updateLine = $db->prepare("UPDATE requisition_lines SET unused_qty = ? WHERE id = ? AND requisition_id = ?");
            $adjustStock = $db->prepare("UPDATE items SET stock_qty = stock_qty + ? WHERE id = ?");

            foreach ($unusedLines as $ul) {
                $lineId = (int)($ul['line_id'] ?? 0);
                $newUnused = max(0, (float)($ul['unused_qty'] ?? 0));
                if (!$lineId) continue;

                // Get current unused and item_id
                $checkStmt = $db->prepare("SELECT item_id, received_qty, fulfilled_qty, unused_qty FROM requisition_lines WHERE id = ? AND requisition_id = ?");
                $checkStmt->execute([$lineId, $reqId]);
                $lineRow = $checkStmt->fetch();
                if (!$lineRow) continue;

                $maxUnused = (float)$lineRow['received_qty'] ?: (float)$lineRow['fulfilled_qty'];
                if ($newUnused > $maxUnused) $newUnused = $maxUnused;

                $oldUnused = (float)$lineRow['unused_qty'];
                $delta = $newUnused - $oldUnused; // positive = more returned, negative = less returned

                if (abs($delta) < 0.001) continue; // no change

                $updateLine->execute([$newUnused, $lineId, $reqId]);
                $adjustStock->execute([$delta, (int)$lineRow['item_id']]);
            }

            $db->commit();

            auditLog('requisition_update_unused', 'requisition', $reqId, null, [
                'entries' => count($unusedLines)
            ]);

            jsonResponse(['updated' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to update: ' . $e->getMessage());
        }

    // ── Dashboard stats (chef) — single query ──
    case 'dashboard_stats':
        $kid = (int)($_GET['kitchen_id'] ?? $kitchenId);
        $today = date('Y-m-d');

        // Count by status, excluding empty drafts (0 items) from active count
        $stmt = $db->prepare("SELECT r.status, COUNT(*) AS cnt,
            SUM(CASE WHEN r.status = 'draft' AND (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) = 0 THEN 1 ELSE 0 END) AS empty_drafts
            FROM requisitions r WHERE r.req_date = ? AND r.kitchen_id = ? GROUP BY r.status");
        $stmt->execute([$today, $kid]);
        $rows = $stmt->fetchAll();

        $counts = [];
        $total = 0;
        $emptyDrafts = 0;
        foreach ($rows as $r) {
            $counts[$r['status']] = (int)$r['cnt'];
            $total += (int)$r['cnt'];
            if ($r['status'] === 'draft') $emptyDrafts = (int)$r['empty_drafts'];
        }

        // Active = non-empty drafts + submitted + processing (exclude empty drafts)
        $activeDrafts = max(0, ($counts['draft'] ?? 0) - $emptyDrafts);
        $stats = [
            'active_sessions' => $activeDrafts + ($counts['submitted'] ?? 0) + ($counts['processing'] ?? 0),
            'awaiting_supply' => $counts['submitted'] ?? 0,
            'ready_close'     => ($counts['fulfilled'] ?? 0) + ($counts['received'] ?? 0),
            'total_sessions'  => $total,
        ];

        jsonResponse(['stats' => $stats, 'date' => $today]);

    // ── Store stats — single query ──
    case 'store_stats':
        $kid = (int)($_GET['kitchen_id'] ?? $kitchenId);
        $today = date('Y-m-d');

        $stmt = $db->prepare("SELECT status, COUNT(*) AS cnt,
            SUM(CASE WHEN status = 'fulfilled' AND DATE(updated_at) = ? THEN 1 ELSE 0 END) AS fulfilled_today
            FROM requisitions WHERE kitchen_id = ? AND status IN ('submitted','processing','fulfilled')
            GROUP BY status");
        $stmt->execute([$today, $kid]);
        $rows = $stmt->fetchAll();

        $stats = ['new_orders' => 0, 'processing' => 0, 'fulfilled_today' => 0];
        foreach ($rows as $r) {
            if ($r['status'] === 'submitted') $stats['new_orders'] = (int)$r['cnt'];
            if ($r['status'] === 'processing') $stats['processing'] = (int)$r['cnt'];
            if ($r['status'] === 'fulfilled') $stats['fulfilled_today'] = (int)$r['fulfilled_today'];
        }

        jsonResponse(['stats' => $stats]);

    // ── Day summary ──
    case 'day_summary':
        $date = $_GET['date'] ?? date('Y-m-d');
        $kid = (int)($_GET['kitchen_id'] ?? $kitchenId);

        $stmt = $db->prepare("SELECT r.*, u.name AS chef_name,
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) AS line_count,
            (SELECT COALESCE(SUM(order_qty), 0) FROM requisition_lines WHERE requisition_id = r.id) AS total_kg
            FROM requisitions r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.req_date = ? AND r.kitchen_id = ?
            ORDER BY r.session_number ASC, r.supplement_number ASC");
        $stmt->execute([$date, $kid]);
        $reqs = $stmt->fetchAll();

        // Summary — track empty drafts separately
        $summary = [
            'total_sessions' => count($reqs),
            'draft' => 0, 'submitted' => 0, 'processing' => 0,
            'fulfilled' => 0, 'received' => 0, 'closed' => 0,
            'empty_drafts' => 0
        ];
        foreach ($reqs as $r) {
            $summary[$r['status']]++;
            if ($r['status'] === 'draft' && (int)$r['line_count'] === 0) {
                $summary['empty_drafts']++;
            }
        }

        // Load lines for fulfilled/received/closed requisitions (for day close unused entry)
        $receivedIds = array_filter(array_map(fn($r) => in_array($r['status'], ['fulfilled', 'received', 'closed']) ? (int)$r['id'] : null, $reqs));
        $linesByReq = [];
        if (!empty($receivedIds)) {
            $ph = implode(',', array_fill(0, count($receivedIds), '?'));
            // Self-healing: ensure unused_qty column exists before querying
            try {
                $db->query("SELECT unused_qty FROM requisition_lines LIMIT 0");
            } catch (Exception $e) {
                $db->exec("ALTER TABLE requisition_lines ADD COLUMN unused_qty DECIMAL(10,2) DEFAULT 0");
            }
            $lStmt = $db->prepare("SELECT rl.id, rl.requisition_id, rl.item_id, rl.item_name, rl.uom,
                rl.order_qty, rl.fulfilled_qty, rl.received_qty, rl.unused_qty
                FROM requisition_lines rl WHERE rl.requisition_id IN ($ph) ORDER BY rl.item_name");
            $lStmt->execute(array_values($receivedIds));
            foreach ($lStmt->fetchAll() as $line) {
                $linesByReq[(int)$line['requisition_id']][] = $line;
            }
        }

        jsonResponse(['requisitions' => $reqs, 'summary' => $summary, 'lines_by_req' => $linesByReq ?: new \stdClass()]);

    // ── Get items for requisition form (cached) — legacy, kept for backward compat ──
    case 'get_items':
        $q = trim($_GET['q'] ?? '');

        if (!$q) {
            // Use cache for unfiltered list
            $result = getCachedItems();
            jsonResponse($result);
        }

        // Filtered search — query DB directly
        $escaped = escapeLike($q);
        $sql = "SELECT id, name, code, category, uom, stock_qty, portion_weight, order_mode FROM items WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY category, name";
        $stmt = $db->prepare($sql);
        $stmt->execute(["%$escaped%", "%$escaped%"]);
        $items = $stmt->fetchAll();

        $grouped = [];
        foreach ($items as $item) {
            $c = $item['category'] ?: 'Uncategorized';
            $grouped[$c][] = $item;
        }

        jsonResponse(['items' => $items, 'grouped' => $grouped]);

    // ── Search recipes for dish picker ──
    case 'search_recipes':
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) jsonError('Search query too short');

        $escaped = escapeLike($q);
        $stmt = $db->prepare("SELECT id, name, cuisine, servings, prep_time,
            (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = recipes.id) AS ingredient_count
            FROM recipes WHERE (name LIKE ? OR cuisine LIKE ?)
            ORDER BY name LIMIT 20");
        $stmt->execute(["%$escaped%", "%$escaped%"]);
        $recipes = $stmt->fetchAll();

        jsonResponse(['recipes' => $recipes]);

    // ── Get recipe ingredients with stock data ──
    case 'get_recipe_ingredients':
        $recipeId = (int)($_GET['recipe_id'] ?? 0);
        if (!$recipeId) jsonError('Recipe ID required');

        $stmt = $db->prepare("SELECT id, name, cuisine, servings, prep_time FROM recipes WHERE id = ?");
        $stmt->execute([$recipeId]);
        $recipe = $stmt->fetch();
        if (!$recipe) jsonError('Recipe not found', 404);

        $stmt = $db->prepare("SELECT ri.id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
            i.name AS item_name, i.stock_qty, i.portion_weight, i.order_mode, i.category
            FROM recipe_ingredients ri
            LEFT JOIN items i ON i.id = ri.item_id
            WHERE ri.recipe_id = ?
            ORDER BY ri.is_primary DESC, i.name");
        $stmt->execute([$recipeId]);
        $ingredients = $stmt->fetchAll();

        jsonResponse(['recipe' => $recipe, 'ingredients' => $ingredients]);

    // ── Add a single dish to a requisition (from Recipes page) ──
    case 'add_single_dish':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqId = (int)($data['requisition_id'] ?? 0);
        $recipeId = (int)($data['recipe_id'] ?? 0);
        if (!$reqId || !$recipeId) jsonError('Requisition ID and Recipe ID required');

        // Verify requisition is draft
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in draft status');

        // Get recipe info
        $stmt = $db->prepare("SELECT id, name, servings FROM recipes WHERE id = ?");
        $stmt->execute([$recipeId]);
        $recipe = $stmt->fetch();
        if (!$recipe) jsonError('Recipe not found');

        // Check not already added
        $stmt = $db->prepare("SELECT id FROM requisition_dishes WHERE requisition_id = ? AND recipe_id = ?");
        $stmt->execute([$reqId, $recipeId]);
        if ($stmt->fetch()) jsonError('This dish is already in that order');

        // Insert
        $guestCount = (int)($req['guest_count'] ?? 20);
        $recipeServings = (int)($recipe['servings'] ?? 4);
        if ($recipeServings < 1) $recipeServings = 4;
        $scaleFactor = $guestCount / $recipeServings;

        $stmt = $db->prepare("INSERT INTO requisition_dishes (requisition_id, recipe_id, recipe_name, recipe_servings, scale_factor, guest_count)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$reqId, $recipeId, $recipe['name'], $recipeServings, round($scaleFactor, 3), $guestCount]);

        auditLog('requisition_add_dish', 'requisition', $reqId, null, [
            'recipe_id' => $recipeId, 'recipe_name' => $recipe['name']
        ]);

        jsonResponse(['added' => true, 'recipe_name' => $recipe['name']]);

    // ── Save dish-based requisition lines ──
    case 'save_dish_lines':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqId = (int)($data['requisition_id'] ?? 0);
        $dishes = $data['dishes'] ?? [];
        $guestCount = (int)($data['guest_count'] ?? 20);
        if (!$reqId) jsonError('Requisition ID required');
        if (empty($dishes)) jsonError('At least one dish is required');

        // Verify requisition is draft
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in draft status');

        // Load kitchen rounding settings
        $roundingMode = 'half';
        try {
            $settingsStmt = $db->prepare("SELECT rounding_mode FROM kitchens WHERE id = ?");
            $settingsStmt->execute([$req['kitchen_id']]);
            $kitchenRow = $settingsStmt->fetch();
            if ($kitchenRow && $kitchenRow['rounding_mode']) $roundingMode = $kitchenRow['rounding_mode'];
        } catch (Exception $e) { /* columns may not exist yet */ }

        // Self-healing: add source tracking columns if missing
        try {
            $db->query("SELECT source_dish_id FROM requisition_lines LIMIT 0");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE requisition_lines ADD COLUMN source_dish_id INT DEFAULT NULL, ADD COLUMN source_recipe_id INT DEFAULT NULL");
        }

        $db->beginTransaction();
        try {
            // Clear old dish entries and lines for this requisition
            $db->prepare("DELETE FROM requisition_dishes WHERE requisition_id = ?")->execute([$reqId]);
            $db->prepare("DELETE FROM requisition_lines WHERE requisition_id = ?")->execute([$reqId]);

            // Aggregated items: itemId => { item_name, total_qty, uom, stock_qty, portion_weight, order_mode, category, sources[] }
            $aggregated = [];

            // Batch-load ALL recipe ingredients in one query (avoids N+1)
            $recipeIds = array_unique(array_filter(array_map(fn($d) => (int)($d['recipe_id'] ?? 0), $dishes)));
            $allIngredients = [];
            if ($recipeIds) {
                $ph = implode(',', array_fill(0, count($recipeIds), '?'));
                $batchIngStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom,
                    i.name AS item_name, i.stock_qty, i.portion_weight, i.order_mode, i.category
                    FROM recipe_ingredients ri
                    LEFT JOIN items i ON i.id = ri.item_id
                    WHERE ri.recipe_id IN ($ph)");
                $batchIngStmt->execute(array_values($recipeIds));
                foreach ($batchIngStmt->fetchAll() as $ing) {
                    $allIngredients[(int)$ing['recipe_id']][] = $ing;
                }
            }

            foreach ($dishes as $dish) {
                $recipeId = (int)($dish['recipe_id'] ?? 0);
                $recipeName = $dish['recipe_name'] ?? '';
                $recipeServings = (int)($dish['recipe_servings'] ?? 4);
                if ($recipeServings < 1) $recipeServings = 4;

                // Per-dish portions: each dish can have its own portion count
                $dishPortions = (int)($dish['dish_portions'] ?? $guestCount);
                if ($dishPortions < 1) $dishPortions = $guestCount;

                $scaleFactor = $dishPortions / $recipeServings;

                // Insert dish record
                $dStmt = $db->prepare("INSERT INTO requisition_dishes (requisition_id, recipe_id, recipe_name, recipe_servings, scale_factor, guest_count)
                    VALUES (?, ?, ?, ?, ?, ?)");
                $dStmt->execute([$reqId, $recipeId, $recipeName, $recipeServings, round($scaleFactor, 3), $dishPortions]);
                $dishId = $db->lastInsertId();

                // Use pre-loaded ingredients (no per-dish query)
                $ingredients = $allIngredients[$recipeId] ?? [];

                foreach ($ingredients as $ing) {
                    $itemId = (int)$ing['item_id'];
                    $scaledQty = (float)$ing['qty'] * $scaleFactor;

                    if (isset($aggregated[$itemId])) {
                        $aggregated[$itemId]['total_qty'] += $scaledQty;
                        $aggregated[$itemId]['sources'][] = ['dish_id' => $dishId, 'recipe_id' => $recipeId, 'recipe_name' => $recipeName];
                    } else {
                        $aggregated[$itemId] = [
                            'item_name' => $ing['item_name'],
                            'total_qty' => $scaledQty,
                            'uom' => $ing['uom'] ?? ($ing['order_mode'] === 'direct_kg' ? 'kg' : 'kg'),
                            'stock_qty' => (float)$ing['stock_qty'],
                            'portion_weight' => (float)$ing['portion_weight'],
                            'order_mode' => $ing['order_mode'],
                            'category' => $ing['category'],
                            'sources' => [['dish_id' => $dishId, 'recipe_id' => $recipeId, 'recipe_name' => $recipeName]],
                        ];
                    }
                }
            }

            // Apply manual adjustments if provided
            $adjustments = $data['adjustments'] ?? [];
            foreach ($adjustments as $itemId => $adj) {
                if (isset($aggregated[(int)$itemId])) {
                    $aggregated[(int)$itemId]['total_qty'] += (float)$adj;
                }
            }

            // Insert aggregated lines
            $insertStmt = $db->prepare("INSERT INTO requisition_lines
                (requisition_id, item_id, item_name, meal, order_mode, portions, portion_weight, required_kg, stock_qty, order_qty, uom, source_dish_id, source_recipe_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)");

            $totalItems = 0;
            $totalKg = 0;
            $meal = $req['meals'] ?? 'lunch';

            // Rounding helper per kitchen setting
            $roundUp = function($val) use ($roundingMode) {
                if ($roundingMode === 'none') return $val;
                if ($roundingMode === 'whole') return ceil($val);
                return ceil($val * 2) / 2; // 'half' — round up to nearest 0.5
            };

            foreach ($aggregated as $itemId => $agg) {
                $requiredKg = $roundUp($agg['total_qty']);
                $orderQty = max(0, $roundUp($requiredKg - $agg['stock_qty']));

                if ($requiredKg <= 0) continue;

                // Use first source for tracking
                $sourceDishId = $agg['sources'][0]['dish_id'] ?? null;
                $sourceRecipeId = $agg['sources'][0]['recipe_id'] ?? null;

                $insertStmt->execute([
                    $reqId, $itemId, $agg['item_name'], $meal, $agg['order_mode'],
                    $agg['portion_weight'], $requiredKg, $agg['stock_qty'], $orderQty,
                    $agg['uom'], $sourceDishId, $sourceRecipeId
                ]);

                $totalItems++;
                $totalKg += $orderQty;
            }

            // Update guest count on requisition
            $db->prepare("UPDATE requisitions SET guest_count = ?, updated_at = NOW() WHERE id = ?")->execute([$guestCount, $reqId]);

            $db->commit();

            auditLog('requisition_save_dish_lines', 'requisition', $reqId, null, [
                'dishes' => count($dishes), 'items' => $totalItems, 'total_kg' => $totalKg, 'guests' => $guestCount
            ]);

            jsonResponse(['saved' => true, 'total_items' => $totalItems, 'total_kg' => round($totalKg, 2), 'dish_count' => count($dishes)]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to save dish lines: ' . $e->getMessage());
        }

    // ── Get dishes for a requisition with all ingredients (batch) ──
    case 'get_dishes_with_ingredients':
        $reqId = (int)($_GET['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        // Get dishes
        $dStmt = $db->prepare("SELECT rd.recipe_id, rd.recipe_name, rd.recipe_servings, rd.scale_factor
            FROM requisition_dishes rd WHERE rd.requisition_id = ? ORDER BY rd.created_at");
        $dStmt->execute([$reqId]);
        $dishes = $dStmt->fetchAll();

        if (empty($dishes)) {
            jsonResponse(['dishes' => [], 'ingredients_by_recipe' => new \stdClass()]);
        }

        // Batch-load all recipe ingredients in ONE query
        $recipeIds = array_unique(array_column($dishes, 'recipe_id'));
        $ph = implode(',', array_fill(0, count($recipeIds), '?'));
        $iStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
            i.name AS item_name, i.stock_qty, i.portion_weight, i.order_mode, i.category
            FROM recipe_ingredients ri
            LEFT JOIN items i ON i.id = ri.item_id
            WHERE ri.recipe_id IN ($ph)
            ORDER BY ri.recipe_id, ri.is_primary DESC, i.name");
        $iStmt->execute(array_values($recipeIds));

        $ingredientsByRecipe = [];
        foreach ($iStmt->fetchAll() as $ing) {
            $ingredientsByRecipe[$ing['recipe_id']][] = $ing;
        }

        jsonResponse(['dishes' => $dishes, 'ingredients_by_recipe' => $ingredientsByRecipe ?: new \stdClass()]);

    default:
        jsonError('Unknown action');
}
