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

function doLogout() {
    $_SESSION = [];
    session_destroy();
}
