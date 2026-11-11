<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

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
        if (!auth_can_manage_stock()) {
            throw new DomainException('เฉพาะ Admin และ Staff เท่านั้นที่สามารถเพิ่มสินค้าและรับเข้าคลังได้');
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
            'supplier' => inventory_text('supplier', 'ผู้ส่งมอบ', 150),
            'received_by' => inventory_text('received_by', 'ผู้รับสินค้า', 150),
            'department' => inventory_text('department', 'แผนก/หน่วยงาน', 150),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ];
        $uploadedProductImage = inventory_store_product_image($_FILES['product_image'] ?? null);
        $newProduct['image_filename'] = $uploadedProductImage;
        inventory_create_product_with_receipt($newProduct, $firstReceipt);
        inventory_flash_redirect('เพิ่มสินค้าใหม่และบันทึกรับเข้าคลังแล้ว', 'receive.php');
    } catch (DomainException|RuntimeException $exception) {
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
            'supplier' => inventory_text('supplier', 'ผู้ส่งมอบ', 150),
            'received_by' => inventory_text('received_by', 'ผู้รับสินค้า', 150),
            'department' => inventory_text('department', 'แผนก/หน่วยงาน', 150),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ]);
        inventory_flash_redirect('บันทึกรับสินค้าเข้าคลังแล้ว', 'receive.php');
    } catch (DomainException|PDOException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

$pageProducts = inventory_products();
$history = inventory_movements('receipt');
$openNewProduct = (isset($_GET['new']) && $_GET['new'] === '1')
    || ($_POST['action'] ?? '') === 'create_product_receipt';
require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div><div class="eyebrow section-eyebrow">STOCK IN</div><h1>รับสินค้า<span class="heading-period">.</span></h1><p>รับสินค้าเดิมเข้าคลัง หรือเพิ่มทะเบียนสินค้าใหม่พร้อมบันทึกรับเข้าครั้งแรก</p></div>
        <div class="page-kpis">
            <span><strong><?= count($history) ?></strong> รายการรับ</span>
            <a class="secondary-button" href="export.php?type=receipts" style="font-size:11px;">📊 Export ประวัติรับสินค้า</a>
            <a class="secondary-button" href="catalog.php">ดูทะเบียนสินค้า ↗</a>
        </div>
    </div>
    <section class="inventory-panel">
        <div class="panel-heading"><div><h2>รับสินค้าเดิม</h2><p>เลือกรหัสสินค้าที่อยู่ในทะเบียน แล้วกรอกวันที่และจำนวนรับ</p></div><a class="secondary-button" href="#new-product">เพิ่มสินค้าใหม่ ↘</a></div>
        <form class="inventory-form movement-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="record_receipt">
            <label>วันที่รับ <input type="date" name="movement_date" required value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>"></label>
            <label>เลขที่เอกสาร <input name="document_no" maxlength="100" value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี"></label>
            <label>รหัสสินค้า <select name="product_code" id="movement-product" required><option value="">เลือกสินค้า</option><?php foreach ($pageProducts as $product): ?><option value="<?= h($product['id']) ?>" data-name="<?= h($product['name']) ?>" data-unit="<?= h($product['unit']) ?>" <?= ($_POST['product_code'] ?? '') === $product['id'] ? 'selected' : '' ?>><?= h($product['id'] . ' — ' . $product['name']) ?></option><?php endforeach; ?></select></label>
            <label class="form-span-2">ชื่อสินค้า <input id="movement-product-name" readonly placeholder="เลือกสินค้าเพื่อแสดงชื่อ"></label>
            <label>จำนวนรับ <input type="number" name="quantity" min="0.001" step="0.001" required value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
            <label>หน่วยนับ <input id="movement-product-unit" readonly placeholder="—"></label>
            <label>ผู้ส่งมอบ <input name="supplier" maxlength="150" value="<?= h((string) ($_POST['supplier'] ?? '')) ?>"></label>
            <label>ผู้รับสินค้า <input name="received_by" maxlength="150" value="<?= h((string) ($_POST['received_by'] ?? '')) ?>"></label>
            <label>แผนก/หน่วยงาน <input name="department" maxlength="150" value="<?= h((string) ($_POST['department'] ?? '')) ?>"></label>
            <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500" value="<?= h((string) ($_POST['notes'] ?? '')) ?>"></label>
            <div class="form-actions form-span-2"><button class="primary-button" type="submit">บันทึกรับสินค้า</button></div>
        </form>
    </section>
    <details class="inventory-panel new-product-details" id="new-product" <?= $openNewProduct ? 'open' : '' ?>>
        <summary>เพิ่มสินค้าใหม่พร้อมรับเข้าครั้งแรก</summary>
        <p class="new-product-description">กรอกรายละเอียดสินค้าและจำนวนที่รับเข้าครั้งแรก ระบบจะสร้างทะเบียนและบันทึกรับสินค้าไว้พร้อมกัน</p>
        <form class="inventory-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="create_product_receipt">
            <label>รหัสสินค้า <input name="code" maxlength="80" required value="<?= h((string) ($_POST['code'] ?? '')) ?>"></label>
            <label class="form-span-2">ชื่อสินค้า <input name="name" maxlength="250" required value="<?= h((string) ($_POST['name'] ?? '')) ?>"></label>
            <label>หมวดหมู่ <select name="category" required>
                <option value="">เลือกหมวดหมู่</option>
                <?php foreach ($categoryOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['category'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>ประเภท <select name="item_type" required>
                <option value="">เลือกประเภท</option>
                <?php foreach ($typeOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['item_type'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>หน่วยนับ <select name="unit" required>
                <option value="">เลือกหน่วยนับ</option>
                <?php foreach ($unitOptions as $option): ?><option value="<?= h($option) ?>" <?= ($_POST['unit'] ?? '') === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?>
            </select></label>
            <label>ตำแหน่งจัดเก็บ <input name="location" maxlength="80" value="<?= h((string) ($_POST['location'] ?? '')) ?>" placeholder="เช่น A1"></label>
            <label>จุดสั่งซื้อขั้นต่ำ <input name="reorder_point" type="number" step="0.001" min="0" required value="<?= h((string) ($_POST['reorder_point'] ?? '0')) ?>"></label>
            <label class="product-image-upload-field form-span-2">รูปสินค้า
                <input class="product-image-upload" type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB</small>
                <span class="product-image-preview"><img alt="ตัวอย่างรูปสินค้า" hidden><span>ยังไม่ได้เลือกรูปสินค้า</span></span>
            </label>
            <label>วันที่รับ <input type="date" name="movement_date" required value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>"></label>
            <label>เลขที่เอกสาร <input name="document_no" maxlength="100" value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี"></label>
            <label>จำนวนรับครั้งแรก <input type="number" name="quantity" min="0.001" step="0.001" required value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
            <label>ผู้ส่งมอบ <input name="supplier" maxlength="150" value="<?= h((string) ($_POST['supplier'] ?? '')) ?>"></label>
            <label>ผู้รับสินค้า <input name="received_by" maxlength="150" value="<?= h((string) ($_POST['received_by'] ?? '')) ?>"></label>
            <label>แผนก/หน่วยงาน <input name="department" maxlength="150" value="<?= h((string) ($_POST['department'] ?? '')) ?>"></label>
            <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500" value="<?= h((string) ($_POST['notes'] ?? '')) ?>"></label>
            <div class="form-actions form-span-2"><button class="primary-button" type="submit">เพิ่มสินค้าและบันทึกรับ</button></div>
        </form>
    </details>
    <section class="inventory-panel table-panel">
        <div class="panel-heading"><div><h2>ประวัติรับสินค้า</h2><p>แสดงรายการล่าสุดไม่เกิน 250 รายการ</p></div></div>
        <div class="table-scroll"><table class="inventory-table movement-table">
            <thead><tr><th>วันที่รับ</th><th>เลขที่เอกสาร</th><th>รหัสสินค้า</th><th>ชื่อสินค้า</th><th class="numeric">จำนวนรับ</th><th>หน่วยนับ</th><th>ผู้ส่งมอบ</th><th>ผู้รับสินค้า</th><th>แผนก/หน่วยงาน</th><th>หมายเหตุ</th></tr></thead>
            <tbody><?php foreach ($history as $row): ?><tr>
                <td><?= h($row['movement_date']) ?></td><td><?= h($row['document_no']) ?></td><td class="code-cell"><?= h($row['product_code']) ?></td><td class="name-cell"><?= h($row['product_name']) ?></td>
                <td class="numeric"><?= inventory_quantity($row['quantity']) ?></td><td><?= h($row['unit']) ?></td><td><?= h($row['supplier']) ?></td><td><?= h($row['received_by']) ?></td><td><?= h($row['department']) ?></td><td><?= h($row['notes']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($history === []): ?><p class="table-empty">ยังไม่มีประวัติรับสินค้า</p><?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
