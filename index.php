<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/auth.php';
$me = requireLogin($pdo);
$GLOBALS['UANGKU_CUR'] = $me['currency'] ?? 'Rp';
$GLOBALS['ME'] = $me;   // dipakai topbar (profil/avatar/mode)
prosesTransaksiRutin($pdo); prosesSetoranAuto($pdo);   // jalankan otomatis yang jatuh tempo

$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$page  = $_GET['page'] ?? 'beranda';
if ($page==='kerjaan') $page='kalender'; // kerjaan kini bagian dari kalender
$valid = ['beranda','transaksi','kalender','anggaran','tabungan','tagihan','pengaturan','tentang'];
if (!in_array($page,$valid)) $page='beranda';

$notifs = getNotifs($pdo,$bulan,$tahun);
$dark   = $me['dark_mode'] ? 'dark' : '';

$MENU = [
  ['beranda','Beranda','home'],['transaksi','Analisa','chart'],['kalender','Kalender & Agenda','cal'],
  ['_sep','',''],
  ['anggaran','Anggaran','budget'],['tabungan','Tabungan','savings'],['tagihan','Tagihan','bill'],
  ['_sep','',''],
  ['pengaturan','Pengaturan','settings'],
];
$BNAV = [['beranda','Beranda','home'],['transaksi','Analisa','chart'],['kalender','Agenda','cal'],['anggaran','Anggaran','budget'],['tabungan','Tabungan','savings'],['tagihan','Tagihan','bill'],['pengaturan','Akun','settings']];
?>
<!DOCTYPE html>
<html lang="id" class="<?= $dark ? 'dark' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="color-scheme" content="<?= $dark ? 'dark' : 'light' ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Uangku — Aplikasi Keuangan</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="<?= $dark ? '#0f141c' : '#eef1f4' ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="assets/style.css?v=20">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Hanken+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
<script>
  // pulihkan status sidebar collapse & mode gelap sebelum render (anti-flash putih)
  if(localStorage.getItem('sb')==='1') document.documentElement.classList.add('pre-collapsed');
  if(localStorage.getItem('dk')==='1') document.documentElement.classList.add('dark');
  // terapkan warna aksen pilihan user lebih awal (anti-flash)
  try{var _ac=localStorage.getItem('accent'); if(_ac){var _s=document.createElement('style'); _s.id='accent-style'; _s.textContent=':root,body.dark{--terra:'+_ac+'}'; document.head.appendChild(_s);}}catch(e){}
</script>
<style>html.pre-collapsed body{--sb:74px}</style>
</head>
<body class="<?= $dark ?>">

<!-- ── Sidebar ── -->
<aside id="sidebar">
  <button class="sb-collapse" onclick="toggleSidebar()" title="Perkecil menu"><?= icon('chevL',15) ?></button>
  <div class="sb-logo">
    <div class="mark">U</div>
    <div class="txt"><div class="wm">Uangku</div><div class="tg">Keuangan pribadi</div></div>
  </div>
  <nav class="sb-nav">
    <?php foreach($MENU as [$id,$label,$ic]): ?>
      <?php if($id==='_sep'): ?><div class="sb-sep"></div>
      <?php else: ?>
        <a class="sb-item <?= $page===$id?'active':'' ?>" href="?page=<?= $id ?>"><?= icon($ic,18) ?><span><?= $label ?></span></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sb-foot">
    <button class="sb-add" onclick="openModal('m-tx')"><?= icon('plus',18,'#fff',2.5) ?><span>Tambah Transaksi</span></button>
    <div style="display:flex;align-items:center;gap:6px;margin-top:8px">
      <a href="?page=pengaturan" class="sb-user" style="flex:1;margin-top:0">
        <div class="av" style="<?= !empty($me['avatar_img'])?'background-image:url(uploads/avatars/'.e($me['avatar_img']).');background-size:cover;background-position:center':'' ?>"><?= !empty($me['avatar_img'])?'':e($me['avatar']) ?></div>
        <div class="nm"><?= e($me['nama']) ?></div>
      </a>
      <form method="get" action="logout.php" data-confirm="Yakin mau keluar / logout dari akun ini?" data-confirm-type="danger" data-confirm-icon="🚪" data-confirm-ok="Ya, keluar">
        <button type="submit" class="sb-logout" title="Logout"><?= icon('export',18,'currentColor') ?></button>
      </form>
    </div>
  </div>
</aside>

