<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_supplier_m2.php';

$akun = akunLoginWajib();
// Kebijakan TEST ONLY: belum membuka akses admin cabang/gudang sampai scoping disetujui.
if (($akun['role_db'] ?? '') !== 'rl_owner_ops') {
    http_response_code(403);
    exit('403 — Akun ini tidak memiliki izin mengelola supplier.');
}
$db = koneksiKalawaM2(); // Guard nama DB pengujian ada di app/database_m2.php.
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['aksi'] ?? '') : '';
$error = '';
$errors = [];
$old = [];
$editingId = '';
$savedChoice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validasiCsrf();
    try {
        if ($action === 'hapus') {
            $id = trim((string) ($_POST['id_supplier'] ?? ''));
            if (!idSupplierValidM2($id)) {
                throw new InvalidArgumentException('Supplier tidak valid. Pilih dari daftar.');
            }
            // Menolak penghapusan yang masih punya barang; FK tetap jadi perlindungan akhir.
            $st = $db->prepare('SELECT COUNT(*) FROM supplier_suku_cadang WHERE id_supplier = :id');
            $st->execute(['id'=>$id]);
            if ((int) $st->fetchColumn() !== 0) {
                throw new InvalidArgumentException('Supplier masih memasok barang. Buka Kelola barang lalu lepas kaitannya sebelum menghapus supplier.');
            }
            $st = $db->prepare('DELETE FROM supplier WHERE id_supplier = :id');
            $st->execute(['id'=>$id]);
            if ($st->rowCount() !== 1) {
                throw new InvalidArgumentException('Supplier tidak ditemukan. Muat ulang daftar.');
            }
            pesanKilAt('Supplier berhasil dihapus.');
            pindah('supplier.php');
        }

        if ($action === 'simpan_barang') {
            $id = trim((string) ($_POST['id_supplier'] ?? ''));
            if (!idSupplierValidM2($id)) {
                throw new InvalidArgumentException('Supplier tidak valid. Pilih dari daftar.');
            }
            $allCodes = array_map('strval', $db->query('SELECT kode_suku_cadang FROM suku_cadang')->fetchAll(PDO::FETCH_COLUMN));
            $checked = validasiPilihanBarangSupplierM2($_POST['barang'] ?? [], $allCodes);
            if ($checked['error']) {
                throw new InvalidArgumentException($checked['error']);
            }
            $savedChoice = $checked['data'];
            // Transaksi + kunci baris supplier untuk mencegah dua pengelolaan serentak.
            $db->beginTransaction();
            $lock = $db->prepare('SELECT id_supplier FROM supplier WHERE id_supplier = :id FOR UPDATE');
            $lock->execute(['id'=>$id]);
            if (!$lock->fetchColumn()) {
                throw new InvalidArgumentException('Supplier sudah tidak ada. Muat ulang daftar.');
            }
            $st = $db->prepare('SELECT kode_suku_cadang FROM supplier_suku_cadang WHERE id_supplier = :id FOR UPDATE');
            $st->execute(['id'=>$id]);
            $current = $st->fetchAll(PDO::FETCH_COLUMN);
            $diff = perubahanBarangSupplierM2($current, $savedChoice);
            if ($diff['lepas']) {
                $del = $db->prepare('DELETE FROM supplier_suku_cadang WHERE id_supplier = :id AND kode_suku_cadang = :code');
                foreach ($diff['lepas'] as $code) $del->execute(['id'=>$id, 'code'=>$code]);
            }
            if ($diff['tambah']) {
                $add = $db->prepare('INSERT INTO supplier_suku_cadang (id_supplier, kode_suku_cadang) VALUES (:id, :code)');
                foreach ($diff['tambah'] as $code) $add->execute(['id'=>$id, 'code'=>$code]);
            }
            $db->commit();
            pesanKilAt('Daftar barang yang dipasok berhasil disimpan.');
            // Kembali ke daftar tanpa mempertahankan panel kelola setelah penyimpanan sukses.
            pindah('supplier.php');
        }

        if ($action !== 'tambah' && $action !== 'ubah') {
            throw new InvalidArgumentException('Tindakan tidak dikenali. Muat ulang halaman.');
        }
        $validation = validasiNamaSupplierM2($_POST['nama_supplier'] ?? null);
        $old = $validation['data'];
        $errors = $validation['errors'];
        $editingId = $action === 'ubah' ? trim((string) ($_POST['id_supplier'] ?? '')) : '';
        if ($action === 'ubah' && !idSupplierValidM2($editingId)) {
            throw new InvalidArgumentException('Kode supplier tidak valid. Pilih Ubah dari daftar.');
        }
        if (!$errors) {
            $stmt = $db->prepare('SELECT 1 FROM supplier WHERE LOWER(nama_supplier) = LOWER(:name) AND id_supplier <> :id LIMIT 1');
            $stmt->execute(['name'=>$old['nama_supplier'], 'id'=>$editingId]);
            if ($stmt->fetchColumn()) {
                $errors['nama_supplier'] = 'Nama supplier sudah digunakan. Masukkan nama berbeda.';
            }
        }
        if (!$errors && $action === 'tambah') {
            $codes = $db->query('SELECT id_supplier FROM supplier')->fetchAll(PDO::FETCH_COLUMN);
            $id = idSupplierBerikutnyaM2($codes);
            $stmt = $db->prepare('INSERT INTO supplier (id_supplier, nama_supplier) VALUES (:id, :name)');
            $stmt->execute(['id'=>$id, 'name'=>$old['nama_supplier']]);
            pesanKilAt('Supplier berhasil ditambahkan.');
            // Admin membuka Kelola barang hanya jika diperlukan, bukan otomatis setelah simpan.
            pindah('supplier.php');
        }
        if (!$errors && $action === 'ubah') {
            $stmt = $db->prepare('UPDATE supplier SET nama_supplier = :name WHERE id_supplier = :id');
            $stmt->execute(['name'=>$old['nama_supplier'], 'id'=>$editingId]);
            if ($stmt->rowCount() === 0) {
                $check = $db->prepare('SELECT 1 FROM supplier WHERE id_supplier = :id');
                $check->execute(['id'=>$editingId]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Supplier tidak ditemukan. Muat ulang daftar.');
                }
            }
            pesanKilAt('Nama supplier berhasil diperbarui.');
            pindah('supplier.php');
        }
        if ($errors) {
            $error = 'Periksa kolom yang ditandai. Data yang sudah diisi tetap ada.';
        }
    } catch (InvalidArgumentException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa supplier TEST] ' . $e->getMessage());
        $number = (int) ($e->errorInfo[1] ?? 0);
        $error = match ($number) {
            1451 => 'Supplier masih terhubung dengan barang. Lepas kaitan barang terlebih dahulu.',
            1062 => 'Kode atau nama supplier bertabrakan dengan perubahan lain. Muat ulang dan coba lagi.',
            1142 => 'Akses database belum diizinkan. Periksa GRANT akun pengujian.',
            default => 'Data tidak berhasil disimpan. Periksa log server.',
        };
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa supplier TEST] ' . $e->getMessage());
        $error = 'Operasi belum berhasil. Muat ulang atau periksa log server.';
    }
}

