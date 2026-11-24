<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (auth_is_viewer()) {
    inventory_flash_redirect('คุณมีสิทธิ์ Viewer (ดูข้อมูลได้อย่างเดียว) ไม่สามารถเข้าหน้าบันทึกรับสินค้าได้', 'catalog.php', 'error');
}

$pageTitle = 'รับสินค้าเข้าคลัง | Store Automeation';
$pageDescription = 'เพิ่มสินค้าใหม่พร้อมรับเข้าครั้งแรก หรือบันทึกรับสินค้าเดิมเข้าคลัง';
$activePage = 'receive';
$glDescriptionOptions = inventory_gl_description_options();
$apexOptions = inventory_apex_options();
$unitOptions = inventory_unit_options();
$uploadedProductImage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_product_receipt') {
    try {
        inventory_require_csrf();
        if (!auth_is_admin()) {
            throw new DomainException('เฉพาะ Admin (ผู้ดูแลระบบ) เท่านั้นที่สามารถเพิ่มสินค้าใหม่เข้าระบบได้ (Staff สามารถบันทึกรับสินค้าเดิมได้)');
        }
        $accountCode1 = inventory_text('account_code1', 'Account Code1', 100);
        if ($accountCode1 === '') {
            $accountCode1 = '-';
        }
        $rawStockCode = inventory_text('stock_code', 'Stock Code', 80);
        $isPlaceholderCode = ($rawStockCode === '' || $rawStockCode === '-' || $rawStockCode === '—');
        $stockCode = $isPlaceholderCode ? '-' : $rawStockCode;
        if (!$isPlaceholderCode && inventory_product_exists_pair($stockCode, $accountCode1)) {
            throw new DomainException('สินค้าที่มี Stock Code และ Account Code1 นี้มีในรายการแล้ว กรุณาตรวจสอบหรือเลือกรับสินค้าเดิม');
        }
        $code = ($isPlaceholderCode || inventory_product($stockCode) !== null)
            ? inventory_generate_internal_code()
            : $stockCode;
        $notes = inventory_text('notes', 'หมายเหตุ', 500);
        $glDescription = inventory_gl_description_input('gl_description');
        $glList = inventory_normalize_gl_list($glDescription);
        $primaryCategory = $glList[0] ?? 'General';
        $reorderRaw = trim((string) ($_POST['reorder_point'] ?? ''));
        $reorderPoint = $reorderRaw === '' ? 0.0 : inventory_number('reorder_point', 'จุดสั่งซื้อขั้นต่ำ');
        $newProduct = [
            'code' => $code,
            'stock_code' => $stockCode,
            'account_code1' => $accountCode1,
            'gl_description' => $glDescription,
            'section_code' => inventory_text('section_code', 'Section code', 100),
            'section_name' => inventory_text('section_name', 'Section Name', 255),
            'apex' => inventory_apex_input('apex'),
            'name' => inventory_text('name', 'Name', 250, true),
            'category' => $primaryCategory,
            'item_type' => $primaryCategory,
            'unit' => inventory_choice('unit', 'Unit', inventory_unit_options()),
            'reorder_point' => $reorderPoint,
            'buy_date' => inventory_optional_datetime('buy_date'),
            'buy_price' => inventory_optional_number('buy_price', 'BuyPrice/Unit', 2),
            'cal_no' => inventory_text('cal_no', 'Cal No.', 100),
            'cal_date' => inventory_optional_date('cal_date', 'Cal date'),
            'cal_exp' => inventory_optional_date('cal_exp', 'Cal Exp.'),
            'notes' => $notes,
        ];
        $firstReceipt = [
            'movement_date' => date('Y-m-d'),
            'movement_time' => date('H:i'),
            'document_no' => '',
            'product_code' => $code,
            'account_code1' => $accountCode1,
            'quantity' => inventory_number('quantity', 'Quantity', false),
            'supplier' => '',
            'received_by' => (string) ($currentUser['name'] ?? ''),
            'department' => '',
            'notes' => $notes,
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
        $baseProduct = inventory_product($productCode);
        if ($baseProduct === null) {
            throw new DomainException('ไม่พบรหัสสินค้าที่เลือก กรุณาเลือกสินค้าใหม่');
        }
        $dt = inventory_receipt_datetime('movement_datetime', 'movement_date', 'movement_time');
        $movementDate = $dt['date'];
        $movementTime = $dt['time'];
        $accountCode1 = inventory_text('account_code1', 'Account Code1', 100);
        if ($accountCode1 === '' || $accountCode1 === '—') {
            $accountCode1 = '-';
        }
        $receiptData = [
            'movement_date' => $movementDate,
            'movement_time' => $movementTime,
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'product_code' => $productCode,
            'account_code1' => $accountCode1,
            'quantity' => inventory_number('quantity', 'จำนวนรับ', false),
            'supplier' => '',
            'received_by' => inventory_text('received_by', 'ผู้รับสินค้า', 150),
            'department' => '',
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ];

        $baseStockCode = trim((string) ($baseProduct['stock_code'] ?? ''));
        $hasRealStockCode = ($baseStockCode !== '' && $baseStockCode !== '-' && $baseStockCode !== '—');
        if ($accountCode1 !== '-' && $hasRealStockCode) {
            $matchedVariant = inventory_find_product_by_pair($baseStockCode, $accountCode1);
            if ($matchedVariant !== null) {
                $receiptData['product_code'] = (string) $matchedVariant['code'];
                inventory_record_movement('receipt', $receiptData);
            } else {
                $newVariantCode = inventory_generate_internal_code();
                $newVariant = [
                    'code' => $newVariantCode,
                    'stock_code' => $baseStockCode,
                    'account_code1' => $accountCode1,
                    'gl_description' => (string) ($baseProduct['gl_description'] ?? ''),
                    'section_code' => (string) ($baseProduct['section_code'] ?? ''),
                    'section_name' => (string) ($baseProduct['section_name'] ?? ''),
                    'apex' => (string) ($baseProduct['apex'] ?? ''),
                    'name' => (string) ($baseProduct['name'] ?? ''),
                    'category' => (string) ($baseProduct['category'] ?? 'General'),
                    'item_type' => (string) ($baseProduct['item_type'] ?? 'General'),
                    'unit' => (string) ($baseProduct['unit'] ?? 'PCS'),
                    'location' => (string) ($baseProduct['location'] ?? ''),
                    'reorder_point' => (float) ($baseProduct['reorder_point'] ?? 0),
                    'buy_date' => $movementDate . ($movementTime !== '' ? ' ' . $movementTime : ''),
                    'buy_price' => $baseProduct['buy_price'] ?? null,
                    'cal_no' => '',
                    'cal_date' => '',
                    'cal_exp' => '',
                    'notes' => $receiptData['notes'],
                    'art' => (string) ($baseProduct['art'] ?? 'coupler'),
                    'image_filename' => $baseProduct['image_filename'] ?? null,
                ];
                $receiptData['product_code'] = $newVariantCode;
                inventory_create_product_with_receipt($newVariant, $receiptData);
            }
        } else {
            inventory_record_movement('receipt', $receiptData);
        }
        inventory_flash_redirect('บันทึกรับสินค้าเข้าคลังแล้ว', 'receive.php');
    } catch (DomainException | PDOException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

$pageProducts = inventory_products();
$groupedProducts = inventory_group_products_by_stock_code($pageProducts);
$history = inventory_movements('receipt');
$isRecordPost = ($_POST['action'] ?? '') === 'record_receipt';
$isCreatePost = ($_POST['action'] ?? '') === 'create_product_receipt';
$openNewProduct = (isset($_GET['new']) && $_GET['new'] === '1') || $isCreatePost;
$selectedProductCode = $isRecordPost ? (string) ($_POST['product_code'] ?? '') : '';
$selectedProductName = '';
$selectedProductUnit = '';
$selectedStockCodeDisplay = '';
foreach ($groupedProducts as $p) {
    $matchedOption = ($p['id'] === $selectedProductCode);
    if (!$matchedOption) {
        foreach ($p['variants'] as $v) {
            if ($v['id'] === $selectedProductCode) {
                $matchedOption = true;
                break;
            }
        }
    }
    if ($matchedOption) {
        $selectedProductCode = (string) $p['id'];
        $selectedProductName = (string) $p['name'];
        $selectedProductUnit = (string) $p['unit'];
        $selectedStockCodeDisplay = (string) ($p['stock_code'] ?? $p['id']);
        break;
    }
}
$defaultMovementDatetime = $isRecordPost
    ? (string) ($_POST['movement_datetime'] ?? (($_POST['movement_date'] ?? date('Y-m-d')) . 'T' . ($_POST['movement_time'] ?? date('H:i'))))
    : date('Y-m-d\TH:i');
$settings = inventory_get_settings();
require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">STOCK IN</div>
            <h1>รับสินค้า<span class="heading-period">.</span></h1>
            <p>รับสินค้าเดิมเข้าคลัง หรือเพิ่มรายการสินค้าใหม่พร้อมบันทึกรับเข้าครั้งแรก</p>
        </div>
        <div class="page-kpis">
            <span style="font-size:12px;color:#475569;background:#f8fafc;border:1px solid #e2e8f0;padding:5px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:6px;">
                Telegram: <strong><?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'เปิด' : 'ปิด' ?></strong> |
                Sheets: <strong><?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'เปิด' : 'ปิด' ?></strong>
                <?php if (auth_is_admin()): ?>
                    <a href="settings.php" class="secondary-button" style="padding:2px 8px;font-size:11px;min-height:24px;">ตั้งค่า</a>
                <?php endif; ?>
            </span>
            <span style="font-size:12px;color:#074080;background:#f0f5fc;border:1px solid #d0e1f7;padding:5px 10px;border-radius:8px;display:inline-flex;align-items:center;gap:4px;">
                <strong><?= count($history) ?></strong> รายการรับ
            </span>
            <a class="secondary-button" href="export.php?type=receipts" style="font-size:11px;">Export ประวัติรับสินค้า</a>
            <a class="secondary-button" href="catalog.php">ดูรายการสินค้า ↗</a>
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
            <label>วันที่และเวลารับ <input type="datetime-local" name="movement_datetime" required
                    value="<?= h($defaultMovementDatetime) ?>"></label>
            <label>เลขที่เอกสาร <input name="document_no" maxlength="100"
                    value="<?= h($isRecordPost ? (string) ($_POST['document_no'] ?? '') : '') ?>" placeholder="ถ้ามี"></label>
            <label class="form-span-2">
                ชื่อสินค้า *
                <div class="product-combobox" id="receive-product-combobox" data-product-combobox>
                    <input type="hidden" name="product_code" id="receive-product-code" data-combobox-code
                        value="<?= h($selectedProductCode) ?>">
                    <div class="product-combobox-input-wrap">
                        <input type="text" id="receive-product-search" data-combobox-search
                            placeholder="พิมพ์ชื่อสินค้า, Stock Code หรือ Account Code1 เพื่อค้นหา..." autocomplete="off" role="combobox"
                            aria-autocomplete="list" aria-expanded="false" aria-controls="receive-product-list" required
                            value="<?= h($selectedProductName) ?>">
                        <button type="button" class="product-combobox-toggle" id="receive-product-toggle"
                            data-combobox-toggle aria-label="เปิดรายการสินค้า" tabindex="-1">▾</button>
                    </div>
                    <div class="product-combobox-list" id="receive-product-list" data-combobox-list role="listbox"
                        hidden>
                        <?php foreach ($groupedProducts as $product): ?>
                            <?php
                            $rSc = (string) ($product['stock_code'] ?? $product['id']);
                            $variantIds = [];
                            $variantAc1s = [];
                            foreach ($product['variants'] as $variant) {
                                $variantIds[] = (string) $variant['id'];
                                $vAc1 = trim((string) ($variant['account_code1'] ?? ''));
                                if ($vAc1 !== '' && $vAc1 !== '-' && !in_array($vAc1, $variantAc1s, true)) {
                                    $variantAc1s[] = $vAc1;
                                }
                            }
                            $rAc1First = $variantAc1s[0] ?? '';
                            $rSearchHaystack = trim($product['name'] . ' ' . $rSc . ' ' . implode(' ', $variantIds) . ' ' . implode(' ', $variantAc1s));
                            ?>
                            <button type="button" class="product-combobox-option" role="option"
                                data-code="<?= h($product['id']) ?>"
                                data-stock-code="<?= h($rSc) ?>"
                                data-account-code1="<?= h($rAc1First) ?>"
                                data-name="<?= h($product['name']) ?>"
                                data-unit="<?= h($product['unit']) ?>"
                                data-stock="<?= h(inventory_quantity($product['stock'])) ?>"
                                data-search="<?= h(mb_strtolower($rSearchHaystack, 'UTF-8')) ?>">
                                <strong><?= h($product['name']) ?></strong>
                                <small>Stock Code <?= h($rSc) ?> • คงเหลือ <?= inventory_quantity($product['stock']) ?>
                                    <?= h($product['unit']) ?></small>
                            </button>
                        <?php endforeach; ?>
                        <div class="product-combobox-empty" id="receive-product-empty" data-combobox-empty hidden>
                            ไม่พบสินค้าที่ตรงกับคำค้นหา</div>
                    </div>
                </div>
            </label>
            <label>Stock Code <input id="receive-product-code-display" data-combobox-code-display readonly
                    placeholder="เลือกสินค้าเพื่อแสดงรหัส" value="<?= h($selectedStockCodeDisplay) ?>"></label>
            <label>Account Code1 <input name="account_code1" maxlength="100" placeholder="ถ้าไม่มีใส่ -"
                    value="<?= h($isRecordPost ? (string) ($_POST['account_code1'] ?? '') : '') ?>"></label>
            <label>จำนวนรับ <input type="number" name="quantity" min="1" step="1" required
                    value="<?= h($isRecordPost ? (string) ($_POST['quantity'] ?? '') : '') ?>"></label>
            <label>หน่วยนับ <input id="movement-product-unit" data-combobox-unit-display readonly placeholder="—"
                    value="<?= h($selectedProductUnit) ?>"></label>
            <label>ผู้รับสินค้า <input name="received_by" maxlength="150"
                    value="<?= h($isRecordPost ? (string) ($_POST['received_by'] ?? '') : '') ?>"></label>
            <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500"
                    value="<?= h($isRecordPost ? (string) ($_POST['notes'] ?? '') : '') ?>"></label>
            <div class="form-actions"><button class="primary-button" type="submit">บันทึกรับสินค้า</button>
            </div>
        </form>
    </section>
    <?php if (auth_is_admin()): ?>
        <details class="inventory-panel new-product-details" id="new-product" <?= $openNewProduct ? 'open' : '' ?>>
            <summary>เพิ่มสินค้าใหม่พร้อมรับเข้าครั้งแรก</summary>
            <p class="new-product-description">กรอกรายละเอียดสินค้าและจำนวนที่รับเข้าครั้งแรก
                ระบบจะสร้างรายการและบันทึกรับสินค้าไว้พร้อมกัน</p>
            <form class="inventory-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action"
                    value="create_product_receipt">
                <label>Account Code1 <input name="account_code1" maxlength="100"
                        placeholder="ถ้าไม่มีใส่ -"
                        value="<?= h((string) ($_POST['account_code1'] ?? '')) ?>"></label>
                <label>Stock Code <input name="stock_code" maxlength="80"
                        placeholder="ถ้าไม่มีใส่ -"
                        value="<?= h((string) ($_POST['stock_code'] ?? '')) ?>"></label>
                <?php
                $postedGlRaw = $_POST['gl_description'] ?? [];
                $postedGlList = is_array($postedGlRaw) ? $postedGlRaw : inventory_normalize_gl_list((string) $postedGlRaw);
                ?>
                <div class="form-field form-span-2">
                    <span class="form-field-label">GL Description</span>
                    <div class="multi-dropdown" data-multi-dropdown data-placeholder="เลือก GL Description (เลือกได้หลายหมวด)">
                        <button type="button" class="multi-dropdown-toggle" data-multi-toggle aria-expanded="false">
                            <span class="multi-dropdown-label" data-multi-label><?= $postedGlList !== [] ? h(implode(', ', $postedGlList)) : 'เลือก GL Description (เลือกได้หลายหมวด)' ?></span>
                            <span class="multi-dropdown-arrow">▾</span>
                        </button>
                        <div class="multi-dropdown-menu" data-multi-menu hidden>
                            <?php foreach ($glDescriptionOptions as $option): ?>
                                <label class="multi-dropdown-option">
                                    <input type="checkbox" name="gl_description[]" value="<?= h($option) ?>" <?= in_array($option, $postedGlList, true) ? 'checked' : '' ?>>
                                    <span><?= h($option) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <label>Section code <input name="section_code" maxlength="100"
                        value="<?= h((string) ($_POST['section_code'] ?? '')) ?>"></label>
                <label>Section Name <input name="section_name" maxlength="255"
                        value="<?= h((string) ($_POST['section_name'] ?? '')) ?>"></label>
                <label>Apex <select name="apex">
                        <option value="">เลือก Apex</option>
                        <?php foreach ($apexOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= ($_POST['apex'] ?? '') === $option ? 'selected' : '' ?>>
                                <?= h($option) ?></option><?php endforeach; ?>
                    </select></label>
                <label class="form-span-2">Name <input name="name" maxlength="250" required
                        value="<?= h((string) ($_POST['name'] ?? '')) ?>"></label>
                <label>Unit <select name="unit" required>
                        <option value="">เลือกหน่วยนับ</option>
                        <?php foreach ($unitOptions as $option): ?>
                            <option value="<?= h($option) ?>" <?= ($_POST['unit'] ?? '') === $option ? 'selected' : '' ?>>
                                <?= h($option) ?></option><?php endforeach; ?>
                    </select></label>
                <label>Quantity <input type="number" name="quantity" min="1" step="1" required
                        value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
                <label>จุดสั่งซื้อขั้นต่ำ <input type="number" name="reorder_point" min="0" step="1"
                        placeholder="ไม่บังคับ"
                        value="<?= h((string) ($_POST['reorder_point'] ?? '')) ?>"></label>
                <label>Buydate <input type="date" name="buy_date"
                        value="<?= h((string) ($_POST['buy_date'] ?? '')) ?>"></label>
                <label>เวลา Buydate (ไม่บังคับ) <input type="time" name="buy_date_time"
                        value="<?= h((string) ($_POST['buy_date_time'] ?? '')) ?>"></label>
                <label>BuyPrice/Unit <input type="number" name="buy_price" min="0" step="0.01"
                        placeholder="ไม่บังคับ"
                        value="<?= h((string) ($_POST['buy_price'] ?? '')) ?>"></label>
                <label>Cal No. (ไม่บังคับ) <input type="text" name="cal_no" maxlength="100" placeholder="ไม่บังคับ"
                        value="<?= h((string) ($_POST['cal_no'] ?? '')) ?>"></label>
                <label>Cal date (ไม่บังคับ) <input type="date" name="cal_date"
                        value="<?= h((string) ($_POST['cal_date'] ?? '')) ?>"></label>
                <label>Cal Exp. (ไม่บังคับ) <input type="date" name="cal_exp"
                        value="<?= h((string) ($_POST['cal_exp'] ?? '')) ?>"></label>
                <label class="product-image-upload-field form-span-2">รูปสินค้า
                    <input class="product-image-upload" type="file" name="product_image"
                        accept="image/jpeg,image/png,image/webp">
                    <small>JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB (หากไม่ใส่จะใช้รูปตัวอย่างตามหมวดหมู่โดยอัตโนมัติ)</small>
                    <span class="product-image-preview"><img alt="ตัวอย่างรูปสินค้า"
                            hidden><span>ยังไม่ได้เลือกรูปสินค้า</span></span>
                </label>
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
                        <th>วันที่–เวลารับ</th>
                        <th>เลขที่เอกสาร</th>
                        <th>Stock Code</th>
                        <th>Account Code1</th>
                        <th>ชื่อสินค้า</th>
                        <th class="numeric">จำนวนรับ</th>
                        <th>หน่วยนับ</th>
                        <th>ผู้รับสินค้า</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($history as $row): ?>
                        <?php
                        $rHistSc = inventory_clean_stock_code((string) ($row['stock_code'] ?? $row['product_code'] ?? ''));
                        $rHistAc1 = trim((string) ($row['account_code1'] ?? ''));
                        $rHistTime = trim((string) ($row['movement_time'] ?? ''));
                        $rHistDateTime = $row['movement_date'] . ($rHistTime !== '' ? ' ' . $rHistTime : '');
                        ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= h($rHistDateTime) ?></td>
                            <td><?= h($row['document_no']) ?></td>
                            <td class="code-cell"><?= h($rHistSc) ?></td>
                            <td class="code-cell"><?= h($rHistAc1 !== '' ? $rHistAc1 : '-') ?></td>
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