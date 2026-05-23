<?php
/**
 * HICM V2025 Assessment System - Auditor Assignment Management
 * หน้าจัดการจับคู่กรรมการประเมิน - รองรับ 1:หลาย และ Smart Match ตาม HICM Pillars
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();
requireRole(ROLE_ADMIN);

// ============================================
// Ensure assessment_evaluators table exists (must be before functions)
// ============================================
$db = getDB();
try {
    // Add missing columns if table exists
    $db->prepare("ALTER TABLE assessment_evaluators ADD COLUMN IF NOT EXISTS pillar_code VARCHAR(10) DEFAULT NULL")->execute();
    $db->prepare("ALTER TABLE assessment_evaluators ADD COLUMN IF NOT EXISTS assigned_by INT DEFAULT NULL")->execute();
} catch (Exception $e) {
    // Columns might already exist - ignore
}

// ============================================
// Helper Functions
// ============================================

function getCompaniesWithAssignments($periodId = null) {
    $db = getDB();
    
    $sql = "
        SELECT 
            c.id as company_id,
            c.company_name,
            c.industry_type,
            a.id as assessment_id,
            a.status as assessment_status,
            a.period_id,
            GROUP_CONCAT(DISTINCT ae.user_id) as auditor_ids,
            GROUP_CONCAT(DISTINCT u.name SEPARATOR '|||') as auditor_names,
            GROUP_CONCAT(DISTINCT u.hicm_expertise SEPARATOR '|||') as auditor_hicm
        FROM companies c
        LEFT JOIN assessments a ON c.id = a.company_id " . ($periodId ? "AND a.period_id = ?" : "") . "
        LEFT JOIN assessment_evaluators ae ON a.id = ae.assessment_id
        LEFT JOIN users u ON ae.user_id = u.id
        WHERE c.is_active = 1
        GROUP BY c.id, a.id
        ORDER BY c.company_name
    ";
    
    $stmt = $db->prepare($sql);
    if ($periodId) {
        $stmt->execute([$periodId]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

function getAuditorsWithDetails() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.expertise, u.hicm_expertise, u.organization_id,
               o.name as org_name, o.short_name as org_short,
               (SELECT COUNT(DISTINCT ae.assessment_id) FROM assessment_evaluators ae 
                JOIN assessments a ON ae.assessment_id = a.id 
                WHERE ae.user_id = u.id) as total_assignments
        FROM users u 
        LEFT JOIN organizations o ON u.organization_id = o.id
        WHERE u.role = 'auditor' AND u.is_active = 1 
        ORDER BY u.name
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAllPeriods() {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, year, status FROM assessment_periods ORDER BY year DESC, start_date DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * คำนวณคะแนน Smart Match (ใช้จาก includes/assessment.php ถ้ามี)
 */
if (!function_exists('calculateSmartMatchScore')) {
function calculateSmartMatchScore($companyIndustry, $auditorExpertise, $auditorHicm, $requiredPillars = []) {
    $industryScore = 0;
    $hicmScore = 0;
    $details = [];
    
    // 1. คำนวณคะแนนจากประเภทอุตสาหกรรม (40%)
    if (!empty($companyIndustry) && !empty($auditorExpertise)) {
        $companyTypes = array_map('trim', explode('|', $companyIndustry));
        $auditorTypes = array_map('trim', explode('|', $auditorExpertise));
        
        foreach ($companyTypes as $cType) {
            foreach ($auditorTypes as $aType) {
                // เปรียบเทียบแบบไม่สนใจวงเล็บ
                $cShort = trim(preg_replace('/\s*\([^)]+\)/', '', strtolower($cType)));
                $aShort = trim(preg_replace('/\s*\([^)]+\)/', '', strtolower($aType)));
                if (!empty($cShort) && !empty($aShort) && 
                    (strpos($aShort, $cShort) !== false || strpos($cShort, $aShort) !== false)) {
                    $industryScore = 100;
                    $details[] = "✓ ตรงสายงาน: {$cType}";
                    break 2;
                }
            }
        }
    }
    
    // 2. คำนวณคะแนนจาก HICM Pillars (60%)
    if (!empty($auditorHicm)) {
        $auditorPillars = array_filter(array_map('trim', explode('|', $auditorHicm)));
        
        if (empty($requiredPillars)) {
            // ถ้าไม่ได้ระบุ Pillars ที่ต้องการ ให้คะแนนตามจำนวน Pillars ที่กรรมการมี
            $hicmScore = min(100, count($auditorPillars) * 25);
            $details[] = "HICM: " . implode(', ', $auditorPillars);
        } else {
            // คำนวณว่ากรรมการมี Pillars ที่ต้องการกี่อัน
            $matchedPillars = array_intersect($auditorPillars, $requiredPillars);
            $hicmScore = count($requiredPillars) > 0 
                ? round((count($matchedPillars) / count($requiredPillars)) * 100) 
                : 0;
            if (!empty($matchedPillars)) {
                $details[] = "✓ HICM ตรง: " . implode(', ', $matchedPillars);
            }
        }
    }
    
    // คำนวณคะแนนรวม (Industry 40% + HICM 60%)
    $totalScore = ($industryScore * 0.4) + ($hicmScore * 0.6);
    
    return [
        'total' => round($totalScore),
        'industry' => $industryScore,
        'hicm' => $hicmScore,
        'details' => $details
    ];
}
}

/**
 * Smart Match: หาชุดกรรมการที่เหมาะสมที่สุดสำหรับบริษัท (Balanced Distribution)
 * 
 * อัลกอริทึม (ปรับปรุงใหม่ — เน้นกระจายงานให้สม่ำเสมอ):
 * 1. คำนวณ matchScore (Industry 40% + HICM 60%) 
 * 2. คำนวณ Composite Score ทุกรอบ: 
 *    - Pillar Value (0-40): คะแนนเฉพาะ pillar ที่ยังขาดในชุดปัจจุบัน
 *    - Match Quality (0-30): ความเหมาะสมด้านอุตสาหกรรม + HICM
 *    - Load Balance (0-30): กรรมการงานน้อยได้คะแนนสูง (exponential penalty)
 * 3. เลือกคนที่ Composite Score สูงสุดทีละคน จนครบจำนวน
 * 4. ไม่มีคนไหนถูกเลือกเพราะ pillar coverage อย่างเดียว — ต้องผ่านเกณฑ์ load ด้วย
 *
 * @param array $auditors รายชื่อกรรมการทั้งหมด
 * @param string $companyIndustry ประเภทอุตสาหกรรม
 * @param int $maxAuditors จำนวนกรรมการที่ต้องการ
 * @param array &$assignmentTracker ตัวนับงานแบบ runtime (สำหรับ batch matching)
 * @return array รายชื่อกรรมการที่แนะนำพร้อมคะแนน
 */
