<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'เบิกสินค้าออกจากคลัง | Store Automeation';
$pageDescription = 'บันทึกเบิกสินค้าออกจากคลังและดูประวัติรายการเบิก';
$activePage = 'issue';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_issue') {
    try {
        inventory_require_csrf();
        $productCode = inventory_text('product_code', 'รหัสสินค้า', 80, true);
        inventory_record_movement('issue', [
            'movement_date' => inventory_date(),
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'product_code' => $productCode,
            'quantity' => inventory_number('quantity', 'จำนวนเบิก', false),
            'requested_by' => inventory_text('requested_by', 'ผู้เบิก', 150),
            'department' => inventory_text('department', 'แผนก/ผู้รับ', 150),
            'approved_by' => inventory_text('approved_by', 'ผู้อนุมัติ', 150, true),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ]);
        inventory_flash_redirect('บันทึกเบิกสินค้าออกจากคลังแล้ว', 'issue.php');
    } catch (DomainException|PDOException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
    }
}

$pageProducts = inventory_products();
$history = inventory_movements('issue');
require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div><div class="eyebrow section-eyebrow">STOCK OUT</div><h1>บันทึกเบิกสินค้า<span class="heading-period">.</span></h1><p>บันทึกการเบิกหรือจ่ายสินค้าออก พร้อมระบุผู้เบิกและผู้อนุมัติ</p></div>
        <div class="page-kpis"><span><strong><?= count($history) ?></strong> รายการเบิก</span><a class="secondary-button" href="catalog.php">ดูทะเบียนสินค้า ↗</a></div>
    </div>
    <section class="inventory-panel">
        <div class="panel-heading"><div><h2>เพิ่มรายการเบิกสินค้า</h2><p>เลือกสินค้าแล้วกรอกวันที่ จำนวนเบิก และข้อมูลผู้เบิก</p></div></div>
        <form class="inventory-form movement-form" method="post">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>"><input type="hidden" name="action" value="record_issue">
            <label>วันที่เบิก <input type="date" name="movement_date" required value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>"></label>
            <label>เลขที่เอกสาร <input name="document_no" maxlength="100" value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี"></label>
            <label>รหัสสินค้า <select name="product_code" id="movement-product" required><option value="">เลือกสินค้า</option><?php foreach ($pageProducts as $product): ?><option value="<?= h($product['id']) ?>" data-name="<?= h($product['name']) ?>" data-unit="<?= h($product['unit']) ?>" data-stock="<?= h((string) $product['stock']) ?>" <?= ($_POST['product_code'] ?? '') === $product['id'] ? 'selected' : '' ?>><?= h($product['id'] . ' — ' . $product['name']) ?> (คงเหลือ <?= inventory_quantity($product['stock']) ?>)</option><?php endforeach; ?></select></label>
            <label class="form-span-2">ชื่อสินค้า <input id="movement-product-name" readonly placeholder="เลือกสินค้าเพื่อแสดงชื่อ"></label>
            <label>จำนวนเบิก <input type="number" name="quantity" min="0.001" step="0.001" required value="<?= h((string) ($_POST['quantity'] ?? '')) ?>"></label>
            <label>หน่วยนับ <input id="movement-product-unit" readonly placeholder="—"></label>
            <label>ผู้เบิก <input name="requested_by" maxlength="150" value="<?= h((string) ($_POST['requested_by'] ?? '')) ?>"></label>
            <label>แผนก/ผู้รับ <input name="department" maxlength="150" value="<?= h((string) ($_POST['department'] ?? '')) ?>"></label>
            <label>ผู้อนุมัติ * <input name="approved_by" maxlength="150" required value="<?= h((string) ($_POST['approved_by'] ?? '')) ?>"></label>
            <label class="form-span-2">หมายเหตุ <input name="notes" maxlength="500" value="<?= h((string) ($_POST['notes'] ?? '')) ?>"></label>
            <div class="form-actions form-span-2"><button class="primary-button" type="submit">บันทึกเบิกสินค้า</button></div>
        </form>
    </section>
    <section class="inventory-panel table-panel">
        <div class="panel-heading"><div><h2>ประวัติเบิกสินค้า</h2><p>แสดงรายการล่าสุดไม่เกิน 250 รายการ</p></div></div>
        <div class="table-scroll"><table class="inventory-table movement-table">
            <thead><tr><th>วันที่เบิก</th><th>เลขที่เอกสาร</th><th>รหัสสินค้า</th><th>ชื่อสินค้า</th><th class="numeric">จำนวนเบิก</th><th>หน่วยนับ</th><th>ผู้เบิก</th><th>แผนก/ผู้รับ</th><th>ผู้อนุมัติ</th><th>หมายเหตุ</th></tr></thead>
            <tbody><?php foreach ($history as $row): ?><tr>
                <td><?= h($row['movement_date']) ?></td><td><?= h($row['document_no']) ?></td><td class="code-cell"><?= h($row['product_code']) ?></td><td class="name-cell"><?= h($row['product_name']) ?></td>
                <td class="numeric"><?= inventory_quantity($row['quantity']) ?></td><td><?= h($row['unit']) ?></td><td><?= h($row['requested_by']) ?></td><td><?= h($row['department']) ?></td><td><?= h($row['approved_by']) ?></td><td><?= h($row['notes']) ?></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if ($history === []): ?><p class="table-empty">ยังไม่มีประวัติเบิกสินค้า</p><?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
