<?php
declare(strict_types=1);

/**
 * Excel Live & Power Query Sync Library
 * - Real-time dataset queries (Products, Receipts, Issues, History, Borrows, Summary)
 * - JSON / CSV / HTML Table renderers for Power Query & Excel Web Query
 * - Pure-PHP Live-Connected .xlsx generator (OpenXML + Connections + QueryTables, no ZipArchive required)
 * - .iqy Web Query file & Power Query M-code generators
 */

function excel_live_is_enabled(): bool
{
    return inventory_get_setting('excel_live_enabled', '1') !== '0';
}

function excel_live_get_token(): string
{
    $token = trim(inventory_get_setting('excel_live_token', ''));
    if ($token === '' || strlen($token) < 24) {
        $token = bin2hex(random_bytes(24));
        try {
            inventory_set_setting('excel_live_token', $token);
        } catch (Throwable $e) {
            // Ignore write errors in read-only edge cases
        }
    }
    return $token;
}

function excel_live_regenerate_token(): string
{
    $token = bin2hex(random_bytes(24));
    inventory_set_setting('excel_live_token', $token);
    return $token;
}

function excel_live_verify_token(?string $provided): bool
{
    if ($provided === null || trim($provided) === '') {
        return false;
    }
    $expected = excel_live_get_token();
    return hash_equals($expected, trim($provided));
}

function excel_live_refresh_interval(): int
{
    $raw = inventory_get_setting('excel_refresh_interval', '1');
    $val = is_numeric($raw) ? (int) $raw : 1;
    if ($val < 0) {
        return 0;
    }
    if ($val > 60) {
        return 60;
    }
    return $val;
}

function excel_live_detect_base_url(): string
{
    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
    ) {
        $scheme = 'https';
    }

    $host = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000'));
    if (str_contains($host, ',')) {
        $host = trim(explode(',', $host)[0]);
    }
    $host = preg_replace('/[^a-zA-Z0-9\.\-:\[\]]/', '', $host) ?: '127.0.0.1:8000';

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if (preg_match('#/(api|web|mobile)$#', $dir)) {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }

    return $scheme . '://' . $host . $dir;
}

function excel_live_base_url(): string
{
    $configured = trim(inventory_get_setting('excel_public_base_url', ''));
    if ($configured !== '' && preg_match('#^https?://#i', $configured)) {
        return rtrim($configured, '/');
    }
    return excel_live_detect_base_url();
}

function excel_live_is_local_url(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1') {
        return true;
    }
    if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
        return true;
    }
    return false;
}

function excel_live_valid_types(): array
{
    return ['summary', 'products', 'receipts', 'issues', 'history', 'borrows'];
}

function excel_live_type_meta(string $type): array
{
    $map = [
        'summary' => [
            'sheet' => 'ภาพรวม',
            'conn' => 'Store_Summary',
            'title' => 'ภาพรวมสต๊อก (Live)',
            'file' => 'store_summary',
        ],
        'products' => [
            'sheet' => 'รายการสินค้า',
            'conn' => 'Store_Products',
            'title' => 'รายการสินค้าทั้งหมด',
            'file' => 'store_products',
        ],
        'receipts' => [
            'sheet' => 'ประวัติรับเข้า',
            'conn' => 'Store_Receipts',
            'title' => 'ประวัติรับสินค้าเข้า',
            'file' => 'store_receipts',
        ],
        'issues' => [
            'sheet' => 'ประวัติเบิกออก',
            'conn' => 'Store_Issues',
            'title' => 'ประวัติเบิกสินค้าออก',
            'file' => 'store_issues',
        ],
        'history' => [
            'sheet' => 'ประวัติรับ-เบิกรวม',
            'conn' => 'Store_History',
            'title' => 'ประวัติรับ-เบิกรวมทั้งหมด',
            'file' => 'store_history',
        ],
        'borrows' => [
            'sheet' => 'ยืม-คืนพัสดุ',
            'conn' => 'Store_Borrows',
            'title' => 'ประวัติยืม-คืนพัสดุ',
            'file' => 'store_borrows',
        ],
    ];
    return $map[$type] ?? $map['products'];
}

function excel_live_feed_url(string $type, string $format = 'json', ?string $baseUrl = null): string
{
    $safeType = in_array($type, excel_live_valid_types(), true) ? $type : 'products';
    $safeFormat = in_array($format, ['json', 'csv', 'html'], true) ? $format : 'json';
    $base = rtrim($baseUrl !== null && trim($baseUrl) !== '' ? trim($baseUrl) : excel_live_base_url(), '/');
    $token = excel_live_get_token();
    return $base . '/api/excel.php?type=' . rawurlencode($safeType)
        . '&format=' . rawurlencode($safeFormat)
        . '&token=' . rawurlencode($token);
}

/**
 * Fetch full live dataset directly from MySQL/TiDB (not clamped to 250 rows)
 */
