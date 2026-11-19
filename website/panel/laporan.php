<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/laporan_m2.php';
$akun = akunLoginWajib();
$db = koneksiKalawaM2();
$filter = [];
$hasil = ['headers'=>[],'rows'=>[],'dibatasi'=>false];
$error = '';
try {
    $filter = laporanFilter($db,$akun,$_GET);
    $db->exec('SET SESSION group_concat_max_len=16384');
    $hasil = laporanAmbil($db,$filter);
} catch (OperasionalDitolak $e) {
    http_response_code($e->status);
    exit(amanHtml($e->getMessage()));
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    $error = $e->getMessage();
} catch (Throwable $e) {
    error_log('[Kalawa laporan] '.$e->getMessage());
    http_response_code(503);
    $error = 'Laporan belum dapat dibuka. Periksa log server.';
}
$reportPage=ksUiPageMeta(count($hasil['rows']),'hal_laporan');
$scope = opsCabang($akun,['rl_owner_ops','rl_internal_audit','rl_branch_admin','rl_cashier_ops','rl_inventory_ops']);
$cabangs = opsDaftarCabang($db,$scope);
$jenisList = ['riwayat'=>'Riwayat servis per motor','pendapatan'=>'Pendapatan per cabang',
    'pemakaian'=>'Pemakaian suku cadang per WO','stok'=>'Stok di bawah minimum'];
if ($akun['role_db'] === 'rl_inventory_ops') {
    $jenisList = array_intersect_key($jenisList,array_flip(['pemakaian','stok']));
}
$aktif = $filter['jenis'] ?? (is_string($_GET['jenis']??null) ? $_GET['jenis'] : array_key_first($jenisList));
if (!isset($jenisList[$aktif])) $aktif = array_key_first($jenisList);
$formData = $filter + ['jenis'=>$aktif, 'cabang'=>$scope??'', 'awal'=>date('Y-m-01'),
    'akhir'=>date('Y-m-d'), 'no_polisi'=>'', 'no_work_order'=>''];
if (!$filter) {
    foreach (['awal','akhir','no_polisi','no_work_order'] as $field) {
        if (is_string($_GET[$field]??null)) $formData[$field] = substr($_GET[$field],0,80);
    }
}
$cabangLabel = $scope === null ? 'Seluruh cabang' : (string)($cabangs[0]['nama_cabang']??$scope);
$deskripsi = ['riwayat'=>'Telusuri pekerjaan, mekanik, dan rincian servis setiap motor.',
    'pendapatan'=>'Lihat penerimaan pembayaran individual dan korporat menurut cabang.',
    'pemakaian'=>'Periksa jumlah dan nilai suku cadang yang dipakai pada Work Order.',
    'stok'=>'Periksa barang yang jumlahnya lebih kecil dari batas minimum.'];
if($filter && !$error){
    $meta=['Jumlah data'=>count($hasil['rows']).' baris dimuat'];
    if($filter['no_polisi']!=='')$meta['Nomor polisi']=$filter['no_polisi'];
    if($filter['no_work_order']!=='')$meta['Nomor WO']=$filter['no_work_order'];
    if($filter['jenis']==='stok')$meta['Posisi data']=ksUiTanggal(date('Y-m-d H:i:s'),true).' WIB (saat dibuka)';
    ksCetakSiapkan($db,$akun,$filter['jenis'],['cabang'=>$filter['cabang'],
        'periode'=>$filter['jenis']==='stok'?'':ksUiTanggal($filter['awal']).' - '.ksUiTanggal($filter['akhir']),
        'nama_file'=>$filter['jenis']==='stok'?date('Y-m-d'):$filter['awal'].' sd '.$filter['akhir'],
        'metadata'=>$meta,'catatan'=>$hasil['dibatasi']?'Hasil dibatasi 500 baris. Persempit periode atau filter untuk memperoleh rincian lainnya.':'']);
}
awalHalamanM2('Laporan',$akun);
?>
<div class="ks-ui-page-head"><div><h1>Laporan bengkel</h1><p>Riwayat servis, pendapatan, pemakaian barang, dan persediaan.</p></div><span class="ks-ui-scope"><?=ksIconR02('boxes')?><?=amanHtml($cabangLabel)?></span></div>
<?php if ($error): ?><div class="alert bad" role="alert"><?=amanHtml($error)?></div><?php endif; ?>
<nav class="ks-ui-report-nav ks-ui-no-print" aria-label="Jenis laporan">
<?php foreach ($jenisList as $key=>$label):
    $link = http_build_query(['jenis'=>$key, 'cabang'=>$filter['cabang']??($scope??''),
        'awal'=>$filter['awal']??date('Y-m-01'), 'akhir'=>$filter['akhir']??date('Y-m-d')]);
