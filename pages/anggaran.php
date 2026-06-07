<?php
$budgets=getAnggaranSemua($pdo);
$histori=array_values(array_filter($budgets,fn($b)=>!empty($b['expired'])));
$aktifAll=array_values(array_filter($budgets,fn($b)=>empty($b['expired'])));
$aktif=array_filter($aktifAll,fn($b)=>!$b['lewat']);
$lewat=array_filter($aktifAll,fn($b)=>$b['lewat']);
$totBatas=array_sum(array_column($aktifAll,'batas'));
$totPakai=array_sum(array_column($aktifAll,'terpakai'));
$kategoriKeluar=getKategori($pdo,'keluar');
$dompetList=getDompet($pdo);
$periodeOpt=['harian'=>'Harian','mingguan'=>'Mingguan','bulanan'=>'Bulanan','tahunan'=>'Tahunan','selamanya'=>'Selamanya'];

$angLog=getAnggaranLog($pdo);
topbar('Anggaran', count($budgets).' anggaran · '.count($dompetList).' dompet', $notifs, 'anggaran',
  '<button class="btn btn-ghost hide-mobile" onclick="openModal(\'m-riwayat\')">'.icon('clock',16,'currentColor').' Riwayat</button>'.
  '<button class="btn btn-ghost hide-mobile" onclick="openAng()">'.icon('plus',16,'currentColor',2.5).' Anggaran</button>');

function angCard($b,$periodeOpt,$isHist=false){ global $pdo;
  $pct=$b['pct']; $sisa=$b['batas']-$b['terpakai'];
  $warna=$b['lewat']?'var(--red)':($pct>=80?'var(--amber)':'var(--green)');
  $sukses=$b['terpakai']<=$b['batas'];
  ?>
  <div class="card" style="padding:18px 20px;<?= $isHist?'opacity:.92;border-color:'.($sukses?'var(--green)':'var(--red)'):($b['lewat']?'border-color:var(--red);background:linear-gradient(var(--card),var(--redT))':'') ?>">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:13px">
      <div class="cat" style="width:42px;height:42px;font-size:21px;background:<?= $b['tint'] ?>"><?= $b['emoji']?:katMeta($pdo,$b['kategori'])['emoji'] ?></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap"><span style="font-size:15px;font-weight:700"><?= e($b['kategori']) ?></span>
          <span class="pill" style="background:var(--card2);color:var(--soft);font-size:10px;padding:2px 8px"><?= e(anggaranPeriodeLabel($b)) ?></span>
          <?php if($isHist): ?><span class="pill" style="background:<?= $sukses?'var(--greenT)':'var(--redT)' ?>;color:<?= $sukses?'var(--green)':'var(--red)' ?>;font-size:10px;padding:2px 8px"><?= $sukses?'✅ Selesai':'❌ Tidak selesai' ?></span><?php endif; ?></div>
        <div style="font-size:12px;color:var(--soft);margin-top:2px"><?= rp($b['terpakai']) ?> / <?= rp($b['batas']) ?></div>
      </div>
      <div class="card-actions">
        <button class="mini-btn" onclick='editAng(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',15) ?></button>
        <form method="post" action="actions.php" data-confirm="Yakin hapus anggaran <?= e($b['kategori']) ?>?"><input type="hidden" name="action" value="delete_anggaran"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="?page=anggaran"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
      </div>
    </div>
    <div class="prog"><i style="width:<?= min(100,$pct) ?>%;background:<?= $warna ?>"></i></div>
    <div style="display:flex;justify-content:space-between;margin-top:9px">
      <span style="font-family:var(--serif);font-size:15px;font-weight:600;color:<?= $warna ?>"><?= round($pct) ?>%</span>
      <span style="font-size:11.5px;font-weight:700;color:<?= $sisa<0?'var(--red)':'var(--soft)' ?>"><?= $sisa<0?'⚠️ Lewat '.rpShort(-$sisa):'Sisa '.rpShort($sisa) ?></span>
    </div>
    <?php if(!$isHist): ?>
    <!-- Tambah / Kurangi jumlah anggaran -->
    <div style="display:flex;gap:8px;margin-top:12px">
      <button class="btn btn-dark btn-sm" style="flex:1;justify-content:center" onclick='openAdj(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"tambah")'><?= icon('plus',13,'var(--bg)',2.5) ?> Tambah</button>
      <button class="btn btn-ghost btn-sm" style="flex:1;justify-content:center" onclick='openAdj(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"kurang")'>− Kurangi</button>
    </div>
    <?php if($b['lewat']): ?><div style="font-size:11.5px;color:var(--red);font-weight:700;margin-top:8px">Anggaran terlampaui — pindah ke bawah. Tambah batas / edit untuk aktifkan lagi.</div><?php endif; ?>
    <?php else: ?><div style="font-size:11.5px;color:var(--soft);font-weight:700;margin-top:10px">Periode selesai <?= e(tglIndo($b['selesai_tgl'],false)) ?> · terpakai <?= rpShort($b['terpakai']) ?> dari <?= rpShort($b['batas']) ?></div><?php endif; ?>
  </div>
