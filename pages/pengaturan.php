<?php
$dompet=getDompet($pdo);
$kategori=getKategori($pdo);
$rutin=getTransaksiRutin($pdo);
$punyaPin=!empty($me['pin']);
$rutinLbl=['harian'=>'Tiap hari','mingguan'=>'Tiap minggu','bulanan'=>'Tiap bulan','tahunan'=>'Tiap tahun'];
topbar('Pengaturan', 'Akun & preferensi', $notifs, 'pengaturan');
$msg=$_GET['msg']??'';
?>
<?php if($msg==='restoreok'): ?><div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--greenT);color:var(--green);font-weight:600;font-size:13px">✓ Data berhasil dipulihkan dari backup.</div><?php endif; ?>
<?php if($msg==='restorefail'): ?><div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--redT);color:var(--red);font-weight:600;font-size:13px">⚠️ Restore gagal — pastikan file backup .json benar.</div><?php endif; ?>

<div class="grid-fit">
  <!-- Kolom 1: Profil & keamanan -->
  <div>
    <div class="eyebrow">Profil</div>
    <div class="card" style="padding:18px;margin-bottom:22px">
      <form method="post" action="actions.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profil"><input type="hidden" name="back" value="?page=pengaturan">
        <input type="hidden" name="avatar" data-emoji id="pf-avatar" value="<?= e($me['avatar']) ?>">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
          <div class="cat" id="pf-prev" style="width:60px;height:60px;border-radius:18px;background:var(--terra);font-size:30px;color:#fff;overflow:hidden;background-size:cover;background-position:center;<?= $me['avatar_img']?'background-image:url(uploads/avatars/'.e($me['avatar_img']).'?t='.time().')':'' ?>"><?= $me['avatar_img']?'':e($me['avatar']) ?></div>
          <div style="font-size:12px;color:var(--soft)">Pilih avatar:<div class="emoji-pick" style="margin-top:6px">
            <?php foreach(['🧑','👩','👨','🧔','👧','🦊','🐱','🐼'] as $av): ?><div class="ei <?= $av===$me['avatar']?'on':'' ?>" style="width:38px;height:38px;font-size:18px;border-color:<?= $av===$me['avatar']?'var(--terra)':'var(--line)' ?>" onclick="document.getElementById('pf-avatar').value='<?= $av ?>';document.getElementById('pf-prev').style.backgroundImage='';document.getElementById('pf-prev').textContent='<?= $av ?>';this.parentElement.querySelectorAll('.ei').forEach(x=>x.style.borderColor='var(--line)');this.style.borderColor='var(--terra)'"><?= $av ?></div><?php endforeach; ?>
          </div></div>
        </div>
        <div class="field"><label>Foto profil (opsional · JPG/PNG/WebP, maks 2MB)</label><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></div>
        <div class="field"><label>Nama</label><input type="text" name="nama" value="<?= e($me['nama']) ?>" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="<?= e($me['email']) ?>" required></div>
        <div class="field"><label>Mata uang</label>
          <select name="currency">
            <?php $curs=['Rp'=>'Rupiah (Rp)','$'=>'Dollar ($)','€'=>'Euro (€)','£'=>'Pound (£)','¥'=>'Yen (¥)','RM'=>'Ringgit (RM)','S$'=>'Dollar Singapura (S$)','฿'=>'Baht (฿)'];
            $curNow=$me['currency']??'Rp';
            foreach($curs as $sym=>$lbl): ?><option value="<?= e($sym) ?>" <?= $curNow===$sym?'selected':'' ?>><?= e($lbl) ?></option><?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;font-weight:800">Simpan Profil</button>
      </form>
    </div>

    <div class="eyebrow">Keamanan</div>
    <div class="card" style="padding:18px;margin-bottom:22px">
      <div style="font-size:12.5px;color:var(--soft);margin-bottom:16px;padding:10px 12px;background:var(--card2);border-radius:10px">🔐 Login utama memakai <b>Google</b>. PIN menambah lapisan keamanan saat membuka aplikasi.</div>
      <form method="post" action="actions.php">
        <input type="hidden" name="action" value="set_pin"><input type="hidden" name="back" value="?page=pengaturan">
        <div style="font-size:14px;font-weight:700;margin-bottom:4px">🔒 PIN Aplikasi <?= $punyaPin?'<span class="pill" style="background:var(--greenT);color:var(--green);font-size:10px">aktif</span>':'' ?></div>
        <div style="font-size:12px;color:var(--soft);margin-bottom:12px">Wajib <b>tepat 6 angka</b>. PIN diminta tiap aplikasi dibuka ulang.</div>
        <div class="pin-set">
          <input type="password" name="pin" inputmode="numeric" maxlength="6" minlength="6" pattern="\d{6}" placeholder="PIN baru (6 angka)" required>
          <input type="password" name="pin2" inputmode="numeric" maxlength="6" minlength="6" pattern="\d{6}" placeholder="Ulangi PIN" required>
          <button class="btn btn-primary btn-sm" style="justify-content:center"><?= $punyaPin?'Ubah PIN':'Simpan PIN' ?></button>
        </div>
      </form>
      <?php if($punyaPin): ?>
      <form method="post" action="actions.php" data-confirm="Matikan PIN aplikasi? Aplikasi tidak akan terkunci lagi." data-confirm-icon="🔓" data-confirm-ok="Ya, matikan" style="margin-top:10px">
        <input type="hidden" name="action" value="set_pin"><input type="hidden" name="hapus" value="1"><input type="hidden" name="back" value="?page=pengaturan">
        <button class="btn btn-ghost btn-sm" style="color:var(--red)">Matikan PIN</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="eyebrow">Preferensi</div>
    <div class="card" style="overflow:hidden">
      <div class="row" onclick="toggleDark()" style="cursor:pointer">
        <div class="cat" style="width:38px;height:38px;border-radius:12px;background:var(--card2);color:var(--terra)"><?= icon('moon',19,'var(--terra)') ?></div>
        <div style="flex:1"><div style="font-size:14.5px;font-weight:600">Mode Gelap</div><div id="dark-state" style="font-size:12px;color:var(--soft);margin-top:1px"><?= $me['dark_mode']?'Aktif':'Nonaktif' ?></div></div>
        <span class="switch" id="dark-sw" style="background:<?= $me['dark_mode']?'var(--green)':'#d8cfbe' ?>"><i id="dark-knob" style="left:<?= $me['dark_mode']?'22px':'2.5px' ?>"></i></span>
      </div>
      <div class="row" style="border-top:1px solid var(--line);align-items:flex-start">
        <div class="cat" style="width:38px;height:38px;border-radius:12px;background:var(--card2);color:var(--terra)">🎨</div>
        <div style="flex:1">
          <div style="font-size:14.5px;font-weight:600">Warna Aksen</div>
          <div style="font-size:12px;color:var(--soft);margin:1px 0 10px">Pilih warna utama aplikasi</div>
          <div class="acc-list">
            <?php foreach([['#ef6c2e','Oranye'],['#3b82f6','Biru'],['#16a06b','Hijau'],['#8b5cf6','Ungu'],['#e23d4e','Merah'],['#ec4899','Pink']] as $c): ?>
              <button type="button" class="acc-sw" data-c="<?= $c[0] ?>" title="<?= $c[1] ?>" style="background:<?= $c[0] ?>" onclick="setAccent('<?= $c[0] ?>')"></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <a href="?page=tentang" class="row" style="border-top:1px solid var(--line)">
        <div class="cat" style="width:38px;height:38px;border-radius:12px;background:var(--card2);color:var(--terra)">ℹ️</div>
        <div style="flex:1"><div style="font-size:14.5px;font-weight:600">Tentang &amp; Bantuan</div><div style="font-size:12px;color:var(--soft);margin-top:1px">Panduan singkat memakai aplikasi</div></div>
        <?= icon('chevR',18,'var(--muted)') ?>
      </a>
      <a href="logout.php" class="row" style="border-top:1px solid var(--line)">
        <div class="cat" style="width:38px;height:38px;border-radius:12px;background:var(--redT);color:var(--red)"><?= icon('lock',19,'var(--red)') ?></div>
        <div style="flex:1"><div style="font-size:14.5px;font-weight:600;color:var(--red)">Keluar / Logout</div></div>
        <?= icon('chevR',18,'var(--muted)') ?>
      </a>
    </div>

    <div class="eyebrow">Data</div>
    <div class="card" style="overflow:hidden">
      <a href="backup.php" class="row">
        <div class="cat" style="width:38px;height:38px;border-radius:12px;background:var(--greenT);color:var(--green)"><?= icon('export',18,'var(--green)') ?></div>
        <div style="flex:1"><div style="font-size:14.5px;font-weight:600">Backup data</div><div style="font-size:12px;color:var(--soft);margin-top:1px">Unduh semua datamu (file .json)</div></div>
        <?= icon('chevR',18,'var(--muted)') ?>
      </a>
      <form method="post" action="actions.php" enctype="multipart/form-data" class="row" style="border-top:1px solid var(--line);gap:10px;flex-wrap:wrap" data-confirm="Restore akan MENGGANTI seluruh datamu saat ini dengan isi file backup. Lanjutkan?" data-confirm-icon="♻️" data-confirm-ok="Ya, restore">
        <input type="hidden" name="action" value="restore_data"><input type="hidden" name="back" value="?page=pengaturan">
        <div class="cat" style="width:38px;height:38px;border-radius:12px;background:var(--blueT);color:var(--blue);font-size:18px">♻️</div>
        <div style="flex:1;min-width:160px"><div style="font-size:14.5px;font-weight:600">Restore data</div><div style="font-size:12px;color:var(--soft);margin-top:1px">Pulihkan dari file backup .json</div></div>
        <input type="file" name="file" accept="application/json,.json" required style="font-size:12px;max-width:180px">
        <button class="btn btn-ghost btn-sm">Restore</button>
      </form>
    </div>
  </div>

  <!-- Kolom 2: Dompet & Kategori -->
  <div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div class="eyebrow" style="margin:0">Dompet Saya</div><button class="btn btn-ghost btn-sm" onclick="openDompet()"><?= icon('plus',13,'currentColor',2.5) ?> Dompet</button></div>
    <div class="card" style="overflow:hidden;margin-bottom:22px">
      <?php foreach($dompet as $w): ?>
        <div class="row">
          <div class="cat" style="width:40px;height:40px;border-radius:12px;font-size:20px;background:var(--card2)"><?= $w['emoji'] ?></div>
          <div style="flex:1"><div style="font-size:14.5px;font-weight:600"><?= e($w['nama']) ?></div><div style="font-size:12.5px;font-family:var(--serif);color:var(--soft)"><?= rp($w['saldo']) ?></div></div>
          <div class="card-actions">
            <button class="mini-btn" onclick='editDompet(<?= json_encode($w,JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'><?= icon('edit',15) ?></button>
            <form method="post" action="actions.php" data-confirm="Hapus dompet <?= e($w['nama']) ?>? Transaksi terkait tetap ada."><input type="hidden" name="action" value="delete_dompet"><input type="hidden" name="id" value="<?= $w['id'] ?>"><input type="hidden" name="back" value="?page=pengaturan"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div class="eyebrow" style="margin:0">Kategori</div><button class="btn btn-ghost btn-sm" onclick="openModal('m-kat')"><?= icon('plus',13,'currentColor',2.5) ?> Kategori</button></div>
    <div class="card" style="padding:14px">
      <div style="display:flex;flex-wrap:wrap;gap:8px">
        <?php foreach($kategori as $k): ?>
          <div style="display:flex;align-items:center;gap:7px;padding:7px 10px 7px 7px;background:var(--card2);border-radius:11px">
            <div class="cat" style="width:30px;height:30px;border-radius:9px;font-size:16px;background:<?= $k['tint'] ?>"><?= $k['emoji'] ?></div>
            <span style="font-size:13px;font-weight:600"><?= e($k['nama']) ?></span>
            <span style="font-size:9px;font-weight:800;color:var(--muted);text-transform:uppercase"><?= $k['tipe']==='masuk'?'masuk':'' ?></span>
            <form method="post" action="actions.php" data-confirm="Hapus kategori <?= e($k['nama']) ?>?"><input type="hidden" name="action" value="delete_kategori"><input type="hidden" name="id" value="<?= $k['id'] ?>"><input type="hidden" name="back" value="?page=pengaturan"><button class="mini-btn danger" style="width:24px;height:24px"><?= icon('x',12) ?></button></form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if($rutin): ?>
    <div class="eyebrow" style="margin-top:22px">🔁 Transaksi Berulang</div>
    <div class="card" style="overflow:hidden">
      <?php foreach($rutin as $r): ?>
        <div class="row">
          <div class="cat" style="width:38px;height:38px;border-radius:12px;font-size:18px;background:<?= $r['tint'] ?>"><?= $r['emoji']?:'🔁' ?></div>
          <div style="flex:1;min-width:0"><div style="font-size:14px;font-weight:700"><?= e($r['judul']) ?></div><div style="font-size:12px;color:var(--soft)"><?= e($rutinLbl[$r['frekuensi']]??'Tiap bulan') ?> · <?= rp(abs($r['jumlah'])) ?> · <?= $r['jumlah']>=0?'masuk':'keluar' ?></div></div>
          <form method="post" action="actions.php" data-confirm="Hentikan transaksi berulang &quot;<?= e($r['judul']) ?>&quot;? Transaksi yang sudah tercatat tetap ada."><input type="hidden" name="action" value="delete_rutin"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="back" value="?page=pengaturan"><button class="mini-btn danger"><?= icon('trash',15) ?></button></form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal dompet -->
