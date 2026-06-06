<?php
// Callback dari Google — tukar code jadi token, ambil email, login/daftar
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/google_config.php';

if (empty($GOOGLE['enabled'])) { header('Location: login.php?gerr=setup'); exit; }

// Validasi state (anti-CSRF)
$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';
if (!$code || !$state || ($state !== ($_SESSION['g_state'] ?? ''))) {
    header('Location: login.php?gerr=state'); exit;
}
unset($_SESSION['g_state']);

// Helper POST/GET via cURL
function gHttp($url, $post = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch); curl_close($ch);
    return json_decode($res, true);
}

// 1. Tukar code → access_token
$tok = gHttp('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $GOOGLE['client_id'],
    'client_secret' => $GOOGLE['client_secret'],
    'redirect_uri'  => $GOOGLE['redirect_uri'],
    'grant_type'    => 'authorization_code',
]);
if (empty($tok['access_token'])) { header('Location: login.php?gerr=token'); exit; }

// 2. Ambil profil (email, nama)
$info = gHttp('https://www.googleapis.com/oauth2/v2/userinfo', null, ['Authorization: Bearer ' . $tok['access_token']]);
$email = $info['email'] ?? '';
$nama  = $info['name']  ?? 'Pengguna Google';
$gid   = $info['id']    ?? '';
if (!$email) { header('Location: login.php?gerr=email'); exit; }

// 3. Cari user; kalau belum ada → daftar otomatis + kategori default
$s = $pdo->prepare('SELECT * FROM users WHERE email=? OR (google_id IS NOT NULL AND google_id=?)');
$s->execute([$email, $gid]);
$u = $s->fetch();
if (!$u) {
    $pdo->prepare('INSERT INTO users (nama,email,password,avatar,google_id) VALUES (?,?,?,?,?)')
        ->execute([$nama, $email, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), '🧑', $gid]);
    $uid = (int)$pdo->lastInsertId();
    seedUserDefaults($pdo, $uid);
} else {
    $uid = (int)$u['id'];
    if (empty($u['google_id']) && $gid) $pdo->prepare('UPDATE users SET google_id=? WHERE id=?')->execute([$gid, $uid]);
}

session_regenerate_id(true);   // cegah session fixation
$_SESSION['uid'] = $uid;
header('Location: index.php');
exit;
