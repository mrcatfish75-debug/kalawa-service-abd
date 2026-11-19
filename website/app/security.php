<?php
declare(strict_types=1);

function amanHtml(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function mulaiSesiKalawa(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (headers_sent()) throw new RuntimeException('Header terlanjur dikirim sebelum session.');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('KALAWA_KORP_UJI_SESSID');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/kalawa-service-f1-korporat-uji/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    if (isset($_SESSION['terakhir_aktif']) && time() - (int) $_SESSION['terakhir_aktif'] > 1800) {
        bersihkanSesiKalawa();
        session_start();
    }
    $_SESSION['terakhir_aktif'] = time();
    if (!isset($_SESSION['dibuat_session'])) $_SESSION['dibuat_session'] = time();
    if (time() - (int) $_SESSION['dibuat_session'] > 900) {
        session_regenerate_id(true);
        $_SESSION['dibuat_session'] = time();
    }
}

function bersihkanSesiKalawa(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $options = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600, 'path' => $options['path'],
            'domain' => $options['domain'], 'secure' => $options['secure'],
            'httponly' => $options['httponly'], 'samesite' => $options['samesite'] ?? 'Lax'
        ]);
    }
    session_destroy();
}

function tokenCsrf(): string
{
    mulaiSesiKalawa();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf'];
}

function validasiCsrf(): void
{
    mulaiSesiKalawa();
    $csrfSesi = $_SESSION['csrf'] ?? null;
    $csrfForm = $_POST['csrf'] ?? null;
    if (!is_string($csrfSesi) || $csrfSesi === '' ||
        !is_string($csrfForm) || !hash_equals($csrfSesi, $csrfForm)) {
        http_response_code(403);
        exit('Permintaan tidak valid atau sesi kedaluwarsa. Muat ulang halaman dan coba lagi.');
    }
}

function pindah(string $relative): never
{
    header('Location: ' . $relative, true, 303);
    exit;
}

function pesanKilAt(string $message, string $type = 'ok'): void
{
    mulaiSesiKalawa();
    $_SESSION['flash'] = ['pesan' => $message, 'jenis' => $type];
}

function ambilPesanKilAt(): ?array
{
    mulaiSesiKalawa();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}
