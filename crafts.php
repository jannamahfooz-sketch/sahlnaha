<?php
require __DIR__ . '/includes/functions.php';
$f = [];
foreach (['q','cat','gov','area','svc','rating','pmin','pmax','exp','sort','dist','lat','lng','budget'] as $k)
    if (isset($_GET[$k]) && trim($_GET[$k]) !== '') $f[$k] = trim($_GET[$k]);
if (!empty($f['budget'])) {
    [$lo, $hi] = array_map('intval', explode('-', $f['budget']) + [0, 0]);
    if ($lo && !isset($f['pmin'])) $f['pmin'] = $lo;
    if ($hi && !isset($f['pmax'])) $f['pmax'] = $hi;
}
$res = search_craftsmen($f, 60);
$gid = (int)($f['gov'] ?? 0);
$gv = $gid ? q('SELECT * FROM governorates WHERE id=?', [$gid])->fetch() : null;
$cats = cats();
$svcs = q('SELECT id,name FROM services' . (!empty($f['cat']) ? ' WHERE category_id=' . (int)$f['cat'] : '') . ' ORDER BY name')->fetchAll();
$tab = fn($c) => url('crafts.php?' . http_build_query(array_diff_key($f, ['cat' => 1, 'svc' => 1]) + ($c ? ['cat' => $c] : [])));
$pts = array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'lat' => (float)$c['lat'], 'lng' => (float)$c['lng'], 'cat' => $c['cat_name']], $res);
$title = 'كل الحرفيين — ' . SITE_NAME; $nav = 'crafts'; $navSearch = true; $leaflet = true;
require __DIR__ . '/includes/header.php';
?>
<section class="banner">
  <div class="wrap banner-in">
    <div class="banner-txt">
      <h1>اختر الخدمة اللي تحتاجها</h1>
      <p>وصول لأفضل الحرفيين في منطقتك</p>
      <div class="cat-tabs">
        <a class="ct all<?= empty($f['cat']) ? ' active' : '' ?>" href="<?= $tab(0) ?>"><i class="fa-solid fa-magnifying-glass"></i> كل الخدمات</a>
        <?php foreach (array_reverse($cats) as $c): ?>
          <a class="ct<?= ($f['cat'] ?? 0) == $c['id'] ? ' active' : '' ?>" href="<?= $tab($c['id']) ?>"><span class="ic" style="--c:<?= e($c['color']) ?>"><i class="fa-solid fa-<?= e($c['icon']) ?>"></i></span><?= e($c['name']) ?></a>
        <?php endforeach ?>
      </div>
    </div>
    <img class="banner-img" src="<?= url('assets/img/hero-worker.png') ?>" alt="">
  </div>
</section>

