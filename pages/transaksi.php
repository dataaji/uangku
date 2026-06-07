<?php
$filter=$_GET['filter']??'semua';
$periode=$_GET['periode']??'bulan';
$limit=max(1,(int)($_GET['limit']??10));   // jumlah baris histori: 5/10/50/100/custom
$dari=$_GET['dari']??''; $sampai=$_GET['sampai']??'';   // filter rentang tanggal (list)
$kat=$_GET['kat']??'';                                   // filter kategori (histori)
$q=trim($_GET['q']??'');                                 // pencarian teks
$hl=$_GET['hl']??'';                                     // sorot kategori (flash)
[$pa,$pb,$plabel]=periodeRange($periode);
$txAll=getTransaksi($pdo,$filter,$dari?:null,$sampai?:null);
if($kat) $txAll=array_values(array_filter($txAll,fn($t)=>$t['kategori']===$kat));
if($q!==''){ $ql=mb_strtolower($q); $txAll=array_values(array_filter($txAll,fn($t)=>mb_strpos(mb_strtolower(($t['judul']??'').' '.($t['kategori']??'').' '.($t['dompet_nama']??'')),$ql)!==false)); }
$tx=array_slice($txAll,0,$limit);            // hanya tampilkan N teratas
// ringkasan untuk rentang tanggal (jika dipakai)
$rangeRing=($dari&&$sampai)?getRingkasan($pdo,$dari,$sampai):null;
$all=getTransaksi($pdo,'semua');             // 1× ambil semua (utk hitung total + daftar kategori)
$katList=array_values(array_unique(array_column($all,'kategori')));
$ring=getRingkasan($pdo,$pa,$pb);
// Perbandingan bulan ini vs bulan lalu
$cmpCur =getRingkasan($pdo,date('Y-m-01'),date('Y-m-t'));
$cmpPrev=getRingkasan($pdo,date('Y-m-01',strtotime('first day of -1 month')),date('Y-m-t',strtotime('first day of -1 month')));
$pctChg=function($cur,$prev){ if($prev<=0) return $cur>0?100:0; return round(($cur-$prev)/$prev*100); };
$spend=getSpendKategori($pdo,$pa,$pb);
$spendTotal=array_sum(array_column($spend,'total'));

// Ringkasan SEMUA menu
$bdg=getAnggaranSemua($pdo); $bdgPakai=array_sum(array_column($bdg,'terpakai')); $bdgBatas=array_sum(array_column($bdg,'batas')); $bdgLewat=count(array_filter($bdg,fn($b)=>$b['lewat']));
$tbg=getTabungan($pdo); $tbgKumpul=array_sum(array_column($tbg,'terkumpul')); $tbgTarget=array_sum(array_column($tbg,'target'));
$tgh=getTagihan($pdo); $tghDue=0;$tghOver=0;$tghLunas=0; foreach($tgh as $b){$s=tagihanStatus($b); if($s==='lunas')$tghLunas++; elseif($s==='overdue')$tghOver++; else $tghDue++;}
$saldo=getSaldoTotal($pdo);

// Data donut (SVG arcs) — pengeluaran
$C=2*M_PI*70; $seg=[]; $acc=0;
foreach($spend as $s){ $frac=$spendTotal>0?$s['total']/$spendTotal:0; $len=$frac*$C;
  $seg[]=['kat'=>$s['kategori'],'warna'=>katMeta($pdo,$s['kategori'])['warna'],'emoji'=>katMeta($pdo,$s['kategori'])['emoji'],'len'=>$len,'off'=>$acc,'total'=>$s['total'],'pct'=>round($frac*100)];
  $acc+=$len; }
// donut — pemasukan
$spendIn=getIncomeKategori($pdo,$pa,$pb); $incTotal=array_sum(array_column($spendIn,'total'));
$segIn=[]; $acc=0;
foreach($spendIn as $s){ $frac=$incTotal>0?$s['total']/$incTotal:0; $len=$frac*$C;
  $segIn[]=['kat'=>$s['kategori'],'warna'=>katMeta($pdo,$s['kategori'])['warna'],'emoji'=>katMeta($pdo,$s['kategori'])['emoji'],'len'=>$len,'off'=>$acc,'total'=>$s['total'],'pct'=>round($frac*100)];
  $acc+=$len; }
// donut — anggaran (alokasi batas per kategori)
$bdgTot=array_sum(array_column($bdg,'batas')); $segBdg=[]; $acc=0;
foreach($bdg as $b){ $frac=$bdgTot>0?$b['batas']/$bdgTot:0; $len=$frac*$C;
  $segBdg[]=['kat'=>$b['kategori'],'warna'=>katMeta($pdo,$b['kategori'])['warna'],'emoji'=>$b['emoji']?:katMeta($pdo,$b['kategori'])['emoji'],'len'=>$len,'off'=>$acc,'total'=>$b['batas'],'terpakai'=>$b['terpakai'],'pct'=>round($frac*100)];
  $acc+=$len; }

