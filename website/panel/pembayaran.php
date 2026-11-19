<?php
declare(strict_types=1);
/** Owner seluruh cabang, kasir membayar WO individual pada cabang penempatannya. */
require __DIR__.'/../app/layout_m2.php';
require_once __DIR__.'/../app/pembayaran_r01f.php';
require_once __DIR__.'/../app/layanan_r03.php';
$akun=akunLoginWajib();
try {
    $scope=ksPayScope($akun);
} catch (DomainException $e) {
    http_response_code(403);
    exit('403 — Akun tidak memiliki izin modul Pembayaran.');
}
try {
    $db=koneksiKalawaM2();
    ksPayUjiDb($db);
} catch (Throwable $e) {
    error_log('[R01E: DB preflight] '.$e->getMessage());
    http_response_code(503);
    exit('Modul Pembayaran tidak dapat digunakan. Periksa database pengujian dan G0 preflight.');
}

// Token satu kali: disimpan di sisi server, mengikat WO & subtotal saat form dilihat.
if (!isset($_SESSION['ks_pay_tokens']) || !is_array($_SESSION['ks_pay_tokens'])) $_SESSION['ks_pay_tokens']=[];
foreach ($_SESSION['ks_pay_tokens'] as $key=>$meta) {
    if (!is_array($meta) || !is_int($meta['issued']??null) || $meta['issued']<time()-1200) {
        unset($_SESSION['ks_pay_tokens'][$key]);
    }
}
function ksPayTokenTerbit(string $wo,int $gross): string {
    $nonce=bin2hex(random_bytes(24));
    $_SESSION['ks_pay_tokens'][$nonce]=['wo'=>$wo,'gross'=>$gross,'issued'=>time()];
    if (count($_SESSION['ks_pay_tokens'])>10) {
        uasort($_SESSION['ks_pay_tokens'],static fn($a,$b)=>($a['issued']??0)<=>($b['issued']??0));
        $_SESSION['ks_pay_tokens']=array_slice($_SESSION['ks_pay_tokens'],-10,null,true);
    }
    return $nonce;
}

$error='';$success='';$oldMetode='TUNAI';$oldDiskon='0';
$target=trim((string)($_GET['lihat']??$_GET['wo']??''));
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    validasiCsrf();
    $target=is_string($_POST['no_work_order']??null)?trim($_POST['no_work_order']):'';
    $oldMetode=is_string($_POST['metode_pembayaran']??null)?$_POST['metode_pembayaran']:'TUNAI';
    $oldDiskon=is_string($_POST['diskon_member']??null)?$_POST['diskon_member']:'0';
    try {
        if (($_POST['aksi']??'')!=='lunasi') throw new InvalidArgumentException('Tindakan tidak dikenal.');
        $input=ksPayValidasiInput($_POST);
        if (!ksPayAmbilWO($db,$input['wo'],$scope)) {
            throw new OperasionalDitolak('Work Order tidak tersedia untuk pembayaran individual akun ini.',403);
        }
        $meta=$_SESSION['ks_pay_tokens'][$input['token']]??null;
        if (!is_array($meta) || ($meta['wo']??'')!==$input['wo'] ||
            !is_int($meta['issued']??null) || $meta['issued']<time()-1200 ||
            !is_int($meta['gross']??null)) {
            http_response_code(409);
            throw new DomainException('Form pembayaran sudah digunakan atau kedaluwarsa. Buka kembali detail WO.');
        }
        $input['expected_gross']=$meta['gross'];
        $result=ksPayLunasi($db,$akun,$input);
        unset($_SESSION['ks_pay_tokens'][$input['token']]);
        pesanKilAt('Pembayaran WO '.$result['wo'].' berhasil dicatat LUNAS sebesar Rp'.number_format((float)$result['amount'],2,',','.').'.');
        pindah('pembayaran.php?lihat='.rawurlencode($result['wo']).'#rincian-pembayaran');
    } catch (OperasionalDitolak $e) {
        http_response_code($e->status);
        $error=$e->getMessage();
    } catch (DomainException $e) {
        $error=$e->getMessage();
        if (http_response_code()===200) http_response_code(409);
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error=$e->getMessage();
    } catch (PDOException $e) {
        error_log('[Kalawa R01E database] '.$e->getMessage());
        $code=(int)($e->errorInfo[1]??0);
        if ($code===1062) {http_response_code(409);$error='Pembayaran sudah dicatat oleh sesi lain. Muat ulang.';}
        elseif (in_array($code,[1142,1143],true)) {http_response_code(503);$error='Pembayaran belum tersimpan karena izin koneksi database belum lengkap. Hubungi pengelola.';}
        else {$error='Pembayaran gagal. Tidak ada transaksi yang boleh dianggap berhasil. Periksa log server.';}
    } catch (Throwable $e) {
        error_log('[Kalawa R01E] '.$e->getMessage());
        http_response_code(503);
        $error='Proses pembayaran tidak tersedia. Data tidak dinyatakan berhasil.';
    }
}

