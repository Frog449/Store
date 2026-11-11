<?php
declare(strict_types=1);

function auth_user(): ?array
{
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    if (empty($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
        return null;
    }

    try {
        $stmt = inventory_db()->prepare(
            'SELECT id, username, name, role, department, status, created_at
             FROM `users`
             WHERE id = :id'
        );
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user === false || ($user['status'] ?? 'active') === 'suspended') {
            unset($_SESSION['user_id']);
            return null;
        }

        $cachedUser = [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'name' => (string) $user['name'],
            'role' => (string) $user['role'],
            'department' => (string) $user['department'],
            'status' => (string) ($user['status'] ?? 'active'),
            'created_at' => (string) $user['created_at'],
        ];
        return $cachedUser;
    } catch (Throwable $e) {
        error_log('Auth error: ' . $e->getMessage());
        return null;
    }
}

function auth_require_login(): array
{
    $user = auth_user();
    if ($user === null) {
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'index.php';
        if (!str_contains($requestUri, 'login.php') && !str_contains($requestUri, 'logout.php')) {
            $_SESSION['redirect_after_login'] = $requestUri;
        }
        header('Location: login.php');
        exit;
    }
    return $user;
}

function auth_require_role(string ...$allowedRoles): array
{
    $user = auth_require_login();
    if (!in_array($user['role'], $allowedRoles, true)) {
        inventory_flash_redirect('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะ: ' . implode(', ', $allowedRoles) . ')', 'index.php', 'error');
    }
    return $user;
}

function auth_login(string $username, string $password): bool
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return false;
    }

    try {
        $stmt = inventory_db()->prepare(
            'SELECT id, username, name, password_hash, role, department, status
             FROM `users`
             WHERE username = :username'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, (string) $user['password_hash'])) {
            if (($user['status'] ?? 'active') === 'suspended') {
                audit_log('login_blocked', $username, 'เข้าสู่ระบบไม่สำเร็จ: บัญชีถูกระงับการใช้งาน');
                throw new DomainException('บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อหัวหน้าคลังสินค้าหรือผู้ดูแลระบบ');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            audit_log('login', $user['username'], 'เข้าสู่ระบบสำเร็จ (IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-') . ')');
            return true;
        }

        audit_log('login_failed', $username, 'เข้าสู่ระบบไม่สำเร็จ (รหัสผ่านไม่ถูกต้อง)');
        return false;
    } catch (DomainException $e) {
        throw $e;
    } catch (Throwable $e) {
        error_log('Login error: ' . $e->getMessage());
        return false;
    }
}

