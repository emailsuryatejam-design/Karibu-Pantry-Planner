<?php
/**
 * Karibu Pantry Planner — PDF Generator
 * Produces the same order sheet that storekeepers open and print from the dashboard.
 * Requires dompdf (composer require dompdf/dompdf)
 */

/**
 * @param array  $req        Requisition row (id, req_date, meals, guest_count, status, submitter_name, chef_name)
 * @param string $kitchenName
 * @param string $mealLabel
 * @param array  $lines      Each row: item_name, item_code, uom, order_qty, fulfilled_qty, received_qty, unused_qty
 * @param array  $dishes     Each row: recipe_name, guest_count  (optional)
 */
function generateOrderPDF(array $req, string $kitchenName, string $mealLabel, array $lines, array $dishes = []): ?string {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) return null;
    require_once $autoload;
    if (!class_exists('Dompdf\Dompdf')) return null;

    $reqId       = (int)($req['id'] ?? 0);
    $date        = date('D, d M Y', strtotime($req['req_date']));
    $guestCount  = (int)($req['guest_count'] ?? 0);
    $chefName    = htmlspecialchars($req['chef_name'] ?? $req['submitter_name'] ?? 'Chef', ENT_QUOTES);
    $status      = strtoupper($req['status'] ?? 'submitted');
    $generatedAt = date('d M Y H:i');

    // Status badge colour
    $statusBg = '#dbeafe'; $statusColor = '#1d4ed8'; // submitted default
    if ($req['status'] === 'draft')     { $statusBg = '#f3f4f6'; $statusColor = '#374151'; }
    if ($req['status'] === 'fulfilled') { $statusBg = '#dcfce7'; $statusColor = '#15803d'; }
    if ($req['status'] === 'received')  { $statusBg = '#dcfce7'; $statusColor = '#15803d'; }
    if ($req['status'] === 'closed')    { $statusBg = '#e5e7eb'; $statusColor = '#4b5563'; }

    // ── Items table rows ──
    $tableRows = '';
    $totalUnused = 0;
    foreach ($lines as $i => $l) {
        $orderQty    = number_format((float)($l['order_qty'] ?? 0), 2, '.', '');
        $fulfilledQty = number_format((float)($l['fulfilled_qty'] ?? 0), 2, '.', '');
        $receivedQty  = number_format((float)($l['received_qty'] ?? 0), 2, '.', '');
        $unusedQty    = (float)($l['unused_qty'] ?? 0);
        $totalUnused += $unusedQty;
        $diff = (float)$receivedQty - (float)$fulfilledQty;
        $hasDiff = abs($diff) > 0.01;
        $rowBg     = $hasDiff ? '#fef2f2' : ($i % 2 === 0 ? '#f9fafb' : '#ffffff');
        $diffColor = $diff < 0 ? '#dc2626' : ($diff > 0 ? '#16a34a' : '#6b7280');
        $diffBold  = $hasDiff ? 'font-weight:bold;' : '';
        $diffText  = $hasDiff ? (($diff > 0 ? '+' : '') . number_format($diff, 2, '.', '')) : '—';
        $unusedText = $unusedQty > 0 ? number_format($unusedQty, 2, '.', '') : '—';
        $unusedColor = $unusedQty > 0 ? '#d97706' : '#6b7280';
        $fulfilledText = (float)$fulfilledQty > 0 ? $fulfilledQty : '—';
        $receivedText  = (float)$receivedQty  > 0 ? $receivedQty  : '—';

        $num      = $i + 1;
        $itemCode = htmlspecialchars($l['item_code'] ?? $l['code'] ?? '', ENT_QUOTES);
        $itemName = htmlspecialchars($l['item_name'] ?? $l['name'] ?? '', ENT_QUOTES);
        $uom      = htmlspecialchars($l['uom'] ?? 'kg', ENT_QUOTES);

        $tableRows .= "
        <tr style='background:{$rowBg}'>
            <td style='padding:6px 8px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>{$num}</td>
            <td style='padding:6px 8px;font-size:10px;color:#9ca3af;font-family:monospace;border-bottom:1px solid #e5e7eb'>" . ($itemCode ?: '—') . "</td>
            <td style='padding:6px 8px;font-weight:500;border-bottom:1px solid #e5e7eb'>{$itemName}</td>
            <td style='padding:6px 8px;text-align:center;color:#6b7280;font-size:11px;border-bottom:1px solid #e5e7eb'>{$uom}</td>
            <td style='padding:6px 8px;text-align:center;border-bottom:1px solid #e5e7eb'>{$orderQty}</td>
            <td style='padding:6px 8px;text-align:center;font-weight:600;color:#2563eb;border-bottom:1px solid #e5e7eb'>{$fulfilledText}</td>
            <td style='padding:6px 8px;text-align:center;font-weight:600;color:#16a34a;border-bottom:1px solid #e5e7eb'>{$receivedText}</td>
            <td style='padding:6px 8px;text-align:center;color:{$unusedColor};font-weight:bold;border-bottom:1px solid #e5e7eb'>{$unusedText}</td>
            <td style='padding:6px 8px;text-align:center;color:{$diffColor};{$diffBold}border-bottom:1px solid #e5e7eb'>{$diffText}</td>
        </tr>";
    }
    if (!$tableRows) {
        $tableRows = "<tr><td colspan='9' style='padding:12px;text-align:center;color:#9ca3af;font-size:12px'>No items</td></tr>";
    }

    // ── Dishes section ──
    $dishesHtml = '';
    if (!empty($dishes)) {
        $dishItems = '';
        foreach ($dishes as $d) {
            $rname = htmlspecialchars($d['recipe_name'] ?? '—', ENT_QUOTES);
            $pax   = (int)($d['guest_count'] ?? $guestCount);
            $dishItems .= "<span style='display:inline-block;margin:2px 4px 2px 0;padding:3px 8px;background:#fef3c7;border-radius:6px;font-size:11px'>{$rname} <strong style='color:#92400e'>({$pax} pax)</strong></span>";
        }
        $dishCount = count($dishes);
        $dishesHtml = "
        <div style='margin-top:16px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px'>
            <div style='font-size:10px;font-weight:bold;color:#92400e;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px'>Dishes ({$dishCount})</div>
            <div>{$dishItems}</div>
        </div>";
    }

    // ── Unused summary ──
    $unusedHtml = '';
    if ($totalUnused > 0) {
        $tu = number_format($totalUnused, 1);
        $unusedHtml = "<div style='margin-top:12px;padding:10px 12px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px'>
            <span style='font-size:12px;font-weight:bold;color:#d97706'>Unused: {$tu} kg returned to inventory</span>
        </div>";
    }

    $itemCount = count($lines);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, Helvetica, sans-serif; padding: 24px; color: #1f2937; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #f3f4f6; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #374151; border-bottom: 2px solid #d1d5db; }
  th.center { text-align: center; }
