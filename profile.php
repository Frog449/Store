<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_login();

$pageTitle = 'ข้อมูลส่วนตัว (Profile) | Store Automeation';
$pageDescription = 'จัดการและแก้ไขข้อมูลโปรไฟล์ ชื่อ-นามสกุล แผนก และรหัสผ่านของคุณ';
$activePage = 'profile';

$currentUser = auth_user();
$canEdit = in_array($currentUser['role'] ?? '', ['admin', 'staff'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    try {
        inventory_require_csrf();
        if (!$canEdit) {
            throw new DomainException('เฉพาะระดับ Admin และ Staff เท่านั้นที่สามารถแก้ไขโปรไฟล์ได้');
        }

        $name = (string) ($_POST['name'] ?? '');
        $department = (string) ($_POST['department'] ?? '');
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($newPassword !== '' || $confirmPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                throw new DomainException('รหัสผ่านใหม่และยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
            }
            auth_update_profile($currentUser['id'], $name, $department, $currentPassword, $newPassword);
            inventory_flash_redirect('บันทึกข้อมูลส่วนตัวและเปลี่ยนรหัสผ่านเรียบร้อยแล้ว', 'profile.php');
        } else {
            auth_update_profile($currentUser['id'], $name, $department);
            inventory_flash_redirect('บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว', 'profile.php');
        }
    } catch (DomainException $e) {
        $notice = inventory_handle_error($e);
        $noticeType = 'error';
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page profile-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">USER PROFILE</div>
            <h1>ข้อมูลส่วนตัว<span class="heading-period">.</span></h1>
            <p>จัดการข้อมูลโปรไฟล์ ชื่อ-นามสกุล แผนกสังกัด และรหัสผ่านของคุณ</p>
        </div>
        <div class="page-kpis">
            <span class="user-chip-role role-<?= h($currentUser['role'] ?? 'staff') ?>" style="font-size:12px;padding:4px 12px;">
                สิทธิ์: <?= h(strtoupper($currentUser['role'] ?? 'staff')) ?>
            </span>
            <a class="secondary-button" href="catalog.php">กลับสู่หน้าหลัก ↗</a>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start;">
        <!-- Card 1: สรุปข้อมูลบัญชี -->
        <div class="inventory-panel" style="text-align:center;padding:28px 20px;">
            <div style="width:72px;height:72px;margin:0 auto 16px;border-radius:50%;background:#e8ede9;color:#2c4a40;display:grid;place-items:center;font-size:28px;font-weight:700;">
                <?= h(mb_substr($currentUser['name'] ?? $currentUser['username'], 0, 1, 'UTF-8')) ?>
            </div>
            <h2 style="margin:0 0 4px;font-size:18px;color:#292326;"><?= h($currentUser['name']) ?></h2>
            <p style="margin:0 0 12px;font-size:12px;color:#777;">@<?= h($currentUser['username']) ?></p>
            <div style="display:inline-block;margin-bottom:20px;">
                <span class="user-chip-role role-<?= h($currentUser['role'] ?? 'staff') ?>" style="font-size:11px;padding:3px 10px;">
                    <?= h(strtoupper($currentUser['role'] ?? 'staff')) ?>
                </span>
            </div>

            <div style="border-top:1px solid #edf0eb;padding-top:16px;text-align:left;font-size:12px;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">แผนก / สังกัด:</span>
                    <strong style="color:#292326;"><?= h($currentUser['department'] !== '' ? $currentUser['department'] : 'ไม่ระบุ') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">สถานะบัญชี:</span>
                    <strong style="color:#2e7d32;"><?= ($currentUser['status'] ?? 'active') === 'active' ? 'ใช้งานปกติ' : 'ระงับการใช้งาน' ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">วันที่สร้าง:</span>
                    <strong style="color:#292326;"><?= h(substr((string)($currentUser['created_at'] ?? ''), 0, 10)) ?></strong>
                </div>
            </div>
        </div>

        <!-- Card 2: ฟอร์มแก้ไขข้อมูล -->
        <div class="inventory-panel">
            <div class="panel-heading">
                <div>
                    <h2>แก้ไขข้อมูลส่วนตัว</h2>
                    <p>ระดับ Admin และ Staff สามารถแก้ไขชื่อ แผนก และเปลี่ยนรหัสผ่านได้</p>
                </div>
            </div>

            <?php if (!$canEdit): ?>
                <div style="padding:16px 20px;background:#fbfaf5;border:1px solid #e8e5d9;border-radius:8px;color:#71674e;font-size:12px;line-height:1.7;">
                    <strong>ข้อความแจ้งเตือน:</strong><br>
                    บัญชีของคุณมีสิทธิ์ระดับ <strong>Viewer</strong> (สำหรับดูสต๊อกและตำแหน่งจัดเก็บเท่านั้น) จึงไม่สามารถแก้ไขข้อมูลส่วนตัวได้ หากต้องการแก้ไขกรุณาติดต่อผู้ดูแลระบบ (Admin)
                </div>
            <?php else: ?>
                <form class="inventory-form" method="post" style="grid-template-columns:1fr 1fr;gap:16px;">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <label>
                        ชื่อผู้ใช้ (Username)
                        <input type="text" value="<?= h($currentUser['username']) ?>" disabled style="background:#f4f5f2;color:#666;">
                        <small style="color:#888;font-size:9px;">ชื่อผู้ใช้ไม่สามารถเปลี่ยนแปลงได้</small>
                    </label>

                    <label>
                        บทบาท (Role)
                        <input type="text" value="<?= h(strtoupper($currentUser['role'])) ?>" disabled style="background:#f4f5f2;color:#666;">
                        <small style="color:#888;font-size:9px;">กำหนดโดยผู้ดูแลระบบ</small>
                    </label>

                    <label class="form-span-2">
                        ชื่อ-นามสกุล *
                        <input type="text" name="name" required maxlength="150" value="<?= h((string)($_POST['name'] ?? $currentUser['name'])) ?>" placeholder="เช่น สมชาย ใจดี">
                    </label>

                    <label class="form-span-2">
                        แผนก / สังกัด
                        <input type="text" name="department" maxlength="150" value="<?= h((string)($_POST['department'] ?? $currentUser['department'])) ?>" placeholder="เช่น คลังสินค้า, ฝ่ายซ่อมบำรุง">
                    </label>

                    <div class="form-span-2" style="border-top:1px solid #edf0eb;margin-top:8px;padding-top:16px;">
                        <h3 style="margin:0 0 6px;font-size:13px;color:#292326;">เปลี่ยนรหัสผ่าน</h3>
                        <p style="margin:0 0 14px;font-size:11px;color:#888;">หากไม่ต้องการเปลี่ยนรหัสผ่าน ให้เว้นว่าง 3 ช่องด้านล่างนี้ไว้</p>
                    </div>

                    <label class="form-span-2">
                        รหัสผ่านเดิม (Current Password)
                        <input type="password" name="current_password" placeholder="กรอกรหัสผ่านเดิมเพื่อยืนยันการเปลี่ยน">
                    </label>

                    <label>
                        รหัสผ่านใหม่ (New Password)
                        <input type="password" name="new_password" minlength="4" placeholder="อย่างน้อย 4 ตัวอักษร">
                    </label>

                    <label>
                        ยืนยันรหัสผ่านใหม่ (Confirm Password)
                        <input type="password" name="confirm_password" minlength="4" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง">
                    </label>

                    <div class="form-actions form-span-2" style="justify-content:flex-end;gap:10px;margin-top:10px;">
                        <a class="secondary-button" href="catalog.php" style="min-height:38px;">ยกเลิก</a>
                        <button class="primary-button" type="submit" style="min-height:38px;padding:0 24px;font-size:12px;">บันทึกข้อมูลส่วนตัว</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

