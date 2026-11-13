<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

auth_require_role('admin');

$pageTitle = 'ตั้งค่าระบบ (Telegram & Google Sheets) | Store Automeation';
$pageDescription = 'ตั้งค่าการแจ้งเตือน Telegram และเชื่อมต่อบันทึกข้อมูลกับ Google Sheets';
$activePage = 'settings';

$settings = inventory_get_settings();
$productCount = count(inventory_products());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    inventory_require_csrf();

    if ($action === 'save_settings') {
      inventory_set_setting('telegram_notify_enabled', isset($_POST['telegram_notify_enabled']) ? '1' : '0');
      inventory_set_setting('telegram_bot_token', trim((string) ($_POST['telegram_bot_token'] ?? '')));
      inventory_set_setting('telegram_chat_id', trim((string) ($_POST['telegram_chat_id'] ?? '')));

      inventory_set_setting('google_sheet_sync_enabled', isset($_POST['google_sheet_sync_enabled']) ? '1' : '0');
      inventory_set_setting('google_sheet_webhook_url', trim((string) ($_POST['google_sheet_webhook_url'] ?? '')));

      audit_log('settings_update', 'notifications', 'อัปเดตการตั้งค่า Telegram และ Google Sheets Webhook');
      inventory_flash_redirect('บันทึกการตั้งค่า Telegram และ Google Sheets เรียบร้อยแล้ว', 'settings.php');

    } elseif ($action === 'test_telegram') {
      $mockMovement = [
        'id' => 99999,
        'product_code' => 'TEST-001',
        'quantity' => 1,
        'document_no' => 'TEST-DOC',
        'movement_date' => date('Y-m-d'),
        'received_by' => 'ผู้ดูแลระบบ (ทดสอบ)',
        'notes' => 'ข้อความทดสอบการเชื่อมต่อระบบแจ้งเตือน Telegram จาก Store Automeation',
        'created_by_name' => $currentUser['name'] ?? 'Admin',
      ];
      $mockProduct = [
        'code' => 'TEST-001',
        'name' => 'รายการทดสอบ (Test Product)',
        'category' => 'ทดสอบระบบ',
        'unit' => 'pcs (ชิ้น)',
        'stock' => 10,
        'reorder_point' => 5,
        'location' => 'Zone-Test',
        'image_filename' => null,
      ];
      $res = inventory_notify_telegram_movement($mockMovement, 'receipt', $mockProduct);
      if ($res['success']) {
        inventory_flash_redirect('ส่งข้อความทดสอบเข้า Telegram สำเร็จ! กรุณาตรวจสอบในห้องแชต Telegram', 'settings.php');
      } else {
        throw new DomainException('ส่งข้อความเข้า Telegram ไม่สำเร็จ: ' . $res['message']);
      }

    } elseif ($action === 'test_google_sheet') {
      $mockMovement = [
        'id' => 99999,
        'product_code' => 'TEST-001',
        'quantity' => 1,
        'document_no' => 'TEST-SHEET',
        'movement_date' => date('Y-m-d'),
        'received_by' => 'ผู้ดูแลระบบ (ทดสอบ)',
        'notes' => 'ทดสอบการส่งข้อมูลเข้า Google Sheets จาก Store Automeation',
        'created_by_name' => $currentUser['name'] ?? 'Admin',
      ];
      $mockProduct = [
        'code' => 'TEST-001',
        'name' => 'รายการทดสอบ (Test Product)',
        'category' => 'ทดสอบระบบ',
        'unit' => 'pcs (ชิ้น)',
        'stock' => 10,
        'reorder_point' => 5,
        'location' => 'Zone-Test',
      ];
      $res = inventory_sync_google_sheet_movement($mockMovement, 'receipt', $mockProduct);
      if ($res['success']) {
        inventory_flash_redirect('ส่งข้อมูลทดสอบเข้า Google Sheet สำเร็จ (' . $res['message'] . ') กรุณาตรวจสอบในชีต', 'settings.php');
      } else {
        throw new DomainException('ส่งข้อมูลเข้า Google Sheet ไม่สำเร็จ: ' . $res['message']);
      }

    } elseif ($action === 'sync_all_catalog') {
      $res = inventory_sync_all_catalog_to_google_sheet();
      if ($res['success']) {
        inventory_flash_redirect('ซิงค์ทะเบียนสินค้าทั้งหมด (' . $productCount . ' รายการ) ไปยัง Google Sheet สำเร็จแล้ว', 'settings.php');
      } else {
        throw new DomainException('ซิงค์ทะเบียนสินค้าไม่สำเร็จ: ' . $res['message']);
      }
    }
  } catch (DomainException $e) {
    $notice = $e->getMessage();
    $noticeType = 'error';
  }
}

