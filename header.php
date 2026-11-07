<?php
require_once __DIR__ . '/functions.php';
$u = user();
$title = $title ?? SITE_NAME;
$nav = $nav ?? '';
$flash = flash();
if ($u) {
    $nc = (int)q('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$u['id']])->fetchColumn();
    $notes = q('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 6', [$u['id']])->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<?php if (!empty($leaflet)): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><?php endif ?>
<link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<header class="nav"><div class="nav-in">
  <a class="logo" href="<?= url('index.php') ?>">
    <svg viewBox="0 0 48 48" width="46" height="46" fill="none" stroke="#0a6cde" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22 24 6l19 16"/><path d="M10 20v22h28V20"/><path d="M24 37s-7-5.5-7-11a7 7 0 0114 0c0 5.5-7 11-7 11z" fill="#0a6cde" stroke="none"/><circle cx="24" cy="26" r="2.6" fill="#fff" stroke="none"/></svg>
    <span><b><?= SITE_NAME ?></b><small><?= SITE_TAGLINE ?></small></span>
  </a>
  <nav class="links" id="links">
    <a href="<?= url('index.php') ?>" class="<?= $nav === 'home' ? 'on' : '' ?>"><i class="fa-solid fa-house"></i> الرئيسية</a>
    <a href="<?= url('crafts.php') ?>" class="<?= $nav === 'crafts' ? 'on' : '' ?>">كل الحرفيين</a>
    <a href="<?= url('about.php') ?>" class="<?= $nav === 'about' ? 'on' : '' ?>">عن المنصة</a>
    <a href="<?= url('contact.php') ?>" class="<?= $nav === 'contact' ? 'on' : '' ?>">تواصل معنا</a>
  </nav>
  <?php if (!empty($navSearch)): ?>
  <form class="nav-search" action="<?= url('crafts.php') ?>" method="get">
    <i class="fa-solid fa-magnifying-glass"></i>
    <input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="ابحث عن حرفة، منطقة، أو اسم حرفي...">
  </form>
  <?php endif ?>
  <div class="nav-acts">
  <?php if ($u): ?>
    <div class="dd-wrap">
      <button class="icon-btn" data-dd="ddUser"><img class="mini-av" src="<?= e(img($u['avatar'], 'assets/img/workers/default.jpg')) ?>" alt=""><i class="fa-solid fa-chevron-down"></i></button>
      <div class="dd" id="ddUser" hidden>
        <div class="dd-h"><?= e($u['name']) ?></div>
        <?php if ($u['role'] === 'admin'): ?><a href="<?= url('admin/index.php') ?>"><i class="fa-solid fa-gauge"></i> لوحة الإدارة</a>
        <?php else: ?><a href="<?= url('dashboard.php') ?>"><i class="fa-solid fa-chart-line"></i> لوحة التحكم</a><?php endif ?>
        <a href="<?= url('messages.php') ?>"><i class="fa-regular fa-comments"></i> رسائلي</a>
        <?php if ($u['role'] === 'client'): ?><a href="<?= url('request.php') ?>"><i class="fa-solid fa-file-pen"></i> اطلب خدمة</a><?php endif ?>
        <a href="<?= url('recommend.php') ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> ترشيح ذكي</a>
        <a href="<?= url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket"></i> تسجيل الخروج</a>
      </div>
    </div>
    <a class="icon-btn" href="<?= url('dashboard.php#fav') ?>" aria-label="المفضلة"><i class="fa-regular fa-heart"></i></a>
    <div class="dd-wrap">
      <button class="icon-btn" data-dd="ddNotes" aria-label="الإشعارات"><i class="fa-regular fa-bell"></i><?php if ($nc): ?><span class="bell-n"></span><?php endif ?></button>
      <div class="dd notes" id="ddNotes" hidden>
        <div class="dd-h">الإشعارات</div>
        <?php foreach ($notes as $n): ?><a href="<?= url($n['link'] ?: 'dashboard.php') ?>" class="<?= $n['is_read'] ? '' : 'unread' ?>"><?= e($n['text']) ?></a>
        <?php endforeach; if (!$notes): ?><p class="empty-s">لا توجد إشعارات</p><?php endif ?>
      </div>
    </div>
  <?php else: ?>
    <a class="btn" href="<?= url('register.php') ?>">إنشاء حساب</a>
    <a class="login-link" href="<?= url('login.php') ?>"><i class="fa-solid fa-circle-user"></i> تسجيل الدخول</a>
    <span class="icon-btn"><i class="fa-regular fa-bell"></i></span>
  <?php endif ?>
  </div>
  <button class="burger" id="burger" aria-label="القائمة"><i class="fa-solid fa-bars"></i></button>
</div></header>
<?php if ($flash): ?><div class="toast <?= $flash[1] === 'err' ? 'err' : '' ?>"><?= e($flash[0]) ?></div><?php endif ?>