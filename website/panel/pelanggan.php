<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_pelanggan_m2.php';
require_once __DIR__ . '/../app/korporat_m2.php';
$akun = akunLoginWajib();
if (!bolehMengelolaPelanggan($akun)) {
    http_response_code(403);
    exit('403 — Tidak memiliki izin untuk modul pelanggan.');
}
$db = koneksiKalawaM2();
$korporat = ($_GET['tipe'] ?? null) === 'korporat';
$halaman = $korporat ? 'pelanggan.php?tipe=korporat' : 'pelanggan.php';
$urlUbah = $halaman . ($korporat ? '&' : '?') . 'ubah=';
$filterJenis = $korporat ? 'pk.id_pelanggan IS NOT NULL' : 'pk.id_pelanggan IS NULL';
$judul = $korporat ? 'Pelanggan Korporat' : 'Data Pelanggan';
$error = '';
$errors = [];
$old = [];
$aksiPost = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validasiCsrf();
    $aksiPost = (string) ($_POST['aksi'] ?? '');
    try {
        if ($aksiPost === 'hapus') {
            $id = trim((string) ($_POST['id_pelanggan'] ?? ''));
            if (!preg_match('/\A[A-Za-z0-9._-]{3,15}\z/D', $id)) {
                throw new InvalidArgumentException('ID pelanggan tidak valid.');
            }
            korporatMulaiTransaksi($db);
            if (!korporatKunciPelanggan($db, $id) || korporatAdaProfil($db, $id) !== $korporat) {
                throw new InvalidArgumentException('Pelanggan tidak ditemukan di daftar ini.');
            }
            if ($korporat) {
                $stmt = $db->prepare('DELETE FROM pelanggan_korporat WHERE id_pelanggan = :id');
                $stmt->execute(['id' => $id]);
            }
            $stmt = $db->prepare('DELETE FROM pelanggan WHERE id_pelanggan = :id');
            $stmt->execute(['id' => $id]);
            $db->commit();
            pesanKilAt('Pelanggan berhasil dihapus.');
            pindah($halaman);
        }
        if (!in_array($aksiPost, ['tambah', 'ubah'], true)) {
            throw new InvalidArgumentException('Aksi tidak dikenal.');
        }
        // Data formulir ditahan SEBELUM validasi sehingga tidak hilang saat error.
        $hasil = validasiInputPelangganM2($_POST, $korporat);
        $data = $hasil['data'];
        $old = $data;
        $errors = $hasil['errors'];
        $pic = is_string($_POST['nama_pic'] ?? null) ? trim($_POST['nama_pic']) : '';
        if ($korporat) {
            $old['nama_pic'] = $pic;
            if (panjangTeksKalawa($pic) > 100 || preg_match('/\A[^\x00-\x1F\x7F]*\z/uD', $pic) !== 1) {
                $errors['nama_pic'] = 'Nama PIC maksimal 100 karakter tanpa karakter kontrol.';
            }
        }
        $id = $data['id_pelanggan'];
        if ($aksiPost === 'ubah' && (string) ($_GET['ubah'] ?? '') !== $id) {
            $errors['id_pelanggan'] = 'ID pelanggan yang diubah tidak cocok dengan URL. Buka ulang data dari daftar.';
        }
        if (!$errors) {
            if ($data['kode_cabang_favorit'] !== '') {
                $st = $db->prepare('SELECT 1 FROM cabang WHERE kode_cabang = :kode LIMIT 1');
                $st->execute(['kode' => $data['kode_cabang_favorit']]);
                if (!$st->fetchColumn()) {
                    $errors['kode_cabang_favorit'] = 'Cabang tidak ditemukan. Pilih cabang yang tersedia.';
                }
            }
            $st = $db->prepare('SELECT id_pelanggan FROM pelanggan WHERE no_identitas = :nomor LIMIT 1');
            $st->execute(['nomor' => $data['no_identitas']]);
            $existingId = $st->fetchColumn();
            if ($existingId !== false && ($aksiPost === 'tambah' || (string) $existingId !== $id)) {
                $errors['no_identitas'] = 'Nomor identitas sudah digunakan pelanggan lain.';
            }
            if ($aksiPost === 'tambah') {
                $st = $db->prepare('SELECT 1 FROM pelanggan WHERE id_pelanggan = :id LIMIT 1');
                $st->execute(['id' => $id]);
                if ($st->fetchColumn()) {
                    $errors['id_pelanggan'] = 'ID pelanggan sudah digunakan. Gunakan ID yang berbeda.';
                }
            }
        }
        if (!$errors) {
            $data['kode_cabang_favorit'] = $data['kode_cabang_favorit'] ?: null;
            korporatMulaiTransaksi($db);
            if ($aksiPost === 'ubah' && (!korporatKunciPelanggan($db, $id) || korporatAdaProfil($db, $id) !== $korporat)) {
                throw new InvalidArgumentException('Pelanggan tidak ditemukan di daftar ini.');
            }
            if ($aksiPost === 'tambah') {
                $sql = 'INSERT INTO pelanggan (id_pelanggan, nama_pelanggan, no_telepon,
                        jenis_identitas, no_identitas, alamat, kode_cabang_favorit, id_mekanik_pilihan)
                        VALUES (:id_pelanggan, :nama_pelanggan, :no_telepon, :jenis_identitas,
                                :no_identitas, :alamat, :kode_cabang_favorit, NULL)';
            } else {
                $sql = 'UPDATE pelanggan SET nama_pelanggan=:nama_pelanggan,
                        no_telepon=:no_telepon, jenis_identitas=:jenis_identitas,
                        no_identitas=:no_identitas, alamat=:alamat,
                        kode_cabang_favorit=:kode_cabang_favorit
                        WHERE id_pelanggan=:id_pelanggan';
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($data);
            if ($korporat) {
                $sqlProfil = $aksiPost === 'tambah'
                    ? 'INSERT INTO pelanggan_korporat (id_pelanggan,nama_pic) VALUES (:id,:pic)'
                    : 'UPDATE pelanggan_korporat SET nama_pic=:pic WHERE id_pelanggan=:id';
                $stmt = $db->prepare($sqlProfil);
                $stmt->execute(['id'=>$id, 'pic'=>$pic === '' ? null : $pic]);
            }
            $db->commit();
            pesanKilAt($aksiPost === 'tambah' ? 'Pelanggan ditambahkan.' : 'Data pelanggan disimpan.');
            pindah($halaman);
        }
        if ($errors) {
            $error = 'Periksa ' . count($errors) . ' kolom yang ditandai. Data yang sudah diisi tetap tersimpan di form.';
        }
    } catch (InvalidArgumentException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa M2: pelanggan POST] ' . $e->getMessage());
        $sqlCode = (int) ($e->errorInfo[1] ?? 0);
        if ($aksiPost === 'hapus' && $sqlCode === 1451) {
            $error = 'Pelanggan tidak dapat dihapus karena masih mempunyai relasi motor, kontrak, atau transaksi.';
        } elseif ($sqlCode === 1062) {
            $error = 'ID pelanggan atau nomor identitas sudah digunakan. Periksa kembali kolom tersebut.';
        } elseif ((string) $e->getCode() === '23000') {
            $error = 'Operasi ditolak aturan relasi database. Pastikan referensi pelanggan dan cabang valid.';
        } else {
            $error = 'Perubahan belum tersimpan. Periksa hak akses atau catatan server.';
        }
    }
}
$edit = null;
if (isset($_GET['ubah'])) {
    $id = is_string($_GET['ubah']) ? $_GET['ubah'] : '';
    if (preg_match('/\A[A-Za-z0-9._-]{3,15}\z/D', $id)) {
        $stmt = $db->prepare('SELECT p.*,pk.nama_pic FROM pelanggan p
            LEFT JOIN pelanggan_korporat pk ON pk.id_pelanggan=p.id_pelanggan
            WHERE p.id_pelanggan=:id AND ' . $filterJenis);
        $stmt->execute(['id' => $id]);
        $edit = $stmt->fetch() ?: null;
    }
}
try {
    $cabangs = $db->query('SELECT kode_cabang, nama_cabang FROM cabang ORDER BY kode_cabang')->fetchAll();
    $q = trim((string) ($_GET['q'] ?? ''));
    $q = substr($q, 0, 70);
    $list = 'SELECT p.id_pelanggan,p.nama_pelanggan,p.no_telepon,p.jenis_identitas,p.kode_cabang_favorit
        FROM pelanggan p LEFT JOIN pelanggan_korporat pk ON pk.id_pelanggan=p.id_pelanggan WHERE ' . $filterJenis;
    $args=[];
    if($q!==''){$list.=' AND (p.id_pelanggan LIKE :a OR p.nama_pelanggan LIKE :b)';$args=['a'=>'%'.$q.'%','b'=>'%'.$q.'%'];}
    $daftar=ksUiList($db,$list.' ORDER BY p.id_pelanggan DESC',$args,'hal_pelanggan');
} catch (Throwable $e) {
    error_log('[Kalawa M2: pelanggan SELECT] ' . $e->getMessage());
    http_response_code(503);
    exit('Data pelanggan tidak dapat dibaca.');
}
$form = $old ?: ($edit ?: []);
$isEditing = $aksiPost === 'ubah' || $edit !== null;
$flash = ambilPesanKilAt();
// Pesan sukses lama tidak boleh tampil bersamaan dengan kegagalan POST baru.
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $error !== '') {
    $flash = null;
}
function fieldHintM2(string $field, string $message, array $errors): void
{
    echo '<small id="' . amanHtml($field) . '-hint" class="field-hint">' . amanHtml($message) . '</small>';
    if (isset($errors[$field])) {
        echo '<small id="' . amanHtml($field) . '-error" class="field-error">' . amanHtml($errors[$field]) . '</small>';
    }
}
function fieldAttrsM2(string $field, array $errors): string
{
    return 'aria-describedby="' . amanHtml($field) . '-hint' . (isset($errors[$field]) ? ' ' . amanHtml($field) . '-error' : '') . '"'
        . (isset($errors[$field]) ? ' aria-invalid="true" data-server-error="true"' : '');
}
awalHalamanM2($judul, $akun);
?>
<h1><?= amanHtml($judul) ?></h1>
<p class="muted"><?= $korporat ? 'Kelola profil perusahaan dan kontrak servisnya.' : 'Kelola identitas dan kontak pelanggan perorangan.' ?></p>
<nav class="row" aria-label="Jenis pelanggan">
  <a class="btn<?= $korporat ? ' light' : '' ?>" href="pelanggan.php" <?= $korporat ? '' : 'aria-current="page"' ?>>Perorangan</a>
  <a class="btn<?= $korporat ? '' : ' light' ?>" href="pelanggan.php?tipe=korporat" <?= $korporat ? 'aria-current="page"' : '' ?>>Korporat</a>
