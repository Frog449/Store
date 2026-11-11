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
            $password = (string) ($_POST['password'] ?? '');
            $name = (string) ($_POST['name'] ?? '');
            $role = (string) ($_POST['role'] ?? 'staff');
            $department = (string) ($_POST['department'] ?? '');

            inventory_create_user($username, $password, $name, $role, $department);
            inventory_flash_redirect('เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว', 'users.php');
        } elseif ($action === 'update_user') {
            $id = (int) ($_POST['user_id'] ?? 0);
            $name = (string) ($_POST['name'] ?? '');
            $role = (string) ($_POST['role'] ?? 'staff');
            $department = (string) ($_POST['department'] ?? '');
            $newPassword = isset($_POST['password']) && trim((string) $_POST['password']) !== '' ? (string) $_POST['password'] : null;

            inventory_update_user($id, $name, $role, $department, $newPassword);
            inventory_flash_redirect('บันทึกการแก้ไขข้อมูลผู้ใช้แล้ว', 'users.php');
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
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>บทบาท (Role)</th>
                        <th>แผนก / สังกัด</th>
                        <th>วันที่สร้าง</th>
                        <th style="text-align:right;">การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userList as $u): ?>
                        <tr>
                            <td>#<?= (int) $u['id'] ?></td>
                            <td><strong><?= h($u['username']) ?></strong></td>
                            <td><?= h($u['name']) ?></td>
                            <td>
                                <span class="user-chip-role role-<?= h($u['role']) ?>">
                                    <?= h(strtoupper($u['role'])) ?>
                                </span>
                            </td>
                            <td><?= h($u['department'] !== '' ? $u['department'] : '-') ?></td>
                            <td><?= h(substr((string) $u['created_at'], 0, 10)) ?></td>
                            <td style="text-align:right;">
                                <button class="secondary-button" type="button" style="padding:4px 10px;font-size:10px;"
                                    onclick="editUser(<?= (int) $u['id'] ?>, '<?= h(addslashes($u['username'])) ?>', '<?= h(addslashes($u['name'])) ?>', '<?= h(addslashes($u['role'])) ?>', '<?= h(addslashes($u['department'])) ?>')">
                                    แก้ไข
                                </button>
                                <?php if ($currentUser && $currentUser['id'] !== (int) $u['id']): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันที่จะลบผู้ใช้นี้หรือไม่?')">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                        <button class="danger-button" type="submit" style="padding:4px 10px;font-size:10px;">ลบ</button>
                                    </form>
                                <?php endif; ?>
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
                <input type="text" id="edit-username" disabled style="background:#f4f4f4;color:#666;">
            </label>

            <label>
                ชื่อ-นามสกุล
                <input type="text" name="name" id="edit-name" required>
            </label>

            <label>
                บทบาท (Role)
                <select name="role" id="edit-role" required>
                    <option value="staff">Staff (บันทึกรับ-เบิกสินค้าได้)</option>
                    <option value="viewer">Viewer (ดูข้อมูลได้อย่างเดียว)</option>
                    <option value="admin">Admin (ผู้ดูแลระบบ จัดการได้ทั้งหมด)</option>
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
function editUser(id, username, name, role, department) {
    document.querySelector('#edit-user-id').value = id;
    document.querySelector('#edit-username').value = username;
    document.querySelector('#edit-name').value = name;
    document.querySelector('#edit-role').value = role;
    document.querySelector('#edit-department').value = department;
    document.querySelector('#edit-user-title').textContent = 'แก้ไขผู้ใช้: ' + username;
    document.querySelector('#edit-user-dialog').showModal();
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>

