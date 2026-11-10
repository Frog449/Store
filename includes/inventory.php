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
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (!empty($config['ssl'])) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        $options[PDO::MYSQL_ATTR_SSL_CA] = true;
    }
    $db = new PDO($dsn, $config['username'], $config['password'], $options);
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
            KEY `idx_receipts_product` (`product_code`),
            CONSTRAINT `fk_receipts_product_code` FOREIGN KEY (`product_code`) REFERENCES `product` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
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
            KEY `idx_issues_product` (`product_code`),
            CONSTRAINT `fk_issues_product_code` FOREIGN KEY (`product_code`) REFERENCES `product` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );

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
    if (!is_string($mimeType)
        || !isset($extensions[$mimeType])
        || $imageInfo === false
        || ($imageInfo['mime'] ?? null) !== $mimeType
        || $imageInfo[0] * $imageInfo[1] > 20000000) {
        throw new DomainException('รองรับเฉพาะรูป JPG, PNG หรือ WEBP ที่มีขนาดไม่เกิน 20 ล้านพิกเซล');
    }

    $directory = __DIR__ . '/../storage/product-images';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์จัดเก็บรูปสินค้าได้');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('ไม่สามารถจัดเก็บรูปสินค้าได้');
    }
    return $filename;
}

function inventory_product_image_url(?string $filename): ?string
{
    if ($filename === null || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
        return null;
    }
    return 'storage/product-images/' . $filename;
}

function inventory_delete_product_image(?string $filename): void
{
    if (inventory_product_image_url($filename) === null) {
        return;
    }

    $path = __DIR__ . '/../storage/product-images/' . $filename;
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to remove product image: ' . $filename);
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
    return ['ชิ้น', 'อัน'];
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
                m.quantity, p.`หน่วยนับ` AS unit, {$extraColumns}, m.notes
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

        $insertReceipt = $db->prepare(
            'INSERT INTO `receipts` (`movement_date`, `document_no`, `product_code`, `quantity`, `supplier`,
                                     `received_by`, `department`, `notes`)
             VALUES (:movement_date, :document_no, :product_code, :quantity, :supplier, :received_by, :department, :notes)'
        );
        $insertReceipt->execute($receipt);
        inventory_sync_product_summary($db, $product['code']);
        $db->commit();
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

    if ($kind === 'receipt') {
        $statement = inventory_db()->prepare(
            'INSERT INTO receipts (movement_date, document_no, product_code, quantity, supplier, received_by, department, notes)
             VALUES (:movement_date, :document_no, :product_code, :quantity, :supplier, :received_by, :department, :notes)'
        );
    } else {
        $statement = inventory_db()->prepare(
            'INSERT INTO issues (movement_date, document_no, product_code, quantity, requested_by, department, approved_by, notes)
             VALUES (:movement_date, :document_no, :product_code, :quantity, :requested_by, :department, :approved_by, :notes)'
        );
    }
    $statement->execute($values);
    inventory_sync_product_summary(inventory_db(), $values['product_code']);
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

function inventory_flash_redirect(string $message, string $location): never
{
    $_SESSION['flash_notice'] = ['message' => $message, 'type' => 'success'];
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