</nav>
<?php if ($flash): ?><div class="alert" id="flash-pelanggan" role="status"><?= amanHtml($flash['pesan']) ?></div><?php endif; ?>
<?php if ($error): ?>
<div class="alert bad error-summary" role="alert" tabindex="-1">
  <strong><?= amanHtml($error) ?></strong>
  <?php if ($errors): ?>
  <ul>
    <?php foreach ($errors as $field => $message): ?>
      <li><a href="#<?= amanHtml($field) ?>"><?= amanHtml($message) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endif; ?>
<section class="panel" id="form-pelanggan">
<h2><?= $isEditing ? 'Ubah pelanggan' : 'Tambah pelanggan' ?></h2>
<p class="muted"><small>Kolom bertanda * wajib diisi. Format masing-masing kolom dijelaskan di bawahnya.</small></p>
<form id="pelanggan-form" method="post" action="<?= amanHtml($isEditing ? $urlUbah . urlencode((string) ($form['id_pelanggan'] ?? '')) : $halaman) ?>">
<input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
<input type="hidden" name="aksi" value="<?= $isEditing ? 'ubah' : 'tambah' ?>">
<div class="grid"><div>
<label for="id_pelanggan">ID pelanggan *</label>
<input id="id_pelanggan" name="id_pelanggan" maxlength="15" minlength="3" pattern="[A-Za-z0-9._-]{3,15}" required
 title="Gunakan 3–15 huruf, angka, titik, strip, atau garis bawah."
 value="<?= amanHtml($form['id_pelanggan'] ?? '') ?>" <?= $isEditing ? 'readonly' : '' ?> <?= fieldAttrsM2('id_pelanggan', $errors) ?>>
