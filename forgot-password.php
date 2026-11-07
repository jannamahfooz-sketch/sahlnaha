<?php
require __DIR__ . '/includes/functions.php';

require_once __DIR__ . '/includes/PHPMailer-PHPMailer-36d2461/src/Exception.php';
require_once __DIR__ . '/includes/PHPMailer-PHPMailer-36d2461/src/PHPMailer.php';
require_once __DIR__ . '/includes/PHPMailer-PHPMailer-36d2461/src/SMTP.php';

$err = '';
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'اكتبي بريدًا إلكترونيًا صحيحًا.';
    } else {
        $account = q(
            'SELECT id,name,email FROM users
             WHERE email=? AND email_verified=1 AND status=?',
            [$email, 'active']
        )->fetch();

        if ($account) {
            $code = (string)random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + 600);

            q(
                'UPDATE users
                 SET password_reset_code=?, password_reset_expires=?
                 WHERE id=?',
                [$code, $expires, $account['id']]
            );

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;
                $mail->CharSet = 'UTF-8';

                $mail->setFrom('sahlnaha77@gmail.com', 'سهلناها');
                $mail->addAddress($email, $account['name']);

                $mail->isHTML(true);
                $mail->Subject = 'كود استعادة كلمة المرور - سهلناها';

                $safeName = htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8');

                $mail->Body = '
                    <div dir="rtl" style="font-family:Arial,sans-serif;text-align:center;padding:25px;background:#f3f8ff;">
                        <h2 style="color:#0a6cde;">استعادة كلمة المرور</h2>
                        <p>أهلاً ' . $safeName . '</p>
                        <p>استخدمي الكود التالي لتعيين كلمة مرور جديدة:</p>
                        <h1 style="letter-spacing:8px;color:#0752b3;">' . $code . '</h1>
                        <p>الكود صالح لمدة 10 دقائق فقط.</p>
                        <p>إذا لم تطلبي استعادة كلمة المرور، تجاهلي هذه الرسالة.</p>
                    </div>
                ';

                $mail->AltBody = "كود استعادة كلمة المرور في سهلناها هو: $code. صالح لمدة 10 دقائق.";

                $mail->send();

            } catch (Throwable $e) {
                error_log('Password Reset Mail Error: ' . $e->getMessage());

                q(
                    'UPDATE users
                     SET password_reset_code=NULL, password_reset_expires=NULL
                     WHERE id=?',
                    [$account['id']]
                );

                $err = 'تعذر إرسال الرسالة حاليًا. حاولي مرة أخرى.';
            }
        }

        if (!$err) {
            $success = 'لو البريد مسجل ومؤكد، هيوصلك كود استعادة كلمة المرور. راجعي الوارد والرسائل غير المرغوب فيها.';
        }
    }
}

$title = 'نسيت كلمة المرور';
$nav = '';

require __DIR__ . '/includes/header.php';
?>

<div class="auth">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>

    <h1>نسيت كلمة المرور؟</h1>
    <p class="mut">اكتبي بريدك الإلكتروني المسجل في سهلناها لإرسال كود الاستعادة.</p>

    <?php if ($err): ?>
      <div class="alert err"><?= e($err) ?></div>
    <?php endif ?>

    <?php if ($success): ?>
      <div class="alert"><?= e($success) ?></div>

      <p class="c" style="margin-top:15px;">
        <a class="btn big block"
           href="<?= url('reset-password.php?email=' . urlencode($email)) ?>">
           إدخال الكود وتغيير كلمة المرور
        </a>
      </p>
    <?php endif ?>

    <div class="field full">
      <label>البريد الإلكتروني</label>
      <input type="email" name="email"
        value="<?= e($email) ?>"
        required>
    </div>

    <button class="btn big block">إرسال كود الاستعادة</button>

    <p class="mut c">
      <a href="<?= url('login.php') ?>"><b>العودة لتسجيل الدخول</b></a>
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>