<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Store Automeation | ภาพรวมคลังสินค้า';
$pageDescription = 'ภาพรวมคลังสินค้า จำนวนสินค้า สถานะสต๊อก และรายการรับเบิก';
$activePage = 'overview';

$isAdmin = auth_is_admin();
$scopeUserId = $isAdmin ? null : (int) ($currentUser['id'] ?? 0);
$scopeUserName = $isAdmin ? null : (string) ($currentUser['name'] ?? '');

$statusCounts = ['normal' => 0, 'reorder' => 0, 'negative' => 0];
$glOptions = inventory_gl_description_options();
$categoryCounts = array_fill_keys($glOptions, 0);
$categoryStockTotals = array_fill_keys($glOptions, 0.0);
$categoryRoundStats = inventory_category_round_stats($scopeUserId, $scopeUserName);
$productsToReorder = 0;

foreach ($products as $product) {
    if ($product['stock'] <= 0) {
        $statusCounts['negative']++;
    } elseif ($product['stock'] <= $product['minimum']) {
        $statusCounts['reorder']++;
        $productsToReorder++;
    } else {
        $statusCounts['normal']++;
    }

    foreach (inventory_product_gl_list($product) as $category) {
        if (!array_key_exists($category, $categoryCounts)) {
            $categoryCounts[$category] = 0;
            $categoryStockTotals[$category] = 0.0;
        }
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
        $categoryStockTotals[$category] = ($categoryStockTotals[$category] ?? 0.0) + (float) $product['stock'];
    }
}

$movementCounts = inventory_movement_counts($scopeUserId, $scopeUserName);
$borrowCounts = inventory_borrow_counts($scopeUserId, $scopeUserName);
$categoryPalette = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#06b6d4', '#f97316', '#ec4899'];
$categoryGradientStops = [];
$categoryLegend = [];
$categoryOffset = 0.0;
$categoryIndex = 0;
$productCount = count($products);
$totalCategoryAssignments = array_sum($categoryCounts);

foreach ($categoryCounts as $category => $count) {
    $color = $categoryPalette[$categoryIndex % count($categoryPalette)];
    $nextOffset = $totalCategoryAssignments > 0 ? $categoryOffset + ($count / $totalCategoryAssignments * 100) : 100;
    $categoryGradientStops[] = $color . ' ' . number_format($categoryOffset, 2, '.', '') . '% ' . number_format($nextOffset, 2, '.', '') . '%';
    $categoryLegend[] = [
        'name'           => $category,
        'count'          => $count,
        'stock'          => $categoryStockTotals[$category] ?? 0.0,
        'receipt_rounds' => $categoryRoundStats[$category]['receipt_rounds'] ?? 0,
        'issue_rounds'   => $categoryRoundStats[$category]['issue_rounds'] ?? 0,
        'color'          => $color,
    ];
    $categoryOffset = $nextOffset;
    $categoryIndex++;
}

