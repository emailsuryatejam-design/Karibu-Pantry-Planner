<?php
/**
 * Karibu Pantry Planner — Mailer Utility
 *
 * Sends branded HTML emails via Gmail SMTP (jambo@karibucamps.com).
 * Uses PHPMailer — install with: composer install
 *
 * SMTP credentials are read from .env:
 *   MAIL_SMTP_USER=jambo@karibucamps.com
 *   MAIL_SMTP_PASS=REDACTED
 *   MAIL_FROM_NAME=Karibu Pantry Planner
 *   APP_URL=https://...
 */

$_mailerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($_mailerAutoload)) {
    require_once $_mailerAutoload;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!defined('MAIL_SMTP_HOST')) define('MAIL_SMTP_HOST', $_ENV['MAIL_SMTP_HOST'] ?? 'smtp.gmail.com');
if (!defined('MAIL_SMTP_PORT')) define('MAIL_SMTP_PORT', (int)($_ENV['MAIL_SMTP_PORT'] ?? 587));
if (!defined('MAIL_SMTP_USER')) define('MAIL_SMTP_USER', $_ENV['MAIL_SMTP_USER'] ?? 'jambo@karibucamps.com');
if (!defined('MAIL_SMTP_PASS')) define('MAIL_SMTP_PASS', $_ENV['MAIL_SMTP_PASS'] ?? '');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Karibu Pantry Planner');
if (!defined('MAIL_FROM'))      define('MAIL_FROM',      $_ENV['MAIL_FROM']      ?? 'jambo@karibucamps.com');
if (!defined('APP_URL'))        define('APP_URL',        $_ENV['APP_URL']        ?? 'https://palegoldenrod-coyote-386848.hostingersite.com');

// ── Core Send ─────────────────────────────────────────────────────────────

/**
 * Send an HTML email via Gmail SMTP.
 * Falls back to php mail() if PHPMailer is not installed.
 */
function sendMail(string $to, string $subject, string $html): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    // ── PHPMailer path (preferred) ──
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host        = MAIL_SMTP_HOST;
            $mail->SMTPAuth    = true;
            $mail->Username    = MAIL_SMTP_USER;
            $mail->Password    = MAIL_SMTP_PASS;
            $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port        = MAIL_SMTP_PORT;
            $mail->CharSet     = 'UTF-8';
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log('[Karibu Mailer] PHPMailer error to ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    // ── Fallback: php mail() ──
    $boundary = '----=_Part_' . md5(uniqid('', true));
    $headers  = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'X-Mailer: Karibu-Pantry/1.0',
    ]);
    $text = wordwrap(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'), 76, "\r\n", true);
    $body = "--{$boundary}\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n{$text}\r\n\r\n"
          . "--{$boundary}\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n{$html}\r\n\r\n--{$boundary}--";
    $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    if (!$sent) error_log('[Karibu Mailer] mail() failed to ' . $to);
    return $sent;
}

// ── HTML Template ─────────────────────────────────────────────────────────

/**
 * Wrap content in Karibu branded HTML email shell.
 */
function mailTemplate(string $title, string $content, string $ctaText = '', string $ctaUrl = ''): string {
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $safeUrl  = htmlspecialchars($ctaUrl,  ENT_QUOTES);
        $safeText = htmlspecialchars($ctaText, ENT_QUOTES);
        $cta = "<tr><td style='padding:8px 28px 24px;text-align:center'>"
             . "<a href='{$safeUrl}' style='display:inline-block;background:#ea580c;color:#ffffff;"
             . "padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:700;"
             . "font-size:14px;font-family:Arial,Helvetica,sans-serif'>{$safeText}</a></td></tr>";
    }
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0"
       style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 6px rgba(0,0,0,0.10);max-width:560px">
  <tr><td style="background:#ea580c;padding:20px 28px">
    <table width="100%" cellpadding="0" cellspacing="0"><tr>
      <td style="color:#ffffff;font-size:17px;font-weight:700;letter-spacing:.3px">&#127829; Karibu Pantry Planner</td>
      <td align="right" style="color:#fed7aa;font-size:11px">Karibu Safari Camps</td>
    </tr></table>
  </td></tr>
  <tr><td style="padding:28px 28px 20px">
    <h2 style="margin:0 0 16px;color:#111827;font-size:18px;font-weight:700;line-height:1.3">{$title}</h2>
    <div style="color:#374151;font-size:14px;line-height:1.6">{$content}</div>
  </td></tr>
  {$cta}
  <tr><td style="padding:16px 28px 24px;border-top:1px solid #f3f4f6">
    <p style="margin:0;color:#9ca3af;font-size:11px;line-height:1.6">
      This is an automated notification from Karibu Pantry Planner.<br>
      &copy; {$year} Karibu Safari Camps &mdash; Confidential, for internal use only.
    </p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

// ── Convenience Helpers ───────────────────────────────────────────────────

function notifyAdmins(string $subject, string $html): void {
    try {
        $db = getDB();
        $rows = $db->query("SELECT email FROM users WHERE is_active=1 AND role='admin' AND email IS NOT NULL AND TRIM(email)!=''")->fetchAll();
        foreach ($rows as $r) sendMail(trim($r['email']), $subject, $html);
    } catch (Exception $e) { error_log('[Karibu Mailer] notifyAdmins: ' . $e->getMessage()); }
}

function notifyStorekeepers(int $kitchenId, string $subject, string $html): void {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT email FROM users WHERE is_active=1 AND kitchen_id=? AND role='storekeeper' AND email IS NOT NULL AND TRIM(email)!=''");
        $st->execute([$kitchenId]);
        foreach ($st->fetchAll() as $r) sendMail(trim($r['email']), $subject, $html);
    } catch (Exception $e) { error_log('[Karibu Mailer] notifyStorekeepers: ' . $e->getMessage()); }
}

function notifyChefs(int $kitchenId, string $subject, string $html): void {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT email FROM users WHERE is_active=1 AND kitchen_id=? AND role='chef' AND email IS NOT NULL AND TRIM(email)!=''");
        $st->execute([$kitchenId]);
        foreach ($st->fetchAll() as $r) sendMail(trim($r['email']), $subject, $html);
    } catch (Exception $e) { error_log('[Karibu Mailer] notifyChefs: ' . $e->getMessage()); }
}

function notifyUser(int $userId, string $subject, string $html): void {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT email FROM users WHERE id=? AND is_active=1 AND email IS NOT NULL AND TRIM(email)!=''");
        $st->execute([$userId]);
        $r = $st->fetch();
        if ($r) sendMail(trim($r['email']), $subject, $html);
    } catch (Exception $e) { error_log('[Karibu Mailer] notifyUser: ' . $e->getMessage()); }
}
