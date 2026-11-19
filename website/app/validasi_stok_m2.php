<?php
declare(strict_types=1);

/** Validasi angka stok dan batas minimum; database menggunakan INT UNSIGNED. */
function angkaStokKalawa(mixed $raw, string $label, bool $positif = false): int
{
    if (!is_string($raw) && !is_int($raw)) {
        throw new InvalidArgumentException("$label harus berupa angka bulat.");
    }
    $value = trim((string) $raw);
    if ($value === '' || !preg_match('/\A[0-9]{1,10}\z/D', $value)) {
        throw new InvalidArgumentException("$label harus berupa angka bulat tanpa titik atau koma.");
    }
    $number = (int) $value;
    if ($number > 4294967295 || ($positif && $number === 0)) {
        throw new InvalidArgumentException("$label di luar batas yang diizinkan.");
    }
    return $number;
}

function kodeCabangStokValid(string $value): bool
{
    return (bool) preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D', $value);
}
function kodeBarangStokValid(string $value): bool
{
    return (bool) preg_match('/\A[A-Za-z0-9_-]{1,15}\z/D', $value);
}

function validasiCatatanStok(mixed $raw): string
{
    $text = is_string($raw) ? trim($raw) : '';
    if (!preg_match('//u', $text) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text)) {
        throw new InvalidArgumentException('Catatan perubahan stok mengandung karakter tidak valid.');
    }
    $len = preg_match_all('/./us', $text);
    if ($len === false || $len < 5 || $len > 255 || strlen($text) > 765) {
        throw new InvalidArgumentException('Catatan wajib 5–255 karakter, misalnya "Barang diterima dari supplier".');
    }
    return $text;
}

/** Membuat rancangan nilai baru. Semua operasi memakai nilai database yang terkunci. */
function hasilPerubahanStok(string $aksi, ?array $existing, string $jumlah, string $minimum): array
{
    if (!in_array($aksi, ['awal', 'masuk', 'koreksi', 'minimum'], true)) {
        throw new InvalidArgumentException('Pilih tindakan persediaan yang tersedia.');
    }
    if ($aksi === 'awal' && $existing !== null) {
        throw new InvalidArgumentException('Stok cabang sudah tercatat. Gunakan Barang Masuk atau Koreksi Stok.');
    }
    if ($aksi !== 'awal' && $existing === null) {
        throw new InvalidArgumentException('Stok barang belum dicatat di cabang ini. Catat stok awal dahulu.');
    }
    $before = $existing === null ? 0 : (int) $existing['jumlah_stok'];
    $oldMin = $existing === null ? 0 : (int) $existing['stok_minimum'];
    $after = $before;
    $newMin = $oldMin;
    if ($aksi === 'awal') {
        $after = angkaStokKalawa($jumlah, 'Stok awal');
        $newMin = angkaStokKalawa($minimum, 'Batas minimum');
    } elseif ($aksi === 'masuk') {
        $qty = angkaStokKalawa($jumlah, 'Jumlah barang masuk', true);
        if ($qty > (4294967295 - $before)) {
            throw new InvalidArgumentException('Jumlah barang masuk melebihi kapasitas penyimpanan stok.');
        }
        $after = $before + $qty;
    } elseif ($aksi === 'koreksi') {
        $after = angkaStokKalawa($jumlah, 'Stok fisik hasil pemeriksaan');
    } else {
        $newMin = angkaStokKalawa($minimum, 'Batas minimum');
    }
    if ($existing !== null && $after === $before && $newMin === $oldMin) {
        throw new InvalidArgumentException('Tidak ada perubahan jumlah atau batas minimum.');
    }
    return ['sebelum'=>$before, 'sesudah'=>$after, 'minimum_sebelum'=>$oldMin,
            'minimum_sesudah'=>$newMin, 'selisih'=>$after-$before];
}
