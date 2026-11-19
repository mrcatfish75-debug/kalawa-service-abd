<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/korporat_m2.php';

$akun = akunLoginWajib();
if (!bolehMengelolaPelanggan($akun)) {
    http_response_code(403);
    exit('403 — Kontrak korporat dikelola oleh Owner.');
}
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
if (!preg_match('/\A[A-Za-z0-9._-]{3,15}\z/D', $id)) {
    http_response_code(404);
    exit('Pilih perusahaan dari daftar pelanggan korporat.');
}
$db = koneksiKalawaM2();
$halaman = 'kontrak_korporat.php?id=' . urlencode($id);
$pesan = '';
$field = '';
$input = [];
try {
    $stmt = $db->prepare('SELECT p.nama_pelanggan FROM pelanggan p
        JOIN pelanggan_korporat k ON k.id_pelanggan=p.id_pelanggan WHERE p.id_pelanggan=:id');
    $stmt->execute(['id'=>$id]);
    $perusahaan = $stmt->fetchColumn();
    if ($perusahaan === false) {
        http_response_code(404);
        exit('Pelanggan korporat tidak ditemukan.');
    }
    $hari = (string) $db->query('SELECT CURDATE()')->fetchColumn();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        validasiCsrf();
        foreach (['berlaku_mulai', 'berlaku_sampai', 'plafon_kredit'] as $key) {
            $input[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        }
        try {
            korporatTambahKontrak($db, $id, $input['berlaku_mulai'], $input['berlaku_sampai'], $input['plafon_kredit']);
            pesanKilAt('Kontrak korporat berhasil ditambahkan.');
            pindah($halaman);
        } catch (KorporatDitolak $e) {
            http_response_code(422);
            $pesan = $e->getMessage();
            $field = $e->field;
        } catch (PDOException $e) {
            error_log('[Kalawa kontrak POST] ' . $e->getMessage());
            http_response_code(409);
            $pesan = 'Kontrak belum dapat disimpan. Muat ulang daftar dan periksa periode sebelum mencoba lagi.';
        }
    }
    $stmt = $db->prepare('SELECT id_kontrak,berlaku_mulai,berlaku_sampai,plafon_kredit FROM kontrak_korporat
        WHERE id_pelanggan=:id ORDER BY berlaku_mulai DESC');
    $stmt->execute(['id'=>$id]);
    $kontrak = $stmt->fetchAll();
    $saldo = korporatSaldo($db, $id);
} catch (PDOException $e) {
    error_log('[Kalawa kontrak READ] ' . $e->getMessage());
    http_response_code(503);
    exit('Data kontrak belum dapat dibaca. Hubungi pengelola.');
}

function rupiahKontrak(string $value): string
{
    [$unit, $desimal] = array_pad(explode('.', $value, 2), 2, '00');
    return 'Rp' . preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', '.', $unit) . ',' . str_pad($desimal, 2, '0');
}

$flash = ambilPesanKilAt();
$input += ['berlaku_mulai'=>$hari, 'berlaku_sampai'=>(new DateTimeImmutable($hari))->modify('last day of this month')->format('Y-m-d'), 'plafon_kredit'=>''];
awalHalamanM2('Kontrak Korporat', $akun);
?>
<div class="row space">
  <div><h1>Kontrak korporat</h1><p class="muted"><?=amanHtml($perusahaan)?> · <?=amanHtml($id)?></p></div>
  <a class="btn light" href="pelanggan.php?tipe=korporat">Kembali ke pelanggan korporat</a>
</div>
<?php if ($pesan): ?><div class="alert bad" role="alert"><?=amanHtml($pesan)?></div>
<?php elseif ($flash): ?><div class="alert" role="status"><?=amanHtml($flash['pesan'])?></div><?php endif; ?>
<section class="panel">
  <h2>Tambah kontrak</h2>
  <p class="muted">Tanggal mulai dan akhir termasuk masa berlaku. Plafon berlaku untuk saldo perusahaan di semua cabang.</p>
  <form method="post" action="<?=amanHtml($halaman)?>">
    <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
    <div class="grid"><div>
      <label for="berlaku_mulai">Tanggal mulai *</label>
      <input type="date" id="berlaku_mulai" name="berlaku_mulai" required value="<?=amanHtml($input['berlaku_mulai'])?>" aria-invalid="<?=$field==='berlaku_mulai'?'true':'false'?>">
    </div><div>
      <label for="berlaku_sampai">Tanggal akhir *</label>
      <input type="date" id="berlaku_sampai" name="berlaku_sampai" required value="<?=amanHtml($input['berlaku_sampai'])?>" aria-invalid="<?=$field==='berlaku_sampai'?'true':'false'?>">
    </div></div>
    <label for="plafon_kredit">Plafon kredit (Rp) *</label>
    <input id="plafon_kredit" name="plafon_kredit" inputmode="decimal" maxlength="15" required placeholder="Contoh 5000000" value="<?=amanHtml($input['plafon_kredit'])?>" aria-invalid="<?=$field==='plafon_kredit'?'true':'false'?>">
    <p class="muted">Isi nominal tanpa pemisah ribuan. Angka desimal maksimal dua digit.</p>
    <button type="submit">Simpan kontrak</button>
  </form>
</section>
<section class="panel">
  <div class="row space"><h2>Daftar kontrak</h2><p>Plafon terpakai <strong><?=amanHtml(rupiahKontrak($saldo))?></strong></p></div>
  <div class="tablewrap"><table><thead><tr><th>Kontrak</th><th>Mulai</th><th>Akhir</th><th>Plafon</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($kontrak as $row): ?>
    <tr>
      <td>#<?=amanHtml($row['id_kontrak'])?></td><td><?=amanHtml($row['berlaku_mulai'])?></td><td><?=amanHtml($row['berlaku_sampai'])?></td>
      <td><?=amanHtml(rupiahKontrak($row['plafon_kredit']))?></td>
      <td><?=$row['berlaku_mulai'] > $hari ? 'Terjadwal' : ($row['berlaku_sampai'] < $hari ? 'Berakhir' : 'Aktif')?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$kontrak): ?><tr><td colspan="5">Belum ada kontrak.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?php akhirHalamanM2(); ?>

