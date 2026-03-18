<?php
/**
 * Recipe Seed Script — Inserts all 65 Karibu Camps recipes
 * Run via: GET /api/seed-recipes.php?action=seed
 * Admin-only. Skips recipes that already exist (by name).
 */
require_once __DIR__ . '/../auth.php';
$user = requireAuth();
if ($user['role'] !== 'admin') { jsonError('Admin only'); }

$db = getDB();
$action = $_GET['action'] ?? '';

if ($action === 'seed') {
    $recipes = getRecipeData();

    $checkStmt = $db->prepare('SELECT id FROM recipes WHERE name = ?');
    $insertRecipe = $db->prepare('INSERT INTO recipes (name, category, cuisine, difficulty, servings, instructions, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insertIng = $db->prepare('INSERT INTO recipe_ingredients (recipe_id, item_id, item_name, qty, uom, is_primary) VALUES (?, NULL, ?, ?, ?, ?)');

    // Pantry staples — always in store, don't need daily ordering
    $pantryStaples = [
        'salt', 'black pepper', 'oil', 'cooking oil', 'olive oil', 'sunflower oil',
        'oil (sunflower)', 'sugar', 'brown sugar', 'castor sugar',
        'aromat', 'soy sauce', 'balsamic vinegar', 'vinegar',
        'turmeric powder', 'paprika', 'cayenne pepper', 'curry powder',
        'cumin', 'coriander powder', 'chilli paste', 'garlic paste',
        'vanilla essence', 'vanilla', 'baking powder', 'baking soda',
        'bicarbonate of soda', 'corn flour', 'cornstarch', 'corn starch',
        'wheat flour', 'flour', 'dijon mustard', 'ketchup',
        'tomato paste', 'pesto sauce', 'fish sauce', 'curry sauce',
        'golden syrup', 'cocoa powder', 'chicken cubes', 'vegetable cubes',
        'gelatine', 'cream cheese', 'condensed milk',
    ];

    $created = 0;
    $skipped = 0;
    $totalIngs = 0;
    $stapleIngs = 0;

    foreach ($recipes as $r) {
        $checkStmt->execute([$r['name']]);
        if ($checkStmt->fetch()) {
            $skipped++;
            continue;
        }

        $insertRecipe->execute([
            $r['name'],
            $r['category'],
            $r['cuisine'] ?? null,
            $r['difficulty'] ?? 'medium',
            $r['servings'],
            $r['instructions'] ?? null,
            $r['notes'] ?? null,
            $user['id']
        ]);
        $recipeId = $db->lastInsertId();
        $created++;

        if (!empty($r['ingredients'])) {
            foreach ($r['ingredients'] as $ing) {
                if ($ing['qty'] > 0) {
                    $isPrimary = in_array(strtolower($ing['item']), $pantryStaples) ? 0 : 1;
                    $insertIng->execute([$recipeId, $ing['item'], $ing['qty'], $ing['uom'], $isPrimary]);
                    $totalIngs++;
                    if (!$isPrimary) $stapleIngs++;
                }
            }
        }
    }

    jsonResponse([
        'created' => $created,
        'skipped' => $skipped,
        'ingredients_added' => $totalIngs,
        'staple_ingredients' => $stapleIngs,
        'total_in_file' => count($recipes)
    ]);
}

if ($action === 'fix_staples') {
    // Mark pantry staple ingredients as is_primary=0 across ALL recipes
    $staples = [
        'salt', 'black pepper', 'oil', 'cooking oil', 'olive oil', 'sunflower oil',
        'oil (sunflower)', 'sugar', 'brown sugar', 'castor sugar',
        'aromat', 'soy sauce', 'balsamic vinegar', 'vinegar',
        'turmeric powder', 'paprika', 'cayenne pepper', 'curry powder',
        'cumin', 'coriander powder', 'chilli paste', 'garlic paste',
        'vanilla essence', 'vanilla', 'baking powder', 'baking soda',
        'bicarbonate of soda', 'corn flour', 'cornstarch', 'corn starch',
        'wheat flour', 'flour', 'dijon mustard', 'ketchup',
        'tomato paste', 'pesto sauce', 'fish sauce', 'curry sauce',
        'golden syrup', 'cocoa powder', 'chicken cubes', 'vegetable cubes',
        'gelatine', 'cream cheese', 'condensed milk',
    ];

    $placeholders = implode(',', array_fill(0, count($staples), '?'));
    $stmt = $db->prepare("UPDATE recipe_ingredients SET is_primary = 0 WHERE LOWER(item_name) IN ($placeholders) AND is_primary = 1");
    $stmt->execute($staples);
    $updated = $stmt->rowCount();

    jsonResponse(['updated_to_staple' => $updated]);
}

jsonError('Use ?action=seed or ?action=fix_staples');

function getRecipeData() {
    return [
        // ═══════════════════════════════════════
        // APPETIZERS
        // ═══════════════════════════════════════
        [
            'name' => 'Vegetable Spring Rolls',
            'category' => 'appetizer',
            'cuisine' => 'Asian',
            'servings' => 12,
            'notes' => 'Dinner — Monday, Friday. Serve on the oval plate.',
            'instructions' => 'Crispy golden spring rolls filled with a mix of fresh vegetables, served with peanut butter, coconut milk, and sweet chili sauce dips, with herb salad and lemon dressing.',
            'ingredients' => [
                ['item' => 'Cabbage', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Carrots', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Green pepper', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Onion', 'qty' => 0.15, 'uom' => 'kg'],
                ['item' => 'Oil', 'qty' => 0.5, 'uom' => 'ltr'],
                ['item' => 'Salt', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Soy sauce', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Aromat', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Balsamic vinegar', 'qty' => 2, 'uom' => 'tbsp'],
            ]
        ],
        [
            'name' => 'Caprese Salad with Basil Pesto',
            'category' => 'appetizer',
            'cuisine' => 'Italian',
            'servings' => 12,
            'notes' => 'Dinner — Tuesday, Saturday.',
            'instructions' => 'Layers of ripe tomatoes, creamy mozzarella, and fresh basil leaves, drizzled with aromatic basil pesto.',
            'ingredients' => [
                ['item' => 'Fresh tomato', 'qty' => 2, 'uom' => 'tin'],
                ['item' => 'Cheddar cheese', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Fresh basil', 'qty' => 0.5, 'uom' => 'bottle'],
                ['item' => 'Pesto sauce', 'qty' => 3, 'uom' => 'tbsp'],
                ['item' => 'Basil', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Salt', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Olive oil', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Balsamic vinegar', 'qty' => 2, 'uom' => 'tbsp'],
            ]
        ],
        [
            'name' => 'Camembert and Caramelized Onion Bruschetta',
            'category' => 'appetizer',
            'cuisine' => 'French',
            'servings' => 6,
            'notes' => 'Lunch — Wednesday, Thursday, Friday, Sunday.',
            'instructions' => 'Caramelize onions in olive oil and sugar. Top toasted bread with Camembert and caramelized onion mixture. Drizzle with balsamic glaze and serve.',
            'ingredients' => [
                ['item' => 'Camembert', 'qty' => 1, 'uom' => 'pkt'],
                ['item' => 'Onion', 'qty' => 0.3, 'uom' => 'kg'],
                ['item' => 'Olive oil', 'qty' => 0.05, 'uom' => 'ltr'],
                ['item' => 'Sugar', 'qty' => 0.05, 'uom' => 'kg'],
                ['item' => 'Bread', 'qty' => 2, 'uom' => 'pcs'],
            ]
        ],
        [
            'name' => 'Tomato, Avocado, and Mango Bruschetta',
            'category' => 'appetizer',
            'cuisine' => 'Fusion',
            'servings' => 12,
            'notes' => 'Lunch — Thursday. Serve on the oval plate.',
            'instructions' => 'Rub cream cheese on toasted bread, top with avocado-tomato-mango mixture and serve. Dressing: Dijon mustard, soy sauce, olive oil, balsamic vinegar.',
            'ingredients' => [
                ['item' => 'Avocado', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Fresh tomato', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Mango', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Olive oil', 'qty' => 0.02, 'uom' => 'ltr'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Dijon mustard', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Soy sauce', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Balsamic vinegar', 'qty' => 2, 'uom' => 'tbsp'],
            ]
        ],
        [
            'name' => 'Avocado Vinaigrette',
            'category' => 'appetizer',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday, Saturday.',
            'instructions' => 'Fresh avocado halves drizzled with a tangy vinaigrette dressing.',
            'ingredients' => []
        ],
        [
            'name' => 'Curried Sweet Potato Samosas with Tomato Salsa',
            'category' => 'appetizer',
            'cuisine' => 'Indian',
            'servings' => 12,
            'notes' => 'Dinner — Wednesday, Sunday.',
            'instructions' => 'Golden and crispy samosas filled with spiced sweet potato, paired with zesty tomato salsa.',
            'ingredients' => []
        ],
        [
            'name' => 'Sliced Beetroot with Orange Segments and Feta Cheese',
            'category' => 'appetizer',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Thinly sliced fresh beetroot paired with juicy orange segments and crumbled feta cheese, dressed with honey and wholegrain mustard vinaigrette.',
            'ingredients' => []
        ],
        [
            'name' => 'Vegetable Samosa with Sweet Chili Sauce',
            'category' => 'appetizer',
            'cuisine' => 'Indian',
            'servings' => 12,
            'notes' => 'Lunch — Monday.',
            'instructions' => 'Crispy golden pastry stuffed with a flavorful mix of spiced vegetables, served with sweet chili dipping sauce.',
            'ingredients' => [
                ['item' => 'Carrot', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Bell pepper', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Broccoli', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Green beans', 'qty' => 0.25, 'uom' => 'kg'],
                ['item' => 'Chilli', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Salt', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Garlic', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Coriander', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Wheat flour', 'qty' => 0.25, 'uom' => 'kg'],
            ]
        ],

        // ═══════════════════════════════════════
        // SOUPS
        // ═══════════════════════════════════════
        [
            'name' => 'Cream of Broccoli Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Dinner — Monday, Friday.',
            'instructions' => 'Smooth and creamy soup made from fresh broccoli, simmered with herbs and finished with cream.',
            'ingredients' => [
                ['item' => 'Broccoli', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Onions', 'qty' => 0.15, 'uom' => 'kg'],
                ['item' => 'Potato', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Celery', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Butter (salted)', 'qty' => 0.05, 'uom' => 'kg'],
                ['item' => 'Chicken cubes', 'qty' => 1, 'uom' => 'pkt'],
            ]
        ],
        [
            'name' => 'Pumpkin Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Dinner — Tuesday, Saturday.',
            'instructions' => 'Rich and creamy soup made from roasted pumpkin, blended with warm spices and cream.',
            'ingredients' => [
                ['item' => 'Pumpkin', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Onions', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Ginger', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Butter (salted)', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Aromat', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Celery', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Salt', 'qty' => 2, 'uom' => 'tbsp'],
            ]
        ],
        [
            'name' => 'Baby Marrow Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday, Sunday.',
            'instructions' => 'Light and refreshing soup made with cucumber and baby marrow, blended with herbs.',
            'ingredients' => []
        ],
        [
            'name' => 'Mixed Vegetable Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Wholesome soup made with a medley of fresh seasonal vegetables, simmered with herbs.',
            'ingredients' => []
        ],
        [
            'name' => 'Carrot and Celery Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Smooth and flavorful soup from fresh carrots and celery, simmered with aromatic spices.',
            'ingredients' => [
                ['item' => 'Carrot', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Garlic', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Celery', 'qty' => 0.5, 'uom' => 'bunch'],
                ['item' => 'Cooking oil', 'qty' => 4, 'uom' => 'tbsp'],
                ['item' => 'Salt', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Black pepper', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Vegetable cubes', 'qty' => 1, 'uom' => 'pcs'],
            ]
        ],
        [
            'name' => 'Leek and Potato Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday, Saturday.',
            'instructions' => 'Hearty, creamy soup made with fresh leeks and potatoes.',
            'ingredients' => []
        ],
        [
            'name' => 'Tomato Basil Soup with Croutons',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Lunch — Wednesday, Sunday.',
            'instructions' => 'Velvety and mildly sweet soup from ripe tomatoes and fresh basil, served with golden crunchy croutons.',
            'ingredients' => [
                ['item' => 'Fresh tomato', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Onion', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Basil', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Salt', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Black pepper', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Olive oil', 'qty' => 0.05, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Roasted Butternut Squash Soup',
            'category' => 'soup',
            'servings' => 10,
            'notes' => 'Lunch — Thursday.',
            'instructions' => 'Smooth and creamy soup from roasted butternut squash, blended with spices.',
            'ingredients' => [
                ['item' => 'Butternut squash', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Tomato', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Onions', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Celery', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Ginger', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 0.03, 'uom' => 'kg'],
            ]
        ],

        // ═══════════════════════════════════════
        // MAIN COURSES
        // ═══════════════════════════════════════
        [
            'name' => 'Braised Lamb Chops',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Monday, Friday. Served with Lyonnaise Potatoes.',
            'instructions' => 'Slow-cooked lamb chops served with creamy mashed potatoes, glazed carrots, and green beans.',
            'ingredients' => [
                ['item' => 'Lamb (sliced)', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Salt', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Tomato paste', 'qty' => 0.1, 'uom' => 'ltr'],
                ['item' => 'Green pepper', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Onions', 'qty' => 0.25, 'uom' => 'kg'],
                ['item' => 'Carrots', 'qty' => 0.25, 'uom' => 'kg'],
                ['item' => 'Celery', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Potatoes', 'qty' => 3, 'uom' => 'kg'],
                ['item' => 'Butter', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Cream', 'qty' => 0.1, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Grilled Beef Fillet',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Tuesday, Saturday.',
            'instructions' => 'Succulent beef fillet grilled to perfection, served with moist potatoes, cherry tomatoes, green beans, and pepper sauce.',
            'ingredients' => [
                ['item' => 'Beef fillet', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Ginger', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Soy sauce', 'qty' => 0.03, 'uom' => 'ltr'],
                ['item' => 'Garlic', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Potatoes', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Oil', 'qty' => 0.1, 'uom' => 'ltr'],
                ['item' => 'Cherry tomato', 'qty' => 1, 'uom' => 'pkt'],
                ['item' => 'Carrots', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'French beans', 'qty' => 0.2, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Grilled Pork Chop with Rice and Honey Mustard Sauce',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday, Sunday.',
            'instructions' => 'Juicy grilled pork chop served with steamed rice, seasonal vegetables, and tangy honey mustard sauce.',
            'ingredients' => []
        ],
        [
            'name' => 'Tilapia Fish Fillet',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Tender tilapia fish fillet, lightly seasoned and pan-seared. Ideal with rice, chips, or fresh vegetables.',
            'ingredients' => []
        ],
        [
            'name' => 'Grilled Breast Chicken with Lyonnaise Potatoes and Salad',
            'category' => 'main_course',
            'servings' => 5,
            'notes' => 'Lunch — Saturday | Dinner — Monday, Friday. Serves 5, scale x2 for 10.',
            'instructions' => 'In a heated pan add oil then fry the onion, garlic and leeks. Add cream and season. Cut marinated chicken into medium cubes and mix with creamy mixture. Add penne pasta and mix well.',
            'ingredients' => [
                ['item' => 'Chicken breast', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Ginger', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Soy sauce', 'qty' => 0.03, 'uom' => 'ltr'],
                ['item' => 'Garlic', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Turmeric powder', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Potatoes', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 0.3, 'uom' => 'kg'],
                ['item' => 'Oil (sunflower)', 'qty' => 0.5, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Pan-Fried Nile Perch Fillet',
            'category' => 'main_course',
            'servings' => 5,
            'notes' => 'Dinner — Tuesday, Saturday. Serves 5, scale x2 for 10.',
            'instructions' => 'Fresh Nile perch fillet lightly pan-fried, served with steamed rice, sauteed vegetables, and creamy coconut tartar sauce.',
            'ingredients' => [
                ['item' => 'Nile perch fillet', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Salt', 'qty' => 2, 'uom' => 'tsp'],
                ['item' => 'Ginger', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Paprika', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Soy sauce', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Rice', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Broccoli', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Carrot', 'qty' => 0.3, 'uom' => 'kg'],
                ['item' => 'Lemon', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Oil (sunflower)', 'qty' => 0.02, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'One-Pot Garlic Chicken with Tagliatelle Pasta',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday.',
            'instructions' => 'Creamy one-pot dish with tender garlic-seasoned chicken and al dente tagliatelle pasta.',
            'ingredients' => []
        ],
        [
            'name' => 'Beef, Carrot and Potato Stew',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Hearty beef stew slow-cooked with paprika, onions, spices and merlot flavor, served with rice and seasonal vegetables.',
            'ingredients' => []
        ],
        [
            'name' => 'Vegetarian Spaghetti Bolognaise',
            'category' => 'main_course',
            'servings' => 5,
            'notes' => 'Dinner — Monday, Friday. Serves 5, scale x2 for 10.',
            'instructions' => 'Flavorful vegetarian spaghetti served with garden salad.',
            'ingredients' => [
                ['item' => 'Carrots', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Green pepper', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Fresh tomato', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Parmesan cheese', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Tomato paste', 'qty' => 1, 'uom' => 'tin'],
                ['item' => 'Spaghetti', 'qty' => 1, 'uom' => 'pkt'],
            ]
        ],
        [
            'name' => 'Stir-Fried Vegetables with Noodles or Rice',
            'category' => 'main_course',
            'servings' => 5,
            'notes' => 'Dinner — Tuesday, Saturday. Serves 5, scale x2 for 10.',
            'instructions' => 'Light soy-based sauce with broccoli, cabbage, and carrots, served over noodles or steamed rice.',
            'ingredients' => [
                ['item' => 'Carrots', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Baby marrow', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Green pepper', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Cheddar cheese', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Fresh tomato', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Tomato paste', 'qty' => 1, 'uom' => 'tin'],
                ['item' => 'Spaghetti / Noodles', 'qty' => 1, 'uom' => 'pkt'],
            ]
        ],
        [
            'name' => 'Vegetable Ratatouille',
            'category' => 'main_course',
            'cuisine' => 'French',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday.',
            'instructions' => 'Classic French vegetable stew with eggplant, zucchini, bell peppers, and tomatoes, served with steamed rice.',
            'ingredients' => []
        ],
        [
            'name' => 'Vegetable Risotto',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Rich and creamy Magugu rice served with cauliflower, capers and golden sultanas.',
            'ingredients' => []
        ],
        [
            'name' => 'Red Kidney Beans in Coconut Sauce',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Dinner — Monday, Friday.',
            'instructions' => 'Red kidney beans in mildly spiced coconut sauce, served with steamed rice and fresh kachumbari salad.',
            'ingredients' => [
                ['item' => 'Red kidney beans', 'qty' => 1.4, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Carrots', 'qty' => 0.15, 'uom' => 'kg'],
                ['item' => 'Green pepper', 'qty' => 0.05, 'uom' => 'kg'],
                ['item' => 'Coconut milk', 'qty' => 1, 'uom' => 'tin'],
                ['item' => 'Rice', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Aromat', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Oil', 'qty' => 0.1, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Vegetable Lasagne with Salad',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 10,
            'notes' => 'Dinner — Tuesday, Saturday.',
            'instructions' => 'Layers of lasagna sheet, mixed vegetables, and creamy tomato-based sauce, baked to golden perfection with garden salad.',
            'ingredients' => [
                ['item' => 'Lasagna pasta', 'qty' => 1, 'uom' => 'pkt'],
                ['item' => 'Carrots', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Green pepper', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Tomato', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Cheddar cheese', 'qty' => 2, 'uom' => 'kg'],
                ['item' => 'Milk', 'qty' => 0.2, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Pasta Alfredo with Garlic Toast',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday.',
            'instructions' => 'Creamy Alfredo pasta with rich cheesy sauce, served with warm buttery garlic toast.',
            'ingredients' => []
        ],
        [
            'name' => 'Veg Moussaka',
            'category' => 'main_course',
            'cuisine' => 'Greek',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Layered bake of grilled eggplant, zucchini, and spiced lentils, topped with creamy bechamel and oven-finished to golden crust.',
            'ingredients' => []
        ],
        [
            'name' => 'Kilimanjaro Beer-Battered Tilapia with Chips',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Fresh tilapia fillet in light beer batter fried to golden perfection, served with crispy chips and zesty tartar sauce.',
            'ingredients' => [
                ['item' => 'Tilapia fish fillet', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Salt', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Black pepper', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Cayenne pepper', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Beer', 'qty' => 1, 'uom' => 'bottle'],
                ['item' => 'Corn flour', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Wheat flour', 'qty' => 3, 'uom' => 'tbsp'],
                ['item' => 'Mayonnaise', 'qty' => 3, 'uom' => 'tbsp'],
                ['item' => 'Celery stalk', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Dill', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Garlic', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Capers', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Dill pickle', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Lemon juice', 'qty' => 1, 'uom' => 'tbsp'],
            ]
        ],
        [
            'name' => 'Stuffed Chicken',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday.',
            'instructions' => 'Juicy chicken breast stuffed with mushrooms, spinach, and cheese, paired with turmeric rice and glazed vegetables.',
            'ingredients' => []
        ],
        [
            'name' => 'Fish Nile Perch Paprika',
            'category' => 'main_course',
            'servings' => 4,
            'notes' => 'Lunch — Wednesday, Sunday. Serves 4, scale as needed.',
            'instructions' => 'Nile perch fillet seasoned with paprika and pan-finished. Served with rice or chips.',
            'ingredients' => [
                ['item' => 'Nile perch fillet', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Paprika', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Soy sauce', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Fish sauce', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Lemon', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Cooking oil', 'qty' => 0.03, 'uom' => 'ltr'],
                ['item' => 'Potatoes', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Rice', 'qty' => 0.5, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Pizza of Your Choice',
            'category' => 'main_course',
            'servings' => 5,
            'notes' => 'Lunch — Wednesday.',
            'instructions' => 'Fusion pizza topped with tender chicken tikka pieces and melted cheese, with mango Thai salad.',
            'ingredients' => [
                ['item' => 'Beef fillet', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Salt', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Olive oil', 'qty' => 0.1, 'uom' => 'ltr'],
                ['item' => 'Green pepper', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Onions', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Wheat flour', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Mozzarella cheese', 'qty' => 0.5, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Chicken Satay with Cajun Potato Wedges and Garden Salad',
            'category' => 'main_course',
            'cuisine' => 'Thai',
            'servings' => 5,
            'notes' => 'Lunch — Thursday.',
            'instructions' => 'Tender marinated chicken skewers grilled to perfection, served with Cajun-seasoned potato wedges and garden salad.',
            'ingredients' => [
                ['item' => 'Chicken breast', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Ginger', 'qty' => 0.015, 'uom' => 'kg'],
                ['item' => 'Soy sauce', 'qty' => 0.03, 'uom' => 'ltr'],
                ['item' => 'Garlic', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Paprika', 'qty' => 0.015, 'uom' => 'kg'],
                ['item' => 'Turmeric powder', 'qty' => 0.015, 'uom' => 'kg'],
                ['item' => 'Irish potatoes', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Peanut butter', 'qty' => 0.1, 'uom' => 'ltr'],
                ['item' => 'Sunflower oil', 'qty' => 0.1, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Grilled Pork Chops with Sweet and Sour Sauce',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Tender pork chops grilled and glazed with sweet-and-sour sauce, served with seasonal vegetables.',
            'ingredients' => [
                ['item' => 'Pork', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Soy sauce', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Rosemary', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Garlic paste', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Dijon mustard', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Black pepper', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Ketchup', 'qty' => 0.25, 'uom' => 'ltr'],
                ['item' => 'Vinegar', 'qty' => 0.15, 'uom' => 'ltr'],
                ['item' => 'Brown sugar', 'qty' => 2, 'uom' => 'tbsp'],
                ['item' => 'Corn starch', 'qty' => 1, 'uom' => 'tsp'],
            ]
        ],
        [
            'name' => 'Spaghetti Bolognese with Garlic Toast',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 5,
            'notes' => 'Lunch — Tuesday, Saturday. Serves 5, scale x2 for 10.',
            'instructions' => 'Classic spaghetti in rich savory meat sauce, served with buttery garlic toast.',
            'ingredients' => [
                ['item' => 'Carrots', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Green pepper', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Fresh tomato', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Parmesan cheese', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Tomato paste', 'qty' => 1, 'uom' => 'tin'],
                ['item' => 'Spaghetti', 'qty' => 1, 'uom' => 'pkt'],
            ]
        ],
        [
            'name' => 'Beef Stroganoff',
            'category' => 'main_course',
            'cuisine' => 'Russian',
            'servings' => 5,
            'notes' => 'Lunch — Wednesday, Sunday.',
            'instructions' => 'Tender strips of beef in creamy mushroom sauce with mustard. Served with rice or mashed potatoes.',
            'ingredients' => [
                ['item' => 'Beef fillet', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Garlic', 'qty' => 4, 'uom' => 'pcs'],
                ['item' => 'Mushrooms', 'qty' => 2, 'uom' => 'tin'],
                ['item' => 'Tomato paste', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Cooking oil', 'qty' => 3, 'uom' => 'tbsp'],
                ['item' => 'Rosemary', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Milac cream', 'qty' => 0.15, 'uom' => 'ltr'],
                ['item' => 'Dijon mustard', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Aromat', 'qty' => 5, 'uom' => 'tsp'],
            ]
        ],
        [
            'name' => 'Vegetable Quiche with Mixed Salad',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Flaky pastry filled with creamy egg mixture and seasonal vegetables, paired with mixed salad.',
            'ingredients' => [
                ['item' => 'Roasted butternut', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Spinach', 'qty' => 2, 'uom' => 'bunch'],
                ['item' => 'Broccoli', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Bell pepper', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Cooking oil', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Butter', 'qty' => 1, 'uom' => 'tbsp'],
                ['item' => 'Egg', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Cream', 'qty' => 0.25, 'uom' => 'kg'],
                ['item' => 'Flour', 'qty' => 0.15, 'uom' => 'kg'],
                ['item' => 'Butter (crust)', 'qty' => 0.075, 'uom' => 'kg'],
                ['item' => 'Egg (crust)', 'qty' => 1, 'uom' => 'pcs'],
            ]
        ],
        [
            'name' => 'Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday, Saturday.',
            'instructions' => 'Baked aubergine rolls filled with vegetables and cheese, with fresh salad and sweet corn salsa.',
            'ingredients' => []
        ],
        [
            'name' => 'Butternut Squash Ravioli',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 5,
            'notes' => 'Lunch — Wednesday, Sunday.',
            'instructions' => 'Delicate pasta parcels filled with roasted butternut squash, served in light butter sage sauce.',
            'ingredients' => [
                ['item' => 'Roasted butternut', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Rosemary', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Garlic', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Cooking oil', 'qty' => 3, 'uom' => 'tbsp'],
                ['item' => 'Wheat flour', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Eggs', 'qty' => 6, 'uom' => 'pcs'],
                ['item' => 'Salt', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Butter', 'qty' => 0.1, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Thai Green Vegetable Curry',
            'category' => 'main_course',
            'cuisine' => 'Thai',
            'servings' => 10,
            'notes' => 'Lunch — Thursday.',
            'instructions' => 'Made with seasonal vegetables, fresh herbs, and served with rice.',
            'ingredients' => [
                ['item' => 'Carrots', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Cauliflower', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Green pepper', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Broccoli', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Tomato', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Garlic', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Rice', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Coconut milk', 'qty' => 1, 'uom' => 'pkt'],
            ]
        ],
        [
            'name' => 'Pasta with Tomato Pesto Sauce',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Penne pasta tossed in tomato pesto sauce, served with warm garlic toast.',
            'ingredients' => [
                ['item' => 'Pasta', 'qty' => 1, 'uom' => 'pkt'],
                ['item' => 'Tomato', 'qty' => 4, 'uom' => 'kg'],
                ['item' => 'Basil', 'qty' => 0.25, 'uom' => 'bunch'],
                ['item' => 'Chilli paste', 'qty' => 1, 'uom' => 'tsp'],
            ]
        ],
        [
            'name' => 'Vegetable Cannelloni with Salad',
            'category' => 'main_course',
            'cuisine' => 'Italian',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday, Saturday.',
            'instructions' => 'Rolled pasta stuffed with vegetable filling, baked in white sauce, paired with crispy garden salad.',
            'ingredients' => []
        ],
        [
            'name' => 'Vegetable Wraps',
            'category' => 'main_course',
            'servings' => 10,
            'notes' => 'Lunch — Wednesday, Sunday.',
            'instructions' => 'Grilled seasonal vegetables wrapped in soft flatbread, served with yogurt dip.',
            'ingredients' => [
                ['item' => 'Wheat flour', 'qty' => 1, 'uom' => 'kg'],
                ['item' => 'Carrot', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Bell pepper', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Black pepper', 'qty' => 0.25, 'uom' => 'tsp'],
                ['item' => 'Salt', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Cooking oil', 'qty' => 0.1, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Veg Burger',
            'category' => 'main_course',
            'servings' => 5,
            'notes' => 'Lunch — Thursday.',
            'instructions' => 'Hearty vegetable patty with fresh lettuce, tomato, and house-made sauce in toasted bun with chips.',
            'ingredients' => [
                ['item' => 'Carrots', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Baby marrow', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Onion', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Mashed potato', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Cheddar cheese', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Curry sauce', 'qty' => 3, 'uom' => 'tbsp'],
                ['item' => 'Wheat flour', 'qty' => 1, 'uom' => 'kg'],
            ]
        ],

        // ═══════════════════════════════════════
        // SIDES
        // ═══════════════════════════════════════
        [
            'name' => 'Coconut Rice',
            'category' => 'side',
            'servings' => 10,
            'notes' => 'Paired with Red Kidney Beans and other mains.',
            'instructions' => 'Fluffy rice cooked in rich coconut milk.',
            'ingredients' => [
                ['item' => 'Coconut milk', 'qty' => 1, 'uom' => 'pkt'],
                ['item' => 'Rice', 'qty' => 0.5, 'uom' => 'kg'],
            ]
        ],

        // ═══════════════════════════════════════
        // DESSERTS
        // ═══════════════════════════════════════
        [
            'name' => 'Invisible Apple Cake',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Dinner — Monday, Friday. Served with strawberry coulis.',
            'instructions' => 'Unique apple cake with delicate layers of thinly sliced apples, served with tangy strawberry coulis.',
            'ingredients' => [
                ['item' => 'Eggs', 'qty' => 8, 'uom' => 'pcs'],
                ['item' => 'Sugar', 'qty' => 1.25, 'uom' => 'cup'],
                ['item' => 'Milk', 'qty' => 2, 'uom' => 'cup'],
                ['item' => 'Wheat flour', 'qty' => 2.5, 'uom' => 'cup'],
                ['item' => 'Vanilla essence', 'qty' => 3, 'uom' => 'tsp'],
                ['item' => 'Apples', 'qty' => 5, 'uom' => 'pcs'],
                ['item' => 'Butter (unsalted)', 'qty' => 0.5, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Chocolate Brownies',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Dinner — Tuesday, Saturday. Served with homemade ice cream.',
            'instructions' => 'Decadent chocolate dessert served with homemade ice cream.',
            'ingredients' => [
                ['item' => 'Butter', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Dark chocolate', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Brown sugar', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Eggs', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Cocoa powder', 'qty' => 0.06, 'uom' => 'kg'],
                ['item' => 'Wheat flour', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Vanilla', 'qty' => 0.015, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Malva Pudding',
            'category' => 'dessert',
            'cuisine' => 'South African',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday.',
            'instructions' => 'Traditional South African moist sponge pudding soaked in sweet syrup, served warm.',
            'ingredients' => []
        ],
        [
            'name' => 'Apple Crumble with Custard Sauce',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Warm spiced apples topped with crumbly streusel, served with creamy custard sauce.',
            'ingredients' => []
        ],
        [
            'name' => 'Passion and Cheddar Cheese Tart',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Dinner — Monday, Friday.',
            'instructions' => 'Rich and creamy tart with tangy passion fruit and sharp cheddar cheese, with sweet and savory balance.',
            'ingredients' => [
                ['item' => 'Cream cheese', 'qty' => 0.5, 'uom' => 'ltr'],
                ['item' => 'Cheddar cheese', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Eggs', 'qty' => 2, 'uom' => 'pcs'],
                ['item' => 'Castor sugar', 'qty' => 0.08, 'uom' => 'kg'],
                ['item' => 'Vanilla essence', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Whipping cream', 'qty' => 0.3, 'uom' => 'ltr'],
                ['item' => 'Passion fruits', 'qty' => 0.25, 'uom' => 'ltr'],
                ['item' => 'Digestive biscuits', 'qty' => 0.1, 'uom' => 'kg'],
                ['item' => 'Butter (unsalted)', 'qty' => 0.1, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Sticky Toffee Pudding',
            'category' => 'dessert',
            'cuisine' => 'British',
            'servings' => 10,
            'notes' => 'Dinner — Tuesday, Saturday.',
            'instructions' => 'Moist, date-filled sponge cake soaked in rich buttery toffee sauce. Served warm with vanilla ice cream.',
            'ingredients' => [
                ['item' => 'Dates', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Eggs', 'qty' => 5, 'uom' => 'pcs'],
                ['item' => 'Butter', 'qty' => 0.14, 'uom' => 'kg'],
                ['item' => 'Castor sugar', 'qty' => 0.08, 'uom' => 'kg'],
                ['item' => 'Vanilla essence', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Brown sugar', 'qty' => 0.15, 'uom' => 'kg'],
                ['item' => 'Wheat flour', 'qty' => 0.5, 'uom' => 'kg'],
                ['item' => 'Baking soda', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Cream', 'qty' => 0.25, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Pineapple Upside-Down Cake',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Dinner — Wednesday, Sunday.',
            'instructions' => 'Moist and buttery cake topped with caramelized pineapple slices.',
            'ingredients' => []
        ],
        [
            'name' => 'Lemon Cheesecake',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Dinner — Thursday.',
            'instructions' => 'Smooth and tangy cheesecake with buttery biscuit base, topped with zesty lemon glaze.',
            'ingredients' => []
        ],
        [
            'name' => 'Fresh Fruit Salad',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Vibrant selection of seasonal fruits, freshly prepared.',
            'ingredients' => []
        ],
        [
            'name' => 'Mango Sorbet',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday, Saturday.',
            'instructions' => 'Refreshing sorbet made from ripe, juicy mangoes.',
            'ingredients' => []
        ],
        [
            'name' => 'Berry Panna Cotta',
            'category' => 'dessert',
            'cuisine' => 'Italian',
            'servings' => 10,
            'notes' => 'Lunch — Wednesday, Sunday.',
            'instructions' => 'Smooth and creamy Italian dessert infused with fresh berries.',
            'ingredients' => [
                ['item' => 'Fresh strawberries', 'qty' => 2, 'uom' => 'pkt'],
                ['item' => 'Castor sugar', 'qty' => 0.08, 'uom' => 'kg'],
                ['item' => 'Vanilla essence', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Whipping cream', 'qty' => 0.3, 'uom' => 'ltr'],
                ['item' => 'Gelatine', 'qty' => 0.03, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Lemon Meringue Pie',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Thursday.',
            'instructions' => 'Zesty lemon custard topped with fluffy golden meringue, on buttery crust.',
            'ingredients' => [
                ['item' => 'Eggs', 'qty' => 8, 'uom' => 'pcs'],
                ['item' => 'Sugar', 'qty' => 0.25, 'uom' => 'kg'],
                ['item' => 'Cornstarch', 'qty' => 0.15, 'uom' => 'kg'],
                ['item' => 'Heavy cream', 'qty' => 0.25, 'uom' => 'kg'],
                ['item' => 'Vanilla essence', 'qty' => 0.015, 'uom' => 'ltr'],
            ]
        ],
        [
            'name' => 'Chocolate Mousse',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Monday, Friday.',
            'instructions' => 'Rich and creamy mousse made with fine chocolate, topped with cocoa or fresh berries.',
            'ingredients' => []
        ],
        [
            'name' => 'Coconut Cream Caramel',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Tuesday, Saturday.',
            'instructions' => 'Luscious and smooth caramel custard infused with coconut flavor, topped with light caramel glaze.',
            'ingredients' => []
        ],
        [
            'name' => 'Banana Fritters with Custard Sauce',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Wednesday, Sunday.',
            'instructions' => 'Sweet banana fritters fried to golden crisp, served with rich creamy custard sauce.',
            'ingredients' => [
                ['item' => 'Banana', 'qty' => 5, 'uom' => 'pcs'],
                ['item' => 'Sugar', 'qty' => 1.25, 'uom' => 'cup'],
                ['item' => 'Milk', 'qty' => 2, 'uom' => 'cup'],
                ['item' => 'Eggs', 'qty' => 1, 'uom' => 'pcs'],
                ['item' => 'Vanilla essence', 'qty' => 3, 'uom' => 'tsp'],
                ['item' => 'Baking powder', 'qty' => 0.015, 'uom' => 'kg'],
            ]
        ],
        [
            'name' => 'Chocolate Fudge Cake',
            'category' => 'dessert',
            'servings' => 10,
            'notes' => 'Lunch — Thursday.',
            'instructions' => 'Moist and indulgent chocolate cake layered with rich fudge.',
            'ingredients' => [
                ['item' => 'Condensed milk', 'qty' => 0.15, 'uom' => 'ltr'],
                ['item' => 'Eggs', 'qty' => 3, 'uom' => 'pcs'],
                ['item' => 'Castor sugar', 'qty' => 0.08, 'uom' => 'kg'],
                ['item' => 'Vanilla essence', 'qty' => 1, 'uom' => 'tsp'],
                ['item' => 'Cocoa powder', 'qty' => 0.03, 'uom' => 'kg'],
                ['item' => 'Wheat flour', 'qty' => 0.2, 'uom' => 'kg'],
                ['item' => 'Bicarbonate of soda', 'qty' => 0.02, 'uom' => 'kg'],
                ['item' => 'Golden syrup', 'qty' => 0.05, 'uom' => 'ltr'],
            ]
        ],
    ];
}
