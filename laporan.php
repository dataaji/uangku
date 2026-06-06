<?php
// ============================================================
// Laporan Keuangan (siap cetak / simpan PDF)
// Buka dari menu Analisa → otomatis muncul dialog cetak.
// ============================================================
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';
$me = requireLogin($pdo);

$periode = $_GET['periode'] ?? 'bulan';
$dari = $_GET['dari'] ?? ''; $sampai = $_GET['sampai'] ?? '';
if($dari && $sampai){ $pa=$dari; $pb=$sampai; $plabel=tglIndo($pa,false).' – '.tglIndo($pb,false); }
elseif($periode==='semua'){
    $rg=$pdo->prepare('SELECT MIN(tanggal) a, MAX(tanggal) b FROM transaksi WHERE user_id=?'); $rg->execute([(int)$me['id']]); $r=$rg->fetch();
    $pa=$r['a']?:'2000-01-01'; $pb=$r['b']?:date('Y-m-d'); $plabel='Semua waktu';
}
else { [$pa,$pb,$plabel]=periodeRange($periode); }

$ring   = getRingkasan($pdo,$pa,$pb);
$selisih= $ring['masuk']-$ring['keluar'];
$saldo  = getSaldoTotal($pdo);
$dompet = getDompet($pdo);
$spend  = getSpendKategori($pdo,$pa,$pb); $spendTot=array_sum(array_column($spend,'total'));
$income = getIncomeKategori($pdo,$pa,$pb); $incomeTot=array_sum(array_column($income,'total'));
$bdg    = getAnggaranSemua($pdo);
$tbg    = getTabungan($pdo);
$tgh    = getTagihan($pdo);
$tx     = getTransaksi($pdo,'semua',$pa,$pb);

