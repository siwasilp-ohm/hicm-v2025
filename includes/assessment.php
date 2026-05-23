<?php
/**
 * HICM V2025 Assessment System - Assessment Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Get all pillars with indicators
 */
function getPillarsWithIndicators() {
    $db = getDB();
    
    try {
        $stmt = $db->getConnection()->query("
            SELECT p.*, 
                   JSON_ARRAYAGG(
                       JSON_OBJECT(
                           'id', i.id,
                           'code', i.code,
                           'name_th', i.name_th,
                           'name_en', i.name_en,
                           'description', i.description,
                           'criteria_0', i.criteria_0,
                           'criteria_025', i.criteria_025,
                           'criteria_05', i.criteria_05,
                           'criteria_075', i.criteria_075,
                           'criteria_1', i.criteria_1,
                           'allow_na', i.allow_na,
                           'display_order', i.display_order
                       )
                   ) as indicators
            FROM pillars p
            LEFT JOIN indicators i ON p.id = i.pillar_id AND i.is_active = 1
            WHERE p.is_active = 1
            GROUP BY p.id
            ORDER BY p.display_order
        ");
        
        $pillars = $stmt->fetchAll();
        
        // Parse JSON indicators
        foreach ($pillars as &$pillar) {
            $pillar['indicators'] = json_decode($pillar['indicators'], true);
            if (!is_array($pillar['indicators'])) {
                $pillar['indicators'] = [];
            }
            // Sort indicators by display_order
            usort($pillar['indicators'], function($a, $b) {
                return $a['display_order'] - $b['display_order'];
            });
        }
        
        return $pillars;
        
    } catch (Exception $e) {
        error_log("Get pillars error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get or create assessment for company
 */
function getOrCreateAssessment($companyId, $periodId = null) {
    $db = getDB();
    
    try {
        // Get current period if not specified
        if (!$periodId) {
            $stmt = $db->getConnection()->query("
                SELECT id FROM assessment_periods 
                WHERE status IN ('open', 'evaluating') AND is_active = 1 
                ORDER BY start_date DESC LIMIT 1
            ");
            $period = $stmt->fetch();
            $periodId = $period['id'] ?? null;
        }
        
        if (!$periodId) {
            return ['success' => false, 'message' => 'ไม่พบรอบการประเมินที่เปิดอยู่'];
        }
        
        // Check if assessment exists
        $stmt = $db->prepare("
            SELECT * FROM assessments 
            WHERE company_id = ? AND period_id = ?
        ");
        $stmt->execute([$companyId, $periodId]);
        $assessment = $stmt->fetch();
        
        if ($assessment) {
            return ['success' => true, 'assessment' => $assessment];
        }
        
        // Create new assessment
        $stmt = $db->prepare("
            INSERT INTO assessments (company_id, period_id, status) 
            VALUES (?, ?, 'draft')
        ");
        $stmt->execute([$companyId, $periodId]);
        $assessmentId = $db->lastInsertId();
        
        // Initialize scores for all indicators
        initializeAssessmentScores($assessmentId);
        
        // Get created assessment
        $stmt = $db->prepare("SELECT * FROM assessments WHERE id = ?");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch();
        
        return ['success' => true, 'assessment' => $assessment];
        
    } catch (Exception $e) {
        error_log("Get/Create assessment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด'];
    }
}

/**
 * Initialize assessment scores
 */
function initializeAssessmentScores($assessmentId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO assessment_scores (assessment_id, indicator_id, self_score)
            SELECT ?, id, NULL FROM indicators WHERE is_active = 1
        ");
        $stmt->execute([$assessmentId]);
        
    } catch (Exception $e) {
        error_log("Initialize scores error: " . $e->getMessage());
    }
}

/**
 * Get assessment with scores
 */
function getAssessmentWithScores($assessmentId, $userId = null) {
    $db = getDB();
    
    try {
        // Get assessment (evaluator_id / evaluated_by may be added by migration)
        $stmt = $db->prepare("
            SELECT a.*, 
                   c.company_name, c.industry_type, c.employee_count,
                   c.address, c.province, c.district, c.postal_code,
                   c.phone, c.website, c.contact_name, c.contact_position, c.contact_email, c.contact_phone,
                   c.established_year, c.company_size, c.tax_id, c.logo,
                   owner.avatar as company_owner_avatar,
                   ap.year, ap.name as period_name, ap.start_date, ap.end_date,
                   ap.submission_deadline, ap.status as period_status,
                   u.name as evaluator_name
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods ap ON a.period_id = ap.id
            LEFT JOIN users owner ON c.user_id = owner.id
            LEFT JOIN users u ON u.id = COALESCE(a.evaluator_id, a.evaluated_by)
            WHERE a.id = ?
        ");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch();
        
        if (!$assessment) {
            return null;
        }

        // Get Multiple Evaluators
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email 
            FROM assessment_evaluators ae
            JOIN users u ON ae.user_id = u.id
            WHERE ae.assessment_id = ?
        ");
        $stmt->execute([$assessmentId]);
        $assessment['evaluators'] = $stmt->fetchAll();
        
        // Fallback for UI compatibility if needed
        if (empty($assessment['evaluators']) && !empty($assessment['evaluator_name'])) {
            $assessment['evaluators'][] = [
                'id' => $assessment['evaluator_id'],
                'name' => $assessment['evaluator_name']
            ];
        }
        
        // Get scores grouped by pillar
        $stmt = $db->prepare("
            SELECT 
                p.code as pillar_code,
                p.name_th as pillar_name,
                p.weight as pillar_weight,
                p.color as pillar_color,
                i.id as indicator_id,
                i.code as indicator_code,
                i.name_th as indicator_name,
                i.description,
                i.criteria_0, i.criteria_025, i.criteria_05, i.criteria_075, i.criteria_1, i.allow_na,
                s.id as score_id,
                s.self_score,
                s.self_evidence,
                s.is_na,
                s.auditor_score,
                s.auditor_comment,
                s.auditor_is_na,
                (SELECT COUNT(*) FROM attachments WHERE assessment_score_id = s.id) as attachment_count
            FROM pillars p
            JOIN indicators i ON p.id = i.pillar_id
            LEFT JOIN assessment_scores s ON i.id = s.indicator_id AND s.assessment_id = ?
            WHERE p.is_active = 1 AND i.is_active = 1
            ORDER BY p.display_order, i.display_order
        ");
        $stmt->execute([$assessmentId]);
        $scores = $stmt->fetchAll();
        
        // Get individual auditor scores for all indicators in this assessment
        $stmt = $db->prepare("
            SELECT es.*, u.name as auditor_name, u.avatar as auditor_avatar
            FROM assessment_evaluator_scores es
            JOIN users u ON es.user_id = u.id
            WHERE es.assessment_id = ?
        ");
        $stmt->execute([$assessmentId]);
        $evaluatorScores = $stmt->fetchAll();
        
        // Map evaluator scores to indicator_id
        $evalMap = [];
        foreach ($evaluatorScores as $es) {
            $key = $es['indicator_id'];
            if (!isset($evalMap[$key])) {
                $evalMap[$key] = [];
            }
            $evalMap[$key][] = $es;
        }
        
        // Group by pillar and attach evaluator scores
        $pillars = [];
        foreach ($scores as $score) {
            $pillarCode = $score['pillar_code'];
            if (!isset($pillars[$pillarCode])) {
                $pillars[$pillarCode] = [
                    'code' => $pillarCode,
                    'name' => $score['pillar_name'],
                    'weight' => $score['pillar_weight'],
                    'color' => $score['pillar_color'],
                    'indicators' => []
                ];
            }
            
            // Attach individual evaluator scores
            $evaluatorsList = $evalMap[$score['indicator_id']] ?? [];
            $score['evaluator_scores'] = $evaluatorsList;
            
            // If specific user ID is provided, use their personal score/comment instead of average
            if ($userId) {
                $userPersonalScore = null;
                foreach ($evaluatorsList as $es) {
                    if ($es['user_id'] == $userId) {
                        $userPersonalScore = $es;
                        break;
                    }
                }
                
                if ($userPersonalScore) {
                    $score['auditor_score'] = $userPersonalScore['score'];
                    $score['auditor_comment'] = $userPersonalScore['comment'];
                    $score['auditor_is_na'] = $userPersonalScore['is_na'];
                } else {
                    $score['auditor_score'] = null;
                    $score['auditor_comment'] = '';
                    $score['auditor_is_na'] = 0;
                }
            }
            
            $pillars[$pillarCode]['indicators'][] = $score;
        }
        
        $assessment['pillars'] = $pillars;
        
        return $assessment;
        
    } catch (Exception $e) {
        error_log("Get assessment with scores error: " . $e->getMessage());
        return null;
    }
}

/**
 * Save self assessment score
 */
function saveSelfScore($assessmentId, $indicatorId, $score, $evidence = '', $isNa = 0) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            UPDATE assessment_scores 
            SET self_score = ?, self_evidence = ?, is_na = ?, updated_at = NOW()
            WHERE assessment_id = ? AND indicator_id = ?
        ");
        $stmt->execute([$score, $evidence, $isNa, $assessmentId, $indicatorId]);
        
        // Recalculate total score
        recalculateAssessmentScore($assessmentId);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Save self score error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด'];
    }
}

/**
 * Save auditor score
 */
function saveAuditorScore($assessmentId, $indicatorId, $score, $comment = '', $auditorId = null, $isNa = 0) {
    $db = getDB();
    
    try {
        // 1. Save or update individual auditor score
        $stmt = $db->prepare("
            INSERT INTO assessment_evaluator_scores (assessment_id, indicator_id, user_id, score, comment, is_na, evaluated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            score = VALUES(score), 
            comment = VALUES(comment), 
            is_na = VALUES(is_na), 
            evaluated_at = NOW()
        ");
        $stmt->execute([$assessmentId, $indicatorId, $auditorId, $score, $comment, $isNa]);

        // 2. Calculate average score for this indicator (only from non-N/A entries)
        $stmt = $db->prepare("
            SELECT AVG(score) as avg_score, GROUP_CONCAT(comment SEPARATOR ' | ') as all_comments
            FROM assessment_evaluator_scores 
            WHERE assessment_id = ? AND indicator_id = ? AND is_na = 0
        ");
        $stmt->execute([$assessmentId, $indicatorId]);
        $avgData = $stmt->fetch();
        $avgScore = $avgData['avg_score'];

        // 3. Check if all evaluators marked as N/A
        $stmt = $db->prepare("
            SELECT (COUNT(*) = SUM(is_na)) as is_all_na
            FROM assessment_evaluator_scores 
            WHERE assessment_id = ? AND indicator_id = ?
        ");
        $stmt->execute([$assessmentId, $indicatorId]);
        $isAllNa = $stmt->fetchColumn();

        // 4. Update core assessment_scores table with the aggregate result
        $stmt = $db->prepare("
            UPDATE assessment_scores 
            SET auditor_score = ?, auditor_comment = ?, auditor_is_na = ?, auditor_id = ?, evaluated_at = NOW()
            WHERE assessment_id = ? AND indicator_id = ?
        ");
        $stmt->execute([$avgScore, $avgData['all_comments'], $isAllNa, $auditorId, $assessmentId, $indicatorId]);
        
        // Recalculate total score
        recalculateAssessmentScore($assessmentId);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Save auditor score error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกคะแนน: ' . $e->getMessage()];
    }
}

/**
 * Recalculate assessment scores
 */
function recalculateAssessmentScore($assessmentId) {
    $db = getDB();
    
    try {
        // Get all scores
        $stmt = $db->prepare("
            SELECT 
                p.code as pillar_code,
                p.weight as pillar_weight,
                s.self_score,
                s.is_na,
                s.auditor_score,
                s.auditor_is_na
            FROM assessment_scores s
            JOIN indicators i ON s.indicator_id = i.id
            JOIN pillars p ON i.pillar_id = p.id
            WHERE s.assessment_id = ?
        ");
        $stmt->execute([$assessmentId]);
        $scores = $stmt->fetchAll();
        
        // Calculate scores by pillar
        $pillarScores = [
            'H1' => ['self' => 0, 'auditor' => 0, 'count' => 0, 'na_count' => 0, 'auditor_na_count' => 0, 'auditor_scored_count' => 0, 'weight' => 300],
            'I2' => ['self' => 0, 'auditor' => 0, 'count' => 0, 'na_count' => 0, 'auditor_na_count' => 0, 'auditor_scored_count' => 0, 'weight' => 300],
            'C3' => ['self' => 0, 'auditor' => 0, 'count' => 0, 'na_count' => 0, 'auditor_na_count' => 0, 'auditor_scored_count' => 0, 'weight' => 200],
            'M4' => ['self' => 0, 'auditor' => 0, 'count' => 0, 'na_count' => 0, 'auditor_na_count' => 0, 'auditor_scored_count' => 0, 'weight' => 200]
        ];
        
        foreach ($scores as $score) {
            $code = $score['pillar_code'];
            $pillarScores[$code]['count']++;
            
            if ($score['is_na']) {
                $pillarScores[$code]['na_count']++;
            } else {
                $pillarScores[$code]['self'] += floatval($score['self_score']);
            }
            
            if ($score['auditor_is_na']) {
                $pillarScores[$code]['auditor_na_count']++;
            } elseif ($score['auditor_score'] !== null && $score['auditor_score'] !== '') {
                // Only count auditor score if actually scored (not NULL)
                $pillarScores[$code]['auditor'] += floatval($score['auditor_score']);
                $pillarScores[$code]['auditor_scored_count']++;
            }
        }
        
        // Calculate weighted scores
        $selfTotal = 0;
        $auditorTotal = 0;
        $totalNa = 0;
        $hasAnyAuditorScore = false;
        
        foreach ($pillarScores as $code => $pillar) {
            $totalNa += $pillar['na_count'];
            
            // Self Score Calculation
            $effectiveCount = $pillar['count'] - $pillar['na_count'];
            if ($effectiveCount > 0) {
                $selfWeighted = ($pillar['self'] / $effectiveCount) * $pillar['weight'];
                $selfTotal += $selfWeighted;
            }
            
            // Auditor Score Calculation — use total active count for weight distribution
            // This ensures partial scoring (e.g. 1 of 3 auditors) doesn't inflate the total
            $effectiveCountAuditor = $pillar['count'] - $pillar['na_count'];
            if ($pillar['auditor_scored_count'] > 0 && $effectiveCountAuditor > 0) {
                $hasAnyAuditorScore = true;
                $auditorWeighted = ($pillar['auditor'] / $effectiveCountAuditor) * $pillar['weight'];
                $auditorTotal += $auditorWeighted;
            }
        }
        
        // Determine HICM level — only use auditor total if auditors have actually scored
        $finalScore = $hasAnyAuditorScore ? $auditorTotal : $selfTotal;
        $hicmLevel = calculateHICMLevel($finalScore);
        
        // Update assessment
        $stmt = $db->prepare("
            UPDATE assessments 
            SET self_total_score = ?, auditor_total_score = ?, final_score = ?, hicm_level = ?
            WHERE id = ?
        ");
        $stmt->execute([
            round($selfTotal, 2),
            round($auditorTotal, 2),
            round($finalScore, 2),
            $hicmLevel,
            $assessmentId
        ]);
        
    } catch (Exception $e) {
        error_log("Recalculate score error: " . $e->getMessage());
    }
}

/**
 * Calculate HICM level
 */
function calculateHICMLevel($score) {
    if ($score >= 900) return 5;
    if ($score >= 800) return 4;
    if ($score >= 700) return 3;
    if ($score >= 600) return 2;
    return 1;
}

/**
 * Get HICM level name
 */
function getHICMLevelName($level) {
    $levels = [
        1 => ['name' => 'เริ่มต้น', 'name_en' => 'Emerging'],
        2 => ['name' => 'กำลังพัฒนา', 'name_en' => 'Developing'],
        3 => ['name' => 'พัฒนาดี', 'name_en' => 'Performing'],
        4 => ['name' => 'เป็นเลิศ', 'name_en' => 'Excellence'],
        5 => ['name' => 'ระดับโลก', 'name_en' => 'World-Class']
    ];
    return $levels[$level] ?? $levels[1];
}

/**
 * Submit assessment
 * รองรับการส่งใหม่ได้ (resubmit) และบันทึก milestone อัตโนมัติ
 */
function submitAssessment($assessmentId) {
    $db = getDB();
    
    try {
        // First check if assessment exists and get its current status
        $stmt = $db->prepare("SELECT id, status FROM assessments WHERE id = ?");
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch();
        
        if (!$assessment) {
            return ['success' => false, 'message' => 'ไม่พบข้อมูลการประเมิน'];
        }
        
        // Allow submit from draft, or resubmit from submitted/under_review/evaluated
        $allowedStatuses = ['draft', 'submitted', 'under_review', 'evaluated'];
        if (!in_array($assessment['status'], $allowedStatuses)) {
            return ['success' => false, 'message' => 'ไม่สามารถส่งแบบประเมินได้ในสถานะ: ' . $assessment['status']];
        }
        
        // Check deadline/period — fetch period info
        $stmtP = $db->prepare("
            SELECT p.status as period_status, p.submission_deadline, p.end_date
            FROM assessments a
            JOIN assessment_periods p ON a.period_id = p.id
            WHERE a.id = ?
        ");
        $stmtP->execute([$assessmentId]);
        $periodInfo = $stmtP->fetch();
        
        if ($periodInfo) {
            $pStatus = $periodInfo['period_status'];
            $deadline = $periodInfo['submission_deadline'] ?? $periodInfo['end_date'] ?? null;
            $pClosed = !in_array($pStatus, ['open', 'evaluating']);
            $deadlinePassed = $deadline && (date('Y-m-d') > $deadline);
            
            if ($pClosed) {
                return ['success' => false, 'message' => 'รอบการประเมินนี้ปิดแล้ว ไม่สามารถส่งได้'];
            }
            if ($deadlinePassed) {
                return ['success' => false, 'message' => 'เลยกำหนดส่ง ไม่สามารถส่งได้'];
            }
        }
        
        $isResubmit = in_array($assessment['status'], ['submitted', 'under_review', 'evaluated']);
        
        // Auto-save milestone before submitting
        $milestoneNote = $isResubmit ? 'บันทึกอัตโนมัติก่อนส่งใหม่ (แก้ไข)' : 'บันทึกอัตโนมัติก่อนส่ง';
        $milestoneResult = saveMilestone($assessmentId, 'self', $milestoneNote);
        
        // If resubmitting from evaluated, reset auditor submissions so they re-evaluate
        if ($assessment['status'] === 'evaluated') {
            $stmt = $db->prepare("UPDATE assessment_evaluators SET submitted_at = NULL WHERE assessment_id = ?");
            $stmt->execute([$assessmentId]);
        }
        
        // Update status to submitted (or re-submitted)
        $stmt = $db->prepare("
            UPDATE assessments 
            SET status = 'submitted', submitted_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$assessmentId]);
        
        if ($stmt->rowCount() > 0) {
            // Log activity
            $userId = $_SESSION['user_id'] ?? null;
            if ($userId) {
                $logMsg = $isResubmit ? 'ส่งแบบประเมินใหม่ (แก้ไข) ID: ' : 'ส่งแบบประเมิน ID: ';
                logActivity($userId, 'submit_assessment', $logMsg . $assessmentId);
            }
            
            // Notify assigned auditors
            require_once __DIR__ . '/notification.php';
            $notifAction = $isResubmit ? 'resubmitted' : 'submitted';
            notifyAssignedAuditors($assessmentId, $notifAction);
            
            return ['success' => true, 'resubmit' => $isResubmit, 'milestone' => $milestoneResult];
        }
        
        return ['success' => false, 'message' => 'ไม่สามารถส่งแบบประเมินได้'];
        
    } catch (Exception $e) {
        error_log("Submit assessment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Get all assessments (for auditor/admin)
 */
function getAllAssessments($filters = []) {
    $db = getDB();
    
    try {
        $sql = "
            SELECT 
                a.*,
                c.company_name, c.industry_type, c.employee_count, c.logo,
                owner.avatar as company_owner_avatar,
                ap.year, ap.name as period_name, ap.status as period_status,
                u.name as evaluator_name,
                (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ') 
                 FROM assessment_evaluators ae 
                 JOIN users u2 ON ae.user_id = u2.id 
                 WHERE ae.assessment_id = a.id) as auditors_list,
                (SELECT GROUP_CONCAT(ae2.user_id) 
                 FROM assessment_evaluators ae2 
                 WHERE ae2.assessment_id = a.id) as auditor_ids,
                (SELECT COUNT(*) 
                 FROM assessment_evaluators ae3 
                 WHERE ae3.assessment_id = a.id) as total_evaluators,
                (SELECT COUNT(*) 
                 FROM assessment_evaluators ae4 
                 WHERE ae4.assessment_id = a.id AND ae4.submitted_at IS NOT NULL) as submitted_evaluators
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods ap ON a.period_id = ap.id
            LEFT JOIN users owner ON c.user_id = owner.id
            LEFT JOIN users u ON a.evaluated_by = u.id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['period_id'])) {
            $sql .= " AND a.period_id = ?";
            $params[] = $filters['period_id'];
        }
        
        if (!empty($filters['company_id'])) {
            $sql .= " AND a.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND c.company_name LIKE ?";
            $params[] = "%{$filters['search']}%";
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get all assessments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get assessment statistics
 */
function getAssessmentStatistics($periodId = null) {
    $db = getDB();
    
    try {
        // Get assessment statistics
        $sql = "
            SELECT 
                COUNT(*) as total_assessments,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_count,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review_count,
                SUM(CASE WHEN status = 'evaluated' THEN 1 ELSE 0 END) as evaluated_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                AVG(final_score) as avg_score,
                MAX(final_score) as max_score,
                MIN(final_score) as min_score
            FROM assessments
            WHERE 1=1
        ";
        $params = [];
        
        if ($periodId) {
            $sql .= " AND period_id = ?";
            $params[] = $periodId;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        // Get total companies count
        $companyStmt = $db->getConnection()->query("SELECT COUNT(*) as total FROM companies WHERE is_active = 1");
        $companyCount = $companyStmt->fetch();
        $result['total'] = $companyCount['total'] ?? 0;
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Get statistics error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get score distribution by level
 */
function getScoreDistribution($periodId = null) {
    $db = getDB();
    
    try {
        $sql = "
            SELECT 
                hicm_level,
                COUNT(*) as count
            FROM assessments
            WHERE 1=1
        ";
        $params = [];
        
        if ($periodId) {
            $sql .= " AND period_id = ?";
            $params[] = $periodId;
        }
        
        $sql .= " GROUP BY hicm_level ORDER BY hicm_level";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get score distribution error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get pillar scores for chart
 */
function getPillarScoresForChart($assessmentId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT 
                p.code,
                p.name_th,
                p.weight,
                AVG(s.self_score) as avg_self_score,
                AVG(s.auditor_score) as avg_auditor_score
            FROM pillars p
            JOIN indicators i ON p.id = i.pillar_id
            JOIN assessment_scores s ON i.id = s.indicator_id
            WHERE s.assessment_id = ?
            GROUP BY p.id
            ORDER BY p.display_order
        ");
        $stmt->execute([$assessmentId]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get pillar scores error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attachments for a specific score
 */
function getAttachmentsByScoreId($scoreId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM attachments 
            WHERE assessment_score_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$scoreId]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get attachments error: " . $e->getMessage());
        return [];
    }
}


/**
 * Get performance text interpretation based on percentage
 */
function getPillarPerformanceText($percentage) {
    if ($percentage >= 90) return 'ยอดเยี่ยม (Excellent)';
    if ($percentage >= 80) return 'ดีมาก (Very Good)';
    if ($percentage >= 70) return 'ดี (Good)';
    if ($percentage >= 60) return 'พอใช้ (Fair)';
    if ($percentage >= 50) return 'ควรปรับปรุง (Pass/Needs Improvement)';
    return 'ต้องปรับปรุงเร่งด่วน (Urgent Improvement Required)';
}

/**
 * Get assessment history for a company
 */
function getCompanyAssessmentHistory($companyId) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT a.id, a.period_id, a.self_total_score, a.final_score, a.hicm_level, a.status, 
                   ap.year, ap.name as period_name, ap.status as period_status,
                   ap.show_auditor_results, ap.announcement_date
            FROM assessments a
            JOIN assessment_periods ap ON a.period_id = ap.id
            WHERE a.company_id = ? AND a.status IN ('completed', 'evaluated', 'submitted', 'under_review')
            ORDER BY ap.year ASC, ap.start_date ASC
        ");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get history error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get adjacent assessment IDs for navigation
 */
function getAdjacentAssessmentIds($assessmentId, $companyId) {
    $db = getDB();
    $result = ['prev' => null, 'next' => null];
    
    try {
        // Find previous
        $stmt = $db->prepare("
            SELECT a.id 
            FROM assessments a
            JOIN assessment_periods ap ON a.period_id = ap.id
            WHERE a.company_id = ? 
            AND (ap.year < (SELECT year FROM assessment_periods WHERE id = (SELECT period_id FROM assessments WHERE id = ?))
                 OR (ap.year = (SELECT year FROM assessment_periods WHERE id = (SELECT period_id FROM assessments WHERE id = ?)) 
                     AND ap.start_date < (SELECT start_date FROM assessment_periods WHERE id = (SELECT period_id FROM assessments WHERE id = ?))))
            ORDER BY ap.year DESC, ap.start_date DESC
            LIMIT 1
        ");
        $stmt->execute([$companyId, $assessmentId, $assessmentId, $assessmentId]);
        $prev = $stmt->fetch();
        if ($prev) $result['prev'] = $prev['id'];

        // Find next
        $stmt = $db->prepare("
            SELECT a.id 
            FROM assessments a
            JOIN assessment_periods ap ON a.period_id = ap.id
            WHERE a.company_id = ? 
            AND (ap.year > (SELECT year FROM assessment_periods WHERE id = (SELECT period_id FROM assessments WHERE id = ?))
                 OR (ap.year = (SELECT year FROM assessment_periods WHERE id = (SELECT period_id FROM assessments WHERE id = ?)) 
                     AND ap.start_date > (SELECT start_date FROM assessment_periods WHERE id = (SELECT period_id FROM assessments WHERE id = ?))))
            ORDER BY ap.year ASC, ap.start_date ASC
            LIMIT 1
        ");
        $stmt->execute([$companyId, $assessmentId, $assessmentId, $assessmentId]);
        $next = $stmt->fetch();
        if ($next) $result['next'] = $next['id'];

        return $result;
    } catch (Exception $e) {
        error_log("Get adjacent error: " . $e->getMessage());
        return $result;
    }
}
/**
 * Assign multiple auditors to an assessment
 */
function assignMultipleAuditors($assessmentId, $auditorIds) {
    if (empty($auditorIds)) return ['success' => false, 'message' => 'กรุณาเลือกผู้ตรวจอย่างน้อย 1 คน'];
    
    $db = getDB();
    try {
        $db->beginTransaction();
        
        // Clear previous assignments (optional? usually yes for a re-assign)
        $stmt = $db->prepare("DELETE FROM assessment_evaluators WHERE assessment_id = ?");
        $stmt->execute([$assessmentId]);
        
        // Insert new assignments
        $stmt = $db->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id) VALUES (?, ?)");
        foreach ($auditorIds as $audId) {
            $stmt->execute([$assessmentId, $audId]);
        }
        
        // Also update the primary columns for backward compatibility with existing views
        $primaryAuditor = $auditorIds[0];
        $db->prepare("UPDATE assessments SET evaluator_id = ?, evaluated_by = ?, status = 'under_review', updated_at = NOW() WHERE id = ?")
           ->execute([$primaryAuditor, $primaryAuditor, $assessmentId]);
        
        $db->commit();
        
        // Log action
        logActivity($_SESSION['user_id'], 'assign_auditors', 'มอบหมาย auditors จำนวน ' . count($auditorIds) . ' คน ให้ assessment ID: ' . $assessmentId);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollback();
        error_log("Assign multiple auditors error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการมอบหมาย'];
    }
}

/**
 * Notify assigned auditors via email
 */
function notifyAuditors($assessmentId, $auditorIds) {
    require_once __DIR__ . '/email.php';
    
    $assessment = getAssessmentWithScores($assessmentId);
    if (!$assessment) return false;
    
    $results = [];
    foreach ($assessment['evaluators'] as $auditor) {
        if (in_array($auditor['id'], $auditorIds)) {
            $variables = [
                'auditor_name' => $auditor['name'],
                'company_name' => $assessment['company_name'],
                'year' => $assessment['year'],
                'period_name' => $assessment['period_name'],
                'view_url' => getBaseUrl() . '/pages/assessment-view.php?id=' . $assessmentId,
                'app_name' => APP_NAME
            ];
            
            $sent = sendTemplatedEmail($auditor['email'], 'new_assignment', $variables);
            if ($sent) {
                // Update notification timestamp
                $db = getDB();
                $db->prepare("UPDATE assessment_evaluators SET notified_at = NOW() WHERE assessment_id = ? AND user_id = ?")
                   ->execute([$assessmentId, $auditor['id']]);
            }
            $results[] = $sent;
        }
    }
    
    return !empty($results) && !in_array(false, $results);
}

// ============================================
// Milestone System Functions
// ระบบบันทึก Checkpoint พัฒนาการ
// ============================================

/**
 * Save assessment milestone (checkpoint)
 * บันทึก snapshot ของคะแนนปัจจุบันเป็น milestone
 */
function saveMilestone($assessmentId, $type = 'self', $note = '') {
    $db = getDB();
    
    try {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'];
        }
        
        // Get current assessment with scores
        $assessment = getAssessmentWithScores($assessmentId);
        if (!$assessment) {
            return ['success' => false, 'message' => 'ไม่พบข้อมูลการประเมิน'];
        }
        
        // Get next version number
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(version), 0) + 1 as next_version
            FROM assessment_milestones 
            WHERE assessment_id = ? AND milestone_type = ?
        ");
        $stmt->execute([$assessmentId, $type]);
        $nextVersion = $stmt->fetchColumn();
        
        // Calculate pillar scores
        $pillarScores = ['H1' => 0, 'I2' => 0, 'C3' => 0, 'M4' => 0];
        $answeredCount = 0;
        $totalIndicators = 0;
        
        $scoreField = ($type === 'self') ? 'self_score' : 'auditor_score';
        
        foreach ($assessment['pillars'] as $pillar) {
            $pillarCode = $pillar['code'];
            $pillarTotal = 0;
            $pillarCount = 0;
            
            foreach ($pillar['indicators'] as $indicator) {
                $totalIndicators++;
                $score = $indicator[$scoreField] ?? 0;
                
                if ($score > 0 || !empty($indicator['self_evidence'])) {
                    $answeredCount++;
                    $pillarTotal += $score;
                    $pillarCount++;
                }
            }
            
            // Calculate pillar percentage (out of 100)
            $maxPillarScore = count($pillar['indicators']);
            $pillarScores[$pillarCode] = $maxPillarScore > 0 ? round(($pillarTotal / $maxPillarScore) * 100, 2) : 0;
        }
        
        // Calculate total score
        $totalScore = ($type === 'self') ? $assessment['self_total_score'] : $assessment['auditor_total_score'];
        
        // Insert milestone
        $stmt = $db->prepare("
            INSERT INTO assessment_milestones 
            (assessment_id, version, milestone_type, total_score, h1_score, i2_score, c3_score, m4_score, 
             answered_count, total_indicators, note, saved_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $assessmentId,
            $nextVersion,
            $type,
            $totalScore,
            $pillarScores['H1'],
            $pillarScores['I2'],
            $pillarScores['C3'],
            $pillarScores['M4'],
            $answeredCount,
            $totalIndicators,
            $note,
            $userId
        ]);
        
        $milestoneId = $db->lastInsertId();
        
        // Save milestone details (individual indicator scores)
        $stmtDetail = $db->prepare("
            INSERT INTO assessment_milestone_details (milestone_id, indicator_id, score, is_na, evidence)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($assessment['pillars'] as $pillar) {
            foreach ($pillar['indicators'] as $indicator) {
                $indicatorId = $indicator['indicator_id'] ?? $indicator['id'] ?? null;
                if (!$indicatorId) continue; // Skip if no indicator ID
                
                $score = ($type === 'self') ? ($indicator['self_score'] ?? 0) : ($indicator['auditor_score'] ?? 0);
                $isNa = $indicator['is_na'] ?? 0;
                $evidence = ($type === 'self') ? ($indicator['self_evidence'] ?? '') : ($indicator['auditor_comment'] ?? '');
                
                $stmtDetail->execute([
                    $milestoneId,
                    $indicatorId,
                    $score,
                    $isNa,
                    $evidence
                ]);
            }
        }
        
        // Update assessment's current milestone version
        $versionField = ($type === 'self') ? 'current_milestone_version' : 'auditor_milestone_version';
        $db->prepare("UPDATE assessments SET {$versionField} = ? WHERE id = ?")
           ->execute([$nextVersion, $assessmentId]);
        
        // Log activity
        logActivity($userId, 'save_milestone', "บันทึก Milestone #{$nextVersion} ({$type}) สำหรับการประเมิน ID: {$assessmentId}");
        
        return [
            'success' => true, 
            'milestone_id' => $milestoneId,
            'version' => $nextVersion,
            'message' => "บันทึก Checkpoint #{$nextVersion} เรียบร้อยแล้ว"
        ];
        
    } catch (Exception $e) {
        error_log("Save milestone error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึก'];
    }
}

/**
 * Get milestones for an assessment
 * ดึงรายการ milestone ของการประเมิน
 */
function getMilestones($assessmentId, $type = null) {
    $db = getDB();
    
    try {
        $sql = "
            SELECT m.*, u.name as saved_by_name
            FROM assessment_milestones m
            JOIN users u ON m.saved_by = u.id
            WHERE m.assessment_id = ?
        ";
        $params = [$assessmentId];
        
        if ($type) {
            $sql .= " AND m.milestone_type = ?";
            $params[] = $type;
        }
        
        $sql .= " ORDER BY m.version ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get milestones error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get milestones for comparison across periods
 * ดึง milestone เพื่อเปรียบเทียบข้ามรอบประเมิน
 */
function getMilestonesAcrossPeriods($companyId, $type = 'self') {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT 
                m.*,
                a.period_id,
                ap.year,
                ap.name as period_name,
                u.name as saved_by_name
            FROM assessment_milestones m
            JOIN assessments a ON m.assessment_id = a.id
            JOIN assessment_periods ap ON a.period_id = ap.id
            JOIN users u ON m.saved_by = u.id
            WHERE a.company_id = ? AND m.milestone_type = ?
            ORDER BY ap.year ASC, ap.start_date ASC, m.version ASC
        ");
        $stmt->execute([$companyId, $type]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get milestones across periods error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get milestone details
 * ดึงรายละเอียดคะแนนแต่ละข้อของ milestone
 */
function getMilestoneDetails($milestoneId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT 
                md.*,
                i.code as indicator_code,
                i.name_th as indicator_name,
                p.code as pillar_code,
                p.name_th as pillar_name
            FROM assessment_milestone_details md
            JOIN indicators i ON md.indicator_id = i.id
            JOIN pillars p ON i.pillar_id = p.id
            WHERE md.milestone_id = ?
            ORDER BY p.display_order, i.display_order
        ");
        $stmt->execute([$milestoneId]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get milestone details error: " . $e->getMessage());
        return [];
    }
}

/**
 * Compare two milestones
 * เปรียบเทียบ 2 milestone
 */
function compareMilestones($milestoneId1, $milestoneId2) {
    $db = getDB();
    
    try {
        // Get both milestones
        $stmt = $db->prepare("SELECT * FROM assessment_milestones WHERE id IN (?, ?)");
        $stmt->execute([$milestoneId1, $milestoneId2]);
        $milestones = $stmt->fetchAll(PDO::FETCH_UNIQUE);
        
        if (count($milestones) !== 2) {
            return null;
        }
        
        $m1 = $milestones[$milestoneId1];
        $m2 = $milestones[$milestoneId2];
        
        return [
            'milestone_1' => $m1,
            'milestone_2' => $m2,
            'total_diff' => $m2['total_score'] - $m1['total_score'],
            'h1_diff' => $m2['h1_score'] - $m1['h1_score'],
            'i2_diff' => $m2['i2_score'] - $m1['i2_score'],
            'c3_diff' => $m2['c3_score'] - $m1['c3_score'],
            'm4_diff' => $m2['m4_score'] - $m1['m4_score'],
            'answered_diff' => $m2['answered_count'] - $m1['answered_count']
        ];
        
    } catch (Exception $e) {
        error_log("Compare milestones error: " . $e->getMessage());
        return null;
    }
}

// ============================================
// Smart Match Functions (Shared)
// ============================================

/**
 * คำนวณคะแนน Smart Match
 */
if (!function_exists('calculateSmartMatchScore')) {
function calculateSmartMatchScore($companyIndustry, $auditorExpertise, $auditorHicm, $requiredPillars = []) {
    $industryScore = 0;
    $hicmScore = 0;
    $details = [];
    
    if (!empty($companyIndustry) && !empty($auditorExpertise)) {
        $companyTypes = array_map('trim', explode('|', $companyIndustry));
        $auditorTypes = array_map('trim', explode('|', $auditorExpertise));
        foreach ($companyTypes as $cType) {
            foreach ($auditorTypes as $aType) {
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
    
    if (!empty($auditorHicm)) {
        $auditorPillars = array_filter(array_map('trim', explode('|', $auditorHicm)));
        if (empty($requiredPillars)) {
            $hicmScore = min(100, count($auditorPillars) * 25);
            $details[] = "HICM: " . implode(', ', $auditorPillars);
        } else {
            $matchedPillars = array_intersect($auditorPillars, $requiredPillars);
            $hicmScore = count($requiredPillars) > 0 
                ? round((count($matchedPillars) / count($requiredPillars)) * 100) 
                : 0;
            if (!empty($matchedPillars)) {
                $details[] = "✓ HICM ตรง: " . implode(', ', $matchedPillars);
            }
        }
    }
    
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
 * Smart Match: Balanced Distribution algorithm
 */
if (!function_exists('smartMatchAuditors')) {
function smartMatchAuditors($auditors, $companyIndustry, $maxAuditors = 4, &$assignmentTracker = null) {
    foreach ($auditors as &$aud) {
        $runtimeExtra = ($assignmentTracker !== null) ? ($assignmentTracker[$aud['id']] ?? 0) : 0;
        $aud['effective_assignments'] = ($aud['total_assignments'] ?? 0) + $runtimeExtra;
    }
    unset($aud);
    
    $candidates = [];
    foreach ($auditors as $aud) {
        $matchScore = calculateSmartMatchScore($companyIndustry, $aud['expertise'], $aud['hicm_expertise']);
        $auditorPillars = array_filter(array_map('trim', explode('|', $aud['hicm_expertise'] ?? '')));
        $candidates[] = [
            'auditor' => $aud,
            'score' => $matchScore,
            'pillars' => $auditorPillars,
            'assignments' => $aud['effective_assignments']
        ];
    }
    
    $selected = [];
    $coveredPillars = [];
    $allPillars = ['H1', 'I2', 'C3', 'M4'];
    $usedIds = [];
    
    for ($round = 0; $round < $maxAuditors && $round < count($candidates); $round++) {
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
        
        $bestIdx = -1;
        $bestComposite = -999;
        
        foreach ($candidates as $idx => $item) {
            if (isset($usedIds[$item['auditor']['id']])) continue;
            
            $uncoveredPillars = array_diff($allPillars, $coveredPillars);
            $newPillars = array_intersect($item['pillars'], $uncoveredPillars);
            $totalUncovered = count($uncoveredPillars);
            $pillarValue = $totalUncovered > 0 ? round((count($newPillars) / $totalUncovered) * 40) : 0;
            
            $matchQuality = round($item['score']['total'] * 0.30);
            
            $loadRatio = ($item['assignments'] - $minAssign) / $range;
            $loadPenalty = pow($loadRatio, 1.5);
            $loadScore = round((1 - $loadPenalty) * 30);
            
            $composite = $pillarValue + $matchQuality + $loadScore;
            
            if ($composite > $bestComposite) {
                $bestComposite = $composite;
                $bestIdx = $idx;
            }
        }
        
        if ($bestIdx === -1) break;
        
        $pick = $candidates[$bestIdx];
        $pick['load_bonus'] = $loadScore ?? 0;
        $pick['balanced_score'] = $bestComposite;
        $selected[] = $pick;
        $usedIds[$pick['auditor']['id']] = true;
        $coveredPillars = array_unique(array_merge($coveredPillars, $pick['pillars']));
    }
    
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

/**
 * ดึง auditors พร้อมข้อมูลรายละเอียด (shared version)
 */
if (!function_exists('getActiveAuditorsWithDetails')) {
function getActiveAuditorsWithDetails() {
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
}

/**
 * Auto Smart Match: จับคู่กรรมการให้บริษัทใหม่อัตโนมัติ
 * เรียกใช้เมื่อสร้างบริษัทใหม่ และมี period ที่เปิด auto_smart_match อยู่
 * 
 * @param int $companyId ID ของบริษัทที่เพิ่งสร้าง
 * @return array ['matched' => bool, 'period' => string, 'auditors_count' => int]
 */
function autoSmartMatchNewCompany($companyId) {
    $db = getDB();
    
    try {
        // หารอบประเมินที่เปิดอยู่ + เปิด auto_smart_match
        $stmt = $db->prepare("
            SELECT id, name, auditors_per_company 
            FROM assessment_periods 
            WHERE status IN ('open', 'evaluating') 
              AND auto_smart_match = 1 
            ORDER BY start_date DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $period = $stmt->fetch();
        
        if (!$period) {
            return ['matched' => false, 'reason' => 'no_active_auto_period'];
        }
        
        $periodId = $period['id'];
        $maxAuditors = $period['auditors_per_company'] ?? 3;
        
        // ตรวจว่าบริษัทนี้ยังไม่มีกรรมการในรอบนี้
        $stmt = $db->prepare("
            SELECT ae.id FROM assessments a 
            JOIN assessment_evaluators ae ON a.id = ae.assessment_id 
            WHERE a.company_id = ? AND a.period_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$companyId, $periodId]);
        if ($stmt->fetch()) {
            return ['matched' => false, 'reason' => 'already_assigned'];
        }
        
        // ดึงข้อมูลบริษัท
        $stmt = $db->prepare("SELECT id, industry_type FROM companies WHERE id = ? AND is_active = 1");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch();
        
        if (!$company) {
            return ['matched' => false, 'reason' => 'company_not_found'];
        }
        
        // ดึงกรรมการทั้งหมด
        $auditors = getActiveAuditorsWithDetails();
        if (empty($auditors)) {
            return ['matched' => false, 'reason' => 'no_auditors'];
        }
        
        // เรียก Smart Match
        $match = smartMatchAuditors($auditors, $company['industry_type'], $maxAuditors);
        
        if (empty($match['auditors'])) {
            return ['matched' => false, 'reason' => 'no_match_found'];
        }
        
        // สร้าง/ดึง Assessment
        $res = getOrCreateAssessment($companyId, $periodId);
        if (!$res['success']) {
            return ['matched' => false, 'reason' => 'assessment_error'];
        }
        
        $assessmentId = $res['assessment']['id'];
        
        // ลบกรรมการเดิม (ถ้ามี)
        $stmt = $db->prepare("DELETE FROM assessment_evaluators WHERE assessment_id = ?");
        $stmt->execute([$assessmentId]);
        
        // เพิ่มกรรมการ
        $stmt = $db->prepare("INSERT INTO assessment_evaluators (assessment_id, user_id, assigned_by) VALUES (?, ?, ?)");
        $firstAuditor = null;
        $count = 0;
        foreach ($match['auditors'] as $item) {
            $stmt->execute([$assessmentId, $item['auditor']['id'], null]); // null = auto-assigned
            if (!$firstAuditor) $firstAuditor = $item['auditor']['id'];
            $count++;
        }
        
        // อัปเดต evaluator_id หลัก
        if ($firstAuditor) {
            $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
            $stmt->execute([$firstAuditor, $assessmentId]);
        }
        
        logActivity(null, 'auto_smart_match', 
            "Auto Smart Match บริษัท ID:{$companyId} ในรอบ {$period['name']} — {$count} กรรมการ (coverage: {$match['coverage']}%)"
        );
        
        return [
            'matched' => true, 
            'period' => $period['name'], 
            'auditors_count' => $count,
            'coverage' => $match['coverage']
        ];
        
    } catch (Exception $e) {
        error_log("Auto Smart Match error for company {$companyId}: " . $e->getMessage());
        return ['matched' => false, 'reason' => 'error', 'message' => $e->getMessage()];
    }
}
?>
