<?php
require_once __DIR__ . '/config.php';

$hash = password_hash('Karibu@2026', PASSWORD_DEFAULT);
$db   = getDB();
$stmt = $db->prepare("UPDATE users SET password_hash=?, email=?, name='Karibu Admin', is_active=1 WHERE id=1");
$stmt->execute([$hash, 'jambo@karibucamps.com']);

echo "Done.\n";
echo "Hash: " . $hash . "\n";
echo "Verify: " . (password_verify('Karibu@2026', $hash) ? 'PASS' : 'FAIL') . "\n";

// Also verify DB round-trip
$row = $db->query("SELECT password_hash FROM users WHERE id=1")->fetch();
echo "DB verify: " . (password_verify('Karibu@2026', $row['password_hash']) ? 'PASS' : 'FAIL') . "\n";
