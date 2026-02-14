<?php
/**
 * Seed weekly menus as recipes into the database
 * Run once: https://your-domain.com/seed-menus.php
 */
require_once __DIR__ . '/config.php';
$db = getDB();

$added = 0;
$skipped = 0;

function addRecipe($db, $name, $category, $description, &$added, &$skipped) {
    $name = trim($name);
    if (!$name) return;

    // Check duplicate
    $check = $db->prepare('SELECT id FROM recipes WHERE name = ?');
    $check->execute([$name]);
    if ($check->fetch()) {
        $skipped++;
        return;
    }

    $courseMap = [
        'appetizer' => 'appetizer',
        'soup' => 'soup',
        'main_course' => 'main_course',
        'dessert' => 'dessert',
    ];

    $stmt = $db->prepare('INSERT INTO recipes (name, category, cuisine, difficulty, prep_time, cook_time, servings, instructions, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $name,
        $category,
        'International',
        'medium',
        20,
        30,
        20,
        trim($description) ?: null,
        null,
    ]);
    $added++;
}

// ══════════════════════════════
// LUNCH MENU
// ══════════════════════════════

// Appetizers
$lunchAppetizers = [
    ['Vegetable Samosa with Sweet Chili Sauce', 'Crispy golden pastry stuffed with a flavorful mix of spiced vegetables, served with a tangy and sweet chili dipping sauce.'],
    ['Avocado Vinaigrette', 'Fresh avocado halves drizzled with a tangy vinaigrette dressing, offering a creamy and refreshing start to your meal.'],
    ['Camembert and Caramelized Onion Bruschetta', 'Crisp toasted bread topped with creamy Camembert cheese and sweet caramelized onions, finished with a drizzle of tangy balsamic glaze.'],
    ['Tomato, Avocado, and Mango Bruschetta', 'Crisp toasted bread topped with a refreshing combination of diced tomato, creamy avocado, and sweet mango, finished with a light vinaigrette.'],
];

// Soups
$lunchSoups = [
    ['Carrot and Celery Soup', 'A smooth and flavorful soup crafted from fresh carrots and celery, gently simmered with aromatic spices for a warm, light start.'],
    ['Leek and Potato Soup', 'A hearty, creamy soup made with fresh leeks and potatoes, gently seasoned to bring out its comforting flavors.'],
    ['Tomato Basil Soup with Croutons', 'A smooth and savory soup made with ripe tomatoes and fresh basil, served with golden, crunchy croutons for added texture.'],
    ['Roasted Butternut Squash Soup', 'A velvety and mildly sweet soup made from roasted butternut squash, blended with spices for a rich, comforting start to your meal.'],
];

// Main Courses
$lunchMains = [
    ['Kilimanjaro Beer-Battered Tilapia with Chips', 'Fresh tilapia fillet coated in a light beer batter and fried to golden perfection, served with crispy chips and a zesty tartar sauce.'],
    ['Stuffed Chicken', 'Juicy chicken breast stuffed with mushrooms, spinach, and cheese, and paired with turmeric rice and glazed vegetables.'],
    ['Fish Nile Perch Paprika', 'Nile perch fillet gently seasoned with paprika and finished in the pan. Served with your choice of rice or chips.'],
    ['Pizza of Your Choice', 'A fusion pizza topped with tender chicken tikka pieces and melted cheese, served alongside a vibrant mango Thai salad.'],
    ['Grilled Breast Chicken with Lyonnaise Potatoes and Salad', 'Juicy grilled chicken breast served with crispy lyonnaise potatoes and a refreshing side salad for a wholesome meal.'],
    ['Grilled Pork Chops with Sweet and Sour Sauce', 'Tender pork chops grilled to perfection and glazed with a rich sweet-and-sour sauce, served with seasonal vegetables.'],
    ['Spaghetti Bolognese with Garlic Toast', 'Classic spaghetti in a rich and savory meat sauce, served with a side of buttery garlic toast.'],
    ['Beef Stroganoff', 'Tender strips of beef simmered in a creamy mushroom sauce, finished with a touch of mustard. Served with rice or mashed potatoes.'],
    ['Chicken Satay with Cajun Potato Wedges and Garden Salad', 'Tender, marinated chicken skewers grilled to perfection, served with crispy Cajun-seasoned potato wedges and a fresh garden salad.'],
    ['Vegetable Quiche with Mixed Salad', 'A flaky pastry filled with a creamy egg mixture and seasonal vegetables, paired with a fresh mixed salad in a light vinaigrette.'],
    ['Stuffed Aubergine Rolls with Salad and Sweet Corn Salsa', 'Baked aubergine rolls filled with a flavorful mix of vegetables and cheese, accompanied by a fresh salad and sweet corn salsa.'],
    ['Butternut Squash Ravioli', 'Delicate pasta parcels filled with roasted butternut squash, served in a light butter sage sauce.'],
    ['Thai Green Vegetable Curry', 'Made with seasonal vegetables, fresh herbs, and served with rice.'],
    ['Pasta with Tomato Pesto Sauce', 'Penne pasta tossed in a flavorful tomato pesto sauce, served with warm garlic toast for a hearty, satisfying option.'],
    ['Vegetable Cannelloni with Salad', 'Rolled pasta stuffed with a flavorful vegetable filling, baked in a white sauce, and paired with a crispy garden salad.'],
    ['Vegetable Wraps', 'Grilled seasonal vegetables wrapped in soft flatbread, served with a light yogurt dip or dressing on the side.'],
    ['Veg Burger', 'A hearty vegetable patty layered with fresh lettuce, tomato, and house-made sauce, served in a toasted bun with a side of chips.'],
];