$search=is_string($_GET['q']??null)?trim($_GET['q']):'';
if (strlen($search)>180) $search=substr($search,0,180);
$statusFilter=is_string($_GET['status']??null)?$_GET['status']:'SIAP';
if (!in_array($statusFilter,['SIAP','SEMUA','LUNAS'],true)) $statusFilter='SIAP';
$sql='SELECT w.no_work_order,w.tanggal_masuk,w.tanggal_selesai,w.kode_cabang,c.nama_cabang,
             w.status_pekerjaan,p.nama_pelanggan,w.no_polisi,
             b.status_pembayaran,b.total_bayar,b.metode_pembayaran,
             (SELECT COALESCE(SUM(d.biaya),0) FROM detail_wo_jasa d
                WHERE d.no_work_order=w.no_work_order) AS subtotal_jasa,
             (SELECT COALESCE(SUM(s.qty*s.harga_satuan),0) FROM detail_wo_suku_cadang s
                WHERE s.no_work_order=w.no_work_order) AS subtotal_suku_cadang
       FROM work_order w
       JOIN cabang c ON c.kode_cabang=w.kode_cabang
       JOIN pelanggan p ON p.id_pelanggan=w.id_pelanggan
       LEFT JOIN pembayaran b ON b.no_work_order=w.no_work_order
       WHERE NOT EXISTS (SELECT 1 FROM work_order_korporat k WHERE k.no_work_order=w.no_work_order)';
$params=[];
if ($scope!==null) {$sql.=' AND w.kode_cabang=:scope';$params['scope']=$scope;}
if ($statusFilter==='SIAP') $sql.=" AND w.status_pekerjaan='SELESAI' AND (b.no_work_order IS NULL OR b.status_pembayaran='BELUM_LUNAS')";
elseif ($statusFilter==='LUNAS') $sql.=" AND b.status_pembayaran='LUNAS'";
if ($search!=='') {
    $sql.=' AND (w.no_work_order LIKE :q1 OR w.no_polisi LIKE :q2 OR p.nama_pelanggan LIKE :q3)';
    $like='%'.addcslashes($search,'\\%_').'%';
    $params['q1']=$like;$params['q2']=$like;$params['q3']=$like;
}
$sql.=' ORDER BY w.tanggal_masuk DESC,w.no_work_order DESC';
$list=ksUiList($db,$sql,$params,'hal_bayar');