$groups=[]; foreach($tx as $t){ if(!isset($groups[$t['tanggal']]))$groups[$t['tanggal']]=[]; $groups[$t['tanggal']][]=$t; }

// ── Tren grafik garis MULTI (per kategori + pemasukan) ──
$tmode=$_GET['tmode']??'bulan';
$TK=getTrendKategori($pdo,$tmode); $tlabels=$TK['labels']; $tseries=$TK['series'];
$tmax=1; foreach($tseries as $s) foreach($s['data'] as $v) $tmax=max($tmax,$v);
$LW=620;$LH=220;$pl=44;$pr=16;$pt=16;$pb=34; $n=count($tlabels); $iw=$LW-$pl-$pr;$ih=$LH-$pt-$pb;
$xp=function($i)use($n,$pl,$iw){ return $n<=1?$pl+$iw/2:$pl+$i*($iw/($n-1)); };
$yp=function($v)use($tmax,$pt,$ih){ return $pt+$ih-($v/$tmax)*$ih; };

topbar('Analisa', count($all).' transaksi · ringkasan semua menu', $notifs, 'transaksi');
?>

<!-- Toggle: pilih menu/section apa yang mau dilihat -->
<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;align-items:center" id="sec-toggle">
  <?php foreach(['ringkasan'=>'🧭 Ringkasan Menu','diagram'=>'📊 Diagram','histori'=>'📜 Histori'] as $s=>$lbl): ?>
    <button type="button" class="pill sec-chip" data-sec="<?= $s ?>" onclick="toggleSec('<?= $s ?>')" style="padding:8px 16px;font-size:12.5px;border:1px solid var(--line);background:var(--ink);color:var(--bg);cursor:pointer"><?= $lbl ?></button>
  <?php endforeach; ?>
  <div style="flex:1"></div>
  <button type="button" class="btn btn-primary btn-sm" onclick="openModal('m-laporan')"><?= icon('export',14,'#fff') ?> Unduh PDF</button>
</div>

<!-- Modal pilih bagian laporan -->
<div class="modal-bg" id="m-laporan"><div class="modal" style="max-width:430px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><h2>Unduh Laporan PDF</h2><button class="icon-btn" onclick="closeModal('m-laporan')"><?= icon('x',18) ?></button></div>
  <div style="font-size:12px;color:var(--soft);background:var(--card2);border-radius:10px;padding:10px 12px;margin-bottom:14px;line-height:1.5">
    ℹ️ <b>Cara pakai:</b> pilih <b>periode</b> (rentang waktu) lalu centang <b>bagian</b> yang ingin dimasukkan, kemudian tekan <b>Unduh PDF</b> → pada dialog cetak pilih <b>"Simpan sebagai PDF"</b>.
  </div>

  <div class="field" style="margin-bottom:10px"><label>Periode laporan</label>
    <select id="lap-periode" onchange="lapPeriodeChange()">
      <option value="">Ikuti filter Analisa (<?= e($plabel) ?>)</option>
      <option value="hari">Hari ini</option>
      <option value="minggu">Minggu ini</option>
      <option value="bulan">Bulan ini</option>
      <option value="tahun">Tahun ini</option>
      <option value="semua">Semua waktu</option>
      <option value="custom">Rentang khusus (pilih tanggal)…</option>
    </select>
  </div>
  <div id="lap-range" style="display:none;gap:12px;margin-bottom:12px">
    <div style="display:flex;gap:12px">
      <div class="field" style="flex:1;margin-bottom:0"><label>Dari</label><input type="date" id="lap-dari"></div>
      <div class="field" style="flex:1;margin-bottom:0"><label>Sampai</label><input type="date" id="lap-sampai"></div>
    </div>
  </div>

  <div style="font-size:11.5px;font-weight:800;letter-spacing:.5px;color:var(--muted);text-transform:uppercase;margin-bottom:6px">Bagian laporan</div>
  <div style="display:flex;flex-direction:column;gap:2px">
    <?php $lapOpts=['ringkasan'=>'🧭 Ringkasan (pemasukan, pengeluaran, saldo)','transaksi'=>'📜 Data Keluar–Masuk (daftar transaksi)','pengeluaran'=>'📤 Pengeluaran per kategori','pemasukan'=>'📥 Pemasukan per kategori','dompet'=>'👛 Saldo per dompet','anggaran'=>'📊 Anggaran','tabungan'=>'🐷 Tabungan','tagihan'=>'💡 Tagihan'];
    foreach($lapOpts as $k=>$lbl): ?>
      <label style="display:flex;align-items:center;gap:10px;cursor:pointer;padding:9px 6px;border-bottom:1px solid var(--line)"><input type="checkbox" class="lap-cb" value="<?= $k ?>" checked style="width:18px;height:18px;accent-color:var(--terra)"><span style="font-size:13px;font-weight:600"><?= $lbl ?></span></label>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px">
    <button class="btn btn-ghost btn-sm" style="flex:1;justify-content:center" onclick="lapAll(true)">Pilih semua</button>
    <button class="btn btn-ghost btn-sm" style="flex:1;justify-content:center" onclick="lapAll(false)">Kosongkan</button>
  </div>
  <button class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800;margin-top:12px" onclick="unduhLaporan()"><?= icon('export',16,'#fff') ?> Unduh PDF</button>
  <button class="btn btn-ghost" style="width:100%;justify-content:center;padding:12px;font-weight:800;margin-top:8px" onclick="unduhExcel()"><?= icon('export',16) ?> Unduh Excel/CSV (transaksi)</button>
  <div style="font-size:11px;color:var(--muted);margin-top:6px;text-align:center">Excel = daftar transaksi sesuai periode di atas.</div>