function auth_logout(): void
{
    $user = auth_user();
    if ($user !== null) {
        audit_log('logout', $user['username'], 'ออกจากระบบ');
    }
    unset($_SESSION['user_id']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function auth_is_admin(): bool
{
    $user = auth_user();
    return $user !== null && $user['role'] === 'admin';
}

function auth_can_manage_stock(): bool
{
    $user = auth_user();
    return $user !== null && in_array($user['role'], ['admin', 'staff'], true);
}

function auth_is_staff(): bool
{
    $user = auth_user();
    return $user !== null && $user['role'] === 'staff';
}

function auth_is_viewer(): bool
{
    $user = auth_user();
    return $user !== null && $user['role'] === 'viewer';
}

function inventory_users(): array
{
    $stmt = inventory_db()->query(
        'SELECT id, username, name, role, department, status, created_at
         FROM `users`
         ORDER BY id ASC'
    );
    return $stmt->fetchAll();
}

function inventory_user_by_id(int $id): ?array
{
    $stmt = inventory_db()->prepare(
        'SELECT id, username, name, role, department, status, created_at
         FROM `users`
         WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function inventory_create_user(string $username, string $password, string $name, string $role, string $department, string $status = 'active'): int
{
    $username = trim($username);
    $name = trim($name);
    $department = trim($department);
    $status = in_array($status, ['active', 'suspended'], true) ? $status : 'active';

    if ($username === '' || strlen($username) > 60) {
        throw new DomainException('ชื่อผู้ใช้ต้องมีความยาว 1-60 ตัวอักษร');
    }
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        throw new DomainException('ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข หรือขีด (- _ .)');
    }
    if (strlen($password) < 4) {
        throw new DomainException('รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร');
    }
    if ($name === '') {
        throw new DomainException('กรุณาระบุชื่อ-นามสกุล');
    }
    if (!in_array($role, ['admin', 'staff', 'viewer'], true)) {
        throw new DomainException('บทบาทไม่ถูกต้อง');
    }

    $db = inventory_db();
    $check = $db->prepare('SELECT COUNT(*) FROM `users` WHERE username = :username');
    $check->execute(['username' => $username]);
    if ((int) $check->fetchColumn() > 0) {
        throw new DomainException('ชื่อผู้ใช้นี้ถูกใช้งานแล้ว');
    }

    $stmt = $db->prepare(
        'INSERT INTO `users` (`username`, `password_hash`, `name`, `role`, `department`, `status`)
         VALUES (:username, :password_hash, :name, :role, :department, :status)'
    );
    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'role' => $role,
        'department' => $department,
        'status' => $status,
    ]);

    $newId = (int) $db->lastInsertId();
    audit_log('user_create', $username, "เพิ่มผู้ใช้ใหม่: {$name} ({$role}) แผนก {$department} สถานะ {$status}");
    return $newId;
}

function inventory_update_user(int $id, string $name, string $role, string $department, ?string $newPassword = null, string $status = 'active'): void
{
    $name = trim($name);
    $department = trim($department);
    $status = in_array($status, ['active', 'suspended'], true) ? $status : 'active';

    if ($name === '') {
        throw new DomainException('กรุณาระบุชื่อ-นามสกุล');
    }
    if (!in_array($role, ['admin', 'staff', 'viewer'], true)) {
        throw new DomainException('บทบาทไม่ถูกต้อง');
    }

    $currentUser = auth_user();
    if ($currentUser && $currentUser['id'] === $id) {
        if ($role !== 'admin') {
            throw new DomainException('ไม่สามารถเปลี่ยนบทบาทของตัวเองออกจาก Admin ได้');
        }
        if ($status === 'suspended') {
            throw new DomainException('ไม่สามารถระงับบัญชีของตนเองได้');
        }
    }

    $db = inventory_db();
    if ($newPassword !== null && trim($newPassword) !== '') {
        if (strlen($newPassword) < 4) {
            throw new DomainException('รหัสผ่านใหม่ต้องมีอย่างน้อย 4 ตัวอักษร');
        }
        $stmt = $db->prepare(
            'UPDATE `users`
             SET `name` = :name, `role` = :role, `department` = :department, `status` = :status, `password_hash` = :hash
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'department' => $department,
            'status' => $status,
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
        audit_log('user_update', "user#{$id}", "แก้ไขข้อมูลและเปลี่ยนรหัสผ่านผู้ใช้: {$name} ({$role}) สถานะ: {$status}");
    } else {
        $stmt = $db->prepare(
            'UPDATE `users`
             SET `name` = :name, `role` = :role, `department` = :department, `status` = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'department' => $department,
            'status' => $status,
        ]);
        audit_log('user_update', "user#{$id}", "แก้ไขข้อมูลผู้ใช้: {$name} ({$role}) สถานะ: {$status}");
    }
}

function inventory_toggle_user_status(int $id): string
{
    $currentUser = auth_user();
    if ($currentUser && $currentUser['id'] === $id) {
        throw new DomainException('ไม่สามารถเปลี่ยนสถานะระงับบัญชีของตนเองได้');
    }

    $user = inventory_user_by_id($id);
    if ($user === null) {
        throw new DomainException('ไม่พบผู้ใช้ที่ระบุ');
    }

    $newStatus = ($user['status'] ?? 'active') === 'suspended' ? 'active' : 'suspended';

    // If trying to suspend admin, check if last active admin
    if ($newStatus === 'suspended' && $user['role'] === 'admin') {
        $db = inventory_db();
        $activeAdminCount = (int) $db->query('SELECT COUNT(*) FROM `users` WHERE role = "admin" AND (status IS NULL OR status = "active")')->fetchColumn();
        if ($activeAdminCount <= 1) {
            throw new DomainException('ไม่สามารถระงับ Admin คนสุดท้ายที่เปิดใช้งานอยู่ได้');
        }
    }

    $stmt = inventory_db()->prepare('UPDATE `users` SET `status` = :status WHERE id = :id');
    $stmt->execute(['id' => $id, 'status' => $newStatus]);

    $actionName = $newStatus === 'suspended' ? 'ระงับการใช้งาน' : 'เปิดใช้งาน';
    audit_log('user_status_change', $user['username'], "เปลี่ยนสถานะบัญชี {$user['username']} เป็น {$actionName}");

    return $newStatus;
}

function inventory_delete_user(int $id): void
{
    $currentUser = auth_user();
    if ($currentUser && $currentUser['id'] === $id) {
        throw new DomainException('ไม่สามารถลบบัญชีของตัวเองได้');
    }

    $user = inventory_user_by_id($id);
    if ($user === null) {
        throw new DomainException('ไม่พบผู้ใช้ที่ต้องการลบ');
    }

    // Check if last admin
    if ($user['role'] === 'admin') {
        $db = inventory_db();
        $adminCount = (int) $db->query('SELECT COUNT(*) FROM `users` WHERE role = "admin"')->fetchColumn();
        if ($adminCount <= 1) {
            throw new DomainException('ไม่สามารถลบ Admin คนสุดท้ายของระบบได้');
        }
    }

    $stmt = inventory_db()->prepare('DELETE FROM `users` WHERE id = :id');
    $stmt->execute(['id' => $id]);
    audit_log('user_delete', $user['username'], "ลบผู้ใช้: {$user['name']} ({$user['username']})");
}

function auth_update_profile(int $userId, string $name, string $department, ?string $currentPassword = null, ?string $newPassword = null): void
{
    $name = trim($name);
    $department = trim($department);

    if ($name === '') {
        throw new DomainException('กรุณาระบุชื่อ-นามสกุล');
    }

    $user = inventory_user_by_id($userId);
    if ($user === null) {
        throw new DomainException('ไม่พบข้อมูลผู้ใช้');
    }

    if (!in_array($user['role'], ['admin', 'staff'], true)) {
        throw new DomainException('สิทธิ์ Viewer ไม่สามารถแก้ไขข้อมูลส่วนตัวได้');
    }

    $db = inventory_db();

    if ($newPassword !== null && $newPassword !== '') {
        if ($currentPassword === null || $currentPassword === '') {
            throw new DomainException('กรุณาระบุรหัสผ่านเดิมเพื่อยืนยันการเปลี่ยนรหัสผ่าน');
        }
        $stmt = $db->prepare('SELECT password_hash FROM `users` WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $existingHash = (string) $stmt->fetchColumn();

        if (!password_verify($currentPassword, $existingHash)) {
            throw new DomainException('รหัสผ่านเดิมไม่ถูกต้อง');
        }

        if (strlen($newPassword) < 4) {
            throw new DomainException('รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร');
        }

        $stmt = $db->prepare(
            'UPDATE `users`
             SET `name` = :name, `department` = :department, `password_hash` = :hash
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'name' => $name,
            'department' => $department,
            'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
        audit_log('profile_update', $user['username'], "แก้ไขโปรไฟล์และเปลี่ยนรหัสผ่าน: {$name}");
    } else {
        $stmt = $db->prepare(
            'UPDATE `users`
             SET `name` = :name, `department` = :department
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'name' => $name,
            'department' => $department,
        ]);
        audit_log('profile_update', $user['username'], "แก้ไขข้อมูลโปรไฟล์: {$name}");
    }
}

