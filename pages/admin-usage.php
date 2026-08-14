<?php
/**
 * Admin — Camp Usage Scorecard (live).
 * The full lifecycle per camp over the last N days: ordered → reaches store → fulfilled → closed,
 * plus how often they order on time. Recomputed on every page load. Admin-only, read-only.
 */
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
$db = getDB();
date_default_timezone_set('Africa/Dar_es_Salaam');

$days = (int)($_GET['days'] ?? 14);
if (!in_array($days, [7, 14, 30], true)) $days = 14;

// DB stores times in its own tz (UTC on this host); the app runs in EAT. Work out the hours to add
// to created_at so order times line up with the EAT meal cutoffs. Auto-adapts if the server tz changes.
$offset = 3;
try { $offset = (int) round((time() - strtotime($db->query("SELECT NOW()")->fetchColumn())) / 3600); } catch (Exception $e) {}

$fromS = (new DateTime('today'))->modify('-' . $days . ' days')->format('Y-m-d');
$toS   = (new DateTime('today'))->modify('-1 day')->format('Y-m-d');   // completed days only

$cut = "CASE r.meals WHEN 'breakfast' THEN '08:00:00' WHEN 'lunch' THEN '12:00:00' ELSE '18:00:00' END";
$eat = "(r.created_at + INTERVAL $offset HOUR)";
$dl  = "TIMESTAMP(r.req_date, $cut)";

