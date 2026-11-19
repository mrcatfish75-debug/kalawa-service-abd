<?php
declare(strict_types=1);
// Controller memuat helper cakupan R01A dan kueri R01B sebelum memanggil workflow.

function woStatusOpsi(string $status, bool $bolehBatal): array
{
    $opsi = match ($status) {
        'MENUNGGU' => ['PROSES' => 'Mulai pengerjaan'],
        'PROSES' => ['SELESAI' => 'Tandai selesai'],
        default => [],
    };
    if ($opsi && $bolehBatal) {
        $opsi['DIBATALKAN'] = 'Batalkan WO';
    }
    return $opsi;
}

function woStatusMekanikAktif(PDO $db, array $akun, bool $transaksi = false): bool
{
    if (($akun['role_db'] ?? '') !== 'rl_service_tech') {
        return false;
    }
    $st = $db->prepare("SELECT m.id_mekanik FROM mekanik m
        JOIN pegawai p ON p.id_pegawai = m.id_mekanik
        WHERE m.id_mekanik = :mekanik AND m.status_aktif = 1
          AND p.kode_cabang = :cabang AND p.jenis_pegawai = 'MEKANIK'"
        . ($transaksi ? ' LOCK IN SHARE MODE' : ''));
    $st->execute(['mekanik' => $akun['id_pegawai'] ?? '', 'cabang' => $akun['kode_cabang'] ?? '']);
    return $st->fetchColumn() !== false;
}

/** Dipanggil pada WO Menunggu atau Proses setelah cakupan WO ditentukan. */
function woStatusAlasanBatal(PDO $db, string $nomor, ?string $scope, string $status, bool $transaksi): string
{
    if (woR01bAlasanKunci($db, $nomor, $scope, $status, $transaksi) !== '') {
        return 'Pembatalan dikunci karena WO ini memiliki catatan pembayaran.';
    }
    $spare = woR01bQuery($db, $nomor, $scope, 'SELECT d.kode_suku_cadang
        FROM work_order w JOIN detail_wo_suku_cadang d ON d.no_work_order = w.no_work_order
        WHERE {wo} LIMIT 1' . ($transaksi ? ' LOCK IN SHARE MODE' : ''))->fetchColumn();
    return $spare === false ? '' : 'Pembatalan dikunci karena WO ini sudah memakai suku cadang.';
}

function woStatusTerapkan(PDO $db, array $akun, string $nomor, string $awal, string $tujuan): void
{
    if (!preg_match('/\A[A-Za-z0-9-]{1,20}\z/D', $nomor)
        || !in_array($awal, ['MENUNGGU', 'PROSES', 'SELESAI', 'DIBATALKAN'], true)
        || !in_array($tujuan, ['PROSES', 'SELESAI', 'DIBATALKAN'], true)) {
        throw new WoR01bDitolak('Permintaan perubahan status tidak valid.', 400);
    }

    $mekanik = ($akun['role_db'] ?? '') === 'rl_service_tech';
    if ((!$mekanik && !ksWOBolehAkses($akun)) || ($mekanik && $tujuan === 'DIBATALKAN')) {
        throw new WoR01bDitolak('Aksi status ini di luar izin akun Anda.', 403);
    }
    $scope = $mekanik ? (string) ($akun['kode_cabang'] ?? '') : ksWOCabangTerikat($akun);
    $whereMekanik = $mekanik ? ' AND w.id_mekanik_pj = :mekanik' : '';
    $params = $mekanik ? ['mekanik' => $akun['id_pegawai'] ?? ''] : [];

    korporatMulaiTransaksi($db);
    try {
        korporatKunciPelangganWo($db, $nomor, $scope, $mekanik ? (string) $akun['id_pegawai'] : null);
        // Kunci baris yang juga dikunci saat menambah jasa, penugasan, dan suku cadang.
        $wo = woR01bQuery($db, $nomor, $scope, 'SELECT w.status_pekerjaan,
                (w.tanggal_masuk <= CURRENT_TIMESTAMP()) AS waktu_sah
            FROM work_order w WHERE {wo}' . $whereMekanik . ' FOR UPDATE', $params)->fetch();
        if (!$wo || ($mekanik && !woStatusMekanikAktif($db, $akun, true))) {
            throw new WoR01bDitolak('Work Order tidak tersedia untuk aksi status akun Anda.', 403);
        }
        $status = $wo['status_pekerjaan'];
        if ($status !== $awal) {
            throw new WoR01bDitolak('Status WO sudah berubah. Muat ulang sebelum mencoba lagi.', 409);
        }
        if (!isset(woStatusOpsi($status, !$mekanik)[$tujuan])) {
            throw new WoR01bDitolak('Perubahan status ini tidak tersedia dari status saat ini.', 409);
        }
        if ($tujuan === 'DIBATALKAN') {
            $alasan = woStatusAlasanBatal($db, $nomor, $scope, $status, true);
            if ($alasan !== '') {
                throw new WoR01bDitolak($alasan, 409);
            }
        }
        if ($tujuan === 'SELESAI' && (int) $wo['waktu_sah'] !== 1) {
            throw new WoR01bDitolak('Waktu server masih sebelum waktu masuk WO. Periksa pengaturan waktu.', 409);
        }

        $tanggal = $tujuan === 'SELESAI' ? 'CURRENT_TIMESTAMP()' : 'NULL';
        $st = woR01bQuery($db, $nomor, $scope, 'UPDATE work_order w
            SET status_pekerjaan = :tujuan, tanggal_selesai = ' . $tanggal . '
            WHERE {wo} AND w.status_pekerjaan = :awal' . $whereMekanik,
            $params + ['tujuan' => $tujuan, 'awal' => $awal]);
        if ($st->rowCount() !== 1) {
            throw new WoR01bDitolak('Status WO sudah berubah. Muat ulang sebelum mencoba lagi.', 409);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function woStatusTanganiPost(PDO $db, array $akun, string $halaman): array
{
    validasiCsrf();
    $input = [];
    foreach (['no_work_order', 'status_awal', 'status_tujuan'] as $key) {
        $input[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
    }
    $nomor = $input['no_work_order'];
    try {
        woStatusTerapkan($db, $akun, $nomor, $input['status_awal'], $input['status_tujuan']);
        pesanKilAt('Status pekerjaan diperbarui menjadi ' . woLabelStatus($input['status_tujuan']) . '.');
        pindah($halaman . '?lihat=' . rawurlencode($nomor));
    } catch (WoR01bDitolak $e) {
        $status = $e->getCode();
        http_response_code($status);
        return [in_array($status, [400, 403], true) ? '' : $nomor, $e->getMessage()];
    } catch (PDOException $e) {
        error_log('[Kalawa WO R01D DB] ' . $e->getMessage());
        $code = (int) ($e->errorInfo[1] ?? 0);
        if (in_array($code, [1205, 1213], true)) {
            http_response_code(409);
            return [$nomor, 'WO sedang diperbarui. Muat ulang sebelum mencoba lagi.'];
        }
        http_response_code(503);
        return [$nomor, 'Status belum dapat disimpan. Hubungi pengelola untuk memeriksa izin transaksi dan pencatatan riwayat.'];
    }
}