<?php fieldHintM2('id_pelanggan', '3–15 karakter; huruf, angka, titik (.), tanda hubung (-), atau garis bawah (_). Contoh: CUS-0100.', $errors); ?>

<label for="nama_pelanggan"><?= $korporat ? 'Nama perusahaan' : 'Nama pelanggan' ?> *</label>
<input id="nama_pelanggan" name="nama_pelanggan" maxlength="100" required autocomplete="name"
 value="<?= amanHtml($form['nama_pelanggan'] ?? '') ?>" <?= fieldAttrsM2('nama_pelanggan', $errors) ?>>
<?php fieldHintM2('nama_pelanggan', 'Wajib diisi, maksimal 100 karakter. Boleh menggunakan spasi, misalnya: Dody Pratama.', $errors); ?>

<label for="no_telepon">Nomor telepon *</label>
<input id="no_telepon" name="no_telepon" type="tel" inputmode="tel" maxlength="20" minlength="7" required
 pattern="[+0-9 ()-]{7,20}" title="Gunakan 7–20 karakter; minimal 7 digit angka. Boleh +, spasi, kurung, atau tanda hubung."
 value="<?= amanHtml($form['no_telepon'] ?? '') ?>" <?= fieldAttrsM2('no_telepon', $errors) ?>>
