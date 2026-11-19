<?php
declare(strict_types=1);
if (!isset($detail, $woStatusAksi, $woStatusNote, $statusEndpoint)) {
    return;
}
?>
<section class="panel ks-wo-module" id="status-wo" aria-labelledby="status-wo-judul">
  <div class="ks-wo-panel-head">
    <div><h2 id="status-wo-judul">Status pekerjaan</h2>
      <p class="sub">Perbarui status sesuai progres servis di bengkel.</p>
    </div>
    <?php if ($woStatusAksi): ?>
      <form method="post" action="<?=amanHtml($statusEndpoint)?>" class="ks-wo-page-actions" id="r01d-form-status">
        <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
        <input type="hidden" name="aksi" value="r01d_status">
        <input type="hidden" name="no_work_order" value="<?=amanHtml($detail['no_work_order'])?>">
        <input type="hidden" name="status_awal" value="<?=amanHtml($detail['status_pekerjaan'])?>">
        <?php foreach ($woStatusAksi as $statusTujuan => $label): ?>
          <?php if ($statusTujuan === 'DIBATALKAN'): ?>
            <button type="submit" class="danger" name="status_tujuan" value="DIBATALKAN"
                    onclick="return window.confirm('Batalkan Work Order ini?');"><?=amanHtml($label)?></button>
          <?php elseif ($statusTujuan === 'SELESAI'): ?>
            <button type="submit" name="status_tujuan" value="SELESAI"
                    onclick="return window.confirm('Tandai pengerjaan Work Order ini selesai?');"><?=amanHtml($label)?></button>
          <?php else: ?>
            <button type="submit" name="status_tujuan" value="<?=amanHtml($statusTujuan)?>"><?=amanHtml($label)?></button>
          <?php endif; ?>
        <?php endforeach; ?>
      </form>
    <?php endif; ?>
  </div>
  <?php if ($woStatusNote !== ''): ?>
    <p class="wo-note"><?=amanHtml($woStatusNote)?></p>
  <?php elseif (!$woStatusAksi): ?>
    <p class="ks-wo-empty">Status pekerjaan ini sudah final.</p>
  <?php endif; ?>
</section>
