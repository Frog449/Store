<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/excel_live.php';

auth_require_login();

$pageTitle = 'Excel Live & Power Query | Store Automeation';
$pageDescription = 'ดาวน์โหลดไฟล์ Excel อัปเดตอัตโนมัติและเชื่อมต่อ Power Query แบบเรียลไทม์จากที่ไหนก็ได้';
$activePage = 'excel_live';

$isAdmin = auth_is_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = (string) ($_POST['action'] ?? '');
    try {
        inventory_require_csrf();

        if ($action === 'save_excel_settings') {
            $enabled = isset($_POST['excel_live_enabled']) ? '1' : '0';
            $publicUrl = trim((string) ($_POST['excel_public_base_url'] ?? ''));
            if ($publicUrl !== '' && !preg_match('#^https?://#i', $publicUrl)) {
                throw new DomainException('URL สาธารณะต้องขึ้นต้นด้วย https:// หรือ http://');
            }
            $publicUrl = rtrim($publicUrl, '/');
            $interval = isset($_POST['excel_refresh_interval']) && is_numeric($_POST['excel_refresh_interval'])
                ? max(0, min(60, (int) $_POST['excel_refresh_interval']))
                : 1;

            inventory_set_setting('excel_live_enabled', $enabled);
            inventory_set_setting('excel_public_base_url', $publicUrl);
            inventory_set_setting('excel_refresh_interval', (string) $interval);

            audit_log('settings_update', 'excel_live', 'อัปเดตการตั้งค่า Excel Live & Power Query');
            inventory_flash_redirect('บันทึกการตั้งค่า Excel Live เรียบร้อยแล้ว', 'excel-live.php');
        } elseif ($action === 'regenerate_excel_token') {
            excel_live_regenerate_token();
            audit_log('settings_update', 'excel_live_token', 'สร้าง Token ใหม่สำหรับ Excel Live (ยกเลิกไฟล์เก่าทั้งหมด)');
            inventory_flash_redirect('สร้าง Token ใหม่เรียบร้อยแล้ว ไฟล์หรือลิงก์เก่าจะไม่สามารถดึงข้อมูลได้อีก กรุณาดาวน์โหลดไฟล์ใหม่', 'excel-live.php');
        }
    } catch (DomainException $e) {
        $notice = $e->getMessage();
        $noticeType = 'error';
    }
}

$enabled = excel_live_is_enabled();
$token = excel_live_get_token();
$detectedBaseUrl = excel_live_detect_base_url();
$configuredPublicUrl = trim(inventory_get_setting('excel_public_base_url', ''));
$activeBaseUrl = excel_live_base_url();
$isLocalUrl = excel_live_is_local_url($activeBaseUrl);
$defaultInterval = excel_live_refresh_interval();

$db = inventory_db();
$productCount = count(inventory_products());
$receiptCount = (int) $db->query('SELECT COUNT(*) FROM `receipts`')->fetchColumn();
$issueCount = (int) $db->query('SELECT COUNT(*) FROM `issues`')->fetchColumn();
$borrowCount = inventory_table_exists($db, 'borrow_records')
    ? (int) $db->query('SELECT COUNT(*) FROM `borrow_records`')->fetchColumn()
    : 0;

$datasetsMeta = [
    'products' => ['label' => 'รายการสินค้า', 'count' => $productCount, 'desc' => 'ครบทุกคอลัมน์ (Stock Code, Account Code1, ยอดรับ/เบิก/คงเหลือ, ราคา, Cal)'],
    'receipts' => ['label' => 'ประวัติรับเข้า', 'count' => $receiptCount, 'desc' => 'รายการรับสินค้าเข้าคลังทั้งหมดพร้อมเลขเอกสารและผู้รับ'],
    'issues'   => ['label' => 'ประวัติเบิกออก', 'count' => $issueCount, 'desc' => 'รายการเบิกสินค้าออกทั้งหมดพร้อมผู้เบิก แผนก และผู้อนุมัติ'],
    'history'  => ['label' => 'ประวัติรับ-เบิกรวม', 'count' => $receiptCount + $issueCount, 'desc' => 'รวมประวัติรับเข้าและเบิกออกเรียงตามวันเวลาล่าสุด'],
    'borrows'  => ['label' => 'ยืม-คืนพัสดุ', 'count' => $borrowCount, 'desc' => 'สถานะพัสดุที่กำลังยืมและคืนแล้วทั้งหมด'],
    'summary'  => ['label' => 'ภาพรวมสต๊อก', 'count' => 10, 'desc' => 'ตัวเลขสรุป KPI สต๊อกและเวลาอัปเดตล่าสุดจากเซิร์ฟเวอร์'],
];

