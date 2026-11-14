<?php
declare(strict_types=1);

/**
 * Telegram Bot Notification Service
 * Store Automeation
 */

function telegram_get_settings(): array
{
    static $settings = null;
    if ($settings === null) {
        $settings = inventory_get_settings();
    }
    return $settings;
}

/**
 * Core function to send text message or photo to Telegram Bot
 */
function telegram_send(string $htmlCaption, ?string $photoFilename = null): array
{
    $settings = telegram_get_settings();
    $enabled = ($settings['telegram_notify_enabled'] ?? '0') === '1';
    $botToken = trim($settings['telegram_bot_token'] ?? '');
    $chatId = trim($settings['telegram_chat_id'] ?? '');

    if (!$enabled || $botToken === '' || $chatId === '') {
        return ['success' => false, 'message' => 'Telegram Notification is disabled or not configured.'];
    }

    $sentPhoto = false;

    // Send Photo if valid filename provided
    if ($photoFilename && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/i', $photoFilename)) {
        $photoPath = __DIR__ . '/../storage/product-images/' . $photoFilename;
        if (!is_file($photoPath)) {
            try {
                $db = db();
                $s = $db->prepare('SELECT `data` FROM `product_images` WHERE `filename` = :f');
                $s->execute(['f' => $photoFilename]);
                $blob = $s->fetchColumn();
                if ($blob) {
                    $dir = __DIR__ . '/../storage/product-images';
                    if (is_dir($dir) || @mkdir($dir, 0755, true)) {
                        @file_put_contents($photoPath, $blob);
                    }
                }
            } catch (Throwable $e) {}
        }

        if (is_file($photoPath)) {
            try {
                $ch = curl_init();
                $curlFile = new CURLFile($photoPath, mime_content_type($photoPath), $photoFilename);
                curl_setopt_array($ch, [
                    CURLOPT_URL => "https://api.telegram.org/bot{$botToken}/sendPhoto",
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => [
                        'chat_id' => $chatId,
                        'photo' => $curlFile,
                        'caption' => $htmlCaption,
                        'parse_mode' => 'HTML',
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode === 200) {
                    $sentPhoto = true;
                }
            } catch (Throwable $e) {
                error_log('Telegram sendPhoto error: ' . $e->getMessage());
            }
        }
    }

    // Fallback or Text Only message
    if (!$sentPhoto) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "https://api.telegram.org/bot{$botToken}/sendMessage",
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'chat_id' => $chatId,
                    'text' => $htmlCaption,
                    'parse_mode' => 'HTML',
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return [
                'success' => $httpCode === 200,
                'message' => $httpCode === 200 ? 'OK' : 'Telegram returned HTTP ' . $httpCode . ': ' . $response,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    return ['success' => true, 'message' => 'OK'];
}

/**
 * Send Receipt / Issue Movement Notification to Telegram
 */
function telegram_notify_movement(array $movement, string $kind, ?array $product = null, bool $isNewProduct = false): array
{
    $isReceipt = $kind === 'receipt';
    $title = $isReceipt
        ? ($isNewProduct ? '<b>[แจ้งเตือนการเพิ่มสินค้าใหม่และรับเข้า]</b>' : '<b>[แจ้งเตือนการรับสินค้าเข้า]</b>')
        : '<b>[แจ้งเตือนการเบิกสินค้าออก]</b>';

    $code = $product['code'] ?? ($movement['product_code'] ?? '');
    $name = $product['name'] ?? ($movement['product_name'] ?? '');
    $qty = (float) ($movement['quantity'] ?? 0);
    $unit = $product['unit'] ?? ($movement['unit'] ?? 'ชิ้น');
    $location = $product['location'] ?? '-';
    $currentStock = isset($product['stock']) ? (float) $product['stock'] : null;

    $lines = [
        $title,
        '<b>Store Automeation · ระบบคลังสินค้า</b>',
        '━━━━━━━━━━━━━━━━━━',
        '<b>สินค้า:</b> ' . htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') . ($code !== '' ? ' (รหัส: ' . htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') . ')' : ''),
        '<b>จำนวน' . ($isReceipt ? 'รับเข้า' : 'เบิกออก') . ':</b> ' . ($isReceipt ? '+' : '-') . inventory_quantity($qty) . ' ' . htmlspecialchars((string) $unit, ENT_QUOTES, 'UTF-8'),
    ];

    if ($currentStock !== null) {
        $lines[] = '<b>ยอดคงเหลือในคลัง:</b> ' . inventory_quantity($currentStock) . ' ' . htmlspecialchars((string) $unit, ENT_QUOTES, 'UTF-8');
    }

    if (!empty($location) && $location !== '-') {
        $lines[] = '<b>ตำแหน่งจัดเก็บ:</b> ' . htmlspecialchars((string) $location, ENT_QUOTES, 'UTF-8');
    }

    if (!empty($movement['document_no'])) {
        $lines[] = '<b>เลขที่เอกสาร:</b> ' . htmlspecialchars((string) $movement['document_no'], ENT_QUOTES, 'UTF-8');
    }

    if ($isReceipt && !empty($movement['received_by'])) {
        $lines[] = '<b>ผู้รับสินค้า:</b> ' . htmlspecialchars((string) $movement['received_by'], ENT_QUOTES, 'UTF-8');
    }

    if (!$isReceipt) {
        if (!empty($movement['requested_by'])) {
            $lines[] = '<b>ผู้ขอเบิก:</b> ' . htmlspecialchars((string) $movement['requested_by'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($movement['department'])) {
            $lines[] = '<b>แผนก/โครงการ:</b> ' . htmlspecialchars((string) $movement['department'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($movement['approved_by'])) {
            $lines[] = '<b>ผู้อนุมัติ:</b> ' . htmlspecialchars((string) $movement['approved_by'], ENT_QUOTES, 'UTF-8');
        }
    }

    if (!empty($movement['notes'])) {
        $lines[] = '<b>หมายเหตุ:</b> ' . htmlspecialchars((string) $movement['notes'], ENT_QUOTES, 'UTF-8');
    }

    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = '<i>วันที่ทำรายการ: ' . date('d/m/Y H:i น.') . '</i>';

    $caption = implode("\n", $lines);
    $photoFilename = $product['image_filename'] ?? null;

    return telegram_send($caption, $photoFilename);
}

/**
 * Send Borrow / Return Notification to Telegram
 */
function telegram_notify_borrow(array $record, string $actionType = 'borrow'): array
{
    $isBorrow = $actionType === 'borrow';
    $title = $isBorrow ? '<b>[แจ้งเตือนการยืมพัสดุ]</b>' : '<b>[แจ้งเตือนการคืนพัสดุ]</b>';
    $statusText = $isBorrow ? 'กำลังยืม' : 'คืนแล้ว';

    $lines = [
        $title,
        '<b>Store Automeation · ระบบยืม-คืนพัสดุ</b>',
        '━━━━━━━━━━━━━━━━━━',
        '<b>ผู้ยืม:</b> ' . htmlspecialchars((string) $record['borrower_name'], ENT_QUOTES, 'UTF-8'),
        '<b>พัสดุ:</b> ' . htmlspecialchars((string) $record['product_name'], ENT_QUOTES, 'UTF-8') . (!empty($record['product_code']) ? ' (รหัส: ' . htmlspecialchars((string) $record['product_code'], ENT_QUOTES, 'UTF-8') . ')' : ''),
        '<b>จำนวน:</b> ' . inventory_quantity($record['quantity'] ?? 1) . ' ' . htmlspecialchars((string) ($record['unit'] ?? 'ชิ้น'), ENT_QUOTES, 'UTF-8'),
        '<b>สถานที่นำไปใช้งาน:</b> ' . htmlspecialchars((string) ($record['location'] ?? '-'), ENT_QUOTES, 'UTF-8'),
        '<b>สถานะ:</b> ' . $statusText,
    ];

    if ($isBorrow) {
        $lines[] = '<b>เวลายืม:</b> ' . htmlspecialchars((string) ($record['borrow_date'] ?? date('Y-m-d'))) . ' ' . htmlspecialchars((string) ($record['borrow_time'] ?? date('H:i')));
    } else {
        $lines[] = '<b>เวลาคืน:</b> ' . htmlspecialchars((string) ($record['return_date'] ?? date('Y-m-d'))) . ' ' . htmlspecialchars((string) ($record['return_time'] ?? date('H:i')));
        if (!empty($record['return_received_by'])) {
            $lines[] = '<b>ผู้รับคืน:</b> ' . htmlspecialchars((string) $record['return_received_by'], ENT_QUOTES, 'UTF-8');
        }
    }

    if (!empty($record['notes'])) {
        $lines[] = '<b>หมายเหตุ:</b> ' . htmlspecialchars((string) $record['notes'], ENT_QUOTES, 'UTF-8');
    }

    $lines[] = '━━━━━━━━━━━━━━━━━━';
    $lines[] = '<i>บันทึกโดย: ' . htmlspecialchars((string) ($record['created_by_name'] ?? 'เจ้าหน้าที่'), ENT_QUOTES, 'UTF-8') . ' (' . date('d/m/Y H:i น.') . ')</i>';

    $caption = implode("\n", $lines);
    $photoFilename = $isBorrow ? ($record['borrow_photo'] ?? null) : ($record['return_photo'] ?? ($record['borrow_photo'] ?? null));

    return telegram_send($caption, $photoFilename);
}
