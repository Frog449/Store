<?php
$stockStatus = $product['stock'] <= 0 ? 'negative' : ($product['stock'] <= $product['minimum'] ? 'low' : 'normal');
$stockLabel = $stockStatus === 'negative' ? 'หมด' : ($stockStatus === 'low' ? 'ควรสั่งซื้อ' : 'ปกติ');
$productImageUrl = inventory_product_image_url($product['image_filename'] ?? null, $product['category'] ?? null);
$hasCustomImage = inventory_has_custom_image($product['image_filename'] ?? null);
?>
<article
    class="product-card product-detail-trigger"
    tabindex="0"
    role="button"
    aria-haspopup="dialog"
    aria-label="ดูรายละเอียด <?= h($product['name']) ?>"
    data-search="<?= h(strtolower($product['name'] . ' ' . $product['id'])) ?>"
    data-code="<?= h($product['id']) ?>"
    data-name="<?= h($product['name']) ?>"
    data-category="<?= h($product['category']) ?>"
    data-type="<?= h($product['type']) ?>"
    data-unit="<?= h($product['unit']) ?>"
    data-location="<?= h($product['location']) ?>"
    data-minimum="<?= h(inventory_quantity($product['minimum'])) ?>"
    data-received="<?= h(inventory_quantity($product['received'])) ?>"
    data-issued="<?= h(inventory_quantity($product['issued'])) ?>"
    data-stock="<?= h(inventory_quantity($product['stock'])) ?>"
    data-status="<?= h($stockLabel) ?>"
    data-status-key="<?= h($stockStatus) ?>"
    data-image="<?= h($productImageUrl) ?>"
    data-has-custom-image="<?= $hasCustomImage ? '1' : '0' ?>"
    style="--card-index: <?= (int) $index ?>"
>
    <div class="product-visual visual-<?= h($product['art'] ?? 'coupler') ?>">
        <span class="visual-grid"></span>
        <span class="product-badge"><?= h($product['type']) ?></span>
        <span class="part-number">PART / <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <img class="product-photo" src="<?= h($productImageUrl) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
        <span class="visual-index">0<?= (int) ($index + 1) ?></span>
    </div>
    <div class="product-content">
        <div class="product-category"><?= h($product['category']) ?><span class="category-dot"></span><?= h($product['unit']) ?></div>
        <h3><?= h($product['name']) ?></h3>
        <div class="product-code">รหัสสินค้า <span><?= h($product['id']) ?></span></div>
        <div class="product-meta">
            <span class="stock-status status-<?= h($stockStatus) ?>"><i></i><?= h($stockLabel) ?></span>
            <span class="location">ชั้น <?= h($product['location']) ?></span>
        </div>
        <div class="product-bottom">
            <div class="quantity-info"><strong><?= number_format((float)$product['stock']) ?></strong><span><?= h($product['unit']) ?> <small>คงเหลือ</small></span></div>
            <span class="card-open-hint" aria-hidden="true">คลิกเพื่อดูรายละเอียด <span>↗</span></span>
        </div>
    </div>
</article>
