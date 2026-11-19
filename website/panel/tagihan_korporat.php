<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/tagihan_korporat_m2.php';
$akun = akunLoginWajib();
try {
    $scope = opsCabang($akun, ['rl_owner_ops','rl_cashier_ops']);
} catch (OperasionalDitolak $e) {
    http_response_code(403);
    exit('Akun tidak memiliki izin untuk tagihan korporat.');
}
$db = koneksiKalawaM2();
$owner = $akun['role_db'] === 'rl_owner_ops';
$error = '';
$selected = is_string($_GET['id'] ?? null) ? trim($_GET['id']) : '';
$bulan = is_string($_GET['bulan'] ?? null) ? trim($_GET['bulan']) :
    (string) $db->query("SELECT DATE_FORMAT(CURDATE()-INTERVAL 1 MONTH,'%Y-%m')")->fetchColumn();
$nomor = is_string($_GET['lihat'] ?? null) ? $_GET['lihat'] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validasiCsrf();
    try {
        $aksi = opsTeks($_POST, 'aksi');
        $token = opsTeks($_POST, 'token_transaksi');
        if ($aksi === 'terbitkan') {
            $selected = opsTeks($_POST, 'id_pelanggan');
            $bulan = opsTeks($_POST, 'bulan');
            $snapshot = opsBacaToken($token, $aksi, $selected . '/' . $bulan);
            $nomor = tagihanTerbitkan($db, $akun, $selected, $bulan, $snapshot);
            pesanKilAt('Tagihan kolektif #' . $nomor . ' berhasil diterbitkan.');
        } elseif ($aksi === 'lunasi') {
            $nomor = opsTeks($_POST, 'id_tagihan');
            $snapshot = opsBacaToken($token, $aksi, $nomor);
            tagihanLunasi($db, $akun, $nomor, opsTeks($_POST, 'metode_pembayaran'), $snapshot);
            pesanKilAt('Tagihan kolektif #' . $nomor . ' berhasil dilunasi.');
        } else {
            throw new InvalidArgumentException('Aksi tidak dikenali.');
        }
        unset($_SESSION['ops_tokens'][$token]);
        pindah('tagihan_korporat.php?lihat=' . rawurlencode($nomor));
    } catch (OperasionalDitolak $e) {
        http_response_code($e->status);
        $error = $e->getMessage();
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[Kalawa tagihan] ' . $e->getMessage());
        http_response_code(503);
        $error = 'Tagihan belum berhasil diproses. Periksa log server.';
    }
}
$companies = $owner ? $db->query('SELECT p.id_pelanggan,p.nama_pelanggan FROM pelanggan p
    JOIN pelanggan_korporat k ON k.id_pelanggan=p.id_pelanggan ORDER BY p.nama_pelanggan')->fetchAll() : [];
$preview = [];
if ($owner && $selected !== '' && $nomor === '') {
    try {
        if (!in_array($selected, array_column($companies,'id_pelanggan'), true)) {
            throw new InvalidArgumentException('Pilih perusahaan dari daftar.');
        }
        $preview = tagihanCalon($db, $selected, $bulan);
    } catch (InvalidArgumentException|OperasionalDitolak $e) {
        if ($error === '') $error = $e->getMessage();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') http_response_code(422);
    }
}
$header = false;
$details = [];
if ($nomor !== '') {
    $header = preg_match('/\A[1-9][0-9]{0,18}\z/D',$nomor) ? tagihanAmbil($db,$nomor,$scope) : false;
    if (!$header) {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') http_response_code(404);
        $error = $error ?: 'Tagihan tidak ditemukan pada cakupan akun ini.';
    } else {
        $details = tagihanRincian($db,$nomor);
    }
}
$sql = 'SELECT t.id_tagihan,t.periode,t.status_pembayaran,t.dibayar_pada,p.nama_pelanggan,
    COALESCE(SUM(d.nilai_final),0) AS total,COUNT(d.no_work_order) AS jumlah_wo
    FROM tagihan_korporat t JOIN pelanggan p ON p.id_pelanggan=t.id_pelanggan
    JOIN detail_tagihan_korporat d ON d.id_tagihan=t.id_tagihan';
$args = [];
if ($scope !== null) {
    $sql .= ' WHERE NOT EXISTS (SELECT 1 FROM detail_tagihan_korporat dx
        JOIN work_order wx ON wx.no_work_order=dx.no_work_order
        WHERE dx.id_tagihan=t.id_tagihan AND wx.kode_cabang<>:cabang)';
    $args['cabang'] = $scope;
}
$sql .= ' GROUP BY t.id_tagihan,t.periode,t.status_pembayaran,t.dibayar_pada,p.nama_pelanggan
    ORDER BY t.id_tagihan DESC';
