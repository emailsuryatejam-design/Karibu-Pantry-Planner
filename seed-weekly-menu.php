<?php
/**
 * Seed the weekly_menu table from the latest CSV menu data.
 * Maps each day-of-week + meal to recipe IDs from the recipes table.
 * Run once: https://your-domain.com/seed-weekly-menu.php
 */
require_once __DIR__ . '/config.php';
$db = getDB();

// Drop and recreate weekly_menu table (no FK for compatibility)
$db->exec("DROP TABLE IF EXISTS weekly_menu");
$db->exec("CREATE TABLE weekly_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,
    meal ENUM('lunch','dinner') NOT NULL,
    recipe_id INT NOT NULL,
    sort_order INT DEFAULT 0
)");

// Build recipe name → id lookup
$allRecipes = $db->query('SELECT id, name FROM recipes')->fetchAll();
$recipeLookup = [];
foreach ($allRecipes as $r) {
    $recipeLookup[strtolower(trim($r['name']))] = (int)$r['id'];
}

function findRecipeId($name) {
    global $recipeLookup;
    $name = strtolower(trim($name));
    if (isset($recipeLookup[$name])) return $recipeLookup[$name];
    foreach ($recipeLookup as $key => $id) {
        if (strpos($key, $name) !== false || strpos($name, $key) !== false) return $id;
    }
    return null;
}

// Day mapping: CSV columns Mon-Sun → PHP day_of_week (0=Sun,1=Mon,...,6=Sat)
$csvToDayOfWeek = [1, 2, 3, 4, 5, 6, 0];

// ── LUNCH MENU ──
$lunchMenu = [
    ['Vegetable Samosa with Sweet Chili Sauce', 'Avocado Vinaigrette', 'Camembert and Caramelized Onion Bruschetta', 'Tomato, Avocado, and Mango Bruschetta', 'Camembert and Caramelized Onion Bruschetta', 'Avocado Vinaigrette', 'Camembert and Caramelized Onion Bruschetta'],
    ['Carrot and Celery Soup', 'Leek and Potato Soup', 'Tomato Basil Soup with Croutons', 'Roasted Butternut Squash Soup', 'Carrot and Celery Soup', 'Leek and Potato Soup', 'Tomato Basil Soup with Croutons'],
    ['Kilimanjaro Beer-Battered Tilapia with Chips', 'Stuffed Chicken', 'Fish Nile Perch Paprika', 'Pizza of Your Choice', 'Kilimanjaro Beer-Battered Tilapia with Chips', 'Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Fish Nile Perch Paprika'],
    ['Grilled Pork Chops with Sweet and Sour Sauce', 'Spaghetti Bolognese with Garlic Toast', 'Beef Stroganoff', 'Chicken Satay with Cajun Potato Wedges and Garden Salad', 'Grilled Pork Chops with Sweet and Sour Sauce', 'Spaghetti Bolognese with Garlic Toast', 'Beef Stroganoff'],
    ['Vegetable Quiche with Mixed Salad', 'Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa', 'Butternut Squash Ravioli', 'Thai Green Vegetable Curry', 'Vegetable Quiche with Mixed Salad', 'Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa', 'Butternut Squash Ravioli'],
    ['Pasta with Tomato Pesto Sauce', 'Vegetable Cannelloni with Salad', 'Vegetable Wraps', 'Veg Burger', 'Pasta with Tomato Pesto Sauce', 'Vegetable Cannelloni with Salad', 'Vegetable Wraps'],
    ['Fresh Fruit Salad', 'Mango Sorbet', 'Berry Panna Cotta', 'Lemon Meringue Pie', 'Fresh Fruit Salad', 'Mango Sorbet', 'Berry Panna Cotta'],
    ['Chocolate Mousse', 'Coconut Cream Caramel', 'Banana Fritters with Custard Sauce', 'Chocolate Fudge Cake', 'Chocolate Mousse', 'Coconut Cream Caramel', 'Banana Fritters with Custard Sauce'],
];

