<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer-PHPMailer-36d2461/src/Exception.php';
require_once __DIR__ . '/PHPMailer-PHPMailer-36d2461/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer-PHPMailer-36d2461/src/SMTP.php';

function send_verification_email(string $to, string $name, string $code): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPDebug  = 0;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('sahlnaha77@gmail.com', 'سهلناها');
        $mail->addAddress($to, $name);

        $mail->isHTML(true);
        $mail->Subject = 'كود تأكيد البريد الإلكتروني - سهلناها';

        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
            <div dir="rtl" style="font-family:Arial,sans-serif;text-align:center;padding:25px;background:#f3f8ff;">
                <h2 style="color:#0a6cde;">أهلاً بك في سهلناها</h2>
                <p>مرحباً ' . $safeName . '</p>
                <p>استخدم الكود التالي لتأكيد بريدك الإلكتروني:</p>
                <h1 style="letter-spacing:8px;color:#0752b3;">' . $safeCode . '</h1>
                <p>الكود صالح لمدة 10 دقائق فقط.</p>
                <p>إذا لم تطلب إنشاء حساب، يمكنك تجاهل هذه الرسالة.</p>
            </div>
        ';

        $mail->AltBody = "كود تأكيد البريد الإلكتروني الخاص بك في سهلناها هو: $code. صالح لمدة 10 دقائق.";

        return $mail->send();

    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}