<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'ประวัติรับ–เบิกสินค้า | Store Automeation';
$pageDescription = 'ประวัติรวมการรับสินค้าเข้าคลังและเบิกสินค้าออก แยกหัวข้อชัดเจน พร้อมระบบค้นหาและกรองข้อมูล';
$activePage = 'history';

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
            <a class="secondary-button" href="export.php?type=history" style="font-size:11px;">📊 Export ประวัติทั้งหมด</a>
            <a class="secondary-button" href="catalog.php">ดูทะเบียนสินค้า ↗</a>
        </div>
    </div>

    <div class="stats-strip history-stats-strip">
        <div class="stat-item">
            <span class="stat-icon icon-box">📥</span>
            <div>
                <strong><?= number_format(count($receipts)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>รับเข้ารวม <?= inventory_quantity($totalReceiptQty) ?> หน่วย</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-alert" style="color:#ef233c;background:#ffebee;">📤</span>
            <div>
                <strong><?= number_format(count($issues)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>เบิกออกรวม <?= inventory_quantity($totalIssueQty) ?> หน่วย</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-check">📋</span>
            <div>
                <strong><?= number_format(count($receipts) + count($issues)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>การเคลื่อนไหวทั้งหมด</span>
            </div>
        </div>
        <div class="stats-note" style="display:flex;gap:10px;">
            <a class="primary-button" href="receive.php" style="min-height:36px;padding:0 14px;font-size:11px;">+ รับสินค้าเข้า</a>
            <a class="secondary-button" href="issue.php" style="min-height:36px;padding:0 14px;font-size:11px;">+ บันทึกเบิก</a>
        </div>
    </div>

    <div class="catalog-toolbar history-toolbar" style="margin-bottom:18px;">
        <div class="category-filters" role="group" aria-label="กรองประเภทประวัติ">
            <button class="filter-chip selected" type="button" data-history-tab="all">
                รายการทั้งหมด <span><?= count($receipts) + count($issues) ?></span>
            </button>
            <button class="filter-chip" type="button" data-history-tab="receipt">
                📥 ประวัติรับสินค้าเข้า <span><?= count($receipts) ?></span>
            </button>
            <button class="filter-chip" type="button" data-history-tab="issue">
                📤 ประวัติเบิกสินค้าออก <span><?= count($issues) ?></span>
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
                <h2>📥 ประวัติรับสินค้าเข้าคลัง</h2>
                <p>รายการรับสินค้าเข้าคลัง ผู้ส่งมอบ ผู้รับสินค้า และเลขที่เอกสารอ้างอิง</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="history-badge history-badge-receipt">รับเข้า</span>
                <span class="result-count section-visible-count"><?= count($receipts) ?> รายการ</span>
                <a class="secondary-button" href="export.php?type=receipts" style="font-size:10px;padding:4px 10px;">📊 Export</a>
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
                        <th>หมายเหตุ</th>
                        <th>ใบรับ</th>
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
                            <td><?= h($row['notes'] !== '' ? $row['notes'] : '-') ?></td>
                            <td><a class="slip-btn" href="slip.php?type=receipt&id=<?= (int)($row['id'] ?? 0) ?>" target="_blank" title="พิมพ์ใบรับสินค้า">🖨️ พิมพ์</a></td>
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
                <h2>📤 ประวัติเบิกสินค้าออก</h2>
                <p>รายการเบิกจ่ายสินค้าออกจากคลัง ผู้ขอเบิก แผนกที่นำไปใช้ และผู้อนุมัติ</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="history-badge history-badge-issue">เบิกออก</span>
                <span class="result-count section-visible-count"><?= count($issues) ?> รายการ</span>
                <a class="secondary-button" href="export.php?type=issues" style="font-size:10px;padding:4px 10px;">📊 Export</a>
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
                        <th>หมายเหตุ</th>
                        <th>ใบเบิก</th>
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
                            <td><?= h($row['notes'] !== '' ? $row['notes'] : '-') ?></td>
                            <td><a class="slip-btn" href="slip.php?type=issue&id=<?= (int)($row['id'] ?? 0) ?>" target="_blank" title="พิมพ์ใบเบิกสินค้า">🖨️ พิมพ์</a></td>
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
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

