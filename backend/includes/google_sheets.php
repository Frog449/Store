<?php
declare(strict_types=1);

/**
 * Google Sheets Webhook Real-time Synchronization Service
 * Store Automeation
 */

function google_sheets_get_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = inventory_get_settings();
    }
    return $settings;
}

/**
 * Send JSON payload to Google Apps Script Web App URL
 */
function google_sheets_post(array $payload): array
{
    $settings = google_sheets_get_settings();
    $enabled = ($settings['google_sheet_sync_enabled'] ?? '0') === '1';
    $webhookUrl = trim($settings['google_sheet_webhook_url'] ?? '');

    // Auto fix if user pasted /edit instead of /exec
    if (str_ends_with($webhookUrl, '/edit')) {
        $webhookUrl = substr($webhookUrl, 0, -5) . '/exec';
    }

    if (!$enabled || $webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'message' => 'Google Sheets Sync is disabled or invalid Web App URL.'];
    }

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true, // Handle 302 redirects of Google Apps Script
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode((string) $response, true);
        if (is_array($responseData)) {
            if (isset($responseData['status']) && $responseData['status'] === 'error') {
                return [
                    'success' => false,
                    'message' => 'Apps Script Error: ' . ($responseData['message'] ?? 'เกิดข้อผิดพลาดใน Google Sheets script'),
                ];
            }
            if (isset($responseData['status']) && $responseData['status'] === 'ok') {
                return [
                    'success' => true,
                    'message' => (string) ($responseData['message'] ?? 'บันทึกลง Google Sheet สำเร็จ'),
                ];
            }
        }

        return [
            'success' => $httpCode >= 200 && $httpCode < 400,
            'message' => 'HTTP ' . $httpCode . ': ' . substr((string) $response, 0, 160),
        ];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Real-time sync for Receipt / Issue movement & update stock in Sheet
 */
function google_sheets_sync_movement(array $movement, string $kind, ?array $product = null, bool $isNewProduct = false): array
{
    $isReceipt = $kind === 'receipt';
    $qty = (float) ($movement['quantity'] ?? 0);
    $unit = $product['unit'] ?? ($movement['unit'] ?? 'ชิ้น');
    $currentStock = isset($product['stock']) ? (float) $product['stock'] : 0.0;

    $payload = [
        'event'          => 'inventory_movement',
        'movement_type'  => $isReceipt ? 'receipt' : 'issue',
        'movement_id'    => (int) ($movement['id'] ?? 0),
        'movement_date'  => $movement['movement_date'] ?? date('Y-m-d'),
        'document_no'    => $movement['document_no'] ?? '',
        'product_code'   => $product['code'] ?? ($movement['product_code'] ?? ''),
        'product_name'   => $product['name'] ?? ($movement['product_name'] ?? ''),
        'category'       => $product['category'] ?? '',
        'quantity'       => $qty,
        'quantity_sign'  => $isReceipt ? $qty : -$qty,
        'unit'           => $unit,
        'current_stock'  => $currentStock,
        'location'       => $product['location'] ?? '',
        'person_primary' => $isReceipt ? ($movement['received_by'] ?? '') : ($movement['requested_by'] ?? ''),
        'department'     => $movement['department'] ?? '',
        'notes'          => $movement['notes'] ?? '',
        'created_by'     => $movement['created_by_name'] ?? 'เจ้าหน้าที่',
        'timestamp'      => date('Y-m-d H:i:s'),
        'is_new_product' => $isNewProduct,
    ];

    return google_sheets_post($payload);
}

/**
 * Real-time sync for Borrow / Return in Google Sheets
 */
function google_sheets_sync_borrow(array $record, string $actionType = 'borrow'): array
{
    $isBorrow = ($actionType === 'borrow');
    $payload = [
        'event'         => 'borrow_record',
        'action_type'   => $actionType,
        'status'        => $isBorrow ? 'borrowed' : 'returned',
        'id'            => (int) ($record['id'] ?? 0),
        'borrower_name' => $record['borrower_name'] ?? '',
        'product_code'  => $record['product_code'] ?? '',
        'product_name'  => $record['product_name'] ?? '',
        'quantity'      => (float) ($record['quantity'] ?? 1),
        'unit'          => $record['unit'] ?? 'ชิ้น',
        'location'      => $record['location'] ?? '',
        'borrow_date'   => $record['borrow_date'] ?? date('Y-m-d'),
        'borrow_time'   => $record['borrow_time'] ?? date('H:i:s'),
        'return_date'   => $record['return_date'] ?? '',
        'return_time'   => $record['return_time'] ?? '',
        'return_by'     => $record['return_received_by'] ?? '',
        'notes'         => $record['notes'] ?? '',
        'created_by'    => $record['created_by_name'] ?? '',
        'timestamp'     => date('Y-m-d H:i:s'),
    ];

    return google_sheets_post($payload);
}

/**
 * Sync single product catalog update to Google Sheets
 */
function google_sheets_sync_product(array $product): array
{
    $payload = [
        'event'     => 'catalog_update',
        'product'   => [
            'code'           => $product['code'] ?? '',
            'name'           => $product['name'] ?? '',
            'category'       => $product['category'] ?? '',
            'type'           => $product['type'] ?? '',
            'unit'           => $product['unit'] ?? 'ชิ้น',
            'location'       => $product['location'] ?? '',
            'minimum'        => (float) ($product['minimum'] ?? 0),
            'stock'          => (float) ($product['stock'] ?? 0),
            'total_received' => (float) ($product['total_received'] ?? 0),
            'total_issued'   => (float) ($product['total_issued'] ?? 0),
            'status'         => $product['status'] ?? 'ปกติ',
        ],
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    return google_sheets_post($payload);
}

/**
 * Sync all products catalog to Google Sheets
 */
function google_sheets_sync_all_catalog(): array
{
    $products = inventory_products();
    $catalogList = [];
    foreach ($products as $p) {
        $catalogList[] = [
            'code'     => $p['code'] ?? '',
            'name'     => $p['name'] ?? '',
            'category' => $p['category'] ?? '',
            'type'     => $p['type'] ?? '',
            'unit'     => $p['unit'] ?? 'ชิ้น',
            'location' => $p['location'] ?? '',
            'minimum'  => (float) ($p['minimum'] ?? 0),
            'stock'    => (float) ($p['stock'] ?? 0),
            'status'   => $p['status'] ?? 'ปกติ',
        ];
    }

    $payload = [
        'event'     => 'catalog_full_sync',
        'products'  => $catalogList,
        'count'     => count($catalogList),
        'timestamp' => date('Y-m-d H:i:s'),
    ];

    return google_sheets_post($payload);
}