$edit = null;
$selected = null;
$allParts = [];
$linkedCodes = [];
$query = '';
try {
    $editId = trim((string) ($_GET['ubah'] ?? ''));
    if ($editId !== '' && idSupplierValidM2($editId)) {
        $st = $db->prepare('SELECT id_supplier, nama_supplier FROM supplier WHERE id_supplier = :id');
        $st->execute(['id'=>$editId]);
        $edit = $st->fetch() ?: null;
        if (!$edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'Supplier tidak ditemukan. Pilih dari daftar.';
        }
    }
    $selectedId = trim((string) ($_GET['pilih'] ?? ''));
    if ($action === 'simpan_barang') {
        $selectedId = trim((string) ($_POST['id_supplier'] ?? ''));
    }
    if ($selectedId !== '' && idSupplierValidM2($selectedId)) {
        $st = $db->prepare('SELECT id_supplier, nama_supplier FROM supplier WHERE id_supplier = :id');
        $st->execute(['id'=>$selectedId]);
        $selected = $st->fetch() ?: null;
        if ($selected) {
            $allParts = $db->query('SELECT kode_suku_cadang, nama_suku_cadang FROM suku_cadang ORDER BY nama_suku_cadang, kode_suku_cadang')->fetchAll();
            $st = $db->prepare('SELECT kode_suku_cadang FROM supplier_suku_cadang WHERE id_supplier = :id');
            $st->execute(['id'=>$selectedId]);
            $linkedCodes = $st->fetchAll(PDO::FETCH_COLUMN);
            if ($savedChoice !== null && $error !== '') $linkedCodes = $savedChoice;
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'Supplier tidak ditemukan. Pilih dari daftar.';
        }
    }
    $query = trim((string) ($_GET['q'] ?? ''));
    if (strlen($query) > 240) $query = substr($query, 0, 240);
    $base = 'SELECT s.id_supplier, s.nama_supplier, COUNT(ssc.kode_suku_cadang) AS jumlah_barang
             FROM supplier s LEFT JOIN supplier_suku_cadang ssc ON ssc.id_supplier = s.id_supplier';
    $args=[];
    if($query!==''){$base.=' WHERE s.nama_supplier LIKE :q OR s.id_supplier LIKE :q2';$args=['q'=>'%'.$query.'%','q2'=>'%'.$query.'%'];}
    $rows=ksUiList($db,$base.' GROUP BY s.id_supplier,s.nama_supplier ORDER BY s.id_supplier',$args,'hal_supplier');
} catch (Throwable $e) {
    error_log('[Kalawa supplier TEST READ] ' . $e->getMessage());
    http_response_code(503);
    exit('Daftar supplier belum bisa dibuka. Periksa koneksi dan hak baca database pengujian.');
}