<div class="modal-bg" id="m-dompet"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2 id="dp-title">Dompet Baru 👛</h2><button class="icon-btn" onclick="closeModal('m-dompet')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" id="dp-act" value="add_dompet"><input type="hidden" name="id" id="dp-id"><input type="hidden" name="back" value="?page=pengaturan">
    <input type="hidden" name="emoji" data-emoji id="dp-emoji" value="💵">
    <div class="field"><label>Pilih Ikon</label><div class="emoji-pick">
      <?php foreach(['💵','🏦','📱','💳','🪙','💰','🏧','🐷'] as $i=>$em): ?><div class="ei <?= $i===0?'on':'' ?>" style="border-color:<?= $i===0?'var(--terra)':'var(--line)' ?>" onclick="pickEmoji(this.parentElement,'<?= $em ?>')"><?= $em ?></div><?php endforeach; ?>
    </div></div>
    <div class="field"><label>Nama Dompet</label><input type="text" name="nama" id="dp-nama" placeholder="Contoh: Bank BCA" required></div>
    <div class="field"><label>Saldo (Rp)</label><input type="text" name="saldo" id="dp-saldo" inputmode="numeric" placeholder="0" oninput="fmtRupiah(this)" style="font-family:var(--serif)"></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800">Simpan Dompet</button>
  </form>
