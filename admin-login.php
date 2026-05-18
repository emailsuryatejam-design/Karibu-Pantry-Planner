<?php
require_once __DIR__ . '/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// Already logged in? Go to app
if (isLoggedIn()) {
    header('Location: /app.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput    = trim($_POST['email'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

    if ($emailInput && $passwordInput) {
        checkLoginRateLimit($emailInput);
        $db   = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$emailInput]);
        $user = $stmt->fetch();

        $valid = $user && !empty($user['password_hash'])
              && password_verify($passwordInput, $user['password_hash']);

        if ($valid) {
            clearLoginAttempts($emailInput);
            session_regenerate_id(true);

            $kitchenName     = '';
            $userKitchenCode = null;
            if ($user['kitchen_id']) {
                $kStmt = $db->prepare('SELECT name, code FROM kitchens WHERE id = ?');
                $kStmt->execute([$user['kitchen_id']]);
                $kitchen = $kStmt->fetch();
                if ($kitchen) {
                    $kitchenName     = $kitchen['name'];
                    $userKitchenCode = $kitchen['code'];
                }
            }

            $_SESSION['user'] = [
                'id'           => $user['id'],
                'name'         => $user['name'],
                'username'     => $user['username'],
                'role'         => $user['role'],
                'camp_id'      => $user['camp_id']   ?? null,
                'camp_name'    => $user['camp_name']  ?? null,
                'kitchen_id'   => $user['kitchen_id'],
                'kitchen_name' => $kitchenName,
                'kitchen_code' => $userKitchenCode,
            ];
            header('Location: /app.php');
            exit;
        } else {
            recordLoginAttempt($emailInput);
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Please enter your email and password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1e3a5f">
    <title>Admin Login — Karibu Pantry Planner</title>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">

        <!-- Logo -->
        <div class="text-center mb-8">
            <img src="/assets/karibu-logo.png" alt="Karibu Camps &amp; Lodges"
                 style="height:90px;width:auto;mix-blend-mode:screen;filter:brightness(1.15);"
                 class="mx-auto mb-3">
            <p class="text-sm text-slate-400">Pantry Planner — Admin Portal</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-slate-800 px-6 py-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Sign in with your credentials</p>
            </div>

            <form method="POST" class="px-6 py-6 space-y-4">

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5 block">Email Address</label>
                    <input type="email" name="email" required autofocus autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="admin@karibucamps.com"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400">
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5 block">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="pwdInput" required autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 pr-11 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400">
                        <button type="button" onclick="togglePwd()" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                            <!-- eye-open (shown when password hidden) -->
                            <svg id="eyeShow" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <!-- eye-off (shown when password revealed) -->
                            <svg id="eyeHide" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-slate-800 hover:bg-slate-700 active:bg-slate-900 text-white py-3 rounded-xl text-sm font-semibold transition shadow-sm mt-2">
                    Sign In
                </button>
            </form>
        </div>

        <!-- Back link -->
        <div class="text-center mt-6">
            <a href="/index.php"
                class="text-xs text-slate-500 hover:text-slate-300 transition inline-flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                Back to Staff Login
            </a>
        </div>

    </div>

    <script>
        function togglePwd() {
            const inp  = document.getElementById('pwdInput');
            const show = document.getElementById('eyeShow');
            const hide = document.getElementById('eyeHide');
            if (inp.type === 'password') {
                inp.type = 'text';
                show.style.display = 'none';
                hide.style.display = '';
            } else {
                inp.type = 'password';
                show.style.display = '';
                hide.style.display = 'none';
            }
        }
    </script>
</body>
</html>
