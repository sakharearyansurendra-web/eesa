<?php
/**
 * includes/mailer.php
 *
 * Sends via SMTP through PHPMailer. Hosts like Render have no local mail
 * server, so PHP's built-in mail() silently fails there — this always goes
 * out over real SMTP using the SMTP_* constants from config.php (which read
 * from environment variables in production).
 *
 * Run `composer install` once (the Dockerfile does this automatically on
 * Render) so vendor/autoload.php exists.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function send_mail(string $to, string $subject, string $htmlBody): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_PORT === 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Mail send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

function mail_otp(string $to, string $otp, string $purposeLabel): bool {
    $subject = "$purposeLabel — Your OTP is $otp";
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto'>
      <h2 style='color:#0b1220'>EESA — " . h($purposeLabel) . "</h2>
      <p>Your one-time password is:</p>
      <p style='font-size:28px;font-weight:bold;letter-spacing:4px;color:#35d4e8'>" . h($otp) . "</p>
      <p style='color:#555'>This code expires in 10 minutes. If you did not request this, ignore this email.</p>
    </div>";
    return send_mail($to, $subject, $body);
}

function mail_join_ticket(string $to, string $name, string $ticketId): bool {
    $subject = "EESA Membership Request Received — $ticketId";
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto'>
      <h2 style='color:#0b1220'>Thanks for applying, " . h($name) . "!</h2>
      <p>Your EESA membership request has been received. Keep this ticket ID for reference:</p>
      <p style='font-size:22px;font-weight:bold;color:#35d4e8'>" . h($ticketId) . "</p>
      <p>An admin will review your request. You'll receive your username and password by email once approved.</p>
    </div>";
    return send_mail($to, $subject, $body);
}

function mail_approval(string $to, string $name, string $username, string $tempPassword): bool {
    $subject = "Welcome to EESA — Your Login Details";
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto'>
      <h2 style='color:#0b1220'>Welcome aboard, " . h($name) . "!</h2>
      <p>Your EESA account has been approved. Here are your login details:</p>
      <p>Username: <b>" . h($username) . "</b><br>Temporary Password: <b>" . h($tempPassword) . "</b></p>
      <p style='color:#555'>Please log in and change your password as soon as possible.</p>
    </div>";
    return send_mail($to, $subject, $body);
}
