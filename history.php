<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'ประวัติรับ–เบิกสินค้า | Store Automeation';
$pageDescription = 'ประวัติรวมการรับสินค้าเข้าคลังและเบิกสินค้าออก แยกหัวข้อชัดเจน พร้อมระบบค้นหาและกรองข้อมูล';
$activePage = 'history';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_movement') {
    try {
        inventory_require_csrf();
        $kind = (string)($_POST['movement_kind'] ?? '');
        $id = (int)($_POST['movement_id'] ?? 0);
        $values = [
            'movement_date' => inventory_date('movement_date'),
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'quantity' => inventory_number('quantity', 'จำนวน', false),
            'department' => inventory_text('department', 'แผนก/หน่วยงาน', 150),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ];
        if ($kind === 'receipt') {
            $values['supplier'] = inventory_text('supplier', 'ผู้ส่งมอบ', 150);
            $values['received_by'] = inventory_text('received_by', 'ผู้รับสินค้า', 150);
        } else {
            $values['requested_by'] = inventory_text('requested_by', 'ผู้เบิก', 150);
            $values['approved_by'] = inventory_text('approved_by', 'ผู้อนุมัติ', 150);
        }
        inventory_update_movement($kind, $id, $values);
        inventory_flash_redirect('บันทึกการแก้ไขรายการเรียบร้อยแล้ว', 'history.php');
    } catch (Throwable $e) {
        $notice = inventory_handle_error($e);
        $noticeType = 'error';
    }
}

$receipts = inventory_movements('receipt', 500);
$issues = inventory_movements('issue', 500);
$movementCounts = inventory_movement_counts();

$totalReceiptQty = 0.0;
foreach ($receipts as $r) {
    $totalReceiptQty += (float) ($r['quantity'] ?? 0);
}

