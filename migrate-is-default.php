<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$db->exec("ALTER TABLE recipes ADD COLUMN IF NOT EXISTS is_default TINYINT(1) NOT NULL DEFAULT 0");
echo "OK: is_default column added\n";
