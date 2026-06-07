<?php
// ============================================================
// Gerbang PIN — kunci aplikasi setelah login (jika PIN aktif)
// ============================================================
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';

$me = currentUser($pdo);
if (!$me) { header('Location: login.php'); exit; }
if (empty($me['pin'])) { $_SESSION['pin_ok'] = 1; header('Location: index.php'); exit; }
if (!empty($_SESSION['pin_ok'])) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');
    if ($pin !== '' && password_verify($pin, $me['pin'])) {
        $_SESSION['pin_ok'] = 1; header('Location: index.php'); exit;
    }
    $err = 'PIN salah, coba lagi.';
}
$dark = !empty($me['dark_mode']);
?>
<!DOCTYPE html>
<html lang="id" class="<?= $dark?'dark':'' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="<?= $dark?'dark':'light' ?>">
<title>Masukkan PIN — Uangku</title>
<link rel="stylesheet" href="assets/style.css?v=17">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;background:linear-gradient(135deg,#1a2230,#2b3447)}
  .pinbox{width:100%;max-width:340px;background:var(--bg);border-radius:26px;padding:34px 28px;box-shadow:0 30px 90px rgba(0,0,0,.4);text-align:center}
  .pinbox .ic{font-size:40px}
  .pinbox h1{font-family:var(--serif);font-size:22px;font-weight:600;margin:8px 0 4px}
  .pinbox p{font-size:13px;color:var(--soft);margin-bottom:20px}
  .pinbox input{width:100%;padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--card);color:var(--ink);font-size:26px;letter-spacing:10px;text-align:center;outline:none;font-family:var(--serif)}
  .pinbox input:focus{border-color:var(--terra)}
  .pinbox .err{background:var(--redT);color:var(--red);font-size:13px;font-weight:600;padding:9px;border-radius:11px;margin-bottom:14px}
  .pinbox .lo{display:block;margin-top:16px;font-size:12px;color:var(--muted)}
</style>
</head>
<body>
<div class="pinbox">
  <div class="ic">🔒</div>
  <h1>Masukkan PIN</h1>
  <p>Aplikasi terkunci. Masukkan PIN untuk lanjut.</p>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <input type="password" name="pin" inputmode="numeric" maxlength="6" placeholder="••••" autofocus required>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800;margin-top:16px">Buka</button>
  </form>
  <a href="logout.php" class="lo">Keluar / pakai akun lain</a>
</div>
</body>
</html>