</div></div></div>

<!-- RINGKASAN SEMUA MENU -->
<div class="sec" data-sec="ringkasan">
<div class="grid-3" style="margin-bottom:8px">
  <a href="?page=beranda" class="card" style="padding:16px 18px;display:block">
    <div style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:var(--soft)"><?= icon('home',15,'var(--terra)') ?> Total Saldo</div>
    <div style="font-family:var(--serif);font-size:22px;font-weight:600;margin-top:6px"><?= rpShort($saldo) ?></div>
  </a>
  <a href="?page=anggaran" class="card" style="padding:16px 18px;display:block">
    <div style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:var(--soft)"><?= icon('budget',15,'var(--blue)') ?> Anggaran</div>
    <div style="font-family:var(--serif);font-size:22px;font-weight:600;margin-top:6px"><?= $bdgBatas?round($bdgPakai/$bdgBatas*100):0 ?>% terpakai</div>
    <div class="prog" style="margin-top:8px;height:6px"><i style="width:<?= $bdgBatas?min(100,$bdgPakai/$bdgBatas*100):0 ?>%;background:<?= $bdgLewat?'var(--red)':'var(--blue)' ?>"></i></div>
  </a>
  <a href="?page=tabungan" class="card" style="padding:16px 18px;display:block">
    <div style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:var(--soft)"><?= icon('savings',15,'var(--green)') ?> Tabungan</div>
    <div style="font-family:var(--serif);font-size:22px;font-weight:600;margin-top:6px"><?= rpShort($tbgKumpul) ?></div>
    <div class="prog" style="margin-top:8px;height:6px"><i style="width:<?= $tbgTarget?min(100,$tbgKumpul/$tbgTarget*100):0 ?>%;background:var(--green)"></i></div>
  </a>
</div>
<div style="margin-bottom:18px"></div>
<a href="?page=tagihan" class="card" style="padding:14px 18px;display:flex;align-items:center;gap:16px;margin-bottom:22px;flex-wrap:wrap">
  <div style="display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:700;color:var(--soft)"><?= icon('bill',15,'var(--amber)') ?> Tagihan:</div>
  <span class="pill" style="background:var(--amberT);color:var(--amber)">⏳ <?= $tghDue ?> jatuh tempo</span>
  <span class="pill" style="background:var(--redT);color:var(--red)">🔴 <?= $tghOver ?> lewat tempo</span>
  <span class="pill" style="background:var(--greenT);color:var(--green)">✅ <?= $tghLunas ?> lunas</span>
  <div style="flex:1"></div><?= icon('chevR',16,'var(--muted)') ?>
</a>
</div><!-- /ringkasan -->

<div class="sec" data-sec="diagram">
<!-- Pemilih periode untuk analisa -->
<div class="seg-tabs" style="margin-bottom:16px">
  <?php foreach(['hari'=>'Hari Ini','minggu'=>'Minggu','bulan'=>'Bulan','tahun'=>'Tahun'] as $k=>$lbl): ?>
    <a href="?page=transaksi&periode=<?= $k ?>&filter=<?= $filter ?>&limit=<?= $limit ?>" onclick="saveScroll()" class="<?= $periode===$k?'on':'' ?>"><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<!-- Ringkasan -->
<div class="grid-3" style="margin-bottom:18px">
  <div class="card" style="padding:16px 18px"><div style="font-size:12px;font-weight:700;color:var(--soft)">📥 Pemasukan</div><div style="font-family:var(--serif);font-size:22px;font-weight:600;color:var(--green);margin-top:6px"><?= rpShort($ring['masuk']) ?></div><div style="font-size:11px;color:var(--muted);margin-top:2px"><?= $plabel ?></div></div>
  <div class="card" style="padding:16px 18px"><div style="font-size:12px;font-weight:700;color:var(--soft)">📤 Pengeluaran</div><div style="font-family:var(--serif);font-size:22px;font-weight:600;color:var(--red);margin-top:6px"><?= rpShort($ring['keluar']) ?></div><div style="font-size:11px;color:var(--muted);margin-top:2px"><?= $ring['jml'] ?> transaksi</div></div>
  <?php $sel=$ring['masuk']-$ring['keluar']; ?>
  <div class="card" style="padding:16px 18px"><div style="font-size:12px;font-weight:700;color:var(--soft)">⚖️ Selisih</div><div style="font-family:var(--serif);font-size:22px;font-weight:600;color:<?= $sel>=0?'var(--green)':'var(--red)' ?>;margin-top:6px"><?= ($sel>=0?'+':'−').rpShort(abs($sel)) ?></div><div style="font-size:11px;color:var(--muted);margin-top:2px"><?= $sel>=0?'surplus':'defisit' ?></div></div>
