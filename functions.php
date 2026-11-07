<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement {
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function url(string $p = ''): string {
    return BASE_URL . $p;
}

function redirect(string $p) {
    header('Location: ' . ((($p[0] ?? '') === '/' || preg_match('#^https?://#', $p)) ? $p : url($p)));
    exit;
}

function user() {
    static $u = null, $done = false;

    if (!$done) {
        $done = true;

        $u = isset($_SESSION['uid'])
            ? (q('SELECT * FROM users WHERE id=? AND status="active"', [$_SESSION['uid']])->fetch() ?: null)
            : null;

        if ($u && $u['role'] === 'craftsman') {
            $craftsman = q(
                'SELECT approval_status FROM craftsmen WHERE user_id=?',
                [$u['id']]
            )->fetch();

            if (!$craftsman || $craftsman['approval_status'] !== 'approved') {
                $u = null;
            }
        }

        if (!$u) {
            unset($_SESSION['uid']);
        }
    }

    return $u;
}

function require_login(?string $role = null) {
    $u = user();

    if (!$u) redirect('login.php?next=' . urlencode($_SERVER['REQUEST_URI']));

    if ($role && $u['role'] !== $role) redirect('index.php');

    return $u;
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf() . '">';
}

function csrf_check() {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? 'x')) {
        http_response_code(419);
        exit('انتهت صلاحية الجلسة، أعد المحاولة.');
    }
}

function flash($m = null, $t = 'ok') {
    if ($m !== null) {
        $_SESSION['flash'] = [$m, $t];
        return null;
    }

    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $f;
}

function upload_file(
    string $key,
    string $sub,
    array $types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ]
): ?string {

    if (
        empty($_FILES[$key]) ||
        $_FILES[$key]['error'] !== UPLOAD_ERR_OK ||
        $_FILES[$key]['size'] > 5 * 1024 * 1024
    ) {
        return null;
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES[$key]['tmp_name']);

    if (!isset($types[$mime])) return null;

    $dir = UPLOAD_DIR . $sub . '/';

    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = bin2hex(random_bytes(10)) . '.' . $types[$mime];

    return move_uploaded_file($_FILES[$key]['tmp_name'], $dir . $name)
        ? UPLOAD_URL . $sub . '/' . $name
        : null;
}

function notify(int $uid, string $text, string $link = '') {
    q(
        'INSERT INTO notifications(user_id,text,link) VALUES(?,?,?)',
        [$uid, $text, $link]
    );
}

function img($path, string $def = 'assets/img/workers/default.jpg'): string {
    return url($path ?: $def);
}

function stars($r): string {
    $o = '';

    for ($i = 1; $i <= 5; $i++) {
        $o .= '<i class="fa-solid fa-star' . ($i <= round($r) ? '' : ' off') . '"></i>';
    }

    return $o;
}

function cats(): array {
    static $c;
    return $c ??= q('SELECT * FROM categories ORDER BY id')->fetchAll();
}

function govs(): array {
    static $g;
    return $g ??= q('SELECT * FROM governorates ORDER BY name')->fetchAll();
}

function favs(): array {
    static $f;

    if ($f === null) {
        $u = user();
        $f = $u
            ? q('SELECT craftsman_id FROM favorites WHERE user_id=?', [$u['id']])->fetchAll(PDO::FETCH_COLUMN)
            : [];
    }

    return $f;
}

/* ---------- حقول البحث المشتركة ---------- */

function gov_picker(int $sel = 0, bool $go = false, bool $submit = false): string {

    $label = 'اختر المحافظة';

    foreach (govs() as $g) {
        if ($g['id'] == $sel) $label = $g['name'];
    }

    return '<div class="field"><label><i class="fa-regular fa-map"></i> المحافظة</label>'
        . '<button type="button" class="picker" data-open-gov'
        . ($go ? ' data-go="1"' : '')
        . ($submit ? ' data-submit="1"' : '')
        . '><span class="pick-label">' . e($label) . '</span><i class="fa-solid fa-chevron-down"></i></button>'
        . '<input type="hidden" name="gov" value="' . ($sel ?: '') . '"></div>';
}

function area_select(int $gov = 0, int $sel = 0): string {

    $o = '<option value="">' . ($gov ? 'كل المناطق' : 'اختر المنطقة') . '</option>';

    if ($gov) {
        foreach (q('SELECT id,name FROM areas WHERE governorate_id=? ORDER BY name', [$gov])->fetchAll() as $a) {
            $o .= '<option value="' . $a['id'] . '"' . ($a['id'] == $sel ? ' selected' : '') . '>'
                . e($a['name']) . '</option>';
        }
    }

    return '<div class="field"><label><i class="fa-solid fa-location-dot"></i> المنطقة</label><select name="area">' . $o . '</select></div>';
}

/* ---------- البحث والتصفية ---------- */

