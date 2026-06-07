<?php
// ============================================================
// Autentikasi & Sesi
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true,
        'secure' => $secure, 'samesite' => 'Lax',
    ]);
    session_start();
}
// Token CSRF per sesi
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_token(){ return $_SESSION['csrf'] ?? ''; }

// ── "Tetap login" (remember me) ──────────────────────────────
// Cookie persisten memulihkan LOGIN (uid), TAPI tidak pin_ok →
// jadi PIN tetap diminta tiap aplikasi dibuka ulang.
define('KEEP_COOKIE', 'uangku_keep');
define('KEEP_DAYS', 30);
function keepSecure(){ return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443); }
function rememberCreate($pdo, $uid){
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $exp   = date('Y-m-d H:i:s', time() + KEEP_DAYS*86400);
    $pdo->prepare('INSERT INTO remember_tokens (user_id,token_hash,expires_at) VALUES (?,?,?)')->execute([$uid,$hash,$exp]);
    setcookie(KEEP_COOKIE, $uid.':'.$token, [
        'expires'=>time()+KEEP_DAYS*86400, 'path'=>'/', 'httponly'=>true,
        'secure'=>keepSecure(), 'samesite'=>'Lax',
    ]);
}
function rememberCheck($pdo){
    if (!empty($_SESSION['uid'])) return;
    if (empty($_COOKIE[KEEP_COOKIE])) return;
    $parts = explode(':', $_COOKIE[KEEP_COOKIE], 2);
    if (count($parts) !== 2) return;
    [$uid,$token] = $parts; $hash = hash('sha256', $token);
    try {
        $s = $pdo->prepare('SELECT id FROM remember_tokens WHERE user_id=? AND token_hash=? AND expires_at>NOW() LIMIT 1');
        $s->execute([(int)$uid, $hash]);
        if ($s->fetchColumn()) $_SESSION['uid'] = (int)$uid;   // login dipulihkan; pin_ok TIDAK diset
    } catch (Throwable $e) { /* tabel belum ada / abaikan */ }
}
function rememberClear($pdo){
    if (!empty($_COOKIE[KEEP_COOKIE])) {
        $parts = explode(':', $_COOKIE[KEEP_COOKIE], 2);
        if (count($parts) === 2) {
            try { $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash=?')->execute([hash('sha256',$parts[1])]); } catch (Throwable $e) {}
        }
        setcookie(KEEP_COOKIE, '', ['expires'=>time()-3600, 'path'=>'/', 'samesite'=>'Lax']);
    }
}

// Beri kategori bawaan untuk user baru (idempoten)
function seedUserDefaults($pdo, $uid) {
    $c = $pdo->prepare('SELECT COUNT(*) FROM kategori WHERE user_id=?'); $c->execute([$uid]);
    if ((int)$c->fetchColumn() > 0) return;
    $def = [
        ['Makanan','🍜','#f7ecd5','#c8602c','keluar'],['Belanja','🛒','#e3ecf6','#3b6fb0','keluar'],
        ['Transport','🚗','#f6e4e1','#c0392b','keluar'],['Tagihan','💡','#f7ecd5','#d99a2b','keluar'],
        ['Hiburan','🎬','#ede4f4','#8a5fb0','keluar'],['Kesehatan','💊','#e4f0ea','#2f7d5d','keluar'],
        ['Hadiah','🎁','#f7e6da','#c8602c','keluar'],['Pemasukan','💼','#e4f0ea','#2f7d5d','masuk'],
        ['Lainnya','✏️','#fbf6ec','#5c5345','keluar'],
    ];
    $ins=$pdo->prepare('INSERT INTO kategori (user_id,nama,emoji,tint,warna,tipe) VALUES (?,?,?,?,?,?)');
    foreach ($def as $d) $ins->execute([$uid,$d[0],$d[1],$d[2],$d[3],$d[4]]);
}

// Seed user demo jika belum ada user sama sekali
function seedDefaultUser($pdo) {
    $n = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($n === 0) {
        $hash = password_hash('uangku123', PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (nama,email,password,avatar) VALUES (?,?,?,?)')
            ->execute(['Budi Santoso', 'budi@email.com', $hash, '🧑']);
        seedUserDefaults($pdo, (int)$pdo->lastInsertId());
    }
}

// User yang sedang login (atau null)
function currentUser($pdo) {
    if (empty($_SESSION['uid'])) rememberCheck($pdo);   // coba pulihkan dari cookie "tetap login"
    if (empty($_SESSION['uid'])) return null;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['uid']]);
    return $stmt->fetch() ?: null;
}

// Wajib login — kalau belum, lempar ke login.php
function requireLogin($pdo) {
    $u = currentUser($pdo);
    if (!$u) { header('Location: login.php'); exit; }
    if (!empty($u['pin']) && empty($_SESSION['pin_ok'])) { header('Location: pin.php'); exit; }
    return $u;
}

function doLogin($pdo, $email, $pass) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([trim($email)]);
    $u = $stmt->fetch();
    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);   // cegah session fixation
        $_SESSION['uid'] = $u['id'];
        return true;
    }
    return false;
}

function doLogout($pdo = null) {
    if ($pdo) rememberClear($pdo);
    $_SESSION = [];
    session_destroy();
}