$totalIssueQty = 0.0;
foreach ($issues as $i) {
    $totalIssueQty += (float) ($i['quantity'] ?? 0);
}

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page history-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">MOVEMENT HISTORY</div>
            <h1>ประวัติรับ–เบิกสินค้า<span class="heading-period">.</span></h1>
            <p>ประวัติความเคลื่อนไหวสินค้าทั้งหมด แยกตามการรับเข้าและเบิกออกอย่างชัดเจน พร้อมระบบค้นหา</p>
        </div>
        <div class="page-kpis">
            <span><strong><?= count($receipts) ?></strong> รายการรับเข้า</span>
            <span><strong><?= count($issues) ?></strong> รายการเบิกออก</span>
            <a class="secondary-button" href="export.php?type=history" style="font-size:11px;">Export ประวัติทั้งหมด</a>
            <a class="secondary-button" href="catalog.php">ดูทะเบียนสินค้า ↗</a>
        </div>
    </div>

    <div class="stats-strip history-stats-strip">
        <div class="stat-item">
            <span class="stat-icon icon-box">IN</span>
            <div>
                <strong><?= number_format(count($receipts)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>รับเข้ารวม <?= inventory_quantity($totalReceiptQty) ?> หน่วย</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-alert" style="color:#ef233c;background:#ffebee;">OUT</span>
            <div>
                <strong><?= number_format(count($issues)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>เบิกออกรวม <?= inventory_quantity($totalIssueQty) ?> หน่วย</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-check">ALL</span>
            <div>
                <strong><?= number_format(count($receipts) + count($issues)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>การเคลื่อนไหวทั้งหมด</span>
            </div>
        </div>
        <div class="stats-note" style="display:flex;gap:10px;">
            <?php if (!auth_is_viewer()): ?>
                <a class="primary-button" href="receive.php" style="min-height:36px;padding:0 14px;font-size:11px;">+ รับสินค้าเข้า</a>
                <a class="secondary-button" href="issue.php" style="min-height:36px;padding:0 14px;font-size:11px;">+ บันทึกเบิก</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="catalog-toolbar history-toolbar" style="margin-bottom:18px;">
        <div class="category-filters" role="group" aria-label="กรองประเภทประวัติ">
            <button class="filter-chip selected" type="button" data-history-tab="all">
                รายการทั้งหมด <span><?= count($receipts) + count($issues) ?></span>
            </button>
            <button class="filter-chip" type="button" data-history-tab="receipt">
                ประวัติรับสินค้าเข้า <span><?= count($receipts) ?></span>
            </button>
            <button class="filter-chip" type="button" data-history-tab="issue">
                ประวัติเบิกสินค้าออก <span><?= count($issues) ?></span>
            </button>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <span class="result-count" id="history-result-count">แสดง <?= count($receipts) + count($issues) ?> รายการ</span>
            <label class="search-box" style="min-width:280px;">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <input id="history-search" type="search" placeholder="ค้นหารหัส, ชื่อสินค้า, เลขที่เอกสาร..." autocomplete="off">
                <kbd>/</kbd>
            </label>
        </div>
    </div>

    <!-- Section 1: ประวัติรับสินค้าเข้าคลัง -->
    <section class="inventory-panel table-panel history-section" id="history-section-receipts">
        <div class="panel-heading">
            <div>
                <div class="eyebrow section-eyebrow" style="color:#2f855a;">STOCK IN</div>
                <h2>ประวัติรับสินค้าเข้าคลัง</h2>
                <p>รายการรับสินค้าเข้าคลัง ผู้ส่งมอบ ผู้รับสินค้า และเลขที่เอกสารอ้างอิง</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="history-badge history-badge-receipt">รับเข้า</span>
                <span class="result-count section-visible-count"><?= count($receipts) ?> รายการ</span>
                <a class="secondary-button" href="export.php?type=receipts" style="font-size:10px;padding:4px 10px;">Export</a>
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
                        <th>ผู้ส่งมอบ</th>
                        <th>ผู้รับสินค้า</th>
                        <th>แผนก/หน่วยงาน</th>
                        <th>ผู้บันทึก</th>
                        <th>หมายเหตุ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $row): ?>
                        <?php
                            $searchText = strtolower(implode(' ', [
                                (string) ($row['movement_date'] ?? ''),
                                (string) ($row['document_no'] ?? ''),
                                (string) ($row['product_code'] ?? ''),
                                (string) ($row['product_name'] ?? ''),
                                (string) ($row['supplier'] ?? ''),
                                (string) ($row['received_by'] ?? ''),
                                (string) ($row['department'] ?? ''),
                                (string) ($row['created_by_name'] ?? ''),
                                (string) ($row['notes'] ?? ''),
                            ]));
                        ?>
                        <tr class="history-row" data-type="receipt" data-search="<?= h($searchText) ?>">
                            <td><?= h($row['movement_date']) ?></td>
                            <td><?= h($row['document_no'] !== '' ? $row['document_no'] : '-') ?></td>
                            <td class="code-cell"><?= h($row['product_code']) ?></td>
                            <td class="name-cell"><strong><?= h($row['product_name']) ?></strong></td>
                            <td class="numeric" style="color:#2b773c;font-weight:700;">+<?= inventory_quantity($row['quantity']) ?></td>
                            <td><?= h($row['unit']) ?></td>
                            <td><?= h($row['supplier'] !== '' ? $row['supplier'] : '-') ?></td>
                            <td><?= h($row['received_by'] !== '' ? $row['received_by'] : '-') ?></td>
                            <td><?= h($row['department'] !== '' ? $row['department'] : '-') ?></td>
                            <td><?= h($row['created_by_name'] !== '' ? $row['created_by_name'] : '-') ?></td>
                            <td><?= h($row['notes'] !== '' ? $row['notes'] : '-') ?></td>
                            <td style="white-space:nowrap;">
                                <div style="display:inline-flex;gap:5px;align-items:center;">
                                    <a class="secondary-button" href="slip.php?type=receipt&id=<?= (int)($row['id'] ?? 0) ?>" target="_blank" style="min-height:28px;padding:0 8px;font-size:11px;" title="พิมพ์ใบรับสินค้า">พิมพ์</a>
                                    <?php if (inventory_can_edit_movement($row)): ?>
                                        <button class="secondary-button" type="button" style="min-height:28px;padding:0 8px;font-size:11px;" onclick="openEditMovement('receipt', <?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">แก้ไข</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="history-empty-search" style="padding:22px;text-align:center;color:#888;" hidden>ไม่พบรายการรับสินค้าที่ตรงกับคำค้นหา</p>
        <?php if ($receipts === []): ?>
            <p class="table-empty">ยังไม่มีประวัติรับสินค้า</p>
        <?php endif; ?>
    </section>

    <!-- Section 2: ประวัติเบิกสินค้าออก -->
    <section class="inventory-panel table-panel history-section" id="history-section-issues" style="margin-top:24px;">
        <div class="panel-heading">
            <div>
                <div class="eyebrow section-eyebrow" style="color:#c53030;">STOCK OUT</div>
                <h2>ประวัติเบิกสินค้าออก</h2>
                <p>รายการเบิกจ่ายสินค้าออกจากคลัง ผู้ขอเบิก แผนกที่นำไปใช้ และผู้อนุมัติ</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="history-badge history-badge-issue">เบิกออก</span>
                <span class="result-count section-visible-count"><?= count($issues) ?> รายการ</span>
                <a class="secondary-button" href="export.php?type=issues" style="font-size:10px;padding:4px 10px;">Export</a>
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
                        <th>แผนก/ผู้รับ</th>
                        <th>ผู้อนุมัติ</th>
                        <th>ผู้บันทึก</th>
                        <th>หมายเหตุ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $row): ?>
                        <?php
                            $searchText = strtolower(implode(' ', [
                                (string) ($row['movement_date'] ?? ''),
                                (string) ($row['document_no'] ?? ''),
                                (string) ($row['product_code'] ?? ''),
                                (string) ($row['product_name'] ?? ''),
                                (string) ($row['requested_by'] ?? ''),
                                (string) ($row['department'] ?? ''),
                                (string) ($row['approved_by'] ?? ''),
                                (string) ($row['created_by_name'] ?? ''),
                                (string) ($row['notes'] ?? ''),
                            ]));
                        ?>
                        <tr class="history-row" data-type="issue" data-search="<?= h($searchText) ?>">
                            <td><?= h($row['movement_date']) ?></td>
                            <td><?= h($row['document_no'] !== '' ? $row['document_no'] : '-') ?></td>
                            <td class="code-cell"><?= h($row['product_code']) ?></td>
                            <td class="name-cell"><strong><?= h($row['product_name']) ?></strong></td>
                            <td class="numeric" style="color:#c53030;font-weight:700;">-<?= inventory_quantity($row['quantity']) ?></td>
                            <td><?= h($row['unit']) ?></td>
                            <td><?= h($row['requested_by'] !== '' ? $row['requested_by'] : '-') ?></td>
                            <td><?= h($row['department'] !== '' ? $row['department'] : '-') ?></td>
                            <td><?= h($row['approved_by'] !== '' ? $row['approved_by'] : '-') ?></td>
                            <td><?= h($row['created_by_name'] !== '' ? $row['created_by_name'] : '-') ?></td>
                            <td><?= h($row['notes'] !== '' ? $row['notes'] : '-') ?></td>
                            <td style="white-space:nowrap;">
                                <div style="display:inline-flex;gap:5px;align-items:center;">
                                    <a class="secondary-button" href="slip.php?type=issue&id=<?= (int)($row['id'] ?? 0) ?>" target="_blank" style="min-height:28px;padding:0 8px;font-size:11px;" title="พิมพ์ใบเบิกสินค้า">พิมพ์</a>
                                    <?php if (inventory_can_edit_movement($row)): ?>
                                        <button class="secondary-button" type="button" style="min-height:28px;padding:0 8px;font-size:11px;" onclick="openEditMovement('issue', <?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">แก้ไข</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="history-empty-search" style="padding:22px;text-align:center;color:#888;" hidden>ไม่พบรายการเบิกสินค้าที่ตรงกับคำค้นหา</p>
        <?php if ($issues === []): ?>
            <p class="table-empty">ยังไม่มีประวัติเบิกสินค้า</p>
        <?php endif; ?>
    </section>

    <!-- Dialog: แก้ไขรายการรับ/เบิกสินค้า -->
    <dialog class="product-detail-dialog" id="edit-movement-dialog" style="max-width:560px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow" id="edit-movement-eyebrow">EDIT MOVEMENT</div>
                <h2 id="edit-movement-title">แก้ไขรายการ</h2>
            </div>
            <button class="product-detail-close" type="button" onclick="document.querySelector('#edit-movement-dialog').close()">×</button>
        </div>
        <form class="inventory-form" method="post" id="edit-movement-form" style="grid-template-columns:1fr 1fr;gap:14px;margin-top:16px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_movement">
            <input type="hidden" name="movement_kind" id="edit-movement-kind" value="">
            <input type="hidden" name="movement_id" id="edit-movement-id" value="">

            <div style="grid-column:span 2;padding:10px 14px;background:#f5f7f4;border-radius:6px;border:1px solid #e2e8e0;">
                <div style="font-size:11px;color:#777;">สินค้าที่ทำรายการ:</div>
                <strong id="edit-movement-product" style="font-size:13px;color:#292326;"></strong>
            </div>

            <label>
                วันที่ทำรายการ
                <input type="date" name="movement_date" id="edit-movement-date" required>
            </label>

            <label>
                เลขที่เอกสาร
                <input type="text" name="document_no" id="edit-movement-doc" placeholder="เช่น REC-2026-001">
            </label>

            <label>
                จำนวน (<span id="edit-movement-unit">หน่วย</span>)
                <input type="number" step="0.01" min="0.01" name="quantity" id="edit-movement-qty" required>
            </label>

            <label>
                แผนก / หน่วยงาน
                <input type="text" name="department" id="edit-movement-dept" placeholder="แผนกที่เกี่ยวข้อง">
            </label>

            <!-- Fields for Receipt only -->
            <div id="receipt-fields" style="grid-column:span 2;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <label>
                    ผู้ส่งมอบ (Supplier)
                    <input type="text" name="supplier" id="edit-movement-supplier" placeholder="บริษัทคู่ค้า / ร้านค้า">
                </label>
                <label>
                    ผู้รับสินค้า
                    <input type="text" name="received_by" id="edit-movement-received-by" placeholder="ชื่อพนักงานที่รับ">
                </label>
            </div>

            <!-- Fields for Issue only -->
            <div id="issue-fields" style="grid-column:span 2;display:grid;grid-template-columns:1fr 1fr;gap:14px;" hidden>
                <label>
                    ผู้ขอเบิก
                    <input type="text" name="requested_by" id="edit-movement-requested-by" placeholder="ชื่อผู้เบิก">
                </label>
                <label>
                    ผู้อนุมัติ (ถ้ามี)
                    <input type="text" name="approved_by" id="edit-movement-approved-by" placeholder="หัวหน้างาน / ผู้มีอำนาจ">
                </label>
            </div>

            <label style="grid-column:span 2;">
                หมายเหตุ
                <input type="text" name="notes" id="edit-movement-notes" placeholder="รายละเอียดเพิ่มเติม (ถ้ามี)">
            </label>

            <div class="form-actions" style="grid-column:span 2;display:flex;justify-content:flex-end;gap:10px;margin-top:8px;">
                <button class="secondary-button" type="button" onclick="document.querySelector('#edit-movement-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit">บันทึกการแก้ไข</button>
            </div>
        </form>
    </dialog>
</section>

<script>
function openEditMovement(kind, data) {
    const dialog = document.querySelector('#edit-movement-dialog');
    if (!dialog) return;

    document.querySelector('#edit-movement-kind').value = kind;
    document.querySelector('#edit-movement-id').value = data.id || '';
    document.querySelector('#edit-movement-eyebrow').textContent = kind === 'receipt' ? 'EDIT RECEIPT' : 'EDIT ISSUE';
    document.querySelector('#edit-movement-title').textContent = kind === 'receipt' ? 'แก้ไขรายการรับสินค้า' : 'แก้ไขรายการเบิกสินค้า';
    document.querySelector('#edit-movement-product').textContent = (data.product_code || '') + ' - ' + (data.product_name || '');
    document.querySelector('#edit-movement-unit').textContent = data.unit || 'หน่วย';
    document.querySelector('#edit-movement-date').value = data.movement_date || '';
    document.querySelector('#edit-movement-doc').value = data.document_no || '';
    document.querySelector('#edit-movement-qty').value = data.quantity || '';
    document.querySelector('#edit-movement-dept').value = data.department || '';
    document.querySelector('#edit-movement-notes').value = data.notes || '';

    const receiptBox = document.querySelector('#receipt-fields');
    const issueBox = document.querySelector('#issue-fields');
    const supplierInput = document.querySelector('#edit-movement-supplier');
    const receivedByInput = document.querySelector('#edit-movement-received-by');
    const requestedByInput = document.querySelector('#edit-movement-requested-by');
    const approvedByInput = document.querySelector('#edit-movement-approved-by');

    if (kind === 'receipt') {
        receiptBox.hidden = false;
        issueBox.hidden = true;
        supplierInput.disabled = false;
        receivedByInput.disabled = false;
        requestedByInput.disabled = true;
        approvedByInput.disabled = true;
        supplierInput.value = data.supplier || '';
        receivedByInput.value = data.received_by || '';
    } else {
        receiptBox.hidden = true;
        issueBox.hidden = false;
        supplierInput.disabled = true;
        receivedByInput.disabled = true;
        requestedByInput.disabled = false;
        approvedByInput.disabled = false;
        requestedByInput.value = data.requested_by || '';
        approvedByInput.value = data.approved_by || '';
    }

    dialog.showModal();
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
