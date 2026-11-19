<?php
declare(strict_types=1);
require __DIR__.'/../app/layout_m2.php';
require_once __DIR__.'/../app/operasional_m2.php';
$akun=akunLoginWajib();
try{$scope=opsCabang($akun,['rl_owner_ops','rl_branch_admin','rl_cashier_ops','rl_inventory_ops']);}catch(OperasionalDitolak $e){http_response_code(403);exit('Katalog tidak tersedia untuk akun ini.');}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit('Katalog hanya untuk dibaca.');}
$db=koneksiKalawaM2();$q=is_string($_GET['q']??null)?trim($_GET['q']):'';$q=substr($q,0,70);$like='%'.addcslashes($q,'\\%_').'%';
$jasa=ksUiList($db,'SELECT kode_jasa,uraian_jasa_servis FROM jasa_servis WHERE uraian_jasa_servis LIKE :q OR kode_jasa LIKE :kode ORDER BY uraian_jasa_servis',['q'=>$like,'kode'=>$like],'hal_jasa');
$barang=ksUiList($db,'SELECT s.kode_suku_cadang,s.nama_suku_cadang,c.nama_cabang,t.jumlah_stok,t.stok_minimum FROM suku_cadang s
    LEFT JOIN stok_cabang t ON t.kode_suku_cadang=s.kode_suku_cadang'.($scope!==null?' AND t.kode_cabang=:cab':'').'
    LEFT JOIN cabang c ON c.kode_cabang=t.kode_cabang WHERE (s.nama_suku_cadang LIKE :q OR s.kode_suku_cadang LIKE :kode)
    ORDER BY s.nama_suku_cadang,c.kode_cabang',['q'=>$like,'kode'=>$like]+($scope!==null?['cab'=>$scope]:[]),'hal_barang');
$supplier=[];
if(in_array($akun['role_db'],['rl_owner_ops','rl_inventory_ops'],true)) {
    $supplier=ksUiList($db,'SELECT id_supplier,nama_supplier FROM supplier WHERE nama_supplier LIKE :q OR id_supplier LIKE :kode ORDER BY nama_supplier',['q'=>$like,'kode'=>$like],'hal_supplier');
}
awalHalamanM2('Katalog bengkel',$akun);
?>
<div class="ks-ui-page-head"><div><h1>Katalog bengkel</h1><p>Periksa daftar jasa dan ketersediaan barang sebelum melayani pelanggan</p></div></div>
<p class="ks-r03-note">Daftar ini menampilkan jenis layanan dan ketersediaan barang. Harga transaksi dicatat pada Work Order. Perubahan master dilakukan Owner; stok cabang dikelola Gudang.</p>
<form class="panel" method="get"><label for="katalog-q">Cari kode, jasa, barang, atau supplier</label><div class="row"><input id="katalog-q" name="q" maxlength="70" value="<?=amanHtml($q)?>"><button type="submit">Cari katalog</button><a class="ks-ui-action" href="katalog.php">Reset</a></div></form>
<section class="panel"><h2>Jasa servis</h2><div class="tablewrap"><table><thead><tr><th>Kode</th><th>Jasa</th></tr></thead><tbody><?php foreach($jasa as $r): ?><tr><td><?=amanHtml($r['kode_jasa'])?></td><td><?=amanHtml($r['uraian_jasa_servis'])?></td></tr><?php endforeach; ?><?php if(!$jasa): ?><tr><td colspan="2">Tidak ada jasa sesuai pencarian</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_jasa'); ?></section>
<section class="panel"><h2>Suku cadang</h2><div class="tablewrap"><table><thead><tr><th>Kode / barang</th><th>Cabang</th><th class="ks-ui-number">Stok</th><th class="ks-ui-number">Minimum</th></tr></thead><tbody><?php foreach($barang as $r): ?><tr><td><?=amanHtml($r['kode_suku_cadang'].' · '.$r['nama_suku_cadang'])?></td><td><?=amanHtml($r['nama_cabang']??'Belum dicatat')?></td><td class="ks-ui-number"><?=amanHtml($r['jumlah_stok']??'—')?></td><td class="ks-ui-number"><?=amanHtml($r['stok_minimum']??'—')?></td></tr><?php endforeach; ?><?php if(!$barang): ?><tr><td colspan="4">Tidak ada barang sesuai pencarian</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_barang'); ?></section>
<?php if(in_array($akun['role_db'],['rl_owner_ops','rl_inventory_ops'],true)): ?><section class="panel"><h2>Daftar supplier</h2><p class="muted">Gunakan daftar supplier untuk koordinasi pengadaan. Catat barang yang sudah diterima melalui Stok Bengkel.</p><div class="tablewrap"><table><thead><tr><th>Kode / supplier</th></tr></thead><tbody><?php foreach($supplier as $r): ?><tr><td><?=amanHtml($r['id_supplier'].' · '.$r['nama_supplier'])?></td></tr><?php endforeach; ?><?php if(!$supplier): ?><tr><td colspan="1">Tidak ada supplier sesuai pencarian</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_supplier'); ?></section><?php endif; akhirHalamanM2(); ?>
