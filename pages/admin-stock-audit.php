<?php
/**
 * Admin — Daily Stock Audit (live). Cross-checks each camp's open orders and stock against
 * the daily SAP snapshot (sap-snapshot.php), joined on items.code = SAP itemCode.
 * Recomputed on every visit. Admin-only, read-only. Same brain as the daily email
 * (cron-sap-audit.php) via sap-audit-lib.php.
 */
if (!isAdmin()) { echo '<p class="text-center text-red-500 py-8">Admin access required</p>'; return; }
require_once __DIR__ . '/../sap-audit-lib.php';
$db = getDB();
date_default_timezone_set('Africa/Dar_es_Salaam');
$A = sapAuditData($db);

function sa_fmt($n) { $s = number_format((float)$n, 1); return rtrim(rtrim($s, '0'), '.'); }
?>

<div class="mb-5 flex items-end justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-xl font-bold text-slate-100">Daily Stock Audit</h1>
        <p class="text-sm text-slate-400 mt-0.5">Each camp's orders &amp; stock checked against the SAP snapshot. Live, recomputed each visit.</p>
    </div>
</div>

<?php if (!$A['ok']): ?>
    <div class="rounded-xl border border-slate-700 p-6 text-center" style="background:#1e293b">
        <p class="text-slate-300 text-sm">No SAP snapshot yet. The first daily pull hasn't stored data — check back after the snapshot runs.</p>
    </div>
    <?php return; endif; ?>

<?php
$capt = $A['captured_at'] ? date('D d M, H:i', strtotime($A['captured_at'])) : $A['latest'];
$reconOn = $A['recon_available'];
?>
<div class="rounded-xl border border-slate-700 px-4 py-3 mb-4 flex items-center justify-between flex-wrap gap-2" style="background:#0f172a">
    <div class="text-xs text-slate-400">
        SAP snapshot <b class="text-slate-200"><?= htmlspecialchars($capt) ?> EAT</b> ·
        <?= number_format($A['items']) ?> items
        <?php if ($reconOn): ?>· movement vs <b class="text-slate-200"><?= htmlspecialchars($A['prev']) ?></b><?php endif; ?>
    </div>
    <?php if (!$reconOn): ?>
        <span class="text-[11px] px-2 py-1 rounded-lg" style="background:#1e293b;color:#94a3b8">Reconciliation activates with the next daily snapshot</span>
    <?php endif; ?>
</div>

<?php
// summary strip
$tNo = 0; $tTr = 0; $tAn = 0;
foreach ($A['camps'] as $c) { $tNo += count($c['no_stock']); $tTr += count($c['in_transit']); $tAn += count($c['anomalies']); }
?>
<div class="grid gap-2 mb-5" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
    <div class="rounded-xl border border-slate-700 px-4 py-3" style="background:#1e293b">
        <div class="text-2xl font-bold" style="color:<?= $tNo ? '#ef4444' : '#22c55e' ?>"><?= $tNo ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5">ordered · none in stock</div>
    </div>
    <div class="rounded-xl border border-slate-700 px-4 py-3" style="background:#1e293b">
        <div class="text-2xl font-bold" style="color:<?= $tTr ? '#f59e0b' : '#64748b' ?>"><?= $tTr ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5">items in transit</div>
    </div>
    <div class="rounded-xl border border-slate-700 px-4 py-3" style="background:#1e293b">
        <div class="text-2xl font-bold" style="color:<?= $tAn ? '#ef4444' : '#22c55e' ?>"><?= $tAn ?></div>
        <div class="text-[11px] text-slate-400 mt-0.5">stock anomalies</div>
    </div>
</div>