function excel_live_fetch_movements(string $kind, int $limit = 10000): array
{
    $limit = max(1, min(50000, $limit));
    $db = inventory_db();

    if ($kind === 'receipt') {
        $sql = "SELECT m.id, m.movement_date, COALESCE(m.movement_time, '') AS movement_time,
                       m.document_no, m.product_code,
                       COALESCE(p.`stock_code`, p.`code`) AS stock_code,
                       COALESCE(NULLIF(m.account_code1, ''), NULLIF(p.`account_code1`, ''), '-') AS account_code1,
                       p.`ชื่อสินค้า` AS product_name,
                       m.quantity, p.`หน่วยนับ` AS unit,
                       m.supplier, m.received_by, m.department, m.notes, m.created_by_name, m.created_at
                FROM `receipts` m
                LEFT JOIN `product` p ON p.`code` = m.product_code
                ORDER BY m.movement_date DESC, m.movement_time DESC, m.id DESC
                LIMIT :limit";
    } else {
        $sql = "SELECT m.id, m.movement_date, '' AS movement_time,
                       m.document_no, m.product_code,
                       COALESCE(p.`stock_code`, p.`code`) AS stock_code,
                       COALESCE(NULLIF(p.`account_code1`, ''), '-') AS account_code1,
                       p.`ชื่อสินค้า` AS product_name,
                       m.quantity, p.`หน่วยนับ` AS unit,
                       m.requested_by, m.department, m.approved_by, m.notes, m.created_by_name, m.created_at
                FROM `issues` m
                LEFT JOIN `product` p ON p.`code` = m.product_code
                ORDER BY m.movement_date DESC, m.id DESC
                LIMIT :limit";
    }

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function excel_live_fetch_borrows(int $limit = 10000): array
{
    $limit = max(1, min(50000, $limit));
    $db = inventory_db();
    if (!inventory_table_exists($db, 'borrow_records')) {
        return [];
    }

    $sql = "SELECT b.*,
                   COALESCE(NULLIF(p.`stock_code`, ''), b.`product_code`) AS product_stock_code,
                   COALESCE(NULLIF(b.`account_code1`, ''), NULLIF(p.`account_code1`, ''), '-') AS resolved_account_code1
            FROM `borrow_records` b
            LEFT JOIN `product` p ON p.`code` = b.`product_code`
            ORDER BY (b.`status` = 'borrowed') DESC, b.`borrow_date` DESC, b.`borrow_time` DESC, b.`id` DESC
            LIMIT :limit";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Build unified dataset descriptor: columns, types, widths, and rows
 */
function excel_live_dataset(string $type, int $limit = 10000): array
{
    $type = in_array($type, excel_live_valid_types(), true) ? $type : 'products';
    $meta = excel_live_type_meta($type);
    $now = date('Y-m-d H:i:s');

    if ($type === 'summary') {
        $products = inventory_products();
        $totalProducts = count($products);
        $normalCount = 0;
        $lowCount = 0;
        $negativeCount = 0;
        $totalStockUnits = 0;
        $totalReceivedUnits = 0;
        $totalIssuedUnits = 0;

        foreach ($products as $p) {
            $stock = (int) ($p['stock'] ?? 0);
            $min = (int) ($p['minimum'] ?? 0);
            $totalStockUnits += $stock;
            $totalReceivedUnits += (int) ($p['received'] ?? 0);
            $totalIssuedUnits += (int) ($p['issued'] ?? 0);
            if ($stock <= 0) {
                $negativeCount++;
            } elseif ($stock <= $min) {
                $lowCount++;
            } else {
                $normalCount++;
            }
        }

        $db = inventory_db();
        $receiptCount = (int) $db->query('SELECT COUNT(*) FROM `receipts`')->fetchColumn();
        $issueCount = (int) $db->query('SELECT COUNT(*) FROM `issues`')->fetchColumn();
        $borrowedCount = 0;
        $returnedCount = 0;
        if (inventory_table_exists($db, 'borrow_records')) {
            $borrowedCount = (int) $db->query("SELECT COUNT(*) FROM `borrow_records` WHERE `status` = 'borrowed'")->fetchColumn();
            $returnedCount = (int) $db->query("SELECT COUNT(*) FROM `borrow_records` WHERE `status` = 'returned'")->fetchColumn();
        }

        $columns = [
            ['label' => 'รายการสรุป', 'type' => 'text', 'width' => 32],
            ['label' => 'ค่าล่าสุด', 'type' => 'text', 'width' => 26],
            ['label' => 'หมายเหตุ', 'type' => 'text', 'width' => 48],
        ];
        $rows = [
            ['อัปเดตล่าสุดจากเซิร์ฟเวอร์', $now, 'เวลาเซิร์ฟเวอร์ (Asia/Bangkok) — กด Data > Refresh All เพื่ออัปเดตทันที'],
            ['จำนวนรายการสินค้าทั้งหมด', (string) $totalProducts, 'รายการสินค้าในฐานข้อมูลทั้งหมด'],
            ['สินค้าสถานะปกติ', (string) $normalCount, 'คงเหลือมากกว่าจุดสั่งซื้อขั้นต่ำ'],
            ['สินค้าควรสั่งซื้อ (สต๊อกต่ำ)', (string) $lowCount, 'คงเหลือน้อยกว่าหรือเท่ากับจุดสั่งซื้อขั้นต่ำ'],
            ['สินค้าหมด / ติดลบ', (string) $negativeCount, 'คงเหลือ 0 หรือติดลบ'],
            ['ยอดคงเหลือรวมทุกสินค้า', (string) $totalStockUnits, 'หน่วยรวมทั้งหมดในคลัง'],
            ['ยอดรับเข้าสะสมรวม', (string) $totalReceivedUnits, 'จากรายการรับเข้าทั้งหมด ' . $receiptCount . ' รายการ'],
            ['ยอดเบิกออกสะสมรวม', (string) $totalIssuedUnits, 'จากรายการเบิกออกทั้งหมด ' . $issueCount . ' รายการ'],
            ['พัสดุที่กำลังถูกยืมอยู่', (string) $borrowedCount, 'ยังไม่ได้คืน (คืนแล้ว ' . $returnedCount . ' รายการ)'],
            ['วิธีอัปเดตข้อมูลใน Excel', 'Data → Refresh All (Ctrl+Alt+F5)', 'ไฟล์นี้ตั้งค่าอัปเดตอัตโนมัติตอนเปิดไฟล์และทุก ' . max(1, excel_live_refresh_interval()) . ' นาที'],
        ];

        return [
            'type' => $type,
            'meta' => $meta,
            'updated_at' => $now,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    if ($type === 'products') {
        $columns = [
            ['label' => 'Account Code1', 'type' => 'text', 'width' => 18],
            ['label' => 'Stock Code', 'type' => 'text', 'width' => 20],
            ['label' => 'GL Description', 'type' => 'text', 'width' => 24],
            ['label' => 'Name', 'type' => 'text', 'width' => 36],
            ['label' => 'Unit', 'type' => 'text', 'width' => 10],
            ['label' => 'รับเข้ารวม', 'type' => 'int', 'width' => 12],
            ['label' => 'เบิกออกรวม', 'type' => 'int', 'width' => 12],
            ['label' => 'Quantity', 'type' => 'int', 'width' => 12],
            ['label' => 'จุดสั่งซื้อขั้นต่ำ', 'type' => 'int', 'width' => 15],
            ['label' => 'สถานะสต๊อก', 'type' => 'text', 'width' => 14],
            ['label' => 'Section code', 'type' => 'text', 'width' => 15],
            ['label' => 'Section Name', 'type' => 'text', 'width' => 22],
            ['label' => 'Apex', 'type' => 'text', 'width' => 14],
            ['label' => 'Buydate', 'type' => 'text', 'width' => 14],
            ['label' => 'เวลาBuydate', 'type' => 'text', 'width' => 13],
            ['label' => 'BuyPrice/Unit', 'type' => 'decimal', 'width' => 15],
            ['label' => 'Cal No.', 'type' => 'text', 'width' => 16],
            ['label' => 'Cal date', 'type' => 'text', 'width' => 14],
            ['label' => 'Cal Exp.', 'type' => 'text', 'width' => 14],
            ['label' => 'หมายเหตุ', 'type' => 'text', 'width' => 28],
        ];

        $products = inventory_products();
        $rows = [];
        foreach ($products as $p) {
            $sc = inventory_clean_stock_code((string) ($p['stock_code'] ?? $p['id'] ?? ''));
            $ac1 = trim((string) ($p['account_code1'] ?? ''));
            if ($ac1 === '') {
                $ac1 = '-';
            }
            $glDesc = trim((string) ($p['gl_description'] ?? '')) !== '' ? (string) $p['gl_description'] : (string) ($p['category'] ?? '');
            $buyParts = inventory_split_buy_datetime((string) ($p['buy_date'] ?? ''));
            $stock = (int) ($p['stock'] ?? 0);
            $min = (int) ($p['minimum'] ?? 0);
            $statusLabel = $stock <= 0 ? 'หมด/ติดลบ' : ($stock <= $min ? 'ควรสั่งซื้อ' : 'ปกติ');

            $rows[] = [
                $ac1,
                $sc,
                $glDesc,
                (string) ($p['name'] ?? ''),
                (string) ($p['unit'] ?? ''),
                (int) ($p['received'] ?? 0),
                (int) ($p['issued'] ?? 0),
                $stock,
                $min,
                $statusLabel,
                (string) ($p['section_code'] ?? ''),
                (string) ($p['section_name'] ?? ''),
                (string) ($p['apex'] ?? ''),
                (string) ($buyParts['date'] ?? ''),
                (string) ($buyParts['time'] ?? ''),
                $p['buy_price'] !== null ? (float) $p['buy_price'] : '',
                (string) ($p['cal_no'] ?? ''),
                (string) ($p['cal_date'] ?? ''),
                (string) ($p['cal_exp'] ?? ''),
                (string) ($p['notes'] ?? ''),
            ];
        }

        return [
            'type' => $type,
            'meta' => $meta,
            'updated_at' => $now,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    if ($type === 'receipts') {
        $columns = [
            ['label' => 'วันที่รับ', 'type' => 'text', 'width' => 14],
            ['label' => 'เวลา', 'type' => 'text', 'width' => 11],
            ['label' => 'เลขที่เอกสาร', 'type' => 'text', 'width' => 18],
            ['label' => 'Stock Code', 'type' => 'text', 'width' => 20],
            ['label' => 'Account Code1', 'type' => 'text', 'width' => 18],
            ['label' => 'ชื่อสินค้า', 'type' => 'text', 'width' => 36],
            ['label' => 'จำนวนรับ', 'type' => 'int', 'width' => 12],
            ['label' => 'หน่วยนับ', 'type' => 'text', 'width' => 10],
            ['label' => 'ผู้รับสินค้า', 'type' => 'text', 'width' => 20],
            ['label' => 'ผู้ขาย/แผนก', 'type' => 'text', 'width' => 20],
            ['label' => 'หมายเหตุ', 'type' => 'text', 'width' => 30],
        ];

        $receipts = excel_live_fetch_movements('receipt', $limit);
        $rows = [];
        foreach ($receipts as $r) {
            $sc = inventory_clean_stock_code((string) ($r['stock_code'] ?? $r['product_code'] ?? ''));
            $ac1 = trim((string) ($r['account_code1'] ?? ''));
            $deptSupplier = trim(implode(' / ', array_filter([
                trim((string) ($r['supplier'] ?? '')),
                trim((string) ($r['department'] ?? '')),
            ])));
            $rows[] = [
                (string) ($r['movement_date'] ?? ''),
                (string) ($r['movement_time'] ?? ''),
                (string) ($r['document_no'] ?? ''),
                $sc,
                $ac1 !== '' ? $ac1 : '-',
                (string) ($r['product_name'] ?? $r['product_code'] ?? ''),
                (int) round((float) ($r['quantity'] ?? 0)),
                (string) ($r['unit'] ?? ''),
                (string) ($r['received_by'] ?? ''),
                $deptSupplier,
                (string) ($r['notes'] ?? ''),
            ];
        }

        return [
            'type' => $type,
            'meta' => $meta,
            'updated_at' => $now,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    if ($type === 'issues') {
        $columns = [
            ['label' => 'วันที่เบิก', 'type' => 'text', 'width' => 14],
            ['label' => 'เลขที่เอกสาร', 'type' => 'text', 'width' => 18],
            ['label' => 'Stock Code', 'type' => 'text', 'width' => 20],
            ['label' => 'Account Code1', 'type' => 'text', 'width' => 18],
            ['label' => 'ชื่อสินค้า', 'type' => 'text', 'width' => 36],
            ['label' => 'จำนวนเบิก', 'type' => 'int', 'width' => 12],
            ['label' => 'หน่วยนับ', 'type' => 'text', 'width' => 10],
            ['label' => 'ผู้เบิก', 'type' => 'text', 'width' => 20],
            ['label' => 'แผนก', 'type' => 'text', 'width' => 18],
            ['label' => 'ผู้อนุมัติ', 'type' => 'text', 'width' => 20],
            ['label' => 'หมายเหตุ', 'type' => 'text', 'width' => 30],
        ];

        $issues = excel_live_fetch_movements('issue', $limit);
        $rows = [];
        foreach ($issues as $i) {
            $sc = inventory_clean_stock_code((string) ($i['stock_code'] ?? $i['product_code'] ?? ''));
            $ac1 = trim((string) ($i['account_code1'] ?? ''));
            $rows[] = [
                (string) ($i['movement_date'] ?? ''),
                (string) ($i['document_no'] ?? ''),
                $sc,
                $ac1 !== '' ? $ac1 : '-',
                (string) ($i['product_name'] ?? $i['product_code'] ?? ''),
                (int) round((float) ($i['quantity'] ?? 0)),
                (string) ($i['unit'] ?? ''),
                (string) ($i['requested_by'] ?? ''),
                (string) ($i['department'] ?? ''),
                (string) ($i['approved_by'] ?? ''),
                (string) ($i['notes'] ?? ''),
            ];
        }

        return [
            'type' => $type,
            'meta' => $meta,
            'updated_at' => $now,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    if ($type === 'history') {
        $columns = [
            ['label' => 'ประเภท', 'type' => 'text', 'width' => 12],
            ['label' => 'วันที่', 'type' => 'text', 'width' => 14],
            ['label' => 'เลขที่เอกสาร', 'type' => 'text', 'width' => 18],
            ['label' => 'Stock Code', 'type' => 'text', 'width' => 20],
            ['label' => 'Account Code1', 'type' => 'text', 'width' => 18],
            ['label' => 'ชื่อสินค้า', 'type' => 'text', 'width' => 36],
            ['label' => 'จำนวน', 'type' => 'int', 'width' => 12],
            ['label' => 'หน่วยนับ', 'type' => 'text', 'width' => 10],
            ['label' => 'ผู้รับ / ผู้เบิก', 'type' => 'text', 'width' => 20],
            ['label' => 'ผู้อนุมัติ', 'type' => 'text', 'width' => 20],
            ['label' => 'หมายเหตุ', 'type' => 'text', 'width' => 30],
        ];

        $receipts = excel_live_fetch_movements('receipt', $limit);
        $issues = excel_live_fetch_movements('issue', $limit);
        $all = [];
        foreach ($receipts as $r) {
            $sc = inventory_clean_stock_code((string) ($r['stock_code'] ?? $r['product_code'] ?? ''));
            $ac1 = trim((string) ($r['account_code1'] ?? ''));
            $all[] = [
                'sort' => (string) ($r['movement_date'] ?? '') . ' ' . (string) ($r['movement_time'] ?? '00:00:00') . ' ' . str_pad((string) ($r['id'] ?? 0), 10, '0', STR_PAD_LEFT),
                'row' => [
                    'รับเข้า',
                    (string) ($r['movement_date'] ?? ''),
                    (string) ($r['document_no'] ?? ''),
                    $sc,
                    $ac1 !== '' ? $ac1 : '-',
                    (string) ($r['product_name'] ?? $r['product_code'] ?? ''),
                    (int) round((float) ($r['quantity'] ?? 0)),
                    (string) ($r['unit'] ?? ''),
                    (string) ($r['received_by'] ?? ''),
                    '-',
                    (string) ($r['notes'] ?? ''),
                ],
            ];
        }
        foreach ($issues as $i) {
            $sc = inventory_clean_stock_code((string) ($i['stock_code'] ?? $i['product_code'] ?? ''));
            $ac1 = trim((string) ($i['account_code1'] ?? ''));
            $all[] = [
                'sort' => (string) ($i['movement_date'] ?? '') . ' 00:00:00 ' . str_pad((string) ($i['id'] ?? 0), 10, '0', STR_PAD_LEFT),
                'row' => [
                    'เบิกออก',
                    (string) ($i['movement_date'] ?? ''),
                    (string) ($i['document_no'] ?? ''),
                    $sc,
                    $ac1 !== '' ? $ac1 : '-',
                    (string) ($i['product_name'] ?? $i['product_code'] ?? ''),
                    -1 * (int) round((float) ($i['quantity'] ?? 0)),
                    (string) ($i['unit'] ?? ''),
                    (string) ($i['requested_by'] ?? ''),
                    (string) ($i['approved_by'] ?? ''),
                    (string) ($i['notes'] ?? ''),
                ],
            ];
        }
        usort($all, static fn(array $a, array $b): int => strcmp($b['sort'], $a['sort']));
        $rows = array_map(static fn(array $item): array => $item['row'], array_slice($all, 0, $limit));

        return [
            'type' => $type,
            'meta' => $meta,
            'updated_at' => $now,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    // borrows
    $columns = [
        ['label' => 'รหัสรายการ', 'type' => 'int', 'width' => 12],
        ['label' => 'สถานะ', 'type' => 'text', 'width' => 14],
        ['label' => 'วันที่ยืม', 'type' => 'text', 'width' => 14],
        ['label' => 'เวลายืม', 'type' => 'text', 'width' => 12],
        ['label' => 'ผู้ยืม', 'type' => 'text', 'width' => 20],
        ['label' => 'Stock Code', 'type' => 'text', 'width' => 20],
        ['label' => 'Account Code1', 'type' => 'text', 'width' => 18],
        ['label' => 'ชื่อสินค้า', 'type' => 'text', 'width' => 34],
        ['label' => 'จำนวน', 'type' => 'int', 'width' => 10],
        ['label' => 'หน่วยนับ', 'type' => 'text', 'width' => 10],
        ['label' => 'สถานที่ใช้งาน', 'type' => 'text', 'width' => 24],
        ['label' => 'วันที่คืน', 'type' => 'text', 'width' => 14],
        ['label' => 'ผู้รับคืน', 'type' => 'text', 'width' => 20],
        ['label' => 'หมายเหตุ', 'type' => 'text', 'width' => 30],
    ];

    $borrows = excel_live_fetch_borrows($limit);
    $rows = [];
    foreach ($borrows as $b) {
        $sc = inventory_clean_stock_code((string) ($b['product_stock_code'] ?? $b['product_code'] ?? ''));
        $ac1 = trim((string) ($b['resolved_account_code1'] ?? ''));
        $statusText = (($b['status'] ?? '') === 'returned') ? 'คืนแล้ว' : 'กำลังยืม';
        $notesJoined = trim(implode(' | ', array_filter([
            trim((string) ($b['notes'] ?? '')),
            trim((string) ($b['return_notes'] ?? '')),
        ])));
        $rows[] = [
            (int) ($b['id'] ?? 0),
            $statusText,
            (string) ($b['borrow_date'] ?? ''),
            substr((string) ($b['borrow_time'] ?? ''), 0, 8),
            (string) ($b['borrower_name'] ?? ''),
            $sc,
            $ac1 !== '' ? $ac1 : '-',
            (string) ($b['product_name'] ?? ''),
            (int) round((float) ($b['quantity'] ?? 0)),
            (string) ($b['unit'] ?? ''),
            (string) ($b['location'] ?? ''),
            (string) ($b['return_date'] ?? ''),
            (string) ($b['return_received_by'] ?? ''),
            $notesJoined,
        ];
    }

    return [
        'type' => $type,
        'meta' => $meta,
        'updated_at' => $now,
        'columns' => $columns,
        'rows' => $rows,
    ];
}

/**
 * Render dataset as JSON (ideal for Power Query Json.Document(Web.Contents(...)))
 */
function excel_live_render_json(string $type, int $limit = 10000): string
{
    $dataset = excel_live_dataset($type, $limit);
    $headers = array_map(static fn(array $c): string => $c['label'], $dataset['columns']);
    $records = [];
    foreach ($dataset['rows'] as $row) {
        $assoc = [];
        foreach ($headers as $idx => $label) {
            $assoc[$label] = $row[$idx] ?? '';
        }
        $records[] = $assoc;
    }

    return (string) json_encode([
        'success' => true,
        'type' => $dataset['type'],
        'title' => $dataset['meta']['title'],
        'updated_at' => $dataset['updated_at'],
        'total' => count($records),
        'columns' => $headers,
        'data' => $records,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Render dataset as clean UTF-8 BOM CSV (ideal for Power Query Csv.Document)
 */
function excel_live_render_csv(string $type, int $limit = 10000): string
{
    $dataset = excel_live_dataset($type, $limit);
    $headers = array_map(static fn(array $c): string => $c['label'], $dataset['columns']);

    $fp = fopen('php://temp', 'r+');
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);
    foreach ($dataset['rows'] as $row) {
        $out = [];
        foreach ($row as $val) {
            $out[] = is_bool($val) ? ($val ? '1' : '0') : (string) $val;
        }
        fputcsv($fp, $out);
    }
    rewind($fp);
    $csv = (string) stream_get_contents($fp);
    fclose($fp);
    return $csv;
}

/**
 * Render dataset as clean UTF-8 HTML Table (ideal for Excel Live .xlsx Web Query & .iqy)
 */
function excel_live_render_html(string $type, int $limit = 10000): string
{
    $dataset = excel_live_dataset($type, $limit);
    $columns = $dataset['columns'];
    $rows = $dataset['rows'];
    $title = htmlspecialchars($dataset['meta']['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html = "\xEF\xBB\xBF<!DOCTYPE html>\r\n"
        . "<html lang=\"th\">\r\n<head>\r\n"
        . "<meta charset=\"UTF-8\">\r\n"
        . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">\r\n"
        . "<title>{$title}</title>\r\n"
        . "<style>table{border-collapse:collapse;font-family:Tahoma,Calibri,sans-serif;font-size:11pt;}"
        . "th{background:#0758B4;color:#FFFFFF;font-weight:bold;padding:6px 8px;border:1px solid #CBD5E1;}"
        . "td{padding:5px 8px;border:1px solid #CBD5E1;}</style>\r\n"
        . "</head>\r\n<body>\r\n"
        . "<table id=\"store-data-table\" border=\"1\">\r\n<thead><tr>";

    foreach ($columns as $col) {
        $label = htmlspecialchars($col['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html .= "<th>{$label}</th>";
    }
    $html .= "</tr></thead>\r\n<tbody>\r\n";

    if ($rows === []) {
        $html .= '<tr>';
        foreach ($columns as $idx => $col) {
            $html .= $idx === 0
                ? '<td style="mso-number-format:\'\\@\';">ยังไม่มีข้อมูล</td>'
                : '<td style="mso-number-format:\'\\@\';"></td>';
        }
        $html .= "</tr>\r\n";
    } else {
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $idx => $col) {
                $val = $row[$idx] ?? '';
                $colType = $col['type'] ?? 'text';
                if ($colType === 'int' && is_numeric($val) && $val !== '') {
                    $num = (int) round((float) $val);
                    $html .= '<td style="mso-number-format:\'#,##0\';">' . $num . '</td>';
                } elseif ($colType === 'decimal' && is_numeric($val) && $val !== '') {
                    $num = number_format((float) $val, 2, '.', '');
                    $html .= '<td style="mso-number-format:\'#,##0.00\';">' . $num . '</td>';
                } else {
                    $escaped = htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html .= '<td style="mso-number-format:\'\\@\';">' . $escaped . '</td>';
                }
            }
            $html .= "</tr>\r\n";
        }
    }

    $html .= "</tbody>\r\n</table>\r\n</body>\r\n</html>";
    return $html;
}

/**
 * Generate ready-to-paste Power Query M code for Excel (Blank Query -> Advanced Editor)
 */
function excel_live_power_query_m(string $type, ?string $baseUrl = null): string
{
    $dataset = excel_live_dataset($type, 1);
    $url = excel_live_feed_url($type, 'json', $baseUrl);
    $escapedUrl = str_replace('"', '""', $url);

    $colNames = [];
    $typeMappings = [];
    foreach ($dataset['columns'] as $col) {
        $name = str_replace('"', '""', $col['label']);
        $colNames[] = '"' . $name . '"';
        $mType = match ($col['type']) {
            'int' => 'Int64.Type',
            'decimal' => 'type number',
            default => 'type text',
        };
        $typeMappings[] = '{"' . $name . '", ' . $mType . '}';
    }

    $colsList = implode(', ', $colNames);
    $typesList = implode(', ', $typeMappings);

    return <<<M
let
    Url = "{$escapedUrl}",
    Source = Json.Document(Web.Contents(Url)),
    DataList = Source[data],
    Columns = {{$colsList}},
    RawTable = if List.IsEmpty(DataList)
        then #table(Columns, {})
        else Table.ExpandRecordColumn(
            Table.FromList(DataList, Splitter.SplitByNothing(), null, null, ExtraValues.Error),
            "Column1",
            Columns
        ),
    TypedTable = Table.TransformColumnTypes(RawTable, {{$typesList}})
in
    TypedTable
M;
}

/**
 * Generate .iqy (Microsoft Excel Internet Query) file for a single table
 */
function excel_live_build_iqy(string $type, ?string $baseUrl = null): string
{
    $url = excel_live_feed_url($type, 'html', $baseUrl);
    return "WEB\r\n1\r\n{$url}\r\n\r\nSelection=store-data-table\r\nFormatting=RTF\r\nPreFormattedTextToColumns=True\r\nConsecutiveDelimitersAsOne=True\r\nSingleBlockTextImport=False\r\nDisableDateRecognition=True\r\nDisableRedirections=False\r\n";
}

/**
 * XML escaping helper that strips invalid XML 1.0 control characters
 */
function excel_live_xml_escape(string $value): string
{
    $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    if ($clean === null) {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
    }
    return htmlspecialchars($clean, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Convert 0-based column index to Excel column letter (0 -> A, 25 -> Z, 26 -> AA)
 */
function excel_live_col_letter(int $index): string
{
    $letter = '';
    $n = $index + 1;
    while ($n > 0) {
        $mod = ($n - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $n = intdiv($n - 1, 26);
    }
    return $letter;
}

/**
 * Pure-PHP ZIP archive builder (PKZIP 2.0 deflate, no ZipArchive extension needed)
 * @param array<string, string> $files map of relative path => binary content
 */
function excel_live_zip_create(array $files): string
{
    $zipData = '';
    $centralDir = '';
    $offset = 0;
    $count = 0;

    $now = getdate();
    $dosTime = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | (($now['seconds'] >> 1) & 0x1F);
    $dosDate = ((max(1980, $now['year']) - 1980) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);

    foreach ($files as $name => $content) {
        $name = ltrim(str_replace('\\', '/', (string) $name), '/');
        $uncompressedSize = strlen($content);
        $crc = crc32($content);
        $compressed = gzdeflate($content, 6);
        if ($compressed === false) {
            $compressed = $content;
            $method = 0;
        } else {
            $method = 8;
        }
        $compressedSize = strlen($compressed);
        $nameLen = strlen($name);

        // Local file header (30 bytes + filename)
        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034B50, // local file header signature
            20,         // version needed to extract (2.0)
            0x0800,     // general purpose bit flag (UTF-8 filenames)
            $method,
            $dosTime,
            $dosDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            $nameLen,
            0           // extra field length
        );

        $zipData .= $localHeader . $name . $compressed;

        // Central directory header (46 bytes + filename)
        $centralDir .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014B50, // central file header signature
            20,         // version made by
            20,         // version needed to extract
            0x0800,     // UTF-8 flag
            $method,
            $dosTime,
            $dosDate,
            $crc,
            $compressedSize,
            $uncompressedSize,
            $nameLen,
            0,          // extra length
            0,          // comment length
            0,          // disk number start
            0,          // internal file attributes
            0,          // external file attributes
            $offset     // relative offset of local header
        ) . $name;

        $offset += strlen($localHeader) + $nameLen + $compressedSize;
        $count++;
    }

    $centralDirSize = strlen($centralDir);
    $endOfCentralDir = pack(
        'VvvvvVVv',
        0x06054B50,      // end of central dir signature
        0,               // number of this disk
        0,               // disk with start of central dir
        $count,          // entries on this disk
        $count,          // total entries
        $centralDirSize,
        $offset,
        0                // comment length
    );

    return $zipData . $centralDir . $endOfCentralDir;
}

/**
 * Build a live-connected .xlsx workbook containing snapshot data + OpenXML Web Connections
 * (auto-refreshes on open + periodic background refresh every N minutes + manual Refresh All)
 */
function excel_live_build_xlsx(array $sheetTypes = ['summary', 'products', 'receipts', 'issues', 'borrows'], ?string $baseUrl = null, ?int $refreshInterval = null): string
{
    $valid = excel_live_valid_types();
    $selectedTypes = [];
    foreach ($sheetTypes as $t) {
        if (in_array($t, $valid, true) && !in_array($t, $selectedTypes, true)) {
            $selectedTypes[] = $t;
        }
    }
    if ($selectedTypes === []) {
        $selectedTypes = ['products'];
    }

    $interval = $refreshInterval !== null ? max(0, min(60, $refreshInterval)) : excel_live_refresh_interval();
    $intervalAttr = $interval > 0 ? ' interval="' . $interval . '"' : '';

    $datasets = [];
    foreach ($selectedTypes as $t) {
        $datasets[] = excel_live_dataset($t, 10000);
    }

    $files = [];
    $sheetCount = count($datasets);

    // 1. [Content_Types].xml
    $ctOverrides = '';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $ctOverrides .= '  <Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . "\n";
        $ctOverrides .= '  <Override PartName="/xl/queryTables/queryTable' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.queryTable+xml"/>' . "\n";
    }
    $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' . "\n"
        . '  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n"
        . '  <Default Extension="xml" ContentType="application/xml"/>' . "\n"
        . '  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . "\n"
        . '  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . "\n"
        . '  <Override PartName="/xl/connections.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.connections+xml"/>' . "\n"
        . $ctOverrides
        . '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n"
        . '  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . "\n"
        . '</Types>';

    // 2. _rels/.rels
    $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
        . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' . "\n"
        . '  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>' . "\n"
        . '  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>' . "\n"
        . '</Relationships>';

    // 3. docProps/core.xml & docProps/app.xml
    $isoNow = gmdate('Y-m-d\TH:i:s\Z');
    $files['docProps/core.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:dcterms="http://purl.org/dc/terms/"'
        . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">' . "\n"
        . '  <dc:title>Store Automeation Live Inventory</dc:title>' . "\n"
        . '  <dc:creator>Store Automeation</dc:creator>' . "\n"
        . '  <dcterms:created xsi:type="dcterms:W3CDTF">' . $isoNow . '</dcterms:created>' . "\n"
        . '  <dcterms:modified xsi:type="dcterms:W3CDTF">' . $isoNow . '</dcterms:modified>' . "\n"
        . '</cp:coreProperties>';

    $sheetTitleTags = '';
    foreach ($datasets as $ds) {
        $sheetTitleTags .= '<vt:lpstr>' . excel_live_xml_escape($ds['meta']['sheet']) . '</vt:lpstr>';
    }
    $files['docProps/app.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"'
        . ' xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">' . "\n"
        . '  <Application>Store Automeation Live Sync</Application>' . "\n"
        . '  <HeadingPairs><vt:vector size="2" baseType="variant"><vt:variant><vt:lpstr>Worksheets</vt:lpstr></vt:variant><vt:variant><vt:i4>' . $sheetCount . '</vt:i4></vt:variant></vt:vector></HeadingPairs>' . "\n"
        . '  <TitlesOfParts><vt:vector size="' . $sheetCount . '" baseType="lpstr">' . $sheetTitleTags . '</vt:vector></TitlesOfParts>' . "\n"
        . '</Properties>';

    // 4. xl/styles.xml
    // Style indices (cellXfs):
    // 0: Default body text (@)
    // 1: Header row (bold white on #0758B4, center)
    // 2: Body text (@) with thin border
    // 3: Body integer (#,##0) with thin border
    // 4: Body decimal (#,##0.00) with thin border
    $files['xl/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
        . '  <numFmts count="2">' . "\n"
        . '    <numFmt numFmtId="164" formatCode="#,##0"/>' . "\n"
        . '    <numFmt numFmtId="165" formatCode="#,##0.00"/>' . "\n"
        . '  </numFmts>' . "\n"
        . '  <fonts count="2">' . "\n"
        . '    <font><sz val="11"/><color rgb="FF0F172A"/><name val="Tahoma"/></font>' . "\n"
        . '    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>' . "\n"
        . '  </fonts>' . "\n"
        . '  <fills count="3">' . "\n"
        . '    <fill><patternFill patternType="none"/></fill>' . "\n"
        . '    <fill><patternFill patternType="gray125"/></fill>' . "\n"
        . '    <fill><patternFill patternType="solid"><fgColor rgb="FF0758B4"/><bgColor indexed="64"/></patternFill></fill>' . "\n"
        . '  </fills>' . "\n"
        . '  <borders count="2">' . "\n"
        . '    <border><left/><right/><top/><bottom/><diagonal/></border>' . "\n"
        . '    <border>'
        . '<left style="thin"><color rgb="FFCBD5E1"/></left>'
        . '<right style="thin"><color rgb="FFCBD5E1"/></right>'
        . '<top style="thin"><color rgb="FFCBD5E1"/></top>'
        . '<bottom style="thin"><color rgb="FFCBD5E1"/></bottom>'
        . '<diagonal/>'
        . '</border>' . "\n"
        . '  </borders>' . "\n"
        . '  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' . "\n"
        . '  <cellXfs count="5">' . "\n"
        . '    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . "\n"
        . '    <xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . "\n"
        . '    <xf numFmtId="49" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' . "\n"
        . '    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n"
        . '    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' . "\n"
        . '  </cellXfs>' . "\n"
        . '  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' . "\n"
        . '</styleSheet>';

    // 5. Build worksheets, queryTables, connections, workbook & rels
    $sheetsXml = '';
    $definedNamesXml = '';
    $wbRelsXml = '';
    $connectionsXml = '';

    foreach ($datasets as $idx => $ds) {
        $sheetNum = $idx + 1;
        $sheetName = $ds['meta']['sheet'];
        $connName = $ds['meta']['conn'];
        $columns = $ds['columns'];
        $rows = $ds['rows'];
        $colCount = count($columns);
        $lastColLetter = excel_live_col_letter($colCount - 1);
        $totalRows = max(1, count($rows) + 1);
        $rangeRef = 'A1:' . $lastColLetter . $totalRows;

        $sheetsXml .= '    <sheet name="' . excel_live_xml_escape($sheetName) . '" sheetId="' . $sheetNum . '" r:id="rId' . $sheetNum . '"/>' . "\n";
        $safeFormulaSheet = str_replace("'", "''", $sheetName);
        $definedNamesXml .= '    <definedName name="' . excel_live_xml_escape($connName) . '" localSheetId="' . $idx . '">\'' . excel_live_xml_escape($safeFormulaSheet) . '\'!$A$1:$' . $lastColLetter . '$' . $totalRows . '</definedName>' . "\n";
        $wbRelsXml .= '  <Relationship Id="rId' . $sheetNum . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>' . "\n";

        // Connection pointing to live HTML feed
        $liveUrl = excel_live_feed_url($ds['type'], 'html', $baseUrl);
        $connectionsXml .= '  <connection id="' . $sheetNum . '" name="' . excel_live_xml_escape($connName) . '"'
            . ' description="' . excel_live_xml_escape($ds['meta']['title']) . '"'
            . ' type="4" refreshedVersion="8" minRefreshableVersion="3" background="1" refreshOnLoad="1" saveData="1"' . $intervalAttr . '>' . "\n"
            . '    <webPr xml="0" sourceData="0" parsePre="1" consecutive="1" firstRow="0" xl97="0" textDates="1" xl2000="1"'
            . ' url="' . excel_live_xml_escape($liveUrl) . '" htmlFormat="rtf" htmlTables="1">' . "\n"
            . '      <tables count="1">' . "\n"
            . '        <s v="store-data-table"/>' . "\n"
            . '      </tables>' . "\n"
            . '    </webPr>' . "\n"
            . '  </connection>' . "\n";

        // xl/queryTables/queryTable{n}.xml
        $files['xl/queryTables/queryTable' . $sheetNum . '.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<queryTable xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' name="' . excel_live_xml_escape($connName) . '"'
            . ' connectionId="' . $sheetNum . '"'
            . ' headers="0"'
            . ' preserveFormatting="1"'
            . ' adjustColumnWidth="1"'
            . ' refreshOnLoad="1"/>';

        // xl/worksheets/_rels/sheet{n}.xml.rels
        $files['xl/worksheets/_rels/sheet' . $sheetNum . '.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
            . '  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/queryTable" Target="../queryTables/queryTable' . $sheetNum . '.xml"/>' . "\n"
            . '</Relationships>';

        // xl/worksheets/sheet{n}.xml
        $colsXml = "  <cols>\n";
        foreach ($columns as $cIdx => $col) {
            $colNum = $cIdx + 1;
            $width = (float) ($col['width'] ?? 16);
            $colsXml .= '    <col min="' . $colNum . '" max="' . $colNum . '" width="' . $width . '" customWidth="1"/>' . "\n";
        }
        $colsXml .= "  </cols>\n";

        $sheetDataXml = "  <sheetData>\n";
        // Header row (Row 1)
        $sheetDataXml .= '    <row r="1" ht="24" customHeight="1">';
        foreach ($columns as $cIdx => $col) {
            $cellRef = excel_live_col_letter($cIdx) . '1';
            $sheetDataXml .= '<c r="' . $cellRef . '" s="1" t="inlineStr"><is><t xml:space="preserve">' . excel_live_xml_escape($col['label']) . '</t></is></c>';
        }
        $sheetDataXml .= "</row>\n";

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            $sheetDataXml .= '    <row r="' . $rowNum . '">';
            foreach ($columns as $cIdx => $col) {
                $cellRef = excel_live_col_letter($cIdx) . $rowNum;
                $val = $row[$cIdx] ?? '';
                $colType = $col['type'] ?? 'text';
                if ($colType === 'int' && is_numeric($val) && $val !== '') {
                    $sheetDataXml .= '<c r="' . $cellRef . '" s="3"><v>' . (int) round((float) $val) . '</v></c>';
                } elseif ($colType === 'decimal' && is_numeric($val) && $val !== '') {
                    $sheetDataXml .= '<c r="' . $cellRef . '" s="4"><v>' . number_format((float) $val, 2, '.', '') . '</v></c>';
                } else {
                    $sheetDataXml .= '<c r="' . $cellRef . '" s="2" t="inlineStr"><is><t xml:space="preserve">' . excel_live_xml_escape((string) $val) . '</t></is></c>';
                }
            }
            $sheetDataXml .= "</row>\n";
            $rowNum++;
        }
        $sheetDataXml .= "  </sheetData>\n";

        $tabSelected = $idx === 0 ? ' tabSelected="1"' : '';
        $files['xl/worksheets/sheet' . $sheetNum . '.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n"
            . '  <dimension ref="' . $rangeRef . '"/>' . "\n"
            . '  <sheetViews>' . "\n"
            . '    <sheetView' . $tabSelected . ' workbookViewId="0">' . "\n"
            . '      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>' . "\n"
            . '    </sheetView>' . "\n"
            . '  </sheetViews>' . "\n"
            . '  <sheetFormatPr defaultRowHeight="20"/>' . "\n"
            . $colsXml
            . $sheetDataXml
            . '  <autoFilter ref="' . $rangeRef . '"/>' . "\n"
            . '</worksheet>';
    }

    // 6. xl/connections.xml
    $files['xl/connections.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<connections xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . "\n"
        . $connectionsXml
        . '</connections>';

    // 7. xl/workbook.xml & xl/_rels/workbook.xml.rels
    $stylesRid = 'rId' . ($sheetCount + 1);
    $connsRid = 'rId' . ($sheetCount + 2);
    $wbRelsXml .= '  <Relationship Id="' . $stylesRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' . "\n";
    $wbRelsXml .= '  <Relationship Id="' . $connsRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/connections" Target="connections.xml"/>' . "\n";

    $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . "\n"
        . $wbRelsXml
        . '</Relationships>';

    $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . "\n"
        . '  <bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="14000"/></bookViews>' . "\n"
        . '  <sheets>' . "\n"
        . $sheetsXml
        . '  </sheets>' . "\n"
        . '  <definedNames>' . "\n"
        . $definedNamesXml
        . '  </definedNames>' . "\n"
        . '</workbook>';

    return excel_live_zip_create($files);
}
