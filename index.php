<?php
require_once __DIR__ . '/config.php';

// Prevent LiteSpeed/proxy caching — user list must always be fresh
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-LiteSpeed-Cache-Control: no-cache');

// Already logged in? Go to app
if (isLoggedIn()) {
    header('Location: /app.php');
    exit;
}

$error = '';

// Kitchen-specific login: ?kitchen=SWC or /SWC/
$kitchenCode = strtoupper(trim($_GET['kitchen'] ?? ''));
$kitchenFilter = null;

// Load all active kitchens for the dropdown
$kitchens = [];
try {
    $db = getDB();
    $kitchens = $db->query('SELECT id, name, code FROM kitchens WHERE is_active = 1 ORDER BY name')->fetchAll();
} catch (Exception $e) {}

if ($kitchenCode) {
    foreach ($kitchens as $k) {
        if (strtoupper($k['code']) === $kitchenCode) {
            $kitchenFilter = $k;
            break;
        }
    }
    if (!$kitchenFilter) {
        header('Location: /index.php');
        exit;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    if ($username && $pin) {
        // Rate limiting
        checkLoginRateLimit($username);

        $db = getDB();
        // Fetch user by username only — verify PIN with password_verify
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Support both hashed PINs (pin_hash) and legacy plaintext (pin)
        $pinValid = false;
        if ($user) {
            if (!empty($user['pin_hash'])) {
                $pinValid = password_verify($pin, $user['pin_hash']);
            } else {
                // Legacy plaintext fallback — auto-upgrade on successful login
                $pinValid = ($user['pin'] === $pin);
                if ($pinValid) {
                    // Auto-upgrade to hashed PIN
                    $hash = password_hash($pin, PASSWORD_DEFAULT);
                    $db->prepare('UPDATE users SET pin_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
                }
            }
        }

        if ($user && $pinValid) {
            clearLoginAttempts($username);
            session_regenerate_id(true);

            // Get kitchen name and code for this user
            $kitchenName = '';
            $userKitchenCode = null;
            if ($user['kitchen_id']) {
                $kStmt = $db->prepare('SELECT name, code FROM kitchens WHERE id = ?');
                $kStmt->execute([$user['kitchen_id']]);
                $kitchen = $kStmt->fetch();
                if ($kitchen) {
                    $kitchenName = $kitchen['name'];
                    $userKitchenCode = $kitchen['code'];
                }
            }
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role'],
                'camp_id' => $user['camp_id'] ?? null,
                'camp_name' => $user['camp_name'] ?? null,
                'kitchen_id' => $user['kitchen_id'],
                'kitchen_name' => $kitchenName,
                'kitchen_code' => $userKitchenCode,
            ];
            header('Location: /app.php');
            exit;
        } else {
            recordLoginAttempt($username);
            $error = 'Invalid username or PIN';
        }
    } else {
        $error = 'Please enter username and PIN';
    }
}

// Users are loaded via AJAX after camp selection — no longer exposed in page source
// API endpoint: api/login-users.php?kitchen_id=X
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#ea580c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="/manifest.php<?= $kitchenCode ? '?kitchen=' . urlencode($kitchenCode) : '' ?>">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.png">
    <title><?= $kitchenFilter ? htmlspecialchars($kitchenFilter['name']) . ' — Karibu' : 'Karibu Pantry Planner' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .pin-btn { min-height: 56px; min-width: 56px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-orange-500 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900" id="loginTitle"><?= $kitchenFilter ? htmlspecialchars($kitchenFilter['name']) : 'Karibu Pantry Planner' ?></h1>
            <p class="text-sm text-gray-500 mt-1">Kitchen & Stores Login</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <form method="POST" id="loginForm">

                <!-- Camp Picker -->
                <div class="px-5 pt-5 pb-3">
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 block">Select Camp</label>
                    <select id="campSelect" onchange="onCampChange()"
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400 appearance-none <?= $kitchenFilter ? 'opacity-70' : '' ?>"
                        <?= $kitchenFilter ? 'disabled' : '' ?>>
                        <?php if (!$kitchenFilter): ?>
                            <option value="">-- Choose your camp --</option>
                        <?php endif; ?>
                        <?php foreach ($kitchens as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" data-code="<?= htmlspecialchars($k['code']) ?>"
                                <?= ($kitchenFilter && $kitchenFilter['id'] == $k['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($kitchenFilter): ?>
                        <p class="text-[10px] text-orange-500 mt-1">Locked to this camp</p>
                    <?php endif; ?>
                </div>

                <!-- Staff Picker (filtered by camp) -->
                <div class="px-5 pb-3">
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 block">Select Staff</label>
                    <select name="username" id="staffSelect" required
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400 appearance-none">
                        <option value="">-- Choose your name --</option>
                    </select>
                </div>

                <!-- PIN Input Display -->
                <div class="px-5 pb-3">
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 block">Enter PIN</label>
                    <div class="flex justify-center gap-3 mb-3">
                        <div class="w-12 h-12 rounded-xl border-2 border-gray-200 flex items-center justify-center text-xl font-bold text-gray-800 pin-dot" data-idx="0"></div>
                        <div class="w-12 h-12 rounded-xl border-2 border-gray-200 flex items-center justify-center text-xl font-bold text-gray-800 pin-dot" data-idx="1"></div>
                        <div class="w-12 h-12 rounded-xl border-2 border-gray-200 flex items-center justify-center text-xl font-bold text-gray-800 pin-dot" data-idx="2"></div>
                        <div class="w-12 h-12 rounded-xl border-2 border-gray-200 flex items-center justify-center text-xl font-bold text-gray-800 pin-dot" data-idx="3"></div>
                    </div>
                    <input type="hidden" name="pin" id="pinInput">
                </div>

                <!-- Error -->
                <?php if ($error): ?>
                    <div class="mx-5 mb-3 bg-red-50 border border-red-200 rounded-xl px-4 py-2 text-sm text-red-700 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- PIN Pad -->
                <div class="px-5 pb-5">
                    <div class="grid grid-cols-3 gap-2">
                        <?php for ($i = 1; $i <= 9; $i++): ?>
                            <button type="button" onclick="addPin('<?= $i ?>')"
                                class="pin-btn bg-gray-50 hover:bg-gray-100 active:bg-orange-100 rounded-xl text-xl font-semibold text-gray-800 transition">
                                <?= $i ?>
                            </button>
                        <?php endfor; ?>
                        <button type="button" onclick="clearPin()"
                            class="pin-btn bg-red-50 hover:bg-red-100 active:bg-red-200 rounded-xl text-sm font-semibold text-red-600 transition">
                            Clear
                        </button>
                        <button type="button" onclick="addPin('0')"
                            class="pin-btn bg-gray-50 hover:bg-gray-100 active:bg-orange-100 rounded-xl text-xl font-semibold text-gray-800 transition">
                            0
                        </button>
                        <button type="button" onclick="removePin()"
                            class="pin-btn bg-gray-50 hover:bg-gray-100 active:bg-gray-200 rounded-xl text-sm font-semibold text-gray-600 transition flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 5a2 2 0 0 0-1.344.519l-6.328 5.74a1 1 0 0 0 0 1.481l6.328 5.741A2 2 0 0 0 10 19h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/><path d="m12 9 6 6"/><path d="m18 9-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Install App Button -->
        <button id="installBtn" class="hidden w-full mt-4 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
            Install App
        </button>

        <p class="text-center text-xs text-gray-400 mt-4">Karibu Camps &mdash; Pantry Planner</p>
    </div>

    <script>
        const lockedKitchen = <?= $kitchenFilter ? (int)$kitchenFilter['id'] : 'null' ?>;

        async function onCampChange() {
            const campId = parseInt(document.getElementById('campSelect').value) || 0;
            const staffSelect = document.getElementById('staffSelect');
            staffSelect.innerHTML = '<option value="">Loading...</option>';

            // Update title
            const campOption = document.getElementById('campSelect').selectedOptions[0];
            const title = document.getElementById('loginTitle');
            if (campId && campOption) {
                title.textContent = campOption.textContent.trim();
            } else {
                title.textContent = 'Karibu Pantry Planner';
            }

            if (!campId) {
                staffSelect.innerHTML = '<option value="">-- Choose camp first --</option>';
                return;
            }

            try {
                const res = await fetch(`/api/login-users.php?kitchen_id=${campId}`);
                const data = await res.json();
                const users = data.users || [];
                staffSelect.innerHTML = '<option value="">-- Choose your name --</option>' +
                    users.map(u => `<option value="${u.username}">${u.name} (${u.role.charAt(0).toUpperCase() + u.role.slice(1)})</option>`).join('');
            } catch (e) {
                staffSelect.innerHTML = '<option value="">-- Error loading --</option>';
            }
        }

        // Init: load users for pre-selected camp
        onCampChange();

        // PWA Install prompt
        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('installBtn').classList.remove('hidden');
        });
        document.getElementById('installBtn').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const result = await deferredPrompt.userChoice;
            if (result.outcome === 'accepted') document.getElementById('installBtn').classList.add('hidden');
            deferredPrompt = null;
        });
        window.addEventListener('appinstalled', () => {
            document.getElementById('installBtn').classList.add('hidden');
            deferredPrompt = null;
        });

        // Register service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js').catch(() => {});
        }

        // PIN pad
        let pin = '';
        const dots = document.querySelectorAll('.pin-dot');
        const pinInput = document.getElementById('pinInput');
        const form = document.getElementById('loginForm');

        function updateDots() {
            dots.forEach((dot, i) => {
                if (i < pin.length) {
                    dot.textContent = '\u2022';
                    dot.classList.add('border-orange-400', 'bg-orange-50');
                    dot.classList.remove('border-gray-200');
                } else {
                    dot.textContent = '';
                    dot.classList.remove('border-orange-400', 'bg-orange-50');
                    dot.classList.add('border-gray-200');
                }
            });
            pinInput.value = pin;
        }

        function addPin(digit) {
            if (pin.length >= 4) return;
            pin += digit;
            updateDots();
            if (pin.length === 4) {
                setTimeout(() => form.submit(), 200);
            }
        }

        function removePin() {
            pin = pin.slice(0, -1);
            updateDots();
        }

        function clearPin() {
            pin = '';
            updateDots();
        }
    </script>
</body>
</html>