<?php }
?>

<?php if(($_GET['err']??'')==='dup'): ?>
  <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--redT);border-color:#f3c0c0;color:var(--red);font-weight:600;font-size:13px">⚠️ Kategori itu sudah punya anggaran. Satu kategori hanya boleh punya satu anggaran (silakan edit yang ada).</div>
<?php endif; ?>
<?php if(($_GET['err']??'')==='nodompet'): ?>
  <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--redT);border-color:#f3c0c0;color:var(--red);font-weight:600;font-size:13px">⚠️ Belum ada dompet. Tambah dompet dulu sebelum menambah saldo.</div>
<?php endif; ?>

<?php $warn=array_values(array_filter($aktifAll,fn($b)=>$b['pct']>=80)); if($warn): ?>
<div class="card" style="padding:14px 18px;margin-bottom:18px;border-left:5px solid var(--red);background:linear-gradient(var(--card),var(--redT))">
  <div style="font-weight:800;font-size:13.5px;color:var(--red);margin-bottom:8px">⚠️ Perhatian Anggaran — <?= count($warn) ?> kategori perlu dicek</div>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach($warn as $b): $over=$b['lewat']; ?>
      <span class="pill" style="background:<?= $over?'var(--redT)':'var(--amberT)' ?>;color:<?= $over?'var(--red)':'var(--amber)' ?>"><?= $b['emoji']?:katMeta($pdo,$b['kategori'])['emoji'] ?> <?= e($b['kategori']) ?> · <?= round($b['pct']) ?>%<?= $over?' (lewat)':'' ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ===== DOMPET (di atas anggaran) ===== -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:8px;flex-wrap:wrap">
  <div class="eyebrow" style="margin:0">💼 Dompet Saya</div>
  <div style="display:flex;gap:8px">
    <?php if(count($dompetList)>=2): ?><button class="btn btn-ghost btn-sm" onclick="openModal('m-transfer')"><?= icon('export',14) ?> Transfer</button><?php endif; ?>
    <button class="btn btn-primary btn-sm" onclick="openDompet()"><?= icon('plus',14,'#fff',2.5) ?> Dompet Baru</button>
  </div>
</div>
<?php $saldo=getSaldoTotal($pdo); ?>
<div class="balance" style="margin-bottom:18px">
  <div class="glow"></div>
  <div style="display:flex;justify-content:space-between;align-items:center;position:relative"><span class="lbl">TOTAL SALDO DOMPET</span><span style="font-size:18px">👛</span></div>
  <div style="font-family:var(--serif);font-size:32px;font-weight:600;margin-top:6px;position:relative"><?= rp($saldo) ?></div>
  <div style="font-size:12px;color:#aab8cc;margin-top:6px;position:relative"><?= count($dompetList) ?> dompet aktif</div>
</div>
<?php if(!$dompetList): ?>
  <div class="card" style="padding:20px;text-align:center;color:var(--soft);font-size:13.5px;margin-bottom:22px">Belum ada dompet. <a href="#" onclick="openDompet();return false" style="color:var(--terra);font-weight:700">Tambah dompet</a> dulu untuk mulai mencatat saldo.</div>
