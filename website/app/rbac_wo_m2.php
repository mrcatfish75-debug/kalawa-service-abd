<?php
declare(strict_types=1);
/** R01A-R02: kebijakan server side untuk membaca dan membuat header Work Order. */
function ksWOBolehAkses(array $akun): bool
{
    $role=(string)($akun['role_db'] ?? '');
    if ($role==='rl_owner_ops') return true;
    if ($role!=='rl_branch_admin') return false;
    $cab=(string)($akun['kode_cabang'] ?? '');
    return $cab!=='' && strlen($cab)<=10 &&
        (bool)preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D',$cab) &&
        $cab===(string)($akun['cabang_pegawai'] ?? '') &&
        (string)($akun['jenis_pegawai'] ?? '')==='ADMIN' &&
        !empty($akun['id_pegawai']);
}
function ksWOCabangTerikat(array $akun): ?string
{
    $role=(string)($akun['role_db'] ?? '');
    if ($role==='rl_owner_ops') return null;
    if ($role!=='rl_branch_admin') throw new DomainException('Role tidak memiliki izin Work Order.');
    $cab=(string)($akun['kode_cabang'] ?? '');
    $staff=(string)($akun['cabang_pegawai'] ?? '');
    if (!preg_match('/\A[A-Za-z0-9_-]{1,10}\z/D',$cab) || $cab!==$staff ||
        (string)($akun['jenis_pegawai'] ?? '')!=='ADMIN' ||
        empty($akun['id_pegawai'])) throw new DomainException('Penempatan cabang tidak valid.');
    return $cab;
}
function ksWOAksesCabang(array $akun, string $requested): bool
{
    if (!ksWOBolehAkses($akun)) return false;
    try {$bound=ksWOCabangTerikat($akun);} catch (DomainException $e) {return false;}
    return $bound===null || hash_equals($bound,$requested);
}
function ksWOLabelCakupan(?string $scope): string
{
    return $scope===null ? 'seluruh cabang' : 'cabang Anda';
}
