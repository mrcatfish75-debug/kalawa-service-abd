<?php
declare(strict_types=1);

/** Format tampilan saja. Nilai uang dari SQL dipertahankan sebagai string desimal. */
function ksUiRupiah(string|int|null $nilai): string
{
    if ($nilai === null) return '—';
    $teks = (string)$nilai;
    if (!preg_match('/\A(-?)([0-9]+)(?:\.([0-9]{1,2}))?\z/D', $teks, $bagian)) {
        return '—';
    }
    $utuh = ltrim($bagian[2], '0');
    $utuh = $utuh === '' ? '0' : $utuh;
    $sen = str_pad($bagian[3] ?? '', 2, '0');
    $minus = $bagian[1] === '-' && ($utuh !== '0' || $sen !== '00') ? '−' : '';
    return 'Rp '.$minus.preg_replace('/\B(?=(?:[0-9]{3})+(?![0-9]))/', '.', $utuh).','.$sen;
}

function ksUiStatus(?string $status): string
{
    [$label, $warna] = match ($status) {
        'MENUNGGU' => ['Menunggu', 'warning'],
        'PROSES' => ['Proses', 'info'],
        'SELESAI' => ['Selesai', 'success'],
        'DIBATALKAN' => ['Dibatalkan', 'neutral'],
        'LUNAS' => ['Lunas', 'success'],
        'BELUM_LUNAS' => ['Belum lunas', 'warning'],
        null, '' => ['Belum dicatat', 'neutral'],
        default => [$status, 'neutral'],
    };
    return '<span class="ks-ui-badge ks-ui-badge--'.$warna.'">'.
        htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>';
}

/** Tidak menggeser zona waktu atau mengubah tanggal tersimpan. */
function ksUiTanggal(?string $nilai, bool $jam = false): string
{
    if ($nilai === null || $nilai === '') return '—';
    if (!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})(?: ([0-9]{2}):([0-9]{2}):([0-9]{2}))?\z/D', $nilai, $b) ||
        !checkdate((int)$b[2], (int)$b[3], (int)$b[1]) ||
        (isset($b[4]) && ((int)$b[4] > 23 || (int)$b[5] > 59 || (int)$b[6] > 59))) {
        return '—';
    }
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return (int)$b[3].' '.$bulan[(int)$b[2]].' '.$b[1].
        ($jam && isset($b[4]) ? ' · '.$b[4].':'.$b[5] : '');
}