</div>

<!-- Perbandingan bulan ini vs bulan lalu -->
<?php
$cmpRow=function($label,$cur,$prev,$emoji) use($pctChg){
  $chg=$pctChg($cur,$prev); $naik=$chg>=0;
  // untuk pengeluaran, naik = jelek (merah); untuk pemasukan, naik = bagus (hijau)
  ?>
  <div class="card" style="padding:14px 16px">
    <div style="font-size:12px;font-weight:700;color:var(--soft)"><?= $emoji ?> <?= $label ?></div>
    <div style="font-family:var(--serif);font-size:20px;font-weight:600;margin-top:4px"><?= rpShort($cur) ?></div>
    <div style="font-size:11.5px;font-weight:700;margin-top:3px;color:<?= $chg==0?'var(--muted)':($naik?'var(--green)':'var(--red)') ?>">
      <?= $chg>0?'▲ +':($chg<0?'▼ ':'• ') ?><?= abs($chg) ?>% <span style="color:var(--muted);font-weight:600">vs bln lalu (<?= rpShort($prev) ?>)</span>
    </div>
  </div>
<?php };
?>
<div class="eyebrow" style="margin-bottom:10px">📅 Bulan Ini vs Bulan Lalu</div>
<div class="grid-3" style="margin-bottom:24px">
  <?php $cmpRow('Pemasukan',(float)$cmpCur['masuk'],(float)$cmpPrev['masuk'],'📥'); ?>
  <?php $cmpRow('Pengeluaran',(float)$cmpCur['keluar'],(float)$cmpPrev['keluar'],'📤'); ?>
  <?php $cmpRow('Selisih',(float)$cmpCur['masuk']-$cmpCur['keluar'],(float)$cmpPrev['masuk']-$cmpPrev['keluar'],'⚖️'); ?>
</div>

<!-- GRAFIK INTERAKTIF -->
<div class="grid-fit" style="margin-bottom:24px">
  <!-- Donut interaktif -->
  <div class="card" style="padding:20px 22px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap">
      <div style="font-family:var(--serif);font-size:17px;font-weight:600"><span id="donut-judul">Pengeluaran</span> per Kategori</div>
      <div class="seg-tabs">
        <a href="#" id="dm-keluar" class="on" onclick="donutMode('keluar');return false">Pengeluaran</a>
        <a href="#" id="dm-masuk" onclick="donutMode('masuk');return false">Pemasukan</a>
        <a href="#" id="dm-anggaran" onclick="donutMode('anggaran');return false">Anggaran</a>
      </div>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:14px">Ketuk bagian donat untuk melihat rincian · pilih Pengeluaran / Pemasukan / Anggaran di atas</div>
    <?php
    $renderDonut=function($seg,$total,$mode) use($C){
      if(!$seg){ echo '<div class="empty" style="padding:30px 0"><div class="msg" style="font-size:13px">Belum ada data 🎉</div></div>'; return; }
      ?>
      <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap">
        <svg viewBox="0 0 180 180" width="170" height="170" style="flex-shrink:0;transform:rotate(-90deg)">
          <?php foreach($seg as $i=>$g): ?><circle cx="90" cy="90" r="70" fill="none" stroke="<?= $g['warna'] ?>" stroke-width="26" stroke-dasharray="<?= $g['len'] ?> <?= $C-$g['len'] ?>" stroke-dashoffset="<?= -$g['off'] ?>" class="donut-seg-<?= $mode ?>" data-i="<?= $i ?>" style="cursor:pointer;transition:stroke-width .15s" onclick="pickSeg('<?= $mode ?>',<?= $i ?>)"></circle><?php endforeach; ?>
        </svg>
        <div style="flex:1;min-width:150px">
          <div id="donut-center-<?= $mode ?>" style="margin-bottom:8px"><div style="font-size:11px;color:var(--muted);font-weight:700">TOTAL</div><div style="font-family:var(--serif);font-size:24px;font-weight:600"><?= rpShort($total) ?></div></div>
          <button type="button" onclick="resetDonut('<?= $mode ?>')" class="btn btn-ghost btn-sm" style="margin-bottom:10px;padding:5px 10px;font-size:11px">↺ Tampilkan semua</button>
          <div style="display:flex;flex-direction:column;gap:7px">
            <?php foreach($seg as $i=>$g): ?><div class="leg-item-<?= $mode ?>" data-i="<?= $i ?>" onclick="pickSeg('<?= $mode ?>',<?= $i ?>)" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:3px 0;border-radius:6px"><span style="width:10px;height:10px;border-radius:3px;background:<?= $g['warna'] ?>"></span><span style="font-size:13px;font-weight:600;flex:1"><?= $g['emoji'] ?> <?= e($g['kat']) ?></span><span style="font-size:12px;font-weight:700;color:var(--soft)"><?= $g['pct'] ?>%</span></div><?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php
    };
    ?>
    <div class="donut-view" data-mode="keluar"><?php $renderDonut($seg,$spendTotal,'keluar'); ?></div>
    <div class="donut-view" data-mode="masuk" style="display:none"><?php $renderDonut($segIn,$incTotal,'masuk'); ?></div>
    <div class="donut-view" data-mode="anggaran" style="display:none"><?php $renderDonut($segBdg,$bdgTot,'anggaran'); ?></div>
  </div>

  <!-- Grafik GARIS MULTI interaktif (per kategori + pemasukan) -->
  <div class="card" style="padding:20px 22px">
    <div style="font-family:var(--serif);font-size:17px;font-weight:600;margin-bottom:4px">Tren Arus Kas — Semua Kategori</div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px">Ketuk titik/batang untuk menampilkan nilai · ketuk nama di bawah untuk menyembunyikan garis</div>
    <!-- filter periode tren sendiri -->
    <div class="seg-tabs" style="margin-bottom:10px">
      <?php foreach(['minggu'=>'Mingguan','bulan'=>'Bulanan','tahun'=>'Tahunan','semua'=>'Semua'] as $k=>$lbl): ?>
        <a href="?page=transaksi&periode=<?= $periode ?>&filter=<?= $filter ?>&limit=<?= $limit ?>&tmode=<?= $k ?>#diagram" onclick="saveScroll()" class="<?= $tmode===$k?'on':'' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
    <?php renderTrendBody($tlabels,$tseries,220); ?>
  </div>
