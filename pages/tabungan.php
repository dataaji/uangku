<?php
$goals=getTabungan($pdo);
$dompetList=getDompet($pdo);
$totSaved=array_sum(array_column($goals,'terkumpul'));
$totTarget=array_sum(array_column($goals,'target'));

function goalState($g){
    $pct=$g['target']>0?$g['terkumpul']/$g['target']*100:0;
    $achieved=$pct>=100;
    $note='';
    // Border: berdasarkan SISA WAKTU ke target_tanggal. Tanpa tanggal → hijau (menabung bebas)
    if($achieved){ $border='var(--green)'; }
    elseif(!empty($g['target_tanggal'])){
        $days=floor((strtotime($g['target_tanggal'])-strtotime('today'))/86400);
        if($days<0)        $border='var(--red)';      // lewat tanggal
        elseif($days<=14)  $border='var(--red)';      // dekat target
        elseif($days<=45)  $border='var(--amber)';    // sebentar lagi
        else               $border='var(--green)';    // masih lama
    } else { $border='var(--green)'; }
    // Pengingat aktif tapi belum menabung bulan ini → border biru + catatan
    if(!$achieved && !empty($g['ingat_tgl']) && $g['ingat_tgl']>0){
        $setorBulanIni = !empty($g['terakhir_setor']) && date('Y-m',strtotime($g['terakhir_setor']))===date('Y-m');
        if(!$setorBulanIni && (int)date('j')>=$g['ingat_tgl']){
            $border='var(--blue)';
            $note = !empty($g['terakhir_setor']) ? 'Belum menabung bulan ini (terakhir setor '.tglIndo($g['terakhir_setor'],false).')' : 'Belum pernah menabung — yuk mulai!';
        }
    }
    // badge progress
    $overdue=!$achieved && !empty($g['target_tanggal']) && $g['target_tanggal']<date('Y-m-d');
    if($achieved)   $b=['warna'=>'var(--green)','badge'=>'✅ Tercapai','bg'=>'var(--greenT)'];
    elseif($overdue)$b=['warna'=>'var(--red)','badge'=>'⏰ Lewat tanggal','bg'=>'var(--redT)'];
    elseif($pct>=50)$b=['warna'=>'var(--blue)','badge'=>'🔵 Setengah jalan','bg'=>'var(--blueT)'];
    else            $b=['warna'=>'var(--muted)','badge'=>'⚪ Baru mulai','bg'=>'var(--card2)'];
    return $b+['pct'=>$pct,'border'=>$border,'note'=>$note];
}

topbar('Tabungan', count($goals).' target aktif', $notifs, 'tabungan',
  '<button class="btn btn-ghost hide-mobile" onclick="openGoal()">'.icon('plus',16,'currentColor',2.5).' Target</button>');
?>

<div class="balance" style="margin-bottom:22px">
  <div class="glow" style="background:radial-gradient(circle,rgba(22,160,107,.4),transparent 70%)"></div>
  <span class="lbl" style="position:relative">TOTAL TERKUMPUL 🐷</span>
  <div style="font-family:var(--serif);font-size:34px;font-weight:600;margin-top:6px;position:relative"><span class="cup" data-v="<?= (int)round($totSaved) ?>"><?= rp($totSaved) ?></span></div>
  <div style="font-size:13px;color:#aab8cc;margin-top:4px;position:relative">dari total target <?= rp($totTarget) ?></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
  <div class="eyebrow" style="margin:0">Target tabungan</div>
  <button class="btn btn-primary btn-sm show-mobile" onclick="openGoal()"><?= icon('plus',14,'#fff',2.5) ?> Target</button>
</div>

<?php if(!$goals): ?><div class="empty"><div class="ico">🐷</div><div class="msg">Belum ada target</div><button class="btn btn-primary" style="margin-top:16px" onclick="openGoal()">Buat Target</button></div><?php endif; ?>

