<?php
declare(strict_types=1);

/** Validasi data layanan; tidak membutuhkan database. */
function validasiJasaM2(array $input): array
{
    $nama = trim((string) ($input['uraian_jasa_servis'] ?? ''));
    $errors = [];
    // Hitung karakter Unicode, bukan byte UTF-8. Extension mbstring tidak diwajibkan.
    $chars = preg_match_all('/./us', $nama);
    if ($nama === '') {
        $errors['uraian_jasa_servis'] = 'Nama layanan wajib diisi.';
    } elseif ($chars === false || $chars < 3 || $chars > 150 || strlen($nama) > 600) {
        $errors['uraian_jasa_servis'] = 'Nama layanan harus 3–150 karakter.';
    } elseif (preg_match('/[\x00-\x1F\x7F]/u', $nama)) {
        $errors['uraian_jasa_servis'] = 'Nama layanan tidak boleh berisi karakter kontrol.';
    }
    return ['data' => ['uraian_jasa_servis' => $nama], 'errors' => $errors];
}

function kodeJasaBerikutnyaM2(array $codes): string
{
    $n = 0;
    foreach ($codes as $code) {
        if (preg_match('/\AJ([0-9]{1,9})\z/D', (string) $code, $m)) {
            $n = max($n, (int) $m[1]);
        }
    }
    if ($n >= 999999999) {
        throw new RuntimeException('Kapasitas nomor kode jasa sudah penuh.');
    }
    return sprintf('J%03d', $n + 1);
}
