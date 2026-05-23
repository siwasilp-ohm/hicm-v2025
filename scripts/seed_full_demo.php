<?php
/**
 * HICM V2025 - Full Demo Data Seeder
 * ============================================================
 * สร้าง demo data ครบ loop:
 *   1. เปิดรอบการประเมิน (assessment period)
 *   2. จับคู่กรรมการ 4 คน ต่อ 1 บริษัท
 *   3. บริษัทประเมินตนเอง (self-assessment) ครบ 60 ตัวชี้วัด
 *   4. กรรมการประเมินบริษัท (auditor evaluation) ครบ 60 ตัวชี้วัด × 4 คน
 *   5. คำนวณคะแนนรวม + กำหนดระดับ HICM
 * ============================================================
 * Usage: php scripts/seed_full_demo.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/assessment.php';

// ======================================================================
// CONFIGURATION
// ======================================================================
$PERIOD_YEAR   = 2569;  // B.E. 2569 = A.D. 2026
$PERIOD_NAME   = 'รอบประเมินประจำปี 2569 (Demo)';
$NUM_AUDITORS  = 4;     // จำนวนกรรมการต่อบริษัท

echo "============================================================\n";
echo "  HICM V2025 - Full Demo Data Seeder\n";
echo "============================================================\n\n";

$db = getDB();
$pdo = $db->getConnection();

// ======================================================================
// Helper: random score with realistic distribution
// ======================================================================
function randomScore($companyProfile = 'medium') {
    // score: 0, 0.25, 0.5, 0.75, 1.0
    $scores = [0, 0.25, 0.5, 0.75, 1.0];
    
    // Weight distribution varies by company profile
    switch ($companyProfile) {
        case 'excellent': // ≥900 → level 5
            $weights = [1, 2, 5, 25, 67]; break;
        case 'good':      // 800-899 → level 4
            $weights = [1, 3, 10, 40, 46]; break;
        case 'performing': // 700-799 → level 3
            $weights = [2, 5, 20, 43, 30]; break;
        case 'developing': // 600-699 → level 2
            $weights = [3, 10, 35, 35, 17]; break;
        case 'emerging':   // <600 → level 1
            $weights = [8, 20, 35, 27, 10]; break;
        default: // medium mix
            $weights = [3, 8, 25, 40, 24]; break;
    }
    
    $rand = mt_rand(1, array_sum($weights));
    $cumulative = 0;
    foreach ($weights as $i => $w) {
        $cumulative += $w;
        if ($rand <= $cumulative) return $scores[$i];
    }
    return 0.5;
}

// Slightly vary auditor scores around the self-score to be realistic
function auditorVariation($selfScore) {
    $scores = [0, 0.25, 0.5, 0.75, 1.0];
    $idx = array_search($selfScore, $scores);
    if ($idx === false) $idx = 2;
    
    // ±1 step variation, with bias toward same score
    $r = mt_rand(1, 100);
    if ($r <= 45) {
        return $selfScore; // same as self
    } elseif ($r <= 65) {
        return $scores[max(0, $idx - 1)]; // one step lower
    } elseif ($r <= 85) {
        return $scores[min(4, $idx + 1)]; // one step higher
    } elseif ($r <= 93) {
        return $scores[max(0, $idx - 2)]; // two steps lower
    } else {
        return $scores[min(4, $idx + 2)]; // two steps higher
    }
}

function randomEvidence($indicatorCode) {
    $evidences = [
        'มีเอกสารนโยบายประกอบ ลงนามโดยผู้บริหารสูงสุด',
        'มีรายงานผลการดำเนินงานประจำปี',
        'มีหลักฐานการอบรมพนักงาน จำนวน 3 ครั้ง/ปี',
        'มีคำสั่งแต่งตั้งคณะกรรมการ พร้อมบันทึกการประชุม',
        'มีแผนปฏิบัติงานและงบประมาณที่ได้รับอนุมัติ',
        'มีรายงานผลการตรวจสอบภายใน',
        'มีหลักฐานภาพกิจกรรม พร้อมรายชื่อผู้เข้าร่วม',
        'มีเอกสาร SOP ฉบับปรับปรุงล่าสุด',
        'มีข้อมูลสถิติและการวิเคราะห์แนวโน้ม 3 ปี',
        'มีบันทึกการทบทวนผลงานรายไตรมาส',
        'มีหลักฐานการสำรวจความพึงพอใจพนักงาน',
        'มีรายงานผลการ audit ภายนอก/ISO',
        'มีหนังสือรับรอง/ใบประกาศเกียรติคุณ',
    ];
    return $evidences[array_rand($evidences)] . " [{$indicatorCode}]";
}

function auditorComment($score) {
    if ($score >= 0.75) {
        $comments = [
            'มีการดำเนินงานดี เป็นระบบ',
            'ผลงานดีเยี่ยม มีหลักฐานครบถ้วน',
            'มีผลลัพธ์ที่ดี ครอบคลุม',
            'การดำเนินงานเป็นที่น่าพอใจมาก',
            'มีการพัฒนาต่อเนื่อง ควรรักษาระดับ',
        ];
    } elseif ($score >= 0.5) {
        $comments = [
            'พอใช้ ควรปรับปรุงเพิ่มเติม',
            'มีการดำเนินงานระดับหนึ่ง แต่ยังขาดความต่อเนื่อง',
            'มีหลักฐานบางส่วน ควรเพิ่มเอกสารประกอบ',
            'ผ่านเกณฑ์ แต่มีจุดที่ต้องพัฒนา',
            'ดำเนินงานได้ แต่ยังไม่เป็นระบบชัดเจน',
        ];
    } else {
        $comments = [
            'ต้องปรับปรุงอย่างเร่งด่วน',
            'ยังไม่มีหลักฐานเพียงพอ',
            'ขาดการดำเนินงานที่ชัดเจน',
            'ไม่พบเอกสารประกอบ',
            'จำเป็นต้องวางแผนและดำเนินงานใหม่',
        ];
    }
    return $comments[array_rand($comments)];
}

// ======================================================================
// STEP 0: Gather existing data
// ======================================================================
echo "[0] กำลังเก็บข้อมูลพื้นฐาน...\n";

// All companies
$companies = $pdo->query("SELECT id, user_id, company_name FROM companies WHERE is_active = 1 ORDER BY id")->fetchAll();
echo "   → พบบริษัท: " . count($companies) . " แห่ง\n";

// All auditors
$auditors = $pdo->query("SELECT id, username, name FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY id")->fetchAll();
echo "   → พบกรรมการ: " . count($auditors) . " คน\n";

if (count($auditors) < $NUM_AUDITORS) {
    die("❌ กรรมการไม่พอ! ต้องการ {$NUM_AUDITORS} คน มีเพียง " . count($auditors) . " คน\n");
}

// All active indicators
$indicators = $pdo->query("SELECT id, pillar_id, code FROM indicators WHERE is_active = 1 ORDER BY pillar_id, display_order")->fetchAll();
echo "   → พบตัวชี้วัด: " . count($indicators) . " ข้อ\n";

// ======================================================================
// STEP 1: Create Assessment Period
// ======================================================================
echo "\n[1] สร้างรอบการประเมิน...\n";

// Check if period already exists
$stmt = $pdo->prepare("SELECT id FROM assessment_periods WHERE year = ? AND name = ?");
$stmt->execute([$PERIOD_YEAR, $PERIOD_NAME]);
$existingPeriod = $stmt->fetch();

if ($existingPeriod) {
    $periodId = $existingPeriod['id'];
    echo "   → ใช้รอบที่มีอยู่แล้ว ID={$periodId}\n";
    
    // Clean up old demo data for this period
    echo "   → ล้างข้อมูล demo เดิมสำหรับรอบนี้...\n";
    $pdo->prepare("DELETE FROM assessment_evaluator_scores WHERE assessment_id IN (SELECT id FROM assessments WHERE period_id = ?)")->execute([$periodId]);
    $pdo->prepare("DELETE FROM assessment_evaluators WHERE assessment_id IN (SELECT id FROM assessments WHERE period_id = ?)")->execute([$periodId]);
    $pdo->prepare("DELETE FROM assessment_scores WHERE assessment_id IN (SELECT id FROM assessments WHERE period_id = ?)")->execute([$periodId]);
    $pdo->prepare("DELETE FROM assessments WHERE period_id = ?")->execute([$periodId]);
    
    // Update the period
    $pdo->prepare("UPDATE assessment_periods SET status = 'open', is_active = 1, show_auditor_results = 1,
        start_date = ?, end_date = ?, submission_deadline = ?, evaluation_start_date = ?, evaluation_end_date = ?, announcement_date = ?
        WHERE id = ?")->execute([
        '2026-01-01', '2026-12-31', '2026-06-30', '2026-07-01', '2026-10-31', '2026-11-15', $periodId
    ]);
} else {
    $stmt = $pdo->prepare("
        INSERT INTO assessment_periods (year, name, description, start_date, end_date, submission_deadline, 
            evaluation_start_date, evaluation_end_date, announcement_date, status, is_active, show_auditor_results, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', 1, 1, 1)
    ");
    $stmt->execute([
        $PERIOD_YEAR,
        $PERIOD_NAME,
        'รอบการประเมิน Demo สำหรับทดสอบ flow ครบ loop - ปี 2569',
        '2026-01-01',   // start
        '2026-12-31',   // end  
        '2026-06-30',   // submission deadline
        '2026-07-01',   // evaluation start
        '2026-10-31',   // evaluation end
        '2026-11-15',   // announcement
    ]);
    $periodId = $pdo->lastInsertId();
    echo "   → สร้างรอบใหม่ ID={$periodId}\n";
}

echo "   ✅ รอบ: {$PERIOD_NAME} (period_id={$periodId}), status=open, show_auditor_results=ON\n";

// ======================================================================
// STEP 2-4: For each company → create assessment, self-score, assign auditors, auditor scores
// ======================================================================
echo "\n[2-4] สร้างข้อมูลการประเมินครบทุกบริษัท...\n";
echo str_repeat("─", 80) . "\n";

// Assign company profiles for variety in levels
$profiles = ['excellent', 'good', 'performing', 'developing', 'emerging'];
$companyCount = count($companies);

// Create round-robin of auditor assignments (rotate groups of 4)
$auditorIds = array_column($auditors, 'id');
shuffle($auditorIds); // randomize order

$stats = ['level1' => 0, 'level2' => 0, 'level3' => 0, 'level4' => 0, 'level5' => 0];

foreach ($companies as $idx => $company) {
    $companyId = $company['id'];
    $companyName = $company['company_name'];
    
    // Assign profile to get variety of levels
    $profile = $profiles[$idx % count($profiles)];
    
    echo "\n  [{$idx}] {$companyName} (company_id={$companyId}, profile={$profile})\n";
    
    // --- Step 2a: Create assessment ---
    $stmt = $pdo->prepare("
        INSERT INTO assessments (company_id, period_id, status, submitted_at)
        VALUES (?, ?, 'evaluated', NOW())
    ");
    $stmt->execute([$companyId, $periodId]);
    $assessmentId = $pdo->lastInsertId();
    echo "      ├─ Assessment ID={$assessmentId}\n";
    
    // --- Step 2b: Select 4 auditors for this company (rotating) ---
    $startIdx = ($idx * $NUM_AUDITORS) % count($auditorIds);
    $assignedAuditors = [];
    for ($a = 0; $a < $NUM_AUDITORS; $a++) {
        $aIdx = ($startIdx + $a) % count($auditorIds);
        $assignedAuditors[] = $auditorIds[$aIdx];
    }
    
    // Insert evaluator assignments
    $stmtEval = $pdo->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id, assigned_at, submitted_at) VALUES (?, ?, NOW(), NOW())");
    foreach ($assignedAuditors as $audId) {
        $stmtEval->execute([$assessmentId, $audId]);
    }
    echo "      ├─ กรรมการ: [" . implode(', ', $assignedAuditors) . "]\n";
    
    // Set evaluator_id & evaluated_by on assessment
    $pdo->prepare("UPDATE assessments SET evaluator_id = ?, evaluated_by = ?, evaluated_at = NOW() WHERE id = ?")
        ->execute([$assignedAuditors[0], $assignedAuditors[0], $assessmentId]);
    
    // --- Step 3: Self-assessment scores (60 indicators) ---
    $stmtScore = $pdo->prepare("
        INSERT INTO assessment_scores (assessment_id, indicator_id, self_score, self_evidence, auditor_score, auditor_comment, auditor_id, evaluated_at)
        VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL)
    ");
    
    $selfScores = []; // indicator_id => score
    foreach ($indicators as $ind) {
        $score = randomScore($profile);
        $evidence = randomEvidence($ind['code']);
        $stmtScore->execute([$assessmentId, $ind['id'], $score, $evidence]);
        $selfScores[$ind['id']] = $score;
    }
    echo "      ├─ ประเมินตนเอง: 60 ตัวชี้วัด ✓\n";
    
    // --- Step 4: Auditor scores (4 auditors × 60 indicators) ---
    $stmtAudScore = $pdo->prepare("
        INSERT INTO assessment_evaluator_scores (assessment_id, indicator_id, user_id, score, comment, is_na, evaluated_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    
    foreach ($indicators as $ind) {
        $indId = $ind['id'];
        $allScoresForInd = [];
        $allComments = [];
        
        foreach ($assignedAuditors as $audId) {
            $audScore = auditorVariation($selfScores[$indId]);
            $audComment = auditorComment($audScore);
            $stmtAudScore->execute([$assessmentId, $indId, $audId, $audScore, $audComment]);
            $allScoresForInd[] = $audScore;
            $allComments[] = $audComment;
        }
        
        // Update assessment_scores with average auditor score
        $avgScore = array_sum($allScoresForInd) / count($allScoresForInd);
        $combinedComment = implode(' | ', $allComments);
        
        $pdo->prepare("
            UPDATE assessment_scores 
            SET auditor_score = ?, auditor_comment = ?, auditor_id = ?, evaluated_at = NOW()
            WHERE assessment_id = ? AND indicator_id = ?
        ")->execute([round($avgScore, 2), $combinedComment, $assignedAuditors[0], $assessmentId, $indId]);
    }
    echo "      ├─ กรรมการประเมิน: " . ($NUM_AUDITORS * 60) . " คะแนน (4×60) ✓\n";
    
    // --- Recalculate total scores ---
    recalculateAssessmentScore($assessmentId);
    
    // Get updated scores
    $result = $pdo->prepare("SELECT self_total_score, auditor_total_score, final_score, hicm_level FROM assessments WHERE id = ?");
    $result->execute([$assessmentId]);
    $updated = $result->fetch();
    
    $level = $updated['hicm_level'];
    $stats["level{$level}"]++;
    
    $levelNames = [1 => 'Emerging', 2 => 'Developing', 3 => 'Performing', 4 => 'Excellence', 5 => 'World-Class'];
    echo "      └─ คะแนน: Self={$updated['self_total_score']}, Auditor={$updated['auditor_total_score']}, Final={$updated['final_score']} → Level {$level} ({$levelNames[$level]})\n";
}

// ======================================================================
// STEP 5: Update period status to show results
// ======================================================================
echo "\n" . str_repeat("─", 80) . "\n";
echo "\n[5] อัปเดตสถานะรอบการประเมิน...\n";

// Keep it 'open' so both company and auditor views work
// show_auditor_results already set to 1
$pdo->prepare("UPDATE assessment_periods SET status = 'evaluating', show_auditor_results = 1 WHERE id = ?")->execute([$periodId]);
echo "   ✅ status='evaluating', show_auditor_results=ON\n";

// ======================================================================
// Summary
// ======================================================================
echo "\n" . str_repeat("═", 80) . "\n";
echo "  ✅ สรุปผล Demo Data\n";
echo str_repeat("═", 80) . "\n";
echo "  รอบการประเมิน: {$PERIOD_NAME} (ID={$periodId})\n";
echo "  สถานะ: evaluating (show_auditor_results=ON)\n";
echo "  บริษัท: " . count($companies) . " แห่ง\n";
echo "  กรรมการต่อบริษัท: {$NUM_AUDITORS} คน\n";
echo "  ตัวชี้วัด: " . count($indicators) . " ข้อ\n\n";
echo "  การกระจายระดับ:\n";
echo "    Level 1 (Emerging):    {$stats['level1']} บริษัท\n";
echo "    Level 2 (Developing):  {$stats['level2']} บริษัท\n";
echo "    Level 3 (Performing):  {$stats['level3']} บริษัท\n";
echo "    Level 4 (Excellence):  {$stats['level4']} บริษัท\n";
echo "    Level 5 (World-Class): {$stats['level5']} บริษัท\n\n";

$totalScores = count($companies) * 60;
$totalAuditorScores = count($companies) * 60 * $NUM_AUDITORS;
echo "  จำนวนข้อมูลที่สร้าง:\n";
echo "    - assessment_scores:          {$totalScores} รายการ\n";
echo "    - assessment_evaluator_scores: {$totalAuditorScores} รายการ\n";
echo "    - assessment_evaluators:       " . (count($companies) * $NUM_AUDITORS) . " รายการ\n\n";

echo "  ทดสอบ Login:\n";
echo "    Admin:   admin1/123\n";
echo "    Auditor: aud1/123 ~ aud20/123\n";
echo "    Company: com1/123 ~ com23/123\n";
echo "    CEO:     ceo1/123\n\n";
echo str_repeat("═", 80) . "\n";
echo "  🎉 Demo data พร้อมใช้งาน!\n";
echo str_repeat("═", 80) . "\n";
