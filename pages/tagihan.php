<?php
$tagihan=getTagihan($pdo);
$dompetList=getDompet($pdo);
$filter=$_GET['fp']??'bulan'; // minggu | bulan | tahun
$today=(int)date('j');

// kelompokkan per status
$due=[]; $overdue=[]; $lunas=[];
foreach($tagihan as $b){ $st=tagihanStatus($b); if($st==='lunas')$lunas[]=$b; elseif($st==='overdue')$overdue[]=$b; else $due[]=$b; }
$totDue=array_sum(array_column($due,'jumlah'));
$totOver=array_sum(array_column($overdue,'jumlah'));
$totLunas=array_sum(array_column($lunas,'jumlah'));

// proyeksi sesuai filter
$mult=$filter==='tahun'?12:1;
$labelFilter=['minggu'=>'minggu ini','bulan'=>'bulan ini','tahun'=>'setahun'][$filter];

function billCard($b){ global $pdo,$today,$dompetList;
  $st=tagihanStatus($b); $info=tagihanInfo()[$st];
  $isCicil=($b['jenis']==='cicilan' && $b['total']>0);
  $pctC=$isCicil&&$b['total']>0?round($b['terbayar']/$b['total']*100):0;
  $sisaCicil=$b['total']-$b['terbayar'];
  $accent=$info['warna'];
  ?>
  <div class="card" style="padding:16px 18px;margin-bottom:12px;border-left:4px solid <?= $accent ?>">
    <div style="display:flex;align-items:center;gap:14px">
      <div class="cat" style="width:46px;height:46px;font-size:22px;background:<?= $b['tint'] ?>"><?= $b['emoji'] ?></div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <span style="font-size:15px;font-weight:700"><?= e($b['nama']) ?></span>
          <span class="pill" style="background:<?= $info['tint'] ?>;color:<?= $accent ?>;font-size:10px;padding:2px 8px"><?= $info['emoji'] ?> <?= $info['label'] ?></span>
          <?php if($b['berulang'] && !$isCicil): ?><span class="pill" style="background:var(--blueT);color:var(--blue);font-size:10px;padding:2px 8px">🔁 bulanan</span><?php endif; ?>
          <?php if($isCicil): ?><span class="pill" style="background:var(--purpleT);color:var(--purple);font-size:10px;padding:2px 8px">📅 cicilan<?= $b['tenor']?' '.$b['tenor'].'x':'' ?></span><?php endif; ?>
        </div>
        <div style="font-size:12.5px;color:var(--soft);margin-top:3px"><?= rp($b['jumlah']) ?><?= $isCicil?'/bln':'' ?> · jatuh tempo tgl <?= $b['tgl_jatuh_tempo'] ?><?= $b['deskripsi']?' · '.e($b['deskripsi']):'' ?></div>
        <?php if($b['catatan']): ?><div style="font-size:11.5px;color:var(--muted);margin-top:2px">📝 <?= e($b['catatan']) ?></div><?php endif; ?>
      </div>
      <div class="card-actions">
        <button class="mini-btn" onclick='editTagihan(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',15) ?></button>
        <form method="post" action="actions.php" data-confirm="Hapus tagihan <?= e($b['nama']) ?>?"><input type="hidden" name="action" value="delete_tagihan"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="?page=tagihan&fp=<?= $_GET['fp']??'bulan' ?>"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
      </div>
    </div>

    <?php if($isCicil): ?>
      <div class="prog" style="margin-top:12px"><i style="width:<?= min(100,$pctC) ?>%;background:var(--purple)"></i></div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:9px;gap:10px;flex-wrap:wrap">
        <span style="font-size:12.5px;font-weight:700"><?= rp($b['terbayar']) ?> / <?= rp($b['total']) ?> · sisa <?= rpShort(max(0,$sisaCicil)) ?></span>
        <?php if(!$b['sudah_bayar']): ?>
        <form method="post" action="actions.php" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap" data-confirm="Bayar cicilan bulan ini?" data-confirm-type="primary" data-confirm-icon="💳" data-confirm-ok="Ya, bayar">
          <input type="hidden" name="action" value="bayar_cicilan"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="?page=tagihan">
          <select name="dompet_id" required style="padding:8px 10px;border:1px solid var(--line);border-radius:10px;font-size:12px;background:var(--card);color:var(--ink);max-width:140px">
            <?php if(!$dompetList): ?><option value="">— belum ada rekening —</option><?php endif; ?>
            <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?></option><?php endforeach; ?>
          </select>
          <input type="text" name="bayar" inputmode="numeric" value="<?= number_format($b['jumlah'],0,',','.') ?>" data-total="<?= $b['total'] ?>" data-terbayar="<?= $b['terbayar'] ?>" oninput="fmtRupiah(this);previewCicil(this)" style="width:120px;padding:8px 11px;border:1px solid var(--line);border-radius:10px;font-size:12.5px;background:var(--card);color:var(--ink);outline:none">
          <button class="btn btn-primary btn-sm">Bayar</button>
          <span class="cicil-prev" style="font-size:11.5px;font-weight:700;color:var(--soft)"></span>
        </form>
        <?php else: ?><span class="pill" style="background:var(--greenT);color:var(--green)">✓ Lunas</span><?php endif; ?>
      </div>
    <?php else: ?>
      <div style="margin-top:12px">
        <?php if(!$b['sudah_bayar']): ?>
          <button class="btn btn-sm" style="width:100%;justify-content:center;padding:10px;background:<?= $info['tint'] ?>;color:<?= $accent ?>" onclick='openBayar(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            💳 Bayar tagihan
          </button>
        <?php else: ?>
          <form method="post" action="actions.php" data-confirm="Batalkan pembayaran? Uang akan dikembalikan ke rekening." data-confirm-type="primary" data-confirm-icon="↩️" data-confirm-ok="Ya, batalkan">
            <input type="hidden" name="action" value="toggle_tagihan"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="?page=tagihan&fp=<?= $_GET['fp']??'bulan' ?>">
            <button class="btn btn-sm" style="width:100%;justify-content:center;padding:10px;background:var(--greenT);color:var(--green)">
              <span style="width:8px;height:8px;border-radius:4px;background:var(--green)"></span>
              ✓ Lunas — ketuk untuk batalkan
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php }

