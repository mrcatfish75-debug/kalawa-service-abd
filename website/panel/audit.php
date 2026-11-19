<?php
declare(strict_types=1);
require __DIR__.'/../app/layout_m2.php';
require_once __DIR__.'/../app/operasional_m2.php';
$akun=akunLoginWajib();
if(!in_array($akun['role_db'],['rl_owner_ops','rl_internal_audit'],true)){http_response_code(403);exit('Jejak audit tersedia untuk Owner dan Auditor.');}
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);header('Allow: GET');exit('Jejak audit hanya dapat dibaca.');}
$db=koneksiKalawaM2();$error='';$rows=[];$mutasi=[];$kas=[];$pembayaranTerpadu=[];$q='';
try {
    $q=opsTeks($_GET,'q');if(strlen($q)>64)throw new InvalidArgumentException('Pencarian maksimal 64 karakter.');
    $pembayaranTerpadu=ksUiList($db,'SELECT no_work_order,kode_cabang,jenis_transaksi,id_tagihan,status_pembayaran,nilai_wo,penerimaan_wo,metode_pembayaran,waktu_penerimaan FROM vw_ringkasan_transaksi_terpadu'.($q!==''?' WHERE no_work_order=:q':'').' ORDER BY tanggal_masuk DESC,no_work_order DESC',$q!==''?['q'=>$q]:[],'hal_transaksi');
    $rows=ksUiList($db,'SELECT id_audit,objek_data,kunci_rekam,jenis_operasi,atribut_berubah,nilai_sebelum,nilai_sesudah,akun_sesi,waktu_kejadian
        FROM log_audit_operasional'.($q!==''?' WHERE kunci_rekam=:q':'').' ORDER BY id_audit DESC',$q!==''?['q'=>$q]:[],'hal_perubahan');
    if($q==='') {
        $mutasi=ksUiList($db,'SELECT m.id_mutasi,m.kode_cabang,m.kode_suku_cadang,m.jenis_mutasi,m.stok_sebelum,m.stok_sesudah,m.catatan,m.dicatat_pada,a.username
            FROM mutasi_stok_manual m JOIN akun_aplikasi a ON a.id_akun=m.id_akun ORDER BY m.id_mutasi DESC',[],'hal_mutasi');
    }
    $kas=ksUiList($db,'SELECT k.id_penerimaan,k.no_work_order,k.id_tagihan,k.dicatat_pada,a.username FROM penerimaan_kas k
        JOIN akun_aplikasi a ON a.id_akun=k.id_akun'.($q!==''?' WHERE k.no_work_order=:q OR k.id_tagihan IN (SELECT d.id_tagihan FROM detail_tagihan_korporat d WHERE d.no_work_order=:qk)':'').' ORDER BY k.id_penerimaan DESC',$q!==''?['q'=>$q,'qk'=>$q]:[],'hal_kas');
} catch(InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
catch(Throwable $e){error_log('[Kalawa audit] '.$e->getMessage());http_response_code(503);$error='Jejak audit belum dapat dimuat. Hubungi pengelola.';}
if($error==='')ksCetakSiapkan($db,$akun,'audit',['referensi'=>$q!==''?'WO '.$q:'Seluruh catatan sesuai halaman aktif',
    'nama_file'=>($q!==''?$q:'Halaman-Aktif').' - '.date('Y-m-d'),
    'catatan'=>'Setiap daftar mencetak halaman yang sedang dibuka. Nomor halaman dan jumlah hasil ditulis di bawah masing-masing tabel.']);
awalHalamanM2('Jejak audit',$akun);
?>
<div class="ks-ui-page-head"><div><h1>Jejak audit</h1><p>Perubahan status, penerimaan pembayaran, dan mutasi stok yang tercatat</p></div><button class="light ks-ui-no-print" type="button" onclick="window.print()">Cetak halaman audit</button></div>
<div class="ks-cetak-document"><?php ksCetakKop(); ?>
<p class="ks-r03-note">Catatan perubahan lama belum mengidentifikasi pengguna aplikasi yang melakukan perubahan. Penerimaan kas dan mutasi manual menampilkan nama akun pencatat. Gunakan keduanya saat menelusuri transaksi.</p>
<form class="panel ks-ui-no-print" method="get"><label for="audit-q">Nomor Work Order persis <span class="ks-ui-optional">(kosongkan untuk terbaru)</span></label><div class="row"><input id="audit-q" name="q" maxlength="64" value="<?=amanHtml($q)?>"><button type="submit">Cari</button><a class="ks-ui-action" href="audit.php">Reset</a></div></form>
<?php if($error): ?><p class="alert bad" role="alert"><?=amanHtml($error)?></p><?php endif; ?>
<section class="panel"><h2>Ringkasan pembayaran per WO</h2>
<p class="muted">Pembayaran individual dan korporat ditampilkan bersama. Nilai korporat adalah bagian WO pada tagihan kolektif; total tagihan tidak diulang pada setiap WO. Waktu kosong berarti belum tercatat.</p>
<div class="tablewrap"><table><thead><tr><th>WO / cabang</th><th>Jenis / tagihan</th><th>Status</th><th class="ks-ui-number">Nilai WO</th><th class="ks-ui-number">Penerimaan WO</th><th>Metode / waktu</th></tr></thead><tbody>
<?php foreach($pembayaranTerpadu as $r): $labelAudit=match($r['status_pembayaran']){'BELUM_DITAGIHKAN'=>'Belum ditagihkan','TIDAK_DITAGIH'=>'Tidak ditagih',default=>null}; ?>
<tr><td><a href="rincian_servis.php?wo=<?=rawurlencode($r['no_work_order'])?>"><?=amanHtml($r['no_work_order'])?></a><br><?=amanHtml($r['kode_cabang'])?></td>
<td><?= $r['jenis_transaksi']==='KORPORAT'?'Korporat':'Individual' ?><?php if($r['id_tagihan']!==null): ?><br>Tagihan #<?=amanHtml($r['id_tagihan'])?><?php endif; ?></td>
<td><?php if($labelAudit!==null): ?><span class="ks-ui-badge ks-ui-badge--neutral"><?=amanHtml($labelAudit)?></span><?php else: ?><?=ksUiStatus($r['status_pembayaran'])?><?php endif; ?></td>
<td class="ks-ui-number"><?=amanHtml(ksUiRupiah($r['nilai_wo']))?></td><td class="ks-ui-number"><?=amanHtml(ksUiRupiah($r['penerimaan_wo']))?></td>
<td><?=amanHtml(str_replace('_',' ',$r['metode_pembayaran']??'—'))?><br><?=amanHtml(ksUiTanggal($r['waktu_penerimaan'],true))?></td></tr>
<?php endforeach; ?><?php if(!$pembayaranTerpadu): ?><tr><td colspan="6">Belum ada WO sesuai pencarian</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_transaksi'); ?>
<p class="muted">Pencetakan mengikuti halaman yang sedang dibuka. Penerimaan historis tanpa waktu belum bisa dimasukkan ke rentang tanggal tertentu.</p></section>
<section class="panel"><h2>Perubahan data</h2><div class="tablewrap"><table><thead><tr><th>Waktu / objek</th><th>Kunci rekam</th><th>Perubahan</th><th>Nilai</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=amanHtml(ksUiTanggal($r['waktu_kejadian'],true))?><br><?=amanHtml($r['objek_data'])?></td><td><?=amanHtml($r['kunci_rekam'])?></td><td><?=amanHtml($r['jenis_operasi'].' · '.$r['atribut_berubah'])?></td><td class="ks-r03-prose"><strong>Sebelum:</strong> <?=amanHtml($r['nilai_sebelum']??'—')?><br><strong>Sesudah:</strong> <?=amanHtml($r['nilai_sesudah']??'—')?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="4">Belum ada catatan sesuai pencarian</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_perubahan'); ?></section>
<section class="panel"><h2>Penerimaan kas</h2><div class="tablewrap"><table><thead><tr><th>Bukti</th><th>Transaksi</th><th>Pencatat</th><th>Waktu</th></tr></thead><tbody>
<?php foreach($kas as $r): ?><tr><td>#<?=amanHtml($r['id_penerimaan'])?></td><td><?=amanHtml($r['no_work_order']??('Tagihan #'.$r['id_tagihan']))?></td><td><?=amanHtml($r['username'])?></td><td><?=amanHtml(ksUiTanggal($r['dicatat_pada'],true))?></td></tr><?php endforeach; ?>
<?php if(!$kas): ?><tr><td colspan="4">Belum ada penerimaan kas sesuai pencarian</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_kas'); ?></section>
<?php if($q===''): ?><section class="panel"><h2>Mutasi stok manual</h2><div class="tablewrap"><table><thead><tr><th>Waktu / pencatat</th><th>Cabang / barang</th><th>Perubahan stok</th><th>Catatan</th></tr></thead><tbody>
<?php foreach($mutasi as $r): ?><tr><td><?=amanHtml(ksUiTanggal($r['dicatat_pada'],true))?><br><?=amanHtml($r['username'])?></td><td><?=amanHtml($r['kode_cabang'].' · '.$r['kode_suku_cadang'])?></td><td><?=amanHtml($r['jenis_mutasi'])?> · <?=amanHtml($r['stok_sebelum']??'Belum ada')?> → <?=amanHtml($r['stok_sesudah'])?></td><td><?=amanHtml($r['catatan'])?></td></tr><?php endforeach; ?>
<?php if(!$mutasi): ?><tr><td colspan="4">Belum ada mutasi manual yang tercatat</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_mutasi'); ?></section><?php endif; ?></div><?php akhirHalamanM2(); ?>
