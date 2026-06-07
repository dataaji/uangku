<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
doLogout($pdo);   // hapus token "tetap login" + sesi
header('Location: login.php');
exit;
