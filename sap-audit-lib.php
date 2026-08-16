<?php
/**
 * Shared SAP stock-audit computation.
 *
 * Used by BOTH the live admin page (pages/admin-stock-audit.php) and the daily email
 * automation (cron-sap-audit.php) so the two can never drift. Pure computation, no output,
 * read-only. Reads the daily snapshot tables (see sap-snapshot.php) + the app's own orders,
 * joined on items.code = SAP itemCode.
 */

/** kitchen_id => camp name + its SAP warehouses (store + bar) and in-transit warehouses. */
function sapCampMap(): array {
  return [
    1 => ['name' => 'Lions Paw', 'store' => ['LP', 'LPBAR'],       'transit' => ['INTLP']],
    4 => ['name' => 'River',     'store' => ['RC', 'RCBAR'],       'transit' => ['INTRC']],
    3 => ['name' => 'Sametu',    'store' => ['SAMETU', 'SMBAR'],   'transit' => ['INTSMT']],
    5 => ['name' => 'Tarangire', 'store' => ['TG', 'TGBAR'],       'transit' => ['INTTG']],
    2 => ['name' => 'Woodlands', 'store' => ['WOODLAND', 'WLBAR'], 'transit' => ['INTWL']],
  ];
}

/**
 * Build the full audit structure. Returns:
 *  ok, latest, prev, captured_at, items, recon_available,
 *  camps => [ kid => name, no_stock[], in_transit[], anomalies[], recon[] ]
 */
