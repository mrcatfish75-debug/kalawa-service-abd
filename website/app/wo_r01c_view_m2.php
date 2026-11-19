<?php
declare(strict_types=1);
if (!isset($detail, $bagianUrl, $formatRupiah, $r01cError)) {
    return;
}
$barang = $r01c['barang'] ?? [];
$pilihan = $r01c['pilihan'] ?? [];
$kunci = $r01c['kunci'] ?? '';
$tambah = $r01cToken !== '';
$judul = $tambah ? 'Tambah suku cadang' : 'Suku Cadang';
?>
<section class="panel ks-wo-module" id="wo-r01c" aria-labelledby="wo-r01c-judul">
  <div class="ks-wo-panel-head">
    <div>
      <h2 id="wo-r01c-judul"><?=amanHtml($judul)?></h2>
      <p class="sub"><?=$tambah ? 'Stok cabang berkurang saat pemakaian disimpan.' : 'Pemakaian suku cadang pada Work Order ini.'?></p>
    </div>
    <?php if ($r01c !== null): ?>
      <div class="ks-wo-page-actions">
        <div class="ks-wo-subtotal">
          <small>Subtotal suku cadang</small>
          <strong><?=amanHtml($formatRupiah((string) ($barang[0]['subtotal'] ?? '0.00')))?></strong>
        </div>
        <?php if (!$tambah && $pilihan && !$r01cBlocked): ?>
          <a class="btn" href="<?=amanHtml($bagianUrl . '&tambah=1')?>">+ Tambah suku cadang</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($flash): ?>
    <div class="alert<?=($flash['jenis'] ?? 'ok') === 'ok' ? '' : ' bad'?>" role="status"><?=amanHtml($flash['pesan'] ?? '')?></div>
  <?php endif; ?>
  <?php if ($r01cError !== '' && (!$tambah || $r01cField === '')): ?>
    <div class="alert bad" role="alert">
      <?=amanHtml($r01cError)?>
      <?php if ($r01cBlocked && $kunci === ''): ?><a href="<?=amanHtml($bagianUrl)?>">Muat ulang rincian</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($kunci !== '' && $kunci !== $r01cError): ?>
    <p class="wo-note"><?=amanHtml($kunci)?></p>
  <?php endif; ?>

  <?php if ($tambah): ?>
    <form method="post" action="<?=amanHtml($bagianUrl)?>" class="ks-wo-editor" id="r01c-form-suku-cadang">
      <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
      <input type="hidden" name="aksi" value="r01c_suku_cadang_tambah">
      <input type="hidden" name="no_work_order" value="<?=amanHtml($detail['no_work_order'])?>">
      <input type="hidden" name="token_r01c" value="<?=amanHtml($r01cToken)?>">
      <div class="ks-wo-field">
        <label for="r01c-kode">Suku cadang di cabang WO</label>
        <select id="r01c-kode" name="kode_suku_cadang" required aria-invalid="<?=$r01cField === 'kode_suku_cadang' ? 'true' : 'false'?>" <?=$r01cField === 'kode_suku_cadang' ? 'aria-describedby="r01c-err-kode"' : ''?>>
          <option value="">Pilih suku cadang</option>
          <?php foreach ($pilihan as $item): ?>
            <option value="<?=amanHtml($item['kode_suku_cadang'])?>" <?=($r01cInput['kode_suku_cadang'] ?? '') === $item['kode_suku_cadang'] ? 'selected' : ''?>>
              <?=amanHtml($item['nama_suku_cadang'] . ' · ' . $item['kode_suku_cadang'] . ' · stok ' . $item['jumlah_stok'])?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($r01cField === 'kode_suku_cadang'): ?><p class="wo-error" id="r01c-err-kode" role="alert"><?=amanHtml($r01cError)?></p><?php endif; ?>
        <?php if (!$pilihan): ?><small>Belum ada pilihan lain dengan stok tersedia.</small><?php endif; ?>
      </div>
      <div class="ks-wo-field">
        <label for="r01c-qty">Jumlah dipakai</label>
        <input id="r01c-qty" name="qty" inputmode="numeric" maxlength="10" required value="<?=amanHtml(substr($r01cInput['qty'] ?? '1', 0, 80))?>" aria-invalid="<?=$r01cField === 'qty' ? 'true' : 'false'?>" aria-describedby="r01c-bantuan-qty<?=$r01cField === 'qty' ? ' r01c-err-qty' : ''?>">
        <small id="r01c-bantuan-qty">Isi jumlah unit sesuai stok tersedia.</small>
        <?php if ($r01cField === 'qty'): ?><p class="wo-error" id="r01c-err-qty" role="alert"><?=amanHtml($r01cError)?></p><?php endif; ?>
      </div>
      <div class="ks-wo-field">
        <label for="r01c-harga">Harga satuan aktual (Rp)</label>
        <input id="r01c-harga" name="harga_satuan" inputmode="decimal" maxlength="13" required value="<?=amanHtml(substr($r01cInput['harga_satuan'] ?? '', 0, 80))?>" placeholder="Contoh 45000" aria-invalid="<?=$r01cField === 'harga_satuan' ? 'true' : 'false'?>" aria-describedby="r01c-bantuan-harga<?=$r01cField === 'harga_satuan' ? ' r01c-err-harga' : ''?>">
        <small id="r01c-bantuan-harga">Tanpa pemisah ribuan. Contoh 45000 atau 45000,50.</small>
        <?php if ($r01cField === 'harga_satuan'): ?><p class="wo-error" id="r01c-err-harga" role="alert"><?=amanHtml($r01cError)?></p><?php endif; ?>
      </div>
      <div class="ks-wo-actions">
        <button type="submit"<?=$pilihan ? '' : ' disabled'?>>Simpan pemakaian</button>
        <a class="ks-wo-link" href="<?=amanHtml($bagianUrl)?>">Batal</a>
      </div>
    </form>
  <?php elseif ($barang): ?>
    <div class="tablewrap ks-wo-module-table" role="region" aria-label="Rincian suku cadang" tabindex="0">
      <table>
        <thead><tr><th scope="col">Suku cadang</th><th scope="col">Jumlah</th><th scope="col">Harga satuan</th><th scope="col">Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($barang as $item): ?>
            <tr>
              <td><strong><?=amanHtml($item['nama_suku_cadang'])?></strong><br><small class="muted"><?=amanHtml($item['kode_suku_cadang'])?></small></td>
              <td><?=amanHtml($item['qty'])?></td>
              <td class="ks-wo-money"><?=amanHtml($formatRupiah((string) $item['harga_satuan']))?></td>
              <td class="ks-wo-money"><?=amanHtml($formatRupiah((string) $item['subtotal_item']))?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php elseif ($r01c !== null): ?>
    <p class="ks-wo-empty">Belum ada pemakaian suku cadang yang dicatat.</p>
  <?php endif; ?>
  <?php if (!$tambah && $r01c !== null && $kunci === '' && !$pilihan): ?>
    <p class="sub">Belum ada pilihan lain dengan stok tersedia di cabang WO.</p>
  <?php endif; ?>
</section>
