<?php
require __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
$a = $_REQUEST['action'] ?? ''; $u = user();
function out($d, int $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? 'x')) out(['error' => 'csrf'], 419);

switch ($a) {
    case 'areas':
        out(q('SELECT id,name FROM areas WHERE governorate_id=? ORDER BY name', [(int)($_GET['gov'] ?? 0)])->fetchAll());
    case 'services':
        out(q('SELECT id,name FROM services WHERE category_id=? ORDER BY name', [(int)($_GET['cat'] ?? 0)])->fetchAll());
    case 'svc_view':
        $id = (int)($_POST['id'] ?? 0);
        if (empty($_SESSION['sv' . $id])) { $_SESSION['sv' . $id] = 1; q('UPDATE craftsman_services SET views=views+1 WHERE id=?', [$id]); }
        out(['ok' => 1]);
    case 'read_notes':
        if ($u) q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$u['id']]);
        out(['ok' => 1]);
    case 'fav':
        if (!$u) out(['login' => 1]);
        $id = (int)($_POST['id'] ?? 0);
        $ex = q('SELECT 1 FROM favorites WHERE user_id=? AND craftsman_id=?', [$u['id'], $id])->fetch();
        if ($ex) q('DELETE FROM favorites WHERE user_id=? AND craftsman_id=?', [$u['id'], $id]);
        else q('INSERT IGNORE INTO favorites(user_id,craftsman_id) VALUES(?,?)', [$u['id'], $id]);
        out(['on' => !$ex]);
    case 'send':
        if (!$u) out(['login' => 1], 401);
        $to = (int)($_POST['to'] ?? 0); $b = trim($_POST['body'] ?? '');
        if ($b === '' || mb_strlen($b) > 2000 || $to === (int)$u['id'] || !q('SELECT 1 FROM users WHERE id=?', [$to])->fetch()) out(['error' => 1], 422);
        $pend = q('SELECT COUNT(*) FROM messages WHERE sender_id=? AND receiver_id=? AND is_read=0', [$u['id'], $to])->fetchColumn();
        q('INSERT INTO messages(sender_id,receiver_id,body) VALUES(?,?,?)', [$u['id'], $to, $b]);
        $id = (int)db()->lastInsertId();
        if (!$pend) notify($to, 'رسالة جديدة من ' . $u['name'], 'messages.php?with=' . $u['id']);
        out(['msg' => ['id' => $id, 'body' => $b, 'mine' => true, 'time' => date('H:i')]]);
    case 'msgs':
        if (!$u) out([], 401);
        $w = (int)($_GET['with'] ?? 0); $after = (int)($_GET['after'] ?? 0);
        $r = q('SELECT id,sender_id,body,created_at FROM messages WHERE id>? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) ORDER BY id', [$after, $u['id'], $w, $w, $u['id']])->fetchAll();
        q('UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?', [$w, $u['id']]);
        out(array_map(fn($m) => ['id' => (int)$m['id'], 'body' => $m['body'], 'mine' => $m['sender_id'] == $u['id'], 'time' => date('H:i', strtotime($m['created_at']))], $r));
    default:
        out(['error' => 'unknown'], 400);
}