</div></div></div>

<!-- Modal kategori -->
<div class="modal-bg" id="m-kat"><div class="modal"><div class="grip"></div><div class="mbody">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px"><h2>Kategori Baru 🏷️</h2><button class="icon-btn" onclick="closeModal('m-kat')"><?= icon('x',18) ?></button></div>
  <form method="post" action="actions.php">
    <input type="hidden" name="action" value="add_kategori"><input type="hidden" name="back" value="?page=pengaturan">
    <input type="hidden" name="emoji" data-emoji value="🏷️"><input type="hidden" name="tint" data-tint value="#fbf6ec"><input type="hidden" name="warna" data-warna value="#5c5345">
    <div class="seg"><input type="radio" name="tipe" id="kt-k" value="keluar" checked><label for="kt-k">💸 Keluar</label><input type="radio" name="tipe" id="kt-m" value="masuk"><label for="kt-m">💰 Masuk</label></div>
    <div class="field"><label>Pilih Ikon</label><div class="emoji-pick">
      <?php $ki=[['🏷️','#fbf6ec','#5c5345'],['🍔','#f7ecd5','#c8602c'],['👕','#e3ecf6','#3b6fb0'],['⛽','#f6e4e1','#c0392b'],['🎮','#ede4f4','#8a5fb0'],['💊','#e4f0ea','#2f7d5d'],['📚','#f7e6da','#c8602c'],['🎁','#f7ecd5','#d99a2b']];
      foreach($ki as $i=>[$em,$tint,$w]): ?><div class="ei <?= $i===0?'on':'' ?>" style="border-color:<?= $i===0?'var(--terra)':'var(--line)' ?>" onclick="pickEmoji(this.parentElement,'<?= $em ?>','<?= $tint ?>','<?= $w ?>')"><?= $em ?></div><?php endforeach; ?>
    </div></div>
    <div class="field"><label>Nama Kategori</label><input type="text" name="nama" placeholder="Contoh: Pendidikan" required></div>
    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;font-weight:800">Simpan Kategori</button>
  </form>
</div></div></div>

<script>
function openDompet(){document.getElementById('dp-title').textContent='Dompet Baru 👛';document.getElementById('dp-act').value='add_dompet';['dp-id','dp-nama','dp-saldo'].forEach(i=>document.getElementById(i).value='');openModal('m-dompet');}
function editDompet(w){document.getElementById('dp-title').textContent='Edit Dompet';document.getElementById('dp-act').value='edit_dompet';document.getElementById('dp-id').value=w.id;document.getElementById('dp-nama').value=w.nama;document.getElementById('dp-saldo').value=Number(w.saldo).toLocaleString('id-ID');document.getElementById('dp-emoji').value=w.emoji;openModal('m-dompet');}
// Tandai swatch warna aksen yang sedang aktif
(function(){var cur=localStorage.getItem('accent')||'#ef6c2e';document.querySelectorAll('.acc-sw').forEach(function(x){x.classList.toggle('on',x.dataset.c===cur);});})();
</script>
