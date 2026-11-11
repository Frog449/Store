<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_role('admin');

$pageTitle = 'ประวัติการใช้งานระบบ (Audit Log) | Store Automeation';
$pageDescription = 'บันทึกประวัติการกระทำ การเข้าสู่ระบบ และการแก้ไขข้อมูลในระบบคลังสินค้า';
$activePage = 'audit';

$db = inventory_db();
$actionFilter = isset($_GET['action']) && is_string($_GET['action']) ? trim($_GET['action']) : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

$sql = 'SELECT id, user_id, username, user_name, action, target, details, ip_address, created_at
        FROM `audit_logs`
        WHERE 1=1';
$params = [];

if ($actionFilter !== '') {
    $sql .= ' AND `action` = :action';
    $params['action'] = $actionFilter;
}

if ($search !== '') {
    $sql .= ' AND (`username` LIKE :q OR `user_name` LIKE :q OR `target` LIKE :q OR `details` LIKE :q)';
    $params['q'] = '%' . $search . '%';
}

$sql .= ' ORDER BY id DESC LIMIT 300';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionCounts = $db->query(
    'SELECT `action`, COUNT(*) as total FROM `audit_logs` GROUP BY `action` ORDER BY total DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">SECURITY & AUDIT TRAIL</div>
            <h1>ประวัติการใช้งานระบบ (Audit Log)<span class="heading-period">.</span></h1>
            <p>บันทึกประวัติกิจกรรม ตรวจสอบความเคลื่อนไหว การเข้าสู่ระบบ และการแก้ไขข้อมูลย้อนหลัง</p>
        </div>
        <div class="page-kpis">
            <span><strong><?= count($logs) ?></strong> รายการล่าสุด</span>
            <a class="secondary-button" href="backup.php">สำรองข้อมูลระบบ ↗</a>
        </div>
    </div>

    <div class="catalog-toolbar" style="margin-bottom:18px;">
        <div class="category-filters" role="group" aria-label="กรองประเภทกิจกรรม">
            <a class="filter-chip <?= $actionFilter === '' ? 'selected' : '' ?>" href="audit.php">ทั้งหมด</a>
            <a class="filter-chip <?= $actionFilter === 'login' ? 'selected' : '' ?>" href="audit.php?action=login">เข้าสู่ระบบ</a>
            <a class="filter-chip <?= $actionFilter === 'product_create' ? 'selected' : '' ?>" href="audit.php?action=product_create">เพิ่มสินค้า</a>
            <a class="filter-chip <?= $actionFilter === 'product_update' ? 'selected' : '' ?>" href="audit.php?action=product_update">แก้ไขสินค้า</a>
            <a class="filter-chip <?= $actionFilter === 'product_delete' ? 'selected' : '' ?>" href="audit.php?action=product_delete">ลบสินค้า</a>
            <a class="filter-chip <?= $actionFilter === 'receipt_create' ? 'selected' : '' ?>" href="audit.php?action=receipt_create">รับเข้า</a>
            <a class="filter-chip <?= $actionFilter === 'issue_create' ? 'selected' : '' ?>" href="audit.php?action=issue_create">เบิกออก</a>
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
            <table class="inventory-table">
                <thead>
                    <tr>
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
                                'login' => '🟢 เข้าสู่ระบบ',
                                'login_failed' => '🔴 ล็อกอินล้มเหลว',
                                'logout' => '⚪ ออกจากระบบ',
                                'product_create' => '📦 เพิ่มสินค้า',
                                'product_update' => '✏️ แก้ไขสินค้า',
                                'product_delete' => '🗑️ ลบสินค้า',
                                'receipt_create' => '📥 รับเข้าคลัง',
                                'issue_create' => '📤 เบิกจ่าย',
                                'user_create' => '👤 สร้างผู้ใช้',
                                'user_update' => '🔧 แก้ไขผู้ใช้',
                                'user_delete' => '❌ ลบผู้ใช้',
                                'export' => '📊 ส่งออก CSV',
                                'backup' => '💾 สำรองข้อมูล',
                                default => $log['action'],
                            };
                        ?>
                        <tr>
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
        </div>
        <?php if ($logs === []): ?>
            <p class="table-empty">ไม่พบประวัติการใช้งานที่ตรงกับเงื่อนไข</p>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

