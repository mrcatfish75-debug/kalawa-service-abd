<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/korporat_m2.php';

final class OperasionalDitolak extends DomainException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}

function opsTeks(array $data, string $key, string $default = ''): string
{
    $value = $data[$key] ?? $default;
    if (!is_string($value)) {
        throw new InvalidArgumentException('Format isian ' . $key . ' tidak valid.');
    }
    return trim($value);
}

function opsCabang(array $akun, array $roles): ?string
{
    $role = (string) ($akun['role_db'] ?? '');
    if (!in_array($role, $roles, true)) {
        throw new OperasionalDitolak('Akun tidak memiliki izin untuk fitur ini.', 403);
    }
    if (in_array($role, ['rl_owner_ops', 'rl_internal_audit'], true)) {
        return null;
    }
    if (!validasiAkunKalawa($akun)) {
        throw new OperasionalDitolak('Penempatan pegawai tidak valid.', 403);
    }
    return (string) $akun['kode_cabang'];
}

function opsToken(string $aksi, string $target, mixed $snapshot): string
{
    $tokens = $_SESSION['ops_tokens'] ?? [];
    $tokens = array_filter($tokens, static fn(array $row): bool => $row['waktu'] >= time() - 1200);
    $token = bin2hex(random_bytes(24));
    $tokens[$token] = ['aksi'=>$aksi, 'target'=>$target, 'snapshot'=>$snapshot, 'waktu'=>time()];
    $_SESSION['ops_tokens'] = array_slice($tokens, -30, null, true);
    return $token;
}

function opsBacaToken(string $token, string $aksi, string $target): mixed
{
    $row = $_SESSION['ops_tokens'][$token] ?? null;
    if (!is_array($row) || $row['aksi'] !== $aksi || $row['target'] !== $target || $row['waktu'] < time() - 1200) {
        throw new OperasionalDitolak('Form sudah diproses atau kedaluwarsa. Muat ulang halaman.');
    }
    return $row['snapshot'];
}

function opsDaftarCabang(PDO $db, ?string $scope): array
{
    $stmt = $db->prepare('SELECT kode_cabang,nama_cabang FROM cabang' .
        ($scope === null ? '' : ' WHERE kode_cabang=:cabang') . ' ORDER BY kode_cabang');
    $stmt->execute($scope === null ? [] : ['cabang'=>$scope]);
    return $stmt->fetchAll();
}

function opsStatusPembayaran(PDO $db, string $nomor): array
{
    $stmt = $db->prepare("SELECT COALESCE(t.status_pembayaran,p.status_pembayaran,'BELUM_LUNAS') AS status,
        k.id_kontrak,d.id_tagihan FROM work_order w
        LEFT JOIN work_order_korporat k ON k.no_work_order=w.no_work_order
        LEFT JOIN detail_tagihan_korporat d ON d.no_work_order=w.no_work_order
        LEFT JOIN tagihan_korporat t ON t.id_tagihan=d.id_tagihan
        LEFT JOIN pembayaran p ON p.no_work_order=w.no_work_order WHERE w.no_work_order=:nomor");
    $stmt->execute(['nomor'=>$nomor]);
    return $stmt->fetch() ?: [];
}
