<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
$akun = akunLoginWajib();
if ($akun['role_db'] === 'rl_service_tech') {
    pindah('pekerjaan.php');
}
if ($akun['role_db'] === 'rl_cashier_ops') pindah('pembayaran.php');
if ($akun['role_db'] === 'rl_inventory_ops') pindah('stok.php');
if ($akun['role_db'] === 'rl_internal_audit') pindah('laporan.php');
require_once __DIR__ . '/../app/rbac_wo_m2.php';
$owner = (string)($akun['role_db'] ?? '') === 'rl_owner_ops';
$woAllowed=ksWOBolehAkses($akun);
$woScope=null;
if ($woAllowed) {
    try { $woScope=ksWOCabangTerikat($akun); }
    catch (DomainException $e) {$woAllowed=false;}
}
$stats = ['MENUNGGU'=>0,'PROSES'=>0,'SELESAI'=>0,'HARI_INI'=>0];
$rows = [];
$warn = '';
if ($woAllowed) {
    try {
        // Cabang ditetapkan oleh session backend; bukan oleh parameter GET.
        $db = koneksiKalawaM2();
        $query = "SELECT
                  COUNT(CASE WHEN status_pekerjaan='MENUNGGU' THEN 1 END) AS menunggu,
                  COUNT(CASE WHEN status_pekerjaan='PROSES' THEN 1 END) AS proses,
                  COUNT(CASE WHEN status_pekerjaan='SELESAI' THEN 1 END) AS selesai,
                  COUNT(CASE WHEN DATE(tanggal_masuk)=CURRENT_DATE() THEN 1 END) AS hari_ini
                  FROM work_order" . ($woScope !== null ? " WHERE kode_cabang=:scope" : "");
        $st=$db->prepare($query);
        $st->execute($woScope===null?[]:['scope'=>$woScope]);
        $counts=$st->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats=['MENUNGGU'=>(int)($counts['menunggu']??0),'PROSES'=>(int)($counts['proses']??0),
                'SELESAI'=>(int)($counts['selesai']??0),'HARI_INI'=>(int)($counts['hari_ini']??0)];
        $st=$db->prepare("SELECT w.no_work_order,w.no_polisi,w.tanggal_masuk,w.status_pekerjaan
                         FROM work_order w
                         " . ($woScope!==null ? "WHERE w.kode_cabang=:scope " : "") .
                         "ORDER BY w.tanggal_masuk DESC,w.no_work_order DESC LIMIT 6");
        $st->execute($woScope===null?[]:['scope'=>$woScope]);
        $rows=$st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[KS Workspace R02 dashboard] '.$e->getMessage());
        $warn='Ringkasan terbaru belum dapat dimuat. Coba buka Work Order atau hubungi pengelola.';
    }
}
awalHalamanM2('Ringkasan', $akun);
?>
<div class="ks-dashboard">
  <div class="ks-dashboard-head"><div><h1>Ringkasan Operasional</h1><p><?= $woAllowed ? ($woScope===null?'Perkembangan pekerjaan servis di seluruh cabang.':'Pekerjaan servis di cabang Anda.') : 'Informasi akun dan akses operasional.' ?></p></div></div>
  <?php if ($warn): ?><div class="alert bad" role="alert"><?=amanHtml($warn)?></div><?php endif; ?>
  <?php if ($woAllowed): ?>
  <div class="ks-dashboard-kpis" aria-label="Ringkasan Work Order">
    <div class="ks-kpi"><div class="ks-kpi-title">WO Menunggu</div><div class="ks-kpi-number"><?= $stats['MENUNGGU'] ?></div><div class="ks-kpi-foot">Pekerjaan belum dimulai</div></div>
    <div class="ks-kpi"><div class="ks-kpi-title">Dalam Proses</div><div class="ks-kpi-number"><?= $stats['PROSES'] ?></div><div class="ks-kpi-foot">Pekerjaan sedang berjalan</div></div>
    <div class="ks-kpi"><div class="ks-kpi-title">WO Selesai</div><div class="ks-kpi-number"><?= $stats['SELESAI'] ?></div><div class="ks-kpi-foot">Status pekerjaan selesai, bukan status bayar</div></div>
    <div class="ks-kpi"><div class="ks-kpi-title">WO Masuk Hari Ini</div><div class="ks-kpi-number"><?= $stats['HARI_INI'] ?></div><div class="ks-kpi-foot">Tanggal sesuai waktu server database</div></div>
  </div>
  <div class="ks-dashboard-grid">
    <section class="panel" aria-labelledby="ks-recent-title">
      <div class="ks-dash-heading"><h2 id="ks-recent-title">Work Order Terbaru</h2><a class="ks-dash-action" href="work_order.php">Lihat Semua</a></div>
      <?php if ($rows): ?>
      <div class="tablewrap" tabindex="0" role="region" aria-label="Daftar Work Order terbaru, dapat digulir">
        <table class="ks-recent-table"><thead><tr><th>Work Order</th><th>Motor</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach ($rows as $w): ?>
          <tr><td><?=amanHtml($w['no_work_order'])?><small><?=amanHtml($w['tanggal_masuk'])?></small></td>
              <td><?=amanHtml($w['no_polisi'])?></td>
              <td><span class="ks-status-badge <?=amanHtml($w['status_pekerjaan'])?>"><?=amanHtml(ucwords(strtolower(str_replace('_',' ',$w['status_pekerjaan']))))?></span></td>
              <td><a href="work_order.php?lihat=<?=rawurlencode($w['no_work_order'])?>#detail-wo">Lihat</a></td></tr>
        <?php endforeach; ?>
        </tbody></table>
      </div>
      <?php else: ?><div class="ks-dashboard-empty">Belum ada Work Order yang dapat ditampilkan.</div><?php endif; ?>
    </section>
    <aside class="panel" aria-labelledby="ks-quick-title">
      <div class="ks-dash-heading"><h2 id="ks-quick-title">Aksi Cepat</h2></div>
      <p class="muted">Pilih pekerjaan yang perlu ditangani.</p>
      <div class="ks-quick-links"><a href="work_order.php?buat=1#buat-wo">+ Buat Work Order</a><?php if ($owner): ?><a href="stok.php">Periksa Stok Bengkel</a><a href="pelanggan.php">Lihat Pelanggan</a><?php endif; ?></div>
      <div class="ks-dashboard-info">Buka detail Work Order untuk memperbarui status pekerjaan sesuai izin akun.</div>
    </aside>
  </div>
  <?php else: ?>
  <section class="panel"><h2>Selamat datang, <?=amanHtml((string)($akun['username']??'Petugas'))?></h2>
    <p class="muted">Peran Anda: <strong><?=amanHtml(ROLE_KALAWA[$akun['role_db']]??'Petugas')?></strong>.</p>
    <p>Akun Anda berhasil masuk. Fitur sesuai tugas dan kewenangan petugas akan tersedia pada tahap berikutnya.</p>
  </section>
  <?php endif; ?>
</div>
<?php akhirHalamanM2(); ?>
