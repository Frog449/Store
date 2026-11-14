<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

auth_require_login();

$pageTitle = 'ข้อมูลส่วนตัว (Profile) | Store Automeation';
$pageDescription = 'จัดการและแก้ไขข้อมูลโปรไฟล์ ชื่อผู้ใช้ อีเมล ชื่อ-นามสกุล และรหัสผ่านของคุณ';
$activePage = 'profile';

$currentUser = auth_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    try {
        inventory_require_csrf();

        $name = trim((string) ($_POST['name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $department = (string) ($currentUser['department'] ?? '');
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($newPassword !== '' || $confirmPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                throw new DomainException('รหัสผ่านใหม่และยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
            }
            auth_update_profile(
                $currentUser['id'],
                $username,
                $name,
                $email !== '' ? $email : null,
                $department,
                $currentPassword,
                $newPassword,
                $employeeId !== '' ? $employeeId : null
            );
            inventory_flash_redirect('บันทึกข้อมูลส่วนตัวและเปลี่ยนรหัสผ่านเรียบร้อยแล้ว', 'profile.php');
        } else {
            auth_update_profile(
                $currentUser['id'],
                $username,
                $name,
                $email !== '' ? $email : null,
                $department,
                null,
                null,
                $employeeId !== '' ? $employeeId : null
            );
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
            <p>จัดการข้อมูลโปรไฟล์ ชื่อผู้ใช้ อีเมล ชื่อ-นามสกุล แผนกสังกัด และรหัสผ่านของคุณ</p>
        </div>
        <div class="page-kpis">
            <span class="user-chip-role role-<?= h($currentUser['role'] ?? 'staff') ?>"
                style="font-size:12px;padding:4px 12px;">
                สิทธิ์: <?= h(strtoupper($currentUser['role'] ?? 'staff')) ?>
            </span>
            <a class="secondary-button" href="catalog.php">กลับสู่หน้าหลัก ↗</a>
        </div>
    </div>

    <div class="profile-layout-grid">
        <!-- Card 1: สรุปข้อมูลบัญชี -->
        <div class="inventory-panel" style="text-align:center;padding:28px 20px;">
            <div
                style="width:72px;height:72px;margin:0 auto 16px;border-radius:50%;background:#e0ecfb;color:#0758b4;display:grid;place-items:center;font-size:28px;font-weight:700;">
                <?= h(mb_substr($currentUser['name'] ?? $currentUser['username'], 0, 1, 'UTF-8')) ?>
            </div>
            <h2 style="margin:0 0 4px;font-size:18px;color:#1e293b;"><?= h($currentUser['name']) ?></h2>
            <p style="margin:0 0 4px;font-size:12px;color:#64748b;">@<?= h($currentUser['username']) ?></p>
            <?php if (!empty($currentUser['email'])): ?>
                <p style="margin:0 0 12px;font-size:11px;color:#0758b4;"><?= h($currentUser['email']) ?></p>
            <?php else: ?>
                <div style="margin-bottom:12px;"></div>
            <?php endif; ?>
            <div style="display:inline-block;margin-bottom:20px;">
                <span class="user-chip-role role-<?= h($currentUser['role'] ?? 'staff') ?>"
                    style="font-size:11px;padding:3px 10px;">
                    <?= h(strtoupper($currentUser['role'] ?? 'staff')) ?>
                </span>
            </div>

            <div style="border-top:1px solid #edf0eb;padding-top:16px;text-align:left;font-size:12px;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">รหัสพนักงาน:</span>
                    <strong style="color:#292326;"><?= (int) $currentUser['id'] ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">อีเมล:</span>
                    <strong style="color:#292326;"><?= h(!empty($currentUser['email']) ? $currentUser['email'] : 'ไม่ระบุ') ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">สถานะบัญชี:</span>
                    <strong
                        style="color:#2e7d32;"><?= ($currentUser['status'] ?? 'active') === 'active' ? 'ใช้งานปกติ' : 'ระงับการใช้งาน' ?></strong>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#777;">วันที่สร้าง:</span>
                    <strong
                        style="color:#292326;"><?= h(substr((string) ($currentUser['created_at'] ?? ''), 0, 10)) ?></strong>
                </div>
            </div>
        </div>

        <!-- Card 2: ฟอร์มแก้ไขข้อมูล -->
        <div class="inventory-panel">
            <div class="panel-heading">
                <div>
                    <h2>แก้ไขข้อมูลส่วนตัว</h2>
                    <p>สามารถแก้ไขชื่อผู้ใช้ ชื่อ-นามสกุล อีเมล และเปลี่ยนรหัสผ่านได้ (ยกเว้นบทบาทกำหนดโดยผู้ดูแลระบบ)</p>
                </div>
            </div>

            <form class="inventory-form profile-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_profile">

                <label>
                    ชื่อผู้ใช้ (Username)
                    <input type="text" name="username" required pattern="[a-zA-Z0-9_.-]+" maxlength="60"
                        value="<?= h((string) ($_POST['username'] ?? $currentUser['username'])) ?>"
                        placeholder="เช่น somchai_k">
                    <small style="color:#888;font-size:9px;">ใช้สำหรับเข้าสู่ระบบ (ภาษาอังกฤษ ตัวเลข ขีด)</small>
                </label>

                <label>
                    รหัสพนักงาน (ID)
                    <input type="text" name="employee_id" required inputmode="numeric" pattern="[0-9]+" maxlength="20"
                        value="<?= h((string) ($_POST['employee_id'] ?? $currentUser['id'])) ?>"
                        placeholder="เช่น 623382">
                    <small style="color:#888;font-size:9px;">ตัวเลขเท่านั้น ใช้เข้าสู่ระบบแทน Username หรือ Email ได้</small>
                </label>

                <label>
                    ชื่อ-นามสกุล
                    <input type="text" name="name" required maxlength="150"
                        value="<?= h((string) ($_POST['name'] ?? $currentUser['name'])) ?>"
                        placeholder="เช่น นายสมชาย ใจดี">
                </label>

                <label>
                    บทบาท (Role)
                    <input type="text" value="<?= h(strtoupper($currentUser['role'])) ?>" disabled readonly
                        style="background:#f4f5f2;color:#666;cursor:not-allowed;">
                    <small style="color:#888;font-size:9px;">กำหนดโดยผู้ดูแลระบบ (ไม่สามารถเปลี่ยนแปลงบทบาทในหน้าโปรไฟล์ได้)</small>
                </label>

                <label class="form-span-2">
                    อีเมล (Email)
                    <input type="email" name="email" maxlength="191"
                        value="<?= h((string) ($_POST['email'] ?? ($currentUser['email'] ?? ''))) ?>"
                        placeholder="เช่น somchai@example.com">
                    <small style="color:#888;font-size:9px;">ใช้สำหรับเข้าสู่ระบบแทน Username ได้</small>
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
                    <button class="primary-button" type="submit"
                        style="min-height:38px;padding:0 24px;font-size:12px;">บันทึกข้อมูลส่วนตัว</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>