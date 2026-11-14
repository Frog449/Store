<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'เกี่ยวกับเรา | Store Automeation';
$pageDescription = 'ข้อมูลระบบรายการสินค้าและจัดการคลังสินค้า';
$activePage = 'about';
require __DIR__ . '/includes/header.php';
?>
<section class="page-intro about-intro">
    <div class="eyebrow section-eyebrow">ABOUT STORE AUTOMEATION</div>
    <h1>อะไหล่ที่ใช่<br><span>งานก็ไปต่อได้</span><b>.</b></h1>
    <p>ระบบจัดการรายการสินค้า รับสินค้าเข้า เบิกสินค้าออก และติดตามจำนวนคงเหลือจากฐานข้อมูลเดียวกัน</p>
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
