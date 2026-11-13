<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_login();

$pageTitle = 'ยืม–คืนพัสดุและอุปกรณ์ | Store Automeation';
$pageDescription = 'บันทึกการยืม-คืนพัสดุ ติดตามรายการที่ยังไม่คืน พร้อมระบบแจ้งเตือน Telegram และเชื่อมต่อ Google Sheets';
$activePage = 'borrow';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        inventory_require_csrf();

        if ($action === 'create_borrow') {
            if (auth_is_viewer()) {
                throw new DomainException('ผู้ใช้ระดับ Viewer ไม่สามารถบันทึกยืมพัสดุได้');
            }
            $newId = inventory_create_borrow_record($_POST, $_FILES['borrow_photo'] ?? null);
            inventory_flash_redirect('บันทึกการยืมพัสดุเรียบร้อยแล้ว (รหัสรายการ #' . $newId . ')', 'borrow.php');
        } elseif ($action === 'return_borrow') {
            if (auth_is_viewer()) {
                throw new DomainException('ผู้ใช้ระดับ Viewer ไม่สามารถบันทึกคืนพัสดุได้');
            }
            $borrowId = (int) ($_POST['borrow_id'] ?? 0);
            inventory_return_borrow_record($borrowId, $_POST, $_FILES['return_photo'] ?? null);
            inventory_flash_redirect('บันทึกคืนพัสดุเรียบร้อยแล้ว', 'borrow.php');
        } elseif ($action === 'delete_borrow') {
            if (!auth_is_admin()) {
                throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถลบรายการได้');
            }
            $borrowId = (int) ($_POST['borrow_id'] ?? 0);
            inventory_delete_borrow_record($borrowId);
            inventory_flash_redirect('ลบรายการยืม-คืนเรียบร้อยแล้ว', 'borrow.php');
        } elseif ($action === 'save_settings') {
            if (!auth_is_admin()) {
                throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถแก้ไขการตั้งค่าระบบได้');
            }
            inventory_set_setting('telegram_notify_enabled', isset($_POST['telegram_notify_enabled']) ? '1' : '0');
            inventory_set_setting('telegram_bot_token', trim((string) ($_POST['telegram_bot_token'] ?? '')));
            inventory_set_setting('telegram_chat_id', trim((string) ($_POST['telegram_chat_id'] ?? '')));

            inventory_set_setting('google_sheet_sync_enabled', isset($_POST['google_sheet_sync_enabled']) ? '1' : '0');
            inventory_set_setting('google_sheet_webhook_url', trim((string) ($_POST['google_sheet_webhook_url'] ?? '')));

            audit_log('settings_update', 'notifications', 'อัปเดตการตั้งค่า Telegram และ Google Sheets Webhook');
            inventory_flash_redirect('บันทึกการตั้งค่าระบบเรียบร้อยแล้ว', 'borrow.php');
        } elseif ($action === 'test_telegram') {
            if (!auth_is_admin()) {
                throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถทดสอบการแจ้งเตือนได้');
            }
            $mockRecord = [
                'id' => 9999,
                'borrower_name' => auth_user()['name'] ?? 'ผู้ทดสอบระบบ',
                'product_name' => 'รายการทดสอบระบบ (Test Item)',
                'product_code' => 'TEST-001',
                'quantity' => 1,
                'unit' => 'pcs (ชิ้น)',
                'location' => 'หน้างานทดสอบ / Test Line',
                'borrow_date' => date('Y-m-d'),
                'borrow_time' => date('H:i:s'),
                'notes' => 'ข้อความทดสอบการเชื่อมต่อ Telegram จาก Store Automeation',
                'status' => 'borrowed',
                'created_by_name' => auth_user()['name'] ?? 'Admin',
            ];
            $res = inventory_notify_telegram($mockRecord, 'borrow');
            if ($res['success']) {
                inventory_flash_redirect('ส่งข้อความทดสอบเข้า Telegram สำเร็จ! กรุณาตรวจสอบในแชต Telegram', 'borrow.php');
            } else {
                throw new DomainException('ส่งข้อความเข้า Telegram ไม่สำเร็จ: ' . $res['message']);
            }
        } elseif ($action === 'test_google_sheet') {
            if (!auth_is_admin()) {
                throw new DomainException('เฉพาะ Admin เท่านั้นที่สามารถทดสอบการเชื่อมต่อได้');
            }
            $mockRecord = [
                'id' => 9999,
                'borrower_name' => auth_user()['name'] ?? 'ผู้ทดสอบระบบ',
                'product_name' => 'รายการทดสอบระบบ (Test Item)',
                'product_code' => 'TEST-001',
                'quantity' => 1,
                'unit' => 'pcs (ชิ้น)',
                'location' => 'หน้างานทดสอบ / Test Line',
                'borrow_date' => date('Y-m-d'),
                'borrow_time' => date('H:i:s'),
                'notes' => 'ทดสอบเชื่อมต่อ Google Sheets จาก Store Automeation',
                'status' => 'borrowed',
                'created_by_name' => auth_user()['name'] ?? 'Admin',
            ];
            $res = inventory_sync_google_sheet($mockRecord, 'borrow');
            if ($res['success']) {
                inventory_flash_redirect('ส่งข้อมูลทดสอบเข้า Google Sheet สำเร็จ! กรุณาเปิดตรวจสอบใน Google Sheets', 'borrow.php');
            } else {
                throw new DomainException('ส่งข้อมูลเข้า Google Sheet ไม่สำเร็จ: ' . $res['message']);
            }
        }
    } catch (Throwable $e) {
        $notice = $e->getMessage();
        $noticeType = 'error';
    }
}

$statusFilter = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : 'all';
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

