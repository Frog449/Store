<?php
$pageTitle = $pageTitle ?? 'Store Automeation | อะไหล่อุตสาหกรรม';
$pageDescription = $pageDescription ?? 'ทะเบียนสินค้าและระบบจัดการสต๊อก';
$activePage = $activePage ?? 'products';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f6f2">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <title><?= h($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=3.0">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="index.php" aria-label="Store Automeation หน้าแรก">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none"><path d="M4 13.5 16 6l12 7.5v13H4v-13Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M11 26.5v-9h10v9M4.5 13.5h23" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M13.5 10.5h5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
            </span>
            <span class="brand-name">Store <span>Automeation</span><small>INDUSTRIAL SUPPLY</small></span>
        </a>
        <nav class="main-nav" aria-label="เมนูหลัก">
            <a class="nav-link <?= $activePage === 'overview' ? 'active' : '' ?>" href="index.php">ภาพรวม</a>
            <a class="nav-link <?= $activePage === 'products' ? 'active' : '' ?>" href="catalog.php">ทะเบียนสินค้า</a>
            <?php if (!function_exists('auth_is_viewer') || !auth_is_viewer()): ?>
                <a class="nav-link <?= $activePage === 'receive' ? 'active' : '' ?>" href="receive.php">รับสินค้า</a>
                <a class="nav-link <?= $activePage === 'issue' ? 'active' : '' ?>" href="issue.php">เบิกสินค้า</a>
            <?php endif; ?>
            <a class="nav-link <?= $activePage === 'history' ? 'active' : '' ?>" href="history.php">ประวัติรับ–เบิก</a>
            <?php if (function_exists('auth_is_admin') && auth_is_admin()): ?>
                <div class="nav-dropdown <?= in_array($activePage, ['users', 'audit', 'backup'], true) ? 'active' : '' ?>">
                    <button class="nav-dropdown-btn <?= in_array($activePage, ['users', 'audit', 'backup'], true) ? 'active' : '' ?>" type="button" aria-expanded="false" aria-controls="system-management-menu" id="system-menu-btn">
                        <span>จัดการระบบ</span> <span class="nav-dropdown-arrow" aria-hidden="true">▾</span>
                    </button>
                    <div class="nav-dropdown-menu" id="system-management-menu" role="menu">
                        <a class="nav-dropdown-item <?= $activePage === 'users' ? 'active' : '' ?>" href="users.php" role="menuitem">จัดการผู้ใช้</a>
                        <a class="nav-dropdown-item <?= $activePage === 'audit' ? 'active' : '' ?>" href="audit.php" role="menuitem">Audit Log</a>
                        <a class="nav-dropdown-item <?= $activePage === 'backup' ? 'active' : '' ?>" href="backup.php" role="menuitem">สำรองข้อมูล</a>
                    </div>
                </div>
            <?php endif; ?>
            <a class="nav-link <?= $activePage === 'guide' ? 'active' : '' ?>" href="guide.php">คู่มือ</a>
            <a class="nav-link <?= $activePage === 'about' ? 'active' : '' ?>" href="about.php">เกี่ยวกับเรา</a>
        </nav>
        <?php if (!empty($currentUser)): ?>
            <?php
                $displayName = trim((string)($currentUser['name'] ?? 'ผู้ใช้'));
                $cleanName = preg_replace('/\s*\(Admin\)/i', '', $displayName);
            ?>
            <div class="user-profile-widget">
                <span class="user-display-name" title="<?= h($displayName) ?>"><?= h($cleanName) ?></span>
                <span class="user-badge-role role-<?= h($currentUser['role'] ?? 'staff') ?>"><?= h(strtoupper($currentUser['role'] ?? 'staff')) ?></span>
                <a class="user-logout-btn" href="logout.php" title="ออกจากระบบ">ออกจากระบบ</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<script>
(function() {
    function bindSystemMenu() {
        const dropdown = document.querySelector('.nav-dropdown');
        if (!dropdown) return;
        const btn = dropdown.querySelector('.nav-dropdown-btn');
        if (!btn) return;
        btn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = dropdown.classList.toggle('open');
            btn.setAttribute('aria-expanded', String(isOpen));
        };
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindSystemMenu);
    } else {
        bindSystemMenu();
    }
})();
</script>
<main>
<?php if ($notice !== ''): ?>
    <div class="notice notice-<?= h($noticeType) ?>" role="alert"><?= h($notice) ?></div>
<?php endif; ?>
