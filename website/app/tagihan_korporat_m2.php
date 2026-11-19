<?php
declare(strict_types=1);
require_once __DIR__ . '/pembayaran_r01f.php';

function tagihanSen(string $nilai): int
{
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?\z/D', $nilai)) {
        throw new OperasionalDitolak('Nilai tagihan berada di luar kapasitas yang didukung.');
    }
    $parts = explode('.', $nilai, 2);
    return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '0', 2, '0');
}

function tagihanDesimal(int $sen): string
{
    if ($sen < 0 || $sen > 99999999999999) {
        throw new OperasionalDitolak('Nilai tagihan melampaui kapasitas nominal.');
    }
    return intdiv($sen, 100) . '.' . str_pad((string) ($sen % 100), 2, '0', STR_PAD_LEFT);
}

function tagihanPeriode(PDO $db, string $bulan): array
{
    if (!preg_match('/\A[1-9][0-9]{3}-(?:0[1-9]|1[0-2])\z/D', $bulan)) {
        throw new InvalidArgumentException('Pilih bulan tagihan yang valid.');
    }
    $mulai = $bulan . '-01';
    $akhir = (new DateTimeImmutable($mulai))->modify('+1 month')->format('Y-m-d');
    if ($akhir > (string) $db->query('SELECT CURDATE()')->fetchColumn()) {
        throw new InvalidArgumentException('Tagihan kolektif diterbitkan setelah bulan layanan berakhir.');
    }
    return [$mulai, $akhir];
}

