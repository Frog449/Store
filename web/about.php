<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'เกี่ยวกับเรา | Store Automeation';
$pageDescription = 'ข้อมูลระบบจัดการคลังสินค้า อะไหล่อุตสาหกรรม และภาพรวมการทำงาน';
$activePage = 'about';

require __DIR__ . '/includes/header.php';
?>
<section class="page-intro about-intro">
    <div class="eyebrow section-eyebrow">ABOUT STORE AUTOMEATION</div>
    <h1>อะไหล่ที่ใช่<br><span>งานก็ไปต่อได้</span><b>.</b></h1>
    <p>ระบบจัดการรายการสินค้า รับสินค้าเข้า เบิกสินค้าออก และติดตามจำนวนคงเหลือจากฐานข้อมูลเดียวกัน</p>
</section>

<!-- Mobile App Promo Banner -->
<section class="about-app-banner" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="display:flex;align-items:center;gap:16px;">
        <div style="width:48px;height:48px;border-radius:10px;background:#e0f2fe;color:#0284c7;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
        </div>
        <div>
            <h3 style="margin:0 0 4px;font-size:16px;color:#0f172a;">แอปพลิเคชันมือถือ Store Automeation</h3>
            <p style="margin:0;font-size:13px;color:#64748b;">รองรับการใช้งานทั้ง Android (ดาวน์โหลดไฟล์ APK) และ iOS (Safari PWA แบบเต็มหน้าจอ)</p>
        </div>
    </div>
    <a href="app.php" class="primary-button" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;padding:10px 18px;font-size:14px;white-space:nowrap;">
        ไปหน้าดาวน์โหลดแอป
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
</section>

<section class="about-detail-grid">
    <article class="about-detail-card">
        <span class="about-detail-number">01</span>
        <div class="about-detail-icon">▦</div>
        <h2>รายการสินค้า</h2>
        <p>ค้นหาด้วยชื่อหรือรหัสสินค้า ดูข้อมูลและสถานะคงเหลือ พร้อมแก้ไขรายละเอียดสินค้า</p>
        <a href="catalog.php">ไปรายการสินค้า <span aria-hidden="true">↗</span></a>
    </article>
    <article class="about-detail-card">
        <span class="about-detail-number">02</span>
        <div class="about-detail-icon">↓</div>
        <h2>รับสินค้าเข้าคลัง</h2>
        <p>บันทึกรับสินค้าเดิม หรือเพิ่มสินค้าใหม่พร้อมจำนวนรับเข้าครั้งแรก</p>
        <a href="receive.php">ไปหน้ารับสินค้า <span aria-hidden="true">↗</span></a>
    </article>
    <article class="about-detail-card">
        <span class="about-detail-number">03</span>
        <div class="about-detail-icon">✓</div>
        <h2>เบิกสินค้าออก</h2>
        <p>บันทึกรายการเบิก ผู้เบิก และผู้อนุมัติ พร้อมปรับยอดคงเหลืออัตโนมัติ</p>
        <a href="issue.php">ไปหน้าเบิกสินค้า <span aria-hidden="true">↗</span></a>
    </article>
</section>

<section class="about-note">
    <span class="about-note-icon">i</span>
    <div><strong>ข้อมูลคลังสินค้า</strong><p>ยอดคงเหลือคำนวณจากยอดรับสะสมลบยอดเบิกสะสม และอัปเดตตามรายการเคลื่อนไหวในระบบ</p></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
