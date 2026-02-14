<?php
/**
 * Seed the weekly_menu table from the latest CSV menu data.
 * Maps each day-of-week + meal to recipe IDs from the recipes table.
 * Run once: https://your-domain.com/seed-weekly-menu.php
 */
require_once __DIR__ . '/config.php';
$db = getDB();

// Ensure weekly_menu table exists
$db->exec("CREATE TABLE IF NOT EXISTS weekly_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday,1=Monday...6=Saturday',
    meal ENUM('lunch','dinner') NOT NULL,
    recipe_id INT NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE
)");

// Clear existing weekly menu
$db->exec("DELETE FROM weekly_menu");

// Build recipe name → id lookup
$allRecipes = $db->query('SELECT id, name FROM recipes')->fetchAll();
$recipeLookup = [];
foreach ($allRecipes as $r) {
    $recipeLookup[strtolower(trim($r['name']))] = $r['id'];
}

function findRecipeId($name) {
    global $recipeLookup;
    $name = strtolower(trim($name));
    // Exact match
    if (isset($recipeLookup[$name])) return $recipeLookup[$name];
    // Partial match
    foreach ($recipeLookup as $key => $id) {
        if (strpos($key, $name) !== false || strpos($name, $key) !== false) return $id;
    }
    return null;
}

// Day mapping: CSV columns are Mon(1), Tue(2), Wed(3), Thu(4), Fri(5), Sat(6), Sun(0)
// PHP date('w'): 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
$csvToDayOfWeek = [1, 2, 3, 4, 5, 6, 0]; // index 0=Mon=1, index 1=Tue=2, ..., index 6=Sun=0

// ══════════════════════════════════════════
// LUNCH MENU (from CSV)
// ══════════════════════════════════════════

$lunchMenu = [
    // [recipe_name_per_day: Mon, Tue, Wed, Thu, Fri, Sat, Sun]
    // Appetizers
    ['Vegetable Samosa with Sweet Chili Sauce', 'Avocado Vinaigrette', 'Camembert and Caramelized Onion Bruschetta', 'Tomato, Avocado, and Mango Bruschetta', 'Camembert and Caramelized Onion Bruschetta', 'Avocado Vinaigrette', 'Camembert and Caramelized Onion Bruschetta'],
    // Soups
    ['Carrot and Celery Soup', 'Leek and Potato Soup', 'Tomato Basil Soup with Croutons', 'Roasted Butternut Squash Soup', 'Carrot and Celery Soup', 'Leek and Potato Soup', 'Tomato Basil Soup with Croutons'],
    // Main 1
    ['Kilimanjaro Beer-Battered Tilapia with Chips', 'Stuffed Chicken', 'Fish Nile Perch Paprika', 'Pizza of Your Choice', 'Kilimanjaro Beer-Battered Tilapia with Chips', 'Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Fish Nile Perch Paprika'],
    // Main 2
    ['Grilled Pork Chops with Sweet and Sour Sauce', 'Spaghetti Bolognese with Garlic Toast', 'Beef Stroganoff', 'Chicken Satay with Cajun Potato Wedges and Garden Salad', 'Grilled Pork Chops with Sweet and Sour Sauce', 'Spaghetti Bolognese with Garlic Toast', 'Beef Stroganoff'],
    // Main 3 (veg)
    ['Vegetable Quiche with Mixed Salad', 'Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa', 'Butternut Squash Ravioli', 'Thai Green Vegetable Curry', 'Vegetable Quiche with Mixed Salad', 'Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa', 'Butternut Squash Ravioli'],
    // Main 4 (veg)
    ['Pasta with Tomato Pesto Sauce', 'Vegetable Cannelloni with Salad', 'Vegetable Wraps', 'Veg Burger', 'Pasta with Tomato Pesto Sauce', 'Vegetable Cannelloni with Salad', 'Vegetable Wraps'],
    // Dessert 1
    ['Fresh Fruit Salad', 'Mango Sorbet', 'Berry Panna Cotta', 'Lemon Meringue Pie', 'Fresh Fruit Salad', 'Mango Sorbet', 'Berry Panna Cotta'],
    // Dessert 2
    ['Chocolate Mousse', 'Coconut Cream Caramel', 'Banana Fritters with Custard Sauce', 'Chocolate Fudge Cake', 'Chocolate Mousse', 'Coconut Cream Caramel', 'Banana Fritters with Custard Sauce'],
];

// ══════════════════════════════════════════
// DINNER MENU (from CSV)
// ══════════════════════════════════════════

