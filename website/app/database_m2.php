<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

const KALAWA_KORPORAT_UJI_DB = 'kalawa_service_f1_g1_clone_20261110_174035_33f8f960';
const KALAWA_KORPORAT_UJI_USER = 'ks_korp_uji_33f8f960';
const KALAWA_KORPORAT_UJI_SITE = 'kalawa-service-f1-korporat-uji';

function koneksiKalawaM2(): PDO
{
    $path = dirname(__DIR__, 3) . '/kalawa-korporat-uji-config.php';
    if (!is_file($path)) {
        throw new RuntimeException('Pasang konfigurasi situs korporat uji terlebih dahulu.');
    }
    $cfg = require $path;
    if (!is_array($cfg) || ($cfg['database'] ?? null) !== KALAWA_KORPORAT_UJI_DB ||
        ($cfg['username'] ?? null) !== KALAWA_KORPORAT_UJI_USER ||
        !is_int($cfg['port'] ?? null) || $cfg['port'] < 1 || $cfg['port'] > 65535 ||
        !is_string($cfg['password'] ?? null) || $cfg['password'] === '') {
        throw new RuntimeException('Konfigurasi koneksi harus menunjuk akun dan salinan korporat uji.');
    }
    $dsn = 'mysql:host=127.0.0.1;port=' . $cfg['port'] . ';dbname=' . KALAWA_KORPORAT_UJI_DB . ';charset=utf8mb4';
    $db = new PDO($dsn, KALAWA_KORPORAT_UJI_USER, $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    $who = $db->query('SELECT DATABASE() AS db, CURRENT_USER() AS akun')->fetch();
    if (!$who || $who['db'] !== KALAWA_KORPORAT_UJI_DB ||
        $who['akun'] !== KALAWA_KORPORAT_UJI_USER . '@127.0.0.1') {
        throw new RuntimeException('Sesi database korporat uji tidak sesuai konfigurasi.');
    }
    $db->exec('SET SESSION innodb_lock_wait_timeout=5');
    return $db;
}
