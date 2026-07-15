<?php
/**
 * Karibu Pantry Planner — Admin Forgot Password (request a reset link)
 *
 * Emails a time-limited, single-use reset link via Gmail SMTP (see mailer.php).
 * Email-enumeration safe: always shows the same generic confirmation.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

if (isLoggedIn()) { header('Location: /app.php?page=admin-home'); exit; }

$done  = false;
$error = '';

// Simple inline rate limit — max 5 reset requests per email+IP per hour
function resetRequestRateLimit(string $email): bool {
    $key  = 'pwreset_' . md5($email . ($_SERVER['REMOTE_ADDR'] ?? ''));
    $file = CACHE_DIR . '/' . md5($key) . '.json';
    $attempts = [];
    if (file_exists($file)) $attempts = json_decode(@file_get_contents($file), true) ?: [];
    $cutoff   = time() - 3600;
    $attempts = array_filter($attempts, fn($t) => $t > $cutoff);
    if (count($attempts) >= 5) return false;
    if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0755, true);
    $attempts[] = time();
    @file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '');
    $email  = trim($_POST['email'] ?? '');

    if (!$csrfOk) {
        $error = 'Session expired — please try again.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!resetRequestRateLimit($email)) {
        $error = 'Too many requests. Please wait a while and try again.';
    } else {
        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token_hash),
                INDEX idx_user (user_id)
            )");

            $stmt = $db->prepare("SELECT id, name, email FROM users WHERE email = ? AND is_active = 1 AND email IS NOT NULL AND TRIM(email) != '' ORDER BY (role='admin') DESC LIMIT 1");
            $stmt->execute([$email]);
            $u = $stmt->fetch();

            if ($u) {
                // Invalidate any prior unused tokens for this user
                $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$u['id']]);

                $token     = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires   = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
                   ->execute([$u['id'], $tokenHash, $expires]);

                $link = rtrim(APP_URL, '/') . '/admin-reset.php?token=' . $token;
                $body = "<p>Hi <strong>" . htmlspecialchars($u['name']) . "</strong>,</p>
                    <p>We received a request to reset the password for your Karibu Pantry Planner admin account.</p>
                    <p>This link is valid for <strong>1 hour</strong> and can be used once. If you didn't request this, you can safely ignore this email — your password will not change.</p>";
                $html = mailTemplate('Reset Your Password', $body, 'Reset Password', $link);
                sendMail($u['email'], 'Karibu Pantry Planner — Password Reset', $html);
            }
        } catch (Exception $e) {
            error_log('[Karibu Reset] request failed: ' . $e->getMessage());
        }
        $done = true; // Generic success regardless of whether the email exists
    }
}

$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1e3a5f">
    <title>Forgot Password — Karibu Pantry Planner</title>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">

        <div class="text-center mb-8">
            <img src="/assets/karibu-logo.png" alt="Karibu Camps &amp; Lodges"
                 style="height:90px;width:auto;mix-blend-mode:screen;filter:brightness(1.15);"
                 class="mx-auto mb-3">
            <p class="text-sm text-slate-400">Admin Portal</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-slate-800 px-6 py-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Reset your password</p>
            </div>

            <?php if ($done): ?>
                <div class="px-6 py-8 text-center">
                    <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">If an account with that email exists, we've sent a password reset link. Please check your inbox (and spam folder).</p>
                    <p class="text-xs text-gray-400 mt-3">The link is valid for 1 hour.</p>
                </div>
            <?php else: ?>
                <form method="POST" class="px-6 py-6 space-y-4">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-xs text-gray-500 leading-relaxed">Enter the email address linked to your admin account and we'll send you a link to reset your password.</p>

                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5 block">Email Address</label>
                        <input type="email" name="email" required autofocus autocomplete="email"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="admin@karibucamps.com"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400">
                    </div>

                    <button type="submit"
                        style="width:100%;background:#1e293b;color:#fff;padding:12px 0;border-radius:12px;font-size:14px;font-weight:600;border:none;cursor:pointer;margin-top:8px;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:background .15s;"
                        onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
                        Send Reset Link
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="text-center mt-6">
            <a href="/admin-login.php"
                class="text-xs text-slate-500 hover:text-slate-300 transition inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                Back to Admin Login
            </a>
        </div>

    </div>
</body>
</html>
