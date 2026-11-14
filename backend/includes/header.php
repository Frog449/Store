<?php
$pageTitle = $pageTitle ?? 'Store Automeation | อะไหล่อุตสาหกรรม';
$pageDescription = $pageDescription ?? 'รายการสินค้าและระบบจัดการสต๊อก';
$activePage = $activePage ?? 'products';
$resourcePrefix = app_resource_prefix();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0758b4">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <title><?= h($pageTitle) ?></title>
    <!-- PWA & Mobile Web App Meta -->
    <link rel="manifest" href="<?= $resourcePrefix ?>manifest.json">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $resourcePrefix ?>assets/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Store AUT">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= $resourcePrefix ?>assets/favicon.png?v=<?= filemtime(__DIR__ . '/../assets/favicon.png') ?>">
    <link rel="shortcut icon" href="<?= $resourcePrefix ?>favicon.ico?v=<?= filemtime(__DIR__ . '/../favicon.ico') ?>">
    <link rel="stylesheet" href="style.css?v=3.8">
    <script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?= $resourcePrefix ?>sw.js?v=1.6').catch(() => {});
        });
    }
    </script>
</head>
<body>
<div class="app-layout">
    <!-- Left Sidebar Navigation -->
    <aside class="site-sidebar" id="site-sidebar">
        <!-- Sidebar Brand Header -->
        <div class="sidebar-header">
            <a class="sidebar-brand" href="index.php" aria-label="Store Automeation หน้าแรก">
                <img src="<?= $resourcePrefix ?>assets/logo.png" alt="AUT Automation" class="sidebar-brand-img">
                <div class="sidebar-brand-text">
                    <span class="brand-name">Store <span>Automeation</span></span>
                    <small>INDUSTRIAL SUPPLY</small>
                </div>
            </a>
            <button class="sidebar-close-btn" id="sidebar-close-btn" type="button" aria-label="ปิดเมนู">×</button>
        </div>

        <!-- Sidebar Navigation Links -->
        <nav class="sidebar-nav" aria-label="เมนูหลัก">
            <div class="nav-section-title">เมนูหลัก</div>
            <a class="sidebar-link <?= $activePage === 'overview' ? 'active' : '' ?>" href="index.php">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                <span>ภาพรวม</span>
            </a>
            <a class="sidebar-link <?= $activePage === 'products' ? 'active' : '' ?>" href="catalog.php">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                <span>รายการสินค้า</span>
            </a>

            <?php if (!function_exists('auth_is_viewer') || !auth_is_viewer()): ?>
                <div class="nav-section-title">คลังสินค้า & สต๊อก</div>
                <a class="sidebar-link <?= $activePage === 'receive' ? 'active' : '' ?>" href="receive.php">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
                    <span>รับสินค้าเข้า</span>
                </a>
                <a class="sidebar-link <?= $activePage === 'issue' ? 'active' : '' ?>" href="issue.php">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
                    <span>เบิกสินค้าออก</span>
                </a>
            <?php endif; ?>

            <div class="nav-section-title">พัสดุ & ประวัติ</div>
            <a class="sidebar-link <?= $activePage === 'borrow' ? 'active' : '' ?>" href="borrow.php">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 3h5v5"/><path d="M4 20L21 3"/><path d="M21 16v5h-5"/><path d="M15 15l6 6"/><path d="M4 4l5 5"/></svg>
                <span>ยืม–คืนพัสดุ</span>
            </a>
            <a class="sidebar-link <?= $activePage === 'history' ? 'active' : '' ?>" href="history.php">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>ประวัติรับ–เบิก</span>
            </a>

            <?php if (function_exists('auth_is_admin') && auth_is_admin()): ?>
                <?php $isSysActive = in_array($activePage, ['users', 'settings', 'audit', 'backup'], true); ?>
                <div class="nav-section-title">ผู้ดูแลระบบ</div>
                <div class="sidebar-accordion <?= $isSysActive ? 'open' : '' ?>" id="sidebar-admin-accordion">
                    <button class="sidebar-link sidebar-accordion-btn <?= $isSysActive ? 'active' : '' ?>" type="button" aria-expanded="<?= $isSysActive ? 'true' : 'false' ?>" id="sidebar-admin-toggle">
                        <span style="display:flex;align-items:center;gap:10px;">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            <span>จัดการระบบ</span>
                        </span>
                        <span class="accordion-arrow">▾</span>
                    </button>
                    <div class="sidebar-submenu">
                        <a class="sidebar-sublink <?= $activePage === 'users' ? 'active' : '' ?>" href="users.php">จัดการผู้ใช้</a>
                        <a class="sidebar-sublink <?= $activePage === 'settings' ? 'active' : '' ?>" href="settings.php">ตั้งค่าระบบ (Telegram / Sheets)</a>
                        <a class="sidebar-sublink <?= $activePage === 'audit' ? 'active' : '' ?>" href="audit.php">Audit Log</a>
                        <a class="sidebar-sublink <?= $activePage === 'backup' ? 'active' : '' ?>" href="backup.php">สำรองข้อมูล</a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="nav-section-title">อื่นๆ</div>
            <?php if (!is_running_in_app()): ?>
                <a class="sidebar-link sidebar-link-app <?= $activePage === 'app' ? 'active' : '' ?>" href="app.php">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                    <span>ดาวน์โหลดแอป</span>
                </a>
            <?php endif; ?>
            <?php if (!function_exists('auth_is_viewer') || !auth_is_viewer()): ?>
                <a class="sidebar-link <?= $activePage === 'guide' ? 'active' : '' ?>" href="guide.php">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span>คู่มือการใช้งาน</span>
                </a>
            <?php endif; ?>
            <a class="sidebar-link <?= $activePage === 'about' ? 'active' : '' ?>" href="about.php">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>เกี่ยวกับเรา</span>
            </a>
        </nav>

        <!-- Sidebar User Footer -->
        <?php if (!empty($currentUser)): ?>
            <?php
                $displayName = trim((string)($currentUser['name'] ?? 'ผู้ใช้'));
                $cleanName = preg_replace('/\s*\(Admin\)/i', '', $displayName);
            ?>
            <div class="sidebar-footer">
                <div class="sidebar-user-card">
                    <a href="profile.php" class="sidebar-user-avatar" title="แก้ไขข้อมูลส่วนตัว">
                        <?= mb_strtoupper(mb_substr($cleanName, 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </a>
                    <div class="sidebar-user-info">
                        <a href="profile.php" class="sidebar-user-name" title="คลิกเพื่อแก้ไขข้อมูลโปรไฟล์"><?= h($cleanName) ?></a>
                        <div class="sidebar-user-meta">
                            <span class="user-badge-role role-<?= h($currentUser['role'] ?? 'staff') ?>"><?= h(strtoupper($currentUser['role'] ?? 'staff')) ?></span>
                        </div>
                    </div>
                    <a class="sidebar-logout-btn" href="logout.php" title="ออกจากระบบ" aria-label="ออกจากระบบ">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </aside>

    <!-- Backdrop for Mobile Drawer -->
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <!-- Main Content Wrapper -->
    <div class="app-content-wrapper">
        <!-- Mobile Header (<= 960px) -->
        <header class="mobile-top-header">
            <button class="mobile-menu-toggle" id="mobile-menu-toggle" type="button" aria-label="เปิดเมนู">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <a class="mobile-brand" href="index.php">
                <img src="<?= $resourcePrefix ?>assets/logo.png" alt="AUT Automation" style="height:26px;width:auto;object-fit:contain;">
                <span>Store <b>Automeation</b></span>
            </a>
            <?php if (!empty($currentUser)): ?>
                <a href="profile.php" class="mobile-user-avatar" title="<?= h($currentUser['name'] ?? '') ?>">
                    <?= mb_strtoupper(mb_substr($cleanName, 0, 1, 'UTF-8'), 'UTF-8') ?>
                </a>
            <?php else: ?>
                <div style="width:32px;"></div>
            <?php endif; ?>
        </header>

        <main>
        <?php if ($notice !== ''): ?>
            <div class="notice notice-<?= h($noticeType) ?>" role="alert"><?= h($notice) ?></div>
        <?php endif; ?>
