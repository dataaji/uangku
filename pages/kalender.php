<?php
$cb=(int)($_GET['cb']??date('n')); $cy=(int)($_GET['cy']??date('Y'));
$selHari=(int)($_GET['hari']??date('j'));
$events=getCalEvents($pdo,$cb,$cy);
$firstDow=(int)date('w',mktime(0,0,0,$cb,1,$cy)); $daysIn=(int)date('t',mktime(0,0,0,$cb,1,$cy));
$selHari=min(max(1,$selHari),$daysIn);
$selTgl=sprintf('%04d-%02d-%02d',$cy,$cb,$selHari);
$backUrl="?page=kalender&cb=$cb&cy=$cy&hari=$selHari";

// Data per hari, dipisah jenis
$tugasHari=array_values(array_filter(getTugas($pdo),fn($t)=>$t['tanggal']===$selTgl));
$catatanHari=getCatatanByDate($pdo,$selTgl);
// Info otomatis (transaksi + tagihan) — read only, klik→halaman
$infoHari=[];
$s=$pdo->prepare('SELECT t.*,d.nama dompet_nama FROM transaksi t LEFT JOIN dompet d ON t.dompet_id=d.id WHERE t.tanggal=?'); $s->execute([$selTgl]);
foreach($s->fetchAll() as $t) $infoHari[]=['c'=>$t['jumlah']>0?'var(--green)':'var(--red)','emoji'=>$t['emoji']?:'💰','title'=>$t['judul'],'sub'=>rp(abs($t['jumlah'])).($t['dompet_nama']?' · '.$t['dompet_nama']:''),'kind'=>$t['jumlah']>0?'Masuk':'Keluar','go'=>'transaksi'];
if($cb==(int)date('n')&&$cy==(int)date('Y')) foreach(getTagihan($pdo) as $b) if($b['tgl_jatuh_tempo']==$selHari) $infoHari[]=['c'=>'var(--blue)','emoji'=>$b['emoji'],'title'=>$b['nama'].' jatuh tempo','sub'=>rp($b['jumlah']),'kind'=>'Tagihan','go'=>'tagihan'];

$prevB=$cb-1;$prevY=$cy;if($prevB<1){$prevB=12;$prevY--;}
$nextB=$cb+1;$nextY=$cy;if($nextB>12){$nextB=1;$nextY++;}
$isCur=($cb==(int)date('n')&&$cy==(int)date('Y')); $today=(int)date('j');
$PRIO=['tinggi'=>'var(--red)','sedang'=>'var(--amber)','rendah'=>'var(--green)'];

$pickB='<select onchange="location=this.value" class="picker">'; for($m=1;$m<=12;$m++)$pickB.='<option value="?page=kalender&cb='.$m.'&cy='.$cy.'&hari=1" '.($m==$cb?'selected':'').'>'.$NAMA_BULAN[$m].'</option>'; $pickB.='</select>';
$pickY='<select onchange="location=this.value" class="picker">'; for($y=date('Y')-5;$y<=date('Y')+3;$y++)$pickY.='<option value="?page=kalender&cb='.$cb.'&cy='.$y.'&hari=1" '.($y==$cy?'selected':'').'>'.$y.'</option>'; $pickY.='</select>';

topbar('Kalender & Agenda', 'Kerjaan, catatan & jadwal keuangan', $notifs, 'kalender');
?>
<style>.picker{padding:9px 12px;border:1px solid var(--line);border-radius:11px;background:var(--card);font-family:var(--sans);font-weight:700;font-size:13.5px;color:var(--ink);cursor:pointer;outline:none}
.sec-head{display:flex;justify-content:space-between;align-items:center;margin:18px 0 10px}
.sec-head .lbl{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;letter-spacing:.5px;text-transform:uppercase}</style>

<div style="display:flex;gap:10px;align-items:center;margin-bottom:18px;flex-wrap:wrap">
  <a href="?page=kalender&cb=<?= $prevB ?>&cy=<?= $prevY ?>&hari=1" class="icon-btn"><?= icon('chevL',18) ?></a>
  <?= $pickB ?><?= $pickY ?>
  <a href="?page=kalender&cb=<?= $nextB ?>&cy=<?= $nextY ?>&hari=1" class="icon-btn"><?= icon('chevR',18) ?></a>
  <a href="?page=kalender&cb=<?= date('n') ?>&cy=<?= date('Y') ?>&hari=<?= date('j') ?>" class="btn btn-ghost btn-sm">Hari ini</a>
  <div style="flex:1"></div>
  <button class="btn btn-ghost btn-sm" onclick="openTugas()"><?= icon('task',14) ?> Kerjaan</button>
  <button class="btn btn-primary btn-sm" onclick="openCatatan()"><?= icon('note',14,'#fff') ?> Catatan</button>
