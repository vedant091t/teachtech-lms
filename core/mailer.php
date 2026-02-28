<?php
/**
 * OTP Mailer
 * Single responsibility: send an OTP email and nothing else.
 */
defined('TT_APP') or die('Direct access not allowed.');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_otp(string $email, string $otp, string $purpose = 'verification'): bool
{
    // Vendor autoload must already be done via bootstrap
    if (!class_exists(PHPMailer::class)) {
        error_log('[TeachTech Mailer] PHPMailer not found. Run composer install.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'] ?? '';
        $mail->Password = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) ($_ENV['SMTP_PORT'] ?? 587);

        $mail->setFrom(
            $_ENV['SMTP_FROM'] ?? $_ENV['SMTP_USER'] ?? 'noreply@teachtech.app',
            $_ENV['SMTP_FROM_NAME'] ?? 'TeachTech'
        );
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your TeachTech OTP — ' . strtoupper($purpose);
        $mail->Body = email_otp_template($otp, $purpose);
        $mail->AltBody = "Your TeachTech OTP is: {$otp}\n\nThis code expires in 10 minutes. Never share it.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('[TeachTech Mailer] ' . $mail->ErrorInfo);
        return false;
    }
}

function email_otp_template(string $otp, string $purpose): string
{
    $year = date('Y');
    return <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 32px 16px; }
        .wrap { max-width: 480px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #6C63FF, #FF6584); padding: 32px; border-radius: 16px 16px 0 0; text-align: center; }
        .header h1 { color: #fff; font-size: 24px; margin: 0; }
        .header p  { color: rgba(255,255,255,0.8); font-size: 14px; margin: 8px 0 0; }
        .body { background: #fff; padding: 40px 32px; border-radius: 0 0 16px 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .otp-box { background: #EEF0FF; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0; }
        .otp-code { font-size: 42px; font-weight: 800; color: #6C63FF; letter-spacing: 8px; line-height: 1; }
        .otp-label { font-size: 12px; color: #64748B; margin-top: 8px; }
        .footer { text-align: center; font-size: 12px; color: #94A3B8; margin-top: 24px; }
        .warning { background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #92400E; margin-top: 16px; }
      </style>
    </head>
    <body>
    <div class="wrap">
      <div class="header">
        <h1>TeachTech 🎓</h1>
        <p>Email {$purpose}</p>
      </div>
      <div class="body">
        <p style="color:#334155;font-size:15px;">Hi there,</p>
        <p style="color:#64748B;font-size:14px;line-height:1.6;">Here is your one-time password for <strong>{$purpose}</strong>:</p>
        <div class="otp-box">
          <div class="otp-code">{$otp}</div>
          <div class="otp-label">Valid for <strong>10 minutes</strong></div>
        </div>
        <div class="warning">
          🔒 <strong>Never share this code</strong> with anyone, including TeachTech support.
          If you didn't request this, ignore this email.
        </div>
      </div>
      <div class="footer">
        &copy; {$year} TeachTech. All rights reserved.
      </div>
    </div>
    </body>
    </html>
    HTML;
}
