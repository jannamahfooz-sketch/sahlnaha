<?php
require __DIR__ . '/../includes/functions.php';

$u = require_login('admin');
$tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $id = (int)($_POST['id'] ?? 0);
    $message = 'تم تنفيذ الإجراء';

    switch ($_POST['act'] ?? '') {

        /* بيانات مدير الإدارة */

        case 'save_admin_profile':

            $name = trim($_POST['name'] ?? '');

            if ($name === '' || mb_strlen($name) > 120) {
                $message = 'اكتبي اسمًا صحيحًا لا يزيد عن 120 حرفًا';
                break;
            }

            $avatar = $u['avatar'];

            if (!empty($_FILES['photo']['name'])) {
                $newPhoto = upload_file('photo', 'avatars');

                if (!$newPhoto) {
                    $message = 'تعذر رفع الصورة. اختاري JPG أو PNG أو WEBP بحجم أقل من 5 ميجابايت.';
                    break;
                }

                $avatar = $newPhoto;
            }

            q(
                'UPDATE users SET name=?, avatar=? WHERE id=? AND role="admin"',
                [$name, $avatar, $u['id']]
            );

            $message = 'تم تحديث بيانات مدير الإدارة بنجاح';
            break;


        case 'save_craftsman_photo':

            $craftsman = q(
                'SELECT u.id
                 FROM users u
                 JOIN craftsmen c ON c.user_id = u.id
                 WHERE u.id = ? AND u.role = "craftsman"',
                [$id]
            )->fetch();

            if (!$craftsman) {
                $message = 'الحرفي غير موجود';
                break;
            }

            $photo = upload_file('photo', 'avatars');

            if (!$photo) {
                $message = 'لم يتم رفع الصورة. تأكدي أنها JPG أو PNG أو WEBP وحجمها أقل من 5 ميجابايت.';
                break;
            }

            q('UPDATE users SET avatar=? WHERE id=?', [$photo, $id]);

            $message = 'تم حفظ صورة الحرفي بنجاح';
            break;


        /* حذف رسالة تواصل معنا */

        case 'delete_contact':

            q('DELETE FROM contact_messages WHERE id=?', [$id]);

            $message = 'تم حذف رسالة التواصل بنجاح';
            break;


        case 'suspend':
            q('UPDATE users SET status="suspended" WHERE id=? AND role<>"admin"', [$id]);
            break;

        case 'activate':
            q('UPDATE users SET status="active" WHERE id=?', [$id]);
            break;

        case 'del_user':
            q('DELETE FROM users WHERE id=? AND role<>"admin"', [$id]);
            break;

        case 'approve':
            q('UPDATE craftsmen SET verified="verified" WHERE id=?', [$id]);
            $uid = q('SELECT user_id FROM craftsmen WHERE id=?', [$id])->fetchColumn();

            if ($uid) {
                notify($uid, 'تم توثيق حسابك، مبروك!', 'dashboard.php');
            }
            break;

        case 'reject':
            q('UPDATE craftsmen SET verified="rejected" WHERE id=?', [$id]);
            $uid = q('SELECT user_id FROM craftsmen WHERE id=?', [$id])->fetchColumn();

            if ($uid) {
                notify($uid, 'تم إلغاء توثيق حسابك.', 'dashboard.php');
            }
            break;

        case 'approve_join':
            q('UPDATE craftsmen SET approval_status="approved" WHERE id=?', [$id]);
            $uid = q('SELECT user_id FROM craftsmen WHERE id=?', [$id])->fetchColumn();

            if ($uid) {
                notify($uid, 'تم قبول طلب انضمامك، يمكنك الآن تسجيل الدخول.', 'login.php');
            }
            break;

        case 'reject_join':
            q('UPDATE craftsmen SET approval_status="rejected" WHERE id=?', [$id]);
            $uid = q('SELECT user_id FROM craftsmen WHERE id=?', [$id])->fetchColumn();

            if ($uid) {
                notify($uid, 'نأسف، لم يتم قبول طلب انضمامك للمنصة.', 'login.php');
            }
            break;

        case 'resolve':
            q('UPDATE complaints SET status="resolved" WHERE id=?', [$id]);
            break;

        case 'toggle_rev':
            q('UPDATE reviews SET status=IF(status="visible","hidden","visible") WHERE id=?', [$id]);
            break;

        case 'del_rev':
            q('DELETE FROM reviews WHERE id=?', [$id]);
            break;

        case 'close_req':
            q('UPDATE requests SET status="closed" WHERE id=?', [$id]);
            break;
    }

    flash($message);
    redirect('admin/index.php?tab=' . urlencode($tab));
}