function sapAuditData(PDO $db): array {
  $out = ['ok' => false, 'latest' => null, 'prev' => null, 'captured_at' => null,
          'items' => 0, 'recon_available' => false, 'camps' => []];

  $latest = $db->query("SELECT snapshot_date FROM sap_snapshot_log WHERE ok=1 ORDER BY snapshot_date DESC LIMIT 1")->fetchColumn();
  if (!$latest) return $out;
  $out['ok'] = true; $out['latest'] = $latest;

  $lg = $db->prepare("SELECT finished_at, items FROM sap_snapshot_log WHERE snapshot_date=? AND ok=1 ORDER BY id DESC LIMIT 1");
  $lg->execute([$latest]); $lgr = $lg->fetch(PDO::FETCH_ASSOC) ?: [];
  $out['captured_at'] = $lgr['finished_at'] ?? null;
  $out['items'] = (int)($lgr['items'] ?? 0);

  $pv = $db->prepare("SELECT snapshot_date FROM sap_snapshot_log WHERE ok=1 AND snapshot_date<? ORDER BY snapshot_date DESC LIMIT 1");
  $pv->execute([$latest]); $prevDate = $pv->fetchColumn() ?: null;
  $out['prev'] = $prevDate; $out['recon_available'] = (bool)$prevDate;

  // stock maps: code => whs => qty
  $stock = []; $st = $db->prepare("SELECT item_code, whs_code, on_hand FROM sap_stock WHERE snapshot_date=?");
  $st->execute([$latest]);
  foreach ($st as $r) $stock[$r['item_code']][$r['whs_code']] = (float)$r['on_hand'];

  $meta = []; $mt = $db->prepare("SELECT item_code, item_name, uom FROM sap_stock_meta WHERE snapshot_date=?");
  $mt->execute([$latest]);
  foreach ($mt as $r) $meta[$r['item_code']] = ['name' => $r['item_name'] ?: $r['item_code'], 'uom' => $r['uom'] ?: ''];

  $pstock = [];
  if ($prevDate) {
    $ps = $db->prepare("SELECT item_code, whs_code, on_hand FROM sap_stock WHERE snapshot_date=?");
    $ps->execute([$prevDate]);
    foreach ($ps as $r) $pstock[$r['item_code']][$r['whs_code']] = (float)$r['on_hand'];
  }

  $sum = function ($map, $code, $whs) { $s = 0; if (isset($map[$code])) foreach ($whs as $w) $s += $map[$code][$w] ?? 0; return $s; };

  // open orders per camp/code (not yet fulfilled, today or later) — feasibility
  $openByCamp = [];
  $oq = $db->query("SELECT r.kitchen_id kid, i.code code, MIN(i.name) name, MIN(i.uom) uom, SUM(rl.order_qty) ordered
     FROM requisition_lines rl JOIN requisitions r ON r.id=rl.requisition_id JOIN items i ON i.id=rl.item_id
     WHERE r.deleted_at IS NULL AND rl.deleted_at IS NULL AND r.status IN ('submitted','processing')
       AND r.req_date >= CURDATE() AND rl.status <> 'rejected' AND i.code IS NOT NULL AND i.code<>''
     GROUP BY r.kitchen_id, i.code HAVING SUM(rl.order_qty) > 0");
  foreach ($oq as $r) $openByCamp[(int)$r['kid']][] = $r;

  // fulfilled per camp/code on the previous snapshot date — reconciliation baseline
  $ffPrev = [];
  if ($prevDate) {
    $fq = $db->prepare("SELECT r.kitchen_id kid, i.code code, SUM(rl.fulfilled_qty) ff
       FROM requisition_lines rl JOIN requisitions r ON r.id=rl.requisition_id JOIN items i ON i.id=rl.item_id
       WHERE r.deleted_at IS NULL AND rl.deleted_at IS NULL AND r.req_date=?
         AND i.code IS NOT NULL AND i.code<>'' GROUP BY r.kitchen_id, i.code");
    $fq->execute([$prevDate]);
    foreach ($fq as $r) $ffPrev[(int)$r['kid']][$r['code']] = (float)$r['ff'];
  }

  foreach (sapCampMap() as $kid => $c) {
    $noStock = []; $inTransit = []; $anomalies = []; $recon = [];

    // (1) feasibility — open-order items with nothing on hand at the camp
    foreach ($openByCamp[$kid] ?? [] as $o) {
      $code = $o['code'];
      if ($sum($stock, $code, $c['store']) <= 0 && $sum($stock, $code, $c['transit']) <= 0) {
        $noStock[] = ['name' => $o['name'] ?: $code, 'code' => $code, 'ordered' => (float)$o['ordered'],
                      'uom' => $o['uom'] ?: '', 'ho' => $stock[$code]['HO'] ?? 0];
      }
    }
    usort($noStock, fn($a, $b) => $b['ordered'] <=> $a['ordered']);

    // (2) in-transit right now (dispatched, not yet received into the camp store)
    foreach ($stock as $code => $_) {
      $t = $sum($stock, $code, $c['transit']);
      if ($t > 0) $inTransit[] = ['name' => $meta[$code]['name'] ?? $code, 'code' => $code, 'qty' => $t, 'uom' => $meta[$code]['uom'] ?? ''];
    }
    usort($inTransit, fn($a, $b) => $b['qty'] <=> $a['qty']);

    // (3) anomalies — negative on-hand anywhere in the camp's warehouses
    foreach (array_merge($c['store'], $c['transit']) as $w) {
      foreach ($stock as $code => $whmap) {
        if (isset($whmap[$w]) && $whmap[$w] < 0)
          $anomalies[] = ['name' => $meta[$code]['name'] ?? $code, 'code' => $code, 'whs' => $w, 'qty' => $whmap[$w]];
      }
    }

    // (4) reconciliation — net store movement (prev → latest) vs app-recorded fulfilment.
    //     ADVISORY: net change conflates receipts/issues/transfers, and SAP vs app units may differ.
    if ($prevDate) {
      $codes = [];
      foreach ($stock as $code => $_) if ($sum($stock, $code, $c['store']) != 0) $codes[$code] = 1;
      foreach ($pstock as $code => $_) if ($sum($pstock, $code, $c['store']) != 0) $codes[$code] = 1;
      foreach (($ffPrev[$kid] ?? []) as $code => $_) $codes[$code] = 1;
      foreach (array_keys($codes) as $code) {
        $moved = $sum($pstock, $code, $c['store']) - $sum($stock, $code, $c['store']); // >0 = left the store
        $ff = $ffPrev[$kid][$code] ?? 0;
        if (abs($moved) < 0.001 && $ff == 0) continue;
        $recon[] = ['name' => $meta[$code]['name'] ?? $code, 'code' => $code,
                    'moved' => $moved, 'fulfilled' => $ff, 'gap' => $moved - $ff];
      }
      usort($recon, fn($a, $b) => abs($b['gap']) <=> abs($a['gap']));
    }

    $out['camps'][$kid] = ['name' => $c['name'], 'no_stock' => $noStock, 'in_transit' => $inTransit,
                           'anomalies' => $anomalies, 'recon' => $recon];
  }
  return $out;
}