</div>

<div class="grid-fit">
  <!-- Kalender -->
  <div class="card" style="padding:18px;height:fit-content">
    <div class="cal-head"><?php foreach(['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $d): ?><span><?= $d ?></span><?php endforeach; ?></div>
    <div class="cal-grid">
      <?php for($i=0;$i<$firstDow;$i++): ?><div></div><?php endfor; ?>
      <?php for($d=1;$d<=$daysIn;$d++): $ev=$events[$d]??[]; $isSel=($d==$selHari); $isTd=($isCur&&$d==$today); $cls='cal-cell'; if($isSel)$cls.=' sel'; elseif($isTd)$cls.=' today'; ?>
        <a href="?page=kalender&cb=<?= $cb ?>&cy=<?= $cy ?>&hari=<?= $d ?>" class="<?= $cls ?>">
          <span style="font-size:14px;font-weight:<?= $isSel||$isTd?'700':'400' ?>"><?= $d ?></span>
          <div class="dot"><?php foreach(array_slice($ev,0,4) as $c): ?><i style="background:<?= $isSel?'#fff':$CAL_COLORS[$c] ?>"></i><?php endforeach; ?></div>
        </a>
      <?php endfor; ?>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:6px 16px;justify-content:center;margin-top:16px">
      <?php foreach(['Masuk'=>'green','Keluar'=>'red','Kerjaan'=>'amber','Tagihan'=>'blue','Catatan'=>'purple'] as $l=>$c): ?>
        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;color:var(--soft)"><span style="width:8px;height:8px;border-radius:4px;background:<?= $CAL_COLORS[$c] ?>"></span><?= $l ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Detail hari: Kerjaan / Catatan / Info -->
  <div>
    <div style="font-family:var(--serif);font-size:19px;font-weight:600;margin-bottom:4px"><?= tglIndo($selTgl) ?></div>

    <!-- KERJAAN -->
    <div class="sec-head"><span class="lbl" style="color:var(--amber)"><?= icon('task',16,'var(--amber)') ?> Kerjaan · <?= count($tugasHari) ?></span>
      <button class="btn btn-ghost btn-sm" onclick="openTugas()"><?= icon('plus',13,'currentColor',2.5) ?></button></div>
    <?php if(!$tugasHari): ?><div style="font-size:12.5px;color:var(--muted);padding:4px 2px 8px">Belum ada kerjaan. Catat apa yang mau dikerjakan + pengingatnya.</div><?php endif; ?>
    <?php foreach($tugasHari as $t): $pc=$PRIO[$t['prioritas']]??'var(--amber)'; ?>
      <div class="card" style="display:flex;align-items:flex-start;gap:12px;padding:13px 14px;margin-bottom:9px;<?= $t['selesai']?'opacity:.55':'' ?>">
        <form method="post" action="actions.php"><input type="hidden" name="action" value="toggle_tugas"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="back" value="<?= e($backUrl) ?>">
          <button style="width:24px;height:24px;border-radius:8px;border:2px solid <?= $t['selesai']?'var(--green)':'var(--line)' ?>;background:<?= $t['selesai']?'var(--green)':'transparent' ?>;display:flex;align-items:center;justify-content:center;margin-top:1px"><?= $t['selesai']?icon('check',14,'#fff',3):'' ?></button>
        </form>
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:7px"><span style="width:8px;height:8px;border-radius:4px;background:<?= $pc ?>"></span><span style="font-size:14px;font-weight:700;<?= $t['selesai']?'text-decoration:line-through':'' ?>"><?= e($t['judul']) ?></span></div>
          <?php if($t['catatan']): ?><div style="font-size:12px;color:var(--soft);margin-top:4px;margin-left:15px"><?= e($t['catatan']) ?></div><?php endif; ?>
          <?php if($t['waktu']): ?><div style="margin-top:6px;margin-left:15px"><span class="pill" style="background:var(--amberT);color:var(--amber);font-size:11px"><?= icon('clock',11,'var(--amber)') ?><?= e($t['waktu']) ?> · pengingat</span></div><?php endif; ?>
        </div>
        <div class="card-actions">
          <button class="mini-btn" onclick='editTugas(<?= json_encode($t,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',14) ?></button>
          <form method="post" action="actions.php" data-confirm="Hapus kerjaan ini?"><input type="hidden" name="action" value="delete_tugas"><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="back" value="<?= e($backUrl) ?>"><button class="mini-btn danger"><?= icon('trash',14) ?></button></form>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- CATATAN -->
    <div class="sec-head"><span class="lbl" style="color:var(--purple)"><?= icon('note',16,'var(--purple)') ?> Catatan · <?= count($catatanHari) ?></span>
      <button class="btn btn-ghost btn-sm" onclick="openCatatan()"><?= icon('plus',13,'currentColor',2.5) ?></button></div>
    <?php if(!$catatanHari): ?><div style="font-size:12.5px;color:var(--muted);padding:4px 2px 8px">Belum ada catatan untuk tanggal ini.</div><?php endif; ?>
    <?php foreach($catatanHari as $c): ?>
      <div class="card" style="display:flex;align-items:flex-start;gap:12px;padding:13px 14px;margin-bottom:9px">
        <div class="cat" style="width:34px;height:34px;font-size:17px;background:var(--purpleT)">📝</div>
        <div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:700"><?= e($c['judul']) ?></div><?php if($c['isi']): ?><div style="font-size:12px;color:var(--soft);margin-top:3px"><?= e($c['isi']) ?></div><?php endif; ?>
          <?php $ulLbl=['tahunan'=>'🔁 Tiap tahun','bulanan'=>'🔁 Tiap bulan','mingguan'=>'🔁 Tiap minggu']; $leadLbl=['hari'=>'H-1','minggu'=>'H-7','bulan'=>'H-30']; ?>
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:5px">
            <?php if(($c['ulang']??'tidak')!=='tidak'): ?><span class="pill" style="background:var(--purpleT);color:var(--purple);font-size:10px;padding:2px 8px"><?= $ulLbl[$c['ulang']] ?></span><?php endif; ?>
            <?php if($c['ingatkan']): ?><span class="pill" style="background:var(--card2);color:var(--soft);font-size:10px;padding:2px 8px">🔔 <?= $leadLbl[$c['ingat_lead']??'hari']??'H-1' ?></span><?php endif; ?>
          </div></div>
        <div class="card-actions">
          <button class="mini-btn" onclick='editCatatan(<?= json_encode($c,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',14) ?></button>
          <form method="post" action="actions.php" data-confirm="Hapus catatan?"><input type="hidden" name="action" value="delete_catatan"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input type="hidden" name="back" value="<?= e($backUrl) ?>"><button class="mini-btn danger"><?= icon('trash',14) ?></button></form>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- INFO LAINNYA -->
    <?php if($infoHari): ?>
      <div class="sec-head"><span class="lbl" style="color:var(--soft)">ℹ️ Info Keuangan · <?= count($infoHari) ?></span></div>
      <?php foreach($infoHari as $a): ?>
        <a href="?page=<?= $a['go'] ?>" class="card" style="display:flex;align-items:center;gap:12px;padding:12px 14px;margin-bottom:9px">
          <span style="width:4px;align-self:stretch;border-radius:3px;background:<?= $a['c'] ?>"></span>
          <div class="cat" style="width:34px;height:34px;font-size:17px;background:var(--card2)"><?= $a['emoji'] ?></div>
          <div style="flex:1;min-width:0"><div style="font-size:13.5px;font-weight:600"><?= e($a['title']) ?></div><div style="font-size:11.5px;color:var(--soft)"><?= e($a['sub']) ?></div></div>
          <span style="font-size:9.5px;font-weight:800;color:<?= $a['c'] ?>;text-transform:uppercase"><?= $a['kind'] ?></span>
          <?= icon('chevR',15,'var(--muted)') ?>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Kerjaan -->