if (!function_exists('smartMatchAuditors')) {
function smartMatchAuditors($auditors, $companyIndustry, $maxAuditors = 4, &$assignmentTracker = null) {
    // คำนวณ effective assignments (รวม runtime tracker สำหรับ batch)
    foreach ($auditors as &$aud) {
        $runtimeExtra = ($assignmentTracker !== null) ? ($assignmentTracker[$aud['id']] ?? 0) : 0;
        $aud['effective_assignments'] = ($aud['total_assignments'] ?? 0) + $runtimeExtra;
    }
    unset($aud);
    
    // เตรียมข้อมูลพื้นฐาน: match score + pillars
    $candidates = [];
    foreach ($auditors as $aud) {
        $matchScore = calculateSmartMatchScore(
            $companyIndustry, 
            $aud['expertise'], 
            $aud['hicm_expertise']
        );
        
        $auditorPillars = array_filter(array_map('trim', explode('|', $aud['hicm_expertise'] ?? '')));
        
        $candidates[] = [
            'auditor' => $aud,
            'score' => $matchScore,
            'pillars' => $auditorPillars,
            'assignments' => $aud['effective_assignments']
        ];
    }
    
    // ===== Iterative Selection: เลือกทีละคนด้วย Composite Score =====
    $selected = [];
    $coveredPillars = [];
    $allPillars = ['H1', 'I2', 'C3', 'M4'];
    $usedIds = [];
    
    for ($round = 0; $round < $maxAuditors && $round < count($candidates); $round++) {
        // คำนวณ min/max assignments ใหม่ทุกรอบ (จาก candidates ที่ยังไม่ถูกเลือก)
        $availableAssignments = [];
        foreach ($candidates as $c) {
            if (!isset($usedIds[$c['auditor']['id']])) {
                $availableAssignments[] = $c['assignments'];
            }
        }
        if (empty($availableAssignments)) break;
        
        $minAssign = min($availableAssignments);
        $maxAssign = max($availableAssignments);
        $range = max(1, $maxAssign - $minAssign);
        
        // หา mean สำหรับ exponential penalty
        $meanAssign = array_sum($availableAssignments) / count($availableAssignments);
        
        $bestIdx = -1;
        $bestComposite = -999;
        
        foreach ($candidates as $idx => $item) {
            if (isset($usedIds[$item['auditor']['id']])) continue;
            
            // --- Component 1: Pillar Value (0-40 คะแนน) ---
            // ให้คะแนนเฉพาะ pillar ที่ยังขาดในชุดปัจจุบัน
            $uncoveredPillars = array_diff($allPillars, $coveredPillars);
            $newPillars = array_intersect($item['pillars'], $uncoveredPillars);
            $totalUncovered = count($uncoveredPillars);
            
            if ($totalUncovered > 0) {
                // คะแนน pillar = สัดส่วนที่ช่วยปิด gap * 40
                $pillarValue = round((count($newPillars) / $totalUncovered) * 40);
            } else {
                // ครบทุก pillar แล้ว — ไม่มีค่า pillar เพิ่ม
                $pillarValue = 0;
            }
            
            // --- Component 2: Match Quality (0-30 คะแนน) ---
            // scale จาก matchScore (0-100) → (0-30)
            $matchQuality = round($item['score']['total'] * 0.30);
            
            // --- Component 3: Load Balance (0-30 คะแนน) ---
            // ใช้ exponential penalty: คนที่มีงานเกิน mean จะถูกลดคะแนนแรงขึ้น
            $loadRatio = ($item['assignments'] - $minAssign) / $range;
            // exponential: loadRatio^1.5 ทำให้คนงานเยอะถูก penalty หนักขึ้นมาก
            $loadPenalty = pow($loadRatio, 1.5);
            $loadScore = round((1 - $loadPenalty) * 30);
            
            // --- Composite Score ---
            $composite = $pillarValue + $matchQuality + $loadScore;
            
            // เก็บคะแนนย่อยไว้แสดง
            $candidates[$idx]['_pillar_value'] = $pillarValue;
            $candidates[$idx]['_match_quality'] = $matchQuality;
            $candidates[$idx]['_load_score'] = $loadScore;
            $candidates[$idx]['_composite'] = $composite;
            
            if ($composite > $bestComposite) {
                $bestComposite = $composite;
                $bestIdx = $idx;
            }
        }
        
        if ($bestIdx === -1) break;
        
        // เลือกคนนี้
        $pick = $candidates[$bestIdx];
        $pick['load_bonus'] = $pick['_load_score'] ?? 0;
        $pick['balanced_score'] = $pick['_composite'] ?? 0;
        $selected[] = $pick;
        $usedIds[$pick['auditor']['id']] = true;
        $coveredPillars = array_unique(array_merge($coveredPillars, $pick['pillars']));
    }
    
    // อัปเดต runtime tracker
    if ($assignmentTracker !== null) {
        foreach ($selected as $item) {
            $assignmentTracker[$item['auditor']['id']] = ($assignmentTracker[$item['auditor']['id']] ?? 0) + 1;
        }
    }
    
    $finalCovered = array_intersect(array_unique($coveredPillars), $allPillars);
    
    return [
        'auditors' => $selected,
        'covered_pillars' => $finalCovered,
        'coverage' => round((count($finalCovered) / 4) * 100)
    ];
}
}

// ============================================
// Handle POST Actions
// ============================================

