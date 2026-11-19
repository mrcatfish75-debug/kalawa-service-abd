<?php
declare(strict_types=1);
require __DIR__ . '/../app/layout_m2.php';
require_once __DIR__ . '/../app/validasi_wo_m2.php';
require_once __DIR__ . '/../app/rbac_wo_m2.php';
require_once __DIR__ . '/../app/wo_r01b_m2.php';
require_once __DIR__ . '/../app/wo_r01c_m2.php';
require_once __DIR__ . '/../app/wo_status_m2.php';
require_once __DIR__ . '/../app/operasional_m2.php';

$akun = akunLoginWajib();
if (!ksWOBolehAkses($akun)) {
    http_response_code(403);
    exit('403 — Akun ini tidak memiliki izin mengakses Work Order.');
}
try { $woScope=ksWOCabangTerikat($akun); }
catch (DomainException $e) {
    http_response_code(403);
    exit('403 — Penempatan cabang akun tidak valid. Hubungi pengelola.');
}
$db = koneksiKalawaM2(); // WAJIB guard database uji pada app/database_m2.php
$error = '';
$errors = [];
$old = [];
$createOpen = is_string($_GET['buat'] ?? null) && $_GET['buat'] === '1';
$selectedId = is_string($_GET['lihat'] ?? null) ? trim($_GET['lihat']) : '';
$woBagian = match ($_GET['bagian'] ?? (isset($_GET['ubah_jasa']) ? 'jasa' : 'ringkasan')) {
    'jasa' => 'jasa',
    'mekanik' => 'mekanik',
    'suku_cadang' => 'suku_cadang',
    default => 'ringkasan',
};
$r01bInput = [];
$r01bError = '';
$r01bField = '';
$r01bBlocked = false;
$r01cInput = [];
$r01cError = '';
$r01cField = '';
$r01cBlocked = false;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = is_string($_POST['aksi'] ?? null) ? $_POST['aksi'] : '';

// Token satu-kali per form/sesi; bukan pengganti UNIQUE KEY no_work_order di MariaDB.
// Maksimal 12 form tersimpan agar dua tab berbeda dapat digunakan secara sah.
if (!isset($_SESSION['wo_r01a_tokens']) || !is_array($_SESSION['wo_r01a_tokens'])) {
    $_SESSION['wo_r01a_tokens'] = [];
}
foreach ($_SESSION['wo_r01a_tokens'] as $key => $issued) {
    if (!is_array($issued) || !is_int($issued['time'] ?? null) || $issued['time'] < time() - 3600) unset($_SESSION['wo_r01a_tokens'][$key]);
}
function woTokenBaru(string $hari): string
{
    $nonce = bin2hex(random_bytes(24));
    $_SESSION['wo_r01a_tokens'][$nonce] = ['time'=>time(),'day'=>$hari];
    if (count($_SESSION['wo_r01a_tokens']) > 12) {
        uasort($_SESSION['wo_r01a_tokens'], static fn(array $a,array $b): int => ($a['time'] ?? 0) <=> ($b['time'] ?? 0));
        $_SESSION['wo_r01a_tokens'] = array_slice($_SESSION['wo_r01a_tokens'], -12, null, true);
    }
    return $nonce;
}

