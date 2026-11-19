<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_jasa_m2.php';
$akun = akunLoginWajib();
// Kebijakan fase uji: hanya Owner. Jangan gunakan kode_cabang_favorit sebagai otorisasi.
if (($akun['role_db'] ?? '') !== 'rl_owner_ops') {
    http_response_code(403);
    exit('403 — Akun ini tidak memiliki izin mengelola jasa servis.');
}
$db = koneksiKalawaM2(); // Guard khusus kalawa_service_f1_test_fixed tetap berlaku.
$error = '';
$errors = [];
$old = [];
$action = (string) ($_POST['aksi'] ?? '');
$editingCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validasiCsrf();
    try {
        if ($action === 'hapus') {
            $code = trim((string) ($_POST['kode_jasa'] ?? ''));
            if (!preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D', $code)) {
                throw new InvalidArgumentException('Kode layanan tidak valid. Pilih dari daftar.');
            }
            // FK fk_dwj_jasa akan menolak penghapusan layanan yang sudah digunakan dalam WO.
            $st = $db->prepare('DELETE FROM jasa_servis WHERE kode_jasa = :code');
            $st->execute(['code' => $code]);
            if ($st->rowCount() !== 1) {
                throw new InvalidArgumentException('Layanan tidak ditemukan. Perbarui daftar dan coba lagi.');
            }
            pesanKilAt('Layanan berhasil dihapus.');
            pindah('jasa.php');
        }
        if (!in_array($action, ['tambah', 'ubah'], true)) {
            throw new InvalidArgumentException('Perintah tidak dikenali. Muat ulang halaman.');
        }
        $hasil = validasiJasaM2($_POST);
        $old = $hasil['data']; // Pertahankan isian meskipun server menemukan kesalahan.
        $errors = $hasil['errors'];
        $editingCode = $action === 'ubah' ? trim((string) ($_POST['kode_jasa'] ?? '')) : '';
        if ($action === 'ubah' && !preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D', $editingCode)) {
            throw new InvalidArgumentException('Kode layanan yang ingin diubah tidak valid. Pilih kembali dari daftar.');
        }
        if (!$errors) {
            $duplicate = $db->prepare('SELECT kode_jasa FROM jasa_servis
                WHERE uraian_jasa_servis = :nama AND kode_jasa <> :kode LIMIT 1');
            $duplicate->execute(['nama' => $old['uraian_jasa_servis'], 'kode' => $editingCode]);
            if ($duplicate->fetchColumn() !== false) {
                $errors['uraian_jasa_servis'] = 'Nama layanan sudah terdaftar. Gunakan nama yang berbeda.';
            }
        }
        if (!$errors && $action === 'tambah') {
            $codes = $db->query('SELECT kode_jasa FROM jasa_servis')->fetchAll(PDO::FETCH_COLUMN);
            $newCode = kodeJasaBerikutnyaM2($codes);
            $st = $db->prepare('INSERT INTO jasa_servis (kode_jasa, uraian_jasa_servis)
                                VALUES (:kode, :nama)');
            $st->execute(['kode' => $newCode, 'nama' => $old['uraian_jasa_servis']]);
            pesanKilAt('Layanan berhasil ditambahkan.');
            pindah('jasa.php');
        }
        if (!$errors && $action === 'ubah') {
            $st = $db->prepare('UPDATE jasa_servis SET uraian_jasa_servis = :nama WHERE kode_jasa = :kode');
            $st->execute(['nama' => $old['uraian_jasa_servis'], 'kode' => $editingCode]);
            if ($st->rowCount() === 0) {
                $check = $db->prepare('SELECT 1 FROM jasa_servis WHERE kode_jasa = :kode');
                $check->execute(['kode' => $editingCode]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Layanan sudah tidak tersedia. Pilih kembali dari daftar.');
                }
            }
            pesanKilAt('Layanan berhasil diperbarui.');
            pindah('jasa.php');
        }
        if ($errors) $error = 'Periksa kolom yang ditandai. Isian kamu tidak hilang.';
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log('[Kalawa F2 TEST jasa] ' . $e->getMessage());
        $errno = (int) ($e->errorInfo[1] ?? 0);
        $error = match ($errno) {
            1451 => 'Layanan tidak bisa dihapus karena sudah tercatat dalam work order.',
            1062 => 'Kode layanan baru sudah digunakan. Muat ulang halaman dan coba lagi.',
            1142 => 'Akses database belum diizinkan. Periksa GRANT pada akun pengujian.',
            default => 'Perubahan belum berhasil disimpan. Periksa catatan server.',
        };
    } catch (Throwable $e) {
        error_log('[Kalawa F2 TEST jasa] ' . $e->getMessage());
        $error = 'Operasi tidak berhasil. Periksa catatan server.';
    }
}

$edit = null;
try {
    $editParam = isset($_GET['ubah']) ? trim((string) $_GET['ubah']) : '';
    if ($editParam !== '' && preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D', $editParam)) {
        $st = $db->prepare('SELECT kode_jasa, uraian_jasa_servis FROM jasa_servis WHERE kode_jasa = :kode');
        $st->execute(['kode' => $editParam]);
        $edit = $st->fetch() ?: null;
        if (!$edit && $_SERVER['REQUEST_METHOD'] !== 'POST') $error = 'Layanan tidak ditemukan. Pilih dari daftar.';
    }
    $q = substr(trim((string) ($_GET['q'] ?? '')), 0, 70);
    $sql='SELECT kode_jasa,uraian_jasa_servis FROM jasa_servis';$args=[];
    if($q!==''){$sql.=' WHERE kode_jasa LIKE :code OR uraian_jasa_servis LIKE :name';$args=['code'=>'%'.$q.'%','name'=>'%'.$q.'%'];}
    $rows=ksUiList($db,$sql.' ORDER BY kode_jasa ASC',$args,'hal_jasa');
} catch (Throwable $e) {
    error_log('[Kalawa F2 TEST jasa SELECT] ' . $e->getMessage());
    http_response_code(503);
    exit('Daftar layanan tidak bisa dibuka. Periksa koneksi dan hak akses database uji.');
}

$isEditing = ($action === 'ubah') || ($edit !== null && $_SERVER['REQUEST_METHOD'] !== 'POST');
$form = $old ?: ($isEditing && $edit ? $edit : []);
$flash = ambilPesanKilAt();
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $error !== '') $flash = null;
awalHalamanM2('Jasa Servis', $akun);
?>
<style>
/* R01: penataan lokal; tidak mengubah CSS modul pelanggan/motor. */
.ks-jasa{--accent:#ae252d;--line:#e5e7eb;--muted:#677486;color:#18212b}
.ks-jasa .head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:6px 0 20px}
.ks-jasa .head h1{margin:0 0 5px}.ks-jasa .sub{color:var(--muted);margin:0;font-size:.91rem;line-height:1.5}
.ks-jasa .button{display:inline-block;background:var(--accent);color:#fff;border:1px solid var(--accent);border-radius:10px;padding:10px 14px;font-weight:650;text-decoration:none;white-space:nowrap}
.ks-jasa .button.quiet{background:#fff;color:var(--accent);border-color:#e8b9be}
.ks-jasa .button:hover{text-decoration:none;filter:brightness(.97)}
.ks-jasa .panel{margin:15px 0;padding:20px}
.ks-jasa .section-top{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.ks-jasa h2{margin:0 0 6px;font-size:1.12rem}
.ks-jasa .find{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.ks-jasa .find input{margin:0;min-width:170px;width:230px;max-width:100%}
.ks-jasa .find button{margin:0}.ks-jasa .tablewrap{overflow-x:auto}
.ks-jasa table{min-width:560px}.ks-jasa td{vertical-align:middle}
.ks-jasa .code{font-weight:650;color:#344155}.ks-jasa .actions{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.ks-jasa .actions form{margin:0}.ks-jasa .actions button{font-size:.83rem;padding:6px 9px;background:#fff;color:var(--accent);border-color:#f0c4c7}
.ks-jasa .link{font-weight:650;font-size:.86rem;padding:7px;color:var(--accent)}
.ks-jasa .editing{background:#fff9e8}.ks-jasa .empty{color:var(--muted);padding:24px;text-align:center}
.ks-jasa .formbox{border:1px solid var(--line);background:#fff;border-radius:14px;overflow:hidden;margin:16px 0}
.ks-jasa .formbox summary{list-style:none;cursor:pointer;font-weight:750;display:flex;justify-content:space-between;align-items:center;padding:18px 20px}
.ks-jasa .formbox summary::-webkit-details-marker{display:none}
.ks-jasa .formbox summary:after{content:'+';font-size:1.3rem;color:var(--accent)}
.ks-jasa .formbox[open] summary{border-bottom:1px solid var(--line)}
.ks-jasa .formbox[open] summary:after{content:'−'}
.ks-jasa .inside{padding:18px 20px}.ks-jasa .hint{font-size:.8rem;color:var(--muted);margin:5px 0 14px;line-height:1.45}
.ks-jasa .errorfield{color:#a81d24;font-weight:650;font-size:.83rem;margin:-7px 0 13px;display:block}
.ks-jasa [aria-invalid=true]{background:#fff7f7;border-color:#b4232d}
.ks-jasa .alert{scroll-margin-top:15px}.ks-jasa .minor{font-size:.81rem;color:var(--muted);margin:6px 0 0}
@media(max-width:650px){.ks-jasa .head{align-items:flex-start}.ks-jasa .head .button{width:100%;text-align:center}.ks-jasa .panel{padding:14px}.ks-jasa .section-top{display:block}.ks-jasa .find{margin-top:12px}.ks-jasa .find input{width:100%}.ks-jasa .find button{flex:1}}
</style>
<div class="ks-jasa">
  <div class="head">
    <div><h1>Jasa servis</h1><p class="sub">Daftar layanan yang dapat ditambahkan ke pekerjaan servis.</p></div>
    <a class="button" href="#form-jasa" id="buka-form">+ Tambah layanan</a>
  </div>
  <?php if ($flash): ?><div class="alert" id="pesan-jasa" role="status"><?= amanHtml($flash['pesan']) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert bad" role="alert" id="error-jasa"><strong><?= amanHtml($error) ?></strong>
    <?php foreach ($errors as $msg): ?><div style="margin-top:7px"><a href="#uraian_jasa_servis"><?= amanHtml($msg) ?></a></div><?php endforeach; ?>
    </div><?php endif; ?>
  <section class="panel" id="daftar-jasa">
    <div class="section-top"><div><h2>Daftar layanan</h2><p class="sub">Pilih Ubah untuk memperbarui nama. Layanan yang sudah dipakai dalam transaksi tidak bisa dihapus.</p></div>
      <form method="get" action="jasa.php" class="find" role="search">
        <label class="sr-only" for="cari-jasa">Cari layanan</label>
        <input id="cari-jasa" name="q" value="<?= amanHtml($q) ?>" maxlength="70" placeholder="Cari nama layanan" aria-label="Cari layanan berdasarkan nama atau kode">
        <button type="submit">Cari</button>
        <?php if ($q !== ''): ?><a class="button quiet" href="jasa.php">Reset</a><?php endif; ?>
      </form>
    </div>
    <div class="tablewrap" role="region" aria-label="Daftar layanan" tabindex="0"><table><thead><tr><th>Layanan</th><th>Kode</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $isEditing && ($edit['kode_jasa'] ?? $editingCode) === $r['kode_jasa'] ? 'editing' : '' ?>">
        <td><strong><?= amanHtml($r['uraian_jasa_servis']) ?></strong></td>
        <td class="code"><?= amanHtml($r['kode_jasa']) ?></td>
        <td><div class="actions"><a class="link" href="jasa.php?ubah=<?= rawurlencode($r['kode_jasa']) ?>#form-jasa">Ubah</a>
          <form method="post" action="jasa.php" onsubmit="return confirm('Hapus layanan ini? Jika pernah dipakai pada work order, penghapusan akan ditolak.');">
            <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="kode_jasa" value="<?= amanHtml($r['kode_jasa']) ?>">
            <button type="submit">Hapus</button>
          </form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="3" class="empty">Tidak ada layanan yang cocok. Coba kata kunci lain.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php ksUiPager('hal_jasa'); ?>
    <p class="minor">Kode dibuat otomatis ketika menambah layanan.</p>
  </section>
  <details class="formbox" id="form-jasa" <?= $isEditing || ($error !== '' && $action !== 'hapus') || $action === 'tambah' ? 'open' : '' ?>>
    <summary><?= $isEditing ? 'Ubah layanan '.amanHtml($editingCode ?: ($edit['kode_jasa'] ?? '')) : 'Tambah layanan baru' ?></summary>
    <div class="inside"><form method="post" id="jasa-form" action="jasa.php<?= $isEditing ? '?ubah='.rawurlencode($editingCode ?: (string)($edit['kode_jasa'] ?? '')) : '' ?>">
      <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
      <input type="hidden" name="aksi" value="<?= $isEditing ? 'ubah' : 'tambah' ?>">
      <?php if ($isEditing): ?><input type="hidden" name="kode_jasa" value="<?= amanHtml($editingCode ?: ($edit['kode_jasa'] ?? '')) ?>"><?php endif; ?>
      <label for="uraian_jasa_servis">Nama layanan *</label>
      <input type="text" id="uraian_jasa_servis" name="uraian_jasa_servis" minlength="3" maxlength="150" required
        placeholder="Contoh: Servis dan pemeriksaan rem"
        value="<?= amanHtml($form['uraian_jasa_servis'] ?? '') ?>"
        aria-describedby="jasa-hint<?= isset($errors['uraian_jasa_servis']) ? ' jasa-error' : '' ?>"
        <?= isset($errors['uraian_jasa_servis']) ? 'aria-invalid="true" data-server-error="true"' : '' ?>>
      <p id="jasa-hint" class="hint">3–150 karakter. Isi nama pekerjaan dengan jelas; harga belum dikelola pada modul ini.</p>
      <?php if (isset($errors['uraian_jasa_servis'])): ?><small class="errorfield" id="jasa-error"><?= amanHtml($errors['uraian_jasa_servis']) ?></small><?php endif; ?>
      <div class="row"><button type="submit"><?= $isEditing ? 'Simpan perubahan' : 'Simpan layanan' ?></button>
        <?php if ($isEditing): ?><a class="button quiet" href="jasa.php">Batal ubah</a><?php endif; ?>
      </div>
    </form></div>
  </details>
</div>
<script>
(() => {
  const details = document.getElementById('form-jasa');
  document.getElementById('buka-form')?.addEventListener('click', () => {
    if (details) details.open = true;
    setTimeout(() => document.getElementById('uraian_jasa_servis')?.focus({preventScroll:true}), 0);
  });
  if (window.location.hash === '#form-jasa' && details) details.open = true;
  const serverError = document.querySelector('[data-server-error="true"]');
  if (serverError) {
    details.open = true;
    window.addEventListener('load', () => { serverError.scrollIntoView({block:'center'}); serverError.focus({preventScroll:true}); }, {once:true});
  }
  // Cegah notifikasi sukses lama muncul selama user sedang menyunting isian baru.
  const success = document.getElementById('pesan-jasa');
  document.getElementById('jasa-form')?.addEventListener('input', () => {if(success) success.hidden = true;}, {once:true});
})();
</script>
<?php akhirHalamanM2(); ?>
