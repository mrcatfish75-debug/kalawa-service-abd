<?php
declare(strict_types=1);
/** Validasi murni, tidak melakukan query database; aman untuk unit test PHP CLI. */
function woKarakter(string $value): int
{
    return preg_match_all('/[\s\S]/u', $value, $matches) ?: 0;
}
function woValidasiKode(string $value, int $length): bool
{
    return $value !== '' && strlen($value) <= $length && (bool) preg_match('/\A[A-Za-z0-9._ -]+\z/D', $value);
}
function woValidasiForm(array $input): array
{
    $keys=['kode_cabang','id_pelanggan','no_polisi','id_mekanik_pj','keluhan','estimasi_biaya'];
    $data=[];
    foreach ($keys as $key) {
        $raw=$input[$key] ?? '';
        $data[$key]=is_string($raw) ? trim($raw) : '';
    }
    $errors=[];
    foreach (['kode_cabang'=>10,'id_pelanggan'=>15,'no_polisi'=>15,'id_mekanik_pj'=>10] as $key=>$limit) {
        if (!woValidasiKode($data[$key], $limit)) {
            $names=['kode_cabang'=>'Cabang','id_pelanggan'=>'Pelanggan','no_polisi'=>'Motor','id_mekanik_pj'=>'Mekanik'];
            $errors[$key]=$names[$key] . ' wajib dipilih dari daftar.';
        }
    }
    $complaint=$data['keluhan'];
    $count=woKarakter($complaint);
    if ($count < 10 || $count > 1500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $complaint)) {
        $errors['keluhan']='Tuliskan keluhan 10–1.500 karakter tanpa karakter kontrol.';
    }
    $price=$data['estimasi_biaya'];
    if (!preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', $price)) {
        $errors['estimasi_biaya']='Isi angka rupiah 0–9.999.999.999,99. Contoh: 150000 atau 150000.50 (tanpa titik ribuan).';
    }
    return ['data'=>$data,'errors'=>$errors];
}
function woLabelStatus(string $status): string
{
    return match ($status) {
        'MENUNGGU'=>'Menunggu','PROSES'=>'Proses','SELESAI'=>'Selesai','DIBATALKAN'=>'Dibatalkan',
        default=>'Tidak diketahui',
    };
}
/** Tanggal + 8 digit hex dari token sekali pakai => panjang 20, kompatibel VARCHAR(20). */
function woNomorDariToken(string $tanggal, string $token): string
{
    if (!preg_match('/\A[0-9]{8}\z/D', $tanggal) ||
        !preg_match('/\A[a-f0-9]{48}\z/D', $token)) {
        throw new InvalidArgumentException('Token atau tanggal tidak valid.');
    }
    // Deterministik untuk request yang sama: PK tetap melindungi replay
    // walau jaringan putus tepat setelah COMMIT sebelum redirect.
    return 'WO-' . $tanggal . '-' . strtoupper(substr($token, 0, 8));
}
