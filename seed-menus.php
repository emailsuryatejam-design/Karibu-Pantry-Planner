<?php
/**
 * Seed weekly menus as recipes + ingredients into the database
 * Run once: https://your-domain.com/seed-menus.php
 *
 * Only tracks cost-significant ingredients (proteins, dairy, vegetables, fruits, grains, pasta).
 * Basic pantry items (spices, herbs, oils, sauces, sugar, flour, etc.) are excluded.
 */
require_once __DIR__ . '/config.php';
$db = getDB();

// ══════════════════════════════════════════
// STEP 1: Seed cost-significant items only
// ══════════════════════════════════════════

$newItems = [
    // Proteins
    ['Tilapia Fillet', 'Proteins', 'kg'],
    ['Nile Perch Fillet', 'Proteins', 'kg'],
    ['Chicken Breast', 'Proteins', 'kg'],
    ['Beef Fillet', 'Proteins', 'kg'],
    ['Beef Mince', 'Proteins', 'kg'],
    ['Lamb Chops', 'Proteins', 'kg'],
    ['Pork Chops', 'Proteins', 'kg'],
    ['Chicken Thighs', 'Proteins', 'kg'],
    ['Beef Stewing', 'Proteins', 'kg'],
    ['Eggs', 'Proteins', 'pcs'],
    // Dairy
    ['Mozzarella Cheese', 'Dairy', 'kg'],
    ['Camembert Cheese', 'Dairy', 'kg'],
    ['Cheddar Cheese', 'Dairy', 'kg'],
    ['Feta Cheese', 'Dairy', 'kg'],
    ['Parmesan Cheese', 'Dairy', 'kg'],
    ['Cream Cheese', 'Dairy', 'kg'],
    ['Heavy Cream', 'Dairy', 'ltr'],
    ['Milk', 'Dairy', 'ltr'],
    ['Yogurt', 'Dairy', 'ltr'],
    // Vegetables
    ['Carrots', 'Vegetables', 'kg'],
    ['Celery', 'Vegetables', 'kg'],
    ['Leeks', 'Vegetables', 'kg'],
    ['Potatoes', 'Vegetables', 'kg'],
    ['Sweet Potatoes', 'Vegetables', 'kg'],
    ['Butternut Squash', 'Vegetables', 'kg'],
    ['Pumpkin', 'Vegetables', 'kg'],
    ['Broccoli', 'Vegetables', 'kg'],
    ['Spinach', 'Vegetables', 'kg'],
    ['Baby Marrow', 'Vegetables', 'kg'],
    ['Aubergine', 'Vegetables', 'kg'],
    ['Bell Peppers', 'Vegetables', 'kg'],
    ['Mushrooms', 'Vegetables', 'kg'],
    ['Cabbage', 'Vegetables', 'kg'],
    ['Green Beans', 'Vegetables', 'kg'],
    ['Cherry Tomatoes', 'Vegetables', 'kg'],
    ['Cucumber', 'Vegetables', 'kg'],
    ['Beetroot', 'Vegetables', 'kg'],
    ['Lettuce', 'Vegetables', 'kg'],
    ['Corn (Sweet Corn)', 'Vegetables', 'kg'],
    ['Cauliflower', 'Vegetables', 'kg'],
    ['Zucchini', 'Vegetables', 'kg'],
    // Fruits
    ['Mango', 'Fruits', 'kg'],
    ['Avocado', 'Fruits', 'kg'],
    ['Banana', 'Fruits', 'kg'],
    ['Lemon', 'Fruits', 'kg'],
    ['Orange', 'Fruits', 'kg'],
    ['Pineapple', 'Fruits', 'kg'],
    ['Passion Fruit', 'Fruits', 'kg'],
    ['Apple', 'Fruits', 'kg'],
    ['Berries (Mixed)', 'Fruits', 'kg'],
    ['Strawberries', 'Fruits', 'kg'],
    ['Dates', 'Fruits', 'kg'],
    // Grains & Pasta
    ['Rice', 'Grains & Pasta', 'kg'],
    ['Spaghetti', 'Grains & Pasta', 'kg'],
    ['Penne Pasta', 'Grains & Pasta', 'kg'],
    ['Tagliatelle', 'Grains & Pasta', 'kg'],
    ['Lasagne Sheets', 'Grains & Pasta', 'kg'],
    ['Noodles', 'Grains & Pasta', 'kg'],
    ['Bread', 'Grains & Pasta', 'pcs'],
    ['Pizza Dough', 'Grains & Pasta', 'kg'],
    ['Pastry Sheets', 'Grains & Pasta', 'kg'],
    ['Tortilla Wraps', 'Grains & Pasta', 'pcs'],
    ['Burger Buns', 'Grains & Pasta', 'pcs'],
    ['Cannelloni Tubes', 'Grains & Pasta', 'kg'],
    ['Ravioli Sheets', 'Grains & Pasta', 'kg'],
    // Pantry (cost-significant only)
    ['Coconut Milk', 'Pantry', 'ltr'],
    ['Peanut Butter', 'Pantry', 'kg'],
    ['Red Kidney Beans', 'Pantry', 'kg'],
    ['Lentils', 'Pantry', 'kg'],
    ['Chocolate', 'Pantry', 'kg'],
    ['Capers', 'Pantry', 'kg'],
    ['Sultanas', 'Pantry', 'kg'],
    // Beverages
    ['Beer (Kilimanjaro)', 'Beverages', 'ltr'],
];