$errors = [];
$success = '';
$currentUserId = $_SESSION['user']['id'] ?? ($_SESSION['user_id'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // บันทึกการตั้งค่ารอบประเมิน
    if (isset($_POST['save_period_settings'])) {
        $periodId = intval($_POST['period_id']);
        $auditorsPerCompany = intval($_POST['auditors_per_company'] ?? 3);
        $autoSmartMatch = isset($_POST['auto_smart_match']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE assessment_periods SET auditors_per_company = ?, auto_smart_match = ? WHERE id = ?");
            $stmt->execute([$auditorsPerCompany, $autoSmartMatch, $periodId]);
            $success = "บันทึกการตั้งค่ารอบประเมินสำเร็จ";
            
            // ถ้าเปิด Auto Smart Match ให้ทำการจับคู่ทันที (Load Balanced)
            if ($autoSmartMatch) {
                $companies = getCompaniesWithAssignments($periodId);
                $auditors = getAuditorsWithDetails();
                $unassigned = array_filter($companies, fn($c) => empty($c['auditor_ids']));
                
                if (!empty($unassigned)) {
                    $count = 0;
                    $assignmentTracker = [];
                    
                    foreach ($unassigned as $comp) {
                        $match = smartMatchAuditors($auditors, $comp['industry_type'], $auditorsPerCompany, $assignmentTracker);
                        
                        if (!empty($match['auditors'])) {
                            $res = getOrCreateAssessment($comp['company_id'], $periodId);
                            if ($res['success']) {
                                $assessmentId = $res['assessment']['id'];
                                
                                $stmt = $db->prepare("DELETE FROM assessment_evaluators WHERE assessment_id = ?");
                                $stmt->execute([$assessmentId]);
                                
                                $stmt = $db->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id, assigned_by) VALUES (?, ?, ?)");
                                $firstAuditor = null;
                                foreach ($match['auditors'] as $item) {
                                    $stmt->execute([$assessmentId, $item['auditor']['id'], $currentUserId]);
                                    if (!$firstAuditor) $firstAuditor = $item['auditor']['id'];
                                }
                                
                                if ($firstAuditor) {
                                    $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
                                    $stmt->execute([$firstAuditor, $assessmentId]);
                                }
                                
                                $count++;
                            }
                        }
                    }
                    if ($count > 0) {
                        $success .= " และ Smart Match อัตโนมัติให้ {$count} บริษัท";
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
    
    // บันทึกการจับคู่กรรมการหลายคนให้บริษัท
    if (isset($_POST['save_assignment'])) {
        $companyId = intval($_POST['company_id']);
        $periodId = intval($_POST['period_id']);
        $auditorIds = $_POST['auditor_ids'] ?? [];
        
        try {
            // สร้างหรือดึง Assessment
            $res = getOrCreateAssessment($companyId, $periodId);
            if (!$res['success']) {
                throw new Exception($res['message']);
            }
            $assessmentId = $res['assessment']['id'];
            
            // ลบกรรมการเดิมทั้งหมด
            $stmt = $db->prepare("DELETE FROM assessment_evaluators WHERE assessment_id = ?");
            $stmt->execute([$assessmentId]);
            
            // เพิ่มกรรมการใหม่
            if (!empty($auditorIds)) {
                $stmt = $db->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id, assigned_by) VALUES (?, ?, ?)");
                foreach ($auditorIds as $auditorId) {
                    $stmt->execute([$assessmentId, intval($auditorId), $currentUserId]);
                }
                
                // อัปเดต evaluator_id หลักใน assessments (ใช้คนแรก)
                $mainEvaluator = reset($auditorIds);
                $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
                $stmt->execute([$mainEvaluator, $assessmentId]);
            }
            
            $success = "บันทึกการจับคู่สำเร็จ (" . count($auditorIds) . " กรรมการ)";
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
    
    // Smart Match ทั้งหมด (Load Balanced Batch)
    if (isset($_POST['smart_match_all'])) {
        $periodId = intval($_POST['period_id']);
        $maxAuditors = intval($_POST['max_auditors'] ?? 3);
        
        $companies = getCompaniesWithAssignments($periodId);
        $auditors = getAuditorsWithDetails();
        
        // กรองเฉพาะบริษัทที่ยังไม่มีกรรมการ
        $unassigned = array_filter($companies, fn($c) => empty($c['auditor_ids']));
        
        if (empty($unassigned)) {
            $success = 'ทุกบริษัทมีกรรมการครบแล้ว';
        } else {
            $count = 0;
            // Runtime assignment tracker สำหรับ load balancing ระหว่าง batch
            $assignmentTracker = [];
            
            foreach ($unassigned as $comp) {
                $match = smartMatchAuditors($auditors, $comp['industry_type'], $maxAuditors, $assignmentTracker);
                
                if (!empty($match['auditors'])) {
                    // สร้าง Assessment
                    $res = getOrCreateAssessment($comp['company_id'], $periodId);
                    if ($res['success']) {
                        $assessmentId = $res['assessment']['id'];
                        
                        // ลบกรรมการเดิม
                        $stmt = $db->prepare("DELETE FROM assessment_evaluators WHERE assessment_id = ?");
                        $stmt->execute([$assessmentId]);
                        
                        // เพิ่มกรรมการใหม่
                        $stmt = $db->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id, assigned_by) VALUES (?, ?, ?)");
                        $firstAuditor = null;
                        foreach ($match['auditors'] as $item) {
                            $stmt->execute([$assessmentId, $item['auditor']['id'], $currentUserId]);
                            if (!$firstAuditor) $firstAuditor = $item['auditor']['id'];
                        }
                        
                        // อัปเดต evaluator_id หลัก
                        if ($firstAuditor) {
                            $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
                            $stmt->execute([$firstAuditor, $assessmentId]);
                        }
                        
                        $count++;
                    }
                }
            }
            $success = "✅ Smart Match สำเร็จ! จับคู่ได้ {$count} บริษัท (Balanced Distribution)";
        }
    }
    
    // Smart Match ใหม่ทั้งหมด (ล้างของเดิม + จับคู่ใหม่)
    if (isset($_POST['smart_match_rematch'])) {
        $periodId = intval($_POST['period_id']);
        $maxAuditors = intval($_POST['max_auditors'] ?? 3);
        
        $companies = getCompaniesWithAssignments($periodId);
        $auditors = getAuditorsWithDetails();
        
        if (empty($companies)) {
            $errors[] = 'ไม่พบบริษัทในรอบประเมินนี้';
        } else {
            try {
                $db->beginTransaction();
                
                // ลบกรรมการเดิมของทุกบริษัทในรอบนี้
                $clearedCount = 0;
                foreach ($companies as $comp) {
                    if (!empty($comp['assessment_id'])) {
                        $stmt = $db->prepare("DELETE FROM assessment_evaluators WHERE assessment_id = ?");
                        $stmt->execute([$comp['assessment_id']]);
                        
                        $stmt = $db->prepare("UPDATE assessments SET evaluator_id = NULL WHERE id = ?");
                        $stmt->execute([$comp['assessment_id']]);
                        $clearedCount++;
                    }
                }
                
                // รีเซ็ต total_assignments ของ auditors ในหน่วยความจำ (เพราะลบไปแล้ว)
                // ดึงข้อมูลใหม่เพื่อให้ total_assignments ถูกต้อง
                $auditors = getAuditorsWithDetails();
                
                // จับคู่ใหม่ทุกบริษัท
                $count = 0;
                $assignmentTracker = [];
                
                foreach ($companies as $comp) {
                    $match = smartMatchAuditors($auditors, $comp['industry_type'], $maxAuditors, $assignmentTracker);
                    
                    if (!empty($match['auditors'])) {
                        $res = getOrCreateAssessment($comp['company_id'], $periodId);
                        if ($res['success']) {
                            $assessmentId = $res['assessment']['id'];
                            
                            $stmt = $db->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id, assigned_by) VALUES (?, ?, ?)");
                            $firstAuditor = null;
                            foreach ($match['auditors'] as $item) {
                                $stmt->execute([$assessmentId, $item['auditor']['id'], $currentUserId]);
                                if (!$firstAuditor) $firstAuditor = $item['auditor']['id'];
                            }
                            
                            if ($firstAuditor) {
                                $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
                                $stmt->execute([$firstAuditor, $assessmentId]);
                            }
                            
                            $count++;
                        }
                    }
                }
                
                $db->commit();
                $success = "✅ จับคู่ใหม่สำเร็จ! ล้าง {$clearedCount} รายการเดิม → จับคู่ใหม่ {$count} บริษัท (Balanced Distribution)";
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}

// ============================================
// Get Data
// ============================================

$periodId = $_GET['period'] ?? null;

if (!$periodId) {
    $stmt = $db->prepare("SELECT id FROM assessment_periods WHERE status IN ('open', 'evaluating') ORDER BY start_date DESC LIMIT 1");
    $stmt->execute();
    $period = $stmt->fetch();
    $periodId = $period['id'] ?? null;
}

$companies = getCompaniesWithAssignments($periodId);
$auditors = getAuditorsWithDetails();

// Get all periods with settings
$stmt = $db->prepare("SELECT id, name, year, status, auditors_per_company, auto_smart_match FROM assessment_periods ORDER BY year DESC, start_date DESC");
$stmt->execute();
$periods = $stmt->fetchAll();

// Stats
$totalCompanies = count($companies);
$assignedCount = count(array_filter($companies, fn($c) => !empty($c['auditor_ids'])));
$unassignedCount = $totalCompanies - $assignedCount;

// Count HICM coverage
$fullCoverageCount = 0;
foreach ($companies as $c) {
    if (!empty($c['auditor_hicm'])) {
        $allHicm = [];
        foreach (explode('|||', $c['auditor_hicm']) as $h) {
            $allHicm = array_merge($allHicm, explode('|', $h));
        }
        $uniquePillars = array_unique(array_filter($allHicm));
        if (count(array_intersect($uniquePillars, ['H1', 'I2', 'C3', 'M4'])) >= 4) {
            $fullCoverageCount++;
        }
    }
}

// Current Period Info with settings
$currentPeriod = null;
$auditorsPerCompany = 3;
$autoSmartMatch = false;
foreach ($periods as $p) {
    if ($p['id'] == $periodId) {
        $currentPeriod = $p;
        $auditorsPerCompany = $p['auditors_per_company'] ?? 3;
        $autoSmartMatch = !empty($p['auto_smart_match']);
        break;
    }
}

// Prepare auditors data for JS (with load balance info)
$totalAuditors = count($auditors);
$avgAssignment = $totalAuditors > 0 ? round(array_sum(array_column($auditors, 'total_assignments')) / $totalAuditors, 1) : 0;
$maxAssignment = $totalAuditors > 0 ? max(array_column($auditors, 'total_assignments')) : 0;
$minAssignment = $totalAuditors > 0 ? min(array_column($auditors, 'total_assignments')) : 0;

// Workload distribution for chart
$workloadDist = ['ว่าง (0)' => 0, 'น้อย (1-2)' => 0, 'ปานกลาง (3-4)' => 0, 'มาก (5+)' => 0];
foreach ($auditors as $a) {
    $cnt = $a['total_assignments'];
    if ($cnt == 0) $workloadDist['ว่าง (0)']++;
    elseif ($cnt <= 2) $workloadDist['น้อย (1-2)']++;
    elseif ($cnt <= 4) $workloadDist['ปานกลาง (3-4)']++;
    else $workloadDist['มาก (5+)']++;
}

$auditorsJson = json_encode(array_map(function($a) {
    return [
        'id' => $a['id'],
        'name' => $a['name'],
        'expertise' => $a['expertise'],
        'hicm_expertise' => $a['hicm_expertise'],
        'org_name' => $a['org_short'] ?? $a['org_name'],
        'total_assignments' => $a['total_assignments']
    ];
}, $auditors));
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จับคู่การประเมิน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .page-title { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        .page-subtitle { opacity: 0.9; font-size: 0.9rem; }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1024px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .stats-row { grid-template-columns: 1fr; } }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--gray-900); }
        .stat-label { font-size: 0.85rem; color: var(--gray-500); }
        
        .section-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .section-title i { color: var(--primary-500); }
        
        /* Company Cards */
        .company-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }
        
        @media (max-width: 500px) { .company-grid { grid-template-columns: 1fr; } }
        
        .company-card {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: 1.25rem;
            border: 2px solid var(--gray-200);
            transition: all 0.2s;
        }
        
        .company-card:hover {
            border-color: var(--primary-300);
            box-shadow: var(--shadow-md);
        }
        
        .company-card.has-auditors { border-color: #10B981; background: #F0FDF4; }
        .company-card.full-coverage { border-color: #8B5CF6; background: #F5F3FF; }
        
        .company-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .company-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        
        .company-industry {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        .coverage-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .coverage-badge.full { background: #8B5CF6; color: white; }
        .coverage-badge.partial { background: #F59E0B; color: white; }
        .coverage-badge.none { background: var(--gray-300); color: var(--gray-700); }
        
        /* Auditor Pills */
        .auditor-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .auditor-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            background: white;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-full);
            font-size: 0.8rem;
        }
        
        .auditor-pill .pillar-dots {
            display: flex;
            gap: 2px;
        }
        
        .pillar-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        .pillar-dot.H1 { background: #10B981; }
        .pillar-dot.I2 { background: #3B82F6; }
        .pillar-dot.C3 { background: #F59E0B; }
        .pillar-dot.M4 { background: #8B5CF6; }
        
        .no-auditor {
            color: var(--gray-400);
            font-size: 0.85rem;
            font-style: italic;
        }
        
        /* Buttons */
        .btn-edit {
            padding: 0.5rem 1rem;
            background: var(--primary-500);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .btn-edit:hover { background: var(--primary-600); }
        
        .btn-smart {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-smart:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        
        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title { font-size: 1.25rem; font-weight: 600; }
        
        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            background: var(--gray-100);
            border-radius: var(--radius-md);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
        }
        
        .modal-close:hover { background: var(--gray-200); }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Auditor Selection */
        .auditor-select-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .auditor-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .auditor-option:hover { border-color: var(--primary-300); background: var(--primary-50); }
        .auditor-option.selected { border-color: var(--primary-500); background: var(--primary-50); }
        
        .auditor-option input { display: none; }
        
        .auditor-checkbox {
            width: 24px;
            height: 24px;
            border: 2px solid var(--gray-400);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        
        .auditor-option.selected .auditor-checkbox {
            background: var(--primary-500);
            border-color: var(--primary-500);
            color: white;
        }
        
        .auditor-info { flex: 1; }
        .auditor-name { font-weight: 600; color: var(--gray-900); }
        .auditor-org { font-size: 0.8rem; color: var(--gray-500); }
        
        .auditor-pillars {
            display: flex;
            gap: 0.25rem;
        }
        
        .pillar-tag {
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
        }
        
        .pillar-tag.H1 { background: #10B981; }
        .pillar-tag.I2 { background: #3B82F6; }
        .pillar-tag.C3 { background: #F59E0B; }
        .pillar-tag.M4 { background: #8B5CF6; }
        
        .match-score {
            text-align: right;
        }
        
        .score-value {
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .score-value.high { color: #10B981; }
        .score-value.medium { color: #F59E0B; }
        .score-value.low { color: var(--gray-400); }
        
        .score-label { font-size: 0.7rem; color: var(--gray-500); }
        
        /* Coverage Indicator in Modal */
        .coverage-indicator {
            background: linear-gradient(135deg, #F5F3FF, #EDE9FE);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .coverage-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pillar-coverage {
            display: flex;
            gap: 0.5rem;
        }
        
        .pillar-slot {
            flex: 1;
            padding: 0.5rem;
            text-align: center;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .pillar-slot.covered { color: white; }
        .pillar-slot.H1.covered { background: #10B981; }
        .pillar-slot.I2.covered { background: #3B82F6; }
        .pillar-slot.C3.covered { background: #F59E0B; }
        .pillar-slot.M4.covered { background: #8B5CF6; }
        .pillar-slot.empty { background: var(--gray-200); color: var(--gray-500); }
        
        /* Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success { background: #D1FAE5; color: #047857; }
        .alert-error { background: #FEE2E2; color: #B91C1C; }
        
        /* Period Selector */
        .period-selector {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .period-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background: white;
        }
        
        /* Settings Card */
        .settings-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 100%);
            padding: 1rem 1.5rem;
            color: white;
        }
        
        .settings-title {
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .settings-body {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        
        .setting-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .setting-label {
            font-size: 0.9rem;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .setting-label small {
            display: block;
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        .setting-label i {
            color: var(--primary-500);
        }
        
        .setting-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background: white;
            min-width: 100px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--gray-300);
            transition: .3s;
            border-radius: 28px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: var(--shadow-sm);
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        
        .btn-save-settings {
            padding: 0.5rem 1.25rem;
            background: var(--primary-500);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-save-settings:hover {
            background: var(--primary-600);
            transform: translateY(-1px);
        }
        
        .auditor-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            color: white;
        }
        
        .auto-match-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #8B5CF6, #6D28D9);
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            color: white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Filter */
        .filter-bar {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-btn:hover { border-color: var(--primary-500); }
        .filter-btn.active { background: var(--primary-500); color: white; border-color: var(--primary-500); }
        
        .sort-btn { transition: all 0.2s; }
        .sort-btn:hover { border-color: var(--primary-400); }
        .sort-btn.active { background: var(--primary-500) !important; color: white !important; border-color: var(--primary-500) !important; }
        
        .btn-smart { transition: all 0.3s; }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-user-check"></i> จับคู่การประเมิน
                        </h1>
                        <p class="page-subtitle">จัดการกรรมการประเมินแบบ 1:หลาย พร้อม Smart Match ตาม HICM Pillars</p>
                    </div>
                    <div class="period-selector">
                        <label style="font-size: 0.9rem; opacity: 0.9;">รอบประเมิน:</label>
                        <select class="period-select" onchange="location.href='?period='+this.value">
                            <?php foreach ($periods as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $periodId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['year']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo implode('<br>', $errors); ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #2563EB);">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $totalCompanies; ?></div>
                        <div class="stat-label">บริษัททั้งหมด</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #10B981, #059669);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $assignedCount; ?></div>
                        <div class="stat-label">มีกรรมการแล้ว</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $unassignedCount; ?></div>
                        <div class="stat-label">รอจับคู่</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #8B5CF6, #7C3AED);">
                        <i class="fas fa-star"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $fullCoverageCount; ?></div>
                        <div class="stat-label">ครบ 4 Pillars</div>
                    </div>
                </div>
            </div>
            
            <!-- Period Settings Card -->
            <div class="settings-card">
                <form method="POST" class="settings-form">
                    <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
                    <input type="hidden" name="save_period_settings" value="1">
                    
                    <div class="settings-header">
                        <div class="settings-title">
                            <i class="fas fa-cog"></i>
                            ตั้งค่ารอบประเมิน: <?php echo htmlspecialchars($currentPeriod['name'] ?? ''); ?>
                        </div>
                    </div>
                    
                    <div class="settings-body">
                        <div class="setting-item">
                            <div class="setting-label">
                                <i class="fas fa-users"></i>
                                จำนวนกรรมการต่อบริษัท
                            </div>
                            <div class="setting-control">
                                <select name="auditors_per_company" class="setting-select">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $auditorsPerCompany == $i ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> คน
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <div class="setting-label">
                                <i class="fas fa-magic"></i>
                                Smart Match อัตโนมัติ
                                <small>จับคู่ให้ทันทีเมื่อมีบริษัทใหม่</small>
                            </div>
                            <div class="setting-control">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_smart_match" <?php echo $autoSmartMatch ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="setting-item">
                            <button type="submit" class="btn-save-settings">
                                <i class="fas fa-save"></i>
                                บันทึกการตั้งค่า
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Main Section -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i>
                        รายการบริษัท
                    </h2>
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <span class="auditor-count-badge">
                            <i class="fas fa-users"></i>
                            <?php echo $auditorsPerCompany; ?> คน/บริษัท
                        </span>
                        <?php if ($autoSmartMatch): ?>
                        <span class="auto-match-badge">
                            <i class="fas fa-bolt"></i>
                            Auto Match เปิด
                        </span>
                        <?php endif; ?>
                        <?php if ($unassignedCount > 0): ?>
                        <button type="button" class="btn-smart" onclick="openSmartMatchAllModal()">
                            <i class="fas fa-magic"></i>
                            Smart Match ทั้งหมด
                            <span style="background: rgba(255,255,255,0.25); padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem;"><?php echo $unassignedCount; ?> บริษัท</span>
                        </button>
                        <?php else: ?>
                        <span class="btn-smart" style="opacity: 0.5; cursor: default;">
                            <i class="fas fa-check-circle"></i>
                            จับคู่ครบแล้ว
                        </span>
                        <?php endif; ?>
                        <?php if ($assignedCount > 0): ?>
                        <button type="button" class="btn-smart" onclick="openRematchModal()" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                            <i class="fas fa-sync-alt"></i>
                            จับคู่ใหม่
                            <span style="background: rgba(255,255,255,0.25); padding: 0.15rem 0.5rem; border-radius: var(--radius-full); font-size: 0.75rem;"><?php echo $totalCompanies; ?> บริษัท</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="filter-bar">
                    <button class="filter-btn active" onclick="filterCompanies('all')">ทั้งหมด (<?php echo $totalCompanies; ?>)</button>
                    <button class="filter-btn" onclick="filterCompanies('assigned')">มีกรรมการ (<?php echo $assignedCount; ?>)</button>
                    <button class="filter-btn" onclick="filterCompanies('unassigned')">รอจับคู่ (<?php echo $unassignedCount; ?>)</button>
                    <button class="filter-btn" onclick="filterCompanies('full')">ครบ Pillars (<?php echo $fullCoverageCount; ?>)</button>
                </div>
                
                <div class="company-grid">
                    <?php foreach ($companies as $company): 
                        $auditorIds = array_filter(explode(',', $company['auditor_ids'] ?? ''));
                        $auditorNames = array_filter(explode('|||', $company['auditor_names'] ?? ''));
                        $auditorHicms = array_filter(explode('|||', $company['auditor_hicm'] ?? ''));
                        
                        // Calculate coverage
                        $allPillars = [];
                        foreach ($auditorHicms as $h) {
                            $allPillars = array_merge($allPillars, explode('|', $h));
                        }
                        $uniquePillars = array_unique(array_filter($allPillars));
                        $coveredPillars = array_intersect($uniquePillars, ['H1', 'I2', 'C3', 'M4']);
                        $coveragePercent = round((count($coveredPillars) / 4) * 100);
                        
                        $cardClass = '';
                        if (count($coveredPillars) >= 4) $cardClass = 'full-coverage';
                        elseif (!empty($auditorIds)) $cardClass = 'has-auditors';
                    ?>
                    <div class="company-card <?php echo $cardClass; ?>" 
                         data-status="<?php echo empty($auditorIds) ? 'unassigned' : (count($coveredPillars) >= 4 ? 'full' : 'assigned'); ?>">
                        <div class="company-header">
                            <div>
                                <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
                                <div class="company-industry"><?php echo htmlspecialchars($company['industry_type'] ?? '-'); ?></div>
                            </div>
                            <div class="coverage-badge <?php echo count($coveredPillars) >= 4 ? 'full' : (count($coveredPillars) > 0 ? 'partial' : 'none'); ?>">
                                <i class="fas fa-<?php echo count($coveredPillars) >= 4 ? 'check' : 'circle'; ?>"></i>
                                <?php echo $coveragePercent; ?>%
                            </div>
                        </div>
                        
                        <div class="auditor-list">
                            <?php if (!empty($auditorNames)): ?>
                                <?php foreach ($auditorNames as $idx => $name): 
                                    $pillars = isset($auditorHicms[$idx]) ? explode('|', $auditorHicms[$idx]) : [];
                                ?>
                                <span class="auditor-pill">
                                    <span class="pillar-dots">
                                        <?php foreach (['H1', 'I2', 'C3', 'M4'] as $p): ?>
                                            <?php if (in_array($p, $pillars)): ?>
                                            <span class="pillar-dot <?php echo $p; ?>"></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </span>
                                    <?php echo htmlspecialchars($name); ?>
                                </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="no-auditor">ยังไม่มีกรรมการ</span>
                            <?php endif; ?>
                        </div>
                        
                        <button class="btn-edit" onclick="openAssignModal(<?php echo $company['company_id']; ?>, '<?php echo addslashes($company['company_name']); ?>', '<?php echo addslashes($company['industry_type'] ?? ''); ?>', [<?php echo implode(',', $auditorIds); ?>])">
                            <i class="fas fa-edit"></i>
                            จัดการกรรมการ
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Smart Match All Confirmation Modal -->
    <div class="modal-overlay" id="smartMatchAllModal">
        <div class="modal-content" style="max-width: 560px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%); color: white; border: none;">
                <h3 class="modal-title" style="color: white;">
                    <i class="fas fa-magic"></i>
                    Smart Match ทั้งหมด
                </h3>
                <button class="modal-close" onclick="closeSmartMatchAllModal()" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #EDE9FE, #DDD6FE); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: #7C3AED;">
                        <i class="fas fa-wand-magic-sparkles"></i>
                    </div>
                    <h3 style="margin: 0 0 0.5rem; color: var(--gray-900);">จับคู่กรรมการอัจฉริยะ</h3>
                    <p style="margin: 0; color: var(--gray-500); font-size: 0.9rem;">ระบบจะจับคู่อัตโนมัติโดยพิจารณาจาก</p>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
                    <div style="padding: 0.75rem; background: #F0FDF4; border-radius: var(--radius-md); text-align: center;">
                        <i class="fas fa-industry" style="color: #10B981; font-size: 1.25rem;"></i>
                        <div style="font-size: 0.8rem; margin-top: 0.25rem; font-weight: 500;">สายงานตรงกัน</div>
                        <div style="font-size: 0.7rem; color: var(--gray-500);">น้ำหนัก 40%</div>
                    </div>
                    <div style="padding: 0.75rem; background: #EFF6FF; border-radius: var(--radius-md); text-align: center;">
                        <i class="fas fa-puzzle-piece" style="color: #3B82F6; font-size: 1.25rem;"></i>
                        <div style="font-size: 0.8rem; margin-top: 0.25rem; font-weight: 500;">HICM Pillars</div>
                        <div style="font-size: 0.7rem; color: var(--gray-500);">น้ำหนัก 60%</div>
                    </div>
                    <div style="padding: 0.75rem; background: #FEF3C7; border-radius: var(--radius-md); text-align: center;">
                        <i class="fas fa-balance-scale" style="color: #D97706; font-size: 1.25rem;"></i>
                        <div style="font-size: 0.8rem; margin-top: 0.25rem; font-weight: 500;">Load Balance</div>
                        <div style="font-size: 0.7rem; color: var(--gray-500);">กระจายงานสม่ำเสมอ</div>
                    </div>
                    <div style="padding: 0.75rem; background: #F5F3FF; border-radius: var(--radius-md); text-align: center;">
                        <i class="fas fa-shield-check" style="color: #8B5CF6; font-size: 1.25rem;"></i>
                        <div style="font-size: 0.8rem; margin-top: 0.25rem; font-weight: 500;">ครอบคลุม 4 Pillars</div>
                        <div style="font-size: 0.7rem; color: var(--gray-500);">H1 I2 C3 M4</div>
                    </div>
                </div>
                
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.85rem; color: var(--gray-600);"><i class="fas fa-building" style="width: 20px;"></i> บริษัทที่จะจับคู่</span>
                        <span style="font-weight: 700; color: var(--primary-600);"><?php echo $unassignedCount; ?> บริษัท</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.85rem; color: var(--gray-600);"><i class="fas fa-users" style="width: 20px;"></i> กรรมการทั้งหมด</span>
                        <span style="font-weight: 700; color: var(--gray-900);"><?php echo $totalAuditors; ?> คน</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--gray-600);"><i class="fas fa-user-check" style="width: 20px;"></i> กรรมการต่อบริษัท</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <select id="smartMatchCount" style="padding: 0.375rem 0.75rem; border: 2px solid var(--primary-300); border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 600; color: var(--primary-600); background: white; min-width: 80px;">
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $auditorsPerCompany == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> คน
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Workload Preview -->
                <div style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 0.5rem;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: #92400E; margin-bottom: 0.5rem;">
                        <i class="fas fa-chart-bar"></i> ภาระงานกรรมการปัจจุบัน
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach ($workloadDist as $label => $cnt): ?>
                        <span style="font-size: 0.75rem; padding: 0.25rem 0.5rem; background: rgba(255,255,255,0.7); border-radius: var(--radius-sm); color: #78350F;">
                            <?php echo $label; ?>: <strong><?php echo $cnt; ?></strong>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #92400E; margin-top: 0.5rem;">
                        เฉลี่ย <?php echo $avgAssignment; ?> งาน/คน • ต่ำสุด <?php echo $minAssignment; ?> • สูงสุด <?php echo $maxAssignment; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeSmartMatchAllModal()">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="submitSmartMatchAll()" style="background: linear-gradient(135deg, #8B5CF6, #6D28D9); border: none; padding: 0.625rem 1.5rem;">
                    <i class="fas fa-magic"></i>
                    เริ่ม Smart Match
                </button>
            </div>
        </div>
    </div>
    
    <!-- Rematch Confirmation Modal -->
    <div class="modal-overlay" id="rematchModal">
        <div class="modal-content" style="max-width: 520px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%); color: white; border: none;">
                <h3 class="modal-title" style="color: white;">
                    <i class="fas fa-sync-alt"></i>
                    จับคู่ใหม่ทั้งหมด
                </h3>
                <button class="modal-close" onclick="closeRematchModal()" style="background: rgba(255,255,255,0.2); color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="width: 72px; height: 72px; background: linear-gradient(135deg, #FEF3C7, #FDE68A); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; color: #D97706;">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3 style="margin: 0 0 0.5rem; color: var(--gray-900);">ล้างและจับคู่ใหม่ทั้งหมด</h3>
                    <p style="margin: 0; color: var(--gray-500); font-size: 0.9rem;">ระบบจะลบการจับคู่เดิมทั้งหมด แล้วจับคู่ใหม่อัตโนมัติ</p>
                </div>
                
                <!-- Warning -->
                <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: flex-start; gap: 0.75rem;">
                        <i class="fas fa-exclamation-triangle" style="color: #DC2626; font-size: 1.25rem; margin-top: 0.1rem;"></i>
                        <div>
                            <div style="font-weight: 600; color: #991B1B; font-size: 0.9rem; margin-bottom: 0.25rem;">คำเตือน</div>
                            <div style="font-size: 0.8rem; color: #7F1D1D; line-height: 1.5;">
                                การจับคู่เดิม <strong><?php echo $assignedCount; ?> บริษัท</strong> จะถูกล้างทั้งหมด<br>
                                แล้วจับคู่ใหม่ให้ <strong><?php echo $totalCompanies; ?> บริษัท</strong> ด้วย Smart Match
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="background: var(--gray-50); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.85rem; color: var(--gray-600);"><i class="fas fa-building" style="width: 20px;"></i> บริษัททั้งหมด</span>
                        <span style="font-weight: 700; color: #D97706;"><?php echo $totalCompanies; ?> บริษัท</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                        <span style="font-size: 0.85rem; color: var(--gray-600);"><i class="fas fa-users" style="width: 20px;"></i> กรรมการทั้งหมด</span>
                        <span style="font-weight: 700; color: var(--gray-900);"><?php echo $totalAuditors; ?> คน</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--gray-600);"><i class="fas fa-user-check" style="width: 20px;"></i> กรรมการต่อบริษัท</span>
                        <select id="rematchCount" style="padding: 0.375rem 0.75rem; border: 2px solid #F59E0B; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 600; color: #D97706; background: white; min-width: 80px;">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $auditorsPerCompany == $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> คน
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); border-radius: var(--radius-lg); padding: 1rem;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: #065F46; margin-bottom: 0.5rem;">
                        <i class="fas fa-sparkles"></i> ข้อดีของการจับคู่ใหม่
                    </div>
                    <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8rem; color: #047857; line-height: 1.8;">
                        <li>กระจายงานกรรมการให้สม่ำเสมอขึ้น</li>
                        <li>ลด bias จากการจับคู่ทีละบริษัท</li>
                        <li>ใช้อัลกอริทึม Balanced Distribution ล่าสุด</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeRematchModal()">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="submitRematch()" style="background: linear-gradient(135deg, #F59E0B, #D97706); border: none; padding: 0.625rem 1.5rem;">
                    <i class="fas fa-sync-alt"></i>
                    ยืนยันจับคู่ใหม่
                </button>
            </div>
        </div>
    </div>
    
    <!-- Assignment Modal -->
    <div class="modal-overlay" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus" style="color: var(--primary-500);"></i>
                    <span id="modalCompanyName">จัดการกรรมการ</span>
                </h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalIndustry" style="margin-bottom: 1rem; padding: 0.75rem; background: var(--gray-100); border-radius: var(--radius-md); font-size: 0.85rem;">
                    <strong>ประเภทอุตสาหกรรม:</strong> <span></span>
                </div>
                
                <div class="coverage-indicator">
                    <div class="coverage-title">
                        <i class="fas fa-chart-pie"></i>
                        HICM Pillars Coverage
                        <span id="coveragePercentText" style="margin-left: auto; font-size: 0.85rem; color: var(--gray-500);">0%</span>
                    </div>
                    <div class="pillar-coverage" id="pillarCoverage">
                        <div class="pillar-slot H1 empty" id="slot-H1">H1</div>
                        <div class="pillar-slot I2 empty" id="slot-I2">I2</div>
                        <div class="pillar-slot C3 empty" id="slot-C3">C3</div>
                        <div class="pillar-slot M4 empty" id="slot-M4">M4</div>
                    </div>
                </div>
                
                <!-- Smart Match Button with count control -->
                <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem; align-items: stretch;">
                    <button type="button" class="btn-smart" onclick="autoSelectBest()" style="flex: 1;">
                        <i class="fas fa-magic"></i>
                        Smart Match
                        <span style="opacity: 0.8; font-size: 0.8rem;">— เลือกกรรมการที่เหมาะสมที่สุด</span>
                    </button>
                    <div style="display: flex; align-items: center; gap: 0.5rem; background: var(--gray-100); border-radius: var(--radius-lg); padding: 0.5rem 0.75rem;">
                        <label style="font-size: 0.75rem; color: var(--gray-600); white-space: nowrap;">จำนวน:</label>
                        <select id="modalMaxAuditors" style="padding: 0.25rem 0.5rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.85rem; font-weight: 600; background: white; min-width: 50px;">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $auditorsPerCompany == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <div style="font-weight: 600; color: var(--gray-700);">
                        เลือกกรรมการ <span id="selectedCount" style="color: var(--primary-500);">(0)</span>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="button" onclick="sortAuditors('score')" class="sort-btn active" data-sort="score" style="padding: 0.25rem 0.5rem; border: 1px solid var(--gray-300); background: white; border-radius: var(--radius-sm); font-size: 0.75rem; cursor: pointer;">
                            <i class="fas fa-trophy"></i> คะแนน
                        </button>
                        <button type="button" onclick="sortAuditors('load')" class="sort-btn" data-sort="load" style="padding: 0.25rem 0.5rem; border: 1px solid var(--gray-300); background: white; border-radius: var(--radius-sm); font-size: 0.75rem; cursor: pointer;">
                            <i class="fas fa-balance-scale"></i> ภาระงาน
                        </button>
                        <button type="button" onclick="sortAuditors('name')" class="sort-btn" data-sort="name" style="padding: 0.25rem 0.5rem; border: 1px solid var(--gray-300); background: white; border-radius: var(--radius-sm); font-size: 0.75rem; cursor: pointer;">
                            <i class="fas fa-font"></i> ชื่อ
                        </button>
                    </div>
                </div>
                
                <div class="auditor-select-grid" id="auditorList">
                    <!-- Populated by JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">ยกเลิก</button>
                <button type="button" class="btn btn-primary" onclick="saveAssignment()">
                    <i class="fas fa-save"></i>
                    บันทึก
                </button>
            </div>
        </div>
    </div>
    
    <form id="saveForm" method="POST" style="display: none;">
        <input type="hidden" name="save_assignment" value="1">
        <input type="hidden" name="company_id" id="formCompanyId">
        <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
        <input type="hidden" name="auditor_ids[]" id="formAuditorIds">
    </form>
    
    <form id="smartMatchAllForm" method="POST" style="display: none;">
        <input type="hidden" name="smart_match_all" value="1">
        <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
        <input type="hidden" name="max_auditors" id="smartMatchAllMaxAuditors" value="<?php echo $auditorsPerCompany; ?>">
    </form>
    
    <form id="rematchForm" method="POST" style="display: none;">
        <input type="hidden" name="smart_match_rematch" value="1">
        <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
        <input type="hidden" name="max_auditors" id="rematchMaxAuditors" value="<?php echo $auditorsPerCompany; ?>">
    </form>
    
    <script>
        const auditors = <?php echo $auditorsJson; ?>;
        const pillars = ['H1', 'I2', 'C3', 'M4'];
        const auditorsPerCompany = <?php echo $auditorsPerCompany; ?>;
        let currentCompanyId = null;
        let currentIndustry = '';
        let selectedAuditors = [];
        let currentSort = 'score';
        
        // ============================================
        // Smart Match All Modal
        // ============================================
        function openSmartMatchAllModal() {
            document.getElementById('smartMatchAllModal').classList.add('active');
        }
        
        function closeSmartMatchAllModal() {
            document.getElementById('smartMatchAllModal').classList.remove('active');
        }
        
        function submitSmartMatchAll() {
            const count = document.getElementById('smartMatchCount').value;
            document.getElementById('smartMatchAllMaxAuditors').value = count;
            document.getElementById('smartMatchAllForm').submit();
        }
        
        // Close Smart Match All modal on overlay click
        document.getElementById('smartMatchAllModal').addEventListener('click', function(e) {
            if (e.target === this) closeSmartMatchAllModal();
        });
        
        // ============================================
        // Rematch Modal
        // ============================================
        function openRematchModal() {
            document.getElementById('rematchModal').classList.add('active');
        }
        
        function closeRematchModal() {
            document.getElementById('rematchModal').classList.remove('active');
        }
        
        function submitRematch() {
            const count = document.getElementById('rematchCount').value;
            document.getElementById('rematchMaxAuditors').value = count;
            document.getElementById('rematchForm').submit();
        }
        
        // Close Rematch modal on overlay click
        document.getElementById('rematchModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeRematchModal();
        });
        
        // ============================================
        // Filter Companies
        // ============================================
        function filterCompanies(filter) {
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            document.querySelectorAll('.company-card').forEach(card => {
                const status = card.dataset.status;
                if (filter === 'all') {
                    card.style.display = 'block';
                } else if (filter === 'unassigned') {
                    card.style.display = status === 'unassigned' ? 'block' : 'none';
                } else if (filter === 'assigned') {
                    card.style.display = (status === 'assigned' || status === 'full') ? 'block' : 'none';
                } else if (filter === 'full') {
                    card.style.display = status === 'full' ? 'block' : 'none';
                }
            });
        }
        
        // ============================================
        // Assignment Modal
        // ============================================
        function openAssignModal(companyId, companyName, industry, currentIds) {
            currentCompanyId = companyId;
            currentIndustry = industry;
            selectedAuditors = currentIds.filter(id => id);
            
            document.getElementById('modalCompanyName').textContent = companyName;
            document.querySelector('#modalIndustry span').textContent = industry || '-';
            
            renderAuditorList();
            updateCoverage();
            
            document.getElementById('assignModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('assignModal').classList.remove('active');
        }
        
        // ============================================
        // Scoring (with Load Balance)
        // ============================================
        function getLoadBalanceInfo() {
            const assignments = auditors.map(a => a.total_assignments || 0);
            const min = Math.min(...assignments);
            const max = Math.max(...assignments);
            const range = Math.max(1, max - min);
            return { min, max, range };
        }
        
        function calculateScore(auditor) {
            let industryScore = 0;
            let hicmScore = 0;
            
            // Industry matching
            if (currentIndustry && auditor.expertise) {
                const companyTypes = currentIndustry.toLowerCase().split('|').map(s => s.trim());
                const auditorTypes = auditor.expertise.toLowerCase().split('|').map(s => s.trim());
                
                for (const cType of companyTypes) {
                    for (const aType of auditorTypes) {
                        const cShort = cType.replace(/\s*\([^)]+\)/g, '').trim();
                        const aShort = aType.replace(/\s*\([^)]+\)/g, '').trim();
                        if (cShort && aShort && (aShort.includes(cShort) || cShort.includes(aShort))) {
                            industryScore = 100;
                            break;
                        }
                    }
                    if (industryScore > 0) break;
                }
            }
            
            // HICM coverage
            if (auditor.hicm_expertise) {
                const auditorPillars = auditor.hicm_expertise.split('|').filter(p => p);
                hicmScore = Math.min(100, auditorPillars.length * 25);
            }
            
            // Match Quality (0-30): scale from matchScore (0-100)
            const matchScore = Math.round(industryScore * 0.4 + hicmScore * 0.6);
            const matchQuality = Math.round(matchScore * 0.30);
            
            // Load Balance (0-30): exponential penalty for high workload
            const lb = getLoadBalanceInfo();
            const loadRatio = ((auditor.total_assignments || 0) - lb.min) / lb.range;
            const loadPenalty = Math.pow(loadRatio, 1.5);
            const loadBonus = Math.round((1 - loadPenalty) * 30);
            
            // Balanced score = matchQuality + loadBonus (without pillar value — that's context-dependent)
            const balancedScore = matchQuality + loadBonus;
            
            return {
                total: matchScore,
                balanced: balancedScore,
                industry: industryScore,
                hicm: hicmScore,
                loadBonus: loadBonus
            };
        }
        
        // ============================================
        // Sort
        // ============================================
        function sortAuditors(sortBy) {
            currentSort = sortBy;
            document.querySelectorAll('.sort-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.sort === sortBy);
                btn.style.background = btn.dataset.sort === sortBy ? 'var(--primary-500)' : 'white';
                btn.style.color = btn.dataset.sort === sortBy ? 'white' : 'inherit';
                btn.style.borderColor = btn.dataset.sort === sortBy ? 'var(--primary-500)' : 'var(--gray-300)';
            });
            renderAuditorList();
        }
        
        // ============================================
        // Render Auditor List
        // ============================================
        function renderAuditorList() {
            const container = document.getElementById('auditorList');
            
            const scored = auditors.map(a => ({
                auditor: a,
                score: calculateScore(a)
            }));
            
            // Sort based on current mode
            if (currentSort === 'score') {
                scored.sort((a, b) => b.score.balanced - a.score.balanced);
            } else if (currentSort === 'load') {
                scored.sort((a, b) => (a.auditor.total_assignments || 0) - (b.auditor.total_assignments || 0));
            } else if (currentSort === 'name') {
                scored.sort((a, b) => a.auditor.name.localeCompare(b.auditor.name, 'th'));
            }
            
            const lb = getLoadBalanceInfo();
            
            container.innerHTML = scored.map(item => {
                const a = item.auditor;
                const score = item.score;
                const isSelected = selectedAuditors.includes(a.id);
                const pillarsArr = (a.hicm_expertise || '').split('|').filter(p => p);
                const assignments = a.total_assignments || 0;
                
                let scoreClass = 'low';
                if (score.balanced >= 70) scoreClass = 'high';
                else if (score.balanced >= 40) scoreClass = 'medium';
                
                // Workload bar (visual indicator)
                const loadPercent = lb.max > 0 ? Math.round((assignments / lb.max) * 100) : 0;
                let loadColor = '#10B981'; // green = low load
                if (loadPercent > 66) loadColor = '#EF4444'; // red = high load
                else if (loadPercent > 33) loadColor = '#F59E0B'; // yellow = medium
                
                return `
                    <label class="auditor-option ${isSelected ? 'selected' : ''}">
                        <input type="checkbox" value="${a.id}" ${isSelected ? 'checked' : ''} onchange="toggleAuditor(${a.id}, event)">
                        <div class="auditor-checkbox">
                            ${isSelected ? '<i class="fas fa-check"></i>' : ''}
                        </div>
                        <div class="auditor-info">
                            <div class="auditor-name">${a.name}</div>
                            <div class="auditor-org">${a.org_name || '-'}</div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                <div style="flex: 1; max-width: 80px; height: 4px; background: var(--gray-200); border-radius: 2px; overflow: hidden;">
                                    <div style="width: ${loadPercent}%; height: 100%; background: ${loadColor}; border-radius: 2px;"></div>
                                </div>
                                <span style="font-size: 0.7rem; color: var(--gray-500);">${assignments} งาน</span>
                                ${score.loadBonus > 0 ? `<span style="font-size: 0.65rem; color: #10B981; font-weight: 600;">+${score.loadBonus}</span>` : ''}
                            </div>
                        </div>
                        <div class="auditor-pillars">
                            ${pillarsArr.map(p => `<span class="pillar-tag ${p}">${p}</span>`).join('')}
                        </div>
                        <div class="match-score">
                            <div class="score-value ${scoreClass}">${score.balanced}%</div>
                            <div class="score-label">คะแนน</div>
                        </div>
                    </label>
                `;
            }).join('');
            
            // Update selected count
            document.getElementById('selectedCount').textContent = `(${selectedAuditors.length})`;
        }
        
        // ============================================
        // Toggle Auditor Selection
        // ============================================
        function toggleAuditor(auditorId, event) {
            if (event) event.stopPropagation();
            const idx = selectedAuditors.indexOf(auditorId);
            if (idx === -1) {
                selectedAuditors.push(auditorId);
            } else {
                selectedAuditors.splice(idx, 1);
            }
            renderAuditorList();
            updateCoverage();
        }
        
        // ============================================
        // Update Coverage Indicator
        // ============================================
        function updateCoverage() {
            const coveredPillars = new Set();
            
            selectedAuditors.forEach(id => {
                const auditor = auditors.find(a => a.id === id);
                if (auditor && auditor.hicm_expertise) {
                    auditor.hicm_expertise.split('|').filter(p => p).forEach(p => coveredPillars.add(p));
                }
            });
            
            let coveredCount = 0;
            pillars.forEach(p => {
                const slot = document.getElementById('slot-' + p);
                if (coveredPillars.has(p)) {
                    slot.classList.remove('empty');
                    slot.classList.add('covered');
                    coveredCount++;
                } else {
                    slot.classList.remove('covered');
                    slot.classList.add('empty');
                }
            });
            
            const pct = Math.round((coveredCount / 4) * 100);
            document.getElementById('coveragePercentText').textContent = pct + '%';
            document.getElementById('coveragePercentText').style.color = 
                pct >= 100 ? '#10B981' : (pct >= 50 ? '#F59E0B' : 'var(--gray-500)');
        }
        
        // ============================================
        // Smart Match: Auto Select Best (Load Balanced)
        // ============================================
        function autoSelectBest() {
            selectedAuditors = [];
            
            const maxAuditors = parseInt(document.getElementById('modalMaxAuditors').value) || auditorsPerCompany;
            const coveredPillars = new Set();
            const allPillars = ['H1', 'I2', 'C3', 'M4'];
            
            // Prepare candidates with base scores
            const candidates = auditors.map(a => {
                const auditorPillars = (a.hicm_expertise || '').split('|').filter(p => p);
                
                // Match score (Industry 40% + HICM 60%)
                let industryScore = 0;
                if (currentIndustry && a.expertise) {
                    const companyTypes = currentIndustry.toLowerCase().split('|').map(s => s.trim());
                    const auditorTypes = a.expertise.toLowerCase().split('|').map(s => s.trim());
                    outer: for (const cType of companyTypes) {
                        for (const aType of auditorTypes) {
                            const cShort = cType.replace(/\s*\([^)]+\)/g, '').trim();
                            const aShort = aType.replace(/\s*\([^)]+\)/g, '').trim();
                            if (cShort && aShort && (aShort.includes(cShort) || cShort.includes(aShort))) {
                                industryScore = 100;
                                break outer;
                            }
                        }
                    }
                }
                let hicmScore = 0;
                if (a.hicm_expertise) {
                    hicmScore = Math.min(100, auditorPillars.length * 25);
                }
                const matchTotal = Math.round(industryScore * 0.4 + hicmScore * 0.6);
                
                return {
                    auditor: a,
                    pillars: auditorPillars,
                    assignments: a.total_assignments || 0,
                    matchTotal: matchTotal
                };
            });
            
            const usedIds = new Set();
            
            // Iterative selection: pick best composite score each round
            for (let round = 0; round < maxAuditors && usedIds.size < candidates.length; round++) {
                // Recalculate min/max for available candidates
                const available = candidates.filter(c => !usedIds.has(c.auditor.id));
                if (available.length === 0) break;
                
                const assignments = available.map(c => c.assignments);
                const minAssign = Math.min(...assignments);
                const maxAssign = Math.max(...assignments);
                const range = Math.max(1, maxAssign - minAssign);
                
                let bestIdx = -1;
                let bestComposite = -999;
                
                for (let i = 0; i < candidates.length; i++) {
                    const item = candidates[i];
                    if (usedIds.has(item.auditor.id)) continue;
                    
                    // Component 1: Pillar Value (0-40)
                    const uncovered = allPillars.filter(p => !coveredPillars.has(p));
                    const newPillars = item.pillars.filter(p => uncovered.includes(p));
                    const pillarValue = uncovered.length > 0 
                        ? Math.round((newPillars.length / uncovered.length) * 40) 
                        : 0;
                    
                    // Component 2: Match Quality (0-30)
                    const matchQuality = Math.round(item.matchTotal * 0.30);
                    
                    // Component 3: Load Balance (0-30) with exponential penalty
                    const loadRatio = (item.assignments - minAssign) / range;
                    const loadPenalty = Math.pow(loadRatio, 1.5);
                    const loadScore = Math.round((1 - loadPenalty) * 30);
                    
                    const composite = pillarValue + matchQuality + loadScore;
                    
                    if (composite > bestComposite) {
                        bestComposite = composite;
                        bestIdx = i;
                    }
                }
                
                if (bestIdx === -1) break;
                
                const chosen = candidates[bestIdx];
                selectedAuditors.push(chosen.auditor.id);
                usedIds.add(chosen.auditor.id);
                chosen.pillars.forEach(p => coveredPillars.add(p));
            }
            
            renderAuditorList();
            updateCoverage();
        }
        
        // ============================================
        // Save Assignment
        // ============================================
        function saveAssignment() {
            const form = document.getElementById('saveForm');
            document.getElementById('formCompanyId').value = currentCompanyId;
            
            // Remove old auditor inputs
            form.querySelectorAll('input[name="auditor_ids[]"]').forEach(el => el.remove());
            
            // Add selected auditors
            selectedAuditors.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'auditor_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            form.submit();
        }
        
        // Close modal on overlay click
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
