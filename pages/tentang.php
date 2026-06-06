<?php topbar('Tentang & Bantuan', 'Panduan singkat memakai Uangku', $notifs, 'tentang'); ?>

<div class="grid-fit">
  <div>
    <div class="card" style="padding:22px;margin-bottom:18px">
      <div style="font-family:var(--serif);font-size:22px;font-weight:600;color:var(--terra)">Uangku</div>
      <div style="font-size:13px;color:var(--soft);margin-top:4px">Aplikasi pencatat keuangan pribadi — atur pemasukan, pengeluaran, anggaran, tabungan, dan tagihan dalam satu tempat.</div>
    </div>

    <div class="eyebrow">Panduan singkat</div>
    <div class="card" style="overflow:hidden">
      <?php
      $help=[
        ['🏠','Beranda','Ringkasan saldo, grafik pemasukan/pengeluaran, dan transaksi terbaru.'],
        ['📊','Analisa','Diagram donat per kategori, tren arus kas (garis/batang), histori, dan unduh laporan PDF/Excel.'],
        ['💼','Anggaran','Atur batas pengeluaran per kategori, kelola dompet, dan transfer antar dompet.'],
        ['🐷','Tabungan','Buat target tabungan, setor/tarik, dan pantau progres.'],
        ['💡','Tagihan','Catat langganan & cicilan; status jatuh tempo / lunas otomatis.'],
        ['📅','Kalender','Agenda, catatan (bisa berulang), dan kerjaan dengan pengingat.'],
        ['⚙️','Pengaturan','Profil, mode gelap, PIN, kelola dompet & kategori.'],
      ];
      foreach($help as [$ic,$t,$d]): ?>
        <div class="row" style="align-items:flex-start">
          <div class="cat" style="width:38px;height:38px;border-radius:12px;font-size:19px;background:var(--card2)"><?= $ic ?></div>
          <div style="flex:1"><div style="font-size:14px;font-weight:700"><?= e($t) ?></div><div style="font-size:12.5px;color:var(--soft);margin-top:2px"><?= e($d) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div>
    <div class="eyebrow">Tanya jawab</div>
    <div class="card" style="overflow:hidden">
      <?php
      $faq=[
        ['Bagaimana cara menambah transaksi?','Ketuk tombol "+ Tambah Transaksi" (sidebar) atau tombol bulat (+) di Beranda.'],
        ['Apa beda Anggaran dan Tabungan?','Anggaran = batas pengeluaran per kategori. Tabungan = target dana yang ingin dikumpulkan.'],
        ['Bagaimana cara unduh laporan?','Buka Analisa → "Unduh PDF" → pilih periode & bagian → simpan PDF, atau "Unduh Excel/CSV".'],
        ['Data saya aman?','Tiap akun hanya bisa melihat datanya sendiri. Login memakai akun Google.'],
        ['Lupa/ingin keluar?','Buka Pengaturan → Keluar / Logout.'],
      ];
      foreach($faq as [$q,$a]): ?>
        <div class="row" style="flex-direction:column;align-items:flex-start;gap:4px">
          <div style="font-size:13.5px;font-weight:700">❓ <?= e($q) ?></div>
          <div style="font-size:12.5px;color:var(--soft)"><?= e($a) ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:16px 18px;margin-top:18px;text-align:center;color:var(--soft);font-size:12px">
      Uangku · dibuat dengan ❤️ untuk mengelola keuangan pribadi.
    </div>
  </div>
</div>
