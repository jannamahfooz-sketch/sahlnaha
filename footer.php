<footer class="foot">
  <div class="wrap foot-in">
    <div><h4><?= SITE_NAME ?></h4><p><?= SITE_TAGLINE ?> — منصة تربطك بأفضل الحرفيين الموثوقين في كل محافظات مصر.</p></div>
    <div><h4>روابط سريعة</h4>
      <a href="<?= url('crafts.php') ?>">كل الحرفيين</a><a href="<?= url('governorates.php') ?>">المحافظات</a>
      <a href="<?= url('request.php') ?>">اطلب خدمة</a><a href="<?= url('recommend.php') ?>">الترشيح الذكي</a>
    </div>
    <div><h4>تواصل معنا</h4>
      <p><i class="fa-solid fa-location-dot"></i> <?= e(OFFICE_ADDRESS) ?></p>
      <p><i class="fa-solid fa-envelope"></i> <?= e(OFFICE_EMAIL) ?></p>
      <p><i class="fa-solid fa-phone"></i> <span dir="ltr"><?= e(OFFICE_PHONE) ?></span></p>
    </div>
  </div>
  <div class="copy">© <?= date('Y') ?> <?= SITE_NAME ?> — جميع الحقوق محفوظة</div>
</footer>

<div class="cmp-bar" id="cmpBar" hidden>
  <span><i class="fa-solid fa-scale-balanced"></i> تمت إضافة <b id="cmpN">0</b> للمقارنة</span>
  <a class="btn sm" id="cmpGo" href="#">قارن الآن</a>
  <button type="button" class="btn sm ghost" id="cmpClear">مسح</button>
</div>

<div class="modal" id="govModal" hidden>
  <div class="modal-box">
    <div class="modal-h"><h3><i class="fa-regular fa-map"></i> اختر المحافظة</h3><button type="button" data-close aria-label="إغلاق">&times;</button></div>
    <div class="gov-grid">
      <?php foreach (govs() as $g): ?><button type="button" data-gov="<?= $g['id'] ?>" data-name="<?= e($g['name']) ?>"><?= e($g['name']) ?></button><?php endforeach ?>
    </div>
  </div>
</div>

<script>window.BASE = <?= json_encode(BASE_URL) ?>; window.CSRF = <?= json_encode(csrf()) ?>;</script>
<?php if (!empty($leaflet)): ?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif ?>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>