<div class="grid-auto">
  <?php foreach($goals as $g): $st=goalState($g); ?>
    <div class="card" style="padding:20px;border:2px solid <?= $st['border'] ?>;box-shadow:0 0 0 4px <?= $st['border'] ?>14">
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px">
        <div class="cat" style="width:50px;height:50px;border-radius:16px;font-size:26px;background:<?= $g['tint'] ?>"><?= $g['emoji'] ?></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:16px;font-weight:700"><?= e($g['judul']) ?></div>
          <div style="font-size:12px;color:var(--soft);margin-top:3px">🎯 <?= rp($g['target']) ?><?php if($g['target_tanggal']): ?> · 📅 <?= tglIndo($g['target_tanggal'],false) ?><?php endif; ?><?php if(!empty($g['ingat_tgl'])): ?> · 🔔 tgl <?= $g['ingat_tgl'] ?><?php endif; ?></div>
        </div>
        <div class="card-actions">
          <button class="mini-btn" onclick='editGoal(<?= json_encode($g,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',15) ?></button>
          <form method="post" action="actions.php" data-confirm="Hapus target <?= e($g['judul']) ?>?"><input type="hidden" name="action" value="delete_tabungan"><input type="hidden" name="id" value="<?= $g['id'] ?>"><input type="hidden" name="back" value="?page=tabungan"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <span class="pill" style="background:<?= $st['bg'] ?>;color:<?= $st['warna'] ?>;font-size:11px"><?= $st['badge'] ?></span>
        <span style="font-family:var(--serif);font-size:18px;font-weight:600;color:<?= $st['warna'] ?>"><?= round($st['pct']) ?>%</span>
      </div>
      <div class="prog" style="height:11px"><i style="width:<?= min(100,$st['pct']) ?>%;background:<?= $st['warna'] ?>"></i></div>
      <div style="display:flex;justify-content:space-between;margin-top:10px">
        <span style="font-size:14px;font-weight:700"><?= rp($g['terkumpul']) ?></span>
        <span style="font-size:13px;color:var(--soft)"><?= $st['pct']>=100?'tercapai 🎉':'kurang '.rpShort(max(0,$g['target']-$g['terkumpul'])) ?></span>
      </div>
      <?php if($st['note']): ?><div style="margin-top:12px;padding:10px 14px;background:var(--blueT);border-radius:12px;display:flex;gap:10px;align-items:center"><span style="font-size:15px">🔔</span><span style="font-size:12.5px;font-weight:700;color:var(--blue)"><?= e($st['note']) ?></span></div><?php endif; ?>
      <?php if($g['catatan']): ?><div style="margin-top:12px;padding:10px 14px;background:var(--card2);border-radius:12px;display:flex;gap:10px;align-items:center"><span style="font-size:15px">📈</span><span style="font-size:12.5px;font-weight:600;color:var(--soft)"><?= e($g['catatan']) ?></span></div><?php endif; ?>
      <div style="display:flex;gap:8px;margin-top:14px">
        <button class="btn btn-dark btn-sm" style="flex:1;justify-content:center" onclick='openDana(<?= json_encode($g,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"setor")'><?= icon('plus',14,'var(--bg)',2.5) ?> Setor</button>
        <button class="btn btn-ghost btn-sm" style="flex:1;justify-content:center" onclick='openDana(<?= json_encode($g,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"tarik")'><?= icon('arrowDn',14) ?> Tarik</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal kelola dana -->
<div class="modal-bg" id="m-dana"><div class="modal" style="max-width:380px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="dn-title">Setor Dana</h2><button class="icon-btn" onclick="closeModal('m-dana')"><?= icon('x',18) ?></button></div>
  <div id="dn-goal" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--card2);border-radius:14px;margin-bottom:16px"></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="dn-act"><input type="hidden" name="id" id="dn-id"><input type="hidden" name="back" value="?page=tabungan">
    <div class="field"><label id="dn-wlbl">Dari rekening</label>
      <select name="dompet_id" id="dn-dompet" required>
        <?php if(!$dompetList): ?><option value="">— belum ada rekening —</option><?php endif; ?>
        <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?> (<?= rp($w['saldo']) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Jumlah (Rp)</label><input type="text" name="dana" id="dn-dana" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this);previewDana()" required style="font-family:var(--serif);font-size:26px;text-align:center" autofocus></div>
    <div id="dn-preview" style="display:none;padding:11px 14px;background:var(--greenT);border-radius:12px;margin-bottom:14px;font-size:13.5px;font-weight:700;text-align:center"></div>
    <button type="submit" class="btn btn-primary" id="dn-btn" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan</button>
  </form>
</div></div></div>