topbar('Tagihan', count($tagihan).' tagihan & cicilan', $notifs, 'tagihan',
  '<button class="btn btn-ghost hide-mobile" onclick="openTagihan()">'.icon('plus',16,'currentColor',2.5).' Tagihan</button>');
?>

<!-- Filter periode -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap">
  <div class="seg-tabs">
    <?php foreach(['minggu'=>'Minggu','bulan'=>'Bulan','tahun'=>'Tahun'] as $k=>$l): ?>
      <a href="?page=tagihan&fp=<?= $k ?>" class="<?= $filter===$k?'on':'' ?>"><?= $l ?></a>
    <?php endforeach; ?>
  </div>
  <span style="font-size:12.5px;color:var(--soft);font-weight:600">Proyeksi <?= $labelFilter ?></span>
</div>

<!-- 3 kartu ringkasan -->
<div class="grid-3" style="margin-bottom:22px">
  <div class="card" style="padding:16px 18px;border-top:3px solid var(--amber)"><div style="font-size:12.5px;font-weight:700;color:var(--soft)">⏳ Jatuh tempo</div><div style="font-family:var(--serif);font-size:22px;font-weight:600;color:var(--amber);margin-top:6px"><?= rpShort($totDue*$mult) ?></div><div style="font-size:12px;color:var(--soft);margin-top:3px"><?= count($due) ?> tagihan</div></div>
  <div class="card" style="padding:16px 18px;border-top:3px solid var(--red)"><div style="font-size:12.5px;font-weight:700;color:var(--soft)">🔴 Lewat tempo</div><div style="font-family:var(--serif);font-size:22px;font-weight:600;color:var(--red);margin-top:6px"><?= rpShort($totOver*$mult) ?></div><div style="font-size:12px;color:var(--soft);margin-top:3px"><?= count($overdue) ?> tagihan</div></div>
  <div class="card" style="padding:16px 18px;border-top:3px solid var(--green)"><div style="font-size:12.5px;font-weight:700;color:var(--soft)">✅ Lunas</div><div style="font-family:var(--serif);font-size:22px;font-weight:600;color:var(--green);margin-top:6px"><?= count($lunas) ?></div><div style="font-size:12px;color:var(--soft);margin-top:3px"><?= rpShort($totLunas) ?></div></div>
