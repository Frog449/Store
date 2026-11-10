<?php
$stockStatus = $product['stock'] < 0 ? 'negative' : ($product['stock'] <= $product['minimum'] ? 'low' : 'normal');
$stockLabel = $stockStatus === 'negative' ? 'สต๊อกติดลบ' : ($stockStatus === 'low' ? 'ควรสั่งซื้อ' : 'ปกติ');
$productImageUrl = inventory_product_image_url($product['image_filename'] ?? null);
$hasImage = ($productImageUrl !== null);
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
    data-image="<?= h($productImageUrl ?? '') ?>"
    style="--card-index: <?= (int) $index ?>"
>
    <div class="product-visual visual-<?= h($product['art']) ?>">
        <span class="visual-grid"></span>
        <span class="product-badge"><?= h($product['type']) ?></span>
        <span class="part-number">PART / <?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <?php if ($hasImage): ?>
            <img class="product-photo" src="<?= h($productImageUrl) ?>" alt="<?= h($product['name']) ?>" loading="lazy" onerror="var p=this.parentElement;this.remove();if(p){p.querySelectorAll('.part-art,.art-caption').forEach(function(e){e.style.display='';});}">
        <?php endif; ?>
        <?php if ($product['art'] === 'seal'): ?>
            <svg class="part-art" viewBox="0 0 240 180" fill="none" aria-hidden="true"<?= $hasImage ? ' style="display:none;"' : '' ?>>
                <ellipse cx="120" cy="132" rx="72" ry="20" fill="#192d31" opacity=".12"/>
                <path d="M54 82c0-15 30-27 66-27s66 12 66 27v33c0 15-30 27-66 27s-66-12-66-27V82Z" fill="#344b4d"/>
                <ellipse cx="120" cy="82" rx="66" ry="27" fill="#52686a"/><ellipse cx="120" cy="82" rx="36" ry="14" fill="#d9e3db"/>
                <ellipse cx="120" cy="82" rx="24" ry="8" fill="#f3f5ed"/><path d="M54 99c0 15 30 27 66 27s66-12 66-27" stroke="#718485" stroke-width="3" opacity=".65"/>
                <path d="M71 72c11-8 29-13 49-14" stroke="#98aaa5" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span class="art-caption"<?= $hasImage ? ' style="display:none;"' : '' ?>>ESD<br>SILICONE</span>
        <?php elseif ($product['art'] === 'fitting'): ?>
            <svg class="part-art" viewBox="0 0 240 180" fill="none" aria-hidden="true"<?= $hasImage ? ' style="display:none;"' : '' ?>>
                <ellipse cx="122" cy="137" rx="73" ry="17" fill="#182d32" opacity=".12"/><path d="M78 80h67v28H78z" fill="#d5d9d3"/>
                <path d="M82 84h62v5H82zM82 96h62v5H82z" fill="#9ea8a3"/><path d="M132 68c0-10 8-18 18-18h14c11 0 20 9 20 20v17h-28V72h-24v-4Z" fill="#bbc5be"/>
                <path d="M156 85h28v26h-28z" fill="#dce1da"/><path d="M162 90h16v3h-16zM162 98h16v3h-16z" fill="#9ea8a3"/>
                <path d="M68 76h13v37H68z" fill="#c6cec7"/><path d="M69 82h11M69 90h11M69 98h11M69 106h11" stroke="#919d97" stroke-width="2"/>
                <path d="M89 81h39v26H89z" fill="#e1e4dc"/><path d="M93 85h30v18H93z" fill="#f3f4ed"/><circle cx="151" cy="68" r="7" fill="#e7ebe4"/>
            </svg>
            <span class="art-caption"<?= $hasImage ? ' style="display:none;"' : '' ?>>SMC<br>FITTING</span>
        <?php else: ?>
            <svg class="part-art" viewBox="0 0 240 180" fill="none" aria-hidden="true"<?= $hasImage ? ' style="display:none;"' : '' ?>>
                <ellipse cx="120" cy="137" rx="70" ry="17" fill="#182d32" opacity=".12"/><path d="M65 82c0-13 25-23 55-23s55 10 55 23v31c0 13-25 23-55 23s-55-10-55-23V82Z" fill="#abb8ae"/>
                <ellipse cx="120" cy="82" rx="55" ry="23" fill="#dce2d9"/><ellipse cx="120" cy="82" rx="31" ry="12" fill="#65797a"/>
                <ellipse cx="120" cy="82" rx="19" ry="7" fill="#33494c"/><path d="M65 98c0 13 25 23 55 23s55-10 55-23" stroke="#eef0e9" stroke-width="3" opacity=".8"/>
                <path d="M86 68c9-4 21-7 34-7" stroke="white" stroke-width="3" stroke-linecap="round" opacity=".8"/>
            </svg>
            <span class="art-caption"<?= $hasImage ? ' style="display:none;"' : '' ?>>ONE-TOUCH<br>COUPLER</span>
        <?php endif; ?>
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
            <div class="quantity-info"><strong><?= number_format($product['stock']) ?></strong><span><?= h($product['unit']) ?> <small>คงเหลือ</small></span></div>
            <span class="card-open-hint" aria-hidden="true">คลิกเพื่อดูรายละเอียด <span>↗</span></span>
        </div>
    </div>
</article>
