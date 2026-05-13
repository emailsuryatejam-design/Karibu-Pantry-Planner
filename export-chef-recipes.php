<?php
/**
 * export-chef-recipes.php
 * Admin-only Excel export: all recipes for a given chef, with ingredients
 * and whether each ingredient is ticked to be ordered from the store (is_primary=1).
 *
 * Usage:  /export-chef-recipes.php?chef=vinay
 *         /export-chef-recipes.php?chef_id=5
 */

require_once __DIR__ . '/config.php';

// ── Guard: only admin / storekeeper ────────────────────────────────────────
if (!isLoggedIn()) {
    http_response_code(401);
    exit('Please log in.');
}
$me = currentUser();
if (!in_array($me['role'], ['admin', 'storekeeper'])) {
    http_response_code(403);
    exit('Access denied.');
}

// ── Resolve chef ────────────────────────────────────────────────────────────
$db = getDB();
$chefId = (int)($_GET['chef_id'] ?? 0);
$chefName = trim($_GET['chef'] ?? '');

if (!$chefId && $chefName) {
    $s = $db->prepare("SELECT id, name FROM users WHERE LOWER(name) LIKE ? AND role = 'chef' LIMIT 1");
    $s->execute(['%' . strtolower($chefName) . '%']);
    $row = $s->fetch();
    if ($row) { $chefId = $row['id']; $chefName = $row['name']; }
}
if (!$chefId) {
    http_response_code(404);
    exit("Chef not found. Use ?chef=Name or ?chef_id=N");
}
if (!$chefName) {
    $s = $db->prepare('SELECT name FROM users WHERE id = ?');
    $s->execute([$chefId]);
    $chefName = $s->fetchColumn() ?: "Chef #$chefId";
}

// ── Fetch recipes ────────────────────────────────────────────────────────────
$rs = $db->prepare(
    'SELECT r.id, r.name, r.category, r.cuisine, r.difficulty,
            r.prep_time, r.cook_time, r.servings, r.notes,
            COALESCE(r.is_packed, 0) AS is_packed
     FROM recipes r
     WHERE r.created_by = ?
     ORDER BY r.category, r.name'
);
$rs->execute([$chefId]);
$recipes = $rs->fetchAll();

if (empty($recipes)) {
    exit("No recipes found for '$chefName'.");
}

// ── Fetch ingredients for all recipes ────────────────────────────────────────
$recipeIds = array_column($recipes, 'id');
$ph = implode(',', array_fill(0, count($recipeIds), '?'));
$ings = $db->prepare(
    "SELECT ri.recipe_id, ri.item_name, ri.qty, ri.uom, ri.is_primary,
            i.stock_qty
     FROM recipe_ingredients ri
     LEFT JOIN items i ON i.id = ri.item_id
     WHERE ri.recipe_id IN ($ph)
     ORDER BY ri.recipe_id, ri.is_primary DESC, ri.item_name"
);
$ings->execute($recipeIds);
$allIngs = $ings->fetchAll();

// Group by recipe_id
$ingsByRecipe = [];
foreach ($allIngs as $ing) {
    $ingsByRecipe[$ing['recipe_id']][] = $ing;
}

// ── Build Excel (XLSX via ZipArchive) ────────────────────────────────────────
// Row accumulator  [col-A … col-K]
$rows = [];

// Styled header row
$rows[] = [
    'Recipe',
    'Category',
    'Cuisine',
    'Difficulty',
    'Prep (min)',
    'Cook (min)',
    'Servings',
    'Packed / Out-of-box',
    'Ingredient',
    'Qty',
    'UOM',
    'Order from Store? ✔',
    'Current Stock',
    'Notes',
];

$catLabels = [
    'main_course' => 'Main Course', 'appetizer' => 'Appetizer', 'soup' => 'Soup',
    'salad' => 'Salad', 'dessert' => 'Dessert', 'beverage' => 'Beverage',
    'breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner',
    'snack' => 'Snack', 'sauce' => 'Sauce', 'bread' => 'Bread', 'other' => 'Other',
];

foreach ($recipes as $r) {
    $ings = $ingsByRecipe[$r['id']] ?? [];
    $catLabel = $catLabels[$r['category']] ?? ucfirst($r['category']);
    $packed = $r['is_packed'] ? 'Yes' : 'No';

    if (empty($ings)) {
        $rows[] = [
            $r['name'], $catLabel, $r['cuisine'] ?? '', $r['difficulty'] ?? 'medium',
            $r['prep_time'] ?? '', $r['cook_time'] ?? '', $r['servings'] ?? '',
            $packed, '(no ingredients)', '', '', '', '', $r['notes'] ?? '',
        ];
    } else {
        $first = true;
        foreach ($ings as $ing) {
            $orderTick = $ing['is_primary'] ? 'YES ✔' : 'no';
            $stock     = $ing['stock_qty'] !== null ? $ing['stock_qty'] : '';
            $rows[] = [
                $first ? $r['name']          : '',
                $first ? $catLabel           : '',
                $first ? ($r['cuisine']??'') : '',
                $first ? ($r['difficulty']??'medium') : '',
                $first ? ($r['prep_time']??'')  : '',
                $first ? ($r['cook_time']??'')  : '',
                $first ? ($r['servings']??'')   : '',
                $first ? $packed             : '',
                $ing['item_name'],
                $ing['qty'],
                $ing['uom'],
                $orderTick,
                $stock,
                $first ? ($r['notes']??'')   : '',
            ];
            $first = false;
        }
    }

    // Blank separator row between recipes
    $rows[] = array_fill(0, 14, '');
}

