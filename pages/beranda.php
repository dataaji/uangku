<?php
$periode = $_GET['periode'] ?? 'bulan';
[$pa,$pb,$plabel] = periodeRange($periode);
$dompet=getDompet($pdo); $saldoTotal=getSaldoTotal($pdo);
$ring=getRingkasan($pdo,$pa,$pb);
$spend=getSpendKategori($pdo,$pa,$pb);
$spendTotal=array_sum(array_column($spend,'total'));
$recent=array_slice(getTransaksi($pdo,'semua'),0,6);
// grafik garis tren MULTI (per kategori + pemasukan + pengeluaran) — sama seperti Analisa
$tmode = $periode==='tahun'?'tahun':'bulan';
$TK=getTrendKategori($pdo,$tmode); $tlabels=$TK['labels']; $tseries=$TK['series'];

// donut SVG arcs — pengeluaran
$C=2*M_PI*70; $seg=[]; $acc=0;
foreach($spend as $s){ $frac=$spendTotal>0?$s['total']/$spendTotal:0; $len=$frac*$C;
  $seg[]=['kat'=>$s['kategori'],'warna'=>katMeta($pdo,$s['kategori'])['warna'],'emoji'=>katMeta($pdo,$s['kategori'])['emoji'],'len'=>$len,'off'=>$acc,'total'=>$s['total'],'pct'=>round($frac*100)];
  $acc+=$len; }
// donut SVG arcs — pemasukan
$spendIn=getIncomeKategori($pdo,$pa,$pb); $incTotal=array_sum(array_column($spendIn,'total'));
$segIn=[]; $acc=0;
foreach($spendIn as $s){ $frac=$incTotal>0?$s['total']/$incTotal:0; $len=$frac*$C;
  $segIn[]=['kat'=>$s['kategori'],'warna'=>katMeta($pdo,$s['kategori'])['warna'],'emoji'=>katMeta($pdo,$s['kategori'])['emoji'],'len'=>$len,'off'=>$acc,'total'=>$s['total'],'pct'=>round($frac*100)];
  $acc+=$len; }

$jam=(int)date('H'); $salam=$jam<11?'Selamat pagi':($jam<15?'Selamat siang':($jam<19?'Selamat sore':'Selamat malam'));
topbar("$salam, ".explode(' ',$me['nama'])[0].' 👋', tglIndo(date('Y-m-d')), $notifs, 'beranda');
?>

<!-- Saldo -->
<div class="balance" style="margin-bottom:18px">
  <div class="glow"></div><div class="glow2"></div>
  <div style="display:flex;justify-content:space-between;align-items:center;position:relative"><span class="lbl">TOTAL SALDO</span><span style="font-size:18px">👛</span></div>
  <div class="amt cup" data-v="<?= (int)round($saldoTotal) ?>"><?= rp($saldoTotal) ?></div>
  <div class="wallets">
    <?php if(!$dompet): ?><div style="font-size:13px;color:#aab8cc">Belum ada dompet — tambah di menu Anggaran/Pengaturan.</div><?php endif; ?>
    <?php foreach($dompet as $w): ?><div class="wallet"><div class="wn"><span style="font-size:14px"><?= $w['emoji'] ?></span><?= e($w['nama']) ?></div><div class="ws"><?= rpShort($w['saldo']) ?></div></div><?php endforeach; ?>
  </div>
</div>

<!-- Periode -->
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
  <div class="seg-tabs">
    <?php foreach(['hari'=>'Hari Ini','minggu'=>'Minggu','bulan'=>'Bulan','tahun'=>'Tahun'] as $k=>$lbl): ?>
      <a href="?page=beranda&periode=<?= $k ?>" class="<?= $periode===$k?'on':'' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
  <span style="font-size:12.5px;color:var(--soft);font-weight:600"><?= $plabel ?> · <?= $ring['jml'] ?> transaksi</span>
</div>

<!-- Masuk/Keluar/Selisih -->
<div class="grid-3" style="margin-bottom:18px">
  <div class="card" style="padding:18px 20px"><div style="display:flex;align-items:center;gap:10px;margin-bottom:10px"><div class="cat" style="width:34px;height:34px;background:var(--greenT)"><?= icon('arrowDn',18,'var(--green)',2.4) ?></div><span style="font-size:13px;font-weight:700;color:var(--soft)">Pemasukan</span></div><div style="font-family:var(--serif);font-weight:600;font-size:24px;color:var(--green)"><?= rpShort($ring['masuk']) ?></div></div>
  <div class="card" style="padding:18px 20px"><div style="display:flex;align-items:center;gap:10px;margin-bottom:10px"><div class="cat" style="width:34px;height:34px;background:var(--redT)"><?= icon('arrowUp',18,'var(--red)',2.4) ?></div><span style="font-size:13px;font-weight:700;color:var(--soft)">Pengeluaran</span></div><div style="font-family:var(--serif);font-weight:600;font-size:24px;color:var(--red)"><?= rpShort($ring['keluar']) ?></div></div>
  <?php $sel=$ring['masuk']-$ring['keluar']; ?>
  <div class="card" style="padding:18px 20px"><div style="display:flex;align-items:center;gap:10px;margin-bottom:10px"><div class="cat" style="width:34px;height:34px;background:var(--blueT)">💼</div><span style="font-size:13px;font-weight:700;color:var(--soft)">Selisih</span></div><div style="font-family:var(--serif);font-weight:600;font-size:24px;color:<?= $sel>=0?'var(--green)':'var(--red)' ?>"><?= ($sel>=0?'+':'−').rpShort(abs($sel)) ?></div></div>
