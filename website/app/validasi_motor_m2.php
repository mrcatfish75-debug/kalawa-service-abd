<?php
declare(strict_types=1);
/** Validasi sesuai kolom fisik motor pada DB Kalawa Service Tugas 3. */
function motorTextLen(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }
function validasiMotorM2(array $raw): array
{
    $v = [];
    foreach (['no_polisi','no_rangka','no_mesin','merek','tipe','tahun_produksi','warna','kilometer_terakhir'] as $k) {
        $v[$k] = trim((string) ($raw[$k] ?? ''));
    }
    $v['no_polisi'] = strtoupper(preg_replace('/\s+/', ' ', $v['no_polisi']));
    $v['no_rangka'] = strtoupper($v['no_rangka']);
    $v['no_mesin'] = strtoupper($v['no_mesin']);
    $e = [];
    if (!preg_match('/\A[A-Z0-9][A-Z0-9 -]{2,13}[A-Z0-9]\z/D', $v['no_polisi']))
        $e['no_polisi'] = 'Nomor polisi harus 4–15 karakter: huruf, angka, spasi, atau tanda hubung. Contoh: KH 4321 AA.';
    foreach (['no_rangka','no_mesin'] as $k) {
        if (!preg_match('/\A[A-Z0-9-]{5,30}\z/D', $v[$k]))
            $e[$k] = 'Gunakan 5–30 huruf kapital, angka atau tanda hubung (-), tanpa spasi.';
    }
    foreach (['merek' => 50, 'tipe' => 80, 'warna' => 30] as $k => $max) {
        if ($v[$k] === '' || motorTextLen($v[$k]) > $max)
            $e[$k] = "Wajib diisi, maksimal {$max} karakter.";
    }
    $year = date('Y');
    if (!preg_match('/\A\d{4}\z/D', $v['tahun_produksi']) || (int)$v['tahun_produksi'] < 1900 || (int)$v['tahun_produksi'] > (int)$year)
        $e['tahun_produksi'] = "Tahun harus berupa 4 angka antara 1900 dan {$year}.";
    if (!preg_match('/\A(?:0|[1-9]\d{0,9})\z/D', $v['kilometer_terakhir']) || (float)$v['kilometer_terakhir'] > 4294967295)
        $e['kilometer_terakhir'] = 'Kilometer harus bilangan bulat 0–4.294.967.295 tanpa tanda pemisah.';
    return ['data' => $v, 'errors' => $e];
}

function validasiKepemilikanM2(array $raw): array
{
    $v = [
        'no_polisi' => trim((string)($raw['no_polisi'] ?? '')),
        'id_pelanggan' => trim((string)($raw['id_pelanggan'] ?? '')),
        'tanggal_mulai' => trim((string)($raw['tanggal_mulai'] ?? '')),
    ];
    $e = [];
    if ($v['no_polisi'] === '' || strlen($v['no_polisi']) > 15) $e['no_polisi'] = 'Pilih motor dari daftar.';
    if (!preg_match('/\A[A-Za-z0-9._-]{3,15}\z/D', $v['id_pelanggan'])) $e['id_pelanggan'] = 'Pilih pelanggan terdaftar.';
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v['tanggal_mulai']);
    if (!$d || $d->format('Y-m-d') !== $v['tanggal_mulai'] || $v['tanggal_mulai'] > date('Y-m-d') || $v['tanggal_mulai'] < '1900-01-01')
        $e['tanggal_mulai'] = 'Pilih tanggal valid antara 1900-01-01 dan hari ini.';
    return ['data'=>$v,'errors'=>$e];
}
