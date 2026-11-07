<?php
require __DIR__ . '/includes/functions.php';

$u = require_login();

/* تحويل نوع الحساب إلى وصف عربي */
function role_label($role) {
    return match ($role) {
        'client', 'customer' => 'عميل',
        'craftsman' => 'حرفي',
        'admin' => 'الإدارة',
        default => 'مستخدم'
    };
}

/* حساب الأدمن */
$admin = q(
    'SELECT id, name, avatar, role
     FROM users
     WHERE role = "admin" AND status = "active"
     LIMIT 1'
)->fetch();

$with = (int)($_GET['with'] ?? 0);

/* قائمة المحادثات */
$convs = q(
    'SELECT u.id, u.name, u.avatar, u.role, m.body, m.created_at,
        (
            SELECT COUNT(*)
            FROM messages x
            WHERE x.sender_id = u.id
            AND x.receiver_id = ?
            AND x.is_read = 0
        ) unread
     FROM users u
     JOIN messages m ON m.id = (
        SELECT MAX(id)
        FROM messages
        WHERE
            (sender_id = u.id AND receiver_id = ?)
            OR
            (sender_id = ? AND receiver_id = u.id)
     )
     WHERE u.id <> ?
     ORDER BY m.id DESC',
    [$u['id'], $u['id'], $u['id'], $u['id']]
)->fetchAll();

/* المحادثة المختارة */
$peer = $with
    ? q(
        'SELECT id, name, avatar, role
         FROM users
         WHERE id = ? AND id <> ? AND status = "active"',
        [$with, $u['id']]
      )->fetch()
    : null;

$msgs = [];
$last = 0;

if ($peer) {
    $msgs = q(
        'SELECT *
         FROM messages
         WHERE
            (sender_id = ? AND receiver_id = ?)
            OR
            (sender_id = ? AND receiver_id = ?)
         ORDER BY id',
        [$u['id'], $peer['id'], $peer['id'], $u['id']]
    )->fetchAll();

    q(
        'UPDATE messages
         SET is_read = 1
         WHERE sender_id = ? AND receiver_id = ?',
        [$peer['id'], $u['id']]
    );

    $last = $msgs ? (int)end($msgs)['id'] : 0;
}

$title = 'الرسائل';
$nav = '';

require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div class="chat" id="chat"
       data-me="<?= (int)$u['id'] ?>"
       data-to="<?= $peer ? (int)$peer['id'] : 0 ?>"
       data-last="<?= $last ?>">

    <aside class="convs">
      <h3>
        <i class="fa-regular fa-comments"></i>
        المحادثات
      </h3>

      <?php if ($u['role'] !== 'admin' && $admin): ?>
        <a class="btn"
           style="display:block;text-align:center;margin-bottom:15px"
           href="<?= url('messages.php?with=' . (int)$admin['id']) ?>">
          <i class="fa-solid fa-headset"></i>
          مراسلة الإدارة
        </a>
      <?php endif; ?>

      <?php foreach ($convs as $c): ?>
        <a class="conv<?= $c['id'] == $with ? ' on' : '' ?>"
           href="<?= url('messages.php?with=' . (int)$c['id']) ?>">

          <img src="<?= e(img($c['avatar'])) ?>" alt="">

          <span>
            <b>
              <?= e($c['name']) ?>
              <small style="display:inline-block;color:#0a6cde">
                (<?= e(role_label($c['role'])) ?>)
              </small>
            </b>

            <small>
              <?= e(mb_strimwidth($c['body'], 0, 40, '…')) ?>
            </small>
          </span>

          <?php if ($c['unread']): ?>
            <i class="cnt"><?= (int)$c['unread'] ?></i>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>

      <?php if (!$convs): ?>
        <p class="empty-s">لا توجد محادثات بعد</p>
      <?php endif; ?>
    </aside>

    <section class="thread">
      <?php if ($peer): ?>

        <div class="thread-h">
          <img src="<?= e(img($peer['avatar'])) ?>" alt="">

          <b><?= e($peer['name']) ?></b>

          <small style="color:#0a6cde;margin-right:8px">
            (<?= e(role_label($peer['role'])) ?>)
          </small>
        </div>

        <div class="msgs" id="msgs">
          <?php foreach ($msgs as $m): ?>
            <div class="msg <?= $m['sender_id'] == $u['id'] ? 'mine' : '' ?>">
              <p><?= nl2br(e($m['body'])) ?></p>
              <time><?= date('H:i', strtotime($m['created_at'])) ?></time>
            </div>
          <?php endforeach; ?>
        </div>

        <form class="send" id="sendForm">
          <input id="msgIn"
                 placeholder="اكتب رسالتك..."
                 autocomplete="off">

          <button class="btn">
            <i class="fa-solid fa-paper-plane"></i>
            إرسال
          </button>
        </form>

      <?php else: ?>
        <div class="empty">
          <i class="fa-regular fa-comments"></i>
          <h3>اختر محادثة للبدء</h3>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>