<?php else: ?>
<div class="grid-3" style="margin-bottom:22px">
  <?php foreach($dompetList as $w): ?>
    <div class="card" style="padding:16px 18px">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div class="cat" style="width:42px;height:42px;font-size:21px;background:var(--card2)"><?= $w['emoji'] ?></div>
        <div style="flex:1;min-width:0"><div style="font-size:14.5px;font-weight:700"><?= e($w['nama']) ?></div><div style="font-family:var(--serif);font-size:18px;font-weight:600;color:var(--terra)"><?= rp($w['saldo']) ?></div></div>
        <div class="card-actions">
          <button class="mini-btn" onclick='editDompet(<?= json_encode($w,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)' title="Edit"><?= icon('edit',15) ?></button>
          <form method="post" action="actions.php" data-confirm="Hapus dompet <?= e($w['nama']) ?>? Transaksi terkait tetap ada (dompetnya jadi kosong)."><input type="hidden" name="action" value="delete_dompet"><input type="hidden" name="id" value="<?= $w['id'] ?>"><input type="hidden" name="back" value="?page=anggaran"><button class="mini-btn danger" title="Hapus"><?= icon('trash',15) ?></button></form>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-dark btn-sm" style="flex:1;justify-content:center" onclick='openSaldoFor(<?= json_encode($w,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"tambah")'><?= icon('plus',13,'var(--bg)',2.5) ?> Isi</button>
        <button class="btn btn-ghost btn-sm" style="flex:1;justify-content:center" onclick='openSaldoFor(<?= json_encode($w,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"kurang")'>− Kurangi</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Transfer Antar Dompet -->
