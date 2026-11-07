<?php
require __DIR__ . '/includes/functions.php';
$u = require_login('client');
$target = (int)($_GET['craftsman'] ?? $_POST['craftsman'] ?? 0);
$tc = $target ? (search_craftsmen(['id' => $target], 1)[0] ?? null) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cat = (int)($_POST['cat'] ?? 0); $gov = (int)($_POST['gov'] ?? 0); $area = (int)($_POST['area'] ?? 0); $svc = (int)($_POST['service'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    if (!$cat || !$gov || mb_strlen($desc) < 10) { flash('من فضلك أكمل البيانات المطلوبة (الوصف 10 أحرف على الأقل).', 'err'); redirect('request.php'); }
    q('INSERT INTO requests(client_id,category_id,service_id,governorate_id,area_id,address,description,budget,urgency) VALUES(?,?,?,?,?,?,?,?,?)',
      [$u['id'], $cat, $svc ?: null, $gov, $area ?: null, trim($_POST['address'] ?? ''), $desc, (int)($_POST['budget'] ?? 0) ?: null, ($_POST['urgency'] ?? '') === 'urgent' ? 'urgent' : 'normal']);
    $rid = (int)db()->lastInsertId();
    $catName = q('SELECT name FROM categories WHERE id=?', [$cat])->fetchColumn();
    $targets = $tc ? [$tc] : recommend(['cat' => $cat, 'gov' => $gov, 'area' => $area, 'budget' => (int)($_POST['budget'] ?? 0)], 5);
    foreach ($targets as $t) {
        q('INSERT IGNORE INTO request_targets(request_id,craftsman_id) VALUES(?,?)', [$rid, $t['id']]);
        notify($t['user_id'], 'طلب خدمة جديد (' . $catName . ') من ' . $u['name'], 'dashboard.php#requests');
    }
    flash($targets ? 'تم إرسال طلبك إلى ' . count($targets) . ' حرفي مناسب، وهنبلغك أول ما حد يقبل.' : 'تم حفظ طلبك، ولم نجد حرفيين مطابقين حالياً وسنُبقيه مفتوحاً.');
    redirect('dashboard.php');
}
$pre = $tc ? (int)$tc['category_id'] : (int)($_GET['cat'] ?? 0);
$gid = $tc ? (int)$tc['governorate_id'] : (int)($_GET['gov'] ?? 0);
$svcs = $pre ? q('SELECT id,name FROM services WHERE category_id=? ORDER BY name', [$pre])->fetchAll() : [];
$title = 'طلب خدمة'; $nav = '';
require __DIR__ . '/includes/header.php';
?>
<section class="page-h"><div class="wrap"><h1><i class="fa-solid fa-file-pen"></i> اطلب خدمة</h1><p><?= $tc ? 'طلب موجّه إلى ' . e($tc['name']) : 'حدد المشكلة والمكان وهنوصل طلبك لأنسب الحرفيين' ?></p></div></section>
<div class="wrap">
  <form class="form-card" method="post"><?= csrf_field() ?>
    <?php if ($tc): ?><input type="hidden" name="craftsman" value="<?= $tc['id'] ?>"><?php endif ?>
    <div class="grid2">
      <div class="field"><label>نوع المشكلة / الحرفة</label><select name="cat" id="catSel" required <?= $tc ? 'style="pointer-events:none;background:#f1f6fd"' : '' ?>><option value="">اختر الحرفة</option><?php foreach (cats() as $c): ?><option value="<?= $c['id'] ?>" <?= $pre == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
      <div class="field"><label>الخدمة المطلوبة</label><select name="service" id="svcSel"><option value="">اختر الخدمة</option><?php foreach ($svcs as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach ?></select></div>
      <?= gov_picker($gid) ?>
      <?= area_select($gid, (int)($tc['area_id'] ?? 0)) ?>
      <div class="field full"><label>العنوان التفصيلي</label><input name="address" placeholder="الشارع، رقم العمارة، علامة مميزة..."></div>
      <div class="field full"><label>وصف المشكلة</label><textarea name="description" rows="5" required placeholder="اشرح المشكلة بالتفصيل عشان الحرفي يقدر يقدّر الشغل..."></textarea></div>
      <div class="field"><label>الميزانية التقريبية (ج.م)</label><input type="number" name="budget" min="0" placeholder="اختياري"></div>
      <div class="field"><label>درجة الاستعجال</label><select name="urgency"><option value="normal">عادي</option><option value="urgent">عاجل</option></select></div>
    </div>
    <button class="btn big"><i class="fa-solid fa-paper-plane"></i> إرسال الطلب</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>