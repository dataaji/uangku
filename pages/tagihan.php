<?php
$tagihan=getTagihan($pdo);
$dompetList=getDompet($pdo);
$filter=$_GET['fp']??'bulan'; // minggu | bulan | tahun
$today=(int)date('j');

// pisahkan piutang (orang berhutang ke kita) & hutang (kita berhutang) dari tagihan biasa
$piutangs=[]; $hutangs=[]; $due=[]; $overdue=[]; $lunas=[];
foreach($tagihan as $b){
  $jn=$b['jenis']??'';
  if($jn==='piutang'){ $piutangs[]=$b; continue; }
  if($jn==='hutang'){ $hutangs[]=$b; continue; }
  $st=tagihanStatus($b); if($st==='lunas')$lunas[]=$b; elseif($st==='overdue')$overdue[]=$b; else $due[]=$b;
}
$pTot=array_sum(array_column($piutangs,'total')); $pKembali=array_sum(array_column($piutangs,'terbayar')); $pSisa=$pTot-$pKembali;
$hTot=array_sum(array_column($hutangs,'total')); $hBayar=array_sum(array_column($hutangs,'terbayar')); $hSisa=$hTot-$hBayar;
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

// Kartu PIUTANG (orang berhutang ke kita)
function piutangCard($b){ global $dompetList;
  $sisa=max(0,(float)$b['total']-(float)$b['terbayar']);
  $pct=$b['total']>0?round($b['terbayar']/$b['total']*100):0;
  $lunas=!empty($b['sudah_bayar']) || $sisa<=0;
  ?>
  <div class="card" style="padding:16px 18px;margin-bottom:12px;border-left:4px solid <?= $lunas?'var(--green)':'var(--blue)' ?>">
    <div style="display:flex;align-items:center;gap:14px">
      <div class="cat" style="width:46px;height:46px;font-size:22px;background:<?= $b['tint']?:'#e3ecf6' ?>">🤝</div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <span style="font-size:15px;font-weight:700"><?= e($b['nama']) ?></span>
          <?php if($lunas): ?><span class="pill" style="background:var(--greenT);color:var(--green);font-size:10px;padding:2px 8px">✅ Lunas</span>
          <?php else: ?><span class="pill" style="background:var(--blueT);color:var(--blue);font-size:10px;padding:2px 8px">🤝 Berhutang</span><?php endif; ?>
        </div>
        <div style="font-size:12.5px;color:var(--soft);margin-top:3px">Pinjam <?= rp($b['total']) ?><?= !empty($b['selesai_tgl'])?' · kembali '.tglIndo($b['selesai_tgl']):'' ?><?= $b['deskripsi']?' · '.e($b['deskripsi']):'' ?></div>
        <?php if($b['catatan']): ?><div style="font-size:11.5px;color:var(--muted);margin-top:2px">📝 <?= e($b['catatan']) ?></div><?php endif; ?>
      </div>
      <div class="card-actions">
        <button class="mini-btn" onclick='editPiutang(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',15) ?></button>
        <form method="post" action="actions.php" data-confirm="Hapus catatan piutang <?= e($b['nama']) ?>? (saldo dompet tidak berubah)"><input type="hidden" name="action" value="delete_tagihan"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="?page=tagihan"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
      </div>
    </div>
    <div class="prog" style="margin-top:12px"><i style="width:<?= min(100,$pct) ?>%;background:var(--blue)"></i></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:9px;gap:10px;flex-wrap:wrap">
      <span style="font-size:12.5px;font-weight:700">Kembali <?= rp($b['terbayar']) ?> / <?= rp($b['total']) ?> · sisa <?= rpShort($sisa) ?></span>
      <?php if(!$lunas): ?>
      <button class="btn btn-primary btn-sm" onclick='openTerima(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"piutang")'>💰 Terima bayar</button>
      <?php endif; ?>
    </div>
  </div>
<?php }

