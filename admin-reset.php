<?php
/**
 * Karibu Pantry Planner — Admin Reset Password (consume a reset link)
 *
 * Validates a single-use, time-limited token from admin-forgot.php and sets a new password.
 */
require_once __DIR__ . '/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$token      = $_GET['token'] ?? ($_POST['token'] ?? '');
$error      = '';
$success    = false;
$validToken = false;
$tokenRow   = null;

function findValidReset(PDO $db, string $token): ?array {
    if (!$token || strlen($token) !== 64 || !ctype_xdigit($token)) return null;
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT pr.id, pr.user_id, u.email, u.name
        FROM password_resets pr JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.is_active = 1
        LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

try {
    $db = getDB();
    // Table may not exist if no reset was ever requested — treat as invalid token
    try {
        $tokenRow   = findValidReset($db, $token);
        $validToken = (bool)$tokenRow;
    } catch (PDOException $e) {
        $validToken = false;
    }
} catch (Exception $e) {
    $error = 'Something went wrong. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $csrfOk = hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '');
    $pw     = $_POST['password'] ?? '';
    $pw2    = $_POST['password_confirm'] ?? '';

    if (!$csrfOk) {
        $error = 'Session expired — please reload and try again.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pw !== $pw2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $tokenRow['user_id']]);
            // Single-use: mark this token (and any other unused ones) consumed
            $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$tokenRow['user_id']]);
            $success = true;
        } catch (Exception $e) {
            error_log('[Karibu Reset] update failed: ' . $e->getMessage());
            $error = 'Could not update your password. Please try again.';
        }
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
    <title>Set New Password — Karibu Pantry Planner</title>
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
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Set a new password</p>
            </div>

            <?php if ($success): ?>
                <div class="px-6 py-8 text-center">
                    <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-green-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">Your password has been updated. You can now sign in with your new password.</p>
                    <a href="/admin-login.php"
                        style="display:inline-block;width:100%;background:#1e293b;color:#fff;padding:12px 0;border-radius:12px;font-size:14px;font-weight:600;text-decoration:none;margin-top:16px;">
                        Go to Admin Login
                    </a>
                </div>
            <?php elseif (!$validToken): ?>
                <div class="px-6 py-8 text-center">
                    <div class="mx-auto mb-4 w-12 h-12 rounded-full bg-red-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">This reset link is invalid or has expired. Reset links are valid for 1 hour and can be used once.</p>
                    <a href="/admin-forgot.php"
                        style="display:inline-block;width:100%;background:#1e293b;color:#fff;padding:12px 0;border-radius:12px;font-size:14px;font-weight:600;text-decoration:none;margin-top:16px;">
                        Request a New Link
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" class="px-6 py-6 space-y-4">
                    <input type="hidden" name="csrf"  value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-xs text-gray-500">Resetting password for <strong class="text-gray-700"><?= htmlspecialchars($tokenRow['email']) ?></strong></p>

                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5 block">New Password</label>
                        <input type="password" name="password" id="pwdInput" required minlength="8" autocomplete="new-password"
                            placeholder="At least 8 characters"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400">
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5 block">Confirm New Password</label>
                        <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password"
                            placeholder="Re-enter password"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400">
                    </div>

                    <button type="submit"
                        style="width:100%;background:#1e293b;color:#fff;padding:12px 0;border-radius:12px;font-size:14px;font-weight:600;border:none;cursor:pointer;margin-top:8px;box-shadow:0 1px 2px rgba(0,0,0,.1);transition:background .15s;"
                        onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">
                        Update Password
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
