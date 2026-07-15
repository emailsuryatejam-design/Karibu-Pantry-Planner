<?php
/**
 * Karibu Pantry Planner — Daily Requisition Email (cron)
 *
 * Emails the PREVIOUS DAY's whole-day requisition sheet (PDF) to every active
 * notification email registered under each kitchen. Stores can still print the
 * same sheet on demand from the app (Day Close / Orders → Print Whole Day).
 *
 * Run from hPanel cron once a day (after midnight EAT), e.g. 06:00:
 *   php /home/uXXXX/domains/.../public_html/cron-daily-req-email.php >> ~/.logs/daily-req-email.log 2>&1
 *
 * Manual / test usage:
 *   php cron-daily-req-email.php                 # yesterday, real recipients
 *   php cron-daily-req-email.php 2026-06-14       # specific date, real recipients
 *   php cron-daily-req-email.php 2026-06-14 --dry # show what would send, send nothing
 *   php cron-daily-req-email.php 2026-06-14 --to=test@example.com  # send all to one test address
 */

require_once __DIR__ . '/config.php';   // sets Africa/Dar_es_Salaam tz, getDB()
require_once __DIR__ . '/mailer.php';    // sendMailWithPDF(), mailTemplate()

$argDate = null; $dry = false; $testTo = null;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--dry') $dry = true;
    elseif (str_starts_with($a, '--to=')) $testTo = substr($a, 5);
    elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $a)) $argDate = $a;
}
$date = $argDate ?: date('Y-m-d', strtotime('yesterday'));

