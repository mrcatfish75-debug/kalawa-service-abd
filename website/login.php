<?php
declare(strict_types=1);
require __DIR__ . '/app/auth.php';
mulaiSesiKalawa();
if (!empty($_SESSION['id_akun'])) pindah('panel/index.php');
$error = '';
$enteredUsername = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validasiCsrf();
    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $enteredUsername = $username;
    $password = (string) ($_POST['password'] ?? '');
    $upaya = (int) ($_SESSION['gagal_login'] ?? 0);
    $awal = (int) ($_SESSION['mulai_blokir'] ?? 0);
    if ($upaya >= 5 && time() - $awal < 300) {
        $error = 'Terlalu banyak percobaan. Coba kembali beberapa menit lagi.';
    } else {
        try {
            $db = koneksiKalawaM2();
            $akun = preg_match('/^[a-z][a-z0-9._-]{3,49}$/', $username)
                ? ambilAkunDenganUsername($db, $username) : false;
            $valid = $akun && validasiAkunKalawa($akun) &&
                     password_verify($password, (string) $akun['password_hash']);
            if ($valid) {
                session_regenerate_id(true);
                $_SESSION = ['id_akun' => (int) $akun['id_akun'],
                             'terakhir_aktif' => time(), 'dibuat_session' => time()];
                pindah('panel/index.php');
            }
            if ($upaya === 0 || time() - $awal >= 300) {
                $_SESSION['gagal_login'] = 1;
                $_SESSION['mulai_blokir'] = time();
            } else $_SESSION['gagal_login'] = $upaya + 1;
            $error = 'Username atau password salah, atau akun tidak aktif.';
        } catch (Throwable $e) {
            error_log('[Kalawa M2: login] ' . $e->getMessage());
            $error = 'Login belum tersedia. Periksa setup database M2.';
        }
    }
}
?><!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light">
<title>Masuk | Kalawa Service</title>
<link rel="stylesheet" href="assets/css/kalawa_m2.css"><link rel="stylesheet" href="assets/css/kalawa_brand.css">
<link rel="stylesheet" href="assets/css/kalawa_workspace_r02.css?v=uiux-r04">
<link rel="stylesheet" href="assets/css/kalawa_login_r02.css?v=uiux-r04">
<script src="assets/js/kalawa_workspace_r02.js?v=uiux-r04" defer></script>
</head>
<body class="ks-login-body">
<main class="ks-login-shell">
  <section class="ks-login-left" aria-labelledby="judul-login">
    <div class="ks-login-brand"><span class="ks-brand-logo"><img src="logo/kalawa-service-logo.svg" width="56" height="56" alt="Logo Kalawa Service"></span>
      <span><strong>Kalawa Service</strong><small>Sistem Operasional Bengkel</small></span></div>
    <div class="ks-login-card">
      <p class="ks-login-overline">Portal pegawai</p>
      <h1 id="judul-login">Masuk ke sistem</h1>
      <p class="ks-login-description">Gunakan akun pegawai yang diberikan pengelola untuk mengakses pekerjaan sesuai peran.</p>
      <?php if ($error): ?><div class="alert bad" role="alert"><?=amanHtml($error)?></div><?php endif; ?>
      <form action="login.php" method="post" id="ks-login-form">
        <input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>">
        <label for="username">Username <span aria-hidden="true">*</span></label>
        <input id="username" name="username" maxlength="50" minlength="4" required autocomplete="username" pattern="[A-Za-z][A-Za-z0-9._\-]{3,49}" title="4–50 karakter; diawali huruf, diikuti huruf, angka, titik, strip, atau garis bawah." aria-describedby="login-username-hint" value="<?=amanHtml($enteredUsername)?>" autofocus>
        <small id="login-username-hint" class="ks-field-help">4–50 karakter. Contoh: adminawal.</small>
        <label for="password">Kata sandi <span aria-hidden="true">*</span></label>
        <div class="ks-password-line"><input id="password" name="password" type="password" required autocomplete="current-password" aria-describedby="login-password-hint"><button type="button" data-ks-password-toggle aria-pressed="false" aria-controls="password">Tampilkan</button></div>
        <small id="login-password-hint" class="ks-field-help">Gunakan kata sandi yang diberikan pengelola.</small>
        <button class="ks-login-submit" type="submit">Masuk</button>
      </form>
      <p class="ks-login-footer">Belum punya akun? Hubungi pengelola sistem. Tidak tersedia pendaftaran publik.</p>
    </div>
    <div class="ks-login-copyright">Kalawa Service · Aplikasi operasional internal</div>
  </section>
  <aside class="ks-login-right" aria-label="Informasi portal operasional">
    <div class="ks-login-right-inner"><p class="ks-login-kicker">KALAWA SERVICE</p><h2>Satu sistem untuk pekerjaan bengkel yang lebih tertata.</h2>
      <p>Kelola data pelanggan, pekerjaan servis, dan persediaan melalui menu yang jelas sesuai kewenangan.</p>
      <div class="ks-login-feature"><strong>Work Order</strong><span>Catat pekerjaan dan pantau statusnya.</span></div>
      <div class="ks-login-feature"><strong>Persediaan</strong><span>Periksa stok setiap cabang secara terpisah.</span></div>
      <div class="ks-login-feature"><strong>Akses petugas</strong><span>Fitur tersedia sesuai peran yang sudah diaktifkan.</span></div>
    </div>
  </aside>
</main>
</body></html>
