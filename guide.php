<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'คู่มือใช้งาน | Store Automeation';
$pageDescription = 'คู่มือบันทึกสินค้า รับเข้า เบิกออก และตรวจสอบยอดสต๊อก';
$activePage = 'guide';
require __DIR__ . '/includes/header.php';
?>
<section class="guide-page">
    <div class="page-intro guide-intro">
        <div class="eyebrow section-eyebrow">HOW TO USE</div>
        <h1>คู่มือใช้งาน<span class="heading-period">.</span></h1>
        <p>ขั้นตอนใช้งานระบบทะเบียนสินค้าและบันทึกสต๊อกให้ยอดคงเหลือถูกต้อง</p>
    </div>

    <section class="guide-steps" aria-label="ขั้นตอนการใช้งาน">
        <article class="guide-step">
            <span class="guide-step-number">01</span><div class="guide-step-body"><h2>เพิ่มสินค้าเข้าทะเบียน</h2><p>เปิดหน้า <a href="receive.php?new=1#new-product">รับสินค้า</a> แล้วเลือกเพิ่มสินค้าใหม่ กรอกรหัส ชื่อ หมวดหมู่ ประเภท หน่วยนับ ตำแหน่งจัดเก็บ จุดสั่งซื้อขั้นต่ำ และจำนวนรับครั้งแรก ระบบจะสร้างทะเบียนและบันทึกรับพร้อมกัน หากมีรูปสินค้าให้เลือกไฟล์ JPG, PNG หรือ WEBP ขนาดไม่เกิน 5 MB</p></div>
        </article>
        <article class="guide-step">
            <span class="guide-step-number">02</span><div class="guide-step-body"><h2>รับสินค้าเดิมเข้าคลัง</h2><p>เปิดหน้า <a href="receive.php">รับสินค้า</a> เลือกสินค้าที่มีในทะเบียน ระบุวันที่และจำนวนรับ พร้อมกรอกเลขที่เอกสาร ผู้ส่งมอบ ผู้รับ และหน่วยงานตามต้องการ</p></div>
        </article>
        <article class="guide-step">
            <span class="guide-step-number">03</span><div class="guide-step-body"><h2>บันทึกเบิกสินค้าออกจากคลัง</h2><p>เปิดหน้า <a href="issue.php">เบิกสินค้า</a> เลือกสินค้าและระบุจำนวนเบิก ผู้เบิก แผนก/ผู้รับ และชื่อผู้อนุมัติ (จำเป็น) หากเบิกเกินยอดคงเหลือ ระบบจะบันทึกและแสดงยอดติดลบเพื่อให้ตรวจสอบ</p></div>
        </article>
        <article class="guide-step">
            <span class="guide-step-number">04</span><div class="guide-step-body"><h2>ตรวจสอบยอดและสถานะ</h2><p>หน้า <a href="index.php">ภาพรวม</a> สรุปจำนวนสินค้า รายการรับ–เบิก สถานะ และจำนวนสินค้าแยกตามหมวดหมู่ ส่วนหน้า <a href="catalog.php">ทะเบียนสินค้า</a> ใช้ค้นหาและดูรายละเอียด หรือแก้ไขข้อมูลพร้อมเพิ่ม เปลี่ยน หรือลบรูปสินค้า</p></div>
        </article>
    </section>

    <section class="guide-callout">
        <span class="about-note-icon">!</span>
        <div><strong>สำรองข้อมูลเป็นระยะ</strong><p>ข้อมูลปัจจุบันจัดเก็บใน MySQL ฐานข้อมูล <code>store</code> ให้สำรองฐานข้อมูลนี้ผ่าน phpMyAdmin เป็นระยะ ส่วน <code>storage/inventory.sqlite</code> เป็นข้อมูลเดิมสำหรับย้ายเข้าฐานครั้งแรก ควรเก็บไว้จนกว่าจะสำรองข้อมูล MySQL เรียบร้อย</p></div>
    </section>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
