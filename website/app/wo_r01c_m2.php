<?php
declare(strict_types=1);
// Helper kueri, nominal, dan otorisasi dimuat dari wo_r01b_m2.php oleh controller.

// Trigger stok memakai variabel INT bertanda untuk jumlah stok.
const WO_R01C_STOK_MAKSIMUM = 2147483647;

function woR01cJumlah(string $input): ?int
{
    $input = trim($input);
    if (!preg_match('/\A[0-9]{1,10}\z/D', $input)
        || (float) $input < 1 || (float) $input > WO_R01C_STOK_MAKSIMUM) {
        return null;
    }
    return (int) $input;
}

function woR01cTerbitkanToken(string $nomor): string
{
    $now = time();
    $tokens = array_filter($_SESSION['wo_r01c_tokens'] ?? [],
        static fn(array $meta): bool => $meta['kedaluwarsa'] >= $now);
    $nonce = bin2hex(random_bytes(24));
    $tokens[$nonce] = ['nomor' => $nomor, 'kedaluwarsa' => $now + 3600];
    $_SESSION['wo_r01c_tokens'] = array_slice($tokens, -12, null, true);
    return $nonce;
}

function woR01cTokenSah(string $nonce, string $nomor): bool
{
    $meta = $_SESSION['wo_r01c_tokens'][$nonce] ?? null;
    return $meta !== null && $meta['nomor'] === $nomor && $meta['kedaluwarsa'] >= time();
}

