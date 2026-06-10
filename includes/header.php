<?php
/**
 * ============================================================
 * ON TIME ADVENTURE — Shared Header Template (Vyzor UI)
 * ============================================================
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
$pageTitle = isset($pageTitle) ? $pageTitle . ' - On Time Adventure' : 'On Time Adventure';

// ── Load Settings (Dynamic Color) ──
if (!isset($app_settings)) {
    $app_settings = [
        'primary_color' => '#198754', // default green
        'tampilkan_kemiripan' => '1'
    ];
}
if (!isset($db)) {
    require_once __DIR__ . '/../config/database.php';
    $db = getDB();
}
if (isset($db)) {
    try {
        $settings_query = $db->query("SELECT kunci, nilai FROM pengaturan");
        while ($row = $settings_query->fetch()) {
            // Jangan timpa dengan string kosong jika sebelumnya sudah memiliki nilai default (seperti dari pengaturan.php)
            if (isset($app_settings[$row['kunci']]) && trim($row['nilai']) === '') {
                continue;
            }
            $app_settings[$row['kunci']] = $row['nilai'];
        }
    } catch (Exception $e) {}
}
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
    return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
}
$primary_rgb = hexToRgb($app_settings['primary_color']);

$cartCount = 0;
if (isset($_SESSION['keranjang']) && is_array($_SESSION['keranjang'])) {
    foreach ($_SESSION['keranjang'] as $cart_item) {
        $cartCount += isset($cart_item['jumlah']) ? (int)$cart_item['jumlah'] : (int)$cart_item;
    }
}

$isLoggedIn = isset($_SESSION['id_user']);
$isAdmin    = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Tentukan apakah kita di admin panel atau frontend
$isAdminArea = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;

// Attributes untuk <html>
$htmlAttrs = $isAdminArea 
    ? 'dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="transparent" data-width="fullwidth" data-menu-styles="transparent"'
    : 'dir="ltr" data-nav-layout="horizontal" data-nav-style="menu-hover" data-menu-position="fixed" data-theme-mode="light"';
?>
<!DOCTYPE html>
<html lang="id" <?= $htmlAttrs ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="On Time Adventure">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Theme Mode Persist -->
    <script>
        if (localStorage.getItem('ontime_theme') === 'dark') {
            document.documentElement.setAttribute('data-theme-mode', 'dark');
        } else if (localStorage.getItem('ontime_theme') === 'light') {
            document.documentElement.setAttribute('data-theme-mode', 'light');
        }
    </script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?= $base_url ?>assets/vyzor/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet" >
    
    <!-- Style Css -->
    <link href="<?= $base_url ?>assets/vyzor/css/styles.css" rel="stylesheet" >

    <!-- Icons Css -->
    <link href="<?= $base_url ?>assets/vyzor/css/icons.css" rel="stylesheet" >

    <!-- Node Waves Css -->
    <link href="<?= $base_url ?>assets/vyzor/libs/node-waves/waves.min.css" rel="stylesheet" >
    
    <!-- Simplebar Css (For Admin Sidebar) -->
    <?php if($isAdminArea): ?>
    <link href="<?= $base_url ?>assets/vyzor/libs/simplebar/simplebar.min.css" rel="stylesheet" >
    <?php endif; ?>

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/vyzor/libs/choices.js/public/assets/styles/choices.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="<?= $base_url ?>assets/vyzor/libs/sweetalert2/sweetalert2.min.css">

    <!-- Our Custom CSS Overrides -->
    <style>
        :root {
            --primary-rgb: <?= $primary_rgb ?> !important;
            --primary-color: rgb(<?= $primary_rgb ?>) !important;
        }
        .custom-card { border: none !important; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important; }
        .app-header .horizontal-logo .header-logo img { height: 2rem; }
        /* Mencegah navbar fixed menutupi konten (body) */
        <?php if (!$isAdminArea): ?>
        .landing-main { padding-top: 110px !important; }
        @media (max-width: 991.98px) {
            .landing-main { padding-top: 80px !important; }
        }
        <?php endif; ?>
        
        /* Mobile Menu Fixes */
        @media (max-width: 991.98px) {
            .app-sidebar {
                position: fixed !important;
                top: 0 !important;
                left: -300px;
                width: 260px !important;
                height: 100vh !important;
                background-color: #ffffff !important;
                z-index: 1050 !important;
                transition: left 0.3s ease !important;
                overflow-y: auto !important;
                box-shadow: 0.25rem 0 1rem rgba(0,0,0,0.1) !important;
            }
            html[data-theme-mode="dark"] .app-sidebar {
                background-color: var(--custom-white) !important;
            }
            html[data-toggled="open"] .app-sidebar {
                left: 0 !important;
            }
            #responsive-overlay {
                visibility: hidden;
                opacity: 0;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
                transition: opacity 0.3s ease;
            }
            html[data-toggled="open"] #responsive-overlay {
                visibility: visible;
                opacity: 1;
            }
            .side-menu__item {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                padding-right: 15px !important;
                white-space: normal !important;
            }
            .side-menu__label {
                flex-grow: 1;
            }
            .side-menu__item .badge {
                position: static !important;
                transform: none !important;
            }
        }
        
        /* Sidebar Text Visibility Logic */
        html[data-toggled="icon-text-close"] .sidebar-title-text,
        html[data-toggled="icon-overlay-close"] .sidebar-title-text,
        html[data-toggled="closed"] .sidebar-title-text {
            display: none !important;
        }
        html[data-toggled="icon-text-close"] .desktop-logo,
        html[data-toggled="icon-overlay-close"] .desktop-logo,
        html[data-toggled="closed"] .desktop-logo,
        html[data-toggled="icon-text-close"] .desktop-dark,
        html[data-toggled="icon-overlay-close"] .desktop-dark,
        html[data-toggled="closed"] .desktop-dark {
            display: none !important;
        }

        /* Force show notification on mobile */
        @media (max-width: 575.98px) {
            .notifications-dropdown {
                display: flex !important;
            }
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const toggleBtn = document.querySelector('.sidemenu-toggle');
            const htmlTag = document.documentElement;
            const overlay = document.getElementById('responsive-overlay');
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (window.innerWidth < 992) {
                        if (htmlTag.getAttribute('data-toggled') === 'open') {
                            htmlTag.removeAttribute('data-toggled');
                        } else {
                            htmlTag.setAttribute('data-toggled', 'open');
                        }
                    } else {
                        if (htmlTag.getAttribute('data-toggled') === 'icon-overlay-close') {
                            htmlTag.removeAttribute('data-toggled');
                        } else {
                            htmlTag.setAttribute('data-toggled', 'icon-overlay-close');
                        }
                    }
                });
            }
            if (overlay) {
                overlay.addEventListener('click', function() {
                    htmlTag.removeAttribute('data-toggled');
                });
            }

            // Theme Toggle Logic
            const themeBtns = document.querySelectorAll('.themeToggleBtn');
            const lightIcons = document.querySelectorAll('.light-layout');
            const darkIcons = document.querySelectorAll('.dark-layout');
            
            function updateThemeIcons() {
                const isDark = htmlTag.getAttribute('data-theme-mode') === 'dark';
                lightIcons.forEach(icon => icon.style.display = isDark ? 'none' : 'inline-block');
                darkIcons.forEach(icon => icon.style.display = isDark ? 'inline-block' : 'none');
            }
            
            updateThemeIcons();
            
            themeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (htmlTag.getAttribute('data-theme-mode') === 'dark') {
                        htmlTag.setAttribute('data-theme-mode', 'light');
                        localStorage.setItem('ontime_theme', 'light');
                    } else {
                        htmlTag.setAttribute('data-theme-mode', 'dark');
                        localStorage.setItem('ontime_theme', 'dark');
                    }
                    updateThemeIcons();
                });
            });
        });
    </script>
