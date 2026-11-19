<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (auth_is_viewer()) {
    inventory_flash_redirect('คุณมีสิทธิ์ Viewer (ดูข้อมูลได้อย่างเดียว) ไม่สามารถเข้าหน้าประวัติเบิกสินค้าได้', 'catalog.php', 'error');
}

$pageTitle = 'ประวัติเบิกสินค้า | Store Automeation';
$pageDescription = 'ดูประวัติการเบิกสินค้าและบันทึกการเบิกสินค้าออกจากคลัง';
$activePage = 'issue';
$reopenIssueDialog = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_issue') {
    try {
        inventory_require_csrf();
        if (!auth_can_manage_stock()) {
            throw new DomainException('เฉพาะ Admin และ Staff เท่านั้นที่สามารถบันทึกเบิกสินค้าได้');
        }
        $productCode = inventory_text('product_code', 'รหัสสินค้า', 80, true);
        $user = auth_user();
        $requestedBy = inventory_text('requested_by', 'ผู้เบิก', 150);
        if ($requestedBy === '' && $user) {
            $requestedBy = (string) $user['name'];
        }
        inventory_record_movement('issue', [
            'movement_date' => inventory_date(),
            'document_no' => inventory_text('document_no', 'เลขที่เอกสาร', 100),
            'product_code' => $productCode,
            'quantity' => inventory_number('quantity', 'จำนวนเบิก', false),
            'requested_by' => $requestedBy,
            'department' => '',
            'approved_by' => inventory_text('approved_by', 'ผู้อนุมัติ', 150, true),
            'notes' => inventory_text('notes', 'หมายเหตุ', 500),
        ]);
        inventory_flash_redirect('บันทึกเบิกสินค้าออกจากคลังแล้ว', 'issue.php');
    } catch (DomainException | PDOException $exception) {
        $notice = inventory_handle_error($exception);
        $noticeType = 'error';
        $reopenIssueDialog = true;
    }
}

$pageProducts = inventory_products();
$isAdmin = auth_is_admin();
$allUsers = $isAdmin ? inventory_users() : [];
$selectedUserParam = ($isAdmin && isset($_GET['user']) && is_string($_GET['user'])) ? trim($_GET['user']) : 'all';
$filterUserId = null;
$filterUserName = null;
$selectedUserLabel = '';

if ($isAdmin) {
    if ($selectedUserParam !== '' && $selectedUserParam !== 'all' && ctype_digit($selectedUserParam)) {
        $targetId = (int) $selectedUserParam;
        foreach ($allUsers as $u) {
            if ((int) $u['id'] === $targetId) {
                $filterUserId = $targetId;
                $filterUserName = (string) $u['name'];
                $selectedUserLabel = (string) $u['name'];
                break;
            }
        }
    }
} else {
    $filterUserId = (int) ($currentUser['id'] ?? 0);
    $filterUserName = (string) ($currentUser['name'] ?? '');
}

$history = inventory_movements('issue', 250, $filterUserId, $filterUserName);
$totalIssueQty = 0.0;
foreach ($history as $hRow) {
    $totalIssueQty += (float) ($hRow['quantity'] ?? 0);
}