$list=ksUiList($db,$sql,$args,'hal_tagihan');
$flash = ambilPesanKilAt();
if($header && $error==='')ksCetakSiapkan($db,$akun,$header['status_pembayaran']==='LUNAS'?'bukti_korporat':'tagihan_korporat',
    ['cabang'=>array_column($details,'kode_cabang'),'referensi'=>'Tagihan #'.$nomor,'periode'=>ksCetakBulan(substr($header['periode'],0,7)),
     'nama_file'=>'Tagihan-'.$nomor.' - '.substr($header['periode'],0,7),'metadata'=>['Perusahaan'=>$header['nama_pelanggan'],'Jumlah WO'=>count($details).' Work Order']]);
awalHalamanM2('Tagihan Korporat',$akun);
?>
<h1>Tagihan korporat</h1>
<p class="muted">Pelunasan bersama untuk WO selesai pada satu bulan layanan.</p>
<?php if ($flash): ?><div class="alert" role="status"><?=amanHtml($flash['pesan'])?></div><?php endif; ?>
<?php if ($error): ?><div class="alert bad" role="alert"><?=amanHtml($error)?></div><?php endif; ?>
<?php if (!$owner): ?><p class="muted">Kasir menangani tagihan yang seluruh WO-nya berada di cabang penempatannya. Tagihan lintas cabang ditangani Owner.</p><?php endif; ?>
<?php if ($header): ?>
<section class="panel ks-cetak-document" id="rincian-tagihan">
  <?php ksCetakKop(); ?>
  <div class="row space ks-cetak-screen"><h2>Tagihan #<?=amanHtml($nomor)?></h2><a class="btn light ks-ui-no-print" href="tagihan_korporat.php">Kembali ke daftar</a></div>
  <p class="ks-cetak-screen"><strong><?=amanHtml($header['nama_pelanggan'])?></strong> · <?=amanHtml(substr($header['periode'],0,7))?></p>
  <p>Status <?=ksUiStatus($header['status_pembayaran'])?> · Terbit <?=amanHtml(ksUiTanggal($header['terbit_pada'],true))?> WIB</p>
  <div class="tablewrap"><table id="tabel-rincian-tagihan" class="ks-ui-cards"><caption class="ks-ui-sr-only">Work Order dalam tagihan korporat</caption><thead><tr><th scope="col">WO</th><th scope="col">Motor</th><th scope="col">Cabang</th><th scope="col">Selesai</th><th scope="col" class="ks-ui-number">Tagihan</th></tr></thead><tbody>
  <?php foreach ($details as $row): ?><tr>
    <td class="ks-ui-identity" data-label="Work Order"><strong><?=amanHtml($row['no_work_order'])?></strong><br><a href="rincian_servis.php?wo=<?=rawurlencode($row['no_work_order'])?>">Rincian jasa dan barang</a></td>
    <td data-label="Motor"><?=amanHtml($row['no_polisi'])?></td>
    <td data-label="Cabang"><?=amanHtml($row['nama_cabang'])?></td>
    <td data-label="Selesai"><?=amanHtml(ksUiTanggal($row['tanggal_selesai'],true))?></td>
    <td class="ks-ui-number" data-label="Tagihan" data-value="<?=amanHtml($row['nilai_final'])?>"><?=amanHtml(ksUiRupiah($row['nilai_final']))?></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
  <p class="ks-cetak-corp-total">Total <strong id="total-tagihan"><?=amanHtml(ksPayRupiah(tagihanTotal($details)))?></strong></p>
  <?php if ($header['status_pembayaran'] === 'BELUM_LUNAS' && $details): ?>
  <form method="post" action="tagihan_korporat.php" id="form-lunasi-korporat" class="ks-ui-no-print">
    <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
    <input type="hidden" name="aksi" value="lunasi">
    <input type="hidden" name="id_tagihan" value="<?=amanHtml($nomor)?>">
    <input type="hidden" name="token_transaksi" value="<?=amanHtml(opsToken('lunasi',$nomor,tagihanFingerprint($details)))?>">
    <label for="metode_pembayaran">Metode pembayaran</label>
    <select name="metode_pembayaran" id="metode_pembayaran" required><?php foreach (KSPAY_METODE as $metode): ?><option><?=amanHtml($metode)?></option><?php endforeach; ?></select>
    <p class="muted">Simpan setelah seluruh nilai tagihan diterima.</p>
    <button type="submit">Catat pelunasan kolektif</button>
  </form>
  <?php else: ?><p>Pelunasan <?=amanHtml(ksUiTanggal($header['dibayar_pada'],true))?> WIB · <?=amanHtml(str_replace('_',' ',$header['metode_pembayaran']??''))?></p><?php endif; ?>
  <button type="button" class="light ks-ui-no-print" onclick="window.print()">Cetak tagihan</button>