require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
  <div class="page-heading">
    <div>
      <div class="eyebrow section-eyebrow">SYSTEM SETTINGS</div>
      <h1>ตั้งค่าระบบ<span class="heading-period">.</span></h1>
      <p>จัดการการแจ้งเตือน Telegram และเชื่อมต่อบันทึกข้อมูลกับ Google Sheets (แบบเรียลไทม์)</p>
    </div>
    <div class="page-kpis">
      <a class="secondary-button" href="borrow.php">ระบบยืม-คืนพัสดุ ↗</a>
      <a class="secondary-button" href="catalog.php">ทะเบียนสินค้า ↗</a>
    </div>
  </div>

  <!-- Status Strip -->
  <div class="stats-strip" style="margin-bottom:24px;">
    <div class="stat-item">
      <span class="stat-icon" style="background:#e0f2fe;color:#0758b4;font-weight:700;">TG</span>
      <div>
        <strong><?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'เปิดใช้งาน' : 'ปิดอยู่' ?></strong>
        <span>แจ้งเตือน Telegram</span>
      </div>
    </div>
    <div class="stat-item">
      <span class="stat-icon" style="background:#dcfce7;color:#16a34a;font-weight:700;">GS</span>
      <div>
        <strong><?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'เปิดใช้งาน' : 'ปิดอยู่' ?></strong>
        <span>Google Sheets Sync</span>
      </div>
    </div>
  </div>

  <form method="post" class="inventory-form" style="grid-template-columns:1fr;gap:24px;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="save_settings">

    <!-- Card 1: Telegram Notifications -->
    <div class="inventory-panel" style="padding:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <span
            style="font-size:14px;font-weight:700;color:#0758b4;background:#e0f2fe;padding:6px 12px;border-radius:8px;">TG</span>
          <div>
            <h2 style="margin:0;font-size:18px;color:#1e293b;">การแจ้งเตือนผ่าน Telegram Bot</h2>
            <p style="margin:2px 0 0;font-size:13px;color:#64748b;">
              ส่งข้อความแจ้งเตือนทันทีเมื่อมีการ <strong>รับสินค้า</strong>, <strong>เบิกสินค้า</strong>, หรือ
              <strong>ยืม-คืนพัสดุ</strong> พร้อมรูปถ่าย
            </p>
          </div>
        </div>
        <label
          style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600;color:#0758b4;">
          <input type="checkbox" name="telegram_notify_enabled" value="1" <?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#0758b4;">
          เปิดใช้งาน Telegram
        </label>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <label>
          Telegram Bot Token
          <input type="password" name="telegram_bot_token"
            value="<?= h((string) ($settings['telegram_bot_token'] ?? '')) ?>"
            placeholder="เช่น 123456789:ABCdefGhIjkLmNoP...">
          <small style="color:#888;">สร้างบอทและรับ Token ได้ฟรีจาก @BotFather ในแอป Telegram</small>
        </label>
        <label>
          Telegram Chat ID / Group ID
          <input type="text" name="telegram_chat_id" value="<?= h((string) ($settings['telegram_chat_id'] ?? '')) ?>"
            placeholder="เช่น -100123456789 หรือ 12345678">
          <small style="color:#888;">Chat ID ของคุณหรือกลุ่มที่ต้องการรับการแจ้งเตือน</small>
        </label>
      </div>

      <div
        style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:14px;border-top:1px dashed #cbd5e1;">
        <span style="font-size:12px;color:#64748b;">
          ส่งรูปภาพสินค้าและรูปถ่ายหลักฐานแบบไบนารีตรงเข้า Telegram ทันที แม้จะรันบน Localhost
        </span>
        <?php if (!empty($settings['telegram_bot_token'])): ?>
          <button type="submit" form="test-tg-form" class="secondary-button" style="padding:6px 14px;font-size:12px;">
            ทดสอบส่งเข้า Telegram
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Card 2: Google Sheets Webhook -->
    <div class="inventory-panel" style="padding:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:12px;">
          <span
            style="font-size:14px;font-weight:700;color:#16a34a;background:#dcfce7;padding:6px 12px;border-radius:8px;">GS</span>
          <div>
            <h2 style="margin:0;font-size:18px;color:#1e293b;">การเชื่อมต่อ Google Sheets (Real-time Sync)</h2>
            <p style="margin:2px 0 0;font-size:13px;color:#64748b;">
              ส่งข้อมูลลง Google Sheet อัตโนมัติ: อัปเดตยอดคงเหลือในแท็บ <strong>ทะเบียนสินค้า</strong> (ลดลงเมื่อเบิก
              เพิ่มเมื่อรับ), บันทึกประวัติในแท็บ <strong>ประวัติรับ-เบิก</strong>, และบันทึกแท็บ
              <strong>ยืม-คืน</strong>
            </p>
          </div>
        </div>
        <label
          style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;font-weight:600;color:#16a34a;">
          <input type="checkbox" name="google_sheet_sync_enabled" value="1" <?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:#16a34a;">
          เปิดใช้งาน Google Sheets
        </label>
      </div>

      <label style="margin-bottom:14px;">
        Google Apps Script Web App URL
        <input type="url" name="google_sheet_webhook_url"
          value="<?= h((string) ($settings['google_sheet_webhook_url'] ?? '')) ?>"
          placeholder="https://script.google.com/macros/s/AKfycb.../exec">
        <small style="color:#888;">URL ของ Web App ที่ Deploy จาก Google Apps Script ใน Google Sheet ของคุณ</small>
      </label>

      <div
        style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px dashed #cbd5e1;">
        <div style="display:flex;gap:10px;align-items:center;">
          <button type="button" class="secondary-button" style="padding:6px 14px;font-size:12px;"
            onclick="document.querySelector('#gas-guide-section').hidden = !document.querySelector('#gas-guide-section').hidden">
            ดูวิธีตั้งค่า & โค้ด Google Apps Script
          </button>
          <?php if (!empty($settings['google_sheet_webhook_url'])): ?>
            <button type="submit" form="sync-catalog-form" class="secondary-button"
              style="padding:6px 14px;font-size:12px;"
              title="ส่งรายการสินค้าทั้งหมดในเว็บ พร้อมยอดคงเหลือปัจจุบัน ไปสร้างและอัปเดตในแท็บ ทะเบียนสินค้า บน Google Sheet">
              ซิงค์ทะเบียนสินค้าทั้งหมดเข้า Sheet (<?= number_format($productCount) ?> รายการ)
            </button>
          <?php endif; ?>
        </div>

        <?php if (!empty($settings['google_sheet_webhook_url'])): ?>
          <button type="submit" form="test-sheet-form" class="secondary-button" style="padding:6px 14px;font-size:12px;">
            ทดสอบส่งเข้า Google Sheet
          </button>
        <?php endif; ?>
      </div>

      <!-- Guide Box -->
      <div id="gas-guide-section"
        style="margin-top:20px;padding:16px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;line-height:1.7;"
        hidden>
        <strong style="color:#0758b4;display:block;margin-bottom:8px;font-size:14px;">ขั้นตอนการเชื่อมต่อ Google Sheets
          ใน 3 ขั้นตอน:</strong>
        <ol style="margin:0 0 14px 20px;padding:0;color:#334155;">
          <li>เปิด Google Sheet ของคุณ > ไปที่เมนู <strong>ส่วนขยาย (Extensions)</strong> > <strong>Apps Script</strong>
          </li>
          <li>ลบโค้ดเดิมออกทั้งหมด แล้ววางโค้ดด้านล่างนี้ลงไป > กด <strong>บันทึก (Save รูปแผ่นดิสก์)</strong></li>
          <li>กดปุ่มสีน้ำเงิน <strong>ทำให้ใช้งานได้ (Deploy)</strong> > <strong>การทำให้ใช้งานได้รายการใหม่ (New
              deployment)</strong> > เลือกประเภท <strong>เว็บแอป (Web app)</strong> > ตั้งค่า Who has access เป็น
            <strong>ทุกคน (Anyone)</strong> > คัดลอก Web App URL มาวางในช่องด้านบน</li>
        </ol>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <span style="font-weight:600;color:#475569;">โค้ด Google Apps Script (รองรับ ทะเบียนสินค้า + รับ-เบิก +
            ยืม-คืน ในไฟล์เดียว):</span>
          <button type="button" class="secondary-button" style="padding:3px 10px;font-size:11px;"
            onclick="copySettingsGasCode()">
            คัดลอกโค้ด
          </button>
        </div>
        <textarea id="settings-gas-code" readonly
          style="width:100%;height:320px;font-family:monospace;font-size:11px;padding:10px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;"><?= h('function doPost(e) {
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
}') ?></textarea>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;">
      <button class="primary-button" type="submit" style="padding:10px 24px;font-size:14px;">
        บันทึกการตั้งค่าทั้งหมด
      </button>
    </div>
  </form>

  <!-- Hidden forms for action triggers -->
  <form method="post" id="test-tg-form" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="test_telegram">
  </form>
  <form method="post" id="test-sheet-form" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="test_google_sheet">
  </form>
  <form method="post" id="sync-catalog-form" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="action" value="sync_all_catalog">
  </form>
</section>

<script>
  function copySettingsGasCode() {
    var textarea = document.getElementById('settings-gas-code');
    if (!textarea) return;
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(textarea.value).then(function () {
        alert('คัดลอกโค้ด Google Apps Script เรียบร้อยแล้ว!');
      });
    } else {
      document.execCommand('copy');
      alert('คัดลอกโค้ด Google Apps Script เรียบร้อยแล้ว!');
    }
  }
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>