<?php
require_once __DIR__ . '/inc/auth.php';
doLogout();
header('Location: login.php');
exit;