function search_craftsmen(array $f = [], int $limit = 60): array {

    // عرض الحسابات النشطة والمقبولة من الإدارة فقط
    $w = [
        'u.status="active"',
        'c.approval_status="approved"'
    ];

    $p = [];
    $sp = [];
    $hp = [];
    $dist = '0';
    $hav = '';

    if (!empty($f['id'])) {
        $w[] = 'c.id=?';
        $p[] = (int)$f['id'];
    }

    if (isset($f['ids'])) {
        $ids = array_map('intval', (array)$f['ids']);
        $w[] = $ids
            ? 'c.id IN (' . implode(',', $ids) . ')'
            : '0';
    }

    if (!empty($f['cat'])) {
        $w[] = 'c.category_id=?';
        $p[] = (int)$f['cat'];
    }

    if (!empty($f['gov'])) {
        $w[] = 'c.governorate_id=?';
        $p[] = (int)$f['gov'];
    }

    if (!empty($f['area'])) {
        $w[] = 'c.area_id=?';
        $p[] = (int)$f['area'];
    }

    if (!empty($f['svc'])) {
        $w[] = 'EXISTS(SELECT 1 FROM craftsman_services cs WHERE cs.craftsman_id=c.id AND cs.service_id=?)';
        $p[] = (int)$f['svc'];
    }

    if (!empty($f['q'])) {
        $k = '%' . $f['q'] . '%';

        $w[] = '(u.name LIKE ? OR c.bio LIKE ? OR cat.name LIKE ? OR EXISTS(
            SELECT 1
            FROM craftsman_services cs
            JOIN services s ON s.id=cs.service_id
            WHERE cs.craftsman_id=c.id
            AND (s.name LIKE ? OR s.keywords LIKE ?)
        ))';

        array_push($p, $k, $k, $k, $k, $k);
    }

    if (!empty($f['rating'])) {
        $w[] = 'COALESCE(r.avg_rating,0)>=?';
        $p[] = (float)$f['rating'];
    }

    if (isset($f['pmin']) && $f['pmin'] !== '') {
        $w[] = 'c.price_from>=?';
        $p[] = (int)$f['pmin'];
    }

    if (!empty($f['pmax'])) {
        $w[] = 'c.price_from<=?';
        $p[] = (int)$f['pmax'];
    }

    if (isset($f['exp']) && $f['exp'] !== '') {
        $x = (int)$f['exp'];

        $w[] = $x === 0
            ? 'c.experience_years<1'
            : ($x === 1
                ? 'c.experience_years BETWEEN 1 AND 3'
                : 'c.experience_years>3');
    }

    if (!empty($f['lat']) && !empty($f['lng'])) {

        $dist = '(6371*ACOS(LEAST(1,COS(RADIANS(?))*COS(RADIANS(c.lat))*COS(RADIANS(c.lng)-RADIANS(?))+SIN(RADIANS(?))*SIN(RADIANS(c.lat)))))';

        $sp = [
            (float)$f['lat'],
            (float)$f['lng'],
            (float)$f['lat']
        ];

        if (!empty($f['dist'])) {
            $hav = 'HAVING dist<=?';
            $hp = [(float)$f['dist']];
        }
    }

    $order = [
        'rating' => 'avg_rating DESC, reviews_count DESC',
        'price' => 'c.price_from ASC',
        'exp' => 'c.experience_years DESC'
    ][$f['sort'] ?? '']
        ?? '(COALESCE(r.avg_rating,0)*20+LEAST(c.experience_years,10)*3+(c.verified="verified")*10-c.available_days) DESC';

    $sql = "SELECT
                c.*,
                u.name,
                u.avatar AS photo,
                cat.name AS cat_name,
                g.name AS gov_name,
                a.name AS area_name,
                COALESCE(r.avg_rating,0) AS avg_rating,
                COALESCE(r.cnt,0) AS reviews_count,
                $dist AS dist

            FROM craftsmen c

            JOIN users u ON u.id=c.user_id
            JOIN categories cat ON cat.id=c.category_id
            JOIN governorates g ON g.id=c.governorate_id
            LEFT JOIN areas a ON a.id=c.area_id

            LEFT JOIN (
                SELECT craftsman_id, AVG(rating) avg_rating, COUNT(*) cnt
                FROM reviews
                WHERE status='visible'
                GROUP BY craftsman_id
            ) r ON r.craftsman_id=c.id

            WHERE " . implode(' AND ', $w) . "

            $hav

            ORDER BY $order

            LIMIT " . (int)$limit;

    return q($sql, array_merge($sp, $p, $hp))->fetchAll();
}

/* ---------- نظام الترشيح الذكي ---------- */