if ($method === 'POST' && $action === 'r01d_status') {
    $createOpen = false;
    $woBagian = 'ringkasan';
    [$selectedId, $error] = woStatusTanganiPost($db, $akun, 'work_order.php');
} elseif ($method === 'POST' && $action === 'r01c_suku_cadang_tambah') {
    tokenCsrf();
    validasiCsrf();
    $createOpen = false;
    $woBagian = 'suku_cadang';
    foreach (['no_work_order', 'token_r01c', 'kode_suku_cadang', 'qty', 'harga_satuan'] as $key) {
        $r01cInput[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $selectedId = $r01cInput['no_work_order'];
    try {
        woR01cTerapkan($db, $selectedId, $woScope, $r01cInput);
        pesanKilAt('Suku cadang berhasil dicatat dan stok cabang sudah berkurang.');
        pindah('work_order.php?lihat=' . rawurlencode($selectedId) . '&bagian=suku_cadang');
    } catch (WoR01bDitolak $e) {
        $status = $e->getCode();
        http_response_code($status);
        $r01cError = $e->getMessage();
        $r01cField = $e->field;
        $r01cBlocked = $status !== 422;
        if ($status === 403 || $status === 400) {
            $selectedId = '';
        }
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        error_log('[Kalawa WO R01C DB] ' . $e->getMessage());
        $r01cError = match ($code) {
            1142, 1143 => 'Pemakaian suku cadang belum tersimpan. Hubungi pengelola untuk memeriksa izin transaksi stok.',
            1449 => 'Akun pemilik trigger stok tidak tersedia. Hubungi pengelola.',
            1205, 1213, 1452, 1644 => 'Stok atau data WO sedang berubah. Muat ulang rincian sebelum mencoba lagi.',
            default => 'Pemakaian suku cadang belum dapat disimpan. Muat ulang untuk memeriksa hasilnya.',
        };
        $r01cBlocked = true;
        http_response_code(in_array($code, [1205, 1213, 1452, 1644], true) ? 409 : 503);
    }
} elseif ($method === 'POST' && str_starts_with($action, 'r01b_')) {
    tokenCsrf(); // Setelah login baru, sesi mungkin belum memiliki token.
    validasiCsrf();
    $createOpen = false;
    $woBagian = str_starts_with($action, 'r01b_mekanik_') ? 'mekanik' : 'jasa';
    $r01bInput = ['aksi' => $action];
    foreach (['no_work_order', 'token_r01b', 'kode_jasa', 'id_mekanik', 'biaya'] as $key) {
        $r01bInput[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $selectedId = $r01bInput['no_work_order'];
    try {
        $pesan = woR01bTerapkan($db, $selectedId, $woScope, $r01bInput);
        pesanKilAt($pesan);
        pindah('work_order.php?lihat=' . rawurlencode($selectedId) . '&bagian=' . $woBagian);
    } catch (WoR01bDitolak $e) {
        $status = $e->getCode();
        http_response_code($status);
        $r01bError = $e->getMessage();
        $r01bField = $e->field;
        $r01bBlocked = $status !== 422;
        if ($status === 403 || $status === 400) {
            $selectedId = '';
        }
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        error_log('[Kalawa WO R01B DB] ' . $e->getMessage());
        $r01bError = match ($code) {
            1142, 1143 => 'Izin database rincian WO belum lengkap. Minta pengelola memeriksa GRANT 012.',
            1205, 1213, 1452 => 'Data sedang berubah. Muat ulang rincian sebelum mencoba lagi.',
            default => 'Rincian belum dapat disimpan. Muat ulang untuk memeriksa hasilnya.',
        };
        $r01bBlocked = true;
        http_response_code(in_array($code, [1205, 1213, 1452], true) ? 409 : 503);
    }
} elseif ($method === 'POST') {
    validasiCsrf();
    $createOpen = true;
    try {
        if (!is_string($_POST['aksi'] ?? null) || $_POST['aksi'] !== 'buat') {
            throw new InvalidArgumentException('Tindakan tidak dikenali. Muat ulang halaman.');
        }
        $nonce = is_string($_POST['token_wo'] ?? null) ? $_POST['token_wo'] : '';
        if (!preg_match('/\A[a-f0-9]{48}\z/D', $nonce) || !isset($_SESSION['wo_r01a_tokens'][$nonce])) {
            throw new InvalidArgumentException('Form ini sudah dikirim atau kedaluwarsa. Buka formulir baru untuk membuat WO lain.');
        }
        $tokenMeta = $_SESSION['wo_r01a_tokens'][$nonce];
        $validation = woValidasiForm($_POST);
        $old = $validation['data'];
        $errors = $validation['errors'];
        $old['token_wo'] = $nonce;
        if ($errors) throw new InvalidArgumentException('Periksa kolom yang ditandai. Input sebelumnya tetap tersimpan.');
        // Cabang POST tidak boleh dipercaya. Pemeriksaan backend sebelum INSERT wajib.
        if (!ksWOAksesCabang($akun, $old['kode_cabang'])) {
            http_response_code(403);
            $errors['kode_cabang']='Anda hanya dapat membuat WO pada cabang penempatan Anda.';
            throw new InvalidArgumentException('Cabang tidak sesuai izin akun.');
        }

        korporatMulaiTransaksi($db);
        try {
            // Rekonfirmasi scope pada batas transaksi, sebelum seluruh SELECT dan INSERT.
            if (!ksWOAksesCabang($akun, $old['kode_cabang'])) {
                http_response_code(403);
                throw new InvalidArgumentException('Cabang tidak sesuai izin akun.');
            }
            // Cabang dan pelanggan harus ada. Tidak memakai cabang favorit sebagai otorisasi.
            $st = $db->prepare('SELECT kode_cabang FROM cabang WHERE kode_cabang=:c');
            $st->execute(['c'=>$old['kode_cabang']]);
            if (!$st->fetchColumn()) {
                $errors['kode_cabang']='Cabang sudah tidak tersedia. Pilih kembali.';
                throw new InvalidArgumentException('Cabang tidak tersedia.');
            }
            // Shared lock pada motor untuk menyelaraskan dengan perubahan kepemilikan motor
            // lewat modul Motor R01 yang menggunakan SELECT .. FOR UPDATE pada motor.
            $st = $db->prepare('SELECT no_polisi FROM motor WHERE no_polisi=:m LOCK IN SHARE MODE');
            $st->execute(['m'=>$old['no_polisi']]);
            if (!$st->fetchColumn()) {
                $errors['no_polisi']='Motor tidak ditemukan.';
                throw new InvalidArgumentException('Motor sudah tidak tersedia.');
            }
            // Tepat satu kepemilikan aktif pada hari WO dibuat, untuk pelanggan yang dipilih.
            $st = $db->prepare('SELECT id_pelanggan FROM kepemilikan_motor
                WHERE no_polisi=:m AND tanggal_selesai IS NULL
                AND tanggal_mulai <= CURDATE() LOCK IN SHARE MODE');
            $st->execute(['m'=>$old['no_polisi']]);
            $owners=$st->fetchAll(PDO::FETCH_COLUMN);
            if (count($owners) !== 1 || $owners[0] !== $old['id_pelanggan']) {
                $errors['no_polisi']='Motor harus mempunyai tepat satu pemilik aktif, yaitu pelanggan terpilih.';
                throw new InvalidArgumentException('Pemilik motor tidak sesuai. Periksa menu Motor.');
            }
            $st = $db->prepare('SELECT p.id_pegawai FROM pegawai p
                JOIN mekanik m ON m.id_mekanik=p.id_pegawai
                WHERE p.id_pegawai=:mek AND p.kode_cabang=:cab
                  AND p.jenis_pegawai=\'MEKANIK\' AND m.status_aktif=1 LOCK IN SHARE MODE');
            $st->execute(['mek'=>$old['id_mekanik_pj'],'cab'=>$old['kode_cabang']]);
            if (!$st->fetchColumn()) {
                $errors['id_mekanik_pj']='Pilih mekanik aktif yang ditempatkan di cabang WO.';
                throw new InvalidArgumentException('Mekanik tidak sesuai dengan cabang atau tidak aktif.');
            }
            // Tanggal memakai jam database; trigger Tugas 3 menolak tanggal masa depan.
            // Random ID + PK => mencegah tabrakan nomor WO dan tidak menebak urutan seed.
            $idPelanggan = $old['id_pelanggan'];
            if (!korporatKunciPelanggan($db, $idPelanggan)) {
                $errors['id_pelanggan']='Pelanggan sudah tidak tersedia.';
                throw new InvalidArgumentException('Pelanggan tidak ditemukan.');
            }
            $jam = $db->query("SELECT CURRENT_TIMESTAMP() AS waktu, DATE_FORMAT(CURRENT_TIMESTAMP(), '%Y%m%d') AS hari")->fetch();
            $date = (string) $jam['hari'];
            if ($date !== ($tokenMeta['day'] ?? '')) {
                throw new InvalidArgumentException('Tanggal server sudah berganti sejak form dibuka. Muat ulang form baru.');
            }
            $kontrak = null;
            if (korporatAdaProfil($db, $idPelanggan)) {
                $kontrak = korporatKontrakAktif($db, $idPelanggan, substr($jam['waktu'], 0, 10));
                korporatCekPlafon($db, korporatSaldo($db, $idPelanggan), $old['estimasi_biaya'], $kontrak['plafon_kredit']);
            }
            $newId = woNomorDariToken($date, $nonce);
            $st = $db->prepare('INSERT INTO work_order
                (no_work_order,tanggal_masuk,tanggal_selesai,kode_cabang,id_pelanggan,
                 no_polisi,id_mekanik_pj,keluhan,estimasi_biaya,status_pekerjaan)
                VALUES (:nomor,:waktu,NULL,:cabang,:pelanggan,
                        :motor,:mekanik,:keluhan,:estimasi,\'MENUNGGU\')');
            $st->execute([
                'nomor'=>$newId, 'waktu'=>$jam['waktu'],
                'cabang'=>$old['kode_cabang'], 'pelanggan'=>$old['id_pelanggan'],
                'motor'=>$old['no_polisi'], 'mekanik'=>$old['id_mekanik_pj'],
                'keluhan'=>$old['keluhan'], 'estimasi'=>$old['estimasi_biaya'],
            ]);
            if ($st->rowCount() !== 1) throw new RuntimeException('Work order tidak tersimpan.');
            if ($kontrak !== null) {
                $st = $db->prepare('INSERT INTO work_order_korporat (no_work_order, id_pelanggan, id_kontrak)
                    VALUES (:nomor,:pelanggan,:kontrak)');
                $st->execute(['nomor'=>$newId, 'pelanggan'=>$idPelanggan, 'kontrak'=>$kontrak['id_kontrak']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        unset($_SESSION['wo_r01a_tokens'][$nonce]);
        pesanKilAt('Work order berhasil dibuat. Nomor: ' . $newId . '.');
        pindah('work_order.php?lihat=' . rawurlencode($newId));
    } catch (KorporatDitolak $e) {
        http_response_code(422);
        $errors[$e->field] = $e->getMessage();
        $error = $e->getMessage();
    } catch (InvalidArgumentException $e) {
        if (http_response_code()===200) http_response_code(422);
        $error=$e->getMessage();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa WO R01A DB] '.$e->getMessage());
        $code=(int) ($e->errorInfo[1] ?? 0);
        $error=match($code) {
            1062 => 'Nomor WO bertabrakan dengan transaksi lain. Belum tersimpan; coba formulir baru.',
            1142 => 'Hak akses database Work Order belum lengkap. Periksa GRANT 010.',
            1452 => 'Data terkait sudah berubah. Periksa pelanggan, motor, cabang, dan mekanik.',
            1644 => 'Tanggal masuk ditolak oleh aturan database. Periksa waktu server.',
            1205,1213 => 'Data sedang digunakan. Muat ulang dan periksa kembali sebelum mengirim.',
            default => 'Work order belum dapat disimpan. Periksa log Apache/PHP dan akun database uji.',
        };
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[Kalawa WO R01A] '.$e->getMessage());
        $error='Penyimpanan tidak berhasil. Periksa log server.';
    }
}

// Daftar dan header R01A. Rincian R01B dibaca terpisah setelah detail lolos scope.
$customers=[]; $branches=[]; $motors=[]; $mechanics=[]; $rows=[]; $detail=null;
try {
    $serverDay=(string)$db->query("SELECT DATE_FORMAT(CURRENT_TIMESTAMP(), '%Y%m%d')")->fetchColumn();
    if ($woScope === null) {
        $branches=$db->query('SELECT kode_cabang,nama_cabang FROM cabang ORDER BY kode_cabang')->fetchAll();
    } else {
        $st=$db->prepare('SELECT kode_cabang,nama_cabang FROM cabang WHERE kode_cabang=:scope');
        $st->execute(['scope'=>$woScope]);$branches=$st->fetchAll();
    }
    $customers=$db->query('SELECT p.id_pelanggan,p.nama_pelanggan, pk.id_pelanggan IS NOT NULL AS korporat
        FROM pelanggan p LEFT JOIN pelanggan_korporat pk ON pk.id_pelanggan=p.id_pelanggan
        ORDER BY p.nama_pelanggan,p.id_pelanggan LIMIT 500')->fetchAll();
    $motors=$db->query('SELECT k.id_pelanggan,k.no_polisi,m.merek,m.tipe
        FROM kepemilikan_motor k JOIN motor m ON m.no_polisi=k.no_polisi
        WHERE k.tanggal_selesai IS NULL AND k.tanggal_mulai <= CURDATE()
        ORDER BY k.no_polisi LIMIT 500')->fetchAll();
    $mechanicSql='SELECT p.id_pegawai AS id_mekanik,p.nama_pegawai,p.kode_cabang
        FROM pegawai p JOIN mekanik m ON m.id_mekanik=p.id_pegawai
        WHERE m.status_aktif=1 AND p.jenis_pegawai=\'MEKANIK\'';
    if ($woScope!==null) $mechanicSql.=' AND p.kode_cabang=:scope';
    $mechanicSql.=' ORDER BY p.kode_cabang,p.nama_pegawai LIMIT 500';
    $st=$db->prepare($mechanicSql);
    $st->execute($woScope===null?[]:['scope'=>$woScope]);
    $mechanics=$st->fetchAll();

    if ($selectedId !== '') {
        if (!preg_match('/\A[A-Za-z0-9-]{1,20}\z/D', $selectedId)) {
            $selectedId = '';
            if (!$error) $error='Nomor work order tidak valid.';
        } else {
            $st=$db->prepare('SELECT w.*, c.nama_cabang, p.nama_pelanggan, wk.id_kontrak,
                     m.merek,m.tipe,pg.nama_pegawai AS nama_mekanik
                FROM work_order w JOIN cabang c ON c.kode_cabang=w.kode_cabang
                JOIN pelanggan p ON p.id_pelanggan=w.id_pelanggan
                LEFT JOIN work_order_korporat wk ON wk.no_work_order=w.no_work_order
                JOIN motor m ON m.no_polisi=w.no_polisi
                JOIN pegawai pg ON pg.id_pegawai=w.id_mekanik_pj
                WHERE w.no_work_order=:nomor' . ($woScope!==null ? ' AND w.kode_cabang=:scope' : ''));
            $st->execute($woScope===null ? ['nomor'=>$selectedId] : ['nomor'=>$selectedId,'scope'=>$woScope]);
            $detail=$st->fetch() ?: null;
            if (!$detail && !$error) {if($method==='GET')http_response_code(404);$error='Work order yang diminta tidak ditemukan.';}
        }
    }
    $filterCabang=is_string($_GET['cabang'] ?? null) ? trim($_GET['cabang']) : '';
    $filterStatus=is_string($_GET['status'] ?? null) ? trim($_GET['status']) : 'semua';
    $filterSearch=is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $availableBranches=array_column($branches,'kode_cabang');
    if (!in_array($filterCabang,$availableBranches,true)) $filterCabang='';
    // Cabang bukan sekadar filter UI: SQL wajib memiliki kondisi scope permanen.
    if ($woScope !== null) $filterCabang=$woScope;
    if (!in_array($filterStatus,['semua','MENUNGGU','PROSES','SELESAI','DIBATALKAN','DIBAYAR'],true)) $filterStatus='semua';
    $filterSearch=substr($filterSearch,0,70);
    $where=[]; $params=[];
    if ($woScope!==null) {$where[]='w.kode_cabang=:scope';$params['scope']=$woScope;}
    elseif ($filterCabang!=='') {$where[]='w.kode_cabang=:c';$params['c']=$filterCabang;}
    if ($filterStatus==='DIBAYAR') {
        $where[]="COALESCE(bt.status_pembayaran,bp.status_pembayaran,'BELUM_LUNAS')='LUNAS'";
    } elseif ($filterStatus!=='semua') {$where[]='w.status_pekerjaan=:s';$params['s']=$filterStatus;}
    if ($filterSearch!=='') {
        $where[]='(w.no_work_order LIKE :nomor OR w.no_polisi LIKE :motor OR p.nama_pelanggan LIKE :nama)';
        $pattern='%'.str_replace(['%','_'],['\\%','\\_'],$filterSearch).'%';
        $params['nomor']=$pattern;$params['motor']=$pattern;$params['nama']=$pattern;
    }
    $whereSql=$where ? ' WHERE '.implode(' AND ',$where) : '';
    $from=' FROM work_order w JOIN pelanggan p ON p.id_pelanggan=w.id_pelanggan
        JOIN cabang c ON c.kode_cabang=w.kode_cabang
        JOIN pegawai pg ON pg.id_pegawai=w.id_mekanik_pj
        LEFT JOIN pembayaran bp ON bp.no_work_order=w.no_work_order
        LEFT JOIN detail_tagihan_korporat bd ON bd.no_work_order=w.no_work_order
        LEFT JOIN tagihan_korporat bt ON bt.id_tagihan=bd.id_tagihan ';
    $st=$db->prepare('SELECT COUNT(*)'.$from.$whereSql);
    $st->execute($params);$total=(int)$st->fetchColumn();
    $rows=ksUiList($db,'SELECT w.no_work_order,w.tanggal_masuk,w.kode_cabang,c.nama_cabang,
                 w.no_polisi,p.nama_pelanggan,pg.nama_pegawai AS nama_mekanik,
                 w.status_pekerjaan,w.estimasi_biaya,
                 COALESCE(bt.status_pembayaran,bp.status_pembayaran,\'BELUM_LUNAS\') AS pembayaran'.$from.$whereSql.'
        ORDER BY w.tanggal_masuk DESC,w.no_work_order DESC',$params,'hal_wo');
} catch (Throwable $e) {
    error_log('[Kalawa WO R01A READ] '.$e->getMessage());
    http_response_code(503);
    exit('Data Work Order belum dapat dibuka. Periksa GRANT 010 dan konfigurasi database pengujian.');
}

$r01b = null;
$r01bToken = '';
$r01c = null;
$r01cToken = '';
if ($detail && $woBagian === 'suku_cadang') {
    $createOpen = false;
    try {
        $r01c = woR01cBaca($db, $detail, $woScope);
        if ($r01c['kunci'] === '' && !$r01cBlocked) {
            if ($r01cInput) {
                $r01cToken = $r01cInput['token_r01c'];
            } elseif ($r01c['pilihan'] && ($_GET['tambah'] ?? null) === '1') {
                $r01cToken = woR01cTerbitkanToken($detail['no_work_order']);
            }
        }
    } catch (PDOException $e) {
        error_log('[Kalawa WO R01C READ] ' . $e->getMessage());
        if ($r01cError === '') {
            http_response_code(503);
            $r01cError = 'Rincian suku cadang belum dapat dibuka. Minta pengelola memeriksa GRANT 013.';
        }
    }
} elseif ($detail) {
    $createOpen = false;
    try {
        $r01b = woR01bBaca($db, $detail, $woScope);
        if ($woBagian !== 'ringkasan' && $r01b['kunci'] === '' && !$r01bBlocked) {
            $r01bToken = $r01bInput
                ? $r01bInput['token_r01b']
                : woR01bTerbitkanToken($detail['no_work_order'], $r01b);
        }
    } catch (PDOException $e) {
        error_log('[Kalawa WO R01B READ] ' . $e->getMessage());
        if ($r01bError === '') {
            http_response_code(503);
            $r01bError = 'Rincian WO belum dapat dibuka. Minta pengelola memeriksa GRANT 012.';
        }
    }
}

$woStatusAksi = [];
$woStatusNote = '';
if ($detail && $woBagian === 'ringkasan') {
    $woStatusAksi = woStatusOpsi($detail['status_pekerjaan'], true);
    if (isset($woStatusAksi['DIBATALKAN'])) {
        try {
            $woStatusNote = woStatusAlasanBatal(
                $db, $detail['no_work_order'], $woScope, $detail['status_pekerjaan'], false
            );
        } catch (PDOException $e) {
            error_log('[Kalawa WO R01D BATAL READ] ' . $e->getMessage());
            $woStatusNote = 'Syarat pembatalan belum dapat diperiksa. Muat ulang halaman.';
        }
        if ($woStatusNote !== '') {
            unset($woStatusAksi['DIBATALKAN']);
        }
    }
}

if ($createOpen && $old === []) {
    $old=['kode_cabang'=>$woScope ?? $filterCabang,'id_pelanggan'=>is_string($_GET['pelanggan']??null)?$_GET['pelanggan']:'','no_polisi'=>is_string($_GET['motor']??null)?$_GET['motor']:'',
        'id_mekanik_pj'=>'','keluhan'=>'','estimasi_biaya'=>'0'];
}
$formToken=$createOpen
    ? ((!empty($old['token_wo']) && isset($_SESSION['wo_r01a_tokens'][$old['token_wo']]))
        ? $old['token_wo'] : woTokenBaru($serverDay))
    : '';
$flash=ambilPesanKilAt();
awalHalamanM2('Work Order', $akun);
?>
<div class="ks-wo">
  <?php if (!$detail): ?>
  <div class="ks-wo-head">
    <div><h1>Work Order</h1><p class="sub">Catat pekerjaan servis dan periksa statusnya.</p></div>
  </div>
  <?php if ($flash): ?><div class="alert<?= ($flash['jenis'] ?? 'ok') === 'ok' ? '' : ' bad' ?>" role="status"><?=amanHtml($flash['pesan'] ?? '')?></div><?php endif; ?>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert bad" role="alert" id="wo-error" tabindex="-1"><?=amanHtml($error)?></div><?php endif; ?>
  <?php if ($r01bError && !$detail): ?><div class="alert bad" role="alert"><?=amanHtml($r01bError)?></div><?php endif; ?>
  <?php if ($r01cError && !$detail): ?><div class="alert bad" role="alert"><?=amanHtml($r01cError)?></div><?php endif; ?>

  <?php if ($detail): ?>
  <?php if ($detail['id_kontrak'] !== null): ?>
    <p class="ks-test-flag">Korporat · Kontrak #<?=amanHtml($detail['id_kontrak'])?></p>
  <?php endif; ?>
  <?php require __DIR__ . '/../app/wo_r01b_view_m2.php'; ?>
  <?php if ($woBagian === 'ringkasan'): ?>
    <?php $statusEndpoint = 'work_order.php'; require __DIR__ . '/../app/wo_status_view_m2.php'; ?>
    <?php $statusBayar=opsStatusPembayaran($db,$detail['no_work_order']); ?>
    <p><a class="ks-ui-action" href="rincian_servis.php?wo=<?=rawurlencode($detail['no_work_order'])?>">Rincian servis dan garansi</a></p>
    <section class="panel"><div class="row space"><div><h2>Pembayaran</h2>
      <p><?=($statusBayar['status']??'')==='LUNAS'?'Dibayar · LUNAS':'Belum lunas'?></p>
      <?php if ($statusBayar['id_tagihan']!==null): ?><p>Tagihan korporat #<?=amanHtml($statusBayar['id_tagihan'])?></p><?php endif; ?>
    </div>
    <?php if ($akun['role_db']==='rl_owner_ops'):
        $urlBayar=$statusBayar['id_kontrak']!==null?'tagihan_korporat.php':'pembayaran.php?lihat='.rawurlencode($detail['no_work_order']); ?>
      <a class="btn light" href="<?=amanHtml($urlBayar)?>">Buka pembayaran</a>
    <?php endif; ?></div></section>
  <?php endif; ?>
  <?php else: ?>

  <?php if ($createOpen): ?>
  <section class="panel" id="buat-wo" aria-labelledby="buat-judul">
    <div class="ks-wo-panel-head">
      <div><h2 id="buat-judul">Buat Work Order</h2><p class="sub">Pilih pelanggan, motor yang dimiliki, dan mekanik cabang.</p></div>
      <a class="ks-wo-link" href="work_order.php">Tutup</a>
    </div>
    <form method="post" action="work_order.php#buat-wo" id="form-wo" autocomplete="off" novalidate>
      <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
      <input type="hidden" name="aksi" value="buat">
      <input type="hidden" name="token_wo" value="<?=amanHtml($formToken)?>">
      <div class="ks-wo-fields">
        <div class="ks-wo-field"><label for="wo-cabang">Cabang <span aria-hidden="true">*</span></label>
          <select id="wo-cabang" name="kode_cabang" required aria-invalid="<?=isset($errors['kode_cabang'])?'true':'false'?>">
            <option value="">Pilih cabang</option>
            <?php foreach($branches as $c): ?><option value="<?=amanHtml($c['kode_cabang'])?>" <?=($old['kode_cabang']??'')===$c['kode_cabang']?'selected':''?>><?=amanHtml($c['nama_cabang'])?></option><?php endforeach; ?>
          </select><small>Wajib. Mekanik mengikuti cabang ini.</small><?php if(isset($errors['kode_cabang'])): ?><div class="wo-error"><?=amanHtml($errors['kode_cabang'])?></div><?php endif; ?>
        </div>
        <div class="ks-wo-field"><label for="wo-pelanggan">Pelanggan <span aria-hidden="true">*</span></label>
          <select id="wo-pelanggan" name="id_pelanggan" required aria-invalid="<?=isset($errors['id_pelanggan'])?'true':'false'?>"><option value="">Pilih pelanggan</option>
            <?php foreach($customers as $p): ?><option value="<?=amanHtml($p['id_pelanggan'])?>" <?=($old['id_pelanggan']??'')===$p['id_pelanggan']?'selected':''?>><?=amanHtml($p['nama_pelanggan'].' · '.$p['id_pelanggan'].((int)$p['korporat'] === 1 ? ' · Korporat' : ''))?></option><?php endforeach; ?>
          </select><small>Pilih pemilik kendaraan. Kontrak aktif dan sisa plafon korporat diperiksa saat simpan.</small><?php if(isset($errors['id_pelanggan'])): ?><div class="wo-error"><?=amanHtml($errors['id_pelanggan'])?></div><?php endif; ?>
        </div>
        <div class="ks-wo-field"><label for="wo-motor">Motor pelanggan <span aria-hidden="true">*</span></label>
          <select id="wo-motor" name="no_polisi" required aria-invalid="<?=isset($errors['no_polisi'])?'true':'false'?>"><option value="">Pilih motor</option>
            <?php foreach($motors as $m): ?><option value="<?=amanHtml($m['no_polisi'])?>" data-pelanggan="<?=amanHtml($m['id_pelanggan'])?>" <?=($old['no_polisi']??'')===$m['no_polisi']?'selected':''?>><?=amanHtml($m['no_polisi'].' · '.$m['merek'].' '.$m['tipe'])?></option><?php endforeach; ?>
          </select><small id="wo-motor-help">Wajib. Hanya motor dengan kepemilikan aktif.</small><?php if(isset($errors['no_polisi'])): ?><div class="wo-error"><?=amanHtml($errors['no_polisi'])?></div><?php endif; ?>
        </div>
        <div class="ks-wo-field"><label for="wo-mekanik">Mekanik penanggung jawab <span aria-hidden="true">*</span></label>
          <select id="wo-mekanik" name="id_mekanik_pj" required aria-invalid="<?=isset($errors['id_mekanik_pj'])?'true':'false'?>"><option value="">Pilih mekanik</option>
            <?php foreach($mechanics as $m): ?><option value="<?=amanHtml($m['id_mekanik'])?>" data-cabang="<?=amanHtml($m['kode_cabang'])?>" <?=($old['id_mekanik_pj']??'')===$m['id_mekanik']?'selected':''?>><?=amanHtml($m['nama_pegawai'].' · '.$m['id_mekanik'])?></option><?php endforeach; ?>
          </select><small id="wo-mekanik-help">Wajib. Harus aktif dan bertugas di cabang terpilih.</small><?php if(isset($errors['id_mekanik_pj'])): ?><div class="wo-error"><?=amanHtml($errors['id_mekanik_pj'])?></div><?php endif; ?>
        </div>
        <div class="ks-wo-field full"><label for="wo-keluhan">Keluhan pelanggan <span aria-hidden="true">*</span></label>
          <textarea id="wo-keluhan" name="keluhan" rows="4" maxlength="1500" required aria-invalid="<?=isset($errors['keluhan'])?'true':'false'?>" placeholder="Contoh: Motor sulit menyala saat pagi dan suara mesin terdengar kasar."><?=amanHtml($old['keluhan']??'')?></textarea>
          <small>Wajib, 10–1.500 karakter. Jelaskan gejala yang disampaikan pelanggan.</small><?php if(isset($errors['keluhan'])): ?><div class="wo-error"><?=amanHtml($errors['keluhan'])?></div><?php endif; ?>
        </div>
        <div class="ks-wo-field"><label for="wo-estimasi">Estimasi biaya (Rp) <span aria-hidden="true">*</span></label>
          <input id="wo-estimasi" name="estimasi_biaya" inputmode="decimal" maxlength="13" required value="<?=amanHtml($old['estimasi_biaya']??'0')?>" placeholder="Contoh: 150000" aria-invalid="<?=isset($errors['estimasi_biaya'])?'true':'false'?>">
          <small>Wajib. Angka nonnegatif tanpa pemisah ribuan; hingga 2 desimal. Ini estimasi, bukan tagihan akhir.</small><?php if(isset($errors['estimasi_biaya'])): ?><div class="wo-error"><?=amanHtml($errors['estimasi_biaya'])?></div><?php endif; ?>
        </div>
        <div class="ks-wo-field"><label>Nomor WO, tanggal, dan status</label>
          <div class="ks-wo-fact" style="min-height:65px"><small>Diisi otomatis saat disimpan</small><strong>Nomor unik · waktu server · Menunggu</strong></div>
          <small>Nomor dan tanggal masuk tidak diketik manual untuk mencegah kesalahan.</small>
        </div>
      </div>
      <div class="ks-wo-actions"><button type="submit" id="wo-simpan">Simpan Work Order</button><a class="ks-wo-link" href="work_order.php">Batal</a><small>Simpan sekali; halaman detail terbuka otomatis bila berhasil.</small></div>
    </form>
  </section>
  <?php endif; ?>

  <section class="panel" id="daftar-wo" aria-labelledby="daftar-judul">
    <div class="ks-wo-panel-head">
      <div><h2 id="daftar-judul">Daftar Work Order</h2><p class="sub">Pekerjaan servis dari <?=amanHtml(ksWOLabelCakupan($woScope))?>.</p></div>
      <?php if(!$createOpen): ?><a class="btn" href="work_order.php?buat=1#buat-wo">+ Buat Work Order</a><?php endif; ?>
    </div>
    <form method="get" action="work_order.php#daftar-wo" class="ks-wo-filters">
      <div class="ks-wo-field"><label for="filter-cabang">Cabang</label><select id="filter-cabang" name="cabang"><?php if ($woScope===null): ?><option value="">Semua cabang</option><?php endif; ?><?php foreach($branches as $c): ?><option value="<?=amanHtml($c['kode_cabang'])?>" <?=$filterCabang===$c['kode_cabang']?'selected':''?>><?=amanHtml($c['nama_cabang'])?></option><?php endforeach; ?></select></div>
      <div class="ks-wo-field"><label for="filter-status">Status</label><select id="filter-status" name="status"><option value="semua">Semua</option><?php foreach(['MENUNGGU','PROSES','SELESAI','DIBATALKAN','DIBAYAR'] as $s): ?><option value="<?=amanHtml($s)?>" <?=$filterStatus===$s?'selected':''?>><?=amanHtml($s==='DIBAYAR'?'Dibayar':woLabelStatus($s))?></option><?php endforeach; ?></select></div>
      <div class="ks-wo-field"><label for="filter-q">Cari WO</label><input id="filter-q" name="q" maxlength="70" placeholder="Nomor WO, polisi, pelanggan" value="<?=amanHtml($filterSearch)?>"></div>
      <div class="ks-wo-actions filter-buttons"><button type="submit">Tampilkan</button><a class="ks-wo-link" href="work_order.php#daftar-wo">Reset</a></div>
    </form>
    <p class="ks-wo-count">Menampilkan <?=count($rows)?> dari <?=$total?> Work Order.</p>
    <p class="ks-wo-table-hint">Geser tabel ke samping untuk melihat semua kolom.</p>
    <div class="ks-wo-table" role="region" aria-label="Daftar Work Order, dapat digulir ke samping" tabindex="0"><table><thead><tr><th>Nomor / masuk</th><th>Cabang</th><th>Pelanggan / motor</th><th>Mekanik</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
      <?php if (!$rows): ?><tr><td colspan="6">Tidak ada Work Order yang cocok. Coba ubah filter.</td></tr><?php endif; ?>
      <?php foreach($rows as $w): ?><tr>
        <td><strong class="ks-wo-code"><?=amanHtml($w['no_work_order'])?></strong><small><?=amanHtml($w['tanggal_masuk'])?></small></td>
        <td><?=amanHtml($w['nama_cabang'])?></td>
        <td><strong><?=amanHtml($w['nama_pelanggan'])?></strong><small><?=amanHtml($w['no_polisi'])?></small></td>
        <td><?=amanHtml($w['nama_mekanik'])?></td>
        <td><span class="ks-wo-status <?=amanHtml($w['status_pekerjaan'])?>"><?=amanHtml(woLabelStatus($w['status_pekerjaan']))?></span><?php if ($w['pembayaran']==='LUNAS'): ?><small>Dibayar · LUNAS</small><?php endif; ?></td>
        <td><a href="work_order.php?lihat=<?=rawurlencode($w['no_work_order'])?>#detail-wo" class="ks-wo-link">Lihat detail</a></td>
      </tr><?php endforeach; ?>
    </tbody></table></div>
<?php ksUiPager('hal_wo'); ?>
  </section>
  <?php endif; ?>
</div>
<script>
(function(){
  const form=document.getElementById('form-wo');
  if(!form)return;
  const cab=form.querySelector('#wo-cabang'),customer=form.querySelector('#wo-pelanggan');
  const motor=form.querySelector('#wo-motor'),tech=form.querySelector('#wo-mekanik');
  function filterOptions(select,attr,value,help,emptyText){
    let shown=0;
    Array.from(select.options).slice(1).forEach(o=>{
      const yes=!!value && o.dataset[attr]===value;
      o.hidden=!yes; o.disabled=!yes;
      if(yes)shown++;
    });
    if(select.selectedOptions.length && select.selectedOptions[0].disabled)select.value='';
    help.textContent=shown===0 ? emptyText : `Tersedia ${shown} pilihan yang sesuai.`;
  }
  function update(clearMotor=false,clearTech=false){
    if(clearMotor) motor.value='';
    if(clearTech) tech.value='';
    filterOptions(motor,'pelanggan',customer.value,document.getElementById('wo-motor-help'),
      customer.value?'Belum ada motor dengan kepemilikan aktif untuk pelanggan ini. Atur melalui menu Motor.':'Pilih pelanggan terlebih dahulu.');
    filterOptions(tech,'cabang',cab.value,document.getElementById('wo-mekanik-help'),
      cab.value?'Tidak ada mekanik aktif di cabang ini. Pilih cabang lain atau perbaiki penempatan mekanik.':'Pilih cabang terlebih dahulu.');
  }
  customer.addEventListener('change',()=>update(true,false));
  cab.addEventListener('change',()=>update(false,true));
  update();
  form.addEventListener('submit', function(){
    // Pencegahan double click di browser; token server & PRIMARY KEY tetap pengaman utama.
    const btn=document.getElementById('wo-simpan');btn.disabled=true;btn.textContent='Menyimpan...';
  });
  const bad=form.querySelector('[aria-invalid="true"]');
  if(document.getElementById('wo-error')){(bad||document.getElementById('wo-error')).focus();}
})();
</script>
<?php akhirHalamanM2(); ?>