$counts = inventory_borrow_counts();
$records = inventory_borrow_records($statusFilter, $searchQuery);
$pageProducts = inventory_products();
$settings = inventory_get_settings();
$unitOptions = inventory_unit_options();

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page borrow-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">BORROW & RETURN TRACKING</div>
            <h1>ยืม–คืนพัสดุและอุปกรณ์<span class="heading-period">.</span></h1>
            <p>บันทึกการยืมพัสดุ ตรวจสอบรายการที่ยังไม่คืน พร้อมระบบแจ้งเตือน Telegram และบันทึกลง Google Sheets
                อัตโนมัติ</p>
        </div>
        <div class="page-kpis">
            <?php if (!auth_is_viewer()): ?>
                <button class="primary-button" type="button" onclick="openBorrowDialog()">
                    + บันทึกการยืมพัสดุ
                </button>
            <?php endif; ?>
            <?php if (auth_is_admin()): ?>
                <button class="secondary-button" type="button"
                    onclick="document.querySelector('#settings-dialog').showModal()">
                    ตั้งค่าแจ้งเตือน (Telegram / Sheets)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Strip -->
    <div class="stats-strip borrow-stats-strip" style="margin-bottom:20px;">
        <div class="stat-item">
            <span class="stat-icon icon-box">ALL</span>
            <div>
                <strong><?= number_format($counts['total']) ?> <small
                        style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>รายการยืมทั้งหมด</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-alert"
                style="color:#d97706;background:#fef3c7;font-weight:700;font-size:11px;">OUT</span>
            <div>
                <strong style="color:#d97706;"><?= number_format($counts['borrowed']) ?> <small
                        style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>กำลังยืม (ยังไม่คืน)</span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-check"
                style="color:#16a34a;background:#dcfce7;font-weight:700;font-size:11px;">IN</span>
            <div>
                <strong style="color:#16a34a;"><?= number_format($counts['returned']) ?> <small
                        style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span>คืนพัสดุเรียบร้อยแล้ว</span>
            </div>
        </div>
        <div class="stats-note" style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:12px;color:#64748b;">
                Telegram:
                <strong><?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'เปิดใช้งาน' : 'ปิดอยู่' ?></strong>
            </span>
            <span style="font-size:12px;color:#64748b;">
                Google Sheets:
                <strong><?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'เปิดใช้งาน' : 'ปิดอยู่' ?></strong>
            </span>
        </div>
    </div>

    <!-- Toolbar Filters -->
    <div class="catalog-toolbar" style="margin-bottom:18px;flex-wrap:wrap;gap:10px 14px;">
        <div class="category-filters" role="group" aria-label="กรองสถานะการยืม">
            <a class="filter-chip <?= $statusFilter === 'all' ? 'selected' : '' ?>"
                href="borrow.php<?= $searchQuery !== '' ? '?q=' . urlencode($searchQuery) : '' ?>">
                ทั้งหมด <span><?= $counts['total'] ?></span>
            </a>
            <a class="filter-chip <?= $statusFilter === 'borrowed' ? 'selected' : '' ?>"
                href="borrow.php?status=borrowed<?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>">
                กำลังยืม (ยังไม่คืน) <span><?= $counts['borrowed'] ?></span>
            </a>
            <a class="filter-chip <?= $statusFilter === 'returned' ? 'selected' : '' ?>"
                href="borrow.php?status=returned<?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>">
                คืนแล้ว <span><?= $counts['returned'] ?></span>
            </a>
        </div>
        <form method="get" class="search-box" style="margin-left:auto;min-width:280px;">
            <?php if ($statusFilter !== 'all'): ?>
                <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
            <?php endif; ?>
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                <path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <input type="search" name="q" placeholder="ค้นหาชื่อผู้ยืม, พัสดุ, สถานที่..."
                value="<?= h($searchQuery) ?>" autocomplete="off">
            <kbd>/</kbd>
        </form>
    </div>

    <!-- Table Panel -->
    <section class="inventory-panel table-panel">
        <div class="panel-heading">
            <div>
                <h2>รายการยืม–คืนพัสดุ</h2>
                <p>แสดงรายการยืมพัสดุและอุปกรณ์ สถานะการคืน วันที่เวลา และสถานที่นำไปใช้งาน</p>
            </div>
            <span class="result-count">แสดง <?= count($records) ?> รายการ</span>
        </div>

        <div class="table-scroll">
            <table class="inventory-table borrow-table">
                <thead>
                    <tr>
                        <th style="width:130px;">วัน–เวลาที่ยืม</th>
                        <th>ผู้ยืม</th>
                        <th>รายการพัสดุ / อุปกรณ์</th>
                        <th>สถานที่นำไปใช้ (ใช้ที่)</th>
                        <th style="width:70px;text-align:center;">รูปถ่าย</th>
                        <th style="width:110px;">สถานะ</th>
                        <th style="text-align:right;width:170px;">การกระทำ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <?php
                        $isBorrowed = $r['status'] === 'borrowed';
                        $hasBorrowPhoto = !empty($r['borrow_photo']);
                        $hasReturnPhoto = !empty($r['return_photo']);
                        ?>
                        <tr>
                            <td style="font-size:11px;color:#64748b;white-space:nowrap;">
                                <strong style="display:block;color:#1e293b;"><?= h((string) $r['borrow_date']) ?></strong>
                                <span><?= h(substr((string) $r['borrow_time'], 0, 5)) ?> น.</span>
                            </td>
                            <td>
                                <strong style="color:#1e293b;"><?= h($r['borrower_name']) ?></strong>
                            </td>
                            <td>
                                <strong style="display:block;color:#0758b4;"><?= h($r['product_name']) ?></strong>
                                <small style="color:#64748b;font-size:11px;">
                                    <?= !empty($r['product_code']) ? 'รหัส: ' . h($r['product_code']) . ' • ' : '' ?>
                                    จำนวน: <strong><?= inventory_quantity($r['quantity']) ?></strong> <?= h($r['unit']) ?>
                                </small>
                            </td>
                            <td>
                                <span style="display:inline-flex;align-items:center;gap:4px;color:#334155;">
                                    <?= h($r['location']) ?>
                                </span>
                                <?php if (!empty($r['notes'])): ?>
                                    <small style="display:block;color:#888;font-size:10px;">หมายเหตุ:
                                        <?= h($r['notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($hasBorrowPhoto): ?>
                                    <button type="button" class="borrow-photo-thumb-btn" title="คลิกดูรูปถ่าย"
                                        onclick="showImageModal('<?= h(inventory_product_image_url($r['borrow_photo'])) ?>', 'รูปถ่ายตอนยืม: <?= h(addslashes($r['product_name'])) ?>')">
                                        <img src="<?= h(inventory_product_image_url($r['borrow_photo'])) ?>" alt="รูปตอนยืม"
                                            class="borrow-table-thumb">
                                    </button>
                                <?php else: ?>
                                    <span style="color:#cbd5e1;font-size:11px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isBorrowed): ?>
                                    <span class="user-chip-role" style="background:#fef3c7;color:#b45309;font-weight:700;">
                                        กำลังยืม
                                    </span>
                                <?php else: ?>
                                    <span class="user-chip-role" style="background:#dcfce7;color:#15803d;font-weight:700;">
                                        คืนแล้ว
                                    </span>
                                    <small style="display:block;color:#64748b;font-size:10px;margin-top:2px;">
                                        <?= h((string) $r['return_date']) ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;white-space:nowrap;">
                                <div class="users-action-group">
                                    <?php if ($isBorrowed && !auth_is_viewer()): ?>
                                        <button class="primary-button users-action-btn" type="button"
                                            style="background:linear-gradient(105deg,#16a34a,#15803d);border-color:#16a34a;"
                                            onclick="openReturnDialog(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)">
                                            คืนพัสดุ
                                        </button>
                                    <?php endif; ?>
                                    <button class="secondary-button users-action-btn" type="button"
                                        onclick="openBorrowDetails(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>)">
                                        รายละเอียด
                                    </button>
                                    <?php if (auth_is_admin()): ?>
                                        <form method="post" onsubmit="return confirm('ยืนยันที่จะลบรายการยืม-คืนนี้หรือไม่?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="action" value="delete_borrow">
                                            <input type="hidden" name="borrow_id" value="<?= (int) $r['id'] ?>">
                                            <button class="danger-button users-action-btn" type="submit">ลบ</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($records === []): ?>
            <p class="table-empty">ไม่พบรายการยืม-คืนพัสดุ</p>
        <?php endif; ?>
    </section>

    <!-- Dialog: บันทึกการยืมพัสดุใหม่ -->
    <dialog class="product-detail-dialog" id="borrow-dialog" style="max-width:580px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow">NEW BORROW RECORD</div>
                <h2>บันทึกการยืมพัสดุ</h2>
            </div>
            <button class="product-detail-close" type="button"
                onclick="document.querySelector('#borrow-dialog').close()">×</button>
        </div>
        <form class="inventory-form" method="post" enctype="multipart/form-data" id="borrow-form"
            style="grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_borrow">

            <label class="form-span-2">
                ชื่อผู้ยืม *
                <input type="text" name="borrower_name" required placeholder="เช่น นายสมเกียรติ มั่นคง หรือ ช่างเอ็กซ์"
                    value="<?= h((string) ($currentUser['name'] ?? '')) ?>">
            </label>

            <label class="form-span-2">
                พัสดุ / อุปกรณ์ที่ยืม *
                <div class="product-combobox" id="borrow-item-combobox" data-product-combobox data-allow-custom="true">
                    <input type="hidden" name="product_code" data-combobox-code value="">
                    <div class="product-combobox-input-wrap">
                        <input type="text" name="product_name" data-combobox-search
                            placeholder="พิมพ์ชื่อสินค้าหรือเลือกรหัสสินค้าในคลัง หรือพิมพ์ชื่ออุปกรณ์..."
                            autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false"
                            aria-controls="borrow-item-list" required>
                        <button type="button" class="product-combobox-toggle" data-combobox-toggle
                            aria-label="เปิดรายการสินค้า" tabindex="-1">▾</button>
                    </div>
                    <div class="product-combobox-list" id="borrow-item-list" data-combobox-list role="listbox" hidden>
                        <?php foreach ($pageProducts as $product): ?>
                            <button type="button" class="product-combobox-option" role="option"
                                data-code="<?= h($product['id']) ?>" data-name="<?= h($product['name']) ?>"
                                data-unit="<?= h($product['unit']) ?>"
                                data-search="<?= h(strtolower($product['name'] . ' ' . $product['id'])) ?>">
                                <strong><?= h($product['name']) ?></strong>
                                <small>รหัส <?= h($product['id']) ?> • คงเหลือ <?= inventory_quantity($product['stock']) ?>
                                    <?= h($product['unit']) ?></small>
                            </button>
                        <?php endforeach; ?>
                        <div class="product-combobox-empty" data-combobox-empty hidden>ไม่พบสินค้าในทะเบียน
                            (คุณสามารถพิมพ์ชื่อเองได้โดยตรง)</div>
                    </div>
                </div>
            </label>

            <label>
                จำนวน
                <input type="number" name="quantity" min="1" step="0" required value="1">
            </label>

            <label>
                หน่วยนับ
                <select name="unit" data-combobox-unit-display>
                    <?php foreach ($unitOptions as $u): ?>
                        <option value="<?= h($u) ?>"><?= h($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                วันที่ยืม *
                <input type="date" name="borrow_date" required value="<?= date('Y-m-d') ?>">
            </label>

            <label>
                เวลาที่ยืม *
                <input type="time" name="borrow_time" required value="<?= date('H:i') ?>">
            </label>

            <label class="form-span-2">
                สถานที่นำไปใช้ (ใช้ที่ไหน / หน้างาน) *
                <input type="text" name="location" required
                    placeholder="เช่น Line 1 - Station 3, ฝ่ายซ่อมบำรุง, หน้างานนอกสถานที่">
            </label>

            <label class="form-span-2">
                ถ่ายรูป / แนบรูปถ่ายตอนยืม
                <input type="file" name="borrow_photo" accept="image/*" capture="environment"
                    onchange="previewUploadImage(this, '#borrow-photo-preview')">
                <small style="color:#888;">รองรับ JPG, PNG, WEBP หรือถ่ายภาพจากกล้องมือถือได้ทันที</small>
                <div class="borrow-image-preview-box" id="borrow-photo-preview" hidden>
                    <img src="" alt="พรีวิวรูปภาพ">
                </div>
            </label>

            <label class="form-span-2">
                หมายเหตุเพิ่มเติม (ถ้ามี)
                <input type="text" name="notes" placeholder="เช่น ยืมไปใช้งานด่วน 2 ชั่วโมง">
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;" class="form-span-2">
                <button class="secondary-button" type="button"
                    onclick="document.querySelector('#borrow-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit">บันทึกการยืม</button>
            </div>
        </form>
    </dialog>

    <!-- Dialog: บันทึกการคืนพัสดุ -->
    <dialog class="product-detail-dialog" id="return-dialog" style="max-width:540px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow" style="color:#16a34a;">RETURN ITEM</div>
                <h2>บันทึกคืนพัสดุ</h2>
            </div>
            <button class="product-detail-close" type="button"
                onclick="document.querySelector('#return-dialog').close()">×</button>
        </div>
        <form class="inventory-form" method="post" enctype="multipart/form-data" id="return-form"
            style="grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="return_borrow">
            <input type="hidden" name="borrow_id" id="return-borrow-id" value="">

            <div class="form-span-2"
                style="padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="font-size:11px;color:#64748b;">รายการพัสดุที่ยืม:</div>
                <strong id="return-item-title" style="font-size:14px;color:#0758b4;display:block;"></strong>
                <div style="font-size:12px;color:#334155;margin-top:4px;">
                    ผู้ยืม: <strong id="return-borrower-name"></strong> • ใช้ที่: <span id="return-location"></span>
                </div>
            </div>

            <label>
                วันที่คืน *
                <input type="date" name="return_date" required value="<?= date('Y-m-d') ?>">
            </label>

            <label>
                เวลาที่คืน *
                <input type="time" name="return_time" required value="<?= date('H:i') ?>">
            </label>

            <label class="form-span-2">
                ผู้รับคืน *
                <input type="text" name="return_received_by" required placeholder="ชื่อเจ้าหน้าที่ผู้รับคืน"
                    value="<?= h((string) ($currentUser['name'] ?? 'เจ้าหน้าที่คลัง')) ?>">
            </label>

            <label class="form-span-2">
                ถ่ายรูป / แนบรูปสภาพของตอนคืน
                <input type="file" name="return_photo" accept="image/*" capture="environment"
                    onchange="previewUploadImage(this, '#return-photo-preview')">
                <small style="color:#888;">ถ่ายภาพสภาพของเพื่อเป็นหลักฐานยืนยันการรับคืน</small>
                <div class="borrow-image-preview-box" id="return-photo-preview" hidden>
                    <img src="" alt="พรีวิวรูปภาพตอนคืน">
                </div>
            </label>

            <label class="form-span-2">
                หมายเหตุการคืน (สภาพของ / ความเรียบร้อย)
                <input type="text" name="return_notes" placeholder="เช่น สภาพสมบูรณ์พร้อมใช้งานต่อ">
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;" class="form-span-2">
                <button class="secondary-button" type="button"
                    onclick="document.querySelector('#return-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit"
                    style="background:linear-gradient(105deg,#16a34a,#15803d);border-color:#16a34a;">
                    ยืนยันการรับคืน
                </button>
            </div>
        </form>
    </dialog>

    <!-- Dialog: ดูรายละเอียดเต็ม -->
    <dialog class="product-detail-dialog" id="borrow-detail-dialog" style="max-width:580px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow">BORROW DETAILS</div>
                <h2 id="detail-title">รายละเอียดการยืม-คืน</h2>
            </div>
            <button class="product-detail-close" type="button"
                onclick="document.querySelector('#borrow-detail-dialog').close()">×</button>
        </div>
        <div style="padding:16px 0;">
            <dl class="product-detail-list" id="borrow-detail-dl" style="grid-template-columns:1fr 1fr;gap:0 18px;">
            </dl>

            <div id="detail-photos-wrap"
                style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px;border-top:1px solid #e2e8f0;padding-top:16px;">
                <div id="detail-borrow-photo-box" hidden>
                    <div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;">รูปถ่ายตอนยืม</div>
                    <img src="" id="detail-borrow-photo-img" alt="รูปตอนยืม" class="borrow-detail-photo">
                </div>
                <div id="detail-return-photo-box" hidden>
                    <div style="font-size:11px;font-weight:600;color:#64748b;margin-bottom:6px;">รูปถ่ายตอนคืน</div>
                    <img src="" id="detail-return-photo-img" alt="รูปตอนคืน" class="borrow-detail-photo">
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1px solid #edf0eb;padding-top:14px;">
            <button class="secondary-button" type="button"
                onclick="document.querySelector('#borrow-detail-dialog').close()">ปิด</button>
        </div>
    </dialog>

    <!-- Dialog: ดูรูปใหญ่ Modal -->
    <dialog class="product-detail-dialog" id="image-view-dialog" style="max-width:640px;text-align:center;">
        <div class="product-detail-heading">
            <h3 id="image-view-title" style="margin:0;font-size:15px;color:#1e293b;"></h3>
            <button class="product-detail-close" type="button"
                onclick="document.querySelector('#image-view-dialog').close()">×</button>
        </div>
        <div style="padding:14px 0;">
            <img id="image-view-img" src="" alt="รูปภาพขนาดใหญ่"
                style="max-width:100%;max-height:70vh;object-fit:contain;border-radius:8px;">
        </div>
    </dialog>

    <!-- Dialog: ตั้งค่าการแจ้งเตือน (Telegram & Google Sheets) -->
    <dialog class="product-detail-dialog" id="settings-dialog" style="max-width:680px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow">INTEGRATIONS & NOTIFICATIONS</div>
                <h2>ตั้งค่า Telegram & Google Sheets</h2>
            </div>
            <button class="product-detail-close" type="button"
                onclick="document.querySelector('#settings-dialog').close()">×</button>
        </div>

        <form method="post" class="inventory-form" style="grid-template-columns:1fr;gap:20px;margin-top:16px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="save_settings">

            <!-- Section 1: Telegram Notification -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span
                            style="font-size:12px;font-weight:700;color:#0758b4;background:#e0f2fe;padding:4px 8px;border-radius:6px;">TG</span>
                        <div>
                            <strong style="font-size:14px;color:#1e293b;">แจ้งเตือนผ่าน Telegram</strong>
                            <p style="margin:0;font-size:11px;color:#64748b;">
                                ส่งข้อความและรูปถ่ายแจ้งเตือนทันทีเมื่อมีการยืมหรือคืนพัสดุ</p>
                        </div>
                    </div>
                    <label
                        style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;color:#0758b4;">
                        <input type="checkbox" name="telegram_notify_enabled" value="1"
                            <?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                            style="width:17px;height:17px;accent-color:#0758b4;">
                        เปิดใช้งาน
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <label>
                        Telegram Bot Token
                        <input type="password" name="telegram_bot_token"
                            value="<?= h((string) ($settings['telegram_bot_token'] ?? '')) ?>"
                            placeholder="เช่น 123456789:ABCdefGhIjkLmNoP...">
                        <small style="color:#888;">ได้จาก @BotFather ใน Telegram</small>
                    </label>
                    <label>
                        Telegram Chat ID / Group ID
                        <input type="text" name="telegram_chat_id"
                            value="<?= h((string) ($settings['telegram_chat_id'] ?? '')) ?>"
                            placeholder="เช่น -100123456789 หรือ 12345678">
                        <small style="color:#888;">Chat ID ของคุณหรือกลุ่มที่ต้องการให้แจ้งเตือน</small>
                    </label>
                </div>

                <div
                    style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px dashed #cbd5e1;">
                    <span style="font-size:11px;color:#64748b;">ส่งรูปถ่ายแบบ Binary ตรงเข้า Telegram ทันที แม้จะรันบน
                        Localhost</span>
                    <?php if (auth_is_admin() && !empty($settings['telegram_bot_token'])): ?>
                        <button type="submit" form="test-telegram-form" class="secondary-button"
                            style="padding:4px 10px;font-size:11px;">
                            ทดสอบส่งเข้า Telegram
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section 2: Google Sheets Webhook -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span
                            style="font-size:12px;font-weight:700;color:#16a34a;background:#dcfce7;padding:4px 8px;border-radius:6px;">GS</span>
                        <div>
                            <strong style="font-size:14px;color:#1e293b;">ส่งข้อมูลเข้า Google Sheets
                                (Real-time)</strong>
                            <p style="margin:0;font-size:11px;color:#64748b;">เพิ่มแถวบันทึกลงใน Google Sheet
                                ของคุณอัตโนมัติเมื่อกดบันทึกยืม/คืน</p>
                        </div>
                    </div>
                    <label
                        style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:600;color:#16a34a;">
                        <input type="checkbox" name="google_sheet_sync_enabled" value="1"
                            <?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                            style="width:17px;height:17px;accent-color:#16a34a;">
                        เปิดใช้งาน
                    </label>
                </div>

                <label>
                    Google Apps Script Web App URL
                    <input type="url" name="google_sheet_webhook_url"
                        value="<?= h((string) ($settings['google_sheet_webhook_url'] ?? '')) ?>"
                        placeholder="https://script.google.com/macros/s/AKfycb.../exec">
                    <small style="color:#888;">URL ของ Web App ที่ Deploy จาก Google Apps Script ใน Sheet ของคุณ</small>
                </label>

                <div
                    style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding-top:10px;border-top:1px dashed #cbd5e1;">
                    <button type="button" class="secondary-button" style="padding:4px 10px;font-size:11px;"
                        onclick="document.querySelector('#gas-guide-box').hidden = !document.querySelector('#gas-guide-box').hidden">
                        ดูวิธีตั้งค่า & โค้ด Google Apps Script
                    </button>
                    <?php if (auth_is_admin() && !empty($settings['google_sheet_webhook_url'])): ?>
                        <button type="submit" form="test-sheet-form" class="secondary-button"
                            style="padding:4px 10px;font-size:11px;">
                            ทดสอบส่งเข้า Google Sheet
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Google Apps Script Guide Box -->
                <div id="gas-guide-box"
                    style="margin-top:14px;padding:14px;background:#fff;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;line-height:1.6;"
                    hidden>
                    <strong style="color:#0758b4;display:block;margin-bottom:6px;">วิธีเชื่อมต่อกับ Google Sheets ใน 3
                        ขั้นตอน:</strong>
                    <ol style="margin:0 0 10px 18px;padding:0;color:#334155;">
                        <li>เปิด Google Sheet ของคุณ > ไปที่เมนู <strong>ส่วนขยาย (Extensions)</strong> > <strong>Apps
                                Script</strong></li>
                        <li>ลบโค้ดเดิมออกทั้งหมด แล้ววางโค้ดด้านล่างนี้ลงไป > กด <strong>บันทึก (Save)</strong></li>
                        <li>กดปุ่มสีน้ำเงิน <strong>ทำให้ใช้งานได้ (Deploy)</strong> >
                            <strong>การทำให้ใช้งานได้รายการใหม่ (New deployment)</strong> > เลือกประเภท <strong>เว็บแอป
                                (Web app)</strong> > ตั้งค่า Who has access เป็น <strong>ทุกคน (Anyone)</strong> >
                            คัดลอก URL มาวางในช่องด้านบน</li>
                    </ol>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <span style="font-weight:600;color:#475569;">โค้ด Apps Script:</span>
                        <button type="button" class="secondary-button" style="padding:2px 8px;font-size:10px;"
                            onclick="copyGasCode()">คัดลอกโค้ด</button>
                    </div>
                    <textarea id="gas-code-textarea" readonly
                        style="width:100%;height:260px;font-family:monospace;font-size:11px;padding:8px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:4px;">function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var ss = SpreadsheetApp.getActiveSpreadsheet();

    // 1. บันทึกรับ-เบิกสินค้า (Receipt / Issue) และอัปเดตยอดคงเหลือในทะเบียนสินค้า
    if (data.event === "movement_sync" || data.action === "receipt" || data.action === "issue") {
      updateCatalogStock(ss, data);
      logMovement(ss, data);
      return jsonResponse({status: "ok", message: "Movement recorded and catalog updated"});
    }

    // 2. ซิงค์ทะเบียนสินค้าทั้งหมด (Batch Catalog Sync)
    if (data.event === "catalog_batch_sync") {
      batchSyncCatalog(ss, data.products || [], data.timestamp);
      return jsonResponse({status: "ok", message: "Catalog batch sync completed"});
    }

    // 3. ซิงค์ข้อมูลสินค้าเดี่ยว (Single Product Catalog Sync)
    if (data.event === "catalog_sync" || data.action === "catalog_update") {
      updateCatalogStock(ss, data);
      return jsonResponse({status: "ok", message: "Product catalog updated"});
    }

    // 4. บันทึกระบบ ยืม-คืน พัสดุ (Borrow & Return Sync)
    return handleBorrowSync(ss, data);

  } catch (err) {
    return jsonResponse({status: "error", message: err.toString()});
  }
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}

function getOrCreateSheet(ss, name, headers) {
  var sheet = ss.getSheetByName(name);
  if (!sheet) {
    var sheets = ss.getSheets();
    if (sheets.length === 1 && sheets[0].getLastRow() === 0) {
      sheet = sheets[0];
      sheet.setName(name);
    } else {
      sheet = ss.insertSheet(name);
    }
  }
  if (sheet.getLastRow() === 0 && headers && headers.length > 0) {
    sheet.appendRow(headers);
    sheet.setFrozenRows(1);
    sheet.getRange(1, 1, 1, headers.length).setFontWeight("bold").setBackground("#f1f5f9");
  }
  return sheet;
}

function updateCatalogStock(ss, data) {
  var catalogSheet = getOrCreateSheet(ss, "ทะเบียนสินค้า", [
    "รหัสสินค้า", "ชื่อสินค้า", "หมวดหมู่", "คงเหลือ", "หน่วยนับ",
    "จุดสั่งซื้อขั้นต่ำ", "ตำแหน่งจัดเก็บ", "อัปเดตล่าสุด"
  ]);

  var targetCode = String(data.product_code || "").trim();
  if (!targetCode) return;

  var values = catalogSheet.getDataRange().getValues();
  var foundRow = 0;
  for (var i = 1; i < values.length; i++) {
    if (String(values[i][0]).trim() === targetCode) {
      foundRow = i + 1;
      break;
    }
  }

  var newStock = (data.current_stock !== undefined && data.current_stock !== null)
                 ? data.current_stock
                 : (data.stock !== undefined ? data.stock : 0);
  var ts = data.timestamp || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss");

  if (foundRow > 0) {
    catalogSheet.getRange(foundRow, 4).setValue(newStock);
    catalogSheet.getRange(foundRow, 8).setValue(ts);
    if (data.product_name) catalogSheet.getRange(foundRow, 2).setValue(data.product_name);
    if (data.category) catalogSheet.getRange(foundRow, 3).setValue(data.category);
    if (data.unit) catalogSheet.getRange(foundRow, 5).setValue(data.unit);
  } else {
    catalogSheet.appendRow([
      targetCode,
      data.product_name || "",
      data.category || "",
      newStock,
      data.unit || "",
      data.reorder_point || 0,
      data.location || "",
      ts
    ]);
  }
}

function logMovement(ss, data) {
  var movSheet = getOrCreateSheet(ss, "ประวัติรับ-เบิก", [
    "วัน-เวลา", "ประเภท", "รหัสสินค้า", "ชื่อสินค้า", "จำนวน", "หน่วยนับ",
    "คงเหลือหลังทำรายการ", "เลขที่เอกสาร", "ผู้รับ/ผู้ขอเบิก", "ผู้อนุมัติ", "หมายเหตุ", "ผู้บันทึก"
  ]);

  var isReceipt = (data.action === "receipt");
  var qtyPrefix = isReceipt ? "+" : "-";
  var actor = isReceipt ? (data.received_by || "-") : (data.requested_by || "-");
  var approver = isReceipt ? "-" : (data.approved_by || "-");

  movSheet.appendRow([
    data.timestamp || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss"),
    data.action_name || (isReceipt ? "รับสินค้า" : "เบิกสินค้า"),
    data.product_code || "",
    data.product_name || "",
    qtyPrefix + (data.quantity || 0),
    data.unit || "",
    data.current_stock !== undefined ? data.current_stock : 0,
    data.document_no || "-",
    actor,
    approver,
    data.notes || "-",
    data.created_by || "-"
  ]);
}

function batchSyncCatalog(ss, products, ts) {
  var catalogSheet = getOrCreateSheet(ss, "ทะเบียนสินค้า", [
    "รหัสสินค้า", "ชื่อสินค้า", "หมวดหมู่", "คงเหลือ", "หน่วยนับ",
    "จุดสั่งซื้อขั้นต่ำ", "ตำแหน่งจัดเก็บ", "อัปเดตล่าสุด"
  ]);

  var lastRow = catalogSheet.getLastRow();
  if (lastRow > 1) {
    catalogSheet.getRange(2, 1, lastRow - 1, catalogSheet.getLastColumn()).clearContent();
  }

  var timeStr = ts || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss");
  var rows = [];
  for (var i = 0; i < products.length; i++) {
    var p = products[i];
    rows.push([
      p.product_code || p.code || "",
      p.product_name || p.name || "",
      p.category || "",
      p.current_stock !== undefined ? p.current_stock : (p.stock || 0),
      p.unit || "",
      p.reorder_point || p.minimum || 0,
      p.location || "",
      timeStr
    ]);
  }
  if (rows.length > 0) {
    catalogSheet.getRange(2, 1, rows.length, 8).setValues(rows);
  }
}

function handleBorrowSync(ss, data) {
  var borrowSheet = ss.getSheetByName("ยืม-คืน");
  if (!borrowSheet) {
    var firstSheet = ss.getSheets()[0];
    var firstRowText = firstSheet.getLastRow() > 0 ? firstSheet.getRange(1, 1, 1, Math.min(firstSheet.getLastColumn(), 16)).getValues()[0].join(" ") : "";
    if (firstRowText.indexOf("ยืม") !== -1 || firstRowText.indexOf("พัสดุ") !== -1 || firstRowText.indexOf("ID") !== -1) {
      borrowSheet = firstSheet;
      try { borrowSheet.setName("ยืม-คืน"); } catch(e) {}
    } else {
      borrowSheet = getOrCreateSheet(ss, "ยืม-คืน", [
        "ID", "กิจกรรม", "ผู้ยืม", "รายการพัสดุ", "รหัสสินค้า",
        "จำนวน", "หน่วยนับ", "สถานที่ใช้งาน", "วันที่ยืม", "เวลาที่ยืม",
        "สถานะ", "วันที่คืน", "เวลาที่คืน", "ผู้รับคืน", "หมายเหตุ", "เวลาที่บันทึก"
      ]);
    }
  }

  var isReturn = (data.action === "return" || data.status === "คืนแล้ว" || data.status_code === "returned");
  var statusText = isReturn ? "คืนแล้ว" : "กำลังยืม";
  var actionText = isReturn ? "คืนพัสดุ" : "ยืมพัสดุ";
  var itemName = data.product_name || data.item || data.product || "";

  var targetId = String(data.id || "");
  var foundRow = 0;
  if (targetId !== "") {
    var allData = borrowSheet.getDataRange().getValues();
    for (var i = 1; i < allData.length; i++) {
      if (String(allData[i][0]) === targetId) {
        foundRow = i + 1;
        break;
      }
    }
  }

  if (foundRow > 0 && isReturn) {
    borrowSheet.getRange(foundRow, 11).setValue("คืนแล้ว");
    borrowSheet.getRange(foundRow, 12).setValue(data.return_date || "");
    borrowSheet.getRange(foundRow, 13).setValue(data.return_time || "");
    borrowSheet.getRange(foundRow, 14).setValue(data.return_received_by || "");
    if (data.return_notes) {
      borrowSheet.getRange(foundRow, 15).setValue(data.return_notes);
    }
  } else {
    borrowSheet.appendRow([
      data.id || "",
      actionText,
      data.borrower_name || data.name || "",
      itemName,
      data.product_code || "",
      data.quantity || 1,
      data.unit || "",
      data.location || "",
      data.borrow_date || "",
      data.borrow_time || "",
      statusText,
      "", "", "",
      data.notes || "",
      data.timestamp || new Date().toLocaleString("th-TH")
    ]);
  }

  return jsonResponse({status: "ok", message: "Borrow record processed"});
}</textarea>
                </div>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button class="secondary-button" type="button"
                    onclick="document.querySelector('#settings-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit">บันทึกการตั้งค่า</button>
            </div>
        </form>
    </dialog>

    <!-- Hidden form for Test Telegram -->
    <form method="post" id="test-telegram-form" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="test_telegram">
    </form>

    <!-- Hidden form for Test Google Sheet -->
    <form method="post" id="test-sheet-form" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="test_google_sheet">
    </form>
</section>

<style>
    .borrow-table th,
    .borrow-table td {
        vertical-align: middle;
    }

    .borrow-table-thumb {
        width: 38px;
        height: 38px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        display: block;
        cursor: pointer;
        transition: transform .15s;
    }

    .borrow-table-thumb:hover {
        transform: scale(1.1);
    }

    .borrow-photo-thumb-btn {
        padding: 0;
        border: 0;
        background: transparent;
        cursor: pointer;
    }

    .borrow-image-preview-box {
        margin-top: 8px;
        padding: 6px;
        border: 1px dashed #cbd5e1;
        border-radius: 6px;
        max-width: 160px;
    }

    .borrow-image-preview-box img {
        width: 100%;
        max-height: 120px;
        object-fit: contain;
        border-radius: 4px;
        display: block;
    }

    .borrow-detail-photo {
        width: 100%;
        height: 160px;
        object-fit: contain;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }
</style>

<script>
    function previewUploadImage(input, previewSelector) {
        const box = document.querySelector(previewSelector);
        if (!box) return;
        const img = box.querySelector('img');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
                box.hidden = false;
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            img.src = '';
            box.hidden = true;
        }
    }

    function showImageModal(src, title) {
        const dialog = document.querySelector('#image-view-dialog');
        document.querySelector('#image-view-img').src = src;
        document.querySelector('#image-view-title').textContent = title || 'รูปถ่าย';
        dialog.showModal();
    }

    function openBorrowDialog() {
        const dialog = document.querySelector('#borrow-dialog');
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())}`;

        const dateInput = dialog.querySelector('input[name="borrow_date"]');
        const timeInput = dialog.querySelector('input[name="borrow_time"]');
        if (dateInput) dateInput.value = dateStr;
        if (timeInput) timeInput.value = timeStr;

        dialog.showModal();
    }

    function openReturnDialog(record) {
        const dialog = document.querySelector('#return-dialog');
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())}`;

        const dateInput = dialog.querySelector('input[name="return_date"]');
        const timeInput = dialog.querySelector('input[name="return_time"]');
        if (dateInput) dateInput.value = dateStr;
        if (timeInput) timeInput.value = timeStr;

        document.querySelector('#return-borrow-id').value = record.id;
        document.querySelector('#return-item-title').textContent = record.product_name + (record.product_code ? ' (' + record.product_code + ')' : '') + ' • ' + record.quantity + ' ' + record.unit;
        document.querySelector('#return-borrower-name').textContent = record.borrower_name;
        document.querySelector('#return-location').textContent = record.location;
        dialog.showModal();
    }

    function openBorrowDetails(record) {
        const dialog = document.querySelector('#borrow-detail-dialog');
        const dl = document.querySelector('#borrow-detail-dl');
        const isBorrowed = record.status === 'borrowed';

        const fields = [
            ['รหัสรายการ', '#' + record.id],
            ['สถานะ', isBorrowed ? 'กำลังยืม' : 'คืนแล้ว'],
            ['ผู้ยืม', record.borrower_name],
            ['พัสดุ / อุปกรณ์', record.product_name],
            ['รหัสสินค้า', record.product_code || '—'],
            ['จำนวน', record.quantity + ' ' + record.unit],
            ['สถานที่นำไปใช้', record.location],
            ['วันที่–เวลายืม', record.borrow_date + ' ' + (record.borrow_time ? record.borrow_time.substring(0, 5) : '') + ' น.'],
            ['หมายเหตุยืม', record.notes || '—'],
            ['ผู้บันทึกการยืม', record.created_by_name || '—']
        ];

        if (!isBorrowed) {
            fields.push(
                ['วันที่–เวลาคืน', (record.return_date || '—') + ' ' + (record.return_time ? record.return_time.substring(0, 5) : '') + ' น.'],
                ['ผู้รับคืน', record.return_received_by || '—'],
                ['หมายเหตุคืน', record.return_notes || '—']
            );
        }

        dl.replaceChildren();
        fields.forEach(([label, val]) => {
            const div = document.createElement('div');
            div.style.display = 'flex';
            div.style.justifyContent = 'space-between';
            div.style.padding = '8px 0';
            div.style.borderBottom = '1px solid #edf0eb';

            const dt = document.createElement('dt');
            dt.style.color = '#64748b';
            dt.style.fontSize = '12px';
            dt.textContent = label;

            const dd = document.createElement('dd');
            dd.style.margin = '0';
            dd.style.fontWeight = '600';
            dd.style.color = '#1e293b';
            dd.style.fontSize = '12px';
            dd.style.textAlign = 'right';
            dd.textContent = val;

            div.append(dt, dd);
            dl.append(div);
        });

        // Handle photos
        const borrowPhotoBox = document.querySelector('#detail-borrow-photo-box');
        const borrowPhotoImg = document.querySelector('#detail-borrow-photo-img');
        const returnPhotoBox = document.querySelector('#detail-return-photo-box');
        const returnPhotoImg = document.querySelector('#detail-return-photo-img');

        if (record.borrow_photo) {
            borrowPhotoImg.src = 'image.php?file=' + encodeURIComponent(record.borrow_photo);
            borrowPhotoBox.hidden = false;
        } else {
            borrowPhotoBox.hidden = true;
        }

        if (record.return_photo) {
            returnPhotoImg.src = 'image.php?file=' + encodeURIComponent(record.return_photo);
            returnPhotoBox.hidden = false;
        } else {
            returnPhotoBox.hidden = true;
        }

        dialog.showModal();
    }

    function copyGasCode() {
        const ta = document.querySelector('#gas-code-textarea');
        ta.select();
        navigator.clipboard.writeText(ta.value).then(() => {
            alert('คัดลอกโค้ด Google Apps Script เรียบร้อยแล้ว!');
        }).catch(() => {
            document.execCommand('copy');
            alert('คัดลอกโค้ดเรียบร้อยแล้ว!');
        });
    }
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>