$preselectIssueCode = isset($_GET['product']) && is_string($_GET['product']) ? trim($_GET['product']) : '';
$selectedProductCode = (string) ($_POST['product_code'] ?? $preselectIssueCode);
$selectedProductName = '';
$selectedProductUnit = '';
$preselectIssueProduct = null;
foreach ($pageProducts as $p) {
    if ($p['id'] === $selectedProductCode) {
        $selectedProductName = $p['name'];
        $selectedProductUnit = $p['unit'];
        if ($preselectIssueCode !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $preselectIssueProduct = [
                'code' => $p['id'],
                'name' => $p['name'],
                'unit' => $p['unit'],
            ];
        }
        break;
    }
}
$settings = inventory_get_settings();
$user = auth_user();
$defaultRequester = (string) ($user['name'] ?? '');
require __DIR__ . '/includes/header.php';
?>
<section class="inventory-page">
    <div class="page-heading">
        <div>
            <div class="eyebrow section-eyebrow">ISSUE HISTORY</div>
            <h1><?= $isAdmin ? 'ประวัติเบิกสินค้า (ทั้งหมด)' : 'ประวัติเบิกสินค้าของคุณ' ?><span class="heading-period">.</span></h1>
            <p><?= $isAdmin
                ? ($selectedUserLabel !== '' ? 'กำลังแสดงประวัติเบิกสินค้าของผู้ใช้งาน: ' . h($selectedUserLabel) : 'แสดงประวัติการเบิกสินค้าของผู้ใช้งานทั้งหมด พร้อมตัวกรองรายบุคคล')
                : 'แสดงรายการเบิกสินค้าของ ' . h((string) ($currentUser['name'] ?? '')) . ' เท่านั้น' ?></p>
        </div>
        <div class="page-kpis">
            <?php if (!auth_is_viewer()): ?>
                <button class="primary-button" type="button" onclick="openIssueDialog()">
                    + บันทึกการเบิกสินค้า
                </button>
            <?php endif; ?>
            <a class="secondary-button" href="export.php?type=issues" style="font-size:11px;">Export ประวัติเบิกสินค้า</a>
            <a class="secondary-button" href="catalog.php">ดูรายการสินค้า ↗</a>
        </div>
    </div>

    <!-- Status Strip -->
    <div class="stats-strip borrow-stats-strip" style="margin-bottom:20px;">
        <div class="stat-item">
            <span class="stat-icon icon-alert" style="color:#dc2626;background:#fee2e2;font-weight:700;font-size:11px;">OUT</span>
            <div>
                <strong><?= number_format(count($history)) ?> <small style="font-size:11px;font-weight:normal;">รายการ</small></strong>
                <span><?= $isAdmin && $filterUserId === null ? 'รายการเบิกทั้งหมด' : 'รายการเบิกของบัญชีนี้' ?></span>
            </div>
        </div>
        <div class="stat-item">
            <span class="stat-icon icon-box">QTY</span>
            <div>
                <strong><?= inventory_quantity($totalIssueQty) ?> <small style="font-size:11px;font-weight:normal;">หน่วย</small></strong>
                <span>จำนวนเบิกรวมล่าสุด</span>
            </div>
        </div>
        <div class="stats-note" style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:12px;color:#64748b;">
                Telegram: <strong><?= ($settings['telegram_notify_enabled'] ?? '0') === '1' ? 'เปิดใช้งาน' : 'ปิดอยู่' ?></strong>
            </span>
            <span style="font-size:12px;color:#64748b;">
                Google Sheets: <strong><?= ($settings['google_sheet_sync_enabled'] ?? '0') === '1' ? 'เปิดใช้งาน' : 'ปิดอยู่' ?></strong>
            </span>
            <?php if (auth_is_admin()): ?>
                <a href="settings.php" class="secondary-button" style="padding:2px 8px;font-size:11px;min-height:24px;">ตั้งค่า</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- History Table Panel -->
    <section class="inventory-panel table-panel">
        <div class="panel-heading">
            <div>
                <h2><?= $isAdmin ? ($selectedUserLabel !== '' ? 'ประวัติเบิกสินค้าของ ' . h($selectedUserLabel) : 'ประวัติเบิกสินค้าทั้งหมด') : 'ประวัติเบิกสินค้าของคุณ (' . h((string) ($currentUser['name'] ?? '')) . ')' ?></h2>
                <p>แสดงรายการล่าสุดไม่เกิน 250 รายการ</p>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <label class="search-box" style="min-width:250px;">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8" />
                        <path d="m16 16 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                    <input id="issue-history-search" type="search" placeholder="ค้นหาชื่อ, Stock Code, Account Code1..." autocomplete="off">
                    <kbd>/</kbd>
                </label>
                <?php if ($isAdmin): ?>
                    <form method="get" style="margin:0;">
                        <select name="user" class="user-filter-select" aria-label="กรองตามผู้ใช้งาน" onchange="this.form.submit()">
                            <option value="all" <?= $filterUserId === null ? 'selected' : '' ?>>ผู้ใช้งานทั้งหมด</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?= (int) $u['id'] ?>" <?= $filterUserId === (int) $u['id'] ? 'selected' : '' ?>>
                                    <?= h($u['name']) ?> (@<?= h($u['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-scroll">
            <table class="inventory-table movement-table">
                <thead>
                    <tr>
                        <th>วันที่เบิก</th>
                        <th>เลขที่เอกสาร</th>
                        <th>Stock Code</th>
                        <th>ชื่อสินค้า</th>
                        <th class="numeric">จำนวนเบิก</th>
                        <th>หน่วยนับ</th>
                        <th>ผู้เบิก</th>
                        <th>ผู้อนุมัติ</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody id="issue-history-tbody"><?php foreach ($history as $row): ?>
                        <?php
                        $iSc = (string) ($row['stock_code'] ?? $row['product_code'] ?? '');
                        $iAc1 = (string) ($row['account_code1'] ?? '');
                        $iSearch = mb_strtolower(implode(' ', [
                            (string) ($row['movement_date'] ?? ''),
                            (string) ($row['document_no'] ?? ''),
                            (string) ($row['product_code'] ?? ''),
                            $iSc,
                            $iAc1,
                            (string) ($row['product_name'] ?? ''),
                            (string) ($row['requested_by'] ?? ''),
                            (string) ($row['approved_by'] ?? ''),
                            (string) ($row['notes'] ?? ''),
                        ]), 'UTF-8');
                        ?>
                        <tr class="issue-history-row" data-search="<?= h($iSearch) ?>">
                            <td><?= h($row['movement_date']) ?></td>
                            <td><?= h($row['document_no']) ?></td>
                            <td class="code-cell"><?= h($iSc) ?></td>
                            <td class="name-cell"><?= h($row['product_name']) ?><?= ($iAc1 !== '' && $iAc1 !== '-') ? ' <small style="color:#64748b;">[' . h($iAc1) . ']</small>' : '' ?></td>
                            <td class="numeric"><?= inventory_quantity($row['quantity']) ?></td>
                            <td><?= h($row['unit']) ?></td>
                            <td><?= h($row['requested_by']) ?></td>
                            <td><?= h($row['approved_by']) ?></td>
                            <td><?= h($row['notes']) ?></td>
                        </tr><?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($history === []): ?>
            <p class="table-empty">ยังไม่มีประวัติเบิกสินค้า</p><?php endif; ?>
        <p class="table-empty" id="issue-history-empty" hidden>ไม่พบประวัติเบิกสินค้าที่ตรงกับคำค้นหา</p>
    </section>

    <!-- Dialog: บันทึกการเบิกสินค้า -->
    <dialog class="product-detail-dialog" id="issue-dialog" style="max-width:580px;">
        <div class="product-detail-heading">
            <div>
                <div class="eyebrow section-eyebrow">STOCK OUT</div>
                <h2>บันทึกการเบิกสินค้า</h2>
            </div>
            <button class="product-detail-close" type="button"
                onclick="document.querySelector('#issue-dialog').close()">×</button>
        </div>
        <form class="inventory-form movement-form" method="post" id="issue-movement-form"
            style="grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="record_issue">

            <label>วันที่เบิก *
                <input type="date" name="movement_date" required
                    value="<?= h((string) ($_POST['movement_date'] ?? date('Y-m-d'))) ?>">
            </label>

            <label>เลขที่เอกสาร
                <input name="document_no" maxlength="100"
                    value="<?= h((string) ($_POST['document_no'] ?? '')) ?>" placeholder="ถ้ามี">
            </label>

            <label class="form-span-2">
                ชื่อสินค้า *
                <div class="product-combobox" id="issue-product-combobox" data-product-combobox>
                    <input type="hidden" name="product_code" id="issue-product-code" data-combobox-code
                        value="<?= h($selectedProductCode) ?>">
                    <div class="product-combobox-input-wrap">
                        <input type="text" id="issue-product-search" data-combobox-search
                            placeholder="พิมพ์ชื่อสินค้า, Stock Code หรือ Account Code1 เพื่อค้นหา..." autocomplete="off" role="combobox"
                            aria-autocomplete="list" aria-expanded="false" aria-controls="issue-product-list" required
                            value="<?= h($selectedProductName) ?>">
                        <button type="button" class="product-combobox-toggle" id="issue-product-toggle"
                            data-combobox-toggle aria-label="เปิดรายการสินค้า" tabindex="-1">▾</button>
                    </div>
                    <div class="product-combobox-list" id="issue-product-list" data-combobox-list role="listbox" hidden>
                        <?php foreach ($pageProducts as $product): ?>
                            <?php
                            $ipSc = (string) ($product['stock_code'] ?? $product['id']);
                            $ipAc1 = (string) ($product['account_code1'] ?? '');
                            ?>
                            <button type="button" class="product-combobox-option" role="option"
                                data-code="<?= h($product['id']) ?>"
                                data-stock-code="<?= h($ipSc) ?>"
                                data-account-code1="<?= h($ipAc1) ?>"
                                data-name="<?= h($product['name']) ?>"
                                data-unit="<?= h($product['unit']) ?>"
                                data-stock="<?= h(inventory_quantity($product['stock'])) ?>"
                                data-search="<?= h(mb_strtolower($product['name'] . ' ' . $product['id'] . ' ' . $ipSc . ' ' . $ipAc1, 'UTF-8')) ?>">
                                <strong><?= h($product['name']) ?><?= ($ipAc1 !== '' && $ipAc1 !== '-') ? ' [' . h($ipAc1) . ']' : '' ?></strong>
                                <small>Stock Code <?= h($ipSc) ?> • คงเหลือ <?= inventory_quantity($product['stock']) ?>
                                    <?= h($product['unit']) ?></small>
                            </button>
                        <?php endforeach; ?>
                        <div class="product-combobox-empty" id="issue-product-empty" data-combobox-empty hidden>
                            ไม่พบสินค้าที่ตรงกับคำค้นหา</div>
                    </div>
                </div>
            </label>

            <label>Stock Code
                <input id="issue-product-code-display" data-combobox-code-display readonly
                    placeholder="เลือกสินค้าเพื่อแสดงรหัส" value="<?= h($selectedProductCode) ?>">
            </label>

            <label>หน่วยนับ
                <input id="movement-product-unit" data-combobox-unit-display readonly placeholder="—"
                    value="<?= h($selectedProductUnit) ?>">
            </label>

            <label>จำนวนเบิก *
                <input type="number" name="quantity" min="1" step="1" required
                    value="<?= h((string) ($_POST['quantity'] ?? '1')) ?>">
            </label>

            <label>ผู้เบิก *
                <input name="requested_by" maxlength="150" required
                    value="<?= h((string) ($_POST['requested_by'] ?? $defaultRequester)) ?>">
            </label>

            <label class="form-span-2">ผู้อนุมัติ *
                <input name="approved_by" maxlength="150" required
                    value="<?= h((string) ($_POST['approved_by'] ?? '')) ?>" placeholder="ระบุชื่อผู้อนุมัติ">
            </label>

            <label class="form-span-2">หมายเหตุ
                <input name="notes" maxlength="500"
                    value="<?= h((string) ($_POST['notes'] ?? '')) ?>" placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)">
            </label>

            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:10px;" class="form-span-2">
                <button class="secondary-button" type="button"
                    onclick="document.querySelector('#issue-dialog').close()">ยกเลิก</button>
                <button class="primary-button" type="submit">บันทึกเบิกสินค้า</button>
            </div>
        </form>
    </dialog>
</section>

<script>
    function openIssueDialog(preselect) {
        const dialog = document.querySelector('#issue-dialog');
        if (!dialog) return;

        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const dateStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const dateInput = dialog.querySelector('input[name="movement_date"]');
        if (dateInput && !dateInput.value) {
            dateInput.value = dateStr;
        }

        if (preselect && preselect.code) {
            const codeHidden = dialog.querySelector('#issue-product-code');
            const nameInput = dialog.querySelector('#issue-product-search');
            const codeDisplay = dialog.querySelector('#issue-product-code-display');
            const unitDisplay = dialog.querySelector('#movement-product-unit');
            if (codeHidden) codeHidden.value = preselect.code;
            if (nameInput) nameInput.value = preselect.name || '';
            if (codeDisplay) codeDisplay.value = preselect.code;
            if (unitDisplay) unitDisplay.value = preselect.unit || '';
        }

        dialog.showModal();
    }

    <?php if ($preselectIssueProduct !== null): ?>
    window.addEventListener('DOMContentLoaded', () => {
        openIssueDialog(<?= json_encode($preselectIssueProduct, JSON_UNESCAPED_UNICODE) ?>);
    });
    <?php elseif ($reopenIssueDialog): ?>
    window.addEventListener('DOMContentLoaded', () => {
        openIssueDialog();
    });
    <?php endif; ?>
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
