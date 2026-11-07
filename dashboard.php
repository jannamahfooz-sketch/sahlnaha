<?php
require __DIR__ . '/includes/functions.php';

$u = require_login();

if ($u['role'] === 'admin') {
    redirect('admin/index.php');
}

$me = $u['role'] === 'craftsman'
    ? q(
        'SELECT c.*, cat.name cat_name
         FROM craftsmen c
         JOIN categories cat ON cat.id=c.category_id
         WHERE c.user_id=?',
        [$u['id']]
    )->fetch()
    : null;

/* التحقق من الموقع المحفوظ */
$locationValid = false;

if ($me && isset($me['lat'], $me['lng']) && is_numeric($me['lat']) && is_numeric($me['lng'])) {
    $savedLat = (float)$me['lat'];
    $savedLng = (float)$me['lng'];

    $locationValid =
        $savedLat >= -90 &&
        $savedLat <= 90 &&
        $savedLng >= -180 &&
        $savedLng <= 180 &&
        !($savedLat == 0 && $savedLng == 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $act = $_POST['act'] ?? '';

    /* تحديث الملف الشخصي والموقع */
    if ($act === 'profile' && $me) {

        $latRaw = trim((string)($_POST['lat'] ?? ''));
        $lngRaw = trim((string)($_POST['lng'] ?? ''));

        $lat = null;
        $lng = null;

        if ($latRaw !== '' || $lngRaw !== '') {

            if (
                $latRaw === '' ||
                $lngRaw === '' ||
                !is_numeric($latRaw) ||
                !is_numeric($lngRaw)
            ) {
                flash('بيانات الموقع غير مكتملة أو غير صحيحة.');
                redirect('dashboard.php');
            }

            $lat = (float)$latRaw;
            $lng = (float)$lngRaw;

            if (
                $lat < -90 ||
                $lat > 90 ||
                $lng < -180 ||
                $lng > 180 ||
                ($lat == 0 && $lng == 0)
            ) {
                flash('اختار موقعك الصحيح على الخريطة.');
                redirect('dashboard.php');
            }
        }

        q(
            'UPDATE craftsmen
             SET bio=?,
                 price_from=?,
                 available_days=?,
                 experience_years=?,
                 lat=?,
                 lng=?
             WHERE id=?',
            [
                trim($_POST['bio'] ?? ''),
                max(0, (int)($_POST['price_from'] ?? 0)),
                max(0, (int)($_POST['available_days'] ?? 0)),
                max(0, (int)($_POST['experience_years'] ?? 0)),
                $lat,
                $lng,
                $me['id']
            ]
        );

        if ($p = upload_file('photo', 'avatars')) {
            q(
                'UPDATE users SET avatar=? WHERE id=?',
                [$p, $u['id']]
            );
        }

        flash('تم تحديث الملف الشخصي بنجاح');
    }

    /* قبول أو رفض الطلبات */
    if (in_array($act, ['accept', 'decline'], true) && $me) {

        $rid = (int)($_POST['rid'] ?? 0);

        $t = q(
            'SELECT t.id, r.client_id
             FROM request_targets t
             JOIN requests r ON r.id=t.request_id
             WHERE t.request_id=? AND t.craftsman_id=?',
            [$rid, $me['id']]
        )->fetch();

        if ($t) {

            q(
                'UPDATE request_targets SET status=? WHERE id=?',
                [
                    $act === 'accept' ? 'accepted' : 'declined',
                    $t['id']
                ]
            );

            if ($act === 'accept') {

                q(
                    'INSERT INTO messages(sender_id,receiver_id,body)
                     VALUES(?,?,?)',
                    [
                        $u['id'],
                        $t['client_id'],
                        'مرحباً، قبلت طلبك رقم #' . $rid .
                        ' ويسعدني خدمتك. تقدر تكلمني هنا لتحديد التفاصيل والموعد.'
                    ]
                );

                notify(
                    $t['client_id'],
                    $u['name'] . ' قبل طلبك رقم #' . $rid,
                    'messages.php?with=' . $u['id']
                );
            }

            flash('تم تحديث حالة الطلب');
        }
    }

    /* إغلاق طلب العميل */
    if ($act === 'close' && !$me) {

        q(
            'UPDATE requests SET status="closed"
             WHERE id=? AND client_id=?',
            [(int)$_POST['rid'], $u['id']]
        );

        flash('تم إغلاق الطلب');
    }

    redirect('dashboard.php');
}

$title = 'لوحة التحكم';
$nav = '';

/* تحميل مكتبة الخرائط للصنايعي */
$leaflet = (bool)$me;

if ($me) {

    $days = [];

    for ($i = 6; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i day"))] = 0;
    }

    $viewRows = q(
        'SELECT DATE(created_at) d, COUNT(*) n
         FROM profile_views
         WHERE craftsman_id=?
         AND created_at>=CURDATE()-INTERVAL 6 DAY
         GROUP BY d',
        [$me['id']]
    )->fetchAll();

    foreach ($viewRows as $r) {
        $days[$r['d']] = (int)$r['n'];
    }

    $max = max(1, max($days));

    $reqCount = (int)q(
        'SELECT COUNT(*)
         FROM request_targets
         WHERE craftsman_id=?',
        [$me['id']]
    )->fetchColumn();

    $rv = q(
        'SELECT COUNT(*) n, COALESCE(AVG(rating),0) a
         FROM reviews
         WHERE craftsman_id=? AND status="visible"',
        [$me['id']]
    )->fetch();

    $topSvc = q(
        'SELECT s.name, cs.views
         FROM craftsman_services cs
         JOIN services s ON s.id=cs.service_id
         WHERE cs.craftsman_id=?
         ORDER BY cs.views DESC
         LIMIT 5',
        [$me['id']]
    )->fetchAll();

    $inc = q(
        'SELECT r.*, t.status ts,
                cat.name cname,
                s.name sname,
                g.name gname,
                a.name aname,
                u.name uname,
                u.id uid
         FROM request_targets t
         JOIN requests r ON r.id=t.request_id
         JOIN categories cat ON cat.id=r.category_id
         LEFT JOIN services s ON s.id=r.service_id
         JOIN governorates g ON g.id=r.governorate_id
         LEFT JOIN areas a ON a.id=r.area_id
         JOIN users u ON u.id=r.client_id
         WHERE t.craftsman_id=?
         ORDER BY r.id DESC',
        [$me['id']]
    )->fetchAll();

    $lastRevs = q(
        'SELECT r.*, u.name
         FROM reviews r
         JOIN users u ON u.id=r.client_id
         WHERE r.craftsman_id=?
         AND r.status="visible"
         ORDER BY r.id DESC
         LIMIT 5',
        [$me['id']]
    )->fetchAll();

} else {

    $reqs = q(
        'SELECT r.*, cat.name cname, s.name sname, g.name gname
         FROM requests r
         JOIN categories cat ON cat.id=r.category_id
         LEFT JOIN services s ON s.id=r.service_id
         JOIN governorates g ON g.id=r.governorate_id
         WHERE r.client_id=?
         ORDER BY r.id DESC',
        [$u['id']]
    )->fetchAll();

    $favRows = search_craftsmen(['ids' => favs()], 12);
}

require __DIR__ . '/includes/header.php';

$vmap = [
    'verified' => ['موثّق', 'ok'],
    'pending' => ['قيد المراجعة', 'warn'],
    'none' => ['غير موثّق', 'off'],
    'rejected' => ['مرفوض', 'off']
];
?>

<section class="page-h">
    <div class="wrap">
        <h1>أهلاً، <?= e($u['name']) ?> 👋</h1>
        <p><?= $me ? 'لوحة إحصائيات الحرفي' : 'متابعة طلباتك ومفضلتك' ?></p>
    </div>
</section>

<div class="wrap dash">

<?php if ($me): $v = $vmap[$me['verified']] ?? ['غير موثّق', 'off']; ?>

    <?php if ($me['verified'] !== 'verified'): ?>
        <div class="alert">
            حسابك <b><?= $v[0] ?></b> — وثّق حسابك لتحصل على شارة "موثوق" وتظهر في أول النتائج.
            <a class="btn sm" href="<?= url('verify.php') ?>">توثيق الحساب</a>
        </div>
    <?php endif; ?>

    <div class="kpis">

        <div class="kpi">
            <i class="fa-regular fa-eye"></i>
            <b><?= number_format($me['views']) ?></b>
            <small>زيارات الملف</small>
        </div>

        <div class="kpi">
            <i class="fa-solid fa-file-pen"></i>
            <b><?= $reqCount ?></b>
            <small>طلبات الخدمة</small>
        </div>

        <div class="kpi">
            <i class="fa-solid fa-star"></i>
            <b><?= number_format($rv['a'], 1) ?></b>
            <small><?= $rv['n'] ?> تقييم</small>
        </div>

        <div class="kpi">
            <i class="fa-solid fa-shield-halved"></i>
            <b><span class="pill <?= $v[1] ?>"><?= $v[0] ?></span></b>
            <small>حالة التوثيق</small>
        </div>

    </div>

    <div class="two-col">

        <section class="panel">
            <h2>الزيارات آخر 7 أيام</h2>

            <div class="chart">
                <?php foreach ($days as $d => $n): ?>
                    <div class="bar">
                        <span style="height:<?= round($n / $max * 100) ?>%">
                            <em><?= $n ?></em>
                        </span>
                        <small><?= date('d/m', strtotime($d)) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel">
            <h2>أكثر الخدمات مشاهدة</h2>

            <?php foreach ($topSvc as $s): ?>
                <div class="svc-row">
                    <span><?= e($s['name']) ?></span>
                    <b><?= (int)$s['views'] ?> مشاهدة</b>
                </div>
            <?php endforeach; ?>

            <?php if (!$topSvc): ?>
                <p class="mut">لا توجد بيانات بعد.</p>
            <?php endif; ?>
        </section>

    </div>

    <section class="panel" id="requests">
        <h2>طلبات الخدمة الواردة</h2>

        <?php foreach ($inc as $r): ?>

            <div class="req">

                <div>
                    <b>
                        #<?= $r['id'] ?> —
                        <?= e($r['sname'] ?: $r['cname']) ?>
                    </b>

                    <?= $r['urgency'] === 'urgent'
                        ? '<span class="pill warn">عاجل</span>'
                        : '' ?>

                    <p><?= e($r['description']) ?></p>

                    <small>
                        <i class="fa-solid fa-user"></i>
                        <?= e($r['uname']) ?>
                        ·
                        <i class="fa-solid fa-location-dot"></i>
                        <?= e($r['gname'] . ($r['aname'] ? ' - ' . $r['aname'] : '')) ?>
                        <?= $r['budget'] ? ' · الميزانية ' . $r['budget'] . ' ج.م' : '' ?>
                    </small>
                </div>

                <div class="req-act">

                    <?php if ($r['ts'] === 'new'): ?>

                        <form method="post">
                            <?= csrf_field() ?>

                            <input type="hidden" name="rid" value="<?= $r['id'] ?>">

                            <button name="act" value="accept" class="btn green sm">
                                قبول
                            </button>

                            <button name="act" value="decline" class="btn ghost sm">
                                رفض
                            </button>
                        </form>

                    <?php else: ?>

                        <span class="pill <?= $r['ts'] === 'accepted' ? 'ok' : 'off' ?>">
                            <?= $r['ts'] === 'accepted' ? 'تم القبول' : 'مرفوض' ?>
                        </span>

                    <?php endif; ?>

                    <a class="btn ghost sm" href="<?= url('messages.php?with=' . $r['uid']) ?>">
                        مراسلة
                    </a>

                </div>

            </div>

        <?php endforeach; ?>

        <?php if (!$inc): ?>
            <p class="mut">لا توجد طلبات واردة حالياً.</p>
        <?php endif; ?>

    </section>

    <div class="two-col">

        <section class="panel">
            <h2>آخر التقييمات</h2>

            <?php foreach ($lastRevs as $r): ?>
                <div class="review">
                    <div class="rh">
                        <b><?= e($r['name']) ?></b>
                        <span class="stars"><?= stars($r['rating']) ?></span>
                    </div>
                    <p><?= e($r['comment']) ?></p>
                </div>
            <?php endforeach; ?>

            <?php if (!$lastRevs): ?>
                <p class="mut">لا توجد تقييمات بعد.</p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>تحديث الملف الشخصي</h2>

            <form method="post" enctype="multipart/form-data" class="stack">

                <?= csrf_field() ?>

                <input type="hidden" name="act" value="profile">

                <label>
                    نبذة عنك
                    <textarea name="bio" rows="4"><?= e($me['bio']) ?></textarea>
                </label>

                <label>
                    سنوات الخبرة
                    <input type="number" name="experience_years"
                           min="0" value="<?= (int)$me['experience_years'] ?>">
                </label>

                <label>
                    السعر يبدأ من (ج.م)
                    <input type="number" name="price_from"
                           min="0" value="<?= (int)$me['price_from'] ?>">
                </label>

                <label>
                    متاح بعد كم يوم؟ (0 = اليوم)
                    <input type="number" name="available_days"
                           min="0" value="<?= (int)$me['available_days'] ?>">
                </label>

                <label>
                    صورة جديدة
                    <input type="file" name="photo" accept="image/*">
                </label>

                <div class="location-editor">

                    <h3>حدد موقعك على الخريطة</h3>

                    <p class="mut">
                        اضغط على الخريطة لتحديد مكانك، أو استخدم تحديد الموقع التلقائي.
                    </p>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:12px 0;">

                        <button type="button" class="btn ghost" id="get-my-location">
                            <i class="fa-solid fa-location-crosshairs"></i>
                            تحديد موقعي تلقائياً
                        </button>

                        <button type="button" class="btn ghost" id="clear-craftsman-location">
                            إزالة الموقع
                        </button>

                    </div>

                    <p id="location-status" class="mut">
                        <?php if ($locationValid): ?>
                            موقعك المحفوظ ظاهر على الخريطة.
                        <?php else: ?>
                            لم يتم تحديد موقعك بعد. اضغط على الخريطة لاختيار موقعك.
                        <?php endif; ?>
                    </p>

                    <div id="craftsman-location-map"
                         style="height:350px;width:100%;border-radius:14px;margin:12px 0;z-index:1;">
                    </div>

                    <input type="hidden"
                           name="lat"
                           id="craftsman-lat"
                           value="<?= $locationValid ? e($me['lat']) : '' ?>">

                    <input type="hidden"
                           name="lng"
                           id="craftsman-lng"
                           value="<?= $locationValid ? e($me['lng']) : '' ?>">

                </div>

                <button class="btn">حفظ التعديلات</button>

            </form>
        </section>

    </div>

<?php else: ?>

    <section class="panel" id="requests">

        <div class="sec-h">
            <h2>طلباتي</h2>

            <a class="btn sm" href="<?= url('request.php') ?>">
                <i class="fa-solid fa-plus"></i>
                طلب جديد
            </a>
        </div>

        <?php foreach ($reqs as $r):

            $tg = q(
                'SELECT t.status, u.id uid, u.name
                 FROM request_targets t
                 JOIN craftsmen c ON c.id=t.craftsman_id
                 JOIN users u ON u.id=c.user_id
                 WHERE t.request_id=?',
                [$r['id']]
            )->fetchAll();

        ?>

            <div class="req">

                <div>

                    <b>
                        #<?= $r['id'] ?> —
                        <?= e($r['sname'] ?: $r['cname']) ?>
                    </b>

                    <span class="pill <?= $r['status'] === 'open' ? 'ok' : 'off' ?>">
                        <?= $r['status'] === 'open' ? 'مفتوح' : 'مغلق' ?>
                    </span>

                    <p><?= e($r['description']) ?></p>

                    <small>
                        <i class="fa-solid fa-location-dot"></i>
                        <?= e($r['gname']) ?>
                        · أُرسل إلى <?= count($tg) ?> حرفي
                    </small>

                    <div class="tg">

                        <?php foreach ($tg as $t): ?>

                            <a class="pill <?= $t['status'] === 'accepted' ? 'ok' : ($t['status'] === 'declined' ? 'off' : 'warn') ?>"
                               href="<?= url('messages.php?with=' . $t['uid']) ?>">

                                <?= e($t['name']) ?> —
                                <?= [
                                    'new' => 'بانتظار الرد',
                                    'accepted' => 'قبل الطلب',
                                    'declined' => 'رفض'
                                ][$t['status']] ?>

                            </a>

                        <?php endforeach; ?>

                    </div>

                </div>

                <?php if ($r['status'] === 'open'): ?>

                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="rid" value="<?= $r['id'] ?>">

                        <button name="act" value="close" class="btn ghost sm">
                            إغلاق الطلب
                        </button>
                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

        <?php if (!$reqs): ?>
            <p class="mut">لم ترسل أي طلبات بعد.</p>
        <?php endif; ?>

    </section>

    <section class="sec" id="fav">

        <div class="sec-h">
            <h2><i class="fa-solid fa-heart"></i> المفضلة</h2>
        </div>

        <div class="cards grid4">
            <?php foreach ($favRows as $c) echo card($c); ?>
        </div>

        <?php if (!$favRows): ?>
            <p class="mut">لم تضف أي حرفي للمفضلة بعد.</p>
        <?php endif; ?>

    </section>

<?php endif; ?>

</div>

<?php if ($me): ?>
<script>
(function () {
    const mapElement = document.getElementById('craftsman-location-map');
    if (!mapElement) return;

    const latInput = document.getElementById('craftsman-lat');
    const lngInput = document.getElementById('craftsman-lng');
    const status = document.getElementById('location-status');
    const locateButton = document.getElementById('get-my-location');
    const clearButton = document.getElementById('clear-craftsman-location');

    let map = null;
    let marker = null;

    function setStatus(message) {
        status.textContent = message;
    }

    function setLocation(lat, lng) {
        lat = Number(lat);
        lng = Number(lng);

        if (
            !Number.isFinite(lat) ||
            !Number.isFinite(lng) ||
            lat < -90 || lat > 90 ||
            lng < -180 || lng > 180 ||
            (lat === 0 && lng === 0)
        ) {
            setStatus('الإحداثيات غير صحيحة. اختار موقعاً آخر.');
            return;
        }

        latInput.value = lat;
        lngInput.value = lng;

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }

        map.setView([lat, lng], 16);
        setStatus('تم تحديد الموقع. اضغط على «حفظ التعديلات» لحفظه.');
    }

    function initializeMap() {
        if (typeof L === 'undefined') {
            setStatus('تعذر تحميل الخريطة. تأكد من اتصال الإنترنت ثم حدّث الصفحة.');
            return;
        }

        const oldLat = Number(latInput.value);
        const oldLng = Number(lngInput.value);

        const hasOldLocation =
            latInput.value !== '' &&
            lngInput.value !== '' &&
            Number.isFinite(oldLat) &&
            Number.isFinite(oldLng) &&
            oldLat >= -90 && oldLat <= 90 &&
            oldLng >= -180 && oldLng <= 180 &&
            !(oldLat === 0 && oldLng === 0);

        const startPosition = hasOldLocation
            ? [oldLat, oldLng]
            : [26.8, 30.8];

        map = L.map('craftsman-location-map', {
            scrollWheelZoom: false
        }).setView(startPosition, hasOldLocation ? 16 : 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        if (hasOldLocation) {
            marker = L.marker([oldLat, oldLng]).addTo(map);
        }

        map.on('click', function (event) {
            setLocation(event.latlng.lat, event.latlng.lng);
        });

        setTimeout(function () {
            map.invalidateSize();
        }, 300);
    }

    function loadLeaflet() {
        if (typeof L !== 'undefined') {
            initializeMap();
            return;
        }

        if (!document.querySelector('link[data-location-leaflet]')) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            css.dataset.locationLeaflet = '1';
            document.head.appendChild(css);
        }

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

        script.onload = initializeMap;

        script.onerror = function () {
            setStatus('لم نتمكن من تحميل الخريطة. تأكد من الإنترنت.');
        };

        document.head.appendChild(script);
    }

    locateButton.addEventListener('click', function () {
        if (!navigator.geolocation) {
            setStatus('المتصفح لا يدعم تحديد الموقع. اضغط على الخريطة لتحديده يدوياً.');
            return;
        }

        setStatus('جاري تحديد موقعك...');

        navigator.geolocation.getCurrentPosition(
            function (position) {
                setLocation(
                    position.coords.latitude,
                    position.coords.longitude
                );
            },
            function (error) {
                if (error.code === 1) {
                    setStatus('لم تسمح بالوصول للموقع. يمكنك تحديد مكانك بالنقر على الخريطة.');
                } else {
                    setStatus('تعذر تحديد موقعك تلقائياً. اضغط على الخريطة لتحديده يدوياً.');
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    });

    clearButton.addEventListener('click', function () {
        latInput.value = '';
        lngInput.value = '';

        if (marker && map) {
            map.removeLayer(marker);
            marker = null;
        }

        setStatus('تم إزالة الموقع. اضغط على الخريطة لاختيار موقع جديد.');
    });

    loadLeaflet();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>