function recommend(array $pr, int $n = 5): array {

    $rows = search_craftsmen(['cat' => $pr['cat'] ?? 0], 200);

    foreach ($rows as &$r) {

        $s = 0;
        $why = [];

        if (!empty($pr['gov']) && $r['governorate_id'] == $pr['gov']) {
            $s += 25;
            $why[] = 'يعمل في نفس محافظتك (' . $r['gov_name'] . ')';
        }

        if (!empty($pr['area']) && $r['area_id'] == $pr['area']) {
            $s += 15;
            $why[] = 'متواجد في منطقتك (' . $r['area_name'] . ')';
        }

        $rt = (float)$r['avg_rating'];

        if ($rt > 0) {
            $s += $rt / 5 * 25;

            if ($rt >= 4.5) {
                $why[] = 'تقييم ممتاز ' . number_format($rt, 1) . ' من ' . $r['reviews_count'] . ' عميل';
            } elseif ($rt >= 4) {
                $why[] = 'تقييم جيد جداً ' . number_format($rt, 1);
            }
        }

        $ex = (int)$r['experience_years'];
        $s += min($ex, 10) * 1.5;

        if ($ex >= 3) {
            $why[] = 'خبرة ' . $ex . ' سنوات في التخصص';
        }

        $d = (int)$r['available_days'];

        $s += $d <= 0 ? 15 : ($d <= 2 ? 10 : ($d <= 7 ? 5 : 0));

        if ($d <= 0) {
            $why[] = 'متاح اليوم';
        } elseif ($d <= 2) {
            $why[] = 'متاح خلال ' . $d . ' يوم';
        }

        if (!empty($pr['days']) && $d <= (int)$pr['days']) {
            $s += 5;
        }

        if (!empty($pr['budget']) && $r['price_from'] <= (int)$pr['budget']) {
            $s += 5;
            $why[] = 'سعره يناسب ميزانيتك (من ' . $r['price_from'] . ' ج.م)';
        }

        if ($r['verified'] === 'verified') {
            $s += 5;
            $why[] = 'حساب موثّق من الإدارة';
        }

        $r['score'] = (int)min(100, round($s));
        $r['why'] = $why;
    }

    unset($r);

    usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_slice($rows, 0, $n);
}

/* ---------- كارت الحرفي ---------- */

function card(array $c, bool $full = false): string {

    $r = (float)$c['avg_rating'];
    $n = (int)$c['reviews_count'];

    if ($r >= 4.8 && $n >= 3) {
        $badge = '<span class="badge b-gold"><i class="fa-solid fa-crown"></i> الأكثر تقييماً</span>';
    } elseif ($c['verified'] === 'verified') {
        $badge = '<span class="badge b-green">موثوق</span>';
    } elseif ($c['experience_years'] >= 5) {
        $badge = '<span class="badge b-blue">محترف</span>';
    } elseif (strtotime($c['created_at']) > strtotime('-30 days')) {
        $badge = '<span class="badge b-blue">جديد</span>';
    } else {
        $badge = '';
    }

    $avail = $c['available_days'] <= 0
        ? 'متاح اليوم'
        : 'متاح بعد ' . $c['available_days'] . ' يوم';

    $loc = $c['gov_name'] . ($c['area_name'] ? ' - ' . $c['area_name'] : '');

    ob_start();
    ?>

    <article class="card">

      <div class="card-img">

        <a href="<?= url('craftsman.php?id=' . $c['id']) ?>">
          <img src="<?= e(img($c['photo'])) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
        </a>

        <button type="button"
                class="heart<?= in_array($c['id'], favs()) ? ' on' : '' ?>"
                data-fav="<?= $c['id'] ?>"
                aria-label="إضافة للمفضلة">
          <i class="fa-solid fa-heart"></i>
        </button>

        <?= $badge ?>

        <?php if ($c['available_days'] <= 0): ?>
          <i class="dot" title="متاح الآن"></i>
        <?php endif ?>

      </div>

      <div class="card-body">

        <h3>
          <a href="<?= url('craftsman.php?id=' . $c['id']) ?>">
            <?= e($c['name']) ?>
          </a>
        </h3>

        <div class="craft"><?= e($c['cat_name']) ?></div>

        <div class="rate">
          <span class="stars"><?= stars($r) ?></span>
          <b><?= number_format($r, 1) ?></b>
          <small>(<?= $n ?> تقييم)</small>
        </div>

        <div class="loc">
          <i class="fa-solid fa-location-dot"></i>
          <?= e($loc) ?>
        </div>

        <div class="price">
          من <b><?= number_format($c['price_from']) ?></b> ج.م
        </div>

        <?php if ($full): ?>

          <div class="chips">
            <span class="chip ok">
              <i class="fa-regular fa-clock"></i>
              <?= $avail ?>
            </span>

            <span class="chip">
              <i class="fa-solid fa-briefcase"></i>
              <?= (int)$c['experience_years'] ?> سنوات خبرة
            </span>
          </div>

          <div class="acts">
            <button type="button" class="btn ghost btn-compare" data-id="<?= $c['id'] ?>">
              <i class="fa-solid fa-scale-balanced"></i>
              مقارنة
            </button>

            <a class="btn" href="<?= url('messages.php?with=' . $c['user_id']) ?>">
              <i class="fa-regular fa-comment"></i>
              تواصل
            </a>
          </div>

        <?php else: ?>

          <a class="btn block" href="<?= url('messages.php?with=' . $c['user_id']) ?>">
            <i class="fa-regular fa-comment"></i>
            تواصل معه
          </a>

        <?php endif ?>

      </div>
    </article>

    <?php
    return ob_get_clean();
}