// ── DINNER MENU ──
$dinnerMenu = [
    ['Vegetable Spring Rolls', 'Caprese Salad with Basil Pesto', 'Curried Sweet Potato Samosas with Tomato Salsa', 'Sliced Beetroot with Orange Segments and Feta Cheese', 'Vegetable Spring Rolls', 'Caprese Salad with Basil Pesto', 'Curried Sweet Potato Samosas with Tomato Salsa'],
    ['Cream of Broccoli Soup', 'Pumpkin Soup', 'Baby Marrow Soup', 'Mixed Vegetable Soup', 'Cream of Broccoli Soup', 'Pumpkin Soup', 'Baby Marrow Soup'],
    ['Braised Lamb Chops', 'Grilled Beef Fillet', 'Grilled Pork Chop with Rice and Honey Mustard Sauce', 'Tilapia Fish Fillet', 'Braised Lamb Chops', 'Grilled Beef Fillet', 'Grilled Pork Chop with Rice and Honey Mustard Sauce'],
    ['Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Pan-Fried Nile Perch Fillet', 'One-Pot Garlic Chicken with Tagliatelle Pasta', 'Beef, Carrot and Potato Stew', 'Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Pan-Fried Nile Perch Fillet', 'One-Pot Garlic Chicken with Tagliatelle Pasta'],
    ['Vegetarian Spaghetti Bolognaise', 'Stir-Fried Vegetables with Noodles or Rice', 'Vegetable Ratatouille', 'Vegetable Risotto', 'Vegetarian Spaghetti Bolognaise', 'Stir-Fried Vegetables with Noodles or Rice', 'Vegetable Ratatouille'],
    ['Red Kidney Beans in Coconut Sauce', 'Vegetable Lasagne with Salad', 'Pasta Alfredo with Garlic Toast', 'Veg Moussaka', 'Red Kidney Beans in Coconut Sauce', 'Vegetable Lasagne with Salad', 'Pasta Alfredo with Garlic Toast'],
    ['Invisible Apple Cake', 'Chocolate Brownies', 'Malva Pudding', 'Apple Crumble with Custard Sauce', 'Invisible Apple Cake', 'Chocolate Brownies', 'Malva Pudding'],
    ['Passion and Cheddar Cheese Tart', 'Sticky Toffee Pudding', 'Pineapple Upside-Down Cake', 'Lemon Cheesecake', 'Passion and Cheddar Cheese Tart', 'Sticky Toffee Pudding', 'Pineapple Upside-Down Cake'],
];

$inserted = 0;
$notFound = [];

// Insert Lunch
foreach ($lunchMenu as $sortOrder => $dishes) {
    foreach ($dishes as $dayIdx => $recipeName) {
        $dayOfWeek = $csvToDayOfWeek[$dayIdx];
        $recipeId = findRecipeId($recipeName);
        if ($recipeId) {
            $db->prepare('INSERT INTO weekly_menu (day_of_week, meal, recipe_id, sort_order) VALUES (?, ?, ?, ?)')->execute([$dayOfWeek, 'lunch', $recipeId, $sortOrder]);
            $inserted++;
        } else {
            $notFound[] = "LUNCH: $recipeName (day $dayOfWeek)";
        }
    }
}

// Insert Dinner
foreach ($dinnerMenu as $sortOrder => $dishes) {
    foreach ($dishes as $dayIdx => $recipeName) {
        $dayOfWeek = $csvToDayOfWeek[$dayIdx];
        $recipeId = findRecipeId($recipeName);
        if ($recipeId) {
            $db->prepare('INSERT INTO weekly_menu (day_of_week, meal, recipe_id, sort_order) VALUES (?, ?, ?, ?)')->execute([$dayOfWeek, 'dinner', $recipeId, $sortOrder]);
            $inserted++;
        } else {
            $notFound[] = "DINNER: $recipeName (day $dayOfWeek)";
        }
    }
}

// Verify
$verify = $db->query('SELECT day_of_week, meal, COUNT(*) as cnt FROM weekly_menu GROUP BY day_of_week, meal ORDER BY day_of_week, meal')->fetchAll();

$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html><head><title>Weekly Menu Seed</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 p-8 font-sans">
<div class="max-w-lg mx-auto bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-xl font-bold text-gray-800">Weekly Menu Seed</h2>
    <p class="text-xs text-gray-500">Recipes found: <?= count($recipeLookup) ?></p>

    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <p class="text-lg font-bold text-green-800">✓ <?= $inserted ?> entries inserted</p>
    </div>

    <?php if (!empty($notFound)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
        <h3 class="font-semibold text-amber-800 mb-2">Not Found (<?= count($notFound) ?>)</h3>
        <?php foreach ($notFound as $nf): ?>
            <p class="text-xs text-amber-600"><?= htmlspecialchars($nf) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-800 mb-2">Per Day/Meal</h3>
        <?php foreach ($verify as $v): ?>
            <p class="text-xs text-blue-700"><?= $dayNames[$v['day_of_week']] ?> <?= ucfirst($v['meal']) ?>: <strong><?= $v['cnt'] ?></strong></p>
        <?php endforeach; ?>
    </div>

    <a href="app.php?page=menu-plan" class="block text-center py-3 bg-orange-500 text-white font-semibold rounded-xl hover:bg-orange-600 transition">Go to Menu Plan →</a>
</div>
</body></html>