<!-- Modal tambah/edit target -->
<div class="modal-bg" id="m-goal"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="goal-title">Target Baru 🎯</h2><button class="icon-btn" onclick="closeModal('m-goal')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="goal-act" value="add_tabungan"><input type="hidden" name="id" id="goal-id"><input type="hidden" name="back" value="?page=tabungan">
    <input type="hidden" name="emoji" data-emoji id="goal-emoji" value="🕋"><input type="hidden" name="tint" data-tint value="#e4f0ea"><input type="hidden" name="warna" data-warna value="#2f7d5d">
    <div class="field"><label>Pilih Ikon</label>
      <div class="emoji-pick">
        <?php $gi=[['🕋','#e4f0ea','#2f7d5d'],['✈️','#e3ecf6','#3b6fb0'],['💻','#f7e6da','#c8602c'],['🚗','#f7ecd5','#d99a2b'],['🏠','#e4f0ea','#2f7d5d'],['💍','#ede4f4','#8a5fb0'],['🎓','#e3ecf6','#3b6fb0'],['📱','#f7e6da','#c8602c']];
        foreach($gi as $i=>[$em,$tint,$w]): ?><div class="ei <?= $i===0?'on':'' ?>" style="background:<?= $i===0?$tint:'var(--card)' ?>;border-color:<?= $i===0?$w:'var(--line)' ?>" onclick="pickEmoji(this.parentElement,'<?= $em ?>','<?= $tint ?>','<?= $w ?>')"><?= $em ?></div><?php endforeach; ?>
      </div></div>
    <div class="field"><label>Nama Target</label><input type="text" name="judul" id="goal-judul" placeholder="Contoh: Tabungan Haji" required></div>
    <div style="display:flex;gap:12px">
      <div class="field" style="flex:1"><label>Target Dana (Rp)</label><input type="text" name="target" id="goal-target" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this);hitungNabung()" required style="font-family:var(--serif)"></div>
      <div class="field" style="flex:1"><label>Saldo Awal (opsional)</label><input type="text" name="saldo_awal" id="goal-awal" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this);hitungNabung()" style="font-family:var(--serif)"></div>
    </div>
    <?php jadwalField('tb',['parts'=>['selesai']]); ?>
    <div class="toggle-row" style="margin-bottom:14px">
      <span style="font-size:13.5px;font-weight:700">Jadwalkan setoran rutin?</span>
      <label class="switch"><input type="checkbox" name="ingatkan" value="1" id="tb-on" onchange="tbSched()"><span class="sl"></span></label></div>
    <div id="tb-sched" style="display:none">
      <?php jadwalField('tb',['parts'=>['mulai','freq']]); ?>
      <div class="field"><label>Nabung/Bulan (opsional)</label><input type="text" name="per_bulan" id="goal-pb" inputmode="numeric" placeholder="otomatis" oninput="fmtRupiah(this);hitungNabung()" style="font-family:var(--serif)"></div>
      <div class="toggle-row" style="margin-bottom:6px">
        <span style="font-size:13px;font-weight:700">💰 Setor otomatis tiap bulan</span>
        <label class="switch"><input type="checkbox" name="auto_setor" value="1" id="goal-auto"><span class="sl"></span></label></div>
      <div class="field"><label>Setor otomatis dari rekening</label>
        <select name="sumber_dompet_id" id="goal-src">
          <option value="">— pilih rekening —</option>
          <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="font-size:11px;color:var(--soft);margin:0 2px 8px">Saldo "Nabung/Bulan" otomatis dipindahkan dari rekening di atas tiap bulan (pada tanggal pengingat). Jika saldo rekening kurang, setoran ditunda.</div>
    </div>
    <div class="field"><label>Catatan (boleh kosong)</label><input type="text" name="catatan" id="goal-cat" placeholder=""></div>
    <div id="goal-hint" style="display:none;padding:10px 14px;background:var(--blueT);border-radius:12px;margin-bottom:14px;font-size:12.5px;font-weight:700;color:var(--blue)"></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Target</button>
  </form>
</div></div></div>

