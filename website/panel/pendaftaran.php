<?php
declare(strict_types=1);
require __DIR__.'/../app/layout_m2.php';
require_once __DIR__.'/../app/pendaftaran_r03.php';
$akun=akunLoginWajib();
try {$scope=ksDaftarScope($akun);} catch(OperasionalDitolak $e) {http_response_code(403);exit('Pendaftaran tersedia untuk Admin Cabang dan Owner.');}
$db=koneksiKalawaM2();$error='';$old=[];
$mode=is_string($_GET['mode']??null) && $_GET['mode']==='terdaftar'?'terdaftar':'baru';
if($_SERVER['REQUEST_METHOD']==='POST') {
    validasiCsrf();$old=$_POST;$mode=is_string($_POST['mode']??null)?$_POST['mode']:'baru';
    try {
        if(opsTeks($_POST,'aksi')!=='daftar')throw new InvalidArgumentException('Tindakan tidak dikenal.');
        $token=opsTeks($_POST,'token_transaksi');opsBacaToken($token,'pendaftaran','baru');
        $r=ksDaftarSimpan($db,$akun,$_POST);unset($_SESSION['ops_tokens'][$token]);
        pesanKilAt('Pelanggan dan motor '.$r['motor'].' siap dipakai. Lanjutkan membuat Work Order.');
        pindah('work_order.php?buat=1&pelanggan='.rawurlencode($r['pelanggan']).'&motor='.rawurlencode($r['motor']).'#buat-wo');
    } catch(OperasionalDitolak $e) {http_response_code($e->status);$error=$e->getMessage();}
    catch(InvalidArgumentException $e) {http_response_code(422);$error=$e->getMessage();}
    catch(PDOException $e) {error_log('[Kalawa pendaftaran] '.$e->getMessage());$code=(int)($e->errorInfo[1]??0);http_response_code($code===1062?409:503);
        $error=$code===1062?'ID, identitas, nomor polisi, rangka, atau mesin sudah terdaftar. Gunakan data yang sudah ada; perubahan dibatalkan seluruhnya.':'Pendaftaran belum tersimpan. Hubungi pengelola untuk memeriksa koneksi dan izin database.';}
    catch(Throwable $e) {error_log('[Kalawa pendaftaran] '.$e->getMessage());http_response_code(503);$error='Pendaftaran belum dapat diproses.';}
}
function ksDaftarValue(string $key): string {global $old;return amanHtml(is_string($old[$key]??null)?$old[$key]:'');}
awalHalamanM2('Pendaftaran pelanggan dan motor',$akun);
?>
<div class="ks-ui-page-head"><div><h1>Pendaftaran pelanggan dan motor</h1><p>Daftarkan motor beserta pemiliknya, lalu buat Work Order</p></div><a class="ks-ui-action" href="work_order.php?buat=1#buat-wo">Motor sudah terdaftar</a></div>
<p class="ks-r03-note">Data pelanggan dipakai bersama antar cabang. Admin Cabang dapat menambah data baru di sini. Koreksi identitas, pemindahan pemilik, dan penghapusan ditangani Owner.</p>
<nav class="ks-ui-report-nav" aria-label="Jenis pendaftaran"><a href="pendaftaran.php" <?=$mode==='baru'?'aria-current="page"':''?>>Pelanggan baru + motor baru</a><a href="pendaftaran.php?mode=terdaftar" <?=$mode==='terdaftar'?'aria-current="page"':''?>>Motor baru milik pelanggan terdaftar</a></nav>
<?php if($error): ?><div class="alert bad" role="alert"><?=amanHtml($error)?></div><?php endif; ?>
<form method="post" action="pendaftaran.php" class="panel" autocomplete="off">
<input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>"><input type="hidden" name="aksi" value="daftar"><input type="hidden" name="mode" value="<?=amanHtml($mode)?>"><input type="hidden" name="token_transaksi" value="<?=amanHtml(opsToken('pendaftaran','baru',null))?>">
<h2>1. Pemilik motor</h2><div class="ks-r03-form-grid">
<label>ID pelanggan<input name="id_pelanggan" maxlength="15" required value="<?=ksDaftarValue('id_pelanggan')?>"><small><?=$mode==='baru'?'Buat ID unik, misalnya CUS-0101':'Isi ID persis seperti pada data pelanggan'?></small></label>
<?php if($mode==='baru'): ?>
<label>Nama pelanggan<input name="nama_pelanggan" maxlength="100" required value="<?=ksDaftarValue('nama_pelanggan')?>"></label>
<label>Nomor telepon<input name="no_telepon" maxlength="20" required value="<?=ksDaftarValue('no_telepon')?>" inputmode="tel"></label>
<label>Jenis identitas<select name="jenis_identitas"><?php foreach(['KTP','SIM','PASPOR'] as $j): ?><option <?=($old['jenis_identitas']??'')===$j?'selected':''?>><?=$j?></option><?php endforeach; ?></select></label>
<label>Nomor identitas<input name="no_identitas" maxlength="30" required value="<?=ksDaftarValue('no_identitas')?>"></label>
<label>Alamat<textarea name="alamat" maxlength="255" required><?=ksDaftarValue('alamat')?></textarea></label>
<?php if($scope===null): ?><label>Cabang favorit<select name="kode_cabang_favorit"><option value="">Belum ditentukan</option><?php foreach(opsDaftarCabang($db,null) as $c): ?><option value="<?=amanHtml($c['kode_cabang'])?>" <?=($old['kode_cabang_favorit']??'')===$c['kode_cabang']?'selected':''?>><?=amanHtml($c['nama_cabang'])?></option><?php endforeach; ?></select></label><?php endif; ?>
<?php else: ?><p class="muted">Data identitas pelanggan yang sudah terdaftar tetap digunakan. Pendaftaran perusahaan dan kontrak korporat dilakukan Owner.</p><?php endif; ?>
</div><h2>2. Motor baru</h2><div class="ks-r03-form-grid">
<?php foreach(['no_polisi'=>['Nomor polisi',15],'no_rangka'=>['Nomor rangka',30],'no_mesin'=>['Nomor mesin',30],'merek'=>['Merek',50],'tipe'=>['Tipe',80],'warna'=>['Warna',30]] as $key=>[$label,$max]): ?><label><?=amanHtml($label)?><input name="<?=$key?>" maxlength="<?=$max?>" value="<?=ksDaftarValue($key)?>" required></label><?php endforeach; ?>
<label>Tahun produksi<input type="number" name="tahun_produksi" min="1900" max="<?=date('Y')?>" value="<?=ksDaftarValue('tahun_produksi')?>" required></label><label>Kilometer terakhir<input type="number" name="kilometer_terakhir" min="0" max="4294967295" value="<?=ksDaftarValue('kilometer_terakhir')?:'0'?>" required></label>
</div><div class="ks-r03-form-foot"><p class="muted">Pelanggan, motor, dan pemilik disimpan dalam satu transaksi. Jika ada data tidak valid, seluruh pendaftaran dibatalkan.</p><button type="submit">Simpan dan lanjut ke Work Order</button></div></form>
<?php akhirHalamanM2(); ?>
