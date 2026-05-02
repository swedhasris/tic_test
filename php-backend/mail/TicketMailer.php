<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/MailConfig.php';

class TicketMailer {

    public static function sendTicketCreatedEmail(array $ticket): bool {
        $email = $ticket['email'] ?? null;

        if (!$email) {
            error_log("[TicketMailer] No email found, skipping.");
            return false;
        }

        $config = MailConfig::getSMTP();

        try {
            $mail = new PHPMailer(true);

            $mail->SMTPDebug = 0;

            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $config['username'];
            $mail->Password = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $config['port'];

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "🎫 Ticket Created — " . ($ticket['ticket_number'] ?? $ticket['id'] ?? '');

            $formattedDate = date('M d, Y — h:i A', strtotime($ticket['created_at'] ?? 'now'));
            $mail->Body = "
                <div style='font-family:Arial, sans-serif; padding:20px; color:#333;'>
                    <h2 style='color:#2F3B4C;'>Your ticket has been created</h2>
                    <table style='border-collapse:collapse; width:100%; max-width:600px;'>
                        <tr style='background:#f8f9fa;'><td style='padding:10px; border:1px solid #ddd; font-weight:bold; width:150px;'>Ticket ID</td><td style='padding:10px; border:1px solid #ddd;'>" . ($ticket['ticket_number'] ?? $ticket['id'] ?? 'N/A') . "</td></tr>
                        <tr><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Title</td><td style='padding:10px; border:1px solid #ddd;'>" . ($ticket['title'] ?? 'N/A') . "</td></tr>
                        <tr style='background:#f8f9fa;'><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Caller</td><td style='padding:10px; border:1px solid #ddd;'>" . ($ticket['caller'] ?? 'N/A') . "</td></tr>
                        <tr><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Priority</td><td style='padding:10px; border:1px solid #ddd;'>" . ($ticket['priority'] ?? 'N/A') . "</td></tr>
                        <tr style='background:#f8f9fa;'><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Status</td><td style='padding:10px; border:1px solid #ddd;'>" . ($ticket['status'] ?? 'New') . "</td></tr>
                        <tr><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Category</td><td style='padding:10px; border:1px solid #ddd;'>" . ($ticket['category'] ?? 'N/A') . "</td></tr>
                        <tr style='background:#f8f9fa;'><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Description</td><td style='padding:10px; border:1px solid #ddd;'>" . nl2br($ticket['description'] ?? 'N/A') . "</td></tr>
                        <tr><td style='padding:10px; border:1px solid #ddd; font-weight:bold;'>Created</td><td style='padding:10px; border:1px solid #ddd;'>{$formattedDate}</td></tr>
                    </table>
                    <p style='margin-top:20px; font-size:12px; color:#666;'>This is an automated notification. Please do not reply directly to this email.</p>
                </div>
            ";

            $mail->send();

            error_log("[EMAIL] SUCCESS → $email");
            return true;

        } catch (Exception $e) {
            error_log("[EMAIL] FAILED → " . $e->getMessage());
            return false;
        }
    }
}
