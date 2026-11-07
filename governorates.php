<?php
require __DIR__ . '/includes/functions.php';
$title = 'المحافظات — ' . SITE_NAME; $nav = 'crafts';
$rows = q('SELECT g.*, (SELECT COUNT(*) FROM craftsmen c WHERE c.governorate_id=g.id) n FROM governorates g ORDER BY g.name')->fetchAll();
require __DIR__ . '/includes/header.php';
?>
<section class="page-h"><div class="wrap"><h1>محافظات مصر</h1><p>اختر محافظتك لتشوف كل الحرفيين المتاحين فيها</p></div></section>
<div class="wrap">
  <div class="gov-cards">
    <?php foreach ($rows as $g): ?>
      <a class="gov-card" href="<?= url('crafts.php?gov=' . $g['id']) ?>">
        <i class="fa-solid fa-location-dot"></i><b><?= e($g['name']) ?></b><small><?= (int)$g['n'] ?> حرفي</small>
      </a>
    <?php endforeach ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>