// Kartu HUTANG (kita berhutang ke orang)
function hutangCard($b){ global $dompetList;
  $sisa=max(0,(float)$b['total']-(float)$b['terbayar']);
  $pct=$b['total']>0?round($b['terbayar']/$b['total']*100):0;
  $lunas=!empty($b['sudah_bayar']) || $sisa<=0;
  ?>
  <div class="card" style="padding:16px 18px;margin-bottom:12px;border-left:4px solid <?= $lunas?'var(--green)':'var(--red)' ?>">
    <div style="display:flex;align-items:center;gap:14px">
      <div class="cat" style="width:46px;height:46px;font-size:22px;background:<?= $b['tint']?:'#f7e6da' ?>">🙏</div>
      <div style="flex:1;min-width:0">
        <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap">
          <span style="font-size:15px;font-weight:700"><?= e($b['nama']) ?></span>
          <?php if($lunas): ?><span class="pill" style="background:var(--greenT);color:var(--green);font-size:10px;padding:2px 8px">✅ Lunas</span>
          <?php else: ?><span class="pill" style="background:var(--redT);color:var(--red);font-size:10px;padding:2px 8px">🙏 Belum lunas</span><?php endif; ?>
        </div>
        <div style="font-size:12.5px;color:var(--soft);margin-top:3px">Hutang <?= rp($b['total']) ?><?= !empty($b['selesai_tgl'])?' · bayar '.tglIndo($b['selesai_tgl']):'' ?><?= $b['deskripsi']?' · '.e($b['deskripsi']):'' ?></div>
        <?php if($b['catatan']): ?><div style="font-size:11.5px;color:var(--muted);margin-top:2px">📝 <?= e($b['catatan']) ?></div><?php endif; ?>
      </div>
      <div class="card-actions">
        <button class="mini-btn" onclick='editHutang(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',15) ?></button>
        <form method="post" action="actions.php" data-confirm="Hapus catatan hutang <?= e($b['nama']) ?>? (saldo dompet tidak berubah)"><input type="hidden" name="action" value="delete_tagihan"><input type="hidden" name="id" value="<?= $b['id'] ?>"><input type="hidden" name="back" value="?page=tagihan"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
      </div>
    </div>
    <div class="prog" style="margin-top:12px"><i style="width:<?= min(100,$pct) ?>%;background:var(--red)"></i></div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:9px;gap:10px;flex-wrap:wrap">
      <span style="font-size:12.5px;font-weight:700">Dibayar <?= rp($b['terbayar']) ?> / <?= rp($b['total']) ?> · sisa <?= rpShort($sisa) ?></span>
      <?php if(!$lunas): ?>
      <button class="btn btn-primary btn-sm" onclick='openTerima(<?= json_encode($b,JSON_HEX_APOS|JSON_HEX_QUOT) ?>,"hutang")'>💸 Bayar</button>
      <?php endif; ?>
    </div>
  </div>
<?php }

