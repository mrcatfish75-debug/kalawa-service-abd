<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/database_m2.php';

const ROLE_KALAWA = [
    'rl_owner_ops' => 'Owner / Operasional',
    'rl_branch_admin' => 'Admin Cabang',
    'rl_cashier_ops' => 'Kasir',
    'rl_service_tech' => 'Mekanik',
    'rl_inventory_ops' => 'Petugas Gudang',
    'rl_internal_audit' => 'Auditor',
];
const ROLE_STAF_KALAWA = [
    'rl_branch_admin' => 'ADMIN',
    'rl_cashier_ops' => 'KASIR',
    'rl_service_tech' => 'MEKANIK',
    'rl_inventory_ops' => 'PETUGAS_GUDANG',
];

function validasiAkunKalawa(array $row): bool
{
    if ((int) $row['aktif'] !== 1 || !isset(ROLE_KALAWA[$row['role_db']])) return false;
    $role = (string) $row['role_db'];
    if (isset(ROLE_STAF_KALAWA[$role])) {
        return !empty($row['id_pegawai']) && !empty($row['kode_cabang']) &&
            ($row['kode_cabang'] === $row['cabang_pegawai']) &&
            ($row['jenis_pegawai'] === ROLE_STAF_KALAWA[$role]);
    }
    return true;
}

function ambilAkunDenganUsername(PDO $db, string $username): array|false
{
    $stmt = $db->prepare('SELECT a.id_akun, a.username, a.password_hash, a.role_db, a.aktif,
                         a.id_pegawai, a.kode_cabang, p.kode_cabang AS cabang_pegawai,
                         p.jenis_pegawai
                         FROM akun_aplikasi a LEFT JOIN pegawai p ON p.id_pegawai = a.id_pegawai
                         WHERE a.username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    return $stmt->fetch();
}

function akunLoginWajib(): array
{
    mulaiSesiKalawa();
    if (!isset($_SESSION['id_akun']) || !is_int($_SESSION['id_akun'])) {
        pindah('../login.php');
    }
    try {
        $db = koneksiKalawaM2();
        $stmt = $db->prepare('SELECT a.id_akun, a.username, a.role_db, a.aktif,
                              a.id_pegawai, a.kode_cabang, p.kode_cabang AS cabang_pegawai,
                              p.jenis_pegawai
                              FROM akun_aplikasi a LEFT JOIN pegawai p ON p.id_pegawai=a.id_pegawai
                              WHERE a.id_akun=:id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['id_akun']]);
        $akun = $stmt->fetch();
        if (!$akun || !validasiAkunKalawa($akun)) {
            bersihkanSesiKalawa();
            pindah('../login.php');
        }
        return $akun;
    } catch (Throwable $e) {
        error_log('[Kalawa M2: auth] ' . $e->getMessage());
        http_response_code(503);
        exit('Layanan autentikasi sedang tidak tersedia. Periksa konfigurasi server.');
    }
}

function bolehMengelolaPelanggan(array $akun): bool
{
    // SENGAJA OWNER SAJA: favorit cabang != otoritas data cabang.
    // Jangan perluas ke rl_branch_admin tanpa kebijakan pemilikan data tersendiri.
    return $akun['role_db'] === 'rl_owner_ops';
}
