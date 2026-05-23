<?php
/**
 * Fix CEO manual items - re-insert with proper UTF-8 encoding
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = getDB()->getConnection();
$db->exec("SET NAMES utf8mb4");

// Delete corrupted CEO entries
$db->exec("DELETE FROM user_manual WHERE role = 'ceo'");
echo "Deleted old corrupted CEO entries.\n";

// Insert proper CEO content
$items = [
    [
        'role' => 'ceo',
        'category' => 'ceo',
        'title' => 'Dashboard: ภาพรวมสำหรับผู้บริหาร',
        'content' => '### หน้า Dashboard ผู้บริหาร

เมื่อล็อกอินเข้าสู่ระบบในฐานะ CEO หน้า Dashboard จะแสดงข้อมูลภาพรวมทั้งหมด:

*   **สรุปจำนวนสถานประกอบการ** — แสดงจำนวนสถานประกอบการที่เข้าร่วมโครงการทั้งหมด
*   **คะแนนเฉลี่ยรวม** — คะแนนเฉลี่ยของทุกสถานประกอบการในรอบประเมินปัจจุบัน
*   **กราฟแสดงผล** — กราฟเปรียบเทียบคะแนนแต่ละ Pillar (H1, I2, C3, M4)
*   **Leaderboard** — อันดับสถานประกอบการเรียงตามคะแนนรวม

> 💡 **เคล็ดลับ:** คลิกที่การ์ดสรุปแต่ละรายการเพื่อดูรายละเอียดเพิ่มเติม',
        'display_order' => 50,
    ],
    [
        'role' => 'ceo',
        'category' => 'ceo',
        'title' => 'Leaderboard: อันดับสถานประกอบการ',
        'content' => '### หน้า Leaderboard

หน้า Leaderboard แสดงอันดับสถานประกอบการทั้งหมดเรียงตามคะแนนรวม:

*   **อันดับ** — แสดงอันดับพร้อมรางวัล 🥇🥈🥉 สำหรับ 3 อันดับแรก
*   **ระดับ HICM** — ระดับการรับรอง (Level 1-5) แสดงเป็นสีตามระดับ
*   **คะแนนรายเสาหลัก** — แสดงคะแนน H1, I2, C3, M4 แยกรายรายการ
*   **ตัวกรอง** — สามารถกรองตามปีประเมิน ประเภทอุตสาหกรรม หรือขนาดสถานประกอบการ

### วิธีใช้งาน

1. เข้าเมนู **Leaderboard** จาก Sidebar
2. เลือกรอบการประเมินที่ต้องการดูจากตัวกรองด้านบน
3. ดูอันดับและคะแนนรวมของแต่ละสถานประกอบการ
4. คลิกที่แถวเพื่อดูรายละเอียดผลการประเมิน',
        'display_order' => 51,
    ],
    [
        'role' => 'ceo',
        'category' => 'ceo',
        'title' => 'Reports: รายงานสรุปผลการประเมิน',
        'content' => '### รายงานสรุปสำหรับผู้บริหาร

ผู้บริหารสามารถเข้าถึงรายงานสรุปผลการประเมินได้หลายรูปแบบ:

*   **Radar Chart** — กราฟใยแมงมุมเปรียบเทียบ 4 Pillars ของแต่ละสถานประกอบการ
*   **Bar Chart** — กราฟแท่งเปรียบเทียบคะแนนรายสถานประกอบการ
*   **ตารางสรุป** — ตารางแสดงคะแนนทั้งหมดพร้อมสถานะการประเมิน
*   **ดูรายละเอียด** — คลิกเพื่อดูผลการประเมินแต่ละสถานประกอบการอย่างละเอียด

### การเข้าถึงรายงาน

1. เข้าเมนู **รายงาน** หรือ **Dashboard** จาก Sidebar
2. เลือกรอบการประเมินที่ต้องการ
3. ดูสรุปภาพรวมหรือกดเข้าดูรายละเอียดของแต่ละบริษัท

> ⚠️ **หมายเหตุ:** ผู้บริหาร (CEO) สามารถดูข้อมูลรายงานได้ทั้งหมด แต่ไม่สามารถแก้ไขผลการประเมิน หากต้องการปรับเปลี่ยนข้อมูล กรุณาติดต่อผู้ดูแลระบบ (Admin)',
        'display_order' => 52,
    ],
];

$stmt = $db->prepare("INSERT INTO user_manual (role, category, title, content, display_order, is_active) VALUES (:role, :category, :title, :content, :order, 1)");

foreach ($items as $item) {
    $stmt->execute([
        ':role' => $item['role'],
        ':category' => $item['category'],
        ':title' => $item['title'],
        ':content' => $item['content'],
        ':order' => $item['display_order'],
    ]);
    echo "Inserted: {$item['title']}\n";
}

echo "\nDone! CEO manual items fixed.\n";
