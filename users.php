<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_role('admin');

$pageTitle = 'จัดการผู้ใช้งาน | Store Automeation';
$pageDescription = 'จัดการบัญชีผู้ใช้งาน สิทธิ์การเข้าถึง และบทบาทพนักงาน';
$activePage = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        inventory_require_csrf();

        if ($action === 'create_user') {
            $username = (string) ($_POST['username'] ?? '');
            $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $name = (string) ($_POST['name'] ?? '');
            $role = (string) ($_POST['role'] ?? 'staff');
            $department = (string) ($_POST['department'] ?? '');

            inventory_create_user(
                $username,
                $password,
                $name,
                $role,
                $department,
                'active',
                $email !== '' ? $email : null,
                $employeeId !== '' ? $employeeId : null
            );
            inventory_flash_redirect('เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว', 'users.php');
        } elseif ($action === 'update_user') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $username = trim((string) ($_POST['username'] ?? ''));
            $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $name = (string) ($_POST['name'] ?? '');
            $role = (string) ($_POST['role'] ?? 'staff');
            $department = (string) ($_POST['department'] ?? '');
            $status = (string) ($_POST['status'] ?? 'active');
            $newPassword = isset($_POST['password']) && trim((string) $_POST['password']) !== '' ? (string) $_POST['password'] : null;

            inventory_update_user(
                $id,
                $name,
                $role,
                $department,
                $newPassword,
                $status,
                $email !== '' ? $email : null,
                $username !== '' ? $username : null,
                $employeeId !== '' ? $employeeId : null
            );
            inventory_flash_redirect('บันทึกการแก้ไขข้อมูลผู้ใช้แล้ว', 'users.php');
        } elseif ($action === 'toggle_status') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $newStatus = inventory_toggle_user_status($id);
            $msg = $newStatus === 'suspended' ? 'ระงับการใช้งานบัญชีผู้ใช้เรียบร้อยแล้ว' : 'เปิดใช้งานบัญชีผู้ใช้เรียบร้อยแล้ว';
            inventory_flash_redirect($msg, 'users.php');
        } elseif ($action === 'delete_user') {
            $id = (int) ($_POST['user_id'] ?? 0);
            inventory_delete_user($id);
            inventory_flash_redirect('ลบผู้ใช้งานเรียบร้อยแล้ว', 'users.php');
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
        $noticeType = 'error';
    }
}

