<?php
require __DIR__ . '/includes/functions.php';

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $code = trim($_POST['code'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'البريد الإلكتروني غير صحيح';

    } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
        $err = 'اكتبي كود التأكيد المكوّن من 6 أرقام';

    } else {
        $account = q(
            'SELECT id,email_verified,email_verification_code,email_verification_expires
             FROM users WHERE email=?',
            [$email]
        )->fetch();

        if (!$account) {
            $err = 'لم يتم العثور على الحساب';

        } elseif ((int)$account['email_verified'] === 1) {
            flash('البريد الإلكتروني مؤكد بالفعل. يمكنك تسجيل الدخول.');
            redirect('login.php');

        } elseif (
            empty($account['email_verification_code']) ||
            !hash_equals((string)$account['email_verification_code'], $code)
        ) {
            $err = 'كود التأكيد غير صحيح';

        } elseif (
            empty($account['email_verification_expires']) ||
            strtotime($account['email_verification_expires']) < time()
        ) {
            $err = 'انتهت صلاحية الكود. نحتاج إلى إرسال كود جديد.';

        } else {
            q(
                'UPDATE users
                 SET email_verified=1,
                     email_verification_code=NULL,
                     email_verification_expires=NULL
                 WHERE id=?',
                [(int)$account['id']]
            );

            flash('تم تأكيد بريدك الإلكتروني بنجاح. يمكنك الآن تسجيل الدخول.');
            redirect('login.php');
        }
    }
}

$title = 'تأكيد البريد الإلكتروني';
$nav = '';

require __DIR__ . '/includes/header.php';
?>

<div class="auth">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>

    <h1>تأكيد البريد الإلكتروني</h1>

    <p class="mut">
      أرسلنا كود تأكيد إلى بريدك الإلكتروني.
      اكتبي الكود المكوّن من 6 أرقام لإكمال التسجيل.
    </p>

    <?php if ($err): ?>
      <div class="alert err"><?= e($err) ?></div>
    <?php endif ?>

    <div class="field">
      <label>البريد الإلكتروني</label>
      <input
        type="email"
        name="email"
        value="<?= e($email) ?>"
        required
        readonly
      >
    </div>

    <div class="field">
      <label>كود التأكيد</label>
      <input
        type="text"
        name="code"
        inputmode="numeric"
        pattern="[0-9]{6}"
        maxlength="6"
        minlength="6"
        placeholder="مثال: 123456"
        required
        autocomplete="one-time-code"
      >
    </div>

    <button class="btn big block">تأكيد البريد الإلكتروني</button>

    <p class="mut c">
      <a href="<?= url('login.php') ?>"><b>العودة لتسجيل الدخول</b></a>
    </p>

  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>