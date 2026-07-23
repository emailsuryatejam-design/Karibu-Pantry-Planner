<?php
/**
 * Missed-meal reminder (per meal). If a camp's chef hasn't placed today's order for the given
 * meal, email EVERY notification address on that kitchen (reception + manager).
 * (Bobby is no longer copied — removed 2026-07-23.)
 * Only sends after the meal's cutoff ($SEND_AFTER) and at most once per camp/meal/day, so the
 * GitHub scheduler can fire early/repeatedly to beat its own lateness without false or double alerts.
 *
 * Run each meal from hPanel cron (server time = Africa/Dar_es_Salaam / EAT):
 *   10 8  * * *  php .../notify-missed-meals.php breakfast >> ~/.logs/missed-meals.log 2>&1   # 08:10
 *   10 12 * * *  php .../notify-missed-meals.php lunch     >> ~/.logs/missed-meals.log 2>&1   # 12:10
 *   10 18 * * *  php .../notify-missed-meals.php dinner    >> ~/.logs/missed-meals.log 2>&1   # 18:10
 *
 * Manual / test:
 *   php notify-missed-meals.php breakfast --dry            # show what would send, send nothing
 *   php notify-missed-meals.php breakfast --to=me@x.com    # send all to one test address
 *   php notify-missed-meals.php                            # (no meal) dry-run all three meals
 *
 * Targets operating camps, EXCLUDING Serengeti Woodlands (id 2) and Demo Kitchen (id 6).
 */
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;

$db = getDB();
$LOOP = '';                        // Bobby removed from missed-meal alerts (2026-07-23)
$EXCLUDE = [2, 6];
$VALID = ['breakfast', 'lunch', 'dinner'];
// Only actually send once the meal's ordering window has closed (server time = EAT).
// This lets the scheduler fire EARLY (to beat its own 1–3h lateness) without ever alerting
// before the cutoff — early fires are skipped, whichever fire lands after the cutoff sends.
$SEND_AFTER = ['breakfast' => '08:10', 'lunch' => '12:10', 'dinner' => '18:10'];

$meal = null; $dry = false; $testTo = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry') $dry = true;
    elseif (str_starts_with($a, '--to=')) $testTo = substr($a, 5);
    elseif (in_array(strtolower($a), $VALID, true)) $meal = strtolower($a);
}
$meals = $meal ? [$meal] : $VALID;
if (!$meal) $dry = true; // safety: running with no meal only ever dry-runs

$today = $db->query("SELECT CURDATE()")->fetchColumn();
$now = date('H:i');

// send To one-or-more addresses with a CC
function sendMealAlert(array $to, string $cc, string $subject, string $html): bool {
    $to = array_values(array_unique(array_filter($to, fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
    if (!$to) return false;
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) { $ok = true; foreach ($to as $t) $ok = sendMail($t, $subject, $html) && $ok; return $ok; }
    try {
        $m = new PHPMailer(true);
        $m->isSMTP(); $m->Host = MAIL_SMTP_HOST; $m->SMTPAuth = true; $m->Username = MAIL_SMTP_USER;
        $m->Password = MAIL_SMTP_PASS; $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $m->Port = MAIL_SMTP_PORT;
        $m->CharSet = 'UTF-8'; $m->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $m->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        foreach ($to as $t) $m->addAddress($t);
        if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL) && !in_array($cc, $to, true)) $m->addCC($cc);
        $m->isHTML(true); $m->Subject = $subject; $m->Body = $html;
        $m->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        $m->send(); return true;
    } catch (Throwable $e) { error_log('[missed-meals] ' . $e->getMessage()); return false; }
}

echo "== Missed-meal check for $today at $now | meals=" . implode(',', $meals) . ($dry ? " | DRY-RUN" : "") . ($testTo ? " | test->$testTo" : "") . " ==\n";
$targets = $db->query("SELECT id,name FROM kitchens WHERE id NOT IN (" . implode(',', $EXCLUDE) . ") AND is_active=1 ORDER BY id")->fetchAll();

foreach ($meals as $mealCode) {
    // Time guard: on a real run, don't send before this meal's window has closed.
    // (Dry-runs and --to= tests always proceed, so you can preview at any time.)
    if (!$dry && !$testTo && isset($SEND_AFTER[$mealCode]) && $now < $SEND_AFTER[$mealCode]) {
        echo "  [$mealCode] before {$SEND_AFTER[$mealCode]} — too early to alert, skipping this run\n";
        continue;
    }
    foreach ($targets as $k) {
        $kid = (int)$k['id'];
        $has = $db->prepare("SELECT COUNT(*) FROM requisitions WHERE kitchen_id=? AND req_date=? AND meals=? AND status<>'draft' AND deleted_at IS NULL");
        $has->execute([$kid, $today, $mealCode]);
        if ((int)$has->fetchColumn() > 0) { echo "  [$mealCode] {$k['name']}: ordered — ok\n"; continue; }

        // every email registered on this kitchen
        $e = $db->prepare("SELECT email FROM notification_emails WHERE kitchen_id=? AND is_active=1 AND deleted_at IS NULL");
        $e->execute([$kid]);
        $emails = array_column($e->fetchAll(), 'email');
        if (!$emails) { echo "  [$mealCode] {$k['name']}: MISSED but no emails on file — skip\n"; continue; }
        $recips = $testTo ? [$testTo] : $emails;

        $M = ucfirst($mealCode);
        $subject = "Reminder: {$k['name']} — {$M} order not placed today";
        $content = "<p style='font-size:15px'>Hi,</p>"
            . "<p style='font-size:15px'>As of <b>{$now}</b> today ({$today}), the chef at <b>" . htmlspecialchars($k['name']) . "</b> has <b>not placed the {$M} order</b> in the app.</p>"
            . "<p style='font-size:15px'>Please follow up with the kitchen so the store can prepare {$M} in time.</p>"
            . "<p style='color:#9ca3af;font-size:12px'>Automated {$M} reminder — Karibu Pantry Planner.</p>";
        $html = mailTemplate("{$M} order reminder — {$k['name']}", $content);

        $ccNote = $LOOP ? " (cc {$LOOP})" : "";
        if ($dry) {
            echo "  [$mealCode] {$k['name']}: MISSED → would email: " . implode(', ', $recips) . "{$ccNote}\n";
            continue;
        }
        // Send each camp at most once per meal per day, however many times the job fires
        // (the scheduler runs several times per meal to beat its own lateness).
        $alertKey = "missedmeal:{$today}:{$mealCode}:{$kid}";
        if (!$testTo && cacheGet($alertKey, 72000)) {
            echo "  [$mealCode] {$k['name']}: MISSED but already alerted today — skip\n";
            continue;
        }
        $ok = sendMealAlert($recips, $LOOP, $subject, $html);
        if ($ok && !$testTo) cacheSet($alertKey, $now);
        echo "  [$mealCode] {$k['name']}: MISSED → sent to " . implode(', ', $recips) . "{$ccNote} : " . ($ok ? 'OK' : 'FAILED') . "\n";
    }
}
echo "== done ==\n";