</style>
</head>
<body>

<!-- Header -->
<div style="display:table;width:100%;border-bottom:3px solid #ea580c;padding-bottom:12px;margin-bottom:16px">
    <div style="display:table-cell;vertical-align:middle">
        <h1 style="font-size:20px;font-weight:700;color:#ea580c">&#127829; Karibu Pantry Planner</h1>
        <div style="font-size:12px;color:#6b7280;margin-top:2px">{$kitchenName}</div>
    </div>
    <div style="display:table-cell;vertical-align:middle;text-align:right">
        <div style="font-size:16px;font-weight:700;color:#1f2937">REQUISITION ORDER</div>
        <div style="font-size:11px;color:#6b7280">#{$reqId}</div>
    </div>
</div>

<!-- Info Grid -->
<div style="display:table;width:100%;margin-bottom:16px;border-collapse:collapse">
    <div style="display:table-row">
        <div style="display:table-cell;padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;width:25%">
            <div style="font-size:9px;font-weight:bold;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Date</div>
            <div style="font-size:13px;font-weight:600;color:#1f2937;margin-top:2px">{$date}</div>
        </div>
        <div style="display:table-cell;padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;width:25%">
            <div style="font-size:9px;font-weight:bold;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Meal Type</div>
            <div style="font-size:13px;font-weight:600;color:#1f2937;margin-top:2px">{$mealLabel}</div>
        </div>
        <div style="display:table-cell;padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;width:25%">
            <div style="font-size:9px;font-weight:bold;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Chef</div>
            <div style="font-size:13px;font-weight:600;color:#1f2937;margin-top:2px">{$chefName}</div>
        </div>
        <div style="display:table-cell;padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;width:25%">
            <div style="font-size:9px;font-weight:bold;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Guests</div>
            <div style="font-size:13px;font-weight:600;color:#1f2937;margin-top:2px">{$guestCount}</div>
        </div>
    </div>
