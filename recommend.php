<?php
require __DIR__ . '/includes/functions.php';
$pr = [];
foreach (['cat', 'gov', 'area', 'days', 'budget'] as $k) if (!empty($_GET[$k])) $pr[$k] = (int)$_GET[$k];
$results = !empty($pr['cat']) ? recommend($pr, 5) : [];
$title = 'الترشيح الذكي — ' . SITE_NAME; $nav = '';
require __DIR__ . '/includes/header.php';
$gid = (int)($pr['gov'] ?? 0);
?>
<section class="page-h"><div class="wrap"><h1><i class="fa-solid fa-wand-magic-sparkles"></i> ابحث، قارن، واختار الأنسب لك</h1><p>قولنا احتياجك وهنرشحلك أنسب الحرفيين مع سبب ترشيح كل واحد</p></div></section>
<div class="wrap">
  <form class="form-card" method="get">
    <div class="grid2">
      <div class="field"><label>التخصص المطلوب</label><select name="cat" required><option value="">اختر التخصص</option><?php foreach (cats() as $c): ?><option value="<?= $c['id'] ?>" <?= ($pr['cat'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></div>
      <?= gov_picker($gid) ?>
      <?= area_select($gid, (int)($pr['area'] ?? 0)) ?>
      <div class="field"><label>أحتاجه خلال</label><select name="days"><option value="">أي وقت</option><option value="0" <?= isset($_GET['days']) && $_GET['days'] === '0' ? 'selected' : '' ?>>اليوم</option><?php foreach ([2 => 'يومين', 7 => 'أسبوع', 14 => 'أسبوعين'] as $v => $l): ?><option value="<?= $v ?>" <?= ($pr['days'] ?? 0) == $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
      <div class="field"><label>أقصى ميزانية (ج.م)</label><input type="number" name="budget" min="0" value="<?= e($pr['budget'] ?? '') ?>" placeholder="مثال: 300"></div>
    </div>
    <button class="btn big"><i class="fa-solid fa-wand-magic-sparkles"></i> رشّح لي</button>
  </form>

  <?php if (!empty($pr['cat'])): ?>
    <h2 class="sec-title">الأنسب لك</h2>
    <?php if (!$results): ?><div class="empty"><h3>لا يوجد حرفيون في هذا التخصص حالياً</h3></div><?php endif ?>
    <?php foreach ($results as $i => $r): ?>
      <article class="rec-card">
        <div class="rank">#<?= $i + 1 ?></div>
        <img src="<?= e(img($r['photo'])) ?>" alt="">
        <div class="rec-info">
          <h3><a href="<?= url('craftsman.php?id=' . $r['id']) ?>"><?= e($r['name']) ?></a> <small><?= e($r['cat_name']) ?></small></h3>
          <div class="rate"><span class="stars"><?= stars($r['avg_rating']) ?></span><b><?= number_format($r['avg_rating'], 1) ?></b><small>(<?= $r['reviews_count'] ?>)</small> · <span class="price">من <b><?= number_format($r['price_from']) ?></b> ج.م</span></div>
          <div class="why"><b>سبب الترشيح:</b><ul><?php foreach ($r['why'] as $w) echo '<li><i class="fa-solid fa-circle-check"></i> ' . e($w) . '</li>'; if (!$r['why']) echo '<li>مناسب لتخصصك المطلوب</li>'; ?></ul></div>
        </div>
        <div class="rec-side">
          <div class="score" style="--p:<?= $r['score'] ?>"><b><?= $r['score'] ?>%</b><small>نسبة التوافق</small></div>
          <a class="btn block" href="<?= url('messages.php?with=' . $r['user_id']) ?>">تواصل</a>
          <a class="btn ghost block" href="<?= url('request.php?craftsman=' . $r['id']) ?>">اطلب خدمة</a>
        </div>
      </article>
    <?php endforeach ?>
  <?php endif ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>