function num($v) { $f = (float)$v; return $f > 0 ? rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.') : '—'; }
function mealName($c) { return ucwords(str_replace('_', ' ', $c ?: 'Order')); }
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

/** Build a dompdf-safe (no flex/grid) whole-day HTML for one kitchen. */
function buildDayHtml(string $kitchenName, string $date, array $reqs): string {
    $dateLabel = date('D, d M Y', strtotime($date));
    $sc = function ($s) {
        return $s == 'closed' ? '#e5e7eb;color:#4b5563'
            : ($s == 'fulfilled' || $s == 'received' ? '#dcfce7;color:#15803d'
            : ($s == 'submitted' ? '#dbeafe;color:#1d4ed8' : '#fef3c7;color:#92400e'));
    };
    $sections = '';
    foreach ($reqs as $r) {
        $lines = $r['lines'] ?? []; $dishes = $r['dishes'] ?? []; $guests = $r['guest_count'] ?: 20;
        $rows = '';
        foreach ($lines as $i => $l) {
            $rows .= "<tr>
                <td style='padding:4px 6px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>" . ($i + 1) . "</td>
                <td style='padding:4px 6px;border-bottom:1px solid #e5e7eb'>" . esc($l['item_name']) . (intval($l['is_staple']) ? " <span style='font-size:8px;color:#9ca3af'>(staple)</span>" : "") . "</td>
                <td style='padding:4px 6px;text-align:center;color:#6b7280;border-bottom:1px solid #e5e7eb'>" . esc($l['uom'] ?: 'kg') . "</td>
                <td style='padding:4px 6px;text-align:center;border-bottom:1px solid #e5e7eb'>" . num($l['required_kg']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#b45309;border-bottom:1px solid #e5e7eb'>" . num($l['stock_qty']) . "</td>
                <td style='padding:4px 6px;text-align:center;font-weight:bold;border-bottom:1px solid #e5e7eb'>" . num($l['order_qty']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#2563eb;border-bottom:1px solid #e5e7eb'>" . num($l['fulfilled_qty']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#16a34a;border-bottom:1px solid #e5e7eb'>" . num($l['received_qty']) . "</td>
                <td style='padding:4px 6px;text-align:center;color:#d97706;border-bottom:1px solid #e5e7eb'>" . num($l['unused_qty']) . "</td>
            </tr>";
        }
        $dishesHtml = '';
        if ($dishes) {
            $chips = '';
            foreach ($dishes as $d) $chips .= "<span style='display:inline-block;margin:2px 4px 2px 0;padding:2px 6px;background:#fef3c7;border-radius:4px;font-size:10px'>" . esc($d['recipe_name']) . " (" . ($d['guest_count'] ?: $guests) . " pax)</span> ";
            $dishesHtml = "<div style='margin:4px 0 8px'>$chips</div>";
        }
        $sections .= "<div style='margin-bottom:16px'>
            <h2 style='font-size:14px;color:#1f2937;margin:0 0 2px;border-bottom:2px solid #ea580c;padding-bottom:4px'>"
                . esc(mealName($r['meals']))
                . " <span style='padding:1px 8px;border-radius:8px;font-size:9px;font-weight:bold;background:" . $sc($r['status']) . "'>" . strtoupper($r['status']) . "</span></h2>
            <div style='font-size:10px;color:#6b7280;margin-bottom:4px'>{$guests} pax &bull; " . esc($r['chef_name'] ?: 'Chef') . " &bull; " . count($lines) . " items</div>
            $dishesHtml
            <table style='width:100%;border-collapse:collapse;font-size:10px'>
                <thead><tr style='background:#f3f4f6'>
                    <th style='padding:5px 6px;text-align:center'>#</th><th style='padding:5px 6px;text-align:left'>Item</th>
                    <th style='padding:5px 6px'>UOM</th><th style='padding:5px 6px'>Req</th><th style='padding:5px 6px'>Stock</th>
                    <th style='padding:5px 6px'>Order</th><th style='padding:5px 6px'>Sent</th><th style='padding:5px 6px'>Recv</th><th style='padding:5px 6px'>Unused</th>
                </tr></thead><tbody>$rows</tbody></table>
        </div>";
    }
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
        body{font-family:Arial,sans-serif;color:#1f2937;font-size:11px}
        th{font-size:9px;text-transform:uppercase;color:#374151;border-bottom:1px solid #d1d5db}
        </style></head><body>
        <table style='width:100%;border-bottom:3px solid #ea580c;margin-bottom:14px'><tr>
            <td style='padding-bottom:8px'><span style='font-size:18px;font-weight:bold;color:#ea580c'>Karibu Pantry Planner</span><br><span style='font-size:11px;color:#6b7280'>" . esc($kitchenName) . "</span></td>
            <td style='padding-bottom:8px;text-align:right'><span style='font-size:14px;font-weight:bold'>DAY REQUISITIONS</span><br><span style='font-size:11px;color:#6b7280'>$dateLabel &bull; " . count($reqs) . " meals</span></td>
        </tr></table>
        $sections
        <table style='width:100%;margin-top:20px'><tr>
            <td style='width:33%'><div style='font-size:9px;color:#6b7280;text-transform:uppercase'>Prepared by (Chef)</div><div style='border-bottom:1px solid #9ca3af;height:24px'></div></td>
            <td style='width:33%;padding-left:12px'><div style='font-size:9px;color:#6b7280;text-transform:uppercase'>Issued by (Store)</div><div style='border-bottom:1px solid #9ca3af;height:24px'></div></td>
            <td style='width:33%;padding-left:12px'><div style='font-size:9px;color:#6b7280;text-transform:uppercase'>Approved by (Manager)</div><div style='border-bottom:1px solid #9ca3af;height:24px'></div></td>
        </tr></table>
        <div style='margin-top:14px;text-align:center;font-size:9px;color:#9ca3af'>Generated " . date('d M Y H:i') . " &mdash; Karibu Safari Camps</div>
        </body></html>";
}

function renderPdf(string $html): ?string {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) return null;
    require_once $autoload;
    if (!class_exists('Dompdf\\Dompdf')) return null;
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
        error_log('[Karibu DailyEmail] PDF: ' . $e->getMessage());
        return null;
    }
}

// ── Main ──
$db = getDB();
echo "[" . date('Y-m-d H:i:s') . "] Daily requisition email for $date" . ($dry ? " (DRY RUN)" : "") . ($testTo ? " (TEST -> $testTo)" : "") . "\n";

// Kitchens that had real (locked) requisitions on $date
$kStmt = $db->prepare("SELECT DISTINCT k.id, k.name FROM kitchens k
    JOIN requisitions r ON r.kitchen_id = k.id
    WHERE r.req_date = ? AND r.status <> 'draft' ORDER BY k.name");
$kStmt->execute([$date]);
$kitchens = $kStmt->fetchAll();

if (!$kitchens) { echo "  No kitchens with requisitions on $date. Nothing to send.\n"; exit(0); }

$lineStmt = $db->prepare("SELECT rl.item_name, rl.uom, rl.required_kg, rl.stock_qty, rl.order_qty,
        rl.fulfilled_qty, rl.received_qty, rl.unused_qty, IFNULL(rl.is_staple,0) AS is_staple
    FROM requisition_lines rl WHERE rl.requisition_id = ? AND rl.deleted_at IS NULL AND rl.status <> 'rejected'
    ORDER BY rl.is_staple ASC, rl.item_name");
$dishStmt = $db->prepare("SELECT recipe_name, guest_count FROM requisition_dishes WHERE requisition_id = ? AND deleted_at IS NULL ORDER BY created_at");

$totalSent = 0;
foreach ($kitchens as $k) {
    $kid = (int)$k['id'];
    $rStmt = $db->prepare("SELECT r.id, r.meals, r.status, r.guest_count, r.req_date, u.name AS chef_name
        FROM requisitions r LEFT JOIN users u ON u.id = r.created_by
        WHERE r.req_date = ? AND r.kitchen_id = ? AND r.status <> 'draft'
        ORDER BY r.session_number, r.supplement_number");
    $rStmt->execute([$date, $kid]);
    $reqs = [];
    foreach ($rStmt->fetchAll() as $r) {
        $lineStmt->execute([(int)$r['id']]);
        $r['lines'] = $lineStmt->fetchAll();
        if (!$r['lines']) continue;
        $dishStmt->execute([(int)$r['id']]);
        $r['dishes'] = $dishStmt->fetchAll();
        $reqs[] = $r;
    }
    if (!$reqs) { echo "  [{$k['name']}] no items — skipped\n"; continue; }

    // Recipients = every active notification email under this kitchen (kitchen-specific + global)
    $recStmt = $db->prepare("SELECT name, email FROM notification_emails
        WHERE is_active = 1 AND deleted_at IS NULL AND email IS NOT NULL AND TRIM(email) <> ''
          AND (kitchen_id = ? OR kitchen_id IS NULL)");
    $recStmt->execute([$kid]);
    $recipients = $recStmt->fetchAll();
    if ($testTo) $recipients = [['name' => 'Test', 'email' => $testTo]];

    if (!$recipients) { echo "  [{$k['name']}] " . count($reqs) . " meals — NO recipients configured, skipped\n"; continue; }

    $pdf = renderPdf(buildDayHtml($k['name'], $date, $reqs));
    if (!$pdf) { echo "  [{$k['name']}] PDF render FAILED — skipped\n"; error_log("[Karibu DailyEmail] PDF render failed for kitchen $kid"); continue; }

    $dateLabel = date('D, d M Y', strtotime($date));
    $subject   = "Requisitions — {$k['name']} — $dateLabel";
    $body      = mailTemplate(
        "Daily Requisition Summary",
        "<p>Attached is the requisition sheet for <strong>" . esc($k['name']) . "</strong> for <strong>$dateLabel</strong> (" . count($reqs) . " meal" . (count($reqs) > 1 ? 's' : '') . ").</strong></p>
         <p>This is an automated daily record. Storekeepers can also print the same sheet anytime from the app (Day Close &rarr; Print Whole Day).</p>"
    );
    $filename = "Requisitions_" . preg_replace('/[^A-Za-z0-9]+/', '', $k['name']) . "_$date.pdf";

    $emails = array_map(fn($r) => trim($r['email']), $recipients);
    echo "  [{$k['name']}] " . count($reqs) . " meals, " . count($emails) . " recipient(s): " . implode(', ', $emails) . "\n";
    if ($dry) continue;

    foreach ($recipients as $rcp) {
        $ok = sendMailWithPDF(trim($rcp['email']), $subject, $body, $pdf, $filename);
        echo "      -> " . trim($rcp['email']) . ": " . ($ok ? "sent" : "FAILED") . "\n";
        if ($ok) $totalSent++;
    }
}
echo "[" . date('Y-m-d H:i:s') . "] Done. Emails sent: $totalSent\n";