function act(string $act, int $id, string $label, string $cls = 'ghost') {
    echo '<form method="post" class="inl">' .
        csrf_field() .
        '<input type="hidden" name="act" value="' . $act . '">' .
        '<input type="hidden" name="id" value="' . $id . '">' .
        '<button class="btn sm ' . $cls . '">' . $label . '</button></form>';
}


$title = 'لوحة الإدارة';
$nav = '';

require __DIR__ . '/../includes/header.php';


$tabs = [
    'overview' => ['نظرة عامة', 'gauge'],
    'profile' => ['بيانات مدير الإدارة', 'user-gear'],
    'contacts' => ['رسائل تواصل معنا', 'envelope'],
    'users' => ['مراجعة الحسابات', 'users'],
    'join' => ['طلبات انضمام الصنايعية', 'user-check'],
    'verify' => ['توثيق الصنايعية', 'shield-halved'],
    'photos' => ['صور الحرفيين', 'images'],
    'reports' => ['الشكاوى والتقييمات', 'flag'],
    'requests' => ['مراقبة الطلبات', 'eye']
];
?>

<div class="wrap admin">

<aside class="a-side">

    <?php foreach ($tabs as $k => $t): ?>

        <a class="<?= $tab === $k ? 'on' : '' ?>"
           href="<?= url('admin/index.php?tab=' . $k) ?>">

            <i class="fa-solid fa-<?= $t[1] ?>"></i>
            <?= $t[0] ?>

        </a>

    <?php endforeach ?>

</aside>


<main class="a-main">


<?php if ($tab === 'profile'): ?>

    <h1>بيانات مدير الإدارة</h1>

    <section class="panel">

        <form method="post"
              enctype="multipart/form-data"
              style="max-width:500px">

            <?= csrf_field() ?>

            <input type="hidden"
                   name="act"
                   value="save_admin_profile">

            <div style="text-align:center;margin-bottom:20px">

                <img
                    src="<?= e(img($u['avatar'])) ?>"
                    alt="صورة مدير الإدارة"
                    style="width:110px;height:110px;object-fit:cover;border-radius:50%;"
                >

                <p>الصورة الحالية</p>

            </div>

            <label>اسم مدير الإدارة</label>

            <input
                type="text"
                name="name"
                value="<?= e($u['name']) ?>"
                maxlength="120"
                required
                style="width:100%;margin:10px 0 20px"
            >

            <label>الصورة الشخصية</label>

            <input
                type="file"
                name="photo"
                accept="image/jpeg,image/png,image/webp"
                style="width:100%;margin:10px 0 20px"
            >

            <button type="submit" class="btn green">
                حفظ البيانات
            </button>

        </form>

    </section>


<?php elseif ($tab === 'contacts'): ?>

    <h1>رسائل تواصل معنا</h1>

    <p class="mut">كل الرسائل التي أرسلها العملاء والحرفيون من صفحة تواصل معنا.</p>

    <?php
    $contactMessages = q(
        'SELECT * FROM contact_messages ORDER BY id DESC'
    )->fetchAll();
    ?>

    <?php if (!$contactMessages): ?>

        <section class="panel">
            <p>لا توجد رسائل تواصل حتى الآن.</p>
        </section>

    <?php else: ?>

        <?php foreach ($contactMessages as $m): ?>

            <section class="panel" style="margin-bottom:15px">

                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">

                    <div>
                        <h3><?= e($m['name']) ?></h3>

                        <p>
                            <i class="fa-solid fa-envelope"></i>
                            <?= e($m['email']) ?>
                        </p>
                    </div>

                    <small>
                        رقم الرسالة: #<?= (int)$m['id'] ?>

                        <?php if (!empty($m['created_at'])): ?>
                            <br>
                            <?= e($m['created_at']) ?>
                        <?php endif ?>
                    </small>

                </div>

                <hr>

                <p style="white-space:pre-wrap"><?= e($m['message']) ?></p>

                <form method="post"
                      onsubmit="return confirm('هل أنت متأكد من حذف هذه الرسالة؟')">

                    <?= csrf_field() ?>

                    <input type="hidden" name="act" value="delete_contact">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">

                    <button class="btn sm danger" type="submit">
                        <i class="fa-solid fa-trash"></i>
                        حذف الرسالة
                    </button>

                </form>

            </section>

        <?php endforeach ?>

    <?php endif ?>


