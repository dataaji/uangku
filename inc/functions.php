<?php
// ============================================================
// Helper & Query — Uangku v2
// ============================================================

function rp($n){ return 'Rp' . number_format(round($n), 0, ',', '.'); }
function rpShort($n){
    $neg=$n<0?'-':''; $n=abs((float)$n);
    if($n>=1e12)     $s=rtrim(rtrim(number_format($n/1e12,2,',','.'),'0'),',').'T';
    elseif($n>=1e9)  $s=rtrim(rtrim(number_format($n/1e9,2,',','.'),'0'),',').'M';
    elseif($n>=1e6)  $s=rtrim(rtrim(number_format($n/1e6,2,',','.'),'0'),',').'jt';
    elseif($n>=1e3)  $s=round($n/1e3).'rb';
    else             $s=number_format($n,0,',','.');
    return 'Rp'.$neg.$s;
}
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// ── User aktif (untuk pemisahan data tiap akun) ─────────────
function uid(){ return (int)($_SESSION['uid'] ?? 0); }

$NAMA_BULAN=[1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$NAMA_HARI=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$CAL_COLORS=['green'=>'#2f7d5d','red'=>'#c0392b','amber'=>'#d99a2b','blue'=>'#3b6fb0','purple'=>'#8a5fb0'];

// ── Kategori (dari DB) — per user ───────────────────────────
function getKategori($pdo,$tipe=null){
    if($tipe){ $s=$pdo->prepare('SELECT * FROM kategori WHERE user_id=? AND tipe=? ORDER BY id'); $s->execute([uid(),$tipe]); return $s->fetchAll(); }
    $s=$pdo->prepare('SELECT * FROM kategori WHERE user_id=? ORDER BY tipe DESC, id'); $s->execute([uid()]); return $s->fetchAll();
}
function katMeta($pdo,$nama){
    static $cache=null; static $cuid=null;
    if($cache===null || $cuid!==uid()){ $cache=[]; $cuid=uid();
        $s=$pdo->prepare('SELECT * FROM kategori WHERE user_id=?'); $s->execute([uid()]);
        foreach($s->fetchAll() as $k) $cache[$k['nama']]=$k; }
    return $cache[$nama] ?? ['emoji'=>'🏷️','tint'=>'#fbf6ec','warna'=>'#5c5345'];
}

// ── Dompet ───────────────────────────────────────────────────
// Auto-reset: saat tanggal reset tiba, saldo dompet jadi "uang baru" (uang saku).
function prosesResetDompet($pdo){
    static $done=false; if($done) return; $done=true;
    $u=uid(); if(!$u) return;
    $hari=(int)date('j'); $akhir=(int)date('t');
    $s=$pdo->prepare('SELECT id,reset_tgl,uang_baru,reset_terakhir FROM dompet WHERE user_id=? AND reset_tgl>0'); $s->execute([$u]);
    foreach($s->fetchAll() as $w){
        $eff=min((int)$w['reset_tgl'],$akhir);
        if($hari>=$eff){
            $tgReset=date('Y-m-').str_pad($eff,2,'0',STR_PAD_LEFT);
            if(empty($w['reset_terakhir']) || $w['reset_terakhir']<$tgReset)
                $pdo->prepare('UPDATE dompet SET saldo=?, reset_terakhir=? WHERE id=? AND user_id=?')->execute([$w['uang_baru'],$tgReset,$w['id'],$u]);
        }
    }
}
function getDompet($pdo){ prosesResetDompet($pdo); $s=$pdo->prepare('SELECT * FROM dompet WHERE user_id=? ORDER BY id'); $s->execute([uid()]); return $s->fetchAll(); }
function getSaldoTotal($pdo){ prosesResetDompet($pdo); $s=$pdo->prepare('SELECT COALESCE(SUM(saldo),0) FROM dompet WHERE user_id=?'); $s->execute([uid()]); return (float)$s->fetchColumn(); }

// ── Rentang tanggal untuk periode ───────────────────────────
function periodeRange($periode){
    $now=time();
    switch($periode){
        case 'hari':   $a=date('Y-m-d'); $b=date('Y-m-d'); $label='Hari Ini'; break;
        case 'minggu': $a=date('Y-m-d',strtotime('monday this week')); $b=date('Y-m-d',strtotime('sunday this week')); $label='Minggu Ini'; break;
        case 'tahun':  $a=date('Y-01-01'); $b=date('Y-12-31'); $label='Tahun Ini'; break;
        case 'bulan':
        default:       $a=date('Y-m-01'); $b=date('Y-m-t'); $label='Bulan Ini'; break;
    }
    return [$a,$b,$label];
}

// ── Transaksi ── (filter + rentang tanggal opsional) ────────
function getTransaksi($pdo,$filter='semua',$dari=null,$sampai=null){
    $sql='SELECT t.*, d.nama AS dompet_nama FROM transaksi t LEFT JOIN dompet d ON t.dompet_id=d.id WHERE t.user_id=?';
    $p=[uid()];
    if($filter==='masuk') $sql.=' AND t.jumlah>0';
    if($filter==='keluar') $sql.=' AND t.jumlah<0';
    if($dari){ $sql.=' AND t.tanggal>=?'; $p[]=$dari; }
    if($sampai){ $sql.=' AND t.tanggal<=?'; $p[]=$sampai; }
    $sql.=' ORDER BY t.tanggal DESC, t.id DESC';
    $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchAll();
}

function getRingkasan($pdo,$a,$b){
    $s=$pdo->prepare('SELECT COALESCE(SUM(CASE WHEN jumlah>0 THEN jumlah END),0) masuk,
        COALESCE(SUM(CASE WHEN jumlah<0 THEN -jumlah END),0) keluar,
        COUNT(*) jml FROM transaksi WHERE user_id=? AND tanggal BETWEEN ? AND ?');
    $s->execute([uid(),$a,$b]); return $s->fetch();
}
function getSpendKategori($pdo,$a,$b){
    $s=$pdo->prepare('SELECT kategori, SUM(-jumlah) total FROM transaksi
        WHERE user_id=? AND jumlah<0 AND tanggal BETWEEN ? AND ? GROUP BY kategori ORDER BY total DESC');
    $s->execute([uid(),$a,$b]); return $s->fetchAll();
}
function getIncomeKategori($pdo,$a,$b){
    $s=$pdo->prepare('SELECT kategori, SUM(jumlah) total FROM transaksi
        WHERE user_id=? AND jumlah>0 AND tanggal BETWEEN ? AND ? GROUP BY kategori ORDER BY total DESC');
    $s->execute([uid(),$a,$b]); return $s->fetchAll();
}
function getSpendByCategory($pdo,$bulan,$tahun){
    $s=$pdo->prepare('SELECT kategori, SUM(-jumlah) total FROM transaksi
        WHERE user_id=? AND jumlah<0 AND MONTH(tanggal)=? AND YEAR(tanggal)=? GROUP BY kategori');
    $s->execute([uid(),$bulan,$tahun]); $out=[]; foreach($s->fetchAll() as $r) $out[$r['kategori']]=(float)$r['total']; return $out;
}

function getTrend($pdo,$bulan,$tahun,$mode='bulan'){
    global $NAMA_BULAN; $out=[];
    $q='SELECT COALESCE(SUM(CASE WHEN jumlah>0 THEN jumlah END),0) i,COALESCE(SUM(CASE WHEN jumlah<0 THEN -jumlah END),0) o FROM transaksi WHERE user_id=? AND MONTH(tanggal)=? AND YEAR(tanggal)=?';
    if($mode==='tahun'){
        for($m=1;$m<=12;$m++){ $s=$pdo->prepare($q); $s->execute([uid(),$m,$tahun]); $r=$s->fetch();
            $out[]=['m'=>substr($NAMA_BULAN[$m],0,3),'in'=>(float)$r['i']/1e6,'out'=>(float)$r['o']/1e6]; }
    } else {
        for($i=5;$i>=0;$i--){ $m=$bulan-$i;$y=$tahun; while($m<=0){$m+=12;$y--;}
            $s=$pdo->prepare($q); $s->execute([uid(),$m,$y]); $r=$s->fetch();
            $out[]=['m'=>substr($NAMA_BULAN[$m],0,3),'in'=>(float)$r['i']/1e6,'out'=>(float)$r['o']/1e6]; }
    }
    return $out;
}

// Seri tren untuk grafik garis: mode = minggu | bulan | tahun | semua
function getTrendSeries($pdo,$mode){
    global $NAMA_BULAN; $out=[];
    if($mode==='minggu'){
        for($i=9;$i>=0;$i--){ $a=date('Y-m-d',strtotime("monday this week -$i week")); $b=date('Y-m-d',strtotime("sunday this week -$i week"));
            $r=getRingkasan($pdo,$a,$b); $out[]=['label'=>(int)date('j',strtotime($a)).'/'.date('n',strtotime($a)),'in'=>(float)$r['masuk'],'out'=>(float)$r['keluar']]; }
    } elseif($mode==='tahun'){
        $y0=(int)date('Y')-4; for($y=$y0;$y<=(int)date('Y');$y++){ $r=getRingkasan($pdo,"$y-01-01","$y-12-31"); $out[]=['label'=>(string)$y,'in'=>(float)$r['masuk'],'out'=>(float)$r['keluar']]; }
    } elseif($mode==='semua'){
        $s=$pdo->prepare('SELECT MIN(YEAR(tanggal)) a, MAX(YEAR(tanggal)) b FROM transaksi WHERE user_id=?'); $s->execute([uid()]); $rg=$s->fetch();
        $a=(int)($rg['a']?:date('Y')); $b=(int)($rg['b']?:date('Y')); if($b-$a>15)$a=$b-15;
        for($y=$a;$y<=$b;$y++){ $r=getRingkasan($pdo,"$y-01-01","$y-12-31"); $out[]=['label'=>(string)$y,'in'=>(float)$r['masuk'],'out'=>(float)$r['keluar']]; }
    } else { // bulan: 6 bulan terakhir
        for($i=5;$i>=0;$i--){ $a=date('Y-m-01',strtotime("first day of -$i month")); $b=date('Y-m-t',strtotime("first day of -$i month"));
            $r=getRingkasan($pdo,$a,$b); $out[]=['label'=>substr($NAMA_BULAN[(int)date('n',strtotime($a))],0,3),'in'=>(float)$r['masuk'],'out'=>(float)$r['keluar']]; }
    }
    return $out;
}

// Tren per kategori + pemasukan (untuk grafik multi-garis di Analisa)
// return ['labels'=>[...], 'series'=>[['kat'=>..,'warna'=>..,'data'=>[...]],...]]
function getTrendKategori($pdo,$mode){
    global $NAMA_BULAN;
    $periods=[];
    if($mode==='minggu'){
        for($i=9;$i>=0;$i--){ $a=date('Y-m-d',strtotime("monday this week -$i week")); $z=date('Y-m-d',strtotime("sunday this week -$i week")); $periods[]=[(int)date('j',strtotime($a)).'/'.date('n',strtotime($a)),$a,$z]; }
    } elseif($mode==='tahun'){
        $y0=(int)date('Y')-4; for($y=$y0;$y<=(int)date('Y');$y++) $periods[]=[(string)$y,"$y-01-01","$y-12-31"];
    } elseif($mode==='semua'){
        $s=$pdo->prepare('SELECT MIN(YEAR(tanggal)) a,MAX(YEAR(tanggal)) b FROM transaksi WHERE user_id=?'); $s->execute([uid()]); $rg=$s->fetch();
        $a=(int)($rg['a']?:date('Y')); $b=(int)($rg['b']?:date('Y')); if($b-$a>15)$a=$b-15;
        for($y=$a;$y<=$b;$y++) $periods[]=[(string)$y,"$y-01-01","$y-12-31"];
    } else {
        for($i=5;$i>=0;$i--){ $a=date('Y-m-01',strtotime("first day of -$i month")); $z=date('Y-m-t',strtotime("first day of -$i month")); $periods[]=[substr($NAMA_BULAN[(int)date('n',strtotime($a))],0,3),$a,$z]; }
    }
    $labels=array_column($periods,0); $np=count($periods);
    // Ambil semua transaksi pada rentang sekali jalan, lalu kelompokkan di PHP (hemat query)
    $incData=array_fill(0,$np,0.0); $outData=array_fill(0,$np,0.0); $catData=[];
    if($np>0){
        $min=$periods[0][1]; $max=$periods[$np-1][2];
        $s=$pdo->prepare('SELECT kategori,tanggal,jumlah FROM transaksi WHERE user_id=? AND tanggal BETWEEN ? AND ?');
        $s->execute([uid(),$min,$max]);
        foreach($s->fetchAll() as $t){
            $pi=-1; for($i=0;$i<$np;$i++){ if($t['tanggal']>=$periods[$i][1] && $t['tanggal']<=$periods[$i][2]){ $pi=$i; break; } }
            if($pi<0) continue; $j=(float)$t['jumlah'];
            if($j>0){ $incData[$pi]+=$j; }
            else { $outData[$pi]+=-$j; $k=$t['kategori']; if(!isset($catData[$k]))$catData[$k]=array_fill(0,$np,0.0); $catData[$k][$pi]+=-$j; }
        }
    }
    $series=[];
    $series[]=['kat'=>'Pemasukan','warna'=>'#16a06b','data'=>$incData];
    $series[]=['kat'=>'Pengeluaran','warna'=>'#e23d4e','data'=>$outData];
    foreach(getKategori($pdo,'keluar') as $k){
        $nm=$k['nama']; if(isset($catData[$nm]) && array_sum($catData[$nm])>0)
            $series[]=['kat'=>$nm,'warna'=>$k['warna'],'data'=>$catData[$nm]];
    }
    return ['labels'=>$labels,'series'=>$series];
}

// Render isi kartu tren: tombol Garis/Batang + SVG (garis & batang) + legenda.
// Dipakai bersama oleh Dashboard & Analisa agar konsisten.
function renderTrendBody($tlabels,$tseries,$lh=210){
    $LW=620;$LH=$lh;$pl=44;$pr=16;$pt=16;$pb=34; $n=count($tlabels); $iw=$LW-$pl-$pr;$ih=$LH-$pt-$pb;
    $tmax=1; foreach($tseries as $s) foreach($s['data'] as $v)$tmax=max($tmax,$v);
    $ns=max(1,count($tseries)); $baseY=$pt+$ih;
    $xp=function($i)use($n,$pl,$iw){ return $n<=1?$pl+$iw/2:$pl+$i*($iw/($n-1)); };
    $yp=function($v)use($tmax,$pt,$ih){ return $pt+$ih-($v/$tmax)*$ih; };
    $spacing = $n>1 ? $iw/($n-1) : $iw;
    $bw = max(3, ($spacing*0.6)/$ns);
    ?>
    <div style="display:flex;justify-content:flex-end;margin-bottom:8px">
      <div class="seg-tabs">
        <a href="#" class="ct-line on" onclick="chartMode('line');return false">📈 Garis</a>
        <a href="#" class="ct-bar" onclick="chartMode('bar');return false">📊 Batang</a>
      </div>
    </div>
    <div id="line-tip" style="height:30px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:var(--soft)">Ketuk titik atau batang untuk menampilkan nilai</div>
    <svg viewBox="0 0 <?= $LW ?> <?= $LH ?>" style="width:100%;height:auto;overflow:visible">
      <?php for($g=0;$g<=3;$g++): $gy=$pt+$ih-$g/3*$ih; ?>
        <line x1="<?= $pl ?>" y1="<?= round($gy,1) ?>" x2="<?= $LW-$pr ?>" y2="<?= round($gy,1) ?>" stroke="var(--line)" stroke-width="1"/>
        <text x="<?= $pl-6 ?>" y="<?= round($gy+3,1) ?>" text-anchor="end" font-size="9" fill="var(--muted)"><?= rpShort($tmax*$g/3) ?></text>
      <?php endfor; ?>
      <!-- BATANG -->
      <g class="chart-bar" style="display:none">
        <?php foreach($tseries as $si=>$s): foreach($s['data'] as $i=>$v): $cx=$xp($i); $x=$cx-($ns*$bw)/2+$si*$bw; $y=$yp($v); ?>
          <rect class="bar-<?= $si ?>" x="<?= round($x,1) ?>" y="<?= round($y,1) ?>" width="<?= round(max(1,$bw-1),1) ?>" height="<?= round($baseY-$y,1) ?>" rx="1.5" fill="<?= $s['warna'] ?>" style="cursor:pointer" onclick="pickPt(<?= $si ?>,<?= $i ?>)"></rect>
        <?php endforeach; endforeach; ?>
      </g>
      <!-- GARIS -->
      <g class="chart-line">
        <?php foreach($tseries as $si=>$s): $pts=''; foreach($s['data'] as $i=>$v) $pts.=round($xp($i),1).','.round($yp($v),1).' '; ?>
          <polyline class="tl tl-<?= $si ?>" points="<?= trim($pts) ?>" fill="none" stroke="<?= $s['warna'] ?>" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
          <?php foreach($s['data'] as $i=>$v): ?><circle class="tc tc-<?= $si ?>" cx="<?= round($xp($i),1) ?>" cy="<?= round($yp($v),1) ?>" r="3.5" fill="<?= $s['warna'] ?>" style="cursor:pointer" onclick="pickPt(<?= $si ?>,<?= $i ?>)"/><?php endforeach; ?>
        <?php endforeach; ?>
      </g>
      <?php foreach($tlabels as $i=>$lbl): ?><text x="<?= round($xp($i),1) ?>" y="<?= $LH-12 ?>" text-anchor="middle" font-size="9.5" fill="var(--soft)" font-weight="600"><?= e($lbl) ?></text><?php endforeach; ?>
    </svg>
    <div id="trend-leg" style="display:flex;flex-wrap:wrap;gap:7px;margin-top:12px">
      <?php foreach($tseries as $si=>$s): ?><button type="button" class="tleg" data-i="<?= $si ?>" onclick="toggleLine(<?= $si ?>)" style="--c:<?= $s['warna'] ?>"><?= e($s['kat']) ?></button><?php endforeach; ?>
    </div>
<?php }

// ── Anggaran ─────────────────────────────────────────────────
function getAnggaran($pdo,$bulan,$tahun){
    $s=$pdo->prepare('SELECT * FROM anggaran WHERE user_id=? AND bulan=? AND tahun=? ORDER BY id'); $s->execute([uid(),$bulan,$tahun]);
    $rows=$s->fetchAll(); $spend=getSpendByCategory($pdo,$bulan,$tahun);
    foreach($rows as &$b) $b['terpakai']=$spend[$b['kategori']] ?? 0;
    return $rows;
}

// Rentang tanggal untuk anggaran berdasarkan frekuensi
// 'tanggal' = dari tanggal mulai s/d tanggal selesai (kalender)
function anggaranRange($freq,$mulai=null,$selesai=null){
    switch($freq){
        case 'harian':   return [date('Y-m-d'), date('Y-m-d'), 'Hari ini'];
        case 'mingguan': return [date('Y-m-d',strtotime('monday this week')), date('Y-m-d',strtotime('sunday this week')), 'Minggu ini'];
        case 'tahunan':  return [date('Y-01-01'), date('Y-12-31'), 'Tahun ini'];
        case 'selamanya':return ['2000-01-01','2099-12-31','Selamanya'];
        case 'tanggal':
            $a=$mulai?:date('Y-m-01'); $z=$selesai?:date('Y-m-t');
            if($z<$a){ $x=$a;$a=$z;$z=$x; }
            return [$a,$z,tglIndo($a,false).' – '.tglIndo($z,false)];
        case 'bulanan':
        default:         return [date('Y-m-01'), date('Y-m-t'), 'Bulan ini'];
    }
}
// Label frekuensi/batas waktu untuk ditampilkan di kartu
function anggaranPeriodeLabel($b){
    $freq=$b['frekuensi']??'static';
    $map=['harian'=>'Reset harian','mingguan'=>'Reset mingguan','bulanan'=>'Reset bulanan','tahunan'=>'Reset tahunan'];
    $base = ($freq==='static'||$freq==='') ? 'Tetap' : ($map[$freq]??'Reset bulanan');
    if(!empty($b['selesai_tgl'])) $base.=' · s/d '.tglIndo($b['selesai_tgl'],false);
    return $base;
}

// ── Komponen JADWAL terpadu (Frekuensi + Selesai setelah) ──
// $p = prefix id unik (mis. 'tg','tb'). Dirender di dalam <form>.
// $opts: ['parts'=>['mulai','freq','selesai'], 'mulai'=>bool tampilkan field tgl mulai, 'label'=>judul dropdown frekuensi]
function jadwalField($p,$opts=[]){
    global $NAMA_BULAN,$NAMA_HARI;
    $today=date('Y-m-d');
    $parts=$opts['parts']??['mulai','freq','selesai'];
    $showMulai=$opts['mulai']??true;
    $freqLabel=$opts['label']??'Frekuensi';
    ?>
    <?php if(in_array('mulai',$parts)): ?>
      <?php if($showMulai): ?>
      <div class="field"><label>Tanggal Mulai</label>
        <input type="date" name="mulai_tgl" id="<?= $p ?>-mulai" value="<?= $today ?>"></div>
      <?php else: ?>
      <input type="hidden" name="mulai_tgl" id="<?= $p ?>-mulai" value="<?= $today ?>">
      <?php endif; ?>
    <?php endif; ?>
    <?php if(in_array('freq',$parts)): ?>
    <div class="field"><label><?= e($freqLabel) ?></label>
      <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:130px">
          <select name="frekuensi" id="<?= $p ?>-freq" onchange="jadwalToggle('<?= $p ?>')" style="width:100%">
            <option value="harian">Harian</option>
            <option value="mingguan">Mingguan</option>
            <option value="bulanan" selected>Bulanan</option>
            <option value="tahunan">Tahunan</option>
          </select>
        </div>
        <div id="<?= $p ?>-hari-wrap" style="flex:1;min-width:120px;display:none">
          <div class="jdw-sub">Hari</div>
          <select name="freq_hari" id="<?= $p ?>-hari" style="width:100%">
            <?php foreach([1,2,3,4,5,6,0] as $d): ?><option value="<?= $d ?>" <?= $d===1?'selected':'' ?>><?= $NAMA_HARI[$d] ?></option><?php endforeach; ?>
          </select>
        </div>
        <div id="<?= $p ?>-bulan-wrap" style="flex:1;min-width:120px;display:none">
          <div class="jdw-sub">Bulan</div>
          <select name="freq_bulan" id="<?= $p ?>-bulan" style="width:100%">
            <?php for($m=1;$m<=12;$m++): ?><option value="<?= $m ?>"><?= $NAMA_BULAN[$m] ?></option><?php endfor; ?>
          </select>
        </div>
        <div id="<?= $p ?>-tgl-wrap" style="flex:1;min-width:110px;display:none">
          <div class="jdw-sub">Tanggal</div>
          <select name="freq_tgl" id="<?= $p ?>-tgl" style="width:100%">
            <?php for($d=1;$d<=31;$d++): ?><option value="<?= $d ?>"><?= str_pad($d,2,'0',STR_PAD_LEFT) ?></option><?php endfor; ?>
          </select>
        </div>
      </div>
      <div style="font-size:11.5px;color:var(--soft);margin-top:6px" id="<?= $p ?>-freqhint"></div>
    </div>
    <?php endif; ?>
    <?php if(in_array('selesai',$parts)): ?>
    <div class="field"><label>Selesai setelah</label>
      <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <div style="flex:1;min-width:150px">
          <select name="selesai_mode" id="<?= $p ?>-emode" onchange="jadwalToggle('<?= $p ?>')" style="width:100%">
            <option value="selamanya" selected>Selamanya</option>
            <option value="tanggal">Sampai tanggal</option>
          </select>
        </div>
        <div id="<?= $p ?>-selesai-wrap" style="flex:1;min-width:170px;display:none">
          <div class="jdw-sub">Tanggal selesai</div>
          <input type="date" name="selesai_tgl" id="<?= $p ?>-selesai" style="width:100%">
        </div>
      </div>
    </div>
    <?php endif; ?>
<?php }
function spendKategoriRange($pdo,$kategori,$a,$b){
    $s=$pdo->prepare('SELECT COALESCE(SUM(-jumlah),0) FROM transaksi WHERE user_id=? AND kategori=? AND jumlah<0 AND tanggal BETWEEN ? AND ?');
    $s->execute([uid(),$kategori,$a,$b]); return (float)$s->fetchColumn();
}
// Semua anggaran (per-periode), dengan terpakai dihitung sesuai periode
function getAnggaranSemua($pdo){
    $s=$pdo->prepare('SELECT * FROM anggaran WHERE user_id=? ORDER BY id'); $s->execute([uid()]);
    $rows=$s->fetchAll();
    $today=date('Y-m-d');
    foreach($rows as &$b){
        $freq=$b['frekuensi']??'static';
        if($freq==='static' || $freq===''){ $a=$b['mulai_tgl']?:date('Y-m-01'); $z=$today; }   // tetap: akumulasi sejak dibuat
        else { [$a,$z]=anggaranRange($freq); }                                                  // reset per periode
        if(!empty($b['selesai_tgl']) && $b['selesai_tgl']<$z) $z=$b['selesai_tgl'];
        if($z<$a) $z=$a;
        $b['terpakai']=spendKategoriRange($pdo,$b['kategori'],$a,$z);
        $b['pct']=$b['batas']>0?$b['terpakai']/$b['batas']*100:0;
        $b['lewat']=$b['terpakai']>$b['batas'];
        $b['expired']=!empty($b['selesai_tgl']) && $b['selesai_tgl']<$today;
    }
    return $rows;
}
function getAnggaranLog($pdo){ $s=$pdo->prepare('SELECT * FROM anggaran_log WHERE user_id=? ORDER BY id DESC LIMIT 60'); $s->execute([uid()]); return $s->fetchAll(); }
function anggaranKategoriAda($pdo,$kategori,$kecualiId=0){
    $s=$pdo->prepare('SELECT COUNT(*) FROM anggaran WHERE user_id=? AND kategori=? AND id<>?');
    $s->execute([uid(),$kategori,$kecualiId]); return $s->fetchColumn()>0;
}

// Status tagihan: lunas | overdue | due
function tagihanStatus($b){
    if($b['sudah_bayar']) return 'lunas';
    $today=(int)date('j');
    if(($b['tgl_jatuh_tempo'] < $today)) return 'overdue';
    return 'due';
}
function tagihanInfo(){
    return [
        'due'    =>['label'=>'Jatuh tempo','warna'=>'var(--amber)','tint'=>'var(--amberT)','emoji'=>'⏳'],
        'overdue'=>['label'=>'Lewat tempo','warna'=>'var(--red)','tint'=>'var(--redT)','emoji'=>'🔴'],
        'lunas'  =>['label'=>'Lunas','warna'=>'var(--green)','tint'=>'var(--greenT)','emoji'=>'✅'],
    ];
}

// ── Tabungan ─────────────────────────────────────────────────
function getTabungan($pdo){ $s=$pdo->prepare('SELECT * FROM tabungan WHERE user_id=? ORDER BY id'); $s->execute([uid()]); return $s->fetchAll(); }

// ── Tagihan ──────────────────────────────────────────────────
function getTagihan($pdo){ $s=$pdo->prepare('SELECT * FROM tagihan WHERE user_id=? ORDER BY sudah_bayar ASC, tgl_jatuh_tempo ASC'); $s->execute([uid()]); return $s->fetchAll(); }

// ── Tugas ────────────────────────────────────────────────────
function getTugas($pdo){ $s=$pdo->prepare('SELECT * FROM tugas WHERE user_id=? ORDER BY selesai ASC, tanggal ASC, id ASC'); $s->execute([uid()]); return $s->fetchAll(); }

// ── Catatan / Agenda ─────────────────────────────────────────
function getCatatan($pdo){ $s=$pdo->prepare('SELECT * FROM catatan WHERE user_id=? ORDER BY id'); $s->execute([uid()]); return $s->fetchAll(); }
// Tanggal kejadian berikutnya (>=hari ini) sesuai pengulangan
function catatanNextDate($c){
    $base=strtotime($c['tanggal']); $today=strtotime('today'); $u=$c['ulang']??'tidak';
    if($u==='tahunan'){ $m=date('n',$base);$d=date('j',$base);$cand=mktime(0,0,0,$m,$d,date('Y')); if($cand<$today)$cand=mktime(0,0,0,$m,$d,date('Y')+1); return $cand; }
    if($u==='bulanan'){ $d=date('j',$base);$cand=mktime(0,0,0,date('n'),$d,date('Y')); if($cand<$today)$cand=strtotime('+1 month',$cand); return $cand; }
    if($u==='mingguan'){ $dow=date('w',$base);$diff=($dow-date('w')+7)%7; return strtotime("+$diff day",$today); }
    return $base;
}
// Catatan yang jatuh pada tanggal $tgl (termasuk yang berulang)
function getCatatanByDate($pdo,$tgl){
    $out=[]; $m=(int)date('n',strtotime($tgl)); $d=(int)date('j',strtotime($tgl)); $w=(int)date('w',strtotime($tgl));
    foreach(getCatatan($pdo) as $c){
        $u=$c['ulang']??'tidak'; $b=strtotime($c['tanggal']);
        $match = ($c['tanggal']===$tgl)
            || ($u==='tahunan' && (int)date('n',$b)===$m && (int)date('j',$b)===$d)
            || ($u==='bulanan' && (int)date('j',$b)===$d)
            || ($u==='mingguan' && (int)date('w',$b)===$w);
        if($match) $out[]=$c;
    }
    return $out;
}
function getCatatanBulan($pdo,$bulan,$tahun){ $s=$pdo->prepare('SELECT * FROM catatan WHERE user_id=? AND MONTH(tanggal)=? AND YEAR(tanggal)=?'); $s->execute([uid(),$bulan,$tahun]); return $s->fetchAll(); }

// ── Notifikasi (dengan status dibaca) ───────────────────────
function notifDibaca($pdo){
    $set=[]; $s=$pdo->prepare('SELECT notif_key FROM notif_dibaca WHERE user_id=?'); $s->execute([uid()]);
    foreach($s->fetchAll() as $r) $set[$r['notif_key']]=true; return $set;
}
// Pengingat bertahap: 7 → 3 → 0 hari. Tahap berikut hanya muncul
// setelah tahap sebelumnya dibaca/dihapus. (+ tahap "telat" bila lewat)
function stageNotif($targetTs,$prefix,$dibaca){
    $today=strtotime('today'); $days=(int)floor(($targetTs-$today)/86400);
    if($days<0){ $k=$prefix.'-late'; return isset($dibaca[$k])?null:[$k,$days]; }
    foreach([7,3,0] as $t){ if($days<=$t){ $k=$prefix.'-'.$t; if(!isset($dibaca[$k])) return [$k,$days]; } }
    return null;
}
function getNotifs($pdo,$bulan,$tahun){
    $dibaca=notifDibaca($pdo); $notifs=[];
    $kapan=function($d){ return $d<0?('telat '.abs($d).' hari'):($d==0?'hari ini':($d==1?'besok':"$d hari lagi")); };

    // Tagihan (jatuh tempo bulan ini) — bertahap
    foreach(getTagihan($pdo) as $b){
        if($b['sudah_bayar']) continue;
        if(isset($b['ingatkan']) && !$b['ingatkan']) continue;   // cicilan bebas (tombol off) → tanpa notif
        $d=str_pad((string)min($b['tgl_jatuh_tempo'],(int)date('t')),2,'0',STR_PAD_LEFT);
        $st=stageNotif(strtotime(date('Y-m-').$d),'bill-'.$b['id'],$dibaca); if(!$st) continue;
        [$k,$days]=$st;
        $notifs[]=['key'=>$k,'emoji'=>$days<0?'🔴':$b['emoji'],'tint'=>$days<0?'#fde3e6':$b['tint'],
            'title'=>"Tagihan {$b['nama']} ".($days<0?'TELAT — ':'jatuh tempo ').$kapan($days),
            'sub'=>rp($b['jumlah']).($b['deskripsi']?' · '.$b['deskripsi']:''),'go'=>'tagihan','dibaca'=>false];
    }
    // Tabungan: target tanggal (bertahap) + pengingat menabung bulanan
    foreach(getTabungan($pdo) as $g){
        $belum = $g['terkumpul'] < $g['target'];
        if($belum && !empty($g['target_tanggal'])){
            $st=stageNotif(strtotime($g['target_tanggal']),'goal-'.$g['id'],$dibaca);
            if($st){ [$k,$days]=$st; $pct=$g['target']>0?round($g['terkumpul']/$g['target']*100):0;
                $notifs[]=['key'=>$k,'emoji'=>'🎯','tint'=>'#ece4fc','title'=>"Target {$g['judul']} — ".$kapan($days),
                    'sub'=>"Terkumpul $pct% · kurang ".rpShort(max(0,$g['target']-$g['terkumpul'])),'go'=>'tabungan','dibaca'=>false]; }
        }
        // pengingat menabung bulanan (tanggal X tiap bulan)
        if($belum && !empty($g['ingat_tgl']) && $g['ingat_tgl']>0){
            $setorBulanIni = !empty($g['terakhir_setor']) && date('Y-m',strtotime($g['terakhir_setor']))===date('Y-m');
            if(!$setorBulanIni && (int)date('j')>=$g['ingat_tgl']){
                $k='setor-'.$g['id'].'-'.date('Ym'); if(!isset($dibaca[$k]))
                    $notifs[]=['key'=>$k,'emoji'=>'🐷','tint'=>'#e3edfd','title'=>"Waktunya menabung: {$g['judul']}",
                        'sub'=>'Pengingat tiap tgl '.$g['ingat_tgl'].' · belum menabung bulan ini','go'=>'tabungan','dibaca'=>false];
            }
        }
    }
    // Kerjaan/tugas — bertahap
    foreach(getTugas($pdo) as $t){
        if($t['selesai']||!$t['tanggal']) continue;
        $st=stageNotif(strtotime($t['tanggal']),'task-'.$t['id'],$dibaca); if(!$st) continue;
        [$k,$days]=$st;
        $notifs[]=['key'=>$k,'emoji'=>'⏰','tint'=>'#e3edfd',
            'title'=>"Kerjaan \"{$t['judul']}\" ".$kapan($days).($t['waktu']?' '.$t['waktu']:''),
            'sub'=>"prioritas {$t['prioritas']}",'go'=>'kalender','dibaca'=>false];
    }
    // Catatan/agenda — berulang + waktu pengingat (1 hari / 1 minggu / 1 bulan sebelum)
    $leadMap=['hari'=>1,'minggu'=>7,'bulan'=>30];
    foreach(getCatatan($pdo) as $c){
        if(!$c['ingatkan']) continue;
        $next=catatanNextDate($c); $days=(int)floor(($next-strtotime('today'))/86400);
        $lead=$leadMap[$c['ingat_lead']??'hari']??1;
        if($days<0 || $days>$lead) continue;                 // hanya dalam jendela pengingat
        $k='note-'.$c['id'].'-'.date('Ymd',$next);            // unik per kejadian (berulang otomatis aktif lagi)
        if(isset($dibaca[$k])) continue;
        $ul=($c['ulang']??'tidak')!=='tidak' ? ' 🔁' : '';
        $notifs[]=['key'=>$k,'emoji'=>'📝','tint'=>'#ece4fc',
            'title'=>"Catatan: {$c['judul']}{$ul} — ".$kapan($days),'sub'=>$c['isi']?:'Agenda','go'=>'kalender','dibaca'=>false];
    }
    // Anggaran hampir habis (bisa dihapus, muncul lagi jika makin terpakai)
    foreach(getAnggaran($pdo,$bulan,$tahun) as $b){
        $pct=$b['batas']>0?$b['terpakai']/$b['batas']:0;
        if($pct>=0.8){ $k="budget-{$b['id']}-".min(10,(int)round($pct*10)); if(isset($dibaca[$k]))continue;
            $notifs[]=['key'=>$k,'emoji'=>'🔴','tint'=>'#fde3e6','title'=>"Anggaran {$b['kategori']} ".($pct>=1?'sudah habis':'hampir habis'),
                'sub'=>'Terpakai '.round($pct*100).'% · sisa '.rpShort(max(0,$b['batas']-$b['terpakai'])),'go'=>'anggaran','dibaca'=>false]; }
    }
    return $notifs;
}

// ── Event titik kalender ─────────────────────────────────────
function getCalEvents($pdo,$bulan,$tahun){
    $ev=[]; $add=function($d,$c)use(&$ev){$d=(int)$d;if(!isset($ev[$d]))$ev[$d]=[];if(!in_array($c,$ev[$d]))$ev[$d][]=$c;};
    $s=$pdo->prepare('SELECT DAY(tanggal) d,jumlah FROM transaksi WHERE user_id=? AND MONTH(tanggal)=? AND YEAR(tanggal)=?'); $s->execute([uid(),$bulan,$tahun]);
    foreach($s->fetchAll() as $r) $add($r['d'],$r['jumlah']>0?'green':'red');
    $s=$pdo->prepare('SELECT DAY(tanggal) d FROM tugas WHERE user_id=? AND MONTH(tanggal)=? AND YEAR(tanggal)=?'); $s->execute([uid(),$bulan,$tahun]);
    foreach($s->fetchAll() as $r) $add($r['d'],'amber');
    // Catatan (termasuk berulang: tahunan→bulan sama, bulanan→tiap bulan, mingguan→tiap hari yg sesuai)
    $daysInM=(int)date('t',mktime(0,0,0,$bulan,1,$tahun));
    foreach(getCatatan($pdo) as $c){
        $u=$c['ulang']??'tidak'; $b=strtotime($c['tanggal']); $cm=(int)date('n',$b); $cy=(int)date('Y',$b); $cd=(int)date('j',$b); $cw=(int)date('w',$b);
        if($u==='tidak'){ if($cm==$bulan && $cy==$tahun) $add($cd,'purple'); }
        elseif($u==='tahunan'){ if($cm==$bulan && $cd<=$daysInM) $add($cd,'purple'); }
        elseif($u==='bulanan'){ if($cd<=$daysInM) $add($cd,'purple'); }
        elseif($u==='mingguan'){ for($dd=1;$dd<=$daysInM;$dd++) if((int)date('w',mktime(0,0,0,$bulan,$dd,$tahun))==$cw) $add($dd,'purple'); }
    }
    if($bulan==(int)date('n')&&$tahun==(int)date('Y')) foreach(getTagihan($pdo) as $b) $add($b['tgl_jatuh_tempo'],'blue');
    return $ev;
}

// ── Agenda 1 hari (klik → menuju halaman) ───────────────────
function getAgendaHari($pdo,$tgl){
    $items=[]; $day=(int)date('j',strtotime($tgl)); $m=(int)date('n',strtotime($tgl)); $y=(int)date('Y',strtotime($tgl));
    $s=$pdo->prepare('SELECT t.*,d.nama dompet_nama FROM transaksi t LEFT JOIN dompet d ON t.dompet_id=d.id WHERE t.user_id=? AND t.tanggal=?'); $s->execute([uid(),$tgl]);
    foreach($s->fetchAll() as $t) $items[]=['kind'=>$t['jumlah']>0?'Masuk':'Keluar','color'=>$t['jumlah']>0?'#2f7d5d':'#c0392b','emoji'=>$t['emoji']?:'💰','title'=>$t['judul'],'sub'=>rp(abs($t['jumlah'])).($t['dompet_nama']?' · '.$t['dompet_nama']:''),'go'=>'transaksi'];
    $s=$pdo->prepare('SELECT * FROM tugas WHERE user_id=? AND tanggal=?'); $s->execute([uid(),$tgl]);
    foreach($s->fetchAll() as $t) $items[]=['kind'=>'Tugas','color'=>'#d99a2b','emoji'=>'✅','title'=>$t['judul'],'sub'=>($t['waktu']?:'').($t['catatan']?' · '.$t['catatan']:''),'go'=>'kerjaan'];
    $s=$pdo->prepare('SELECT * FROM catatan WHERE user_id=? AND tanggal=?'); $s->execute([uid(),$tgl]);
    foreach($s->fetchAll() as $c) $items[]=['kind'=>'Catatan','color'=>'#8a5fb0','emoji'=>'📝','title'=>$c['judul'],'sub'=>$c['isi']?:'','go'=>'kalender'];
    if($m==(int)date('n')&&$y==(int)date('Y')) foreach(getTagihan($pdo) as $b) if($b['tgl_jatuh_tempo']==$day) $items[]=['kind'=>'Tagihan','color'=>'#3b6fb0','emoji'=>$b['emoji'],'title'=>$b['nama'].' jatuh tempo','sub'=>rp($b['jumlah']),'go'=>'tagihan'];
    return $items;
}

function tglIndo($tgl,$pakaiHari=true){
    global $NAMA_HARI,$NAMA_BULAN; $ts=strtotime($tgl);
    return ($pakaiHari?$NAMA_HARI[(int)date('w',$ts)].', ':'').(int)date('j',$ts).' '.$NAMA_BULAN[(int)date('n',$ts)].' '.date('Y',$ts);
}

// ── Topbar bersama: judul + (khusus dashboard) Tambah + lonceng ─
function topbar($title,$sub,$notifs,$page,$extra=''){
    $belum=0; foreach($notifs as $n) if(empty($n['dibaca'])) $belum++;
    $isDash = ($page==='beranda');
    ?>
    <div class="topbar">
      <div>
        <h1><?= $title ?></h1>
        <?php if($sub): ?><div class="sub"><?= $sub ?></div><?php endif; ?>
      </div>
      <div class="topbar-actions">
        <?= $extra ?>
        <?php if($isDash): /* Tambah & notifikasi HANYA di dashboard */ ?>
          <button class="btn btn-primary hide-mobile" onclick="openModal('m-tx')"><?= icon('plus',16,'#fff',2.5) ?> Tambah</button>
          <button class="bell <?= $belum>0?'on':'' ?>" onclick="openModal('m-notif')" title="Notifikasi">
            <?= icon('bell',20) ?>
            <?php if($belum>0): ?><span class="badge"><?= $belum ?></span><?php endif; ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

// Selisih bulan dari hari ini ke tanggal target (minimal 1)
function bulanKeTanggal($tglTarget){
    if(!$tglTarget) return 0;
    $now=new DateTime('today'); $t=new DateTime($tglTarget);
    if($t<=$now) return 0;
    $d=$now->diff($t); return max(1,$d->y*12+$d->m+($d->d>0?1:0));
}
