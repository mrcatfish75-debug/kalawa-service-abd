<?php
declare(strict_types=1);
require __DIR__ . '/app/security.php';
mulaiSesiKalawa();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: POST'); exit('Gunakan tombol Keluar.');
}
validasiCsrf();
bersihkanSesiKalawa();
pindah('login.php');