<div class="modal-bg" id="m-task"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="tk-title">Kerjaan Baru ✅</h2><button class="icon-btn" onclick="closeModal('m-task')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="tk-act" value="add_tugas"><input type="hidden" name="id" id="tk-id"><input type="hidden" name="back" value="<?= e($backUrl) ?>">
    <div class="field"><label>Yang akan dikerjakan</label><input type="text" name="judul" id="tk-judul" placeholder="Contoh: Bayar SPP anak" required></div>
    <div class="field"><label>Keterangan (boleh panjang)</label><textarea name="catatan" id="tk-cat" rows="3" placeholder="Tulis penjelasan lengkap di sini kalau kerjaan butuh detail panjang..."></textarea></div>
    <div style="display:flex;gap:12px">
      <div class="field" style="flex:1"><label>Tanggal</label><input type="date" name="tanggal" id="tk-tgl" value="<?= $selTgl ?>"></div>
      <div class="field" style="flex:1"><label>Jam pengingat</label><input type="time" name="waktu" id="tk-waktu"></div>
    </div>
    <div class="field"><label>Prioritas</label><select name="prioritas" id="tk-prio"><option value="tinggi">🔴 Tinggi</option><option value="sedang" selected>🟡 Sedang</option><option value="rendah">🟢 Rendah</option></select></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Kerjaan</button>
  </form>
