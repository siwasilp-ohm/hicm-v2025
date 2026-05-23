<?php
/**
 * Cleanup Demo Data Script
 * ลบข้อมูล HICM Demo ออกจากฐานข้อมูล
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB()->getConnection();
    
    echo "🗑️ เริ่มการลบข้อมูล Demo...\n\n";
    
    // Find all HICM Demo companies
    $stmt = $db->prepare("
        SELECT id, user_id FROM companies 
        WHERE company_name LIKE '%HICM%' OR company_name LIKE '%Demo%'
    ");
    $stmt->execute();
    $demoCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deletedCompanies = 0;
    $deletedUsers = 0;
    
    foreach ($demoCompanies as $company) {
        $companyId = $company['id'];
        $userId = $company['user_id'];
        
        // Delete assessments and related data (cascades will handle it)
        $stmt = $db->prepare("DELETE FROM assessments WHERE company_id = ?");
        $stmt->execute([$companyId]);
        
        // Delete company
        $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        
        // Delete user if it's a demo user (user_id >= 79)
        if ($userId && $userId >= 79) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $deletedUsers++;
        }
        
        $deletedCompanies++;
    }
    
    echo "✅ ผลลัพธ์:\n";
    echo "   - ลบบริษัท Demo: $deletedCompanies บริษัท\n";
    echo "   - ลบ User Demo: $deletedUsers ผู้ใช้\n\n";
    
    // Show remaining real companies
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM companies 
        WHERE company_name NOT LIKE '%HICM%' AND company_name NOT LIKE '%Demo%'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 บริษัทจริงที่เหลือ: " . $result['count'] . " บริษัท\n";
    
    // Show real users (company role)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE role = 'company' AND username NOT LIKE 'demo%'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "👥 ผู้ใช้บริษัทจริง: " . $result['count'] . " ผู้ใช้\n";
    
    // Show auditor users
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE role = 'auditor'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "👨‍⚖️ กรรมการประเมิน: " . $result['count'] . " คน\n\n";
    
    echo "✨ การลบข้อมูล Demo สำเร็จ!\n";
    echo "ตอนนี้คุณสามารถเขียนใช้บริษัทจริงสำหรับรอบการประเมิน Demo ใหม่ได้\n";
    
} catch (Exception $e) {
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
    exit(1);
}
?>
