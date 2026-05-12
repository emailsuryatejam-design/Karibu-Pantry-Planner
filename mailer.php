<?php
/**
 * Karibu Pantry Planner — Mailer Utility
 *
 * Sends branded HTML emails via PHP mail() (Hostinger native SMTP).
 * Configure MAIL_FROM / APP_URL in .env for production.
 *
 * Usage:
 *   require_once __DIR__ . '/mailer.php';
 *   notifyAdmins('New Order', mailTemplate('New Order', '<p>...</p>', 'View Orders', APP_URL.'/app.php'));
 */

if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Karibu Pantry Planner');
if (!defined('MAIL_FROM'))      define('MAIL_FROM',      $_ENV['MAIL_FROM']      ?? 'noreply@karibucamps.com');
if (!defined('MAIL_REPLY'))     define('MAIL_REPLY',     $_ENV['MAIL_REPLY']     ?? 'admin@karibucamps.com');
if (!defined('APP_URL'))        define('APP_URL',        $_ENV['APP_URL']        ?? 'https://palegoldenrod-coyote-386848.hostingersite.com');

// ── Core Send ──────────────────────────────────────────────────────────────

/**
 * Send an HTML email (with plain-text fallback).
 */
function sendMail(string $to, string $subject, string $html): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $boundary = '----=_Part_' . md5(uniqid('', true));

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_REPLY,
        'X-Mailer: Karibu-Pantry/1.0',
    ]);

    // Plain-text fallback
    $text = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
    $text = wordwrap(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), 76, "\r\n", true);

    $body = "--{$boundary}\r\n"
          . "Content-Type: text/plain; charset=utf-8\r\n"
          . "Content-Transfer-Encoding: 7bit\r\n\r\n"
          . $text . "\r\n\r\n"
          . "--{$boundary}\r\n"
          . "Content-Type: text/html; charset=utf-8\r\n"
          . "Content-Transfer-Encoding: 7bit\r\n\r\n"
          . $html . "\r\n\r\n"
          . "--{$boundary}--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $sent = @mail($to, $encodedSubject, $body, $headers);
    if (!$sent) {
        error_log("[Karibu Mailer] Failed to send to {$to}: {$subject}");
    }
    return $sent;
}

// ── HTML Template ──────────────────────────────────────────────────────────

/**
 * Wrap content in a branded HTML email shell.
 *
 * @param string $title   Heading inside the email body
 * @param string $content HTML content (paragraphs, tables, etc.)
 * @param string $ctaText Optional call-to-action button label
 * @param string $ctaUrl  URL for the CTA button
 */
function mailTemplate(string $title, string $content, string $ctaText = '', string $ctaUrl = ''): string {
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $safeUrl  = htmlspecialchars($ctaUrl,  ENT_QUOTES);
        $safeText = htmlspecialchars($ctaText, ENT_QUOTES);
        $cta = "
      <tr><td style='padding:8px 28px 24px;text-align:center'>
        <a href='{$safeUrl}'
           style='display:inline-block;background:#ea580c;color:#ffffff;padding:12px 32px;
                  border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;
                  font-family:Arial,Helvetica,sans-serif'>
          {$safeText}
        </a>
      </td></tr>";
    }

    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0"
           style="background:#ffffff;border-radius:12px;overflow:hidden;
                  box-shadow:0 1px 6px rgba(0,0,0,0.10);max-width:560px">

      <!-- Header -->
      <tr><td style="background:#ea580c;padding:20px 28px">
        <table width="100%" cellpadding="0" cellspacing="0"><tr>
          <td style="color:#ffffff;font-size:17px;font-weight:700;letter-spacing:.3px">
            &#127829; Karibu Pantry Planner
          </td>
          <td align="right" style="color:#fed7aa;font-size:11px">
            Karibu Safari Camps
          </td>
        </tr></table>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:28px 28px 20px">
        <h2 style="margin:0 0 16px;color:#111827;font-size:18px;font-weight:700;line-height:1.3">
          {$title}
        </h2>
        <div style="color:#374151;font-size:14px;line-height:1.6">
          {$content}
        </div>
      </td></tr>

      <!-- CTA -->
      {$cta}

      <!-- Footer -->
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

/**
 * Notify all active admin users who have an email address.
 */
function notifyAdmins(string $subject, string $html): void {
    try {
        $db = getDB();
        $stmt = $db->query(
            "SELECT email FROM users WHERE is_active = 1 AND role = 'admin'
             AND email IS NOT NULL AND email != '' AND TRIM(email) != ''"
        );
        foreach ($stmt->fetchAll() as $row) {
            sendMail(trim($row['email']), $subject, $html);
        }
    } catch (Exception $e) {
        error_log('[Karibu Mailer] notifyAdmins error: ' . $e->getMessage());
    }
}

/**
 * Notify all storekeepers in a given kitchen who have an email address.
 */
function notifyStorekeepers(int $kitchenId, string $subject, string $html): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT email FROM users WHERE is_active = 1 AND kitchen_id = ?
             AND role = 'storekeeper' AND email IS NOT NULL AND email != '' AND TRIM(email) != ''"
        );
        $stmt->execute([$kitchenId]);
        foreach ($stmt->fetchAll() as $row) {
            sendMail(trim($row['email']), $subject, $html);
        }
    } catch (Exception $e) {
        error_log('[Karibu Mailer] notifyStorekeepers error: ' . $e->getMessage());
    }
}

/**
 * Notify a specific user by their user ID.
 */
function notifyUser(int $userId, string $subject, string $html): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT email FROM users WHERE id = ? AND is_active = 1
             AND email IS NOT NULL AND email != '' AND TRIM(email) != ''"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) sendMail(trim($row['email']), $subject, $html);
    } catch (Exception $e) {
        error_log('[Karibu Mailer] notifyUser error: ' . $e->getMessage());
    }
}

/**
 * Notify all chefs in a given kitchen who have an email address.
 */
function notifyChefs(int $kitchenId, string $subject, string $html): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT email FROM users WHERE is_active = 1 AND kitchen_id = ?
             AND role = 'chef' AND email IS NOT NULL AND email != '' AND TRIM(email) != ''"
        );
        $stmt->execute([$kitchenId]);
        foreach ($stmt->fetchAll() as $row) {
            sendMail(trim($row['email']), $subject, $html);
        }
    } catch (Exception $e) {
        error_log('[Karibu Mailer] notifyChefs error: ' . $e->getMessage());
    }
}
