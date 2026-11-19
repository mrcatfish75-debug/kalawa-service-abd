<?php
declare(strict_types=1);
function ksUiSuite(KsSuite $s):void {
 $s->t->group='UI R04 · navigasi enam role';
 $homes=['owner'=>'index.php','admin1'=>'index.php','cash1'=>'pembayaran.php','stock1'=>'stok.php','tech1'=>'pekerjaan.php','audit'=>'laporan.php'];
 foreach($homes as $role=>$home)$s->test('Menu ponsel dan identitas '.$role,function()use($s,$role,$home){$r=$s->c($role)->get('panel/'.$home);$s->ok($r);ksAssert(!preg_match('/>\s*Uji korporat\s*</i',$r['body']),'Label uji masih dirender');preg_match('~<nav class="ks-bottom-nav"[^>]*>(.*?)</nav>~s',$r['body'],$m);ksAssert(isset($m[1]),'Tab ponsel tidak tersedia');ksAssert(substr_count($m[1],'<a ')+substr_count($m[1],'<button ')<=5,'Lebih dari lima tab');ksAssert(str_contains($r['body'],'aria-label="Buka pencarian menu"'));});
 foreach(['cash1','stock1','tech1','audit'] as $role)$s->test('Tab '.$role.' tidak menawarkan master Owner',function()use($s,$role,$homes){$r=$s->c($role)->get('panel/'.$homes[$role]);preg_match('~<nav class="ks-bottom-nav"[^>]*>(.*?)</nav>~s',$r['body'],$m);ksAssert(!preg_match('~href="(?:pelanggan|motor|jasa|suku_cadang|supplier)\.php~',$m[1]));});
 $s->test('Login memakai label portal pegawai',function()use($s){$h=new KsHttp($s->base,'ui-login');$r=$h->get('login.php');$s->ok($r);ksAssert(str_contains($r['body'],'Portal pegawai')&&!str_contains($r['body'],'UJI KORPORAT'));});
 $s->t->group='UI R04 · pagination riil dan keutuhan baca';
 $prefix='Paginasi '.$s->tag; $codes=[];
 for($i=1;$i<=61;$i++){$code='UP'.sprintf('%06d',$i);$codes[]=$code;ksExec($s->db,'INSERT INTO jasa_servis(kode_jasa,uraian_jasa_servis) VALUES (?,?)',[$code,$prefix.' '.sprintf('%03d',$i)]);}
 $seen=[];$q=rawurlencode($prefix);
 foreach([1=>25,2=>25,3=>11] as $page=>$expected)$s->test('Jasa halaman '.$page.' berisi '.$expected.' baris',function()use($s,$q,$page,$expected,&$seen){$r=$s->c('owner')->get('panel/jasa.php?q='.$q.'&hal_jasa='.$page);$s->ok($r);preg_match_all('/UP[0-9]{6}/',$r['body'],$m);$found=array_values(array_unique($m[0]));ksAssert(count($found)===$expected,'Jumlah data halaman tidak tepat');ksAssert(count(array_intersect($seen,$found))===0,'Halaman mengulang data');$seen=array_merge($seen,$found);ksAssert(str_contains($r['body'],'Halaman '.$page.' dari 3'));});
 $s->test('Seluruh 61 jasa dapat dijangkau tanpa batas 50 lama',fn()=>ksAssert($seen===$codes));
 foreach(['0','-2','abc','999999999','1%20OR%201=1','%3Cscript%3E','%5B%5D'] as $raw)$s->test('Input halaman aman: '.$raw,function()use($s,$q,$raw){$r=$s->c('owner')->get('panel/jasa.php?q='.$q.'&hal_jasa='.$raw);$s->ok($r);ksAssert(str_contains($r['body'],' dari 61 hasil'));});
 $s->test('Array parameter halaman tidak menyebabkan fatal',function()use($s,$q){$r=$s->c('owner')->get('panel/jasa.php?q='.$q.'&hal_jasa[]=2');$s->ok($r);ksAssert(str_contains($r['body'],'Halaman 1 dari 3'));});
 $s->test('Tautan dan lompat halaman mempertahankan pencarian',function()use($s,$q,$prefix){$r=$s->c('owner')->get('panel/jasa.php?q='.$q.'&hal_jasa=2');ksAssert(str_contains($r['body'],'name="q" value="'.$prefix.'"'));ksAssert(str_contains($r['body'],'q=Paginasi%20'.$s->tag.'&amp;hal_jasa=3'));});
 $s->test('Pagination tidak membuat aksi POST tambahan',function()use($s,$q){$r=$s->c('owner')->get('panel/jasa.php?q='.$q.'&hal_jasa=2');preg_match('~<form class="ks-page-jump"(.*?)</form>~s',$r['body'],$m);ksAssert(isset($m[1])&&str_contains($m[1],'method="get"')&&!str_contains($m[1],'name="csrf"'));});
 // Native prepared query must support GROUP BY, subqueries, and authorized view lists.
 foreach(['pelanggan.php?hal_pelanggan=2','motor.php?hal_motor=2','supplier.php?hal_supplier=2','suku_cadang.php?hal_barang=2','work_order.php?hal_wo=2','pembayaran.php?status=SEMUA&hal_bayar=2','tagihan_korporat.php?hal_tagihan=2','stok.php?hal_stok=2','audit.php?hal_transaksi=2&hal_perubahan=2&hal_kas=2&hal_mutasi=2','katalog.php?hal_jasa=2&hal_barang=2&hal_supplier=2'] as $page)$s->test('Halaman daftar dapat dibaca: '.$page,function()use($s,$page){$s->ok($s->c('owner')->get('panel/'.$page));});
 $s->test('Cabang kedua tetap tidak terlihat di semua halaman WO admin',function()use($s){for($p=1;$p<=5;$p++){$r=$s->c('admin1')->get('panel/work_order.php?cabang=CBG002&hal_wo='.$p);$s->ok($r);preg_match('~<div class="ks-wo-table"(.*?)</table>~s',$r['body'],$m);ksAssert(!str_contains($m[1]??'','Kalawa Service Garuda'),'Filter halaman melampaui cabang');}});
 $s->test('Mekanik tetap ditolak saat memanggil pagination master',function()use($s){$s->ok($s->c('tech1')->get('panel/jasa.php?hal_jasa=2'),403);});
 $s->test('Halaman kedua Pekerjaan Saya tetap memakai penugasan',function()use($s){$s->ok($s->c('tech1')->get('panel/pekerjaan.php?hal_tugas=2'));});
 $s->test('Laporan simpan seluruh baris cetak yang dimuat',function()use($s){$r=$s->c('audit')->get('panel/laporan.php?jenis=riwayat&awal=2020-01-01&akhir=2199-12-31&hal_laporan=2');$s->ok($r);ksAssert(str_contains($r['body'],'cetak mencakup seluruh baris yang dimuat'));});
 $before=ksDigests($s->db,(string)ksOne($s->db,'SELECT DATABASE()'));
 $s->test('Berpindah daftar dan halaman tidak mengubah tabel',function()use($s,$before){foreach(['owner','admin1','cash1','stock1','tech1','audit'] as $role)$s->c($role)->get('panel/panduan.php');ksAssert(ksDigests($s->db,(string)ksOne($s->db,'SELECT DATABASE()'))===$before);});
 foreach($codes as $code)ksExec($s->db,'DELETE FROM jasa_servis WHERE kode_jasa=?',[$code]);
}