$itemsAdded = 0;
$itemsSkipped = 0;
foreach ($newItems as $item) {
    $check = $db->prepare('SELECT id FROM items WHERE name = ?');
    $check->execute([$item[0]]);
    if ($check->fetch()) {
        $itemsSkipped++;
        continue;
    }
    $stmt = $db->prepare('INSERT INTO items (name, category, uom, is_active) VALUES (?, ?, ?, 1)');
    $stmt->execute([$item[0], $item[1], $item[2]]);
    $itemsAdded++;
}

// Build item lookup cache
$allItems = $db->query('SELECT id, name FROM items')->fetchAll();
$itemLookup = [];
foreach ($allItems as $item) {
    $itemLookup[strtolower(trim($item['name']))] = $item['id'];
}

function findItemId($name) {
    global $itemLookup;
    $name = strtolower(trim($name));
    if (isset($itemLookup[$name])) return $itemLookup[$name];
    foreach ($itemLookup as $key => $id) {
        if (strpos($key, $name) !== false || strpos($name, $key) !== false) return $id;
    }
    return null;
}

// ══════════════════════════════════════════
// STEP 2: Seed recipes with ingredients
// (only cost-significant ingredients)
// ══════════════════════════════════════════

$recipesAdded = 0;
$recipesSkipped = 0;

