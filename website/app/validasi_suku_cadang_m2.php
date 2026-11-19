<?php
declare(strict_types=1);

/** Validasi nama master barang mengikuti skema suku_cadang VARCHAR(150). */
function validasiSukuCadangM2(array $input): array
{
    $nama = trim((string) ($input['nama_suku_cadang'] ?? ''));
    $errors = [];
    $n = preg_match_all('/./us', $nama);
    if ($nama === '') {
        $errors['nama_suku_cadang'] = 'Nama suku cadang wajib diisi.';
    } elseif ($n === false || $n < 3 || $n > 150 || strlen($nama) > 600) {
        $errors['nama_suku_cadang'] = 'Nama suku cadang harus terdiri dari 3–150 karakter.';
    } elseif (preg_match('/[\x00-\x1F\x7F]/u', $nama)) {
        $errors['nama_suku_cadang'] = 'Nama suku cadang tidak boleh menggunakan baris baru atau karakter kontrol.';
    }
    return ['data'=>['nama_suku_cadang'=>$nama], 'errors'=>$errors];
}

/** Kode otomatis SP-xxxx; tidak mengubah format kode lama. */
function kodeSukuCadangBerikutnyaM2(array $codes): string
{
    $max = 999;
    foreach ($codes as $code) {
        if (preg_match('/\ASP-([0-9]{4,12})\z/D', (string) $code, $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    if ($max >= 999999999999) {
        throw new RuntimeException('Nomor kode suku cadang sudah penuh.');
    }
    return 'SP-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
}

function kodeSukuCadangValidM2(string $kode): bool
{
    // Kode existing dari skema VARCHAR(15) bisa bervariasi; whitelist aman di URL/POST.
    return (bool) preg_match('/\A[A-Za-z0-9_-]{1,15}\z/D', $kode);
}
