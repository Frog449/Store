<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (auth_is_viewer()) {
    inventory_flash_redirect('คุณมีสิทธิ์ Viewer (ดูข้อมูลได้อย่างเดียว) ไม่สามารถเข้าหน้าบันทึกเบิกสินค้าได้', 'catalog.php', 'error');
}

$pageTitle = 'เบิกสินค้าออกจากคลัง | Store Automeation';
$pageDescription = 'บันทึกเบิกสินค้าออกจากคลังและดูประวัติรายการเบิก';
$activePage = 'issue';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_issue') {
    try {
        inventory_require_csrf();
        if (!auth_can_manage_stock()) {
            throw new DomainException('เฉพาะ Admin และ Staff เท่านั้นที่สามารถบันทึกเบิกสินค้าได้');
        }
        $productCode = inventory_text('product_code', 'รหัสสินค้า', 80, true);
        $user = auth_user();
        $requestedBy = inventory_text('requested_by', 'ผู้เบิก', 150);
        if ($requestedBy === '' && $user) {
            $requestedBy = (string) $user['name'];
        }
        inventory_record_movement('issue', [
            'movement_date' => inventory_date(),
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'product_code' => $productCode,
            'quantity' => inventory_number('quantity', 'จำนวนเบิก', false),
            'requested_by' => $requestedBy,
            'department' => '',
            'approved_by' => inventory_text('approved_by', 'ผู้อนุมัติ', 150, true),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ]);
        inventory_flash_redirect('บันทึกเบิกสินค้าออกจากคลังแล้ว', 'issue.php');
    } catch (DomainException | PDOException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

$pageProducts = inventory_products();
$history = inventory_movements('issue');
$selectedProductCode = (string) ($_POST['product_code'] ?? '');
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
            <div class="eyebrow section-eyebrow">STOCK OUT</div>
            <h1>บันทึกเบิกสินค้า<span class="heading-period">.</span></h1>
            <p>บันทึกการเบิกหรือจ่ายสินค้าออก พร้อมระบุผู้เบิกและผู้อนุมัติ</p>
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
                <strong><?= count($history) ?></strong> รายการเบิก
            </span>
            <a class="secondary-button" href="export.php?type=issues" style="font-size:11px;">Export ประวัติเบิกสินค้า</a>
            <a class="secondary-button" href="catalog.php">ดูรายการสินค้า ↗</a>
        </div>
    </div>
    <section class="inventory-panel">
        <div class="panel-heading">
            <div>
                <h2>เพิ่มรายการเบิกสินค้า</h2>
                <p>เลือกสินค้าจากชื่อสินค้า (พิมพ์ค้นหาได้) แล้วกรอกวันที่ จำนวนเบิก และข้อมูลผู้เบิก</p>
            </div>
        </div>
        <form class="inventory-form movement-form" method="post" id="issue-movement-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action"
                value="record_issue">
            <label>วันที่เบิก <input type="date" name="movement_date" required
                    value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>"></label>
            <label>เลขที่เอกสาร <input name="document_no" maxlength="100"
                    value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี"></label>
            <label class="form-span-2">
                ชื่อสินค้า *
                <div class="product-combobox" id="issue-product-combobox" data-product-combobox>
                    <input type="hidden" name="product_code" id="issue-product-code" data-combobox-code
                        value="<?= h($selectedProductCode) ?>">
                    <div class="product-combobox-input-wrap">
                        <input type="text" id="issue-product-search" data-combobox-search
                            placeholder="พิมพ์ชื่อสินค้า หรือรหัสสินค้าเพื่อค้นหา..." autocomplete="off" role="combobox"
                            aria-autocomplete="list" aria-expanded="false" aria-controls="issue-product-list" required
                            value="<?= h($selectedProductName) ?>">
                        <button type="button" class="product-combobox-toggle" id="issue-product-toggle"
                            data-combobox-toggle aria-label="เปิดรายการสินค้า" tabindex="-1">▾</button>
                    </div>
                    <div class="product-combobox-list" id="issue-product-list" data-combobox-list role="listbox" hidden>
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
                        <div class="product-combobox-empty" id="issue-product-empty" data-combobox-empty hidden>
                            ไม่พบสินค้าที่ตรงกับคำค้นหา</div>
                    </div>
                </div>
            </label>
            <label>รหัสสินค้า <input id="issue-product-code-display" data-combobox-code-display readonly
                    placeholder="เลือกสินค้าเพื่อแสดงรหัส" value="<?= h($selectedProductCode) ?>"></label>
            <label>จำนวนเบิก <input type="number" name="quantity" min="0" step="1" required
                    value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
            <label>หน่วยนับ <input id="movement-product-unit" data-combobox-unit-display readonly placeholder="—"
                    value="<?= h($selectedProductUnit) ?>"></label>
            <?php
            $user = auth_user();
            $defaultRequester = (string) ($user['name'] ?? '');
            ?>
            <label>ผู้เบิก <input name="requested_by" maxlength="150" required
                    value="<?= h((string) ($_POST['requested_by'] ?? $defaultRequester)) ?>"></label>
            <label>ผู้อนุมัติ * <input name="approved_by" maxlength="150" required
                    value="<?= h((string) ($_POST['approved_by'] ?? '')) ?>"></label>
            <label class="form-span-3">หมายเหตุ <input name="notes" maxlength="500"
                    value="<?= h((string) ($_POST['notes'] ?? '')) ?>"></label>
            <div class="form-actions form-span-2"><button class="primary-button" type="submit">บันทึกเบิกสินค้า</button>
            </div>
        </form>
    </section>
    <section class="inventory-panel table-panel">
        <div class="panel-heading">
            <div>
                <h2>ประวัติเบิกสินค้า</h2>
                <p>แสดงรายการล่าสุดไม่เกิน 250 รายการ</p>
            </div>
        </div>
        <div class="table-scroll">
            <table class="inventory-table movement-table">
                <thead>
                    <tr>
                        <th>วันที่เบิก</th>
                        <th>เลขที่เอกสาร</th>
                        <th>รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th class="numeric">จำนวนเบิก</th>
                        <th>หน่วยนับ</th>
                        <th>ผู้เบิก</th>
                        <th>ผู้อนุมัติ</th>
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
                            <td><?= h($row['requested_by']) ?></td>
                            <td><?= h($row['approved_by']) ?></td>
                            <td><?= h($row['notes']) ?></td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($history === []): ?>
            <p class="table-empty">ยังไม่มีประวัติเบิกสินค้า</p><?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>