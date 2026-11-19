<?php
declare(strict_types=1);
require_once __DIR__ . '/korporat_m2.php';
require_once __DIR__ . '/validasi_wo_m2.php';

final class WoR01bDitolak extends RuntimeException
{
    public function __construct(string $pesan, int $status = 422, public readonly string $field = '')
    {
        parent::__construct($pesan, $status);
    }
}

function woR01bNominal(string $input): ?string
{
    if (!preg_match('/\A([0-9]{1,10})(?:[.,]([0-9]{1,2}))?\z/D', trim($input), $parts)) {
        return null;
    }
    return (ltrim($parts[1], '0') ?: '0') . '.' . str_pad($parts[2] ?? '', 2, '0');
}

/** Semua kueri R01B menggunakan alias w dan kondisi WO yang sama. Scope berasal dari sesi. */
function woR01bQuery(PDO $db, string $nomor, ?string $scope, string $sql, array $params = []): PDOStatement
{
    $where = 'w.no_work_order = :nomor';
    $params['nomor'] = $nomor;
    if ($scope !== null) {
        $where .= ' AND w.kode_cabang = :scope';
        $params['scope'] = $scope;
    }
    $st = $db->prepare(str_replace('{wo}', $where, $sql));
    $st->execute($params);
    return $st;
}

