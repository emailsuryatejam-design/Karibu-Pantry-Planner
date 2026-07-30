<?php
/**
 * Admin — Kitchen Attendance / Usage Calendar
 * At-a-glance grid: rows = kitchens, columns = days, each day shows breakfast/lunch/dinner.
 *   green = ordered (submitted→closed) · amber = started but NOT submitted (processing) ·
 *   red = missed · grey = not due yet. Server-rendered (admin-only, read-only).
 */
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
$db = getDB();
date_default_timezone_set('Africa/Dar_es_Salaam');

$days = (int)($_GET['days'] ?? 14);
if (!in_array($days, [7, 14, 30], true)) $days = 14;

$today   = new DateTime('today');
$todayS  = $today->format('Y-m-d');
$from    = (clone $today)->modify('-' . ($days - 1) . ' days');
$fromS   = $from->format('Y-m-d');
$nowHM   = date('H:i');

$MEALS   = ['breakfast' => 'B', 'lunch' => 'L', 'dinner' => 'D'];
$CUTOFF  = ['breakfast' => '08:00', 'lunch' => '12:00', 'dinner' => '18:00'];
$RANK    = ['processing' => 1, 'submitted' => 2, 'fulfilled' => 3, 'received' => 4, 'closed' => 5];

$kitchens = [];
$map = [];
try {
    $kitchens = $db->query("SELECT id, name FROM kitchens WHERE is_active = 1 AND id <> 6 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $st = $db->prepare("SELECT kitchen_id, req_date, LOWER(meals) meal, status
        FROM requisitions
        WHERE deleted_at IS NULL AND status <> 'draft' AND kitchen_id <> 6
          AND req_date BETWEEN ? AND ?");
    $st->execute([$fromS, $todayS]);
    foreach ($st as $r) {
        $m = $r['meal'];
        if (!isset($MEALS[$m])) continue;
        $rk = $RANK[$r['status']] ?? 0;
        $k = (int)$r['kitchen_id']; $d = $r['req_date'];
        if (!isset($map[$k][$d][$m]) || $map[$k][$d][$m] < $rk) $map[$k][$d][$m] = $rk;
    }
} catch (Exception $e) {}

// Build the ordered list of dates (oldest → newest).
$dates = [];
for ($i = 0; $i < $days; $i++) $dates[] = (clone $from)->modify("+$i days");

/** Resolve one meal cell to a state. */
function att_state($rk, string $dateS, string $meal, string $todayS, string $nowHM, array $CUTOFF): string {
    if ($rk >= 2) return 'ok';      // submitted or beyond
    if ($rk === 1) return 'proc';   // built but not submitted to store
    if ($dateS < $todayS) return 'miss';
    if ($dateS === $todayS) return ($nowHM >= $CUTOFF[$meal]) ? 'miss' : 'pending';
    return 'pending';
}
$DOT = [
    'ok'      => 'background:#22c55e;',                    // green
    'proc'    => 'background:#f59e0b;',                    // amber
    'miss'    => 'background:#ef4444;',                    // red
    'pending' => 'background:#e5e7eb;border:1px solid #d1d5db;', // grey
];
$LABEL = ['ok' => 'ordered', 'proc' => 'started, not submitted', 'miss' => 'missed', 'pending' => 'not due yet'];
?>

<div class="mb-5 flex items-end justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-xl font-bold text-slate-100">Kitchen Attendance</h1>
        <p class="text-sm text-slate-400 mt-0.5">Who's ordering, who's missing — breakfast · lunch · dinner, per day.</p>
    </div>
    <div class="flex items-center gap-1.5">
        <?php foreach ([7, 14, 30] as $d): ?>
            <a href="app.php?page=admin-attendance&days=<?= $d ?>"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $days === $d ? 'bg-orange-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' ?>">
               <?= $d ?> days
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Legend -->
<div class="flex flex-wrap items-center gap-4 mb-4 text-xs text-slate-300">
    <?php foreach (['ok' => 'Ordered', 'proc' => 'Started, not submitted', 'miss' => 'Missed', 'pending' => 'Not due yet'] as $s => $lbl): ?>
        <span class="inline-flex items-center gap-1.5">
            <span style="width:12px;height:12px;border-radius:3px;display:inline-block;<?= $DOT[$s] ?>"></span><?= $lbl ?>
        </span>
    <?php endforeach; ?>
    <span class="text-slate-500">· each day shows <b>B</b> L D (breakfast · lunch · dinner)</span>
</div>

<?php if (!$kitchens): ?>
    <p class="text-slate-400 py-8 text-center">No active kitchens.</p>
<?php else: ?>
<div class="rounded-xl border border-slate-700 overflow-hidden" style="background:#1e293b">
  <div style="overflow-x:auto">
    <table style="border-collapse:collapse;width:100%;min-width:640px">
        <thead>
            <tr style="background:#0f172a">
                <th style="position:sticky;left:0;background:#0f172a;z-index:2;text-align:left;padding:10px 12px;font-size:12px;color:#94a3b8;min-width:170px">Kitchen</th>
                <?php foreach ($dates as $dt):
                    $isToday = $dt->format('Y-m-d') === $todayS; ?>
                    <th style="padding:8px 4px;font-size:10px;color:<?= $isToday ? '#fbbf24' : '#94a3b8' ?>;text-align:center;font-weight:600;white-space:nowrap">
                        <?= $dt->format('D') ?><br><span style="font-size:12px;color:<?= $isToday ? '#fbbf24' : '#e2e8f0' ?>"><?= $dt->format('j') ?></span>
                    </th>
                <?php endforeach; ?>
                <th style="padding:8px 10px;font-size:10px;color:#94a3b8;text-align:center;min-width:70px">Usage</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kitchens as $k):
                $kid = (int)$k['id'];
                $green = 0; $due = 0; ?>
                <tr style="border-top:1px solid #334155">
                    <td style="position:sticky;left:0;background:#1e293b;z-index:1;padding:10px 12px;font-size:13px;color:#e2e8f0;font-weight:600;white-space:nowrap"><?= htmlspecialchars($k['name']) ?></td>
                    <?php foreach ($dates as $dt):
                        $dS = $dt->format('Y-m-d'); ?>
                        <td style="padding:6px 4px;text-align:center;<?= $dS === $todayS ? 'background:#0f172a55' : '' ?>">
                            <div style="display:flex;gap:2px;justify-content:center">
                                <?php foreach ($MEALS as $mealCode => $letter):
                                    $rk = $map[$kid][$dS][$mealCode] ?? 0;
                                    $state = att_state($rk, $dS, $mealCode, $todayS, $nowHM, $CUTOFF);
                                    if ($state !== 'pending') $due++;
                                    if ($state === 'ok') $green++;
                                    $tip = $k['name'] . ' · ' . ucfirst($mealCode) . ' · ' . $dt->format('D j M') . ' — ' . $LABEL[$state]; ?>
                                    <span title="<?= htmlspecialchars($tip) ?>"
                                          style="width:11px;height:11px;border-radius:3px;display:inline-block;<?= $DOT[$state] ?>"></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    <?php endforeach; ?>
                    <?php
                        $pct = $due > 0 ? round($green / $due * 100) : 0;
                        $barColor = $pct >= 80 ? '#22c55e' : ($pct >= 50 ? '#f59e0b' : '#ef4444'); ?>
                    <td style="padding:8px 10px;text-align:center">
                        <div style="font-size:13px;font-weight:700;color:<?= $barColor ?>"><?= $pct ?>%</div>
                        <div style="height:4px;border-radius:2px;background:#334155;margin-top:3px;overflow:hidden">
                            <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</div>

<p class="text-xs text-slate-500 mt-3 leading-relaxed">
    <b class="text-slate-400">Usage %</b> = meals ordered ÷ meals due (today's not-yet-due meals are excluded).
    <b style="color:#f59e0b">Amber</b> means the chef built the order but never tapped <b>Submit to Store</b> — it never reached the storekeeper.
    Only breakfast · lunch · dinner are tracked here; other meal types (lunchboxes, picnics…) aren't counted.
</p>
<?php endif; ?>
