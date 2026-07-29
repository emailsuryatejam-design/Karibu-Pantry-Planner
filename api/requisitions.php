<?php
/**
 * Karibu Pantry Planner — Requisitions API
 * Core ordering system: portions-based + direct KG
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/push-sender.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../pdf.php';

$user = requireAuth();
$action = $_GET['action'] ?? 'list';
$db = getDB();
$kitchenId = $user['kitchen_id'] ?? null;

/**
 * Meal-type codes to auto-create draft requisitions for, per kitchen.
 * Prevents empty "phantom" drafts for meal types a kitchen never serves
 * (e.g. sundowner / bush_dinner at a camp that doesn't offer them).
 * = core meals (always) ∪ meals this kitchen has actually generated an order for
 *   (any status beyond 'draft' means it was locked/submitted at least once).
 * Any other meal can still be started on demand via the 'ensure_session' action.
 */
function mealsToAutoCreate(PDO $db, int $kid): array {
    $meals = ['breakfast', 'lunch', 'dinner'];
    try {
        $stmt = $db->prepare("SELECT DISTINCT LOWER(meals) m FROM requisitions
            WHERE kitchen_id = ? AND status <> 'draft' AND deleted_at IS NULL");
        $stmt->execute([$kid]);
        foreach ($stmt->fetchAll() as $r) $meals[] = $r['m'];
    } catch (Exception $e) { /* fall back to core meals */ }
    return array_values(array_unique($meals));
}

/**
 * Translate a computed order quantity into the item's purchase unit (how the store buys/stocks it),
 * so the chef plans in recipe units but the order/store speak the buying unit.
 *   - item bought by kg/ltr  → keep weight/volume (g→kg, ml→ltr already handled upstream)
 *   - item bought as a PACK (pcs/tin/bottle/box/pkt/…):
 *        · qty already a count  → keep the count (eggs, apples — never "convert" a count)
 *        · qty in weight/volume → divide by pack size (grams/ml per pack) → whole packs, rounded up
 *        · no pack size on file → leave in kg as a safe fallback
 * Returns [qty, uom]. Pure read-time helper — recipes are never altered.
 */
function toPurchaseUnit(float $qty, ?string $curUom, ?string $itemUom, $packSizeG): array {
    $cur = strtolower(trim((string)$curUom));
    $iu  = strtolower(trim((string)$itemUom));
    if ($iu === '' || $iu === $cur) return [$qty, $curUom ?: ($itemUom ?: 'kg')];
    $count = ['pcs','pc','piece','tin','tins','box','pkt','packet','bottle','bunch','tray','bag','unit','can','cans'];
    if (in_array($cur, $count) && in_array($iu, $count)) return [$qty, $itemUom];        // count → count: keep
    if (in_array($iu, ['kg','kgs','kilogram','kilograms'])) {
        if (in_array($cur, ['g','grams','gram','gm'])) return [$qty / 1000, 'kg'];
        return [$qty, 'kg'];
    }
    if (in_array($iu, ['ltr','ltrs','l','litre','litres','liter'])) {
        if (in_array($cur, ['ml','mls','milliliter','millilitre'])) return [$qty / 1000, 'ltr'];
        return [$qty, 'ltr'];
    }
    if (in_array($iu, $count)) {
        $base = null;
        if (in_array($cur, ['kg','kgs','kilogram','kilograms'])) $base = $qty * 1000;
        elseif (in_array($cur, ['g','grams','gram','gm'])) $base = $qty;
        elseif (in_array($cur, ['l','ltr','ltrs','litre','litres','liter'])) $base = $qty * 1000;
        elseif (in_array($cur, ['ml','mls','milliliter','millilitre'])) $base = $qty;
        $ps = (float)$packSizeG;
        if ($base !== null && $ps > 0) return [ceil($base / $ps), $itemUom];             // → whole packs
        return [$base !== null ? round($base / 1000, 2) : $qty, $base !== null ? 'kg' : $itemUom]; // fallback
    }
    return [$qty, $itemUom];
}

/**
 * Back-dating guard (2026-07-16).
 * Orders may only be created or changed for TODAY or later — no back-dated requisitions.
 * `date('Y-m-d')` is EAT (config.php sets Africa/Dar_es_Salaam), i.e. the camps' own day.
 *
 * Admins are exempt, so an old order can still be corrected when there is a genuine reason.
 * Deliberately NOT guarded — these legitimately happen AFTER the order's own date:
 *   fulfill / confirm_receipt / close / close_with_unused / update_unused / day_close_*
 *   / cancel_order / admin_*  — plus all read-only actions.
 */
function guardBackdate(PDO $db, string $action, array $user): void {
    static $GUARDED = [
        'create', 'create_supplementary', 'ensure_session', 'save_lines', 'submit',
        'add_single_dish', 'add_custom_dish', 'add_packed_dish', 'remove_packed_dish',
        'lock_menu', 'submit_order', 'recalculate_order', 'add_line_to_order',
        'toggle_line', 'update_line', 'chef_remove_line', 'save_and_submit', 'save_dish_lines',
    ];
    if (!in_array($action, $GUARDED, true)) return;
    if (($user['role'] ?? '') === 'admin') return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;

    $in = getJsonInput();
    $date = null;

    if (!empty($in['req_date'])) {
        $date = substr((string)$in['req_date'], 0, 10);
    } else {
        // Resolve the order's date from whichever id this action carries.
        $reqId = (int)($in['requisition_id'] ?? $in['parent_id'] ?? 0);
        if (!$reqId && !empty($in['line_id'])) {
            $s = $db->prepare("SELECT requisition_id FROM requisition_lines WHERE id = ?");
            $s->execute([(int)$in['line_id']]);
            $reqId = (int)$s->fetchColumn();
        }
        if ($reqId) {
            $s = $db->prepare("SELECT req_date FROM requisitions WHERE id = ?");
            $s->execute([$reqId]);
            $date = $s->fetchColumn() ?: null;
        }
    }

    if ($date && $date < date('Y-m-d')) {
        jsonError('That day has already passed — orders can only be made or changed for today onwards.', 400);
    }
}

guardBackdate($db, $action, $user);

switch ($action) {

    // ── List requisitions for a date/kitchen ──
    case 'list':
        $date = $_GET['date'] ?? date('Y-m-d');
        $status = $_GET['status'] ?? '';
        $kid = (int)($_GET['kitchen_id'] ?? $kitchenId);

        $sql = "SELECT r.*, u.name AS chef_name,
                (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL) AS line_count
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

        // The chef's Orders page passes include_off=1 so toggled-off (rejected) lines stay
        // visible there (greyed, switch-back-on) — every other caller keeps hiding them.
        $offFilter = !empty($_GET['include_off']) ? '' : "AND rl.status != 'rejected' ";
        $lines = $db->prepare("SELECT rl.*, i.stock_qty AS current_stock, i.code AS item_code FROM requisition_lines rl LEFT JOIN items i ON i.id = rl.item_id WHERE rl.requisition_id = ? AND rl.deleted_at IS NULL $offFilter ORDER BY rl.item_name");
        $lines->execute([$id]);
        $lineData = $lines->fetchAll();

        // Include dishes with per-dish portions
        $dStmt = $db->prepare("SELECT rd.recipe_id, rd.recipe_name, rd.recipe_servings, rd.scale_factor, rd.guest_count
            FROM requisition_dishes rd WHERE rd.requisition_id = ? AND rd.deleted_at IS NULL ORDER BY rd.created_at");
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

        // 3. Auto-create requisitions (INSERT IGNORE — safe for duplicates).
        //    Only for meals this kitchen actually uses — avoids empty phantom drafts.
        //    Other meals stay available via the "+ start meal" chip (ensure_session).
        //    Never auto-create on a PAST date — browsing back through the calendar must not
        //    silently make back-dated drafts (that is how old empty orders piled up).
        $initCreated = 0;
        if ($reqDate >= date('Y-m-d')) {
            $autoMeals = mealsToAutoCreate($db, $kid);
            $insertStmt = $db->prepare("INSERT IGNORE INTO requisitions
                (kitchen_id, req_date, session_number, guest_count, meals, supplement_number, status, created_by)
                VALUES (?, ?, ?, ?, ?, 0, 'draft', ?)");
            foreach ($initTypes as $type) {
                if (!in_array(strtolower($type['code']), $autoMeals, true)) continue;
                $insertStmt->execute([$kid, $reqDate, $type['sort_order'], $guestCount, $type['code'], $user['id']]);
                if ($insertStmt->rowCount() > 0) $initCreated++;
            }
        }

        // 4. Fetch all sessions for this date
        $rStmt = $db->prepare("SELECT r.*, u.name AS chef_name,
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL) AS line_count
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
            $lStmt = $db->prepare("SELECT rl.*, i.stock_qty AS current_stock FROM requisition_lines rl LEFT JOIN items i ON i.id = rl.item_id WHERE rl.requisition_id = ? AND rl.deleted_at IS NULL ORDER BY rl.item_name");
            $lStmt->execute([$fid]);
            $fLines = $lStmt->fetchAll();

            // Dishes + ingredients
            $dStmt = $db->prepare("SELECT rd.recipe_id, rd.recipe_name, rd.recipe_servings, rd.scale_factor, rd.guest_count
                FROM requisition_dishes rd WHERE rd.requisition_id = ? AND rd.deleted_at IS NULL ORDER BY rd.created_at");
            $dStmt->execute([$fid]);
            $fDishes = $dStmt->fetchAll();

            $fIngredients = new \stdClass();
            if (!empty($fDishes)) {
                $recipeIds = array_unique(array_column($fDishes, 'recipe_id'));
                $ph = implode(',', array_fill(0, count($recipeIds), '?'));
                $iStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
                    i.name AS item_name, i.stock_qty, i.portion_weight, i.order_mode, i.category
                    FROM recipe_ingredients ri LEFT JOIN items i ON i.id = ri.item_id
                    WHERE ri.recipe_id IN ($ph) AND ri.deleted_at IS NULL ORDER BY ri.recipe_id, ri.is_primary DESC, i.name");
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

        // Fix #8: send push reminder for any fulfilled orders awaiting receipt for >8h
        try {
            require_once __DIR__ . '/../lib/push-sender.php';
            $staleStmt = $db->prepare("
                SELECT r.id, r.meals, r.req_date FROM requisitions r
                WHERE r.kitchen_id = ?
                  AND r.status = 'fulfilled'
                  AND r.updated_at < DATE_SUB(NOW(), INTERVAL 8 HOUR)
                  AND NOT EXISTS (
                      SELECT 1 FROM notifications n
                      WHERE n.ref_id = r.id
                        AND n.type = 'receipt_reminder'
                        AND n.created_at > DATE_SUB(NOW(), INTERVAL 20 HOUR)
                  )
                LIMIT 3
            ");
            $staleStmt->execute([$kid]);
            foreach ($staleStmt->fetchAll() as $sr) {
                $mealLabel = ucfirst($sr['meals']);
                $dateLabel = date('d M', strtotime($sr['req_date']));
                $payload = [
                    'title' => '⏰ Confirm Receipt',
                    'body'  => "Please confirm receipt for your {$mealLabel} order ({$dateLabel})",
                    'url'   => '/app.php?page=orders',
                    'tag'   => 'receipt-reminder-' . $sr['id'],
                ];
                sendPushToKitchen((int)$kid, $payload, 'chef', null);
                storeNotification((int)$kid, null, $payload['title'], $payload['body'], 'receipt_reminder', (int)$sr['id']);
            }
        } catch (Exception $e) { /* non-critical */ }

        jsonResponse([
            'settings' => $initSettings,
            'types' => $initTypes,
            'requisitions' => $initReqs,
            'created' => $initCreated,
            'first_session' => $firstSession,
        ]);

    // ── Create a single draft on demand for one meal (the "+ start meal" chip) ──
    //    Lets a chef begin a meal type that isn't auto-created, without phantom drafts.
    case 'ensure_session':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $kid = (int)($data['kitchen_id'] ?? $kitchenId);
        $reqDate = $data['req_date'] ?? date('Y-m-d');
        $meal = strtolower(trim($data['meals'] ?? ''));
        if (!$kid || $meal === '') jsonError('kitchen_id and meals required');

        $tStmt = $db->prepare("SELECT sort_order FROM requisition_types WHERE code = ? AND is_active = 1");
        $tStmt->execute([$meal]);
        $sortOrder = $tStmt->fetchColumn();
        if ($sortOrder === false) jsonError('Unknown meal type');

        $gc = (int)($data['guest_count'] ?? 20);
        if ($gc <= 0) $gc = 20;
        $insOne = $db->prepare("INSERT IGNORE INTO requisitions
            (kitchen_id, req_date, session_number, guest_count, meals, supplement_number, status, created_by)
            VALUES (?, ?, ?, ?, ?, 0, 'draft', ?)");
        $insOne->execute([$kid, $reqDate, (int)$sortOrder, $gc, $meal, $user['id']]);

        $getOne = $db->prepare("SELECT * FROM requisitions
            WHERE kitchen_id = ? AND req_date = ? AND meals = ? AND supplement_number = 0 AND deleted_at IS NULL
            ORDER BY id LIMIT 1");
        $getOne->execute([$kid, $reqDate, $meal]);
        jsonResponse(['requisition' => $getOne->fetch()]);

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
        // Never auto-create on a PAST date (see page_init) — browsing back must not back-date.
        $created = 0;
        if ($reqDate >= date('Y-m-d')) {
            $autoMeals = mealsToAutoCreate($db, $kid);
            $insertStmt = $db->prepare("INSERT IGNORE INTO requisitions
                (kitchen_id, req_date, session_number, guest_count, meals, supplement_number, status, created_by)
                VALUES (?, ?, ?, ?, ?, 0, 'draft', ?)");

            foreach ($types as $type) {
                if (!in_array(strtolower($type['code']), $autoMeals, true)) continue;
                $insertStmt->execute([$kid, $reqDate, $type['sort_order'], $guestCount, $type['code'], $user['id']]);
                if ($insertStmt->rowCount() > 0) $created++;
            }
        }

        if ($created > 0) {
            auditLog('requisition_auto_create', 'requisition', null, null, [
                'date' => $reqDate, 'kitchen_id' => $kid, 'created' => $created
            ]);
        }

        // Return all requisitions for this date
        $stmt = $db->prepare("SELECT r.*, u.name AS chef_name,
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL) AS line_count
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
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL) AS line_count
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

        // Soft-delete existing lines before re-inserting
        $db->prepare("UPDATE requisition_lines SET deleted_at = NOW(), deleted_by = ? WHERE requisition_id = ? AND deleted_at IS NULL")->execute([$user['id'], $reqId]);

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

        // Check has lines (only count active, non-deleted, non-rejected lines)
        $lineCount = $db->prepare("SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = ? AND deleted_at IS NULL AND status != 'rejected'");
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

        // ── Email alert with PDF attachment ──
        try {
            if (!isset($kitchenName)) {
                $kStmt = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
                $kStmt->execute([$req['kitchen_id']]);
                $kRow = $kStmt->fetch();
                $kitchenName = $kRow ? $kRow['name'] : 'Camp';
            }
            if (!isset($mealLabel)) {
                $mealLabel = ucfirst($req['meals'] ?? 'order');
                $suppNum = (int)($req['supplement_number'] ?? 0);
                if ($suppNum > 0) $mealLabel .= ' (' . ($suppNum + 1) . ')';
            }
            $reqDate    = date('D, d M Y', strtotime($req['req_date']));
            $guestCount = (int)($req['guest_count'] ?? 0);
            $appUrl     = APP_URL;

            // Fetch lines for PDF — same fields as the printOrder() sheet storekeepers print
            $lineStmt = $db->prepare("
                SELECT rl.order_qty, rl.uom, rl.fulfilled_qty, rl.received_qty, rl.unused_qty,
                       i.name AS item_name, i.code AS item_code
                FROM requisition_lines rl
                LEFT JOIN items i ON i.id = rl.item_id
                WHERE rl.requisition_id = ? AND rl.deleted_at IS NULL
                ORDER BY i.name
            ");
            $lineStmt->execute([$reqId]);
            $pdfLines = $lineStmt->fetchAll();

            // Fetch dishes for PDF
            $dishStmt = $db->prepare("
                SELECT rd.recipe_name, rd.guest_count
                FROM requisition_dishes rd
                WHERE rd.requisition_id = ? AND rd.deleted_at IS NULL
                ORDER BY rd.id
            ");
            $dishStmt->execute([$reqId]);
            $pdfDishes = $dishStmt->fetchAll();

            // Build submitter / chef name
            $req['submitter_name'] = $user['name'] ?? 'Chef';
            $req['chef_name']      = $user['name'] ?? 'Chef';

            // Generate PDF — isolated try/catch so a PDF failure never blocks the email
            $pdfBytes    = null;
            $pdfFilename = 'Order-' . preg_replace('/[^a-z0-9]/i', '-', $kitchenName) . '-' . str_replace(' ', '-', $mealLabel) . '-' . date('Y-m-d', strtotime($req['req_date'])) . '.pdf';
            try {
                $pdfBytes = generateOrderPDF($req, $kitchenName, $mealLabel, $pdfLines, $pdfDishes);
            } catch (Exception $pdfEx) {
                error_log('[Karibu PDF] PDF generation failed for req #' . $reqId . ': ' . $pdfEx->getMessage());
                // email still sends below, just without attachment
            }

            $hasPdf = $pdfBytes ? ' (PDF attached)' : '';
            $emailSubject = "New Order: {$mealLabel} — {$kitchenName} ({$reqDate})";
            $emailBody = "
              <p>A new requisition has been submitted and requires your attention.</p>
              <table style='border-collapse:collapse;width:100%;font-size:13px;margin:16px 0'>
                <tr style='background:#f9fafb'>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280;width:130px'>Camp</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$kitchenName}</td>
                </tr>
                <tr>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Date</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$reqDate}</td>
                </tr>
                <tr style='background:#f9fafb'>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Meal</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$mealLabel}</td>
                </tr>
                <tr>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Guests</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$guestCount}</td>
                </tr>
                <tr style='background:#f9fafb'>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Submitted by</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$user['name']}</td>
                </tr>
              </table>
              <p style='color:#6b7280;font-size:13px'>Please log in to review and fulfill this order{$hasPdf}.</p>";

            $html = mailTemplate("New Requisition Submitted", $emailBody, "View Store Dashboard", "{$appUrl}/app.php?page=store-dashboard");
            notifyStorekeepersWithPDF((int)$req['kitchen_id'], $emailSubject, $html, $pdfBytes, $pdfFilename);
            notifyAdminsWithPDF($emailSubject, $html, $pdfBytes, $pdfFilename);
            error_log('[Karibu Email] submit alert sent for req #' . $reqId . ' (' . ($pdfBytes ? 'with PDF' : 'no PDF') . ')');
        } catch (Exception $e) {
            error_log('[Karibu Email] submit alert failed for req #' . $reqId . ': ' . $e->getMessage());
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

        $updateLine = $db->prepare("UPDATE requisition_lines SET fulfilled_qty = ?, status = 'approved', store_notes = ? WHERE id = ? AND requisition_id = ? AND deleted_at IS NULL");
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

        // ── Email alert: notify the chef who submitted + all chefs in this kitchen ──
        try {
            if (!isset($kitchenName) || !$kitchenName) {
                $kStmt2 = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
                $kStmt2->execute([$req['kitchen_id']]);
                $kRow2 = $kStmt2->fetch();
                $kitchenName = $kRow2 ? $kRow2['name'] : 'Camp';
            }
            if (!isset($mealLabel)) {
                $mealLabel = ucfirst($req['meals'] ?? 'order');
                $suppNum = (int)($req['supplement_number'] ?? 0);
                if ($suppNum > 0) $mealLabel .= ' (' . ($suppNum + 1) . ')';
            }
            $reqDate = date('D, d M Y', strtotime($req['req_date']));
            $appUrl  = APP_URL;

            $emailSubject = "Order Ready: {$mealLabel} — {$kitchenName} ({$reqDate})";
            $emailBody = "
              <p>Great news! Your order has been fulfilled by the store and is ready for collection.</p>
              <table style='border-collapse:collapse;width:100%;font-size:13px;margin:16px 0'>
                <tr style='background:#f9fafb'>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280;width:130px'>Camp</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$kitchenName}</td>
                </tr>
                <tr>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Date</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$reqDate}</td>
                </tr>
                <tr style='background:#f9fafb'>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Meal</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$mealLabel}</td>
                </tr>
                <tr>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600;color:#6b7280'>Fulfilled by</td>
                  <td style='padding:8px 12px;border:1px solid #e5e7eb'>{$user['name']}</td>
                </tr>
              </table>
              <p style='color:#6b7280;font-size:13px'>Please confirm receipt once you have collected the items.</p>";

            $html = mailTemplate(
                "Your Order Has Been Fulfilled",
                $emailBody,
                "Confirm Receipt",
                "{$appUrl}/app.php?page=day-close"
            );
            // Notify the chef who submitted specifically
            if (!empty($req['created_by'])) {
                notifyUser((int)$req['created_by'], $emailSubject, $html);
            }
            // Also notify all chefs in the kitchen (in case another chef needs to collect)
            notifyChefs((int)$req['kitchen_id'], $emailSubject, $html);
        } catch (Exception $e) {
            error_log('[Karibu Email] fulfill alert failed: ' . $e->getMessage());
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
            $db->prepare("UPDATE requisition_lines SET received_qty = COALESCE(fulfilled_qty, 0) WHERE requisition_id = ? AND id NOT IN ($ph2) AND received_qty IS NULL AND deleted_at IS NULL AND status != 'rejected'")
               ->execute(array_merge([$reqId], $lineIds));
        } else {
            // No lines confirmed at all — default everything to fulfilled
            $db->prepare("UPDATE requisition_lines SET received_qty = COALESCE(fulfilled_qty, 0) WHERE requisition_id = ? AND received_qty IS NULL AND deleted_at IS NULL AND status != 'rejected'")
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

    // ── Day-close per-item reconciliation: items used today + what's available ──
    //    available = opening kitchen stock (line snapshot) + what the store actually sent.
    //    The chef declares what's left (unused); that becomes the new stock (overwrite).
    case 'day_close_items':
        requireRole(['chef', 'admin']);
        $date = $_GET['date'] ?? date('Y-m-d');
        $kid  = (int)($_GET['kitchen_id'] ?? $kitchenId);
        if (!$kid) jsonError('Kitchen ID required');

        // One row per item across all of today's locked requisitions.
        // opening_stock = MAX(stock_qty snapshot) — the kitchen stock at lock time (single opening value per item).
        // received = what the store fulfilled/the chef received (only counts issued goods).
        $stmt = $db->prepare("
            SELECT rl.item_id,
                   MAX(rl.item_name) AS item_name,
                   MAX(rl.uom) AS uom,
                   MAX(COALESCE(rl.stock_qty, 0)) AS opening_stock,
                   COALESCE(SUM(rl.order_qty), 0) AS ordered_today,
                   COALESCE(SUM(CASE WHEN r.status IN ('fulfilled','received','closed')
                        THEN COALESCE(NULLIF(rl.received_qty, 0), rl.fulfilled_qty, 0) ELSE 0 END), 0) AS received_today
            FROM requisition_lines rl
            JOIN requisitions r ON r.id = rl.requisition_id
            WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status <> 'draft'
              AND rl.deleted_at IS NULL AND rl.status <> 'rejected' AND rl.item_id IS NOT NULL
            GROUP BY rl.item_id
            ORDER BY MAX(rl.item_name)
        ");
        $stmt->execute([$date, $kid]);
        $rows = $stmt->fetchAll();

        // current kitchen stock (what was last declared) — used to prefill when re-editing a closed day
        $invMap = [];
        $invStmt = $db->prepare("SELECT item_id, qty FROM kitchen_inventory WHERE kitchen_id = ?");
        $invStmt->execute([$kid]);
        foreach ($invStmt->fetchAll() as $iv) $invMap[(int)$iv['item_id']] = (float)$iv['qty'];

        $items = [];
        foreach ($rows as $r) {
            $opening  = (float)$r['opening_stock'];
            $received = (float)$r['received_today'];
            $items[] = [
                'item_id'       => (int)$r['item_id'],
                'item_name'     => $r['item_name'],
                'uom'           => $r['uom'] ?: 'kg',
                'opening_stock' => round($opening, 2),
                'ordered'       => round((float)$r['ordered_today'], 2),
                'received'      => round($received, 2),
                'available'     => round($opening + $received, 2),
                'current_stock' => round($invMap[(int)$r['item_id']] ?? 0, 2),
            ];
        }

        $statusStmt = $db->prepare("SELECT status, COUNT(*) c FROM requisitions WHERE req_date=? AND kitchen_id=? AND status<>'draft' GROUP BY status");
        $statusStmt->execute([$date, $kid]);
        $statusCounts = [];
        foreach ($statusStmt->fetchAll() as $s) $statusCounts[$s['status']] = (int)$s['c'];

        jsonResponse(['date' => $date, 'items' => $items, 'status_counts' => $statusCounts]);

    // ── Day-close reconcile: set kitchen stock = declared unused (overwrite), close the day ──
    case 'day_close_reconcile':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $date = $data['date'] ?? date('Y-m-d');
        $kid  = (int)($data['kitchen_id'] ?? $kitchenId);
        $itemsIn = $data['items'] ?? []; // [{item_id, unused}, ...]
        if (!$kid) jsonError('Kitchen ID required');

        // Recompute available server-side (authoritative cap) — identical to day_close_items
        $availStmt = $db->prepare("
            SELECT rl.item_id,
                   MAX(COALESCE(rl.stock_qty, 0)) AS opening_stock,
                   COALESCE(SUM(CASE WHEN r.status IN ('fulfilled','received','closed')
                        THEN COALESCE(NULLIF(rl.received_qty, 0), rl.fulfilled_qty, 0) ELSE 0 END), 0) AS received_today
            FROM requisition_lines rl
            JOIN requisitions r ON r.id = rl.requisition_id
            WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status <> 'draft'
              AND rl.deleted_at IS NULL AND rl.status <> 'rejected' AND rl.item_id IS NOT NULL
            GROUP BY rl.item_id
        ");
        $availStmt->execute([$date, $kid]);
        $availMap = [];
        foreach ($availStmt->fetchAll() as $a) {
            $availMap[(int)$a['item_id']] = (float)$a['opening_stock'] + (float)$a['received_today'];
        }

        $db->beginTransaction();
        try {
            // Overwrite kitchen stock with the declared leftover (clamped to what was available)
            $setStock = $db->prepare("INSERT INTO kitchen_inventory (kitchen_id, item_id, qty) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE qty = VALUES(qty)");
            $applied = 0;
            foreach ($itemsIn as $it) {
                $itemId = (int)($it['item_id'] ?? 0);
                if (!$itemId || !isset($availMap[$itemId])) continue;
                $unused = max(0, (float)($it['unused'] ?? 0));
                if ($unused > $availMap[$itemId]) $unused = $availMap[$itemId];
                $setStock->execute([$kid, $itemId, $unused]);
                $applied++;
            }

            // Complete the order lifecycle for the day (mirror the existing close behaviour)
            $db->prepare("UPDATE requisition_lines rl
                JOIN requisitions r ON r.id = rl.requisition_id
                SET rl.received_qty = rl.fulfilled_qty
                WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status = 'fulfilled' AND (rl.received_qty IS NULL OR rl.received_qty = 0)")
               ->execute([$date, $kid]);
            $db->prepare("UPDATE requisitions SET status = 'closed', updated_at = NOW()
                WHERE req_date = ? AND kitchen_id = ? AND status IN ('received','fulfilled')")
               ->execute([$date, $kid]);

            $db->commit();
            auditLog('day_close_reconcile', 'kitchen', $kid, null, ['date' => $date, 'items_set' => $applied]);
            jsonResponse(['closed' => true, 'items_set' => $applied]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to close: ' . $e->getMessage());
        }

    // ── Whole-day print: every requisition for a date with lines + dishes, in one payload ──
    case 'day_print':
        requireRole(['chef', 'admin']);
        $date = $_GET['date'] ?? date('Y-m-d');
        $kid  = (int)($_GET['kitchen_id'] ?? $kitchenId);
        if (!$kid) jsonError('Kitchen ID required');

        $knStmt = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
        $knStmt->execute([$kid]);
        $kitchenName = $knStmt->fetchColumn() ?: '';

        $rStmt = $db->prepare("SELECT r.id, r.meals, r.status, r.guest_count, r.req_date, r.supplement_number,
                u.name AS chef_name
            FROM requisitions r LEFT JOIN users u ON u.id = r.created_by
            WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status <> 'draft'
            ORDER BY r.session_number ASC, r.supplement_number ASC");
        $rStmt->execute([$date, $kid]);
        $reqs = $rStmt->fetchAll();

        $lStmt = $db->prepare("SELECT rl.item_name, rl.uom, rl.required_kg, rl.stock_qty, rl.order_qty,
                rl.fulfilled_qty, rl.received_qty, rl.unused_qty, IFNULL(rl.is_staple,0) AS is_staple, i.code AS item_code
            FROM requisition_lines rl LEFT JOIN items i ON i.id = rl.item_id
            WHERE rl.requisition_id = ? AND rl.deleted_at IS NULL AND rl.status <> 'rejected'
            ORDER BY rl.is_staple ASC, rl.item_name");
        $dStmt = $db->prepare("SELECT recipe_name, guest_count FROM requisition_dishes
            WHERE requisition_id = ? AND deleted_at IS NULL ORDER BY created_at");

        $out = [];
        foreach ($reqs as $r) {
            $lStmt->execute([(int)$r['id']]);
            $lines = $lStmt->fetchAll();
            if (empty($lines)) continue; // skip meals with no items
            $dStmt->execute([(int)$r['id']]);
            $r['lines']  = $lines;
            $r['dishes'] = $dStmt->fetchAll();
            $out[] = $r;
        }

        jsonResponse(['date' => $date, 'kitchen_name' => $kitchenName, 'requisitions' => $out]);

    // ── Whole-day PDF (download/print from the app) — chef, store, admin ──
    case 'day_pdf':
        requireRole(['chef', 'admin', 'storekeeper']);
        $date = $_GET['date'] ?? date('Y-m-d');
        $kid  = (int)($_GET['kitchen_id'] ?? $kitchenId);
        if (!$kid) { http_response_code(400); echo 'Kitchen ID required'; exit; }

        $knStmt = $db->prepare("SELECT name FROM kitchens WHERE id = ?");
        $knStmt->execute([$kid]);
        $kitchenName = $knStmt->fetchColumn() ?: 'Kitchen';

        $rStmt = $db->prepare("SELECT r.id, r.meals, r.status, r.guest_count, u.name AS chef_name
            FROM requisitions r LEFT JOIN users u ON u.id = r.created_by
            WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status <> 'draft'
            ORDER BY r.session_number, r.supplement_number");
        $rStmt->execute([$date, $kid]);
        $lStmt = $db->prepare("SELECT rl.item_name, rl.uom, rl.required_kg, rl.stock_qty, rl.order_qty,
                rl.fulfilled_qty, rl.received_qty, rl.unused_qty, IFNULL(rl.is_staple,0) AS is_staple
            FROM requisition_lines rl WHERE rl.requisition_id = ? AND rl.deleted_at IS NULL AND rl.status <> 'rejected'
            ORDER BY rl.is_staple ASC, rl.item_name");
        $dStmt = $db->prepare("SELECT recipe_name, guest_count FROM requisition_dishes WHERE requisition_id = ? AND deleted_at IS NULL ORDER BY created_at");
        $out = [];
        foreach ($rStmt->fetchAll() as $r) {
            $lStmt->execute([(int)$r['id']]);
            $r['lines'] = $lStmt->fetchAll();
            if (!$r['lines']) continue;
            $dStmt->execute([(int)$r['id']]);
            $r['dishes'] = $dStmt->fetchAll();
            $out[] = $r;
        }

        require_once __DIR__ . '/../pdf.php';
        $pdfBytes = generateDayPDF($kitchenName, $date, $out);
        if (!$pdfBytes) { http_response_code(500); echo 'PDF generation failed'; exit; }
        $fname = 'Requisitions_' . preg_replace('/[^A-Za-z0-9]+/', '', $kitchenName) . '_' . $date . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fname . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
        exit;

    // ── Dashboard stats (chef) — single query ──
    case 'dashboard_stats':
        $kid = (int)($_GET['kitchen_id'] ?? $kitchenId);
        $today = date('Y-m-d');

        // Count by status, excluding empty drafts (0 items) from active count
        $stmt = $db->prepare("SELECT r.status, COUNT(*) AS cnt,
            SUM(CASE WHEN r.status = 'draft' AND (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL) = 0 THEN 1 ELSE 0 END) AS empty_drafts
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
            (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL AND status != 'rejected') AS line_count,
            (SELECT COALESCE(SUM(order_qty), 0) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL AND status != 'rejected') AS total_kg
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
                FROM requisition_lines rl WHERE rl.requisition_id IN ($ph) AND rl.deleted_at IS NULL AND rl.status != 'rejected' ORDER BY rl.item_name");
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
            WHERE ri.recipe_id = ? AND ri.deleted_at IS NULL
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

        // Check not already added (ignore soft-deleted entries so re-adding after removal works)
        $stmt = $db->prepare("SELECT id FROM requisition_dishes WHERE requisition_id = ? AND recipe_id = ? AND deleted_at IS NULL");
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

    // ── Add a CUSTOM dish from the Orders screen ──
    //    Saves it as a reusable recipe (owned by the chef, filed under the order's meal type)
    //    AND adds it to this order, generating its lines. If a recipe of the same name already
    //    exists for this chef it is reused (no duplicate). Recipes are authored by the chef and
    //    never altered by the order process — only read when generating lines.
    case 'add_custom_dish':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        $dishName = trim($data['dish_name'] ?? '');
        $ingredientsIn = $data['ingredients'] ?? [];
        if (!$reqId || $dishName === '') jsonError('Order and dish name are required');
        if (mb_strlen($dishName) > 150) jsonError('Dish name too long (max 150 characters)');

        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('draft','processing','submitted')");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Order not found or already being fulfilled by the store');

        $guestCount = (int)($req['guest_count'] ?? 20); if ($guestCount < 1) $guestCount = 20;
        $servings = (int)($data['servings'] ?? $guestCount); if ($servings < 1) $servings = $guestCount;
        $mealCode = $req['meals'] ?? 'lunch';

        try { $db->query("SELECT is_staple FROM requisition_lines LIMIT 0"); }
        catch (Exception $e) { $db->exec("ALTER TABLE requisition_lines ADD COLUMN is_staple TINYINT(1) DEFAULT 0"); }

        $db->beginTransaction();
        try {
            // 1. Reuse an existing same-name recipe for this chef, else create a new one
            $find = $db->prepare("SELECT id FROM recipes WHERE created_by = ? AND LOWER(name) = LOWER(?) AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
            $find->execute([$user['id'], $dishName]);
            $recipeId = (int)$find->fetchColumn();
            $reused = $recipeId > 0;

            if (!$reused) {
                $cleanIngs = [];
                foreach ($ingredientsIn as $ing) {
                    $nm = trim($ing['item_name'] ?? '');
                    $q  = (float)($ing['qty'] ?? 0);
                    if ($nm === '' || $q <= 0) continue;
                    $cleanIngs[] = [
                        'item_id'    => !empty($ing['item_id']) ? (int)$ing['item_id'] : null,
                        'item_name'  => $nm,
                        'qty'        => $q,
                        'uom'        => trim($ing['uom'] ?? 'kg') ?: 'kg',
                        'is_primary' => isset($ing['is_primary']) ? ((int)$ing['is_primary'] ? 1 : 0) : 1,
                    ];
                }
                if (empty($cleanIngs)) { $db->rollBack(); jsonError('Add at least one ingredient'); }

                $insR = $db->prepare("INSERT INTO recipes (name, category, cuisine, difficulty, servings, created_by, is_active) VALUES (?, ?, NULL, 'medium', ?, ?, 1)");
                $insR->execute([$dishName, $mealCode, $servings, $user['id']]);
                $recipeId = (int)$db->lastInsertId();
                $insI = $db->prepare("INSERT INTO recipe_ingredients (recipe_id, item_id, item_name, qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?)");
                foreach ($cleanIngs as $ci) $insI->execute([$recipeId, $ci['item_id'], $ci['item_name'], $ci['qty'], $ci['uom'], $ci['is_primary']]);
            }

            // 2. Attach as a dish on this order (skip if already attached)
            $scaleFactor = $servings > 0 ? $guestCount / $servings : 1;
            $dchk = $db->prepare("SELECT id FROM requisition_dishes WHERE requisition_id = ? AND recipe_id = ? AND deleted_at IS NULL");
            $dchk->execute([$reqId, $recipeId]);
            $dishId = (int)$dchk->fetchColumn();
            $dishExisted = $dishId > 0;
            if (!$dishExisted) {
                $insD = $db->prepare("INSERT INTO requisition_dishes (requisition_id, recipe_id, recipe_name, recipe_servings, scale_factor, guest_count) VALUES (?, ?, ?, ?, ?, ?)");
                $insD->execute([$reqId, $recipeId, $dishName, $servings, round($scaleFactor, 3), $guestCount]);
                $dishId = (int)$db->lastInsertId();
            }

            // 3. Generate the dish's order lines — only when the dish is newly attached, so
            //    re-adding the same dish to the same order can't double its lines.
            //    (same unit normalization as save_dish_lines)
            $linesAdded = 0;
            $roundUp = function($v) { return ceil($v * 2) / 2; }; // half-up (default kitchen mode)
            $ingRows = [];
            if (!$dishExisted) {
                $ingStmt = $db->prepare("SELECT ri.item_id, ri.qty, ri.uom, ri.item_name,
                        i.uom AS item_uom, i.piece_weight, i.portion_weight, i.order_mode, i.pack_size_g
                    FROM recipe_ingredients ri LEFT JOIN items i ON i.id = ri.item_id
                    WHERE ri.recipe_id = ? AND ri.deleted_at IS NULL AND ri.is_primary = 1");
                $ingStmt->execute([$recipeId]);
                $ingRows = $ingStmt->fetchAll();
            }
            foreach ($ingRows as $ing) {
                $itemId = (int)$ing['item_id'];
                $scaledQty = (float)$ing['qty'] * $scaleFactor;
                $ingUom = $ing['uom'] ?? 'kg';
                $rU = strtolower(trim($ingUom)); $iU = strtolower(trim($ing['item_uom'] ?? ''));
                if (in_array($rU, ['g','grams','gram','gm']) && in_array($iU, ['kg','kgs','kilogram','kilograms'])) { $scaledQty /= 1000; $ingUom = $ing['item_uom']; }
                elseif (in_array($rU, ['ml','milliliter','millilitre','mls']) && in_array($iU, ['ltr','l','litre','liter','lt','ltrs'])) { $scaledQty /= 1000; $ingUom = $ing['item_uom']; }
                elseif (in_array($rU, ['kg','kgs','kilogram']) && in_array($iU, ['g','grams','gram'])) { $scaledQty *= 1000; $ingUom = $ing['item_uom']; }
                if (in_array($ingUom, ['pcs','tins','box','pkt','unit']) && !empty($ing['piece_weight']) && (float)$ing['piece_weight'] > 0) { $scaledQty *= (float)$ing['piece_weight']; $ingUom = 'kg'; }
                $qty = $roundUp($scaledQty);
                // Translate into the item's purchase/stock unit (the store's unit)
                [$qty, $ingUom] = toPurchaseUnit($qty, $ingUom, $ing['item_uom'], $ing['pack_size_g'] ?? null);
                if ($qty <= 0) continue;

                $exRow = false;
                if ($itemId) {
                    $ex = $db->prepare("SELECT id FROM requisition_lines WHERE requisition_id = ? AND item_id = ? AND deleted_at IS NULL AND status <> 'rejected' LIMIT 1");
                    $ex->execute([$reqId, $itemId]); $exRow = $ex->fetch();
                }
                if ($exRow) {
                    $db->prepare("UPDATE requisition_lines SET order_qty = order_qty + ?, required_kg = COALESCE(required_kg,0) + ? WHERE id = ?")
                       ->execute([$qty, $qty, $exRow['id']]);
                } else {
                    $db->prepare("INSERT INTO requisition_lines (requisition_id, item_id, item_name, meal, order_mode, required_kg, order_qty, uom, status, source_dish_id, source_recipe_id, is_staple)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, 0)")
                       ->execute([$reqId, $itemId ?: null, $ing['item_name'], $mealCode, $ing['order_mode'] ?: 'portion', $qty, $qty, $ingUom, $dishId, $recipeId]);
                }
                $linesAdded++;
            }

            $db->prepare("UPDATE requisitions SET updated_at = NOW() WHERE id = ?")->execute([$reqId]);
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('Failed to add dish: ' . $e->getMessage());
        }

        auditLog('add_custom_dish', 'requisitions', $reqId, null, ['dish' => $dishName, 'recipe_id' => $recipeId, 'reused' => $reused, 'lines' => $linesAdded], $reqId);
        jsonResponse(['added' => true, 'recipe_id' => $recipeId, 'reused' => $reused, 'lines_added' => $linesAdded, 'dish_name' => $dishName]);

    // ── Add a packed / no-recipe dish to a breakfast order ──
    case 'add_packed_dish':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();

        $reqId    = (int)($data['requisition_id'] ?? 0);
        $dishName = trim($data['dish_name'] ?? '');
        if (!$reqId || !$dishName) jsonError('Requisition ID and dish name are required');
        if (mb_strlen($dishName) > 150) jsonError('Dish name too long (max 150 chars)');

        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status = 'draft'");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Requisition not found or not in draft status');

        // Prevent duplicate packed dish names on same requisition
        $dup = $db->prepare("SELECT id FROM requisition_dishes WHERE requisition_id = ? AND recipe_id IS NULL AND recipe_name = ?");
        $dup->execute([$reqId, $dishName]);
        if ($dup->fetch()) jsonError('This packed dish is already in the order');

        $guestCount = (int)($req['guest_count'] ?? 20);
        $db->prepare("INSERT INTO requisition_dishes (requisition_id, recipe_id, is_packed, recipe_name, recipe_servings, scale_factor, guest_count)
            VALUES (?, NULL, 1, ?, ?, 1, ?)")
           ->execute([$reqId, $dishName, $guestCount, $guestCount]);

        auditLog('requisition_add_packed_dish', 'requisition', $reqId, null, ['dish_name' => $dishName]);
        jsonResponse(['added' => true, 'dish_name' => $dishName]);

    // ── Remove a packed dish from a requisition ──
    case 'remove_packed_dish':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId    = (int)($data['requisition_id'] ?? 0);
        $dishName = trim($data['dish_name'] ?? '');
        if (!$reqId || !$dishName) jsonError('Requisition ID and dish name required');

        $db->prepare("DELETE FROM requisition_dishes WHERE requisition_id = ? AND recipe_id IS NULL AND recipe_name = ?")
           ->execute([$reqId, $dishName]);
        jsonResponse(['removed' => true]);

    // ── Lock menu: save dishes + generate items, set status to 'processing' ──
    case 'lock_menu':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        // Editable until SUBMIT — allow (re)generating while draft or processing
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('draft','processing')");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Order not found, or already submitted to the store');

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

            // If already submitted/fulfilled, the chef is sending EXTRA items (e.g. staples) added
            // after the fact. Don't dead-end — apply any qty changes, re-notify the store, and
            // resurface the order so the added items aren't missed.
            $statusChk = $db->prepare("SELECT * FROM requisitions WHERE id = ?");
            $statusChk->execute([$reqId]);
            $statusRow = $statusChk->fetch();
            if ($statusRow && in_array($statusRow['status'], ['submitted', 'fulfilled', 'received'])) {
                $lineUpdates = $data['lines'] ?? [];
                if (!empty($lineUpdates)) {
                    $updStmt = $db->prepare('UPDATE requisition_lines SET order_qty = ? WHERE id = ? AND requisition_id = ?');
                    foreach ($lineUpdates as $lu) { $updStmt->execute([max(0, (float)($lu['order_qty'] ?? 0)), (int)$lu['id'], $reqId]); }
                }
                $db->prepare("UPDATE requisitions SET updated_at = NOW() WHERE id = ?")->execute([$reqId]);
                try {
                    $kName = $db->query("SELECT name FROM kitchens WHERE id = " . (int)$statusRow['kitchen_id'])->fetchColumn() ?: 'Camp';
                    $mealLbl = ucfirst($statusRow['meals'] ?? 'order');
                    $body = "{$user['name']} added extra items to the {$mealLbl} order for {$kName} — please re-check before fulfilling.";
                    $payload = ['title' => 'Order updated — items added', 'body' => $body, 'url' => '/app.php?page=store-dashboard', 'tag' => 'req-addition-' . $reqId];
                    sendPushToKitchen((int)$statusRow['kitchen_id'], $payload, 'storekeeper', $user['id']);
                    storeNotification((int)$statusRow['kitchen_id'], null, $payload['title'], $body, 'requisition_addition', $reqId);
                    require_once __DIR__ . '/../mailer.php';
                    $html = mailTemplate('Items added to a submitted order', "<p>{$body}</p>", 'View Store Dashboard', rtrim(APP_URL, '/') . '/app.php?page=store-dashboard');
                    notifyStorekeepers((int)$statusRow['kitchen_id'], "Items added — {$mealLbl} ({$kName})", $html);
                } catch (Exception $e) { error_log('[Karibu] resubmit notify: ' . $e->getMessage()); }
                auditLog('requisition_resubmit_additions', 'requisition', $reqId);
                jsonResponse(['submitted' => true, 'requisition_id' => $reqId, 'notified' => true, 'message' => 'Store notified of the added items']);
            }

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

            // Recalculate all non-staple line quantities proportionally (skip soft-deleted lines)
            $db->prepare("UPDATE requisition_lines SET order_qty = ROUND(order_qty * ?, 1), portions = ? WHERE requisition_id = ? AND deleted_at IS NULL AND (is_staple = 0 OR is_staple IS NULL)")
               ->execute([$ratio, $newGuestCount, $reqId]);

            // Also update requisition_dishes guest_count and scale_factor (skip soft-deleted dishes)
            $db->prepare("UPDATE requisition_dishes SET guest_count = ?, scale_factor = ROUND(scale_factor * ?, 3) WHERE requisition_id = ? AND deleted_at IS NULL")
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
            // All manual line-adds go to Staples. Menu lines come only from lock_menu
            // (recipe ingredients with is_primary=1) — never via this endpoint.
            $isStaple = 1;

            if (!$reqId || (!$itemId && !$itemName)) jsonError('Requisition ID and item required');

            // Self-healing: ensure is_staple column exists
            try { $db->query("SELECT is_staple FROM requisition_lines LIMIT 0"); }
            catch (Exception $e) { $db->exec("ALTER TABLE requisition_lines ADD COLUMN is_staple TINYINT(1) DEFAULT 0"); }

            // Get item name from items table if item_id provided
            if ($itemId && !$itemName) {
                $iStmt = $db->prepare('SELECT name, uom FROM items WHERE id = ?');
                $iStmt->execute([$itemId]);
                $iRow = $iStmt->fetch();
                if ($iRow) { $itemName = $iRow['name']; if (!$uom) $uom = $iRow['uom']; }
            }

            $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('draft','processing','submitted')");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();
            if (!$req) jsonError('Requisition not found or not editable');

            // Check if item already exists (only if item_id is provided)
            if ($itemId) {
                $existCheck = $db->prepare("SELECT id FROM requisition_lines WHERE requisition_id = ? AND item_id = ? AND deleted_at IS NULL AND status != 'rejected'");
                $existCheck->execute([$reqId, $itemId]);
                if ($existCheck->fetch()) jsonError('Item already in this order');
            }

            $ins = $db->prepare("INSERT INTO requisition_lines (requisition_id, item_id, item_name, uom, order_qty, status, is_staple) VALUES (?, ?, ?, ?, ?, 'pending', ?)");
            $ins->execute([$reqId, $itemId ?: null, $itemName, $uom, $orderQty, $isStaple]);
            $lineId = $db->lastInsertId();

            auditLog('add_line_to_order', 'requisition_lines', $lineId, null, ['item' => $itemName, 'qty' => $orderQty, 'uom' => $uom], $reqId);
            jsonResponse(['line_id' => $lineId, 'added' => true]);
        } catch (Exception $e) {
            jsonError('Failed to add item: ' . $e->getMessage());
        }
        break;

    // ── Toggle an order line on/off (the orange dot on the Orders screen) ──
    //    OFF: drop it from THIS order now (status=rejected) AND turn the item off in the recipes
    //    used by this order (is_primary=0, camp-wide) so future orders skip it too.
    //    ON: reverse both. Same behaviour as the Recipes orange toggle, driven from Orders.
    case 'toggle_line':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $lineId = (int)($data['line_id'] ?? 0);
        $on = !empty($data['on']) ? 1 : 0;
        if (!$lineId) jsonError('Line ID required');

        $lc = $db->prepare("SELECT rl.id, rl.item_id, rl.item_name, rl.requisition_id, r.status, r.kitchen_id
            FROM requisition_lines rl JOIN requisitions r ON r.id = rl.requisition_id
            WHERE rl.id = ? AND rl.deleted_at IS NULL");
        $lc->execute([$lineId]);
        $ln = $lc->fetch();
        if (!$ln) jsonError('Line not found');
        if (in_array($ln['status'], ['fulfilled', 'received', 'closed'])) jsonError('Order already sent — items are locked');

        $reqId    = (int)$ln['requisition_id'];
        $itemId   = (int)$ln['item_id'];
        $itemName = (string)$ln['item_name'];
        $kId      = (int)$ln['kitchen_id'];

        // 1. This order, right now: rejected = skipped, pending = ordered (qty preserved)
        $db->prepare("UPDATE requisition_lines SET status = ? WHERE id = ?")
           ->execute([$on ? 'pending' : 'rejected', $lineId]);

        // 2. Future orders: flip is_primary for this item across the camp's copies of the
        //    dishes used in THIS order (camp-wide, same match rule as the Recipes toggle:
        //    by item_id when we have one, else by item_name).
        $recipeSynced = 0;
        $nm = $db->prepare("SELECT DISTINCT rc.name FROM requisition_dishes d
            JOIN recipes rc ON rc.id = d.recipe_id
            WHERE d.requisition_id = ? AND d.deleted_at IS NULL AND d.recipe_id IS NOT NULL");
        $nm->execute([$reqId]);
        $names = array_column($nm->fetchAll(), 'name');
        if ($names && ($itemId || $itemName !== '')) {
            $ph = implode(',', array_fill(0, count($names), '?'));
            $matchCol = $itemId ? 'ri.item_id = ?' : 'ri.item_name = ?';
            $matchVal = $itemId ?: $itemName;
            $upd = $db->prepare("UPDATE recipe_ingredients ri
                JOIN recipes rc ON rc.id = ri.recipe_id
                JOIN users u ON u.id = rc.created_by
                SET ri.is_primary = ?
                WHERE rc.name IN ($ph) AND u.kitchen_id = ? AND $matchCol
                  AND ri.deleted_at IS NULL AND rc.deleted_at IS NULL");
            $upd->execute(array_merge([$on], $names, [$kId, $matchVal]));
            $recipeSynced = $upd->rowCount();
        }
        auditLog('toggle_line', 'requisition_lines', $lineId, null, ['on' => $on, 'recipe_synced' => $recipeSynced], $reqId);
        jsonResponse(['updated' => true, 'on' => $on, 'recipe_synced' => $recipeSynced]);

    // ── Update a single line item (qty + UOM editable until storekeeper acts) ──
    case 'update_line':
        requireMethod('POST');
        requireRole(['chef', 'admin']);
        $data = getJsonInput();
        $lineId   = (int)($data['line_id'] ?? 0);
        $orderQty = isset($data['order_qty']) ? (float)$data['order_qty'] : -1;
        $newUom   = trim($data['uom'] ?? '');

        if (!$lineId) jsonError('Line ID required');
        if ($orderQty < 0 && !$newUom) jsonError('Nothing to update');

        // Fetch old values + requisition status
        $lineCheck = $db->prepare(
            "SELECT rl.id, rl.order_qty, rl.uom, rl.requisition_id, r.status
             FROM requisition_lines rl
             JOIN requisitions r ON r.id = rl.requisition_id
             WHERE rl.id = ? AND rl.deleted_at IS NULL"
        );
        $lineCheck->execute([$lineId]);
        $lineRow = $lineCheck->fetch();
        if (!$lineRow) jsonError('Line not found');
        if (in_array($lineRow['status'], ['fulfilled', 'received', 'closed'])) {
            jsonError('Order already fulfilled — quantities are locked');
        }

        $oldQty = (float)$lineRow['order_qty'];
        $oldUom = $lineRow['uom'];
        $reqId  = (int)$lineRow['requisition_id'];

        // Keep existing value if not being changed
        if ($orderQty < 0) $orderQty = $oldQty;
        if (!$newUom)       $newUom   = $oldUom;

        // Validate UOM
        $allowedUoms = ['kg', 'grams', 'pcs', 'ltr', 'ml', 'bunch', 'pkt'];
        if (!in_array($newUom, $allowedUoms)) jsonError('Invalid unit of measure');

        $db->prepare("UPDATE requisition_lines SET order_qty = ?, uom = ? WHERE id = ? AND deleted_at IS NULL")
           ->execute([$orderQty, $newUom, $lineId]);

        // Log only when something actually changed
        if ($orderQty != $oldQty || $newUom !== $oldUom) {
            auditLog(
                'update_line',
                'requisition_line',
                $lineId,
                ['order_qty' => $oldQty, 'uom' => $oldUom],
                ['order_qty' => $orderQty, 'uom' => $newUom],
                $reqId
            );
        }
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
        $check = $db->prepare("SELECT rl.id, rl.item_name, rl.requisition_id, r.status, r.kitchen_id FROM requisition_lines rl JOIN requisitions r ON r.id = rl.requisition_id WHERE rl.id = ?");
        $check->execute([$lineId]);
        $row = $check->fetch();
        if (!$row) jsonError('Line not found', 404);
        if (!in_array($row['status'], ['draft', 'processing', 'submitted'])) jsonError('Cannot modify fulfilled orders');

        $db->prepare("UPDATE requisition_lines SET deleted_at = NOW(), deleted_by = ? WHERE id = ?")->execute([$user['id'], $lineId]);
        auditLog('chef_remove_line', 'requisition_lines', $lineId, ['item_name' => $row['item_name'] ?? null], null, (int)$row['requisition_id']);
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

            $db->prepare("UPDATE requisition_lines SET deleted_at = NOW(), deleted_by = ? WHERE requisition_id = ? AND deleted_at IS NULL")->execute([$user['id'], $reqId]);
            $db->prepare("UPDATE requisition_dishes SET deleted_at = NOW(), deleted_by = ? WHERE requisition_id = ? AND deleted_at IS NULL")->execute([$user['id'], $reqId]);
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

        // Editable until SUBMIT — allow (re)generating lines while draft or processing
        $stmt = $db->prepare("SELECT * FROM requisitions WHERE id = ? AND status IN ('draft','processing')");
        $stmt->execute([$reqId]);
        $req = $stmt->fetch();
        if (!$req) jsonError('Order not found, or already submitted to the store');

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
            // Clear old dish entries and menu-generated lines (soft-delete to preserve audit trail)
            $db->prepare("UPDATE requisition_dishes SET deleted_at = NOW(), deleted_by = ? WHERE requisition_id = ? AND deleted_at IS NULL")->execute([$user['id'], $reqId]);
            $db->prepare("UPDATE requisition_lines SET deleted_at = NOW(), deleted_by = ? WHERE requisition_id = ? AND is_staple = 0 AND deleted_at IS NULL")->execute([$user['id'], $reqId]);

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
                    i.piece_weight, i.is_pantry_staple, i.uom AS item_uom, i.pack_size_g
                    FROM recipe_ingredients ri
                    LEFT JOIN items i ON i.id = ri.item_id
                    LEFT JOIN kitchen_inventory ki ON ki.item_id = ri.item_id AND ki.kitchen_id = ?
                    WHERE ri.recipe_id IN ($ph) AND ri.deleted_at IS NULL");
                $batchIngStmt->execute(array_merge([$kitchenId], array_values($recipeIds)));
                foreach ($batchIngStmt->fetchAll() as $ing) {
                    $allIngredients[(int)$ing['recipe_id']][] = $ing;
                }
            }

            foreach ($dishes as $dish) {
                $recipeId    = (int)($dish['recipe_id'] ?? 0);
                $isPacked    = !empty($dish['is_packed']) || $recipeId === 0;
                $recipeName  = $dish['recipe_name'] ?? '';
                $recipeServings = (int)($dish['recipe_servings'] ?? 4);
                if ($recipeServings < 1) $recipeServings = 4;

                // Per-dish portions: each dish can have its own portion count
                $dishPortions = (int)($dish['dish_portions'] ?? $guestCount);
                if ($dishPortions < 1) $dishPortions = $guestCount;

                $scaleFactor = $dishPortions / $recipeServings;

                // Insert dish record
                $dStmt = $db->prepare("INSERT INTO requisition_dishes (requisition_id, recipe_id, is_packed, recipe_name, recipe_servings, scale_factor, guest_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $dStmt->execute([$reqId, $isPacked ? null : ($recipeId ?: null), $isPacked ? 1 : 0, $recipeName, $recipeServings, round($scaleFactor, 3), $dishPortions]);
                $dishId = $db->lastInsertId();

                // Packed/out-of-box dishes: no ingredients to generate — skip
                if ($isPacked) continue;

                // Use pre-loaded ingredients (no per-dish query)
                $ingredients = $allIngredients[$recipeId] ?? [];

                foreach ($ingredients as $ing) {
                    $itemId = (int)$ing['item_id'];
                    if (!$itemId) continue;

                    // Recipe-level skip: if chef toggled the ingredient off (is_primary=0),
                    // do NOT order it — regardless of whether the item is globally marked
                    // a pantry staple. The orange-circle toggle on the recipe is authoritative.
                    if (empty($ing['is_primary'])) {
                        $staplesSkipped = ($staplesSkipped ?? 0) + 1;
                        continue;
                    }

                    $scaledQty = (float)$ing['qty'] * $scaleFactor;
                    $ingUom = $ing['uom'] ?? 'kg';

                    // Normalize the recipe unit to the ITEM's catalog unit so the same item always
                    // orders in one consistent unit (grams→kg, ml→ltr). Recipes may be authored in
                    // grams/ml; the order, store issue and day-close all then speak the item's unit.
                    $rU = strtolower(trim($ingUom));
                    $iU = strtolower(trim($ing['item_uom'] ?? ''));
                    if (in_array($rU, ['g','grams','gram','gm']) && in_array($iU, ['kg','kgs','kilogram','kilograms'])) {
                        $scaledQty = $scaledQty / 1000; $ingUom = $ing['item_uom'];
                    } elseif (in_array($rU, ['ml','milliliter','millilitre','mls']) && in_array($iU, ['ltr','l','litre','liter','lt','ltrs'])) {
                        $scaledQty = $scaledQty / 1000; $ingUom = $ing['item_uom'];
                    } elseif (in_array($rU, ['kg','kgs','kilogram']) && in_array($iU, ['g','grams','gram'])) {
                        $scaledQty = $scaledQty * 1000; $ingUom = $ing['item_uom'];
                    }

                    // Convert pcs→kg if item has piece_weight
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
                            'item_uom' => $ing['item_uom'] ?? null,
                            'pack_size_g' => $ing['pack_size_g'] ?? null,
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

            // Phase 3 — running stock balance: how much kitchen stock the day's OTHER (non-draft)
            // orders have already claimed per item, so a shared item isn't double-subtracted across
            // meals/supplements. Read-time only — kitchen_inventory is never mutated here; day-close
            // still overwrites it with the physical count.
            $claimedMap = [];
            try {
                $claimStmt = $db->prepare("SELECT rl.item_id, SUM(GREATEST(0, COALESCE(rl.required_kg,0) - COALESCE(rl.order_qty,0))) AS claimed
                    FROM requisition_lines rl JOIN requisitions r ON r.id = rl.requisition_id
                    WHERE r.kitchen_id = ? AND r.req_date = ? AND r.id <> ? AND r.status <> 'draft'
                      AND rl.deleted_at IS NULL AND COALESCE(rl.is_staple,0) = 0
                    GROUP BY rl.item_id");
                $claimStmt->execute([(int)$req['kitchen_id'], $req['req_date'], $reqId]);
                foreach ($claimStmt->fetchAll() as $cm) $claimedMap[(int)$cm['item_id']] = (float)$cm['claimed'];
            } catch (Exception $e) { /* non-fatal — fall back to raw opening stock */ }

            foreach ($aggregated as $itemId => $agg) {
                $requiredKg = $roundUp($agg['total_qty']);
                // Stock still available to THIS order = opening stock minus what other orders already claimed
                $availStock = max(0, (float)$agg['stock_qty'] - ($claimedMap[$itemId] ?? 0));
                $orderQty = max(0, $roundUp($requiredKg - $availStock));

                if ($requiredKg <= 0) continue;

                // Translate the order into the item's purchase/stock unit (the store's unit).
                // Recipes stay in the chef's units; only the order line speaks the buying unit.
                [$orderQty, $lineUom]   = toPurchaseUnit($orderQty,  $agg['uom'], $agg['item_uom'], $agg['pack_size_g']);
                [$requiredKg, ]         = toPurchaseUnit($requiredKg, $agg['uom'], $agg['item_uom'], $agg['pack_size_g']);

                // Use first source for tracking + store all sources as JSON
                $sourceDishId = $agg['sources'][0]['dish_id'] ?? null;
                $sourceRecipeId = $agg['sources'][0]['recipe_id'] ?? null;
                $sourceDishesJson = json_encode(array_map(function($s) {
                    return ['name' => $s['recipe_name'] ?? '', 'qty' => round($s['qty'] ?? 0, 2)];
                }, $agg['sources']));

                $insertStmt->execute([
                    $reqId, $itemId, $agg['item_name'], $meal, $agg['order_mode'],
                    $guestCount, $agg['portion_weight'], $requiredKg, $availStock, $orderQty,
                    $lineUom, $sourceDishId, $sourceRecipeId, $sourceDishesJson
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

        // Get dishes (recipe-based AND packed)
        $dStmt = $db->prepare("SELECT rd.id, rd.recipe_id, COALESCE(rd.is_packed, 0) AS is_packed,
            rd.recipe_name, rd.recipe_servings, rd.scale_factor, rd.guest_count
            FROM requisition_dishes rd WHERE rd.requisition_id = ? AND rd.deleted_at IS NULL ORDER BY rd.is_packed ASC, rd.created_at");
        $dStmt->execute([$reqId]);
        $dishes = $dStmt->fetchAll();

        if (empty($dishes)) {
            jsonResponse(['dishes' => [], 'packed_dishes' => [], 'ingredients_by_recipe' => new \stdClass()]);
        }

        // Separate packed dishes (no recipe) from recipe-based dishes
        $packedDishes  = array_values(array_filter($dishes, fn($d) => !empty($d['is_packed']) || $d['recipe_id'] === null));
        $recipeDishes  = array_values(array_filter($dishes, fn($d) => empty($d['is_packed']) && $d['recipe_id'] !== null));

        // Batch-load ingredients only for recipe-based dishes
        $ingredientsByRecipe = [];
        if (!empty($recipeDishes)) {
            $recipeIds = array_unique(array_column($recipeDishes, 'recipe_id'));
            $recipeIds = array_filter($recipeIds, fn($id) => $id !== null);
            if (!empty($recipeIds)) {
                $ph = implode(',', array_fill(0, count($recipeIds), '?'));
                $iStmt = $db->prepare("SELECT ri.recipe_id, ri.item_id, ri.qty, ri.uom, ri.is_primary,
                    i.name AS item_name, i.stock_qty, i.portion_weight, i.order_mode, i.category
                    FROM recipe_ingredients ri
                    LEFT JOIN items i ON i.id = ri.item_id
                    WHERE ri.recipe_id IN ($ph) AND ri.deleted_at IS NULL
                    ORDER BY ri.recipe_id, ri.is_primary DESC, i.name");
                $iStmt->execute(array_values($recipeIds));
                foreach ($iStmt->fetchAll() as $ing) {
                    $ingredientsByRecipe[$ing['recipe_id']][] = $ing;
                }
            }
        }

        jsonResponse([
            'dishes'               => $dishes,
            'packed_dishes'        => $packedDishes,
            'ingredients_by_recipe'=> $ingredientsByRecipe ?: new \stdClass(),
        ]);

    // ── Admin: list all requisitions cross-kitchen ──
    case 'admin_list':
        requireRole(['admin']);
        $dateFrom  = $_GET['date_from']  ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo    = $_GET['date_to']    ?? date('Y-m-d');
        $filterKid = (int)($_GET['kitchen_id'] ?? 0);
        $filterSt  = $_GET['status'] ?? '';

        $sql = "SELECT r.id, r.req_date, r.meals, r.session_number, r.supplement_number,
                       r.guest_count, r.status, r.created_at, r.updated_at,
                       k.name AS kitchen_name,
                       u.name AS chef_name,
                       (SELECT COUNT(*) FROM requisition_lines WHERE requisition_id = r.id AND deleted_at IS NULL) AS line_count
                FROM requisitions r
                LEFT JOIN kitchens k ON k.id = r.kitchen_id
                LEFT JOIN users u ON u.id = r.created_by
                WHERE r.req_date BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];

        if ($filterKid) { $sql .= " AND r.kitchen_id = ?"; $params[] = $filterKid; }
        if ($filterSt)  { $sql .= " AND r.status = ?";     $params[] = $filterSt; }

        $sql .= " ORDER BY r.req_date DESC, r.kitchen_id, r.session_number, r.supplement_number";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonResponse(['requisitions' => $stmt->fetchAll()]);

    // ── Admin: force-close any order ──
    case 'admin_close':
        requireMethod('POST');
        requireRole(['admin']);
        $data  = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        // Auto-fill received_qty from fulfilled_qty if missing (only active lines)
        $db->prepare("UPDATE requisition_lines SET received_qty = fulfilled_qty
                      WHERE requisition_id = ? AND deleted_at IS NULL AND (received_qty IS NULL OR received_qty = 0)")->execute([$reqId]);
        $db->prepare("UPDATE requisitions SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$reqId]);

        auditLog('admin_force_close', 'requisition', $reqId);
        jsonResponse(['closed' => true]);

    // ── Admin: reopen a closed/fulfilled order ──
    case 'admin_reopen':
        requireMethod('POST');
        requireRole(['admin']);
        $data  = getJsonInput();
        $reqId = (int)($data['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');

        $db->prepare("UPDATE requisitions SET status = 'submitted', updated_at = NOW() WHERE id = ?")->execute([$reqId]);
        auditLog('admin_reopen', 'requisition', $reqId);
        jsonResponse(['reopened' => true]);

    // ── Admin: reset all orders — DISABLED 2026-07-29 (no hard deletes) ──
    // This used to hard-DELETE every requisition/line/dish/notification in ONE call, with no undo.
    // Neutered so no admin can wipe the live database. To genuinely reset: take a mysqldump backup
    // first, then clear via direct DB access — never re-add a one-click destructive endpoint here.
    case 'reset_all_orders':
        requireMethod('POST');
        requireRole(['admin']);
        auditLog('reset_all_orders_blocked', 'requisition', null, null, ['blocked' => true]);
        jsonError('Disabled: bulk order wipe is turned off. Data is never hard-deleted from here.', 403);

    // ── Change log for a requisition (all audit entries for that order) ──
    case 'change_log':
        requireMethod('GET');
        requireRole(['chef', 'admin', 'storekeeper']);
        $reqId = (int)($_GET['requisition_id'] ?? 0);
        if (!$reqId) jsonError('Requisition ID required');
        $stmt = $db->prepare("
            SELECT action, user_name, old_value, new_value, created_at
            FROM audit_log
            WHERE requisition_id = ?
               OR (entity = 'requisition' AND entity_id = ?)
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$reqId, $reqId]);
        jsonResponse(['log' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action');
}