function tagihanCalon(PDO $db, string $id, string $bulan, bool $locking = false): array
{
    [$mulai, $akhir] = tagihanPeriode($db, $bulan);
    $stmt = $db->prepare("SELECT w.no_work_order,w.no_polisi,w.kode_cabang,w.tanggal_selesai
        FROM work_order w JOIN work_order_korporat k ON k.no_work_order=w.no_work_order
        WHERE k.id_pelanggan=:id AND w.status_pekerjaan='SELESAI'
        AND w.tanggal_selesai>=:mulai AND w.tanggal_selesai<:akhir
        AND NOT EXISTS (SELECT 1 FROM detail_tagihan_korporat d WHERE d.no_work_order=w.no_work_order)
        AND NOT EXISTS (SELECT 1 FROM pembayaran p WHERE p.no_work_order=w.no_work_order)
        ORDER BY w.no_work_order" . ($locking ? ' FOR UPDATE' : ''));
    $stmt->execute(['id'=>$id,'mulai'=>$mulai,'akhir'=>$akhir]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $detail = ksPayAmbilTagihan($db, $row['no_work_order'], $locking);
        $row['nilai_final'] = tagihanDesimal($detail['jasa'] + $detail['spare']);
    }
    unset($row);
    return $rows;
}

function tagihanFingerprint(array $rows): string
{
    return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
}

function tagihanTotal(array $rows): int
{
    $total = 0;
    foreach ($rows as $row) {
        $total += tagihanSen($row['nilai_final']);
    }
    tagihanDesimal($total);
    return $total;
}

function tagihanTerbitkan(PDO $db, array $akun, string $id, string $bulan, string $snapshot): string
{
    opsCabang($akun, ['rl_owner_ops']);
    korporatMulaiTransaksi($db);
    try {
        if (!korporatKunciPelanggan($db, $id) || !korporatAdaProfil($db, $id)) {
            throw new OperasionalDitolak('Pelanggan korporat tidak ditemukan.', 404);
        }
        [$mulai] = tagihanPeriode($db, $bulan);
        $stmt = $db->prepare('SELECT id_tagihan FROM tagihan_korporat WHERE id_pelanggan=:id AND periode=:periode');
        $stmt->execute(['id'=>$id,'periode'=>$mulai]);
        if ($stmt->fetchColumn() !== false) {
            throw new OperasionalDitolak('Tagihan perusahaan untuk bulan ini sudah diterbitkan.');
        }
        $rows = tagihanCalon($db, $id, $bulan, true);
        if (!$rows) {
            throw new OperasionalDitolak('Belum ada WO selesai yang dapat ditagihkan pada bulan ini.');
        }
        if (!hash_equals(tagihanFingerprint($rows), $snapshot)) {
            throw new OperasionalDitolak('Daftar WO berubah. Periksa kembali pratinjau tagihan.');
        }
        tagihanTotal($rows);
        $stmt = $db->prepare('INSERT INTO tagihan_korporat (id_pelanggan,periode) VALUES (:id,:periode)');
        $stmt->execute(['id'=>$id,'periode'=>$mulai]);
        $nomor = $db->lastInsertId();
        $stmt = $db->prepare('INSERT INTO detail_tagihan_korporat
            (no_work_order,id_pelanggan,id_tagihan,nilai_final) VALUES (:wo,:id,:nomor,:nilai)');
        foreach ($rows as $row) {
            $stmt->execute(['wo'=>$row['no_work_order'],'id'=>$id,'nomor'=>$nomor,'nilai'=>$row['nilai_final']]);
        }
        $db->commit();
        return $nomor;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function tagihanAmbil(PDO $db, string $nomor, ?string $scope, bool $locking = false): array|false
{
    $sql = 'SELECT t.*,p.nama_pelanggan FROM tagihan_korporat t
        JOIN pelanggan p ON p.id_pelanggan=t.id_pelanggan WHERE t.id_tagihan=:nomor';
    $args = ['nomor'=>$nomor];
    if ($scope !== null) {
        $sql .= ' AND EXISTS (SELECT 1 FROM detail_tagihan_korporat d
            JOIN work_order w ON w.no_work_order=d.no_work_order
            WHERE d.id_tagihan=t.id_tagihan AND w.kode_cabang=:cabang)
            AND NOT EXISTS (SELECT 1 FROM detail_tagihan_korporat d2
            JOIN work_order w2 ON w2.no_work_order=d2.no_work_order
            WHERE d2.id_tagihan=t.id_tagihan AND w2.kode_cabang<>:lain)';
        $args += ['cabang'=>$scope,'lain'=>$scope];
    }
    $stmt = $db->prepare($sql . ($locking ? ' FOR UPDATE' : ''));
    $stmt->execute($args);
    return $stmt->fetch();
}

function tagihanRincian(PDO $db, string $nomor): array
{
    $stmt = $db->prepare('SELECT d.no_work_order,d.nilai_final,w.no_polisi,w.kode_cabang,
        w.tanggal_selesai,c.nama_cabang FROM detail_tagihan_korporat d
        JOIN work_order w ON w.no_work_order=d.no_work_order
        JOIN cabang c ON c.kode_cabang=w.kode_cabang WHERE d.id_tagihan=:nomor ORDER BY d.no_work_order');
    $stmt->execute(['nomor'=>$nomor]);
    return $stmt->fetchAll();
}

function tagihanLunasi(PDO $db, array $akun, string $nomor, string $metode, string $snapshot): void
{
    $scope = opsCabang($akun, ['rl_owner_ops','rl_cashier_ops']);
    if (!in_array($metode, KSPAY_METODE, true)) {
        throw new InvalidArgumentException('Pilih metode pembayaran yang tersedia.');
    }
    $header = tagihanAmbil($db, $nomor, $scope);
    if (!$header) throw new OperasionalDitolak('Tagihan tidak tersedia untuk akun ini.', 403);
    korporatMulaiTransaksi($db);
    try {
        if (!korporatKunciPelanggan($db, $header['id_pelanggan'])) {
            throw new OperasionalDitolak('Pelanggan korporat tidak ditemukan.', 404);
        }
        $header = tagihanAmbil($db, $nomor, $scope, true);
        if (!$header || $header['status_pembayaran'] !== 'BELUM_LUNAS') {
            throw new OperasionalDitolak('Tagihan sudah lunas atau sudah berubah.');
        }
        $rows = tagihanRincian($db, $nomor);
        if (!$rows || !hash_equals(tagihanFingerprint($rows), $snapshot)) {
            throw new OperasionalDitolak('Rincian tagihan berubah. Muat ulang sebelum melunasi.');
        }
        tagihanTotal($rows);
        $stmt = $db->prepare("UPDATE tagihan_korporat SET status_pembayaran='LUNAS',
            dibayar_pada=CURRENT_TIMESTAMP,metode_pembayaran=:metode
            WHERE id_tagihan=:nomor AND status_pembayaran='BELUM_LUNAS'");
        $stmt->execute(['metode'=>$metode,'nomor'=>$nomor]);
        if ($stmt->rowCount() !== 1) throw new OperasionalDitolak('Tagihan telah diproses oleh sesi lain.');
        $stmt = $db->prepare('INSERT INTO penerimaan_kas (id_tagihan,id_akun) VALUES (:nomor,:akun)');
        $stmt->execute(['nomor'=>$nomor,'akun'=>$akun['id_akun']]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
