<?php
require __DIR__ . '/includes/functions.php';
$u = require_login('craftsman');
$me = q('SELECT * FROM craftsmen WHERE user_id=?', [$u['id']])->fetch();
$types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $idDoc = upload_file('id_doc', 'docs', $types);
    $cert = upload_file('cert_doc', 'docs', $types);
    if (!$idDoc) { flash('ارفع صورة البطاقة (JPG / PNG / PDF حتى 5MB).', 'err'); redirect('verify.php'); }
    q('UPDATE craftsmen SET id_doc=?, cert_doc=?, verified="pending" WHERE id=?', [$idDoc, $cert ?: $me['cert_doc'], $me['id']]);
    foreach (q('SELECT id FROM users WHERE role="admin"')->fetchAll(PDO::FETCH_COLUMN) as $aid) notify($aid, 'طلب توثيق جديد من ' . $u['name'], 'admin/index.php?tab=verify');
    flash('تم إرسال مستنداتك وهتتم مراجعتها قريباً.');
    redirect('dashboard.php');
}
$title = 'توثيق الحساب'; $nav = '';
require __DIR__ . '/includes/header.php';
?>
<section class="page-h"><div class="wrap"><h1><i class="fa-solid fa-shield-halved"></i> توثيق الحساب</h1><p>الحسابات الموثقة تحصل على شارة "موثوق" وثقة أكبر من العملاء</p></div></section>
<div class="wrap">
  <form class="form-card narrow" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
    <p class="mut">الحالة الحالية: <b><?= ['none' => 'غير موثّق', 'pending' => 'قيد المراجعة', 'verified' => 'موثّق', 'rejected' => 'مرفوض — أعد رفع المستندات'][$me['verified']] ?></b></p>
    <div class="field full"><label>صورة بطاقة الرقم القومي (مطلوب)</label><input type="file" name="id_doc" accept="image/*,application/pdf" required></div>
    <div class="field full"><label>شهادة خبرة / كارنيه نقابة (اختياري)</label><input type="file" name="cert_doc" accept="image/*,application/pdf"></div>
    <button class="btn big">إرسال للتوثيق</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>