$categoryGradient = $categoryGradientStops === []
    || $totalCategoryAssignments === 0
    ? 'conic-gradient(#52525b 0 100%)'
    : 'conic-gradient(' . implode(', ', $categoryGradientStops) . ')';

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-dashboard" aria-labelledby="dashboard-title">
    <header class="dashboard-title">
        <div>
            <h1 id="dashboard-title">รายงานภาพรวมคลังสินค้า</h1>
        </div>
        <a href="catalog.php" class="view-catalog-link">ดูรายการสินค้า <span aria-hidden="true">↗</span></a>
    </header>

    <div class="dashboard-grid">
        <!-- Panel 1: Main Metrics -->
        <section class="dashboard-panel" aria-labelledby="dashboard-metrics">
            <h2 class="dashboard-panel-heading" id="dashboard-metrics">ตัวชี้วัดหลัก</h2>
            <div class="dashboard-rows">
                <div class="dashboard-row">
                    <span>จำนวนสินค้าทั้งหมด</span>
                    <strong><?= number_format($productCount) ?></strong>
                </div>
                <div class="dashboard-row">
                    <span>รายการรับสินค้าทั้งหมด</span>
                    <strong><?= number_format($movementCounts['receipts'] ?? 0) ?></strong>
                </div>
                <div class="dashboard-row">
                    <span><?= $isAdmin ? 'รายการเบิกสินค้าทั้งหมด' : 'รายการเบิกสินค้าของคุณ' ?></span>
                    <strong><a href="issue.php" style="color:inherit;text-decoration:none;"><?= number_format($movementCounts['issues'] ?? 0) ?></a></strong>
                </div>
                <div class="dashboard-row">
                    <span><?= $isAdmin ? 'รายการยืม–คืนทั้งหมด' : 'รายการยืม–คืนของคุณ' ?></span>
                    <strong><a href="borrow.php" style="color:inherit;text-decoration:none;"><?= number_format($borrowCounts['total'] ?? 0) ?></a></strong>
                </div>
            </div>
        </section>

        <!-- Panel 2: Product Status -->
        <section class="dashboard-panel" aria-labelledby="dashboard-status">
            <h2 class="dashboard-panel-heading" id="dashboard-status">สถานะสินค้า</h2>
            <div class="dashboard-rows">
                <div class="dashboard-row">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;flex-shrink:0;"></span>ปกติ
                    </span>
                    <strong style="color:#16a34a;"><?= number_format($statusCounts['normal']) ?></strong>
                </div>
                <div class="dashboard-row" style="<?= $statusCounts['reorder'] > 0 ? 'background:#fffbeb;' : '' ?>">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#f59e0b;flex-shrink:0;"></span>ควรสั่งซื้อ
                    </span>
                    <strong style="color:#d97706;"><?= number_format($statusCounts['reorder']) ?></strong>
                </div>
                <div class="dashboard-row" style="<?= $statusCounts['negative'] > 0 ? 'background:#fff1f2;' : '' ?>">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#ef4444;flex-shrink:0;"></span>หมด
                    </span>
                    <strong style="color:#dc2626;"><?= number_format($statusCounts['negative']) ?></strong>
                </div>
                <div class="dashboard-row" style="<?= ($borrowCounts['borrowed'] ?? 0) > 0 ? 'background:#eff6ff;' : '' ?>">
                    <span style="display:flex;align-items:center;gap:8px;">
                        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#3b82f6;flex-shrink:0;"></span>กำลังยืม
                    </span>
                    <strong style="color:#2563eb;"><a href="borrow.php?status=borrowed" style="color:inherit;text-decoration:none;"><?= number_format($borrowCounts['borrowed'] ?? 0) ?></a></strong>
                </div>
            </div>
        </section>
    </div>

    <!-- Panel 3: Categories & Donut Chart -->
    <section class="dashboard-panel dashboard-category-panel" aria-labelledby="dashboard-categories">
        <h2 class="dashboard-panel-heading" id="dashboard-categories">จำนวนสินค้าแยกตาม GL Description</h2>
        <div class="dashboard-category-content">
            <div class="dashboard-category-table">
                <div class="dashboard-category-row dashboard-category-header">
                    <span>GL Description</span>
                    <span>รายการสินค้า</span>
                    <span>จำนวนสินค้า</span>
                    <span>รายการรับเข้า</span>
                    <span><?= $isAdmin ? 'รายการเบิกออก' : 'เบิกออกของคุณ' ?></span>
                </div>
                <?php foreach ($categoryLegend as $category): ?>
                    <div class="dashboard-category-row">
                        <span><?= h($category['name']) ?></span>
                        <strong><?= number_format($category['count']) ?></strong>
                        <strong><?= h(inventory_quantity($category['stock'])) ?></strong>
                        <strong><?= number_format($category['receipt_rounds']) ?></strong>
                        <strong><?= number_format($category['issue_rounds']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <?php if ($categoryLegend === []): ?>
                    <p class="dashboard-empty">ยังไม่มีข้อมูลหมวดหมู่สินค้า</p>
                <?php endif; ?>
            </div>
            <div class="dashboard-chart" aria-label="แผนภูมิสัดส่วนจำนวนรหัสสินค้าตามหมวดหมู่">
                <div class="dashboard-donut" role="img" aria-label="สินค้า <?= $productCount ?> รหัส แยกตามหมวดหมู่"
                    style="background: <?= h($categoryGradient) ?>">
                    <div class="dashboard-donut-hole">
                        <strong><?= $productCount ?></strong>
                        <span>รหัสสินค้า</span>
                    </div>
                </div>
                <ul class="dashboard-legend">
                    <?php foreach ($categoryLegend as $category): ?>
                        <li>
                            <span style="--legend-color: <?= h($category['color']) ?>"></span>
                            <?= h($category['name']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
