<?php
/**
 * Karibu Pantry Planner — Performance Indexes Migration
 * Run once: visit /migrate-indexes.php in browser
 */

require_once __DIR__ . '/config.php';

$db = getDB();

echo "<pre>\n";
echo "=== Adding Performance Indexes ===\n\n";

$indexes = [
    // Requisitions: most queried by date + kitchen + status
    "ALTER TABLE requisitions ADD INDEX idx_req_date_kitchen (req_date, kitchen_id, status)" => 'requisitions(req_date, kitchen_id, status)',
    "ALTER TABLE requisitions ADD INDEX idx_req_kitchen_status (kitchen_id, status)" => 'requisitions(kitchen_id, status)',
    "ALTER TABLE requisitions ADD INDEX idx_req_created_by (created_by)" => 'requisitions(created_by)',

    // Requisition lines: always queried by requisition_id
    "ALTER TABLE requisition_lines ADD INDEX idx_reqlines_reqid (requisition_id)" => 'requisition_lines(requisition_id)',

    // Push subscriptions: looked up by kitchen + role
    "ALTER TABLE push_subscriptions ADD INDEX idx_push_kitchen (kitchen_id)" => 'push_subscriptions(kitchen_id)',
    "ALTER TABLE push_subscriptions ADD INDEX idx_push_user (user_id)" => 'push_subscriptions(user_id)',

    // Notifications: queried by kitchen + user + read status
    "ALTER TABLE notifications ADD INDEX idx_notif_kitchen_user (kitchen_id, user_id, is_read)" => 'notifications(kitchen_id, user_id, is_read)',

    // Users: looked up by kitchen_id
    "ALTER TABLE users ADD INDEX idx_users_kitchen (kitchen_id)" => 'users(kitchen_id)',

    // Items: filtered by is_active + category
    "ALTER TABLE items ADD INDEX idx_items_active_cat (is_active, category)" => 'items(is_active, category)',

    // Audit log: queried by entity
    "ALTER TABLE audit_log ADD INDEX idx_audit_entity (entity, entity_id)" => 'audit_log(entity, entity_id)',
];

foreach ($indexes as $sql => $label) {
    try {
        $db->exec($sql);
        echo "[OK] $label\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "[SKIP] $label (already exists)\n";
        } else {
            echo "[FAIL] $label — " . $e->getMessage() . "\n";
        }
    }
}

echo "\n=== Done ===\n";
echo "</pre>\n";
