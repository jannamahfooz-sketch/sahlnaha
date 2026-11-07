<?php
require __DIR__ . '/includes/functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $n = trim($_POST['name'] ?? ''); $m = trim($_POST['email'] ?? ''); $t = trim($_POST['message'] ?? '');
    if ($n && filter_var($m, FILTER_VALIDATE_EMAIL) && $t) { q('INSERT INTO contact_messages(name,email,message) VALUES(?,?,?)', [$n, $m, $t]); flash('تم إرسال رسالتك، سنرد عليك قريباً.'); }
    else flash('من فضلك أكمل البيانات.', 'err');
    redirect('contact.php');
}
$title = 'تواصل معنا — ' . SITE_NAME; $nav = 'contact';
require __DIR__ . '/includes/header.php';
?>
<section class="page-h"><div class="wrap"><h1>تواصل معنا</h1><p>يسعدنا سماع رأيك واستفساراتك</p></div></section>
<div class="wrap two-col contact">
  <div class="info-cards">
    <div class="panel"><i class="fa-solid fa-location-dot big-ic"></i><h3>عنوان المكتب</h3><p><?= e(OFFICE_ADDRESS) ?></p></div>
    <div class="panel"><i class="fa-solid fa-envelope big-ic"></i><h3>البريد الإلكتروني</h3><p><?= e(OFFICE_EMAIL) ?></p></div>
    <div class="panel"><i class="fa-solid fa-phone big-ic"></i><h3>رقم التواصل</h3><p dir="ltr" style="text-align:right"><?= e(OFFICE_PHONE) ?></p></div>
  </div>
  <form class="form-card" method="post"><?= csrf_field() ?>
    <div class="field full"><label>الاسم</label><input name="name" required></div>
    <div class="field full"><label>البريد الإلكتروني</label><input type="email" name="email" required></div>
    <div class="field full"><label>رسالتك</label><textarea name="message" rows="5" required></textarea></div>
    <button class="btn big">إرسال</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>