<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_suku_cadang_m2.php';

$akun = akunLoginWajib();
if (($akun['role_db'] ?? '') !== 'rl_owner_ops') {
    http_response_code(403);
    exit('403 — Akun ini tidak memiliki izin mengelola suku cadang.');
}
$db = koneksiKalawaM2(); // Guard TEST ONLY tetap di database_m2.php.
$error = '';
$errors = [];
$old = [];
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['aksi'] ?? '') : '';
$editingCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validasiCsrf();
    try {
        if ($action === 'hapus') {
            $code = trim((string) ($_POST['kode_suku_cadang'] ?? ''));
            if (!kodeSukuCadangValidM2($code)) {
                throw new InvalidArgumentException('Barang tidak valid. Pilih barang dari daftar.');
            }
            // FK stok_cabang, detail_wo_suku_cadang, supplier_suku_cadang
            // menolak DELETE barang yang masih dirujuk; tidak menonaktifkan constraint.
            $st = $db->prepare('DELETE FROM suku_cadang WHERE kode_suku_cadang = :code');
            $st->execute(['code' => $code]);
            if ($st->rowCount() !== 1) {
                throw new InvalidArgumentException('Barang tidak ditemukan. Muat ulang daftar.');
            }
            pesanKilAt('Suku cadang berhasil dihapus.');
            pindah('suku_cadang.php');
        }
        if ($action !== 'tambah' && $action !== 'ubah') {
            throw new InvalidArgumentException('Permintaan tidak dikenali. Muat ulang halaman.');
        }
        $result = validasiSukuCadangM2($_POST);
        $old = $result['data'];
        $errors = $result['errors'];
        $editingCode = $action === 'ubah' ? trim((string) ($_POST['kode_suku_cadang'] ?? '')) : '';
        if ($action === 'ubah' && !kodeSukuCadangValidM2($editingCode)) {
            throw new InvalidArgumentException('Kode suku cadang tidak valid. Pilih dari daftar.');
        }
        if (!$errors) {
            $st = $db->prepare('SELECT 1 FROM suku_cadang
                WHERE nama_suku_cadang = :nama AND kode_suku_cadang <> :kode LIMIT 1');
            $st->execute(['nama' => $old['nama_suku_cadang'], 'kode' => $editingCode]);
            if ($st->fetchColumn()) {
                $errors['nama_suku_cadang'] = 'Nama barang sudah terdaftar. Gunakan nama berbeda.';
            }
        }
        if (!$errors && $action === 'tambah') {
            $codes = $db->query('SELECT kode_suku_cadang FROM suku_cadang')->fetchAll(PDO::FETCH_COLUMN);
            $code = kodeSukuCadangBerikutnyaM2($codes);
            $st = $db->prepare('INSERT INTO suku_cadang (kode_suku_cadang, nama_suku_cadang)
                VALUES (:code, :name)');
            $st->execute(['code' => $code, 'name' => $old['nama_suku_cadang']]);
            pesanKilAt('Suku cadang berhasil ditambahkan. Stok cabang belum dicatat.');
            pindah('suku_cadang.php?stok=' . rawurlencode($code) . '#detail-stok');
        }
        if (!$errors && $action === 'ubah') {
            $st = $db->prepare('UPDATE suku_cadang SET nama_suku_cadang = :name
                WHERE kode_suku_cadang = :code');
            $st->execute(['name' => $old['nama_suku_cadang'], 'code' => $editingCode]);
            if ($st->rowCount() === 0) {
                $check = $db->prepare('SELECT 1 FROM suku_cadang WHERE kode_suku_cadang = :code');
                $check->execute(['code'=>$editingCode]);
                if (!$check->fetchColumn()) {
                    throw new InvalidArgumentException('Barang sudah tidak tersedia. Pilih kembali dari daftar.');
                }
            }
            pesanKilAt('Nama suku cadang berhasil diperbarui.');
            pindah('suku_cadang.php');
        }
        if ($errors) {
            $error = 'Periksa kolom yang ditandai. Data yang sudah diketik tetap ada.';
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    } catch (PDOException $e) {
        error_log('[Kalawa spare TEST] ' . $e->getMessage());
        $number = (int) ($e->errorInfo[1] ?? 0);
        $error = match ($number) {
            1451 => 'Tidak bisa dihapus. Barang masih tercatat dalam stok cabang, supplier, atau pekerjaan servis.',
            1062 => 'Kode barang sudah digunakan. Muat ulang halaman dan coba lagi.',
            1142 => 'Akses database belum diizinkan. Periksa GRANT database pengujian.',
            default => 'Perubahan belum dapat disimpan. Periksa log server.',
        };
    } catch (Throwable $e) {
        error_log('[Kalawa spare TEST] ' . $e->getMessage());
        $error = 'Operasi tidak berhasil. Periksa log server.';
    }
}

$edit = null;
$selected = null;
$stockRows = [];
try {
    $editCode = trim((string) ($_GET['ubah'] ?? ''));
    if ($editCode !== '' && kodeSukuCadangValidM2($editCode)) {
        $st = $db->prepare('SELECT kode_suku_cadang, nama_suku_cadang
            FROM suku_cadang WHERE kode_suku_cadang = :code');
        $st->execute(['code'=>$editCode]);
        $edit = $st->fetch() ?: null;
        if (!$edit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'Barang tidak ditemukan. Pilih dari daftar.';
        }
    }
    $query = trim((string) ($_GET['q'] ?? ''));
    if (preg_match_all('/./us', $query) > 70) {
        $query = implode('', array_slice(preg_split('//u', $query, -1, PREG_SPLIT_NO_EMPTY), 0, 70));
    }
    // Agregat persediaan: bukan kuantitas yang otomatis dibuat untuk barang baru.
    $baseSQL = 'SELECT s.kode_suku_cadang, s.nama_suku_cadang,
            r.jumlah_cabang, r.total_stok, r.cabang_kurang
        FROM suku_cadang s
        LEFT JOIN (
            SELECT kode_suku_cadang, COUNT(*) AS jumlah_cabang,
                SUM(jumlah_stok) AS total_stok,
                SUM(CASE WHEN jumlah_stok < stok_minimum THEN 1 ELSE 0 END) AS cabang_kurang
            FROM stok_cabang GROUP BY kode_suku_cadang
        ) r ON r.kode_suku_cadang = s.kode_suku_cadang ';
    $args=[];
    if($query!==''){$baseSQL.=' WHERE s.nama_suku_cadang LIKE :name OR s.kode_suku_cadang LIKE :code';$args=['name'=>'%'.$query.'%','code'=>'%'.$query.'%'];}
    $rows=ksUiList($db,$baseSQL.' ORDER BY s.kode_suku_cadang',$args,'hal_barang');

    $stockCode = trim((string) ($_GET['stok'] ?? ''));
    if ($stockCode !== '' && kodeSukuCadangValidM2($stockCode)) {
        $st = $db->prepare('SELECT kode_suku_cadang, nama_suku_cadang
            FROM suku_cadang WHERE kode_suku_cadang = :code');
        $st->execute(['code'=>$stockCode]);
        $selected = $st->fetch() ?: null;
        if ($selected) {
            $st = $db->prepare('SELECT c.nama_cabang, c.kode_cabang,
                    sc.jumlah_stok, sc.stok_minimum
                FROM stok_cabang sc
                JOIN cabang c ON c.kode_cabang = sc.kode_cabang
                WHERE sc.kode_suku_cadang = :code
                ORDER BY c.kode_cabang');
            $st->execute(['code'=>$stockCode]);
            $stockRows = $st->fetchAll();
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'Barang yang dipilih tidak ditemukan. Pilih dari daftar.';
        }
    }
} catch (Throwable $e) {
    error_log('[Kalawa spare TEST READ] ' . $e->getMessage());
    http_response_code(503);
    exit('Daftar suku cadang tidak dapat dibuka. Periksa koneksi dan hak baca database uji.');
}

$isEditing = ($action === 'ubah') || ($edit !== null && $_SERVER['REQUEST_METHOD'] !== 'POST');
$form = $old ?: ($isEditing && $edit ? $edit : []);
$flash = ambilPesanKilAt();
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $error !== '') $flash = null;
awalHalamanM2('Suku Cadang', $akun);
?>
<style>
.ks-spare{--red:#ae252d;--line:#e5e7eb;--muted:#5d6878}
.ks-spare .head{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin:8px 0 17px}
.ks-spare .head h1{margin:0 0 4px}.ks-spare .sub{color:var(--muted);font-size:.9rem;margin:0;line-height:1.45}
.ks-spare .main-button,.ks-spare .quiet-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;border:1px solid var(--red);background:var(--red);color:#fff;font-weight:650;text-decoration:none}
.ks-spare .quiet-button{background:#fff;color:var(--red);border-color:#e9bdc1}
.ks-spare .main-button:hover,.ks-spare .quiet-button:hover{text-decoration:none;filter:brightness(.97)}
.ks-spare .panel{padding:19px;margin:16px 0}.ks-spare h2{font-size:1.1rem;margin:0 0 8px}
.ks-spare .rowtop{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px}
.ks-spare .search{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:0}
.ks-spare .search input{width:230px;max-width:100%;margin:0}.ks-spare .search button{margin:0}
.ks-spare .tbl{overflow:auto}.ks-spare table{min-width:640px}.ks-spare td{vertical-align:middle}
.ks-spare .code{font-size:.85rem;color:#596475;font-weight:650}
.ks-spare .actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.ks-spare .actions form{margin:0}.ks-spare .actions button{background:#fff;color:var(--red);border-color:#eebfc3;font-size:.84rem;padding:6px 9px}
.ks-spare .link{font-weight:650;font-size:.85rem;padding:6px;color:var(--red)}
.ks-spare .status{display:inline-block;border-radius:100px;padding:4px 8px;font-size:.78rem;background:#eff3f7;color:#3d5067}
.ks-spare .status.low{background:#fff0ce;color:#805000}.ks-spare .status.none{background:#f2f3f5;color:#5d6878}
.ks-spare .selected{border-color:#d7c6a2;background:#fffef9;scroll-margin-top:18px}
.ks-spare .stock-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
.ks-spare .stock-empty{padding:12px 0;color:var(--muted)}
.ks-spare .formbox{border:1px solid var(--line);background:#fff;border-radius:14px;margin:16px 0;overflow:hidden;scroll-margin-top:20px}
.ks-spare .formbox summary{display:flex;align-items:center;justify-content:space-between;list-style:none;cursor:pointer;padding:17px 19px;font-weight:750}
.ks-spare .formbox summary::-webkit-details-marker{display:none}.ks-spare .formbox summary:after{content:'+';color:var(--red);font-size:1.3rem}
.ks-spare .formbox[open] summary{border-bottom:1px solid var(--line)}.ks-spare .formbox[open] summary:after{content:'−'}
.ks-spare .inner{padding:19px}.ks-spare .inner input{max-width:650px}.ks-spare .hint{font-size:.82rem;color:var(--muted);margin:-4px 0 12px}
.ks-spare .field-error{display:block;color:#a21925;font-weight:650;margin:-7px 0 15px;font-size:.85rem}
.ks-spare [aria-invalid="true"]{border-color:#b4232d;background:#fff7f7}.ks-spare .empty{text-align:center;padding:20px;color:var(--muted)}
.ks-spare .notice{font-size:.81rem;color:var(--muted);margin:10px 0 0}
@media(max-width:680px){.ks-spare .panel{padding:14px}.ks-spare .search input{width:100%}.ks-spare .search{width:100%}.ks-spare .search button{flex:1}.ks-spare .head .main-button{width:100%}.ks-spare .rowtop{display:block}.ks-spare .rowtop .search{margin-top:12px}}
</style>
<div class="ks-spare">
  <div class="head">
    <div><h1>Suku cadang</h1><p class="sub">Daftar barang untuk pekerjaan servis.</p></div>
    <a href="#form-spare" class="main-button" id="open-spare">+ Tambah barang</a>
  </div>
  <?php if ($flash): ?><div class="alert" id="spare-flash" role="status"><?= amanHtml($flash['pesan']) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert bad" role="alert"><strong><?= amanHtml($error) ?></strong>
    <?php foreach ($errors as $field=>$message): ?><div style="margin-top:6px"><a href="#<?= amanHtml($field) ?>"><?= amanHtml($message) ?></a></div><?php endforeach; ?>
  </div><?php endif; ?>

  <?php if ($selected): ?>
  <section class="panel selected" id="detail-stok">
    <div class="stock-head">
      <div><h2>Stok per cabang — <?= amanHtml($selected['nama_suku_cadang']) ?></h2>
        <p class="sub">Kode <?= amanHtml($selected['kode_suku_cadang']) ?> · Hanya untuk dilihat</p></div>
      <a class="quiet-button" href="suku_cadang.php">Tutup</a>
    </div>
    <?php if ($stockRows): ?>
      <div class="tbl"><table><thead><tr><th>Cabang</th><th>Stok saat ini</th><th>Batas minimum</th><th>Keterangan</th></tr></thead><tbody>
        <?php foreach ($stockRows as $r): ?>
        <tr><td><?= amanHtml($r['nama_cabang']) ?></td>
          <td><strong><?= number_format((int)$r['jumlah_stok'],0,',','.') ?></strong></td>
          <td><?= number_format((int)$r['stok_minimum'],0,',','.') ?></td>
          <td><?php if ((int)$r['jumlah_stok'] < (int)$r['stok_minimum']): ?><span class="status low">Perlu ditambah</span>
            <?php else: ?><span class="status">Cukup</span><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?>
      <p class="stock-empty">Stok barang ini belum dicatat di cabang mana pun. Belum berarti stok fisik = 0.</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="panel" id="daftar-spare">
    <div class="rowtop"><div><h2>Daftar suku cadang</h2><p class="sub">Klik Lihat stok untuk memeriksa persediaan setiap cabang.</p></div>
      <form class="search" method="get" action="suku_cadang.php" role="search">
        <input name="q" aria-label="Cari barang berdasarkan nama atau kode" maxlength="70"
          placeholder="Cari nama atau kode" value="<?= amanHtml($query) ?>">
        <button type="submit">Cari</button>
        <?php if ($query !== ''): ?><a href="suku_cadang.php" class="quiet-button">Reset</a><?php endif; ?>
      </form>
    </div>
    <div class="tbl" role="region" tabindex="0" aria-label="Tabel suku cadang"><table>
      <thead><tr><th>Nama barang</th><th>Kode</th><th>Stok semua cabang</th><th>Aksi</th></tr></thead><tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= amanHtml($r['nama_suku_cadang']) ?></strong></td>
          <td class="code"><?= amanHtml($r['kode_suku_cadang']) ?></td>
          <td>
            <?php if ($r['jumlah_cabang'] === null): ?>
              <span class="status none">Belum dicatat</span>
            <?php else: ?>
              <strong><?= number_format((int)$r['total_stok'],0,',','.') ?></strong>
              <span class="notice">(<?= (int)$r['jumlah_cabang'] ?> cabang)</span>
              <?php if ((int)$r['cabang_kurang'] > 0): ?><br><span class="status low">Ada stok rendah</span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td><div class="actions">
            <a class="link" href="suku_cadang.php?stok=<?= rawurlencode($r['kode_suku_cadang']) ?>#detail-stok">Lihat stok</a>
            <a class="link" href="suku_cadang.php?ubah=<?= rawurlencode($r['kode_suku_cadang']) ?>#form-spare">Ubah</a>
            <form method="post" action="suku_cadang.php" onsubmit="return confirm('Hapus suku cadang ini? Jika masih tercatat di stok, supplier, atau transaksi, sistem akan menolak.');">
              <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="kode_suku_cadang" value="<?= amanHtml($r['kode_suku_cadang']) ?>">
              <button type="submit">Hapus</button>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4" class="empty">Barang tidak ditemukan. Coba kata kunci lain.</td></tr><?php endif; ?>
      </tbody></table></div>
<?php ksUiPager('hal_barang'); ?>
    <p class="notice">Stok adalah total dari cabang yang sudah memiliki catatan persediaan.</p>
  </section>

  <details class="formbox" id="form-spare" <?= ($isEditing || ($error !== '' && $action !== 'hapus') || $action === 'tambah') ? 'open' : '' ?>>
    <summary><?= $isEditing ? 'Ubah nama barang' : 'Tambah suku cadang baru' ?></summary>
    <div class="inner"><form method="post" id="spare-form" action="suku_cadang.php<?= $isEditing ? '?ubah=' . rawurlencode($editingCode ?: (string)($edit['kode_suku_cadang'] ?? '')) : '' ?>">
      <input type="hidden" name="csrf" value="<?= amanHtml(tokenCsrf()) ?>">
      <input type="hidden" name="aksi" value="<?= $isEditing ? 'ubah' : 'tambah' ?>">
      <?php if ($isEditing): ?><input type="hidden" name="kode_suku_cadang" value="<?= amanHtml($editingCode ?: ($edit['kode_suku_cadang'] ?? '')) ?>"><?php endif; ?>
      <?php if ($isEditing): ?><p class="sub" style="margin-bottom:13px">Kode barang: <strong><?= amanHtml($editingCode ?: ($edit['kode_suku_cadang'] ?? '')) ?></strong> (tidak dapat diubah)</p><?php endif; ?>
      <label for="nama_suku_cadang">Nama suku cadang *</label>
      <input type="text" id="nama_suku_cadang" name="nama_suku_cadang" required minlength="3" maxlength="150"
        placeholder="Contoh: Kampas rem belakang" value="<?= amanHtml($form['nama_suku_cadang'] ?? '') ?>"
        aria-describedby="spare-hint<?= isset($errors['nama_suku_cadang']) ? ' spare-error' : '' ?>"
        <?= isset($errors['nama_suku_cadang']) ? 'aria-invalid="true" data-spare-error="1"' : '' ?>>
      <p class="hint" id="spare-hint">3–150 karakter. Kode otomatis; stok setiap cabang dicatat terpisah.</p>
      <?php if (isset($errors['nama_suku_cadang'])): ?><small class="field-error" id="spare-error"><?= amanHtml($errors['nama_suku_cadang']) ?></small><?php endif; ?>
      <div class="row"><button type="submit"><?= $isEditing ? 'Simpan perubahan' : 'Simpan suku cadang' ?></button>
        <?php if ($isEditing): ?><a class="quiet-button" href="suku_cadang.php">Batal ubah</a><?php endif; ?></div>
    </form></div>
  </details>
</div>
<script>
(() => {
  const details = document.getElementById('form-spare');
  document.getElementById('open-spare')?.addEventListener('click', () => {
    details.open = true;
    setTimeout(() => document.getElementById('nama_suku_cadang')?.focus({preventScroll:true}), 0);
  });
  if (location.hash === '#form-spare' && details) details.open = true;
  const invalid = document.querySelector('[data-spare-error="1"]');
  if (invalid) {
    details.open = true;
    window.addEventListener('load', () => {
      invalid.scrollIntoView({block:'center'});
      invalid.focus({preventScroll:true});
    }, {once:true});
  }
  const success = document.getElementById('spare-flash');
  document.getElementById('spare-form')?.addEventListener('input', () => {
    if (success) success.hidden = true;
  }, {once:true});
})();
</script>
<?php akhirHalamanM2(); ?>
