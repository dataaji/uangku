<?php
// ============================================================
// CONTOH konfigurasi Google. Di server: salin jadi google_config.php lalu isi.
// ============================================================
$GOOGLE = [
    'client_id'     => '',   // <-- Client ID dari Google
    'client_secret' => '',   // <-- Client Secret dari Google
    'redirect_uri'  => 'https://domainmu.com/uangku/google_callback.php', // pakai HTTPS di hosting
];
$GOOGLE['enabled'] = ($GOOGLE['client_id'] !== '' && $GOOGLE['client_secret'] !== '');
