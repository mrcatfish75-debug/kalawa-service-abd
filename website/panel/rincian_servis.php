<?php
declare(strict_types=1);
require __DIR__.'/../app/layout_m2.php';
require_once __DIR__.'/../app/layanan_r03.php';
$akun=akunLoginWajib();
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') {http_response_code(405);header('Allow: GET');exit('Halaman rincian hanya untuk dibaca.');}
$w=false;$error='';
try {
    $w=ksLayananAmbil(koneksiKalawaM2(),$akun,opsTeks($_GET,'wo'));
    if(!$w) {http_response_code(404);$error='Work Order tidak ditemukan dalam cakupan akun Anda.';}
} catch (OperasionalDitolak $e) {http_response_code($e->status);$error=$e->getMessage();}
catch (InvalidArgumentException $e) {http_response_code(422);$error=$e->getMessage();}
catch (Throwable $e) {error_log('[Kalawa rincian] '.$e->getMessage());http_response_code(503);$error='Rincian belum dapat dimuat. Hubungi pengelola.';}
if($w && $error==='')ksCetakSiapkan(koneksiKalawaM2(),$akun,'rincian_servis',
    ['cabang'=>$w['kode_cabang'],'referensi'=>$w['no_work_order'],'metadata'=>['Nomor polisi'=>$w['no_polisi']]]);
awalHalamanM2('Rincian servis',$akun);
?>
<div class="ks-ui-page-head"><div><h1>Rincian servis</h1><p>Identitas pekerjaan, item yang digunakan, dan penyelesaiannya</p></div><a class="ks-ui-action ks-ui-no-print" href="panduan.php">Panduan kerja</a></div>
<?php if($error): ?><p class="alert bad" role="alert"><?=amanHtml($error)?></p><?php endif; ?>
<?php if($w): ?><section class="panel ks-cetak-document ks-cetak-rincian"><?php ksCetakKop(); ?>
<div class="ks-ui-section-head ks-cetak-screen"><div><h2><?=amanHtml($w['no_work_order'])?></h2><p><?=amanHtml($w['nama_cabang'])?></p></div><button class="light ks-ui-no-print" type="button" onclick="window.print()">Cetak rincian</button></div>
<dl class="ks-r03-facts"><div><dt>Motor</dt><dd><?=amanHtml($w['no_polisi'].' · '.$w['merek'].' '.$w['tipe'])?></dd></div>
<?php if($akun['role_db']!=='rl_inventory_ops'): ?><div><dt>Pelanggan</dt><dd><?=amanHtml($w['nama_pelanggan'])?></dd></div><?php endif; ?>
<div><dt>Mekanik utama</dt><dd><?=amanHtml($w['mekanik'])?></dd></div><div><dt>Mekanik pembantu</dt><dd><?=amanHtml(implode(', ',$w['pembantu']) ?: 'Tidak ada')?></dd></div>
<div><dt>Masuk</dt><dd><?=amanHtml(ksUiTanggal($w['tanggal_masuk'],true))?> WIB</dd></div><div><dt>Selesai</dt><dd><?=amanHtml(ksUiTanggal($w['tanggal_selesai'],true))?></dd></div>
<div><dt>Status pekerjaan</dt><dd><?=ksUiStatus($w['status_pekerjaan'])?></dd></div>
<?php if($w['keuangan']): ?><div><dt>Status pembayaran</dt><dd><?=ksUiStatus($w['bayar']['status']??null)?></dd></div><?php endif; ?></dl>
<?php if($akun['role_db']!=='rl_inventory_ops'): ?><h3>Keluhan pelanggan</h3><p class="ks-r03-prose"><?=nl2br(amanHtml($w['keluhan']))?></p><?php endif; ?>
<?php ksLayananItem($w,$w['keuangan']); ?>
<?php if($w['keuangan']): ?>
<?php if($w['pembayaran']): $b=$w['pembayaran']; ?><div class="ks-r03-totals"><p>Diskon <strong><?=amanHtml(ksUiRupiah($b['diskon_member']))?></strong></p><p>Total tercatat <strong><?=amanHtml(ksUiRupiah($b['total_bayar']))?></strong></p><p>Metode <strong><?=amanHtml(str_replace('_',' ',$b['metode_pembayaran']))?></strong></p><p>Pelunasan <strong><?=amanHtml(ksUiTanggal($b['dicatat_pada'],true))?></strong></p></div><?php endif; ?>
<?php if(($w['bayar']['id_kontrak']??null)!==null): ?><p class="muted">Layanan korporat · <?=($w['bayar']['id_tagihan']??null)!==null?'Tagihan #'.amanHtml($w['bayar']['id_tagihan']):'Belum dimasukkan ke tagihan bulanan'?></p><?php endif; ?>
<div class="ks-cetak-garansi"><h3>Garansi pekerjaan</h3>
<?php if($w['garansi']): $g=$w['garansi'];$sah=$w['status_pekerjaan']==='SELESAI' && ($w['bayar']['status']??'')==='LUNAS' && $w['tanggal_selesai']!==null && $g['hari_ini']>=substr($w['tanggal_selesai'],0,10);
$label=!$sah?'Belum berlaku':($g['hari_ini']<=$g['sampai']?'Aktif':'Kedaluwarsa'); ?>
<p><strong><?=amanHtml($label)?></strong> · <?=amanHtml($g['jumlah_hari_garansi'])?> hari · sampai <?=amanHtml(ksUiTanggal($g['sampai']))?></p><p><?=amanHtml($g['catatan_garansi'])?></p>
<?php else: ?><p class="muted">Belum ada catatan garansi untuk pekerjaan ini</p><?php endif; ?></div>
<?php endif; ?>
</section><?php endif; akhirHalamanM2(); ?>
