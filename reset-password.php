<?php
require __DIR__ . '/includes/functions.php';

$email = trim($_POST['email'] ?? $_GET['email'] ?? '');
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $code = trim($_POST['code'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'البريد الإلكتروني غير صحيح.';

    } elseif (!preg_match('/^[0-9]{6}$/', $code)) {
        $err = 'كود الاستعادة يجب أن يتكون من 6 أرقام.';

    } elseif (strlen($pass) < 6) {
        $err = 'كلمة المرور الجديدة لا تقل عن 6 أحرف.';

    } elseif ($pass !== $confirm) {
        $err = 'كلمتا المرور غير متطابقتين.';

    } else {
        $account = q(
            'SELECT id,password_reset_code,password_reset_expires
             FROM users
             WHERE email=? AND email_verified=1 AND status=?',
            [$email, 'active']
        )->fetch();

        if (
            !$account ||
            empty($account['password_reset_code']) ||
            !hash_equals((string)$account['password_reset_code'], $code)
        ) {
            $err = 'بيانات الاستعادة غير صحيحة.';

        } elseif (
            empty($account['password_reset_expires']) ||
            strtotime($account['password_reset_expires']) < time()
        ) {
            $err = 'انتهت صلاحية كود الاستعادة. اطلبي كودًا جديدًا.';

        } else {
            q(
                'UPDATE users
                 SET password=?,
                     password_reset_code=NULL,
                     password_reset_expires=NULL
                 WHERE id=?',
                [
                    password_hash($pass, PASSWORD_DEFAULT),
                    $account['id']
                ]
            );

            flash('تم تغيير كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.');
            redirect('login.php');
        }
    }
}

$title = 'تعيين كلمة مرور جديدة';
$nav = '';

require __DIR__ . '/includes/header.php';
?>

<div class="auth">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>

    <h1>تعيين كلمة مرور جديدة</h1>
    <p class="mut">أدخلي كود الاستعادة واختاري كلمة مرور جديدة.</p>

    <?php if ($err): ?>
      <div class="alert err"><?= e($err) ?></div>
    <?php endif ?>

    <div class="field full">
      <label>البريد الإلكتروني</label>
      <input
        type="email"
        name="email"
        value="<?= e($email) ?>"
        required
      >
    </div>

    <div class="field full">
      <label>كود الاستعادة</label>
      <input
        type="text"
        name="code"
        inputmode="numeric"
        pattern="[0-9]{6}"
        maxlength="6"
        minlength="6"
        placeholder="أدخلي الكود المكوّن من 6 أرقام"
        required
      >
    </div>

    <div class="field full">
      <label>كلمة المرور الجديدة</label>
      <input
        type="password"
        name="password"
        minlength="6"
        required
      >
    </div>

    <div class="field full">
      <label>تأكيد كلمة المرور الجديدة</label>
      <input
        type="password"
        name="confirm_password"
        minlength="6"
        required
      >
    </div>

    <button class="btn big block">تغيير كلمة المرور</button>

    <p class="mut c">
      <a href="<?= url('login.php') ?>"><b>العودة لتسجيل الدخول</b></a>
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>