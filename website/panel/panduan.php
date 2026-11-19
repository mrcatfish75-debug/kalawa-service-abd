<?php
declare(strict_types=1);
require __DIR__.'/../app/layout_m2.php';
require_once __DIR__.'/../app/panduan_r03.php';
$akun=akunLoginWajib();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit('Panduan hanya untuk dibaca.');}
$g=ksPanduanRole($akun['role_db']);awalHalamanM2('Panduan kerja',$akun);
?>
<div class="ks-ui-page-head"><div><h1>Panduan kerja <?=amanHtml($g['judul'])?></h1><p><?=amanHtml($g['tujuan'])?></p></div><span class="ks-ui-scope"><?=amanHtml($g['cakupan'])?></span></div>
<section class="panel"><h2>Langkah kerja Anda</h2><ol class="ks-r03-guide"><?php foreach($g['langkah'] as [$judul,$isi,$url]): ?><li><div><h3><?=amanHtml($judul)?></h3><p><?=amanHtml($isi)?></p></div><a class="ks-ui-action" href="<?=amanHtml($url)?>">Buka halaman</a></li><?php endforeach; ?></ol></section>
<section class="panel"><h2>Batas kewenangan</h2><p class="ks-r03-prose"><?=amanHtml($g['batas'])?></p></section>
<section class="panel"><h2>Arti status servis</h2><dl class="ks-r03-facts"><div><dt>Menunggu</dt><dd>WO tercatat, pengerjaan belum dimulai</dd></div><div><dt>Proses</dt><dd>Motor sedang dikerjakan</dd></div><div><dt>Selesai</dt><dd>Pekerjaan selesai; belum tentu lunas</dd></div><div><dt>Dibayar / Lunas</dt><dd>Pembayaran individual atau tagihan korporat sudah dilunasi</dd></div><div><dt>Dibatalkan</dt><dd>WO dihentikan sesuai syarat pembatalan</dd></div></dl><p class="muted">Status pekerjaan dan status pembayaran ditampilkan terpisah. Garansi yang tercatat berlaku setelah pekerjaan selesai dan pembayaran lunas, sampai tanggal batas garansi.</p></section>
<?php akhirHalamanM2(); ?>