$userList = inventory_users();

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">USER ACCESS MANAGEMENT</div>
            <h1>จัดการผู้ใช้งาน<span class="heading-period">.</span></h1>
            <p>กำหนดสิทธิ์การเข้าใช้งานระบบคลังสินค้าสำหรับผู้ดูแล พนักงานคลัง และผู้ตรวจดู</p>
        </div>
        <div class="page-kpis">
            <span><strong><?= count($userList) ?></strong> บัญชีผู้ใช้</span>
            <button class="primary-button" type="button" onclick="document.querySelector('#add-user-dialog').showModal()">+ เพิ่มผู้ใช้ใหม่</button>
        </div>
    </div>

    <section class="inventory-panel table-panel">
        <div class="panel-heading">
            <div>
                <h2>รายชื่อผู้ใช้งานทั้งหมด</h2>
                <p>สิทธิ์การใช้งาน: Admin (จัดการได้ทั้งหมด), Staff (รับ-เบิกได้), Viewer (ดูข้อมูลได้อย่างเดียว)</p>
            </div>
        </div>

        <div class="table-scroll">
            <table class="inventory-table users-table">
                <thead>
                    <tr>
                        <th style="width:105px;">รหัสพนักงาน</th>
                        <th>ผู้ใช้งาน</th>
                        <th>อีเมล</th>
                        <th style="width:88px;">บทบาท</th>
                        <th>แผนก / สังกัด</th>
                        <th style="width:110px;">สถานะ</th>
                        <th style="width:96px;">วันที่สร้าง</th>
                        <th style="text-align:right;width:170px;">การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userList as $u): ?>
                        <?php $isSuspended = ($u['status'] ?? 'active') === 'suspended'; ?>
                        <tr>
                            <td class="code-cell">
                                <span class="user-emp-badge"><?= (int) $u['id'] ?></span>
                            </td>
                            <td>
                                <strong style="display:block;color:#1e293b;"><?= h($u['name']) ?></strong>
                                <small style="display:block;color:#64748b;font-size:11px;">@<?= h($u['username']) ?></small>
                            </td>
                            <td style="word-break:break-word;"><?= h(!empty($u['email']) ? $u['email'] : '—') ?></td>
                            <td>
                                <span class="user-chip-role role-<?= h($u['role']) ?>">
                                    <?= h(strtoupper($u['role'])) ?>
                                </span>
                            </td>
                            <td><?= h($u['department'] !== '' ? $u['department'] : '—') ?></td>
                            <td style="white-space:nowrap;">
                                <span class="user-chip-role" style="background:<?= $isSuspended ? '#fee2e2;color:#b91c1c;' : '#dcfce7;color:#15803d;' ?>">
                                    <?= $isSuspended ? 'ระงับการใช้งาน' : 'ใช้งานปกติ' ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap;font-size:11px;color:#64748b;"><?= h(substr((string) $u['created_at'], 0, 10)) ?></td>
                            <td style="text-align:right;white-space:nowrap;">
                                <div class="users-action-group">
                                    <button class="secondary-button users-action-btn" type="button"
                                        onclick="editUser(<?= (int) $u['id'] ?>, '<?= h(addslashes($u['username'])) ?>', '<?= h(addslashes($u['email'] ?? '')) ?>', '<?= h(addslashes($u['name'])) ?>', '<?= h(addslashes($u['role'])) ?>', '<?= h(addslashes($u['department'])) ?>', '<?= h(addslashes($u['status'] ?? 'active')) ?>')">
                                        แก้ไข
                                    </button>
                                    <?php if ($currentUser && $currentUser['id'] !== (int) $u['id']): ?>
                                        <form method="post" onsubmit="return confirm('ยืนยันที่จะ<?= $isSuspended ? 'ปลดการระงับ' : 'ระงับการใช้งาน' ?>ผู้ใช้นี้หรือไม่?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button class="secondary-button users-action-btn" type="submit" style="color:<?= $isSuspended ? '#15803d' : '#b91c1c' ?>;border-color:<?= $isSuspended ? '#bbf7d0' : '#fecaca' ?>;">
                                                <?= $isSuspended ? 'ปลดระงับ' : 'ระงับ' ?>
                                            </button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('ยืนยันที่จะลบผู้ใช้นี้หรือไม่?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                            <button class="danger-button users-action-btn" type="submit">ลบ</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Dialog เพิ่มผู้ใช้ใหม่ -->
    <dialog class="product-detail-dialog" id="add-user-dialog" style="max-width:500px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow">NEW USER</div>
                <h2>เพิ่มผู้ใช้งานใหม่</h2>
            </div>
            <button class="product-detail-close" type="button" onclick="document.querySelector('#add-user-dialog').close()">×</button>
        </div>
        <form class="inventory-form" method="post" style="grid-template-columns:1fr;gap:14px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_user">

            <label>
                ชื่อผู้ใช้ (Username)
                <input type="text" name="username" required pattern="[a-zA-Z0-9_.-]+" placeholder="เช่น somchai_k">
                <small style="color:#888;">ตัวอักษรภาษาอังกฤษ ตัวเลข และขีดเท่านั้น</small>
            </label>

            <label>
                รหัสพนักงาน (ID)
                <input type="text" name="employee_id" inputmode="numeric" pattern="[0-9]+" placeholder="เช่น 623384 (เว้นว่างเพื่อสร้างรหัสอัตโนมัติ)">
                <small style="color:#888;">ตัวเลขเท่านั้น ใช้เข้าสู่ระบบแทนชื่อผู้ใช้ได้</small>
            </label>

            <label>
                อีเมล (Email)
                <input type="email" name="email" placeholder="เช่น somchai@example.com (ใช้เข้าสู่ระบบได้)">
            </label>

            <label>
                รหัสผ่าน (Password)
                <input type="password" name="password" required minlength="4" placeholder="อย่างน้อย 4 ตัวอักษร">
            </label>

            <label>
                ชื่อ-นามสกุล
                <input type="text" name="name" required placeholder="เช่น นายสมชาย ใจดี">
            </label>

            <label>
                บทบาท (Role)
                <select name="role" required>
                    <option value="staff" selected>Staff (บันทึกรับ-เบิกสินค้าได้)</option>
                    <option value="viewer">Viewer (ดูข้อมูลได้อย่างเดียว)</option>
                    <option value="admin">Admin (ผู้ดูแลระบบ จัดการได้ทั้งหมด)</option>
                </select>
            </label>

            <label>
                แผนก / หน่วยงาน
                <input type="text" name="department" placeholder="เช่น ซ่อมบำรุง, คลังสินค้า">
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                <button class="secondary-button" type="button" onclick="document.querySelector('#add-user-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit">บันทึกผู้ใช้ใหม่</button>
            </div>
        </form>
    </dialog>

    <!-- Dialog แก้ไขผู้ใช้ -->
    <dialog class="product-detail-dialog" id="edit-user-dialog" style="max-width:500px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow">EDIT USER</div>
                <h2 id="edit-user-title">แก้ไขข้อมูลผู้ใช้</h2>
            </div>
            <button class="product-detail-close" type="button" onclick="document.querySelector('#edit-user-dialog').close()">×</button>
        </div>
        <form class="inventory-form" method="post" id="edit-user-form" style="grid-template-columns:1fr;gap:14px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" id="edit-user-id" value="">

            <label>
                ชื่อผู้ใช้ (Username)
                <input type="text" name="username" id="edit-username" required pattern="[a-zA-Z0-9_.-]+">
                <small style="color:#888;">ตัวอักษรภาษาอังกฤษ ตัวเลข และขีดเท่านั้น</small>
            </label>

            <label>
                รหัสพนักงาน (ID)
                <input type="text" name="employee_id" id="edit-employee-id" required inputmode="numeric" pattern="[0-9]+" placeholder="เช่น 623382">
                <small style="color:#888;">ตัวเลขเท่านั้น ใช้เข้าสู่ระบบแทนชื่อผู้ใช้ได้</small>
            </label>

            <label>
                อีเมล (Email)
                <input type="email" name="email" id="edit-email" placeholder="เช่น name@example.com (ใช้เข้าสู่ระบบได้)">
            </label>

            <label>
                ชื่อ-นามสกุล
                <input type="text" name="name" id="edit-name" required>
            </label>

            <label>
                บทบาท (Role)
                <select name="role" id="edit-role" required>
                    <option value="staff">Staff (พนักงานคลังสินค้า: รับ-เบิกสินค้าได้)</option>
                    <option value="viewer">Viewer (พนักงานทั่วไป: ดูสต๊อก/ตำแหน่งจัดเก็บได้อย่างเดียว)</option>
                    <option value="admin">Admin (ผู้ดูแลระบบ: จัดการได้ทั้งหมด)</option>
                </select>
            </label>

            <label>
                สถานะบัญชี
                <select name="status" id="edit-status" required>
                    <option value="active">ใช้งานปกติ (Active)</option>
                    <option value="suspended">ระงับการใช้งาน (Suspended)</option>
                </select>
            </label>

            <label>
                แผนก / หน่วยงาน
                <input type="text" name="department" id="edit-department">
            </label>

            <label>
                เปลี่ยนรหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)
                <input type="password" name="password" minlength="4" placeholder="กรอกรหัสผ่านใหม่...">
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;">
                <button class="secondary-button" type="button" onclick="document.querySelector('#edit-user-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit">บันทึกการแก้ไข</button>
            </div>
        </form>
    </dialog>
</section>

<script>
function editUser(id, username, email, name, role, department, status) {
    document.querySelector('#edit-user-id').value = id;
    document.querySelector('#edit-username').value = username;
    document.querySelector('#edit-employee-id').value = id || '';
    document.querySelector('#edit-email').value = email || '';
    document.querySelector('#edit-name').value = name;
    document.querySelector('#edit-role').value = role;
    document.querySelector('#edit-department').value = department;
    document.querySelector('#edit-status').value = status || 'active';
    document.querySelector('#edit-user-title').textContent = 'แก้ไขผู้ใช้: ' + username;
    document.querySelector('#edit-user-dialog').showModal();
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

