<?php
require __DIR__ . '/includes/functions.php';

if (user()) redirect('index.php');

$err = '';
$unverifiedEmail = '';

$role = ($_POST['role'] ?? ($_GET['role'] ?? 'client')) === 'craftsman'
    ? 'craftsman'
    : 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $m = q(
        'SELECT * FROM users WHERE email=?',
        [trim($_POST['email'] ?? '')]
    )->fetch();

    if (!$m || !password_verify($_POST['password'] ?? '', $m['password'])) {
        $err = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
    }

    elseif ($m['status'] !== 'active') {
        $err = 'هذا الحساب موقوف، تواصل مع الإدارة';
    }

    // التأكد من تأكيد البريد لكل الحسابات ما عدا الإدارة
    elseif ($m['role'] !== 'admin' && (int)$m['email_verified'] !== 1) {
        $err = 'يجب تأكيد بريدك الإلكتروني أولاً.';
        $unverifiedEmail = $m['email'];
    }

    elseif ($m['role'] !== 'admin' && $m['role'] !== $role) {
        $err = $m['role'] === 'craftsman'
            ? 'هذا الحساب مسجّل كحرفي، اختر "أبحث عن عمل".'
            : 'هذا الحساب مسجّل كعميل، اختر "أبحث عن حد يساعدني".';
    }

    // التحقق من موافقة الإدارة على طلب انضمام الحرفي
    elseif ($m['role'] === 'craftsman') {

        $approval = q(
            'SELECT approval_status FROM craftsmen WHERE user_id=?',
            [$m['id']]
        )->fetchColumn();

        if ($approval === 'pending') {
            $err = 'طلب انضمامك ما زال قيد المراجعة من الإدارة.';
        }

        elseif ($approval === 'rejected') {
            $err = 'نأسف، تم رفض طلب انضمامك. يمكنك التواصل مع الإدارة.';
        }

        elseif ($approval !== 'approved') {
            $err = 'تعذر التحقق من حالة حسابك، تواصل مع الإدارة.';
        }

        else {
            session_regenerate_id(true);
            $_SESSION['uid'] = $m['id'];

            $n = $_GET['next'] ?? '';

            redirect(
                ($n[0] ?? '') === '/' && ($n[1] ?? '') !== '/'
                    ? $n
                    : 'dashboard.php'
            );
        }
    }

    else {
        session_regenerate_id(true);
        $_SESSION['uid'] = $m['id'];

        $n = $_GET['next'] ?? '';

        redirect(
            $m['role'] === 'admin'
                ? 'admin/index.php'
                : (($n[0] ?? '') === '/' && ($n[1] ?? '') !== '/'
                    ? $n
                    : 'dashboard.php')
        );
    }
}

$title = 'تسجيل الدخول';
$nav = '';

require __DIR__ . '/includes/header.php';
?>

<div class="auth">
  <form class="auth-card" method="post">
    <?= csrf_field() ?>

    <h1>تسجيل الدخول</h1>
    <p class="mut">أهلاً بعودتك! اختر نوع حسابك</p>

    <div class="role-cards">

      <label class="role">
        <input type="radio" name="role" value="craftsman"
          <?= $role === 'craftsman' ? 'checked' : '' ?>>
        <span>
          <i class="fa-solid fa-helmet-safety"></i>
          <b>أبحث عن عمل</b>
          <small>أنا حرفي / صنايعي</small>
        </span>
      </label>

      <label class="role">
        <input type="radio" name="role" value="client"
          <?= $role !== 'craftsman' ? 'checked' : '' ?>>
        <span>
          <i class="fa-solid fa-magnifying-glass-location"></i>
          <b>أبحث عن حد يساعدني</b>
          <small>نجار، سباك، كهربائي...</small>
        </span>
      </label>

    </div>

    <?php if ($err): ?>
      <div class="alert err"><?= e($err) ?></div>
    <?php endif ?>

    <?php if ($unverifiedEmail): ?>
      <p class="mut c">
        <a href="<?= url('verify-email.php?email=' . urlencode($unverifiedEmail)) ?>">
          <b>اضغط هنا لتأكيد بريدك الإلكتروني</b>
        </a>
      </p>
    <?php endif ?>

    <div class="field full">
      <label>البريد الإلكتروني</label>
      <input type="email" name="email" required
        value="<?= e($_POST['email'] ?? '') ?>">
    </div>

    <div class="field full">
      <label>كلمة المرور</label>
      <input type="password" name="password" required>
    </div>

    <!-- رابط استعادة كلمة المرور -->
    <p class="mut" style="text-align:left; margin:8px 0 15px;">
      <a href="<?= url('forgot-password.php') ?>">
        <b>نسيت كلمة المرور؟</b>
      </a>
    </p>

    <button class="btn big block">دخول</button>

    <p class="mut c">
      ليس لديك حساب؟
      <a href="<?= url('register.php') ?>"><b>أنشئ حساباً</b></a>
    </p>

  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>