$isEditing = $action === 'ubah' || ($edit && $_SERVER['REQUEST_METHOD'] !== 'POST');
$form = $old ?: ($isEditing && $edit ? $edit : []);
$flash = ambilPesanKilAt();
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $error !== '') $flash = null;
awalHalamanM2('Supplier', $akun);
?>
<div class="ks-supplier">
  <div class="ks-top">
    <div><h1>Supplier</h1><p class="ks-sub">Kelola pemasok dan barang yang dapat mereka sediakan.</p></div>
    <a href="#form-supplier" class="ks-btn" id="open-supplier">+ Tambah supplier</a>
  </div>
  <?php if ($flash): ?><div class="alert" id="supplier-flash" role="status"><?= amanHtml($flash['pesan']) ?></div><?php endif; ?>
  <?php if ($error): ?>
    <div class="alert bad" id="supplier-error" role="alert" tabindex="-1"><strong><?= amanHtml($error) ?></strong>
      <?php foreach ($errors as $key=>$message): ?><div style="margin-top:6px"><a href="#<?= amanHtml($key) ?>"><?= amanHtml($message) ?></a></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($selected): ?>
  <section class="panel ks-selected ks-compact" id="kelola-barang" aria-labelledby="judul-barang-supplier">
    <div class="ks-panel-head">
      <div><h2 id="judul-barang-supplier">Barang yang dipasok — <?= amanHtml($selected['nama_supplier']) ?></h2>
        <p class="ks-sub">Kode <?= amanHtml($selected['id_supplier']) ?> · <?= count($linkedCodes) ?> barang dipilih</p></div>
      <a class="ks-btn ks-ghost" href="supplier.php">Tutup</a>
    </div>
    <form method="post" action="supplier.php?pilih=<?= rawurlencode($selected['id_supplier']) ?>#kelola-barang" id="supplier-parts-form">
      <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
      <input type="hidden" name="aksi" value="simpan_barang">
      <input type="hidden" name="id_supplier" value="<?= amanHtml($selected['id_supplier']) ?>">
      <?php if (!$allParts): ?>
        <p class="ks-empty">Belum ada barang terdaftar. Tambahkan di menu <a href="suku_cadang.php">Suku Cadang</a> dahulu.</p>
      <?php else: ?>
        <label for="cari-barang-supplier">Cari barang</label>
        <input type="search" id="cari-barang-supplier" placeholder="Ketik nama atau kode barang" autocomplete="off" style="max-width:420px;margin:0">
        <p class="ks-hint">Centang barang yang dapat dipasok. Hapus centang untuk melepas hubungan dengan supplier ini.</p>
        <div class="ks-pick" id="supplier-parts-list">
          <?php foreach ($allParts as $part): ?>
            <label class="ks-pick-item" data-search="<?= amanHtml(strtolower($part['nama_suku_cadang'] . ' ' . $part['kode_suku_cadang'])) ?>">
              <input type="checkbox" name="barang[]" value="<?= amanHtml($part['kode_suku_cadang']) ?>"
                <?= in_array($part['kode_suku_cadang'], $linkedCodes, true) ? 'checked' : '' ?>>
              <span><?= amanHtml($part['nama_suku_cadang']) ?><small><?= amanHtml($part['kode_suku_cadang']) ?></small></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="ks-form-action"><button type="submit">Simpan daftar barang</button>
          <span class="ks-inline-info" id="jumlah-barang-dipilih"><?= count($linkedCodes) ?> dipilih</span>
        </div>
        <p class="ks-inline-info">Perubahan di sini tidak mengubah stok cabang atau data master barang.</p>
      <?php endif; ?>
    </form>
  </section>
  <?php endif; ?>

  <section class="panel ks-compact" aria-labelledby="daftar-supplier-judul">
    <div class="ks-panel-head">
      <div><h2 id="daftar-supplier-judul">Daftar supplier</h2>
        <p class="ks-sub">Supplier yang masih memasok barang harus dilepas hubungannya sebelum dihapus.</p></div>
      <form class="ks-search" method="get" action="supplier.php">
        <label for="cari-supplier" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)">Cari supplier</label>
        <input type="search" id="cari-supplier" name="q" value="<?= amanHtml($query) ?>" maxlength="80" placeholder="Cari nama atau kode" aria-label="Cari supplier">
        <button type="submit">Cari</button>
        <?php if ($query !== ''): ?><a class="ks-text-btn" href="supplier.php">Reset</a><?php endif; ?>
      </form>
    </div>
    <div class="ks-table"><table><thead><tr><th>Nama supplier</th><th>Kode</th><th>Barang dipasok</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
      <tr><td><strong><?= amanHtml($row['nama_supplier']) ?></strong></td>
        <td class="ks-code"><?= amanHtml($row['id_supplier']) ?></td>
        <td><span class="ks-count"><?= (int) $row['jumlah_barang'] ?> jenis</span></td>
        <td><div class="ks-actions">
          <a class="ks-text-btn" href="supplier.php?pilih=<?= rawurlencode($row['id_supplier']) ?>#kelola-barang">Kelola barang</a>
          <a class="ks-text-btn" href="supplier.php?ubah=<?= rawurlencode($row['id_supplier']) ?>#form-supplier">Ubah</a>
          <form method="post" action="supplier.php" onsubmit="return confirm('Hapus supplier ini? Supplier yang masih memasok barang tidak dapat dihapus.');">
            <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
            <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_supplier" value="<?= amanHtml($row['id_supplier']) ?>">
            <button type="submit" class="ks-btn ks-ghost ks-tiny">Hapus</button>
          </form>
        </div></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4" class="ks-empty">Supplier tidak ditemukan. Periksa kata pencarian atau tambah supplier baru.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php ksUiPager('hal_supplier'); ?>
    <p class="ks-inline-info">Pilih supplier untuk mengelola daftar barang yang dipasok.</p>
  </section>

  <details class="ks-create" id="form-supplier" <?= ($isEditing || $action === 'tambah' || !empty($errors)) ? 'open' : '' ?>>
    <summary><?= $isEditing ? 'Ubah nama supplier' : 'Tambah supplier' ?></summary>
    <div class="ks-form-body">
      <form method="post" action="supplier.php#form-supplier" id="supplier-data-form" novalidate>
        <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
        <input type="hidden" name="aksi" value="<?= $isEditing ? 'ubah' : 'tambah' ?>">
        <?php if ($isEditing): ?><input type="hidden" name="id_supplier" value="<?= amanHtml($editingId !== '' ? $editingId : ($edit['id_supplier'] ?? '')) ?>">
          <p class="ks-hint">Kode <?= amanHtml($editingId !== '' ? $editingId : ($edit['id_supplier'] ?? '')) ?> tidak dapat diubah.</p>
        <?php else: ?><p class="ks-hint">Kode supplier dibuat otomatis ketika disimpan.</p><?php endif; ?>
        <div class="ks-field">
          <label for="nama_supplier">Nama supplier *</label>
          <input id="nama_supplier" name="nama_supplier" type="text" maxlength="120" minlength="3" required
            placeholder="Contoh: Kahayan Parts Mandiri" value="<?= amanHtml($form['nama_supplier'] ?? '') ?>"
            aria-describedby="nama-supplier-hint<?= isset($errors['nama_supplier']) ? ' nama-supplier-error' : '' ?>"
            <?= isset($errors['nama_supplier']) ? 'aria-invalid="true" data-supplier-error="1"' : '' ?>>
          <p class="ks-hint" id="nama-supplier-hint">3–120 karakter. Nama tidak boleh sama dengan supplier lain.</p>
          <?php if (isset($errors['nama_supplier'])): ?><small class="ks-field-error" id="nama-supplier-error"><?= amanHtml($errors['nama_supplier']) ?></small><?php endif; ?>
        </div>
        <div class="ks-form-action"><button type="submit"><?= $isEditing ? 'Simpan perubahan' : 'Simpan supplier' ?></button>
          <?php if ($isEditing): ?><a class="ks-btn ks-ghost" href="supplier.php">Batal ubah</a><?php endif; ?>
        </div>
      </form>
    </div>
  </details>
