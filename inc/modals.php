<?php
// Modal global: Tambah Transaksi + Notifikasi
$katKeluar = getKategori($pdo,'keluar');
$dompetList = getDompet($pdo);
$backUrl = '?page='.$page.( $page==='kalender' ? '' : '');
?>

<script>
// ── Grafik tren bersama (Dashboard & Analisa) ──
function _rp(n){return 'Rp'+Math.round(n).toLocaleString('id-ID');}
function pickPt(si,j){ if(typeof TSERIES==='undefined')return; var s=TSERIES[si]; if(!s)return;
  document.getElementById('line-tip').innerHTML='<span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:'+s.warna+';margin-right:6px"></span><b style="color:'+s.warna+'">'+s.kat+'</b> · '+TLABELS[j]+' : '+_rp(s.data[j]);
}
function toggleLine(i){
  var pl=document.querySelector('.tl-'+i); var hide = pl ? (pl.style.display!=='none') : true;
  document.querySelectorAll('.tl-'+i+',.tc-'+i+',.bar-'+i).forEach(function(e){e.style.display=hide?'none':'';});
  var chip=document.querySelector('.tleg[data-i="'+i+'"]'); if(chip)chip.classList.toggle('off',hide);
}
function chartMode(m){
  try{localStorage.setItem('chartMode',m);}catch(e){}
  document.querySelectorAll('.chart-line').forEach(g=>g.style.display=m==='line'?'':'none');
  document.querySelectorAll('.chart-bar').forEach(g=>g.style.display=m==='bar'?'':'none');
  document.querySelectorAll('.ct-line').forEach(b=>b.classList.toggle('on',m==='line'));
  document.querySelectorAll('.ct-bar').forEach(b=>b.classList.toggle('on',m==='bar'));
}
// pulihkan pilihan Garis/Batang setelah ganti periode/filter (reload halaman)
document.addEventListener('DOMContentLoaded',function(){ try{ if(localStorage.getItem('chartMode')==='bar' && document.querySelector('.chart-bar')) chartMode('bar'); }catch(e){} });
// ── Komponen JADWAL terpadu: tampilkan kolom samping sesuai frekuensi & selesai ──
function jadwalToggle(p){
  var fe=document.getElementById(p+'-freq'), eme=document.getElementById(p+'-emode');
  var f=fe?fe.value:'', em=eme?eme.value:'';
  var setw=function(s,on){var el=document.getElementById(p+'-'+s+'-wrap'); if(el)el.style.display=on?'':'none';};
  setw('hari',    f==='mingguan');
  setw('bulan',   f==='tahunan');
  setw('tgl',     f==='bulanan'||f==='tahunan');
  setw('selesai', em==='tanggal');
  var hints={harian:'Diingatkan setiap hari.',mingguan:'Diingatkan setiap minggu pada hari terpilih.',
    bulanan:'Diingatkan tiap bulan pada tanggal terpilih.',tahunan:'Diingatkan sekali setahun pada tanggal & bulan terpilih.'};
  var h=document.getElementById(p+'-freqhint'); if(h)h.textContent=hints[f]||'';
  if(typeof window.afterJadwalToggle==='function') window.afterJadwalToggle(p);
}
// Isi nilai jadwal saat edit. d = {mulai_tgl,ingatkan,frekuensi,freq_hari,freq_tgl,freq_bulan,selesai_tgl}
function jadwalSet(p,d){
  d=d||{};
  var v=function(s,val){var el=document.getElementById(p+'-'+s); if(el&&val!=null&&val!=='')el.value=val;};
  var setSel=function(s,val){var el=document.getElementById(p+'-'+s); if(el)el.value=val;};
  if(document.getElementById(p+'-mulai')) document.getElementById(p+'-mulai').value=d.mulai_tgl||new Date().toISOString().slice(0,10);
  var freqs=['harian','mingguan','bulanan','tahunan'];
  setSel('freq', (freqs.indexOf(d.frekuensi)>=0?d.frekuensi:'bulanan'));
  v('hari', d.freq_hari); v('tgl', d.freq_tgl); v('bulan', d.freq_bulan);
  setSel('emode', d.selesai_tgl ? 'tanggal' : 'selamanya');
  setSel('selesai', d.selesai_tgl||'');
  jadwalToggle(p);
}
</script>

