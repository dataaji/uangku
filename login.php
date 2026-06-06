<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/google_config.php';
seedDefaultUser($pdo);

$gerr = $_GET['gerr'] ?? '';
$gerrMsg = ['setup'=>'Login Google belum dikonfigurasi (isi Client ID di inc/google_config.php).','state'=>'Sesi Google tidak valid, coba lagi.','token'=>'Gagal verifikasi ke Google.','email'=>'Email Google tidak terbaca.'][$gerr] ?? '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Hanya login (pendaftaran dinonaktifkan — gunakan Google)
    if (doLogin($pdo, $_POST['email'] ?? '', $_POST['password'] ?? '')) { header('Location: index.php'); exit; }
    $err = 'Email atau password salah.';
}
if (currentUser($pdo)) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Uangku</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=13">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;
    background:linear-gradient(135deg,#1a2230,#2b3447)}
  .auth{width:100%;max-width:400px;background:var(--bg);border-radius:28px;padding:36px 32px;
    box-shadow:0 30px 90px rgba(0,0,0,.4)}
  .auth .logo{font-family:var(--serif);font-size:34px;font-weight:600;color:var(--terra);text-align:center}
  .auth .sub{text-align:center;color:var(--soft);font-size:13px;margin:4px 0 26px}
  .auth .tabs{display:flex;background:var(--card2);border-radius:12px;padding:4px;margin-bottom:22px}
  .auth .tabs a{flex:1;text-align:center;padding:9px 0;border-radius:9px;font-size:13.5px;font-weight:700;color:var(--soft)}
  .auth .tabs a.on{background:var(--ink);color:#fff}
  .auth .err{background:var(--redT);color:var(--red);font-size:13px;font-weight:600;padding:10px 14px;border-radius:11px;margin-bottom:16px;text-align:center}
  .auth .hint{font-size:12px;color:var(--soft);text-align:center;margin-top:18px;line-height:1.6}
  .gbtn{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px;border-radius:13px;
    background:#fff;border:1px solid var(--line);color:#1a2230;font-weight:700;font-size:14.5px;box-shadow:var(--shadow);transition:.15s}
  .gbtn:hover{background:#f7f8fa}
  .orline{display:flex;align-items:center;gap:12px;margin:18px 0;color:var(--muted);font-size:12px}
  .orline::before,.orline::after{content:'';flex:1;height:1px;background:var(--line)}
</style>
</head>
<body>
<div class="auth">
  <div class="logo">Uangku</div>
  <div class="sub">Masuk untuk mengatur keuanganmu</div>
  <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
  <?php if ($gerrMsg): ?><div class="err" style="background:var(--amberT);color:#8a6d1a"><?= e($gerrMsg) ?></div><?php endif; ?>

  <!-- Masuk dengan Google -->
  <a href="google_login.php" class="gbtn">
    <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.6 6.1 29.6 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.5 0 10.5-2.1 14.2-5.6l-6.6-5.5C29.6 34.5 26.9 36 24 36c-5.2 0-9.6-3.3-11.2-8l-6.6 5.1C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.1-4.1 5.5l6.6 5.5C42.5 36 44 30.5 44 24c0-1.3-.1-2.3-.4-3.5z"/></svg>
    Masuk dengan Google
  </a>
  <div class="orline"><span>atau masuk dengan email</span></div>

  <form method="post">
    <div class="field"><label>Email</label><input type="email" name="email" placeholder="email@contoh.com" required></div>
    <div class="field"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800;margin-top:6px">Masuk</button>
  </form>
  <div class="hint">Akun uji coba:<br><b>budi@email.com</b> / <b>uangku123</b></div>
</div>
</body>
</html>
