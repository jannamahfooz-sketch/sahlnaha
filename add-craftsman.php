<?php
require __DIR__ . '/../includes/functions.php';

$u = require_login('admin');
$err = '';
$gid = (int)($_POST['gov'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $category = (int)($_POST['category'] ?? 0);
    $gov = (int)($_POST['gov'] ?? 0);
    $area = (int)($_POST['area'] ?? 0);
    $experience = max(0, (int)($_POST['experience'] ?? 0));
    $price = max(0, (int)($_POST['price'] ?? 0));
    $bio = trim($_POST['bio'] ?? '');

    if (!$name || !$phone || !$email || !$password) {
        $err = 'املئي البيانات الأساسية.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'البريد الإلكتروني غير صحيح.';
    } elseif (strlen($password) < 6) {
        $err = 'كلمة المرور يجب ألا تقل عن 6 أحرف.';
    } elseif (!$category || !$gov) {
        $err = 'اختاري التخصص والمحافظة.';
    } elseif (q('SELECT id FROM users WHERE email=?', [$email])->fetch()) {
        $err = 'البريد الإلكتروني مستخدم بالفعل.';
    } elseif (!q('SELECT id FROM categories WHERE id=?', [$category])->fetch()) {
        $err = 'التخصص غير موجود.';
    } elseif (!q('SELECT id FROM governorates WHERE id=?', [$gov])->fetch()) {
        $err = 'المحافظة غير موجودة.';
    } elseif ($area && !q('SELECT id FROM areas WHERE id=? AND governorate_id=?', [$area, $gov])->fetch()) {
        $err = 'المنطقة لا تتبع المحافظة.';
    } else {
        $pdo = db();

        try {
            $avatar = upload_file('photo', 'avatars');

            $location = q(
                'SELECT lat,lng FROM governorates WHERE id=?',
                [$gov]
            )->fetch();

            $lat = $location && $location['lat'] !== null
                ? (float)$location['lat'] + mt_rand(-100, 100) / 10000
                : null;

            $lng = $location && $location['lng'] !== null
                ? (float)$location['lng'] + mt_rand(-100, 100) / 10000
                : null;

            $pdo->beginTransaction();

            q(
                'INSERT INTO users(name,email,phone,password,role,avatar)
                 VALUES(?,?,?,?,?,?)',
                [
                    $name,
                    $email,
                    $phone,
                    password_hash($password, PASSWORD_DEFAULT),
                    'craftsman',
                    $avatar
                ]
            );

            $uid = (int)$pdo->lastInsertId();

            q(
                'INSERT INTO craftsmen
                (
                    user_id,
                    approval_status,
                    category_id,
                    governorate_id,
                    area_id,
                    bio,
                    experience_years,
                    price_from,
                    lat,
                    lng
                )
                VALUES(?,?,?,?,?,?,?,?,?,?)',
                [
                    $uid,
                    'approved',
                    $category,
                    $gov,
                    $area ?: null,
                    $bio,
                    $experience,
                    $price,
                    $lat,
                    $lng
                ]
            );

            $pdo->commit();

            flash('تمت إضافة الصنايعي بنجاح. حسابه مقبول للانضمام لكنه غير موثّق.', 'ok');
            redirect('admin/index.php?tab=verify');

        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($ex->getMessage());
            $err = 'حدث خطأ أثناء إضافة الصنايعي.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="admin">

    <aside class="a-side">
        <h3>لوحة الإدارة</h3>

        <a href="<?= url('admin/index.php?tab=overview') ?>">
            <i class="fa-solid fa-chart-line"></i> الرئيسية
        </a>

        <a href="<?= url('admin/index.php?tab=users') ?>">
            <i class="fa-solid fa-users"></i> المستخدمون
        </a>

        <a href="<?= url('admin/index.php?tab=verify') ?>">
            <i class="fa-solid fa-user-check"></i> مراجعة التوثيق
        </a>

        <a href="<?= url('admin/index.php?tab=reports') ?>">
            <i class="fa-solid fa-flag"></i> البلاغات
        </a>

        <a href="<?= url('admin/index.php?tab=requests') ?>">
            <i class="fa-solid fa-clipboard-list"></i> الطلبات
        </a>

        <a class="on" href="<?= url('admin/add-craftsman.php') ?>">
            <i class="fa-solid fa-user-plus"></i> إضافة صنايعي
        </a>
    </aside>

    <main class="a-main">

        <div class="page-head">
            <div>
                <h1>إضافة صنايعي جديد</h1>
                <p>أضيفي بيانات الصنايعي لإنشاء حساب جديد.</p>
            </div>
        </div>

        <?php if ($err): ?>
            <div class="alert error"><?= e($err) ?></div>
        <?php endif; ?>

        <div class="form-card">

            <div class="alert">
                <i class="fa-solid fa-circle-info"></i>
                الحساب سيكون مقبولًا للانضمام تلقائيًا، لكنه سيظل غير موثّق حتى تراجعي مستنداته.
            </div>

            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="grid2">

                    <div class="field">
                        <label>اسم الصنايعي *</label>
                        <input type="text" name="name" required
                        value="<?= e($_POST['name'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label>رقم الهاتف *</label>
                        <input type="tel" name="phone" required
                        value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label>البريد الإلكتروني *</label>
                        <input type="email" name="email" required
                        value="<?= e($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label>كلمة المرور *</label>
                        <input type="password" name="password" required minlength="6">
                    </div>

                    <div class="field">
                        <label>التخصص *</label>
                        <select name="category" required>
                            <option value="">اختاري التخصص</option>
                            <?php foreach (cats() as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"
                                <?= (int)($_POST['category'] ?? 0) === (int)$cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label>المحافظة *</label>
                        <select name="gov" required>
                            <option value="">اختاري المحافظة</option>
                            <?php foreach (govs() as $g): ?>
                                <option value="<?= (int)$g['id'] ?>"
                                <?= $gid === (int)$g['id'] ? 'selected' : '' ?>>
                                    <?= e($g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?= area_select($gid, (int)($_POST['area'] ?? 0)) ?>

                    <div class="field">
                        <label>سنوات الخبرة</label>
                        <input type="number" name="experience" min="0"
                        value="<?= e($_POST['experience'] ?? '0') ?>">
                    </div>

                    <div class="field">
                        <label>السعر يبدأ من (جنيه)</label>
                        <input type="number" name="price" min="0"
                        value="<?= e($_POST['price'] ?? '0') ?>">
                    </div>

                    <div class="field full">
                        <label>صورة الصنايعي</label>
                        <input type="file" name="photo"
                        accept="image/jpeg,image/png,image/webp">
                    </div>

                    <div class="field full">
                        <label>نبذة عن الصنايعي</label>
                        <textarea name="bio" rows="4"><?= e($_POST['bio'] ?? '') ?></textarea>
                    </div>

                </div>

                <div class="acts">
                    <button type="submit" class="btn">
                        <i class="fa-solid fa-plus"></i>
                        إضافة الصنايعي
                    </button>

                    <a href="<?= url('admin/index.php?tab=verify') ?>" class="btn ghost">
                        إلغاء
                    </a>
                </div>

            </form>
        </div>

    </main>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>