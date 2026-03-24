<?php
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
$db = getDB();

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? ($_POST['action'] ?? ''));

switch ($action) {

    // ── List recipes ──
    case 'list':
        $q = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';

        $sql = 'SELECT r.*, u.name AS chef_name, (SELECT COUNT(*) FROM recipe_ingredients WHERE recipe_id = r.id) as ingredient_count FROM recipes r LEFT JOIN users u ON u.id = r.created_by WHERE 1=1';
        $params = [];

        // Chefs see only their own recipes; admin/storekeeper see all (with optional chef filter)
        if ($user['role'] === 'chef') {
            $sql .= ' AND r.created_by = ?';
            $params[] = $user['id'];
        } elseif (!empty($_GET['chef_id'])) {
            $sql .= ' AND r.created_by = ?';
            $params[] = (int)$_GET['chef_id'];
        }

        if ($q) {
            $escaped = escapeLike($q);
            $sql .= ' AND (r.name LIKE ? OR r.cuisine LIKE ?)';
            $params[] = "%$escaped%";
            $params[] = "%$escaped%";
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
            // Ownership check: chefs can only edit their own recipes
            if ($user['role'] === 'chef') {
                $own = $db->prepare('SELECT created_by FROM recipes WHERE id = ?');
                $own->execute([$id]);
                $owner = $own->fetchColumn();
                if ($owner && (int)$owner !== (int)$user['id']) jsonError('You can only edit your own recipes', 403);
            }
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

        // Ownership check: chefs can only delete their own recipes
        if ($user['role'] === 'chef') {
            $own = $db->prepare('SELECT created_by FROM recipes WHERE id = ?');
            $own->execute([$id]);
            $owner = $own->fetchColumn();
            if ($owner && (int)$owner !== (int)$user['id']) jsonError('You can only delete your own recipes', 403);
        }

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

    // ── Toggle ingredient primary/staple status ──
    case 'toggle_primary':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        $isPrimary = (int)($input['is_primary'] ?? 0);
        if (!$id) jsonError('Ingredient ID required');

        $db->prepare('UPDATE recipe_ingredients SET is_primary = ? WHERE id = ?')->execute([$isPrimary, $id]);
        jsonResponse(['updated' => true, 'is_primary' => $isPrimary]);
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

    // ── Assign all unowned recipes to a chef ──
    case 'assign_unowned':
        requireMethod('POST');
        requireRole(['admin']);
        $chefId = (int)($input['chef_id'] ?? 0);
        if (!$chefId) jsonError('Chef ID required');
        $stmt = $db->prepare('UPDATE recipes SET created_by = ? WHERE created_by IS NULL');
        $stmt->execute([$chefId]);
        jsonResponse(['assigned' => $stmt->rowCount()]);
        break;

    // ── Duplicate all recipes from one chef to another ──
    case 'duplicate_for_chef':
        requireMethod('POST');
        requireRole(['admin']);
        $fromChefId = (int)($input['from_chef_id'] ?? 0);
        $toChefId = (int)($input['to_chef_id'] ?? 0);
        if (!$fromChefId || !$toChefId) jsonError('from_chef_id and to_chef_id required');
        if ($fromChefId === $toChefId) jsonError('Cannot duplicate to same chef');

        // Get all source recipes
        $srcRecipes = $db->prepare('SELECT * FROM recipes WHERE created_by = ?');
        $srcRecipes->execute([$fromChefId]);
        $recipes = $srcRecipes->fetchAll();
        if (empty($recipes)) jsonError('No recipes found for source chef');

        $insRecipe = $db->prepare('INSERT INTO recipes (name, category, cuisine, difficulty, prep_time, cook_time, servings, instructions, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insIng = $db->prepare('INSERT INTO recipe_ingredients (recipe_id, item_id, item_name, qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, ?)');
        $getIngs = $db->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ?');

        $count = 0;
        foreach ($recipes as $r) {
            $insRecipe->execute([$r['name'], $r['category'], $r['cuisine'], $r['difficulty'], $r['prep_time'], $r['cook_time'], $r['servings'], $r['instructions'], $r['notes'], $toChefId]);
            $newId = $db->lastInsertId();

            // Copy ingredients
            $getIngs->execute([$r['id']]);
            foreach ($getIngs->fetchAll() as $ing) {
                $insIng->execute([$newId, $ing['item_id'], $ing['item_name'], $ing['qty'], $ing['uom'], $ing['is_primary']]);
            }
            $count++;
        }

        auditLog('duplicate_recipes', 'recipes', null, null, ['from' => $fromChefId, 'to' => $toChefId, 'count' => $count]);
        jsonResponse(['duplicated' => $count]);
        break;

    // ── Bulk fix: mark pantry staple ingredients as is_primary=0 ──
    case 'fix_staples':
        requireMethod('POST');
        requireRole(['admin']);
        $staples = [
            'salt', 'black pepper', 'oil', 'cooking oil', 'olive oil', 'sunflower oil',
            'oil (sunflower)', 'sugar', 'brown sugar', 'castor sugar',
            'aromat', 'soy sauce', 'balsamic vinegar', 'vinegar',
            'turmeric powder', 'turmeric', 'paprika', 'cayenne pepper', 'curry powder',
            'cumin', 'coriander powder', 'chilli paste', 'garlic paste',
            'vanilla essence', 'vanilla', 'baking powder', 'baking soda',
            'bicarbonate of soda', 'corn flour', 'cornstarch', 'corn starch',
            'wheat flour', 'flour', 'dijon mustard', 'ketchup',
            'tomato paste', 'pesto sauce', 'fish sauce', 'curry sauce',
            'golden syrup', 'cocoa powder', 'chicken cubes', 'vegetable cubes',
            'gelatine', 'cream cheese', 'condensed milk',
        ];
        $ph = implode(',', array_fill(0, count($staples), '?'));
        $stmt = $db->prepare("UPDATE recipe_ingredients SET is_primary = 0 WHERE LOWER(item_name) IN ($ph) AND is_primary = 1");
        $stmt->execute($staples);
        jsonResponse(['updated' => $stmt->rowCount()]);
        break;

    default:
        jsonError('Unknown action');
}
