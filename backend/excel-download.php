<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/excel_live.php';

auth_require_login();

if (!excel_live_is_enabled()) {
    inventory_flash_redirect('ระบบ Excel Live ถูกปิดใช้งานชั่วคราว กรุณาติดต่อผู้ดูแลระบบ', 'excel-live.php', 'error');
}

$mode = isset($_GET['mode']) && is_string($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'xlsx';
$type = isset($_GET['type']) && is_string($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
$interval = isset($_GET['interval']) && is_numeric($_GET['interval'])
    ? max(0, min(60, (int) $_GET['interval']))
    : excel_live_refresh_interval();

$baseUrl = excel_live_base_url();

// Clean any accidental output buffers before streaming binary file
while (ob_get_level() > 0) {
    ob_end_clean();
}

if ($mode === 'iqy') {
    $safeType = in_array($type, excel_live_valid_types(), true) ? $type : 'products';
    $meta = excel_live_type_meta($safeType);
    $content = excel_live_build_iqy($safeType, $baseUrl);
    $filename = $meta['file'] . '_live.iqy';

    audit_log('export', 'excel_live_iqy', 'ดาวน์โหลดไฟล์ Excel Web Query (.iqy) ประเภท ' . $safeType);

    header_remove('Pragma');
    header_remove('Expires');
    header('Content-Type: text/x-ms-iqy; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) strlen($content));
    header('Cache-Control: private, must-revalidate, max-age=0');
    header('Expires: 0');
    echo $content;
    exit;
}

// Default: Live-connected .xlsx workbook
if ($type === 'all') {
    $sheetTypes = ['summary', 'products', 'receipts', 'issues', 'borrows'];
    $filename = 'Store_Automeation_Live.xlsx';
    $logDetail = 'ดาวน์โหลดไฟล์ Excel Live อัปเดตอัตโนมัติ (ครบ 5 ชีต, รีเฟรชทุก ' . ($interval > 0 ? $interval . ' นาที' : 'ตอนเปิดไฟล์') . ')';
} elseif (in_array($type, excel_live_valid_types(), true)) {
    $sheetTypes = [$type];
    $meta = excel_live_type_meta($type);
    $filename = $meta['file'] . '_live.xlsx';
    $logDetail = 'ดาวน์โหลดไฟล์ Excel Live อัปเดตอัตโนมัติ (' . $meta['title'] . ')';
} else {
    $sheetTypes = ['summary', 'products', 'receipts', 'issues', 'borrows'];
    $filename = 'Store_Automeation_Live.xlsx';
    $logDetail = 'ดาวน์โหลดไฟล์ Excel Live อัปเดตอัตโนมัติ (ครบ 5 ชีต)';
}

$xlsxBinary = excel_live_build_xlsx($sheetTypes, $baseUrl, $interval);

audit_log('export', 'excel_live_xlsx', $logDetail);

header_remove('Pragma');
header_remove('Expires');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) strlen($xlsxBinary));
header('Cache-Control: private, must-revalidate, max-age=0');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

echo $xlsxBinary;
exit;
