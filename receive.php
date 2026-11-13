<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (auth_is_viewer()) {
    inventory_flash_redirect('คุณมีสิทธิ์ Viewer (ดูข้อมูลได้อย่างเดียว) ไม่สามารถเข้าหน้าบันทึกรับสินค้าได้', 'catalog.php', 'error');
}

$pageTitle = 'รับสินค้าเข้าคลัง | Store Automeation';
$pageDescription = 'เพิ่มสินค้าใหม่พร้อมรับเข้าครั้งแรก หรือบันทึกรับสินค้าเดิมเข้าคลัง';
$activePage = 'receive';
$categoryOptions = inventory_category_options();
$typeOptions = inventory_type_options();
$unitOptions = inventory_unit_options();
$uploadedProductImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_product_receipt') {
    try {
        inventory_require_csrf();
        if (!auth_is_admin()) {
            throw new DomainException('เฉพาะ Admin (ผู้ดูแลระบบ) เท่านั้นที่สามารถเพิ่มสินค้าใหม่เข้าระบบได้ (Staff สามารถบันทึกรับสินค้าเดิมได้)');
        }
        $code = inventory_text('code', 'รหัสสินค้า', 80, true);
        $newProduct = [
            'code' => $code,
            'name' => inventory_text('name', 'ชื่อสินค้า', 250, true),
            'category' => inventory_choice('category', 'หมวดหมู่', inventory_category_options()),
            'item_type' => inventory_choice('item_type', 'ประเภท', inventory_type_options()),
            'unit' => inventory_choice('unit', 'หน่วยนับ', inventory_unit_options()),
            'location' => inventory_text('location', 'ตำแหน่งจัดเก็บ', 80),
            'reorder_point' => inventory_number('reorder_point', 'จุดสั่งซื้อขั้นต่ำ'),
            'art' => 'coupler',
        ];
        $firstReceipt = [
            'movement_date' => inventory_date(),
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'product_code' => $code,
            'quantity' => inventory_number('quantity', 'จำนวนรับ', false),
            'supplier' => '',
            'received_by' => inventory_text('received_by', 'ผู้รับสินค้า', 150),
            'department' => '',
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ];
        $uploadedProductImage = inventory_store_product_image($_FILES['product_image'] ?? null);
        $newProduct['image_filename'] = $uploadedProductImage;
        inventory_create_product_with_receipt($newProduct, $firstReceipt);
        inventory_flash_redirect('เพิ่มสินค้าใหม่และบันทึกรับเข้าคลังแล้ว', 'receive.php');
    } catch (DomainException | RuntimeException $exception) {
        inventory_delete_product_image($uploadedProductImage);
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_receipt') {
    try {
        inventory_require_csrf();
        if (!auth_can_manage_stock()) {
            throw new DomainException('เฉพาะ Admin และ Staff เท่านั้นที่สามารถบันทึกรับสินค้าเข้าคลังได้');
        }
        $productCode = inventory_text('product_code', 'รหัสสินค้า', 80, true);
        inventory_record_movement('receipt', [
            'movement_date' => inventory_date(),
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'product_code' => $productCode,
            'quantity' => inventory_number('quantity', 'จำนวนรับ', false),
            'supplier' => '',
            'received_by' => inventory_text('received_by', 'ผู้รับสินค้า', 150),
            'department' => '',
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ]);
        inventory_flash_redirect('บันทึกรับสินค้าเข้าคลังแล้ว', 'receive.php');
    } catch (DomainException | PDOException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

$pageProducts = inventory_products();
$history = inventory_movements('receipt');
$openNewProduct = (isset($_GET['new']) && $_GET['new'] === '1')
    || ($_POST['action'] ?? '') === 'create_product_receipt';
$selectedProductCode = ($_POST['action'] ?? '') === 'record_receipt' ? (string) ($_POST['product_code'] ?? '') : '';
$selectedProductName = '';
$selectedProductUnit = '';
foreach ($pageProducts as $p) {
    if ($p['id'] === $selectedProductCode) {
        $selectedProductName = $p['name'];
        $selectedProductUnit = $p['unit'];
        break;
    }
}
$settings = inventory_get_settings();
require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">STOCK IN</div>
            <h1>รับสินค้า<span class="heading-period">.</span></h1>
            <p>รับสินค้าเดิมเข้าคลัง หรือเพิ่มทะเบียนสินค้าใหม่พร้อมบันทึกรับเข้าครั้งแรก</p>
        </div>
        <div class="page-kpis">
            <span style="font-size:12px;color:#475569;display:inline-flex;align-items:center;gap:6px;">
                Telegram: <strong><?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'เปิด' : 'ปิด' ?></strong> |
                Sheets: <strong><?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'เปิด' : 'ปิด' ?></strong>
                <?php if (auth_is_admin()): ?>
                    <a href="settings.php" class="secondary-button" style="padding:2px 8px;font-size:11px;">ตั้งค่า</a>
                <?php endif; ?>
            </span>
            <span><strong><?= count($history) ?></strong> รายการรับ</span>
            <a class="secondary-button" href="export.php?type=receipts" style="font-size:11px;">Export
                ประวัติรับสินค้า</a>
            <a class="secondary-button" href="catalog.php">ดูทะเบียนสินค้า ↗</a>
        </div>
    </div>
    <section class="inventory-panel">
        <div class="panel-heading">
            <div>
                <h2>รับสินค้าเดิม</h2>
                <p>เลือกสินค้าจากชื่อสินค้า (พิมพ์ค้นหาได้) แล้วกรอกวันที่และจำนวนรับ</p>
            </div>
            <?php if (auth_is_admin()): ?>
                <a class="secondary-button" href="#new-product">เพิ่มสินค้าใหม่ ↘</a>
            <?php endif; ?>
        </div>
        <form class="inventory-form movement-form" method="post" id="receive-movement-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action"
                value="record_receipt">
            <label>วันที่รับ <input type="date" name="movement_date" required
                    value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>"></label>
            <label>เลขที่เอกสาร <input name="document_no" maxlength="100"
                    value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี"></label>
            <label class="form-span-2">
                ชื่อสินค้า *
                <div class="product-combobox" id="receive-product-combobox" data-product-combobox>
                    <input type="hidden" name="product_code" id="receive-product-code" data-combobox-code
                        value="<?= h($selectedProductCode) ?>">
                    <div class="product-combobox-input-wrap">
                        <input type="text" id="receive-product-search" data-combobox-search
                            placeholder="พิมพ์ชื่อสินค้า หรือรหัสสินค้าเพื่อค้นหา..." autocomplete="off" role="combobox"
                            aria-autocomplete="list" aria-expanded="false" aria-controls="receive-product-list" required
                            value="<?= h($selectedProductName) ?>">
                        <button type="button" class="product-combobox-toggle" id="receive-product-toggle"
                            data-combobox-toggle aria-label="เปิดรายการสินค้า" tabindex="-1">▾</button>
                    </div>
                    <div class="product-combobox-list" id="receive-product-list" data-combobox-list role="listbox"
                        hidden>
                        <?php foreach ($pageProducts as $product): ?>
                            <button type="button" class="product-combobox-option" role="option"
                                data-code="<?= h($product['id']) ?>" data-name="<?= h($product['name']) ?>"
                                data-unit="<?= h($product['unit']) ?>"
                                data-stock="<?= h(inventory_quantity($product['stock'])) ?>"
                                data-search="<?= h(strtolower($product['name'] . ' ' . $product['id'])) ?>">
                                <strong><?= h($product['name']) ?></strong>
                                <small>รหัส <?= h($product['id']) ?> • คงเหลือ <?= inventory_quantity($product['stock']) ?>
                                    <?= h($product['unit']) ?></small>
                            </button>
                        <?php endforeach; ?>
                        <div class="product-combobox-empty" id="receive-product-empty" data-combobox-empty hidden>
                            ไม่พบสินค้าที่ตรงกับคำค้นหา</div>
                    </div>
                </div>
            </label>
            <label>รหัสสินค้า <input id="receive-product-code-display" data-combobox-code-display readonly
                    placeholder="เลือกสินค้าเพื่อแสดงรหัส" value="<?= h($selectedProductCode) ?>"></label>
            <label>จำนวนรับ <input type="number" name="quantity" min="0" step="1" required
                    value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
            <label>หน่วยนับ <input id="movement-product-unit" data-combobox-unit-display readonly placeholder="—"
                    value="<?= h($selectedProductUnit) ?>"></label>
            <label>ผู้รับสินค้า <input name="received_by" maxlength="150"
                    value="<?= h((string) ($_POST['received_by'] ?? '')) ?>"></label>
            <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500"
                    value="<?= h((string) ($_POST['notes'] ?? '')) ?>"></label>
            <div class="form-actions form-span-2"><button class="primary-button" type="submit">บันทึกรับสินค้า</button>
            </div>
        </form>
    </section>
    <?php if (auth_is_admin()): ?>
        <details class="inventory-panel new-product-details" id="new-product" <?= $openNewProduct ? 'open' : '' ?>>
            <summary>เพิ่มสินค้าใหม่พร้อมรับเข้าครั้งแรก</summary>
            <p class="new-product-description">กรอกรายละเอียดสินค้าและจำนวนที่รับเข้าครั้งแรก
                ระบบจะสร้างทะเบียนและบันทึกรับสินค้าไว้พร้อมกัน</p>
            <form class="inventory-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action"
                    value="create_product_receipt">
                <label>รหัสสินค้า <input name="code" maxlength="80" required
                        value="<?= h((string) ($_POST['code'] ?? '')) ?>"></label>
                <label class="form-span-2">ชื่อสินค้า <input name="name" maxlength="250" required
                        value="<?= h((string) ($_POST['name'] ?? '')) ?>"></label>
                <label>หมวดหมู่ <select name="category" required>
                        <option value="">เลือกหมวดหมู่</option>
                        <?php foreach ($categoryOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= ($_POST['category'] ?? '') === $option ? 'selected' : '' ?>>
                                <?= h($option) ?></option><?php endforeach; ?>
                    </select></label>
                <label>ประเภท <select name="item_type" required>
                        <option value="">เลือกประเภท</option>
                        <?php foreach ($typeOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= ($_POST['item_type'] ?? '') === $option ? 'selected' : '' ?>>
                                <?= h($option) ?></option><?php endforeach; ?>
                    </select></label>
                <label>หน่วยนับ <select name="unit" required>
                        <option value="">เลือกหน่วยนับ</option>
                        <?php foreach ($unitOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= ($_POST['unit'] ?? '') === $option ? 'selected' : '' ?>>
                                <?= h($option) ?></option><?php endforeach; ?>
                    </select></label>
                <label>ตำแหน่งจัดเก็บ <input name="location" maxlength="80"
                        value="<?= h((string) ($_POST['location'] ?? '')) ?>" placeholder="เช่น A1"></label>
                <label>จุดสั่งซื้อขั้นต่ำ <input name="reorder_point" type="number" step="1" min="0" required
                        value="<?= h((string) ($_POST['reorder_point'] ?? '0')) ?>"></label>
                <label class="product-image-upload-field form-span-2">รูปสินค้า
                    <input class="product-image-upload" type="file" name="product_image"
                        accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB (หากไม่ใส่จะใช้รูปตัวอย่างตามหมวดหมู่โดยอัตโนมัติ)</small>
                    <span class="product-image-preview"><img alt="ตัวอย่างรูปสินค้า"
                            hidden><span>ยังไม่ได้เลือกรูปสินค้า</span></span>
                </label>
                <label>วันที่รับ <input type="date" name="movement_date" required
                        value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>"></label>
                <label>เลขที่เอกสาร <input name="document_no" maxlength="100"
                        value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี"></label>
                <label>จำนวนรับครั้งแรก <input type="number" name="quantity" min="0" step="1" required
                        value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
                <label>ผู้รับสินค้า <input name="received_by" maxlength="150"
                        value="<?= h((string) ($_POST['received_by'] ?? '')) ?>"></label>
                <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500"
                        value="<?= h((string) ($_POST['notes'] ?? '')) ?>"></label>
                <div class="form-actions form-span-2"><button class="primary-button"
                        type="submit">เพิ่มสินค้าและบันทึกรับ</button></div>
            </form>
        </details>
    <?php endif; ?>
    <section class="inventory-panel table-panel">
        <div class="panel-heading">
            <div>
                <h2>ประวัติรับสินค้า</h2>
                <p>แสดงรายการล่าสุดไม่เกิน 250 รายการ</p>
            </div>
        </div>
        <div class="table-scroll">
            <table class="inventory-table movement-table">
                <thead>
                    <tr>
                        <th>วันที่รับ</th>
                        <th>เลขที่เอกสาร</th>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th class="numeric">จำนวนรับ</th>
                        <th>หน่วยนับ</th>
                        <th>ผู้รับสินค้า</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($history as $row): ?>
                        <tr>
                            <td><?= h($row['movement_date']) ?></td>
                            <td><?= h($row['document_no']) ?></td>
                            <td class="code-cell"><?= h($row['product_code']) ?></td>
                            <td class="name-cell"><?= h($row['product_name']) ?></td>
                            <td class="numeric"><?= inventory_quantity($row['quantity']) ?></td>
                            <td><?= h($row['unit']) ?></td>
                            <td><?= h($row['received_by']) ?></td>
                            <td><?= h($row['notes']) ?></td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($history === []): ?>
            <p class="table-empty">ยังไม่มีประวัติรับสินค้า</p><?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>