</div></div></div>

<!-- Modal Catatan -->
<div class="modal-bg" id="m-catatan"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="ct-title">Catatan Baru 📝</h2><button class="icon-btn" onclick="closeModal('m-catatan')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="ct-act" value="add_catatan"><input type="hidden" name="id" id="ct-id"><input type="hidden" name="back" value="<?= e($backUrl) ?>">
    <div class="field"><label>Tanggal</label><input type="date" name="tanggal" id="ct-tgl" value="<?= $selTgl ?>" required></div>
    <div class="field"><label>Judul</label><input type="text" name="judul" id="ct-judul" placeholder="Contoh: Cek promo cashback" required></div>
    <div class="field"><label>Isi (opsional)</label><textarea name="isi" id="ct-isi" placeholder="Detail catatan..."></textarea></div>
    <div style="display:flex;gap:12px">
      <div class="field" style="flex:1"><label>Ulangi</label>
        <select name="ulang" id="ct-ulang">
          <option value="tidak">Tidak diulang</option>
          <option value="tahunan">Tiap tahun (mis. ultah)</option>
          <option value="bulanan">Tiap bulan</option>
          <option value="mingguan">Tiap minggu</option>
        </select></div>
      <div class="field" style="flex:1"><label>Ingatkan sebelumnya</label>
        <select name="ingat_lead" id="ct-lead">
          <option value="hari" selected>1 hari sebelum</option>
          <option value="minggu">1 minggu sebelum</option>
          <option value="bulan">1 bulan sebelum</option>
        </select></div>
    </div>
    <label style="display:flex;align-items:center;gap:10px;font-size:13.5px;font-weight:600;color:var(--soft);margin-bottom:16px;cursor:pointer"><input type="checkbox" name="ingatkan" id="ct-ingat" checked style="width:18px;height:18px;accent-color:var(--terra)"> Ingatkan saya (notifikasi)</label>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Catatan</button>
  </form>
</div></div></div>

<script>
function openTugas(){document.getElementById('tk-title').textContent='Kerjaan Baru ✅';document.getElementById('tk-act').value='add_tugas';['tk-id','tk-judul','tk-cat','tk-waktu'].forEach(i=>document.getElementById(i).value='');document.getElementById('tk-tgl').value='<?= $selTgl ?>';document.getElementById('tk-prio').value='sedang';openModal('m-task');}
function editTugas(t){document.getElementById('tk-title').textContent='Edit Kerjaan';document.getElementById('tk-act').value='edit_tugas';document.getElementById('tk-id').value=t.id;document.getElementById('tk-judul').value=t.judul;document.getElementById('tk-cat').value=t.catatan||'';document.getElementById('tk-tgl').value=t.tanggal||'';document.getElementById('tk-waktu').value=(t.waktu||'').replace('.',':');document.getElementById('tk-prio').value=t.prioritas;openModal('m-task');}
function openCatatan(){document.getElementById('ct-title').textContent='Catatan Baru 📝';document.getElementById('ct-act').value='add_catatan';['ct-id','ct-judul','ct-isi'].forEach(i=>document.getElementById(i).value='');document.getElementById('ct-tgl').value='<?= $selTgl ?>';document.getElementById('ct-ingat').checked=true;document.getElementById('ct-ulang').value='tidak';document.getElementById('ct-lead').value='hari';openModal('m-catatan');}
function editCatatan(c){document.getElementById('ct-title').textContent='Edit Catatan';document.getElementById('ct-act').value='edit_catatan';document.getElementById('ct-id').value=c.id;document.getElementById('ct-judul').value=c.judul;document.getElementById('ct-isi').value=c.isi||'';document.getElementById('ct-tgl').value=c.tanggal;document.getElementById('ct-ingat').checked=c.ingatkan==1;document.getElementById('ct-ulang').value=c.ulang||'tidak';document.getElementById('ct-lead').value=c.ingat_lead||'hari';openModal('m-catatan');}
</script>
