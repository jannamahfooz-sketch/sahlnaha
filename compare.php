<?php
require __DIR__ . '/includes/functions.php';
$ids = array_slice(array_values(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? '')))), 0, 4);
$rows = $ids ? search_craftsmen(['ids' => $ids], 4) : [];
usort($rows, fn($a, $b) => array_search($a['id'], $ids) <=> array_search($b['id'], $ids));
$best = ['r' => 0, 'p' => PHP_INT_MAX, 'e' => 0];
foreach ($rows as $r) { $best['r'] = max($best['r'], $r['avg_rating']); $best['p'] = min($best['p'], $r['price_from']); $best['e'] = max($best['e'], $r['experience_years']); }
$svc = [];
foreach ($rows as $r) $svc[$r['id']] = q('SELECT s.name, cs.price FROM craftsman_services cs JOIN services s ON s.id=cs.service_id WHERE cs.craftsman_id=?', [$r['id']])->fetchAll();
$title = 'مقارنة الحرفيين'; $nav = 'crafts';
require __DIR__ . '/includes/header.php';
?>
<section class="page-h"><div class="wrap"><h1><i class="fa-solid fa-scale-balanced"></i> مقارنة الحرفيين</h1><p>قارن بين الحرفيين من حيث التقييم والخبرة والأسعار والخدمات</p></div></section>
<div class="wrap">
<?php if (count($rows) < 2): ?>
  <div class="empty"><i class="fa-solid fa-scale-balanced"></i><h3>اختر حرفيين اثنين على الأقل للمقارنة</h3><a class="btn" href="<?= url('crafts.php') ?>">تصفح الحرفيين</a></div>
<?php else: ?>
  <div class="tbl-wrap"><table class="cmp-table">
    <tr><th></th><?php foreach ($rows as $r): ?><td class="top">
      <a href="<?= url('craftsman.php?id=' . $r['id']) ?>"><img src="<?= e(img($r['photo'])) ?>" alt=""><b><?= e($r['name']) ?></b></a>
      <a class="rm" href="<?= url('compare.php?ids=' . implode(',', array_diff($ids, [$r['id']]))) ?>"><i class="fa-solid fa-xmark"></i> إزالة</a></td><?php endforeach ?></tr>
    <tr><th>التخصص</th><?php foreach ($rows as $r) echo '<td>' . e($r['cat_name']) . '</td>'; ?></tr>
    <tr><th>التقييم</th><?php foreach ($rows as $r): ?><td class="<?= $r['avg_rating'] == $best['r'] ? 'best' : '' ?>"><span class="stars"><?= stars($r['avg_rating']) ?></span> <b><?= number_format($r['avg_rating'], 1) ?></b> (<?= $r['reviews_count'] ?>)</td><?php endforeach ?></tr>
    <tr><th>الخبرة</th><?php foreach ($rows as $r): ?><td class="<?= $r['experience_years'] == $best['e'] ? 'best' : '' ?>"><?= (int)$r['experience_years'] ?> سنوات</td><?php endforeach ?></tr>
    <tr><th>السعر يبدأ من</th><?php foreach ($rows as $r): ?><td class="<?= $r['price_from'] == $best['p'] ? 'best' : '' ?>"><?= number_format($r['price_from']) ?> ج.م</td><?php endforeach ?></tr>
    <tr><th>الموقع</th><?php foreach ($rows as $r) echo '<td>' . e($r['gov_name'] . ($r['area_name'] ? ' - ' . $r['area_name'] : '')) . '</td>'; ?></tr>
    <tr><th>التوافر</th><?php foreach ($rows as $r) echo '<td>' . ($r['available_days'] <= 0 ? 'متاح اليوم' : 'بعد ' . $r['available_days'] . ' يوم') . '</td>'; ?></tr>
    <tr><th>التوثيق</th><?php foreach ($rows as $r) echo '<td>' . ($r['verified'] === 'verified' ? '<span class="pill ok">موثّق</span>' : '<span class="pill off">غير موثّق</span>') . '</td>'; ?></tr>
    <tr><th>الخدمات والأسعار</th><?php foreach ($rows as $r): ?><td class="sv"><?php foreach ($svc[$r['id']] as $s) echo '<div>' . e($s['name']) . ' <b>' . number_format($s['price']) . '</b></div>'; ?></td><?php endforeach ?></tr>
    <tr><th></th><?php foreach ($rows as $r): ?><td><a class="btn block" href="<?= url('messages.php?with=' . $r['user_id']) ?>">تواصل</a></td><?php endforeach ?></tr>
  </table></div>
<?php endif ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>