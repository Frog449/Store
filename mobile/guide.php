<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'คู่มือใช้งาน | Store Automeation';
$pageDescription = 'คู่มือบันทึกสินค้า รับเข้า เบิกออก และตรวจสอบยอดสต๊อก';
$activePage = 'guide';
require __DIR__ . '/includes/header.php';
?>
<section class="guide-page">
    <div class="page-intro guide-intro">
        <div class="eyebrow section-eyebrow">HOW TO USE</div>
        <h1>คู่มือใช้งาน<span class="heading-period">.</span></h1>
    </div>

    <section class="guide-steps" aria-label="ขั้นตอนการใช้งาน">
        <article class="guide-step">
            <span class="guide-step-number">01</span><div class="guide-step-body"><h2>เพิ่มสินค้าเข้ารายการ</h2><p>เปิดหน้า <a href="receive.php?new=1#new-product">รับสินค้า</a> แล้วเลือกเพิ่มสินค้าใหม่ กรอกรหัส ชื่อ หมวดหมู่ ประเภท หน่วยนับ ตำแหน่งจัดเก็บ จุดสั่งซื้อขั้นต่ำ และจำนวนรับครั้งแรก ระบบจะสร้างรายการและบันทึกรับพร้อมกัน หากมีรูปสินค้าให้เลือกไฟล์ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB</p></div>
        </article>
        <article class="guide-step">
            <span class="guide-step-number">02</span><div class="guide-step-body"><h2>รับสินค้าเดิมเข้าคลัง</h2><p>เปิดหน้า <a href="receive.php">รับสินค้า</a> เลือกสินค้าที่มีในรายการ ระบุวันที่และจำนวนรับ พร้อมกรอกเลขที่เอกสาร ผู้ส่งมอบ ผู้รับ และหน่วยงานตามต้องการ</p></div>
        </article>
        <article class="guide-step">
            <span class="guide-step-number">03</span><div class="guide-step-body"><h2>บันทึกเบิกสินค้าออกจากคลัง</h2><p>เปิดหน้า <a href="issue.php">เบิกสินค้า</a> เลือกสินค้าและระบุจำนวนเบิก ผู้เบิก แผนก/ผู้รับ และชื่อผู้อนุมัติ (จำเป็น) หากเบิกเกินยอดคงเหลือ ระบบจะบันทึกและแสดงยอดติดลบเพื่อให้ตรวจสอบ</p></div>
        </article>
        <article class="guide-step">
            <span class="guide-step-number">04</span><div class="guide-step-body"><h2>ตรวจสอบยอดและสถานะ</h2><p>หน้า <a href="index.php">ภาพรวม</a> สรุปจำนวนสินค้า รายการรับ–เบิก สถานะ และจำนวนสินค้าแยกตามหมวดหมู่ ส่วนหน้า <a href="catalog.php">รายการสินค้า</a> ใช้ค้นหา ดูรายละเอียด และแก้ไขสินค้า หากลบสินค้า ระบบจะลบประวัติรับ–เบิกที่เกี่ยวข้องด้วย และไม่สามารถกู้คืนได้</p></div>
        </article>
    </section>

    <section class="guide-pdf-section" aria-label="เอกสารคู่มือการใช้งาน">
        <div class="guide-pdf-header">
            <div class="guide-pdf-title-wrap">
                <span class="guide-pdf-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </span>
                <div>
                    <h2 class="guide-pdf-title">คู่มือการใช้งาน Store Automeation สำหรับ Admin &amp; Staff</h2>
                    <p class="guide-pdf-desc">เอกสารคู่มือประกอบภาพหน้าจอระบบอย่างละเอียด (PDF • 11 หน้า)</p>
                </div>
            </div>
            <div class="guide-pdf-actions">
                <a href="docs/store-automation-guide.pdf" target="_blank" rel="noopener" class="guide-pdf-btn guide-pdf-btn-outline">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span>เปิดดูเต็มหน้า</span>
                </a>
                <a href="docs/store-automation-guide.pdf" download="Store-Automation-Manual.pdf" class="guide-pdf-btn guide-pdf-btn-primary">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>ดาวน์โหลด PDF</span>
                </a>
            </div>
        </div>
        <div class="guide-pdf-viewer">
            <object data="docs/store-automation-guide.pdf#toolbar=1" type="application/pdf" width="100%" height="100%">
                <iframe src="docs/store-automation-guide.pdf#toolbar=1" title="คู่มือการใช้งาน Store Automeation สำหรับ Admin &amp; Staff" width="100%" height="100%">
                    <div class="guide-pdf-fallback">
                        <p>บราวเซอร์ของคุณไม่รองรับการแสดงตัวอย่างไฟล์ PDF โดยตรงในหน้านี้</p>
                        <a href="docs/store-automation-guide.pdf" target="_blank" rel="noopener" class="guide-pdf-btn guide-pdf-btn-primary">คลิกที่นี่เพื่อเปิดหรือดาวน์โหลดคู่มือ</a>
                    </div>
                </iframe>
            </object>
        </div>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
