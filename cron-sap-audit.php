<?php
/**
 * Karibu — daily SAP stock-audit email.
 *
 * Emails a per-camp digest of the flags computed by sap-audit-lib.php (same brain as the live
 * admin page). Runs once a day AFTER the snapshot. CLI ONLY.
 *
 * Usage: php cron-sap-audit.php [--dry] [--force] [--to=email]
 *   --dry    build + print the digest, send nothing
 *   --force  ignore the once-a-day guard (re-send)
 *   --to=x   send only to x (testing)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); } // CLI only
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/sap-audit-lib.php';
date_default_timezone_set('Africa/Dar_es_Salaam');

// Recipients during rollout: the owner. Add camp admins here once the feature is signed off.
$RECIPIENTS = ['emailsuryateja.m@gmail.com'];

$dry = false; $force = false; $testTo = null;
foreach (array_slice($argv, 1) as $a) {
  if ($a === '--dry') $dry = true;
  elseif ($a === '--force') $force = true;
  elseif (str_starts_with($a, '--to=')) $testTo = substr($a, 5);
}

$db = getDB();
$A = sapAuditData($db);
if (!$A['ok']) { echo "No SAP snapshot yet — nothing to audit.\n"; exit(0); }

function ca_fmt($n) { $s = number_format((float)$n, 1); return rtrim(rtrim($s, '0'), '.'); }
function ca_esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// ---- build digest ----
$tNo = 0; $tTr = 0; $tAn = 0;
foreach ($A['camps'] as $c) { $tNo += count($c['no_stock']); $tTr += count($c['in_transit']); $tAn += count($c['anomalies']); }

$capt = $A['captured_at'] ? date('D d M, H:i', strtotime($A['captured_at'])) : $A['latest'];
$row = fn($l, $r) => '<tr><td style="padding:3px 8px 3px 0;color:#334155">' . $l . '</td><td style="padding:3px 0;color:#64748b;text-align:right">' . $r . '</td></tr>';

$c = '<p style="margin:0 0 4px;color:#475569;font-size:13px">SAP snapshot <b>' . ca_esc($capt) . ' EAT</b> · ' . number_format($A['items']) . ' items'
   . ($A['recon_available'] ? ' · movement vs ' . ca_esc($A['prev']) : ' · reconciliation activates with the next snapshot')
   . '</p>';
$c .= '<p style="margin:0 0 14px;font-size:14px"><b>' . $tNo . '</b> ordered-but-no-stock · <b>' . $tTr . '</b> in transit · <b>' . $tAn . '</b> anomalies</p>';

foreach ($A['camps'] as $kid => $cp) {
  $has = count($cp['no_stock']) + count($cp['anomalies']);
  $reconBig = 0;
  foreach ($cp['recon'] as $x) if (abs($x['gap']) > 5) $reconBig++;
  $c .= '<div style="margin:0 0 12px;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden">';
  $c .= '<div style="background:#0f172a;color:#fff;padding:8px 12px;font-weight:700;font-size:13px">' . ca_esc($cp['name'])
      . '<span style="float:right;font-weight:400;color:#94a3b8">' . count($cp['no_stock']) . ' no-stock · ' . count($cp['in_transit']) . ' transit · ' . count($cp['anomalies']) . ' anomaly</span></div>';
  $c .= '<div style="padding:10px 12px;font-size:12px">';

  if (!$has && !$reconBig) {
    $c .= '<span style="color:#16a34a">✓ All open orders covered, no anomalies.</span>';
  } else {
    if ($cp['no_stock']) {
      $c .= '<div style="color:#dc2626;font-weight:600;margin:0 0 4px">Ordered — nothing on hand at camp</div><table style="width:100%;border-collapse:collapse;margin:0 0 8px">';
      foreach (array_slice($cp['no_stock'], 0, 12) as $x)
        $c .= $row(ca_esc($x['name']), 'ordered <b>' . ca_fmt($x['ordered']) . '</b> ' . ca_esc($x['uom']) . ' · ' . ($x['ho'] > 0 ? 'HO holds ' . ca_fmt($x['ho']) : 'none at HO'));
      $c .= '</table>';
    }
    if ($cp['anomalies']) {
      $c .= '<div style="color:#dc2626;font-weight:600;margin:0 0 4px">Anomalies (negative stock)</div><table style="width:100%;border-collapse:collapse;margin:0 0 8px">';
      foreach (array_slice($cp['anomalies'], 0, 12) as $x)
        $c .= $row(ca_esc($x['name']), ca_esc($x['whs']) . ' = <b>' . ca_fmt($x['qty']) . '</b>');
      $c .= '</table>';
    }
    if ($reconBig) {
      $c .= '<div style="color:#64748b;font-weight:600;margin:0 0 4px">Movement vs recorded fulfilment (advisory)</div><table style="width:100%;border-collapse:collapse;margin:0 0 8px">';
      foreach (array_slice($cp['recon'], 0, 8) as $x) { if (abs($x['gap']) <= 5) continue;
        $c .= $row(ca_esc($x['name']), 'moved <b>' . ca_fmt($x['moved']) . '</b> · app <b>' . ca_fmt($x['fulfilled']) . '</b> · gap <b>' . ca_fmt($x['gap']) . '</b>'); }
      $c .= '</table>';
    }
  }
  if ($cp['in_transit']) $c .= '<div style="color:#94a3b8;font-size:11px">' . count($cp['in_transit']) . ' item(s) in transit to this camp.</div>';
  $c .= '</div></div>';
}

$subject = 'Karibu Stock Audit — ' . date('D d M', strtotime($A['latest'])) . ': ' . $tNo . ' no-stock, ' . $tAn . ' anomalies';
$html = mailTemplate('Daily Stock Audit', $c, 'Open the live audit', 'https://palegoldenrod-coyote-386848.hostingersite.com/app.php?page=admin-stock-audit');

if ($dry) { echo "DRY RUN — subject: $subject\n" . strip_tags(str_replace(['</div>', '</tr>'], "\n", $c)) . "\n"; exit(0); }

// once-a-day guard (unless forced): don't double-send for the same snapshot date
$key = 'sapaudit:' . $A['latest'];
if (!$force && !$testTo && function_exists('cacheGet') && cacheGet($key, 72000)) {
  echo "Already sent the audit for {$A['latest']} today — skipping (use --force).\n"; exit(0);
}

$recips = $testTo ? [$testTo] : $RECIPIENTS;
$ok = true;
foreach ($recips as $to) { $sent = sendMail($to, $subject, $html); $ok = $ok && $sent; echo ($sent ? 'sent' : 'FAILED') . " -> $to\n"; }
if ($ok && !$testTo && function_exists('cacheSet')) cacheSet($key, '1');
echo ($ok ? "OK" : "SOME FAILED") . " — $subject\n";
exit($ok ? 0 : 1);