</div>
</div><!-- /diagram -->

<!-- ====== HISTORI (filter list terpisah dari diagram) ====== -->
<div class="sec" data-sec="histori">
<div class="eyebrow" id="histori" style="margin-bottom:12px">📜 Histori Transaksi</div>
<?php $qbase="?page=transaksi&periode=$periode"; $dq=($dari?"&dari=$dari":'').($sampai?"&sampai=$sampai":'').($kat?'&kat='.urlencode($kat):'').($q!==''?'&q='.urlencode($q):''); ?>

<!-- Pencarian transaksi -->
<form method="get" action="index.php" style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap" onsubmit="saveScroll()">
  <input type="hidden" name="page" value="transaksi"><input type="hidden" name="periode" value="<?= e($periode) ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><input type="hidden" name="limit" value="<?= (int)$limit ?>">
  <?php if($dari): ?><input type="hidden" name="dari" value="<?= e($dari) ?>"><?php endif; ?><?php if($sampai): ?><input type="hidden" name="sampai" value="<?= e($sampai) ?>"><?php endif; ?>
  <input type="text" name="q" value="<?= e($q) ?>" placeholder="🔍 Cari nama/kategori/dompet…" style="flex:1;min-width:180px;padding:9px 13px;border:1px solid var(--line);border-radius:11px;background:var(--card);color:var(--ink);font-size:13px;outline:none">
  <button class="btn btn-dark btn-sm">Cari</button>
  <?php if($q!==''): ?><a href="<?= $qbase.($dari?"&dari=$dari":'').($sampai?"&sampai=$sampai":'').($kat?'&kat='.urlencode($kat):'') ?>&filter=<?= $filter ?>&limit=<?= $limit ?>#histori" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
