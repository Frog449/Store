<?php
declare(strict_types=1);

define('PUBLIC_PAGE', true);
require __DIR__ . '/includes/bootstrap.php';

// If already inside the mobile application, redirect to dashboard
if (is_running_in_app()) {
    header('Location: index.php');
    exit;
}
$pageTitle = 'ดาวน์โหลดแอปพลิเคชันมือถือ | Store Automeation';
$pageDescription = 'ดาวน์โหลดแอปพลิเคชันระบบจัดการคลังสินค้าสำหรับ Android (.APK) และคู่มือติดตั้งบน iOS (Safari PWA)';
$activePage = 'app';

$apkPath = __DIR__ . '/downloads/store-automeation.apk';
$apkSizeMb = file_exists($apkPath) ? round(filesize($apkPath) / (1024 * 1024), 1) : 4.3;
$apkDate = file_exists($apkPath) ? date('Y-m-d', filemtime($apkPath)) : date('Y-m-d');

require __DIR__ . '/includes/header.php';
?>
<section class="page-intro">
    <div class="eyebrow section-eyebrow">MOBILE APPLICATION</div>
    <h1>แอปพลิเคชันมือถือ<br><span>ใช้งานได้ทุกที่ ทุกเวลา</span><b>.</b></h1>
    <p>เชื่อมต่อระบบจัดการคลังสินค้า บันทึกรับเข้า เบิกออก และตรวจนับสต๊อกได้สะดวกรวดเร็วบนสมาร์ตโฟน</p>
</section>

<!-- Mobile App & PWA Section -->
<section class="about-app-section" id="mobile-app">
    <div class="about-app-grid">
        <!-- Android APK Card -->
        <article class="about-app-card android-card">
            <div class="app-card-header">
                <span class="app-card-badge android-badge">Android Application</span>
                <span class="app-card-ver">v1.2.0</span>
            </div>
            <div class="app-card-body">
                <h3>แอปพลิเคชัน Android (APK)</h3>
                <p>แอปพลิเคชันเฉพาะสำหรับระบบ Android ใช้งานรวดเร็ว รองรับการถ่ายรูปพัสดุประกอบการรับ-เบิก และสามารถปรับเปลี่ยน Server URL ได้จากในแอป</p>

                <div class="app-card-specs">
                    <div><span>ชื่อไฟล์:</span> <strong>store-automeation.apk</strong></div>
                    <div><span>ขนาดไฟล์:</span> <strong><?= $apkSizeMb ?> MB</strong></div>
                    <div><span>ระบบปฏิบัติการ:</span> <strong>Android 7.0 ขึ้นไป (Nougat ถึง Android 15)</strong></div>
                    <div><span>ฟีเจอร์เด่น:</span> <strong>Swipe to Refresh / Camera Upload / Custom Server IP</strong></div>
                    <div><span>อัปเดตล่าสุด:</span> <strong><?= $apkDate ?></strong></div>
                </div>

                <div class="app-card-actions">
                    <a href="download.php?file=apk" class="primary-button app-download-btn" download="store-automeation.apk">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ดาวน์โหลดไฟล์ APK สำหรับ Android
                    </a>
                    <div style="margin-top:8px;font-size:12px;color:#64748b;text-align:center;">
                        หากดาวน์โหลดไม่เริ่มอัตโนมัติ: <a href="downloads/store-automeation.apk" download="store-automeation.apk" style="color:#0284c7;text-decoration:underline;">คลิกที่นี่เพื่อโหลดลิงก์สำรองโดยตรง</a>
                    </div>
                </div>

                <div class="app-card-steps">
                    <strong>คำแนะนำการติดตั้งบน Android:</strong>
                    <ol>
                        <li>แตะปุ่มดาวน์โหลดไฟล์ด้านบน</li>
                        <li>เปิดไฟล์ <code>store-automeation.apk</code> จากแถบแจ้งเตือนหรือโฟลเดอร์ดาวน์โหลด</li>
                        <li>เลือก "ติดตั้ง" (หากระบบเตือนความปลอดภัย ให้เลือก "อนุญาตการติดตั้งจากแหล่งที่ไม่รู้จัก")</li>
                        <li>เปิดแอปและเข้าสู่ระบบได้ทันที (สามารถกดค้างที่หน้าจอเพื่อเปลี่ยน Server IP หากเปลี่ยนวง Wi-Fi)</li>
                    </ol>
                </div>
            </div>
        </article>

        <!-- iOS Safari PWA Card -->
        <article class="about-app-card ios-card">
            <div class="app-card-header">
                <span class="app-card-badge ios-badge">iOS Safari (PWA)</span>
                <span class="app-card-ver">Standalone App</span>
            </div>
            <div class="app-card-body">
                <h3>แอปพลิเคชัน iOS (iPhone / iPad)</h3>
                <p>ติดตั้งเป็นแอปพลิเคชันแบบเต็มหน้าจอ (Progressive Web App) ผ่าน Safari ได้ทันทีโดยไม่ต้องผ่าน App Store เปิดใช้งานได้รวดเร็วเหมือนแอปแท้</p>

                <div class="app-card-specs">
                    <div><span>อุปกรณ์:</span> <strong>iPhone และ iPad ทุกรุ่น</strong></div>
                    <div><span>เบราว์เซอร์:</span> <strong>Safari (iOS / iPadOS)</strong></div>
                    <div><span>โหมดแสดงผล:</span> <strong>เต็มหน้าจอ (Standalone / ไม่มีแถบ URL)</strong></div>
                    <div><span>แคชออฟไลน์:</span> <strong>Service Worker & Asset Cache ทำงานอัตโนมัติ</strong></div>
                </div>

                <div class="app-card-steps">
                    <strong>วิธีติดตั้งผ่าน Safari บน iPhone / iPad:</strong>
                    <ol>
                        <li>เปิดเว็บไซต์นี้ด้วยเบราว์เซอร์ <strong>Safari</strong> บนเครื่อง iPhone หรือ iPad</li>
                        <li>แตะที่ปุ่ม <strong>แชร์ (Share)</strong> (ไอคอนสี่เหลี่ยมลูกศรชี้ขึ้น ที่แถบเมนูด้านล่าง)</li>
                        <li>เลื่อนลงแล้วแตะเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</strong></li>
                        <li>แตะปุ่ม <strong>"เพิ่ม" (Add)</strong> ที่มุมบนขวา เพื่อเสร็จสิ้น</li>
                        <li>ไอคอน <strong>Store AUT</strong> จะไปปรากฏบนหน้าจอหลัก พร้อมเปิดใช้งานได้ทันที</li>
                    </ol>
                </div>

                <div class="ios-status-box" style="margin-top:16px;padding:12px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:12px;color:#166534;line-height:1.5;">
                    <strong>สถานะความพร้อม:</strong> ระบบรองรับ PWA ครบถ้วนตามมาตรฐาน (Manifest, Service Worker, Apple Touch Icon และ Offline Caching)
                </div>
            </div>
        </article>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
