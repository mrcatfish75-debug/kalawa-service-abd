<?php
declare(strict_types=1);
if (!isset($detail, $woBagian, $r01bError)) {
    return;
}

$detailUrl = 'work_order.php?lihat=' . rawurlencode($detail['no_work_order']);
$sections = [
    'ringkasan' => ['Ringkasan', $detailUrl],
    'jasa' => ['Jasa Servis', $detailUrl . '&bagian=jasa'],
    'mekanik' => ['Mekanik Pembantu', $detailUrl . '&bagian=mekanik'],
    'suku_cadang' => ['Suku Cadang', $detailUrl . '&bagian=suku_cadang'],
];
$formatRupiah = static function (string $nilai): string {
    [$rupiah, $sen] = explode('.', $nilai . '.00', 3);
    return 'Rp ' . preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', '.', $rupiah) . ',' . $sen;
};
?>
<header class="ks-wo-head ks-wo-page-head" id="detail-wo">
  <div>
    <p class="ks-wo-eyebrow">Work Order</p>
    <h1 class="ks-wo-code"><?=amanHtml($detail['no_work_order'])?></h1>
    <p class="sub"><?=amanHtml($detail['nama_cabang'])?></p>
  </div>
  <div class="ks-wo-page-actions">
    <span class="ks-wo-status <?=amanHtml($detail['status_pekerjaan'])?>"><?=amanHtml(woLabelStatus($detail['status_pekerjaan']))?></span>
    <a class="ks-wo-link" href="work_order.php#daftar-wo">Kembali ke daftar</a>
  </div>
</header>
<nav class="ks-wo-section-nav" aria-label="Bagian Work Order">
  <?php foreach ($sections as $key => [$label, $url]): ?>
    <a href="<?=amanHtml($url)?>"<?=$woBagian === $key ? ' aria-current="page"' : ''?>><?=amanHtml($label)?></a>
  <?php endforeach; ?>
</nav>
<?php
$bagianUrl = $sections[$woBagian][1];
if ($woBagian === 'suku_cadang') {
    require __DIR__ . '/wo_r01c_view_m2.php';
    return;
}
$ringkasan = $woBagian === 'ringkasan';
$isJasa = $woBagian === 'jasa';
$editable = !$ringkasan && $r01bToken !== '';
$aksi = $r01bInput['aksi'] ?? '';
$aksiTambah = $isJasa ? 'r01b_jasa_tambah' : 'r01b_mekanik_tambah';
$labelTambah = $isJasa ? 'Tambah jasa' : 'Tambah mekanik';
$pilihan = $r01b[$isJasa ? 'pilihanJasa' : 'pilihanMekanik'] ?? [];
$ubahKode = $r01bInput
    ? ($aksi === 'r01b_jasa_ubah' ? $r01bInput['kode_jasa'] : '')
    : (is_string($_GET['ubah_jasa'] ?? null) ? $_GET['ubah_jasa'] : '');
$editJasa = $editable && $isJasa
    ? (array_column($r01b['jasa'], null, 'kode_jasa')[$ubahKode] ?? null)
    : null;
$tambahDiminta = $r01bInput ? $aksi === $aksiTambah : ($pilihan && ($_GET['tambah'] ?? null) === '1');
$mode = 'daftar';
if ($editable) {
    if ($editJasa) {
        $mode = 'ubah';
    } elseif ($tambahDiminta) {
        $mode = 'tambah';
    }
}
$judul = match ($mode) {
    'ubah' => 'Ubah biaya jasa',
    'tambah' => $isJasa ? 'Tambah jasa servis' : 'Tambah mekanik pembantu',
    default => $ringkasan ? 'Ringkasan Work Order' : $sections[$woBagian][0],
};
$pesanInline = $mode !== 'daftar' && ($isJasa
    ? ($r01bField === 'biaya' || ($mode === 'tambah' && $r01bField === 'kode_jasa'))
    : $r01bField === 'id_mekanik');