// Desserts
$lunchDesserts = [
    ['Fresh Fruit Salad', 'A vibrant selection of seasonal fruits, freshly prepared to offer a naturally sweet and refreshing end to the meal.'],
    ['Mango Sorbet', 'A refreshing sorbet made from ripe, juicy mangoes, offering a sweet and tropical finish to the meal.'],
    ['Berry Panna Cotta', 'A smooth and creamy Italian dessert infused with fresh berries, offering a delightful balance of sweetness and tanginess.'],
    ['Lemon Meringue Pie', 'A zesty lemon custard topped with fluffy, golden meringue, served on a buttery crust for a perfectly tangy-sweet dessert.'],
    ['Chocolate Mousse', 'A rich and creamy mousse made with fine chocolate, offering a decadent dessert topped with a touch of cocoa or fresh berries.'],
    ['Coconut Cream Caramel', 'A luscious and smooth caramel custard infused with coconut flavor, topped with a light caramel glaze.'],
    ['Banana Fritters with Custard Sauce', 'Sweet banana fritters fried to a golden crisp, served with a rich and creamy custard sauce for a delightful finish.'],
    ['Chocolate Fudge Cake', 'A moist and indulgent chocolate cake layered with rich fudge, perfect for chocolate lovers.'],
];

// ══════════════════════════════
// DINNER MENU
// ══════════════════════════════

$dinnerAppetizers = [
    ['Vegetable Spring Rolls', 'Crispy golden spring rolls filled with a mix of fresh vegetables, served with a trio of flavorful dips: peanut butter, coconut milk, and sweet chili sauce, accompanied by a refreshing herb salad with lemon dressing.'],
    ['Caprese Salad with Basil Pesto', 'A refreshing salad made with layers of ripe tomatoes, creamy mozzarella, and fresh basil leaves, drizzled with aromatic basil pesto for a classic Italian flavor.'],
    ['Curried Sweet Potato Samosas with Tomato Salsa', 'Golden and crispy samosas filled with spiced sweet potato, paired with a zesty tomato salsa for a flavorful and vibrant start to your meal.'],
    ['Sliced Beetroot with Orange Segments and Feta Cheese', 'Thinly sliced fresh beetroot paired with juicy orange segments and crumbled feta cheese, dressed with a honey and wholegrain mustard vinaigrette for a vibrant and tangy start.'],
];

$dinnerSoups = [
    ['Cream of Broccoli Soup', 'A smooth and creamy soup made from fresh broccoli, gently simmered with herbs and finished with a touch of cream for a comforting start.'],
    ['Pumpkin Soup', 'A rich and creamy soup made from roasted pumpkin, blended with warm spices and a touch of cream for a velvety, comforting start.'],
    ['Baby Marrow Soup', 'A light and refreshing soup made with cucumber and baby marrow, blended with herbs for a delicate and soothing appetizer.'],
    ['Mixed Vegetable Soup', 'A wholesome soup made with a medley of fresh seasonal vegetables, gently simmered with herbs for a warm and comforting start.'],
];

