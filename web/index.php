<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Store Automeation';
$pageDescription = 'ภาพรวมคลังสินค้า จำนวนสินค้า สถานะสต๊อก และรายการรับเบิก';
$activePage = 'overview';
$statusCounts = ['normal' => 0, 'reorder' => 0, 'negative' => 0];
$categoryCounts = array_fill_keys(inventory_category_options(), 0);
$categoryStockTotals = array_fill_keys(inventory_category_options(), 0.0);
$categoryRoundStats = inventory_category_round_stats();
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

    $category = trim($product['category']) !== '' ? $product['category'] : 'ไม่ระบุหมวดหมู่';
    if (!array_key_exists($category, $categoryCounts)) {
        $categoryCounts[$category] = 0;
        $categoryStockTotals[$category] = 0.0;
    }
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
    $categoryStockTotals[$category] = ($categoryStockTotals[$category] ?? 0.0) + (float) $product['stock'];
}
$movementCounts = inventory_movement_counts();
$categoryPalette = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#06b6d4', '#f97316', '#ec4899'];
$categoryGradientStops = [];
$categoryLegend = [];
$categoryOffset = 0.0;
$categoryIndex = 0;
$productCount = count($products);
foreach ($categoryCounts as $category => $count) {
    $color = $categoryPalette[$categoryIndex % count($categoryPalette)];
    $nextOffset = $productCount > 0 ? $categoryOffset + ($count / $productCount * 100) : 100;
    $categoryGradientStops[] = $color . ' ' . number_format($categoryOffset, 2, '.', '') . '% ' . number_format($nextOffset, 2, '.', '') . '%';
    $categoryLegend[] = [
        'name' => $category,
        'count' => $count,
        'stock' => $categoryStockTotals[$category] ?? 0.0,
        'receipt_rounds' => $categoryRoundStats[$category]['receipt_rounds'] ?? 0,
        'issue_rounds' => $categoryRoundStats[$category]['issue_rounds'] ?? 0,
        'color' => $color,
    ];
    $categoryOffset = $nextOffset;
    $categoryIndex++;
}
$categoryGradient = $categoryGradientStops === []
    || $productCount === 0
    ? 'conic-gradient(#52525b 0 100%)'
    : 'conic-gradient(' . implode(', ', $categoryGradientStops) . ')';
require __DIR__ . '/includes/header.php';
?>
<section class="inventory-dashboard" aria-labelledby="dashboard-title">
    <header class="dashboard-title">
        <div>
            <h1 id="dashboard-title">รายงานภาพรวมคลังสินค้า</h1>
        </div>
        <a href="catalog.php">ดูรายการสินค้า <span aria-hidden="true">↗</span></a>
    </header>
    <div class="dashboard-grid">
        <section class="dashboard-panel" aria-labelledby="dashboard-metrics">
            <h2 class="dashboard-panel-heading" id="dashboard-metrics">ตัวชี้วัดหลัก</h2>
            <div class="dashboard-rows">
                <div class="dashboard-row"><span>จำนวนสินค้าทั้งหมด</span><strong><?= $productCount ?></strong>
                </div>
                <div class="dashboard-row">
                    <span>รายการรับสินค้าทั้งหมด</span><strong><?= $movementCounts['receipts'] ?></strong>
                </div>
                <div class="dashboard-row">
                    <span>รายการเบิกสินค้าทั้งหมด</span><strong><?= $movementCounts['issues'] ?></strong>
                </div>
            </div>
        </section>
        <section class="dashboard-panel" aria-labelledby="dashboard-status">
            <h2 class="dashboard-panel-heading" id="dashboard-status">สถานะสินค้า</h2>
            <div class="dashboard-rows">
                <div class="dashboard-row">
                    <span style="display:flex;align-items:center;gap:7px;">
                        <span
                            style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;flex-shrink:0;"></span>ปกติ
                    </span>
                    <strong style="color:#16a34a;"><?= $statusCounts['normal'] ?></strong>
                </div>
                <div class="dashboard-row" style="<?= $statusCounts['reorder'] > 0 ? 'background:#fffbeb;' : '' ?>">
                    <span style="display:flex;align-items:center;gap:7px;">
                        <span
                            style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#f59e0b;flex-shrink:0;"></span>ควรสั่งซื้อ
                    </span>
                    <strong
                        style="color:<?= $statusCounts['reorder'] >=0 ? '#d97706' : '#292326' ?>;"><?= $statusCounts['reorder'] ?></strong>
                </div>
                <div class="dashboard-row" style="<?= $statusCounts['negative'] > 0 ? 'background:#fff1f2;' : '' ?>">
                    <span style="display:flex;align-items:center;gap:7px;">
                        <span
                            style="display:inline-block;width:9px;height:9px;border-radius:50%;background:<?= $statusCounts['negative'] >= 0 ? '#ef233c' : '#d1d5db' ?>;flex-shrink:0;"></span>หมด
                    </span>
                    <strong
                        style="color:<?= $statusCounts['negative'] >= 0 ? '#dc2626' : '#292326' ?>;"><?= $statusCounts['negative'] ?></strong>
                </div>
            </div>
        </section>
    </div>
    <section class="dashboard-panel dashboard-category-panel" aria-labelledby="dashboard-categories">
        <h2 class="dashboard-panel-heading" id="dashboard-categories">จำนวนสินค้าแยกตามหมวดหมู่
        </h2>
        <div class="dashboard-category-content">
            <div class="dashboard-category-table">
                <div class="dashboard-category-row dashboard-category-header">
                    <span>หมวดหมู่</span>
                    <span>รายการสินค้า</span>
                    <span>จำนวนสินค้า</span>
                    <span>รายการรับเข้า</span>
                    <span>รายการเบิกออก</span>
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
                    <p class="dashboard-empty">ยังไม่มีข้อมูลหมวดหมู่สินค้า</p><?php endif; ?>
            </div>
            <div class="dashboard-chart" aria-label="แผนภูมิสัดส่วนจำนวนรหัสสินค้าตามหมวดหมู่">
                <div class="dashboard-donut" role="img" aria-label="สินค้า <?= $productCount ?> รหัส แยกตามหมวดหมู่"
                    style="background: <?= h($categoryGradient) ?>">
                    <div class="dashboard-donut-hole"><strong><?= $productCount ?></strong><span>รหัสสินค้า</span></div>
                </div>
                <ul class="dashboard-legend">
                    <?php foreach ($categoryLegend as $category): ?>
                        <li><span style="--legend-color: <?= h($category['color']) ?>"></span><?= h($category['name']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>