$subtotalJasa = $r01b !== null ? $formatRupiah($r01b['jasa'][0]['subtotal'] ?? '0.00') : null;
$csrfR01b = $editable ? tokenCsrf() : '';
$hiddenR01b = static function (string $action, array $extra = []) use ($csrfR01b, $r01bToken, $detail): void {
    $fields = [
        'csrf' => $csrfR01b,
        'aksi' => $action,
        'no_work_order' => $detail['no_work_order'],
        'token_r01b' => $r01bToken,
    ];
    foreach ($fields + $extra as $name => $value) {
        printf('<input type="hidden" name="%s" value="%s">', amanHtml($name), amanHtml($value));
    }
};
?>
<section class="panel ks-wo-module" id="wo-r01b" aria-labelledby="wo-r01b-judul">
  <div class="ks-wo-panel-head">
    <div>
      <h2 id="wo-r01b-judul"><?=amanHtml($judul)?></h2>
      <?php if ($mode === 'daftar' && !$ringkasan): ?>
        <p class="sub"><?=$isJasa ? 'Biaya aktual jasa pada Work Order ini.' : 'Penugasan mekanik tambahan pada Work Order ini.'?></p>
      <?php endif; ?>
    </div>
    <?php if (!$ringkasan): ?>
      <div class="ks-wo-page-actions">
        <?php if ($isJasa && $subtotalJasa !== null): ?>
          <div class="ks-wo-subtotal"><small>Subtotal jasa</small><strong><?=amanHtml($subtotalJasa)?></strong></div>
        <?php endif; ?>
        <?php if ($mode === 'daftar' && $editable): ?>
          <?php if ($pilihan): ?>
            <a class="btn" href="<?=amanHtml($bagianUrl . '&tambah=1')?>">+ <?=amanHtml($labelTambah)?></a>
          <?php else: ?>
            <button type="button" disabled>+ <?=amanHtml($labelTambah)?></button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?>
    <div class="alert<?=($flash['jenis'] ?? 'ok') === 'ok' ? '' : ' bad'?>" role="status"><?=amanHtml($flash['pesan'] ?? '')?></div>
  <?php endif; ?>
  <?php if ($r01bError !== '' && !$pesanInline): ?>
    <div class="alert bad" id="wo-r01b-error" tabindex="-1" role="alert">
      <?=amanHtml($r01bError)?>
      <?php if ($r01bBlocked && ($r01b['kunci'] ?? '') === ''): ?><a href="<?=amanHtml($bagianUrl)?>">Muat ulang halaman</a><?php endif; ?>
      <?php if ($r01bBlocked && ($r01bInput['biaya'] ?? '') !== ''): ?>
        <p>Biaya yang tadi diisi <strong><?=amanHtml(substr($r01bInput['biaya'], 0, 80))?></strong></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($ringkasan): ?>
    <div class="ks-wo-facts">
      <div class="ks-wo-fact"><small>Pelanggan</small><strong><?=amanHtml($detail['nama_pelanggan'])?> (<?=amanHtml($detail['id_pelanggan'])?>)</strong></div>
      <div class="ks-wo-fact"><small>Motor</small><strong><?=amanHtml($detail['no_polisi'])?> · <?=amanHtml($detail['merek'].' '.$detail['tipe'])?></strong></div>
      <div class="ks-wo-fact"><small>Tanggal masuk</small><strong><?=amanHtml($detail['tanggal_masuk'])?></strong></div>
      <div class="ks-wo-fact"><small>Tanggal selesai</small><strong><?=amanHtml($detail['tanggal_selesai'] ?: 'Belum selesai')?></strong></div>
      <div class="ks-wo-fact"><small>Mekanik utama</small><strong><?=amanHtml($detail['nama_mekanik'])?> (<?=amanHtml($detail['id_mekanik_pj'])?>)</strong></div>
      <div class="ks-wo-fact"><small>Keluhan pelanggan</small><div class="ks-wo-complaint"><?=amanHtml($detail['keluhan'])?></div></div>
      <div class="ks-wo-fact"><small>Estimasi awal</small><strong><?=amanHtml($formatRupiah((string) $detail['estimasi_biaya']))?></strong></div>
      <?php if ($subtotalJasa !== null): ?>
        <div class="ks-wo-fact"><small>Subtotal jasa aktual</small><strong><?=amanHtml($subtotalJasa)?></strong></div>
      <?php endif; ?>
    </div>
  <?php elseif ($r01b !== null): ?>
    <?php if ($r01b['kunci'] !== '' && $r01bError !== $r01b['kunci']): ?>
      <p class="wo-note"><?=amanHtml($r01b['kunci'])?></p>
    <?php endif; ?>

    <?php if ($mode !== 'daftar'): ?>
      <form method="post" action="<?=amanHtml($bagianUrl)?>" class="ks-wo-editor" id="r01b-form-<?=$isJasa ? 'jasa' : 'mekanik'?>" data-r01b-form>
        <?php $hiddenR01b($editJasa ? 'r01b_jasa_ubah' : $aksiTambah, $editJasa ? ['kode_jasa' => $ubahKode] : []); ?>
        <?php if ($isJasa): ?>
          <?php if ($editJasa): ?>
            <div class="ks-wo-fact">
              <small>Jasa yang dipilih</small>
              <strong><?=amanHtml($editJasa['uraian_jasa_servis'])?> · <?=amanHtml($editJasa['kode_jasa'])?></strong>
              <small>Biaya tercatat <?=amanHtml($formatRupiah($editJasa['biaya']))?></small>
            </div>
          <?php else: ?>
            <div class="ks-wo-field">
              <label for="r01b-kode-jasa">Jasa servis</label>
              <select id="r01b-kode-jasa" name="kode_jasa" required aria-invalid="<?=$r01bField === 'kode_jasa' ? 'true' : 'false'?>" <?=$r01bField === 'kode_jasa' ? 'aria-describedby="r01b-err-jasa"' : ''?>>
                <option value="">Pilih jasa servis</option>
                <?php foreach ($pilihan as $item): ?>
                  <option value="<?=amanHtml($item['kode_jasa'])?>" <?=($r01bInput['kode_jasa'] ?? '') === $item['kode_jasa'] ? 'selected' : ''?>><?=amanHtml($item['uraian_jasa_servis'] . ' · ' . $item['kode_jasa'])?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($r01bField === 'kode_jasa'): ?><p class="wo-error" id="r01b-err-jasa"><?=amanHtml($r01bError)?></p><?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="ks-wo-field">
            <label for="r01b-biaya">Biaya aktual (Rp)</label>
            <input id="r01b-biaya" name="biaya" inputmode="decimal" maxlength="13" required value="<?=amanHtml(substr($r01bInput['biaya'] ?? $editJasa['biaya'] ?? '', 0, 80))?>" placeholder="Contoh 90000" aria-invalid="<?=$r01bField === 'biaya' ? 'true' : 'false'?>" aria-describedby="r01b-bantuan-biaya<?=$r01bField === 'biaya' ? ' r01b-err-biaya' : ''?>">
            <small id="r01b-bantuan-biaya">Tanpa pemisah ribuan. Contoh 90000 atau 90000,50.</small>
            <?php if ($r01bField === 'biaya'): ?><p class="wo-error" id="r01b-err-biaya"><?=amanHtml($r01bError)?></p><?php endif; ?>
          </div>
        <?php else: ?>
          <div class="ks-wo-field">
            <label for="r01b-id-mekanik">Mekanik aktif di cabang WO</label>
            <select id="r01b-id-mekanik" name="id_mekanik" required aria-invalid="<?=$r01bField === 'id_mekanik' ? 'true' : 'false'?>" <?=$r01bField === 'id_mekanik' ? 'aria-describedby="r01b-err-mekanik"' : ''?>>
              <option value="">Pilih mekanik pembantu</option>
              <?php foreach ($pilihan as $item): ?>
                <option value="<?=amanHtml($item['id_mekanik'])?>" <?=($r01bInput['id_mekanik'] ?? '') === $item['id_mekanik'] ? 'selected' : ''?>><?=amanHtml($item['nama_pegawai'] . ' · ' . $item['id_mekanik'])?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($r01bField === 'id_mekanik'): ?><p class="wo-error" id="r01b-err-mekanik"><?=amanHtml($r01bError)?></p><?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="ks-wo-actions">
          <button type="submit"<?=$mode === 'tambah' && !$pilihan ? ' disabled' : ''?>><?=$editJasa ? 'Simpan biaya' : amanHtml($labelTambah)?></button>
          <a class="ks-wo-link" href="<?=amanHtml($bagianUrl)?>">Batal</a>
        </div>
      </form>
    <?php elseif ($isJasa): ?>
      <?php if ($r01b['jasa']): ?>
        <div class="tablewrap ks-wo-module-table" role="region" aria-label="Rincian jasa servis" tabindex="0">
          <table>
            <thead><tr><th scope="col">Jasa servis</th><th scope="col">Biaya aktual</th><?php if ($editable): ?><th scope="col">Aksi</th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($r01b['jasa'] as $jasa): ?>
                <tr>
                  <td><strong><?=amanHtml($jasa['uraian_jasa_servis'])?></strong><br><small class="muted"><?=amanHtml($jasa['kode_jasa'])?></small></td>
                  <td class="ks-wo-money"><?=amanHtml($formatRupiah($jasa['biaya']))?></td>
                  <?php if ($editable): ?>
                    <td>
                      <div class="row">
                        <a class="ks-wo-link" href="<?=amanHtml($bagianUrl . '&ubah_jasa=' . rawurlencode($jasa['kode_jasa']))?>">Ubah biaya</a>
                        <form method="post" action="<?=amanHtml($bagianUrl)?>" data-r01b-form data-r01b-hapus="Hapus jasa ini dari Work Order?">
                          <?php $hiddenR01b('r01b_jasa_hapus', ['kode_jasa' => $jasa['kode_jasa']]); ?>
                          <button class="danger" type="submit" aria-label="Hapus jasa <?=amanHtml($jasa['uraian_jasa_servis'])?>">Hapus</button>
                        </form>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="ks-wo-empty">Belum ada jasa yang dicatat.</p>
      <?php endif; ?>
      <?php if ($editable && !$pilihan): ?><p class="sub">Tidak ada jasa lain yang dapat ditambahkan dari master.</p><?php endif; ?>
    <?php else: ?>
      <?php if ($r01b['pembantu']): ?>
        <div class="tablewrap ks-wo-module-table" role="region" aria-label="Daftar mekanik pembantu" tabindex="0">
          <table>
            <thead><tr><th scope="col">Mekanik pembantu</th><?php if ($editable): ?><th scope="col">Aksi</th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($r01b['pembantu'] as $mekanik): ?>
                <tr>
                  <td>
                    <?php if ($mekanik['id_mekanik'] === null): ?>
                      <span class="muted">Penugasan lama di luar cabang</span>
                    <?php else: ?>
                      <strong><?=amanHtml($mekanik['nama_pegawai'])?></strong><br><small class="muted"><?=amanHtml($mekanik['id_mekanik'])?></small>
                      <?php if ($mekanik['kode_cabang'] !== $detail['kode_cabang']): ?><br><small class="muted">Penempatan saat ini di luar cabang WO</small><?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <?php if ($editable): ?>
                    <td>
                      <?php if ($mekanik['id_mekanik'] !== null): ?>
                        <form method="post" action="<?=amanHtml($bagianUrl)?>" data-r01b-form data-r01b-hapus="Lepas penugasan mekanik pembantu ini?">
                          <?php $hiddenR01b('r01b_mekanik_hapus', ['id_mekanik' => $mekanik['id_mekanik']]); ?>
                          <button class="danger" type="submit" aria-label="Lepas penugasan <?=amanHtml($mekanik['nama_pegawai'])?>">Lepas</button>
                        </form>
                      <?php else: ?>
                        <small class="muted">Dikelola Owner</small>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="ks-wo-empty">Belum ada mekanik pembantu yang ditugaskan.</p>
      <?php endif; ?>
      <?php if ($editable && !$pilihan): ?><p class="sub">Belum ada mekanik aktif lain yang dapat ditugaskan pada WO ini.</p><?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</section>
<script>
(() => {
  const area = document.getElementById('wo-r01b');
  const forms = area.querySelectorAll('[data-r01b-form]');
  forms.forEach(form => {
    const button = form.querySelector('button[type="submit"]');
    const label = button.textContent;
    const disabled = button.disabled;
    form.addEventListener('submit', event => {
      const prompt = form.dataset.r01bHapus;
      if (button.disabled || (prompt && !window.confirm(prompt))) {
        event.preventDefault();
        return;
      }
      button.disabled = true;
      button.textContent = 'Memproses…';
    });
    window.addEventListener('pageshow', event => {
      if (event.persisted) {
        button.disabled = disabled;
        button.textContent = label;
      }
    });
  });
  (area.querySelector('[aria-invalid="true"]') || document.getElementById('wo-r01b-error'))?.focus();
})();
</script>
