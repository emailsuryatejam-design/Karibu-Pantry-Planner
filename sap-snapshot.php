<?php
/**
 * Karibu — daily SAP stock snapshot.
 *
 * Pulls the full SAP ItemListDetails catalogue once a day and stores it per item + per
 * warehouse for that date. This is the FOUNDATION of the SAP audit: SAP itself only serves
 * "stock right now" (no dates), so we stamp each daily pull with its date here — the
 * day-over-day delta of these snapshots IS the movement we reconcile against app fulfilments.
 *
 * CLI ONLY (SSH / GitHub Actions). READ-only against SAP (ItemListDetails); writes only sap_* tables.
 * One full pull per run = one snapshot/day (etiquette). Re-running for the same day overwrites it.
 *
 * Usage: php sap-snapshot.php [--date=YYYY-MM-DD] [--quiet]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); } // CLI only — no web access
require __DIR__ . '/config.php';
date_default_timezone_set('Africa/Dar_es_Salaam');

$SAP_URL   = 'http://196.61.9.142:8588/api/ItemListDetails';
$MAX_PAGES = 60; // safety cap; catalogue ~3,100 items / 100 per page ≈ 32 pages

$opts  = getopt('', ['date::', 'quiet', 'force']);
$date  = $opts['date'] ?? date('Y-m-d');
$quiet = isset($opts['quiet']);
function out($m) { global $quiet; if (!$quiet) fwrite(STDOUT, $m . "\n"); }

$db = getDB();

/* ---- schema (idempotent; safe to run every time) ---- */
$db->exec("CREATE TABLE IF NOT EXISTS sap_stock (
  snapshot_date DATE NOT NULL,
  item_code VARCHAR(50) NOT NULL,
  whs_code VARCHAR(20) NOT NULL,
  on_hand DECIMAL(14,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (snapshot_date, item_code, whs_code),
  KEY idx_item (item_code),
  KEY idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS sap_stock_meta (
  snapshot_date DATE NOT NULL,
  item_code VARCHAR(50) NOT NULL,
  item_name VARCHAR(200) NULL,
  itm_grp VARCHAR(100) NULL,
  uom VARCHAR(20) NULL,
  on_hand DECIMAL(14,3) NOT NULL DEFAULT 0,
  on_order DECIMAL(14,3) NOT NULL DEFAULT 0,
  committed DECIMAL(14,3) NOT NULL DEFAULT 0,
  available DECIMAL(14,3) NOT NULL DEFAULT 0,
  price DECIMAL(14,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (snapshot_date, item_code),
  KEY idx_item (item_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS sap_snapshot_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  snapshot_date DATE NOT NULL,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  pages INT NOT NULL DEFAULT 0,
  items INT NOT NULL DEFAULT 0,
  wh_rows INT NOT NULL DEFAULT 0,
  ok TINYINT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  KEY idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* Already have a good snapshot for this day? Skip. Lets the scheduler fire a few staggered
   crons for reliability without ever double-pulling SAP (etiquette: one full pull/day). */
if (!isset($opts['force'])) {
  $chk = $db->prepare("SELECT COUNT(*) FROM sap_snapshot_log WHERE snapshot_date=? AND ok=1");
  $chk->execute([$date]);
  if ((int)$chk->fetchColumn() > 0) { out("Snapshot for $date already complete — skipping (use --force to re-pull)."); exit(0); }
}

$startedAt = date('Y-m-d H:i:s');
out("SAP snapshot for $date — started $startedAt");

// One log row for this run, updated after every page so progress survives a dropped connection.
$db->prepare("INSERT INTO sap_snapshot_log (snapshot_date,started_at,pages,items,wh_rows,ok,note) VALUES (?,?,0,0,0,0,'running')")
   ->execute([$date, $startedAt]);
$logId = (int)$db->lastInsertId();

function sapPage($url, $page) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode(["Index" => $page, "ItemCode" => "", "ItemName" => ""]),
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_CONNECTTIMEOUT => 20,
  ]);
  $r = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
  if ($r === false) return ['_err' => $err ?: 'curl failed'];
  $j = json_decode($r, true);
  if (!is_array($j)) return ['_err' => 'bad json'];
  return $j;
}

// Upserts (idempotent) so a re-run — or a retry after a dropped page — overwrites in place.
$mIns = $db->prepare("INSERT INTO sap_stock_meta (snapshot_date,item_code,item_name,itm_grp,uom,on_hand,on_order,committed,available,price)
  VALUES (?,?,?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE item_name=VALUES(item_name),itm_grp=VALUES(itm_grp),uom=VALUES(uom),
    on_hand=VALUES(on_hand),on_order=VALUES(on_order),committed=VALUES(committed),available=VALUES(available),price=VALUES(price)");
$sIns = $db->prepare("INSERT INTO sap_stock (snapshot_date,item_code,whs_code,on_hand)
  VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE on_hand=VALUES(on_hand)");
$updLog = $db->prepare("UPDATE sap_snapshot_log SET pages=?,items=?,wh_rows=?,finished_at=?,note=? WHERE id=?");

$seen = []; // itemCode => true (dedupe + no-progress guard)
$itemCount = 0; $whRows = 0; $page = 1; $ok = true; $note = '';
while ($page <= $MAX_PAGES) {
  $j = sapPage($SAP_URL, $page);
  if (isset($j['_err'])) { $ok = false; $note = "page $page: " . $j['_err']; break; }
  $rd = $j['responseData'] ?? null;
  if (!is_array($rd) || count($rd) === 0) break; // end of list (statusCode 2 / Data Not Found)

  $newThisPage = 0;
  $db->beginTransaction();
  foreach ($rd as $it) {
    $code = (string)($it['itemCode'] ?? '');
    if ($code === '' || isset($seen[$code])) continue;
    $seen[$code] = true; $newThisPage++; $itemCount++;
    $mIns->execute([$date, $code, $it['itemName'] ?? null, $it['itmsGrpNam'] ?? null, $it['invntryUom'] ?? null,
      (float)($it['onHand'] ?? 0), (float)($it['onOrder'] ?? 0), (float)($it['isCommited'] ?? 0),
      (float)($it['avaibale'] ?? 0), (float)($it['price'] ?? 0)]); // 'avaibale' = SAP's own typo, kept
    foreach (($it['itemListStockDetails'] ?? []) as $w) {
      $q = (float)($w['onHand'] ?? 0);
      if ($q != 0.0) { $sIns->execute([$date, $code, (string)$w['whsCode'], $q]); $whRows++; } // store only non-zero; absent = 0
    }
  }
  $db->commit(); // page is now durable — a dropped connection can no longer lose it
  $updLog->execute([$page, $itemCount, $whRows, date('Y-m-d H:i:s'), 'running', $logId]);
  out("  page $page: " . count($rd) . " rows, +$newThisPage new (cum $itemCount items, $whRows wh rows)");
  if ($newThisPage === 0) break; // no new codes — guards a non-paginating API from looping
  $page++;
}
$pagesPulled = $page - 1;

if (!$ok || $itemCount === 0) {
  $updLog->execute([$pagesPulled, $itemCount, $whRows, date('Y-m-d H:i:s'), ($note ?: 'empty pull'), $logId]);
  out("ABORT: " . ($note ?: 'empty pull') . " — pages committed so far left intact");
  exit(1);
}
$updLog->execute([$pagesPulled, $itemCount, $whRows, date('Y-m-d H:i:s'), 'ok', $logId]);
$db->prepare("UPDATE sap_snapshot_log SET ok=1 WHERE id=?")->execute([$logId]);
out("DONE: $pagesPulled pages, $itemCount items, $whRows warehouse rows stored for $date");
