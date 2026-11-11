<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'ทะเบียนสินค้า | Store Automeation';
$pageDescription = 'ค้นหาและจัดการทะเบียนสินค้าและจำนวนคงเหลือ';
$activePage = 'products';
$uploadedProductImage = null;
$editCode = isset($_GET['edit']) && is_string($_GET['edit']) ? $_GET['edit'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    $editCode = isset($_POST['original_code']) && is_string($_POST['original_code'])
        ? trim($_POST['original_code'])
        : null;
}
$editProduct = $editCode === null ? null : inventory_product($editCode);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_product') {
    try {
        inventory_require_csrf();
        if (!auth_can_manage_stock()) {
            throw new DomainException('เฉพาะ Admin และ Staff เท่านั้นที่สามารถแก้ไขสินค้าได้');
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
        inventory_save_product([
            'code' => $code,
            'name' => inventory_text('name', 'ชื่อสินค้า', 250, true),
            'category' => inventory_choice('category', 'หมวดหมู่', inventory_category_options()),
            'item_type' => inventory_choice('item_type', 'ประเภท', inventory_type_options()),
            'unit' => inventory_choice('unit', 'หน่วยนับ', inventory_unit_options()),
            'location' => inventory_text('location', 'ตำแหน่งจัดเก็บ', 80),
            'reorder_point' => inventory_number('reorder_point', 'จุดสั่งซื้อขั้นต่ำ'),
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

$categoryOptions = inventory_category_options();
$typeOptions = inventory_type_options();
$unitOptions = inventory_unit_options();
$editProductImageUrl = inventory_product_image_url($editProduct['image_filename'] ?? null);
$categoryCounts = [];
foreach ($products as $product) {
    $category = trim($product['category']) !== '' ? $product['category'] : 'ไม่ระบุหมวดหมู่';
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}
require __DIR__ . '/includes/header.php';
?>
<section class="catalog-section catalog-page" id="catalog">
    <div class="section-heading">
        <div>
            <div class="eyebrow section-eyebrow">PRODUCT REGISTER</div>
            <h1>ทะเบียนสินค้า<span class="heading-period">.</span></h1>
            <p>คลิกการ์ดสินค้าเพื่อดูรายละเอียด รูปสินค้าเพิ่มหรือเปลี่ยนได้ในหน้าต่างแก้ไข</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <a class="secondary-button" href="export.php?type=products" style="font-size:11px;">📊 Export ทะเบียนสินค้า (.csv)</a>
            <label class="search-box">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <input id="product-search" type="search" placeholder="ค้นหาชื่อหรือรหัสสินค้า..." autocomplete="off">
                <kbd>/</kbd>
            </label>
        </div>
    </div>
    <div class="catalog-toolbar">
        <div class="category-filters" role="group" aria-label="กรองตามหมวดหมู่">
            <button class="filter-chip selected" type="button" data-category="all">สินค้าทั้งหมด <span><?= count($products) ?></span></button>
            <?php foreach ($categoryCounts as $category => $count): ?>
                <button class="filter-chip" type="button" data-category="<?= h($category) ?>"><?= h($category) ?> <span><?= $count ?></span></button>
            <?php endforeach; ?>
        </div>
        <span class="result-count" id="result-count">แสดง <?= count($products) ?> รายการ</span>
    </div>
    <div class="product-grid" id="product-grid">
        <?php foreach ($products as $index => $product): ?>
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
            <div><dt>หมวดหมู่</dt><dd data-detail="category"></dd></div>
            <div><dt>ประเภท</dt><dd data-detail="type"></dd></div>
            <div><dt>หน่วยนับ</dt><dd data-detail="unit"></dd></div>
            <div><dt>ตำแหน่งจัดเก็บ</dt><dd data-detail="location"></dd></div>
            <div><dt>จุดสั่งซื้อขั้นต่ำ</dt><dd data-detail="minimum"></dd></div>
            <div><dt>รับเข้ารวม</dt><dd data-detail="received"></dd></div>
            <div><dt>เบิกออกรวม</dt><dd data-detail="issued"></dd></div>
            <div><dt>คงเหลือ</dt><dd data-detail="stock"></dd></div>
            <div><dt>สถานะสต๊อก</dt><dd data-detail="status"></dd></div>
        </dl>
        <div class="product-detail-actions">
            <form class="product-delete-form" method="post" data-product-delete-form>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="delete_product">
                <input type="hidden" name="product_code" value="">
                <button class="danger-button" type="submit">ลบสินค้า</button>
            </form>
            <button class="secondary-button" type="button" data-close-product-dialog>ปิด</button>
            <button class="primary-button" type="button" id="product-detail-edit">แก้ไขข้อมูล</button>
        </div>
    </dialog>
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
            <label>รหัสสินค้า <input name="code" maxlength="80" required readonly value="<?= h($editProduct['code'] ?? '') ?>"></label>
            <label class="form-span-2">ชื่อสินค้า <input name="name" maxlength="250" required value="<?= h((string) ($_POST['name'] ?? $editProduct['name'] ?? '')) ?>"></label>
            <label>หมวดหมู่ <select name="category" required>
                <option value="">เลือกหมวดหมู่</option>
                <?php foreach ($categoryOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['category'] ?? $editProduct['category'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>ประเภท <select name="item_type" required>
                <option value="">เลือกประเภท</option>
                <?php foreach ($typeOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['item_type'] ?? $editProduct['item_type'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>หน่วยนับ <select name="unit" required>
                <option value="">เลือกหน่วยนับ</option>
                <?php foreach ($unitOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['unit'] ?? $editProduct['unit'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>ตำแหน่งจัดเก็บ <input name="location" maxlength="80" value="<?= h((string) ($_POST['location'] ?? $editProduct['location'] ?? '')) ?>"></label>
            <label>จุดสั่งซื้อขั้นต่ำ <input name="reorder_point" type="number" step="0.001" min="0" required value="<?= h((string) ($_POST['reorder_point'] ?? $editProduct['reorder_point'] ?? '')) ?>"></label>
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
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