</div>

<div style="display:flex;justify-content:flex-end;margin-bottom:14px"><button class="btn btn-primary btn-sm show-mobile" onclick="openTagihan()"><?= icon('plus',14,'#fff',2.5) ?> Tagihan</button></div>

<?php if(!$tagihan): ?><div class="empty"><div class="ico">💡</div><div class="msg">Belum ada tagihan</div><button class="btn btn-primary" style="margin-top:16px" onclick="openTagihan()">Tambah Tagihan</button></div><?php endif; ?>
<?php if($overdue): ?><div class="eyebrow" style="color:var(--red)">🔴 Lewat jatuh tempo · <?= count($overdue) ?></div><?php foreach($overdue as $b) billCard($b); ?><?php endif; ?>
<?php if($due): ?><div class="eyebrow" style="margin-top:18px;color:var(--amber)">⏳ Jatuh tempo · <?= count($due) ?></div><?php foreach($due as $b) billCard($b); ?><?php endif; ?>
<?php if($lunas): ?><div class="eyebrow" style="margin-top:18px;color:var(--green)">✅ Sudah lunas · <?= count($lunas) ?></div><?php foreach($lunas as $b) billCard($b); ?><?php endif; ?>

<!-- Modal tambah/edit tagihan -->
<div class="modal-bg" id="m-tagihan"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="tg-title">Tagihan Baru 💡</h2><button class="icon-btn" onclick="closeModal('m-tagihan')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="tg-act" value="add_tagihan"><input type="hidden" name="id" id="tg-id"><input type="hidden" name="back" value="?page=tagihan">
    <input type="hidden" name="emoji" data-emoji id="tg-emoji" value="💡"><input type="hidden" name="tint" data-tint value="#f7ecd5">
    <div class="seg" id="tg-seg">
      <input type="radio" name="jenis" id="jn-l" value="langganan" checked onchange="tgJenis('langganan')"><label for="jn-l">🔁 Langganan</label>
      <input type="radio" name="jenis" id="jn-c" value="cicilan" onchange="tgJenis('cicilan')"><label for="jn-c">📅 Cicilan</label>
    </div>
    <div class="field"><label>Ikon</label><div class="emoji-pick">
      <?php $ti=[['💡','#f7ecd5'],['📶','#e3ecf6'],['🎬','#ede4f4'],['🎵','#e4f0ea'],['💧','#e3ecf6'],['🏠','#f7e6da'],['📱','#f7ecd5'],['🚗','#f6e4e1']];
      foreach($ti as $i=>[$em,$tint]): ?><div class="ei <?= $i===0?'on':'' ?>" style="background:<?= $i===0?$tint:'var(--card)' ?>;border-color:<?= $i===0?'var(--terra)':'var(--line)' ?>" onclick="pickEmoji(this.parentElement,'<?= $em ?>','<?= $tint ?>')"><?= $em ?></div><?php endforeach; ?>
    </div></div>
    <div class="field"><label>Nama</label><input type="text" name="nama" id="tg-nama" placeholder="Contoh: IndiHome / Cicilan Motor" required></div>

    <!-- Cicilan: total hutang + tombol on/off -->
    <div id="tg-cicil" style="display:none">
      <div class="field"><label>Total hutang (Rp)</label><input type="text" name="total" id="tg-total" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this);hitungCicil()" style="font-family:var(--serif)"></div>
      <div class="toggle-row" style="margin-bottom:8px">
        <span style="font-size:13.5px;font-weight:700">Atur jadwal &amp; pengingat?</span>
        <label class="switch"><input type="checkbox" name="ingatkan" value="1" id="tg-on" onchange="tgDetailVis();hitungCicil()"><span class="sl"></span></label></div>
      <div style="font-size:11.5px;color:var(--soft);margin:0 2px 12px">Mati = cicilan bebas (tanpa pengingat). Nyala = atur auto budgeting &amp; jadwal pembayaran.</div>
    </div>

    <div id="tg-detail">
      <div class="field"><label id="tg-jlbl">Jumlah / bulan (Rp)</label><input type="text" name="jumlah" id="tg-jumlah" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this);hitungCicil()" style="font-family:var(--serif)"><div style="font-size:11px;color:var(--soft);margin-top:5px" id="tg-jhint"></div></div>
      <?php jadwalField('tg',['label'=>'Auto budgeting']); ?>
      <div class="field"><label>Catatan (opsional)</label><input type="text" name="catatan" id="tg-cat" placeholder=""></div>
      <div id="tg-hitung" style="display:none;padding:11px 14px;background:var(--purpleT);border-radius:12px;margin-bottom:14px;font-size:13px;font-weight:700;color:var(--purple)">Estimasi: —</div>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Tagihan</button>
  </form>
