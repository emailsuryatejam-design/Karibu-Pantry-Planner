<?php
/**
 * Karibu Pantry Planner — PDF Generator
 * Requires dompdf (composer require dompdf/dompdf)
 */

function generateOrderPDF(array $req, string $kitchenName, string $mealLabel, array $lines): ?string {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) return null;
    require_once $autoload;
    if (!class_exists('Dompdf\Dompdf')) return null;

    $date       = date('D, d M Y', strtotime($req['req_date']));
    $guestCount = (int)($req['guest_count'] ?? 0);
    $submittedBy = $req['submitter_name'] ?? 'Chef';
    $generatedAt = date('d M Y H:i');

    // Build items table rows
    $rows = '';
    foreach ($lines as $i => $line) {
        $bg   = ($i % 2 === 0) ? '#f9fafb' : '#ffffff';
        $name = htmlspecialchars($line['item_name'] ?? $line['name'] ?? '');
        $qty  = number_format((float)($line['order_qty'] ?? $line['qty'] ?? 0), 2);
        $uom  = htmlspecialchars($line['uom'] ?? 'kg');
        $rows .= "<tr style='background:{$bg}'>
            <td style='padding:7px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#111827'>{$name}</td>
            <td style='padding:7px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;text-align:right;color:#111827'>{$qty}</td>
            <td style='padding:7px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280'>{$uom}</td>
        </tr>";
    }
    if (!$rows) $rows = "<tr><td colspan='3' style='padding:12px;text-align:center;color:#9ca3af;font-size:12px'>No items</td></tr>";

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 0; color: #111827; }
  .header { background: #ea580c; color: #fff; padding: 20px 28px; }
  .header h1 { margin: 0; font-size: 18px; font-weight: 700; }
  .header p  { margin: 4px 0 0; font-size: 11px; color: #fed7aa; }
  .body { padding: 24px 28px; }
  .meta-grid { display: table; width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .meta-row { display: table-row; }
  .meta-label { display: table-cell; padding: 7px 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 11px; font-weight: 700; color: #6b7280; width: 130px; }
  .meta-value { display: table-cell; padding: 7px 12px; border: 1px solid #e5e7eb; font-size: 12px; }
  h2 { font-size: 13px; font-weight: 700; color: #374151; margin: 0 0 8px; text-transform: uppercase; letter-spacing: .5px; }
  table { width: 100%; border-collapse: collapse; }
  thead th { background: #1f4e78; color: #fff; padding: 9px 12px; font-size: 11px; font-weight: 700; text-align: left; text-transform: uppercase; letter-spacing: .5px; }
  thead th:last-child { width: 50px; }
  thead th.right { text-align: right; width: 80px; }
  .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 10px; color: #9ca3af; }
</style>
</head>
<body>
<div class="header">
  <h1>&#127829; Karibu Pantry Planner</h1>
  <p>Order Sheet &mdash; {$mealLabel} &mdash; {$kitchenName}</p>
</div>
<div class="body">
  <table class="meta-grid" style="margin-bottom:20px">
    <tr><td class="meta-label">Camp / Kitchen</td><td class="meta-value">{$kitchenName}</td></tr>
    <tr><td class="meta-label">Date</td><td class="meta-value">{$date}</td></tr>
    <tr><td class="meta-label">Meal</td><td class="meta-value">{$mealLabel}</td></tr>
    <tr><td class="meta-label">Guests</td><td class="meta-value">{$guestCount}</td></tr>
    <tr><td class="meta-label">Submitted by</td><td class="meta-value">{$submittedBy}</td></tr>
  </table>

  <h2>Items Required</h2>
  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th class="right">Qty</th>
        <th>UOM</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>

  <div class="footer">
    Generated: {$generatedAt} &mdash; Karibu Safari Camps &mdash; Confidential, for internal use only.
  </div>
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