</section>
<?php endif; ?>
<?php if ($owner && !$header): ?>
<section class="panel ks-ui-no-print">
  <h2>Siapkan tagihan bulanan</h2>
  <form method="get" action="tagihan_korporat.php" id="form-pratinjau-tagihan">
    <label for="perusahaan">Perusahaan</label><select name="id" id="perusahaan" required><option value="">Pilih perusahaan</option><?php foreach ($companies as $p): ?><option value="<?=amanHtml($p['id_pelanggan'])?>" <?=$selected===$p['id_pelanggan']?'selected':''?>><?=amanHtml($p['nama_pelanggan'].' · '.$p['id_pelanggan'])?></option><?php endforeach; ?></select>
    <label for="bulan">Bulan layanan</label><input type="month" name="bulan" id="bulan" value="<?=amanHtml($bulan)?>" required>
    <button type="submit">Tampilkan pratinjau</button>
  </form>
  <?php if ($selected !== ''): ?>
  <h3>WO yang dapat ditagihkan</h3>
  <div class="tablewrap"><table id="pratinjau-tagihan-korporat" class="ks-ui-cards"><caption class="ks-ui-sr-only">Pratinjau Work Order yang akan ditagihkan</caption><thead><tr><th scope="col">WO</th><th scope="col">Motor</th><th scope="col">Cabang</th><th scope="col" class="ks-ui-number">Nilai</th></tr></thead><tbody>
    <?php foreach ($preview as $row): ?><tr>
      <td class="ks-ui-identity" data-label="Work Order"><strong><?=amanHtml($row['no_work_order'])?></strong><br><a href="rincian_servis.php?wo=<?=rawurlencode($row['no_work_order'])?>">Rincian jasa dan barang</a></td>
      <td data-label="Motor"><?=amanHtml($row['no_polisi'])?></td>
      <td data-label="Cabang"><?=amanHtml($row['kode_cabang'])?></td>
      <td class="ks-ui-number" data-label="Nilai" data-value="<?=amanHtml($row['nilai_final'])?>"><?=amanHtml(ksUiRupiah($row['nilai_final']))?></td>
    </tr><?php endforeach; ?>
    <?php if (!$preview): ?><tr class="ks-ui-empty"><td colspan="4">Belum ada WO selesai yang dapat ditagihkan pada bulan ini.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php if ($preview): ?>
  <p>Total <strong><?=amanHtml(ksPayRupiah(tagihanTotal($preview)))?></strong></p>
  <form method="post" action="tagihan_korporat.php" id="form-terbitkan-korporat">
    <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
    <input type="hidden" name="aksi" value="terbitkan">
    <input type="hidden" name="id_pelanggan" value="<?=amanHtml($selected)?>">
    <input type="hidden" name="bulan" value="<?=amanHtml($bulan)?>">
    <input type="hidden" name="token_transaksi" value="<?=amanHtml(opsToken('terbitkan',$selected.'/'.$bulan,tagihanFingerprint($preview)))?>">
    <button type="submit">Terbitkan tagihan kolektif</button>
  </form>
  <?php endif; endif; ?>
</section>
<?php endif; ?>
<section class="panel<?=$header?' ks-ui-no-print':''?>"><h2>Daftar tagihan</h2>
<div class="tablewrap"><table id="daftar-tagihan-korporat" class="ks-ui-cards"><caption class="ks-ui-sr-only">Daftar tagihan korporat sesuai cakupan akun</caption><thead><tr><th scope="col">Tagihan</th><th scope="col">Perusahaan</th><th scope="col">Periode</th><th scope="col" class="ks-ui-number">Jumlah WO</th><th scope="col" class="ks-ui-number">Total</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody>
<?php foreach ($list as $row): ?><tr>
  <td class="ks-ui-identity" data-label="Tagihan"><strong>Tagihan #<?=amanHtml($row['id_tagihan'])?></strong></td>
  <td data-label="Perusahaan"><?=amanHtml($row['nama_pelanggan'])?></td>
  <td data-label="Periode"><?=amanHtml(substr($row['periode'],0,7))?></td>
  <td class="ks-ui-number" data-label="Jumlah WO"><?=amanHtml($row['jumlah_wo'])?></td>
  <td class="ks-ui-number" data-label="Total" data-value="<?=amanHtml($row['total'])?>"><strong><?=amanHtml(ksUiRupiah($row['total']))?></strong></td>
  <td data-label="Status"><?=ksUiStatus($row['status_pembayaran'])?></td>
  <td class="ks-ui-row-action" data-label="Aksi"><a class="ks-ui-action" aria-label="<?=amanHtml('Lihat tagihan #'.$row['id_tagihan'])?>" href="tagihan_korporat.php?lihat=<?=amanHtml($row['id_tagihan'])?>">Lihat tagihan</a></td>
</tr><?php endforeach; ?>
<?php if (!$list): ?><tr class="ks-ui-empty"><td colspan="7">Belum ada tagihan pada cakupan akun ini.</td></tr><?php endif; ?>
</tbody></table></div>
<?php ksUiPager('hal_tagihan'); ?><p class="muted">Tagihan diurutkan dari yang terbaru sesuai cakupan akun.</p></section>
<?php akhirHalamanM2(); ?>
