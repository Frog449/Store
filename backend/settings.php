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
        'unit' => 'PCS',
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
        'unit' => 'PCS',
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
        inventory_flash_redirect('ส่งข้อมูลล่าสุดทั้งหมดไปยัง Google Sheet สำเร็จแล้ว (สินค้า ' . $productCount . ' รายการ พร้อมตารางรับสินค้า, เบิกสินค้า และยืม-คืนแยกกัน)', 'settings.php');
      } else {
        throw new DomainException('ส่งข้อมูลทั้งหมดไปยัง Google Sheet ไม่สำเร็จ: ' . $res['message']);
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
      <a class="secondary-button" href="catalog.php">รายการสินค้า ↗</a>
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
              ส่งข้อมูลลง Google Sheet อัตโนมัติ แยกตารางชัดเจน 4 แท็บ: <strong>รายการสินค้า</strong> (อัปเดตยอดคงเหลืออัตโนมัติ),
              <strong>ประวัติรับสินค้า</strong> (ตารางรับเข้า), <strong>ประวัติเบิกสินค้า</strong> (ตารางเบิกออก), และ
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
              title="ส่งข้อมูลล่าสุดจากเว็บทั้งหมดไปยังแท็บ รายการสินค้า, ประวัติรับสินค้า, ประวัติเบิกสินค้า และยืม-คืน บน Google Sheet">
              ส่งข้อมูลทั้งหมดให้เป็นปัจจุบัน
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
          <li>กดปุ่มสีน้ำเงิน <strong>ทำให้ใช้งานได้ (Deploy)</strong> > หากเคย Deploy แล้วให้เลือก <strong>จัดการการทำให้ใช้งานได้ (Manage deployments)</strong> > กดรูปดินสอ > เลือกเวอร์ชัน <strong>เวอร์ชันใหม่ (New version)</strong> > กดทำให้ใช้งานได้ (หรือถ้าทำครั้งแรกให้เลือก <strong>การทำให้ใช้งานได้รายการใหม่</strong> > <strong>เว็บแอป</strong> > Who has access: <strong>ทุกคน (Anyone)</strong> แล้วคัดลอก URL มาวางด้านบน)</li>
          <li>กลับมาที่หน้านี้แล้วกดปุ่ม <strong>ส่งข้อมูลทั้งหมดให้เป็นปัจจุบัน</strong> ระบบจะสร้างแท็บ <strong>ประวัติรับสินค้า</strong> และ <strong>ประวัติเบิกสินค้า</strong> แยกตารางให้อัตโนมัติ (แท็บ "ประวัติรับ-เบิก" เดิมสามารถลบออกได้ตามต้องการ)</li>
        </ol>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
          <span style="font-weight:600;color:#475569;">โค้ด Google Apps Script V4.0 (แยกตาราง: รายการสินค้า + ประวัติรับสินค้า + ประวัติเบิกสินค้า + ยืม-คืน):</span>
          <button type="button" class="secondary-button" style="padding:3px 10px;font-size:11px;"
            onclick="copySettingsGasCode()">
            คัดลอกโค้ด
          </button>
        </div>
        <textarea id="settings-gas-code" readonly
          style="width:100%;height:320px;font-family:monospace;font-size:11px;padding:10px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:6px;"><?= h('/**
 * =========================================================================
 * STORE AUTOMEATION - GOOGLE APPS SCRIPT WEBHOOK (V4.0)
 * ระบบเชื่อมต่อ Google Sheets แยกตาราง 4 แท็บ:
 *   1. รายการสินค้า      (อัปเดตสต๊อกคงเหลืออัตโนมัติ)
 *   2. ประวัติรับสินค้า   (ตารางรับสินค้าเข้าแยกเฉพาะ - สีเขียว)
 *   3. ประวัติเบิกสินค้า  (ตารางเบิกสินค้าออกแยกเฉพาะ - สีแดง)
 *   4. ยืม-คืน          (ตารางยืม-คืนพัสดุ)
 * (รองรับซิงค์ข้อมูลและคอลัมน์ตรงกับหน้าเว็บ 100%)
 * =========================================================================
 */

var CATALOG_HEADERS = [
  "Account Code1", "Stock Code", "GL Description", "Name",
  "Unit", "Quantity", "Section code", "Section Name", "Apex",
  "Buydate", "เวลาBuydate", "BuyPrice/Unit",
  "Cal No.", "Cal date", "Cal Exp.", "จุดสั่งซื้อขั้นต่ำ"
];

var RECEIPT_HEADERS = [
  "วัน-เวลารับ", "ประเภท", "เลขที่เอกสาร", "Stock Code", "Account Code1",
  "ชื่อสินค้า", "จำนวนรับ", "หน่วยนับ", "คงเหลือหลังรับ",
  "ผู้รับสินค้า", "หมายเหตุ", "ผู้บันทึก"
];

var ISSUE_HEADERS = [
  "วัน-เวลาเบิก", "เลขที่เอกสาร", "Stock Code", "Account Code1",
  "ชื่อสินค้า", "จำนวนเบิก", "หน่วยนับ", "คงเหลือหลังเบิก",
  "ผู้เบิก", "ผู้อนุมัติ", "หมายเหตุ", "ผู้บันทึก"
];

var BORROW_HEADERS = [
  "ID", "กิจกรรม", "ผู้ยืม", "รายการพัสดุ", "Stock Code", "Account Code1",
  "จำนวน", "หน่วยนับ", "สถานที่ใช้งาน", "วันที่ยืม", "เวลาที่ยืม",
  "สถานะ", "วันที่คืน", "เวลาที่คืน", "ผู้รับคืน", "หมายเหตุ", "เวลาที่บันทึก"
];

// 1. ฟังก์ชันตอบสนองเมื่อเปิดผ่าน Browser หรือ Health Check
function doGet(e) {
  return jsonResponse({
    status: "ok",
    message: "Google Apps Script Web App พร้อมใช้งาน (Store Automeation V4.0 - แยกตารางรับ/เบิก)",
    timestamp: Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss")
  });
}

// 2. ฟังก์ชันหลักรับข้อมูลแบบ POST จาก Store Automeation
function doPost(e) {
  var lock = LockService.getScriptLock();
  var hasLock = lock.tryLock(20000);

  try {
    if (!e || !e.postData || !e.postData.contents) {
      return jsonResponse({
        status: "error",
        message: "ไม่พบข้อมูล POST หรือเรียกใช้ฟังก์ชันโดยตรง (หากทดสอบใน Apps Script แนะนำให้รันฟังก์ชัน testWebhook)"
      });
    }

    var data;
    try {
      data = JSON.parse(e.postData.contents);
    } catch (parseErr) {
      return jsonResponse({ status: "error", message: "JSON ผิดรูปแบบ: " + parseErr.toString() });
    }

    var ss = SpreadsheetApp.getActiveSpreadsheet();

    // 1. รับ-เบิกสินค้า (Receipt / Issue) — แยกบันทึกลงแท็บ "ประวัติรับสินค้า" และ "ประวัติเบิกสินค้า"
    if (data.event === "movement_sync" || data.action === "receipt" || data.action === "issue") {
      updateCatalogStock(ss, data);
      if (data.action === "receipt") {
        logReceipt(ss, data);
        return jsonResponse({ status: "ok", message: "บันทึกประวัติรับสินค้า และอัปเดตยอดคงเหลือในรายการสินค้าเรียบร้อยแล้ว" });
      } else {
        logIssue(ss, data);
        return jsonResponse({ status: "ok", message: "บันทึกประวัติเบิกสินค้า และอัปเดตยอดคงเหลือในรายการสินค้าเรียบร้อยแล้ว" });
      }
    }

    // 2. ซิงค์ข้อมูลทั้งหมดให้ตรงกับหน้าเว็บ 100% (รายการสินค้า + ประวัติรับสินค้า + ประวัติเบิกสินค้า + ยืม-คืน)
    if (data.event === "catalog_batch_sync") {
      var count = batchSyncCatalog(ss, data.products || [], data.timestamp);
      var receiptsList = Array.isArray(data.receipts)
        ? data.receipts
        : (Array.isArray(data.movements) ? data.movements.filter(function(m) { return m.action === "receipt"; }) : null);
      var issuesList = Array.isArray(data.issues)
        ? data.issues
        : (Array.isArray(data.movements) ? data.movements.filter(function(m) { return m.action !== "receipt"; }) : null);

      var receiptCount = 0;
      var issueCount = 0;
      if (receiptsList !== null) {
        receiptCount = batchSyncReceipts(ss, receiptsList);
      }
      if (issuesList !== null) {
        issueCount = batchSyncIssues(ss, issuesList);
      }
      if (Array.isArray(data.borrows)) {
        batchSyncBorrows(ss, data.borrows);
      }
      return jsonResponse({
        status: "ok",
        message: "ซิงค์ข้อมูลทั้งหมดเข้า Google Sheet สำเร็จ (สินค้า " + count + " รายการ, รับเข้า " + receiptCount + " รายการ, เบิกออก " + issueCount + " รายการ)"
      });
    }

    // 3. ซิงค์ข้อมูลสินค้าเดี่ยว (Single Product Catalog Sync)
    if (data.event === "catalog_sync" || data.action === "catalog_update") {
      updateCatalogStock(ss, data);
      return jsonResponse({ status: "ok", message: "อัปเดตข้อมูลสินค้าในรายการสินค้าเรียบร้อยแล้ว" });
    }

    // 4. บันทึกระบบ ยืม-คืน พัสดุ (Borrow & Return Sync)
    return handleBorrowSync(ss, data);

  } catch (err) {
    return jsonResponse({ status: "error", message: err.toString() });
  } finally {
    if (hasLock) {
      lock.releaseLock();
    }
  }
}

function jsonResponse(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function cleanCode(val) {
  var s = String(val === undefined || val === null ? "" : val).trim();
  if (s === "" || s === "—" || s.indexOf("ITEM-") === 0 || s.indexOf("legacy-") === 0) {
    return "-";
  }
  return s;
}

function asTextCell(val, fallback) {
  var s = String(val === undefined || val === null ? "" : val).trim();
  if (s === "") {
    return fallback !== undefined ? fallback : "";
  }
  if (s === "-") {
    return "-";
  }
  return "\'" + s;
}

function ensureSheetHeaders(sheet, headers, bgColor, fontColor) {
  bgColor = bgColor || "#f1f5f9";
  fontColor = fontColor || "#1e293b";
  if (sheet.getLastRow() === 0) {
    sheet.appendRow(headers);
  } else {
    var lastCol = Math.max(headers.length, sheet.getLastColumn());
    var currentHeaders = sheet.getRange(1, 1, 1, lastCol).getValues()[0];
    var mismatch = false;
    if (sheet.getLastColumn() > headers.length) {
      mismatch = true;
    } else {
      for (var i = 0; i < headers.length; i++) {
        if (String(currentHeaders[i] || "").trim() !== headers[i]) {
          mismatch = true;
          break;
        }
      }
    }
    if (mismatch) {
      if (sheet.getLastColumn() > headers.length) {
        var extraCols = sheet.getLastColumn() - headers.length;
        sheet.getRange(1, headers.length + 1, Math.max(1, sheet.getLastRow()), extraCols).clearContent();
        sheet.getRange(1, headers.length + 1, 1, extraCols).clearFormat();
      }
      sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    }
  }
  sheet.setFrozenRows(1);
  var hRange = sheet.getRange(1, 1, 1, headers.length);
  hRange.setFontWeight("bold").setBackground(bgColor).setFontColor(fontColor).setHorizontalAlignment("center");
}

function getOrCreateSheet(ss, name, headers, bgColor, fontColor) {
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
  if (headers && headers.length > 0) {
    ensureSheetHeaders(sheet, headers, bgColor, fontColor);
  }
  return sheet;
}

function getCatalogSheet(ss) {
  var names = ["รายการสินค้า", "ทะเบียนสินค้า", "Catalog", "สินค้า"];
  for (var i = 0; i < names.length; i++) {
    var s = ss.getSheetByName(names[i]);
    if (s) {
      ensureSheetHeaders(s, CATALOG_HEADERS, "#f1f5f9", "#1e293b");
      return s;
    }
  }
  var sNew = getOrCreateSheet(ss, "รายการสินค้า", CATALOG_HEADERS, "#f1f5f9", "#1e293b");
  try { sNew.getRange("A:B").setNumberFormat("@"); } catch(e) {}
  return sNew;
}

function getReceiptSheet(ss) {
  var names = ["ประวัติรับสินค้า", "ประวัติรับเข้า", "รับสินค้า", "รับเข้า", "Receipts"];
  for (var i = 0; i < names.length; i++) {
    var s = ss.getSheetByName(names[i]);
    if (s) {
      ensureSheetHeaders(s, RECEIPT_HEADERS, "#dcfce7", "#166534");
      try { s.setTabColor("#16a34a"); } catch(e) {}
      return s;
    }
  }
  var sNew = getOrCreateSheet(ss, "ประวัติรับสินค้า", RECEIPT_HEADERS, "#dcfce7", "#166534");
  try {
    sNew.setTabColor("#16a34a");
    sNew.getRange("C:E").setNumberFormat("@");
  } catch(e) {}
  return sNew;
}

function getIssueSheet(ss) {
  var names = ["ประวัติเบิกสินค้า", "ประวัติเบิกออก", "เบิกสินค้า", "เบิกออก", "Issues"];
  for (var i = 0; i < names.length; i++) {
    var s = ss.getSheetByName(names[i]);
    if (s) {
      ensureSheetHeaders(s, ISSUE_HEADERS, "#fee2e2", "#991b1b");
      try { s.setTabColor("#dc2626"); } catch(e) {}
      return s;
    }
  }
  var sNew = getOrCreateSheet(ss, "ประวัติเบิกสินค้า", ISSUE_HEADERS, "#fee2e2", "#991b1b");
  try {
    sNew.setTabColor("#dc2626");
    sNew.getRange("B:D").setNumberFormat("@");
  } catch(e) {}
  return sNew;
}

function getBorrowSheet(ss) {
  var names = ["ยืม-คืน", "ยืมคืน", "Borrow"];
  for (var i = 0; i < names.length; i++) {
    var s = ss.getSheetByName(names[i]);
    if (s) {
      ensureSheetHeaders(s, BORROW_HEADERS, "#f1f5f9", "#1e293b");
      return s;
    }
  }
  var sNew = getOrCreateSheet(ss, "ยืม-คืน", BORROW_HEADERS, "#f1f5f9", "#1e293b");
  try {
    sNew.getRange("A:A").setNumberFormat("@");
    sNew.getRange("E:F").setNumberFormat("@");
  } catch(e) {}
  return sNew;
}

function buildCatalogRow(p) {
  var ac1 = cleanCode(p.account_code1 || "-");
  var sc = cleanCode(p.stock_code !== undefined ? p.stock_code : (p.product_code || p.code || "-"));
  var gl = String(p.gl_description || p.category || "").trim();
  var name = String(p.product_name || p.name || "").trim();
  var unit = String(p.unit || "PCS").trim();
  var stock = Math.round(Number(p.current_stock !== undefined && p.current_stock !== null ? p.current_stock : (p.stock || 0)));
  var secCode = String(p.section_code || "").trim();
  var secName = String(p.section_name || "").trim();
  var apex = String(p.apex || "").trim();
  var rawBuy = String(p.buy_date || "").replace("T", " ").trim();
  var buyDate = rawBuy;
  var buyTime = String(p.buy_time !== undefined ? p.buy_time : (p.buy_date_time || "")).trim();
  if (rawBuy.indexOf(" ") !== -1) {
    var bParts = rawBuy.split(/\s+/);
    buyDate = bParts[0] || "";
    if (buyTime === "" && bParts[1]) {
      buyTime = bParts[1].substring(0, 5);
    }
  }
  var buyPrice = (p.buy_price !== undefined && p.buy_price !== null && p.buy_price !== "") ? Number(p.buy_price) : "";
  var calNo = String(p.cal_no || "").trim();
  var calDate = String(p.cal_date || "").trim();
  var calExp = String(p.cal_exp || "").trim();
  var minOrder = Math.round(Number(p.reorder_point !== undefined && p.reorder_point !== null ? p.reorder_point : (p.minimum || 0)));

  return [
    "\'" + ac1,
    "\'" + sc,
    gl,
    name,
    unit,
    stock,
    secCode !== "" ? "\'" + secCode : "",
    secName,
    apex,
    buyDate !== "" ? "\'" + buyDate : "",
    buyTime !== "" ? "\'" + buyTime : "",
    buyPrice,
    calNo !== "" ? "\'" + calNo : "",
    calDate !== "" ? "\'" + calDate : "",
    calExp !== "" ? "\'" + calExp : "",
    minOrder
  ];
}

function buildReceiptRow(m) {
  var sc = cleanCode(m.stock_code !== undefined ? m.stock_code : (m.product_code || "-"));
  var ac1 = cleanCode(m.account_code1 || "-");
  var ts = m.timestamp || (m.movement_date ? (m.movement_date + (m.movement_time ? " " + m.movement_time : "")) : Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss"));
  var displayName = m.display_name || m.product_name || "";
  var receiver = m.received_by || m.actor || "-";
  return [
    asTextCell(ts, ""),
    m.action_name || "รับสินค้า",
    asTextCell(m.document_no, "-"),
    "\'" + sc,
    "\'" + ac1,
    displayName,
    Math.round(Number(m.quantity || 0)),
    m.unit || "PCS",
    m.current_stock !== undefined ? Math.round(Number(m.current_stock)) : 0,
    receiver,
    m.notes || "-",
    m.created_by || "-"
  ];
}

function buildIssueRow(m) {
  var sc = cleanCode(m.stock_code !== undefined ? m.stock_code : (m.product_code || "-"));
  var ac1 = cleanCode(m.account_code1 || "-");
  var ts = m.timestamp || m.movement_date || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss");
  var displayName = m.display_name || m.product_name || "";
  var requester = m.requested_by || m.actor || "-";
  var approver = m.approved_by || m.approver || "-";
  return [
    asTextCell(ts, ""),
    asTextCell(m.document_no, "-"),
    "\'" + sc,
    "\'" + ac1,
    displayName,
    Math.round(Number(m.quantity || 0)),
    m.unit || "PCS",
    m.current_stock !== undefined ? Math.round(Number(m.current_stock)) : 0,
    requester,
    approver,
    m.notes || "-",
    m.created_by || "-"
  ];
}

function updateCatalogStock(ss, data) {
  var catalogSheet = getCatalogSheet(ss);
  var ac1 = cleanCode(data.account_code1 || "-").toLowerCase();
  var sc = cleanCode(data.stock_code !== undefined ? data.stock_code : (data.product_code || "-")).toLowerCase();
  var pName = String(data.product_name || data.name || "").trim().toLowerCase();

  var values = catalogSheet.getDataRange().getValues();
  var foundRow = 0;
  for (var i = 1; i < values.length; i++) {
    var rowAc1 = String(values[i][0] || "").trim().toLowerCase();
    var rowSc = String(values[i][1] || "").trim().toLowerCase();
    var rowName = String(values[i][3] || "").trim().toLowerCase();

    if (ac1 !== "-" && sc !== "-" && rowAc1 === ac1 && rowSc === sc) {
      foundRow = i + 1;
      break;
    }
    if (ac1 !== "-" && rowAc1 === ac1 && rowName === pName) {
      foundRow = i + 1;
      break;
    }
    if (ac1 === "-" && sc !== "-" && rowSc === sc && rowAc1 === "-") {
      foundRow = i + 1;
      break;
    }
    if (ac1 === "-" && sc === "-" && pName !== "" && rowName === pName && rowAc1 === "-" && rowSc === "-") {
      foundRow = i + 1;
      break;
    }
  }

  var rowValues = buildCatalogRow(data);
  if (foundRow > 0) {
    catalogSheet.getRange(foundRow, 1, 1, CATALOG_HEADERS.length).setValues([rowValues]);
  } else {
    catalogSheet.appendRow(rowValues);
  }
}

function logReceipt(ss, data) {
  var receiptSheet = getReceiptSheet(ss);
  receiptSheet.appendRow(buildReceiptRow(data));
}

function logIssue(ss, data) {
  var issueSheet = getIssueSheet(ss);
  issueSheet.appendRow(buildIssueRow(data));
}

function batchSyncCatalog(ss, products, ts) {
  var catalogSheet = getCatalogSheet(ss);
  var lastRow = catalogSheet.getLastRow();
  var lastCol = Math.max(CATALOG_HEADERS.length, catalogSheet.getLastColumn());
  if (lastRow > 1) {
    catalogSheet.getRange(2, 1, lastRow - 1, lastCol).clearContent();
  }
  ensureSheetHeaders(catalogSheet, CATALOG_HEADERS, "#f1f5f9", "#1e293b");

  var timeStr = ts || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss");
  var rows = [];
  for (var i = 0; i < products.length; i++) {
    rows.push(buildCatalogRow(products[i], timeStr));
  }
  if (rows.length > 0) {
    catalogSheet.getRange(2, 1, rows.length, CATALOG_HEADERS.length).setValues(rows);
  }
  return rows.length;
}

function batchSyncReceipts(ss, receipts) {
  var receiptSheet = getReceiptSheet(ss);
  var lastRow = receiptSheet.getLastRow();
  var lastCol = Math.max(RECEIPT_HEADERS.length, receiptSheet.getLastColumn());
  if (lastRow > 1) {
    receiptSheet.getRange(2, 1, lastRow - 1, lastCol).clearContent();
  }
  ensureSheetHeaders(receiptSheet, RECEIPT_HEADERS, "#dcfce7", "#166534");

  var rows = [];
  for (var i = 0; i < receipts.length; i++) {
    rows.push(buildReceiptRow(receipts[i]));
  }
  if (rows.length > 0) {
    receiptSheet.getRange(2, 1, rows.length, RECEIPT_HEADERS.length).setValues(rows);
  }
  return rows.length;
}

function batchSyncIssues(ss, issues) {
  var issueSheet = getIssueSheet(ss);
  var lastRow = issueSheet.getLastRow();
  var lastCol = Math.max(ISSUE_HEADERS.length, issueSheet.getLastColumn());
  if (lastRow > 1) {
    issueSheet.getRange(2, 1, lastRow - 1, lastCol).clearContent();
  }
  ensureSheetHeaders(issueSheet, ISSUE_HEADERS, "#fee2e2", "#991b1b");

  var rows = [];
  for (var i = 0; i < issues.length; i++) {
    rows.push(buildIssueRow(issues[i]));
  }
  if (rows.length > 0) {
    issueSheet.getRange(2, 1, rows.length, ISSUE_HEADERS.length).setValues(rows);
  }
  return rows.length;
}

function batchSyncBorrows(ss, borrows) {
  var borrowSheet = getBorrowSheet(ss);
  var lastRow = borrowSheet.getLastRow();
  var lastCol = Math.max(BORROW_HEADERS.length, borrowSheet.getLastColumn());
  if (lastRow > 1) {
    borrowSheet.getRange(2, 1, lastRow - 1, lastCol).clearContent();
  }
  ensureSheetHeaders(borrowSheet, BORROW_HEADERS, "#f1f5f9", "#1e293b");

  var rows = [];
  for (var i = 0; i < borrows.length; i++) {
    var b = borrows[i];
    var sc = cleanCode(b.stock_code !== undefined ? b.stock_code : (b.product_code || "-"));
    var ac1 = cleanCode(b.account_code1 || "-");
    rows.push([
      "\'" + String(b.id || ""),
      b.action_name || "ยืมพัสดุ",
      b.borrower_name || "",
      b.product_name || "",
      "\'" + sc,
      "\'" + ac1,
      Math.round(Number(b.quantity || 1)),
      b.unit || "PCS",
      b.location || "",
      b.borrow_date || "",
      b.borrow_time || "",
      b.status || "กำลังยืม",
      b.return_date || "",
      b.return_time || "",
      b.return_received_by || "",
      b.notes || "-",
      b.timestamp || ""
    ]);
  }
  if (rows.length > 0) {
    borrowSheet.getRange(2, 1, rows.length, BORROW_HEADERS.length).setValues(rows);
  }
  return rows.length;
}

function handleBorrowSync(ss, data) {
  var borrowSheet = getBorrowSheet(ss);
  var isReturn = (data.action === "return" || data.status === "คืนแล้ว" || data.status_code === "returned");
  var itemName = data.product_name || data.item || data.product || "";
  var targetId = String(data.id || "").trim();
  var sc = cleanCode(data.stock_code !== undefined ? data.stock_code : (data.product_code || "-"));
  var ac1 = cleanCode(data.account_code1 || "-");
  var foundRow = 0;

  if (targetId !== "") {
    var allData = borrowSheet.getDataRange().getValues();
    for (var i = 1; i < allData.length; i++) {
      if (String(allData[i][0]).trim() === targetId) {
        foundRow = i + 1;
        break;
      }
    }
  }

  if (foundRow > 0 && isReturn) {
    borrowSheet.getRange(foundRow, 2).setValue("คืนพัสดุ");
    borrowSheet.getRange(foundRow, 12).setValue("คืนแล้ว");
    borrowSheet.getRange(foundRow, 13).setValue(data.return_date || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd"));
    borrowSheet.getRange(foundRow, 14).setValue(data.return_time || Utilities.formatDate(new Date(), "Asia/Bangkok", "HH:mm"));
    borrowSheet.getRange(foundRow, 15).setValue(data.return_received_by || "-");
    var existingNote = String(borrowSheet.getRange(foundRow, 16).getValue() || "").trim();
    var returnNote = String(data.return_notes || "").trim();
    if (returnNote !== "") {
      var merged = (existingNote !== "" && existingNote !== "-") ? existingNote + " | [คืน: " + returnNote + "]" : returnNote;
      borrowSheet.getRange(foundRow, 16).setValue(merged);
    }
    return jsonResponse({status: "ok", message: "อัปเดตรายการคืนพัสดุในแถวที่ " + foundRow + " เรียบร้อย"});
  }

  if (foundRow > 0 && !isReturn) {
    borrowSheet.getRange(foundRow, 2).setValue("ยืมพัสดุ");
    if (data.borrower_name) borrowSheet.getRange(foundRow, 3).setValue(data.borrower_name);
    if (itemName) borrowSheet.getRange(foundRow, 4).setValue(itemName);
    borrowSheet.getRange(foundRow, 5).setValue("\'" + sc);
    borrowSheet.getRange(foundRow, 6).setValue("\'" + ac1);
    if (data.quantity !== undefined) borrowSheet.getRange(foundRow, 7).setValue(Math.round(Number(data.quantity || 1)));
    if (data.unit) borrowSheet.getRange(foundRow, 8).setValue(data.unit);
    if (data.location) borrowSheet.getRange(foundRow, 9).setValue(data.location);
    borrowSheet.getRange(foundRow, 12).setValue("กำลังยืม");
    if (data.notes) borrowSheet.getRange(foundRow, 16).setValue(data.notes);
    return jsonResponse({status: "ok", message: "อัปเดตข้อมูลการยืมในแถวที่ " + foundRow + " เรียบร้อย"});
  }

  // Append new row
  borrowSheet.appendRow([
    "\'" + targetId,
    isReturn ? "คืนพัสดุ" : "ยืมพัสดุ",
    data.borrower_name || data.name || "",
    itemName,
    "\'" + sc,
    "\'" + ac1,
    Math.round(Number(data.quantity || 1)),
    data.unit || "PCS",
    data.location || "",
    data.borrow_date || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd"),
    data.borrow_time || Utilities.formatDate(new Date(), "Asia/Bangkok", "HH:mm"),
    isReturn ? "คืนแล้ว" : "กำลังยืม",
    isReturn ? (data.return_date || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd")) : "",
    isReturn ? (data.return_time || Utilities.formatDate(new Date(), "Asia/Bangkok", "HH:mm")) : "",
    isReturn ? (data.return_received_by || "-") : "",
    (isReturn ? data.return_notes : data.notes) || "-",
    data.timestamp || Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss")
  ]);

  return jsonResponse({status: "ok", message: isReturn ? "บันทึกรายการคืนพัสดุใหม่เรียบร้อยแล้ว" : "บันทึกการยืมพัสดุใหม่เรียบร้อยแล้ว"});
}

// ฟังก์ชันสำหรับทดสอบรันใน Google Apps Script Editor โดยตรง (สร้างและทดสอบทั้งตารางรับและเบิก)
function testWebhook() {
  var nowStr = Utilities.formatDate(new Date(), "Asia/Bangkok", "yyyy-MM-dd HH:mm:ss");
  var receiptPayload = {
    postData: {
      contents: JSON.stringify({
        event: "movement_sync",
        action: "receipt",
        action_name: "รับสินค้า (ทดสอบ)",
        stock_code: "TEST-001",
        account_code1: "AUT-TEST-01",
        product_code: "TEST-001",
        product_name: "สินค้าทดสอบระบบ (Test Item)",
        gl_description: "Equipment, Tools",
        section_code: "G9301",
        section_name: "AUT-G",
        apex: "A-2",
        quantity: 5,
        unit: "PCS",
        current_stock: 50,
        reorder_point: 10,
        document_no: "TEST-IN-01",
        received_by: "Admin Tester",
        notes: "ทดสอบตารางประวัติรับสินค้าผ่าน testWebhook",
        created_by: "Admin Tester",
        timestamp: nowStr
      })
    }
  };
  var issuePayload = {
    postData: {
      contents: JSON.stringify({
        event: "movement_sync",
        action: "issue",
        action_name: "เบิกสินค้า (ทดสอบ)",
        stock_code: "TEST-001",
        account_code1: "AUT-TEST-01",
        product_code: "TEST-001",
        product_name: "สินค้าทดสอบระบบ (Test Item)",
        gl_description: "Equipment, Tools",
        section_code: "G9301",
        section_name: "AUT-G",
        apex: "A-2",
        quantity: 2,
        unit: "PCS",
        current_stock: 48,
        reorder_point: 10,
        document_no: "TEST-OUT-01",
        requested_by: "Staff Tester",
        approved_by: "Supervisor Tester",
        notes: "ทดสอบตารางประวัติเบิกสินค้าผ่าน testWebhook",
        created_by: "Staff Tester",
        timestamp: nowStr
      })
    }
  };
  Logger.log(doPost(receiptPayload).getContent());
  Logger.log(doPost(issuePayload).getContent());
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