// Bagian yang ditampilkan (dipilih user). Kosong = semua.
$allSec=['ringkasan','dompet','pengeluaran','pemasukan','anggaran','tabungan','tagihan','transaksi'];
$secParam=trim($_GET['sec']??'');
$sec = $secParam==='' ? $allSec : array_values(array_intersect($allSec, explode(',',$secParam)));
if(!$sec) $sec=$allSec;
function show($k){ global $sec; return in_array($k,$sec); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Keuangan — <?= e($me['nama']) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;color:#1c2330;background:#f0f2f5;padding:24px;font-size:13px;line-height:1.45}
  .sheet{max-width:820px;margin:0 auto;background:#fff;padding:38px 42px;box-shadow:0 4px 24px rgba(0,0,0,.08);border-radius:8px}
  h1{font-size:22px;color:#ef6c2e;margin-bottom:2px}
  h2{font-size:14px;margin:22px 0 8px;padding-bottom:5px;border-bottom:2px solid #ef6c2e;color:#1c2330}
  .muted{color:#6b7280;font-size:12px}
  .row3{display:flex;gap:12px;margin-top:12px;flex-wrap:wrap}
  .box{flex:1;min-width:150px;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px}
  .box .lbl{font-size:11px;color:#6b7280;font-weight:700;text-transform:uppercase}
  .box .val{font-size:20px;font-weight:700;margin-top:3px}
  table{width:100%;border-collapse:collapse;margin-top:6px}
  th,td{text-align:left;padding:7px 9px;border-bottom:1px solid #eef0f3;font-size:12.5px}
  th{background:#f7f8fa;font-size:11px;text-transform:uppercase;color:#6b7280;letter-spacing:.3px}
  td.r,th.r{text-align:right}
  .green{color:#16a06b}.red{color:#e23d4e}.amber{color:#d99a2b}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700}
  .toolbar{max-width:820px;margin:0 auto 16px;display:flex;gap:10px;justify-content:flex-end}
  .btn{padding:10px 18px;border-radius:10px;border:none;font-size:13px;font-weight:700;cursor:pointer}
  .btn-print{background:#ef6c2e;color:#fff}.btn-ghost{background:#fff;border:1px solid #d8dee6;color:#1c2330}
  .foot{margin-top:26px;text-align:center;color:#9aa3b0;font-size:11px}
  @media print{
    body{background:#fff;padding:0;font-size:12px}
    .sheet{box-shadow:none;border-radius:0;max-width:none;padding:0 6mm}
    .toolbar{display:none}
    h2{break-after:avoid}
    table{break-inside:auto}
    tr{break-inside:avoid}
  }
</style>
</head>
<body>
<div class="toolbar">
  <button class="btn btn-ghost" onclick="window.close()">Tutup</button>
  <button class="btn btn-print" onclick="window.print()">⬇️ Simpan / Cetak PDF</button>
</div>

<div class="sheet">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
    <div>
      <h1>Laporan Keuangan</h1>
      <div class="muted">Uangku · <?= e($me['nama']) ?> &lt;<?= e($me['email']) ?>&gt;</div>
    </div>
    <div style="text-align:right" class="muted">
      <div><b style="color:#1c2330">Periode:</b> <?= e($plabel) ?></div>
      <div>Dicetak: <?= tglIndo(date('Y-m-d')) ?></div>
    </div>
  </div>

  <!-- Ringkasan -->
  <?php if(show('ringkasan')): ?>
  <h2>Ringkasan</h2>
  <div class="row3">
    <div class="box"><div class="lbl">Pemasukan</div><div class="val green"><?= rp($ring['masuk']) ?></div></div>
    <div class="box"><div class="lbl">Pengeluaran</div><div class="val red"><?= rp($ring['keluar']) ?></div></div>
    <div class="box"><div class="lbl">Selisih</div><div class="val <?= $selisih>=0?'green':'red' ?>"><?= ($selisih>=0?'+':'−').rp(abs($selisih)) ?></div></div>
    <div class="box"><div class="lbl">Total Saldo</div><div class="val"><?= rp($saldo) ?></div></div>
  </div>
  <div class="muted" style="margin-top:8px"><?= $ring['jml'] ?> transaksi pada periode ini.</div>
  <?php endif; ?>

  <!-- Saldo per dompet -->
  <?php if(show('dompet') && $dompet): ?>
  <h2>Saldo per Dompet</h2>
  <table><thead><tr><th>Dompet</th><th class="r">Saldo</th></tr></thead><tbody>
    <?php foreach($dompet as $w): ?><tr><td><?= e($w['emoji'].' '.$w['nama']) ?></td><td class="r"><?= rp($w['saldo']) ?></td></tr><?php endforeach; ?>
    <tr><td><b>Total</b></td><td class="r"><b><?= rp($saldo) ?></b></td></tr>
  </tbody></table>
  <?php endif; ?>

  <!-- Pengeluaran per kategori -->
  <?php if(show('pengeluaran')): ?>
  <h2>Pengeluaran per Kategori</h2>
  <?php if($spend): ?>
  <table><thead><tr><th>Kategori</th><th class="r">Jumlah</th><th class="r">%</th></tr></thead><tbody>
    <?php foreach($spend as $s): $pct=$spendTot>0?round($s['total']/$spendTot*100):0; ?>
      <tr><td><?= katMeta($pdo,$s['kategori'])['emoji'] ?> <?= e($s['kategori']) ?></td><td class="r red"><?= rp($s['total']) ?></td><td class="r"><?= $pct ?>%</td></tr>
    <?php endforeach; ?>
    <tr><td><b>Total</b></td><td class="r"><b><?= rp($spendTot) ?></b></td><td class="r">100%</td></tr>
  </tbody></table>
  <?php else: ?><div class="muted">Tidak ada pengeluaran pada periode ini.</div><?php endif; ?>
  <?php endif; ?>

  <!-- Pemasukan per kategori -->
  <?php if(show('pemasukan') && $income): ?>
  <h2>Pemasukan per Kategori</h2>
  <table><thead><tr><th>Kategori</th><th class="r">Jumlah</th><th class="r">%</th></tr></thead><tbody>
    <?php foreach($income as $s): $pct=$incomeTot>0?round($s['total']/$incomeTot*100):0; ?>
      <tr><td><?= katMeta($pdo,$s['kategori'])['emoji'] ?> <?= e($s['kategori']) ?></td><td class="r green"><?= rp($s['total']) ?></td><td class="r"><?= $pct ?>%</td></tr>
    <?php endforeach; ?>
    <tr><td><b>Total</b></td><td class="r"><b><?= rp($incomeTot) ?></b></td><td class="r">100%</td></tr>
  </tbody></table>
  <?php endif; ?>

  <!-- Anggaran -->
  <?php if(show('anggaran') && $bdg): ?>
  <h2>Status Anggaran</h2>
  <table><thead><tr><th>Kategori</th><th class="r">Batas</th><th class="r">Terpakai</th><th class="r">Sisa</th><th>Status</th></tr></thead><tbody>
    <?php foreach($bdg as $b): $sisa=$b['batas']-$b['terpakai']; ?>
      <tr><td><?= e($b['kategori']) ?></td><td class="r"><?= rp($b['batas']) ?></td><td class="r"><?= rp($b['terpakai']) ?></td>
        <td class="r <?= $sisa<0?'red':'green' ?>"><?= ($sisa<0?'−':'').rp(abs($sisa)) ?></td>
        <td><?= $b['lewat']?'<span class="red">Terlampaui</span>':(($b['pct']>=80)?'<span class="amber">Hampir habis</span>':'<span class="green">Aman</span>') ?><?= !empty($b['expired'])?' · selesai':'' ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>

  <!-- Tabungan -->
  <?php if(show('tabungan') && $tbg): ?>
  <h2>Tabungan</h2>
  <table><thead><tr><th>Target</th><th class="r">Terkumpul</th><th class="r">Target</th><th class="r">Progres</th></tr></thead><tbody>
    <?php foreach($tbg as $g): $pct=$g['target']>0?round($g['terkumpul']/$g['target']*100):0; ?>
      <tr><td><?= e($g['emoji'].' '.$g['judul']) ?></td><td class="r"><?= rp($g['terkumpul']) ?></td><td class="r"><?= rp($g['target']) ?></td><td class="r"><?= $pct ?>%</td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>

  <!-- Tagihan -->
  <?php if(show('tagihan') && $tgh): ?>
  <h2>Tagihan</h2>
  <table><thead><tr><th>Nama</th><th class="r">Jumlah</th><th>Status</th></tr></thead><tbody>
    <?php foreach($tgh as $b): $st=tagihanStatus($b); $info=tagihanInfo()[$st]; ?>
      <tr><td><?= e($b['emoji'].' '.$b['nama']) ?> <span class="muted">· tgl <?= (int)$b['tgl_jatuh_tempo'] ?></span></td><td class="r"><?= rp($b['jumlah']) ?></td><td><?= $info['label'] ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>

  <!-- Transaksi -->
  <?php if(show('transaksi')): ?>
  <h2>Daftar Transaksi (<?= count($tx) ?>)</h2>
  <?php if($tx): ?>
  <table><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Kategori</th><th>Dompet</th><th class="r">Jumlah</th></tr></thead><tbody>
    <?php foreach($tx as $t): ?>
      <tr><td><?= date('d/m/Y',strtotime($t['tanggal'])) ?></td><td><?= e($t['judul']) ?></td><td><?= e($t['kategori']) ?></td><td><?= e($t['dompet_nama']) ?></td>
        <td class="r <?= $t['jumlah']>0?'green':'red' ?>"><?= ($t['jumlah']>0?'+':'−').rp(abs($t['jumlah'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table>
  <?php else: ?><div class="muted">Tidak ada transaksi pada periode ini.</div><?php endif; ?>
  <?php endif; ?>

  <div class="foot">Dibuat otomatis oleh Uangku · <?= date('d/m/Y H:i') ?></div>
</div>

<script>
// otomatis buka dialog cetak/simpan PDF
window.addEventListener('load',function(){ setTimeout(function(){ window.print(); }, 500); });
</script>
</body>
</html>
