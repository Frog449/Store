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
    <link rel="stylesheet" href="style.css">
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
            <a class="nav-link <?= $activePage === 'receive' ? 'active' : '' ?>" href="receive.php">รับสินค้า</a>
            <a class="nav-link <?= $activePage === 'issue' ? 'active' : '' ?>" href="issue.php">เบิกสินค้า</a>
            <a class="nav-link <?= $activePage === 'guide' ? 'active' : '' ?>" href="guide.php">คู่มือ</a>
            <a class="nav-link <?= $activePage === 'about' ? 'active' : '' ?>" href="about.php">เกี่ยวกับเรา</a>
        </nav>
    </div>
</header>
<main>
<?php if ($notice !== ''): ?>
    <div class="notice notice-<?= h($noticeType) ?>" role="alert"><?= h($notice) ?></div>
<?php endif; ?>