<!-- ── Main ── -->
<main id="main">
  <div id="content">
    <?php if(!empty($_SESSION['flash'])): $fl=$_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="flash flash-<?= $fl['t']==='err'?'err':'ok' ?>"><span><?= $fl['t']==='err'?'⚠️':'✅' ?></span><div><?= e($fl['m']) ?></div></div>
    <?php endif; ?>
    <?php require __DIR__ . "/pages/{$page}.php"; ?>
  </div>
</main>

<!-- ── Bottom nav mobile ── -->
<nav id="bnav"><div class="inner">
  <?php foreach($BNAV as [$id,$label,$ic]): $on=($page===$id); ?>
    <a class="bn <?= $on?'active':'' ?>" href="?page=<?= $id ?>"><?= icon($ic,20,'currentColor',$on?2.1:1.8) ?><span><?= $label ?></span></a>
  <?php endforeach; ?>
</div></nav>
<?php if($page==='beranda'): /* FAB tambah hanya di dashboard */ ?>
<button id="fab" onclick="openModal('m-tx')"><?= icon('plus',24,'#fff',2.4) ?></button>
<?php endif; ?>

<?php include __DIR__ . '/inc/modals.php'; ?>

<!-- Modal konfirmasi (pengganti confirm bawaan) -->
<div class="modal-bg" id="m-confirm">
  <div class="modal" style="max-width:360px"><div class="mbody" style="padding:26px 24px 22px;text-align:center">
    <div id="cf-icon" style="width:58px;height:58px;border-radius:18px;background:var(--redT);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px">⚠️</div>
    <div id="cf-title" style="font-family:var(--serif);font-size:20px;font-weight:600;margin-bottom:6px">Konfirmasi</div>
    <div id="cf-msg" style="font-size:13.5px;color:var(--soft);line-height:1.5;margin-bottom:22px">Yakin?</div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-ghost" style="flex:1;justify-content:center;padding:13px" onclick="closeModal('m-confirm')">Batal</button>
      <button id="cf-ok" class="btn" style="flex:1;justify-content:center;padding:13px;background:var(--red);color:#fff">Ya, lanjut</button>
    </div>
  </div></div>
</div>

<script>
// Token CSRF — disuntik otomatis ke semua form POST
window.CSRF=<?= json_encode(csrf_token()) ?>;
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('form').forEach(function(f){
    if((f.getAttribute('method')||'').toLowerCase()==='post' && !f.querySelector('input[name="_csrf"]')){
      var i=document.createElement('input'); i.type='hidden'; i.name='_csrf'; i.value=window.CSRF; f.appendChild(i);
    }
  });
});
// sinkronkan preferensi mode gelap (dari server) untuk anti-flash di navigasi berikutnya
try{ localStorage.setItem('dk','<?= $dark?1:0 ?>'); document.documentElement.classList.toggle('dark', <?= $dark?'true':'false' ?>); }catch(e){}
// Ganti mode gelap/terang (dipakai tombol topbar & switch Pengaturan) — tanpa reload
function toggleDark(){
  var on=!document.documentElement.classList.contains('dark');
  document.documentElement.classList.toggle('dark',on); document.body.classList.toggle('dark',on);
  try{localStorage.setItem('dk',on?'1':'0');}catch(e){}
  document.querySelectorAll('.dark-toggle-ic').forEach(function(el){el.textContent=on?'☀️':'🌙';});
  var st=document.getElementById('dark-state'); if(st)st.textContent=on?'Aktif':'Nonaktif';
  var sw=document.getElementById('dark-sw'); if(sw)sw.style.background=on?'var(--green)':'#d8cfbe';
  var kn=document.getElementById('dark-knob'); if(kn)kn.style.left=on?'22px':'2.5px';
  fetch('actions.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=toggle_dark&_csrf='+encodeURIComponent(window.CSRF||'')}).catch(function(){});
}
window.CUR=<?= json_encode(curSym()) ?>;
// Animasi hitung-naik untuk angka besar (elemen .cup data-v)
function countUp(){
  document.querySelectorAll('.cup').forEach(function(el){
    if(el.dataset.done) return; el.dataset.done='1';
    var finalTxt=el.textContent, to=parseFloat(el.dataset.v)||0, sym=(window.CUR||'Rp');
    var neg=to<0, abs=Math.abs(to), dur=650, st=performance.now();
    if(abs<1){ return; }
    function step(t){ var p=Math.min(1,(t-st)/dur), v=abs*(1-Math.pow(1-p,3));
      el.textContent=sym+(neg?'-':'')+Math.round(v).toLocaleString('id-ID');
      if(p<1) requestAnimationFrame(step); else el.textContent=finalTxt; }
    requestAnimationFrame(step);
  });
}
// Pilih warna aksen tema (disimpan di perangkat)
function setAccent(hex){
  try{localStorage.setItem('accent',hex);}catch(e){}
  var s=document.getElementById('accent-style'); if(!s){s=document.createElement('style');s.id='accent-style';document.head.appendChild(s);}
  s.textContent=':root,body.dark{--terra:'+hex+'}';
  document.querySelectorAll('.acc-sw').forEach(function(x){x.classList.toggle('on',x.dataset.c===hex);});
}
document.addEventListener('DOMContentLoaded',countUp);
function openModal(id){ var m=document.getElementById(id); if(m){m.classList.add('open');document.body.style.overflow='hidden';} }
function closeModal(id){ document.getElementById(id).classList.remove('open');document.body.style.overflow=''; }
function fmtRupiah(el){ let v=el.value.replace(/\D/g,''); el.value=v.replace(/\B(?=(\d{3})+(?!\d))/g,'.'); }
// Simpan & pulihkan posisi scroll (agar tidak lompat ke atas saat ganti filter)
function saveScroll(){ try{sessionStorage.setItem('uScroll',String(window.scrollY));}catch(e){} }
window.addEventListener('DOMContentLoaded',function(){ try{var y=sessionStorage.getItem('uScroll'); if(y!==null){window.scrollTo(0,parseInt(y));sessionStorage.removeItem('uScroll');}}catch(e){} });
function toggleSidebar(){ document.body.classList.toggle('sb-collapsed'); localStorage.setItem('sb', document.body.classList.contains('sb-collapsed')?'1':'0'); }
if(localStorage.getItem('sb')==='1') document.body.classList.add('sb-collapsed');
document.documentElement.classList.remove('pre-collapsed');
// modal: tutup klik backdrop
document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id);}));