$dinnerMenu = [
    // Appetizers
    ['Vegetable Spring Rolls', 'Caprese Salad with Basil Pesto', 'Curried Sweet Potato Samosas with Tomato Salsa', 'Sliced Beetroot with Orange Segments and Feta Cheese', 'Vegetable Spring Rolls', 'Caprese Salad with Basil Pesto', 'Curried Sweet Potato Samosas with Tomato Salsa'],
    // Soups
    ['Cream of Broccoli Soup', 'Pumpkin Soup', 'Baby Marrow Soup', 'Mixed Vegetable Soup', 'Cream of Broccoli Soup', 'Pumpkin Soup', 'Baby Marrow Soup'],
    // Main 1
    ['Braised Lamb Chops', 'Grilled Beef Fillet', 'Grilled Pork Chop with Rice and Honey Mustard Sauce', 'Tilapia Fish Fillet', 'Braised Lamb Chops', 'Grilled Beef Fillet', 'Grilled Pork Chop with Rice and Honey Mustard Sauce'],
    // Main 2
    ['Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Pan-Fried Nile Perch Fillet', 'One-Pot Garlic Chicken with Tagliatelle Pasta', 'Beef, Carrot and Potato Stew', 'Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Pan-Fried Nile Perch Fillet', 'One-Pot Garlic Chicken with Tagliatelle Pasta'],
    // Main 3 (veg)
    ['Vegetarian Spaghetti Bolognaise', 'Stir-Fried Vegetables with Noodles or Rice', 'Vegetable Ratatouille', 'Vegetable Risotto', 'Vegetarian Spaghetti Bolognaise', 'Stir-Fried Vegetables with Noodles or Rice', 'Vegetable Ratatouille'],
    // Main 4 (veg)
    ['Red Kidney Beans in Coconut Sauce', 'Vegetable Lasagne with Salad', 'Pasta Alfredo with Garlic Toast', 'Veg Moussaka', 'Red Kidney Beans in Coconut Sauce', 'Vegetable Lasagne with Salad', 'Pasta Alfredo with Garlic Toast'],
    // Dessert 1
    ['Invisible Apple Cake', 'Chocolate Brownies', 'Malva Pudding', 'Apple Crumble with Custard Sauce', 'Invisible Apple Cake', 'Chocolate Brownies', 'Malva Pudding'],
    // Dessert 2
    ['Passion and Cheddar Cheese Tart', 'Sticky Toffee Pudding', 'Pineapple Upside-Down Cake', 'Lemon Cheesecake', 'Passion and Cheddar Cheese Tart', 'Sticky Toffee Pudding', 'Pineapple Upside-Down Cake'],
];

$inserted = 0;
$notFound = [];

$stmt = $db->prepare('INSERT INTO weekly_menu (day_of_week, meal, recipe_id, sort_order) VALUES (?, ?, ?, ?)');

// Insert Lunch
foreach ($lunchMenu as $sortOrder => $dishes) {
    foreach ($dishes as $dayIdx => $recipeName) {
        $dayOfWeek = $csvToDayOfWeek[$dayIdx];
        $recipeId = findRecipeId($recipeName);
        if ($recipeId) {
            $stmt->execute([$dayOfWeek, 'lunch', $recipeId, $sortOrder]);
            $inserted++;
        } else {
            $notFound[] = "LUNCH $recipeName (day $dayOfWeek)";
        }
    }
}

// Insert Dinner
foreach ($dinnerMenu as $sortOrder => $dishes) {
    foreach ($dishes as $dayIdx => $recipeName) {
        $dayOfWeek = $csvToDayOfWeek[$dayIdx];
        $recipeId = findRecipeId($recipeName);
        if ($recipeId) {
            $stmt->execute([$dayOfWeek, 'dinner', $recipeId, $sortOrder]);
            $inserted++;
        } else {
            $notFound[] = "DINNER $recipeName (day $dayOfWeek)";
        }
    }
}

// Verify counts per day
$verify = $db->query('SELECT day_of_week, meal, COUNT(*) as cnt FROM weekly_menu GROUP BY day_of_week, meal ORDER BY day_of_week, meal')->fetchAll();

?>
<!DOCTYPE html>
<html><head><title>Weekly Menu Seed</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-50 p-8 font-sans">
<div class="max-w-lg mx-auto bg-white rounded-xl shadow p-6 space-y-4">
    <h2 class="text-xl font-bold text-gray-800">Weekly Menu Seed Complete</h2>

    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <h3 class="font-semibold text-green-800 mb-2">Results</h3>
        <p class="text-sm text-green-700">Entries inserted: <strong><?= $inserted ?></strong></p>
    </div>

    <?php if (!empty($notFound)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <h3 class="font-semibold text-red-800 mb-2">Not Found (<?= count($notFound) ?>)</h3>
        <?php foreach ($notFound as $nf): ?>
            <p class="text-xs text-red-600"><?= htmlspecialchars($nf) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h3 class="font-semibold text-blue-800 mb-2">Verification (dishes per day/meal)</h3>
        <?php
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        foreach ($verify as $v):
        ?>
            <p class="text-xs text-blue-700"><?= $dayNames[$v['day_of_week']] ?> <?= ucfirst($v['meal']) ?>: <strong><?= $v['cnt'] ?> dishes</strong></p>
        <?php endforeach; ?>
    </div>

    <a href="app.php?page=menu-plan" class="block text-center py-3 bg-orange-500 text-white font-semibold rounded-xl hover:bg-orange-600 transition">
        Go to Menu Plan &rarr;
    </a>
</div>
</body></html>
