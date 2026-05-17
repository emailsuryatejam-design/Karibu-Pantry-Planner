<?php
require_once __DIR__ . '/config.php';
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS notification_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(200) NOT NULL,
    notify_on ENUM('submit','fulfill','both') NOT NULL DEFAULT 'both',
    kitchen_id INT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email)
)");
echo "OK: notification_emails table created\n";