function addRecipeWithIngredients($db, $name, $category, $description, $ingredients, &$added, &$skipped) {
    $name = trim($name);
    if (!$name) return;

    $check = $db->prepare('SELECT id FROM recipes WHERE name = ?');
    $check->execute([$name]);
    if ($check->fetch()) {
        $skipped++;
        return;
    }

    $stmt = $db->prepare('INSERT INTO recipes (name, category, cuisine, difficulty, prep_time, cook_time, servings, instructions, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $category, 'International', 'medium', 20, 30, 4, trim($description) ?: null, null]);
    $recipeId = $db->lastInsertId();
    $added++;

    $ingStmt = $db->prepare('INSERT INTO recipe_ingredients (recipe_id, item_id, item_name, qty, uom, is_primary) VALUES (?, ?, ?, ?, ?, 1)');
    foreach ($ingredients as $ing) {
        $itemId = findItemId($ing[0]);
        $ingStmt->execute([$recipeId, $itemId, $ing[0], $ing[1], $ing[2] ?? 'kg']);
    }
}

// ══════════════════════════════════════════
// LUNCH MENU
// ══════════════════════════════════════════

// --- Appetizers ---
addRecipeWithIngredients($db, 'Vegetable Samosa with Sweet Chili Sauce', 'appetizer',
    'Crispy golden pastry stuffed with a flavorful mix of spiced vegetables, served with a tangy and sweet chili dipping sauce.',
    [['Pastry Sheets', 0.5], ['Potatoes', 0.8], ['Carrots', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Avocado Vinaigrette', 'appetizer',
    'Fresh avocado halves drizzled with a tangy vinaigrette dressing.',
    [['Avocado', 1.5], ['Lemon', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Camembert and Caramelized Onion Bruschetta', 'appetizer',
    'Crisp toasted bread topped with creamy Camembert cheese and sweet caramelized onions, finished with balsamic glaze.',
    [['Bread', 10, 'pcs'], ['Camembert Cheese', 0.4]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Tomato, Avocado, and Mango Bruschetta', 'appetizer',
    'Crisp toasted bread topped with diced tomato, creamy avocado, and sweet mango, finished with a light vinaigrette.',
    [['Bread', 10, 'pcs'], ['Cherry Tomatoes', 0.5], ['Avocado', 0.6], ['Mango', 0.4]],
    $recipesAdded, $recipesSkipped);

// --- Soups ---
addRecipeWithIngredients($db, 'Carrot and Celery Soup', 'soup',
    'A smooth and flavorful soup crafted from fresh carrots and celery, gently simmered with aromatic spices.',
    [['Carrots', 1.5], ['Celery', 0.5], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Leek and Potato Soup', 'soup',
    'A hearty, creamy soup made with fresh leeks and potatoes, gently seasoned.',
    [['Leeks', 0.8], ['Potatoes', 1.0], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Tomato Basil Soup with Croutons', 'soup',
    'A smooth and savory soup made with ripe tomatoes and fresh basil, served with golden croutons.',
    [['Cherry Tomatoes', 1.0]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Roasted Butternut Squash Soup', 'soup',
    'A velvety and mildly sweet soup made from roasted butternut squash, blended with spices.',
    [['Butternut Squash', 2.0], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

// --- Main Courses (Lunch) ---
addRecipeWithIngredients($db, 'Kilimanjaro Beer-Battered Tilapia with Chips', 'main_course',
    'Fresh tilapia fillet coated in a light beer batter and fried to golden perfection, served with crispy chips.',
    [['Tilapia Fillet', 2.0], ['Beer (Kilimanjaro)', 0.5, 'ltr'], ['Potatoes', 2.0], ['Lemon', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Stuffed Chicken', 'main_course',
    'Juicy chicken breast stuffed with mushrooms, spinach, and cheese, paired with turmeric rice.',
    [['Chicken Breast', 2.5], ['Mushrooms', 0.3], ['Spinach', 0.3], ['Cheddar Cheese', 0.3], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Fish Nile Perch Paprika', 'main_course',
    'Nile perch fillet gently seasoned with paprika and pan-finished. Served with rice or chips.',
    [['Nile Perch Fillet', 2.5], ['Lemon', 0.3], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Pizza of Your Choice', 'main_course',
    'A fusion pizza topped with tender chicken tikka pieces and melted cheese.',
    [['Pizza Dough', 1.5], ['Chicken Breast', 1.5], ['Mozzarella Cheese', 0.8], ['Bell Peppers', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'main_course',
    'Juicy grilled chicken breast served with crispy lyonnaise potatoes and a refreshing side salad.',
    [['Chicken Breast', 2.5], ['Potatoes', 1.5], ['Lettuce', 0.3], ['Cherry Tomatoes', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Grilled Pork Chops with Sweet and Sour Sauce', 'main_course',
    'Tender pork chops grilled to perfection and glazed with a rich sweet-and-sour sauce.',
    [['Pork Chops', 2.5], ['Bell Peppers', 0.3], ['Pineapple', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Spaghetti Bolognese with Garlic Toast', 'main_course',
    'Classic spaghetti in a rich meat sauce, served with buttery garlic toast.',
    [['Spaghetti', 1.5], ['Beef Mince', 1.5], ['Bread', 10, 'pcs'], ['Parmesan Cheese', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Beef Stroganoff', 'main_course',
    'Tender strips of beef simmered in a creamy mushroom sauce. Served with rice or mashed potatoes.',
    [['Beef Fillet', 2.0], ['Mushrooms', 0.5], ['Heavy Cream', 0.3, 'ltr'], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Chicken Satay with Cajun Potato Wedges and Garden Salad', 'main_course',
    'Marinated chicken skewers grilled to perfection, served with Cajun potato wedges and garden salad.',
    [['Chicken Breast', 2.0], ['Peanut Butter', 0.15], ['Potatoes', 1.5], ['Lettuce', 0.3], ['Cucumber', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetable Quiche with Mixed Salad', 'main_course',
    'A flaky pastry filled with a creamy egg mixture and seasonal vegetables, paired with a fresh mixed salad.',
    [['Pastry Sheets', 0.5], ['Eggs', 10, 'pcs'], ['Heavy Cream', 0.2, 'ltr'], ['Spinach', 0.3], ['Bell Peppers', 0.2], ['Cheddar Cheese', 0.3], ['Lettuce', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa', 'main_course',
    'Baked aubergine rolls filled with vegetables and cheese, with salad and sweet corn salsa.',
    [['Aubergine', 1.5], ['Mozzarella Cheese', 0.4], ['Cherry Tomatoes', 0.3], ['Corn (Sweet Corn)', 0.3], ['Lettuce', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Butternut Squash Ravioli', 'main_course',
    'Delicate pasta parcels filled with roasted butternut squash, in a light butter sage sauce.',
    [['Ravioli Sheets', 1.0], ['Butternut Squash', 1.5], ['Parmesan Cheese', 0.2], ['Eggs', 5, 'pcs']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Thai Green Vegetable Curry', 'main_course',
    'Made with seasonal vegetables, fresh herbs, and served with rice.',
    [['Coconut Milk', 0.8, 'ltr'], ['Broccoli', 0.3], ['Bell Peppers', 0.3], ['Baby Marrow', 0.3], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Pasta with Tomato Pesto Sauce', 'main_course',
    'Penne pasta tossed in a flavorful tomato pesto sauce, served with warm garlic toast.',
    [['Penne Pasta', 1.5], ['Bread', 10, 'pcs'], ['Parmesan Cheese', 0.15]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetable Cannelloni with Salad', 'main_course',
    'Rolled pasta stuffed with a flavorful vegetable filling, baked in a white sauce, with garden salad.',
    [['Cannelloni Tubes', 0.8], ['Spinach', 0.4], ['Mushrooms', 0.3], ['Heavy Cream', 0.3, 'ltr'], ['Lettuce', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetable Wraps', 'main_course',
    'Grilled seasonal vegetables wrapped in soft flatbread, served with a light yogurt dip.',
    [['Tortilla Wraps', 10, 'pcs'], ['Bell Peppers', 0.4], ['Mushrooms', 0.3], ['Lettuce', 0.2], ['Avocado', 0.4], ['Yogurt', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Veg Burger', 'main_course',
    'A hearty vegetable patty with fresh lettuce, tomato, and house sauce, in a toasted bun with chips.',
    [['Potatoes', 1.0], ['Carrots', 0.3], ['Green Beans', 0.2], ['Burger Buns', 10, 'pcs'], ['Lettuce', 0.2], ['Cherry Tomatoes', 0.3]],
    $recipesAdded, $recipesSkipped);

// --- Desserts (Lunch) ---
addRecipeWithIngredients($db, 'Fresh Fruit Salad', 'dessert',
    'A vibrant selection of seasonal fruits.',
    [['Mango', 0.5], ['Pineapple', 0.5], ['Banana', 0.5], ['Orange', 0.5], ['Berries (Mixed)', 0.3], ['Passion Fruit', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Mango Sorbet', 'dessert',
    'A refreshing sorbet made from ripe mangoes.',
    [['Mango', 1.5], ['Lemon', 0.1]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Berry Panna Cotta', 'dessert',
    'Smooth and creamy Italian dessert infused with fresh berries.',
    [['Heavy Cream', 0.5, 'ltr'], ['Milk', 0.3, 'ltr'], ['Berries (Mixed)', 0.4]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Lemon Meringue Pie', 'dessert',
    'Zesty lemon custard topped with fluffy golden meringue on a buttery crust.',
    [['Lemon', 0.5], ['Eggs', 8, 'pcs']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Chocolate Mousse', 'dessert',
    'Rich and creamy mousse made with fine chocolate.',
    [['Chocolate', 0.4], ['Heavy Cream', 0.4, 'ltr'], ['Eggs', 6, 'pcs']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Coconut Cream Caramel', 'dessert',
    'Luscious smooth caramel custard infused with coconut flavor.',
    [['Coconut Milk', 0.5, 'ltr'], ['Eggs', 6, 'pcs'], ['Milk', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Banana Fritters with Custard Sauce', 'dessert',
    'Sweet banana fritters fried to golden crisp, served with custard sauce.',
    [['Banana', 1.5], ['Eggs', 4, 'pcs'], ['Milk', 0.3, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Chocolate Fudge Cake', 'dessert',
    'Moist and indulgent chocolate cake layered with rich fudge.',
    [['Chocolate', 0.4], ['Eggs', 6, 'pcs'], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

// ══════════════════════════════════════════
// DINNER MENU
// ══════════════════════════════════════════

// --- Appetizers (Dinner) ---
addRecipeWithIngredients($db, 'Vegetable Spring Rolls', 'appetizer',
    'Crispy golden spring rolls filled with fresh vegetables, served with peanut, coconut, and sweet chili dips.',
    [['Pastry Sheets', 0.5], ['Cabbage', 0.4], ['Carrots', 0.3], ['Mushrooms', 0.2], ['Peanut Butter', 0.1], ['Coconut Milk', 0.15, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Caprese Salad with Basil Pesto', 'appetizer',
    'Ripe tomatoes, creamy mozzarella, and fresh basil with aromatic pesto.',
    [['Cherry Tomatoes', 0.8], ['Mozzarella Cheese', 0.6]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Curried Sweet Potato Samosas with Tomato Salsa', 'appetizer',
    'Golden crispy samosas filled with spiced sweet potato, paired with tomato salsa.',
    [['Sweet Potatoes', 1.0], ['Pastry Sheets', 0.5], ['Cherry Tomatoes', 0.4]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Sliced Beetroot with Orange Segments and Feta Cheese', 'appetizer',
    'Sliced beetroot paired with orange segments and feta cheese, with honey mustard vinaigrette.',
    [['Beetroot', 1.0], ['Orange', 0.6], ['Feta Cheese', 0.3]],
    $recipesAdded, $recipesSkipped);

// --- Soups (Dinner) ---
addRecipeWithIngredients($db, 'Cream of Broccoli Soup', 'soup',
    'Smooth and creamy soup from fresh broccoli, finished with cream.',
    [['Broccoli', 1.5], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Pumpkin Soup', 'soup',
    'Rich and creamy soup from roasted pumpkin, blended with warm spices.',
    [['Pumpkin', 2.0], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Baby Marrow Soup', 'soup',
    'Light and refreshing soup made with cucumber and baby marrow.',
    [['Baby Marrow', 1.5], ['Cucumber', 0.5], ['Heavy Cream', 0.15, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Mixed Vegetable Soup', 'soup',
    'Wholesome soup with a medley of fresh seasonal vegetables.',
    [['Carrots', 0.4], ['Potatoes', 0.4], ['Celery', 0.2], ['Green Beans', 0.2]],
    $recipesAdded, $recipesSkipped);

// --- Main Courses (Dinner) ---
addRecipeWithIngredients($db, 'Braised Lamb Chops', 'main_course',
    'Slow-cooked lamb to perfection, with mashed potatoes, glazed carrots, and green beans.',
    [['Lamb Chops', 3.0], ['Potatoes', 1.5], ['Carrots', 0.5], ['Green Beans', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Grilled Beef Fillet', 'main_course',
    'Succulent beef fillet grilled to perfection, with roast potatoes and pepper sauce.',
    [['Beef Fillet', 3.0], ['Potatoes', 1.5], ['Cherry Tomatoes', 0.3], ['Green Beans', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Grilled Pork Chop with Rice and Honey Mustard Sauce', 'main_course',
    'Juicy grilled pork chop with steamed rice and honey mustard sauce.',
    [['Pork Chops', 2.5], ['Rice', 1.5], ['Green Beans', 0.3], ['Carrots', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Tilapia Fish Fillet', 'main_course',
    'Tender tilapia fillet lightly seasoned and pan-seared.',
    [['Tilapia Fillet', 2.5], ['Lemon', 0.3], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Pan-Fried Nile Perch Fillet', 'main_course',
    'Fresh Nile perch fillet pan-fried with steamed rice, vegetables, and coconut tartar sauce.',
    [['Nile Perch Fillet', 2.5], ['Rice', 1.5], ['Coconut Milk', 0.2, 'ltr'], ['Carrots', 0.3], ['Green Beans', 0.2], ['Lemon', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'One-Pot Garlic Chicken with Tagliatelle Pasta', 'main_course',
    'Creamy one-pot dish with garlic chicken and al dente tagliatelle.',
    [['Chicken Breast', 2.0], ['Tagliatelle', 1.5], ['Heavy Cream', 0.3, 'ltr'], ['Parmesan Cheese', 0.2], ['Spinach', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Beef, Carrot and Potato Stew', 'main_course',
    'Hearty beef stew slow-cooked with paprika, onions, and spices, served with rice.',
    [['Beef Stewing', 2.5], ['Carrots', 0.5], ['Potatoes', 1.0], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetarian Spaghetti Bolognaise', 'main_course',
    'Flavorful vegetarian spaghetti served with garden salad.',
    [['Spaghetti', 1.5], ['Mushrooms', 0.5], ['Lentils', 0.3], ['Lettuce', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Stir-Fried Vegetables with Noodles or Rice', 'main_course',
    'Light soy-based sauce with broccoli, cabbage, and carrots.',
    [['Noodles', 1.0], ['Broccoli', 0.4], ['Cabbage', 0.4], ['Carrots', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetable Ratatouille', 'main_course',
    'Classic French vegetable stew with eggplant, zucchini, bell peppers, and tomatoes with rice.',
    [['Aubergine', 0.5], ['Zucchini', 0.5], ['Bell Peppers', 0.4], ['Cherry Tomatoes', 0.5], ['Rice', 1.5]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetable Risotto', 'main_course',
    'Rich and creamy rice served with cauliflower, capers and golden sultanas.',
    [['Rice', 1.5], ['Cauliflower', 0.5], ['Capers', 0.05], ['Sultanas', 0.1], ['Parmesan Cheese', 0.2]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Red Kidney Beans in Coconut Sauce', 'main_course',
    'Red kidney beans in mildly spiced coconut sauce with steamed rice and kachumbari salad.',
    [['Red Kidney Beans', 1.0], ['Coconut Milk', 0.5, 'ltr'], ['Rice', 1.5], ['Cherry Tomatoes', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Vegetable Lasagne with Salad', 'main_course',
    'Layers of lasagne, mixed vegetables, and creamy tomato sauce, baked golden and served with salad.',
    [['Lasagne Sheets', 0.8], ['Spinach', 0.4], ['Mushrooms', 0.3], ['Heavy Cream', 0.3, 'ltr'], ['Mozzarella Cheese', 0.4], ['Lettuce', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Pasta Alfredo with Garlic Toast', 'main_course',
    'Creamy Alfredo pasta with a rich cheesy sauce and warm garlic toast.',
    [['Penne Pasta', 1.5], ['Heavy Cream', 0.4, 'ltr'], ['Parmesan Cheese', 0.3], ['Bread', 10, 'pcs']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Veg Moussaka', 'main_course',
    'Layered bake of grilled eggplant, zucchini, and spiced lentils, topped with creamy bechamel.',
    [['Aubergine', 1.0], ['Zucchini', 0.5], ['Lentils', 0.4], ['Heavy Cream', 0.3, 'ltr']],
    $recipesAdded, $recipesSkipped);

// --- Desserts (Dinner) ---
addRecipeWithIngredients($db, 'Invisible Apple Cake', 'dessert',
    'Unique apple cake with delicate layers of thinly sliced apples, with strawberry coulis.',
    [['Apple', 1.5], ['Eggs', 4, 'pcs'], ['Strawberries', 0.3]],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Chocolate Brownies', 'dessert',
    'Decadent chocolate dessert served with homemade ice cream.',
    [['Chocolate', 0.4], ['Eggs', 4, 'pcs']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Malva Pudding', 'dessert',
    'Traditional moist sponge pudding soaked in sweet syrup, served warm.',
    [['Eggs', 4, 'pcs'], ['Milk', 0.2, 'ltr'], ['Heavy Cream', 0.3, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Apple Crumble with Custard Sauce', 'dessert',
    'Warm spiced apples topped with crumbly streusel, served with custard sauce.',
    [['Apple', 1.5], ['Milk', 0.3, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Passion and Cheddar Cheese Tart', 'dessert',
    'Rich tart made with tangy passion fruit and sharp cheddar cheese.',
    [['Passion Fruit', 0.4], ['Cheddar Cheese', 0.3], ['Pastry Sheets', 0.4], ['Eggs', 4, 'pcs'], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Sticky Toffee Pudding', 'dessert',
    'Moist date-filled sponge cake soaked in rich buttery toffee sauce, with vanilla ice cream.',
    [['Dates', 0.4], ['Eggs', 4, 'pcs'], ['Heavy Cream', 0.3, 'ltr']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Pineapple Upside-Down Cake', 'dessert',
    'Moist and buttery cake topped with caramelized pineapple slices.',
    [['Pineapple', 1.0], ['Eggs', 4, 'pcs']],
    $recipesAdded, $recipesSkipped);

addRecipeWithIngredients($db, 'Lemon Cheesecake', 'dessert',
    'Smooth and tangy cheesecake with buttery biscuit base and zesty lemon glaze.',
    [['Cream Cheese', 0.6], ['Lemon', 0.4], ['Eggs', 4, 'pcs'], ['Heavy Cream', 0.2, 'ltr']],
    $recipesAdded, $recipesSkipped);

// ══════════════════════════════════════════
// STEP 3: Clean up old low-cost items from DB
// Remove previously seeded spices/sauces/herbs
// ══════════════════════════════════════════

$lowCostItems = [
    'Salt', 'Black Pepper', 'Garlic', 'Ginger', 'Onions', 'Basil (Fresh)',
    'Paprika', 'Turmeric', 'Cumin', 'Coriander', 'Thyme', 'Rosemary', 'Sage',
    'Cajun Seasoning', 'Curry Paste (Green)', 'Olive Oil', 'Vegetable Oil',
    'Vinegar', 'Balsamic Vinegar', 'Soy Sauce', 'Sweet Chili Sauce',
    'Tartar Sauce', 'Pepper Sauce', 'Pesto (Basil)', 'Honey', 'Mustard',
    'Sugar', 'Brown Sugar', 'Vanilla Extract', 'Baking Powder', 'Cornstarch',
    'Cocoa Powder', 'Gelatin', 'Tomato Paste', 'Flour', 'Breadcrumbs',
    'Croutons', 'Butter', 'Lime',
];

$itemsCleaned = 0;
$ingCleaned = 0;
foreach ($lowCostItems as $itemName) {
    // Delete recipe_ingredients referencing this item name
    $delIng = $db->prepare('DELETE FROM recipe_ingredients WHERE item_name = ?');
    $delIng->execute([$itemName]);
    $ingCleaned += $delIng->rowCount();

    // Delete the item itself (only if not used in dish_ingredients)
    $checkDish = $db->prepare('SELECT COUNT(*) FROM dish_ingredients WHERE item_name = ?');
    $checkDish->execute([$itemName]);
    if ($checkDish->fetchColumn() == 0) {
        $delItem = $db->prepare('DELETE FROM items WHERE name = ?');
        $delItem->execute([$itemName]);
        $itemsCleaned += $delItem->rowCount();
    }
}

// ══════════════════════════════════════════
// OUTPUT RESULTS
// ══════════════════════════════════════════
?>
<!DOCTYPE html>
<html><head><title>Menu Seed Results</title>
<link rel="stylesheet" href="/assets/tailwind.min.css"></head>
<body class="bg-gray-50 p-8 font-sans">
<div class="max-w-lg mx-auto bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-xl font-bold text-gray-800">Menu Seed Complete</h2>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-800 mb-2">Items</h3>
        <p class="text-sm text-blue-700">Added: <strong><?= $itemsAdded ?></strong></p>
        <p class="text-sm text-blue-700">Skipped (existing): <strong><?= $itemsSkipped ?></strong></p>
    </div>

    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <h3 class="font-semibold text-green-800 mb-2">Recipes (with ingredients)</h3>
        <p class="text-sm text-green-700">Added: <strong><?= $recipesAdded ?></strong></p>
        <p class="text-sm text-green-700">Skipped (existing): <strong><?= $recipesSkipped ?></strong></p>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <h3 class="font-semibold text-amber-800 mb-2">Cleanup (low-cost items removed)</h3>
        <p class="text-sm text-amber-700">Items deleted: <strong><?= $itemsCleaned ?></strong></p>
        <p class="text-sm text-amber-700">Ingredient references deleted: <strong><?= $ingCleaned ?></strong></p>
    </div>

    <a href="app.php?page=recipes" class="block text-center py-3 bg-orange-500 text-white font-semibold rounded-xl hover:bg-orange-600 transition">
        Go to Recipes &rarr;
    </a>
</div>
</body></html>
