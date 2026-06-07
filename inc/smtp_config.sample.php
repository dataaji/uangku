<?php
// ============================================================
// CONTOH konfigurasi SMTP untuk kirim email (reset PIN).
// CARA PAKAI di hosting:
//   1. Buat email di cPanel, mis. noreply@ledgerid.site (catat passwordnya)
//   2. Salin file ini jadi  inc/smtp_config.php  (tanpa .sample)
//   3. Isi host/user/pass sesuai cPanel, set 'enabled' => true
// File asli (smtp_config.php) TIDAK diunggah ke Git (rahasia).
// ============================================================
$SMTP = [
    'enabled'    => false,                     // ubah jadi true setelah diisi
    'host'       => 'mail.ledgerid.site',      // host SMTP cPanel
    'port'       => 465,                        // 465 = SSL, 587 = STARTTLS
    'secure'     => 'ssl',                      // 'ssl' (465) atau 'tls' (587)
    'user'       => 'noreply@ledgerid.site',    // alamat email penuh
    'pass'       => 'GANTI_PASSWORD_EMAIL',     // password email cPanel
    'from_email' => 'noreply@ledgerid.site',
    'from_name'  => 'Uangku',
];