</div>

<!-- Donut interaktif + Tren interaktif -->
<div class="grid-fit" style="margin-bottom:18px">
  <div class="card" style="padding:20px 22px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">
      <div style="font-family:var(--serif);font-size:17px;font-weight:600"><span id="donut-judul">Pengeluaran</span> · <?= $plabel ?></div>
      <div class="seg-tabs">
        <a href="#" id="dm-keluar" class="on" onclick="donutMode('keluar');return false">Pengeluaran</a>
        <a href="#" id="dm-masuk" onclick="donutMode('masuk');return false">Pemasukan</a>
      </div>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:14px">Ketuk bagian donat untuk melihat nominalnya · pilih Pengeluaran / Pemasukan di atas</div>
    <?php
    $renderDonut=function($seg,$total,$mode,$showGoto) use($C){
      if(!$seg){ echo '<div class="empty" style="padding:24px 0"><div class="msg" style="font-size:13px">Belum ada data 🎉</div></div>'; return; }
      ?>
      <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap">
        <svg viewBox="0 0 180 180" width="160" height="160" style="flex-shrink:0;transform:rotate(-90deg)">
          <?php foreach($seg as $i=>$g): ?><circle cx="90" cy="90" r="70" fill="none" stroke="<?= $g['warna'] ?>" stroke-width="26" stroke-dasharray="<?= $g['len'] ?> <?= $C-$g['len'] ?>" stroke-dashoffset="<?= -$g['off'] ?>" class="donut-seg-<?= $mode ?>" data-i="<?= $i ?>" style="cursor:pointer;transition:stroke-width .15s" onclick="pickSeg('<?= $mode ?>',<?= $i ?>)"></circle><?php endforeach; ?>
        </svg>
        <div style="flex:1;min-width:150px">
          <div id="donut-center-<?= $mode ?>" style="margin-bottom:8px"><div style="font-size:11px;color:var(--muted);font-weight:700">TOTAL</div><div style="font-family:var(--serif);font-size:22px;font-weight:600"><?= rpShort($total) ?></div></div>
          <button type="button" onclick="resetDonut('<?= $mode ?>')" class="btn btn-ghost btn-sm" style="margin-bottom:10px;padding:5px 10px;font-size:11px">↺ Tampilkan semua</button>
          <div style="display:flex;flex-direction:column;gap:7px">
            <?php foreach($seg as $i=>$g): ?><div class="leg-item-<?= $mode ?>" data-i="<?= $i ?>" onclick="pickSeg('<?= $mode ?>',<?= $i ?>)" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 6px;border-radius:8px"><span style="width:10px;height:10px;border-radius:3px;background:<?= $g['warna'] ?>"></span><span style="font-size:13px;font-weight:600;flex:1"><?= $g['emoji'] ?> <?= e($g['kat']) ?></span><span style="font-size:12px;font-weight:700;color:var(--soft)"><?= $g['pct'] ?>%</span><?php if($showGoto): ?><span onclick="event.stopPropagation();gotoKat('<?= e($g['kat']) ?>')" title="Lihat di Analisa" style="color:var(--terra);font-weight:800"><?= icon('chevR',14,'var(--terra)') ?></span><?php endif; ?></div><?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php
    };
    ?>
    <div class="donut-view" data-mode="keluar"><?php $renderDonut($seg,$spendTotal,'keluar',true); ?></div>
    <div class="donut-view" data-mode="masuk" style="display:none"><?php $renderDonut($segIn,$incTotal,'masuk',false); ?></div>
  </div>

  <div class="card" style="padding:20px 22px">
    <div style="font-family:var(--serif);font-size:17px;font-weight:600;margin-bottom:4px">Tren Arus Kas — Semua Kategori</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:6px">Ketuk titik/batang untuk menampilkan nilai · ketuk nama di bawah untuk menyembunyikan garis</div>
    <?php renderTrendBody($tlabels,$tseries,200); ?>
  </div>
</div>

<!-- Transaksi terbaru (masuk & keluar) -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
  <div class="eyebrow" style="margin:0">Transaksi Terbaru</div>
  <a href="?page=transaksi" style="font-size:12.5px;font-weight:700;color:var(--terra)">Lihat semua →</a>