<?php foreach ($A['camps'] as $kid => $c): ?>
    <div class="rounded-xl border border-slate-700 overflow-hidden mb-4" style="background:#1e293b">
        <div class="px-4 py-3 flex items-center justify-between" style="background:#0f172a">
            <h2 class="text-sm font-bold text-slate-100"><?= htmlspecialchars($c['name']) ?></h2>
            <div class="flex items-center gap-1.5 text-[10px] font-semibold">
                <span class="px-2 py-0.5 rounded-full" style="background:<?= count($c['no_stock']) ? '#7f1d1d' : '#1e293b' ?>;color:<?= count($c['no_stock']) ? '#fecaca' : '#64748b' ?>"><?= count($c['no_stock']) ?> no-stock</span>
                <span class="px-2 py-0.5 rounded-full" style="background:<?= count($c['in_transit']) ? '#78350f' : '#1e293b' ?>;color:<?= count($c['in_transit']) ? '#fde68a' : '#64748b' ?>"><?= count($c['in_transit']) ?> transit</span>
                <span class="px-2 py-0.5 rounded-full" style="background:<?= count($c['anomalies']) ? '#7f1d1d' : '#1e293b' ?>;color:<?= count($c['anomalies']) ? '#fecaca' : '#64748b' ?>"><?= count($c['anomalies']) ?> anomaly</span>
            </div>
        </div>
        <div class="p-4 space-y-4">

            <!-- ordered but no stock -->
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide mb-1.5" style="color:#f87171">Ordered — nothing on hand at camp</div>
                <?php if (!$c['no_stock']): ?>
                    <div class="text-xs text-slate-500">All open-order items have stock at the camp. ✓</div>
                <?php else: foreach ($c['no_stock'] as $x): ?>
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-700/50">
                        <span class="text-slate-200 truncate pr-2"><?= htmlspecialchars($x['name']) ?></span>
                        <span class="shrink-0 text-slate-400">ordered <b class="text-slate-200"><?= sa_fmt($x['ordered']) ?></b> <?= htmlspecialchars($x['uom']) ?>
                            · <?= $x['ho'] > 0 ? 'HO holds <b style="color:#fbbf24">' . sa_fmt($x['ho']) . '</b> (transfer)' : '<b style="color:#f87171">none at HO either</b>' ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- in transit -->
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide mb-1.5" style="color:#fbbf24">In transit to camp now</div>
                <?php if (!$c['in_transit']): ?>
                    <div class="text-xs text-slate-500">Nothing in transit.</div>
                <?php else:
                    foreach (array_slice($c['in_transit'], 0, 12) as $x): ?>
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-700/50">
                        <span class="text-slate-200 truncate pr-2"><?= htmlspecialchars($x['name']) ?></span>
                        <span class="shrink-0 text-slate-400"><b class="text-slate-200"><?= sa_fmt($x['qty']) ?></b> <?= htmlspecialchars($x['uom']) ?></span>
                    </div>
                    <?php endforeach;
                    if (count($c['in_transit']) > 12): ?>
                        <div class="text-[11px] text-slate-500 mt-1">+ <?= count($c['in_transit']) - 12 ?> more in transit</div>
                    <?php endif; endif; ?>
            </div>

            <!-- anomalies -->
            <?php if ($c['anomalies']): ?>
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide mb-1.5" style="color:#f87171">Anomalies (negative stock)</div>
                <?php foreach ($c['anomalies'] as $x): ?>
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-700/50">
                        <span class="text-slate-200 truncate pr-2"><?= htmlspecialchars($x['name']) ?></span>
                        <span class="shrink-0" style="color:#f87171"><?= htmlspecialchars($x['whs']) ?> = <b><?= sa_fmt($x['qty']) ?></b></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- reconciliation -->
            <?php if ($reconOn): ?>
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide mb-1.5" style="color:#94a3b8">Movement vs recorded fulfilment <span class="normal-case font-normal text-slate-500">(advisory · net change, units may differ)</span></div>
                <?php if (!$c['recon']): ?>
                    <div class="text-xs text-slate-500">No stock movement recorded for this camp.</div>
                <?php else:
                    foreach (array_slice($c['recon'], 0, 10) as $x):
                        $gap = $x['gap']; $big = abs($gap) > 0.001;
                        $col = !$big ? '#22c55e' : (abs($gap) > 5 ? '#f87171' : '#fbbf24'); ?>
                    <div class="flex items-center justify-between text-xs py-1 border-b border-slate-700/50">
                        <span class="text-slate-200 truncate pr-2"><?= htmlspecialchars($x['name']) ?></span>
                        <span class="shrink-0 text-slate-400">stock moved <b class="text-slate-200"><?= sa_fmt($x['moved']) ?></b>
                            · app <b class="text-slate-200"><?= sa_fmt($x['fulfilled']) ?></b>
                            · gap <b style="color:<?= $col ?>"><?= sa_fmt($gap) ?></b></span>
                    </div>
                    <?php endforeach; endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
<?php endforeach; ?>

<div class="mt-4 grid gap-2 text-xs text-slate-400" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
    <div><b class="text-slate-300">Ordered — nothing on hand</b> — an open order (today+) whose item shows 0 across the camp's store, bar and in-transit. If HO holds it, an internal transfer covers it; if not, a real shortage.</div>
    <div><b class="text-slate-300">In transit</b> — dispatched to the camp but not yet received into its store (SAP <code>INT*</code>).</div>
    <div><b class="text-slate-300">Anomalies</b> — negative on-hand in SAP = a data or counting error to chase.</div>
    <div><b class="text-slate-300">Movement vs fulfilment</b> — how much the camp's store fell since the last snapshot vs what the app recorded the store issuing. A gap is a lead to investigate — net change also includes deliveries, and SAP/app units can differ.</div>
</div>
<p class="text-xs text-slate-500 mt-3">Joined on <code>items.code = SAP itemCode</code>. Snapshot pulled daily; this page reads the latest.</p>