?><a href="laporan.php?<?=amanHtml($link)?>" <?=$aktif===$key?'aria-current="page"':''?>><?=amanHtml($label)?></a><?php endforeach; ?>
</nav>
<section class="panel ks-ui-report-filter ks-ui-no-print" aria-labelledby="filter-heading">
<h2 id="filter-heading"><?=amanHtml($jenisList[$aktif])?></h2><p class="muted"><?=amanHtml($deskripsi[$aktif])?></p>
<form method="get" action="laporan.php" id="form-laporan">
<input type="hidden" name="jenis" id="jenis" value="<?=amanHtml($aktif)?>">
<div class="ks-ui-report-fields">
<?php if ($scope===null): ?><div><label for="cabang">Cabang</label><select name="cabang" id="cabang"><option value="">Semua cabang</option><?php foreach ($cabangs as $c): ?><option value="<?=amanHtml($c['kode_cabang'])?>" <?=$formData['cabang']===$c['kode_cabang']?'selected':''?>><?=amanHtml($c['nama_cabang'])?></option><?php endforeach; ?></select></div><?php endif; ?>
<?php if ($aktif!=='stok'): ?>
<div><label for="awal">Tanggal mulai</label><input type="date" name="awal" id="awal" min="1900-01-01" max="2199-12-31" value="<?=amanHtml($formData['awal'])?>" required></div>
<div><label for="akhir">Tanggal akhir</label><input type="date" name="akhir" id="akhir" min="1900-01-01" max="2199-12-31" value="<?=amanHtml($formData['akhir'])?>" required></div>
<?php endif; ?>
<?php if ($aktif==='riwayat'): ?><div><label for="no_polisi">Nomor polisi <span class="ks-ui-optional">(opsional)</span></label><input name="no_polisi" id="no_polisi" maxlength="15" placeholder="Contoh KH 2714 AD" value="<?=amanHtml($formData['no_polisi'])?>"></div><?php endif; ?>
<?php if ($aktif==='pemakaian'): ?><div><label for="no_work_order">Nomor Work Order <span class="ks-ui-optional">(opsional)</span></label><input name="no_work_order" id="no_work_order" maxlength="20" placeholder="Nomor WO lengkap" value="<?=amanHtml($formData['no_work_order'])?>"></div><?php endif; ?>
<div class="ks-ui-filter-actions"><button type="submit">Tampilkan laporan</button><a class="ks-ui-action" href="laporan.php?jenis=<?=amanHtml($aktif)?>">Reset</a></div>
</div></form></section>
<?php if ($filter && !$error): ?>
<section class="panel ks-ui-report-result ks-cetak-document"><?php ksCetakKop(); ?><div class="ks-ui-section-head ks-cetak-screen"><div><h2><?=amanHtml($jenisList[$filter['jenis']])?></h2><p class="muted"><?=amanHtml($filter['cabang']===''?'Semua cabang':($cabangs[array_search($filter['cabang'],array_column($cabangs,'kode_cabang'),true)]['nama_cabang']??$filter['cabang']))?></p></div><button type="button" class="light ks-ui-no-print" onclick="window.print()">Cetak laporan</button></div>
<?php if ($filter['jenis']==='stok'): ?>
<p class="muted ks-cetak-screen">Kondisi persediaan saat laporan dibuka. Barang tampil jika jumlah stok lebih kecil dari batas minimum.</p>
<?php else: ?><p class="muted ks-cetak-screen"><?=amanHtml(ksUiTanggal($filter['awal']).' – '.ksUiTanggal($filter['akhir']))?> · WIB</p><?php endif; ?>
<?php if ($filter['jenis']==='pendapatan'): ?>
<p class="muted">Pendapatan mengikuti tanggal pelunasan. Pembayaran korporat dibagi ke cabang masing-masing WO.</p>
<?php
$stmt=$db->prepare("SELECT COUNT(*) AS jumlah,COALESCE(SUM(p.total_bayar),0) AS total
    FROM pembayaran p JOIN work_order w ON w.no_work_order=p.no_work_order
    WHERE p.status_pembayaran='LUNAS' AND NOT EXISTS
    (SELECT 1 FROM penerimaan_kas k WHERE k.no_work_order=p.no_work_order)" .
    ($filter['cabang']!==''?' AND w.kode_cabang=:cabang':''));