<?php elseif ($tab === 'overview'):

$s = q('SELECT
    (SELECT COUNT(*) FROM users WHERE role="client") cl,
    (SELECT COUNT(*) FROM craftsmen) cr,
    (SELECT COUNT(*) FROM requests) rq,
    (SELECT COUNT(*) FROM craftsmen WHERE verified="pending") pv,
    (SELECT COUNT(*) FROM complaints WHERE status="open") oc,
    (SELECT COUNT(*) FROM messages) ms,
    (SELECT COUNT(*) FROM contact_messages) cm
')->fetch();

?>

<h1>نظرة عامة</h1>

<div class="kpis">

    <div class="kpi">
        <i class="fa-solid fa-user"></i>
        <b><?= $s['cl'] ?></b>
        <small>العملاء</small>
    </div>

    <div class="kpi">
        <i class="fa-solid fa-helmet-safety"></i>
        <b><?= $s['cr'] ?></b>
        <small>الحرفيين</small>
    </div>

    <div class="kpi">
        <i class="fa-solid fa-file-pen"></i>
        <b><?= $s['rq'] ?></b>
        <small>طلبات الخدمة</small>
    </div>

    <div class="kpi">
        <i class="fa-solid fa-shield-halved"></i>
        <b><?= $s['pv'] ?></b>
        <small>توثيق معلّق</small>
    </div>

    <div class="kpi">
        <i class="fa-solid fa-flag"></i>
        <b><?= $s['oc'] ?></b>
        <small>شكاوى مفتوحة</small>
    </div>

    <div class="kpi">
        <i class="fa-regular fa-comments"></i>
        <b><?= $s['ms'] ?></b>
        <small>رسائل المنصة</small>
    </div>

</div>


<section class="panel">

    <h2>رسائل التواصل الأخيرة (<?= $s['cm'] ?>)</h2>

    <?php foreach (q('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 8')->fetchAll() as $m): ?>

        <div class="review">

            <div class="rh">
                <b><?= e($m['name']) ?></b>
                <small><?= e($m['email']) ?></small>
            </div>

            <p><?= e($m['message']) ?></p>

        </div>

    <?php endforeach ?>

</section>


<?php elseif ($tab === 'photos'): ?>

<h1>صور الحرفيين</h1>

<p>اختاري صورة لكل حرفي واضغطي حفظ. الصورة هتظهر في كارت الحرفي على الموقع.</p>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>الصورة الحالية</th>
    <th>اسم الحرفي</th>
    <th>الحرفة</th>
    <th>اختيار صورة جديدة</th>
    <th>حفظ</th>
</tr>

<?php

$craftsmen = q('
    SELECT
        u.id AS user_id,
        u.name,
        u.avatar,
        cat.name AS category_name
    FROM craftsmen c
    JOIN users u ON u.id = c.user_id
    JOIN categories cat ON cat.id = c.category_id
    ORDER BY u.name
')->fetchAll();

?>

<?php if (!$craftsmen): ?>

<tr>
    <td colspan="5">لا يوجد حرفيون مسجلون حاليًا.</td>
</tr>

<?php else: ?>

<?php foreach ($craftsmen as $r): ?>

<tr>

    <td>
        <img
            src="<?= e(img($r['avatar'])) ?>"
            alt="<?= e($r['name']) ?>"
            style="width:65px;height:65px;object-fit:cover;border-radius:50%;"
        >
    </td>

    <td><?= e($r['name']) ?></td>

    <td><?= e($r['category_name']) ?></td>

    <td colspan="2">

        <form method="post"
              enctype="multipart/form-data"
              style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">

            <?= csrf_field() ?>

            <input type="hidden" name="act" value="save_craftsman_photo">

            <input type="hidden" name="id" value="<?= (int)$r['user_id'] ?>">

            <input
                type="file"
                name="photo"
                accept="image/jpeg,image/png,image/webp"
                required
            >

            <button type="submit" class="btn sm green">
                حفظ الصورة
            </button>

        </form>

    </td>

</tr>

<?php endforeach ?>

<?php endif ?>

</table>

</div>


<?php elseif ($tab === 'users'): ?>

<h1>مراجعة الحسابات</h1>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>#</th>
    <th>الاسم</th>
    <th>البريد</th>
    <th>النوع</th>
    <th>الحالة</th>
    <th>التسجيل</th>
    <th>إجراءات</th>
</tr>

<?php foreach (q('SELECT * FROM users ORDER BY id DESC LIMIT 200')->fetchAll() as $r): ?>

<tr>

    <td><?= $r['id'] ?></td>

    <td><?= e($r['name']) ?></td>

    <td><?= e($r['email']) ?></td>

    <td>
        <?= ['admin' => 'مدير', 'client' => 'عميل', 'craftsman' => 'حرفي'][$r['role']] ?>
    </td>

    <td>
        <span class="pill <?= $r['status'] === 'active' ? 'ok' : 'off' ?>">
            <?= $r['status'] === 'active' ? 'نشط' : 'موقوف' ?>
        </span>
    </td>

    <td><?= date('Y/m/d', strtotime($r['created_at'])) ?></td>

    <td>

        <?php if ($r['role'] !== 'admin') {

            if ($r['status'] === 'active') {
                act('suspend', $r['id'], 'إيقاف');
            } else {
                act('activate', $r['id'], 'تفعيل', 'green');
            }

            act('del_user', $r['id'], 'حذف', 'danger');

        } ?>

    </td>

</tr>

<?php endforeach ?>

</table>

</div>


<?php elseif ($tab === 'join'): ?>

<h1>طلبات انضمام الصنايعية</h1>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>#</th>
    <th>اسم الصنايعي</th>
    <th>البريد الإلكتروني</th>
    <th>رقم الهاتف</th>
    <th>الحرفة</th>
    <th>الحالة</th>
    <th>الإجراءات</th>
</tr>

<?php

$joins = q('
    SELECT c.*, u.name, u.email, u.phone, cat.name AS category_name
    FROM craftsmen c
    JOIN users u ON u.id = c.user_id
    JOIN categories cat ON cat.id = c.category_id
    WHERE c.approval_status = "pending"
    ORDER BY c.id DESC
')->fetchAll();

?>

<?php if (!$joins): ?>

<tr>
    <td colspan="7">لا توجد طلبات انضمام معلّقة حاليًا.</td>
</tr>

<?php else: ?>

<?php foreach ($joins as $r): ?>

<tr>

    <td><?= $r['id'] ?></td>

    <td><?= e($r['name']) ?></td>

    <td><?= e($r['email']) ?></td>

    <td><?= e($r['phone'] ?? '') ?></td>

    <td><?= e($r['category_name']) ?></td>

    <td>
        <span class="pill warn">في انتظار الموافقة</span>
    </td>

    <td>
        <?php
        act('approve_join', $r['id'], 'قبول', 'green');
        act('reject_join', $r['id'], 'رفض', 'danger');
        ?>
    </td>

</tr>

<?php endforeach ?>

<?php endif ?>

</table>

</div>


<?php elseif ($tab === 'verify'): ?>

<h1>توثيق الصنايعية</h1>

<p class="mut">
    يمكنك معاينة البطاقة والشهادة قبل اتخاذ قرار التوثيق.
</p>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>الحرفي</th>
    <th>الحرفة</th>
    <th>الحالة</th>
    <th>البطاقة الشخصية</th>
    <th>الشهادة</th>
    <th>إجراءات</th>
</tr>

<?php

$verificationRequests = q('
    SELECT
        c.*,
        u.name,
        cat.name AS category_name
    FROM craftsmen c
    JOIN users u ON u.id = c.user_id
    JOIN categories cat ON cat.id = c.category_id
    ORDER BY FIELD(c.verified,"pending","none","rejected","verified"), c.id DESC
')->fetchAll();

?>

<?php if (!$verificationRequests): ?>

<tr>
    <td colspan="6">لا يوجد حرفيون مسجلون حاليًا.</td>
</tr>

<?php else: ?>

<?php foreach ($verificationRequests as $r): ?>

<tr>

    <td><?= e($r['name']) ?></td>

    <td><?= e($r['category_name']) ?></td>

    <td>

        <span class="pill <?= ['verified' => 'ok', 'pending' => 'warn', 'none' => 'off', 'rejected' => 'off'][$r['verified']] ?? 'off' ?>">

            <?= ['verified' => 'موثّق', 'pending' => 'قيد المراجعة', 'none' => 'لم يرفع', 'rejected' => 'مرفوض'][$r['verified']] ?? 'غير معروف' ?>

        </span>

    </td>

    <!-- معاينة البطاقة -->

    <td style="min-width:180px">

        <?php if (!empty($r['id_doc'])): ?>

            <?php
            $idDocUrl = url($r['id_doc']);
            $idDocExt = strtolower(pathinfo(parse_url($r['id_doc'], PHP_URL_PATH), PATHINFO_EXTENSION));
            ?>

            <?php if (in_array($idDocExt, ['jpg', 'jpeg', 'png', 'webp'], true)): ?>

                <a href="<?= e($idDocUrl) ?>" target="_blank" rel="noopener">
                    <img
                        src="<?= e($idDocUrl) ?>"
                        alt="صورة بطاقة <?= e($r['name']) ?>"
                        loading="lazy"
                        style="width:130px;height:90px;object-fit:contain;border:1px solid #ddd;border-radius:8px;background:#fff;display:block;margin-bottom:8px"
                    >
                    <span>فتح صورة البطاقة بحجم كامل</span>
                </a>

            <?php elseif ($idDocExt === 'pdf'): ?>

                <a href="<?= e($idDocUrl) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-file-pdf"></i>
                    فتح ملف البطاقة PDF
                </a>

            <?php else: ?>

                <span class="mut">صيغة الملف غير معروفة</span>

            <?php endif; ?>

        <?php else: ?>

            <span class="mut">لم يتم رفع البطاقة</span>

        <?php endif; ?>

    </td>


    <!-- معاينة الشهادة -->

    <td style="min-width:180px">

        <?php if (!empty($r['cert_doc'])): ?>

            <?php
            $certDocUrl = url($r['cert_doc']);
            $certDocExt = strtolower(pathinfo(parse_url($r['cert_doc'], PHP_URL_PATH), PATHINFO_EXTENSION));
            ?>

            <?php if (in_array($certDocExt, ['jpg', 'jpeg', 'png', 'webp'], true)): ?>

                <a href="<?= e($certDocUrl) ?>" target="_blank" rel="noopener">
                    <img
                        src="<?= e($certDocUrl) ?>"
                        alt="شهادة <?= e($r['name']) ?>"
                        loading="lazy"
                        style="width:130px;height:90px;object-fit:contain;border:1px solid #ddd;border-radius:8px;background:#fff;display:block;margin-bottom:8px"
                    >
                    <span>فتح الشهادة بحجم كامل</span>
                </a>

            <?php elseif ($certDocExt === 'pdf'): ?>

                <a href="<?= e($certDocUrl) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-file-pdf"></i>
                    فتح الشهادة PDF
                </a>

            <?php else: ?>

                <span class="mut">صيغة الملف غير معروفة</span>

            <?php endif; ?>

        <?php else: ?>

            <span class="mut">لم يرفع شهادة</span>

        <?php endif; ?>

    </td>


    <!-- إجراءات التوثيق -->

    <td style="min-width:140px">

        <?php if ($r['verified'] !== 'verified'): ?>

            <?php act('approve', $r['id'], 'توثيق', 'green'); ?>

        <?php else: ?>

            <?php act('reject', $r['id'], 'إلغاء التوثيق', 'danger'); ?>

        <?php endif ?>

    </td>

</tr>

<?php endforeach ?>

<?php endif ?>

</table>

</div>


<?php elseif ($tab === 'reports'): ?>

<h1>إدارة الشكاوى والتقييمات</h1>

<section class="panel">

<h2>الشكاوى</h2>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>ضد الحرفي</th>
    <th>من</th>
    <th>السبب</th>
    <th>الحالة</th>
    <th></th>
</tr>

<?php foreach (q('SELECT cp.*, u.name cname, c.id cid, cu.name fname FROM complaints cp JOIN craftsmen c ON c.id=cp.craftsman_id JOIN users u ON u.id=c.user_id JOIN users cu ON cu.id=cp.user_id ORDER BY cp.status, cp.id DESC LIMIT 100')->fetchAll() as $r): ?>

<tr>

    <td>
        <a href="<?= url('craftsman.php?id=' . $r['cid']) ?>">
            <?= e($r['cname']) ?>
        </a>
    </td>

    <td><?= e($r['fname']) ?></td>

    <td><?= e($r['reason']) ?></td>

    <td>
        <span class="pill <?= $r['status'] === 'open' ? 'warn' : 'ok' ?>">
            <?= $r['status'] === 'open' ? 'مفتوحة' : 'تم الحل' ?>
        </span>
    </td>

    <td>
        <?php if ($r['status'] === 'open') act('resolve', $r['id'], 'تم الحل', 'green'); ?>
    </td>

</tr>

<?php endforeach ?>

</table>

</div>

</section>


<section class="panel">

<h2>التقييمات</h2>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>الحرفي</th>
    <th>العميل</th>
    <th>التقييم</th>
    <th>التعليق</th>
    <th>الحالة</th>
    <th></th>
</tr>

<?php foreach (q('SELECT r.*, u.name cname, cu.name fname FROM reviews r JOIN craftsmen c ON c.id=r.craftsman_id JOIN users u ON u.id=c.user_id JOIN users cu ON cu.id=r.client_id ORDER BY r.id DESC LIMIT 100')->fetchAll() as $r): ?>

<tr>

    <td><?= e($r['cname']) ?></td>

    <td><?= e($r['fname']) ?></td>

    <td><?= (int)$r['rating'] ?>/5</td>

    <td><?= e($r['comment']) ?></td>

    <td>
        <span class="pill <?= $r['status'] === 'visible' ? 'ok' : 'off' ?>">
            <?= $r['status'] === 'visible' ? 'ظاهر' : 'مخفي' ?>
        </span>
    </td>

    <td>

        <?php

        act('toggle_rev', $r['id'], $r['status'] === 'visible' ? 'إخفاء' : 'إظهار');
        act('del_rev', $r['id'], 'حذف', 'danger');

        ?>

    </td>

</tr>

<?php endforeach ?>

</table>

</div>

</section>


<?php else: ?>

<h1>مراقبة الطلبات</h1>

<div class="tbl-wrap">

<table class="tbl">

<tr>
    <th>#</th>
    <th>العميل</th>
    <th>الحرفة / الخدمة</th>
    <th>المحافظة</th>
    <th>الوصف</th>
    <th>المرسل إليهم</th>
    <th>المقبول</th>
    <th>الحالة</th>
    <th></th>
</tr>

<?php foreach (q('SELECT r.*, u.name uname, cat.name cat, s.name sname, g.name gname,
    (SELECT COUNT(*) FROM request_targets t WHERE t.request_id=r.id) n,
    (SELECT COUNT(*) FROM request_targets t WHERE t.request_id=r.id AND t.status="accepted") acc
    FROM requests r
    JOIN users u ON u.id=r.client_id
    JOIN categories cat ON cat.id=r.category_id
    LEFT JOIN services s ON s.id=r.service_id
    JOIN governorates g ON g.id=r.governorate_id
    ORDER BY r.id DESC LIMIT 100')->fetchAll() as $r): ?>

<tr>

    <td><?= $r['id'] ?></td>

    <td><?= e($r['uname']) ?></td>

    <td><?= e($r['cat'] . ($r['sname'] ? ' / ' . $r['sname'] : '')) ?></td>

    <td><?= e($r['gname']) ?></td>

    <td><?= e(mb_strimwidth($r['description'], 0, 60, '…')) ?></td>

    <td><?= $r['n'] ?></td>

    <td><?= $r['acc'] ?></td>

    <td>
        <span class="pill <?= $r['status'] === 'open' ? 'ok' : 'off' ?>">
            <?= $r['status'] === 'open' ? 'مفتوح' : 'مغلق' ?>
        </span>
    </td>

    <td>
        <?php if ($r['status'] === 'open') act('close_req', $r['id'], 'إغلاق'); ?>
    </td>

</tr>

<?php endforeach ?>

</table>

</div>

<?php endif; ?>

</main>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>