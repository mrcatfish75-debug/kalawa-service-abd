<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/wo_r01b_m2.php';
require_once __DIR__ . '/../app/wo_status_m2.php';
require_once __DIR__.'/../app/layanan_r03.php';

$akun = akunLoginWajib();
if ($akun['role_db'] !== 'rl_service_tech') {
    http_response_code(403);
    exit('Halaman Pekerjaan Saya tersedia untuk akun mekanik.');
}
$error = '';
$selectedId = is_string($_GET['lihat'] ?? null) ? trim($_GET['lihat']) : '';
try {
    $db = koneksiKalawaM2();
    if (!woStatusMekanikAktif($db, $akun)) {
        http_response_code(403);
        exit('Akun memerlukan penempatan mekanik aktif pada cabang Anda.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (($_POST['aksi'] ?? null) !== 'r01d_status') {
            http_response_code(400);
            exit('Tindakan Pekerjaan Saya tidak valid.');
        }
        [$selectedId, $error] = woStatusTanganiPost($db, $akun, 'pekerjaan.php');
    }
    if ($selectedId !== '' && !preg_match('/\A[A-Za-z0-9-]{1,20}\z/D', $selectedId)) {
        http_response_code(400);
        $selectedId = '';
        $error = 'Nomor Work Order tidak valid.';
    }

    // Penugasan historis lintas cabang tetap berada di luar cakupan mekanik.
    $mekanikId = $akun['id_pegawai'];
    $params = ['cabang' => $akun['kode_cabang'], 'utama' => $mekanikId, 'pembantu' => $mekanikId, 'penugas' => $mekanikId];
    $sql = "SELECT w.no_work_order, w.no_polisi, w.tanggal_masuk, w.tanggal_selesai,
            w.status_pekerjaan, (w.id_mekanik_pj = :penugas) AS tugas_utama, w.keluhan, m.merek, m.tipe
        FROM work_order w JOIN motor m ON m.no_polisi = w.no_polisi
        WHERE w.kode_cabang = :cabang AND (w.id_mekanik_pj = :utama OR EXISTS (
            SELECT 1 FROM mekanik_pembantu_wo p
            WHERE p.no_work_order = w.no_work_order AND p.id_mekanik = :pembantu
        ))";
    if ($selectedId !== '') {
        $sql .= ' AND w.no_work_order = :nomor';
        $params['nomor'] = $selectedId;
    }
    $sql.=" ORDER BY (w.status_pekerjaan IN ('MENUNGGU', 'PROSES')) DESC,w.tanggal_masuk DESC,w.no_work_order DESC";
    if($selectedId==='')$rows=ksUiList($db,$sql,$params,'hal_tugas');
    else{$st=$db->prepare($sql);$st->execute($params);$rows=$st->fetchAll();}
    $detail = $selectedId !== '' ? ($rows[0] ?? null) : null;
    if ($selectedId !== '' && !$detail && $error === '') {
        http_response_code(404);
        $error = 'Pekerjaan yang diminta tidak ditemukan pada penugasan Anda.';
    }
} catch (PDOException $e) {
    error_log('[Kalawa pekerjaan R01D READ] ' . $e->getMessage());
    http_response_code(503);
    exit('Daftar pekerjaan belum dapat dibuka. Hubungi pengelola.');
}