$stmt->execute($filter['cabang']!==''?['cabang'=>$filter['cabang']]:[]);
$historis=$stmt->fetch();
?>
<?php if ((int)$historis['jumlah']>0): ?><p class="alert ks-ui-note">Ada <?=amanHtml($historis['jumlah'])?> pembayaran lama berstatus LUNAS senilai <?=amanHtml(ksUiRupiah($historis['total']))?> dengan tanggal pelunasan belum tercatat. Nilainya ditampilkan terpisah dari pendapatan pada rentang tanggal ini.</p><?php endif; ?>
<?php endif; ?>
<?php if ($filter['jenis']==='riwayat'): ?>
<div class="tablewrap"><table id="hasil-laporan" class="ks-ui-cards ks-ui-history"><caption class="ks-ui-sr-only">Riwayat servis dan rincian pekerjaan sesuai filter</caption><thead><tr><th scope="col">Work Order / masuk</th><th scope="col">Motor</th><?php if($scope===null): ?><th scope="col">Cabang</th><?php endif; ?><th scope="col">Pelanggan</th><th scope="col">Mekanik</th><th scope="col">Status</th><th scope="col">Rincian servis</th></tr></thead><tbody>
<?php foreach ($hasil['rows'] as $rowIndex=>$row): ?><tr class="<?=($rowIndex<$reportPage['offset']||$rowIndex>=$reportPage['offset']+$reportPage['size'])?'ks-report-paged-out':''?>">
<td class="ks-ui-identity" data-label="Work Order / masuk"><strong><?=amanHtml($row['no_work_order'])?></strong><small><?=amanHtml(ksUiTanggal($row['tanggal_masuk'],true))?></small></td>
<td data-label="Motor"><strong><?=amanHtml($row['no_polisi'])?></strong><small><?=amanHtml($row['merek'].' '.$row['tipe'])?></small></td>
<?php if($scope===null): ?><td data-label="Cabang"><?=amanHtml($row['nama_cabang'])?></td><?php endif; ?>
<td data-label="Pelanggan"><?=amanHtml($row['nama_pelanggan'])?></td><td data-label="Mekanik"><?=amanHtml($row['mekanik'])?></td>
<td data-label="Status"><div class="ks-ui-status-pair"><span><span class="ks-ui-sr-only">Pekerjaan </span><?=ksUiStatus($row['status_pekerjaan'])?></span><span><span class="ks-ui-sr-only">Pembayaran </span><?=ksUiStatus($row['pembayaran'])?></span></div></td>
<td class="ks-ui-row-details" data-label="Rincian servis"><details data-ks-print-details><summary>Lihat jasa dan barang</summary><dl><dt>Jasa</dt><dd><?=amanHtml($row['jasa'])?></dd><dt>Suku cadang</dt><dd><?=amanHtml($row['suku_cadang'])?></dd></dl><a class="ks-ui-action ks-ui-no-print" href="rincian_servis.php?wo=<?=rawurlencode($row['no_work_order'])?>">Rincian lengkap dan biaya</a></details></td>
</tr><?php endforeach; ?>
<?php if (!$hasil['rows']): ?><tr class="ks-ui-empty"><td colspan="<?=$scope===null?7:6?>">Tidak ada data sesuai filter.</td></tr><?php endif; ?>
</tbody></table></div>
<?php else:
    $headers=$hasil['headers'];
    if ($scope!==null) unset($headers['nama_cabang']);
    $moneyKeys=['individual','korporat','total_pendapatan','harga_satuan','subtotal'];
    $numberKeys=['jumlah_stok','stok_minimum','qty'];
?>
<div class="tablewrap"><table id="hasil-laporan" class="ks-ui-cards"><caption class="ks-ui-sr-only"><?=amanHtml($jenisList[$filter['jenis']])?></caption><thead><tr><?php foreach ($headers as $key=>$label): ?><th scope="col" class="<?=in_array($key,array_merge($moneyKeys,$numberKeys),true)?'ks-ui-number':''?>"><?=amanHtml($label)?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($hasil['rows'] as $rowIndex=>$row): ?><tr class="<?=($rowIndex<$reportPage['offset']||$rowIndex>=$reportPage['offset']+$reportPage['size'])?'ks-report-paged-out':''?>"><?php foreach ($headers as $key=>$label):
    $isMoney=in_array($key,$moneyKeys,true);
    $isNumber=in_array($key,$numberKeys,true);
    $value=$isMoney?ksUiRupiah($row[$key]):($isNumber?number_format((int)$row[$key],0,',','.'):
        ($key==='tanggal_masuk'?ksUiTanggal($row[$key],true):$row[$key]));
?><td data-label="<?=amanHtml($label)?>" data-value="<?=amanHtml($row[$key])?>" class="<?=$isMoney||$isNumber?'ks-ui-number':''?>"><?=amanHtml($value)?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$hasil['rows']): ?><tr class="ks-ui-empty"><td colspan="<?=count($headers)?>">Tidak ada data sesuai filter.</td></tr><?php endif; ?>
</tbody></table></div>
<?php endif; ?>
<?php ksUiPager('hal_laporan'); ?>
<p class="muted"><?=count($hasil['rows'])?> baris dimuat; cetak mencakup seluruh baris yang dimuat.<?=$hasil['dibatasi']?' Hasil dibatasi 500 baris. Persempit tanggal atau filter.':''?></p>
</section>
<?php endif; akhirHalamanM2(); ?>
