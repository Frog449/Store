<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'รายการสินค้า | Store Automeation';
$pageDescription = 'ค้นหาและจัดการรายการสินค้าและจำนวนคงเหลือ';
$activePage = 'products';
$uploadedProductImage = null;
$editCode = isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    $editCode = isset($_POST['original_code']) && is_string($_POST['original_code'])
        ? trim($_POST['original_code'])
        : null;
}
$editProduct = $editCode === null ? null : inventory_product($editCode);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync_catalog_to_sheet') {
    try {
        inventory_require_csrf();
        if (!auth_can_manage_stock()) {
            throw new DomainException('เฉพาะ Admin และ Staff เท่านั้นที่สามารถซิงค์ข้อมูลกับ Google Sheet ได้');
        }
        $res = inventory_sync_all_catalog_to_google_sheet();
        if ($res['success']) {
            inventory_flash_redirect('ซิงค์รายการสินค้าทั้งหมดไปยัง Google Sheet สำเร็จแล้ว', 'catalog.php');
        } else {
            throw new DomainException('ซิงค์กับ Google Sheet ไม่สำเร็จ: ' . $res['message']);
        }
    } catch (DomainException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    try {
        inventory_require_csrf();
        if (!auth_is_admin()) {
            throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถแก้ไขข้อมูลสินค้าได้');
        }
        $originalCode = trim((string) ($_POST['original_code'] ?? ''));
        if ($originalCode === '' || inventory_product($originalCode) === null) {
            throw new DomainException('ไม่พบสินค้าที่ต้องการแก้ไข');
        }

        $code = inventory_text('code', 'รหัสสินค้า', 80, true);
        if ($code !== $originalCode) {
            throw new DomainException('รหัสสินค้าเดิมไม่สามารถเปลี่ยนได้');
        }

        $newImage = inventory_store_product_image($_FILES['product_image'] ?? null);
        $uploadedProductImage = $newImage;
        $removeImage = ($_POST['remove_image'] ?? '') === '1';
        if ($newImage !== null && $removeImage) {
            throw new DomainException('เลือกอัปโหลดรูปใหม่หรือเอารูปเดิมออกอย่างใดอย่างหนึ่ง');
        }
        $imageFilename = $newImage
            ?? ($removeImage ? null : $editProduct['image_filename']);
        $glDescription = inventory_gl_description_input('gl_description');
        $glList = inventory_normalize_gl_list($glDescription);
        $primaryCategory = $glList[0] ?? 'General';
        $accountCode1 = inventory_text('account_code1', 'Account Code1', 100);
        if ($accountCode1 === '') {
            $accountCode1 = '-';
        }
        $stockCode = inventory_text('stock_code', 'Stock Code', 100);
        if ($stockCode === '' || $stockCode === '—') {
            $stockCode = '-';
        }
        $reorderRaw = trim((string) ($_POST['reorder_point'] ?? ''));
        $reorderPoint = $reorderRaw === '' ? 0.0 : inventory_number('reorder_point', 'จุดสั่งซื้อขั้นต่ำ');
        inventory_save_product([
            'code' => $code,
            'stock_code' => $stockCode,
            'account_code1' => $accountCode1,
            'gl_description' => $glDescription,
            'section_code' => inventory_text('section_code', 'Section code', 100),
            'section_name' => inventory_text('section_name', 'Section Name', 255),
            'apex' => inventory_apex_input('apex'),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
            'buy_date' => inventory_optional_datetime('buy_date'),
            'buy_price' => inventory_optional_number('buy_price', 'BuyPrice/Unit', 2),
            'name' => inventory_text('name', 'Name', 250, true),
            'category' => $primaryCategory,
            'item_type' => $primaryCategory,
            'unit' => inventory_choice('unit', 'Unit', inventory_unit_options()),
            'location' => $editProduct['location'],
            'reorder_point' => $reorderPoint,
            'art' => $editProduct['art'],
            'image_filename' => $imageFilename,
        ], $originalCode);
        if ($imageFilename !== $editProduct['image_filename']) {
            inventory_delete_product_image($editProduct['image_filename']);
        }
        inventory_flash_redirect('บันทึกการแก้ไขสินค้าแล้ว', 'catalog.php');
    } catch (DomainException|RuntimeException $exception) {
        inventory_delete_product_image($uploadedProductImage);
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    try {
        inventory_require_csrf();
        if (!auth_is_admin()) {
            throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถลบสินค้าได้');
        }
        $code = inventory_text('product_code', 'รหัสสินค้า', 80, true);
        $imageFilename = inventory_delete_product($code);
        inventory_delete_product_image($imageFilename);
        inventory_flash_redirect('ลบสินค้าและประวัติรับ–เบิกที่เกี่ยวข้องแล้ว', 'catalog.php');
    } catch (DomainException|RuntimeException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

$glDescriptionOptions = inventory_gl_description_options();
$apexOptions = inventory_apex_options();
$categoryOptions = $glDescriptionOptions;
$typeOptions = inventory_type_options();
$unitOptions = inventory_unit_options();
$editGlList = $editProduct !== null ? inventory_product_gl_list($editProduct) : [];
$editProductImageUrl = inventory_product_image_url($editProduct['image_filename'] ?? null, $editGlList[0] ?? 'General');
$groupedProducts = inventory_group_products_by_stock_code($products);
$categoryCounts = [];
foreach ($categoryOptions as $cat) {
    $categoryCounts[$cat] = 0;
}
$statusCounts = ['normal' => 0, 'low' => 0, 'negative' => 0];
foreach ($groupedProducts as $product) {
    foreach ($product['gl_list'] as $glCat) {
        $categoryCounts[$glCat] = ($categoryCounts[$glCat] ?? 0) + 1;
    }
    if ($product['stock'] <= 0) {
        $statusCounts['negative']++;
    } elseif ($product['stock'] <= $product['minimum']) {
        $statusCounts['low']++;
    } else {
        $statusCounts['normal']++;
    }
}
require __DIR__ . '/includes/header.php';
?>
<section class="catalog-section catalog-page" id="catalog">
    <div class="section-heading">
        <div>
            <div class="eyebrow section-eyebrow">PRODUCT REGISTER</div>
            <h1>รายการสินค้า<span class="heading-period">.</span></h1>
            <p>คลิกการ์ดสินค้าเพื่อดูรายละเอียด<?= auth_is_admin() ? ' รูปสินค้าเพิ่มหรือเปลี่ยนได้ในหน้าต่างแก้ไข' : '' ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <a class="secondary-button" href="export.php?type=products" style="font-size:11px;">Export รายการสินค้า (.csv)</a>
            <?php if (auth_can_manage_stock()): ?>
                <form method="post" style="display:inline;margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="sync_catalog_to_sheet">
                    <button type="submit" class="secondary-button" style="font-size:11px;" title="ซิงค์ข้อมูลสินค้าและยอดคงเหลือทั้งหมดไปยัง Google Sheet (แท็บ รายการสินค้า)">
                        ซิงค์กับ Google Sheet
                    </button>
                </form>
            <?php endif; ?>
            <label class="search-box">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <input id="product-search" type="search" placeholder="ค้นหาชื่อ, Stock Code, Account Code1..." autocomplete="off">
                <kbd>/</kbd>
            </label>
        </div>
    </div>
    <div class="catalog-toolbar" style="flex-wrap:wrap;gap:10px 14px;">
        <div class="category-filters" role="group" aria-label="กรองตามหมวดหมู่">
            <button class="filter-chip selected" type="button" data-category="all">สินค้าทั้งหมด <span><?= count($groupedProducts) ?></span></button>
            <?php foreach ($categoryCounts as $category => $count): ?>
                <button class="filter-chip" type="button" data-category="<?= h($category) ?>"><?= h($category) ?> <span><?= $count ?></span></button>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;gap:10px;margin-left:auto;flex-wrap:wrap;">
            <div class="status-filters" role="group" aria-label="กรองตามสถานะสต๊อก" style="display:flex;align-items:center;gap:6px;">
                <button class="status-filter-chip" type="button" data-status-filter="normal" title="กรองเฉพาะสินค้าสถานะปกติ">
                    <span class="status-dot dot-normal"></span>ปกติ <span><?= $statusCounts['normal'] ?></span>
                </button>
                <button class="status-filter-chip" type="button" data-status-filter="low" title="กรองเฉพาะสินค้าที่ควรสั่งซื้อ">
                    <span class="status-dot dot-low"></span>ควรสั่งซื้อ <span><?= $statusCounts['low'] ?></span>
                </button>
                <button class="status-filter-chip" type="button" data-status-filter="negative" title="กรองเฉพาะสินค้าที่หมดสต๊อก">
                    <span class="status-dot dot-negative"></span>หมด <span><?= $statusCounts['negative'] ?></span>
                </button>
            </div>
            <select id="product-sort" class="user-filter-select" aria-label="เรียงลำดับสินค้า" style="min-height:32px;font-size:11px;">
                <option value="default">เรียงตามลำดับเริ่มต้น</option>
                <option value="newest">ล่าสุด → เก่าสุด</option>
                <option value="oldest">เก่าสุด → ล่าสุด</option>
                <option value="stock-desc">จำนวน: มาก → น้อย</option>
                <option value="stock-asc">จำนวน: น้อย → มาก</option>
            </select>
            <?php if (auth_is_admin()): ?>
                <a class="primary-button" href="receive.php?new=1#new-product" style="min-height:32px;padding:0 14px;font-size:11px;font-weight:600;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;">
                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    <span>เพิ่มสินค้าใหม่</span>
                </a>
            <?php endif; ?>
            <span class="result-count" id="result-count">แสดง <?= count($groupedProducts) ?> รายการ</span>
        </div>
    </div>
    <div class="product-grid" id="product-grid">
        <?php foreach ($groupedProducts as $index => $product): ?>
            <?php require __DIR__ . '/includes/product-card.php'; ?>
        <?php endforeach; ?>
        <div class="empty-search" id="empty-search" hidden>ไม่พบสินค้าที่ตรงกับคำค้นหา ลองค้นหาด้วยคำอื่น</div>
    </div>
    <dialog class="product-detail-dialog" id="product-detail-dialog" aria-labelledby="product-detail-title">
        <div class="product-detail-image-wrap">
            <img id="product-detail-image" alt="รูปสินค้า" hidden>
            <span id="product-detail-image-empty">ยังไม่มีรูปสินค้า</span>
        </div>
        <div class="product-detail-heading">
            <div><div class="eyebrow section-eyebrow">PRODUCT DETAILS</div><h2 id="product-detail-title"></h2><p id="product-detail-code"></p></div>
            <button class="product-detail-close" type="button" aria-label="ปิดรายละเอียด" data-close-product-dialog>×</button>
        </div>
        <dl class="product-detail-list">
            <div class="detail-account-row">
                <dt>Account Code1</dt>
                <dd>
                    <button type="button" class="account-code-picker-btn" id="product-detail-account-toggle" aria-expanded="false" title="คลิกเพื่อดูรายการ Account Code1 ทั้งหมดของ Stock Code นี้">
                        <span data-detail="accountCode1">-</span>
                        <span class="account-code-count-badge" id="product-detail-account-count" hidden></span>
                    </button>
                </dd>
            </div>
            <div><dt>Stock Code</dt><dd data-detail="stockCode"></dd></div>
            <div class="account-code-list-panel" id="product-detail-account-list" hidden></div>
            <div><dt>GL Description</dt><dd data-detail="glDescription"></dd></div>
            <div><dt>Section code</dt><dd data-detail="sectionCode"></dd></div>
            <div><dt>Section Name</dt><dd data-detail="sectionName"></dd></div>
            <div><dt>Apex</dt><dd data-detail="apex"></dd></div>
            <div><dt>Unit</dt><dd data-detail="unit"></dd></div>
            <div><dt>Quantity</dt><dd data-detail="stock"></dd></div>
            <div><dt>จุดสั่งซื้อขั้นต่ำ</dt><dd data-detail="minimum"></dd></div>
            <div><dt>Buydate</dt><dd data-detail="buyDate"></dd></div>
            <div><dt>BuyPrice/Unit</dt><dd data-detail="buyPrice"></dd></div>
            <div><dt>หมายเหตุ</dt><dd data-detail="notes"></dd></div>
        </dl>
        <div class="product-detail-actions">
            <?php if (auth_is_admin()): ?>
                <form class="product-delete-form" method="post" data-product-delete-form>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_code" value="">
                    <button class="danger-button" type="submit">ลบสินค้า</button>
                </form>
            <?php endif; ?>
            <button class="secondary-button" type="button" data-close-product-dialog>ปิด</button>
            <?php if (!auth_is_viewer()): ?>
                <a class="secondary-button" id="product-detail-borrow" href="borrow.php">ยืมสินค้า</a>
                <a class="secondary-button" id="product-detail-issue" href="issue.php">เบิกสินค้า</a>
            <?php endif; ?>
            <?php if (auth_is_admin()): ?>
                <button class="primary-button" type="button" id="product-detail-edit">แก้ไขข้อมูล</button>
            <?php endif; ?>
        </div>
    </dialog>
    <?php if (auth_is_admin()): ?>
    <dialog class="product-detail-dialog product-edit-dialog" id="product-edit-dialog" aria-labelledby="product-edit-title" data-edit-code="<?= h($editProduct['code'] ?? '') ?>">
        <div class="product-detail-heading">
            <div><div class="eyebrow section-eyebrow">EDIT PRODUCT</div><h2 id="product-edit-title">แก้ไขข้อมูลสินค้า</h2><p id="product-edit-code"></p></div>
            <button class="product-detail-close" type="button" aria-label="ปิดหน้าต่างแก้ไข" data-close-edit-dialog>×</button>
        </div>
        <form class="inventory-form product-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_product">
            <input type="hidden" name="original_code" value="<?= h($editProduct['code'] ?? '') ?>">
            <input type="hidden" name="remove_image" value="0">
            <input type="hidden" name="code" value="<?= h($editProduct['code'] ?? '') ?>">
            <label>Account Code1 <input name="account_code1" maxlength="100" placeholder="ถ้าไม่มีใส่ -" value="<?= h((string) ($_POST['account_code1'] ?? $editProduct['account_code1'] ?? '')) ?>"></label>
            <label>Stock Code <input name="stock_code" maxlength="100" placeholder="ถ้าไม่มีใส่ -" value="<?= h((string) ($_POST['stock_code'] ?? $editProduct['stock_code'] ?? '')) ?>"></label>
            <?php
            $editPostedGlRaw = $_POST['gl_description'] ?? $editGlList;
            $editSelectedGl = is_array($editPostedGlRaw) ? $editPostedGlRaw : inventory_normalize_gl_list((string) $editPostedGlRaw);
            $editApexVal = (string) ($_POST['apex'] ?? $editProduct['apex'] ?? '');
            ?>
            <div class="form-field form-span-2">
                <span class="form-field-label">GL Description</span>
                <div class="multi-dropdown" id="edit-gl-description-dropdown" data-multi-dropdown data-placeholder="เลือก GL Description (เลือกได้หลายหมวด)">
                    <button type="button" class="multi-dropdown-toggle" data-multi-toggle aria-expanded="false">
                        <span class="multi-dropdown-label" data-multi-label><?= $editSelectedGl !== [] ? h(implode(', ', $editSelectedGl)) : 'เลือก GL Description (เลือกได้หลายหมวด)' ?></span>
                        <span class="multi-dropdown-arrow">▾</span>
                    </button>
                    <div class="multi-dropdown-menu" data-multi-menu hidden>
                        <?php foreach ($glDescriptionOptions as $option): ?>
                            <label class="multi-dropdown-option">
                                <input type="checkbox" name="gl_description[]" value="<?= h($option) ?>" <?= in_array($option, $editSelectedGl, true) ? 'checked' : '' ?>>
                                <span><?= h($option) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <label>Section code <input name="section_code" maxlength="100" value="<?= h((string) ($_POST['section_code'] ?? $editProduct['section_code'] ?? '')) ?>"></label>
            <label>Section Name <input name="section_name" maxlength="255" value="<?= h((string) ($_POST['section_name'] ?? $editProduct['section_name'] ?? '')) ?>"></label>
            <label>Apex <select name="apex">
                <option value="">เลือก Apex</option>
                <?php foreach ($apexOptions as $option): ?><option value="<?= h($option) ?>" <?= $editApexVal === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label class="form-span-2">Name <input name="name" maxlength="250" required value="<?= h((string) ($_POST['name'] ?? $editProduct['name'] ?? '')) ?>"></label>
            <label>Unit <select name="unit" required>
                <option value="">เลือกหน่วยนับ</option>
                <?php foreach ($unitOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['unit'] ?? $editProduct['unit'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>จุดสั่งซื้อขั้นต่ำ <input name="reorder_point" type="number" step="1" min="0" placeholder="ไม่บังคับ" value="<?= h((string) ($_POST['reorder_point'] ?? (isset($editProduct['reorder_point']) ? (int) round((float) $editProduct['reorder_point']) : ''))) ?>"></label>
            <label>Buydate <input name="buy_date" type="datetime-local" value="<?= h((string) ($_POST['buy_date'] ?? str_replace(' ', 'T', substr((string) ($editProduct['buy_date'] ?? ''), 0, 16)))) ?>"></label>
            <label>BuyPrice/Unit <input name="buy_price" type="number" step="0.01" min="0" placeholder="ไม่บังคับ" value="<?= h((string) ($_POST['buy_price'] ?? (isset($editProduct['buy_price']) && $editProduct['buy_price'] !== null ? number_format((float) $editProduct['buy_price'], 2, '.', '') : ''))) ?>"></label>
            <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500" value="<?= h((string) ($_POST['notes'] ?? $editProduct['notes'] ?? '')) ?>"></label>
            <label class="product-image-upload-field form-span-2">รูปสินค้า
                <input class="product-image-upload" type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB</small>
                <span class="product-image-preview" data-current-image="<?= h($editProductImageUrl ?? '') ?>">
                    <img alt="ตัวอย่างรูปสินค้า" <?= $editProductImageUrl === null ? 'hidden' : 'src="' . h($editProductImageUrl) . '"' ?> onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                    <span <?= $editProductImageUrl === null ? '' : 'hidden' ?>>ยังไม่ได้เลือกรูปสินค้า</span>
                </span>
                <span class="product-image-remove"><input type="checkbox" class="product-image-remove-toggle" value="1" <?= !empty($_POST['remove_image']) ? 'checked' : '' ?>> เอารูปสินค้าเดิมออก</span>
            </label>

            <div class="form-actions form-span-2">
                <button class="secondary-button" type="button" data-back-to-product-details>กลับไปข้อมูลสินค้า</button>
                <button class="primary-button" type="submit">บันทึกการแก้ไข</button>
            </div>
        </form>
    </dialog>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
