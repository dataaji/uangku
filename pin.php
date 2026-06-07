<?php
// ============================================================
// Gerbang PIN — kunci aplikasi setelah login (jika PIN aktif)
// + Lupa PIN: kirim kode ke email terdaftar
// ============================================================
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';

$me = currentUser($pdo);
if (!$me) { header('Location: login.php'); exit; }
if (empty($me['pin'])) { $_SESSION['pin_ok'] = 1; header('Location: index.php'); exit; }
if (!empty($_SESSION['pin_ok'])) { header('Location: index.php'); exit; }

$err = ''; $info = ''; $step = 'pin';   // pin | kode

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'verify';

    if ($mode === 'verify') {
        $pin = preg_replace('/\D/', '', $_POST['pin'] ?? '');
        if ($pin !== '' && password_verify($pin, $me['pin'])) {
            $_SESSION['pin_ok'] = 1; header('Location: index.php'); exit;
        }
        $err = 'PIN salah, coba lagi.';

    } elseif ($mode === 'req') {            // minta kode reset ke email
        $step = 'kode';
        if (!empty($_SESSION['pin_reset']['sent']) && time() - $_SESSION['pin_reset']['sent'] < 60) {
            $err = 'Tunggu sebentar sebelum minta kode lagi.';
        } else {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['pin_reset'] = ['h' => password_hash($code, PASSWORD_DEFAULT), 'exp' => time() + 900, 'sent' => time()];
            $html = '<div style="font-family:sans-serif;font-size:15px;color:#222">'
                  . '<p>Halo ' . e($me['nama']) . ',</p>'
                  . '<p>Kode untuk mereset <b>PIN Uangku</b> kamu:</p>'
                  . '<p style="font-size:30px;font-weight:bold;letter-spacing:6px;color:#ef6c2e">' . $code . '</p>'
                  . '<p>Berlaku 15 menit. Abaikan email ini jika kamu tidak meminta.</p></div>';
            if (sendMail($me['email'], 'Kode Reset PIN Uangku', $html)) {
                $info = 'Kode dikirim ke ' . maskEmail($me['email']) . '. Cek email (termasuk folder Spam).';
            } else {
                $err = 'Gagal mengirim email. Coba lagi nanti.';
            }
        }

    } elseif ($mode === 'reset') {          // verifikasi kode + set PIN baru
        $step = 'kode';
        $code = preg_replace('/\D/', '', $_POST['kode'] ?? '');
        $new  = preg_replace('/\D/', '', $_POST['pin_baru'] ?? '');
        $r = $_SESSION['pin_reset'] ?? null;
        if (!$r || time() > $r['exp']) { $err = 'Kode kedaluwarsa. Minta kode baru.'; $step = 'pin'; }
        elseif (!password_verify($code, $r['h'])) { $err = 'Kode salah.'; }
        elseif (strlen($new) !== 6) { $err = 'PIN baru harus tepat 6 angka.'; }
        else {
            $pdo->prepare('UPDATE users SET pin=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            unset($_SESSION['pin_reset']);
            $_SESSION['pin_ok'] = 1; header('Location: index.php'); exit;
        }
    }
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
<link rel="stylesheet" href="assets/style.css?v=23">
<style>
  body{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;background:linear-gradient(135deg,#1a2230,#2b3447)}
  .pinbox{width:100%;max-width:340px;background:var(--bg);border-radius:26px;padding:34px 28px;box-shadow:0 30px 90px rgba(0,0,0,.4);text-align:center}
  .pinbox .ic{font-size:40px}
  .pinbox h1{font-family:var(--serif);font-size:22px;font-weight:600;margin:8px 0 4px}
  .pinbox p{font-size:13px;color:var(--soft);margin-bottom:20px}
  .pinbox input{width:100%;padding:14px;border:1px solid var(--line);border-radius:14px;background:var(--card);color:var(--ink);font-size:26px;letter-spacing:10px;text-align:center;outline:none;font-family:var(--serif)}
  .pinbox input:focus{border-color:var(--terra)}
  .pinbox input.kode{font-size:22px;letter-spacing:8px;margin-bottom:10px}
  .pinbox .err{background:var(--redT);color:var(--red);font-size:13px;font-weight:600;padding:9px;border-radius:11px;margin-bottom:14px}
  .pinbox .ok{background:var(--greenT);color:var(--green);font-size:13px;font-weight:600;padding:9px;border-radius:11px;margin-bottom:14px}
  .pinbox .lo{display:block;margin-top:16px;font-size:12px;color:var(--muted)}
  .pinbox .lupa{display:inline-block;margin-top:14px;font-size:12.5px;color:var(--terra);font-weight:700;background:none;border:none;cursor:pointer}
</style>
</head>
<body>
<div class="pinbox">
  <?php if ($step === 'kode'): ?>
    <div class="ic">📧</div>
    <h1>Reset PIN</h1>
    <p>Masukkan kode yang dikirim ke email, lalu buat PIN baru.</p>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="ok"><?= e($info) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="mode" value="reset">
      <input class="kode" type="text" name="kode" inputmode="numeric" maxlength="6" placeholder="Kode 6 digit" autofocus required>
      <input type="password" name="pin_baru" inputmode="numeric" maxlength="6" minlength="6" pattern="\d{6}" placeholder="PIN baru (6 angka)" required>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800;margin-top:16px">Simpan PIN Baru</button>
    </form>
    <form method="post" style="margin-top:6px"><input type="hidden" name="mode" value="req"><button class="lupa" type="submit">Kirim ulang kode</button></form>
    <a href="pin.php" class="lo">← Kembali masukkan PIN</a>
  <?php else: ?>
    <div class="ic">🔒</div>
    <h1>Masukkan PIN</h1>
    <p>Aplikasi terkunci. Masukkan PIN untuk lanjut.</p>
    <?php if ($err): ?><div class="err"><?= e($err) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="ok"><?= e($info) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="mode" value="verify">
      <input type="password" name="pin" inputmode="numeric" maxlength="6" placeholder="••••••" autofocus required>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800;margin-top:16px">Buka</button>
    </form>
    <form method="post"><input type="hidden" name="mode" value="req"><button class="lupa" type="submit">Lupa PIN? Kirim kode ke email</button></form>
    <a href="logout.php" class="lo">Keluar / pakai akun lain</a>
  <?php endif; ?>
</div>
</body>
</html>