topbar('Tagihan', count($tagihan).' tagihan, piutang & hutang', $notifs, 'tagihan',
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

<?php if(!$tagihan): ?><div class="empty"><div class="ico">💡</div><div class="msg">Belum ada tagihan atau piutang</div><button class="btn btn-primary" style="margin-top:16px" onclick="openTagihan()">Tambah Tagihan / Piutang</button></div><?php endif; ?>
<?php if($overdue): ?><div class="eyebrow" style="color:var(--red)">🔴 Lewat jatuh tempo · <?= count($overdue) ?></div><?php foreach($overdue as $b) billCard($b); ?><?php endif; ?>
<?php if($due): ?><div class="eyebrow" style="margin-top:18px;color:var(--amber)">⏳ Jatuh tempo · <?= count($due) ?></div><?php foreach($due as $b) billCard($b); ?><?php endif; ?>
<?php if($lunas): ?><div class="eyebrow" style="margin-top:18px;color:var(--green)">✅ Sudah lunas · <?= count($lunas) ?></div><?php foreach($lunas as $b) billCard($b); ?><?php endif; ?>

<?php if($piutangs): ?>
  <div class="eyebrow" style="margin-top:22px;color:var(--blue)">🤝 Piutang — orang berhutang ke kamu · <?= count($piutangs) ?></div>
  <div class="card" style="padding:14px 18px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--blueT)">
    <div style="font-size:12.5px;font-weight:700;color:var(--blue)">💙 Total dipinjamkan <?= rp($pTot) ?></div>
    <div style="font-size:12.5px;font-weight:700;color:var(--blue)">Belum kembali <b><?= rp($pSisa) ?></b></div>
  </div>
  <?php foreach($piutangs as $b) piutangCard($b); ?>
<?php endif; ?>

<?php if($hutangs): ?>
  <div class="eyebrow" style="margin-top:22px;color:var(--red)">🙏 Hutang — kamu berhutang ke orang · <?= count($hutangs) ?></div>
  <div class="card" style="padding:14px 18px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;background:var(--redT)">
    <div style="font-size:12.5px;font-weight:700;color:var(--red)">❤️ Total hutang <?= rp($hTot) ?></div>
    <div style="font-size:12.5px;font-weight:700;color:var(--red)">Belum dibayar <b><?= rp($hSisa) ?></b></div>
  </div>
  <?php foreach($hutangs as $b) hutangCard($b); ?>
<?php endif; ?>

<!-- Modal tambah/edit tagihan -->
<div class="modal-bg" id="m-tagihan"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="tg-title">Tagihan Baru 💡</h2><button class="icon-btn" onclick="closeModal('m-tagihan')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="tg-act" value="add_tagihan"><input type="hidden" name="id" id="tg-id"><input type="hidden" name="back" value="?page=tagihan">
    <input type="hidden" name="emoji" data-emoji id="tg-emoji" value="💡"><input type="hidden" name="tint" data-tint value="#f7ecd5">
    <div class="seg seg-4" id="tg-seg">
      <input type="radio" name="jenis" id="jn-l" value="langganan" checked onchange="tgJenis('langganan')"><label for="jn-l">🔁 Langganan</label>
      <input type="radio" name="jenis" id="jn-c" value="cicilan" onchange="tgJenis('cicilan')"><label for="jn-c">📅 Cicilan</label>
      <input type="radio" name="jenis" id="jn-p" value="piutang" onchange="tgJenis('piutang')"><label for="jn-p">🤝 Piutang</label>
      <input type="radio" name="jenis" id="jn-h" value="hutang" onchange="tgJenis('hutang')"><label for="jn-h">🙏 Hutang</label>
    </div>
    <div class="field" id="tg-ficon"><label>Ikon</label><div class="emoji-pick">
      <?php $ti=[['💡','#f7ecd5'],['📶','#e3ecf6'],['🎬','#ede4f4'],['🎵','#e4f0ea'],['💧','#e3ecf6'],['🏠','#f7e6da'],['📱','#f7ecd5'],['🚗','#f6e4e1']];
      foreach($ti as $i=>[$em,$tint]): ?><div class="ei <?= $i===0?'on':'' ?>" style="background:<?= $i===0?$tint:'var(--card)' ?>;border-color:<?= $i===0?'var(--terra)':'var(--line)' ?>" onclick="pickEmoji(this.parentElement,'<?= $em ?>','<?= $tint ?>')"><?= $em ?></div><?php endforeach; ?>
    </div></div>
    <div class="field"><label id="tg-nlbl">Nama</label><input type="text" name="nama" id="tg-nama" placeholder="Contoh: IndiHome / Cicilan Motor" required></div>

    <!-- PIUTANG (orang berhutang ke kita) / HUTANG (kita berhutang) — blok dipakai bersama -->
    <div id="tg-piutang" style="display:none">
      <div id="tg-pt-hint" style="font-size:12px;color:var(--soft);margin:0 2px 12px"></div>
      <div class="field"><label id="tg-pt-dlbl">Uang diambil dari dompet</label>
        <select name="dompet_id" id="tg-pt-dompet" disabled>
          <?php if(!$dompetList): ?><option value="">— belum ada dompet —</option><?php endif; ?>
          <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?> (<?= rp($w['saldo']) ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label id="tg-pt-tlbl">Jumlah dipinjamkan (Rp)</label><input type="text" name="total" id="tg-pt-total" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" disabled style="font-family:var(--serif);font-size:22px;text-align:center"></div>
      <div class="field"><label>Tanggal harus lunas (kosongkan jika tanpa tenggat)</label><input type="date" name="tempo_tgl" id="tg-pt-tempo" disabled></div>
      <div class="field"><label>Catatan (opsional)</label><input type="text" name="catatan" id="tg-pt-cat" placeholder="Contoh: buat modal usaha" disabled></div>
    </div>

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
    <button type="submit" id="tg-submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Simpan Tagihan</button>
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

<!-- Modal TERIMA pembayaran piutang / BAYAR hutang (dipakai bersama) -->
<div class="modal-bg" id="m-terima"><div class="modal" style="max-width:380px"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="tr-title">💰 Terima Pembayaran</h2><button class="icon-btn" onclick="closeModal('m-terima')"><?= icon('x',18) ?></button></div>
  <div id="tr-info" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--card2);border-radius:14px;margin-bottom:16px"></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="tr-act" value="bayar_piutang"><input type="hidden" name="id" id="tr-id"><input type="hidden" name="back" value="?page=tagihan">
    <div class="field"><label id="tr-dlbl">Uang masuk ke dompet</label>
      <select name="dompet_id" id="tr-dompet" required>
        <?php if(!$dompetList): ?><option value="">— belum ada dompet —</option><?php endif; ?>
        <?php foreach($dompetList as $w): ?><option value="<?= $w['id'] ?>"><?= e($w['emoji'].' '.$w['nama']) ?> (<?= rp($w['saldo']) ?>)</option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label id="tr-jlbl">Jumlah dikembalikan (Rp)</label><input type="text" name="bayar" id="tr-jml" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" required style="font-family:var(--serif);font-size:22px;text-align:center"><div style="font-size:11px;color:var(--soft);margin-top:5px">Boleh sebagian kalau nyicil — sisanya tetap tercatat.</div></div>
    <button type="submit" id="tr-submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:15px;font-size:16px;font-weight:800">Terima & Catat</button>
  </form>
</div></div></div>

<script>
function editPH(b,type){  // edit piutang/hutang (segmen di modal tagihan)
  var piu=(type==='piutang');
  document.getElementById('tg-title').textContent=piu?'Edit Piutang':'Edit Hutang';
  document.getElementById('tg-id').value=b.id;
  document.getElementById('tg-nama').value=b.nama;
  document.getElementById('tg-pt-cat').value=b.catatan||'';
  document.getElementById('tg-pt-tempo').value=b.selesai_tgl||'';
  document.getElementById('tg-pt-total').value=b.total>0?Number(b.total).toLocaleString('id-ID'):'';
  _tgEdit=true;
  document.getElementById(piu?'jn-p':'jn-h').checked=true; document.getElementById('tg-seg').style.display='flex';
  tgJenis(type);
  // saat edit, dompet & jumlah tidak bisa diubah (uang sudah berpindah)
  document.getElementById('tg-pt-dompet').disabled=true; document.getElementById('tg-pt-dompet').parentElement.style.display='none';
  document.getElementById('tg-pt-total').disabled=true; document.getElementById('tg-pt-total').parentElement.style.display='none';
  openModal('m-tagihan');
}
function editPiutang(b){ editPH(b,'piutang'); }
function editHutang(b){ editPH(b,'hutang'); }
function openTerima(b,type){
  var piu=(type==='piutang');
  document.getElementById('tr-act').value=piu?'bayar_piutang':'bayar_hutang';
  document.getElementById('tr-id').value=b.id;
  var sisa=Math.max(0,Number(b.total)-Number(b.terbayar));
  document.getElementById('tr-jml').value=sisa.toLocaleString('id-ID');
  document.getElementById('tr-title').textContent=piu?'💰 Terima Pembayaran':'💸 Bayar Hutang';
  document.getElementById('tr-dlbl').textContent=piu?'Uang masuk ke dompet':'Bayar dari dompet';
  document.getElementById('tr-jlbl').textContent=piu?'Jumlah dikembalikan (Rp)':'Jumlah dibayar (Rp)';
  document.getElementById('tr-submit').textContent=piu?'Terima & Catat':'Bayar';
  document.getElementById('tr-info').innerHTML='<div class="cat" style="width:38px;height:38px;font-size:19px;background:'+(piu?'#e3ecf6':'#f7e6da')+'">'+(piu?'🤝':'🙏')+'</div><div><div style="font-size:14px;font-weight:700">'+b.nama+'</div><div style="font-size:12px;color:var(--soft)">Sisa '+(piu?'piutang':'hutang')+' Rp'+sisa.toLocaleString('id-ID')+'</div></div>';
  openModal('m-terima');
}
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
var _tgEdit=false;
function tgJenis(j){
  var cic=(j==='cicilan'), piu=(j==='piutang'), hut=(j==='hutang'), special=piu||hut;
  // blok piutang/hutang vs tagihan biasa
  document.getElementById('tg-piutang').style.display=special?'block':'none';
  document.getElementById('tg-ficon').style.display=special?'none':'';
  document.getElementById('tg-detail').style.display=special?'none':'';
  document.getElementById('tg-cicil').style.display=cic?'block':'none';
  document.getElementById('tg-hitung').style.display=cic?'block':'none';
  document.getElementById('tg-jlbl').textContent=cic?'Cicilan / bulan (Rp)':'Jumlah / bulan (Rp)';
  document.getElementById('tg-jhint').textContent=cic?'Estimasi lama lunas dihitung otomatis di bawah.':'';
  // aktif/nonaktif input agar tidak bentrok nama (total/catatan) & required tersembunyi
  document.getElementById('tg-pt-dompet').disabled=!special;
  document.getElementById('tg-pt-total').disabled=!special;
  document.getElementById('tg-pt-tempo').disabled=!special;
  document.getElementById('tg-pt-cat').disabled=!special;
  document.getElementById('tg-total').disabled=special;
  document.getElementById('tg-jumlah').disabled=special;
  document.getElementById('tg-cat').disabled=special;
  // label dinamis untuk piutang/hutang
  document.getElementById('tg-nlbl').textContent=piu?'Nama orang':(hut?'Hutang ke siapa':'Nama');
  document.getElementById('tg-nama').placeholder=piu?'Contoh: Andi':(hut?'Contoh: Budi / Bank':'Contoh: IndiHome / Cicilan Motor');
  document.getElementById('tg-pt-dlbl').textContent=hut?'Uang masuk ke dompet':'Uang diambil dari dompet';
  document.getElementById('tg-pt-tlbl').textContent=hut?'Jumlah hutang (Rp)':'Jumlah dipinjamkan (Rp)';
  document.getElementById('tg-pt-hint').textContent=hut
    ? 'Catat saat kamu meminjam uang. Uang masuk ke dompet yang dipilih; saat kamu bayar (boleh nyicil), uang keluar dari dompet.'
    : 'Catat saat kamu menghutangi orang. Uang keluar dari dompet yang dipilih; saat dia bayar (boleh nyicil), uang balik ke dompet.';
  if(special){
    var addAct=piu?'add_piutang':'add_hutang', editAct=piu?'edit_piutang':'edit_hutang';
    document.getElementById('tg-act').value=_tgEdit?editAct:addAct;
    document.getElementById('tg-submit').textContent=_tgEdit?'Simpan Perubahan':(piu?'Simpan Piutang':'Simpan Hutang');
    if(!_tgEdit) document.getElementById('tg-title').textContent=piu?'🤝 Catat Piutang':'🙏 Catat Hutang';
    document.getElementById('tg-pt-total').required=!_tgEdit;
    document.getElementById('tg-pt-dompet').required=!_tgEdit;
  } else {
    document.getElementById('tg-act').value=_tgEdit?'edit_tagihan':'add_tagihan';
    document.getElementById('tg-submit').textContent='Simpan Tagihan';
    document.getElementById('tg-pt-total').required=false;
    document.getElementById('tg-pt-dompet').required=false;
    if(!_tgEdit) document.getElementById('tg-title').textContent='Tagihan Baru 💡';
    tgDetailVis();
  }
  hitungCicil();
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
(function(){var s=document.getElementById('tg-selesai');if(s)s.addEventListener('change',hitungCicil);})();
function openTagihan(){
  _tgEdit=false;
  document.getElementById('tg-title').textContent='Tagihan Baru 💡';
  ['tg-id','tg-nama','tg-jumlah','tg-total','tg-cat','tg-pt-total','tg-pt-tempo','tg-pt-cat'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('tg-on').checked=false;
  // pulihkan field piutang yang mungkin disembunyikan saat edit sebelumnya
  document.getElementById('tg-pt-dompet').parentElement.style.display='';
  document.getElementById('tg-pt-total').parentElement.style.display='';
  jadwalSet('tg',{});document.getElementById('jn-l').checked=true;document.getElementById('tg-seg').style.display='flex';tgJenis('langganan');
  openModal('m-tagihan');
}
function openPiutang(){ openTagihan(); document.getElementById('jn-p').checked=true; tgJenis('piutang'); }
function editTagihan(b){
  _tgEdit=true;
  document.getElementById('tg-title').textContent='Edit Tagihan';
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
