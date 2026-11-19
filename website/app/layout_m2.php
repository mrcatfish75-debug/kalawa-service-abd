<?php
declare(strict_types=1);
/**
 * Kalawa Workspace R02 — lapisan presentasi untuk modul F1-Test yang sudah berjalan.
 * Tidak mengubah auth, pemeriksaan role di endpoint, SQL, CSRF, atau logic transaksi.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac_wo_m2.php';
require_once __DIR__ . '/presentasi_ui.php';
require_once __DIR__ . '/paginasi_ui.php';
require_once __DIR__ . '/cetak_r05.php';

function ksIconR02(string $name): string
{
    $paths = [
        'layout-dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'clipboard-list' => '<rect x="5" y="4" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 11h6M9 15h6M9 19h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'bike' => '<circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path d="M15 6h2.5M5.5 17.5l5-10h4l4 10M8 11h7M10 7.5l-2-3H5"/>',
        'list-checks' => '<path d="m3 7 2 2 4-4M13 7h8m-18 9 2 2 4-4M13 16h8"/>',
        'package' => '<path d="m12 2 9 5-9 5-9-5 9-5ZM3 7v10l9 5 9-5V7M12 12v10"/>',
        'boxes' => '<path d="m12 2 8 4-8 4-8-4 8-4ZM4 6v8l8 4 8-4V6M12 10v8M2 16l5 3 5-3M12 16l5 3 5-3"/>',
        'truck' => '<path d="M3 5h11v12H3zM14 9h4l3 4v4h-7V9Z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'wrench' => '<path d="M14.7 6.3a4.5 4.5 0 0 0-5.9 5.9l-6.2 6.2a2 2 0 0 0 2.8 2.8l6.2-6.2a4.5 4.5 0 0 0 5.9-5.9L15 11l-3-3 2.7-1.7Z"/>',
    ];
    $shape = $paths[$name] ?? $paths['layout-dashboard'];
    return '<svg class="ks-nav-icon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$shape.'</svg>';
}

function ksNavItemR02(string $file, string $label, string $icon, string $active): void
{
    $isActive = $file === $active;
    echo '<a class="ks-side-link" href="'.amanHtml($file).'"'.($isActive?' aria-current="page"':'').'>'.ksIconR02($icon).'<span>'.amanHtml($label).'</span></a>';
}

function awalHalamanM2(string $title, array $akun): void
{
    $role = (string)($akun['role_db'] ?? '');
    $owner = $role === 'rl_owner_ops';
    $mekanik = $role === 'rl_service_tech';
    $kasir = $role === 'rl_cashier_ops';
    $gudang = $role === 'rl_inventory_ops';
    $laporan = in_array($role,['rl_owner_ops','rl_internal_audit','rl_branch_admin','rl_cashier_ops','rl_inventory_ops'],true);
    $woAllowed=ksWOBolehAkses($akun);
    // Modul eksisting saat ini melindungi operasi dengan role owner di endpoint.
    // Menu non-owner sengaja tidak membuka link modul yang belum diuji RBAC.
    $roleLabel = ROLE_KALAWA[$role] ?? 'Petugas';
    $username = (string)($akun['username'] ?? 'Petugas');
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $cssModule = [
        'supplier.php' => 'kalawa_supplier_m2.css',
        'stok.php' => 'kalawa_stok_m2.css',
        'work_order.php' => 'kalawa_wo_m2.css',
        'pekerjaan.php' => 'kalawa_wo_m2.css',
        'pembayaran.php' => 'kalawa_pembayaran_r01e.css',
        'tagihan_korporat.php' => 'kalawa_pembayaran_r01e.css',
    ][$page] ?? null;
    ?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= amanHtml($GLOBALS['ks_cetak']['nama_file'] ?? ($title.' | Kalawa Service')) ?></title>
  <link rel="stylesheet" href="../assets/css/kalawa_m2.css">
  <link rel="stylesheet" href="../assets/css/kalawa_brand.css">
  <?php if ($cssModule): ?><link rel="stylesheet" href="../assets/css/<?= amanHtml($cssModule) ?>"><?php endif; ?>
  <link rel="stylesheet" href="../assets/css/kalawa_workspace_r02.css?v=uiux-r04">
  <link rel="stylesheet" href="../assets/css/kalawa_ui_polish.css?v=uiux-r04">
  <link rel="stylesheet" href="../assets/css/kalawa_roles_r03.css?v=uiux-r04">
  <link rel="stylesheet" href="../assets/css/kalawa_cetak_r05.css?v=cetak-r05">
  <?php ksCetakHead(); ?>
  <script src="../assets/js/kalawa_cetak_r05.js?v=cetak-r05" defer></script>
  <script src="../assets/js/kalawa_workspace_r02.js?v=uiux-r04" defer></script>
  <script src="../assets/js/kalawa_ui_polish.js?v=1.0" defer></script>
  <noscript><style>@media(max-width:1099px){.ks-sidebar{position:static;width:100%;height:auto;transform:none;visibility:visible}.ks-menu-toggle,.ks-sidebar-close,.ks-bottom-nav{display:none}.ks-side-nav{display:flex;flex-direction:row;flex-wrap:wrap}.ks-side-group-title{width:100%}.ks-sidebar-footer{display:block}}</style></noscript>
</head>
<body class="ks-workspace<?=!empty($GLOBALS['ks_cetak'])?' ks-cetak-actif':''?>" data-role="<?=amanHtml($role)?>">
  <a class="ks-skip-link" href="#konten-utama">Lewati navigasi</a>
  <div class="ks-app-shell">
    <aside id="ks-sidebar" class="ks-sidebar" aria-label="Navigasi utama">
      <div class="ks-sidebar-brand">
        <a href="index.php" class="ks-brand-link" aria-label="Kalawa Service — Beranda">
          <span class="ks-brand-logo"><img src="../logo/kalawa-service-logo.svg" width="51" height="51" alt="Logo Kalawa Service"></span>
          <span class="ks-brand-name"><strong>Kalawa Service</strong><small>Sistem Operasional Bengkel</small></span>
        </a>
        <button type="button" class="ks-sidebar-close" data-ks-close-menu aria-label="Tutup menu navigasi"><?=ksIconR02('x')?></button>
      </div>
      <div class="ks-nav-scroll" aria-hidden="true"><span></span></div>
      <nav class="ks-side-nav" aria-label="Menu aplikasi">
        <div class="ks-side-group-title">Utama</div>
        <?php if ($mekanik): ?>
        <?php ksNavItemR02('pekerjaan.php', 'Pekerjaan Saya', 'wrench', $page); ?>
        <?php elseif ($owner || $role === 'rl_branch_admin'): ?>
        <?php ksNavItemR02('index.php', 'Ringkasan', 'layout-dashboard', $page); ?>
        <?php endif; ?>
        <?php if ($woAllowed): ?><?php ksNavItemR02('pendaftaran.php', 'Pendaftaran', 'users', $page); ?><?php endif; ?>
        <?php if ($woAllowed): ?><?php ksNavItemR02('work_order.php', 'Work Order', 'clipboard-list', $page); ?><?php endif; ?>
        <?php if ($owner || $kasir): ?>
        <?php ksNavItemR02('pembayaran.php', 'Pembayaran', 'list-checks', $page); ?>
        <?php ksNavItemR02('tagihan_korporat.php', 'Tagihan Korporat', 'clipboard-list', $page); ?>
        <?php endif; ?>
        <?php if ($gudang): ?><?php ksNavItemR02('stok.php', 'Stok Bengkel', 'boxes', $page); ?><?php endif; ?>
        <?php if ($laporan): ?><?php ksNavItemR02('laporan.php', 'Laporan', 'list-checks', $page); ?><?php endif; ?>
        <?php if (in_array($role,['rl_owner_ops','rl_branch_admin','rl_cashier_ops','rl_inventory_ops'],true)): ?><?php ksNavItemR02('katalog.php','Katalog Bengkel','package',$page); ?><?php endif; ?>
        <?php if ($owner || $role==='rl_internal_audit'): ?><?php ksNavItemR02('audit.php','Jejak Audit','shield-check',$page); ?><?php endif; ?>
        <?php if ($owner): ?>
        <div class="ks-side-group-title">Data bengkel</div>
        <?php ksNavItemR02('pelanggan.php', 'Pelanggan', 'users', $page === 'kontrak_korporat.php' ? 'pelanggan.php' : $page); ?>
        <?php ksNavItemR02('motor.php', 'Motor', 'bike', $page); ?>
        <?php ksNavItemR02('jasa.php', 'Jasa Servis', 'list-checks', $page); ?>
        <?php ksNavItemR02('suku_cadang.php', 'Suku Cadang', 'package', $page); ?>
        <?php ksNavItemR02('stok.php', 'Stok Bengkel', 'boxes', $page); ?>
        <?php ksNavItemR02('supplier.php', 'Supplier', 'truck', $page); ?>
        <?php endif; ?>
        <div class="ks-side-group-title">Bantuan</div>
        <?php ksNavItemR02('panduan.php','Panduan Kerja','list-checks',$page); ?>
      </nav>
      <div class="ks-sidebar-footer">
        <div class="ks-current-account"><span class="ks-avatar" aria-hidden="true"><?=amanHtml(strtoupper(substr($username,0,1)))?></span><span><strong><?=amanHtml($username)?></strong><small><?=amanHtml($roleLabel)?><?=!empty($akun['kode_cabang'])?' · '.amanHtml($akun['kode_cabang']):''?></small></span></div>
        <form method="post" action="../logout.php"><input type="hidden" name="csrf" value="<?=amanHtml(tokenCsrf())?>"><button class="ks-logout" type="submit"><?=ksIconR02('log-out')?><span>Keluar</span></button></form>
      </div>
    </aside>
    <div class="ks-menu-backdrop" data-ks-close-menu hidden></div>
    <div class="ks-app-area">
      <header class="ks-topbar">
        <div class="ks-topbar-left">
          <button class="ks-menu-toggle" type="button" data-ks-menu aria-controls="ks-sidebar" aria-expanded="false" aria-label="Buka menu navigasi"><?=ksIconR02('menu')?></button>
          <div class="ks-breadcrumb" aria-label="Lokasi halaman"><span>Kalawa Service</span><?=ksIconR02('chevron-right')?><strong><?=amanHtml($title)?></strong></div>
        </div>
        <div class="ks-topbar-right"><button type="button" class="ks-command-open" data-ks-command hidden aria-haspopup="dialog" aria-label="Buka pencarian menu"> <?=ksIconR02('search')?> <span>Cari menu</span><kbd>Ctrl K</kbd></button><span class="ks-topbar-user" title="<?=amanHtml($roleLabel)?>"><?=amanHtml($username)?></span></div>
      </header>
      <main id="konten-utama" class="wrap ks-content" tabindex="-1">
      <div class="ks-account-context"><span><?=amanHtml($roleLabel)?></span><span><?=amanHtml($akun['kode_cabang']??'Seluruh cabang')?></span></div>
    <?php
    $tabs=match($role){
      'rl_owner_ops'=>[['index.php','Ringkasan','layout-dashboard'],['work_order.php','Servis','clipboard-list'],['pembayaran.php','Bayar','list-checks'],['laporan.php','Laporan','list-checks']],
      'rl_branch_admin'=>[['index.php','Ringkasan','layout-dashboard'],['pendaftaran.php','Daftar servis','users'],['work_order.php','Servis','clipboard-list'],['laporan.php','Laporan','list-checks']],
      'rl_cashier_ops'=>[['pembayaran.php','Bayar','list-checks'],['tagihan_korporat.php','Tagihan','clipboard-list'],['laporan.php','Laporan','list-checks']],
      'rl_service_tech'=>[['pekerjaan.php','Pekerjaan','wrench']],
      'rl_inventory_ops'=>[['stok.php','Stok','boxes'],['laporan.php','Laporan','list-checks'],['katalog.php','Katalog','package']],
      'rl_internal_audit'=>[['laporan.php','Laporan','list-checks'],['audit.php','Audit','shield-check']],default=>[]};
    ob_start(); ?><nav class="ks-bottom-nav" aria-label="Menu cepat ponsel"><?php foreach($tabs as [$file,$label,$icon]): ?><a href="<?=amanHtml($file)?>" <?=$page===$file?'aria-current="page"':''?>><?=ksIconR02($icon)?><span><?=amanHtml($label)?></span></a><?php endforeach; ?><button type="button" data-ks-menu aria-label="Buka semua menu" aria-controls="ks-sidebar" aria-expanded="false"><?=ksIconR02('menu')?><span>Menu</span></button></nav>
    <dialog class="ks-command" aria-labelledby="ks-command-title"><div class="ks-command-head"><h2 id="ks-command-title">Buka menu</h2><button type="button" data-ks-command-close aria-label="Tutup pencarian menu"><?=ksIconR02('x')?></button></div><label class="ks-ui-sr-only" for="ks-command-query">Cari menu sesuai peran Anda</label><input id="ks-command-query" type="search" autocomplete="off" placeholder="Ketik nama menu…" aria-controls="ks-command-results"><div id="ks-command-results" class="ks-command-results"></div><p class="ks-command-empty" hidden>Tidak ada menu yang cocok.</p><p class="ks-command-help">↑ ↓ pilih menu · Enter buka · Esc tutup</p></dialog><?php
    $GLOBALS['ks_ui_navigation']=ob_get_clean();
}

function akhirHalamanM2(): void
{
    echo '<footer class="ks-r03-help ks-ui-no-print"><span>Perlu petunjuk langkah berikutnya?</span><a href="panduan.php">Buka panduan kerja sesuai akun Anda</a></footer></main></div></div>'.($GLOBALS['ks_ui_navigation']??'').'</body></html>';
}