</div></div></div>

<!-- Modal bayar langganan -->
<div class="modal-bg" id="m-bayar"><div class="modal" style="max-width:380px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2>💳 Bayar Tagihan</h2><button class="icon-btn" onclick="closeModal('m-bayar')"><?= icon('x',18) ?></button></div>
  <div id="by-info" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--card2);border-radius:14px;margin-bottom:16px"></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" value="bayar_langganan"><input type="hidden" name="id" id="by-id"><input type="hidden" name="back" value="?page=tagihan&fp=<?= $_GET['fp']??'bulan' ?>">
    <div class="field"><label>Bayar dari rekening</label>
      <select name="dompet_id" id="by-dompet" required>
        <?php if(!$dompetList): ?><option value="">— belum ada rekening —</option><?php endif; ?>
        <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?> (<?= rp($w['saldo']) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Jumlah bayar (Rp)</label><input type="text" name="bayar" id="by-jml" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" required style="font-family:var(--serif);font-size:22px;text-align:center"></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Bayar & Tandai Lunas</button>
  </form>
</div></div></div>

<script>
function openBayar(b){
  document.getElementById('by-id').value=b.id;
  document.getElementById('by-jml').value=Number(b.jumlah).toLocaleString('id-ID');
  document.getElementById('by-info').innerHTML='<div class="cat" style="width:38px;height:38px;font-size:19px;background:'+b.tint+'">'+b.emoji+'</div><div><div style="font-size:14px;font-weight:700">'+b.nama+'</div><div style="font-size:12px;color:var(--soft)">Tagihan rutin</div></div>';
  openModal('m-bayar');
}
function previewCicil(el){
  var bayar=Number((el.value||'').replace(/\D/g,'')); var total=Number(el.dataset.total),terbayar=Number(el.dataset.terbayar);
  var sisa=Math.max(0,total-terbayar-bayar); var prev=el.parentElement.querySelector('.cicil-prev');
  if(prev) prev.innerHTML = sisa<=0 ? '✅ <span style="color:var(--green)">LUNAS setelah ini</span>' : '➡️ Sisa jadi <b>Rp'+sisa.toLocaleString('id-ID')+'</b>';
}
function tgJenis(j){
  var cic=(j==='cicilan');
  document.getElementById('tg-cicil').style.display=cic?'block':'none';
  document.getElementById('tg-hitung').style.display=cic?'block':'none';
  document.getElementById('tg-jlbl').textContent=cic?'Cicilan / bulan (Rp)':'Jumlah / bulan (Rp)';
  document.getElementById('tg-jhint').textContent=cic?'Estimasi lama lunas dihitung otomatis di bawah.':'';
  tgDetailVis(); hitungCicil();
}
function tgDetailVis(){
  var cic=document.getElementById('jn-c').checked;
  var on=document.getElementById('tg-on').checked;
  document.getElementById('tg-detail').style.display=(!cic||on)?'':'none';
}
function periodsBetween(mulai,selesai,freq){
  if(!mulai||!selesai) return 0;
  var a=new Date(mulai), b=new Date(selesai); if(b<=a) return 0;
  var days=Math.round((b-a)/86400000);
  if(freq==='harian')   return days;
  if(freq==='mingguan') return Math.floor(days/7);
  if(freq==='tahunan')  return Math.max(0,b.getFullYear()-a.getFullYear());
  var m=(b.getFullYear()-a.getFullYear())*12+(b.getMonth()-a.getMonth()); if(b.getDate()>=a.getDate())m++; return Math.max(0,m);
}
function hitungCicil(){
  var total=Number((document.getElementById('tg-total').value||'').replace(/\D/g,''));
  var jml=Number((document.getElementById('tg-jumlah').value||'').replace(/\D/g,''));
  var box=document.getElementById('tg-hitung');
  var em=document.getElementById('tg-emode'), fq=document.getElementById('tg-freq'), ml=document.getElementById('tg-mulai'), sl=document.getElementById('tg-selesai');
  var sampai=(em&&em.value==='tanggal'&&sl)?sl.value:'';
  if(total>0 && jml>0 && sampai){
    var mulai=(ml&&ml.value)?ml.value:new Date().toISOString().slice(0,10);
    var per=periodsBetween(mulai,sampai,fq?fq.value:'bulanan');
    var terbayar=jml*per, kurang=total-terbayar;
    if(kurang>0) box.innerHTML='📅 Sampai '+sampai+': '+per+'× → terbayar Rp'+terbayar.toLocaleString('id-ID')+' · sisa <b style="color:var(--red)">−Rp'+kurang.toLocaleString('id-ID')+'</b>';
    else box.innerHTML='📅 Sampai '+sampai+': '+per+'× → <b style="color:var(--green)">✅ Lunas</b> (terbayar Rp'+terbayar.toLocaleString('id-ID')+')';
  } else if(total>0 && jml>0){ var bln=Math.ceil(total/jml); box.textContent='📅 Estimasi lunas: '+bln+' bulan (Rp'+jml.toLocaleString('id-ID')+'/bln)'; }
  else box.textContent='📅 Estimasi: isi total hutang & jumlah/bulan';
}
window.afterJadwalToggle=function(p){ if(p==='tg') hitungCicil(); };
document.addEventListener('DOMContentLoaded',function(){var s=document.getElementById('tg-selesai');if(s)s.addEventListener('change',hitungCicil);});
function openTagihan(){
  document.getElementById('tg-title').textContent='Tagihan Baru 💡';document.getElementById('tg-act').value='add_tagihan';
  ['tg-id','tg-nama','tg-jumlah','tg-total','tg-cat'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('tg-on').checked=false;
  jadwalSet('tg',{});document.getElementById('jn-l').checked=true;document.getElementById('tg-seg').style.display='flex';tgJenis('langganan');
  openModal('m-tagihan');
}
function editTagihan(b){
  document.getElementById('tg-title').textContent='Edit Tagihan';document.getElementById('tg-act').value='edit_tagihan';
  document.getElementById('tg-id').value=b.id;document.getElementById('tg-nama').value=b.nama;
  document.getElementById('tg-jumlah').value=Number(b.jumlah).toLocaleString('id-ID');
  document.getElementById('tg-total').value=b.total>0?Number(b.total).toLocaleString('id-ID'):'';
  document.getElementById('tg-cat').value=b.catatan||'';
  document.getElementById('tg-emoji').value=b.emoji; jadwalSet('tg',b);
  var c=(b.jenis==='cicilan'); document.getElementById('jn-c').checked=c;document.getElementById('jn-l').checked=!c;
  document.getElementById('tg-on').checked=(b.ingatkan==1);
  document.getElementById('tg-seg').style.display='flex'; tgJenis(b.jenis||'langganan');
  openModal('m-tagihan');
}
</script>