</div>
<script>
(() => {
  const block = document.getElementById('form-supplier');
  document.getElementById('open-supplier')?.addEventListener('click', () => {
    block.open = true;
    setTimeout(() => document.getElementById('nama_supplier')?.focus({preventScroll:true}), 0);
  });
  if (location.hash === '#form-supplier') block.open = true;
  const invalid = document.querySelector('[data-supplier-error="1"]');
  if (invalid) {
    block.open = true;
    window.addEventListener('load', () => {
      invalid.scrollIntoView({block:'center'});
      invalid.focus({preventScroll:true});
    }, {once:true});
  } else if (document.getElementById('supplier-error')) {
    // Saat gagal, pertahankan panel dan tunjukkan penyebabnya.
    window.addEventListener('load', () => {
      const error = document.getElementById('supplier-error');
      error?.scrollIntoView({block:'start'});
      error?.focus({preventScroll:true});
    }, {once:true});
  }
  const flash = document.getElementById('supplier-flash');
  document.getElementById('supplier-data-form')?.addEventListener('input', () => {if (flash) flash.hidden = true;}, {once:true});
  document.getElementById('supplier-parts-form')?.addEventListener('change', () => {if (flash) flash.hidden = true;}, {once:true});
  const search = document.getElementById('cari-barang-supplier');
  const labels = [...document.querySelectorAll('[data-search]')];
  const count = document.getElementById('jumlah-barang-dipilih');
  if (search) search.addEventListener('input', () => {
    const term = search.value.trim().toLocaleLowerCase('id');
    labels.forEach(label => {label.hidden = !!term && !label.dataset.search.includes(term);});
  });
  function refreshCount() {
    if (count) count.textContent = document.querySelectorAll('#supplier-parts-form input[name="barang[]"]:checked').length + ' dipilih';
  }
  document.getElementById('supplier-parts-form')?.addEventListener('change', refreshCount);
})();
</script>
<?php akhirHalamanM2(); ?>
