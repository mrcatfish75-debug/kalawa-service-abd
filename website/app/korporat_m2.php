<?php
declare(strict_types=1);

final class KorporatDitolak extends DomainException
{
    public function __construct(string $pesan, public readonly string $field = 'id_pelanggan')
    {
        parent::__construct($pesan);
    }
}

function korporatMulaiTransaksi(PDO $db): void
{
    // Pembacaan saldo setelah antre kunci harus melihat perubahan yang baru commit.
    $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    $db->beginTransaction();
}

function korporatKunciPelanggan(PDO $db, string $id): bool
{
    if (!$db->inTransaction()) {
        throw new LogicException('Kunci pelanggan memerlukan transaksi aktif.');
    }
    $stmt = $db->prepare('SELECT id_pelanggan FROM pelanggan WHERE id_pelanggan=:id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    return $stmt->fetchColumn() !== false;
}

function korporatAdaProfil(PDO $db, string $id): bool
{
    $stmt = $db->prepare('SELECT id_pelanggan FROM pelanggan_korporat WHERE id_pelanggan=:id');
    $stmt->execute(['id' => $id]);
    return $stmt->fetchColumn() !== false;
}

/** Tautan WO dan kontrak dibentuk dalam transaksi pembuatan WO dan bersifat tetap. */
function korporatKunciPelangganWo(
    PDO $db, string $nomor, ?string $cabang, ?string $mekanik = null
): ?string {
    $sql = 'SELECT k.id_pelanggan FROM work_order_korporat k
        JOIN work_order w ON w.no_work_order=k.no_work_order WHERE w.no_work_order=:nomor';
    $params = ['nomor' => $nomor];
    if ($cabang !== null) {
        $sql .= ' AND w.kode_cabang=:cabang';
        $params['cabang'] = $cabang;
    }
    if ($mekanik !== null) {
        $sql .= ' AND w.id_mekanik_pj=:mekanik';
        $params['mekanik'] = $mekanik;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        return null;
    }
    if (!korporatKunciPelanggan($db, $id)) {
        throw new RuntimeException('Pelanggan korporat terkait WO tidak tersedia.');
    }
    return $id;
}

function korporatTanggalSah(string $tanggal): bool
{
    return preg_match('/\A([1-9][0-9]{3})-([0-9]{2})-([0-9]{2})\z/D', $tanggal, $m) === 1
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

function korporatTambahKontrak(PDO $db, string $id, string $mulai, string $sampai, string $plafon): void
{
    if (!korporatTanggalSah($mulai)) {
        throw new KorporatDitolak('Isi tanggal mulai yang valid.', 'berlaku_mulai');
    }
    if (!korporatTanggalSah($sampai) || $sampai < $mulai) {
        throw new KorporatDitolak('Tanggal akhir harus pada atau setelah tanggal mulai.', 'berlaku_sampai');
    }
    $plafon = str_replace(',', '.', $plafon);
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?\z/D', $plafon)) {
        throw new KorporatDitolak('Isi plafon 0 sampai Rp999.999.999.999,99 tanpa pemisah ribuan.', 'plafon_kredit');
    }

    korporatMulaiTransaksi($db);
    try {
        if (!korporatKunciPelanggan($db, $id) || !korporatAdaProfil($db, $id)) {
            throw new KorporatDitolak('Pilih pelanggan yang mempunyai profil korporat.');
        }
        $stmt = $db->prepare('SELECT id_kontrak FROM kontrak_korporat WHERE id_pelanggan=:id
            AND berlaku_mulai<=:sampai AND berlaku_sampai>=:mulai LIMIT 1');
        $periode = ['id'=>$id, 'mulai'=>$mulai, 'sampai'=>$sampai];
        $stmt->execute($periode);
        if ($stmt->fetchColumn() !== false) {
            throw new KorporatDitolak('Periode ini tumpang tindih dengan kontrak perusahaan yang sudah tercatat.', 'berlaku_mulai');
        }
        $stmt = $db->prepare('INSERT INTO kontrak_korporat (id_pelanggan, berlaku_mulai, berlaku_sampai, plafon_kredit)
            VALUES (:id,:mulai,:sampai,:plafon)');
        $stmt->execute($periode + ['plafon'=>$plafon]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function korporatKontrakAktif(PDO $db, string $id, string $tanggal): array
{
    $stmt = $db->prepare('SELECT id_kontrak, plafon_kredit FROM kontrak_korporat WHERE id_pelanggan=:id
        AND berlaku_mulai<=:mulai AND berlaku_sampai>=:sampai ORDER BY berlaku_mulai DESC LIMIT 2');
    $stmt->execute(['id'=>$id, 'mulai'=>$tanggal, 'sampai'=>$tanggal]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        throw new KorporatDitolak('Perusahaan belum memiliki kontrak aktif pada tanggal transaksi.');
    }
    if (count($rows) !== 1) {
        throw new KorporatDitolak('Kontrak aktif bertumpuk. Minta Owner memeriksa periode kontrak.');
    }
    return $rows[0];
}

function korporatSaldo(PDO $db, string $id): string
{
    $sql = "SELECT CAST(COALESCE(SUM(CASE
            WHEN status_pekerjaan='DIBATALKAN' THEN 0
            WHEN nilai_final IS NOT NULL THEN CASE WHEN status_tagihan='LUNAS' THEN 0 ELSE nilai_final END
            WHEN status_pekerjaan='SELESAI' THEN aktual
            ELSE GREATEST(estimasi_biaya, aktual)
        END), 0) AS DECIMAL(38,2))
        FROM (
            SELECT w.status_pekerjaan, w.estimasi_biaya, d.nilai_final, t.status_pembayaran AS status_tagihan,
                COALESCE((SELECT SUM(j.biaya) FROM detail_wo_jasa j WHERE j.no_work_order=w.no_work_order), 0)
                + COALESCE((SELECT SUM(s.qty*s.harga_satuan) FROM detail_wo_suku_cadang s
                    WHERE s.no_work_order=w.no_work_order), 0) AS aktual
            FROM work_order_korporat k JOIN work_order w ON w.no_work_order=k.no_work_order
            LEFT JOIN detail_tagihan_korporat d ON d.no_work_order=k.no_work_order
            LEFT JOIN tagihan_korporat t ON t.id_tagihan=d.id_tagihan
            WHERE k.id_pelanggan=:id
        ) saldo_per_wo";
    $stmt = $db->prepare($sql);
    $stmt->execute(['id'=>$id]);
    return (string) $stmt->fetchColumn();
}

function korporatCekPlafon(PDO $db, string $saldo, string $tambahan, string $plafon): void
{
    $stmt = $db->prepare('SELECT CAST(:saldo AS DECIMAL(38,2)) + CAST(:tambahan AS DECIMAL(38,2))
        <= CAST(:plafon AS DECIMAL(14,2))');
    $stmt->execute(['saldo'=>$saldo, 'tambahan'=>$tambahan, 'plafon'=>$plafon]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new KorporatDitolak('Nominal ini melampaui sisa plafon kredit perusahaan.', 'estimasi_biaya');
    }
}

function korporatCekPerubahanBiaya(PDO $db, string $id, string $sebelum): void
{
    $sesudah = korporatSaldo($db, $id);
    $stmt = $db->prepare('SELECT CAST(:sesudah AS DECIMAL(38,2)) > CAST(:sebelum AS DECIMAL(38,2))');
    $stmt->execute(['sesudah'=>$sesudah, 'sebelum'=>$sebelum]);
    if ((int) $stmt->fetchColumn() === 1) {
        $tanggal = (string) $db->query('SELECT CURDATE()')->fetchColumn();
        $kontrak = korporatKontrakAktif($db, $id, $tanggal);
        korporatCekPlafon($db, $sesudah, '0', $kontrak['plafon_kredit']);
    }
}

