<?php
/**
 * Karibu Pantry Planner — Configuration
 * Database connection, session, helpers
 */

// ── Timezone ──
date_default_timezone_set('Africa/Dar_es_Salaam');

// ── Load .env file if exists ──
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
        }
    }
}

// ── Session ──
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

// ── VAPID Keys (Push Notifications) — from .env ──
define('VAPID_PUBLIC_KEY', $_ENV['VAPID_PUBLIC_KEY'] ?? 'BPp5G-UF9ehoRSuEkjJ2gG-8Fy7FwN5z0_SNfNn40N9uS8YFqpPbK8BkXGR4l5x72nxxfUOGEa7848wIQZF1oiA');
define('VAPID_PRIVATE_KEY', $_ENV['VAPID_PRIVATE_KEY'] ?? 'MCfLFGa0KvCVsp868ywlHiwSiBoh83kod1bcZ5cQD9w');
define('VAPID_SUBJECT', $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@karibupantry.com');

// ── Database — from .env (hardcoded fallback for Hostinger until .env uploaded) ──
define('DB_HOST', $_ENV['DB_HOST'] ?? 'auth-db960.hstgr.io');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'u929828006_Pantryplanner');
define('DB_USER', $_ENV['DB_USER'] ?? 'u929828006_Pantryplanner');
define('DB_PASS', $_ENV['DB_PASS'] ?? '6145ury@Teja');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                    PDO::ATTR_PERSISTENT          => true,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed']));
        }
    }
    return $pdo;
}

// ── File Cache (reduces remote DB round-trips) ──
define('CACHE_DIR', __DIR__ . '/.cache');

function cacheGet(string $key, int $ttlSeconds = 300) {
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    if (!file_exists($file)) return null;
    if (time() - filemtime($file) > $ttlSeconds) { @unlink($file); return null; }
    $data = @file_get_contents($file);
    return $data ? json_decode($data, true) : null;
}

function cacheSet(string $key, $data): void {
    if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function cacheClear(string $key = ''): void {
    if ($key) {
        @unlink(CACHE_DIR . '/' . md5($key) . '.json');
    } else {
        // Clear all cache
        $files = glob(CACHE_DIR . '/*.json');
        if ($files) foreach ($files as $f) @unlink($f);
    }
}

/**
 * Get active items list (cached for 5 min, most expensive query)
 */
function getCachedItems(): array {
    $cached = cacheGet('active_items', 300);
    if ($cached !== null) return $cached;

    $db = getDB();
    $items = $db->query("SELECT id, name, code, category, uom, stock_qty, portion_weight, order_mode FROM items WHERE is_active = 1 ORDER BY category, name")->fetchAll();

    $grouped = [];
    foreach ($items as $item) {
        $c = $item['category'] ?: 'Uncategorized';
        $grouped[$c][] = $item;
    }

    $result = ['items' => $items, 'grouped' => $grouped];
    cacheSet('active_items', $result);
    return $result;
}

/**
 * Get kitchens list (cached for 10 min)
 */
function getCachedKitchens(): array {
    $cached = cacheGet('kitchens', 600);
    if ($cached !== null) return $cached;

    $db = getDB();
    $kitchens = $db->query("SELECT k.*, (SELECT COUNT(*) FROM users WHERE kitchen_id = k.id) AS user_count FROM kitchens k ORDER BY k.name")->fetchAll();

    cacheSet('kitchens', $kitchens);
    return $kitchens;
}


// ── SQL Helpers ──
function escapeLike(string $str): string {
    return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $str);
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
    if (!$raw) return [];
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        jsonError('Invalid JSON input', 400);
    }
    return $data ?: [];
}

// ── CSRF Protection ──
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        jsonError('Invalid CSRF token', 403);
    }
}

// ── Login Rate Limiting ──
function checkLoginRateLimit(string $username): void {
    $key = 'login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(@file_get_contents($file), true) ?: [];
    }
    // Remove attempts older than 15 minutes
    $cutoff = time() - 900;
    $attempts = array_filter($attempts, fn($t) => $t > $cutoff);
    if (count($attempts) >= 5) {
        jsonError('Too many login attempts. Try again in 15 minutes.', 429);
    }
}

function recordLoginAttempt(string $username): void {
    $key = 'login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(@file_get_contents($file), true) ?: [];
    }
    $cutoff = time() - 900;
    $attempts = array_filter($attempts, fn($t) => $t > $cutoff);
    $attempts[] = time();
    @file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
}

function clearLoginAttempts(string $username): void {
    $key = 'login_attempts_' . md5($username . ($_SERVER['REMOTE_ADDR'] ?? ''));
    @unlink(CACHE_DIR . '/' . md5($key) . '.json');
}

// ── Auth Helpers ──
function currentUser() {
    if (!isset($_SESSION['user'])) return null;
    // Refresh user data from DB periodically (every 60s) to pick up role/kitchen changes
    $lastRefresh = $_SESSION['user_refreshed'] ?? 0;
    if (time() - $lastRefresh > 60) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT u.*, k.name AS kitchen_name FROM users u LEFT JOIN kitchens k ON k.id = u.kitchen_id WHERE u.id = ? AND u.is_active = 1");
            $stmt->execute([$_SESSION['user']['id']]);
            $fresh = $stmt->fetch();
            if ($fresh) {
                unset($fresh['password_hash'], $fresh['pin_hash']);
                $_SESSION['user'] = $fresh;
                $_SESSION['user_refreshed'] = time();
            } else {
                // User deactivated or deleted
                unset($_SESSION['user']);
                return null;
            }
        } catch (Exception $e) { /* keep cached data on DB error */ }
    }
    return $_SESSION['user'];
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /index.php');
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

// ── Kitchen Helpers ──
function currentKitchenId() {
    $user = currentUser();
    return $user['kitchen_id'] ?? null;
}

function currentKitchenName() {
    $user = currentUser();
    return $user['kitchen_name'] ?? '';
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
