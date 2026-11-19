<?php
declare(strict_types=1);
/** Validasi data formulir pelanggan, independen dari koneksi database. */
function panjangTeksKalawa(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

function jenisIdentitasPelangganM2(bool $korporat): array
{
    return $korporat ? ['NIB', 'NPWP_BADAN'] : ['KTP', 'SIM', 'PASPOR'];
}

/** @return array{data: array<string,string>, errors: array<string,string>} */
function validasiInputPelangganM2(array $source, bool $korporat = false): array
{
    $keys = ['id_pelanggan', 'nama_pelanggan', 'no_telepon', 'jenis_identitas',
             'no_identitas', 'alamat', 'kode_cabang_favorit'];
    $data = [];
    foreach ($keys as $key) {
        $input = $source[$key] ?? '';
        $data[$key] = is_string($input) || is_numeric($input) ? trim((string) $input) : '';
    }
    $errors = [];
    if (!preg_match('/\A[A-Za-z0-9._-]{3,15}\z/D', $data['id_pelanggan'])) {
        $errors['id_pelanggan'] = 'ID harus 3–15 karakter, hanya huruf A–Z, angka, titik (.), tanda hubung (-), atau garis bawah (_).';
    }
    $lengthName = panjangTeksKalawa($data['nama_pelanggan']);
    if ($lengthName < 1 || $lengthName > 100) {
        $errors['nama_pelanggan'] = 'Nama wajib diisi, maksimal 100 karakter.';
    }
    $phone = $data['no_telepon'];
    if (!preg_match('/\A[+0-9 ()-]{7,20}\z/D', $phone) || preg_match_all('/[0-9]/', $phone) < 7) {
        $errors['no_telepon'] = 'Telepon harus 7–20 karakter dan berisi minimal 7 angka. Boleh menggunakan +, spasi, kurung, atau tanda hubung.';
    }
    if (!in_array($data['jenis_identitas'], jenisIdentitasPelangganM2($korporat), true)) {
        $errors['jenis_identitas'] = $korporat ? 'Pilih NIB atau NPWP badan.' : 'Pilih KTP, SIM, atau PASPOR.';
    }
    if (!preg_match('/\A[A-Za-z0-9-]{5,30}\z/D', $data['no_identitas'])) {
        $errors['no_identitas'] = 'Nomor identitas harus 5–30 karakter, hanya huruf, angka, atau tanda hubung (-).';
    }
    $lengthAddr = panjangTeksKalawa($data['alamat']);
    if ($lengthAddr < 1 || $lengthAddr > 255) {
        $errors['alamat'] = 'Alamat wajib diisi, maksimal 255 karakter.';
    }
    if ($data['kode_cabang_favorit'] !== '' &&
        !preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D', $data['kode_cabang_favorit'])) {
        $errors['kode_cabang_favorit'] = 'Pilih cabang dari daftar yang tersedia.';
    }
    return ['data' => $data, 'errors' => $errors];
}