$dinnerMains = [
    ['Braised Lamb Chops', 'Slow-cooked Lamb chunk to perfection, served with creamy mashed potatoes, glazed carrots, and green beans for a rich and hearty meal.'],
    ['Grilled Beef Fillet', 'Succulent beef fillet grilled to perfection, served with roast potatoes, cherry tomatoes, green beans, and a robust pepper sauce.'],
    ['Grilled Pork Chop with Rice and Honey Mustard Sauce', 'Juicy, grilled pork chop served with steamed rice, seasonal vegetables, and a tangy honey mustard sauce for a perfect balance of flavors.'],
    ['Tilapia Fish Fillet', 'Tender tilapia fish fillet, lightly seasoned and pan-seared to perfection for a mild, delicate flavor. Ideal with rice, chips, or fresh vegetables.'],
    ['Pan-Fried Nile Perch Fillet', 'Fresh Nile perch fillet lightly pan-fried and served with steamed rice, sauteed vegetables, and a creamy coconut tartar sauce.'],
    ['One-Pot Garlic Chicken with Tagliatelle Pasta', 'A creamy one-pot dish featuring tender garlic-seasoned chicken and al dente tagliatelle pasta.'],
    ['Beef, Carrot and Potato Stew', 'A hearty and flavorful beef stew slow-cooked with paprika, onions, spices and merlot flavor served with rice and seasonal vegetables.'],
    ['Vegetarian Spaghetti Bolognaise', 'A flavorful vegetarian spaghetti served with garden salad for a delicious vegetarian option.'],
    ['Stir-Fried Vegetables with Noodles or Rice', 'Light soy-based sauce with broccoli, cabbage, and carrots.'],
    ['Vegetable Ratatouille', 'A classic French vegetable stew made with eggplant, zucchini, bell peppers, and tomatoes, served with fluffy steamed rice.'],
    ['Vegetable Risotto', 'A rich and creamy Magugu rice served with cauliflower, capers and golden sultanas.'],
    ['Red Kidney Beans in Coconut Sauce', 'Red kidney beans cooked in a mildly spiced coconut sauce, served with steamed rice and a fresh kachumbari salad.'],
    ['Vegetable Lasagne with Salad', 'Layers of lasagne sheet, mixed vegetables, and a creamy tomato-based sauce, baked to golden perfection and served with a fresh garden salad.'],
    ['Pasta Alfredo with Garlic Toast', 'Creamy Alfredo pasta made with a rich and cheesy sauce, served with warm, buttery garlic toast.'],
    ['Veg Moussaka', 'A layered bake of grilled eggplant, zucchini, and spiced lentils, topped with creamy bechamel and oven-finished to a golden crust.'],
];

$dinnerDesserts = [
    ['Invisible Apple Cake', 'A unique apple cake with delicate layers of thinly sliced apples, served with a tangy strawberry coulis for a light yet indulgent treat.'],
    ['Chocolate Brownies', 'A decadent chocolate dessert served with homemade ice cream.'],
    ['Malva Pudding', 'A traditional South African dessert, this moist sponge pudding is soaked in a sweet syrup and served warm.'],
    ['Apple Crumble with Custard Sauce', 'A comforting dessert featuring warm, spiced apples topped with a crumbly streusel, served with a creamy custard sauce.'],
    ['Passion and Cheddar Cheese Tart', 'A rich and creamy tart made with tangy passion fruit and sharp cheddar cheese, offering a balance of sweet and savory flavors.'],
    ['Sticky Toffee Pudding', 'A moist, date-filled sponge cake soaked in a rich, buttery toffee sauce. Served warm, often topped with extra toffee sauce and paired with vanilla ice cream.'],
    ['Pineapple Upside-Down Cake', 'A moist and buttery cake topped with caramelized pineapple slices, offering a sweet and tropical finish to the meal.'],
    ['Lemon Cheesecake', 'A smooth and tangy cheesecake with a buttery biscuit base, topped with a zesty lemon glaze for a refreshing end to your meal.'],
];

// Insert all
foreach ($lunchAppetizers as $r) addRecipe($db, $r[0], 'appetizer', $r[1], $added, $skipped);
foreach ($lunchSoups as $r) addRecipe($db, $r[0], 'soup', $r[1], $added, $skipped);
foreach ($lunchMains as $r) addRecipe($db, $r[0], 'main_course', $r[1], $added, $skipped);
foreach ($lunchDesserts as $r) addRecipe($db, $r[0], 'dessert', $r[1], $added, $skipped);

foreach ($dinnerAppetizers as $r) addRecipe($db, $r[0], 'appetizer', $r[1], $added, $skipped);
foreach ($dinnerSoups as $r) addRecipe($db, $r[0], 'soup', $r[1], $added, $skipped);
foreach ($dinnerMains as $r) addRecipe($db, $r[0], 'main_course', $r[1], $added, $skipped);
foreach ($dinnerDesserts as $r) addRecipe($db, $r[0], 'dessert', $r[1], $added, $skipped);

echo "<h2>Menu Seed Complete</h2>";
echo "<p><strong>Added:</strong> $added recipes</p>";
echo "<p><strong>Skipped (duplicates):</strong> $skipped</p>";
echo "<p><a href='app.php?page=recipes'>Go to Recipes →</a></p>";