</div>

<!-- Status -->
<div style="margin-bottom:12px">
    <span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:600;background:{$statusBg};color:{$statusColor}">{$status}</span>
    <span style="font-size:12px;color:#6b7280;margin-left:8px">{$itemCount} items</span>
</div>

<!-- Items Table -->
<table>
    <thead>
        <tr>
            <th style="width:28px;text-align:center">#</th>
            <th style="width:65px">Item No</th>
            <th>Item</th>
            <th class="center" style="width:42px">UOM</th>
            <th class="center" style="width:58px">Requested</th>
            <th class="center" style="width:52px">Sent</th>
            <th class="center" style="width:58px">Received</th>
            <th class="center" style="width:48px">Unused</th>
            <th class="center" style="width:48px">Diff</th>
        </tr>
    </thead>
    <tbody>
        {$tableRows}
    </tbody>
</table>

{$dishesHtml}
{$unusedHtml}

<!-- Signature area -->
<div style="margin-top:32px;display:table;width:100%">
    <div style="display:table-row">
        <div style="display:table-cell;width:33%;padding-right:16px">
            <div style="font-size:10px;font-weight:bold;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Prepared by</div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div>
            <div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div>
        </div>
        <div style="display:table-cell;width:33%;padding:0 8px">
            <div style="font-size:10px;font-weight:bold;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Issued by</div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div>
            <div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div>
        </div>
        <div style="display:table-cell;width:33%;padding-left:16px">
            <div style="font-size:10px;font-weight:bold;color:#6b7280;text-transform:uppercase;margin-bottom:12px">Received by (Manager)</div>
            <div style="border-bottom:1px solid #9ca3af;margin-bottom:6px;height:28px"></div>
            <div style="font-size:10px;color:#9ca3af">Signature &amp; Date</div>
        </div>
    </div>
</div>

<div style="margin-top:24px;text-align:center;font-size:10px;color:#9ca3af">
    Generated: {$generatedAt} &mdash; Karibu Safari Camps &mdash; Confidential, for internal use only.
</div>

</body>
</html>
HTML;

    try {
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output(); // raw PDF bytes
    } catch (\Exception $e) {
        error_log('[Karibu PDF] ' . $e->getMessage());
        return null;
    }
}

/**
 * Whole-day requisition PDF — every meal for a date, one section per meal heading.
 * @param array $reqs Each: id, meals, status, guest_count, chef_name, lines[], dishes[]
 *               lines[]: item_name, uom, required_kg, stock_qty, order_qty, fulfilled_qty, received_qty, unused_qty, is_staple
 *               dishes[]: recipe_name, guest_count
 */
