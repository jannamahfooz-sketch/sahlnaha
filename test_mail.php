<?php

require __DIR__ . '/config.php';
require __DIR__ . '/includes/mailer.php';

$to = 'jannamahfooz@gmail.com';

if (send_verification_email($to, 'إدارة سهلناها', '123456')) {
    echo 'تم إرسال رسالة الاختبار بنجاح! راجعي Gmail.';
} else {
    echo 'فشل إرسال الرسالة. راجعي سجل أخطاء PHP في XAMPP.';
}