$flash = ambilPesanKilAt();
$utama = $detail && (int) $detail['tugas_utama'] === 1;
awalHalamanM2('Pekerjaan Saya', $akun);
?>
<div class="ks-wo">
  <header class="ks-wo-head ks-wo-page-head">
    <div>
      <?php if ($detail): ?>
        <p class="ks-wo-eyebrow">Pekerjaan saya</p>
        <h1 class="ks-wo-code"><?=amanHtml($detail['no_work_order'])?></h1>
        <p class="sub"><?=$utama ? 'Mekanik utama' : 'Mekanik pembantu'?></p>
      <?php else: ?>
        <h1>Pekerjaan Saya</h1><p class="sub">Work Order yang ditugaskan kepada Anda di cabang ini.</p>
      <?php endif; ?>
    </div>
    <?php if ($detail): ?>
      <div class="ks-wo-page-actions">
        <span class="ks-wo-status <?=amanHtml($detail['status_pekerjaan'])?>"><?=amanHtml(woLabelStatus($detail['status_pekerjaan']))?></span>
        <a class="ks-wo-link" href="pekerjaan.php">Kembali ke daftar</a>
      </div>
    <?php endif; ?>
  </header>

  <?php if ($flash): ?>
    <div class="alert<?=($flash['jenis'] ?? 'ok') === 'ok' ? '' : ' bad'?>" role="status"><?=amanHtml($flash['pesan'] ?? '')?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert bad" role="alert"><?=amanHtml($error)?></div><?php endif; ?>

  <?php if ($detail): ?>
    <section class="panel ks-wo-module" aria-labelledby="pekerjaan-detail-judul">
      <div class="ks-wo-panel-head"><h2 id="pekerjaan-detail-judul">Ringkasan pekerjaan</h2></div>
      <div class="ks-wo-facts">
        <div class="ks-wo-fact"><small>Motor</small><strong><?=amanHtml($detail['no_polisi'] . ' · ' . $detail['merek'] . ' ' . $detail['tipe'])?></strong></div>
        <div class="ks-wo-fact"><small>Keluhan pelanggan</small><div class="ks-wo-complaint"><?=amanHtml($detail['keluhan'])?></div></div>
        <div class="ks-wo-fact"><small>Tanggal masuk</small><strong><?=amanHtml($detail['tanggal_masuk'])?></strong></div>
        <div class="ks-wo-fact"><small>Tanggal selesai</small><strong><?=amanHtml($detail['tanggal_selesai'] ?: 'Belum selesai')?></strong></div>
      </div>
    </section>
    <?php $itemWO=ksLayananAmbil($db,$akun,$detail['no_work_order']); if($itemWO): ?>
    <section class="panel"><h2>Rencana pekerjaan</h2><p class="muted">Mekanik pembantu: <?=amanHtml(implode(', ',$itemWO['pembantu']) ?: 'Tidak ada')?></p><?php ksLayananItem($itemWO,false); ?></section>
    <?php endif; ?>
    <?php
    $woStatusAksi = $utama ? woStatusOpsi($detail['status_pekerjaan'], false) : [];
    $woStatusNote = $utama ? '' : 'Status diperbarui oleh mekanik utama atau pengelola Work Order.';
    $statusEndpoint = 'pekerjaan.php';
    require __DIR__ . '/../app/wo_status_view_m2.php';
    ?>
  <?php elseif ($selectedId === ''): ?>
    <section class="panel ks-wo-module" aria-labelledby="daftar-pekerjaan-judul">
      <div class="ks-wo-panel-head"><h2 id="daftar-pekerjaan-judul">Daftar penugasan</h2></div>
      <?php if ($rows): ?>
        <div class="tablewrap ks-wo-module-table" tabindex="0" role="region" aria-label="Daftar penugasan mekanik">
          <table>
            <thead><tr><th scope="col">Work Order</th><th scope="col">Motor</th><th scope="col">Penugasan</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($rows as $wo): ?>
                <tr>
                  <td><strong><?=amanHtml($wo['no_work_order'])?></strong><br><small class="muted"><?=amanHtml($wo['tanggal_masuk'])?></small></td>
                  <td><?=amanHtml($wo['no_polisi'])?><br><small class="muted"><?=amanHtml($wo['merek'] . ' ' . $wo['tipe'])?></small></td>
                  <td><?=(int) $wo['tugas_utama'] === 1 ? 'Utama' : 'Pembantu'?></td>
                  <td><span class="ks-wo-status <?=amanHtml($wo['status_pekerjaan'])?>"><?=amanHtml(woLabelStatus($wo['status_pekerjaan']))?></span></td>
                  <td><a class="ks-wo-link" href="pekerjaan.php?lihat=<?=rawurlencode($wo['no_work_order'])?>">Lihat detail</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php ksUiPager('hal_tugas'); ?>
      <?php else: ?>
        <p class="ks-wo-empty">Belum ada Work Order yang ditugaskan kepada Anda.</p>
      <?php endif; ?>
    </section>
  <?php else: ?>
    <a class="ks-wo-link" href="pekerjaan.php">Kembali ke daftar</a>
  <?php endif; ?>
</div>
<?php akhirHalamanM2(); ?>
