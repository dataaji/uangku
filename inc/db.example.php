<?php
// ============================================================
// CONTOH koneksi DB. Di server (Rumahweb): salin jadi db.php lalu isi
// kredensial database hosting (nama DB, user, password dari cPanel).
// ============================================================
$IS_LOCAL = in_array($_SERVER['REMOTE_ADDR'] ?? 'cli', ['127.0.0.1','::1','cli'], true);
error_reporting(E_ALL);
ini_set('display_errors', $IS_LOCAL ? '1' : '0');
ini_set('log_errors', '1');

$DB_HOST = 'localhost';
$DB_NAME = 'namauser_uangku';   // <-- nama database dari cPanel
$DB_USER = 'namauser_dbuser';   // <-- user database dari cPanel
$DB_PASS = 'PASSWORD_DB';       // <-- password database dari cPanel

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database belum siap. ' . ($IS_LOCAL ? htmlspecialchars($e->getMessage()) : 'Coba lagi nanti.');
    exit;
}