$pqPayloads = [];
foreach (array_keys($datasetsMeta) as $t) {
    $pqPayloads[$t] = [
        'jsonUrl' => excel_live_feed_url($t, 'json', $activeBaseUrl),
        'csvUrl'  => excel_live_feed_url($t, 'csv', $activeBaseUrl),
        'htmlUrl' => excel_live_feed_url($t, 'html', $activeBaseUrl),
        'mCode'   => excel_live_power_query_m($t, $activeBaseUrl),
    ];
}

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">EXCEL LIVE &amp; POWER QUERY</div>
            <h1>Excel อัปเดตสด<span class="heading-period">.</span></h1>
            <p>ดาวน์โหลดไฟล์ Excel ที่เชื่อมต่อกับฐานข้อมูลสด เปิดดูและอัปเดตข้อมูลล่าสุดได้จากที่ไหนก็ได้ทุกครั้งที่มีการเพิ่มหรือเปลี่ยนแปลงข้อมูล</p>
        </div>
        <div class="page-kpis">
            <a class="secondary-button" href="catalog.php">รายการสินค้า ↗</a>
            <a class="secondary-button" href="history.php">ประวัติรับ–เบิก ↗</a>
        </div>
    </div>

    <!-- Live Status Strip -->
    <div class="stats-strip" style="margin-bottom:24px;">
        <div class="stat-item">
            <span class="stat-icon" style="background:<?= $enabled ? '#dcfce7;color:#15803d' : '#fee2e2;color:#b91c1c' ?>;font-weight:700;">XL</span>
            <div>
                <strong><?= $enabled ? 'พร้อมเชื่อมต่อสด' : 'ปิดใช้งานอยู่' ?></strong>
                <span>สถานะ Excel Live Feed</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-box">PD</span>
            <div>
                <strong><?= number_format($productCount) ?></strong>
                <span>รายการสินค้าทั้งหมด</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon" style="background:#e0f2fe;color:#0758b4;font-weight:700;">IO</span>
            <div>
                <strong><?= number_format($receiptCount + $issueCount) ?></strong>
                <span>ประวัติรับเข้า <?= number_format($receiptCount) ?> / เบิก <?= number_format($issueCount) ?></span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon" style="background:#fef3c7;color:#b45309;font-weight:700;">BR</span>
            <div>
                <strong><?= number_format($borrowCount) ?></strong>
                <span>รายการยืม–คืนพัสดุ</span>
            </div>
        </div>
    </div>

    <?php if ($isLocalUrl): ?>
        <div class="notice" style="background:#fffbeb;border:1px solid #f59e0b;color:#92400e;margin-bottom:20px;line-height:1.65;">
            <strong>แจ้งเตือนโหมด Localhost (<code><?= h($activeBaseUrl) ?></code>):</strong>
            ลิงก์นี้จะอัปเดตได้เฉพาะบนเครื่องนี้เท่านั้น หากต้องการให้เปิดดูและอัปเดตจาก <strong>ที่ไหนก็ได้</strong> กรุณาเปิดหน้าเว็บนี้ผ่านลิงก์เว็บออนไลน์ที่ Deploy แล้ว (เช่น Render / Railway / โดเมนบริษัท)
            <?php if ($isAdmin): ?>
                หรือกรอก <strong>Public Server URL</strong> ในส่วนตั้งค่าผู้ดูแลระบบด้านล่าง
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Main One-Click Download Card -->
    <div class="inventory-panel" style="padding:24px;margin-bottom:24px;border-left:4px solid #15803d;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px;">
            <div>
                <span style="display:inline-block;font-size:11px;font-weight:700;letter-spacing:0.06em;color:#15803d;background:#dcfce7;padding:3px 10px;border-radius:999px;margin-bottom:8px;">แนะนำ • กดโหลดแล้วใช้ได้เลย</span>
                <h2 style="margin:0;font-size:20px;color:#0f172a;">ดาวน์โหลดไฟล์ Excel อัปเดตอัตโนมัติ (ครบ 5 ชีตในไฟล์เดียว)</h2>
                <p style="margin:6px 0 0;font-size:13px;color:#475569;max-width:740px;">
                    รวมชีต <strong>ภาพรวม</strong>, <strong>รายการสินค้า</strong>, <strong>ประวัติรับเข้า</strong>, <strong>ประวัติเบิกออก</strong> และ <strong>ยืม-คืนพัสดุ</strong> ไว้ครบในไฟล์เดียว
                    ตั้งค่าดึงข้อมูลล่าสุดจากเซิร์ฟเวอร์อัตโนมัติทุกครั้งที่เปิดไฟล์ + อัปเดตเบื้องหลังอัตโนมัติตามเวลาที่กำหนด
                </p>
            </div>
            <div style="font-size:12px;color:#475569;background:#f8fafc;border:1px solid #e2e8f0;padding:8px 12px;border-radius:8px;">
                เซิร์ฟเวอร์ปลายทาง:<br>
                <strong style="color:#0758b4;word-break:break-all;"><?= h($activeBaseUrl) ?></strong>
            </div>
        </div>

        <form method="get" action="excel-download.php" style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #e2e8f0;">
            <input type="hidden" name="mode" value="xlsx">
            <input type="hidden" name="type" value="all">
            <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#334155;min-width:250px;">
                ความถี่การอัปเดตอัตโนมัติใน Excel
                <select name="interval" style="padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:13px;">
                    <option value="1" <?= $defaultInterval === 1 ? 'selected' : '' ?>>อัปเดตตอนเปิดไฟล์ + ทุก 1 นาที (แนะนำ)</option>
                    <option value="5" <?= $defaultInterval === 5 ? 'selected' : '' ?>>อัปเดตตอนเปิดไฟล์ + ทุก 5 นาที</option>
                    <option value="10" <?= $defaultInterval === 10 ? 'selected' : '' ?>>อัปเดตตอนเปิดไฟล์ + ทุก 10 นาที</option>
                    <option value="30" <?= $defaultInterval === 30 ? 'selected' : '' ?>>อัปเดตตอนเปิดไฟล์ + ทุก 30 นาที</option>
                    <option value="0" <?= $defaultInterval === 0 ? 'selected' : '' ?>>อัปเดตเฉพาะตอนเปิดไฟล์ / กด Refresh All เอง</option>
                </select>
            </label>

            <button type="submit" class="primary-button" style="background:#15803d;border-color:#15803d;padding:11px 22px;font-size:14px;" <?= !$enabled ? 'disabled' : '' ?>>
                ดาวน์โหลด Store_Automeation_Live.xlsx
            </button>
        </form>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;margin-top:16px;font-size:12.5px;color:#334155;">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;">
                <strong style="color:#0758b4;display:block;margin-bottom:4px;">1. เปิดไฟล์จากเครื่องไหนก็ได้</strong>
                ส่งไฟล์ <code>.xlsx</code> นี้ไปเปิดบนคอมพิวเตอร์เครื่องใดก็ได้ที่มีอินเทอร์เน็ต ไม่ต้องลงไดรเวอร์หรือตั้งค่าเพิ่ม
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;">
                <strong style="color:#0758b4;display:block;margin-bottom:4px;">2. กด Enable Content ครั้งแรก</strong>
                เมื่อเปิดไฟล์ใน Microsoft Excel กดปุ่มแถบเหลือง <strong>Enable Content (เปิดใช้งานเนื้อหา)</strong> 1 ครั้งเพื่อให้ Excel ดึงข้อมูลสด
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px;">
                <strong style="color:#0758b4;display:block;margin-bottom:4px;">3. อัปเดตทันทีเมื่อข้อมูลเปลี่ยน</strong>
                ข้อมูลอัปเดตอัตโนมัติตอนเปิดไฟล์และทุกนาที หรือกด <strong>Data → Refresh All (Ctrl+Alt+F5)</strong> เพื่อดูข้อมูลล่าสุดทันที
            </div>
        </div>
    </div>

    <!-- Individual Sheet Downloads -->
    <div class="inventory-panel" style="padding:24px;margin-bottom:24px;">
        <h2 style="margin:0 0 6px;font-size:18px;color:#0f172a;">ดาวน์โหลดแยกเฉพาะชีต (Live .xlsx / .iqy)</h2>
        <p style="margin:0 0 16px;font-size:13px;color:#64748b;">เหมาะสำหรับคนที่ต้องการเปิดดูเฉพาะรายการสินค้า หรือเฉพาะประวัติการเคลื่อนไหว</p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:14px;">
            <?php foreach ($datasetsMeta as $key => $info): ?>
                <div style="border:1px solid #e2e8f0;border-radius:10px;padding:14px;background:#fff;display:flex;flex-direction:column;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;">
                            <strong style="font-size:14px;color:#0f172a;"><?= h($info['label']) ?></strong>
                            <span style="font-size:11px;font-weight:700;color:#0758b4;background:#e0f2fe;padding:2px 8px;border-radius:999px;"><?= number_format($info['count']) ?> แถว</span>
                        </div>
                        <p style="margin:0;font-size:12px;color:#64748b;line-height:1.5;"><?= h($info['desc']) ?></p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a class="secondary-button" href="excel-download.php?mode=xlsx&amp;type=<?= urlencode($key) ?>&amp;interval=<?= $defaultInterval ?>" style="font-size:12px;padding:6px 12px;">
                            โหลด .xlsx (สด)
                        </a>
                        <a class="secondary-button" href="excel-download.php?mode=iqy&amp;type=<?= urlencode($key) ?>" style="font-size:12px;padding:6px 12px;" title="ไฟล์ Web Query ขนาดเล็ก เปิดใน Excel เพื่อดึงตารางนี้โดยตรง">
                            โหลด .iqy
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Power Query (M Code & Feed URLs) -->
    <div class="inventory-panel" style="padding:24px;margin-bottom:24px;">
        <div style="margin-bottom:14px;">
            <h2 style="margin:0;font-size:18px;color:#0f172a;">เชื่อมต่อด้วย Power Query ในไฟล์ Excel ของคุณเอง</h2>
            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">
                หากมีไฟล์ Excel รายงานเดิมอยู่แล้ว สามารถคัดลอกลิงก์ด้านล่างไปวางใน <strong>Data → Get Data → From Other Sources → From Web</strong>
                หรือคัดลอกโค้ด <strong>M Code</strong> ไปวางใน <strong>Blank Query → Advanced Editor</strong> ได้เลย (เลือก Authentication แบบ <em>Anonymous</em>)
            </p>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;" id="pq-tabs" role="tablist">
            <?php $firstTab = true; foreach ($datasetsMeta as $key => $info): ?>
                <button type="button"
                        class="filter-chip <?= $firstTab ? 'selected' : '' ?>"
                        data-pq-tab="<?= h($key) ?>">
                    <?= h($info['label']) ?>
                </button>
            <?php $firstTab = false; endforeach; ?>
        </div>

        <div style="display:grid;gap:14px;">
            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                    <label style="font-size:13px;font-weight:600;color:#1e293b;">URL สำหรับ Power Query — รูปแบบ JSON (แนะนำ)</label>
                    <div style="display:flex;gap:8px;">
                        <a id="pq-preview-link" href="<?= h($pqPayloads['products']['jsonUrl']) ?>" target="_blank" rel="noopener" class="secondary-button" style="font-size:11px;padding:4px 10px;">ทดสอบเปิดข้อมูล ↗</a>
                        <button type="button" class="secondary-button" style="font-size:11px;padding:4px 10px;" onclick="copyTextField('pq-json-url', this)">คัดลอก URL</button>
                    </div>
                </div>
                <input id="pq-json-url" type="text" readonly value="<?= h($pqPayloads['products']['jsonUrl']) ?>" style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;">
            </div>

            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                    <label style="font-size:13px;font-weight:600;color:#1e293b;">URL สำหรับ Power Query — รูปแบบ CSV (From Web ได้ทันที ไม่ต้องแตก JSON)</label>
                    <button type="button" class="secondary-button" style="font-size:11px;padding:4px 10px;" onclick="copyTextField('pq-csv-url', this)">คัดลอก CSV URL</button>
                </div>
                <input id="pq-csv-url" type="text" readonly value="<?= h($pqPayloads['products']['csvUrl']) ?>" style="width:100%;font-family:monospace;font-size:12px;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;">
            </div>

            <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;">
                    <label style="font-size:13px;font-weight:600;color:#1e293b;">โค้ด Power Query (M Code) — วางใน Blank Query → Advanced Editor</label>
                    <button type="button" class="primary-button" style="font-size:11px;padding:5px 12px;" onclick="copyTextField('pq-m-code', this)">คัดลอกโค้ด M</button>
                </div>
                <textarea id="pq-m-code" readonly rows="11" style="width:100%;font-family:Consolas,monospace;font-size:12px;padding:12px;border:1px solid #cbd5e1;border-radius:8px;background:#0f172a;color:#e2e8f0;line-height:1.55;"><?= h($pqPayloads['products']['mCode']) ?></textarea>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
        <!-- Admin Settings Card -->
        <div class="inventory-panel" style="padding:24px;">
            <h2 style="margin:0 0 6px;font-size:18px;color:#0f172a;">ตั้งค่าการเชื่อมต่อ &amp; ความปลอดภัย (ผู้ดูแลระบบ)</h2>
            <p style="margin:0 0 18px;font-size:13px;color:#64748b;">กำหนดโดเมนสาธารณะสำหรับฝังในไฟล์ Excel และจัดการ Token ความปลอดภัย</p>

            <form method="post" style="display:grid;gap:16px;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="save_excel_settings">

                <label style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;font-weight:600;color:#15803d;">
                    <input type="checkbox" name="excel_live_enabled" value="1" <?= $enabled ? 'checked' : '' ?> style="width:19px;height:19px;accent-color:#15803d;">
                    เปิดใช้งานระบบ Excel Live &amp; Power Query Feed
                </label>

                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;">
                    <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#334155;">
                        Public Server URL (ถ้าเว้นว่างจะใช้ URL ปัจจุบันอัตโนมัติ)
                        <input type="url" name="excel_public_base_url" value="<?= h($configuredPublicUrl) ?>" placeholder="เช่น https://store-automeation.onrender.com" style="padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                        <small style="font-weight:400;color:#64748b;">URL ที่ตรวจพบตอนนี้: <code><?= h($detectedBaseUrl) ?></code></small>
                    </label>

                    <label style="display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600;color:#334155;">
                        ค่าเริ่มต้นความถี่อัปเดตอัตโนมัติใน Excel (นาที)
                        <select name="excel_refresh_interval" style="padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;">
                            <option value="1" <?= $defaultInterval === 1 ? 'selected' : '' ?>>ทุก 1 นาที</option>
                            <option value="5" <?= $defaultInterval === 5 ? 'selected' : '' ?>>ทุก 5 นาที</option>
                            <option value="10" <?= $defaultInterval === 10 ? 'selected' : '' ?>>ทุก 10 นาที</option>
                            <option value="30" <?= $defaultInterval === 30 ? 'selected' : '' ?>>ทุก 30 นาที</option>
                            <option value="0" <?= $defaultInterval === 0 ? 'selected' : '' ?>>เฉพาะตอนเปิดไฟล์ / กด Refresh เอง</option>
                        </select>
                        <small style="font-weight:400;color:#64748b;">ทุกไฟล์ตั้งค่าอัปเดตอัตโนมัติตอนเปิดไฟล์ (Refresh on Open) เสมอ</small>
                    </label>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;padding-top:12px;border-top:1px dashed #cbd5e1;">
                    <button type="submit" class="primary-button">บันทึกการตั้งค่า Excel Live</button>
                    <button type="submit" form="regen-token-form" class="secondary-button" style="color:#b91c1c;border-color:#fca5a5;" onclick="return confirm('ยืนยันสร้าง Token ใหม่? ไฟล์ Excel หรือลิงก์ Power Query เก่าทั้งหมดจะอัปเดตไม่ได้และต้องดาวน์โหลดใหม่');">
                        รีเซ็ต Token ใหม่ (ยกเลิกไฟล์เก่า)
                    </button>
                </div>
            </form>

            <form id="regen-token-form" method="post" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="regenerate_excel_token">
            </form>
        </div>
    <?php endif; ?>
</section>

<script>
const pqPayloads = <?= json_encode($pqPayloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

document.querySelectorAll('#pq-tabs [data-pq-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
        const key = btn.getAttribute('data-pq-tab');
        const data = pqPayloads[key];
        if (!data) return;

        document.querySelectorAll('#pq-tabs [data-pq-tab]').forEach((b) => b.classList.remove('selected'));
        btn.classList.add('selected');

        document.getElementById('pq-json-url').value = data.jsonUrl;
        document.getElementById('pq-csv-url').value = data.csvUrl;
        document.getElementById('pq-m-code').value = data.mCode;
        document.getElementById('pq-preview-link').href = data.jsonUrl;
    });
});

function copyTextField(id, btn) {
    const el = document.getElementById(id);
    if (!el) return;
    el.select();
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(el.value).then(() => flashCopied(btn)).catch(() => {
            document.execCommand('copy');
            flashCopied(btn);
        });
    } else {
        document.execCommand('copy');
        flashCopied(btn);
    }
}

function flashCopied(btn) {
    if (!btn) return;
    const orig = btn.textContent;
    btn.textContent = 'คัดลอกแล้ว ✓';
    setTimeout(() => { btn.textContent = orig; }, 1600);
}
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