// ── XLSX assembly ─────────────────────────────────────────────────────────────
function xlEsc(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

// Shared strings
$strings = [];
$strIndex = [];
function ssIdx(string $val): int {
    global $strings, $strIndex;
    if (!isset($strIndex[$val])) {
        $strIndex[$val] = count($strings);
        $strings[] = $val;
    }
    return $strIndex[$val];
}

// Build cell data
$cellData = []; // [rowIdx][colIdx] => [type, value]
$colCount = 14;

foreach ($rows as $ri => $row) {
    foreach ($row as $ci => $cell) {
        $cell = (string)$cell;
        if ($cell === '') {
            $cellData[$ri][$ci] = ['e', ''];
        } elseif (is_numeric($cell) && !preg_match('/^0\d/', $cell)) {
            $cellData[$ri][$ci] = ['n', $cell];
        } else {
            $cellData[$ri][$ci] = ['s', ssIdx($cell)];
        }
    }
}

// Column letters
function colLetter(int $n): string { // 0-based
    $letters = '';
    $n++;
    while ($n > 0) {
        $n--;
        $letters = chr(65 + ($n % 26)) . $letters;
        $n = (int)($n / 26);
    }
    return $letters;
}

// sheet1.xml
$sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet"';
$sheetXml .= ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n";
$sheetXml .= '<sheetViews><sheetView workbookViewId="0"><selection activeCell="A1"/></sheetView></sheetViews>' . "\n";
// Freeze top row
$sheetXml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">';
$sheetXml .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
$sheetXml .= '<selection pane="bottomLeft"/></sheetView></sheetViews>' . "\n";
// Column widths
$widths = [28, 14, 14, 11, 10, 10, 10, 16, 28, 8, 8, 20, 14, 30];
$sheetXml .= '<cols>';
foreach ($widths as $i => $w) {
    $c = $i + 1;
    $sheetXml .= "<col min=\"$c\" max=\"$c\" width=\"$w\" customWidth=\"1\"/>";
}
$sheetXml .= '</cols>' . "\n";

$sheetXml .= '<sheetData>' . "\n";
foreach ($rows as $ri => $row) {
    $rowNum = $ri + 1;
    $styleId = ($ri === 0) ? '1' : '0'; // header style vs normal
    $sheetXml .= "<row r=\"$rowNum\">";
    foreach ($row as $ci => $cell) {
        $colLet = colLetter($ci);
        $ref = $colLet . $rowNum;
        $cd  = $cellData[$ri][$ci] ?? ['e', ''];
        if ($cd[0] === 'e') {
            // empty — skip (lighter file)
        } elseif ($cd[0] === 'n') {
            $sheetXml .= "<c r=\"$ref\" s=\"$styleId\"><v>" . xlEsc($cd[1]) . "</v></c>";
        } else {
            $sheetXml .= "<c r=\"$ref\" t=\"s\" s=\"$styleId\"><v>" . $cd[1] . "</v></c>";
        }
    }
    $sheetXml .= "</row>\n";
}
$sheetXml .= '</sheetData>';

// Auto-filter on header row
$lastCol = colLetter($colCount - 1);
$lastRow = count($rows);
$sheetXml .= "<autoFilter ref=\"A1:{$lastCol}1\"/>";
$sheetXml .= '</worksheet>';

// sharedStrings.xml
$ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet"';
$ssXml .= ' count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
foreach ($strings as $s) {
    $ssXml .= '<si><t xml:space="preserve">' . xlEsc($s) . '</t></si>';
}
$ssXml .= '</sst>';

// styles.xml  (styleId 0 = normal, styleId 1 = bold header)
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0"><alignment wrapText="0"/></xf>
  </cellXfs>
</styleSheet>';

// workbook.xml
$workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/sheet"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Recipes" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

// [Content_Types].xml
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

// _rels/.rels
$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

// xl/_rels/workbook.xml.rels
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

// ── Zip it up ─────────────────────────────────────────────────────────────────
$tmp = tempnam(sys_get_temp_dir(), 'kpp_export_') . '.xlsx';
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP archive.');
}
$zip->addFromString('[Content_Types].xml', $contentTypes);
$zip->addFromString('_rels/.rels',         $rootRels);
$zip->addFromString('xl/workbook.xml',     $workbookXml);
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$zip->addFromString('xl/worksheets/sheet1.xml',   $sheetXml);
$zip->addFromString('xl/sharedStrings.xml',       $ssXml);
$zip->addFromString('xl/styles.xml',              $stylesXml);
$zip->close();

// ── Stream to browser ─────────────────────────────────────────────────────────
$filename = 'Recipes-' . preg_replace('/\s+/', '-', $chefName) . '-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-cache');
readfile($tmp);
unlink($tmp);
exit;