$byId = [];
try {
    $st = $db->prepare("SELECT k.id,
        COUNT(*) ordered, COUNT(DISTINCT r.req_date) days_active,
        SUM($eat <= $dl) ontime,
        SUM(r.status IN ('submitted','fulfilled','received','closed')) reached,
        SUM(r.status IN ('fulfilled','received','closed')) fulfilled,
        SUM(r.status IN ('received','closed')) closed,
        SUM(r.status = 'processing') stuck
        FROM requisitions r JOIN kitchens k ON k.id = r.kitchen_id
        WHERE r.kitchen_id <> 6 AND r.deleted_at IS NULL AND r.status <> 'draft'
          AND r.meals IN ('breakfast','lunch','dinner') AND r.req_date BETWEEN ? AND ?
        GROUP BY k.id");
    $st->execute([$fromS, $toS]);
    foreach ($st as $r) $byId[(int)$r['id']] = $r;
} catch (Exception $e) {}

$camps = $db->query("SELECT id, name FROM kitchens WHERE is_active = 1 AND id <> 6 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
// order best → worst by fulfilment rate, dormant camps last
usort($camps, function ($a, $b) use ($byId) {
    $fa = ($byId[$a['id']]['ordered'] ?? 0) ? ($byId[$a['id']]['fulfilled'] / $byId[$a['id']]['ordered']) : -1;
    $fb = ($byId[$b['id']]['ordered'] ?? 0) ? ($byId[$b['id']]['fulfilled'] / $byId[$b['id']]['ordered']) : -1;
    return $fb <=> $fa;
});

function up_pct($n, $d) { return $d > 0 ? (int) round(100 * $n / $d) : 0; }
function up_cell($p) {
    $c = $p >= 80 ? '#22c55e' : ($p >= 50 ? '#f59e0b' : '#ef4444');
    return "<td style='text-align:center;font-weight:700;color:$c;padding:10px 8px'>{$p}%</td>";
}
$expected = $days * 3; // core meals possible per camp over the window
?>

<div class="mb-5 flex items-end justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-xl font-bold text-slate-100">Camp Usage Scorecard</h1>
        <p class="text-sm text-slate-400 mt-0.5">The full lifecycle per camp — ordered → reaches store → fulfilled → closed. Live, recomputed each visit.</p>
    </div>
    <div class="flex items-center gap-1.5">
        <?php foreach ([7, 14, 30] as $d): ?>
            <a href="app.php?page=admin-usage&days=<?= $d ?>"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $days === $d ? 'bg-orange-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' ?>">
               <?= $d ?> days
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="rounded-xl border border-slate-700 overflow-hidden" style="background:#1e293b">
  <div style="overflow-x:auto">
    <table style="border-collapse:collapse;width:100%;min-width:720px">
        <thead>
            <tr style="background:#0f172a;color:#94a3b8;font-size:12px">
                <th style="position:sticky;left:0;background:#0f172a;z-index:2;text-align:left;padding:10px 12px;min-width:180px">Camp</th>
                <th style="padding:10px 8px" title="Core meals ordered vs possible (<?= $expected ?>)">Ordered</th>
                <th style="padding:10px 8px" title="Ordered before the meal's cutoff">On&nbsp;time</th>
                <th style="padding:10px 8px" title="Submitted so the store can see it">Reaches store</th>
                <th style="padding:10px 8px" title="Store marked it sent">Fulfilled</th>
                <th style="padding:10px 8px" title="Received + day-closed">Closed</th>
                <th style="padding:10px 8px" title="Built but never Submitted — the store never sees these">Stuck</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($camps as $k):
                $r = $byId[(int)$k['id']] ?? null;
                if (!$r || (int)$r['ordered'] === 0): ?>
                    <tr style="border-top:1px solid #334155">
                        <td style="position:sticky;left:0;background:#1e293b;z-index:1;padding:10px 12px;font-size:13px;color:#e2e8f0;font-weight:600;white-space:nowrap"><?= htmlspecialchars($k['name']) ?></td>
                        <td colspan="6" style="text-align:center;color:#ef4444;font-size:12px;padding:10px">Dormant — no orders in <?= $days ?> days</td>
                    </tr>
                    <?php continue; endif; ?>
                <tr style="border-top:1px solid #334155">
                    <td style="position:sticky;left:0;background:#1e293b;z-index:1;padding:10px 12px;font-size:13px;color:#e2e8f0;font-weight:600;white-space:nowrap"><?= htmlspecialchars($k['name']) ?></td>
                    <td style="text-align:center;color:#e2e8f0;padding:10px 8px"><b><?= (int)$r['ordered'] ?></b><span style="color:#64748b;font-size:11px"> / <?= $expected ?></span><div style="color:#64748b;font-size:10px"><?= (int)$r['days_active'] ?>/<?= $days ?> days</div></td>
                    <?= up_cell(up_pct($r['ontime'], $r['ordered'])) ?>
                    <?= up_cell(up_pct($r['reached'], $r['ordered'])) ?>
                    <?= up_cell(up_pct($r['fulfilled'], $r['ordered'])) ?>
                    <?= up_cell(up_pct($r['closed'], $r['ordered'])) ?>
                    <td style="text-align:center;padding:10px 8px;font-weight:700;color:<?= (int)$r['stuck'] > 0 ? '#f59e0b' : '#475569' ?>"><?= (int)$r['stuck'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
  </div>
</div>

<div class="mt-4 grid gap-2 text-xs text-slate-400" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
    <div><b class="text-slate-300">Ordered</b> — core meals (B/L/D) placed vs the <?= $expected ?> possible this window.</div>
    <div><b class="text-slate-300">On time</b> — placed before that meal's cutoff (breakfast 08:00, lunch 12:00, dinner 18:00).</div>
    <div><b class="text-slate-300">Reaches store</b> — the chef tapped <i>Submit</i> so the storekeeper can see it.</div>
    <div><b class="text-slate-300">Fulfilled</b> — the store marked it sent.</div>
    <div><b class="text-slate-300">Closed</b> — received and day-closed (the full loop).</div>
    <div><b style="color:#f59e0b">Stuck</b> — orders built but <b>never submitted</b> — the store never sees them.</div>
</div>
<p class="text-xs text-slate-500 mt-3">Green ≥ 80% · amber 50–79% · red &lt; 50%. Window: last <?= $days ?> completed days (<?= $fromS ?> → <?= $toS ?>). Order times shown in EAT.</p>
