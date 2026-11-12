<?php
declare(strict_types=1);

function inventory_db(): PDO
{
    static $db;
    if ($db instanceof PDO) {
        return $db;
    }

    $config = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['database']
    );
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (!empty($config['ssl'])) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $options[PDO::MYSQL_ATTR_SSL_CA] = true;
    }
    try {
        $db = new PDO($dsn, $config['username'], $config['password'], $options);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Unknown database')) {
            $serverDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']);
            $serverDb = new PDO($serverDsn, $config['username'], $config['password'], $options);
            $serverDb->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db = new PDO($dsn, $config['username'], $config['password'], $options);
        } else {
            throw $e;
        }
    }
    inventory_ensure_schema($db);

    $count = (int) $db->query('SELECT COUNT(*) FROM `product`')->fetchColumn();
    if ($count === 0) {
        $sqlitePath = __DIR__ . '/../storage/inventory.sqlite';
        if (is_file($sqlitePath)) {
            inventory_import_sqlite($db, $sqlitePath);
        } else {
            inventory_seed_defaults($db);
        }
    }

    return $db;
}

function inventory_ensure_schema(PDO $db): void
{
    if (!inventory_table_exists($db, 'product')) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `product` (
            `รหัสสินค้า` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ชื่อสินค้า` TEXT NOT NULL,
            `หมวดหมู่` VARCHAR(100) NOT NULL,
            `ประเภท` VARCHAR(100) NOT NULL,
            `หน่วยนับ` VARCHAR(40) NOT NULL,
            `ตำแหน่งจัดเก็บ` VARCHAR(80) NOT NULL DEFAULT \'\',
            `จุดสั่งซื้อขั้นต่ำ` DECIMAL(14,3) NOT NULL DEFAULT 0,
            `รับเข้ารวม` DECIMAL(14,3) NOT NULL DEFAULT 0,
            `เบิกออกรวม` DECIMAL(14,3) NOT NULL DEFAULT 0,
            `คงเหลือ` DECIMAL(14,3) NOT NULL DEFAULT 0,
            `สถานะสต๊อก` VARCHAR(30) NOT NULL DEFAULT \'ปกติ\',
            `code` VARCHAR(80) NULL,
            `art` VARCHAR(32) NOT NULL DEFAULT \'coupler\',
            `image_filename` VARCHAR(80) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`รหัสสินค้า`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    $columns = inventory_table_columns($db, 'product');
    $legacyColumns = [
        'ชื่อสินค้า' => 'TEXT NOT NULL',
        'หมวดหมู่' => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
        'ประเภท' => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
        'หน่วยนับ' => 'VARCHAR(40) NOT NULL DEFAULT \'\'',
        'ตำแหน่งจัดเก็บ' => 'VARCHAR(80) NOT NULL DEFAULT \'\'',
        'จุดสั่งซื้อขั้นต่ำ' => 'DECIMAL(14,3) NOT NULL DEFAULT 0',
        'รับเข้ารวม' => 'DECIMAL(14,3) NOT NULL DEFAULT 0',
        'เบิกออกรวม' => 'DECIMAL(14,3) NOT NULL DEFAULT 0',
        'คงเหลือ' => 'DECIMAL(14,3) NOT NULL DEFAULT 0',
        'สถานะสต๊อก' => 'VARCHAR(30) NOT NULL DEFAULT \'ปกติ\'',
    ];
    foreach ($legacyColumns as $column => $definition) {
        if (!isset($columns[$column])) {
            $db->exec('ALTER TABLE `product` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    foreach ([
        'code' => 'VARCHAR(80) NULL',
        'art' => 'VARCHAR(32) NOT NULL DEFAULT \'coupler\'',
        'image_filename' => 'VARCHAR(80) NULL',
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
    ] as $column => $definition) {
        if (!isset($columns[$column])) {
            $db->exec('ALTER TABLE `product` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    $db->exec(
        'UPDATE `product`
         SET `code` = CONCAT(\'legacy-\', `รหัสสินค้า`)
         WHERE `code` IS NULL OR `code` = \'\''
    );
    if (!inventory_has_unique_index($db, 'product', 'code')) {
        $db->exec('ALTER TABLE `product` ADD UNIQUE KEY `uq_product_code_ref` (`code`)');
    }

    if (!inventory_table_exists($db, 'receipts')) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `receipts` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `movement_date` DATE NOT NULL,
            `document_no` VARCHAR(100) NOT NULL DEFAULT \'\',
            `product_code` VARCHAR(80) NOT NULL,
            `quantity` DECIMAL(14,3) NOT NULL,
            `supplier` VARCHAR(150) NOT NULL DEFAULT \'\',
            `received_by` VARCHAR(150) NOT NULL DEFAULT \'\',
            `department` VARCHAR(150) NOT NULL DEFAULT \'\',
            `notes` VARCHAR(500) NOT NULL DEFAULT \'\',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_receipts_date` (`movement_date`),
            KEY `idx_receipts_product` (`product_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }
    if (!inventory_table_exists($db, 'issues')) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `issues` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `movement_date` DATE NOT NULL,
            `document_no` VARCHAR(100) NOT NULL DEFAULT \'\',
            `product_code` VARCHAR(80) NOT NULL,
            `quantity` DECIMAL(14,3) NOT NULL,
            `requested_by` VARCHAR(150) NOT NULL DEFAULT \'\',
            `department` VARCHAR(150) NOT NULL DEFAULT \'\',
            `approved_by` VARCHAR(150) NOT NULL DEFAULT \'\',
            `notes` VARCHAR(500) NOT NULL DEFAULT \'\',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_issues_date` (`movement_date`),
            KEY `idx_issues_product` (`product_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    if (!inventory_table_exists($db, 'product_images')) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `product_images` (
            `filename` VARCHAR(80) NOT NULL,
            `mime_type` VARCHAR(32) NOT NULL,
            `data` MEDIUMBLOB NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    if (!inventory_table_exists($db, 'users')) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `users` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `employee_id` VARCHAR(50) NULL DEFAULT NULL,
            `username` VARCHAR(60) NOT NULL,
            `email` VARCHAR(191) NULL DEFAULT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `role` VARCHAR(30) NOT NULL DEFAULT \'staff\',
            `department` VARCHAR(150) NOT NULL DEFAULT \'\',
            `status` VARCHAR(20) NOT NULL DEFAULT \'active\',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_users_username` (`username`),
            UNIQUE KEY `uq_users_email` (`email`),
            UNIQUE KEY `uq_users_employee_id` (`employee_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $initialPassword = password_hash('admin1234', PASSWORD_DEFAULT);
        $stmt = $db->prepare(
            'INSERT INTO `users` (`username`, `password_hash`, `name`, `role`, `department`, `status`)
             VALUES (:username, :password_hash, :name, :role, :department, :status)'
        );
        $stmt->execute([
            'username' => 'admin',
            'password_hash' => $initialPassword,
            'name' => 'ผู้ดูแลระบบ',
            'role' => 'admin',
            'department' => 'คลังสินค้าส่วนกลาง',
            'status' => 'active',
        ]);
    }

    if (inventory_table_exists($db, 'users')) {
        $userCols = inventory_table_columns($db, 'users');
        if (!isset($userCols['employee_id'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `employee_id` VARCHAR(50) NULL DEFAULT NULL AFTER `id`");
            try {
                $db->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_employee_id` (`employee_id`)");
            } catch (Throwable $e) {
                // Ignore if index already exists
            }
        }
        if (!isset($userCols['status'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        if (!isset($userCols['email'])) {
            $db->exec("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(191) NULL DEFAULT NULL AFTER `username`");
            try {
                $db->exec("ALTER TABLE `users` ADD UNIQUE KEY `uq_users_email` (`email`)");
            } catch (Throwable $e) {
                // Ignore if index already exists
            }
        }
    }

    if (inventory_table_exists($db, 'receipts')) {
        $receiptCols = inventory_table_columns($db, 'receipts');
        if (!isset($receiptCols['created_by_user_id'])) {
            $db->exec("ALTER TABLE `receipts` ADD COLUMN `created_by_user_id` BIGINT UNSIGNED NULL");
        }
        if (!isset($receiptCols['created_by_name'])) {
            $db->exec("ALTER TABLE `receipts` ADD COLUMN `created_by_name` VARCHAR(150) NOT NULL DEFAULT ''");
        }
    }

    if (inventory_table_exists($db, 'issues')) {
        $issueCols = inventory_table_columns($db, 'issues');
        if (!isset($issueCols['created_by_user_id'])) {
            $db->exec("ALTER TABLE `issues` ADD COLUMN `created_by_user_id` BIGINT UNSIGNED NULL");
        }
        if (!isset($issueCols['created_by_name'])) {
            $db->exec("ALTER TABLE `issues` ADD COLUMN `created_by_name` VARCHAR(150) NOT NULL DEFAULT ''");
        }
    }

    if (!inventory_table_exists($db, 'audit_logs')) {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NULL,
            `username` VARCHAR(60) NOT NULL DEFAULT \'system\',
            `user_name` VARCHAR(150) NOT NULL DEFAULT \'\',
            `action` VARCHAR(50) NOT NULL,
            `target` VARCHAR(100) NOT NULL DEFAULT \'\',
            `details` TEXT NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT \'\',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_audit_created` (`created_at`),
            KEY `idx_audit_user` (`user_id`),
            KEY `idx_audit_action` (`action`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );
    }

    $legacyProducts = $db->query(
        'SELECT p.`code`, p.`รับเข้ารวม` AS received, p.`เบิกออกรวม` AS issued,
                r.total AS recorded_receipts, i.total AS recorded_issues
         FROM `product` p
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM `receipts` GROUP BY product_code) r
            ON r.product_code = p.`code`
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM `issues` GROUP BY product_code) i
            ON i.product_code = p.`code`
         WHERE p.`code` LIKE \'legacy-%\''
    )->fetchAll();
    $insertLegacyReceipt = $db->prepare(
        'INSERT INTO `receipts` (`movement_date`, `product_code`, `quantity`, `notes`)
         VALUES (CURRENT_DATE, :code, :quantity, :notes)'
    );
    $insertLegacyIssue = $db->prepare(
        'INSERT INTO `issues` (`movement_date`, `product_code`, `quantity`, `notes`)
         VALUES (CURRENT_DATE, :code, :quantity, :notes)'
    );
    foreach ($legacyProducts as $product) {
        if ($product['recorded_receipts'] === null && (float) $product['received'] > 0) {
            $insertLegacyReceipt->execute([
                'code' => $product['code'],
                'quantity' => $product['received'],
                'notes' => 'ย้ายยอดรวมเดิมจากทะเบียนสินค้า',
            ]);
        }
        if ($product['recorded_issues'] === null && (float) $product['issued'] > 0) {
            $insertLegacyIssue->execute([
                'code' => $product['code'],
                'quantity' => $product['issued'],
                'notes' => 'ย้ายยอดรวมเดิมจากทะเบียนสินค้า',
            ]);
        }
    }

    if (isset($columns['opening_stock'])) {
        inventory_migrate_opening_stock($db);
        $db->exec('ALTER TABLE `product` DROP COLUMN `opening_stock`');
    }

    // Migrate legacy Thai units ('ชิ้น', 'อัน') to 'pcs (ชิ้น)'
    $db->exec("UPDATE `product` SET `หน่วยนับ` = 'pcs (ชิ้น)' WHERE `หน่วยนับ` IN ('ชิ้น', 'อัน')");
}

function inventory_store_product_image(?array $upload): ?string
{
    if ($upload === null) {
        return null;
    }

    $error = $upload['error'] ?? null;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (!is_int($error) || $error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'รูปสินค้าต้องมีขนาดไม่เกิน 5 MB',
            UPLOAD_ERR_PARTIAL => 'อัปโหลดรูปไม่สำเร็จ กรุณาลองใหม่',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'ระบบจัดเก็บไฟล์อัปโหลดไม่พร้อม กรุณาติดต่อผู้ดูแล',
            UPLOAD_ERR_EXTENSION => 'เซิร์ฟเวอร์ปฏิเสธการอัปโหลดรูป',
            default => 'ไฟล์รูปสินค้าไม่ถูกต้อง',
        };
        if (in_array($error, [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE], true)) {
            throw new RuntimeException($message);
        }
        throw new DomainException($message);
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    $fileSize = $upload['size'] ?? null;
    if (!is_string($temporaryPath) || !is_int($fileSize) || !is_uploaded_file($temporaryPath)) {
        throw new DomainException('ไฟล์รูปสินค้าไม่ถูกต้อง');
    }
    if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new DomainException('รูปสินค้าต้องมีขนาดไม่เกิน 5 MB');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $imageInfo = @getimagesize($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (
        !is_string($mimeType)
        || !isset($extensions[$mimeType])
        || $imageInfo === false
        || ($imageInfo['mime'] ?? null) !== $mimeType
        || $imageInfo[0] * $imageInfo[1] > 20000000
    ) {
        throw new DomainException('รองรับเฉพาะรูป JPG, PNG หรือ WEBP ที่มีขนาดไม่เกิน 20 ล้านพิกเซล');
    }

    $data = file_get_contents($temporaryPath);
    if ($data === false) {
        throw new RuntimeException('ไม่สามารถอ่านไฟล์รูปสินค้าได้');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];

    $db = inventory_db();
    $statement = $db->prepare(
        'INSERT INTO `product_images` (`filename`, `mime_type`, `data`)
         VALUES (:filename, :mime_type, :data)'
    );
    $statement->bindValue(':filename', $filename, PDO::PARAM_STR);
    $statement->bindValue(':mime_type', $mimeType, PDO::PARAM_STR);
    $statement->bindValue(':data', $data, PDO::PARAM_LOB);
    $statement->execute();

    $directory = __DIR__ . '/../storage/product-images';
    if (is_dir($directory) || @mkdir($directory, 0755, true)) {
        @file_put_contents($directory . '/' . $filename, $data);
    }

    return $filename;
}

function inventory_has_custom_image(?string $filename): bool
{
    return $filename !== null && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename);
}

function inventory_product_image_url(?string $filename, ?string $category = null): string
{
    if (inventory_has_custom_image($filename)) {
        return 'image.php?file=' . rawurlencode((string) $filename);
    }
    $cat = $category !== null && trim($category) !== '' ? trim($category) : 'สินค้าทั่วไป';
    return 'image.php?cat=' . rawurlencode($cat);
}

function inventory_category_placeholder_svg(string $category): string
{
    $cleanCat = trim($category);

    if (str_contains($cleanCat, 'อะไหล่') || str_contains($cleanCat, 'เครื่องจักร')) {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <defs>
    <linearGradient id="bg_mach" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f8fafc"/>
      <stop offset="100%" stop-color="#edf2f7"/>
    </linearGradient>
    <filter id="sh_mach" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#0f172a" flood-opacity="0.12"/>
    </filter>
  </defs>
  <rect width="400" height="300" fill="url(#bg_mach)"/>
  <g opacity="0.18" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3 3">
    <line x1="0" y1="75" x2="400" y2="75"/><line x1="0" y1="150" x2="400" y2="150"/><line x1="0" y1="225" x2="400" y2="225"/>
    <line x1="100" y1="0" x2="100" y2="300"/><line x1="200" y1="0" x2="200" y2="300"/><line x1="300" y1="0" x2="300" y2="300"/>
  </g>
  <g filter="url(#sh_mach)" transform="translate(195, 122)">
    <circle cx="-16" cy="-8" r="54" fill="#cbd5e1" stroke="#94a3b8" stroke-width="2"/>
    <g fill="#94a3b8">
      <rect x="-22" y="-72" width="12" height="18" rx="3"/>
      <rect x="-22" y="36" width="12" height="18" rx="3"/>
      <rect x="-80" y="-14" width="18" height="12" rx="3"/>
      <rect x="28" y="-14" width="18" height="12" rx="3"/>
      <rect x="-63" y="-55" width="16" height="12" rx="3" transform="rotate(45 -55 -49)"/>
      <rect x="23" y="31" width="16" height="12" rx="3" transform="rotate(45 31 37)"/>
      <rect x="23" y="-55" width="16" height="12" rx="3" transform="rotate(-45 31 -49)"/>
      <rect x="-63" y="31" width="16" height="12" rx="3" transform="rotate(-45 -55 37)"/>
    </g>
    <circle cx="-16" cy="-8" r="30" fill="#f1f5f9" stroke="#94a3b8" stroke-width="2.5"/>
    <circle cx="-16" cy="-8" r="14" fill="#64748b"/>
    <circle cx="36" cy="24" r="40" fill="#ffffff" stroke="#93c5fd" stroke-width="2"/>
    <g fill="#2563eb">
      <rect x="31" y="-22" width="10" height="14" rx="2.5"/>
      <rect x="31" y="56" width="10" height="14" rx="2.5"/>
      <rect x="-10" y="19" width="14" height="10" rx="2.5"/>
      <rect x="68" y="19" width="14" height="10" rx="2.5"/>
      <rect x="2" y="-10" width="12" height="10" rx="2.5" transform="rotate(45 8 -5)"/>
      <rect x="58" y="46" width="12" height="10" rx="2.5" transform="rotate(45 64 51)"/>
      <rect x="58" y="-10" width="12" height="10" rx="2.5" transform="rotate(-45 64 -5)"/>
      <rect x="2" y="46" width="12" height="10" rx="2.5" transform="rotate(-45 8 51)"/>
    </g>
    <circle cx="36" cy="24" r="22" fill="#eff6ff" stroke="#3b82f6" stroke-width="2"/>
    <circle cx="36" cy="24" r="9" fill="#1d4ed8"/>
  </g>
  <rect x="115" y="228" width="170" height="30" rx="15" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.2" filter="url(#sh_mach)"/>
  <circle cx="132" cy="243" r="5" fill="#2563eb"/>
  <text x="146" y="247" font-family="'Noto Sans Thai', sans-serif" font-size="11.5" font-weight="600" fill="#1e293b">อะไหล่เครื่องจักร</text>
  <text x="200" y="278" text-anchor="middle" font-family="'DM Sans', sans-serif" font-size="8.5" font-weight="600" letter-spacing="1.5" fill="#64748b">SPARE PARTS</text>
</svg>
SVG;
    } elseif (str_contains($cleanCat, 'สิ้นเปลือง')) {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <defs>
    <linearGradient id="bg_cons" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fffbeb"/>
      <stop offset="100%" stop-color="#fef3c7"/>
    </linearGradient>
    <filter id="sh_cons" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#78350f" flood-opacity="0.1"/>
    </filter>
  </defs>
  <rect width="400" height="300" fill="url(#bg_cons)"/>
  <g filter="url(#sh_cons)" transform="translate(195, 120)">
    <ellipse cx="45" cy="42" rx="46" ry="16" fill="#78350f" opacity="0.15"/>
    <ellipse cx="45" cy="38" rx="42" ry="14" fill="#374151"/>
    <ellipse cx="45" cy="38" rx="26" ry="9" fill="#fef3c7"/>
    <ellipse cx="-20" cy="30" rx="58" ry="22" fill="#78350f" opacity="0.12"/>
    <path d="M-68, -10 C-68, -35 28, -35 28, -10 L28, 16 C28, 41 -68, 41 -68, 16 Z" fill="#d97706" stroke="#b45309" stroke-width="2"/>
    <ellipse cx="-20" cy="-10" rx="48" ry="20" fill="#f59e0b" stroke="#d97706" stroke-width="2"/>
    <ellipse cx="-20" cy="-10" rx="26" ry="11" fill="#78350f"/>
    <ellipse cx="-20" cy="-10" rx="21" ry="8" fill="#fffbeb"/>
    <path d="M28, 10 C48, 14 62, 28 66, 38 L48, 44 C44, 34 32, 22 18, 18 Z" fill="#fbbf24" stroke="#d97706" stroke-width="1.5"/>
  </g>
  <rect x="120" y="228" width="160" height="30" rx="15" fill="#ffffff" stroke="#fcd34d" stroke-width="1.2" filter="url(#sh_cons)"/>
  <circle cx="137" cy="243" r="5" fill="#d97706"/>
  <text x="151" y="247" font-family="'Noto Sans Thai', sans-serif" font-size="11.5" font-weight="600" fill="#78350f">วัสดุสิ้นเปลือง</text>
  <text x="200" y="278" text-anchor="middle" font-family="'DM Sans', sans-serif" font-size="8.5" font-weight="600" letter-spacing="1.5" fill="#b45309">CONSUMABLES</text>
</svg>
SVG;
    } elseif (str_contains($cleanCat, 'เครื่องมือ')) {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <defs>
    <linearGradient id="bg_tool" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f0fdfa"/>
      <stop offset="100%" stop-color="#ccfbf1"/>
    </linearGradient>
    <filter id="sh_tool" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#134e4a" flood-opacity="0.12"/>
    </filter>
  </defs>
  <rect width="400" height="300" fill="url(#bg_tool)"/>
  <g filter="url(#sh_tool)" transform="translate(200, 120)">
    <g transform="rotate(45)">
      <rect x="-8" y="10" width="16" height="58" rx="6" fill="#0f766e" stroke="#115e59" stroke-width="2"/>
      <line x1="-3" y1="20" x2="-3" y2="58" stroke="#2dd4bf" stroke-width="2"/>
      <line x1="3" y1="20" x2="3" y2="58" stroke="#134e4a" stroke-width="2"/>
      <rect x="-4" y="-48" width="8" height="58" fill="#94a3b8" stroke="#64748b" stroke-width="1.5"/>
      <polygon points="-5,-48 5,-48 2,-58 -2,-58" fill="#475569"/>
    </g>
    <g transform="rotate(-45)">
      <rect x="-8" y="-36" width="16" height="72" rx="4" fill="#cbd5e1" stroke="#64748b" stroke-width="2"/>
      <circle cx="0" cy="26" r="4.5" fill="#0f766e"/>
      <path d="M-18,-36 C-22,-48 0,-62 18,-36 C10,-42 -10,-42 -18,-36 Z" fill="#94a3b8" stroke="#64748b" stroke-width="2"/>
      <circle cx="0" cy="-44" r="15" fill="#f0fdfa" stroke="#64748b" stroke-width="2"/>
      <rect x="-7" y="-58" width="14" height="18" fill="#f0fdfa"/>
    </g>
  </g>
  <rect x="122" y="228" width="156" height="30" rx="15" fill="#ffffff" stroke="#99f6e4" stroke-width="1.2" filter="url(#sh_tool)"/>
  <circle cx="139" cy="243" r="5" fill="#0f766e"/>
  <text x="153" y="247" font-family="'Noto Sans Thai', sans-serif" font-size="11.5" font-weight="600" fill="#134e4a">เครื่องมือช่าง</text>
  <text x="200" y="278" text-anchor="middle" font-family="'DM Sans', sans-serif" font-size="8.5" font-weight="600" letter-spacing="1.5" fill="#0f766e">HARDWARE TOOLS</text>
</svg>
SVG;
    } elseif (str_contains($cleanCat, 'วัตถุดิบ')) {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <defs>
    <linearGradient id="bg_raw" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#eef2ff"/>
      <stop offset="100%" stop-color="#e0e7ff"/>
    </linearGradient>
    <filter id="sh_raw" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#312e81" flood-opacity="0.12"/>
    </filter>
  </defs>
  <rect width="400" height="300" fill="url(#bg_raw)"/>
  <g filter="url(#sh_raw)" transform="translate(195, 120)">
    <polygon points="-75,25 35,55 85,25 -25,-5" fill="#94a3b8" stroke="#64748b" stroke-width="1.5"/>
    <polygon points="-75,25 35,55 35,68 -75,38" fill="#64748b"/>
    <polygon points="35,55 85,25 85,38 35,68" fill="#475569"/>
    <ellipse cx="-20" cy="10" rx="16" ry="16" fill="#818cf8" stroke="#4f46e5" stroke-width="2"/>
    <path d="M-20,-6 L50,-24 L50,8 L-20,26 Z" fill="#a5b4fc" stroke="#4f46e5" stroke-width="1.5"/>
    <ellipse cx="50" cy="-8" rx="16" ry="16" fill="#c7d2fe" stroke="#4f46e5" stroke-width="2"/>
    <ellipse cx="-45" cy="18" rx="14" ry="14" fill="#6366f1" stroke="#4338ca" stroke-width="2"/>
    <path d="M-45,4 L25,-14 L25,14 L-45,32 Z" fill="#818cf8" stroke="#4338ca" stroke-width="1.5"/>
    <ellipse cx="25" cy="0" rx="14" ry="14" fill="#a5b4fc" stroke="#4338ca" stroke-width="2"/>
  </g>
  <rect x="135" y="228" width="130" height="30" rx="15" fill="#ffffff" stroke="#c7d2fe" stroke-width="1.2" filter="url(#sh_raw)"/>
  <circle cx="152" cy="243" r="5" fill="#4f46e5"/>
  <text x="166" y="247" font-family="'Noto Sans Thai', sans-serif" font-size="11.5" font-weight="600" fill="#312e81">วัตถุดิบ</text>
  <text x="200" y="278" text-anchor="middle" font-family="'DM Sans', sans-serif" font-size="8.5" font-weight="600" letter-spacing="1.5" fill="#4f46e5">RAW MATERIALS</text>
</svg>
SVG;
    } else {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">
  <defs>
    <linearGradient id="bg_gen" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#fff1f2"/>
      <stop offset="100%" stop-color="#ffe4e6"/>
    </linearGradient>
    <filter id="sh_gen" x="-20%" y="-20%" width="140%" height="140%">
      <feDropShadow dx="0" dy="6" stdDeviation="10" flood-color="#881337" flood-opacity="0.1"/>
    </filter>
  </defs>
  <rect width="400" height="300" fill="url(#bg_gen)"/>
  <g filter="url(#sh_gen)" transform="translate(195, 118)">
    <polygon points="-70,42 20,64 75,42 -15,20" fill="#d1d5db"/>
    <polygon points="-70,42 20,64 20,72 -70,50" fill="#9ca3af"/>
    <polygon points="20,64 75,42 75,50 20,72" fill="#6b7280"/>
    <polygon points="-55,-8 15,16 15,48 -55,24" fill="#fb7185" stroke="#e11d48" stroke-width="1.5"/>
    <polygon points="15,16 65,-6 65,26 15,48" fill="#e11d48" stroke="#be123c" stroke-width="1.5"/>
    <polygon points="-55,-8 15,16 65,-6 -5,-30" fill="#fecdd3" stroke="#e11d48" stroke-width="1.5"/>
    <polygon points="-22,-20 38,4 43,1 -17,-23" fill="#e11d48"/>
    <rect x="-42" y="5" width="28" height="14" rx="2" fill="#ffffff" transform="skewY(18)"/>
    <line x1="-38" y1="18" x2="-38" y2="28" stroke="#1e293b" stroke-width="1.5"/>
    <line x1="-34" y1="19" x2="-34" y2="29" stroke="#1e293b" stroke-width="1"/>
    <line x1="-30" y1="20" x2="-30" y2="30" stroke="#1e293b" stroke-width="2"/>
    <line x1="-24" y1="22" x2="-24" y2="32" stroke="#1e293b" stroke-width="1.5"/>
    <line x1="-18" y1="24" x2="-18" y2="34" stroke="#1e293b" stroke-width="1"/>
  </g>
  <rect x="125" y="228" width="150" height="30" rx="15" fill="#ffffff" stroke="#fecdd3" stroke-width="1.2" filter="url(#sh_gen)"/>
  <circle cx="142" cy="243" r="5" fill="#e11d48"/>
  <text x="156" y="247" font-family="'Noto Sans Thai', sans-serif" font-size="11.5" font-weight="600" fill="#881337">สินค้าทั่วไป</text>
  <text x="200" y="278" text-anchor="middle" font-family="'DM Sans', sans-serif" font-size="8.5" font-weight="600" letter-spacing="1.5" fill="#be123c">GENERAL SUPPLY</text>
</svg>
SVG;
    }
}

function inventory_delete_product_image(?string $filename): void
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
        return;
    }

    try {
        $statement = inventory_db()->prepare('DELETE FROM `product_images` WHERE `filename` = :filename');
        $statement->execute(['filename' => $filename]);
    } catch (Throwable $e) {
        error_log('Unable to remove product image from database: ' . $e->getMessage());
    }

    $path = __DIR__ . '/../storage/product-images/' . $filename;
    if (is_file($path)) {
        @unlink($path);
    }
}

function inventory_table_columns(PDO $db, string $table): array
{
    $columns = [];
    foreach ($db->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll() as $column) {
        $columns[$column['Field']] = $column;
    }
    return $columns;
}

function inventory_table_exists(PDO $db, string $table): bool
{
    $statement = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $statement->execute(['table' => $table]);
    return (int) $statement->fetchColumn() > 0;
}

function inventory_has_unique_index(PDO $db, string $table, string $column): bool
{
    $indexes = [];
    foreach ($db->query('SHOW INDEX FROM `' . $table . '`')->fetchAll() as $index) {
        $indexes[$index['Key_name']][] = $index;
    }

    foreach ($indexes as $indexColumns) {
        if (count($indexColumns) !== 1) {
            continue;
        }

        $index = $indexColumns[0];
        if (
            $index['Column_name'] === $column
            && (int) $index['Non_unique'] === 0
            && (int) $index['Seq_in_index'] === 1
            && $index['Sub_part'] === null
        ) {
            return true;
        }
    }

    return false;
}

function inventory_category_options(): array
{
    return ['อะไหล่เครื่องจักร', 'วัสดุสิ้นเปลือง', 'เครื่องมือช่าง', 'สินค้าทั่วไป', 'วัตถุดิบ', 'อื่นๆ'];
}

function inventory_type_options(): array
{
    return [...inventory_category_options(), 'อะไหล่'];
}

function inventory_unit_options(): array
{
    return ['pcs (ชิ้น)', 'Roll (ม้วน)', 'Sheet (แผ่น)', 'm. (เมตร)'];
}

function inventory_choice(string $key, string $label, array $options): string
{
    $value = inventory_text($key, $label, 100, true);
    if (!in_array($value, $options, true)) {
        throw new DomainException("กรุณาเลือก{$label}จากรายการ");
    }
    return $value;
}

function inventory_seed_defaults(PDO $db): void
{
    $products = require __DIR__ . '/../data/products.php';
    $insertProduct = $db->prepare(
        'INSERT INTO `product` (`code`, `ชื่อสินค้า`, `หมวดหมู่`, `ประเภท`, `หน่วยนับ`, `ตำแหน่งจัดเก็บ`,
                               `จุดสั่งซื้อขั้นต่ำ`, `art`)
         VALUES (:code, :name, :category, :item_type, :unit, :location, :reorder_point, :art)'
    );
    $insertReceipt = $db->prepare(
        'INSERT INTO receipts (movement_date, product_code, quantity, notes) VALUES (:date, :code, :quantity, :notes)'
    );
    $insertIssue = $db->prepare(
        'INSERT INTO issues (movement_date, product_code, quantity, notes) VALUES (:date, :code, :quantity, :notes)'
    );

    $receipts = [
        ['741116xxx202-xx', 30],
        ['741123137045-xx', 11],
        ['741123137044-xx', 5],
        ['741116xxx202-xx', 50],
    ];
    $issues = [
        ['741116xxx202-xx', 10],
        ['741123137044-xx', 10],
        ['741116xxx202-xx', 20],
        ['741116xxx202-xx', 40],
    ];

    $db->beginTransaction();
    try {
        foreach ($products as $product) {
            $insertProduct->execute([
                'code' => $product['id'],
                'name' => $product['name'],
                'category' => $product['category'],
                'item_type' => $product['type'],
                'unit' => $product['unit'],
                'location' => $product['location'],
                'reorder_point' => $product['minimum'],
                'art' => $product['art'],
            ]);
        }
        foreach ($receipts as [$code, $quantity]) {
            $insertReceipt->execute([
                'date' => '2026-11-06',
                'code' => $code,
                'quantity' => $quantity,
                'notes' => 'ประวัติเดิมจากไฟล์ Excel',
            ]);
        }
        foreach ($issues as [$code, $quantity]) {
            $insertIssue->execute([
                'date' => '2026-11-06',
                'code' => $code,
                'quantity' => $quantity,
                'notes' => 'ประวัติเดิมจากไฟล์ Excel',
            ]);
        }
        $db->commit();
        foreach ($products as $product) {
            inventory_sync_product_summary($db, $product['id']);
        }
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function inventory_import_sqlite(PDO $db, string $sqlitePath): void
{
    $source = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $sourceProducts = $source->query('SELECT * FROM products ORDER BY created_at, code')->fetchAll();
    if ($sourceProducts === []) {
        inventory_seed_defaults($db);
        return;
    }

    $insertProduct = $db->prepare(
        'INSERT INTO `product` (`code`, `ชื่อสินค้า`, `หมวดหมู่`, `ประเภท`, `หน่วยนับ`, `ตำแหน่งจัดเก็บ`,
                               `จุดสั่งซื้อขั้นต่ำ`, `art`)
         VALUES (:code, :name, :category, :item_type, :unit, :location, :reorder_point, :art)'
    );
    $insertReceipt = $db->prepare(
        'INSERT INTO `receipts` (`movement_date`, `document_no`, `product_code`, `quantity`, `supplier`, `received_by`, `department`, `notes`)
         VALUES (:movement_date, :document_no, :product_code, :quantity, :supplier, :received_by, :department, :notes)'
    );
    $insertIssue = $db->prepare(
        'INSERT INTO `issues` (`movement_date`, `document_no`, `product_code`, `quantity`, `requested_by`, `department`, `approved_by`, `notes`)
         VALUES (:movement_date, :document_no, :product_code, :quantity, :requested_by, :department, :approved_by, :notes)'
    );

    $db->beginTransaction();
    try {
        foreach ($sourceProducts as $product) {
            $insertProduct->execute([
                'code' => $product['code'],
                'name' => $product['name'],
                'category' => $product['category'],
                'item_type' => $product['item_type'],
                'unit' => $product['unit'],
                'location' => $product['location'],
                'reorder_point' => $product['reorder_point'],
                'art' => $product['art'],
            ]);
        }
        foreach ($source->query('SELECT * FROM receipts ORDER BY id') as $receipt) {
            $insertReceipt->execute([
                'movement_date' => $receipt['movement_date'],
                'document_no' => $receipt['document_no'],
                'product_code' => $receipt['product_code'],
                'quantity' => $receipt['quantity'],
                'supplier' => $receipt['supplier'],
                'received_by' => $receipt['received_by'],
                'department' => $receipt['department'],
                'notes' => $receipt['notes'],
            ]);
        }
        foreach ($source->query('SELECT * FROM issues ORDER BY id') as $issue) {
            $insertIssue->execute([
                'movement_date' => $issue['movement_date'],
                'document_no' => $issue['document_no'],
                'product_code' => $issue['product_code'],
                'quantity' => $issue['quantity'],
                'requested_by' => $issue['requested_by'],
                'department' => $issue['department'],
                'approved_by' => $issue['approved_by'],
                'notes' => $issue['notes'],
            ]);
        }
        $insertImportedOpening = $db->prepare(
            'INSERT INTO `receipts` (`movement_date`, `product_code`, `quantity`, `notes`)
             VALUES (CURRENT_DATE, :code, :quantity, :notes)'
        );
        $insertImportedOpeningIssue = $db->prepare(
            'INSERT INTO `issues` (`movement_date`, `product_code`, `quantity`, `notes`)
             VALUES (CURRENT_DATE, :code, :quantity, :notes)'
        );
        foreach ($sourceProducts as $product) {
            $openingStock = (float) ($product['opening_stock'] ?? 0);
            if ($openingStock > 0) {
                $insertImportedOpening->execute([
                    'code' => $product['code'],
                    'quantity' => $openingStock,
                    'notes' => 'แปลงยอดตั้งต้นเดิมเป็นรายการรับเข้า',
                ]);
            } elseif ($openingStock < 0) {
                $insertImportedOpeningIssue->execute([
                    'code' => $product['code'],
                    'quantity' => abs($openingStock),
                    'notes' => 'แปลงยอดตั้งต้นเดิมเป็นรายการเบิกออก',
                ]);
            }
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
    foreach ($sourceProducts as $product) {
        inventory_sync_product_summary($db, $product['code']);
    }
}

function inventory_migrate_opening_stock(PDO $db): void
{
    $db->beginTransaction();
    try {
        $products = $db->query(
            'SELECT `code`, `opening_stock` FROM `product` WHERE `opening_stock` <> 0 FOR UPDATE'
        )->fetchAll();
        $insertReceipt = $db->prepare(
            'INSERT INTO `receipts` (`movement_date`, `product_code`, `quantity`, `notes`)
             VALUES (CURRENT_DATE, :code, :quantity, :notes)'
        );
        $insertIssue = $db->prepare(
            'INSERT INTO `issues` (`movement_date`, `product_code`, `quantity`, `notes`)
             VALUES (CURRENT_DATE, :code, :quantity, :notes)'
        );
        $clearOpeningStock = $db->prepare(
            'UPDATE `product` SET `opening_stock` = 0 WHERE `code` = :code'
        );

        foreach ($products as $product) {
            $quantity = (float) $product['opening_stock'];
            if ($quantity > 0) {
                $insertReceipt->execute([
                    'code' => $product['code'],
                    'quantity' => $quantity,
                    'notes' => 'แปลงยอดตั้งต้นเดิมเป็นรายการรับเข้า',
                ]);
            } elseif ($quantity < 0) {
                $insertIssue->execute([
                    'code' => $product['code'],
                    'quantity' => abs($quantity),
                    'notes' => 'แปลงยอดตั้งต้นเดิมเป็นรายการเบิกออก',
                ]);
            }
            $clearOpeningStock->execute(['code' => $product['code']]);
            inventory_sync_product_summary($db, $product['code']);
        }
        $db->commit();
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function inventory_products(): array
{
    $statement = inventory_db()->query(
        'SELECT p.`code`, p.`ชื่อสินค้า` AS name, p.`หมวดหมู่` AS category,
                p.`ประเภท` AS item_type, p.`หน่วยนับ` AS unit, p.`ตำแหน่งจัดเก็บ` AS location,
                p.`จุดสั่งซื้อขั้นต่ำ` AS reorder_point, p.`art`, p.`image_filename`,
                COALESCE(r.total, 0) AS received,
                COALESCE(i.total, 0) AS issued,
                COALESCE(r.total, 0) - COALESCE(i.total, 0) AS stock
         FROM `product` p
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM receipts GROUP BY product_code) r
            ON r.product_code = p.`code`
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM issues GROUP BY product_code) i
            ON i.product_code = p.`code`
         ORDER BY p.`รหัสสินค้า`, p.`code`'
    );
    $products = [];
    foreach ($statement->fetchAll() as $row) {
        $products[] = [
            'id' => $row['code'],
            'name' => $row['name'],
            'category' => $row['category'],
            'type' => $row['item_type'],
            'unit' => $row['unit'],
            'location' => $row['location'],
            'minimum' => (float) $row['reorder_point'],
            'received' => (float) $row['received'],
            'issued' => (float) $row['issued'],
            'stock' => (float) $row['stock'],
            'art' => $row['art'],
            'image_filename' => $row['image_filename'],
        ];
    }
    return $products;
}

function inventory_product(string $code): ?array
{
    $statement = inventory_db()->prepare(
        'SELECT p.`รหัสสินค้า` AS id, p.`code`, p.`ชื่อสินค้า` AS name, p.`หมวดหมู่` AS category,
                p.`ประเภท` AS item_type, p.`หน่วยนับ` AS unit, p.`ตำแหน่งจัดเก็บ` AS location,
                p.`จุดสั่งซื้อขั้นต่ำ` AS reorder_point, p.`art`, p.`image_filename`,
                COALESCE(r.total, 0) AS received, COALESCE(i.total, 0) AS issued,
                COALESCE(r.total, 0) - COALESCE(i.total, 0) AS stock
         FROM `product` p
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM `receipts` GROUP BY product_code) r ON r.product_code = p.`code`
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM `issues` GROUP BY product_code) i ON i.product_code = p.`code`
         WHERE p.`code` = :code'
    );
    $statement->execute(['code' => $code]);
    $product = $statement->fetch();
    return $product === false ? null : $product;
}

function inventory_movements(string $kind, int $limit = 250): array
{
    if ($kind === 'receipt') {
        $table = 'receipts';
        $extraColumns = 'm.supplier, m.received_by, m.department';
    } elseif ($kind === 'issue') {
        $table = 'issues';
        $extraColumns = 'm.requested_by, m.department, m.approved_by';
    } else {
        throw new InvalidArgumentException('รายการเคลื่อนไหวไม่ถูกต้อง');
    }

    $statement = inventory_db()->prepare(
        "SELECT m.id, m.movement_date, m.document_no, m.product_code, p.`ชื่อสินค้า` AS product_name,
                m.quantity, p.`หน่วยนับ` AS unit, {$extraColumns}, m.notes, m.created_by_user_id, m.created_by_name
         FROM {$table} m
         JOIN `product` p ON p.`code` = m.product_code
         ORDER BY m.movement_date DESC, m.id DESC
         LIMIT :limit"
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function inventory_movement_counts(): array
{
    $counts = inventory_db()->query(
        'SELECT (SELECT COUNT(*) FROM `receipts`) AS receipts,
                (SELECT COUNT(*) FROM `issues`) AS issues'
    )->fetch();
    return [
        'receipts' => (int) $counts['receipts'],
        'issues' => (int) $counts['issues'],
    ];
}

function inventory_category_round_stats(): array
{
    $db = inventory_db();
    $stats = [];

    $receiptRows = $db->query(
        'SELECT p.`หมวดหมู่` AS category, COUNT(r.id) AS rounds
         FROM `receipts` r
         JOIN `product` p ON p.`code` = r.product_code
         GROUP BY p.`หมวดหมู่`'
    )->fetchAll();
    foreach ($receiptRows as $row) {
        $cat = trim((string) ($row['category'] ?? '')) !== '' ? (string) $row['category'] : 'ไม่ระบุหมวดหมู่';
        $stats[$cat]['receipt_rounds'] = (int) $row['rounds'];
    }

    $issueRows = $db->query(
        'SELECT p.`หมวดหมู่` AS category, COUNT(i.id) AS rounds
         FROM `issues` i
         JOIN `product` p ON p.`code` = i.product_code
         GROUP BY p.`หมวดหมู่`'
    )->fetchAll();
    foreach ($issueRows as $row) {
        $cat = trim((string) ($row['category'] ?? '')) !== '' ? (string) $row['category'] : 'ไม่ระบุหมวดหมู่';
        $stats[$cat]['issue_rounds'] = (int) $row['rounds'];
    }

    return $stats;
}

function inventory_movement_by_id(string $kind, int $id): ?array
{
    if ($kind === 'receipt') {
        $table = 'receipts';
        $extraColumns = 'm.supplier, m.received_by, m.department';
    } elseif ($kind === 'issue') {
        $table = 'issues';
        $extraColumns = 'm.requested_by, m.department, m.approved_by';
    } else {
        throw new InvalidArgumentException('ประเภทรายการเคลื่อนไหวไม่ถูกต้อง');
    }

    $statement = inventory_db()->prepare(
        "SELECT m.id, m.movement_date, m.document_no, m.product_code, p.`ชื่อสินค้า` AS product_name,
                p.`หมวดหมู่` AS category, p.`ตำแหน่งจัดเก็บ` AS location,
                m.quantity, p.`หน่วยนับ` AS unit, {$extraColumns}, m.notes, m.created_at, m.created_by_user_id, m.created_by_name
         FROM `{$table}` m
         LEFT JOIN `product` p ON p.`code` = m.product_code
         WHERE m.id = :id"
    );
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function audit_log(string $action, string $target = '', string $details = ''): void
{
    try {
        $user = function_exists('auth_user') ? auth_user() : null;
        $userId = $user['id'] ?? null;
        $username = $user['username'] ?? 'system';
        $userName = $user['name'] ?? ($user ? 'User' : 'Guest/System');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt = inventory_db()->prepare(
            'INSERT INTO `audit_logs` (`user_id`, `username`, `user_name`, `action`, `target`, `details`, `ip_address`)
             VALUES (:user_id, :username, :user_name, :action, :target, :details, :ip)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'username' => $username,
            'user_name' => $userName,
            'action' => $action,
            'target' => $target,
            'details' => $details,
            'ip' => $ip,
        ]);
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

function inventory_save_product(array $values, ?string $originalCode = null): void
{
    $db = inventory_db();
    $parameters = [
        'code' => $values['code'],
        'name' => $values['name'],
        'category' => $values['category'],
        'item_type' => $values['item_type'],
        'unit' => $values['unit'],
        'location' => $values['location'],
        'reorder_point' => $values['reorder_point'],
        'art' => $values['art'],
        'image_filename' => $values['image_filename'] ?? null,
    ];

    $db->beginTransaction();
    try {
        if ($originalCode === null) {
            $statement = $db->prepare(
                'INSERT INTO `product` (`code`, `ชื่อสินค้า`, `หมวดหมู่`, `ประเภท`, `หน่วยนับ`, `ตำแหน่งจัดเก็บ`,
                                        `จุดสั่งซื้อขั้นต่ำ`, `art`, `image_filename`)
                 VALUES (:code, :name, :category, :item_type, :unit, :location, :reorder_point, :art, :image_filename)'
            );
        } else {
            $statement = $db->prepare(
                'UPDATE `product`
                 SET `ชื่อสินค้า` = :name, `หมวดหมู่` = :category, `ประเภท` = :item_type, `หน่วยนับ` = :unit,
                     `ตำแหน่งจัดเก็บ` = :location,
                     `จุดสั่งซื้อขั้นต่ำ` = :reorder_point, `art` = :art, `image_filename` = :image_filename
                 WHERE `code` = :code'
            );
            $parameters['code'] = $originalCode;
        }
        $statement->execute($parameters);
        if ($originalCode !== null && $statement->rowCount() === 0 && inventory_product($originalCode) === null) {
            throw new DomainException('ไม่พบสินค้าที่ต้องการแก้ไข');
        }
        inventory_sync_product_summary($db, $originalCode ?? $values['code']);
        $db->commit();
        if ($originalCode === null) {
            audit_log('product_create', $values['code'], "เพิ่มสินค้าใหม่: {$values['name']} (จุดสั่งซื้อ: {$values['reorder_point']} {$values['unit']})");
        } else {
            audit_log('product_update', $values['code'], "แก้ไขข้อมูลสินค้า: {$values['name']} (จุดสั่งซื้อ: {$values['reorder_point']} {$values['unit']})");
        }
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function inventory_delete_product(string $code): ?string
{
    $db = inventory_db();
    $db->beginTransaction();
    try {
        $findProduct = $db->prepare(
            'SELECT `image_filename` FROM `product` WHERE `code` = :code FOR UPDATE'
        );
        $findProduct->execute(['code' => $code]);
        $product = $findProduct->fetch();
        if ($product === false) {
            throw new DomainException('ไม่พบสินค้าที่ต้องการลบ');
        }

        foreach (['issues', 'receipts'] as $table) {
            $deleteMovements = $db->prepare(
                "DELETE FROM `{$table}` WHERE `product_code` = :code"
            );
            $deleteMovements->execute(['code' => $code]);
        }

        $deleteProduct = $db->prepare('DELETE FROM `product` WHERE `code` = :code');
        $deleteProduct->execute(['code' => $code]);
        if ($deleteProduct->rowCount() !== 1) {
            throw new RuntimeException('ลบสินค้าไม่สำเร็จ กรุณาลองอีกครั้ง');
        }

        $db->commit();
        audit_log('product_delete', $code, "ลบสินค้าและประวัติที่เกี่ยวข้อง รหัส {$code}");
        return $product['image_filename'] === null ? null : (string) $product['image_filename'];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function inventory_create_product_with_receipt(array $product, array $receipt): void
{
    $db = inventory_db();
    if (inventory_product($product['code']) !== null) {
        throw new DomainException('รหัสสินค้านี้มีในทะเบียนแล้ว กรุณาเลือกรับสินค้าเดิม');
    }

    $db->beginTransaction();
    try {
        $insertProduct = $db->prepare(
            'INSERT INTO `product` (`code`, `ชื่อสินค้า`, `หมวดหมู่`, `ประเภท`, `หน่วยนับ`, `ตำแหน่งจัดเก็บ`,
                                    `จุดสั่งซื้อขั้นต่ำ`, `art`, `image_filename`)
             VALUES (:code, :name, :category, :item_type, :unit, :location, :reorder_point, :art, :image_filename)'
        );
        $insertProduct->execute([
            'code' => $product['code'],
            'name' => $product['name'],
            'category' => $product['category'],
            'item_type' => $product['item_type'],
            'unit' => $product['unit'],
            'location' => $product['location'],
            'reorder_point' => $product['reorder_point'],
            'art' => $product['art'],
            'image_filename' => $product['image_filename'] ?? null,
        ]);

        $u = function_exists('auth_user') ? auth_user() : null;
        $receipt['created_by_user_id'] = $u ? $u['id'] : null;
        $receipt['created_by_name'] = $u ? $u['name'] : '';

        $insertReceipt = $db->prepare(
            'INSERT INTO `receipts` (`movement_date`, `document_no`, `product_code`, `quantity`, `supplier`,
                                     `received_by`, `department`, `notes`, `created_by_user_id`, `created_by_name`)
             VALUES (:movement_date, :document_no, :product_code, :quantity, :supplier, :received_by, :department, :notes, :created_by_user_id, :created_by_name)'
        );
        $insertReceipt->execute($receipt);
        inventory_sync_product_summary($db, $product['code']);
        $db->commit();
        audit_log('product_create', $product['code'], "เพิ่มสินค้าพร้อมรับเข้าครั้งแรก: {$product['name']} จำนวน {$receipt['quantity']} {$product['unit']} (เอกสาร: {$receipt['document_no']})");
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $exception;
    }
}

function inventory_record_movement(string $kind, array $values): void
{
    if (!in_array($kind, ['receipt', 'issue'], true)) {
        throw new InvalidArgumentException('ประเภทรายการไม่ถูกต้อง');
    }
    if (inventory_product($values['product_code']) === null) {
        throw new DomainException('ไม่พบรหัสสินค้าที่เลือก กรุณาเลือกสินค้าใหม่');
    }

    $u = function_exists('auth_user') ? auth_user() : null;
    $values['created_by_user_id'] = $u ? $u['id'] : null;
    $values['created_by_name'] = $u ? $u['name'] : '';

    if ($kind === 'receipt') {
        $statement = inventory_db()->prepare(
            'INSERT INTO receipts (movement_date, document_no, product_code, quantity, supplier, received_by, department, notes, created_by_user_id, created_by_name)
             VALUES (:movement_date, :document_no, :product_code, :quantity, :supplier, :received_by, :department, :notes, :created_by_user_id, :created_by_name)'
        );
    } else {
        $statement = inventory_db()->prepare(
            'INSERT INTO issues (movement_date, document_no, product_code, quantity, requested_by, department, approved_by, notes, created_by_user_id, created_by_name)
             VALUES (:movement_date, :document_no, :product_code, :quantity, :requested_by, :department, :approved_by, :notes, :created_by_user_id, :created_by_name)'
        );
    }
    $statement->execute($values);
    inventory_sync_product_summary(inventory_db(), $values['product_code']);
    if ($kind === 'receipt') {
        audit_log('receipt_create', $values['product_code'], "รับสินค้าเข้า: จำนวน {$values['quantity']} (เอกสาร: " . ($values['document_no'] ?: '-') . ") ผู้ส่งมอบ: " . ($values['supplier'] ?: '-') . " ผู้รับ: " . ($values['received_by'] ?: '-'));
    } else {
        audit_log('issue_create', $values['product_code'], "เบิกสินค้าออก: จำนวน {$values['quantity']} (เอกสาร: " . ($values['document_no'] ?: '-') . ") ผู้ขอเบิก: " . ($values['requested_by'] ?: '-') . " แผนก: " . ($values['department'] ?: '-'));
    }
}

function inventory_can_edit_movement(array $movement): bool
{
    $currentUser = function_exists('auth_user') ? auth_user() : null;
    if ($currentUser === null) {
        return false;
    }
    if ($currentUser['role'] === 'admin') {
        return true;
    }
    if ($currentUser['role'] === 'staff') {
        // Staff can edit only if they were the creator
        return !empty($movement['created_by_user_id']) && (int) $movement['created_by_user_id'] === (int) $currentUser['id'];
    }
    return false; // Viewer cannot edit
}

function inventory_update_movement(string $kind, int $id, array $values): void
{
    if (!in_array($kind, ['receipt', 'issue'], true)) {
        throw new InvalidArgumentException('ประเภทรายการไม่ถูกต้อง');
    }
    $existing = inventory_movement_by_id($kind, $id);
    if ($existing === null) {
        throw new DomainException('ไม่พบรายการที่ต้องการแก้ไข');
    }
    if (!inventory_can_edit_movement($existing)) {
        throw new DomainException('คุณไม่มีสิทธิ์แก้ไขรายการนี้ (Staff แก้ไขได้เฉพาะรายการที่ตนเองเป็นผู้บันทึกเท่านั้น)');
    }

    $db = inventory_db();
    if ($kind === 'receipt') {
        $stmt = $db->prepare(
            'UPDATE receipts
             SET movement_date = :movement_date,
                 document_no = :document_no,
                 quantity = :quantity,
                 supplier = :supplier,
                 received_by = :received_by,
                 department = :department,
                 notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'movement_date' => $values['movement_date'],
            'document_no' => $values['document_no'] ?? '',
            'quantity' => $values['quantity'],
            'supplier' => $values['supplier'] ?? '',
            'received_by' => $values['received_by'] ?? '',
            'department' => $values['department'] ?? '',
            'notes' => $values['notes'] ?? '',
        ]);
        inventory_sync_product_summary($db, $existing['product_code']);
        audit_log('receipt_update', $existing['product_code'], "แก้ไขรายการรับเข้า #{$id}: จำนวน {$values['quantity']} (เอกสาร: " . ($values['document_no'] ?: '-') . ")");
    } else {
        $stmt = $db->prepare(
            'UPDATE issues
             SET movement_date = :movement_date,
                 document_no = :document_no,
                 quantity = :quantity,
                 requested_by = :requested_by,
                 department = :department,
                 approved_by = :approved_by,
                 notes = :notes
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'movement_date' => $values['movement_date'],
            'document_no' => $values['document_no'] ?? '',
            'quantity' => $values['quantity'],
            'requested_by' => $values['requested_by'] ?? '',
            'department' => $values['department'] ?? '',
            'approved_by' => $values['approved_by'] ?? '',
            'notes' => $values['notes'] ?? '',
        ]);
        inventory_sync_product_summary($db, $existing['product_code']);
        audit_log('issue_update', $existing['product_code'], "แก้ไขรายการเบิกออก #{$id}: จำนวน {$values['quantity']} (เอกสาร: " . ($values['document_no'] ?: '-') . ")");
    }
}

function inventory_sync_product_summary(PDO $db, string $code): void
{
    $statement = $db->prepare(
        'UPDATE `product` p
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM `receipts` GROUP BY product_code) r
            ON r.product_code = p.`code`
         LEFT JOIN (SELECT product_code, SUM(quantity) AS total FROM `issues` GROUP BY product_code) i
            ON i.product_code = p.`code`
         SET p.`รับเข้ารวม` = COALESCE(r.total, 0),
             p.`เบิกออกรวม` = COALESCE(i.total, 0),
             p.`คงเหลือ` = COALESCE(r.total, 0) - COALESCE(i.total, 0),
             p.`สถานะสต๊อก` = CASE
                WHEN COALESCE(r.total, 0) - COALESCE(i.total, 0) < 0 THEN \'สต๊อกติดลบ\'
                WHEN COALESCE(r.total, 0) - COALESCE(i.total, 0) <= p.`จุดสั่งซื้อขั้นต่ำ` THEN \'ควรสั่งซื้อ\'
                ELSE \'ปกติ\'
             END
         WHERE p.`code` = :code'
    );
    $statement->execute(['code' => $code]);
}

function inventory_csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

function inventory_require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new DomainException('คำขอไม่ถูกต้อง กรุณาโหลดหน้าเว็บใหม่แล้วลองอีกครั้ง');
    }
}

function inventory_text(string $key, string $label, int $maxLength = 200, bool $required = false): string
{
    $input = $_POST[$key] ?? '';
    if (!is_string($input) && !is_numeric($input)) {
        throw new DomainException("ข้อมูล{$label}ไม่ถูกต้อง");
    }
    $value = trim((string) $input);
    if ($required && $value === '') {
        throw new DomainException("กรุณากรอก{$label}");
    }
    if (strlen($value) > $maxLength) {
        throw new DomainException("{$label}ยาวเกิน {$maxLength} ตัวอักษร");
    }
    return $value;
}

function inventory_number(string $key, string $label, bool $allowZero = true): float
{
    $input = $_POST[$key] ?? '';
    if (!is_string($input) && !is_numeric($input)) {
        throw new DomainException("กรุณากรอก{$label}เป็นตัวเลข");
    }
    $raw = trim((string) $input);
    if ($raw === '' || !is_numeric($raw)) {
        throw new DomainException("กรุณากรอก{$label}เป็นตัวเลข");
    }
    $value = (float) $raw;
    if (!is_finite($value) || $value < 0 || (!$allowZero && $value <= 0)) {
        throw new DomainException($allowZero ? "{$label}ต้องไม่ติดลบ" : "{$label}ต้องมากกว่า 0");
    }
    return $value;
}

function inventory_date(string $key = 'movement_date'): string
{
    $value = inventory_text($key, 'วันที่', 10, true);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if ($date === false || $date->format('Y-m-d') !== $value) {
        throw new DomainException('กรุณาระบุวันที่ให้ถูกต้อง');
    }
    return $value;
}

function inventory_flash(): string
{
    global $notice;
    return $notice ?? '';
}

function inventory_flash_redirect(string $message, string $location, string $type = 'success'): never
{
    $_SESSION['flash_notice'] = ['message' => $message, 'type' => $type];
    header('Location: ' . $location);
    exit;
}

function inventory_handle_error(DomainException|RuntimeException $exception): string
{
    if ($exception instanceof DomainException) {
        return $exception->getMessage();
    }
    error_log('Inventory operation failed: ' . $exception->getMessage());
    http_response_code(500);
    return 'บันทึกข้อมูลไม่สำเร็จ กรุณาลองอีกครั้งหรือติดต่อผู้ดูแลระบบ';
}

function inventory_quantity(float|int|string $quantity): string
{
    $number = (float) $quantity;
    if (floor($number) === $number) {
        return number_format($number, 0);
    }
    return rtrim(rtrim(number_format($number, 3, '.', ','), '0'), '.');
}

/**
 * ลบรายการรับ/เบิกสินค้าหลายรายการพร้อมกัน และ sync ยอดสต๊อก
 * Admin ลบได้ทุกรายการ, Staff ลบได้เฉพาะของตัวเอง
 */
function inventory_delete_movements(string $kind, array $ids): void
{
    if ($ids === []) {
        return;
    }
    if ($kind === 'receipt') {
        $table = 'receipts';
    } elseif ($kind === 'issue') {
        $table = 'issues';
    } else {
        throw new InvalidArgumentException('ประเภทรายการไม่ถูกต้อง');
    }

    $user = auth_user();
    if ($user === null) {
        throw new DomainException('กรุณาเข้าสู่ระบบก่อน');
    }

    $db = inventory_db();
    $intIds = array_map('intval', $ids);
    $intIds = array_filter($intIds, fn($v) => $v > 0);
    if ($intIds === []) {
        return;
    }

    // เฉพาะ Admin เท่านั้นที่สามารถลบประวัติรับ-เบิกได้
    if (!auth_is_admin()) {
        throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถลบประวัติรับ-เบิกสินค้าได้');
    }

    // ดึง product_code ก่อนลบ เพื่อ sync ยอดทีหลัง
    $placeholders = implode(',', array_fill(0, count($intIds), '?'));
    $stmt = $db->prepare("SELECT DISTINCT product_code FROM `{$table}` WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($intIds));
    $affectedCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->prepare("DELETE FROM `{$table}` WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($intIds));
    $deletedCount = $stmt->rowCount();

    foreach ($affectedCodes as $code) {
        inventory_sync_product_summary($db, (string)$code);
    }

    $kindLabel = $kind === 'receipt' ? 'รับเข้าคลัง' : 'เบิกออก';
    audit_log("movement_delete_{$kind}", '', "ลบประวัติ{$kindLabel} {$deletedCount} รายการ (ID: " . implode(', ', $intIds) . ")");
}

/**
 * ลบ audit log หลายรายการพร้อมกัน (Admin เท่านั้น)
 */
function inventory_delete_audit_logs(array $ids): void
{
    if ($ids === []) {
        return;
    }
    if (!auth_is_admin()) {
        throw new DomainException('เฉพาะผู้ดูแลระบบเท่านั้นที่สามารถลบ Audit Log ได้');
    }
    $intIds = array_map('intval', $ids);
    $intIds = array_filter($intIds, fn($v) => $v > 0);
    if ($intIds === []) {
        return;
    }
    $db = inventory_db();
    $placeholders = implode(',', array_fill(0, count($intIds), '?'));
    $stmt = $db->prepare("DELETE FROM `audit_logs` WHERE id IN ({$placeholders})");
    $stmt->execute(array_values($intIds));
    $deletedCount = $stmt->rowCount();
    // บันทึก audit ว่าลบ log (ใช้ SQL direct เพื่อไม่เกิด loop)
    $db->prepare("INSERT INTO `audit_logs` (user_id, username, user_name, action, target, details, ip_address)
                  VALUES (:uid, :uname, :unamefull, 'audit_log_delete', '', :details, :ip)")
       ->execute([
           'uid'      => auth_user()['id'] ?? 0,
           'uname'    => auth_user()['username'] ?? '',
           'unamefull'=> auth_user()['name'] ?? '',
           'details'  => "ลบ Audit Log {$deletedCount} รายการ (ID: " . implode(', ', $intIds) . ")",
           'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
       ]);
}