<!-- MODAL: Tambah Transaksi -->
<div class="modal-bg" id="m-tx">
  <div class="modal"><div class="grip"></div><div class="mbody">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2>Tambah Transaksi</h2>
      <button class="icon-btn" onclick="closeModal('m-tx')"><?= icon('x',18) ?></button>
    </div>
    <form method="post" action="actions.php">
      <input type="hidden" name="action" value="add_transaksi">
      <input type="hidden" name="back" value="?page=<?= $page ?>">
      <div class="seg">
        <input type="radio" name="tipe" id="tp-k" value="keluar" checked><label for="tp-k">💸 Pengeluaran</label>
        <input type="radio" name="tipe" id="tp-m" value="masuk"><label for="tp-m">💰 Pemasukan</label>
      </div>
      <div class="field"><label>Nama Transaksi</label><input type="text" name="judul" placeholder="Contoh: Makan siang" required></div>
      <div class="field"><label>Jumlah (Rp)</label><input type="text" name="jumlah" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" required style="font-family:var(--serif);font-size:24px;text-align:center"></div>
      <div class="field" id="kat-wrap"><label>Kategori</label>
        <div class="catgrid">
          <?php foreach($katKeluar as $i=>$k): ?>
            <label class="catpick">
              <input type="radio" name="kategori" value="<?= e($k['nama']) ?>" <?= $i===0?'checked':'' ?>>
              <span class="box" style="background:<?= $k['tint'] ?>"><?= $k['emoji'] ?></span>
              <span class="nm"><?= e($k['nama']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="display:flex;gap:12px">
        <div class="field" style="flex:1"><label>Dompet</label><select name="dompet_id">
          <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field" style="flex:1"><label>Tanggal</label><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <label class="toggle-row" style="cursor:pointer">
        <span style="font-size:13.5px;font-weight:700">🔁 Ulangi otomatis</span>
        <span class="switch"><input type="checkbox" name="rutin" value="1" id="tx-rutin" onchange="document.getElementById('tx-rutin-freq').style.display=this.checked?'block':'none'"><span class="sl"></span></span>
      </label>
      <div class="field" id="tx-rutin-freq" style="display:none;margin-top:8px"><label>Frekuensi ulang</label>
        <select name="rutin_freq"><option value="harian">Tiap hari</option><option value="mingguan">Tiap minggu</option><option value="bulanan" selected>Tiap bulan</option><option value="tahunan">Tiap tahun</option></select>
        <div style="font-size:11px;color:var(--soft);margin-top:5px">Transaksi ini akan dicatat otomatis tiap periode (mis. tagihan/gaji rutin).</div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Transaksi</button>
    </form>
  </div></div>
</div>

<!-- MODAL: Notifikasi -->
<div class="modal-bg" id="m-notif">
  <div class="modal"><div class="grip"></div><div class="mbody">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h2>🔔 Notifikasi</h2>
      <div style="display:flex;gap:8px">
        <?php if($notifs): ?>
        <form method="post" action="actions.php" style="display:inline">
          <input type="hidden" name="action" value="mark_all_notif">
          <input type="hidden" name="back" value="?page=<?= $page ?>">
          <button type="submit" class="btn btn-ghost btn-sm">✓ Baca semua</button>
        </form>
        <?php endif; ?>
        <button class="icon-btn" onclick="closeModal('m-notif')"><?= icon('x',18) ?></button>
      </div>
    </div>
    <?php if(!$notifs): ?>
      <div class="empty" style="padding:30px 0"><div class="ico">🎉</div><div class="msg" style="font-size:13px">Tidak ada notifikasi</div></div>
    <?php endif; ?>
    <?php foreach($notifs as $n): $rd=!empty($n['dibaca']); ?>
      <div class="card" style="display:flex;gap:12px;padding:13px 14px;margin-bottom:10px;align-items:flex-start;<?= $rd?'opacity:.55':'' ?>">
        <div class="cat" style="width:40px;height:40px;font-size:20px;background:<?= $rd?'var(--card2)':$n['tint'] ?>"><?= $rd?'✓':$n['emoji'] ?></div>
        <a href="?page=<?= $n['go'] ?>" style="flex:1">
          <div style="font-size:14px;font-weight:700;line-height:1.3;color:<?= $rd?'var(--soft)':'var(--ink)' ?>"><?= e($n['title']) ?></div>
          <div style="font-size:12.5px;color:var(--soft);margin-top:3px"><?= e($n['sub']) ?></div>
        </a>
        <?php if(!$rd): ?>
        <form method="post" action="actions.php">
          <input type="hidden" name="action" value="mark_notif"><input type="hidden" name="key" value="<?= e($n['key']) ?>"><input type="hidden" name="back" value="?page=<?= $page ?>">
          <button type="submit" class="mini-btn" title="Tandai dibaca"><?= icon('check',15) ?></button>
        </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div></div>
</div>
