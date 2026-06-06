<?php
ob_start(); // kebal "headers already sent" jika ada output/BOM di file include
// Mulai alur login Google — arahkan ke halaman izin Google
require_once __DIR__ . '/inc/auth.php';      // session
require_once __DIR__ . '/inc/google_config.php';

if (empty($GOOGLE['enabled'])) {
    header('Location: login.php?gerr=setup'); exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['g_state'] = $state;

$params = http_build_query([
    'client_id'     => $GOOGLE['client_id'],
    'redirect_uri'  => $GOOGLE['redirect_uri'],
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
