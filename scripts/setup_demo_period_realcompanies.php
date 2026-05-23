<?php
/**
 * Create Demo Assessment Period - Using Real Companies
 * สร้างรอบการประเมิน Demo โดยใช้บริษัทจริงในฐานข้อมูล
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB()->getConnection();
    
    echo "📋 สร้างรอบการประเมิน Demo ใหม่...\n\n";
    
    // Get all real companies
    $stmt = $db->prepare("
        SELECT id, company_name FROM companies 
        ORDER BY id
        LIMIT 23
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 บริษัททั้งหมด: " . count($companies) . " บริษัท\n";
    
    // Get auditors (5 auditors for 1 company = 5:1 ratio)
    $stmt = $db->prepare("
        SELECT id, name FROM users 
        WHERE role = 'auditor'
        ORDER BY id
        LIMIT 5
    ");
    $stmt->execute();
    $auditors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "👨‍⚖️ กรรมการประเมิน: " . count($auditors) . " คน\n\n";
    
    if (count($auditors) < 5) {
        echo "⚠️ ต้องมีกรรมการประเมินอย่างน้อย 5 คน\n";
        exit(1);
    }
    
    // Create assessment period
    $periodYear = date('Y');
    $periodName = "Demo Assessment Period " . date('Y-m-d H:i:s');
    $adminId = 1; // Admin user
    
    $stmt = $db->prepare("
        INSERT INTO assessment_periods 
        (year, name, description, start_date, end_date, submission_deadline, 
         evaluation_start_date, evaluation_end_date, announcement_date, 
         status, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', 1, ?)
    ");
    
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));
    $nextMonth = date('Y-m-d', strtotime('+30 days'));
    
    $stmt->execute([
        $periodYear,
        $periodName,
        'Demo assessment period for testing with real companies',
        $today,
        $nextMonth,
        $nextWeek,
        $tomorrow,
        $nextMonth,
        $nextMonth,
        $adminId
    ]);
    
    $periodId = $db->lastInsertId();
    echo "✅ สร้างรอบการประเมิน: ID $periodId\n";
    echo "   ชื่อ: $periodName\n\n";
    
    // Create assessments and assign auditors (5:1 ratio)
    $createdAssessments = 0;
    
    foreach ($companies as $index => $company) {
        // Determine which auditors will review this company (5:1 ratio means each company gets all 5 auditors)
        $assignedAuditors = $auditors; // All 5 auditors review each company
        
        // Create assessment
        $stmt = $db->prepare("
            INSERT INTO assessments 
            (company_id, period_id, status, created_at, updated_at)
            VALUES (?, ?, 'draft', NOW(), NOW())
        ");
        $stmt->execute([$company['id'], $periodId]);
        $assessmentId = $db->lastInsertId();
        
        // Create assessment scores with assigned auditors
        $stmt = $db->prepare("
            SELECT id FROM indicators ORDER BY id
        ");
        $stmt->execute();
        $indicators = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($indicators as $indicator) {
            // Get one auditor for this indicator (rotate through the assigned auditors)
            $auditorIndex = ($indicator['id'] - 1) % count($assignedAuditors);
            $auditorId = $assignedAuditors[$auditorIndex]['id'];
            
            $stmt = $db->prepare("
                INSERT INTO assessment_scores 
                (assessment_id, indicator_id, self_score, auditor_id, created_at, updated_at)
                VALUES (?, ?, 0, ?, NOW(), NOW())
            ");
            $stmt->execute([$assessmentId, $indicator['id'], $auditorId]);
        }
        
        $createdAssessments++;
        echo "📝 บริษัท: " . htmlspecialchars($company['company_name']) . "\n";
        echo "   ประเมินโดย: " . implode(', ', array_map(fn($a) => $a['name'], $assignedAuditors)) . "\n";
    }
    
    echo "\n✨ สร้างสำเร็จ!\n";
    echo "   - รอบการประเมิน: 1\n";
    echo "   - บริษัท: " . $createdAssessments . "\n";
    echo "   - ตัวชี้วัดต่อบริษัท: 60\n";
    echo "   - กรรมการประเมินต่อบริษัท: " . count($auditors) . " (5:1 ratio)\n";
    echo "   - รวมคะแนนที่ต้องประเมิน: " . ($createdAssessments * 60 * count($auditors)) . " ข้อ\n\n";
    
    echo "🔗 Period ID: $periodId\n";
    echo "📌 ใช้ Period ID นี้เพื่อเข้าถึงรอบการประเมิน Demo ในระบบ\n";
    
} catch (Exception $e) {
    echo "❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "\n";
    exit(1);
}
?>