<div class="wrap">
  <form class="fbar" id="ff" action="<?= url('crafts.php') ?>" method="get">
    <input type="hidden" name="cat" value="<?= e($f['cat'] ?? '') ?>">
    <input type="hidden" name="q" value="<?= e($f['q'] ?? '') ?>">
    <input type="hidden" name="lat" id="lat" value="<?= e($f['lat'] ?? '') ?>">
    <input type="hidden" name="lng" id="lng" value="<?= e($f['lng'] ?? '') ?>">
    <?= gov_picker($gid, false, true) ?>
    <?= area_select($gid, (int)($f['area'] ?? 0)) ?>
    <div class="field"><label><i class="fa-solid fa-faucet-drip"></i> نوع الخدمة</label>
      <select name="svc"><option value="">اختر الخدمة</option>
        <?php foreach ($svcs as $s): ?><option value="<?= $s['id'] ?>" <?= ($f['svc'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field"><label><i class="fa-solid fa-coins"></i> الميزانية التقريبية</label>
      <select name="budget"><option value="">اختر الميزانية</option>
        <?php foreach (['0-150' => 'حتى 150 ج.م', '150-300' => '150 - 300 ج.م', '300-0' => 'أكثر من 300 ج.م'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($f['budget'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
    <button class="btn big"><i class="fa-solid fa-magnifying-glass"></i> بحث</button>
  </form>

  <div class="layout">
    <aside class="map-col">
      <div class="map-wrap">
        <div id="map"></div>
        <div class="map-label"><b><i class="fa-solid fa-location-dot"></i> <?= e($gv['name'] ?? 'كل المحافظات') ?></b><a href="<?= url('governorates.php') ?>">عرض المحافظات</a></div>
      </div>
      <div class="map-card">
        <h4><i class="fa-solid fa-location-dot"></i> لاختيار منطقة أخرى</h4>
        <p>اختر المحافظة أو المنطقة من الأعلى</p>
        <button type="button" class="btn ghost block" id="changeArea">تغيير المنطقة</button>
      </div>
    </aside>

    <main>
      <div class="res-h">
        <h2><i class="fa-solid fa-hand-holding-heart"></i> تم العثور على <?= count($res) ?> حرفي في <?= $gv ? 'محافظة ' . e($gv['name']) : 'كل المحافظات' ?></h2>
        <div class="views">
          <button type="button" data-view="grid3" class="on" aria-label="شبكة"><i class="fa-solid fa-table-cells-large"></i></button>
          <button type="button" data-view="list" aria-label="قائمة"><i class="fa-solid fa-list"></i></button>
        </div>
      </div>
      <?php if ($res): ?>
        <div class="cards grid3" id="cards"><?php foreach ($res as $c) echo card($c, true); ?></div>
      <?php else: ?>
        <div class="empty"><i class="fa-regular fa-face-frown"></i><h3>لا يوجد حرفيون مطابقون</h3><p>جرّب تغيير الفلاتر، أو ارسل طلب خدمة وهنوصله للمناسبين.</p><a class="btn" href="<?= url('request.php') ?>">اطلب خدمة</a></div>
      <?php endif ?>
    </main>

    <aside class="side">
      <h3><i class="fa-solid fa-sliders"></i> تصفية النتائج</h3>
      <div class="fgroup"><h4>التقييم</h4>
        <?php foreach ([5, 4, 3] as $r): ?><label class="rad"><input form="ff" type="radio" name="rating" value="<?= $r ?>" data-auto <?= ($f['rating'] ?? '') == $r ? 'checked' : '' ?>><span><?= $r ?> <i class="fa-solid fa-star"></i> نجوم فأكثر</span></label><?php endforeach ?></div>
      <div class="fgroup"><h4>السعر (ج.م)</h4>
        <input type="range" id="pr" min="50" max="1000" step="10" value="<?= e($f['pmax'] ?? 1000) ?>">
        <div class="two"><input form="ff" type="number" name="pmin" placeholder="من" value="<?= e($f['pmin'] ?? '') ?>" data-auto><input form="ff" type="number" id="pmax" name="pmax" placeholder="إلى" value="<?= e($f['pmax'] ?? '') ?>" data-auto></div></div>
      <div class="fgroup"><h4>الخبرة</h4>
        <?php foreach (['0' => 'أقل من سنة', '1' => 'من سنة إلى 3 سنوات', '3' => 'أكثر من 3 سنوات'] as $v => $l): ?>
          <label class="rad"><input form="ff" type="radio" name="exp" value="<?= $v ?>" data-auto <?= ($f['exp'] ?? '') === (string)$v ? 'checked' : '' ?>><span><?= $l ?></span></label><?php endforeach ?></div>
      <div class="fgroup"><h4>المسافة</h4>
        <select form="ff" name="dist" id="distSel"><option value="">أي مسافة</option>
          <?php foreach ([5, 10, 20, 50] as $d): ?><option value="<?= $d ?>" <?= ($f['dist'] ?? '') == $d ? 'selected' : '' ?>>خلال <?= $d ?> كم</option><?php endforeach ?></select></div>
      <div class="fgroup"><h4>الفرز حسب</h4>
        <select form="ff" name="sort" data-auto>
          <?php foreach (['best' => 'الأكثر ملاءمة', 'rating' => 'الأفضل تقييماً', 'price' => 'الأقل سعراً', 'exp' => 'الأكثر خبرة'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($f['sort'] ?? 'best') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?></select></div>
      <a class="btn ghost block" href="<?= url('crafts.php') ?>">مسح الفلاتر</a>
    </aside>
  </div>
</div>
<script>
window.MAP_POINTS = <?= json_encode($pts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
<?php if ($gv): ?>window.MAP_CENTER = [<?= (float)$gv['lat'] ?>, <?= (float)$gv['lng'] ?>]; window.MAP_ZOOM = 11;<?php endif ?>
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>