</head>
<body class="<?= $isAdminArea ? '' : 'landing-body' ?>">

<div class="page <?= $isAdminArea ? '' : 'landing-page-wrapper' ?>">

    <!-- app-header -->
    <header class="app-header" id="header">
        <div class="main-header-container container-fluid">
            <div class="header-content-left">
                <div class="header-element">
                    <a href="javascript:void(0);" class="sidemenu-toggle header-link" data-bs-toggle="sidebar">
                        <span class="open-toggle">
                            <i class="ri-menu-3-line fs-20"></i>
                        </span>
                    </a>
                </div>
                <div class="header-element">
                    <div class="horizontal-logo">
                        <a href="<?= $base_url ?>" class="header-logo d-flex align-items-center">
                            <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="On Time Adventure" class="d-none d-md-block" style="height: 40px;">
                            <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="On Time Adventure" class="d-block d-md-none ms-3 ms-sm-0" style="height: 30px;">
                            <span class="fw-bold text-primary ms-2 d-block" style="font-size: 14px; letter-spacing: 0.5px; white-space: nowrap;">ON TIME ADVENTURE</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="header-content-right">
                <!-- Light/Dark Mode Toggle -->
                <div class="header-element header-theme-mode">
                    <a href="javascript:void(0);" class="header-link layout-setting themeToggleBtn">
                        <span class="light-layout">
                            <i class="ri-moon-clear-line fs-20"></i>
                        </span>
                        <span class="dark-layout" style="display:none;">
                            <i class="ri-sun-line fs-20"></i>
                        </span>
                    </a>
                </div>

                <?php if (!$isLoggedIn): ?>
                <div class="header-element align-items-center">
                    <div class="btn-list d-lg-none d-block">
                        <?php if (basename($_SERVER['PHP_SELF']) == 'register.php'): ?>
                            <a href="<?= $base_url ?>login.php" class="btn btn-primary-light">Login</a>
                        <?php else: ?>
                            <a href="<?= $base_url ?>register.php" class="btn btn-primary-light">Daftar</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                
                <!-- Notifications Dropdown -->
                <div class="header-element notifications-dropdown me-2 me-sm-3">
                    <a href="javascript:void(0);" class="header-link btn-notif-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="border: none; background: transparent; outline: none; box-shadow: none;">
                        <i class="ri-notification-3-line fs-20"></i>
                        <span class="badge bg-danger rounded-pill header-icon-badge pulse pulse-secondary notif-badge" style="display: none;">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow dropdown-menu-md shadow-sm border-0 pt-0" style="width: 300px;">
                        <div class="dropdown-header bg-light d-flex align-items-center justify-content-between p-3 border-bottom">
                            <span class="fw-semibold fs-15 text-dark">Notifikasi</span>
                            <a href="<?= $base_url ?>notifikasi.php" class="text-primary fs-12 text-decoration-none">Lihat Semua</a>
                        </div>
                        <div class="dropdown-body p-0 notif-list" style="max-height: 300px; overflow-y: auto;">
                            <div class="p-3 text-center text-muted fs-13">Belum ada notifikasi</div>
                        </div>
                    </div>
                </div>

                <!-- User Profile Dropdown -->
                <div class="header-element">
                    <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <div class="d-flex align-items-center">
                            <div class="me-sm-2 me-0">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nama'] ?? 'User') ?>&background=random" alt="img" width="32" height="32" class="rounded-circle">
                            </div>
                            <div class="d-sm-block d-none">
                                <p class="fw-semibold mb-0 lh-1"><?= htmlspecialchars($_SESSION['nama'] ?? 'Pengguna') ?></p>
                                <span class="op-7 fw-normal d-block fs-11"><?= $isAdmin ? 'Admin' : 'Pelanggan' ?></span>
                            </div>
                        </div>
                    </a>
                    <ul class="dropdown-menu pt-0 header-profile-dropdown dropdown-menu-end" aria-labelledby="mainHeaderProfile">
                        <li><a class="dropdown-item d-flex" href="<?= $base_url ?>logout.php"><i class="ti ti-logout fs-18 me-2 op-7"></i>Logout</a></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Start::app-sidebar -->
    <aside class="app-sidebar sticky" id="sidebar">
        <?php if ($isAdminArea): ?>
        <div class="main-sidebar-header" style="justify-content: flex-start; padding-left: 20px;">
            <a href="<?= $base_url ?>admin/" class="header-logo d-flex align-items-center" style="text-decoration: none; width: 100%;">
                <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="logo" class="desktop-logo" style="height: 35px;">
                <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="logo" class="toggle-logo" style="height: 35px; margin: 0 auto;">
                <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="logo" class="desktop-dark" style="height: 35px;">
                <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="logo" class="toggle-dark" style="height: 35px; margin: 0 auto;">
                
                <span class="fw-bold text-primary ms-2 sidebar-title-text" style="font-size: 14px; white-space: nowrap;">ON TIME ADVENTURE</span>
            </a>
        </div>
        <?php endif; ?>
        <div class="<?= $isAdminArea ? 'main-sidebar' : 'container px-0' ?>" id="<?= $isAdminArea ? 'sidebar-scroll' : '' ?>">
            <nav class="main-menu-container nav nav-pills <?= $isAdminArea ? 'flex-column' : '' ?> sub-open">
                <?php if (!$isAdminArea): ?>
                <div class="landing-logo-container px-4 py-3">
                    <div class="horizontal-logo">
                        <a href="<?= $base_url ?>" class="header-logo text-decoration-none d-flex align-items-center">
                            <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="On Time Adventure" style="height: 40px;">
                            <span class="fw-bold text-primary ms-2 d-block" style="font-size: 14px; letter-spacing: 0.5px; white-space: nowrap;">ON TIME ADVENTURE</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <div class="slide-left" id="slide-left"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path> </svg></div>
                <ul class="main-menu <?= $isAdminArea ? '' : 'flex-fill justify-content-center' ?>">
                    
                    <?php if ($isAdminArea): ?>
                        <li class="slide__category"><span class="category-name">Admin Dashboard</span></li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                                <i class="ri-dashboard-line side-menu__icon"></i>
                                <span class="side-menu__label">Dashboard</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/kelola_kategori.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'kelola_kategori.php' ? 'active' : '' ?>">
                                <i class="ri-price-tag-3-line side-menu__icon"></i>
                                <span class="side-menu__label">Kelola Kategori</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/kelola_item.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'kelola_item.php' ? 'active' : '' ?>">
                                <i class="ri-box-3-line side-menu__icon"></i>
                                <span class="side-menu__label">Kelola Item</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/kelola_po.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'kelola_po.php' ? 'active' : '' ?>">
                                <i class="ri-shopping-cart-2-line side-menu__icon"></i>
                                <span class="side-menu__label">Kelola Penyewaan</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/barang_keluar.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'barang_keluar.php' ? 'active' : '' ?>">
                                <i class="ri-truck-line side-menu__icon"></i>
                                <span class="side-menu__label">Barang Keluar</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/kelola_ulasan.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'kelola_ulasan.php' ? 'active' : '' ?>">
                                <i class="ri-star-smile-line side-menu__icon"></i>
                                <span class="side-menu__label">Kelola Ulasan</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/kelola_user.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'kelola_user.php' ? 'active' : '' ?>">
                                <i class="ri-group-line side-menu__icon"></i>
                                <span class="side-menu__label">Kelola User</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>admin/pengaturan.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'pengaturan.php' ? 'active' : '' ?>">
                                <i class="ri-settings-3-line side-menu__icon"></i>
                                <span class="side-menu__label">Pengaturan</span>
                            </a>
                        </li>
                        <li class="slide__category"><span class="category-name">Lainnya</span></li>
                        <li class="slide">
                            <a href="<?= $base_url ?>katalog.php" class="side-menu__item">
                                <i class="ri-arrow-go-back-line side-menu__icon"></i>
                                <span class="side-menu__label">Ke Website Publik</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Public Menu -->
                        <li class="slide">
                            <a href="<?= $base_url ?>index.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                                <span class="side-menu__label">Beranda</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>katalog.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'katalog.php' ? 'active' : '' ?>">
                                <span class="side-menu__label">Katalog Alat</span>
                            </a>
                        </li>
                        <?php if ($isLoggedIn): ?>
                            <li class="slide">
                            <a href="<?= $base_url ?>riwayat_po.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'riwayat_po.php' ? 'active' : '' ?>">
                                <span class="side-menu__label">Riwayat</span>
                            </a>
                        </li>
                        <li class="slide">
                            <a href="<?= $base_url ?>favorit.php" class="side-menu__item <?= basename($_SERVER['PHP_SELF']) == 'favorit.php' ? 'active' : '' ?>">
                                <span class="side-menu__label">Favorit</span>
                            </a>
                        </li>
                            <?php if ($isAdmin): ?>
                                <li class="slide">
                                    <a href="<?= $base_url ?>admin/" class="side-menu__item text-danger fw-bold">
                                        <span class="side-menu__label">Admin Panel</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24"> <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path> </svg></div>
                
                <?php if (!$isAdminArea): ?>
                <div class="d-lg-flex d-none align-items-center gap-4 pe-4">
                    <!-- Theme Toggle Desktop -->
                    <div class="header-theme-mode">
                        <a href="javascript:void(0);" class="header-link layout-setting themeToggleBtn" style="padding: 0;">
                            <span class="light-layout">
                                <i class="ri-moon-clear-line fs-20"></i>
                            </span>
                            <span class="dark-layout" style="display:none;">
                                <i class="ri-sun-line fs-20"></i>
                            </span>
                        </a>
                    </div>

                    <?php if (!$isLoggedIn): ?>
                      <div class="btn-list d-xl-flex d-none">
                          <a href="<?= $base_url ?>login.php" class="btn btn-wave btn-primary border">Login / Register</a>
                      </div>
                      <?php else: ?>
                      
                      <!-- Notifications Dropdown (Desktop) -->
                      <div class="dropdown me-3 notifications-dropdown d-flex align-items-center">
                          <a href="javascript:void(0);" class="d-flex align-items-center text-decoration-none btn-notif-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="color: var(--default-text-color); position: relative; border: none; background: transparent; outline: none; box-shadow: none;">
                              <i class="ri-notification-3-line fs-20"></i>
                              <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle notif-badge" style="display: none; font-size: 0.6rem;">0</span>
                          </a>
                          <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 pt-0" style="width: 300px;">
                              <div class="dropdown-header bg-light d-flex align-items-center justify-content-between p-3 border-bottom">
                                  <span class="fw-semibold fs-15 text-dark">Notifikasi</span>
                                  <a href="<?= $base_url ?>notifikasi.php" class="text-primary fs-12 text-decoration-none">Lihat Semua</a>
                              </div>
                              <div class="dropdown-body p-0 notif-list" style="max-height: 300px; overflow-y: auto;">
                                  <div class="p-3 text-center text-muted fs-13">Belum ada notifikasi</div>
                              </div>
                          </div>
                      </div>

                      <div class="dropdown">
                        <a href="javascript:void(0);" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="desktopHeaderProfile" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--default-text-color);">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['nama'] ?? 'User') ?>&background=random" alt="img" width="32" height="32" class="rounded-circle me-2">
                            <div class="me-1">
                                <p class="fw-semibold mb-0 lh-1"><?= htmlspecialchars($_SESSION['nama'] ?? 'Pengguna') ?></p>
                                <span class="op-7 fw-normal d-block fs-11"><?= $isAdmin ? 'Admin' : 'Pelanggan' ?></span>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="desktopHeaderProfile">
                            <li><a class="dropdown-item d-flex" href="<?= $base_url ?>logout.php"><i class="ti ti-logout fs-18 me-2 op-7"></i>Logout</a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </nav>
        </div>
    </aside>

    <!-- Start::app-content -->
    <div class="main-content <?= $isAdminArea ? 'app-content' : 'landing-main' ?>">
