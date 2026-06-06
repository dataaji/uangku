<?php
// ============================================================
// Backup data milik user (unduh JSON)
// ============================================================
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';
$me = requireLogin($pdo);
$U  = (int)$me['id'];

$tables = ['dompet','kategori','transaksi','anggaran','anggaran_log','tabungan','tagihan','tugas','catatan'];
$data = [
    '_app' => 'uangku', '_ver' => 9,
    '_user' => ['nama'=>$me['nama'],'email'=>$me['email']],
    'exported_at' => date('c'),
];
foreach ($tables as $t) {
    $s = $pdo->prepare("SELECT * FROM `$t` WHERE user_id=?");
    $s->execute([$U]);
    $data[$t] = $s->fetchAll();
}

$fname = 'uangku-backup-'.date('Ymd-His').'.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
