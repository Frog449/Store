<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

try {
    $db = inventory_db();
    $q = trim((string) ($_GET['q'] ?? ''));
    $category = trim((string) ($_GET['category'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? 'all'));
    $limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 250;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

    $allProducts = inventory_products();
    $categories = inventory_category_options();

    $filtered = [];
    foreach ($allProducts as $p) {
        $code = (string) ($p['code'] ?? '');
        $name = (string) ($p['name'] ?? '');
        $cat = (string) ($p['category'] ?? '');
        $stock = (float) ($p['stock'] ?? 0);
        $min = (float) ($p['minimum'] ?? 0);

        // Status determination
        $statusKey = 'normal';
        if ($stock <= 0) {
            $statusKey = 'negative';
        } elseif ($stock <= $min) {
            $statusKey = 'reorder';
        }

        // Filter by keyword
        if ($q !== '') {
            $searchStr = mb_strtolower($code . ' ' . $name . ' ' . $cat . ' ' . ($p['location'] ?? ''), 'UTF-8');
            $needle = mb_strtolower($q, 'UTF-8');
            if (!str_contains($searchStr, $needle)) {
                continue;
            }
        }

        // Filter by category
        if ($category !== '' && $category !== 'all' && $cat !== $category) {
            continue;
        }

        // Filter by status
        if ($status !== '' && $status !== 'all' && $statusKey !== $status) {
            continue;
        }

        $imagePath = !empty($p['image']) ? '../image.php?code=' . rawurlencode($code) : null;

        $filtered[] = [
            'code'           => $code,
            'name'           => $name,
            'category'       => $cat,
            'type'           => (string) ($p['type'] ?? ''),
            'unit'           => (string) ($p['unit'] ?? 'ชิ้น'),
            'location'       => (string) ($p['location'] ?? ''),
            'minimum'        => $min,
            'stock'          => $stock,
            'total_received' => (float) ($p['total_received'] ?? 0),
            'total_issued'   => (float) ($p['total_issued'] ?? 0),
            'status'         => $statusKey,
            'image'          => $p['image'] ?? null,
            'image_url'      => $imagePath,
        ];
    }

    $totalFiltered = count($filtered);
    $sliced = array_slice($filtered, $offset, $limit);

    echo json_encode([
        'success'    => true,
        'total'      => $totalFiltered,
        'limit'      => $limit,
        'offset'     => $offset,
        'categories' => $categories,
        'data'       => $sliced,
        'timestamp'  => time(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
