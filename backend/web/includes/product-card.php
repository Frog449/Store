<?php
$stockStatus = $product['stock'] <= 0 ? 'negative' : ($product['stock'] <= $product['minimum'] ? 'low' : 'normal');
$stockLabel = $stockStatus === 'negative' ? 'หมด' : ($stockStatus === 'low' ? 'ควรสั่งซื้อ' : 'ปกติ');
$glList = $product['gl_list'] ?? inventory_product_gl_list($product);
$glDisplay = implode(', ', $glList);
$primaryGl = $glList[0] ?? 'General';
$productImageUrl = inventory_product_image_url($product['image_filename'] ?? null, $primaryGl);
$hasCustomImage = inventory_has_custom_image($product['image_filename'] ?? null);
$variants = $product['variants'] ?? [$product];
$variantCount = count($variants);

$variantPayloads = [];
$searchTokens = [
    $product['name'] ?? '',
    $product['id'] ?? '',
    $product['stock_code'] ?? '',
    $glDisplay,
    $product['section_code'] ?? '',
    $product['section_name'] ?? '',
    $product['apex'] ?? '',
];
foreach ($variants as $v) {
    $vGlList = inventory_product_gl_list($v);
    $vPrimaryGl = $vGlList[0] ?? 'General';
    $vAc1 = trim((string) ($v['account_code1'] ?? ''));
    $vBuyDate = str_replace('T', ' ', (string) ($v['buy_date'] ?? ''));
    $searchTokens[] = $vAc1;
    $searchTokens[] = (string) ($v['id'] ?? '');
    $variantPayloads[] = [
        'code' => (string) $v['id'],
        'stockCode' => (string) ($v['stock_code'] ?? $v['id']),
        'accountCode1' => $vAc1 !== '' ? $vAc1 : '-',
        'name' => (string) $v['name'],
        'glDescription' => implode(', ', $vGlList),
        'category' => $vPrimaryGl,
        'type' => $vPrimaryGl,
        'sectionCode' => (string) ($v['section_code'] ?? ''),
        'sectionName' => (string) ($v['section_name'] ?? ''),
        'apex' => (string) ($v['apex'] ?? ''),
        'unit' => (string) ($v['unit'] ?? ''),
        'location' => (string) ($v['location'] ?? ''),
        'minimum' => inventory_quantity($v['minimum'] ?? 0),
        'received' => inventory_quantity($v['received'] ?? 0),
        'issued' => inventory_quantity($v['issued'] ?? 0),
        'stock' => inventory_quantity($v['stock'] ?? 0),
        'notes' => (string) ($v['notes'] ?? ''),
        'buyDate' => (string) ($v['buy_date'] ?? ''),
        'buyDateInput' => substr($vBuyDate, 0, 10),
        'buyDateTimeInput' => strlen($vBuyDate) > 10 ? substr($vBuyDate, 11, 5) : '',
        'buyPrice' => isset($v['buy_price']) && $v['buy_price'] !== null && $v['buy_price'] !== '' ? number_format((float) $v['buy_price'], 2) : '',
        'buyPriceRaw' => isset($v['buy_price']) && $v['buy_price'] !== null && $v['buy_price'] !== '' ? number_format((float) $v['buy_price'], 2, '.', '') : '',
        'calNo' => (string) ($v['cal_no'] ?? ''),
        'calDate' => (string) ($v['cal_date'] ?? ''),
        'calExp' => (string) ($v['cal_exp'] ?? ''),
        'borrowedQty' => (int) ($v['borrowed_qty'] ?? 0),
        'borrowedBorrowers' => implode(', ', (array) ($v['borrowed_borrowers'] ?? [])),
        'image' => inventory_product_image_url($v['image_filename'] ?? null, $vPrimaryGl),
        'hasCustomImage' => inventory_has_custom_image($v['image_filename'] ?? null) ? '1' : '0',
    ];
}