<div class="modal-bg" id="m-transfer"><div class="modal" style="max-width:400px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2>↔️ Transfer Antar Dompet</h2><button class="icon-btn" onclick="closeModal('m-transfer')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" value="transfer_saldo"><input type="hidden" name="back" value="?page=anggaran">
    <div class="field"><label>Dari Dompet</label><select name="from_id" id="tf-from"><?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?> — <?= rp($w['saldo']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Ke Dompet</label><select name="to_id" id="tf-to"><?php foreach($dompetList as $i=>$w): ?><option value="<?= $w['id'] ?>" <?= $i===1?'selected':'' ?>><?= e($w['emoji'].' '.$w['nama']) ?> — <?= rp($w['saldo']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Jumlah (Rp)</label><input type="text" name="jumlah" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" required style="font-family:var(--serif);font-size:22px;text-align:center"></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800"><?= icon('export',16,'#fff') ?> Transfer</button>
  </form>
</div></div></div>

<div class="balance" style="margin-bottom:22px">
  <div class="glow"></div>
  <span class="lbl" style="position:relative">TOTAL ANGGARAN</span>
  <div style="font-family:var(--serif);font-size:32px;font-weight:600;margin-top:6px;position:relative"><span class="cup" data-v="<?= (int)round($totPakai) ?>"><?= rp($totPakai) ?></span> <span style="font-size:15px;color:#9fb0c9;font-family:var(--sans)">/ <?= rpShort($totBatas) ?></span></div>
  <div class="prog" style="margin-top:14px;background:rgba(255,255,255,.12);height:8px;position:relative"><i style="width:<?= $totBatas?min(100,$totPakai/$totBatas*100):0 ?>%;background:var(--terra)"></i></div>
  <div style="font-size:12px;color:#aab8cc;margin-top:8px;position:relative"><?= count($aktif) ?> aman · <?= count($lewat) ?> terlampaui</div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
  <div class="eyebrow" style="margin:0">Anggaran aktif</div>
  <button class="btn btn-primary btn-sm show-mobile" onclick="openAng()"><?= icon('plus',14,'#fff',2.5) ?> Tambah</button>
</div>

<?php if(!$budgets): ?><div class="empty"><div class="ico">📊</div><div class="msg">Belum ada anggaran</div><button class="btn btn-primary" style="margin-top:16px" onclick="openAng()">Buat Anggaran</button></div><?php endif; ?>

<div class="grid-auto"><?php foreach($aktif as $b) angCard($b,$periodeOpt); ?></div>

<?php if($lewat): ?>
  <div class="eyebrow" style="margin:24px 0 14px;color:var(--red)">⚠️ Terlampaui · <?= count($lewat) ?></div>
  <div class="grid-auto"><?php foreach($lewat as $b) angCard($b,$periodeOpt); ?></div>
<?php endif; ?>

<?php if($histori): ?>
  <div class="eyebrow" style="margin:24px 0 14px;color:var(--soft)">📋 Histori (periode selesai) · <?= count($histori) ?></div>
  <div class="grid-auto"><?php foreach($histori as $b) angCard($b,$periodeOpt,true); ?></div>
<?php endif; ?>

<!-- Modal tambah/edit -->
<div class="modal-bg" id="m-ang"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="ang-title">Anggaran Baru 📊</h2><button class="icon-btn" onclick="closeModal('m-ang')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="ang-act" value="add_anggaran"><input type="hidden" name="id" id="ang-id"><input type="hidden" name="back" value="?page=anggaran">
    <div class="field"><label>Kategori (pengeluaran)</label>
      <select name="kategori" id="ang-kat"><?php foreach($kategoriKeluar as $k): ?><option value="<?= e($k['nama']) ?>"><?= $k['emoji'] ?> <?= e($k['nama']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Jumlah Anggaran (Rp)</label><input type="text" name="batas" id="ang-batas" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" required style="font-family:var(--serif)"></div>
    <div class="toggle-row">
      <span style="font-size:13.5px;font-weight:700">Atur reset &amp; batas waktu?</span>
      <label class="switch"><input type="checkbox" name="pakai_batas" id="ang-pakai" onchange="angToggleBatas()"><span class="sl"></span></label></div>
    <div style="font-size:11.5px;color:var(--soft);margin:0 2px 12px;line-height:1.6">
      <b>Nonaktif:</b> anggaran berjalan terus apa adanya.<br>
      <b>Aktif:</b> anggaran dihitung ulang dari nol secara berkala (mis. tiap bulan). Pilih <b>Selamanya</b> agar berulang terus, atau <b>Sampai tanggal</b> untuk berhenti pada tanggal tertentu (otomatis pindah ke <b>Histori</b>).</div>
    <div id="ang-sched" style="display:none"><?php jadwalField('ang',['parts'=>['freq','selesai']]); ?></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Anggaran</button>
  </form>
</div></div></div>

<!-- Modal Isi/Kurangi Saldo Dompet -->
<div class="modal-bg" id="m-saldo"><div class="modal" style="max-width:380px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="sl-title">Isi Saldo</h2><button class="icon-btn" onclick="closeModal('m-saldo')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" value="tambah_saldo"><input type="hidden" name="back" value="?page=anggaran"><input type="hidden" name="tipe" id="sl-tipe" value="tambah">
    <div class="field"><label>Dompet</label><select name="dompet_id" id="sl-dompet"><?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?> — <?= rp($w['saldo']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label id="sl-lbl">Jumlah ditambah (Rp)</label><input type="text" name="jumlah" id="sl-jml" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" required style="font-family:var(--serif);font-size:24px;text-align:center" autofocus></div>
    <button type="submit" class="btn btn-primary" id="sl-btn" style="width:100%;justify-content:center;padding:14px;font-weight:800">Isi Saldo</button>
  </form>
</div></div></div>

<!-- Modal Tambah/Edit Dompet -->
<div class="modal-bg" id="m-dompet"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="dp-title">Dompet Baru 👛</h2><button class="icon-btn" onclick="closeModal('m-dompet')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="dp-act" value="add_dompet"><input type="hidden" name="id" id="dp-id"><input type="hidden" name="back" value="?page=anggaran">
    <input type="hidden" name="emoji" data-emoji id="dp-emoji" value="💵">
    <div class="field"><label>Pilih Ikon</label><div class="emoji-pick">
      <?php foreach(['💵','🏦','📱','💳','🪙','💰','🏧','🐷'] as $i=>$em): ?><div class="ei <?= $i===0?'on':'' ?>" style="border-color:<?= $i===0?'var(--terra)':'var(--line)' ?>" onclick="pickEmoji(this.parentElement,'<?= $em ?>')"><?= $em ?></div><?php endforeach; ?>
    </div></div>
    <div class="field"><label>Nama Dompet</label><input type="text" name="nama" id="dp-nama" placeholder="Contoh: Bank BCA" required></div>
    <div class="field"><label>Saldo (Rp)</label><input type="text" name="saldo" id="dp-saldo" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" style="font-family:var(--serif)"></div>
    <div class="toggle-row">
      <span style="font-size:13.5px;font-weight:700">Auto reset dompet?</span>
      <label class="switch"><input type="checkbox" name="reset_on" id="dp-reseton" onchange="dpReset()"><span class="sl"></span></label></div>
    <div id="dp-reset-wrap" style="display:none;margin-top:8px">
      <div style="display:flex;gap:12px">
        <div class="field" style="flex:1"><label>Tanggal reset</label><select name="reset_tgl" id="dp-resettgl"><?php for($d=1;$d<=31;$d++): ?><option value="<?= $d ?>"><?= str_pad($d,2,'0',STR_PAD_LEFT) ?></option><?php endfor; ?></select></div>
        <div class="field" style="flex:1"><label>Uang baru (Rp)</label><input type="text" name="uang_baru" id="dp-uangbaru" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" style="font-family:var(--serif)"></div>
      </div>
      <div style="font-size:11.5px;color:var(--soft);margin:0 2px 12px">Saat tanggal tersebut, saldo otomatis jadi "uang baru" (mis. uang saku bulanan). Kosong = jadi 0.</div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800">Simpan Dompet</button>
  </form>
</div></div></div>

<!-- Modal Riwayat Anggaran -->
<div class="modal-bg" id="m-riwayat"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2>🕘 Riwayat Anggaran</h2><button class="icon-btn" onclick="closeModal('m-riwayat')"><?= icon('x',18) ?></button></div>
  <?php if(!$angLog): ?><div class="empty" style="padding:24px 0"><div class="msg" style="font-size:13px">Belum ada riwayat perubahan</div></div><?php endif; ?>
  <?php $aksiMap=['buat'=>['Dibuat','var(--blue)','📊'],'tambah'=>['Ditambah','var(--green)','➕'],'kurang'=>['Dikurangi','var(--red)','➖'],'edit'=>['Diubah','var(--amber)','✏️']];
  foreach($angLog as $l): $am=$aksiMap[$l['aksi']]??['Diubah','var(--soft)','•']; ?>
    <div style="display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--line)">
      <div class="cat" style="width:34px;height:34px;font-size:16px;background:var(--card2)"><?= $am[2] ?></div>
      <div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:700"><?= e($l['kategori']) ?> · <span style="color:<?= $am[1] ?>"><?= $am[0] ?></span><?= $l['jumlah']>0&&$l['aksi']!=='buat'?' '.rpShort($l['jumlah']):'' ?></div><div style="font-size:11.5px;color:var(--soft)">Batas jadi <?= rp($l['batas_baru']) ?> · <?= date('d M Y H:i',strtotime($l['created_at'])) ?></div></div>
    </div>
  <?php endforeach; ?>
</div></div></div>

<!-- Modal Tambah/Kurangi Jumlah Anggaran -->
<div class="modal-bg" id="m-adj"><div class="modal" style="max-width:380px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="aj-title">Tambah Anggaran</h2><button class="icon-btn" onclick="closeModal('m-adj')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" value="adjust_anggaran"><input type="hidden" name="back" value="?page=anggaran"><input type="hidden" name="id" id="aj-id"><input type="hidden" name="tipe" id="aj-tipe" value="tambah">
    <div id="aj-info" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--card2);border-radius:14px;margin-bottom:16px"></div>
    <div class="field"><label id="aj-lbl">Jumlah ditambah (Rp)</label><input type="text" name="jumlah" id="aj-jml" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this);previewAdj()" required style="font-family:var(--serif);font-size:24px;text-align:center" autofocus></div>
    <div id="aj-preview" style="display:none;padding:11px 14px;border-radius:12px;margin-bottom:14px;font-size:13.5px;font-weight:700;text-align:center"></div>
    <button type="submit" class="btn btn-primary" id="aj-btn" style="width:100%;justify-content:center;padding:14px;font-weight:800">Simpan</button>
  </form>
</div></div></div>

<script>
var _ajBatas=0,_ajTipe='tambah';
function previewAdj(){
  var v=Number((document.getElementById('aj-jml').value||'').replace(/\D/g,'')); var p=document.getElementById('aj-preview');
  if(!v){p.style.display='none';return;}
  var hasil=_ajTipe==='tambah'?_ajBatas+v:Math.max(0,_ajBatas-v);
  p.style.display='block'; p.style.background=_ajTipe==='tambah'?'var(--greenT)':'var(--redT)'; p.style.color=_ajTipe==='tambah'?'var(--green)':'var(--red)';
  p.innerHTML='➡️ Batas anggaran jadi <b>Rp'+hasil.toLocaleString('id-ID')+'</b>';
}
function openAdj(b,tipe){
  document.getElementById('aj-id').value=b.id; document.getElementById('aj-tipe').value=tipe; document.getElementById('aj-jml').value='';
  _ajBatas=Number(b.batas); _ajTipe=tipe; document.getElementById('aj-preview').style.display='none';
  var add=(tipe==='tambah');
  document.getElementById('aj-title').textContent=add?'➕ Tambah Anggaran '+b.kategori:'➖ Kurangi Anggaran '+b.kategori;
  document.getElementById('aj-lbl').textContent=add?'Jumlah ditambah (Rp)':'Jumlah dikurangi (Rp)';
  document.getElementById('aj-btn').textContent=add?'Tambah':'Kurangi';
  document.getElementById('aj-info').innerHTML='<div class="cat" style="width:36px;height:36px;font-size:18px;background:'+b.tint+'">'+b.emoji+'</div><div><div style="font-size:13.5px;font-weight:700">'+b.kategori+'</div><div style="font-size:12px;color:var(--soft)">Batas sekarang: Rp'+Number(b.batas).toLocaleString('id-ID')+'</div></div>';
  openModal('m-adj');
}
function angToggleBatas(){document.getElementById('ang-sched').style.display=document.getElementById('ang-pakai').checked?'':'none';}
function openAng(){document.getElementById('ang-title').textContent='Anggaran Baru 📊';document.getElementById('ang-act').value='add_anggaran';document.getElementById('ang-id').value='';document.getElementById('ang-batas').value='';document.getElementById('ang-kat').disabled=false;document.getElementById('ang-pakai').checked=false;jadwalSet('ang',{});angToggleBatas();openModal('m-ang');}
function editAng(b){document.getElementById('ang-title').textContent='Edit Anggaran';document.getElementById('ang-act').value='edit_anggaran';document.getElementById('ang-id').value=b.id;document.getElementById('ang-kat').value=b.kategori;document.getElementById('ang-kat').disabled=false;document.getElementById('ang-batas').value=Number(b.batas).toLocaleString('id-ID');var on=(b.frekuensi&&b.frekuensi!=='static');document.getElementById('ang-pakai').checked=on;jadwalSet('ang',b);angToggleBatas();openModal('m-ang');}
function dpReset(){document.getElementById('dp-reset-wrap').style.display=document.getElementById('dp-reseton').checked?'':'none';}
function openDompet(){document.getElementById('dp-title').textContent='Dompet Baru 👛';document.getElementById('dp-act').value='add_dompet';['dp-id','dp-nama','dp-saldo','dp-uangbaru'].forEach(i=>document.getElementById(i).value='');document.getElementById('dp-emoji').value='💵';document.getElementById('dp-reseton').checked=false;document.getElementById('dp-resettgl').value='1';dpReset();openModal('m-dompet');}
function editDompet(w){document.getElementById('dp-title').textContent='Edit Dompet';document.getElementById('dp-act').value='edit_dompet';document.getElementById('dp-id').value=w.id;document.getElementById('dp-nama').value=w.nama;document.getElementById('dp-saldo').value=Number(w.saldo).toLocaleString('id-ID');document.getElementById('dp-emoji').value=w.emoji;var on=(+w.reset_tgl>0);document.getElementById('dp-reseton').checked=on;document.getElementById('dp-resettgl').value=on?w.reset_tgl:'1';document.getElementById('dp-uangbaru').value=(+w.uang_baru>0)?Number(w.uang_baru).toLocaleString('id-ID'):'';dpReset();openModal('m-dompet');}
function openSaldoFor(w,tipe){
  document.getElementById('sl-dompet').value=w.id;
  document.getElementById('sl-tipe').value=tipe;
  document.getElementById('sl-jml').value='';
  var isAdd=(tipe==='tambah');
  document.getElementById('sl-title').textContent=isAdd?'💰 Isi Saldo — '+w.nama:'💸 Kurangi Saldo — '+w.nama;
  document.getElementById('sl-lbl').textContent=isAdd?'Jumlah ditambah (Rp)':'Jumlah dikurangi (Rp)';
  document.getElementById('sl-btn').textContent=isAdd?'Isi Saldo':'Kurangi Saldo';
  openModal('m-saldo');
}
</script>
