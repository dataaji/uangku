<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/auth.php';
$me = requireLogin($pdo);
$U  = (int)$me['id'];   // user aktif — semua data dibatasi ke user ini

// Keamanan: aksi pengubah data hanya via POST + verifikasi token CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method Not Allowed'); }
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) { http_response_code(403); exit('Permintaan ditolak (token tidak valid). Muat ulang halaman.'); }

$action = $_POST['action'] ?? '';
$back   = $_POST['back'] ?? 'index.php';
// Hanya izinkan redirect ke path internal (cegah open redirect / header injection)
function redirect($u){
    if ($u==='') $u='index.php';
    if ($u[0]==='?') { $u='index.php'.$u; }
    elseif (preg_match('~^[a-z][a-z0-9+.\-]*:~i',$u) || strncmp($u,'//',2)===0 || strpbrk($u,"\r\n")!==false) { $u='index.php'; }
    header("Location: $u"); exit;
}
function num($k){ return (float)preg_replace('/[^\d]/','',$_POST[$k]??'0'); }
function str_($k){ return trim($_POST[$k]??''); }
// Pesan kilat (muncul sekali di halaman berikutnya): tipe 'ok' | 'err'
function flash($m,$t='ok'){ $_SESSION['flash']=['t'=>$t,'m'=>$m]; }
// Ambil 1 dompet milik user (atau null)
function dompetById($pdo,$U,$id){ $s=$pdo->prepare('SELECT * FROM dompet WHERE id=? AND user_id=?'); $s->execute([(int)$id,$U]); return $s->fetch() ?: null; }
// Catat pembayaran tagihan: potong saldo rekening + simpan jejak sbg pengeluaran
function bayarTagihan($pdo,$U,$b,$jml,$w){
    $km=katMeta($pdo,'Tagihan');
    $pdo->prepare('UPDATE dompet SET saldo=saldo-? WHERE id=? AND user_id=?')->execute([$jml,$w['id'],$U]);
    $pdo->prepare('INSERT INTO transaksi (user_id,emoji,tint,judul,kategori,dompet_id,tanggal,jumlah,catatan,tagihan_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$U,$b['emoji']?:$km['emoji'],$b['tint']?:$km['tint'],$b['nama'],'Tagihan',$w['id'],date('Y-m-d'),-$jml,'Pembayaran tagihan',$b['id']]);
}
function logAng($pdo,$U,$kat,$aksi,$jml,$batas){ $pdo->prepare('INSERT INTO anggaran_log (user_id,kategori,aksi,jumlah,batas_baru) VALUES (?,?,?,?,?)')->execute([$U,$kat,$aksi,$jml,$batas]); }
// Baca field komponen jadwal terpadu (tanggal mulai + auto ingatkan + frekuensi)
function jadwalPost(){
    $freq=$_POST['frekuensi']??'bulanan';
    $valid=['harian','mingguan','bulanan','tahunan'];
    if(!in_array($freq,$valid)) $freq='bulanan';
    $emode=$_POST['selesai_mode']??'selamanya';                 // selamanya | tanggal
    $selesai=($emode==='tanggal' && ($_POST['selesai_tgl']??''))?$_POST['selesai_tgl']:null;
    return [
        'mulai'   => ($_POST['mulai_tgl']??'')?:null,
        'ingatkan'=> isset($_POST['ingatkan'])?1:0,
        'freq'    => $freq,
        'hari'    => max(0,min(6,(int)($_POST['freq_hari']??1))),
        'tgl'     => max(1,min(31,(int)($_POST['freq_tgl']??1))),
        'bulan'   => max(1,min(12,(int)($_POST['freq_bulan']??1))),
        'selesai' => $selesai,
    ];
}

switch ($action) {

// ════════════ TRANSAKSI ════════════
case 'add_transaksi': {
    $tipe=$_POST['tipe']??'keluar'; $judul=str_('judul');
    $kategori=$tipe==='masuk'?'Pemasukan':($_POST['kategori']??'Lainnya');
    $nominal=num('jumlah'); $dompetId=(int)($_POST['dompet_id']??0);
    $tanggal=$_POST['tanggal']??date('Y-m-d'); $catatan=str_('catatan');
    // Jika dompet tidak dipilih / tidak ada → otomatis pakai "Tunai" (buat bila perlu)
    if($dompetId){ $ck=$pdo->prepare('SELECT id FROM dompet WHERE id=? AND user_id=?'); $ck->execute([$dompetId,$U]); if(!$ck->fetchColumn()) $dompetId=0; }
    if(!$dompetId){
        $w=$pdo->prepare("SELECT id FROM dompet WHERE user_id=? AND nama='Tunai' LIMIT 1"); $w->execute([$U]); $dompetId=(int)$w->fetchColumn();
        if(!$dompetId){ $w=$pdo->prepare('SELECT id FROM dompet WHERE user_id=? ORDER BY id LIMIT 1'); $w->execute([$U]); $dompetId=(int)$w->fetchColumn(); }
        if(!$dompetId){ $pdo->prepare("INSERT INTO dompet (user_id,nama,emoji,saldo) VALUES (?,?,?,0)")->execute([$U,'Tunai','💵']); $dompetId=(int)$pdo->lastInsertId(); }
    }
    // Boleh pengeluaran walau saldo 0 (saldo jadi minus)
    if($judul && $nominal>0){
        $jumlah=$tipe==='masuk'?$nominal:-$nominal; $km=katMeta($pdo,$kategori);
        $pdo->prepare('INSERT INTO transaksi (user_id,emoji,tint,judul,kategori,dompet_id,tanggal,jumlah,catatan) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$U,$km['emoji'],$km['tint'],$judul,$kategori,$dompetId,$tanggal,$jumlah,$catatan]);
        $pdo->prepare('UPDATE dompet SET saldo=saldo+? WHERE id=? AND user_id=?')->execute([$jumlah,$dompetId,$U]);
        // Buat template berulang otomatis (opsional)
        if(isset($_POST['rutin'])){
            $rf=$_POST['rutin_freq']??'bulanan'; if(!in_array($rf,['harian','mingguan','bulanan','tahunan']))$rf='bulanan';
            $pdo->prepare('INSERT INTO transaksi_rutin (user_id,emoji,tint,judul,kategori,dompet_id,jumlah,frekuensi,mulai_tgl,terakhir_jalan,aktif) VALUES (?,?,?,?,?,?,?,?,?,?,1)')
                ->execute([$U,$km['emoji'],$km['tint'],$judul,$kategori,$dompetId,$jumlah,$rf,$tanggal,$tanggal]);
        }
    } redirect($back);
}
case 'delete_rutin': { $pdo->prepare('DELETE FROM transaksi_rutin WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }
case 'delete_transaksi': {
    $id=(int)$_POST['id']; $s=$pdo->prepare('SELECT * FROM transaksi WHERE id=? AND user_id=?'); $s->execute([$id,$U]); $tx=$s->fetch();
    if($tx){ $pdo->prepare('UPDATE dompet SET saldo=saldo-? WHERE id=? AND user_id=?')->execute([$tx['jumlah'],$tx['dompet_id'],$U]);
        $pdo->prepare('DELETE FROM transaksi WHERE id=? AND user_id=?')->execute([$id,$U]); } redirect($back);
}

// ════════════ TAGIHAN ════════════
case 'add_tagihan': {
    $nama=str_('nama'); $jenis=$_POST['jenis']??'langganan';
    $jumlah=num('jumlah'); $total=num('total'); $tenor=0;
    $emoji=$_POST['emoji']??'💡'; $tint=$_POST['tint']??'#f7ecd5'; $catatan=str_('catatan');
    $j=jadwalPost();
    $ingat = ($jenis==='langganan') ? 1 : ($j['ingatkan']?1:0);   // langganan selalu ingat; cicilan ikut tombol on/off
    $tgl = in_array($j['freq'],['bulanan','tahunan']) ? $j['tgl'] : ($j['mulai']?(int)date('j',strtotime($j['mulai'])):1);
    $berulang = ($jenis==='cicilan') ? 0 : 1;
    if($jenis==='cicilan' && $jumlah>0 && $total>0) $tenor=(int)ceil($total/$jumlah);   // estimasi jumlah bulan
    $desc = $jenis==='cicilan' ? ($tenor>0?"≈$tenor bulan":'cicilan') : '';
    if($nama && ($jumlah>0 || ($jenis==='cicilan' && $total>0))){
        $pdo->prepare('INSERT INTO tagihan (user_id,emoji,tint,nama,deskripsi,jumlah,tgl_jatuh_tempo,jenis,total,terbayar,catatan,berulang,tenor,mulai_tgl,ingatkan,frekuensi,freq_hari,freq_tgl,freq_bulan,selesai_tgl) VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$U,$emoji,$tint,$nama,$desc,$jumlah,$tgl,$jenis,$total,$catatan,$berulang,$tenor,$j['mulai'],$ingat,$j['freq'],$j['hari'],$j['tgl'],$j['bulan'],$j['selesai']]);
    } redirect($back);
}
case 'edit_tagihan': {
    $id=(int)$_POST['id']; $jenis=$_POST['jenis']??'langganan';
    $jumlah=num('jumlah'); $total=num('total'); $tenor=0;
    $j=jadwalPost();
    $ingat = ($jenis==='langganan') ? 1 : ($j['ingatkan']?1:0);
    $tgl = in_array($j['freq'],['bulanan','tahunan']) ? $j['tgl'] : ($j['mulai']?(int)date('j',strtotime($j['mulai'])):1);
    $berulang = ($jenis==='cicilan') ? 0 : 1;
    if($jenis==='cicilan' && $jumlah>0 && $total>0) $tenor=(int)ceil($total/$jumlah);
    $desc = $jenis==='cicilan' ? ($tenor>0?"≈$tenor bulan":'cicilan') : '';
    $pdo->prepare('UPDATE tagihan SET nama=?,deskripsi=?,jumlah=?,total=?,tenor=?,tgl_jatuh_tempo=?,catatan=?,berulang=?,emoji=?,mulai_tgl=?,ingatkan=?,frekuensi=?,freq_hari=?,freq_tgl=?,freq_bulan=?,selesai_tgl=? WHERE id=? AND user_id=?')
        ->execute([str_('nama'),$desc,$jumlah,$total,$tenor,$tgl,str_('catatan'),$berulang,$_POST['emoji']??'💡',$j['mulai'],$ingat,$j['freq'],$j['hari'],$j['tgl'],$j['bulan'],$j['selesai'],$id,$U]);
    redirect($back);
}
case 'delete_tagihan': { $pdo->prepare('DELETE FROM tagihan WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }
case 'toggle_tagihan': {   // batal/reset lunas → kembalikan uang ke rekening (hapus jejak terakhir)
    $id=(int)$_POST['id']; $s=$pdo->prepare('SELECT * FROM tagihan WHERE id=? AND user_id=?'); $s->execute([$id,$U]); $b=$s->fetch();
    if($b){
        if($b['sudah_bayar']){   // sedang lunas → batalkan & refund pembayaran terakhir
            $t=$pdo->prepare('SELECT * FROM transaksi WHERE user_id=? AND tagihan_id=? ORDER BY id DESC LIMIT 1'); $t->execute([$U,$id]); $tx=$t->fetch();
            if($tx){ $pdo->prepare('UPDATE dompet SET saldo=saldo-? WHERE id=? AND user_id=?')->execute([$tx['jumlah'],$tx['dompet_id'],$U]); // jumlah negatif → saldo bertambah
                $pdo->prepare('DELETE FROM transaksi WHERE id=? AND user_id=?')->execute([$tx['id'],$U]);
                flash('Pembayaran '.$b['nama'].' dibatalkan, uang dikembalikan ke rekening.'); }
            $pdo->prepare('UPDATE tagihan SET sudah_bayar=0 WHERE id=? AND user_id=?')->execute([$id,$U]);
        } else {                 // belum lunas → tandai lunas tanpa uang (jarang dipakai; pembayaran via modal)
            $pdo->prepare('UPDATE tagihan SET sudah_bayar=1 WHERE id=? AND user_id=?')->execute([$id,$U]);
        }
    } redirect($back);
}
case 'bayar_cicilan': {   // CICILAN: potong rekening + catat pengeluaran
    $id=(int)$_POST['id']; $bayar=num('bayar'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $s=$pdo->prepare('SELECT * FROM tagihan WHERE id=? AND user_id=?'); $s->execute([$id,$U]); $b=$s->fetch();
    if($b && $bayar>0){
        $baru=min($b['total'],$b['terbayar']+$bayar); $nyata=$baru-(float)$b['terbayar']; // yg benar2 dibayar (di-cap sisa)
        if($nyata<=0){ flash('Cicilan ini sudah lunas.','err'); redirect($back); }
        if(!$w){ flash('Pilih dulu rekening pembayaran.','err'); redirect($back); }
        if((float)$w['saldo'] < $nyata){ flash('Saldo '.$w['nama'].' tidak cukup (tersisa '.rp($w['saldo']).').','err'); redirect($back); }
        $lunas=$baru>=$b['total']?1:0;
        $pdo->prepare('UPDATE tagihan SET terbayar=?,sudah_bayar=? WHERE id=? AND user_id=?')->execute([$baru,$lunas,$id,$U]);
        bayarTagihan($pdo,$U,$b,$nyata,$w);
        flash('Cicilan '.$b['nama'].' '.rp($nyata).' dibayar dari '.$w['nama'].'.');
    } redirect($back);
}
case 'bayar_langganan': {  // LANGGANAN: tandai lunas + potong rekening + catat pengeluaran
    $id=(int)$_POST['id']; $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $s=$pdo->prepare('SELECT * FROM tagihan WHERE id=? AND user_id=?'); $s->execute([$id,$U]); $b=$s->fetch();
    if($b){
        $bayar=num('bayar')>0?num('bayar'):(float)$b['jumlah'];
        if($bayar<=0){ flash('Jumlah tagihan belum diisi.','err'); redirect($back); }
        if(!$w){ flash('Pilih dulu rekening pembayaran.','err'); redirect($back); }
        if((float)$w['saldo'] < $bayar){ flash('Saldo '.$w['nama'].' tidak cukup (tersisa '.rp($w['saldo']).').','err'); redirect($back); }
        $pdo->prepare('UPDATE tagihan SET sudah_bayar=1 WHERE id=? AND user_id=?')->execute([$id,$U]);
        bayarTagihan($pdo,$U,$b,$bayar,$w);
        flash($b['nama'].' '.rp($bayar).' dibayar dari '.$w['nama'].'.');
    } redirect($back);
}

// ════════════ PIUTANG (orang berhutang ke kita) ════════════
case 'add_piutang': {   // Catat piutang: uang keluar dari rekening kita
    $nama=str_('nama'); $total=num('total'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $tempo=($_POST['tempo_tgl']??'')?:null; $catatan=str_('catatan');
    if($nama && $total>0){
        if(!$w){ flash('Pilih dulu dompet sumber uangnya.','err'); redirect($back); }
        if((float)$w['saldo'] < $total){ flash('Saldo '.$w['nama'].' tidak cukup (tersisa '.rp($w['saldo']).').','err'); redirect($back); }
        $tgl = $tempo ? (int)date('j',strtotime($tempo)) : (int)date('j');
        $pdo->prepare('UPDATE dompet SET saldo=saldo-? WHERE id=? AND user_id=?')->execute([$total,$w['id'],$U]);
        $pdo->prepare('INSERT INTO tagihan (user_id,emoji,tint,nama,deskripsi,jumlah,tgl_jatuh_tempo,jenis,total,terbayar,catatan,berulang,tenor,ingatkan,selesai_tgl) VALUES (?,?,?,?,?,?,?,?,?,0,?,0,0,?,?)')
            ->execute([$U,'🤝','#e3ecf6',$nama,'Dari '.$w['nama'],$total,$tgl,'piutang',$total,$catatan,$tempo?1:0,$tempo]);
        flash('Dicatat: '.$nama.' berhutang '.rp($total).' (uang keluar dari '.$w['nama'].').');
    } redirect($back);
}
case 'edit_piutang': {  // ubah catatan piutang (tidak memindahkan uang)
    $id=(int)$_POST['id']; $nama=str_('nama'); $tempo=($_POST['tempo_tgl']??'')?:null;
    $tgl=$tempo?(int)date('j',strtotime($tempo)):(int)date('j');
    $pdo->prepare('UPDATE tagihan SET nama=?,catatan=?,selesai_tgl=?,tgl_jatuh_tempo=?,ingatkan=? WHERE id=? AND user_id=? AND jenis=\'piutang\'')
        ->execute([$nama,str_('catatan'),$tempo,$tgl,$tempo?1:0,$id,$U]);
    redirect($back);
}
case 'bayar_piutang': {  // Dia melunasi: uang balik ke rekening kita
    $id=(int)$_POST['id']; $bayar=num('bayar'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $s=$pdo->prepare('SELECT * FROM tagihan WHERE id=? AND user_id=? AND jenis=\'piutang\''); $s->execute([$id,$U]); $b=$s->fetch();
    if($b && $bayar>0){
        $baru=min($b['total'],$b['terbayar']+$bayar); $nyata=$baru-(float)$b['terbayar'];
        if($nyata<=0){ flash('Piutang ini sudah lunas.','err'); redirect($back); }
        if(!$w){ flash('Pilih dulu dompet tujuan uang masuk.','err'); redirect($back); }
        $lunas=$baru>=$b['total']?1:0;
        $pdo->prepare('UPDATE tagihan SET terbayar=?,sudah_bayar=? WHERE id=? AND user_id=?')->execute([$baru,$lunas,$id,$U]);
        $pdo->prepare('UPDATE dompet SET saldo=saldo+? WHERE id=? AND user_id=?')->execute([$nyata,$w['id'],$U]);
        flash($b['nama'].' mengembalikan '.rp($nyata).' ke '.$w['nama'].'.');
    } redirect($back);
}

// ════════════ HUTANG (kita berhutang ke orang) ════════════
case 'add_hutang': {   // Catat hutang: uang masuk ke rekening kita (kamu menerima pinjaman)
    $nama=str_('nama'); $total=num('total'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $tempo=($_POST['tempo_tgl']??'')?:null; $catatan=str_('catatan');
    if($nama && $total>0){
        if(!$w){ flash('Pilih dulu dompet tujuan uang masuk.','err'); redirect($back); }
        $tgl = $tempo ? (int)date('j',strtotime($tempo)) : (int)date('j');
        $pdo->prepare('UPDATE dompet SET saldo=saldo+? WHERE id=? AND user_id=?')->execute([$total,$w['id'],$U]);
        $pdo->prepare('INSERT INTO tagihan (user_id,emoji,tint,nama,deskripsi,jumlah,tgl_jatuh_tempo,jenis,total,terbayar,catatan,berulang,tenor,ingatkan,selesai_tgl) VALUES (?,?,?,?,?,?,?,?,?,0,?,0,0,?,?)')
            ->execute([$U,'🙏','#f7e6da',$nama,'Masuk ke '.$w['nama'],$total,$tgl,'hutang',$total,$catatan,$tempo?1:0,$tempo]);
        flash('Dicatat: kamu berhutang '.rp($total).' ke '.$nama.' (uang masuk ke '.$w['nama'].').');
    } redirect($back);
}
case 'edit_hutang': {  // ubah catatan hutang (tidak memindahkan uang)
    $id=(int)$_POST['id']; $nama=str_('nama'); $tempo=($_POST['tempo_tgl']??'')?:null;
    $tgl=$tempo?(int)date('j',strtotime($tempo)):(int)date('j');
    $pdo->prepare('UPDATE tagihan SET nama=?,catatan=?,selesai_tgl=?,tgl_jatuh_tempo=?,ingatkan=? WHERE id=? AND user_id=? AND jenis=\'hutang\'')
        ->execute([$nama,str_('catatan'),$tempo,$tgl,$tempo?1:0,$id,$U]);
    redirect($back);
}
case 'bayar_hutang': {  // Kamu membayar hutang: uang keluar dari rekening
    $id=(int)$_POST['id']; $bayar=num('bayar'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $s=$pdo->prepare('SELECT * FROM tagihan WHERE id=? AND user_id=? AND jenis=\'hutang\''); $s->execute([$id,$U]); $b=$s->fetch();
    if($b && $bayar>0){
        $baru=min($b['total'],$b['terbayar']+$bayar); $nyata=$baru-(float)$b['terbayar'];
        if($nyata<=0){ flash('Hutang ini sudah lunas.','err'); redirect($back); }
        if(!$w){ flash('Pilih dulu dompet sumber pembayaran.','err'); redirect($back); }
        if((float)$w['saldo'] < $nyata){ flash('Saldo '.$w['nama'].' tidak cukup (tersisa '.rp($w['saldo']).').','err'); redirect($back); }
        $lunas=$baru>=$b['total']?1:0;
        $pdo->prepare('UPDATE tagihan SET terbayar=?,sudah_bayar=? WHERE id=? AND user_id=?')->execute([$baru,$lunas,$id,$U]);
        $pdo->prepare('UPDATE dompet SET saldo=saldo-? WHERE id=? AND user_id=?')->execute([$nyata,$w['id'],$U]);
        flash('Bayar hutang ke '.$b['nama'].' '.rp($nyata).' dari '.$w['nama'].'.');
    } redirect($back);
}

// ════════════ TUGAS (KERJAAN) ════════════
case 'add_tugas': {
    if(str_('judul')) $pdo->prepare('INSERT INTO tugas (user_id,judul,catatan,tanggal,waktu,prioritas) VALUES (?,?,?,?,?,?)')
        ->execute([$U,str_('judul'),str_('catatan'),$_POST['tanggal']?:null,str_('waktu'),$_POST['prioritas']??'sedang']);
    redirect($back);
}
case 'edit_tugas': {
    $pdo->prepare('UPDATE tugas SET judul=?,catatan=?,tanggal=?,waktu=?,prioritas=? WHERE id=? AND user_id=?')
        ->execute([str_('judul'),str_('catatan'),$_POST['tanggal']?:null,str_('waktu'),$_POST['prioritas']??'sedang',(int)$_POST['id'],$U]);
    redirect($back);
}
case 'delete_tugas': { $pdo->prepare('DELETE FROM tugas WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }
case 'toggle_tugas': { $pdo->prepare('UPDATE tugas SET selesai=1-selesai WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }

// ════════════ TABUNGAN ════════════
case 'add_tabungan': {
    $judul=str_('judul'); $target=num('target');
    $saldoAwal=num('saldo_awal'); $perBulan=num('per_bulan'); $catatan=str_('catatan');
    $j=jadwalPost();
    $tgl   = $j['selesai'];   // target tercapai = tanggal selesai (jika "Sampai tanggal")
    $ingat = ($j['ingatkan'] && $j['freq']==='bulanan') ? $j['tgl'] : 0; // pengingat bulanan tgl X
    if($judul && $target>0){
        $bln=bulanKeTanggal($tgl);
        if($perBulan<=0 && $bln>0) $perBulan=ceil(max(0,$target-$saldoAwal)/$bln);
        if(!$catatan){
            if($perBulan>0 && $bln>0) $catatan='Nabung '.rpShort($perBulan).'/bln → '.$bln.' bulan lagi';
            elseif($perBulan>0) $catatan='Nabung '.rpShort($perBulan).'/bln';
        }
        $pdo->prepare('INSERT INTO tabungan (user_id,emoji,judul,tint,terkumpul,target,per_bulan,warna,catatan,target_tanggal,ingat_tgl,mulai_tgl,ingatkan,frekuensi,freq_hari,freq_tgl,freq_bulan,selesai_tgl,auto_setor,sumber_dompet_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$U,$_POST['emoji']??'🎯',$judul,$_POST['tint']??'#e3ecf6',$saldoAwal,$target,$perBulan,$_POST['warna']??'#3b6fb0',$catatan,$tgl,$ingat,$j['mulai'],$j['ingatkan'],$j['freq'],$j['hari'],$j['tgl'],$j['bulan'],$j['selesai'],isset($_POST['auto_setor'])?1:0,((int)($_POST['sumber_dompet_id']??0))?:null]);
    } redirect($back);
}
case 'edit_tabungan': {
    $j=jadwalPost();
    $tgl   = $j['selesai'];
    $ingat = ($j['ingatkan'] && $j['freq']==='bulanan') ? $j['tgl'] : 0;
    $pdo->prepare('UPDATE tabungan SET judul=?,target=?,per_bulan=?,catatan=?,emoji=?,target_tanggal=?,ingat_tgl=?,mulai_tgl=?,ingatkan=?,frekuensi=?,freq_hari=?,freq_tgl=?,freq_bulan=?,selesai_tgl=?,auto_setor=?,sumber_dompet_id=? WHERE id=? AND user_id=?')
        ->execute([str_('judul'),num('target'),num('per_bulan'),str_('catatan'),$_POST['emoji']??'🎯',$tgl,$ingat,$j['mulai'],$j['ingatkan'],$j['freq'],$j['hari'],$j['tgl'],$j['bulan'],$j['selesai'],isset($_POST['auto_setor'])?1:0,((int)($_POST['sumber_dompet_id']??0))?:null,(int)$_POST['id'],$U]);
    redirect($back);
}
case 'delete_tabungan': { $pdo->prepare('DELETE FROM tabungan WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }
case 'tambah_dana': {   // NABUNG: pindahkan uang dari rekening → tabungan
    $gid=(int)$_POST['id']; $dana=num('dana'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $g=$pdo->prepare('SELECT * FROM tabungan WHERE id=? AND user_id=?'); $g->execute([$gid,$U]); $g=$g->fetch();
    if($g && $dana>0){
        if(!$w){ flash('Pilih dulu rekening sumber uangnya.','err'); redirect($back); }
        if((float)$w['saldo'] < $dana){ flash('Saldo '.$w['nama'].' tidak cukup (tersisa '.rp($w['saldo']).').','err'); redirect($back); }
        $pdo->prepare('UPDATE dompet SET saldo=saldo-? WHERE id=? AND user_id=?')->execute([$dana,$w['id'],$U]);
        $pdo->prepare('UPDATE tabungan SET terkumpul=terkumpul+?, terakhir_setor=CURDATE() WHERE id=? AND user_id=?')->execute([$dana,$gid,$U]);
        flash('Berhasil menabung '.rp($dana).' dari '.$w['nama'].'.');
    } redirect($back);
}
case 'kurangi_dana': {  // TARIK: pindahkan uang dari tabungan → rekening
    $gid=(int)$_POST['id']; $dana=num('dana'); $w=dompetById($pdo,$U,$_POST['dompet_id']??0);
    $g=$pdo->prepare('SELECT * FROM tabungan WHERE id=? AND user_id=?'); $g->execute([$gid,$U]); $g=$g->fetch();
    if($g && $dana>0){
        if(!$w){ flash('Pilih dulu rekening tujuan penarikan.','err'); redirect($back); }
        if((float)$g['terkumpul'] < $dana){ flash('Saldo tabungan tidak cukup (terkumpul '.rp($g['terkumpul']).').','err'); redirect($back); }
        $pdo->prepare('UPDATE tabungan SET terkumpul=terkumpul-? WHERE id=? AND user_id=?')->execute([$dana,$gid,$U]);
        $pdo->prepare('UPDATE dompet SET saldo=saldo+? WHERE id=? AND user_id=?')->execute([$dana,$w['id'],$U]);
        flash('Berhasil menarik '.rp($dana).' ke '.$w['nama'].'.');
    } redirect($back);
}

// ════════════ ANGGARAN ════════════
case 'add_anggaran': {
    $kat=str_('kategori'); $batas=num('batas'); $on=isset($_POST['pakai_batas']);
    $j=jadwalPost();
    $freq = $on ? $j['freq'] : 'static';            // static = anggaran tetap (tanpa reset)
    $selesai = ($on) ? $j['selesai'] : null;        // tanggal selesai → masuk histori
    if(!$kat || $batas<=0) redirect($back);
    if(anggaranKategoriAda($pdo,$kat)) redirect($back.'&err=dup');
    $km=katMeta($pdo,$kat);
    $pdo->prepare('INSERT INTO anggaran (user_id,emoji,kategori,tint,batas,bulan,tahun,periode,frekuensi,mulai_tgl,selesai_tgl,freq_hari,freq_tgl,freq_bulan,ingatkan,aktif) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,1)')
        ->execute([$U,$km['emoji'],$kat,$km['tint'],$batas,(int)date('n'),(int)date('Y'),$freq,$freq,date('Y-m-d'),$selesai,$j['hari'],$j['tgl'],$j['bulan']]);
    logAng($pdo,$U,$kat,'buat',$batas,$batas);
    redirect($back);
}
case 'edit_anggaran': {
    $id=(int)$_POST['id']; $kat=str_('kategori'); $batas=num('batas'); $on=isset($_POST['pakai_batas']);
    $j=jadwalPost();
    $freq = $on ? $j['freq'] : 'static';
    $selesai = ($on) ? $j['selesai'] : null;
    if($kat && anggaranKategoriAda($pdo,$kat,$id)) redirect($back.'&err=dup');
    if($kat){ $km=katMeta($pdo,$kat);
        $pdo->prepare('UPDATE anggaran SET kategori=?,emoji=?,tint=?,batas=?,frekuensi=?,selesai_tgl=?,freq_hari=?,freq_tgl=?,freq_bulan=?,aktif=1 WHERE id=? AND user_id=?')->execute([$kat,$km['emoji'],$km['tint'],$batas,$freq,$selesai,$j['hari'],$j['tgl'],$j['bulan'],$id,$U]);
    } else $pdo->prepare('UPDATE anggaran SET batas=?,frekuensi=?,selesai_tgl=?,freq_hari=?,freq_tgl=?,freq_bulan=?,aktif=1 WHERE id=? AND user_id=?')->execute([$batas,$freq,$selesai,$j['hari'],$j['tgl'],$j['bulan'],$id,$U]);
    redirect($back);
}
case 'delete_anggaran': { $pdo->prepare('DELETE FROM anggaran WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }
case 'adjust_anggaran': {
    $id=(int)$_POST['id']; $jml=num('jumlah'); $tipe=$_POST['tipe']??'tambah';
    if($id && $jml>0){
        if($tipe==='kurang') $pdo->prepare('UPDATE anggaran SET batas=GREATEST(0,batas-?) WHERE id=? AND user_id=?')->execute([$jml,$id,$U]);
        else $pdo->prepare('UPDATE anggaran SET batas=batas+? WHERE id=? AND user_id=?')->execute([$jml,$id,$U]);
        $a=$pdo->prepare('SELECT kategori,batas FROM anggaran WHERE id=? AND user_id=?'); $a->execute([$id,$U]); $r=$a->fetch();
        if($r) logAng($pdo,$U,$r['kategori'],$tipe,$jml,$r['batas']);
    } redirect($back);
}

// ════════════ CATATAN / AGENDA ════════════
case 'add_catatan': {
    $ulang=$_POST['ulang']??'tidak'; if(!in_array($ulang,['tidak','tahunan','bulanan','mingguan']))$ulang='tidak';
    $lead=$_POST['ingat_lead']??'hari'; if(!in_array($lead,['hari','minggu','bulan']))$lead='hari';
    if(str_('judul') && $_POST['tanggal'])
        $pdo->prepare('INSERT INTO catatan (user_id,tanggal,judul,isi,warna,ingatkan,ulang,ingat_lead) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$U,$_POST['tanggal'],str_('judul'),str_('isi'),$_POST['warna']??'#8a5fb0',isset($_POST['ingatkan'])?1:0,$ulang,$lead]);
    redirect($back);
}
case 'edit_catatan': {
    $ulang=$_POST['ulang']??'tidak'; if(!in_array($ulang,['tidak','tahunan','bulanan','mingguan']))$ulang='tidak';
    $lead=$_POST['ingat_lead']??'hari'; if(!in_array($lead,['hari','minggu','bulan']))$lead='hari';
    $pdo->prepare('UPDATE catatan SET judul=?,isi=?,tanggal=?,ingatkan=?,ulang=?,ingat_lead=? WHERE id=? AND user_id=?')
        ->execute([str_('judul'),str_('isi'),$_POST['tanggal'],isset($_POST['ingatkan'])?1:0,$ulang,$lead,(int)$_POST['id'],$U]);
    redirect($back);
}
case 'delete_catatan': { $pdo->prepare('DELETE FROM catatan WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }

// ════════════ DOMPET ════════════
case 'add_dompet': {
    if(str_('nama')){
        $ron=isset($_POST['reset_on']); $rtgl=$ron?max(1,min(31,(int)($_POST['reset_tgl']??1))):0; $ubaru=$ron?num('uang_baru'):0;
        $rter=null; if($rtgl>0){ $eff=min($rtgl,(int)date('t')); if((int)date('j')>=$eff) $rter=date('Y-m-').str_pad($eff,2,'0',STR_PAD_LEFT); }
        $pdo->prepare('INSERT INTO dompet (user_id,nama,emoji,saldo,reset_tgl,uang_baru,reset_terakhir) VALUES (?,?,?,?,?,?,?)')->execute([$U,str_('nama'),$_POST['emoji']??'💵',num('saldo'),$rtgl,$ubaru,$rter]);
    } redirect($back);
}
case 'tambah_saldo': {
    $id=(int)($_POST['dompet_id']??0); $jml=num('jumlah'); $tipe=$_POST['tipe']??'tambah';
    $c=$pdo->prepare('SELECT COUNT(*) FROM dompet WHERE user_id=?'); $c->execute([$U]); $ada=(int)$c->fetchColumn();
    if($ada===0) redirect($back.'&err=nodompet');
    if($id && $jml>0){
        if($tipe==='kurang') $pdo->prepare('UPDATE dompet SET saldo=GREATEST(0,saldo-?) WHERE id=? AND user_id=?')->execute([$jml,$id,$U]);
        else $pdo->prepare('UPDATE dompet SET saldo=saldo+? WHERE id=? AND user_id=?')->execute([$jml,$id,$U]);
    }
    redirect($back);
}
case 'transfer_saldo': {
    $from=(int)($_POST['from_id']??0); $to=(int)($_POST['to_id']??0); $jml=num('jumlah');
    if($from && $to && $from!==$to && $jml>0){
        $s=$pdo->prepare('SELECT saldo FROM dompet WHERE id=? AND user_id=?');
        $s->execute([$from,$U]); $fromOk=$s->fetchColumn();
        $s->execute([$to,$U]);   $toOk=$s->fetchColumn();
        if($fromOk!==false && $toOk!==false){
            $pdo->prepare('UPDATE dompet SET saldo=GREATEST(0,saldo-?) WHERE id=? AND user_id=?')->execute([$jml,$from,$U]);
            $pdo->prepare('UPDATE dompet SET saldo=saldo+? WHERE id=? AND user_id=?')->execute([$jml,$to,$U]);
        }
    }
    redirect($back);
}
case 'edit_dompet': {
    $ron=isset($_POST['reset_on']); $rtgl=$ron?max(1,min(31,(int)($_POST['reset_tgl']??1))):0; $ubaru=$ron?num('uang_baru'):0;
    $rter=null; if($rtgl>0){ $eff=min($rtgl,(int)date('t')); if((int)date('j')>=$eff) $rter=date('Y-m-').str_pad($eff,2,'0',STR_PAD_LEFT); }
    $pdo->prepare('UPDATE dompet SET nama=?,emoji=?,saldo=?,reset_tgl=?,uang_baru=?,reset_terakhir=? WHERE id=? AND user_id=?')->execute([str_('nama'),$_POST['emoji']??'💵',num('saldo'),$rtgl,$ubaru,$rter,(int)$_POST['id'],$U]);
    redirect($back);
}
case 'delete_dompet': { $pdo->prepare('DELETE FROM dompet WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }

// ════════════ KATEGORI ════════════
case 'add_kategori': {
    if(str_('nama')) $pdo->prepare('INSERT INTO kategori (user_id,nama,emoji,tint,warna,tipe) VALUES (?,?,?,?,?,?)')
        ->execute([$U,str_('nama'),$_POST['emoji']??'🏷️',$_POST['tint']??'#fbf6ec',$_POST['warna']??'#5c5345',$_POST['tipe']??'keluar']);
    redirect($back);
}
case 'delete_kategori': { $pdo->prepare('DELETE FROM kategori WHERE id=? AND user_id=?')->execute([(int)$_POST['id'],$U]); redirect($back); }

// ════════════ NOTIFIKASI ════════════
case 'mark_notif': { $pdo->prepare('INSERT IGNORE INTO notif_dibaca (user_id,notif_key) VALUES (?,?)')->execute([$U,$_POST['key']??'']); redirect($back); }
case 'mark_all_notif': {
    foreach(getNotifs($pdo,(int)date('n'),(int)date('Y')) as $n)
        $pdo->prepare('INSERT IGNORE INTO notif_dibaca (user_id,notif_key) VALUES (?,?)')->execute([$U,$n['key']]);
    redirect($back);
}

// ════════════ PROFIL / AKUN ════════════
case 'restore_data': {
    if(empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) redirect($back.'&msg=restorefail');
    $d=json_decode(file_get_contents($_FILES['file']['tmp_name']),true);
    if(!is_array($d) || ($d['_app']??'')!=='uangku') redirect($back.'&msg=restorefail');
    $tables=['dompet','kategori','transaksi','anggaran','anggaran_log','tabungan','tagihan','tugas','catatan'];
    try{
        $pdo->beginTransaction();
        foreach($tables as $t) $pdo->prepare("DELETE FROM `$t` WHERE user_id=?")->execute([$U]);
        foreach($tables as $t){
            if(empty($d[$t]) || !is_array($d[$t])) continue;
            foreach($d[$t] as $row){
                if(!is_array($row)) continue;
                $row['user_id']=$U;
                $cols=array_keys($row); $ph=implode(',',array_fill(0,count($cols),'?'));
                $colSql=implode(',',array_map(fn($c)=>"`$c`",$cols));
                $pdo->prepare("INSERT INTO `$t` ($colSql) VALUES ($ph)")->execute(array_values($row));
            }
        }
        $pdo->commit();
        redirect($back.'&msg=restoreok');
    }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); redirect($back.'&msg=restorefail'); }
}
case 'update_profil': {
    $cur=$_POST['currency']??'Rp'; if(!in_array($cur,['Rp','$','€','£','¥','RM','S$','฿'])) $cur='Rp';
    // Upload foto profil (opsional)
    if(!empty($_FILES['foto']['tmp_name']) && is_uploaded_file($_FILES['foto']['tmp_name'])){
        if($_FILES['foto']['size']<=2*1024*1024){
            $info=@getimagesize($_FILES['foto']['tmp_name']);
            $extMap=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'];
            if($info && isset($extMap[$info[2]])){
                $dir=__DIR__.'/uploads/avatars'; if(!is_dir($dir)) @mkdir($dir,0755,true);
                $fn='u'.$U.'_'.bin2hex(random_bytes(6)).'.'.$extMap[$info[2]];
                if(@move_uploaded_file($_FILES['foto']['tmp_name'],$dir.'/'.$fn)){
                    // hapus foto lama
                    if(!empty($me['avatar_img']) && is_file($dir.'/'.$me['avatar_img'])) @unlink($dir.'/'.$me['avatar_img']);
                    $pdo->prepare('UPDATE users SET avatar_img=? WHERE id=?')->execute([$fn,$U]);
                }
            }
        }
    }
    $pdo->prepare('UPDATE users SET nama=?,email=?,avatar=?,currency=? WHERE id=?')->execute([str_('nama'),str_('email'),$_POST['avatar']??'🧑',$cur,$U]);
    redirect($back);
}
case 'update_password': {
    $lama=$_POST['lama']??''; $baru=$_POST['baru']??'';
    if(password_verify($lama,$me['password']) && strlen($baru)>=4)
        $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($baru,PASSWORD_DEFAULT),$U]);
    redirect($back.'&msg=pw');
}
case 'set_pin': {
    if(isset($_POST['hapus'])){ $pdo->prepare('UPDATE users SET pin=NULL WHERE id=?')->execute([$U]); $_SESSION['pin_ok']=1; flash('PIN dimatikan.'); redirect($back); }
    $pin=preg_replace('/\D/','',$_POST['pin']??''); $pin2=preg_replace('/\D/','',$_POST['pin2']??'');
    if(strlen($pin)!==6){ flash('PIN harus tepat 6 angka.','err'); redirect($back); }
    if($pin!==$pin2){ flash('Konfirmasi PIN tidak cocok.','err'); redirect($back); }
    $pdo->prepare('UPDATE users SET pin=? WHERE id=?')->execute([password_hash($pin,PASSWORD_DEFAULT),$U]);
    $_SESSION['pin_ok']=1; // jangan langsung terkunci setelah set/ubah PIN
    flash('PIN berhasil disimpan.');
    redirect($back);
}
case 'toggle_dark': { $pdo->prepare('UPDATE users SET dark_mode=1-dark_mode WHERE id=?')->execute([$U]); redirect($back); }

default: redirect($back);
}