$detail=false;$pay=false;$amounts=null;$nonce='';
if ($target!=='') {
    if (!preg_match('/\A[A-Za-z0-9-]{5,20}\z/D',$target)) {
        $error=$error?:'Nomor WO tidak valid.';
    } else {
        $detail=ksPayAmbilWO($db,$target,$scope,false);
        if (!$detail) {
            if ($_SERVER['REQUEST_METHOD']==='GET') http_response_code(404);
            $error='Work Order yang diminta tidak ditemukan.';
        } else {
            $st=$db->prepare('SELECT diskon_member,total_bayar,metode_pembayaran,status_pembayaran
                              FROM pembayaran WHERE no_work_order=:wo');
            $st->execute(['wo'=>$target]);$pay=$st->fetch(PDO::FETCH_ASSOC);
            if ($_SERVER['REQUEST_METHOD']==='GET' && $pay) $oldDiskon=$pay['diskon_member'];
            $amounts=ksPayAmbilTagihan($db,$target,false);
            $gross=$amounts['jasa']+$amounts['spare'];
            if ($detail['status_pekerjaan']==='SELESAI' && $detail['tanggal_selesai']!==null &&
                (!$pay || $pay['status_pembayaran']==='BELUM_LUNAS') && $gross>0) {
                $nonce=ksPayTokenTerbit($target,$gross);
            }
        }
    }
}
$flash=ambilPesanKilAt();
$scopeNama='Seluruh cabang';
if ($scope!==null) {
    $cabangAkun=opsDaftarCabang($db,$scope);
    $scopeNama=(string)($cabangAkun[0]['nama_cabang']??$scope);
}
if($detail && $error==='')ksCetakSiapkan($db,$akun,$pay && $pay['status_pembayaran']==='LUNAS'?'bukti_pembayaran':'rincian_tagihan',
    ['cabang'=>$detail['kode_cabang'],'referensi'=>$detail['no_work_order']]);
awalHalamanM2('Pembayaran', $akun);
?>
<div class="ks-pay-page">
  <div class="ks-pay-title">
    <div><h1>Pembayaran</h1><p>Catat pelunasan dan lihat bukti pembayaran Work Order.</p></div>
    <span class="ks-ui-scope"><?=ksIconR02('boxes')?><?=amanHtml($scopeNama)?></span>
  </div>
  <?php if ($flash): ?><div class="ks-pay-alert ks-pay-alert-ok" role="status"><?=amanHtml($flash['pesan']??'')?></div><?php endif;?>
  <?php if ($error!==''): ?><div class="ks-pay-alert ks-pay-alert-error" role="alert"><?=amanHtml($error)?></div><?php endif;?>
  <?php if ($detail): ?>
  <section class="ks-pay-card ks-cetak-document" id="rincian-pembayaran" aria-labelledby="ks-pay-heading">
    <?php ksCetakKop(); ?>
    <div class="ks-pay-card-head ks-cetak-screen"><div><h2 id="ks-pay-heading"><?=$pay && $pay['status_pembayaran']==='LUNAS'?'Bukti pembayaran':'Rincian pembayaran'?></h2><p><?=amanHtml($detail['no_work_order'])?></p></div><a class="ks-pay-link ks-ui-no-print" href="pembayaran.php">Tutup rincian</a></div>
    <div class="ks-pay-details">
      <div><span>Cabang</span><strong><?=amanHtml($detail['nama_cabang'])?></strong></div>
      <div><span>Status pekerjaan</span><?=ksUiStatus($detail['status_pekerjaan'])?></div>
      <div><span>Pelanggan</span><strong><?=amanHtml($detail['nama_pelanggan'])?></strong></div>
      <div><span>Motor</span><strong><?=amanHtml($detail['no_polisi'])?></strong></div>
    </div>
    <?php $itemWO=ksLayananAmbil($db,$akun,$detail['no_work_order']); if($itemWO) ksLayananItem($itemWO); ?>
    <div class="ks-pay-summary">
      <div><span>Subtotal jasa</span><strong><?=ksPayRupiah($amounts['jasa'])?></strong></div>
      <div><span>Subtotal suku cadang</span><strong><?=ksPayRupiah($amounts['spare'])?></strong></div>
      <div class="ks-pay-subtotal"><span>Subtotal sebelum diskon</span><strong><?=ksPayRupiah($amounts['jasa']+$amounts['spare'])?></strong></div>
      <?php if ($pay): ?>
      <div><span>Metode pembayaran</span><strong><?=amanHtml(ucwords(strtolower(str_replace('_',' ',$pay['metode_pembayaran']))))?></strong></div>
      <div><span>Diskon tercatat</span><strong><?=amanHtml(ksUiRupiah($pay['diskon_member']))?></strong></div>
      <div><span>Status pembayaran</span><?=ksUiStatus($pay['status_pembayaran'])?></div>
      <div class="ks-ui-payment-total"><span><?=$pay['status_pembayaran']==='LUNAS'?'Total dibayar':'Tagihan tercatat'?></span><strong><?=amanHtml(ksUiRupiah($pay['total_bayar']))?></strong></div>
      <?php else: ?><div><span>Status pembayaran</span><?=ksUiStatus(null)?></div><?php endif;?>
    </div>
    <?php if ($nonce!==''): ?>
    <form id="form-pembayaran" class="ks-pay-form" method="post" action="pembayaran.php#rincian-pembayaran" autocomplete="off" onsubmit="if(this.dataset.sent==='1')return false;this.dataset.sent='1';this.querySelector('button[type=submit]').disabled=true;return true;">
      <h3>Catat Pelunasan</h3>
      <p class="ks-pay-help">Satu WO hanya boleh mempunyai satu pembayaran. Total dihitung ulang oleh server saat disimpan.</p>
      <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
      <input type="hidden" name="aksi" value="lunasi">
      <input type="hidden" name="no_work_order" value="<?=amanHtml($detail['no_work_order'])?>">
      <input type="hidden" name="token_bayar" value="<?=amanHtml($nonce)?>">
      <div class="ks-pay-form-grid">
        <label>Diskon nominal (Rp)
          <input type="text" name="diskon_member" inputmode="decimal" maxlength="13" required pattern="(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?" value="<?=amanHtml($oldDiskon)?>" aria-describedby="diskon-hint">
          <small id="diskon-hint">Contoh 0, 5000, atau 5000.50. Tidak boleh lebih besar dari subtotal.</small>
        </label>
        <label>Metode pembayaran
          <select name="metode_pembayaran" required>
            <?php foreach (KSPAY_METODE as $m): ?><option value="<?=amanHtml($m)?>" <?=$oldMetode===$m?'selected':''?>><?=amanHtml(str_replace('_',' ',$m))?></option><?php endforeach;?>
          </select>
          <small>Pastikan pembayaran pelanggan sudah benar-benar diterima sebelum menyimpan.</small>
        </label>
      </div>
      <button class="ks-pay-button" type="submit">Simpan pembayaran lunas</button>
    </form>
    <?php elseif ($pay && $pay['status_pembayaran']==='LUNAS'): ?>
      <div class="ks-pay-alert ks-pay-alert-ok ks-cetak-screen">Pembayaran sudah LUNAS. Tidak ada tombol untuk membayar ulang.</div>
    <?php else: ?>
      <div class="ks-pay-alert ks-pay-alert-info">Belum dapat dibayar. Work Order harus SELESAI, tanggal selesai terisi, dan mempunyai rincian jasa atau suku cadang bernilai positif.</div>
    <?php endif; ?>
    <?php if ($pay && $pay['status_pembayaran']==='LUNAS'):
        $receipt=$db->prepare('SELECT k.id_penerimaan,k.dicatat_pada,a.username FROM penerimaan_kas k
            JOIN akun_aplikasi a ON a.id_akun=k.id_akun WHERE k.no_work_order=:wo');
        $receipt->execute(['wo'=>$target]); $receipt=$receipt->fetch(); ?>
      <p class="ks-cetak-receipt-ref"><?= $receipt ? amanHtml('Bukti #'.$receipt['id_penerimaan'].' · '.ksUiTanggal($receipt['dicatat_pada'],true).' WIB · '.$receipt['username']) : 'Tanggal pelunasan pembayaran lama belum tercatat.' ?></p>
      <button class="light ks-ui-no-print" type="button" onclick="window.print()">Cetak bukti pembayaran</button>
    <?php endif; ?>
  </section>
  <?php endif;?>
  <section class="ks-pay-card ks-ui-payment-list" aria-labelledby="ks-pay-list">
    <div class="ks-pay-card-head"><div><h2 id="ks-pay-list">Daftar pembayaran</h2><p>Pilih Work Order untuk melihat rincian atau mencatat pelunasan.</p></div><span class="ks-ui-count"><?=count($list)?> hasil</span></div>
    <form class="ks-pay-filter" method="get" action="pembayaran.php">
      <label>Tampilkan
        <select name="status"><option value="SIAP" <?=$statusFilter==='SIAP'?'selected':''?>>Selesai belum lunas</option><option value="SEMUA" <?=$statusFilter==='SEMUA'?'selected':''?>>Semua pekerjaan</option><option value="LUNAS" <?=$statusFilter==='LUNAS'?'selected':''?>>Sudah lunas</option></select>
      </label>
      <label>Cari WO, motor, atau pelanggan
        <input name="q" type="search" maxlength="60" placeholder="Nomor WO / polisi / pelanggan" value="<?=amanHtml($search)?>">
      </label>
      <div class="ks-ui-filter-actions"><button class="ks-pay-button" type="submit">Tampilkan</button>
      <a class="ks-pay-link" href="pembayaran.php">Reset</a></div>
    </form>
    <div class="ks-pay-tablewrap"><table class="ks-pay-table ks-ui-cards"><caption class="ks-ui-sr-only">Pembayaran Work Order sesuai filter dan cabang akun</caption><thead><tr><th scope="col">Work Order</th><?php if($scope===null): ?><th scope="col">Cabang</th><?php endif; ?><th scope="col">Pelanggan / motor</th><th scope="col">Pekerjaan</th><th scope="col" class="ks-ui-number">Jumlah</th><th scope="col">Pembayaran</th><th scope="col">Aksi</th></tr></thead><tbody>
      <?php foreach ($list as $item):
        $lunas=$item['status_pembayaran']==='LUNAS';
        $biaya=(float)$item['subtotal_jasa']+(float)$item['subtotal_suku_cadang'];
        $nominal=$item['total_bayar']??number_format($biaya,2,'.','');
        $aksiLabel=$lunas?'Lihat bukti':($item['status_pekerjaan']==='SELESAI' && $item['tanggal_selesai']!==null && $biaya>0?'Catat pembayaran':'Lihat rincian');
        $jumlahLabel=$lunas?'Dibayar':($item['total_bayar']!==null?'Tagihan tercatat':'Biaya aktual');
      ?>
      <tr><td class="ks-ui-identity" data-label="Work Order"><strong><?=amanHtml($item['no_work_order'])?></strong><small><?=amanHtml(ksUiTanggal($item['tanggal_masuk'],true))?></small></td>
      <?php if($scope===null): ?><td data-label="Cabang"><?=amanHtml($item['nama_cabang'])?></td><?php endif; ?><td data-label="Pelanggan / motor"><?=amanHtml($item['nama_pelanggan'])?><small><?=amanHtml($item['no_polisi'])?></small></td>
      <td data-label="Pekerjaan"><?=ksUiStatus($item['status_pekerjaan'])?></td>
      <td class="ks-ui-number" data-label="Jumlah" data-value="<?=amanHtml($nominal)?>"><strong><?=amanHtml(ksUiRupiah($nominal))?></strong><small><?=amanHtml($jumlahLabel)?></small></td>
      <td data-label="Pembayaran"><?=ksUiStatus($item['status_pembayaran'])?></td>
      <td class="ks-ui-row-action" data-label="Aksi"><a class="ks-ui-action" aria-label="<?=amanHtml($aksiLabel.' '.$item['no_work_order'])?>" href="pembayaran.php?lihat=<?=rawurlencode($item['no_work_order'])?>#rincian-pembayaran"><?=amanHtml($aksiLabel)?></a></td></tr>
      <?php endforeach;?>
      <?php if (!$list): ?><tr class="ks-ui-empty"><td colspan="<?=$scope===null?7:6?>">Tidak ada Work Order sesuai filter. <?php if($statusFilter==='SIAP'): ?>Pekerjaan harus selesai sebelum dapat dibayar. <a href="pembayaran.php?status=SEMUA">Lihat semua pekerjaan</a><?php endif; ?></td></tr><?php endif;?>
    </tbody></table></div>
<?php ksUiPager('hal_bayar'); ?>
    <p class="ks-pay-help">Daftar mengikuti filter dan cakupan cabang akun Anda.</p>
  </section>
</div>
<?php akhirHalamanM2();
