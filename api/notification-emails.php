<?php
require_once __DIR__ . '/../auth.php';
requireRole(['admin']);
$db = getDB();
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? getJsonInput() : [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {

    case 'list':
        $rows = $db->query("SELECT ne.*, k.name AS kitchen_name FROM notification_emails ne LEFT JOIN kitchens k ON k.id = ne.kitchen_id WHERE ne.deleted_at IS NULL ORDER BY ne.is_active DESC, ne.name")->fetchAll();
        jsonResponse(['emails' => $rows]);
        break;

    case 'save':
        requireMethod('POST');
        $id        = (int)($input['id'] ?? 0);
        $name      = trim($input['name'] ?? '');
        $email     = trim($input['email'] ?? '');
        $notifyOn  = $input['notify_on'] ?? 'both';
        $kitchenId = isset($input['kitchen_id']) && $input['kitchen_id'] !== '' ? (int)$input['kitchen_id'] : null;

        if (!$name || !$email) jsonError('Name and email are required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonError('Invalid email address');
        if (!in_array($notifyOn, ['submit','fulfill','both'])) jsonError('Invalid notify_on value');

        if ($id) {
            $db->prepare("UPDATE notification_emails SET name=?, email=?, notify_on=?, kitchen_id=? WHERE id=?")->execute([$name, $email, $notifyOn, $kitchenId, $id]);
        } else {
            $db->prepare("INSERT INTO notification_emails (name, email, notify_on, kitchen_id) VALUES (?, ?, ?, ?)")->execute([$name, $email, $notifyOn, $kitchenId]);
            $id = $db->lastInsertId();
        }
        jsonResponse(['id' => $id]);
        break;

    case 'toggle':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('ID required');
        $db->prepare("UPDATE notification_emails SET is_active = NOT is_active WHERE id=?")->execute([$id]);
        $row = $db->prepare("SELECT is_active FROM notification_emails WHERE id=?");
        $row->execute([$id]);
        jsonResponse(['is_active' => (int)$row->fetchColumn()]);
        break;

    case 'delete':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('ID required');
        $admin = currentUser();
        $db->prepare("UPDATE notification_emails SET deleted_at = NOW(), deleted_by = ? WHERE id=?")->execute([$admin['id'], $id]);
        jsonResponse(['deleted' => true]);
        break;

    case 'test_send':
        requireMethod('POST');
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonError('ID required');
        $row = $db->prepare("SELECT * FROM notification_emails WHERE id=?");
        $row->execute([$id]);
        $ne = $row->fetch();
        if (!$ne) jsonError('Email not found');

        require_once __DIR__ . '/../mailer.php';
        $subject = 'Karibu Pantry Planner — Test Notification';
        $body    = "<p>Hi <strong>" . htmlspecialchars($ne['name']) . "</strong>,</p>
                    <p>This is a test notification to confirm your email address is correctly configured in the Karibu Pantry Planner system.</p>
                    <p>You are set to receive alerts: <strong>" . htmlspecialchars($ne['notify_on']) . "</strong>.</p>
                    <p>No action is required — your notifications are active.</p>";
        $html    = mailTemplate('Test Notification', $body);
        $sent    = sendMail(trim($ne['email']), $subject, $html);
        if (!$sent) jsonError('Failed to send — check server error logs for SMTP details', 500);
        jsonResponse(['sent' => true, 'to' => $ne['email']]);
        break;

    case 'diag':
        // Quick diagnostic — returns config info (admin only, never logs credentials)
        $vendorOk  = file_exists(__DIR__ . '/../vendor/autoload.php');
        $phpmailer = $vendorOk && class_exists('PHPMailer\PHPMailer\PHPMailer');
        if (!$phpmailer && $vendorOk) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $phpmailer = class_exists('PHPMailer\PHPMailer\PHPMailer');
        }
        $smtpUser = defined('MAIL_SMTP_USER') ? MAIL_SMTP_USER : ($_ENV['MAIL_SMTP_USER'] ?? '');
        $smtpPass = defined('MAIL_SMTP_PASS') ? MAIL_SMTP_PASS : ($_ENV['MAIL_SMTP_PASS'] ?? '');
        jsonResponse([
            'vendor_installed' => $vendorOk,
            'phpmailer_available' => $phpmailer,
            'smtp_user_set' => !empty($smtpUser),
            'smtp_pass_set' => !empty($smtpPass),
            'smtp_host' => defined('MAIL_SMTP_HOST') ? MAIL_SMTP_HOST : 'smtp.gmail.com',
            'smtp_port' => defined('MAIL_SMTP_PORT') ? MAIL_SMTP_PORT : 587,
            'from' => defined('MAIL_FROM') ? MAIL_FROM : $smtpUser,
        ]);
        break;

    default:
        jsonError('Unknown action');
}
