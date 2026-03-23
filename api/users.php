<?php
require_once __DIR__ . '/../auth.php';
$user = requireRole(['admin']);
$db = getDB();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

switch ($action) {

    // ── List all users ──
    case 'list':
        $stmt = $db->query('SELECT u.id, u.name, u.username, u.role, u.camp_name, u.kitchen_id, u.is_active, u.created_at, k.name AS kitchen_name FROM users u LEFT JOIN kitchens k ON k.id = u.kitchen_id ORDER BY u.is_active DESC, u.name');
        jsonResponse(['users' => $stmt->fetchAll()]);
        break;

    // ── Create user ──
    case 'create':
        requireMethod('POST');
        $name = trim($input['name'] ?? '');
        $username = trim($input['username'] ?? '');
        $pin = trim($input['pin'] ?? '');
        $role = $input['role'] ?? 'chef';

        if (!$name || !$username || !$pin) {
            jsonError('Name, username and PIN are required');
        }
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            jsonError('PIN must be exactly 4 digits');
        }
        if (!in_array($role, ['chef', 'storekeeper', 'admin'])) {
            jsonError('Invalid role');
        }

        // Check duplicate username
        $check = $db->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            jsonError('Username already exists');
        }

        $kitchenId = isset($input['kitchen_id']) ? (int)$input['kitchen_id'] : null;

        $stmt = $db->prepare('INSERT INTO users (name, username, pin, role, kitchen_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $username, $pin, $role, $kitchenId]);
        $newUserId = $db->lastInsertId();

        // Auto-copy recipes for new chefs from an existing chef in the same kitchen (or admin)
        $recipesCopied = 0;
        if ($role === 'chef') {
            // Find a template chef: first chef in same kitchen, or admin (id=1)
            $templateChefId = null;
            if ($kitchenId) {
                $tStmt = $db->prepare("SELECT id FROM users WHERE role = 'chef' AND kitchen_id = ? AND id != ? AND is_active = 1 LIMIT 1");
                $tStmt->execute([$kitchenId, $newUserId]);
                $templateChefId = $tStmt->fetchColumn();
            }
            // Fallback: find any chef with recipes
            if (!$templateChefId) {
                $templateChefId = $db->query("SELECT created_by FROM recipes WHERE created_by IS NOT NULL GROUP BY created_by ORDER BY COUNT(*) DESC LIMIT 1")->fetchColumn();
            }

            if ($templateChefId) {
                $srcRecipes = $db->prepare('SELECT * FROM recipes WHERE created_by = ?');
                $srcRecipes->execute([$templateChefId]);
                $recipes = $srcRecipes->fetchAll();

                $insRecipe = $db->prepare('INSERT INTO recipes (name, category, cuisine, difficulty, prep_time, cook_time, servings, instructions, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insIng = $db->prepare('INSERT INTO recipe_ingredients (recipe_id, item_id, item_name, qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?)');
                $getIngs = $db->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ?');

                foreach ($recipes as $r) {
                    $insRecipe->execute([$r['name'], $r['category'], $r['cuisine'], $r['difficulty'], $r['prep_time'], $r['cook_time'], $r['servings'], $r['instructions'], $r['notes'], $newUserId]);
                    $newRecipeId = $db->lastInsertId();
                    $getIngs->execute([$r['id']]);
                    foreach ($getIngs->fetchAll() as $ing) {
                        $insIng->execute([$newRecipeId, $ing['item_id'], $ing['item_name'], $ing['qty'], $ing['uom'], $ing['is_primary']]);
                    }
                    $recipesCopied++;
                }
            }
        }

        auditLog('create_user', 'users', $newUserId, null, ['name' => $name, 'role' => $role, 'recipes_copied' => $recipesCopied]);
        jsonResponse(['id' => $newUserId, 'recipes_copied' => $recipesCopied]);
        break;

    // ── Update user ──
    case 'update':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('User ID required');

        $fields = [];
        $params = [];

        if (isset($input['name']) && trim($input['name'])) {
            $fields[] = 'name = ?';
            $params[] = trim($input['name']);
        }
        if (isset($input['pin']) && trim($input['pin'])) {
            if (strlen(trim($input['pin'])) !== 4 || !ctype_digit(trim($input['pin']))) jsonError('PIN must be exactly 4 digits');
            $fields[] = 'pin = ?';
            $params[] = trim($input['pin']);
        }
        if (isset($input['role']) && in_array($input['role'], ['chef', 'storekeeper', 'admin'])) {
            $fields[] = 'role = ?';
            $params[] = $input['role'];
        }
        if (isset($input['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $input['is_active'] ? 1 : 0;
        }
        if (array_key_exists('kitchen_id', $input)) {
            $fields[] = 'kitchen_id = ?';
            $params[] = $input['kitchen_id'] ? (int)$input['kitchen_id'] : null;
        }

        if (empty($fields)) jsonError('Nothing to update');

        $params[] = $id;
        $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

        auditLog('update_user', 'users', $id);
        jsonResponse(['updated' => true]);
        break;

    // ── Delete user ──
    case 'delete':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('User ID required');
        if ($id === $user['id']) jsonError('Cannot delete yourself');

        // Soft-delete: deactivate instead of hard delete to preserve requisition/recipe history
        $db->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);

        // Count related data for audit
        $reqCount = $db->prepare("SELECT COUNT(*) FROM requisitions WHERE created_by = ?");
        $reqCount->execute([$id]);
        $recipeCount = $db->prepare("SELECT COUNT(*) FROM recipes WHERE created_by = ?");
        $recipeCount->execute([$id]);

        auditLog('deactivate_user', 'users', $id, null, [
            'requisitions' => (int)$reqCount->fetchColumn(),
            'recipes' => (int)$recipeCount->fetchColumn()
        ]);
        jsonResponse(['deleted' => true, 'soft_deleted' => true]);
        break;

    default:
        jsonError('Unknown action');
}
