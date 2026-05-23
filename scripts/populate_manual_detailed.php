<?php
/**
 * HICM V2025 - Populate Exhaustive User Manual
 * Documentation for every single system page.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB()->getConnection();

// Clear existing items
$db->exec("DELETE FROM user_manual");

$manualData = [
    // --- OVERVIEW / GENERAL ---
    [
        'category' => 'overview',
        'role' => 'all',
        'title' => 'แนะนำระบบ HICM V2025',
        'display_order' => 1,
        'content' => '### ศูนย์กลางการประเมินสุขภาวะอุตสาหกรรม
HICM V2025 คือระบบที่ออกแบบมาเพื่อช่วยให้สถานประกอบการยกระดับมาตรฐานสุขภาพ ความปลอดภัย ชุมชน และการบริหารจัดการ โดยใช้เกณฑ์ 4 เสาหลัก (4 Pillars) ที่เป็นมาตรฐานสากล

#### ฟีเจอร์หลักของระบบ
*   **Role-Based Dashboards**: หน้าแรกที่ปรับเปลี่ยนตามบทบาทของผู้ใช้งาน
*   **Real-time Scoring**: เห็นผลคะแนนทันทีหลังจากการประเมินเสร็จสิ้น
*   **Evidence Vault**: ระบบจัดเก็บหลักฐานการประเมินที่ค้นหาและตรวจสอบได้ง่าย
*   **Radar Chart Analytics**: การวิเคราะห์จุดแข็ง-จุดอ่อนผ่านกราฟใยแมงมุม'
    ],

    // --- COMPANY SUITE ---
    [
        'category' => 'company',
        'role' => 'company',
        'title' => 'Dashboard: แดชบอร์ดผู้ประกอบการ',
        'display_order' => 10,
        'content' => '### หน้าสรุปสถานะการประเมิน
ในฐานะ Company หน้า Dashboard คือที่ที่คุณจะเห็นภาพรวมทั้งหมด:
*   **Status Cards**: ดูจำนวนการประเมินที่ "กำลังดำเนินการ", "รอตรวจสอบ" และ "เสร็จสิ้น"
*   **Latest Score**: คะแนน Pillar ล่าสุดที่ได้รับการยืนยัน
*   **Actionable Tasks**: รายการที่ต้องดำเนินการเร่งด่วน เช่น การแนบหลักฐานเพิ่มเติม'
    ],
    [
        'category' => 'company',
        'role' => 'company',
        'title' => 'Assessment: ขั้นตอนการประเมินตนเอง',
        'display_order' => 11,
        'content' => '### การกรอกแบบประเมิน (assessment-form.php)
หัวใจสำคัญของ HICM คือการให้ข้อมูลที่เป็นจริง:
1.  **การเลือกหัวข้อ**: เลือกเสาหลักที่ต้องการประเมิน (H1, I2, C3, M4)
2.  **การให้คะแนน**: 0, 0.25, 0.5, 0.75, 1.0 ตามเกณฑ์ที่ปรากฏในคำอธิบาย
3.  **การใช้ N/A**: หากข้อไหนไม่เข้าข่ายโรงงานของท่าน ให้ระบุ N/A เพื่อให้ระบบไม่นำมาหารคะแนนเฉลี่ย
4.  **บันทึกร่าง**: กดปุ่ม "บันทึกร่าง" ได้ตลอดเวลา ระบบจะเก็บข้อมูลไว้ให้ทำต่อ'
    ],
    [
        'category' => 'company',
        'role' => 'company',
        'title' => 'Milestones: การติดตามความก้าวหน้า',
        'display_order' => 12,
        'content' => '### วางแผนการพัฒนา (milestones.php)
ใช้ Milestones เพื่อกำกับทิศทางการเติบโตขององค์กร:
*   **Set Target**: ตั้งเป้าหมายคะแนนที่ต้องการบรรลุ (เช่น จาก 600 ไปสู่ 800)
*   **Track Growth**: ดูประวัติการพัฒนาคะแนนรายเสาหลักเทียบกับรอบปีก่อนหน้า
*   **Milestone Badge**: รับตราสัญลักษณ์เมื่อบรรลุเป้าหมายที่ตั้งไว้'
    ],
    [
        'category' => 'company',
        'role' => 'company',
        'title' => 'Results: การวิเคราะห์ผลลัพธ์',
        'display_order' => 13,
        'content' => '### ถอดรหัสคะแนน (assessment-result.php)
เมื่อ Auditor ยืนยันผลคะแนน คุณจะได้รับการวิเคราะห์เชิงลึก:
*   **Pillar Breakdown**: ดูคะแนนแยกรายเสาหลักเทียบกับค่าเฉลี่ยของบริษัทในอุตสาหกรรมเดียวกัน
*   **Level Certificate**: ดูระดับเหรียญการรับรอง (Gold, Silver, Excellence)
*   **Gaps & Recommendations**: คำแนะนำจากผู้เชี่ยวชาญเพื่อปรับปรุงองค์กร'
    ],

    // --- AUDITOR SUITE ---
    [
        'category' => 'auditor',
        'role' => 'auditor',
        'title' => 'Evaluations: การตรวจประเมินอย่างมืออาชีพ',
        'display_order' => 20,
        'content' => '### ขั้นตอนการ Audit (auditor-evaluate.php)
Auditor คือผู้ตรวจสอบและให้คำแนะนำที่มีคุณค่า:
*   **Evidence Review**: คลิกดูไฟล์แนบจาก Company ตรวจสอบความสอดคล้องกับเกณฑ์
*   **Score Correction**: หากหลักฐานไม่เพียงพอ ท่านสามารถปรับคะแนนลงพร้อมระบุเหตุผล
*   **Auditor Comments**: เขียนข้อเสนอแนะเพื่อให้องค์กรนำไปพัฒนาต่อ
*   **Finalize**: เมื่อประเมินครบทุกข้อแล้ว กด Finalize เพื่อส่งผลคะแนนเข้าสู่ระบบ'
    ],
    [
        'category' => 'auditor',
        'role' => 'auditor',
        'title' => 'Multi-Auditor Verification',
        'display_order' => 21,
        'content' => '### การทำงานร่วมกับทีมผู้ตรวจ
ในกรณีที่มีการประเมินร่วมกัน:
*   **Side-by-Side View**: ท่านจะเห็นคะแนนที่ท่านให้เทียบกับคะแนนเฉลี่ยของ Auditor ท่านอื่น
*   **Conflict Resolution**: หากคะแนนต่างกันมาก Admin อาจขอให้มีการประชุมร่วมเพื่อสรุปผลคะแนนเดียว'
    ],

    // --- ADMIN SUITE ---
    [
        'category' => 'admin',
        'role' => 'admin',
        'title' => 'Users: การจัดการผู้ใช้และสิทธิ์',
        'display_order' => 30,
        'content' => '### การดูแลสมาชิก (users.php)
*   **Create Users**: เพิ่มผู้ประกอบการใหม่ หรือเชิญ Auditor เข้าสู่ระบบ
*   **Role Management**: กำหนดสิทธิ์การเข้าถึง (Admin, Auditor, Company, CEO)
*   **Reset Passwords**: ช่วยเหลือผู้ใช้ที่ลืมรหัสผ่าน'
    ],
    [
        'category' => 'admin',
        'role' => 'admin',
        'title' => 'Indicators: การปรับแต่งเกณฑ์คะแนน',
        'display_order' => 31,
        'content' => '### วิศวกรรมตัวชี้วัด (indicators.php)
Admin สามารถปรับเปลี่ยนเกณฑ์ให้ทันสมัยเสมอ:
*   **Modify Questions**: แก้ไขข้อความคำถามของแต่ละ Pillar
*   **Weight Adjustment**: ปรับน้ำหนักคะแนนของแต่ละเสาหลัก (รวม 1,000 คะแนน)
*   **Scoring Levels**: กำหนดรายละเอียดของคะแนน 0.25, 0.5, 0.75, 1.0'
    ],
    [
        'category' => 'admin',
        'role' => 'admin',
        'title' => 'Periods: วงจรชีวิตการประเมิน',
        'display_order' => 32,
        'content' => '### จัดการรอบเวลา (periods.php)
*   **Active Period**: กำหนดช่วงวันที่ระบบจะเปิดให้ทำแบบประเมิน
*   **Context Setting**: เลือกว่าปีนี้จะเน้นเกณฑ์เวอร์ชันไหน
*   **Archive**: ย้ายข้อมูลรอบปีก่อนเข้าสู่ระบบคลังข้อมูล'
    ],
    [
        'category' => 'admin',
        'role' => 'admin',
        'title' => 'Assignments: การจับคู่ Auditor',
        'display_order' => 33,
        'content' => '### การกระจายงาน (auditor-assignments.php)
*   **Expertise Match**: ระบบจะแสดงความถนัดของ Auditor (เช่น เชี่ยวชาญเรื่อง Industrial Hygiene)
*   **Queue Balancing**: ตรวจสอบโหลดงานของ Auditor เพื่อไม่ให้ใครรับงานหนักเกินไป'
    ],
    [
        'category' => 'admin',
        'role' => 'admin',
        'title' => 'Settings: การตั้งค่าโครงสร้างระบบ',
        'display_order' => 34,
        'content' => '### การปรับจูนลึก (settings.php / organizations.php)
*   **SMTP Setup**: ตั้งค่าอีเมลเพื่อส่งแจ้งเตือนอัตโนมัติ
*   **Industry Categories**: กำหนดหมวดหมู่โรงงาน (เช่น ปิโตรเคมี, อาหาร, ยานยนต์)
*   **Organization Tree**: จัดกลุ่มบริษัทตามสังกัดหรือภูมิภาค'
    ],

    // --- ACCOUNT & SECURITY ---
    [
        'category' => 'account',
        'role' => 'all',
        'title' => 'Security: ความปลอดภัยของข้อมูล',
        'display_order' => 40,
        'content' => '### ระบบการรักษาความปลอดภัย
*   **Encryption**: รหัสผ่านถูกเข้ารหัสด้วยเทคโนโลยีล่าสุด
*   **Session Control**: ระบบจะตัดการเชื่อมต่ออัตโนมัติหากไม่มีการใช้งาน
*   **Change Password**: แนะนำให้เปลี่ยนรหัสผ่านทุกๆ 3-6 เดือน'
    ]
];

$stmt = $db->prepare("INSERT INTO user_manual (category, role, title, content, display_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");

$successCount = 0;
foreach ($manualData as $item) {
    if ($stmt->execute([$item['category'], $item['role'], $item['title'], $item['content'], $item['display_order']])) {
        $successCount++;
    }
}

echo "Successfully populated $successCount exhaustive manual items with categories.";
?>