<script>
var _dnCur=0,_dnTarget=0,_dnMode='setor';
function openDana(g,mode){
  _dnCur=Number(g.terkumpul); _dnTarget=Number(g.target); _dnMode=mode;
  document.getElementById('dn-title').textContent=mode==='setor'?'💰 Setor Dana':'💸 Tarik Dana';
  document.getElementById('dn-act').value=mode==='setor'?'tambah_dana':'kurangi_dana';
  document.getElementById('dn-id').value=g.id;
  document.getElementById('dn-dana').value=''; document.getElementById('dn-preview').style.display='none';
  document.getElementById('dn-btn').textContent=mode==='setor'?'Setor':'Tarik';
  document.getElementById('dn-wlbl').textContent=mode==='setor'?'Dari rekening':'Ke rekening';
  document.getElementById('dn-goal').innerHTML='<div class="cat" style="width:38px;height:38px;font-size:19px;background:'+g.tint+'">'+g.emoji+'</div><div><div style="font-size:14px;font-weight:700">'+g.judul+'</div><div style="font-size:12px;color:var(--soft)">Terkumpul Rp'+_dnCur.toLocaleString('id-ID')+'</div></div>';
  openModal('m-dana');
}
function previewDana(){
  var v=Number((document.getElementById('dn-dana').value||'').replace(/\D/g,''));
  var p=document.getElementById('dn-preview');
  if(!v){p.style.display='none';return;}
  var hasil=_dnMode==='setor'?_dnCur+v:Math.max(0,_dnCur-v);
  var pct=_dnTarget>0?Math.round(hasil/_dnTarget*100):0;
  p.style.display='block';
  p.style.background=_dnMode==='setor'?'var(--greenT)':'var(--redT)';
  p.style.color=_dnMode==='setor'?'var(--green)':'var(--red)';
  p.innerHTML=(_dnMode==='setor'?'➡️ Terkumpul jadi ':'➡️ Terkumpul jadi ')+'<b>Rp'+hasil.toLocaleString('id-ID')+'</b>'+(_dnTarget>0?' ('+pct+'% target)':'');
}
function bersih(v){return Number((v||'').toString().replace(/\D/g,''));}
function bulanSampai(tgl){ if(!tgl)return 0; var a=new Date(); a.setHours(0,0,0,0); var b=new Date(tgl); if(b<=a)return 0; var m=(b.getFullYear()-a.getFullYear())*12+(b.getMonth()-a.getMonth()); if(b.getDate()>a.getDate())m++; return Math.max(1,m); }
function hitungNabung(){
  var target=bersih(document.getElementById('goal-target').value);
  var awal=bersih(document.getElementById('goal-awal').value);
  var em=document.getElementById('tb-emode'); var tgl=(em&&em.value==='tanggal')?document.getElementById('tb-selesai').value:'';
  var pb=document.getElementById('goal-pb');
  var hint=document.getElementById('goal-hint');
  var bln=bulanSampai(tgl); var pbVal=bersih(pb.value); var perlu=Math.max(0,target-awal);
  if(target>0 && bln>0){
    var perbulan=Math.ceil(perlu/bln);
    hint.style.display='block';
    hint.textContent='💡 Untuk capai target tepat waktu: nabung ±Rp'+perbulan.toLocaleString('id-ID')+'/bulan selama '+bln+' bulan.';
    if(!pb.value) pb.placeholder='otomatis: Rp'+perbulan.toLocaleString('id-ID');
  } else if(target>0 && pbVal>0){
    var n=Math.ceil(perlu/pbVal);
    hint.style.display='block';
    hint.textContent='💡 Dengan menabung Rp'+pbVal.toLocaleString('id-ID')+'/bulan, target tercapai dalam ±'+n+' bulan.';
  } else { hint.style.display='none'; pb.placeholder='otomatis'; }
}
function tbSched(){document.getElementById('tb-sched').style.display=document.getElementById('tb-on').checked?'':'none';hitungNabung();}
function openGoal(){document.getElementById('goal-title').textContent='Target Baru 🎯';document.getElementById('goal-act').value='add_tabungan';['goal-id','goal-judul','goal-target','goal-awal','goal-pb','goal-cat'].forEach(i=>document.getElementById(i).value='');document.getElementById('goal-awal').parentElement.style.display='';document.getElementById('goal-hint').style.display='none';document.getElementById('tb-on').checked=false;document.getElementById('goal-auto').checked=false;document.getElementById('goal-src').value='';jadwalSet('tb',{});tbSched();openModal('m-goal');}
function editGoal(g){document.getElementById('goal-title').textContent='Edit Target';document.getElementById('goal-act').value='edit_tabungan';document.getElementById('goal-id').value=g.id;document.getElementById('goal-judul').value=g.judul;document.getElementById('goal-target').value=Number(g.target).toLocaleString('id-ID');document.getElementById('goal-pb').value=g.per_bulan>0?Number(g.per_bulan).toLocaleString('id-ID'):'';document.getElementById('goal-cat').value=g.catatan||'';document.getElementById('goal-emoji').value=g.emoji;document.getElementById('goal-awal').value='';document.getElementById('goal-awal').parentElement.style.display='none';document.getElementById('goal-hint').style.display='none';document.getElementById('tb-on').checked=(g.ingatkan==1);document.getElementById('goal-auto').checked=(g.auto_setor==1);document.getElementById('goal-src').value=g.sumber_dompet_id||'';jadwalSet('tb',g);tbSched();openModal('m-goal');}
// hitung ulang saat tanggal selesai / frekuensi berubah
(function(){['tb-selesai','tb-freq','tb-emode'].forEach(function(id){var el=document.getElementById(id);if(el)el.addEventListener('change',hitungNabung);});})();
</script>
