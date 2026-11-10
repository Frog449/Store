<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Store Automeation';
$pageDescription = 'ภาพรวมคลังสินค้า จำนวนสินค้า สถานะสต๊อก และรายการรับเบิก';
$activePage = 'overview';
$statusCounts = ['normal' => 0, 'reorder' => 0, 'negative' => 0];
$categoryCounts = array_fill_keys(inventory_category_options(), 0);
$productsToReorder = 0;
foreach ($products as $product) {
    if ($product['stock'] < 0) {
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
    }
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
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
    $categoryLegend[] = ['name' => $category, 'count' => $count, 'color' => $color];
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
        <div><h1 id="dashboard-title">รายงานภาพรวมคลังสินค้า</h1><p>สรุปสถานะสต๊อกและรายการเคลื่อนไหวจากข้อมูลในทะเบียน</p></div>
        <a href="catalog.php">ดูทะเบียนสินค้า <span aria-hidden="true">↗</span></a>
    </header>
    <div class="dashboard-grid">
        <section class="dashboard-panel" aria-labelledby="dashboard-metrics">
            <h2 class="dashboard-panel-heading" id="dashboard-metrics">ตัวชี้วัดหลัก</h2>
            <div class="dashboard-rows">
                <div class="dashboard-row"><span>จำนวนรหัสสินค้าที่ลงทะเบียน</span><strong><?= $productCount ?></strong></div>
                <div class="dashboard-row"><span>จำนวนรายการรับสินค้า</span><strong><?= $movementCounts['receipts'] ?></strong></div>
                <div class="dashboard-row"><span>จำนวนรายการเบิกสินค้า</span><strong><?= $movementCounts['issues'] ?></strong></div>
                <div class="dashboard-row"><span>สินค้าที่ควรสั่งซื้อ</span><strong><?= $productsToReorder ?></strong></div>
                <div class="dashboard-row"><span>รายการสต๊อกติดลบ</span><strong><?= $statusCounts['negative'] ?></strong></div>
            </div>
        </section>
        <section class="dashboard-panel" aria-labelledby="dashboard-status">
            <h2 class="dashboard-panel-heading" id="dashboard-status">สถานะสินค้า</h2>
            <div class="dashboard-rows">
                <div class="dashboard-row"><span>ปกติ</span><strong><?= $statusCounts['normal'] ?></strong></div>
                <div class="dashboard-row"><span>ควรสั่งซื้อ</span><strong><?= $statusCounts['reorder'] ?></strong></div>
                <div class="dashboard-row"><span>สต๊อกติดลบ</span><strong><?= $statusCounts['negative'] ?></strong></div>
            </div>
        </section>
    </div>
    <section class="dashboard-panel dashboard-category-panel" aria-labelledby="dashboard-categories">
        <h2 class="dashboard-panel-heading" id="dashboard-categories">จำนวนสินค้าแยกตามหมวดหมู่ <span>(นับเป็นรหัสสินค้า)</span></h2>
        <div class="dashboard-category-content">
            <div class="dashboard-category-table">
                <div class="dashboard-category-row dashboard-category-header"><span>หมวดหมู่</span><span>จำนวนรหัสสินค้า</span></div>
                <?php foreach ($categoryLegend as $category): ?>
                    <div class="dashboard-category-row"><span><?= h($category['name']) ?></span><strong><?= $category['count'] ?></strong></div>
                <?php endforeach; ?>
                <?php if ($categoryLegend === []): ?><p class="dashboard-empty">ยังไม่มีข้อมูลหมวดหมู่สินค้า</p><?php endif; ?>
            </div>
            <div class="dashboard-chart" aria-label="แผนภูมิสัดส่วนจำนวนรหัสสินค้าตามหมวดหมู่">
                <div class="dashboard-donut" role="img" aria-label="สินค้า <?= $productCount ?> รหัส แยกตามหมวดหมู่" style="background: <?= h($categoryGradient) ?>">
                    <div class="dashboard-donut-hole"><strong><?= $productCount ?></strong><span>รหัสสินค้า</span></div>
                </div>
                <ul class="dashboard-legend">
                    <?php foreach ($categoryLegend as $category): ?>
                        <li><span style="--legend-color: <?= h($category['color']) ?>"></span><?= h($category['name']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
