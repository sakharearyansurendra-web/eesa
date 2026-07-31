<?php
/**
 * includes/mailer.php
 *
 * Minimal mail wrapper. Ships using PHP's built-in mail() so the site works
 * out of the box on most shared hosts. For reliable delivery (Gmail/Outlook
 * inboxes, SPF/DKIM), swap send_mail()'s body for PHPMailer + the SMTP_*
 * constants in config.php:
 *   composer require phpmailer/phpmailer
 * and use PHPMailer's ->isSMTP() with SMTP_HOST/PORT/USER/PASS from config.
 */

function send_mail(string $to, string $subject, string $htmlBody): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    return @mail($to, $subject, $htmlBody, $headers);
}

function mail_otp(string $to, string $otp, string $purposeLabel): bool {
    $subject = "$purposeLabel — Your OTP is $otp";
    $body = "
    <div style='font-family:Arial,sans-serif;max-width:480px;margin:auto'>
      <h2 style='color:#0b1220'>EESA — " . h($purposeLabel) . "</h2>
      <p>Your one-time password is:</p>
      <p style='font-size:28px;font-weight:bold;letter-spacing:4px;color:#c9793f'>" . h($otp) . "</p>
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
      <p style='font-size:22px;font-weight:bold;color:#c9793f'>" . h($ticketId) . "</p>
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
