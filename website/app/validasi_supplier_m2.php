<?php
declare(strict_types=1);

/** Kolom supplier.nama_supplier VARCHAR(120), tanpa menambah atribut di DB. */
function validasiNamaSupplierM2(mixed $value): array
{
    $nama = is_string($value) ? trim($value) : '';
    $errors = [];
    if ($nama === '') {
        $errors['nama_supplier'] = 'Nama supplier wajib diisi.';
    } elseif (!preg_match('//u', $nama)) {
        $errors['nama_supplier'] = 'Nama supplier harus berupa teks yang valid.';
    } else {
        $characters = preg_match_all('/./us', $nama);
        if ($characters === false || $characters < 3 || $characters > 120 || strlen($nama) > 480) {
            $errors['nama_supplier'] = 'Masukkan 3–120 karakter untuk nama supplier.';
        } elseif (preg_match('/[\x00-\x1F\x7F]/u', $nama)) {
            $errors['nama_supplier'] = 'Nama supplier tidak boleh berisi baris baru atau karakter kontrol.';
        }
    }
    return ['data' => ['nama_supplier' => $nama], 'errors' => $errors];
}

function idSupplierValidM2(string $value): bool
{
    // Panjang berasal dari supplier.id_supplier VARCHAR(10).
    return (bool) preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D', $value);
}

/** Format dari data awal SUP001–SUP005; tetap aman terhadap kode yang bervariasi. */
function idSupplierBerikutnyaM2(array $existing): string
{
    $max = 0;
    foreach ($existing as $id) {
        if (preg_match('/\ASUP(\d{1,7})\z/D', (string)$id, $matches)) {
            $max = max($max, (int) $matches[1]);
        }
    }
    if ($max >= 9999999) {
        throw new RuntimeException('Kode supplier sudah mencapai batas penomoran.');
    }
    return 'SUP' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
}

/** Normalisasi pilihan checkbox dengan batas maksimum semua barang yang ditampilkan. */
function validasiPilihanBarangSupplierM2(mixed $input, array $availableCodes): array
{
    if (!is_array($input)) {
        return ['data'=>[], 'error'=>'Daftar barang tidak valid. Muat ulang halaman.'];
    }
    $valid = array_fill_keys(array_map('strval', $availableCodes), true);
    $picked = [];
    foreach ($input as $code) {
        if (!is_string($code) || !isset($valid[$code])) {
            return ['data'=>[], 'error'=>'Ada barang yang tidak terdaftar. Muat ulang daftar.'];
        }
        $picked[$code] = true;
    }
    return ['data'=>array_keys($picked), 'error'=>null];
}

/** Untuk menyimpan relasi many-to-many tanpa menghapus seluruh relasi lalu membuat ulang. */
function perubahanBarangSupplierM2(array $existing, array $chosen): array
{
    $old = array_fill_keys(array_map('strval', $existing), true);
    $new = array_fill_keys(array_map('strval', $chosen), true);
    return [
        'tambah'=>array_keys(array_diff_key($new, $old)),
        'lepas'=>array_keys(array_diff_key($old, $new)),
    ];
}