function generateDayPDF(string $kitchenName, string $date, array $reqs): ?string {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) return null;
    require_once $autoload;
    if (!class_exists('Dompdf\\Dompdf')) return null;

    $num = fn($v) => ((float)$v > 0) ? rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') : '—';
    $meal = fn($c) => ucwords(str_replace('_', ' ', $c ?: 'Order'));
    $esc = fn($s) => htmlspecialchars($s ?? '', ENT_QUOTES);
    $sc  = fn($s) => $s == 'closed' ? '#e5e7eb;color:#4b5563'
        : ($s == 'fulfilled' || $s == 'received' ? '#dcfce7;color:#15803d'
        : ($s == 'submitted' ? '#dbeafe;color:#1d4ed8' : '#fef3c7;color:#92400e'));
    $dateLabel = date('D, d M Y', strtotime($date));

    $sections = '';
    $stapleAgg = []; // "name|uom" => [name, uom, ordered, sent] — all staples across the day
    foreach ($reqs as $r) {
        $allLines = $r['lines'] ?? []; $dishes = $r['dishes'] ?? []; $guests = $r['guest_count'] ?: 20;
        $menuLines = array_values(array_filter($allLines, fn($l) => !intval($l['is_staple'] ?? 0)));

        // collect staples into the day-wide consolidated list (printed once at the end)
        foreach (array_filter($allLines, fn($l) => intval($l['is_staple'] ?? 0)) as $l) {
            $key = ($l['item_name'] ?? '') . '|' . ($l['uom'] ?? 'kg');
            if (!isset($stapleAgg[$key])) $stapleAgg[$key] = ['name' => $l['item_name'], 'uom' => $l['uom'] ?: 'kg', 'ordered' => 0, 'sent' => 0];
            $stapleAgg[$key]['ordered'] += (float)($l['order_qty'] ?? 0);
            $stapleAgg[$key]['sent']    += (float)($l['fulfilled_qty'] ?? 0);
        }

        if (empty($menuLines)) continue; // staples-only meal — its items show in the day staples list

        $rows = '';
        foreach ($menuLines as $i => $l) {
            $rows .= "<tr>
                <td style='padding:4px 6px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>" . ($i + 1) . "</td>
                <td style='padding:4px 6px;border-bottom:1px solid #e5e7eb'>" . $esc($l['item_name']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>" . $esc($l['uom'] ?: 'kg') . "</td>
                <td style='padding:4px 6px;text-align:center;border-bottom:1px solid #e5e7eb'>" . $num($l['required_kg'] ?? 0) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#b45309;border-bottom:1px solid #e5e7eb'>" . $num($l['stock_qty'] ?? 0) . "</td>
                <td style='padding:4px 6px;text-align:center;font-weight:bold;border-bottom:1px solid #e5e7eb'>" . $num($l['order_qty'] ?? 0) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#2563eb;border-bottom:1px solid #e5e7eb'>" . $num($l['fulfilled_qty'] ?? 0) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#16a34a;border-bottom:1px solid #e5e7eb'>" . $num($l['received_qty'] ?? 0) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#d97706;border-bottom:1px solid #e5e7eb'>" . $num($l['unused_qty'] ?? 0) . "</td>
            </tr>";
        }
        $dishesHtml = '';
        if ($dishes) {
            $chips = '';
            foreach ($dishes as $d) $chips .= "<span style='display:inline-block;margin:2px 4px 2px 0;padding:2px 6px;background:#fef3c7;border-radius:4px;font-size:10px'>" . $esc($d['recipe_name']) . " (" . ($d['guest_count'] ?: $guests) . " pax)</span> ";
            $dishesHtml = "<div style='margin:4px 0 8px'>$chips</div>";
        }
        $sections .= "<div style='margin-bottom:16px'>
            <h2 style='font-size:14px;color:#1f2937;margin:0 0 2px;border-bottom:2px solid #ea580c;padding-bottom:4px'>" . $esc($meal($r['meals'])) . " <span style='padding:1px 8px;border-radius:8px;font-size:9px;font-weight:bold;background:" . $sc($r['status']) . "'>" . strtoupper($r['status']) . "</span></h2>
            <div style='font-size:10px;color:#6b7280;margin-bottom:4px'>{$guests} pax &bull; " . $esc($r['chef_name'] ?: 'Chef') . " &bull; " . count($menuLines) . " items</div>
            $dishesHtml
            <table style='width:100%;border-collapse:collapse;font-size:10px'>
                <thead><tr style='background:#f3f4f6'>
                    <th style='padding:5px 6px;text-align:center'>#</th><th style='padding:5px 6px;text-align:left'>Item</th>
                    <th style='padding:5px 6px'>UOM</th><th style='padding:5px 6px'>Req</th><th style='padding:5px 6px'>Stock</th>
                    <th style='padding:5px 6px'>Order</th><th style='padding:5px 6px'>Sent</th><th style='padding:5px 6px'>Recv</th><th style='padding:5px 6px'>Unused</th>
                </tr></thead><tbody>$rows</tbody></table>
        </div>";
    }

    // Consolidated "Staples for the day" — every staple across all meals, combined
    if ($stapleAgg) {
        usort($stapleAgg, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        $srows = '';
        foreach ($stapleAgg as $i => $s) {
            $srows .= "<tr>
                <td style='padding:4px 6px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>" . ($i + 1) . "</td>
                <td style='padding:4px 6px;border-bottom:1px solid #e5e7eb'>" . $esc($s['name']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>" . $esc($s['uom']) . "</td>
                <td style='padding:4px 6px;text-align:center;font-weight:bold;border-bottom:1px solid #e5e7eb'>" . $num($s['ordered']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#16a34a;border-bottom:1px solid #e5e7eb'>" . $num($s['sent']) . "</td>
            </tr>";
        }
        $sections .= "<div style='margin-bottom:16px'>
            <h2 style='font-size:14px;color:#1f2937;margin:0 0 4px;border-bottom:2px solid #7c3aed;padding-bottom:4px'>Staples for the day <span style='font-size:9px;color:#6b7280;font-weight:normal'>&mdash; " . count($stapleAgg) . " item" . (count($stapleAgg) != 1 ? 's' : '') . ", all meals combined</span></h2>
            <table style='width:100%;border-collapse:collapse;font-size:10px'>
                <thead><tr style='background:#f3f4ff'>
                    <th style='padding:5px 6px;text-align:center'>#</th><th style='padding:5px 6px;text-align:left'>Staple item</th>
                    <th style='padding:5px 6px'>UOM</th><th style='padding:5px 6px'>Order</th><th style='padding:5px 6px'>Sent</th>
                </tr></thead><tbody>$srows</tbody></table>
        </div>";
    }

    $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
        body{font-family:Arial,sans-serif;color:#1f2937;font-size:11px}
        th{font-size:9px;text-transform:uppercase;color:#374151;border-bottom:1px solid #d1d5db}
        </style></head><body>
        <table style='width:100%;border-bottom:3px solid #ea580c;margin-bottom:14px'><tr>
            <td style='padding-bottom:8px'><span style='font-size:18px;font-weight:bold;color:#ea580c'>Karibu Pantry Planner</span><br><span style='font-size:11px;color:#6b7280'>" . $esc($kitchenName) . "</span></td>
            <td style='padding-bottom:8px;text-align:right'><span style='font-size:14px;font-weight:bold'>DAY REQUISITIONS</span><br><span style='font-size:11px;color:#6b7280'>$dateLabel &bull; " . count($reqs) . " meal" . (count($reqs) != 1 ? 's' : '') . "</span></td>
        </tr></table>
        $sections
        <table style='width:100%;margin-top:20px'><tr>
            <td style='width:33%'><div style='font-size:9px;color:#6b7280;text-transform:uppercase'>Prepared by (Chef)</div><div style='border-bottom:1px solid #9ca3af;height:24px'></div></td>
            <td style='width:33%;padding-left:12px'><div style='font-size:9px;color:#6b7280;text-transform:uppercase'>Issued by (Store)</div><div style='border-bottom:1px solid #9ca3af;height:24px'></div></td>
            <td style='width:33%;padding-left:12px'><div style='font-size:9px;color:#6b7280;text-transform:uppercase'>Approved by (Manager)</div><div style='border-bottom:1px solid #9ca3af;height:24px'></div></td>
        </tr></table>
        <div style='margin-top:14px;text-align:center;font-size:9px;color:#9ca3af'>Generated " . date('d M Y H:i') . " &mdash; Karibu Safari Camps</div>
        </body></html>";

    try {
        $o = new \Dompdf\Options();
        $o->set('defaultFont', 'Arial');
        $o->set('isHtml5ParserEnabled', true);
        $pdf = new \Dompdf\Dompdf($o);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    } catch (\Exception $e) {
        error_log('[Karibu PDF] day: ' . $e->getMessage());
        return null;
    }
}