$searchHaystack = mb_strtolower(implode(' ', $searchTokens), 'UTF-8');
$primaryAc1 = trim((string) ($product['account_code1'] ?? ''));
$cardAc1Label = $primaryAc1 !== '' ? $primaryAc1 : '-';
if ($variantCount > 1) {
    $cardAc1Label .= ' (+' . ($variantCount - 1) . ')';
}
?>
<article
    class="product-card product-detail-trigger"
    tabindex="0"
    role="button"
    aria-haspopup="dialog"
    aria-label="ดูรายละเอียด <?= h($product['name']) ?>"
    data-search="<?= h($searchHaystack) ?>"
    data-code="<?= h($product['id']) ?>"
    data-name="<?= h($product['name']) ?>"
    data-account-code1="<?= h($primaryAc1 !== '' ? $primaryAc1 : '-') ?>"
    data-stock-code="<?= h($product['stock_code'] ?? $product['id']) ?>"
    data-gl-description="<?= h($glDisplay) ?>"
    data-categories="<?= h(implode('|', $glList)) ?>"
    data-section-code="<?= h($product['section_code'] ?? '') ?>"
    data-section-name="<?= h($product['section_name'] ?? '') ?>"
    data-apex="<?= h($product['apex'] ?? '') ?>"
    data-notes="<?= h($product['notes'] ?? '') ?>"
    data-buy-date="<?= h($product['buy_date'] ?? '') ?>"
    data-buy-date-input="<?= h(substr(str_replace('T', ' ', (string) ($product['buy_date'] ?? '')), 0, 10)) ?>"
    data-buy-date-time-input="<?= h(strlen((string) ($product['buy_date'] ?? '')) > 10 ? substr(str_replace('T', ' ', (string) $product['buy_date']), 11, 5) : '') ?>"
    data-buy-price="<?= h(isset($product['buy_price']) && $product['buy_price'] !== null && $product['buy_price'] !== '' ? number_format((float) $product['buy_price'], 2) : '') ?>"
    data-buy-price-raw="<?= h(isset($product['buy_price']) && $product['buy_price'] !== null && $product['buy_price'] !== '' ? number_format((float) $product['buy_price'], 2, '.', '') : '') ?>"
    data-cal-no="<?= h($product['cal_no'] ?? '') ?>"
    data-cal-date="<?= h($product['cal_date'] ?? '') ?>"
    data-cal-exp="<?= h($product['cal_exp'] ?? '') ?>"
    data-category="<?= h($primaryGl) ?>"
    data-type="<?= h($primaryGl) ?>"
    data-unit="<?= h($product['unit']) ?>"
    data-location="<?= h($product['location']) ?>"
    data-minimum="<?= h(inventory_quantity($product['minimum'])) ?>"
    data-received="<?= h(inventory_quantity($product['received'])) ?>"
    data-issued="<?= h(inventory_quantity($product['issued'])) ?>"
    data-stock="<?= h(inventory_quantity($product['stock'])) ?>"
    data-total-stock="<?= h(inventory_quantity($product['stock'])) ?>"
    data-stock-num="<?= (int) $product['stock'] ?>"
    data-order="<?= (int) $index ?>"
    data-latest-id="<?= (int) ($product['latest_row_id'] ?? $product['row_id'] ?? $index) ?>"
    data-earliest-id="<?= (int) ($product['earliest_row_id'] ?? $product['row_id'] ?? $index) ?>"
    data-latest-ts="<?= (int) (strtotime((string) ($product['latest_created_at'] ?? $product['created_at'] ?? '')) ?: 0) ?>"
    data-earliest-ts="<?= (int) (strtotime((string) ($product['earliest_created_at'] ?? $product['created_at'] ?? '')) ?: 0) ?>"
    data-variant-count="<?= (int) $variantCount ?>"
    data-variants="<?= h(json_encode($variantPayloads, JSON_UNESCAPED_UNICODE)) ?>"
    data-status="<?= h($stockLabel) ?>"
    data-status-key="<?= h($stockStatus) ?>"
    data-borrowed="<?= ((int) ($product['borrowed_qty'] ?? 0)) > 0 ? '1' : '0' ?>"
    data-borrowed-qty="<?= (int) ($product['borrowed_qty'] ?? 0) ?>"
    data-image="<?= h($productImageUrl) ?>"
    data-has-custom-image="<?= $hasCustomImage ? '1' : '0' ?>"
    style="--card-index: <?= (int) $index ?>"
>
    <div class="product-visual visual-<?= h($product['art'] ?? 'coupler') ?>">
        <span class="visual-grid"></span>
        <span class="product-badge"><?= h($primaryGl) ?></span>
        <span class="part-number">PART / <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <img class="product-photo" src="<?= h($productImageUrl) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
        <span class="visual-index"><?= h($cardAc1Label) ?></span>
    </div>
    <div class="product-content">
        <div class="product-category"><?= h($glDisplay) ?><span class="category-dot"></span><?= h($product['unit']) ?></div>
        <h3><?= h($product['name']) ?></h3>
        <div class="product-code">Stock Code <span><?= h($product['stock_code'] ?? $product['id']) ?></span></div>
        <div class="product-meta">
            <div class="product-meta-statuses">
                <span class="stock-status status-<?= h($stockStatus) ?>"><i></i><?= h($stockLabel) ?></span>
                <?php if (((int) ($product['borrowed_qty'] ?? 0)) > 0): ?>
                    <span class="stock-status status-borrowed"><i></i>กำลังยืม <?= (int) $product['borrowed_qty'] ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($product['apex'])): ?>
                <span class="location">Apex <?= h($product['apex']) ?></span>
            <?php elseif (!empty($product['location'])): ?>
                <span class="location">ชั้น <?= h($product['location']) ?></span>
            <?php endif; ?>
        </div>
        <div class="product-bottom">
            <div class="quantity-info"><strong><?= number_format((float)$product['stock']) ?></strong><span><?= h($product['unit']) ?> <small>คงเหลือ</small></span></div>
            <span class="card-open-hint" aria-hidden="true">คลิกเพื่อดูรายละเอียด <span>↗</span></span>
        </div>
    </div>
</article>