</form>
<?php if($q!==''): ?><div style="font-size:12px;color:var(--soft);margin-bottom:10px">Hasil pencarian "<b><?= e($q) ?></b>": <?= count($txAll) ?> transaksi</div><?php endif; ?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <?php foreach(['semua'=>'Semua','masuk'=>'Pemasukan','keluar'=>'Pengeluaran'] as $f=>$lbl): ?>
      <a href="<?= $qbase ?>&filter=<?= $f ?>&limit=<?= $limit ?><?= $dq ?>#histori" onclick="saveScroll()" class="pill" style="padding:8px 18px;font-size:13px;<?= $filter===$f?'background:var(--ink);color:#fff':'background:var(--card);color:var(--soft);border:1px solid var(--line)' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
    <select onchange="saveScroll();location=this.value" style="padding:8px 12px;border:1px solid var(--line);border-radius:999px;background:var(--card);color:var(--ink);font-size:12.5px;font-weight:700;outline:none;cursor:pointer">
      <option value="<?= $qbase ?>&filter=<?= $filter ?>&limit=<?= $limit ?><?= $dari?"&dari=$dari":'' ?><?= $sampai?"&sampai=$sampai":'' ?>#histori">🏷️ Semua kategori</option>
      <?php foreach($katList as $kk): ?><option value="<?= $qbase ?>&filter=<?= $filter ?>&limit=<?= $limit ?><?= $dari?"&dari=$dari":'' ?><?= $sampai?"&sampai=$sampai":'' ?>&kat=<?= urlencode($kk) ?>#histori" <?= $kat===$kk?'selected':'' ?>><?= katMeta($pdo,$kk)['emoji'] ?> <?= e($kk) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <span style="font-size:12.5px;color:var(--soft);font-weight:600">Tampilkan:</span>
    <div class="seg-tabs">
      <?php foreach([5,10,50,100] as $n): ?>
        <a href="<?= $qbase ?>&filter=<?= $filter ?>&limit=<?= $n ?><?= $dq ?>#histori" onclick="saveScroll()" class="<?= $limit===$n?'on':'' ?>"><?= $n ?></a>
      <?php endforeach; ?>
    </div>
    <input type="number" min="1" value="<?= in_array($limit,[5,10,50,100])?'':$limit ?>" placeholder="lain"
      onchange="if(this.value>0){saveScroll();location='<?= $qbase ?>&filter=<?= $filter ?><?= $dq ?>&limit='+this.value+'#histori'}"
      style="width:60px;padding:7px 9px;border:1px solid var(--line);border-radius:9px;background:var(--card);color:var(--ink);font-size:12.5px;font-weight:700;text-align:center;outline:none">
  </div>
</div>

<!-- Filter rentang tanggal -->
<form method="get" action="index.php" style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap" onsubmit="saveScroll()">
  <input type="hidden" name="page" value="transaksi"><input type="hidden" name="periode" value="<?= $periode ?>"><input type="hidden" name="filter" value="<?= $filter ?>"><input type="hidden" name="limit" value="<?= $limit ?>">
  <span style="font-size:12.5px;color:var(--soft);font-weight:600">📅 Dari</span>
  <input type="date" name="dari" value="<?= e($dari) ?>" style="padding:7px 10px;border:1px solid var(--line);border-radius:9px;background:var(--card);color:var(--ink);font-size:12.5px;outline:none">
  <span style="font-size:12.5px;color:var(--soft);font-weight:600">sampai</span>
  <input type="date" name="sampai" value="<?= e($sampai) ?>" style="padding:7px 10px;border:1px solid var(--line);border-radius:9px;background:var(--card);color:var(--ink);font-size:12.5px;outline:none">
  <button class="btn btn-dark btn-sm">Terapkan</button>
  <?php if($dari||$sampai): ?><a href="<?= $qbase ?>&filter=<?= $filter ?>&limit=<?= $limit ?>#histori" class="btn btn-ghost btn-sm">Reset</a><?php endif; ?>
</form>
<?php if($rangeRing): ?>
  <div class="card" style="display:flex;gap:18px;padding:12px 18px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
    <span style="font-size:12.5px;font-weight:700;color:var(--soft)"><?= tglIndo($dari,false) ?> – <?= tglIndo($sampai,false) ?>:</span>
    <span style="font-size:13px;font-weight:700;color:var(--green)">📥 Masuk <?= rp($rangeRing['masuk']) ?></span>
    <span style="font-size:13px;font-weight:700;color:var(--red)">📤 Keluar <?= rp($rangeRing['keluar']) ?></span>
    <span style="font-size:13px;font-weight:700">⚖️ <?= $rangeRing['jml'] ?> transaksi</span>
  </div>
<?php endif; ?>
<div style="font-size:12px;color:var(--muted);margin-bottom:10px">Menampilkan <?= count($tx) ?> dari <?= count($txAll) ?> transaksi<?= $filter!=='semua'?' ('.$filter.')':'' ?></div>

