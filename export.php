<?php
// ============================================================
// Export transaksi ke CSV (dibuka di Excel/Spreadsheet)
// ============================================================
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';
$me = requireLogin($pdo);
$GLOBALS['UANGKU_CUR'] = $me['currency'] ?? 'Rp';

$periode = $_GET['periode'] ?? 'bulan';
$dari = $_GET['dari'] ?? ''; $sampai = $_GET['sampai'] ?? '';
if ($dari && $sampai) { $pa=$dari; $pb=$sampai; }
elseif ($periode === 'semua') {
    $rg=$pdo->prepare('SELECT MIN(tanggal) a, MAX(tanggal) b FROM transaksi WHERE user_id=?'); $rg->execute([(int)$me['id']]); $r=$rg->fetch();
    $pa=$r['a']?:'2000-01-01'; $pb=$r['b']?:date('Y-m-d');
} else { [$pa,$pb] = periodeRange($periode); }

$tx = getTransaksi($pdo,'semua',$pa,$pb);

$fname = 'uangku-transaksi-'.$pa.'_'.$pb.'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$fname.'"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8 (emoji/rupiah benar)
fputcsv($out, ['Tanggal','Keterangan','Kategori','Dompet','Tipe','Jumlah (Rp)']);
$masuk=0; $keluar=0;
foreach ($tx as $t) {
    $j=(float)$t['jumlah'];
    if ($j>0) $masuk+=$j; else $keluar+=-$j;
    fputcsv($out, [
        date('d/m/Y', strtotime($t['tanggal'])),
        $t['judul'],
        $t['kategori'],
        $t['dompet_nama'],
        $j>0 ? 'Pemasukan' : 'Pengeluaran',
        round($j),
    ]);
}
fputcsv($out, []);
fputcsv($out, ['','','','','Total Pemasukan', round($masuk)]);
fputcsv($out, ['','','','','Total Pengeluaran', round($keluar)]);
fputcsv($out, ['','','','','Selisih', round($masuk-$keluar)]);
fclose($out);
exit;
