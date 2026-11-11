<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_login();

$type = isset($_GET['type']) && is_string($_GET['type']) ? trim($_GET['type']) : 'products';
$filename = 'store_' . $type . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 Byte Order Mark (BOM) for Excel Thai language compatibility
fwrite($output, "\xEF\xBB\xBF");

if ($type === 'products') {
    fputcsv($output, [
        'รหัสสินค้า',
        'ชื่อสินค้า',
        'หมวดหมู่',
        'ประเภท',
        'หน่วยนับ',
        'ตำแหน่งจัดเก็บ',
        'จุดสั่งซื้อขั้นต่ำ',
        'รับเข้ารวม',
        'เบิกออกรวม',
        'คงเหลือ',
        'สถานะสต๊อก',
    ]);

    $products = inventory_products();
    foreach ($products as $p) {
        $status = $p['stock'] < 0 ? 'สต๊อกติดลบ' : ($p['stock'] <= $p['minimum'] ? 'ควรสั่งซื้อ' : 'ปกติ');
        fputcsv($output, [
            $p['id'],
            $p['name'],
            $p['category'],
            $p['type'],
            $p['unit'],
            $p['location'],
            $p['minimum'],
            $p['received'],
            $p['issued'],
            $p['stock'],
            $status,
        ]);
    }

    audit_log('export', 'products', 'ส่งออกรายงานทะเบียนสินค้าเป็น CSV/Excel');
} elseif ($type === 'receipts') {
    $receipts = inventory_movements('receipt', 2000);
    fputcsv($output, [
        'วันที่รับ',
        'เลขที่เอกสาร',
        'รหัสสินค้า',
        'ชื่อสินค้า',
        'จำนวนรับ',
        'หน่วยนับ',
        'ผู้ส่งมอบ',
        'ผู้รับสินค้า',
        'แผนก/หน่วยงาน',
        'หมายเหตุ',
    ]);

    foreach ($receipts as $r) {
        fputcsv($output, [
            $r['movement_date'],
            $r['document_no'],
            $r['product_code'],
            $r['product_name'],
            $r['quantity'],
            $r['unit'],
            $r['supplier'],
            $r['received_by'],
            $r['department'],
            $r['notes'],
        ]);
    }

    audit_log('export', 'receipts', 'ส่งออกประวัติรับสินค้าเป็น CSV/Excel');
} elseif ($type === 'issues') {
    $issues = inventory_movements('issue', 2000);
    fputcsv($output, [
        'วันที่เบิก',
        'เลขที่เอกสาร',
        'รหัสสินค้า',
        'ชื่อสินค้า',
        'จำนวนเบิก',
        'หน่วยนับ',
        'ผู้เบิก',
        'แผนก/ผู้รับ',
        'ผู้อนุมัติ',
        'หมายเหตุ',
    ]);

    foreach ($issues as $i) {
        fputcsv($output, [
            $i['movement_date'],
            $i['document_no'],
            $i['product_code'],
            $i['product_name'],
            $i['quantity'],
            $i['unit'],
            $i['requested_by'],
            $i['department'],
            $i['approved_by'],
            $i['notes'],
        ]);
    }

    audit_log('export', 'issues', 'ส่งออกประวัติเบิกสินค้าเป็น CSV/Excel');
} elseif ($type === 'history') {
    $receipts = inventory_movements('receipt', 2000);
    $issues = inventory_movements('issue', 2000);

    fputcsv($output, [
        'ประเภท',
        'วันที่',
        'เลขที่เอกสาร',
        'รหัสสินค้า',
        'ชื่อสินค้า',
        'จำนวน',
        'หน่วยนับ',
        'ผู้เกี่ยวข้อง 1 (ผู้ส่งมอบ / ผู้เบิก)',
        'ผู้เกี่ยวข้อง 2 (ผู้รับ / ผู้อนุมัติ)',
        'แผนก/หน่วยงาน',
        'หมายเหตุ',
    ]);

    $all = [];
    foreach ($receipts as $r) {
        $all[] = [
            'type' => 'รับเข้า',
            'date' => $r['movement_date'],
            'doc' => $r['document_no'],
            'code' => $r['product_code'],
            'name' => $r['product_name'],
            'qty' => '+' . $r['quantity'],
            'unit' => $r['unit'],
            'person1' => $r['supplier'],
            'person2' => $r['received_by'],
            'dept' => $r['department'],
            'notes' => $r['notes'],
        ];
    }
    foreach ($issues as $i) {
        $all[] = [
            'type' => 'เบิกออก',
            'date' => $i['movement_date'],
            'doc' => $i['document_no'],
            'code' => $i['product_code'],
            'name' => $i['product_name'],
            'qty' => '-' . $i['quantity'],
            'unit' => $i['unit'],
            'person1' => $i['requested_by'],
            'person2' => $i['approved_by'],
            'dept' => $i['department'],
            'notes' => $i['notes'],
        ];
    }

    usort($all, static fn($a, $b) => strcmp($b['date'], $a['date']));

    foreach ($all as $row) {
        fputcsv($output, array_values($row));
    }

    audit_log('export', 'history', 'ส่งออกประวัติการเคลื่อนไหวรวมเป็น CSV/Excel');
}

fclose($output);
exit;

