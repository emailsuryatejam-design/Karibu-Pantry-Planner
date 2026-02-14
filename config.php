<?php
/**
 * Karibu Pantry Planner — Configuration
 * Database connection, session, helpers
 */

// ── Session ──
session_start();

// ── Database ──
define('DB_HOST', 'auth-db960.hstgr.io');
define('DB_NAME', 'u929828006_Pantryplanner');
define('DB_USER', 'u929828006_Pantryplanner');
define('DB_PASS', '6145ury@Teja');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// ── JSON Helpers ──
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['error' => $message], $code);
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) return [];
    return $data;
}

// ── Auth Helpers ──
function currentUser() {
    return $_SESSION['user'] ?? null;
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function isChef() {
    $user = currentUser();
    return $user && $user['role'] === 'chef';
}

function isStorekeeper() {
    $user = currentUser();
    return $user && $user['role'] === 'storekeeper';
}

function isAdmin() {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

// ── Date Helpers ──
function todayStr() {
    return date('Y-m-d');
}

// ── Audit Log ──
function auditLog($action, $entity = null, $entityId = null, $oldValue = null, $newValue = null) {
    $user = currentUser();
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO audit_log (user_id, user_name, action, entity, entity_id, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $user['id'] ?? null,
        $user['name'] ?? 'System',
        $action,
        $entity,
        $entityId,
        $oldValue ? json_encode($oldValue) : null,
        $newValue ? json_encode($newValue) : null,
    ]);
}