</div>
<?php if(!$recent): ?>
  <div class="card" style="padding:24px;text-align:center;color:var(--soft);font-size:13.5px">Belum ada transaksi. Ketuk <b>+ Tambah</b> untuk mencatat.</div>
<?php else: ?>
<div class="card">
  <?php foreach($recent as $t): ?>
    <div class="row" style="border-left:4px solid <?= $t['jumlah']>0?'var(--green)':(katMeta($pdo,$t['kategori'])['warna']) ?>;padding-left:12px;border-radius:8px">
      <div class="cat" style="width:42px;height:42px;font-size:20px;background:<?= $t['tint']?:katMeta($pdo,$t['kategori'])['tint'] ?>"><?= $t['emoji']?:katMeta($pdo,$t['kategori'])['emoji'] ?></div>
      <div style="flex:1;min-width:0"><div class="t"><?= e($t['judul']) ?></div><div class="s"><?= e($t['kategori']) ?> · <?= e($t['dompet_nama']) ?> · <?= tglIndo($t['tanggal'],false) ?></div></div>
      <span class="tx-amt" style="color:<?= $t['jumlah']>0?'var(--green)':'var(--red)' ?>"><?= $t['jumlah']>0?'+':'−' ?><?= rp(abs($t['jumlah'])) ?></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Notif teaser -->
<?php if($notifs): ?>
  <a href="?page=<?= $notifs[0]['go'] ?>" class="card" style="display:flex;align-items:center;gap:14px;padding:14px 18px;background:var(--terraT);border-color:#f0d8c5;margin-top:16px">
    <div class="cat" style="width:40px;height:40px;border-radius:12px;background:#fff;font-size:20px"><?= $notifs[0]['emoji'] ?></div>
    <div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($notifs[0]['title']) ?></div><div style="font-size:12px;color:var(--terraD);font-weight:600;margin-top:2px"><?= count($notifs) ?> notifikasi · ketuk lihat</div></div>
    <?= icon('chevR',18,'var(--terra)') ?>
  </a>
<?php endif; ?>

<script>
var SEG_keluar=<?= json_encode(array_map(fn($g)=>['kat'=>$g['kat'],'emoji'=>$g['emoji'],'total'=>$g['total'],'pct'=>$g['pct'],'warna'=>$g['warna']],$seg)) ?>;
var SEG_masuk=<?= json_encode(array_map(fn($g)=>['kat'=>$g['kat'],'emoji'=>$g['emoji'],'total'=>$g['total'],'pct'=>$g['pct'],'warna'=>$g['warna']],$segIn)) ?>;
var TLABELS=<?= json_encode($tlabels) ?>;
var TSERIES=<?= json_encode($tseries) ?>;
function rupiah(n){return 'Rp'+Math.round(n).toLocaleString('id-ID');}
function pickSeg(mode,i){var g=(mode==='masuk'?SEG_masuk:SEG_keluar)[i];if(!g)return;
  document.getElementById('donut-center-'+mode).innerHTML='<div style="font-size:11px;color:'+g.warna+';font-weight:800">'+g.emoji+' '+g.kat.toUpperCase()+'</div><div style="font-family:var(--serif);font-size:22px;font-weight:600">'+rupiah(g.total)+'</div><div style="font-size:12px;color:var(--soft)">'+g.pct+'% dari total</div>';
  document.querySelectorAll('.donut-seg-'+mode).forEach(s=>s.setAttribute('stroke-width',s.dataset.i==i?'34':'26'));
  document.querySelectorAll('.leg-item-'+mode).forEach(l=>l.style.background=l.dataset.i==i?'var(--card2)':'transparent');}
function donutMode(m){
  document.querySelectorAll('.donut-view').forEach(v=>v.style.display=v.dataset.mode===m?'':'none');
  document.getElementById('dm-keluar').classList.toggle('on',m==='keluar');
  document.getElementById('dm-masuk').classList.toggle('on',m==='masuk');
  document.getElementById('donut-judul').textContent=(m==='masuk'?'Pemasukan':'Pengeluaran');
}
document.querySelectorAll('[id^="donut-center-"]').forEach(function(c){c.dataset.orig=c.innerHTML;});
function resetDonut(mode){
  var c=document.getElementById('donut-center-'+mode); if(c&&c.dataset.orig!=null)c.innerHTML=c.dataset.orig;
  document.querySelectorAll('.donut-seg-'+mode).forEach(s=>s.setAttribute('stroke-width','26'));
  document.querySelectorAll('.leg-item-'+mode).forEach(l=>l.style.background='transparent');
}
// klik kategori (legend) → buka Analisa + sorot kategori itu
function gotoKat(kat){ location.href='index.php?page=transaksi&hl='+encodeURIComponent(kat)+'&filter=keluar#histori'; }
</script>