function woR01bAlasanKunci(PDO $db, string $nomor, ?string $scope, string $status, bool $transaksi): string
{
    if (!in_array($status, ['MENUNGGU', 'PROSES'], true)) {
        return 'Rincian hanya dapat diubah saat pekerjaan Menunggu atau Proses.';
    }
    $bayar = woR01bQuery($db, $nomor, $scope, 'SELECT b.no_work_order
        FROM work_order w JOIN pembayaran b ON b.no_work_order = w.no_work_order
        WHERE {wo}' . ($transaksi ? ' LOCK IN SHARE MODE' : ''))->fetchColumn();
    return $bayar === false ? '' : 'Rincian dikunci karena WO ini sudah memiliki catatan pembayaran.';
}

function woR01bBaca(PDO $db, array $wo, ?string $scope): array
{
    $nomor = $wo['no_work_order'];
    $jasa = woR01bQuery($db, $nomor, $scope, 'SELECT j.kode_jasa, j.uraian_jasa_servis,
            d.biaya, SUM(d.biaya) OVER () AS subtotal
        FROM work_order w
        JOIN detail_wo_jasa d ON d.no_work_order = w.no_work_order
        JOIN jasa_servis j ON j.kode_jasa = d.kode_jasa
        WHERE {wo} ORDER BY j.uraian_jasa_servis, j.kode_jasa')->fetchAll();
    // Nama/ID penugasan historis lintas cabang hanya ditampilkan pada cakupan Owner.
    $pembantu = woR01bQuery($db, $nomor, $scope, 'SELECT p.id_pegawai AS id_mekanik,
            p.nama_pegawai, p.kode_cabang
        FROM work_order w JOIN mekanik_pembantu_wo d ON d.no_work_order = w.no_work_order
        LEFT JOIN pegawai p ON p.id_pegawai = d.id_mekanik'
        . ($scope === null ? '' : ' AND p.kode_cabang = w.kode_cabang')
        . ' WHERE {wo} ORDER BY p.nama_pegawai, p.id_pegawai')->fetchAll();
    $kunci = woR01bAlasanKunci($db, $nomor, $scope, $wo['status_pekerjaan'], false);
    $pilihanJasa = [];
    $pilihanMekanik = [];
    if ($kunci === '') {
        $pilihanJasa = woR01bQuery($db, $nomor, $scope, 'SELECT j.kode_jasa, j.uraian_jasa_servis
            FROM work_order w CROSS JOIN jasa_servis j
            LEFT JOIN detail_wo_jasa d ON d.no_work_order = w.no_work_order AND d.kode_jasa = j.kode_jasa
            WHERE {wo} AND d.kode_jasa IS NULL ORDER BY j.uraian_jasa_servis, j.kode_jasa')->fetchAll();
        $pilihanMekanik = woR01bQuery($db, $nomor, $scope, "SELECT m.id_mekanik, p.nama_pegawai
            FROM work_order w JOIN pegawai p ON p.kode_cabang = w.kode_cabang
            JOIN mekanik m ON m.id_mekanik = p.id_pegawai
            LEFT JOIN mekanik_pembantu_wo d ON d.no_work_order = w.no_work_order AND d.id_mekanik = m.id_mekanik
            WHERE {wo} AND m.status_aktif = 1 AND p.jenis_pegawai = 'MEKANIK'
              AND m.id_mekanik <> w.id_mekanik_pj AND d.id_mekanik IS NULL
            ORDER BY p.nama_pegawai, m.id_mekanik")->fetchAll();
    }
    return compact('jasa', 'pembantu', 'kunci', 'pilihanJasa', 'pilihanMekanik');
}

function woR01bTerbitkanToken(string $nomor, array $rincian): string
{
    $now = time();
    $tokens = array_filter($_SESSION['wo_r01b_tokens'] ?? [],
        static fn(array $meta): bool => $meta['kedaluwarsa'] >= $now);
    $nonce = bin2hex(random_bytes(24));
    $tokens[$nonce] = [
        'nomor' => $nomor,
        'kedaluwarsa' => $now + 3600,
        'jasa' => array_column($rincian['jasa'], 'biaya', 'kode_jasa'),
        'pembantu' => array_column($rincian['pembantu'], 'id_mekanik'),
    ];
    $_SESSION['wo_r01b_tokens'] = array_slice($tokens, -12, null, true);
    return $nonce;
}

/** sp_rincian_tagihan_wo menggunakan DECIMAL(12,2) untuk total jasa. */
function woR01bCekSubtotal(PDO $db, string $nomor, ?string $scope, string $baru, string $lama = '0.00'): void
{
    $cukup = woR01bQuery($db, $nomor, $scope, 'SELECT
            COALESCE(SUM(d.biaya), 0) + CAST(:baru AS DECIMAL(12,2)) - CAST(:lama AS DECIMAL(12,2))
                <= 9999999999.99
        FROM work_order w LEFT JOIN detail_wo_jasa d ON d.no_work_order = w.no_work_order
        WHERE {wo}', ['baru' => $baru, 'lama' => $lama])->fetchColumn();
    if ((int) $cukup !== 1) {
        throw new WoR01bDitolak('Subtotal jasa maksimal Rp 9.999.999.999,99.', 422, 'biaya');
    }
}

function woR01bTerapkan(PDO $db, string $nomor, ?string $scope, array $input): string
{
    if (!preg_match('/\A[A-Za-z0-9-]{1,20}\z/D', $nomor)) {
        throw new WoR01bDitolak('Nomor Work Order tidak valid.', 400);
    }
    $aksi = $input['aksi'];
    $pesan = match ($aksi) {
        'r01b_jasa_tambah' => 'Jasa servis berhasil ditambahkan.',
        'r01b_jasa_ubah' => 'Biaya jasa berhasil disimpan.',
        'r01b_jasa_hapus' => 'Jasa servis berhasil dihapus dari WO.',
        'r01b_mekanik_tambah' => 'Mekanik pembantu berhasil ditambahkan.',
        'r01b_mekanik_hapus' => 'Penugasan mekanik pembantu berhasil dilepas.',
        default => throw new WoR01bDitolak('Tindakan rincian tidak dikenali.', 400),
    };
    $nonce = $input['token_r01b'];
    $field = str_starts_with($aksi, 'r01b_jasa_') ? 'kode_jasa' : 'id_mekanik';
    $kode = $input[$field];
    $params = ['kode' => $kode];

    korporatMulaiTransaksi($db);
    try {
        $pelangganKredit = str_starts_with($aksi, 'r01b_jasa_')
            ? korporatKunciPelangganWo($db, $nomor, $scope) : null;
        // Kunci WO sebelum memeriksa status, pembayaran, dan perubahan detail.
        $wo = woR01bQuery($db, $nomor, $scope, 'SELECT w.no_work_order, w.status_pekerjaan
            FROM work_order w WHERE {wo} FOR UPDATE')->fetch();
        if (!$wo) {
            throw new WoR01bDitolak('Work Order tidak tersedia untuk akun Anda.', 403);
        }
        $kunci = woR01bAlasanKunci($db, $nomor, $scope, $wo['status_pekerjaan'], true);
        if ($kunci !== '') {
            throw new WoR01bDitolak($kunci, 409);
        }
        $meta = $_SESSION['wo_r01b_tokens'][$nonce] ?? null;
        if (!$meta || $meta['nomor'] !== $wo['no_work_order'] || $meta['kedaluwarsa'] < time()) {
            throw new WoR01bDitolak('Formulir sudah dikirim, kedaluwarsa, atau berasal dari WO lain. Muat ulang rincian.', 409);
        }
        if (!woValidasiKode($kode, 10)) {
            throw new WoR01bDitolak('Pilih data dari daftar yang tersedia.', 422, $field);
        }
        $biaya = null;
        if (in_array($aksi, ['r01b_jasa_tambah', 'r01b_jasa_ubah'], true)) {
            $biaya = woR01bNominal($input['biaya']);
            if ($biaya === null) {
                throw new WoR01bDitolak('Isi biaya 0 sampai 9.999.999.999,99 tanpa pemisah ribuan, maksimal dua desimal.', 422, 'biaya');
            }
        }

        $saldoAwal = $pelangganKredit !== null ? korporatSaldo($db, $pelangganKredit) : null;
        if ($aksi === 'r01b_jasa_tambah') {
            woR01bCekSubtotal($db, $nomor, $scope, $biaya);
            $st = woR01bQuery($db, $nomor, $scope, 'INSERT INTO detail_wo_jasa (no_work_order, kode_jasa, biaya)
                SELECT w.no_work_order, j.kode_jasa, :biaya
                FROM work_order w JOIN jasa_servis j ON j.kode_jasa = :kode WHERE {wo}',
                $params + ['biaya' => $biaya]);
            if ($st->rowCount() !== 1) {
                throw new WoR01bDitolak('Jasa sudah tidak tersedia. Pilih kembali.', 422, $field);
            }
        } elseif ($field === 'kode_jasa') {
            $lama = woR01bQuery($db, $nomor, $scope, 'SELECT d.biaya
                FROM work_order w JOIN detail_wo_jasa d ON d.no_work_order = w.no_work_order
                WHERE {wo} AND d.kode_jasa = :kode FOR UPDATE', $params)->fetchColumn();
            if ($lama === false || ($meta['jasa'][$kode] ?? null) !== $lama) {
                throw new WoR01bDitolak('Rincian jasa sudah berubah. Muat ulang sebelum menyimpan atau menghapus.', 409);
            }
            if ($aksi === 'r01b_jasa_hapus') {
                woR01bQuery($db, $nomor, $scope, 'DELETE d FROM detail_wo_jasa d
                    JOIN work_order w ON w.no_work_order = d.no_work_order
                    WHERE {wo} AND d.kode_jasa = :kode', $params);
            } elseif ($biaya !== $lama) {
                woR01bCekSubtotal($db, $nomor, $scope, $biaya, $lama);
                woR01bQuery($db, $nomor, $scope, 'UPDATE detail_wo_jasa d
                    JOIN work_order w ON w.no_work_order = d.no_work_order
                    SET d.biaya = :biaya WHERE {wo} AND d.kode_jasa = :kode',
                    $params + ['biaya' => $biaya]);
            }
        } elseif ($aksi === 'r01b_mekanik_tambah') {
            $mekanik = woR01bQuery($db, $nomor, $scope, "SELECT m.id_mekanik
                FROM work_order w JOIN pegawai p ON p.kode_cabang = w.kode_cabang
                JOIN mekanik m ON m.id_mekanik = p.id_pegawai
                WHERE {wo} AND m.id_mekanik = :kode AND m.status_aktif = 1
                  AND p.jenis_pegawai = 'MEKANIK' AND m.id_mekanik <> w.id_mekanik_pj
                LOCK IN SHARE MODE", $params)->fetchColumn();
            if ($mekanik === false) {
                throw new WoR01bDitolak('Pilih mekanik aktif dari cabang WO selain mekanik utama.', 422, $field);
            }
            woR01bQuery($db, $nomor, $scope, 'INSERT INTO mekanik_pembantu_wo (no_work_order, id_mekanik)
                SELECT w.no_work_order, :kode FROM work_order w WHERE {wo}', ['kode' => $mekanik]);
        } else {
            $batasPegawai = $scope === null ? '' : ' AND p.kode_cabang = w.kode_cabang';
            if (!in_array($kode, $meta['pembantu'], true) ||
                woR01bQuery($db, $nomor, $scope, 'DELETE d FROM mekanik_pembantu_wo d
                    JOIN work_order w ON w.no_work_order = d.no_work_order
                    JOIN pegawai p ON p.id_pegawai = d.id_mekanik
                    WHERE {wo} AND d.id_mekanik = :kode' . $batasPegawai, $params)->rowCount() !== 1) {
                throw new WoR01bDitolak('Penugasan sudah berubah atau tidak tersedia. Muat ulang rincian.', 409);
            }
        }
        if ($pelangganKredit !== null && $saldoAwal !== null) {
            korporatCekPerubahanBiaya($db, $pelangganKredit, $saldoAwal);
        }
        $db->commit();
        unset($_SESSION['wo_r01b_tokens'][$nonce]);
        return $pesan;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof KorporatDitolak) {
            throw new WoR01bDitolak($e->getMessage(), 422, 'biaya');
        }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            throw new WoR01bDitolak('Data ini sudah tercatat pada WO tersebut.', 422, $field);
        }
        throw $e;
    }
}