// ── Konfirmasi modern (ganti confirm bawaan) ──
// Pakai: <form ... data-confirm="Pesan" data-confirm-type="danger|primary" data-confirm-icon="🗑️">
var _cfForm=null;
document.addEventListener('submit',function(e){
  var f=e.target; if(!f.dataset||!f.dataset.confirm||f.dataset.confirmed) return;
  e.preventDefault(); _cfForm=f;
  var type=f.dataset.confirmType||'danger';
  document.getElementById('cf-msg').textContent=f.dataset.confirm;
  document.getElementById('cf-icon').textContent=f.dataset.confirmIcon||(type==='danger'?'🗑️':'❓');
  document.getElementById('cf-icon').style.background=type==='danger'?'var(--redT)':'var(--terraT)';
  var ok=document.getElementById('cf-ok'); ok.style.background=type==='danger'?'var(--red)':'var(--terra)';
  ok.textContent=f.dataset.confirmOk||'Ya, lanjut';
  openModal('m-confirm');
},true);
document.getElementById('cf-ok').addEventListener('click',function(){
  if(_cfForm){ _cfForm.dataset.confirmed='1'; closeModal('m-confirm'); _cfForm.submit(); }
});
// modal pemilih emoji generik
function pickEmoji(grpEl, em, tint, warna){
  grpEl.querySelectorAll('.ei').forEach(x=>{x.classList.remove('on');x.style.borderColor='var(--line)';x.style.background='var(--card)';});
  event.currentTarget.classList.add('on');
  if(warna){event.currentTarget.style.borderColor=warna;} else {event.currentTarget.style.borderColor='var(--terra)';}
  if(tint)event.currentTarget.style.background=tint;
  var scope=grpEl.closest('form')||grpEl.parentElement;
  var f=scope.querySelector('input[data-emoji]'); if(f)f.value=em;
  var ft=scope.querySelector('input[data-tint]'); if(ft&&tint)ft.value=tint;
  var fw=scope.querySelector('input[data-warna]'); if(fw&&warna)fw.value=warna;
}
if('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(()=>{});
// Kunci menu bawah ke area terlihat (atasi bar alamat HP yang bikin menu "turun")
(function(){
  var bn=document.getElementById('bnav'), vv=window.visualViewport;
  if(!bn||!vv) return;
  function fix(){
    if(getComputedStyle(bn).display==='none'){ bn.style.top=''; bn.style.bottom=''; return; }
    bn.style.bottom='auto';
    bn.style.top=Math.round(vv.offsetTop + vv.height - bn.offsetHeight)+'px';
  }
  vv.addEventListener('resize',fix); vv.addEventListener('scroll',fix);
  window.addEventListener('orientationchange',fix);
  setTimeout(fix,60); fix();
})();
</script>
</body>
</html>
