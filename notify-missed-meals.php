<?php
/**
 * Missed-meal reminder (per meal). If a camp's chef hasn't placed today's order for the given
 * meal, email EVERY notification address on that kitchen (reception + manager) and CC bobby@karibucamps.com.
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
$LOOP = 'bobby@karibucamps.com';
$EXCLUDE = [2, 6];
$VALID = ['breakfast', 'lunch', 'dinner'];

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
            . "<p style='color:#9ca3af;font-size:12px'>Automated {$M} reminder — Karibu Pantry Planner. bobby@karibucamps.com is copied.</p>";
        $html = mailTemplate("{$M} order reminder — {$k['name']}", $content);

        if ($dry) {
            echo "  [$mealCode] {$k['name']}: MISSED → would email: " . implode(', ', $recips) . " (cc {$LOOP})\n";
        } else {
            $ok = sendMealAlert($recips, $LOOP, $subject, $html);
            echo "  [$mealCode] {$k['name']}: MISSED → sent to " . implode(', ', $recips) . " (cc {$LOOP}) : " . ($ok ? 'OK' : 'FAILED') . "\n";
        }
    }
}
echo "== done ==\n";