function woR01cBaca(PDO $db, array $wo, ?string $scope): array
{
    $nomor = $wo['no_work_order'];
    $barang = woR01bQuery($db, $nomor, $scope, 'SELECT p.kode_suku_cadang, p.nama_suku_cadang,
            d.qty, d.harga_satuan, d.qty * d.harga_satuan AS subtotal_item,
            SUM(d.qty * d.harga_satuan) OVER () AS subtotal
        FROM work_order w JOIN detail_wo_suku_cadang d ON d.no_work_order = w.no_work_order
        JOIN suku_cadang p ON p.kode_suku_cadang = d.kode_suku_cadang
        WHERE {wo} ORDER BY p.nama_suku_cadang, p.kode_suku_cadang')->fetchAll();
    $kunci = woR01bAlasanKunci($db, $nomor, $scope, $wo['status_pekerjaan'], false);
    $pilihan = [];
    if ($kunci === '') {
        $pilihan = woR01bQuery($db, $nomor, $scope, 'SELECT p.kode_suku_cadang, p.nama_suku_cadang, s.jumlah_stok
            FROM work_order w JOIN stok_cabang s ON s.kode_cabang = w.kode_cabang
            JOIN suku_cadang p ON p.kode_suku_cadang = s.kode_suku_cadang
            LEFT JOIN detail_wo_suku_cadang d ON d.no_work_order = w.no_work_order
                AND d.kode_suku_cadang = p.kode_suku_cadang
            WHERE {wo} AND s.jumlah_stok BETWEEN 1 AND :maksimum AND d.kode_suku_cadang IS NULL
            ORDER BY p.nama_suku_cadang, p.kode_suku_cadang',
            ['maksimum' => WO_R01C_STOK_MAKSIMUM])->fetchAll();
    }
    return compact('barang', 'kunci', 'pilihan');
}

function woR01cTerapkan(PDO $db, string $nomor, ?string $scope, array $input): void
{
    if (!preg_match('/\A[A-Za-z0-9-]{1,20}\z/D', $nomor)) {
        throw new WoR01bDitolak('Nomor Work Order tidak valid.', 400);
    }
    $kode = $input['kode_suku_cadang'];
    $nonce = $input['token_r01c'];

    korporatMulaiTransaksi($db);
    try {
        $pelangganKredit = korporatKunciPelangganWo($db, $nomor, $scope);
        $wo = woR01bQuery($db, $nomor, $scope, 'SELECT w.no_work_order, w.kode_cabang, w.status_pekerjaan
            FROM work_order w WHERE {wo} FOR UPDATE')->fetch();
        if (!$wo) {
            throw new WoR01bDitolak('Work Order tidak tersedia untuk akun Anda.', 403);
        }
        $kunci = woR01bAlasanKunci($db, $nomor, $scope, $wo['status_pekerjaan'], true);
        if ($kunci !== '') {
            throw new WoR01bDitolak($kunci, 409);
        }
        if (!woR01cTokenSah($nonce, $wo['no_work_order'])) {
            throw new WoR01bDitolak('Formulir sudah dikirim atau kedaluwarsa. Buka formulir baru dari daftar.', 409);
        }
        if (!woValidasiKode($kode, 15)) {
            throw new WoR01bDitolak('Pilih suku cadang dari daftar yang tersedia.', 422, 'kode_suku_cadang');
        }
        $qty = woR01cJumlah($input['qty']);
        if ($qty === null) {
            throw new WoR01bDitolak('Isi jumlah dengan bilangan bulat positif dalam batas stok cabang.', 422, 'qty');
        }
        $harga = woR01bNominal($input['harga_satuan']);
        if ($harga === null) {
            throw new WoR01bDitolak('Isi harga 0 sampai 9.999.999.999,99 tanpa pemisah ribuan, maksimal dua desimal.', 422, 'harga_satuan');
        }

        $tercatat = woR01bQuery($db, $nomor, $scope, 'SELECT d.kode_suku_cadang
            FROM work_order w JOIN detail_wo_suku_cadang d ON d.no_work_order = w.no_work_order
            WHERE {wo} AND d.kode_suku_cadang = :kode FOR UPDATE', ['kode' => $kode])->fetchColumn();
        if ($tercatat !== false) {
            throw new WoR01bDitolak('Suku cadang ini sudah tercatat pada WO.', 422, 'kode_suku_cadang');
        }

        // Cabang berasal dari WO terkunci. Ambil stok dengan locking read sebelum membuat snapshot.
        $stokQuery = $db->prepare('SELECT kode_suku_cadang, jumlah_stok FROM stok_cabang
            WHERE kode_cabang = :cabang AND kode_suku_cadang = :kode FOR UPDATE');
        $stokParams = ['cabang' => $wo['kode_cabang'], 'kode' => $kode];
        $stokQuery->execute($stokParams);
        $stok = $stokQuery->fetch();
        $stokQuery->closeCursor();
        if (!$stok || (float) $stok['jumlah_stok'] > WO_R01C_STOK_MAKSIMUM) {
            throw new WoR01bDitolak('Stok suku cadang ini tidak tersedia untuk pemakaian di cabang WO.', 422, 'kode_suku_cadang');
        }
        $sebelum = (int) $stok['jumlah_stok'];
        if ($qty > $sebelum) {
            throw new WoR01bDitolak("Stok cabang saat ini $sebelum unit. Sesuaikan jumlah pemakaian.", 422, 'qty');
        }

        // sp_rincian_tagihan_wo menyimpan subtotal suku cadang dalam DECIMAL(12,2).
        $cukup = woR01bQuery($db, $nomor, $scope, 'SELECT
            COALESCE(SUM(d.qty * d.harga_satuan), 0) + CAST(:qty AS UNSIGNED) * CAST(:harga AS DECIMAL(12,2))
                <= 9999999999.99
            FROM work_order w LEFT JOIN detail_wo_suku_cadang d ON d.no_work_order = w.no_work_order
            WHERE {wo}', ['qty' => $qty, 'harga' => $harga])->fetchColumn();
        if ((int) $cukup !== 1) {
            throw new WoR01bDitolak('Subtotal suku cadang maksimal Rp 9.999.999.999,99.', 422, 'harga_satuan');
        }

        $saldoAwal = $pelangganKredit !== null ? korporatSaldo($db, $pelangganKredit) : null;
        // Trigger yang terpasang mengurangi stok. PHP hanya menulis detail dan memeriksa hasilnya.
        $simpan = woR01bQuery($db, $nomor, $scope, 'INSERT INTO detail_wo_suku_cadang
                (no_work_order, kode_suku_cadang, qty, harga_satuan)
            SELECT w.no_work_order, :kode, :qty, :harga FROM work_order w WHERE {wo}',
            ['kode' => $stok['kode_suku_cadang'], 'qty' => $qty, 'harga' => $harga]);
        $stokQuery->execute($stokParams);
        $sesudah = $stokQuery->fetch();
        $stokQuery->closeCursor();
        if ($simpan->rowCount() !== 1 || !$sesudah || (int) $sesudah['jumlah_stok'] !== $sebelum - $qty) {
            throw new WoR01bDitolak('Pengurangan stok tidak sesuai. Transaksi dibatalkan. Hubungi pengelola.', 503);
        }
        if ($pelangganKredit !== null && $saldoAwal !== null) {
            korporatCekPerubahanBiaya($db, $pelangganKredit, $saldoAwal);
        }
        $db->commit();
        unset($_SESSION['wo_r01c_tokens'][$nonce]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($e instanceof KorporatDitolak) {
            throw new WoR01bDitolak($e->getMessage(), 422, 'harga_satuan');
        }
        if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
            throw new WoR01bDitolak('Suku cadang ini sudah tercatat pada WO.', 422, 'kode_suku_cadang');
        }
        throw $e;
    }
}
