<?php
require __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$c = search_craftsmen(['id' => $id], 1)[0] ?? null;

if (!$c) {
    http_response_code(404);
    exit('الحرفي غير موجود');
}

$u = user();

/* التحقق من وجود موقع حقيقي وصحيح */
$lat = $c['lat'] ?? null;
$lng = $c['lng'] ?? null;

$hasLocation = (
    $lat !== null &&
    $lat !== '' &&
    $lng !== null &&
    $lng !== '' &&
    is_numeric($lat) &&
    is_numeric($lng) &&
    (float)$lat >= -90 &&
    (float)$lat <= 90 &&
    (float)$lng >= -180 &&
    (float)$lng <= 180 &&
    !((float)$lat === 0.0 && (float)$lng === 0.0)
);

if ($hasLocation) {
    $lat = (float)$lat;
    $lng = (float)$lng;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_check();

    if (!$u) {
        redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }

    $act = $_POST['act'] ?? '';

    if ($act === 'review' && $u['role'] === 'client') {

        $rt = max(1, min(5, (int)($_POST['rating'] ?? 5)));

        q(
            'INSERT INTO reviews(craftsman_id,client_id,rating,comment)
             VALUES(?,?,?,?)
             ON DUPLICATE KEY UPDATE
             rating=VALUES(rating),
             comment=VALUES(comment),
             status="visible"',
            [
                $id,
                $u['id'],
                $rt,
                trim($_POST['comment'] ?? '')
            ]
        );

        notify(
            $c['user_id'],
            'تقييم جديد من ' . $u['name'],
            'craftsman.php?id=' . $id
        );

        flash('شكراً لتقييمك!');
    }

    if ($act === 'report' && trim($_POST['reason'] ?? '') !== '') {

        q(
            'INSERT INTO complaints(craftsman_id,user_id,reason)
             VALUES(?,?,?)',
            [
                $id,
                $u['id'],
                trim($_POST['reason'])
            ]
        );

        flash('تم إرسال الشكوى للإدارة وسيتم مراجعتها.');
    }

    redirect('craftsman.php?id=' . $id);
}


/* تسجيل زيارة الملف مرة لكل جلسة */

if (
    empty($_SESSION['v' . $id]) &&
    (!$u || $u['id'] != $c['user_id'])
) {

    $_SESSION['v' . $id] = 1;

    q(
        'INSERT INTO profile_views(craftsman_id,viewer_id)
         VALUES(?,?)',
        [$id, $u['id'] ?? null]
    );

    q(
        'UPDATE craftsmen SET views=views+1 WHERE id=?',
        [$id]
    );
}


/* الخدمات */

$svcs = q(
    'SELECT cs.id, cs.price, s.name
     FROM craftsman_services cs
     JOIN services s ON s.id=cs.service_id
     WHERE cs.craftsman_id=?
     ORDER BY cs.price',
    [$id]
)->fetchAll();


/* التقييمات */

$revs = q(
    'SELECT r.*, u.name
     FROM reviews r
     JOIN users u ON u.id=r.client_id
     WHERE r.craftsman_id=?
     AND r.status="visible"
     ORDER BY r.id DESC',
    [$id]
)->fetchAll();


/* حرفيون مشابهون */

$similar = array_values(array_filter(
    search_craftsmen([
        'cat' => $c['category_id'],
        'gov' => $c['governorate_id']
    ], 5),
    fn($x) => $x['id'] != $id
));


/* حالة التوثيق */

$ver = [
    'verified' => ['موثّق', 'ok'],
    'pending' => ['قيد التوثيق', 'warn'],
    'none' => ['غير موثّق', 'off'],
    'rejected' => ['غير موثّق', 'off']
][$c['verified']];


$title = $c['name'] . ' — ' . $c['cat_name'];
$nav = 'crafts';
$navSearch = true;
$leaflet = $hasLocation;

require __DIR__ . '/includes/header.php';
?>

