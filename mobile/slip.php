<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

auth_require_login();

$kind = isset($_GET['type']) && is_string($_GET['type']) ? trim($_GET['type']) : '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!in_array($kind, ['receipt', 'issue'], true) || $id <= 0) {
    inventory_flash_redirect('ไม่พบเอกสารที่ต้องการพิมพ์', 'history.php', 'error');
}

$movement = inventory_movement_by_id($kind, $id);
if ($movement === null) {
    inventory_flash_redirect('ไม่พบรายการเคลื่อนไหวที่ระบุ', 'history.php', 'error');
}

$isReceipt = ($kind === 'receipt');
$slipTitle = $isReceipt ? 'ใบสำคัญรับสินค้า (Goods Receipt Slip)' : 'ใบสำคัญเบิกพัสดุ (Material Issue Slip)';
$refNo = ($isReceipt ? 'REC-' : 'ISS-') . str_pad((string) $id, 6, '0', STR_PAD_LEFT);

// บันทึก audit log การพิมพ์เอกสาร
$printAction = $isReceipt ? 'print_receipt' : 'print_issue';
$printLabel = $isReceipt ? 'พิมพ์ใบรับสินค้า' : 'พิมพ์ใบเบิกสินค้า';
audit_log($printAction, $movement['product_code'] ?? '', "{$printLabel} #{$refNo} - " . ($movement['product_name'] ?? '') . " จำนวน " . ($movement['quantity'] ?? ''));
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($refNo) ?> - <?= h($slipTitle) ?> | Store Automeation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Thai:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/favicon.png?v=<?= filemtime(__DIR__ . '/assets/favicon.png') ?>">
    <link rel="shortcut icon" href="favicon.ico?v=<?= filemtime(__DIR__ . '/favicon.ico') ?>">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 16px;
            background: #f4f4f6;
            color: #1a1a1a;
            font-family: "Noto Sans Thai", "DM Sans", sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .slip-actions {
            max-width: 800px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #ccc;
        }

        .btn-primary {
            background: #0758b4;
            color: #fff;
            border-color: #0758b4;
        }

        .btn-primary:hover {
            background: #05448c;
        }

        .btn-secondary {
            background: #fff;
            color: #444;
        }

        .btn-secondary:hover {
            background: #eee;
        }

        .slip-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 45px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .slip-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 20px;
            border-bottom: 2px solid #0758b4;
            margin-bottom: 24px;
        }

        .slip-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .slip-brand img {
            height: 44px;
            width: auto;
            object-fit: contain;
        }

        .slip-brand h2 {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
        }

        .slip-brand span {
            color: #0758b4;
        }

        .slip-brand small {
            display: block;
            color: #64748b;
            font-size: 11px;
            letter-spacing: 1px;
        }

        .slip-doc-info {
            text-align: right;
        }

        .slip-doc-info h1 {
            margin: 0 0 6px;
            font-size: 18px;
            color: #0758b4;
            font-weight: 700;
        }

        .slip-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px 24px;
            margin-bottom: 26px;
            padding: 14px 18px;
            background: #fafafa;
            border-radius: 6px;
            border: 1px solid #eee;
        }

        .slip-meta-item strong {
            display: inline-block;
            width: 110px;
            color: #666;
            font-weight: 500;
        }

        .slip-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .slip-table th {
            background: #0a1b33;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
        }

        .slip-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .slip-table th.numeric,
        .slip-table td.numeric {
            text-align: right;
        }

        .slip-notes {
            margin-bottom: 40px;
            padding: 12px 16px;
            background: #fffbe6;
            border: 1px solid #ffe58f;
            border-radius: 6px;
            font-size: 12px;
        }

        .slip-signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 50px;
        }

        .signature-box {
            border: 1px dashed #bbb;
            border-radius: 6px;
            padding: 14px 10px;
            text-align: center;
        }

        .signature-title {
            font-weight: 600;
            font-size: 12px;
            color: #333;
            margin-bottom: 50px;
        }

        .signature-line {
            border-bottom: 1px solid #444;
            width: 80%;
            margin: 0 auto 6px;
        }

        .signature-name {
            font-size: 11px;
            color: #666;
        }

        .signature-date {
            margin-top: 4px;
            font-size: 10px;
            color: #888;
        }

        @page {
            size: A4 portrait;
            margin: 0;
            /* ซ่อน Header และ Footer อัตโนมัติของเบราว์เซอร์ เช่น URL ด้านล่าง และ วันที่ด้านบน */
        }

        @media print {
            body {
                background: #fff;
                margin: 0;
                padding: 12mm 15mm;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .slip-actions {
                display: none !important;
            }

            .slip-container {
                border: none;
                box-shadow: none;
                padding: 0;
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="slip-actions">
        <a class="btn btn-secondary" href="history.php">กลับหน้ารวมประวัติ</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">พิมพ์เอกสารนี้ (Print Slip)</button>
    </div>

    <div class="slip-container">
        <div class="slip-header">
            <div class="slip-brand">
                <img src="assets/logo.png?v=<?= filemtime(__DIR__ . '/assets/logo.png') ?>" alt="Store Automeation">
                <div>
                    <h2>Store <span>Automeation</span></h2>
                    <small>INDUSTRIAL SUPPLY · INVENTORY VOUCHER</small>
                    <div style="margin-top:4px;font-size:11px;color:#64748b;">ฝ่ายจัดเก็บและควบคุมพัสดุอุตสาหกรรม</div>
                </div>
            </div>
            <div class="slip-doc-info">
                <h1><?= h($slipTitle) ?></h1>
                <div style="font-size:12px;color:#555;">เลขที่อ้างอิง: <strong><?= h($refNo) ?></strong></div>
                <div style="font-size:12px;color:#555;">วันที่บันทึก:
                    <strong><?= h($movement['movement_date']) ?></strong></div>
            </div>
        </div>

        <div class="slip-meta-grid">
            <div class="slip-meta-item">
                <strong>เลขที่เอกสาร:</strong>
                <span><?= h($movement['document_no'] !== '' ? $movement['document_no'] : '-') ?></span>
            </div>
            <div class="slip-meta-item">
                <strong>แผนก/หน่วยงาน:</strong>
                <span><?= h($movement['department'] !== '' ? $movement['department'] : '-') ?></span>
            </div>
            <?php if ($isReceipt): ?>
                <div class="slip-meta-item">
                    <strong>ผู้ส่งมอบ:</strong>
                    <span><?= h($movement['supplier'] !== '' ? $movement['supplier'] : '-') ?></span>
                </div>
                <div class="slip-meta-item">
                    <strong>ผู้รับเข้าคลัง:</strong>
                    <span><?= h($movement['received_by'] !== '' ? $movement['received_by'] : '-') ?></span>
                </div>
            <?php else: ?>
                <div class="slip-meta-item">
                    <strong>ผู้ขอเบิก:</strong>
                    <span><?= h($movement['requested_by'] !== '' ? $movement['requested_by'] : '-') ?></span>
                </div>
                <div class="slip-meta-item">
                    <strong>ผู้อนุมัติ:</strong>
                    <span><?= h($movement['approved_by'] !== '' ? $movement['approved_by'] : '-') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <table class="slip-table">
            <thead>
                <tr>
                    <th style="width:40px;text-align:center;">#</th>
                    <th>รหัสสินค้า</th>
                    <th>รายการสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th>ตำแหน่งชั้น</th>
                    <th class="numeric">จำนวน<?= $isReceipt ? 'รับ' : 'เบิก' ?></th>
                    <th style="width:60px;">หน่วย</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align:center;">1</td>
                    <td><strong><?= h($movement['product_code']) ?></strong></td>
                    <td><?= h($movement['product_name']) ?></td>
                    <td><?= h($movement['category'] ?? '-') ?></td>
                    <td><?= h($movement['location'] !== '' ? 'ชั้น ' . $movement['location'] : '-') ?></td>
                    <td class="numeric"
                        style="font-size:15px;font-weight:700;color:<?= $isReceipt ? '#2b773c' : '#c53030' ?>;">
                        <?= $isReceipt ? '+' : '-' ?><?= inventory_quantity($movement['quantity']) ?>
                    </td>
                    <td><?= h($movement['unit']) ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($movement['notes'] !== ''): ?>
            <div class="slip-notes">
                <strong>หมายเหตุ:</strong> <?= h($movement['notes']) ?>
            </div>
        <?php endif; ?>

        <div class="slip-signatures">
            <?php if ($isReceipt): ?>
                <div class="signature-box">
                    <div class="signature-title">ผู้ส่งมอบสินค้า</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">(
                        <?= h($movement['supplier'] !== '' ? $movement['supplier'] : '...........................................') ?>
                        )</div>
                    <div class="signature-date">วันที่ ......./......./...........</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">ผู้รับสินค้าเข้าคลัง</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">(
                        <?= h($movement['received_by'] !== '' ? $movement['received_by'] : '...........................................') ?>
                        )</div>
                    <div class="signature-date">วันที่ ......./......./...........</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">ผู้ตรวจสอบ / อนุมัติ</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">( ........................................... )</div>
                    <div class="signature-date">วันที่ ......./......./...........</div>
                </div>
            <?php else: ?>
                <div class="signature-box">
                    <div class="signature-title">ผู้ขอเบิกพัสดุ</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">(
                        <?= h($movement['requested_by'] !== '' ? $movement['requested_by'] : '...........................................') ?>
                        )</div>
                    <div class="signature-date">วันที่ ......./......./...........</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">ผู้จ่ายพัสดุ / เจ้าหน้าที่คลัง</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">( ........................................... )</div>
                    <div class="signature-date">วันที่ ......./......./...........</div>
                </div>
                <div class="signature-box">
                    <div class="signature-title">ผู้อนุมัติการเบิก</div>
                    <div class="signature-line"></div>
                    <div class="signature-name">(
                        <?= h($movement['approved_by'] !== '' ? $movement['approved_by'] : '...........................................') ?>
                        )</div>
                    <div class="signature-date">วันที่ ......./......./...........</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>

</html>