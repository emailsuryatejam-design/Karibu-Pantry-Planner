<?php
/**
 * One-time test: sends a welcome/acknowledgement email to every
 * active notification_email in the system. Delete after use.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

$db = getDB();
$emails = $db->query(
    "SELECT ne.*, k.name AS kitchen_name
     FROM notification_emails ne
     LEFT JOIN kitchens k ON k.id = ne.kitchen_id
     WHERE ne.is_active = 1
     ORDER BY ne.id"
)->fetchAll();

if (empty($emails)) {
    die("No active notification emails found.\n");
}

$results = [];
$appUrl  = APP_URL;

foreach ($emails as $rec) {
    $name        = htmlspecialchars($rec['name'],        ENT_QUOTES);
    $campName    = $rec['kitchen_name']
                   ? htmlspecialchars($rec['kitchen_name'], ENT_QUOTES)
                   : 'All Camps';
    $notifyLabel = match($rec['notify_on']) {
        'submit'  => 'When a chef submits an order',
        'fulfill' => 'When store fulfils an order',
        'both'    => 'On order submission AND fulfilment',
        default   => $rec['notify_on'],
    };

    $html = mailTemplate(
        'You\'re set up — Karibu Pantry Planner',
        "<p>Hi <strong>{$name}</strong>,</p>

        <p>This is a confirmation that your email address has been successfully added to the
        <strong>Karibu Pantry Planner</strong> notification system.</p>

        <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:13px'>
          <tr style='background:#f9fafb'>
            <td style='padding:8px 12px;border:1px solid #e5e7eb;color:#6b7280;width:40%'>Camp / Kitchen</td>
            <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600'>{$campName}</td>
          </tr>
          <tr>
            <td style='padding:8px 12px;border:1px solid #e5e7eb;color:#6b7280'>You will be notified</td>
            <td style='padding:8px 12px;border:1px solid #e5e7eb;font-weight:600'>{$notifyLabel}</td>
          </tr>
          <tr style='background:#f9fafb'>
            <td style='padding:8px 12px;border:1px solid #e5e7eb;color:#6b7280'>Receiving email</td>
            <td style='padding:8px 12px;border:1px solid #e5e7eb'>" . htmlspecialchars($rec['email'], ENT_QUOTES) . "</td>
          </tr>
        </table>

        <p>You don't need to do anything — notifications will arrive automatically whenever
        an order matches your camp and trigger. Each email includes the full order sheet.</p>

        <p style='color:#6b7280;font-size:13px'>If you believe this was added by mistake,
        please contact your Karibu system administrator.</p>",
        'View Pantry Planner',
        $appUrl . '/admin-login.php'
    );

    $sent = sendMail(trim($rec['email']), 'You\'re set up on Karibu Pantry Planner ✓', $html);
    $results[] = [
        'id'    => $rec['id'],
        'name'  => $rec['name'],
        'email' => $rec['email'],
        'camp'  => $rec['kitchen_name'] ?? 'All',
        'sent'  => $sent,
    ];

    // Small delay to avoid SMTP rate-limiting
    usleep(300000); // 0.3s between sends
}

// Output results
header('Content-Type: text/plain');
echo "=== Karibu Pantry Planner — Test Email Results ===\n\n";
$ok = $fail = 0;
foreach ($results as $r) {
    $status = $r['sent'] ? '✓ SENT' : '✗ FAILED';
    echo sprintf("%-3s  %-30s  %-40s  %s\n", $r['id'], $r['name'], $r['email'], $status);
    $r['sent'] ? $ok++ : $fail++;
}
echo "\n--- $ok sent, $fail failed ---\n";