<div class="wrap prof">

    <section class="prof-head">

        <img class="prof-av"
             src="<?= e(img($c['photo'])) ?>"
             alt="<?= e($c['name']) ?>">

        <div class="prof-info">

            <h1>
                <?= e($c['name']) ?>

                <span class="pill <?= $ver[1] ?>">
                    <i class="fa-solid fa-shield-halved"></i>
                    <?= $ver[0] ?>
                </span>
            </h1>

            <div class="craft">
                <?= e($c['cat_name']) ?>
            </div>

            <div class="rate">
                <span class="stars"><?= stars($c['avg_rating']) ?></span>
                <b><?= number_format($c['avg_rating'], 1) ?></b>
                <small>(<?= $c['reviews_count'] ?> تقييم)</small>
            </div>

            <div class="meta">

                <span>
                    <i class="fa-solid fa-location-dot"></i>
                    <?= e($c['gov_name'] . ($c['area_name'] ? ' - ' . $c['area_name'] : '')) ?>
                </span>

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                    <?= (int)$c['experience_years'] ?> سنوات خبرة
                </span>

                <span>
                    <i class="fa-regular fa-clock"></i>
                    <?= $c['available_days'] <= 0
                        ? 'متاح اليوم'
                        : 'متاح بعد ' . $c['available_days'] . ' يوم' ?>
                </span>

                <span>
                    <i class="fa-regular fa-eye"></i>
                    <?= number_format($c['views']) ?> مشاهدة
                </span>

            </div>

        </div>

        <div class="prof-act">

            <div class="price big">
                من <b><?= number_format($c['price_from']) ?></b> ج.م
            </div>

            <a class="btn block"
               href="<?= url('messages.php?with=' . $c['user_id']) ?>">
                <i class="fa-regular fa-comment"></i>
                راسل الحرفي
            </a>

            <a class="btn green block"
               href="<?= url('request.php?craftsman=' . $c['id']) ?>">
                <i class="fa-solid fa-file-pen"></i>
                اطلب خدمة منه
            </a>

            <div class="two">

                <button type="button"
                        class="btn ghost btn-compare"
                        data-id="<?= $c['id'] ?>">
                    <i class="fa-solid fa-scale-balanced"></i>
                    مقارنة
                </button>

                <button type="button"
                        class="btn ghost heart-btn<?= in_array($c['id'], favs()) ? ' on' : '' ?>"
                        data-fav="<?= $c['id'] ?>">
                    <i class="fa-solid fa-heart"></i>
                    مفضلة
                </button>

            </div>

        </div>

    </section>


    <div class="two-col">

        <div>

            <section class="panel">
                <h2>عن الحرفي</h2>
                <p><?= nl2br(e($c['bio'] ?: 'لا يوجد وصف.')) ?></p>
            </section>


            <section class="panel">

                <h2>الخدمات والأسعار</h2>

                <?php foreach ($svcs as $s): ?>

                    <div class="svc-row" data-svc="<?= $s['id'] ?>">

                        <span>
                            <i class="fa-solid fa-circle-check"></i>
                            <?= e($s['name']) ?>
                        </span>

                        <b><?= number_format($s['price']) ?> ج.م</b>

                    </div>

                <?php endforeach; ?>

                <?php if (!$svcs): ?>
                    <p class="mut">لم تتم إضافة خدمات بعد.</p>
                <?php endif; ?>

            </section>


            <section class="panel">

                <h2>التقييمات (<?= count($revs) ?>)</h2>

                <?php if ($u && $u['role'] === 'client'): ?>

                    <form method="post" class="rev-form">

                        <?= csrf_field() ?>

                        <input type="hidden" name="act" value="review">

                        <div class="star-pick">

                            <?php for ($i = 5; $i >= 1; $i--): ?>

                                <input type="radio"
                                       name="rating"
                                       id="s<?= $i ?>"
                                       value="<?= $i ?>"
                                       <?= $i == 5 ? 'checked' : '' ?>>

                                <label for="s<?= $i ?>">
                                    <i class="fa-solid fa-star"></i>
                                </label>

                            <?php endfor; ?>

                        </div>

                        <textarea name="comment"
                                  rows="3"
                                  placeholder="اكتب تجربتك مع الحرفي..."></textarea>

                        <button class="btn">إرسال التقييم</button>

                    </form>

                <?php endif; ?>


                <?php foreach ($revs as $r): ?>

                    <div class="review">

                        <div class="rh">

                            <b><?= e($r['name']) ?></b>

                            <span class="stars">
                                <?= stars($r['rating']) ?>
                            </span>

                            <small>
                                <?= date('Y/m/d', strtotime($r['created_at'])) ?>
                            </small>

                        </div>

                        <p><?= e($r['comment']) ?></p>

                    </div>

                <?php endforeach; ?>

            </section>

        </div>


        <aside>

            <section class="panel">

                <h2>الموقع</h2>

                <?php if ($hasLocation): ?>

                    <div id="map" class="mini-map"></div>

                <?php else: ?>

                    <p class="mut">
                        لم يحدد الحرفي موقعه على الخريطة حتى الآن.
                    </p>

                <?php endif; ?>

            </section>


            <section class="panel">

                <details>

                    <summary>
                        <i class="fa-solid fa-flag"></i>
                        الإبلاغ عن مشكلة
                    </summary>

                    <?php if ($u): ?>

                        <form method="post">

                            <?= csrf_field() ?>

                            <input type="hidden" name="act" value="report">

                            <textarea name="reason"
                                      rows="3"
                                      required
                                      placeholder="اكتب تفاصيل الشكوى..."></textarea>

                            <button class="btn danger sm">
                                إرسال الشكوى
                            </button>

                        </form>

                    <?php else: ?>

                        <p class="mut">سجّل دخولك أولاً.</p>

                    <?php endif; ?>

                </details>

            </section>

        </aside>

    </div>


    <?php if ($similar): ?>

        <section class="sec">

            <div class="sec-h">
                <h2>حرفيون مشابهون</h2>
            </div>

            <div class="cards grid4">

                <?php foreach (array_slice($similar, 0, 4) as $s): ?>
                    <?= card($s) ?>
                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</div>


<?php if ($hasLocation): ?>

<script>

window.MAP_POINTS = [
    <?= json_encode([
        'id' => (int)$c['id'],
        'name' => $c['name'],
        'lat' => $lat,
        'lng' => $lng,
        'cat' => $c['cat_name']
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
];

window.MAP_CENTER = [<?= $lat ?>, <?= $lng ?>];
window.MAP_ZOOM = 13;

</script>

<?php endif; ?>


<?php require __DIR__ . '/includes/footer.php'; ?>