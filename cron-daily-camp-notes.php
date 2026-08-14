<?php
/**
 * Daily per-camp operations report.
 *   php cron-daily-camp-notes.php         → JSON (machine data)
 *   php cron-daily-camp-notes.php html    → a clean, color-coded HTML page anyone can read
 * Read-only. Excludes Demo Kitchen (id 6).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); } // CLI only — no web access
require __DIR__ . '/config.php';
$db = getDB();

$C = "('submitted','processing','fulfilled','received','closed')";
$today = $db->query("SELECT CURDATE()")->fetchColumn();
$tomorrow = $db->query("SELECT CURDATE() + INTERVAL 1 DAY")->fetchColumn();
$mode = $argv[1] ?? 'json';

$camps = [];
$kitchens = $db->query("SELECT id, name FROM kitchens WHERE id <> 6 AND is_active = 1 ORDER BY id")->fetchAll();
foreach ($kitchens as $k) {
    $kid = (int)$k['id'];
    $q = function($sql, $p = []) use ($db, $kid) { $s = $db->prepare($sql); $s->execute(array_merge([$kid], $p)); return $s; };
    $last = $db->query("SELECT DATE(MAX(GREATEST(created_at,updated_at))) FROM requisitions WHERE kitchen_id=$kid AND deleted_at IS NULL AND status IN $C")->fetchColumn();
    $daysAgo = $last ? (int)$db->query("SELECT DATEDIFF(CURDATE(),'$last')")->fetchColumn() : null;
    $byDay = [];
    foreach (['today' => $today, 'tomorrow' => $tomorrow] as $label => $d) {
        $rows = $q("SELECT meals, status FROM requisitions WHERE kitchen_id=? AND req_date=? AND deleted_at IS NULL AND status <> 'draft' ORDER BY session_number", [$d])->fetchAll();
        $byDay[$label] = array_map(fn($r) => $r['meals'] . ':' . $r['status'], $rows);
    }
    $c = [
        'name' => $k['name'],
        'last_active' => $last ?: null,
        'days_since_active' => $daysAgo,
        'submitted_today' => $byDay['today'],
        'submitted_tomorrow' => $byDay['tomorrow'],
        'awaiting_receipt' => (int)$q("SELECT COUNT(*) FROM requisitions WHERE kitchen_id=? AND deleted_at IS NULL AND status='fulfilled'")->fetchColumn(),
        'not_day_closed_yest' => (int)$q("SELECT COUNT(*) FROM requisitions WHERE kitchen_id=? AND deleted_at IS NULL AND req_date=CURDATE()-INTERVAL 1 DAY AND status IN ('fulfilled','received')")->fetchColumn(),
        'drafts_with_items' => (int)$q("SELECT COUNT(*) FROM requisitions r WHERE r.kitchen_id=? AND r.deleted_at IS NULL AND r.status='draft' AND EXISTS(SELECT 1 FROM requisition_lines l WHERE l.requisition_id=r.id AND l.deleted_at IS NULL)")->fetchColumn(),
        'open_disputes' => (int)$q("SELECT COUNT(*) FROM requisitions WHERE kitchen_id=? AND deleted_at IS NULL AND has_dispute=1 AND status <> 'closed'")->fetchColumn(),
        'raw_unit_lines_3d' => (int)$q("SELECT COUNT(*) FROM requisition_lines rl JOIN requisitions r ON r.id=rl.requisition_id WHERE r.kitchen_id=? AND r.deleted_at IS NULL AND r.status IN $C AND rl.deleted_at IS NULL AND r.req_date>=CURDATE()-INTERVAL 3 DAY AND LOWER(TRIM(rl.uom)) IN ('g','grams','ml')")->fetchColumn(),
    ];
    $c['status'] = $last === null ? 'never used' : ($daysAgo <= 1 ? 'active' : ($daysAgo <= 3 ? 'slowing' : 'dormant'));
    // Attention flags (today-actionable)
    $needs = [];
    if ($c['status'] === 'never used') $needs[] = 'Never placed an order — check if this camp is live';
    elseif ($c['status'] === 'dormant') $needs[] = "Gone quiet — last used {$daysAgo} days ago";
    elseif ($c['status'] === 'slowing') $needs[] = "Slowing down — last used {$daysAgo} days ago";
    if ($c['status'] !== 'never used' && empty($c['submitted_today'])) $needs[] = "No orders submitted for today yet";
    if ($c['not_day_closed_yest'] > 0) $needs[] = "{$c['not_day_closed_yest']} of yesterday's orders not day-closed";
    if ($c['open_disputes'] > 0) $needs[] = "{$c['open_disputes']} open dispute(s)";
    $c['needs'] = $needs;
    $camps[] = $c;
}
$out = ['generated' => date('Y-m-d H:i'), 'today' => $today, 'tomorrow' => $tomorrow, 'camps' => $camps];

if ($mode !== 'html') { echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); exit; }

/* ---------- HTML report (self-contained, email-safe inline styles) ---------- */
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function mealsPretty($list){
    if (!$list) return '<span style="color:#c0392b;font-weight:600">— none —</span>';
    $out = [];
    foreach ($list as $ms){ [$m,$s]=explode(':',$ms); $out[] = ucfirst($m); }
    return e(implode(', ', $out));
}
$statusStyle = [
    'active'     => ['#15803d','#dcfce7','On track'],
    'slowing'    => ['#b45309','#fef3c7','Slowing'],
    'dormant'    => ['#b91c1c','#fee2e2','Gone quiet'],
    'never used' => ['#6b7280','#f3f4f6','Never used'],
];
$niceDate = date('l, j M Y', strtotime($today));

