<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_stok_m2.php';
require_once __DIR__ . '/../app/operasional_m2.php';

$akun = akunLoginWajib();
try {
    $stockScope = opsCabang($akun, ['rl_owner_ops','rl_inventory_ops']);
} catch (OperasionalDitolak $e) {
    http_response_code(403);
    exit('403 — Anda tidak memiliki izin mengelola stok bengkel.');
}
$db = koneksiKalawaM2(); // Guard wajib DB kalawa_service_f1_test_fixed.
$error = '';
$old = [];
// Konflik snapshot: wajib muat data terbaru, jangan kirim ulang formulir lama.
$konflikFormStok = false;
$selectedCabang = trim((string) ($_GET['cabang_pilih'] ?? ''));
$selectedBarang = trim((string) ($_GET['barang_pilih'] ?? ''));
if ($stockScope !== null && $selectedCabang !== '' && $selectedCabang !== $stockScope) {
    http_response_code(404);
    exit('Persediaan yang diminta tidak ditemukan.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validasiCsrf();
    $selectedCabang = trim((string) ($_POST['kode_cabang'] ?? ''));
    $selectedBarang = trim((string) ($_POST['kode_suku_cadang'] ?? ''));
    if ($stockScope !== null && $selectedCabang !== $stockScope) {
        http_response_code(403);
        exit('Perubahan stok berada di luar cabang penempatan akun.');
    }
    foreach (['aksi','jumlah','minimum','catatan','expected_present','expected_stok','expected_min','id_permintaan'] as $key) {
        $old[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    try {
        if (!kodeCabangStokValid($selectedCabang) || !kodeBarangStokValid($selectedBarang)) {
            throw new InvalidArgumentException('Pilih cabang dan barang dari daftar yang tersedia.');
        }
        $nonce = $old['id_permintaan'];
        if (!preg_match('/\A[a-f0-9]{32}\z/D', $nonce)) {
            throw new InvalidArgumentException('Permintaan sudah tidak valid. Buka ulang form.');
        }
        $expected = $old['expected_present'];
        if (!in_array($expected, ['0','1'], true)) {
            throw new InvalidArgumentException('Data stok lama tidak lengkap. Buka ulang form.');
        }
        $catatan = validasiCatatanStok($old['catatan']);
        $db->beginTransaction();
        // Periksa barang tanpa mengunci master: akun website hanya perlu SELECT.
        // Rekam stok lama dikunci FOR UPDATE; stok awal serentak dijaga PRIMARY KEY.
        $lk = $db->prepare('SELECT kode_suku_cadang FROM suku_cadang WHERE kode_suku_cadang=:kode');
        $lk->execute(['kode'=>$selectedBarang]);
        if (!$lk->fetchColumn()) {
            throw new InvalidArgumentException('Barang sudah tidak tersedia. Muat ulang daftar.');
        }
        $st = $db->prepare('SELECT kode_cabang FROM cabang WHERE kode_cabang=:cabang');
        $st->execute(['cabang'=>$selectedCabang]);
        if (!$st->fetchColumn()) {
            throw new InvalidArgumentException('Cabang tidak ditemukan. Pilih kembali.');
        }
        $st = $db->prepare('SELECT jumlah_stok, stok_minimum FROM stok_cabang
                            WHERE kode_cabang=:cabang AND kode_suku_cadang=:barang FOR UPDATE');
        $st->execute(['cabang'=>$selectedCabang,'barang'=>$selectedBarang]);
        $current = $st->fetch() ?: null;
        if (($current === null ? '0' : '1') !== $expected) {
            $konflikFormStok = true;
            throw new InvalidArgumentException('Stok sudah berubah sejak form dibuka. Muat ulang dan periksa data terbaru.');
        }
        if ($current !== null) {
            if ((string) $current['jumlah_stok'] !== $old['expected_stok'] ||
                (string) $current['stok_minimum'] !== $old['expected_min']) {
                $konflikFormStok = true;
                throw new InvalidArgumentException('Stok sudah berubah sejak form dibuka. Muat ulang sebelum menyimpan.');
            }
        }
        $values = hasilPerubahanStok($old['aksi'], $current, $old['jumlah'], $old['minimum']);
        if ($current === null) {
            $write = $db->prepare('INSERT INTO stok_cabang
                (kode_cabang,kode_suku_cadang,jumlah_stok,stok_minimum)
                VALUES (:cabang,:barang,:stok,:minimum)');
            $write->execute(['cabang'=>$selectedCabang, 'barang'=>$selectedBarang,
                'stok'=>$values['sesudah'], 'minimum'=>$values['minimum_sesudah']]);
        } else {
            $write = $db->prepare('UPDATE stok_cabang SET jumlah_stok=:stok, stok_minimum=:minimum
                WHERE kode_cabang=:cabang AND kode_suku_cadang=:barang');
            $write->execute(['stok'=>$values['sesudah'], 'minimum'=>$values['minimum_sesudah'],
                'cabang'=>$selectedCabang, 'barang'=>$selectedBarang]);
        }
        $types = ['awal'=>'STOK_AWAL','masuk'=>'BARANG_MASUK',
                  'koreksi'=>'KOREKSI','minimum'=>'UBAH_MINIMUM'];
        $log = $db->prepare('INSERT INTO mutasi_stok_manual
          (id_permintaan,kode_cabang,kode_suku_cadang,id_akun,jenis_mutasi,
           stok_sebelum,stok_sesudah,minimum_sebelum,minimum_sesudah,catatan)
          VALUES (:nonce,:cabang,:barang,:akun,:jenis,:sebelum,:sesudah,:min_lama,:min_baru,:catatan)');
        $log->execute(['nonce'=>$nonce,'cabang'=>$selectedCabang,'barang'=>$selectedBarang,
            'akun'=>$akun['id_akun'],'jenis'=>$types[$old['aksi']],
            'sebelum'=>$values['sebelum'],'sesudah'=>$values['sesudah'],
            'min_lama'=>$values['minimum_sebelum'],'min_baru'=>$values['minimum_sesudah'],
            'catatan'=>$catatan]);
        $db->commit();
        $messages = ['awal'=>'Stok awal berhasil dicatat.', 'masuk'=>'Barang masuk berhasil dicatat.',
            'koreksi'=>'Koreksi stok berhasil disimpan.', 'minimum'=>'Batas minimum berhasil diperbarui.'];
        pesanKilAt($messages[$old['aksi']]);
        // Sukses: form ditutup, cukup notifikasi, seperti Supplier R01.1.
        pindah('stok.php?cabang=' . rawurlencode($selectedCabang));
    } catch (InvalidArgumentException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa stok uji] ' . $e->getMessage());
        $number = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($number, [1062, 1205, 1213], true)) {
            $konflikFormStok = true;
        }
        $error = match ($number) {
            1062 => 'Perubahan serupa sudah diproses atau stok baru dicatat pengguna lain. Muat ulang untuk melihat kondisi terkini.',
            1142 => 'Izin database belum lengkap. Periksa GRANT stok pada database uji.',
            1451,1452 => 'Data referensi tidak sesuai. Pilih ulang cabang dan barang.',
            1213,1205 => 'Stok sedang diperbarui pengguna lain. Periksa stok terbaru lalu ulangi.',
            default => 'Stok belum berhasil disimpan. Hubungi pengelola sistem bila masalah berulang.'
        };
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa stok uji] ' . $e->getMessage());
        $error = 'Operasi belum berhasil. Hubungi pengelola sistem.';
    }
}

$selected = null;
$history = [];
$rows = [];
$total = 0;
$filterCabang = trim((string) ($_GET['cabang'] ?? ''));
if ($stockScope !== null) {
    if ($filterCabang !== '' && $filterCabang !== $stockScope) {
        http_response_code(403);
        exit('Cabang persediaan berada di luar penempatan akun.');
    }
    $filterCabang = $stockScope;
}
$filterSearch = trim((string) ($_GET['q'] ?? ''));
$filterStatus = trim((string) ($_GET['status'] ?? 'semua'));
if (!in_array($filterStatus, ['semua','habis','rendah','cukup','belum'], true)) $filterStatus='semua';
if (strlen($filterSearch) > 70) $filterSearch = substr($filterSearch, 0, 70);
if ($filterCabang !== '' && !kodeCabangStokValid($filterCabang)) $filterCabang = '';
try {
    $cabangs = opsDaftarCabang($db,$stockScope);
    $knownCabang = array_column($cabangs, 'kode_cabang');
    if ($filterCabang !== '' && !in_array($filterCabang,$knownCabang,true)) $filterCabang = '';
    if (kodeCabangStokValid($selectedCabang) && kodeBarangStokValid($selectedBarang)) {
        $sql = 'SELECT c.kode_cabang,c.nama_cabang,p.kode_suku_cadang,p.nama_suku_cadang,
                       sc.jumlah_stok,sc.stok_minimum
                FROM cabang c CROSS JOIN suku_cadang p
                LEFT JOIN stok_cabang sc ON sc.kode_cabang=c.kode_cabang AND sc.kode_suku_cadang=p.kode_suku_cadang
                WHERE c.kode_cabang=:cabang AND p.kode_suku_cadang=:barang';
        $stmt=$db->prepare($sql);
        $stmt->execute(['cabang'=>$selectedCabang,'barang'=>$selectedBarang]);
        $selected=$stmt->fetch() ?: null;
        if (!$selected) $error = 'Cabang atau barang tidak ditemukan. Pilih dari daftar.';
        else {
            $st=$db->prepare('SELECT m.jenis_mutasi,m.stok_sebelum,m.stok_sesudah,
                m.minimum_sebelum,m.minimum_sesudah,m.catatan,m.dicatat_pada,a.username
                FROM mutasi_stok_manual m JOIN akun_aplikasi a ON a.id_akun=m.id_akun
                WHERE m.kode_cabang=:cabang AND m.kode_suku_cadang=:barang
                ORDER BY m.id_mutasi DESC LIMIT 10');
            $st->execute(['cabang'=>$selectedCabang,'barang'=>$selectedBarang]);
            $history=$st->fetchAll();
        }
    }
    $where=[]; $params=[];
    if ($filterCabang !== '') {
        $where[]='c.kode_cabang=:cabang'; $params['cabang']=$filterCabang;
    }
    if ($filterSearch !== '') {
        $where[]='(p.nama_suku_cadang LIKE :q1 OR p.kode_suku_cadang LIKE :q2)';
        $params['q1']='%'.$filterSearch.'%'; $params['q2']='%'.$filterSearch.'%';
    }
    if ($filterStatus === 'belum') $where[]='sc.kode_cabang IS NULL';
    if ($filterStatus === 'habis') $where[]='sc.kode_cabang IS NOT NULL AND sc.jumlah_stok=0';
    if ($filterStatus === 'rendah') $where[]='sc.kode_cabang IS NOT NULL AND sc.jumlah_stok>0 AND sc.jumlah_stok<=sc.stok_minimum';
    if ($filterStatus === 'cukup') $where[]='sc.kode_cabang IS NOT NULL AND sc.jumlah_stok>sc.stok_minimum';
    $from = ' FROM cabang c CROSS JOIN suku_cadang p
        LEFT JOIN stok_cabang sc ON sc.kode_cabang=c.kode_cabang AND sc.kode_suku_cadang=p.kode_suku_cadang';
    $suffix=$where ? ' WHERE '.implode(' AND ',$where) : '';
    $count=$db->prepare('SELECT COUNT(*)'.$from.$suffix);
    $count->execute($params); $total=(int)$count->fetchColumn();
    $rows=ksUiList($db,'SELECT c.kode_cabang,c.nama_cabang,p.kode_suku_cadang,p.nama_suku_cadang,
        sc.jumlah_stok,sc.stok_minimum'.$from.$suffix.' ORDER BY c.nama_cabang,p.nama_suku_cadang',$params,'hal_stok');
} catch (Throwable $e) {
    error_log('[Kalawa stok uji: read] ' . $e->getMessage());
    http_response_code(503);
    exit('Data stok belum dapat dibuka. Periksa migrasi 008 dan hak akses akun uji.');
}

$flash=ambilPesanKilAt();
if (($_SERVER['REQUEST_METHOD']??'GET') === 'POST' || $error!=='') $flash=null;
$missing=$selected && $selected['jumlah_stok']===null;
// KRITIS: saat POST gagal, jangan diam-diam mengganti versi snapshot tersembunyi
// dengan stok terbaru; jika tidak, kirim ulang dapat mengabaikan konflik lama.
$formAfterPost = ($_SERVER['REQUEST_METHOD']??'GET') === 'POST';
$snapshotPresent = $formAfterPost ? ($old['expected_present'] ?? '') : ($selected === null ? '' : ($missing?'0':'1'));
$snapshotStok = $formAfterPost ? ($old['expected_stok'] ?? '') : (($selected === null || $missing)?'':(string)(int)$selected['jumlah_stok']);
$snapshotMinimum = $formAfterPost ? ($old['expected_min'] ?? '') : (($selected === null || $missing)?'':(string)(int)$selected['stok_minimum']);
$mode=$old['aksi']??($missing?'awal':'masuk');
if (!in_array($mode,['awal','masuk','koreksi','minimum'],true)) $mode=$missing?'awal':'masuk';
// Kesalahan tetap mempertahankan data formulir dan diarahkan ke kolom terkait.
$errorField = '';
if ($error !== '') {
    if (stripos($error, 'catatan') !== false) $errorField = 'catatan';
    elseif (stripos($error, 'minimum') !== false || stripos($error, 'batas') !== false) $errorField = 'minimum';
    elseif (stripos($error, 'jumlah') !== false || stripos($error, 'fisik') !== false) $errorField = 'jumlah';
    else $errorField = 'aksi';
}
$labels=['STOK_AWAL'=>'Stok awal','BARANG_MASUK'=>'Barang masuk',
    'KOREKSI'=>'Koreksi','UBAH_MINIMUM'=>'Batas minimum'];
awalHalamanM2('Stok Bengkel',$akun);
?>
<div class="ks-stock">
  <header class="ks-stock-top">
    <div><h1>Stok Bengkel</h1><p class="sub">Periksa dan catat persediaan sesuai cabang.</p></div>
    <span class="ks-ui-scope"><?=ksIconR02('boxes')?><?=amanHtml($stockScope===null?'Seluruh cabang':($cabangs[0]['nama_cabang']??$stockScope))?></span>
  </header>
  <?php if($flash): ?><div class="alert" role="status"><?=amanHtml($flash['pesan'])?></div><?php endif; ?>
  <?php if($error): ?><div class="alert bad" id="stok-error" data-error-field="<?=amanHtml($errorField)?>" role="alert"><?=amanHtml($error)?></div><?php endif; ?>

  <?php if($selected): ?>
  <section class="panel ks-stock-edit" id="atur-stok">
    <header class="ks-stock-heading">
      <div><h2><?=amanHtml($selected['nama_suku_cadang'])?></h2>
        <p class="sub"><?=amanHtml($selected['nama_cabang'])?> · <?=amanHtml($selected['kode_suku_cadang'])?></p>
      </div><a class="ks-light" href="stok.php?cabang=<?=rawurlencode($selected['kode_cabang'])?>">Tutup</a>
    </header>
    <div class="ks-stock-summary">
      <div><small>Stok saat ini</small><strong><?= $missing?'Belum dicatat':number_format((int)$selected['jumlah_stok'],0,',','.') ?></strong></div>
      <div><small>Batas minimum</small><strong><?= $missing?'—':number_format((int)$selected['stok_minimum'],0,',','.') ?></strong></div>
    </div>
    <form method="post" action="stok.php#atur-stok" id="form-stok" novalidate>
      <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
      <input type="hidden" name="kode_cabang" value="<?=amanHtml($selected['kode_cabang'])?>">
      <input type="hidden" name="kode_suku_cadang" value="<?=amanHtml($selected['kode_suku_cadang'])?>">
      <input type="hidden" name="expected_present" value="<?=amanHtml($snapshotPresent)?>">
      <input type="hidden" name="expected_stok" value="<?=amanHtml($snapshotStok)?>">
      <input type="hidden" name="expected_min" value="<?=amanHtml($snapshotMinimum)?>">
      <input type="hidden" name="id_permintaan" value="<?=amanHtml($old['id_permintaan']??bin2hex(random_bytes(16)))?>">
      <div class="ks-stock-grid">
        <div class="ks-stock-field"><label for="aksi">Tindakan *</label><select name="aksi" id="aksi">
          <?php if($missing): ?><option value="awal">Catat stok awal</option><?php else: ?>
          <option value="masuk"<?=$mode==='masuk'?' selected':''?>>Barang masuk</option>
          <option value="koreksi"<?=$mode==='koreksi'?' selected':''?>>Koreksi stok</option>
          <option value="minimum"<?=$mode==='minimum'?' selected':''?>>Ubah batas minimum</option>
          <?php endif; ?>
        </select></div>
        <div class="ks-stock-field" id="box-jumlah"><label for="jumlah" id="label-jumlah">Jumlah barang masuk *</label>
          <input type="text" inputmode="numeric" name="jumlah" id="jumlah" maxlength="10" pattern="[0-9]+" aria-invalid="<?=$errorField==='jumlah'?'true':'false'?>" placeholder="Contoh: 10" value="<?=amanHtml($old['jumlah']??'')?>">
          <small id="hint-jumlah">Masukkan jumlah yang baru datang, bukan stok total.</small></div>
        <div class="ks-stock-field" id="box-minimum"><label for="minimum">Batas minimum *</label>
          <input type="text" inputmode="numeric" name="minimum" id="minimum" maxlength="10" pattern="[0-9]+" aria-invalid="<?=$errorField==='minimum'?'true':'false'?>" placeholder="Contoh: 5" value="<?=amanHtml($old['minimum']??'')?>">
          <small>Jika stok kurang atau sama dengan batas ini, sistem menandai stok rendah.</small></div>
        <div class="ks-stock-field full"><label for="catatan">Catatan perubahan *</label>
          <input type="text" name="catatan" id="catatan" maxlength="255" aria-invalid="<?=$errorField==='catatan'?'true':'false'?>" placeholder="Contoh: Kiriman supplier diterima" value="<?=amanHtml($old['catatan']??'')?>" required>
          <small>5–255 karakter. Isi penyebab atau asal perubahan untuk catatan pemeriksaan.</small></div>
      </div>
      <?php if($konflikFormStok): ?>
      <div class="alert bad" id="stok-conflict-note" role="alert">
        Stok telah berubah atau permintaan serupa sudah diproses. Data sebelumnya dipertahankan untuk pemeriksaan.
        Jangan kirim ulang nilai lama; muat data stok terbaru sebelum menyimpan.
      </div>
      <div class="ks-stock-actions">
        <button type="submit" id="stok-submit" disabled aria-disabled="true">Muat ulang sebelum menyimpan</button>
        <a class="ks-light" href="stok.php?cabang_pilih=<?=rawurlencode($selected['kode_cabang'])?>&amp;barang_pilih=<?=rawurlencode($selected['kode_suku_cadang'])?>#atur-stok">Muat data stok terbaru</a>
      </div>
      <?php else: ?>
      <div class="ks-stock-actions"><button type="submit" id="stok-submit">Simpan perubahan</button><span>Perubahan tercatat atas nama <?=amanHtml($akun['username'])?>.</span></div>
      <?php endif; ?>
    </form>
    <div class="ks-stock-history">
      <h3>Riwayat perubahan manual</h3>
      <?php if(!$history): ?><p class="sub">Belum ada perubahan manual tercatat pada barang dan cabang ini.</p><?php else: ?>
      <div class="ks-stock-scroll"><table><thead><tr><th>Waktu</th><th>Tindakan</th><th>Stok</th><th>Batas minimum</th><th>Petugas</th><th>Catatan</th></tr></thead><tbody>
      <?php foreach($history as $h): ?><tr>
        <td><?=amanHtml(ksUiTanggal($h['dicatat_pada'],true))?></td><td><?=amanHtml($labels[$h['jenis_mutasi']]??$h['jenis_mutasi'])?></td>
        <td><?=number_format((int)$h['stok_sebelum'],0,',','.')?> → <?=number_format((int)$h['stok_sesudah'],0,',','.')?></td>
        <td><?=number_format((int)$h['minimum_sebelum'],0,',','.')?> → <?=number_format((int)$h['minimum_sesudah'],0,',','.')?></td>
        <td><?=amanHtml($h['username'])?></td><td><?=amanHtml($h['catatan'])?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
      <p class="sub">Riwayat ini khusus perubahan stok manual melalui modul. Pemakaian work order belum tercakup di sini.</p>
    </div>
  </section>
  <?php endif; ?>

  <section class="panel ks-stock-list">
    <div class="ks-stock-heading"><div><h2>Daftar persediaan</h2><p class="sub"><?=$stockScope===null?'Pilih cabang dan barang untuk memeriksa stok.':'Cari barang, periksa kondisinya, lalu catat perubahan stok.'?></p></div></div>
    <form method="get" class="ks-stock-filters <?=$stockScope!==null?'ks-ui-scoped-filters':''?>" action="stok.php">
      <?php if ($stockScope === null): ?>
      <div><label for="cabang">Cabang</label><select id="cabang" name="cabang">
        <option value="">Semua cabang</option>
        <?php foreach($cabangs as $c): ?><option value="<?=amanHtml($c['kode_cabang'])?>"<?=$filterCabang===$c['kode_cabang']?' selected':''?>><?=amanHtml($c['nama_cabang'])?></option><?php endforeach; ?>
      </select></div>
      <?php else: ?><input type="hidden" name="cabang" value="<?=amanHtml($stockScope)?>"><?php endif; ?>
      <div><label for="q">Barang</label><input id="q" name="q" type="search" value="<?=amanHtml($filterSearch)?>" maxlength="70" placeholder="Nama atau kode barang"></div>
      <div><label for="status">Kondisi</label><select id="status" name="status">
        <?php foreach(['semua'=>'Semua','habis'=>'Stok habis','rendah'=>'Stok rendah','belum'=>'Belum dicatat','cukup'=>'Cukup'] as $k=>$v): ?><option value="<?=$k?>"<?=$filterStatus===$k?' selected':''?>><?=$v?></option><?php endforeach; ?>
      </select></div>
      <div class="filter-action ks-ui-filter-actions"><button type="submit">Tampilkan</button><a class="ks-ui-action" href="stok.php">Reset</a></div>
    </form>
    <p class="ks-stock-count">Menampilkan <?=count($rows)?> dari <?=number_format($total,0,',','.')?> data persediaan.</p>
    <div class="ks-stock-scroll"><table class="ks-ui-cards"><caption class="ks-ui-sr-only">Daftar stok, batas minimum, dan kondisi barang sesuai filter</caption><thead><tr><th scope="col">Barang</th><?php if($stockScope===null): ?><th scope="col">Cabang</th><?php endif; ?><th scope="col" class="ks-ui-number">Jumlah</th><th scope="col" class="ks-ui-number">Minimum</th><th scope="col">Kondisi</th><th scope="col">Aksi</th></tr></thead><tbody>
    <?php foreach($rows as $r):
      $unregistered=$r['jumlah_stok']===null;
      $stock=$unregistered?null:(int)$r['jumlah_stok'];
      $min=$unregistered?null:(int)$r['stok_minimum'];
      $statusKey=$unregistered?'belum':($stock===0?'habis':($stock<=$min?'rendah':'cukup'));
      $statusLabel=['belum'=>'Belum dicatat','habis'=>'Habis','rendah'=>'Rendah','cukup'=>'Cukup'][$statusKey];
    ?>
      <tr><td class="ks-ui-identity" data-label="Barang"><strong><?=amanHtml($r['nama_suku_cadang'])?></strong><small><?=amanHtml($r['kode_suku_cadang'])?></small></td>
        <?php if($stockScope===null): ?><td data-label="Cabang"><?=amanHtml($r['nama_cabang'])?></td><?php endif; ?>
        <td class="ks-ui-number" data-label="Jumlah"><?= $unregistered?'—':number_format($stock,0,',','.') ?></td>
        <td class="ks-ui-number" data-label="Minimum"><?= $unregistered?'—':number_format($min,0,',','.') ?></td>
        <td data-label="Kondisi"><span class="ks-stock-badge <?=$statusKey?>"><?=$statusLabel?></span></td>
        <td class="ks-ui-row-action" data-label="Aksi"><a class="ks-ui-action <?=$unregistered?'ks-ui-action--primary':''?>" aria-label="<?=amanHtml(($unregistered?'Catat stok ':'Atur stok ').$r['nama_suku_cadang'])?>" href="stok.php?cabang_pilih=<?=rawurlencode($r['kode_cabang'])?>&amp;barang_pilih=<?=rawurlencode($r['kode_suku_cadang'])?>#atur-stok"><?=$unregistered?'Catat stok':'Atur stok'?></a></td></tr>
    <?php endforeach; ?>
    <?php if(!$rows): ?><tr class="ks-ui-empty"><td colspan="<?=$stockScope===null?6:5?>">Tidak ada persediaan yang cocok. Coba ubah filter.</td></tr><?php endif; ?>
    </tbody></table></div>
<?php ksUiPager('hal_stok'); ?>

  </section>
</div>
<script>
(()=>{
  const form=document.querySelector('#form-stok');
  if(!form)return;
  const act=form.querySelector('#aksi'), qty=form.querySelector('#jumlah'), min=form.querySelector('#minimum');
  const qtyBox=form.querySelector('#box-jumlah'), minBox=form.querySelector('#box-minimum');
  const qtyLabel=form.querySelector('#label-jumlah'),hint=form.querySelector('#hint-jumlah');
  const modes={awal:['Stok awal *','Masukkan jumlah fisik awal yang dihitung.'],masuk:['Jumlah barang masuk *','Isi jumlah barang yang baru diterima.'],koreksi:['Stok fisik saat ini *','Isi jumlah TOTAL hasil hitung fisik, bukan selisih.'],minimum:['','']};
  function update(){const v=act.value;
    qtyBox.hidden=(v==='minimum');minBox.hidden=!(v==='awal'||v==='minimum');
    qty.required=!qtyBox.hidden; min.required=!minBox.hidden;
    qtyLabel.textContent=modes[v][0];hint.textContent=modes[v][1];
    if(v==='minimum'||v==='awal')min.placeholder='Contoh: 5';
  }
  act.addEventListener('change',()=>{qty.value='';min.value='';update();});
  update();
  const err=document.querySelector('#stok-error');
  if(err){err.scrollIntoView({behavior:'auto',block:'center'});const field=err.dataset.errorField||'aksi'; const active=form.querySelector('#'+field)||act;active.focus({preventScroll:true});}
  form.addEventListener('submit',ev=>{
    // Cegah submit via Enter setelah konflik, bukan hanya lewat tombol disabled.
    if(form.querySelector('#stok-conflict-note')) {ev.preventDefault(); return;}
    for(const el of [qty,min,form.querySelector('#catatan')]){
      if(!el.required||el.closest('[hidden]'))continue;
      if(!el.value.trim()){ev.preventDefault();el.focus();el.setCustomValidity('Kolom ini wajib diisi.');el.reportValidity();el.setCustomValidity('');return;}
    }
    const b=form.querySelector('#stok-submit'); b.disabled=true;b.textContent='Menyimpan...';
  });
})();
</script>
<?php akhirHalamanM2(); ?>
