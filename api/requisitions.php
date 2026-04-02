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

        $lines = $db->prepare("SELECT rl.*, i.stock_qty AS current_stock, i.code AS item_code FROM requisition_lines rl LEFT JOIN items i ON i.id = rl.item_id WHERE rl.requisition_id = ? AND rl.status != 'rejected' ORDER BY rl.item_name");
        $lines->execute([$id]);
        $lineData = $lines->fetchAll();

        // Include dishes with per-dish portions
        $dStmt = $db->prepare("SELECT rd.recipe_id, rd.recipe_name, rd.recipe_servings, rd.scale_factor, rd.guest_count
            FROM requisition_dishes rd WHERE rd.requisition_id = ? ORDER BY rd.created_at");
        $dStmt->execute([$id]);
        $dishData = $dStmt->fetchAll();

        jsonResponse(['requisition' => $req, 'lines' => $lineData, 'dishes' => $dishData]);

    // ── Page init: single call for everything the requisition page needs ──
    case 'page_init':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqDate = $data['req_date'] ?? date('Y-m-d');
        $kid = (int)($data['kitchen_id'] ?? $kitchenId);
        $guestCount = (int)($data['guest_count'] ?? 20);
        if (!$kid) jsonError('Kitchen ID required');

        // 1. Kitchen settings
        $initSettings = ['default_guest_count' => 20, 'rounding_mode' => 'half', 'min_order_qty' => 0.5];
        try {
            $sStmt = $db->prepare("SELECT default_guest_count, rounding_mode, min_order_qty FROM kitchens WHERE id = ?");
            $sStmt->execute([$kid]);
            $sRow = $sStmt->fetch();
            if ($sRow) {
                $initSettings = [
                    'default_guest_count' => (int)($sRow['default_guest_count'] ?? 20),
                    'rounding_mode' => $sRow['rounding_mode'] ?? 'half',
                    'min_order_qty' => (float)($sRow['min_order_qty'] ?? 0.5),
                ];
            }
        } catch (Exception $e) { /* columns may not exist yet */ }

        // Apply settings to guest count if not explicitly set
        if ($guestCount === 20 && $initSettings['default_guest_count'] !== 20) {
            $guestCount = $initSettings['default_guest_count'];
        }

        // 2. Active types
        $initTypes = $db->query("SELECT id, name, code, sort_order FROM requisition_types WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
        if (empty($initTypes)) {
            $defaults = [['Breakfast', 'breakfast', 1], ['Lunch', 'lunch', 2], ['Dinner', 'dinner', 3]];
            $seedStmt = $db->prepare("INSERT IGNORE INTO requisition_types (name, code, sort_order) VALUES (?, ?, ?)");
            foreach ($defaults as $d) $seedStmt->execute($d);
            $initTypes = $db->query("SELECT id, name, code, sort_order FROM requisition_types WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
        }

        // 3. Auto-create requisitions (INSERT IGNORE — safe for duplicates)
        $initCreated = 0;
        $insertStmt = $db->prepare("INSERT IGNORE INTO requisitions
            (kitchen_id, req_date, session_number, guest_count, meals, supplement_number, status, created_by)
            VALUES (?, ?, ?, ?, ?, 0, 'draft', ?)");
        foreach ($initTypes as $type) {
            $insertStmt->execute([$kid, $reqDate, $type['sort_order'], $guestCount, $type['code'], $user['id']]);
            if ($insertStmt->rowCount() > 0) $initCreated++;
        }

        // 4. Fetch all sessions for this date
        $rStmt = $db->prepare("SELECT r.*, u.name AS chef_name,
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id) AS line_count
            FROM requisitions r LEFT JOIN users u ON u.id = r.created_by
            WHERE r.req_date = ? AND r.kitchen_id = ?
            ORDER BY r.session_number ASC, r.supplement_number ASC");
        $rStmt->execute([$reqDate, $kid]);
        $initReqs = $rStmt->fetchAll();

        // 5. Preload first session's full data (lines + dishes + ingredients)
        $firstSession = null;
        $targetId = (int)($data['active_session_id'] ?? 0);
        $firstReq = $targetId ? array_values(array_filter($initReqs, fn($r) => (int)$r['id'] === $targetId))[0] ?? $initReqs[0] ?? null : $initReqs[0] ?? null;
        if ($firstReq) {
            $fid = (int)$firstReq['id'];
            // Lines
            $lStmt = $db->prepare("SELECT rl.*, i.stock_qty AS current_stock FROM requisition_lines rl LEFT JOIN items i ON i.id = rl.item_id WHERE rl.requisition_id = ? ORDER BY rl.item_name");
            $lStmt->execute([$fid]);
            $fLines = $lStmt->fetchAll();

            // Dishes + ingredients
            $dStmt = $db->prepare("SELECT rd.recipe_id, rd.recipe_name, rd.recipe_servings, rd.scale_factor, rd.guest_count
                FROM requisition_dishes rd WHERE rd.requisition_id = ? ORDER BY rd.created_at");
            $dStmt->execute([$fid]);
            $fDishes = $dStmt->fetchAll();

            $fIngredients = new \stdClass();
            if (!empty($fDishes)) {
                $recipeIds = array_unique(array_column($fDishes, 'recipe_id'));
                $ph = implode(',', array_fill(0, count($recipeIds), '?'));
                $iStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
                    i.name AS item_name, i.stock_qty, i.portion_weight, i.order_mode, i.category
                    FROM recipe_ingredients ri LEFT JOIN items i ON i.id = ri.item_id
                    WHERE ri.recipe_id IN ($ph) ORDER BY ri.recipe_id, ri.is_primary DESC, i.name");
                $iStmt->execute(array_values($recipeIds));
                $byRecipe = [];
                foreach ($iStmt->fetchAll() as $ing) $byRecipe[$ing['recipe_id']][] = $ing;
                $fIngredients = $byRecipe ?: new \stdClass();
            }

            $firstSession = [
                'requisition' => $firstReq,
                'lines' => $fLines,
                'dishes' => $fDishes,
                'ingredients_by_recipe' => $fIngredients,
            ];
        }

        jsonResponse([
            'settings' => $initSettings,
            'types' => $initTypes,
            'requisitions' => $initReqs,
            'created' => $initCreated,
            'first_session' => $firstSession,
        ]);

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
        $migrated = cacheGet('uk_migration_v5_done', 86400 * 365);
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
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
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

                // 2b. Add is_active to recipes if missing
                try {
                    $db->query("SELECT is_active FROM recipes LIMIT 0");
                } catch (Exception $e2) {
                    $db->exec("ALTER TABLE recipes ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                }

                // 2c. Add is_active to set_menu_items if missing
                try {
                    $db->query("SELECT is_active FROM set_menu_items LIMIT 0");
                } catch (Exception $e2) {
                    $db->exec("ALTER TABLE set_menu_items ADD COLUMN is_active TINYINT(1) DEFAULT 1");
                }

                // 2d. Add unused_qty column to requisition_lines if missing
                try {
                    $db->query("SELECT unused_qty FROM requisition_lines LIMIT 0");
                } catch (Exception $e2) {
                    $db->exec("ALTER TABLE requisition_lines ADD COLUMN unused_qty DECIMAL(10,2) DEFAULT 0");
                }

                // 2e. Add is_staple column to requisition_lines if missing
                try {
                    $db->query("SELECT is_staple FROM requisition_lines LIMIT 0");
                } catch (Exception $e2) {
                    $db->exec("ALTER TABLE requisition_lines ADD COLUMN is_staple TINYINT(1) DEFAULT 0");
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

                cacheSet('uk_migration_v5_done', true);
            } catch (Exception $e) {
                // Do NOT cache on failure — retry next request
                error_log('Karibu migration error: ' . $e->getMessage());
            }
        }

        // One-time: seed dinner set menu if missing
        $dinnerSeeded = cacheGet('dinner_menu_seeded_v1', 86400 * 365);
        if (!$dinnerSeeded) {
            try {
                $dinnerCount = (int)$db->query("SELECT COUNT(*) FROM set_menu_items WHERE type_code = 'dinner' AND is_active = 1")->fetchColumn();
                if ($dinnerCount === 0) {
                    $dinnerData = [
                        [1,'dinner',34,'Vegetable Spring Rolls',1],[1,'dinner',38,'Cream of Broccoli Soup',2],[1,'dinner',42,'Braised Lamb Chops',3],[1,'dinner',13,'Grilled Breast Chicken with Lyonnaise Potatoes and Salad',4],[1,'dinner',49,'Vegetarian Spaghetti Bolognaise',5],[1,'dinner',53,'Red Kidney Beans in Coconut Sauce',6],[1,'dinner',57,'Invisible Apple Cake',7],[1,'dinner',61,'Passion and Cheddar Cheese Tart',8],
                        [2,'dinner',35,'Caprese Salad with Basil Pesto',1],[2,'dinner',39,'Pumpkin Soup',2],[2,'dinner',43,'Grilled Beef Fillet',3],[2,'dinner',46,'Pan-Fried Nile Perch Fillet',4],[2,'dinner',50,'Stir-Fried Vegetables with Noodles or Rice',5],[2,'dinner',54,'Vegetable Lasagne with Salad',6],[2,'dinner',58,'Chocolate Brownies',7],[2,'dinner',62,'Sticky Toffee Pudding',8],
                        [3,'dinner',36,'Curried Sweet Potato Samosas with Tomato Salsa',1],[3,'dinner',40,'Baby Marrow Soup',2],[3,'dinner',44,'Grilled Pork Chop with Rice and Honey Mustard Sauce',3],[3,'dinner',47,'One-Pot Garlic Chicken with Tagliatelle Pasta',4],[3,'dinner',51,'Vegetable Ratatouille',5],[3,'dinner',55,'Pasta Alfredo with Garlic Toast',6],[3,'dinner',59,'Malva Pudding',7],[3,'dinner',63,'Pineapple Upside-Down Cake',8],
                        [4,'dinner',37,'Sliced Beetroot with Orange Segments and Feta Cheese',1],[4,'dinner',41,'Mixed Vegetable Soup',2],[4,'dinner',45,'Tilapia Fish Fillet',3],[4,'dinner',48,'Beef, Carrot and Potato Stew',4],[4,'dinner',52,'Vegetable Risotto',5],[4,'dinner',56,'Veg Moussaka',6],[4,'dinner',60,'Apple Crumble with Custard Sauce',7],[4,'dinner',64,'Lemon Cheesecake',8],
                        [5,'dinner',34,'Vegetable Spring Rolls',1],[5,'dinner',38,'Cream of Broccoli Soup',2],[5,'dinner',42,'Braised Lamb Chops',3],[5,'dinner',13,'Grilled Breast Chicken with Lyonnaise Potatoes and Salad',4],[5,'dinner',49,'Vegetarian Spaghetti Bolognaise',5],[5,'dinner',53,'Red Kidney Beans in Coconut Sauce',6],[5,'dinner',57,'Invisible Apple Cake',7],[5,'dinner',61,'Passion and Cheddar Cheese Tart',8],
                        [6,'dinner',35,'Caprese Salad with Basil Pesto',1],[6,'dinner',39,'Pumpkin Soup',2],[6,'dinner',43,'Grilled Beef Fillet',3],[6,'dinner',46,'Pan-Fried Nile Perch Fillet',4],[6,'dinner',50,'Stir-Fried Vegetables with Noodles or Rice',5],[6,'dinner',54,'Vegetable Lasagne with Salad',6],[6,'dinner',58,'Chocolate Brownies',7],[6,'dinner',62,'Sticky Toffee Pudding',8],
                        [7,'dinner',36,'Curried Sweet Potato Samosas with Tomato Salsa',1],[7,'dinner',40,'Baby Marrow Soup',2],[7,'dinner',44,'Grilled Pork Chop with Rice and Honey Mustard Sauce',3],[7,'dinner',47,'One-Pot Garlic Chicken with Tagliatelle Pasta',4],[7,'dinner',51,'Vegetable Ratatouille',5],[7,'dinner',55,'Pasta Alfredo with Garlic Toast',6],[7,'dinner',59,'Malva Pudding',7],[7,'dinner',63,'Pineapple Upside-Down Cake',8],
                    ];
                    $ins = $db->prepare("INSERT IGNORE INTO set_menu_items (day_of_week, type_code, recipe_id, recipe_name, servings, sort_order, is_active) VALUES (?, ?, ?, ?, 4, ?, 1)");
                    foreach ($dinnerData as $d) {
                        $ins->execute([$d[0], $d[1], $d[2], $d[3], $d[4]]);
                    }
                }
                cacheSet('dinner_menu_seeded_v1', true);
            } catch (Exception $e) {
                error_log('Dinner seed error: ' . $e->getMessage());
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
            $batchStmt = $db->prepare("SELECT i.id, i.name, i.portion_weight, i.order_mode, i.uom,
                COALESCE(ki.qty, 0) AS stock_qty
                FROM items i
                LEFT JOIN kitchen_inventory ki ON ki.item_id = i.id AND ki.kitchen_id = ?
                WHERE i.id IN ($placeholders)");
            $batchStmt->execute(array_merge([$kitchenId], array_values($itemIds)));
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

        $db->prepare("UPDATE requisitions SET status = 'submitted', created_by = ?, updated_at = NOW() WHERE id = ?")->execute([$user['id'], $reqId]);

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

        // For any lines NOT in the receipt confirmation, default received_qty to fulfilled_qty
        // This ensures no lines are left with NULL received_qty
        if (!empty($lineIds)) {
            $ph2 = implode(',', array_fill(0, count($lineIds), '?'));
            $db->prepare("UPDATE requisition_lines SET received_qty = COALESCE(fulfilled_qty, 0) WHERE requisition_id = ? AND id NOT IN ($ph2) AND received_qty IS NULL AND status != 'rejected'")
               ->execute(array_merge([$reqId], $lineIds));
        } else {
            // No lines confirmed at all — default everything to fulfilled
            $db->prepare("UPDATE requisition_lines SET received_qty = COALESCE(fulfilled_qty, 0) WHERE requisition_id = ? AND received_qty IS NULL AND status != 'rejected'")
               ->execute([$reqId]);
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
            // Save unused quantities and add to kitchen pantry inventory
            $updateLine = $db->prepare("UPDATE requisition_lines SET unused_qty = ? WHERE id = ?");
            $upsertPantry = $db->prepare("INSERT INTO kitchen_inventory (kitchen_id, item_id, qty) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)");

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
                $upsertPantry->execute([$kid, (int)$lineRow['item_id'], $unusedQty]);
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
            $upsertPantry = $db->prepare("INSERT INTO kitchen_inventory (kitchen_id, item_id, qty) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE qty = GREATEST(0, qty + VALUES(qty))");

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
                $upsertPantry->execute([$kitchenId, (int)$lineRow['item_id'], $delta]);
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
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND status != 'rejected') AS line_count,
            (SELECT COALESCE(SUM(order_qty), 0) FROM requisition_lines WHERE requisition_id = r.id AND status != 'rejected') AS total_kg
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
                rl.order_qty, rl.fulfilled_qty, rl.received_qty, rl.unused_qty,
                IFNULL(rl.is_staple, 0) AS is_staple
                FROM requisition_lines rl WHERE rl.requisition_id IN ($ph) AND rl.status != 'rejected' ORDER BY rl.item_name");
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

        $sql = "SELECT id, name, cuisine, servings, prep_time,
            (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = recipes.id) AS ingredient_count
            FROM recipes WHERE 1=1";
        $params = [];

        // Chef sees only their own recipes
        if ($user['role'] === 'chef') {
            $sql .= ' AND created_by = ?';
            $params[] = $user['id'];
        }

        if (strlen($q) >= 2) {
            $escaped = escapeLike($q);
            $sql .= ' AND (name LIKE ? OR cuisine LIKE ?)';
            $params[] = "%$escaped%";
            $params[] = "%$escaped%";
        }

        $sql .= ' ORDER BY name LIMIT 30';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
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
            i.name AS item_name, COALESCE(ki.qty, 0) AS stock_qty, i.portion_weight, i.order_mode, i.category
            FROM recipe_ingredients ri
            LEFT JOIN items i ON i.id = ri.item_id
            LEFT JOIN kitchen_inventory ki ON ki.item_id = ri.item_id AND ki.kitchen_id = ?
            WHERE ri.recipe_id = ?
            ORDER BY ri.is_primary DESC, i.name");
        $stmt->execute([$kitchenId, $recipeId]);
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

    // ── Lock menu: save dishes + generate items, set status to 'processing' ──
    case 'lock_menu':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in draft status');

        // Set lock flag and redirect to save_dish_lines via goto
        $data['_lock_menu'] = true;
        $input = $data;
        $_GET['action'] = 'save_dish_lines';
        goto save_dish_lines_entry;

    // ── Submit order: take a processing requisition and submit to store ──
    case 'submit_order':
        if (($_GET['action'] ?? '') === 'submit_order') {
            requireMethod('POST');
            requireRole(['chef', 'admin']);
            $data = getJsonInput();
            $reqId = (int)($data['requisition_id'] ?? 0);
            if (!$reqId) jsonError('Requisition ID required');

            $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('draft', 'processing')");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();
            if (!$req) jsonError('Requisition not found or not ready to submit');

            // Apply any quantity adjustments from the orders page
            $lineUpdates = $data['lines'] ?? [];
            if (!empty($lineUpdates)) {
                $updStmt = $db->prepare('UPDATE requisition_lines SET order_qty = ? WHERE id = ? AND requisition_id = ?');
                foreach ($lineUpdates as $lu) {
                    $qty = max(0, (float)($lu['order_qty'] ?? 0));
                    $updStmt->execute([$qty, (int)$lu['id'], $reqId]);
                }
            }

            // Submit
            $db->prepare("UPDATE requisitions SET status = 'submitted', updated_at = NOW() WHERE id = ?")->execute([$reqId]);

            // Send push notification to storekeeper
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
            } catch (Exception $e) {}

            auditLog('requisition_submit_order', 'requisition', $reqId);
            jsonResponse(['submitted' => true, 'requisition_id' => $reqId]);
        }
        // If we got here without matching submit_order, we came from lock_menu fall-through
        // Continue to save_dish_lines
        break;

    // ── Add a line item to an order (chef can add items not from menu) ──
    // ── Recalculate order quantities after guest count change (before store issues) ──
    case 'recalculate_order':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        $newGuestCount = (int)($data['guest_count'] ?? 0);
        if (!$reqId || $newGuestCount < 1) jsonError('Requisition ID and valid guest count required');

        // Only allow editing before store fulfills
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('processing', 'submitted')");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Order not found or already fulfilled');

        $oldGuestCount = (int)($req['guest_count'] ?: 20);
        if ($oldGuestCount === $newGuestCount) jsonResponse(['updated' => true, 'message' => 'No change']);

        $ratio = $newGuestCount / max(1, $oldGuestCount);

        $db->beginTransaction();
        try {
            // Update requisition guest count
            $db->prepare("UPDATE requisitions SET guest_count = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$newGuestCount, $reqId]);

            // Recalculate all non-staple line quantities proportionally
            $db->prepare("UPDATE requisition_lines SET order_qty = ROUND(order_qty * ?, 1), portions = ? WHERE requisition_id = ? AND (is_staple = 0 OR is_staple IS NULL)")
               ->execute([$ratio, $newGuestCount, $reqId]);

            // Also update requisition_dishes guest_count and scale_factor
            $db->prepare("UPDATE requisition_dishes SET guest_count = ?, scale_factor = ROUND(scale_factor * ?, 3) WHERE requisition_id = ?")
               ->execute([$newGuestCount, $ratio, $reqId]);

            $db->commit();
            auditLog('recalculate_order', 'requisitions', $reqId, ['guest_count' => $oldGuestCount], ['guest_count' => $newGuestCount, 'ratio' => $ratio]);
            jsonResponse(['updated' => true, 'old_guest_count' => $oldGuestCount, 'new_guest_count' => $newGuestCount, 'ratio' => round($ratio, 3)]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to recalculate: ' . $e->getMessage());
        }
        break;

    case 'add_line_to_order':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        try {
            $data = getJsonInput();
            $reqId = (int)($data['requisition_id'] ?? 0);
            $itemId = (int)($data['item_id'] ?? 0);
            $itemName = trim($data['item_name'] ?? '');
            $orderQty = max(0, (float)($data['order_qty'] ?? 1));
            $uom = trim($data['uom'] ?? 'kg');
            $isStaple = (int)($data['is_staple'] ?? 1);

            if (!$reqId || (!$itemId && !$itemName)) jsonError('Requisition ID and item required');

            // Self-healing: ensure is_staple column exists
            try { $db->query("SELECT is_staple FROM requisition_lines LIMIT 0"); }
            catch (Exception $e) { $db->exec("ALTER TABLE requisition_lines ADD COLUMN is_staple TINYINT(1) DEFAULT 0"); }

            // Get item name from items table if item_id provided
            if ($itemId && !$itemName) {
                $iStmt = $db->prepare('SELECT name, uom FROM items WHERE id = ?');
                $iStmt->execute([$itemId]);
                $iRow = $iStmt->fetch();
                if ($iRow) { $itemName = $iRow['name']; if (!$uom || $uom === 'kg') $uom = $iRow['uom']; }
            }

            $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('draft','processing','submitted')");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();
            if (!$req) jsonError('Requisition not found or not editable');

            // Check if item already exists (only if item_id is provided)
            if ($itemId) {
                $existCheck = $db->prepare("SELECT id FROM requisition_lines WHERE requisition_id = ? AND item_id = ? AND status != 'rejected'");
                $existCheck->execute([$reqId, $itemId]);
                if ($existCheck->fetch()) jsonError('Item already in this order');
            }

            $ins = $db->prepare("INSERT INTO requisition_lines (requisition_id, item_id, item_name, uom, order_qty, status, is_staple) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $ins->execute([$reqId, $itemId ?: null, $itemName, $uom, $orderQty, $isStaple]);
            $lineId = $db->lastInsertId();

            auditLog('add_line_to_order', 'requisition_lines', $lineId, null, ['item' => $itemName, 'qty' => $orderQty]);
            jsonResponse(['line_id' => $lineId, 'added' => true]);
        } catch (Exception $e) {
            jsonError('Failed to add item: ' . $e->getMessage());
        }
        break;

    // ── Update a single line item (qty/uom) ──
    case 'update_line':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $lineId = (int)($data['line_id'] ?? 0);
        $orderQty = isset($data['order_qty']) ? (float)$data['order_qty'] : -1;
        $uom = trim($data['uom'] ?? '');
        if (!$lineId) jsonError('Line ID required');

        $sets = []; $params = [];
        if ($orderQty >= 0) { $sets[] = 'order_qty = ?'; $params[] = $orderQty; }
        if ($uom) { $sets[] = 'uom = ?'; $params[] = $uom; }
        if (empty($sets)) jsonError('Nothing to update');

        $params[] = $lineId;
        $db->prepare("UPDATE requisition_lines SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        jsonResponse(['updated' => true]);
        break;

    // ── Remove a line item (chef-side delete) ──
    case 'chef_remove_line':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $lineId = (int)($data['line_id'] ?? 0);
        if (!$lineId) jsonError('Line ID required');

        // Verify line belongs to a draft/processing requisition owned by this kitchen
        $check = $db->prepare("SELECT rl.id, r.status, r.kitchen_id FROM requisition_lines rl JOIN requisitions r ON r.id = rl.requisition_id WHERE rl.id = ?");
        $check->execute([$lineId]);
        $row = $check->fetch();
        if (!$row) jsonError('Line not found', 404);
        if (!in_array($row['status'], ['draft', 'processing', 'submitted'])) jsonError('Cannot modify fulfilled orders');

        $db->prepare("DELETE FROM requisition_lines WHERE id = ?")->execute([$lineId]);
        auditLog('chef_remove_line', 'requisition_lines', $lineId);
        jsonResponse(['removed' => true]);
        break;

    // ── Cancel/delete an order (chef can cancel before store fulfills) ──
    case 'cancel_order':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        // Use SELECT FOR UPDATE to prevent race with fulfill
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? FOR UPDATE");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();

            if (!$req || !in_array($req['status'], ['draft', 'processing', 'submitted'])) {
                $db->rollBack();
                jsonError('Order not found or already being fulfilled by store');
            }

            $db->prepare("DELETE FROM requisition_lines WHERE requisition_id = ?")->execute([$reqId]);
            $db->prepare("DELETE FROM requisition_dishes WHERE requisition_id = ?")->execute([$reqId]);
            $db->prepare("UPDATE requisitions SET status = 'draft', updated_at = NOW() WHERE id = ?")->execute([$reqId]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to cancel order');
        }

        auditLog('cancel_order', 'requisitions', $reqId, ['status' => $req['status']], ['status' => 'draft']);
        jsonResponse(['cancelled' => true]);
        break;

    // ── Save dish-based requisition lines ──
    // ── Atomic save + submit (prevents race condition between separate save and submit calls) ──
    case 'save_and_submit':
        if (($_GET['action'] ?? '') === 'save_and_submit') {
            requireMethod('POST');
            requireRole(['chef', 'admin']);
            $data = getJsonInput();
            $reqId = (int)($data['requisition_id'] ?? 0);
            if (!$reqId) jsonError('Requisition ID required');

            $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();
            if (!$req) jsonError('Requisition not found or not in draft status');

            $data['_also_submit'] = true;
            $input = $data;
            $_GET['action'] = 'save_dish_lines';
        }
        // FALL THROUGH to save_dish_lines

    save_dish_lines_entry:
    case 'save_dish_lines':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        if (!isset($data) || empty($data['dishes'])) $data = getJsonInput();

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
        // Self-healing: add source_dishes JSON column if missing
        try {
            $db->query("SELECT source_dishes FROM requisition_lines LIMIT 0");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE requisition_lines ADD COLUMN source_dishes TEXT DEFAULT NULL");
        }
        // Self-healing: add is_staple column if missing
        try {
            $db->query("SELECT is_staple FROM requisition_lines LIMIT 0");
        } catch (Exception $e) {
            $db->exec("ALTER TABLE requisition_lines ADD COLUMN is_staple TINYINT(1) DEFAULT 0");
        }

        $db->beginTransaction();
        try {
            // Clear old dish entries and menu-generated lines (preserve manually-added staple lines)
            $db->prepare("DELETE FROM requisition_dishes WHERE requisition_id = ?")->execute([$reqId]);
            $db->prepare("DELETE FROM requisition_lines WHERE requisition_id = ? AND is_staple = 0")->execute([$reqId]);

            // Aggregated items: itemId => { item_name, total_qty, uom, stock_qty, portion_weight, order_mode, category, sources[] }
            $aggregated = [];
            $staplesSkipped = 0;

            // Batch-load ALL recipe ingredients in one query (avoids N+1)
            $recipeIds = array_unique(array_filter(array_map(fn($d) => (int)($d['recipe_id'] ?? 0), $dishes)));
            $allIngredients = [];
            if ($recipeIds) {
                $ph = implode(',', array_fill(0, count($recipeIds), '?'));
                $batchIngStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
                    i.name AS item_name, COALESCE(ki.qty, 0) AS stock_qty, i.portion_weight, i.order_mode, i.category,
                    i.piece_weight, i.is_pantry_staple
                    FROM recipe_ingredients ri
                    LEFT JOIN items i ON i.id = ri.item_id
                    LEFT JOIN kitchen_inventory ki ON ki.item_id = ri.item_id AND ki.kitchen_id = ?
                    WHERE ri.recipe_id IN ($ph)");
                $batchIngStmt->execute(array_merge([$kitchenId], array_values($recipeIds)));
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
                    if (!$itemId) continue;

                    // Two-level staple check: skip if item is pantry staple AND ingredient is not primary
                    if (!empty($ing['is_pantry_staple']) && empty($ing['is_primary'])) {
                        $staplesSkipped = ($staplesSkipped ?? 0) + 1;
                        continue;
                    }

                    $scaledQty = (float)$ing['qty'] * $scaleFactor;

                    // Convert pcs→kg if item has piece_weight
                    $ingUom = $ing['uom'] ?? 'kg';
                    if (in_array($ingUom, ['pcs', 'tins', 'box', 'pkt', 'unit']) && !empty($ing['piece_weight']) && (float)$ing['piece_weight'] > 0) {
                        $scaledQty = $scaledQty * (float)$ing['piece_weight'];
                        $ingUom = 'kg';
                    }

                    if (isset($aggregated[$itemId])) {
                        $aggregated[$itemId]['total_qty'] += $scaledQty;
                        $aggregated[$itemId]['sources'][] = ['dish_id' => $dishId, 'recipe_id' => $recipeId, 'recipe_name' => $recipeName, 'qty' => $scaledQty];
                    } else {
                        $aggregated[$itemId] = [
                            'item_name' => $ing['item_name'],
                            'total_qty' => $scaledQty,
                            'uom' => $ingUom,
                            'stock_qty' => (float)$ing['stock_qty'],
                            'portion_weight' => (float)$ing['portion_weight'],
                            'order_mode' => $ing['order_mode'],
                            'category' => $ing['category'],
                            'sources' => [['dish_id' => $dishId, 'recipe_id' => $recipeId, 'recipe_name' => $recipeName, 'qty' => $scaledQty]],
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
                (requisition_id, item_id, item_name, meal, order_mode, portions, portion_weight, required_kg, stock_qty, order_qty, uom, source_dish_id, source_recipe_id, source_dishes, is_staple)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");

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

                // Use first source for tracking + store all sources as JSON
                $sourceDishId = $agg['sources'][0]['dish_id'] ?? null;
                $sourceRecipeId = $agg['sources'][0]['recipe_id'] ?? null;
                $sourceDishesJson = json_encode(array_map(function($s) {
                    return ['name' => $s['recipe_name'] ?? '', 'qty' => round($s['qty'] ?? 0, 2)];
                }, $agg['sources']));

                $insertStmt->execute([
                    $reqId, $itemId, $agg['item_name'], $meal, $agg['order_mode'],
                    $guestCount, $agg['portion_weight'], $requiredKg, $agg['stock_qty'], $orderQty,
                    $agg['uom'], $sourceDishId, $sourceRecipeId, $sourceDishesJson
                ]);

                $totalItems++;
                $totalKg += $orderQty;
            }

            // Count total including preserved staple lines
            $stapleLineCount = $db->prepare("SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = ? AND is_staple = 1");
            $stapleLineCount->execute([$reqId]);
            $existingStaples = (int)$stapleLineCount->fetchColumn();
            $grandTotal = $totalItems + $existingStaples;

            // Set status based on which action was called
            $alsoSubmit = !empty($data['_also_submit']);
            $lockMenu = !empty($data['_lock_menu']);
            if ($alsoSubmit && $grandTotal > 0) {
                $db->prepare("UPDATE requisitions SET guest_count = ?, status = 'submitted', created_by = ?, updated_at = NOW() WHERE id = ?")->execute([$guestCount, $user['id'], $reqId]);
            } elseif ($lockMenu) {
                // Always transition to processing on lock_menu — chef can add staples on Orders page even if 0 menu items
                $db->prepare("UPDATE requisitions SET guest_count = ?, status = 'processing', created_by = ?, updated_at = NOW() WHERE id = ?")->execute([$guestCount, $user['id'], $reqId]);
            } else {
                $db->prepare("UPDATE requisitions SET guest_count = ?, created_by = ?, updated_at = NOW() WHERE id = ?")->execute([$guestCount, $user['id'], $reqId]);
            }

            $db->commit();

            auditLog($alsoSubmit ? 'requisition_save_and_submit' : 'requisition_save_dish_lines', 'requisition', $reqId, null, [
                'dishes' => count($dishes), 'items' => $totalItems, 'total_kg' => $totalKg, 'guests' => $guestCount
            ]);

            // Send push notification if submitting
            if ($alsoSubmit && $grandTotal > 0) {
                try {
                    $kitchenName = '';
                    $kStmt2 = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
                    $kStmt2->execute([$req['kitchen_id']]);
                    $kRow2 = $kStmt2->fetch();
                    if ($kRow2) $kitchenName = $kRow2['name'];
                    $mealLabel2 = ucfirst($req['meals'] ?? 'order');
                    $suppNum2 = (int)($req['supplement_number'] ?? 0);
                    if ($suppNum2 > 0) $mealLabel2 .= ' (' . ($suppNum2 + 1) . ')';
                    $pushPayload2 = [
                        'title' => 'New Requisition',
                        'body'  => "{$user['name']} submitted {$mealLabel2} for {$kitchenName}",
                        'url'   => '/app.php?page=store-dashboard',
                        'tag'   => 'req-submitted-' . $reqId,
                    ];
                    sendPushToKitchen((int)$req['kitchen_id'], $pushPayload2, 'storekeeper', $user['id']);
                    storeNotification((int)$req['kitchen_id'], null, $pushPayload2['title'], $pushPayload2['body'], 'requisition_submitted', $reqId);
                } catch (Exception $e) {
                    error_log('Notification error on save_and_submit: ' . $e->getMessage());
                }
            }

            jsonResponse([
                'saved' => true, 'submitted' => $alsoSubmit,
                'total_items' => $totalItems, 'total_kg' => round($totalKg, 2),
                'dish_count' => count($dishes), 'staples_skipped' => $staplesSkipped
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to save dish lines: ' . $e->getMessage());
        }

    // ── Get dishes for a requisition with all ingredients (batch) ──
    case 'get_dishes_with_ingredients':
        $reqId = (int)($_GET['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        // Get dishes
        $dStmt = $db->prepare("SELECT rd.recipe_id, rd.recipe_name, rd.recipe_servings, rd.scale_factor, rd.guest_count
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

    // ── Admin: reset all orders for a clean start ──
    case 'reset_all_orders':
        requireMethod('POST');
        requireRole(['admin']);

        $db->exec("DELETE FROM requisition_lines");
        $db->exec("DELETE FROM requisition_dishes");
        $db->exec("DELETE FROM requisitions");
        $db->exec("DELETE FROM notifications");

        jsonResponse(['message' => 'All orders, lines, dishes, and notifications cleared']);

    default:
        jsonError('Unknown action');
}
