<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';

echo "=== Setting up Local Database (Laragon MySQL) ===\n";

try {
    $db = inventory_db();
    echo "1. Connected to MySQL database 'store' successfully.\n";

    // Ensure schema
    inventory_ensure_schema($db);
    echo "2. Database schema ensured.\n";

    // Ensure Admin account
    $stmt = $db->prepare('SELECT id, username FROM users WHERE username = :username');
    $stmt->execute(['username' => 'admin']);
    $admin = $stmt->fetch();

    $hash = password_hash('admin1234', PASSWORD_DEFAULT);

    if ($admin) {
        $stmt = $db->prepare('UPDATE users SET password_hash = :hash, name = :name, role = "admin", status = "active" WHERE username = "admin"');
        $stmt->execute([
            'hash' => $hash,
            'name' => 'ผู้ดูแลระบบ',
        ]);
        echo "3. Admin account updated: username = 'admin', password = 'admin1234'\n";
    } else {
        $stmt = $db->prepare('INSERT INTO users (username, password_hash, name, role, department, status) VALUES (:u, :p, :n, "admin", "คลังสินค้าส่วนกลาง", "active")');
        $stmt->execute([
            'u' => 'admin',
            'p' => $hash,
            'n' => 'ผู้ดูแลระบบ',
        ]);
        echo "3. Admin account created: username = 'admin', password = 'admin1234'\n";
    }

    // Add Staff account for testing
    $stmt = $db->prepare('SELECT id FROM users WHERE username = "staff"');
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $db->prepare('INSERT INTO users (username, password_hash, name, role, department, status) VALUES ("staff", :p, "สมชาย พนักงานคลัง", "staff", "แผนกจัดเก็บสินค้า", "active")');
        $stmt->execute(['p' => password_hash('staff1234', PASSWORD_DEFAULT)]);
        echo "4. Staff account created: username = 'staff', password = 'staff1234'\n";
    }

    // Add Viewer account for testing
    $stmt = $db->prepare('SELECT id FROM users WHERE username = "viewer"');
    $stmt->execute();
    if (!$stmt->fetch()) {
        $stmt = $db->prepare('INSERT INTO users (username, password_hash, name, role, department, status) VALUES ("viewer", :p, "สมศรี แผนกผลิต", "viewer", "ฝ่ายผลิต", "active")');
        $stmt->execute(['p' => password_hash('viewer1234', PASSWORD_DEFAULT)]);
        echo "5. Viewer account created: username = 'viewer', password = 'viewer1234'\n";
    }

    // Check products
    $productCount = (int) $db->query('SELECT COUNT(*) FROM product')->fetchColumn();
    echo "6. Products in database: {$productCount} items.\n";

    echo "\n=== ALL ACCOUNTS READY ===\n";
    echo "Admin  : admin  / admin1234\n";
    echo "Staff  : staff  / staff1234\n";
    echo "Viewer : viewer / viewer1234\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
