<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

try {
    $db = inventory_db();
    $type = (string) ($_GET['type'] ?? 'all'); // receipts, issues, or all
    $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 50;

    $receipts = [];
    $issues = [];

    if ($type === 'all' || $type === 'receipts') {
        $stmt = $db->prepare('SELECT id, date, product_name, product_code, quantity, unit, lot_number, document_number, received_by, remarks, created_at FROM receipts ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $receipts = $stmt->fetchAll();
    }

    if ($type === 'all' || $type === 'issues') {
        $stmt = $db->prepare('SELECT id, date, product_name, product_code, quantity, unit, document_number, requested_by, approved_by, department, reason, remarks, created_at FROM issues ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $issues = $stmt->fetchAll();
    }

    echo json_encode([
        'success'   => true,
        'type'      => $type,
        'receipts'  => $receipts,
        'issues'    => $issues,
        'timestamp' => time(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
