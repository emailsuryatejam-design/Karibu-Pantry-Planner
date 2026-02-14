<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];

switch ($action) {

    // ── List recipes ──
    case 'list':
        $q = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';

        $sql = 'SELECT r.*, (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id) as ingredient_count FROM recipes r WHERE 1=1';
        $params = [];

        if ($q) {
            $sql .= ' AND (r.name LIKE ? OR r.cuisine LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        if ($category) {
            $sql .= ' AND r.category = ?';
            $params[] = $category;
        }

        $sql .= ' ORDER BY r.name';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['recipes' => $stmt->fetchAll()]);
        break;

    // ── Get single recipe with ingredients ──
    case 'get':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonError('Recipe ID required');

        $recipe = $db->prepare('SELECT * FROM recipes WHERE id = ?');
        $recipe->execute([$id]);
        $recipe = $recipe->fetch();
        if (!$recipe) jsonError('Recipe not found', 404);

        $ings = $db->prepare('SELECT ri.*, i.stock_qty FROM recipe_ingredients ri LEFT JOIN items i ON ri.item_id = i.id WHERE ri.recipe_id = ? ORDER BY ri.id');
        $ings->execute([$id]);
        $recipe['ingredients'] = $ings->fetchAll();

        jsonResponse(['recipe' => $recipe]);
        break;

    // ── Save recipe (create or update) ──
    case 'save':
        requireMethod('POST');
        $id = $input['id'] ?? null;
        $name = trim($input['name'] ?? '');
        $category = $input['category'] ?? 'main_course';
        $cuisine = $input['cuisine'] ?? null;
        $difficulty = $input['difficulty'] ?? 'medium';
        $prepTime = $input['prep_time'] ?? null;
        $cookTime = $input['cook_time'] ?? null;
        $servings = (int)($input['servings'] ?? 4);
        $instructions = $input['instructions'] ?? null;
        $notes = $input['notes'] ?? null;

        if (!$name) jsonError('Recipe name required');

        if ($id) {
            $stmt = $db->prepare('UPDATE recipes SET name = ?, category = ?, cuisine = ?, difficulty = ?, prep_time = ?, cook_time = ?, servings = ?, instructions = ?, notes = ? WHERE id = ?');
            $stmt->execute([$name, $category, $cuisine, $difficulty, $prepTime, $cookTime, $servings, $instructions, $notes, $id]);
            auditLog('update_recipe', 'recipes', $id, null, ['name' => $name]);
        } else {
            $stmt = $db->prepare('INSERT INTO recipes (name, category, cuisine, difficulty, prep_time, cook_time, servings, instructions, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $category, $cuisine, $difficulty, $prepTime, $cookTime, $servings, $instructions, $notes, $user['id']]);
            $id = $db->lastInsertId();
            auditLog('create_recipe', 'recipes', $id, null, ['name' => $name]);
        }

        jsonResponse(['recipe_id' => $id]);
        break;

    // ── Delete recipe ──
    case 'delete':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('Recipe ID required');

        $db->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
        auditLog('delete_recipe', 'recipes', $id);
        jsonResponse(['deleted' => true]);
        break;

    // ── Add ingredient to recipe ──
    case 'add_ingredient':
        requireMethod('POST');
        $recipeId = (int)($input['recipe_id'] ?? 0);
        $itemId = $input['item_id'] ?? null;
        $itemName = trim($input['item_name'] ?? '');
        $qty = (float)($input['qty'] ?? 0);
        $uom = $input['uom'] ?? 'kg';
        $isPrimary = (int)($input['is_primary'] ?? 1);

        if (!$recipeId || !$itemName || $qty <= 0) jsonError('Recipe ID, name, and qty required');

        // If item_id provided, get name from items table
        if ($itemId && !$itemName) {
            $item = $db->prepare('SELECT name FROM items WHERE id = ?');
            $item->execute([$itemId]);
            $itemData = $item->fetch();
            $itemName = $itemData['name'] ?? $itemName;
        }

        $stmt = $db->prepare('INSERT INTO recipe_ingredients (recipe_id, item_id, item_name, qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$recipeId, $itemId, $itemName, $qty, $uom, $isPrimary]);

        jsonResponse(['ingredient_id' => $db->lastInsertId()]);
        break;

    // ── Remove ingredient ──
    case 'remove_ingredient':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('Ingredient ID required');

        $db->prepare('DELETE FROM recipe_ingredients WHERE id = ?')->execute([$id]);
        jsonResponse(['removed' => true]);
        break;

    // ── Search items ──
    case 'search_items':
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) jsonResponse(['items' => []]);

        $stmt = $db->prepare('SELECT id, name, code, category, uom, stock_qty FROM items WHERE is_active = 1 AND (name LIKE ? OR code LIKE ?) ORDER BY name LIMIT 20');
        $stmt->execute(["%$q%", "%$q%"]);
        jsonResponse(['items' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('Unknown action');
}