<?php fieldHintM2('no_telepon', '7–20 karakter, minimal 7 angka. Contoh: 081234567890 atau +62 81234567890.', $errors); ?>

<label for="jenis_identitas">Jenis identitas *</label>
<select id="jenis_identitas" name="jenis_identitas" required <?= fieldAttrsM2('jenis_identitas', $errors) ?>>
<?php foreach (jenisIdentitasPelangganM2($korporat) as $jenis): ?>
<option value="<?= amanHtml($jenis) ?>" <?= ($form['jenis_identitas'] ?? '') === $jenis ? 'selected' : '' ?>><?= $jenis === 'NPWP_BADAN' ? 'NPWP badan' : amanHtml($jenis) ?></option>
<?php endforeach; ?>
</select>
<?php fieldHintM2('jenis_identitas', $korporat ? 'Gunakan identitas badan usaha yang dicatat bengkel.' : 'Pilih dokumen pelanggan.', $errors); ?>
<?php if ($korporat): ?>
<label for="nama_pic">Nama PIC (opsional)</label>
<input id="nama_pic" name="nama_pic" maxlength="100" value="<?= amanHtml($form['nama_pic'] ?? '') ?>" <?= fieldAttrsM2('nama_pic', $errors) ?>>
<?php fieldHintM2('nama_pic', 'Kontak perusahaan untuk pekerjaan servis.', $errors); ?>
<?php endif; ?>
</div><div>
<label for="no_identitas">Nomor identitas *</label>
<input id="no_identitas" name="no_identitas" maxlength="30" minlength="5" pattern="[A-Za-z0-9-]{5,30}" required
 title="5–30 karakter; hanya huruf, angka, atau tanda hubung (-)."
 value="<?= amanHtml($form['no_identitas'] ?? '') ?>" <?= fieldAttrsM2('no_identitas', $errors) ?>>
<?php fieldHintM2('no_identitas', '5–30 karakter; huruf, angka, atau tanda hubung (-). Harus unik untuk tiap pelanggan.', $errors); ?>

<label for="alamat">Alamat *</label>
<textarea id="alamat" name="alamat" rows="3" maxlength="255" required <?= fieldAttrsM2('alamat', $errors) ?>><?= amanHtml($form['alamat'] ?? '') ?></textarea>
<?php fieldHintM2('alamat', 'Wajib diisi, maksimal 255 karakter. Isi alamat yang memudahkan administrasi servis.', $errors); ?>

