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

/**
 * Format string as Excel text formula to prevent:
 * 1. Date column overflow (########) when column width is narrow in Excel
 * 2. Long numeric codes converting into scientific notation (e.g. 3.2E+11)
 * 3. Leading zeroes from being stripped (e.g. 00123 -> 123)
 */
function csv_excel_text(mixed $value): string
{
    $v = (string) ($value ?? '');
    if ($v === '' || $v === '-') {
        return $v;
    }
    $escaped = str_replace('"', '""', $v);
    return '="' . $escaped . '"';
}

if ($type === 'products') {
    fputcsv($output, [
        'Account Code1',
        'Stock Code',
        'GL Description',
        'Name',
        'Unit',
        'Quantity',
        'Section code',
        'Section Name',
        'Apex',
        'Buydate',
        'เวลาBuydate',
        'BuyPrice/Unit',
        'Cal No.',
        'Cal date',
        'Cal Exp.',
        'จุดสั่งซื้อขั้นต่ำ',
    ]);

    $products = inventory_products();
    foreach ($products as $p) {
        $sc = inventory_clean_stock_code((string) ($p['stock_code'] ?? $p['id'] ?? ''));
        $ac1 = trim((string) ($p['account_code1'] ?? ''));
        if ($ac1 === '') {
            $ac1 = '-';
        }
        $glDesc = trim((string) ($p['gl_description'] ?? '')) !== '' ? $p['gl_description'] : ($p['category'] ?? '');
        $buyParts = inventory_split_buy_datetime((string) ($p['buy_date'] ?? ''));
        fputcsv($output, [
            csv_excel_text($ac1),
            csv_excel_text($sc),
            $glDesc,
            $p['name'],
            $p['unit'],
            $p['stock'],
            csv_excel_text($p['section_code'] ?? ''),
            $p['section_name'] ?? '',
            $p['apex'] ?? '',
            csv_excel_text($buyParts['date']),
            csv_excel_text($buyParts['time']),
            $p['buy_price'] !== null ? $p['buy_price'] : '',
            csv_excel_text($p['cal_no']),
            csv_excel_text($p['cal_date']),
            csv_excel_text($p['cal_exp']),
            $p['minimum'],
        ]);
    }

    audit_log('export', 'products', 'ส่งออกรายงานรายการสินค้าเป็น CSV/Excel');
} elseif ($type === 'receipts') {
    $receipts = inventory_movements('receipt', 2000);
    fputcsv($output, [
        'วันที่รับ',
        'เลขที่เอกสาร',
        'Stock Code',
        'Account Code1',
        'ชื่อสินค้า',
        'จำนวนรับ',
        'หน่วยนับ',
        'ผู้รับสินค้า',
        'หมายเหตุ',
    ]);

    foreach ($receipts as $r) {
        $sc = inventory_clean_stock_code((string) ($r['stock_code'] ?? $r['product_code'] ?? ''));
        $ac1 = trim((string) ($r['account_code1'] ?? ''));
        if ($ac1 === '') {
            $ac1 = '-';
        }
        fputcsv($output, [
            csv_excel_text($r['movement_date']),
            csv_excel_text($r['document_no']),
            csv_excel_text($sc),
            csv_excel_text($ac1),
            $r['product_name'],
            $r['quantity'],
            $r['unit'],
            $r['received_by'],
            $r['notes'],
        ]);
    }

    audit_log('export', 'receipts', 'ส่งออกประวัติรับสินค้าเป็น CSV/Excel');
} elseif ($type === 'issues') {
    $issues = inventory_movements('issue', 2000);
    fputcsv($output, [
        'วันที่เบิก',
        'เลขที่เอกสาร',
        'Stock Code',
        'Account Code1',
        'ชื่อสินค้า',
        'จำนวนเบิก',
        'หน่วยนับ',
        'ผู้เบิก',
        'ผู้อนุมัติ',
        'หมายเหตุ',
    ]);

    foreach ($issues as $i) {
        $sc = inventory_clean_stock_code((string) ($i['stock_code'] ?? $i['product_code'] ?? ''));
        $ac1 = trim((string) ($i['account_code1'] ?? ''));
        if ($ac1 === '') {
            $ac1 = '-';
        }
        fputcsv($output, [
            csv_excel_text($i['movement_date']),
            csv_excel_text($i['document_no']),
            csv_excel_text($sc),
            csv_excel_text($ac1),
            $i['product_name'],
            $i['quantity'],
            $i['unit'],
            $i['requested_by'],
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
        'Stock Code',
        'Account Code1',
        'ชื่อสินค้า',
        'จำนวน',
        'หน่วยนับ',
        'ผู้รับสินค้า / ผู้เบิก',
        'ผู้อนุมัติ',
        'หมายเหตุ',
    ]);

    $all = [];
    foreach ($receipts as $r) {
        $sc = inventory_clean_stock_code((string) ($r['stock_code'] ?? $r['product_code'] ?? ''));
        $ac1 = trim((string) ($r['account_code1'] ?? ''));
        $all[] = [
            'type' => 'รับเข้า',
            'date' => $r['movement_date'],
            'doc' => $r['document_no'],
            'sc' => $sc,
            'ac1' => $ac1 !== '' ? $ac1 : '-',
            'name' => $r['product_name'],
            'qty' => '+' . $r['quantity'],
            'unit' => $r['unit'],
            'person1' => $r['received_by'],
            'person2' => '-',
            'notes' => $r['notes'],
        ];
    }
    foreach ($issues as $i) {
        $sc = inventory_clean_stock_code((string) ($i['stock_code'] ?? $i['product_code'] ?? ''));
        $ac1 = trim((string) ($i['account_code1'] ?? ''));
        $all[] = [
            'type' => 'เบิกออก',
            'date' => $i['movement_date'],
            'doc' => $i['document_no'],
            'sc' => $sc,
            'ac1' => $ac1 !== '' ? $ac1 : '-',
            'name' => $i['product_name'],
            'qty' => '-' . $i['quantity'],
            'unit' => $i['unit'],
            'person1' => $i['requested_by'],
            'person2' => $i['approved_by'],
            'notes' => $i['notes'],
        ];
    }

    usort($all, static fn($a, $b) => strcmp($b['date'], $a['date']));

    foreach ($all as $row) {
        fputcsv($output, [
            $row['type'],
            csv_excel_text($row['date']),
            csv_excel_text($row['doc']),
            csv_excel_text($row['sc']),
            csv_excel_text($row['ac1']),
            $row['name'],
            $row['qty'],
            $row['unit'],
            $row['person1'],
            $row['person2'],
            $row['notes'],
        ]);
    }

    audit_log('export', 'history', 'ส่งออกประวัติการเคลื่อนไหวรวมเป็น CSV/Excel');
}

fclose($output);
exit;