// collect all follow-ups
$followups = [];
foreach ($camps as $c) foreach ($c['needs'] as $n) $followups[] = ['camp'=>$c['name'],'note'=>$n];

ob_start(); ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Karibu Kitchen — Daily Ops <?=e($today)?></title></head>
<body style="margin:0;background:#f4f5f7;font-family:-apple-system,Segoe UI,Arial,sans-serif;color:#1f2937">
<div style="max-width:760px;margin:0 auto;padding:16px">
  <div style="background:#ea580c;color:#fff;border-radius:14px 14px 0 0;padding:18px 22px">
    <div style="font-size:20px;font-weight:800">Karibu Camps — Daily Kitchen Report</div>
    <div style="font-size:13px;opacity:.9;margin-top:2px"><?=e($niceDate)?> &nbsp;·&nbsp; generated <?=e($out['generated'])?></div>
  </div>

  <?php if ($followups): ?>
  <div style="background:#fff;border:1px solid #fde68a;border-top:3px solid #f59e0b;padding:16px 22px">
    <div style="font-size:13px;font-weight:800;color:#b45309;text-transform:uppercase;letter-spacing:.4px">⚠ Follow up today</div>
    <ul style="margin:8px 0 0;padding-left:18px;font-size:14px;line-height:1.6">
      <?php foreach ($followups as $f): ?>
        <li><b><?=e($f['camp'])?>:</b> <?=e($f['note'])?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php else: ?>
  <div style="background:#fff;border:1px solid #bbf7d0;border-top:3px solid #22c55e;padding:16px 22px;font-size:14px;font-weight:600;color:#15803d">✓ All camps on track — nothing needs attention.</div>
  <?php endif; ?>

  <!-- at a glance -->
  <table style="width:100%;border-collapse:collapse;background:#fff;font-size:13px">
    <thead>
      <tr style="background:#f9fafb;color:#6b7280;text-align:left">
        <th style="padding:10px 22px;font-size:11px;text-transform:uppercase;letter-spacing:.4px">Camp</th>
        <th style="padding:10px 8px;font-size:11px;text-transform:uppercase">Status</th>
        <th style="padding:10px 8px;font-size:11px;text-transform:uppercase">Today</th>
        <th style="padding:10px 8px;font-size:11px;text-transform:uppercase">Tomorrow</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($camps as $c): $st=$statusStyle[$c['status']]; ?>
      <tr style="border-top:1px solid #f0f0f0">
        <td style="padding:12px 22px;font-weight:700"><?=e($c['name'])?></td>
        <td style="padding:12px 8px"><span style="background:<?=$st[1]?>;color:<?=$st[0]?>;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700;white-space:nowrap"><?=e($st[2])?></span></td>
        <td style="padding:12px 8px"><?=mealsPretty($c['submitted_today'])?></td>
        <td style="padding:12px 8px"><?=mealsPretty($c['submitted_tomorrow'])?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- per-camp detail -->
  <?php foreach ($camps as $c): $st=$statusStyle[$c['status']]; ?>
    <div style="background:#fff;border-top:1px solid #f0f0f0;padding:14px 22px">
      <div style="font-weight:800;font-size:15px"><?=e($c['name'])?>
        <span style="background:<?=$st[1]?>;color:<?=$st[0]?>;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:700;margin-left:6px;vertical-align:middle"><?=e($st[2])?></span></div>
      <div style="font-size:13px;color:#4b5563;margin-top:6px;line-height:1.7">
        <?php if ($c['last_active']): ?>Last used <?=e($c['last_active'])?> (<?=$c['days_since_active']?> day<?=$c['days_since_active']==1?'':'s'?> ago).<?php else: ?>Has never placed an order.<?php endif; ?>
        Ordered for today: <?=mealsPretty($c['submitted_today'])?>.
        <?php
          $chips = [];
          if ($c['awaiting_receipt'] > 0) $chips[] = "{$c['awaiting_receipt']} awaiting the chef's receipt confirmation";
          if ($c['not_day_closed_yest'] > 0) $chips[] = "{$c['not_day_closed_yest']} not day-closed from yesterday";
          if ($c['drafts_with_items'] > 0) $chips[] = "{$c['drafts_with_items']} unsent draft(s) with items";
          if ($c['open_disputes'] > 0) $chips[] = "{$c['open_disputes']} open dispute(s)";
          if ($c['raw_unit_lines_3d'] > 0) $chips[] = "{$c['raw_unit_lines_3d']} line(s) still in grams/ml";
          if ($chips) echo '<br><span style="color:#92400e">Notes: '.e(implode(' · ', $chips)).'</span>';
        ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div style="background:#fff;border-radius:0 0 14px 14px;border-top:1px solid #f0f0f0;padding:12px 22px;font-size:11px;color:#9ca3af">
    Karibu Pantry Planner · automated daily report · Demo Kitchen excluded · read-only
  </div>
</div>
</body></html>
<?php
echo ob_get_clean();