<?php if(!$groups): ?><div class="empty"><div class="ico">📋</div><div class="msg">Belum ada transaksi</div></div><?php endif; ?>
<?php foreach($groups as $tgl=>$items): ?>
  <div class="eyebrow" style="margin:18px 0 8px"><?= e(tglIndo($tgl)) ?></div>
  <div class="card">
    <?php foreach($items as $t): ?>
      <div class="row tx-row" data-kat="<?= e($t['kategori']) ?>" style="cursor:pointer;border-left:4px solid <?= $t['jumlah']>0?'var(--green)':(katMeta($pdo,$t['kategori'])['warna']) ?>;padding-left:12px;border-radius:8px" onclick='openDetail(<?= json_encode($t,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
        <div class="cat" style="width:44px;height:44px;font-size:21px;background:<?= $t['tint']?:katMeta($pdo,$t['kategori'])['tint'] ?>"><?= $t['emoji']?:katMeta($pdo,$t['kategori'])['emoji'] ?></div>
        <div style="flex:1;min-width:0"><div class="t"><?= e($t['judul']) ?></div><div class="s"><?= e($t['kategori']) ?> · <?= e($t['dompet_nama']) ?></div></div>
        <span class="tx-amt" style="color:<?= $t['jumlah']>0?'var(--green)':'var(--red)' ?>"><?= $t['jumlah']>0?'+':'−' ?><?= rp(abs($t['jumlah'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div><!-- /histori -->

<!-- Modal detail -->
<div class="modal-bg" id="m-detail"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:18px">
    <div id="d-emoji" class="cat" style="width:66px;height:66px;border-radius:22px;font-size:32px"></div>
    <div id="d-judul" style="font-family:var(--serif);font-size:22px;font-weight:600;margin-top:12px"></div>
    <div id="d-jumlah" style="font-family:var(--serif);font-size:34px;font-weight:600;margin-top:2px"></div>
  </div>
  <div class="card" style="margin-bottom:16px">
    <div class="row" style="justify-content:space-between;padding:13px 16px"><span style="color:var(--soft);font-weight:600;font-size:13.5px">Kategori</span><span id="d-kat" style="font-weight:700;font-size:13.5px"></span></div>
    <div class="row" style="justify-content:space-between;padding:13px 16px"><span style="color:var(--soft);font-weight:600;font-size:13.5px">Dompet</span><span id="d-dompet" style="font-weight:700;font-size:13.5px"></span></div>
    <div class="row" style="justify-content:space-between;padding:13px 16px"><span style="color:var(--soft);font-weight:600;font-size:13.5px">Tanggal</span><span id="d-tgl" style="font-weight:700;font-size:13.5px"></span></div>
  </div>
  <div style="display:flex;gap:11px">
    <button class="btn btn-ghost" style="flex:1;justify-content:center;padding:14px" onclick="closeModal('m-detail')"><?= icon('x',18) ?> Tutup</button>
    <form method="post" action="actions.php" style="flex:1" data-confirm="Hapus transaksi ini?">
      <input type="hidden" name="action" value="delete_transaksi"><input type="hidden" name="id" id="d-id"><input type="hidden" name="back" value="?page=transaksi">
      <button type="submit" class="btn" style="width:100%;justify-content:center;padding:14px;background:var(--redT);color:var(--red)"><?= icon('trash',18,'var(--red)') ?> Hapus</button>
    </form>
  </div>
</div></div></div>

<script>
var SEG_keluar=<?= json_encode(array_map(fn($g)=>['kat'=>$g['kat'],'emoji'=>$g['emoji'],'total'=>$g['total'],'pct'=>$g['pct'],'warna'=>$g['warna']],$seg)) ?>;
var SEG_masuk=<?= json_encode(array_map(fn($g)=>['kat'=>$g['kat'],'emoji'=>$g['emoji'],'total'=>$g['total'],'pct'=>$g['pct'],'warna'=>$g['warna']],$segIn)) ?>;
var SEG_anggaran=<?= json_encode(array_map(fn($g)=>['kat'=>$g['kat'],'emoji'=>$g['emoji'],'total'=>$g['total'],'terpakai'=>$g['terpakai'],'pct'=>$g['pct'],'warna'=>$g['warna']],$segBdg)) ?>;
var TLABELS=<?= json_encode($tlabels) ?>;
var TSERIES=<?= json_encode($tseries) ?>;
function rupiah(n){return 'Rp'+Math.round(n).toLocaleString('id-ID');}
function lapAll(v){document.querySelectorAll('.lap-cb').forEach(function(c){c.checked=v;});}
function lapPeriodeChange(){document.getElementById('lap-range').style.display=document.getElementById('lap-periode').value==='custom'?'block':'none';}
function lapQuery(){ // bangun query periode dari pilihan modal; null jika tidak valid
  var p=document.getElementById('lap-periode').value;
  if(p===''){ return 'periode=<?= e($periode) ?><?= $dari?'&dari='.e($dari):'' ?><?= $sampai?'&sampai='.e($sampai):'' ?>'; }
  if(p==='custom'){
    var d=document.getElementById('lap-dari').value, s=document.getElementById('lap-sampai').value;
    if(!d||!s){alert('Isi tanggal Dari dan Sampai untuk rentang khusus.');return null;}
    if(d>s){var t=d;d=s;s=t;}
    return 'dari='+d+'&sampai='+s;
  }
  return 'periode='+p;
}
function unduhLaporan(){
  var sel=Array.prototype.slice.call(document.querySelectorAll('.lap-cb:checked')).map(function(c){return c.value;});
  if(!sel.length){alert('Pilih minimal satu bagian laporan.');return;}
  var q=lapQuery(); if(q===null)return;
  window.open('laporan.php?'+q+'&sec='+sel.join(','),'_blank'); closeModal('m-laporan');
}
function unduhExcel(){
  var q=lapQuery(); if(q===null)return;
  window.open('export.php?'+q,'_blank'); closeModal('m-laporan');
}
function donutMode(m){
  document.querySelectorAll('.donut-view').forEach(v=>v.style.display=v.dataset.mode===m?'':'none');
  ['keluar','masuk','anggaran'].forEach(function(x){var b=document.getElementById('dm-'+x);if(b)b.classList.toggle('on',x===m);});
  document.getElementById('donut-judul').textContent=(m==='masuk'?'Pemasukan':(m==='anggaran'?'Anggaran':'Pengeluaran'));
}
document.querySelectorAll('[id^="donut-center-"]').forEach(function(c){c.dataset.orig=c.innerHTML;});
function resetDonut(mode){
  var c=document.getElementById('donut-center-'+mode); if(c&&c.dataset.orig!=null)c.innerHTML=c.dataset.orig;
  document.querySelectorAll('.donut-seg-'+mode).forEach(s=>s.setAttribute('stroke-width','26'));
  document.querySelectorAll('.leg-item-'+mode).forEach(l=>l.style.background='transparent');
}
// toggle section per-menu (disimpan di localStorage)
function applySec(){ try{var h=JSON.parse(localStorage.getItem('anaSec')||'{}');
  document.querySelectorAll('.sec').forEach(el=>{var s=el.dataset.sec; var hide=h[s]===0; el.style.display=hide?'none':''; });
  document.querySelectorAll('.sec-chip').forEach(c=>{var s=c.dataset.sec; var on=h[s]!==0; c.style.background=on?'var(--ink)':'var(--card)'; c.style.color=on?'var(--bg)':'var(--soft)';});
}catch(e){} }
function toggleSec(s){ var h={}; try{h=JSON.parse(localStorage.getItem('anaSec')||'{}');}catch(e){} h[s]=(h[s]===0)?1:0; localStorage.setItem('anaSec',JSON.stringify(h)); applySec(); }
applySec();
function pickSeg(mode,i){
  var arr=(mode==='masuk'?SEG_masuk:(mode==='anggaran'?SEG_anggaran:SEG_keluar)); var g=arr[i]; if(!g)return;
  var html;
  if(mode==='anggaran'){
    var pakai=g.terpakai||0, sisa=g.total-pakai;
    html='<div style="font-size:12px;color:'+g.warna+';font-weight:800">'+g.emoji+' '+g.kat.toUpperCase()+'</div>'
      +'<div style="font-size:12.5px;color:var(--soft);margin-top:6px">Anggaran: <b style="color:var(--ink)">'+rupiah(g.total)+'</b></div>'
      +'<div style="font-size:12.5px;color:var(--soft)">Terpakai: <b style="color:var(--ink)">'+rupiah(pakai)+'</b></div>'
      +'<div style="font-size:14px;font-weight:800;margin-top:2px;color:'+(sisa<0?'var(--red)':'var(--green)')+'">'+(sisa<0?'Kurang: ':'Sisa: ')+rupiah(Math.abs(sisa))+'</div>';
  } else {
    html='<div style="font-size:11px;color:'+g.warna+';font-weight:800">'+g.emoji+' '+g.kat.toUpperCase()+'</div><div style="font-family:var(--serif);font-size:24px;font-weight:600">'+rupiah(g.total)+'</div><div style="font-size:12px;color:var(--soft)">'+g.pct+'% dari total</div>';
  }
  document.getElementById('donut-center-'+mode).innerHTML=html;
  document.querySelectorAll('.donut-seg-'+mode).forEach(s=>s.setAttribute('stroke-width', s.dataset.i==i?'34':'26'));
  document.querySelectorAll('.leg-item-'+mode).forEach(l=>l.style.background=l.dataset.i==i?'var(--card2)':'transparent');
}
// Flash sorot kategori (putih transparan, lalu hilang)
(function(){ var hl=<?= json_encode($hl) ?>; if(!hl)return;
  setTimeout(function(){ document.querySelectorAll('.tx-row').forEach(function(r){ if(r.dataset.kat===hl){ r.classList.add('flash'); setTimeout(()=>r.classList.remove('flash'),1800); } }); },200);
})();
function openDetail(t){
  var em=document.getElementById('d-emoji');em.textContent=t.emoji||'💰';em.style.background=t.tint||'#f7ecd5';
  document.getElementById('d-judul').textContent=t.judul;
  var j=document.getElementById('d-jumlah');j.textContent=(t.jumlah>0?'+':'−')+'Rp'+Math.abs(t.jumlah).toLocaleString('id-ID');j.style.color=t.jumlah>0?'var(--green)':'var(--red)';
  document.getElementById('d-kat').textContent=(t.emoji||'')+' '+t.kategori;
  document.getElementById('d-dompet').textContent=t.dompet_nama||'-';
  document.getElementById('d-tgl').textContent=t.tanggal;
  document.getElementById('d-id').value=t.id;
  openModal('m-detail');
}
</script>
