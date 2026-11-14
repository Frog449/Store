<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $db = inventory_db();

    $receiptRow = $db->query('SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS total FROM receipts')->fetch();
    $issueRow   = $db->query('SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS total FROM issues')->fetch();
    $borrowRow  = $db->query('SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS total FROM borrow_records')->fetch();
    $productRow = $db->query('SELECT COUNT(*) AS total, COALESCE(SUM(คงเหลือ), 0) AS stock_sum, COALESCE(SUM(รับเข้ารวม), 0) AS recv_sum, COALESCE(SUM(เบิกออกรวม), 0) AS issue_sum FROM product')->fetch();
    $auditRow   = $db->query('SELECT COALESCE(MAX(id), 0) AS max_id, COUNT(*) AS total FROM audit_logs')->fetch();

    $versionHash = md5(implode(':', [
        $receiptRow['max_id'] ?? 0,
        $receiptRow['total'] ?? 0,
        $issueRow['max_id'] ?? 0,
        $issueRow['total'] ?? 0,
        $borrowRow['max_id'] ?? 0,
        $borrowRow['total'] ?? 0,
        $productRow['total'] ?? 0,
        $productRow['stock_sum'] ?? 0,
        $productRow['recv_sum'] ?? 0,
        $productRow['issue_sum'] ?? 0,
        $auditRow['max_id'] ?? 0,
        $auditRow['total'] ?? 0,
    ]));

    echo json_encode([
        'success'   => true,
        'version'   => $versionHash,
        'receipts'  => [
            'max_id' => (int) ($receiptRow['max_id'] ?? 0),
            'total'  => (int) ($receiptRow['total'] ?? 0),
        ],
        'issues'    => [
            'max_id' => (int) ($issueRow['max_id'] ?? 0),
            'total'  => (int) ($issueRow['total'] ?? 0),
        ],
        'borrows'   => [
            'max_id' => (int) ($borrowRow['max_id'] ?? 0),
            'total'  => (int) ($borrowRow['total'] ?? 0),
        ],
        'products'  => [
            'total'     => (int) ($productRow['total'] ?? 0),
            'stock_sum' => (float) ($productRow['stock_sum'] ?? 0),
        ],
        'timestamp' => time(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
