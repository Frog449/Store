<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/excel_live.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Excel-Token');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

if (!excel_live_is_enabled()) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'ระบบ Excel Live Feed ถูกปิดใช้งานชั่วคราวโดยผู้ดูแลระบบ',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$providedToken = isset($_GET['token']) && is_string($_GET['token'])
    ? trim($_GET['token'])
    : trim((string) ($_SERVER['HTTP_X_EXCEL_TOKEN'] ?? ''));

// Also allow already logged-in browser sessions to preview without token
$isLoggedIn = function_exists('auth_user') && auth_user() !== null;
if (!$isLoggedIn && !excel_live_verify_token($providedToken)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Token ไม่ถูกต้องหรือหมดอายุ กรุณาดาวน์โหลดไฟล์ Excel หรือคัดลอกลิงก์ใหม่จากหน้าเว็บ',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$type = isset($_GET['type']) && is_string($_GET['type']) ? strtolower(trim($_GET['type'])) : 'products';
if (!in_array($type, excel_live_valid_types(), true)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'ไม่รองรับประเภทข้อมูลนี้ (รองรับ: ' . implode(', ', excel_live_valid_types()) . ')',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$format = isset($_GET['format']) && is_string($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
if (!in_array($format, ['json', 'csv', 'html'], true)) {
    $format = 'json';
}

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(50000, (int) $_GET['limit'])) : 10000;

try {
    if ($format === 'html') {
        header('Content-Type: text/html; charset=UTF-8');
        echo excel_live_render_html($type, $limit);
    } elseif ($format === 'csv') {
        $meta = excel_live_type_meta($type);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $meta['file'] . '.csv"');
        echo excel_live_render_csv($type, $limit);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo excel_live_render_json($type, $limit);
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'ไม่สามารถดึงข้อมูลได้: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
