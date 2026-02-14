<?php
require_once __DIR__ . '/config.php';

// Already logged in? Go to app
if (isLoggedIn()) {
    header('Location: app.php');
    exit;
}

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pin = trim($_POST['pin'] ?? '');

    if ($username && $pin) {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND pin = ? AND is_active = 1');
        $stmt->execute([$username, $pin]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role'],
                'camp_id' => $user['camp_id'],
                'camp_name' => $user['camp_name'],
            ];
            header('Location: app.php');
            exit;
        } else {
            $error = 'Invalid username or PIN';
        }
    } else {
        $error = 'Please enter username and PIN';
    }
}

// Fetch users for staff picker
try {
    $db = getDB();
    $users = $db->query('SELECT id, name, username, role FROM users WHERE is_active = 1 ORDER BY name')->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Karibu Pantry Planner</title>
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
            <h1 class="text-xl font-bold text-gray-900">Karibu Pantry Planner</h1>
            <p class="text-sm text-gray-500 mt-1">Kitchen & Stores Management</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <form method="POST" id="loginForm">
                <!-- Staff Picker -->
                <div class="px-5 pt-5 pb-3">
                    <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 block">Select Staff</label>
                    <select name="username" id="staffSelect" required
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm font-medium text-gray-800 focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-400 appearance-none">
                        <option value="">-- Choose your name --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= htmlspecialchars($u['username']) ?>">
                                <?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
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

        <p class="text-center text-xs text-gray-400 mt-4">Karibu Camps &mdash; Pantry Planner</p>
    </div>

    <script>
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
