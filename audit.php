<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_role('admin');

$pageTitle = 'ประวัติการใช้งานระบบ (Audit Log) | Store Automeation';
$pageDescription = 'แสดงประวัติกิจกรรมล่าสุดไม่เกิน 250 รายการ และการแก้ไขข้อมูลในระบบคลังสินค้า';
$activePage = 'audit';

// --- POST: ลบ Audit Log ที่เลือก ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_audit_logs') {
    try {
        inventory_require_csrf();
        $ids = $_POST['delete_ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            throw new DomainException('กรุณาเลือกรายการที่ต้องการลบ');
        }
        inventory_delete_audit_logs($ids);
        inventory_flash_redirect('ลบรายการ Audit Log เรียบร้อยแล้ว ' . count($ids) . ' รายการ', 'audit.php');
    } catch (Throwable $e) {
        $notice = $e->getMessage();
        $noticeType = 'error';
    }
}

$db = inventory_db();
$actionFilter = isset($_GET['action']) && is_string($_GET['action']) ? trim($_GET['action']) : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

$sql = 'SELECT id, user_id, username, user_name, action, target, details, ip_address, created_at
        FROM `audit_logs`
        WHERE `action` NOT IN (\'login\', \'login_failed\', \'login_blocked\', \'logout\')';
$params = [];

if ($actionFilter !== '') {
    $sql .= ' AND `action` = :action';
    $params['action'] = $actionFilter;
}

if ($search !== '') {
    $sql .= ' AND (`username` LIKE :q OR `user_name` LIKE :q OR `target` LIKE :q OR `details` LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$sql .= ' ORDER BY id DESC LIMIT 250';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionCounts = $db->query(
    'SELECT `action`, COUNT(*) as total FROM `audit_logs` WHERE `action` NOT IN (\'login\', \'login_failed\', \'login_blocked\', \'logout\') GROUP BY `action` ORDER BY total DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">SECURITY & AUDIT TRAIL</div>
            <h1>ประวัติการใช้งานระบบ (Audit Log)<span class="heading-period">.</span></h1>
            <p>บันทึกประวัติกิจกรรม ตรวจสอบความเคลื่อนไหว และการแก้ไขข้อมูลย้อนหลัง</p>
        </div>
        <div class="page-kpis">
            <span><strong><?= count($logs) ?></strong> รายการล่าสุด (จำกัด 250 รายการ)</span>
            <a class="secondary-button" href="backup.php">สำรองข้อมูลระบบ ↗</a>
        </div>
    </div>

    <div class="catalog-toolbar" style="margin-bottom:18px;">
        <div class="category-filters" role="group" aria-label="กรองประเภทกิจกรรม">
            <a class="filter-chip <?= $actionFilter === '' ? 'selected' : '' ?>" href="audit.php">ทั้งหมด</a>
            <a class="filter-chip <?= $actionFilter === 'product_create' ? 'selected' : '' ?>" href="audit.php?action=product_create">เพิ่มสินค้า</a>
            <a class="filter-chip <?= $actionFilter === 'product_update' ? 'selected' : '' ?>" href="audit.php?action=product_update">แก้ไขสินค้า</a>
            <a class="filter-chip <?= $actionFilter === 'product_delete' ? 'selected' : '' ?>" href="audit.php?action=product_delete">ลบสินค้า</a>
            <a class="filter-chip <?= $actionFilter === 'receipt_create' ? 'selected' : '' ?>" href="audit.php?action=receipt_create">รับเข้า</a>
            <a class="filter-chip <?= $actionFilter === 'issue_create' ? 'selected' : '' ?>" href="audit.php?action=issue_create">เบิกออก</a>
            <a class="filter-chip <?= in_array($actionFilter, ['borrow_create', 'borrow_return', 'borrow_delete'], true) ? 'selected' : '' ?>" href="audit.php?action=borrow_create">ยืม-คืน</a>
            <a class="filter-chip <?= in_array($actionFilter, ['user_create', 'user_update', 'user_delete'], true) ? 'selected' : '' ?>" href="audit.php?action=user_update">จัดการผู้ใช้</a>
        </div>
        <form method="get" class="search-box" style="min-width:280px;">
            <?php if ($actionFilter !== ''): ?>
                <input type="hidden" name="action" value="<?= h($actionFilter) ?>">
            <?php endif; ?>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input type="search" name="q" placeholder="ค้นหาชื่อผู้ใช้, รหัส, ข้อความ..." value="<?= h($search) ?>" autocomplete="off">
        </form>
    </div>

    <section class="inventory-panel table-panel">
        <div class="table-scroll">
            <form method="post" id="audit-bulk-form">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="delete_audit_logs">
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f0f5fc;border:1px solid #d0e1f7;border-radius:8px;margin-bottom:12px;" id="audit-bulk-toolbar" hidden>
                    <span id="audit-selected-count" style="font-size:12px;color:#074080;font-weight:600;">0 รายการที่เลือก</span>
                    <button class="primary-button" type="submit" style="background:linear-gradient(105deg,#0758b4,#05448c);min-height:32px;padding:0 14px;font-size:12px;" onclick="return confirm('ยืนยันการลบรายการ Audit Log ที่เลือก?\nการดำเนินการนี้ไม่สามารถย้อนกลับได้')">ลบรายการที่เลือก</button>
                    <button class="secondary-button" type="button" style="min-height:32px;padding:0 14px;font-size:12px;" onclick="auditClearSelection()">ยกเลิกการเลือก</button>
                </div>
            <table class="inventory-table audit-table">
                <thead>
                    <tr>
                        <th style="width:36px;text-align:center;">
                            <input type="checkbox" id="audit-select-all" title="เลือกทั้งหมด" style="accent-color:#0758b4;cursor:pointer;">
                        </th>
                        <th style="width:130px;">วัน-เวลา</th>
                        <th>ผู้ใช้งาน</th>
                        <th>การกระทำ (Action)</th>
                        <th>เป้าหมาย</th>
                        <th>รายละเอียด</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $actionLabel = match ($log['action']) {
                                'product_create' => 'เพิ่มสินค้า',
                                'product_update' => 'แก้ไขสินค้า',
                                'product_delete' => 'ลบสินค้า',
                                'receipt_create' => 'รับเข้าคลัง',
                                'issue_create' => 'เบิกจ่าย',
                                'user_create' => 'สร้างผู้ใช้',
                                'user_update' => 'แก้ไขผู้ใช้',
                                'user_delete' => 'ลบผู้ใช้',
                                'profile_update' => 'แก้ไขโปรไฟล์',
                                'user_status_change' => 'เปลี่ยนสถานะผู้ใช้',
                                'export' => 'ส่งออก CSV',
                                'backup' => 'สำรองข้อมูล',
                                'audit_log_delete' => 'ลบ Audit Log',
                                'movement_delete_receipt' => 'ลบประวัติรับ',
                                'movement_delete_issue' => 'ลบประวัติเบิก',
                                'borrow_create' => 'ยืมพัสดุ',
                                'borrow_return' => 'คืนพัสดุ',
                                'borrow_delete' => 'ลบประวัติยืม',
                                'settings_update' => 'ตั้งค่าระบบ',
                                'print_receipt' => 'พิมพ์ใบรับสินค้า',
                                'print_issue' => 'พิมพ์ใบเบิกสินค้า',
                                default => $log['action'],
                            };
                        ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox" name="delete_ids[]" value="<?= (int)$log['id'] ?>"
                                       class="audit-row-check" style="accent-color:#0758b4;cursor:pointer;">
                            </td>
                            <td style="font-size:11px;color:#666;"><?= h((string) $log['created_at']) ?></td>
                            <td>
                                <strong><?= h($log['user_name'] ?: $log['username']) ?></strong>
                                <small style="display:block;color:#888;font-size:10px;">@<?= h($log['username']) ?></small>
                            </td>
                            <td><span style="font-weight:600;font-size:11px;"><?= h($actionLabel) ?></span></td>
                            <td class="code-cell"><?= h($log['target']) ?></td>
                            <td><?= h($log['details']) ?></td>
                            <td style="font-size:11px;color:#777;"><?= h($log['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </form>
        </div>
        <?php if ($logs === []): ?>
            <p class="table-empty">ไม่พบประวัติการใช้งานที่ตรงกับเงื่อนไข</p>
        <?php endif; ?>
    </section>
</section>
<script>
(function() {
    const selectAll = document.getElementById('audit-select-all');
    const toolbar = document.getElementById('audit-bulk-toolbar');
    const countEl = document.getElementById('audit-selected-count');

    function updateToolbar() {
        const checked = document.querySelectorAll('.audit-row-check:checked');
        const n = checked.length;
        toolbar.hidden = n === 0;
        countEl.textContent = n + ' รายการที่เลือก';
        selectAll.indeterminate = n > 0 && n < document.querySelectorAll('.audit-row-check').length;
        selectAll.checked = n > 0 && n === document.querySelectorAll('.audit-row-check').length;
    }

    selectAll.addEventListener('change', function() {
        document.querySelectorAll('.audit-row-check').forEach(cb => { cb.checked = selectAll.checked; });
        updateToolbar();
    });

    document.querySelectorAll('.audit-row-check').forEach(cb => {
        cb.addEventListener('change', updateToolbar);
    });

    window.auditClearSelection = function() {
        document.querySelectorAll('.audit-row-check').forEach(cb => { cb.checked = false; });
        selectAll.checked = false;
        updateToolbar();
    };
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
