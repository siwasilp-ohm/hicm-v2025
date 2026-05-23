<?php
/**
 * Remove Demo Auditors
 * ลบกรรมการประเมิน Demo ออกจากระบบ
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB()->getConnection();
    
    echo "🗑️ เริ่มการลบกรรมการประเมิน Demo...\n\n";
    
    // Find all demo auditors
    $stmt = $db->prepare("
        SELECT id, name, username FROM users 
        WHERE role = 'auditor' AND username LIKE '%demo%'
    ");
    $stmt->execute();
    $demoAuditors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deletedAuditors = 0;
    $updatedScores = 0;
    
    echo "พบกรรมการประเมิน Demo: " . count($demoAuditors) . " คน\n\n";
    
    foreach ($demoAuditors as $auditor) {
        $auditorId = $auditor['id'];
        $auditorName = $auditor['name'];
        $username = $auditor['username'];
        
        echo "ลบ: $auditorName ($username)\n";
        
        // Set auditor_id to NULL for all scores evaluated by this auditor
        $stmt = $db->prepare("
            UPDATE assessment_scores 
            SET auditor_id = NULL 
            WHERE auditor_id = ?
        ");
        $stmt->execute([$auditorId]);
        $affectedRows = $stmt->rowCount();
        $updatedScores += $affectedRows;
        
        // Delete the auditor user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$auditorId]);
        
        $deletedAuditors++;
    }
    
    echo "\n✅ ผลลัพธ์:\n";
    echo "   - ลบกรรมการประเมิน: $deletedAuditors คน\n";
    echo "   - อัปเดตคะแนน (ยกเลิกการกำหนดกรรมการ): $updatedScores ข้อ\n\n";
    
    // Show remaining auditors
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE role = 'auditor'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "👨‍⚖️ กรรมการประเมินที่เหลือ: " . $result['count'] . " คน\n";
    
    // Show remaining real auditors
    $stmt = $db->prepare("
        SELECT id, name, username FROM users 
        WHERE role = 'auditor'
        ORDER BY id
    ");
    $stmt->execute();
    $realAuditors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nรายชื่อกรรมการประเมินจริง:\n";
    foreach ($realAuditors as $auditor) {
        echo "   - " . $auditor['name'] . " (" . $auditor['username'] . ")\n";
    }
    
    echo "\n✨ การลบเสร็จสมบูรณ์!\n";
    
} catch (Exception $e) {
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
    exit(1);
}
?>
