<?php
require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/mailer.php';

if (user()) redirect('index.php');

$err = '';
$role = ($_POST['role'] ?? $_GET['role'] ?? 'client') === 'craftsman' ? 'craftsman' : 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass = $_POST['password'] ?? '';

    $cat = (int)($_POST['cat'] ?? 0);
    $gov = (int)($_POST['gov'] ?? 0);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '') {
        $err = 'من فضلك أكمل البيانات بشكل صحيح';

    } elseif (strlen($pass) < 6) {
        $err = 'كلمة المرور لا تقل عن 6 أحرف';

    } elseif (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) {
        $err = 'هذا البريد مسجّل بالفعل';

    } elseif ($role === 'craftsman' && (!$cat || !$gov)) {
        $err = 'اختر الحرفة والمحافظة';

    } else {

        $avatar = upload_file('photo', 'avatars');

        // إنشاء كود تأكيد عشوائي صالح لمدة 10 دقائق
        $code = (string)random_int(100000, 999999);
        $expires = date('Y-m-d H:i:s', time() + 600);

        try {
            db()->beginTransaction();

            // إنشاء الحساب غير مؤكد البريد
            q(
                'INSERT INTO users
                (name,email,phone,password,role,avatar,email_verified,email_verification_code,email_verification_expires)
                VALUES(?,?,?,?,?,?,0,?,?)',
                [
                    $name,
                    $email,
                    $phone,
                    password_hash($pass, PASSWORD_DEFAULT),
                    $role,
                    $avatar,
                    $code,
                    $expires
                ]
            );

            $uid = (int)db()->lastInsertId();

            // إنشاء بيانات الحرفي كما كانت سابقاً
            if ($role === 'craftsman') {

                $g = q('SELECT lat,lng FROM governorates WHERE id=?', [$gov])->fetch();

                if (!$g) {
                    throw new Exception('المحافظة المختارة غير موجودة');
                }

                q(
                    'INSERT INTO craftsmen(user_id,category_id,governorate_id,area_id,bio,experience_years,price_from,lat,lng)
                     VALUES(?,?,?,?,?,?,?,?,?)',
                    [
                        $uid,
                        $cat,
                        $gov,
                        (int)($_POST['area'] ?? 0) ?: null,
                        trim($_POST['bio'] ?? ''),
                        (int)($_POST['experience_years'] ?? 0),
                        (int)($_POST['price_from'] ?? 0),
                        $g['lat'] + mt_rand(-400, 400) / 10000,
                        $g['lng'] + mt_rand(-400, 400) / 10000
                    ]
                );
            }

            // إرسال كود التأكيد
            if (!send_verification_email($email, $name, $code)) {
                throw new Exception('تعذر إرسال كود التأكيد إلى بريدك الإلكتروني');
            }

            db()->commit();

            flash('تم إنشاء حسابك وإرسال كود التأكيد إلى بريدك الإلكتروني.');
            redirect('verify-email.php?email=' . urlencode($email));

        } catch (Throwable $e) {

            if (db()->inTransaction()) {
                db()->rollBack();
            }

            error_log('Registration Error: ' . $e->getMessage());

            if ($e->getMessage() === 'تعذر إرسال كود التأكيد إلى بريدك الإلكتروني') {
                $err = 'لم نتمكن من إرسال كود التأكيد. تأكد من البريد وحاول مرة أخرى.';
            } else {
                $err = 'حدث خطأ أثناء إنشاء الحساب. حاول مرة أخرى.';
            }
        }
    }
}

$gid = (int)($_POST['gov'] ?? 0);

$title = 'إنشاء حساب';
$nav = '';

require __DIR__ . '/includes/header.php';
?>

<div class="auth">
  <form class="auth-card wide" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <h1>إنشاء حساب جديد</h1>
    <p class="mut">اختر نوع حسابك لنخصص لك التجربة</p>

    <div class="role-cards">
      <label class="role">
        <input type="radio" name="role" value="craftsman" <?= $role === 'craftsman' ? 'checked' : '' ?>>
        <span>
          <i class="fa-solid fa-helmet-safety"></i>
          <b>أبحث عن عمل</b>
          <small>أنا حرفي / صنايعي</small>
        </span>
      </label>

      <label class="role">
        <input type="radio" name="role" value="client" <?= $role === 'client' ? 'checked' : '' ?>>
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

    <div class="grid2">

      <div class="field">
        <label>الاسم بالكامل</label>
        <input name="name" required value="<?= e($_POST['name'] ?? '') ?>">
      </div>

      <div class="field">
        <label>رقم الموبايل</label>
        <input name="phone" required value="<?= e($_POST['phone'] ?? '') ?>">
      </div>

      <div class="field">
        <label>البريد الإلكتروني</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>

      <div class="field">
        <label>كلمة المرور</label>
        <input type="password" name="password" minlength="6" required>
      </div>

      <div class="field">
        <label>صورتك الشخصية</label>
        <input type="file" name="photo" accept="image/*">
      </div>

      <div class="field craft-only">
        <label>الحرفة</label>
        <select name="cat">
          <option value="">اختر الحرفة</option>
          <?php foreach (cats() as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($_POST['cat'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="field craft-only">
        <label>المحافظة</label>
        <select name="gov">
          <option value="">اختر المحافظة</option>
          <?php foreach (govs() as $g): ?>
            <option value="<?= $g['id'] ?>" <?= $gid == $g['id'] ? 'selected' : '' ?>>
              <?= e($g['name']) ?>
            </option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="craft-only">
        <?= area_select($gid, (int)($_POST['area'] ?? 0)) ?>
      </div>

      <div class="field craft-only">
        <label>سنوات الخبرة</label>
        <input type="number" name="experience_years" min="0" value="<?= e($_POST['experience_years'] ?? 0) ?>">
      </div>

      <div class="field craft-only">
        <label>السعر يبدأ من (ج.م)</label>
        <input type="number" name="price_from" min="0" value="<?= e($_POST['price_from'] ?? '') ?>">
      </div>

      <div class="field full craft-only">
        <label>نبذة عنك</label>
        <textarea name="bio" rows="3"><?= e($_POST['bio'] ?? '') ?></textarea>
      </div>

    </div>

    <button class="btn big block">إنشاء الحساب</button>

    <p class="mut c">
      لديك حساب؟
      <a href="<?= url('login.php') ?>"><b>سجّل الدخول</b></a>
    </p>

  </form>
</div>

<script>
const toggleRole = () => {
    const c = document.querySelector('input[name=role]:checked').value === 'craftsman';

    document.querySelectorAll('.craft-only').forEach(x => {
        x.style.display = c ? '' : 'none';
    });
};

document.querySelectorAll('input[name=role]').forEach(r => {
    r.addEventListener('change', toggleRole);
});

toggleRole();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>