<label for="kode_cabang_favorit">Cabang favorit (opsional)</label>
<select id="kode_cabang_favorit" name="kode_cabang_favorit" <?= fieldAttrsM2('kode_cabang_favorit', $errors) ?>>
<option value="">Tidak ditentukan</option>
<?php foreach ($cabangs as $c): ?>
<option value="<?= amanHtml($c['kode_cabang']) ?>" <?= ($form['kode_cabang_favorit'] ?? '') === $c['kode_cabang'] ? 'selected' : '' ?>><?= amanHtml($c['nama_cabang']) ?></option>
<?php endforeach; ?>
</select>
<?php fieldHintM2('kode_cabang_favorit', 'Opsional. Pilih dari daftar cabang yang tersedia; bukan penentu hak akses cabang.', $errors); ?>
</div></div>
<button type="submit"><?= $isEditing ? 'Simpan perubahan' : 'Tambah pelanggan' ?></button>
<?php if ($isEditing): ?><a class="btn light" href="<?= amanHtml($halaman) ?>">Batal mengubah</a><?php endif; ?>
</form></section>
<section class="panel"><div class="row space"><h2>Daftar pelanggan</h2>
<form class="row" method="get" action="pelanggan.php">
<?php if ($korporat): ?><input type="hidden" name="tipe" value="korporat"><?php endif; ?>
<label class="sr-only" for="q">Cari pelanggan</label>
<input id="q" style="width:220px;margin:0" name="q" maxlength="70" placeholder="ID atau nama" value="<?= amanHtml($q) ?>">
<button type="submit">Cari</button></form></div>
<p class="muted"><small>Cari berdasarkan ID/nama (maksimal 70 karakter). Nomor identitas dan alamat tidak ditampilkan dalam tabel.</small></p>
<div class="tablewrap"><table><thead><tr><th>ID</th><th>Nama</th><th>Telepon</th><th>Jenis Identitas</th><th>Cabang Favorit</th><th>Aksi</th></tr></thead><tbody>
<?php foreach ($daftar as $r): ?><tr>
<td><?= amanHtml($r['id_pelanggan']) ?></td><td><?= amanHtml($r['nama_pelanggan']) ?></td>
<td><?= amanHtml($r['no_telepon']) ?></td><td><?= amanHtml($r['jenis_identitas']) ?></td><td><?= amanHtml($r['kode_cabang_favorit'] ?? '-') ?></td>
<td><div class="row">
<a class="btn light" href="<?= amanHtml($urlUbah . urlencode($r['id_pelanggan'])) ?>">Ubah</a>
<?php if ($korporat): ?>
<a class="btn" href="kontrak_korporat.php?id=<?= urlencode($r['id_pelanggan']) ?>">Kelola kontrak</a>
<?php endif; ?>
<form method="post" action="<?= amanHtml($halaman) ?>" onsubmit="return confirm('Hapus pelanggan ini? Data yang masih digunakan akan ditolak database.');">
<input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
<input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_pelanggan" value="<?= amanHtml($r['id_pelanggan']) ?>">
<button class="danger" type="submit">Hapus</button></form>
</div></td></tr><?php endforeach; ?>
<?php if (!$daftar): ?><tr><td colspan="6">Tidak ada pelanggan yang cocok.</td></tr><?php endif; ?></tbody></table></div>
<?php ksUiPager('hal_pelanggan'); ?></section>
<script src="../assets/js/kalawa_form_m2.js" defer></script>
<script>
// Ketika mulai mengisi form baru atau validasi browser menolak input,
// hilangkan pesan sukses dari operasi SEBELUMNYA.
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('pelanggan-form');
    const flash = document.getElementById('flash-pelanggan');
    if (!form || !flash) return;
    const hideOldSuccess = () => flash.remove();
    for (const eventName of ['input', 'change', 'invalid']) {
        form.addEventListener(eventName, hideOldSuccess, true);
    }
});
</script>
<?php akhirHalamanM2(); ?>
