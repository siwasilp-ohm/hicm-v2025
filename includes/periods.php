<?php
/**
 * Assessment Periods Management Logic
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get all periods with assessment count
 */
function getAllPeriods($filters = []) {
    $db = getDB();
    $sql = "SELECT p.*, u.name as created_by_name,
                   (SELECT COUNT(*) FROM assessments a WHERE a.period_id = p.id) as assessment_count
            FROM assessment_periods p 
            LEFT JOIN users u ON p.created_by = u.id 
            WHERE p.is_active = 1";
    $params = [];

    // Allow viewing archived periods
    if (!empty($filters['include_archived'])) {
        $sql = str_replace('WHERE p.is_active = 1', 'WHERE 1=1', $sql);
    }

    if (!empty($filters['status'])) {
        $sql .= " AND p.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['year'])) {
        $sql .= " AND p.year = ?";
        $params[] = $filters['year'];
    }

    $sql .= " ORDER BY p.year DESC, p.start_date DESC";

    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get period by ID
 */
function getPeriodById($id) {
    $db = getDB();
    $stmt = $db->getConnection()->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM assessments a WHERE a.period_id = p.id) as assessment_count
        FROM assessment_periods p 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Get assessment count for a period
 */
function getPeriodAssessmentCount($periodId) {
    $db = getDB();
    $stmt = $db->getConnection()->prepare("SELECT COUNT(*) FROM assessments WHERE period_id = ?");
    $stmt->execute([$periodId]);
    return $stmt->fetchColumn();
}

function createPeriod($data) {
    $db = getDB();
    try {
        $conn = $db->getConnection();
        
        // ตรวจสอบวันที่ซ้อนทับกับรอบที่เปิดอยู่
        $today = date('Y-m-d');
        $warnings = [];
        
        // ตรวจว่า start_date <= วันนี้ → จะถูก auto-open ทันที
        if (!empty($data['start_date']) && $data['start_date'] <= $today) {
            // ตรวจหารอบที่เปิดอยู่แล้ว
            $stmt = $conn->prepare("
                SELECT id, name, year FROM assessment_periods 
                WHERE status IN ('open', 'evaluating') AND is_active = 1
            ");
            $stmt->execute();
            $openPeriods = $stmt->fetchAll();
            
            if (!empty($openPeriods)) {
                $names = array_map(function($p) {
                    return '"' . $p['name'] . ' (' . $p['year'] . ')"';
                }, $openPeriods);
                $warnings[] = 'วันเริ่มต้นอยู่ในวันนี้หรือก่อนหน้า — รอบนี้จะถูกเปิดอัตโนมัติ และรอบที่เปิดอยู่ (' . implode(', ', $names) . ') จะถูกปิดโดยอัตโนมัติ';
            }
        }
        
        // ตรวจสอบช่วงเวลาซ้อนทับกับรอบอื่น
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $stmt = $conn->prepare("
                SELECT id, name, year, start_date, end_date FROM assessment_periods 
                WHERE is_active = 1 
                AND status NOT IN ('completed')
                AND start_date <= ? AND end_date >= ?
            ");
            $stmt->execute([$data['end_date'], $data['start_date']]);
            $overlapping = $stmt->fetchAll();
            
            if (!empty($overlapping)) {
                $names = array_map(function($p) {
                    return '"' . $p['name'] . ' (' . $p['year'] . ')"';
                }, $overlapping);
                $warnings[] = 'ช่วงเวลาซ้อนทับกับรอบ: ' . implode(', ', $names);
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO assessment_periods (
                year, name, description, start_date, end_date, 
                submission_deadline, evaluation_start_date, evaluation_end_date, announcement_date,
                status, is_active, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', 1, ?)
        ");
        $stmt->execute([
            $data['year'],
            $data['name'],
            $data['description'],
            $data['start_date'],
            $data['end_date'],
            $data['submission_deadline'],
            $data['evaluation_start_date'],
            $data['evaluation_end_date'],
            $data['announcement_date'],
            $_SESSION['user_id'] ?? null
        ]);
        
        logActivity($_SESSION['user_id'], 'create_period', 'สร้างรอบการประเมิน: ' . $data['name']);
        return ['success' => true, 'warnings' => $warnings];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updatePeriod($id, $data) {
    $db = getDB();
    try {
        $conn = $db->getConnection();
        
        // Check if announcement_date changed — if so, reset auto-announce guard
        $stmtOld = $conn->prepare("SELECT announcement_date FROM assessment_periods WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldPeriod = $stmtOld->fetch();
        $annDateChanged = $oldPeriod && $oldPeriod['announcement_date'] !== $data['announcement_date'];
        
        $stmt = $conn->prepare("
            UPDATE assessment_periods 
            SET year = ?, name = ?, description = ?, start_date = ?, end_date = ?, 
                submission_deadline = ?, evaluation_start_date = ?, evaluation_end_date = ?, announcement_date = ?
                " . ($annDateChanged ? ", results_announced_at = NULL, results_announced = 0" : "") . "
            WHERE id = ?
        ");
        $stmt->execute([
            $data['year'],
            $data['name'],
            $data['description'],
            $data['start_date'],
            $data['end_date'],
            $data['submission_deadline'],
            $data['evaluation_start_date'],
            $data['evaluation_end_date'],
            $data['announcement_date'],
            $id
        ]);
        
        $extra = $annDateChanged ? ' (รีเซ็ตกำหนดประกาศผลอัตโนมัติ)' : '';
        logActivity($_SESSION['user_id'], 'update_period', 'แก้ไขรอบการประเมิน ID: ' . $id . $extra);
        return ['success' => true, 'announcement_date_changed' => $annDateChanged];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updatePeriodStatus($id, $status) {
    $db = getDB();
    try {
        $conn = $db->getConnection();
        $conn->beginTransaction();
        
        $closedPeriods = [];
        
        // Rule: Only one period can be open at a time
        // When opening a period, auto-close any other open/evaluating periods
        if ($status === 'open') {
            $stmt = $conn->prepare("
                SELECT id, name, year FROM assessment_periods 
                WHERE status IN ('open', 'evaluating') AND id != ? AND is_active = 1
            ");
            $stmt->execute([$id]);
            $otherOpen = $stmt->fetchAll();
            
            if (!empty($otherOpen)) {
                $stmt = $conn->prepare("
                    UPDATE assessment_periods 
                    SET status = 'closed' 
                    WHERE status IN ('open', 'evaluating') AND id != ? AND is_active = 1
                ");
                $stmt->execute([$id]);
                
                foreach ($otherOpen as $p) {
                    $closedPeriods[] = $p['name'] . ' (' . $p['year'] . ')';
                    logActivity($_SESSION['user_id'], 'auto_close_period', 
                        'ปิดรอบ "' . $p['name'] . '" อัตโนมัติ เนื่องจากเปิดรอบใหม่ ID: ' . $id);
                }
            }
        }
        
        $stmt = $conn->prepare("UPDATE assessment_periods SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        
        $conn->commit();
        
        logActivity($_SESSION['user_id'], 'update_period_status', 'เปลี่ยนสถานะรอบการประเมิน ID: ' . $id . ' เป็น ' . $status);
        
        // Notify companies when period opens
        if ($status === 'open') {
            require_once __DIR__ . '/notification.php';
            notifyCompaniesNewPeriod($id);
        }
        
        $result = ['success' => true];
        if (!empty($closedPeriods)) {
            $result['auto_closed'] = $closedPeriods;
        }
        return $result;
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Archive period (soft delete)
 * ใช้สำหรับ period ที่มี assessments - ซ่อนจากรายการแต่ยังเก็บข้อมูลไว้
 */
function archivePeriod($id) {
    $db = getDB();
    try {
        $stmt = $db->getConnection()->prepare("UPDATE assessment_periods SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'archive_period', 'เก็บรอบการประเมิน ID: ' . $id . ' เข้าคลัง');
        return ['success' => true, 'message' => 'เก็บรอบการประเมินเข้าคลังเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Restore archived period
 */
function restorePeriod($id) {
    $db = getDB();
    try {
        $stmt = $db->getConnection()->prepare("UPDATE assessment_periods SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'restore_period', 'กู้คืนรอบการประเมิน ID: ' . $id);
        return ['success' => true, 'message' => 'กู้คืนรอบการประเมินเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Delete period permanently
 * - ถ้าไม่มี assessments: ลบได้เลย
 * - ถ้ามี assessments: ต้อง archive แทน
 */
function deletePeriod($id) {
    $db = getDB();
    try {
        // Check if there are assessments associated with this period
        $count = getPeriodAssessmentCount($id);
        
        if ($count > 0) {
            // มี assessments - ไม่อนุญาตให้ลบถาวร ให้ใช้ archive แทน
            return [
                'success' => false, 
                'message' => 'ไม่สามารถลบถาวรได้เนื่องจากมีข้อมูลการประเมิน ' . $count . ' รายการ กรุณาใช้ "เก็บเข้าคลัง" แทน',
                'has_assessments' => true,
                'assessment_count' => $count
            ];
        }

        // ไม่มี assessments - ลบถาวรได้
        $stmt = $db->getConnection()->prepare("DELETE FROM assessment_periods WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($_SESSION['user_id'], 'delete_period', 'ลบรอบการประเมิน ID: ' . $id . ' ถาวร');
        return ['success' => true, 'message' => 'ลบรอบการประเมินเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function deleteAllAssessmentsAndPeriod($id) {
    $db = getDB();
    try {
        $conn = $db->getConnection();
        
        // Start transaction
        $conn->beginTransaction();
        
        // Get assessment IDs for this period
        $stmt = $conn->prepare("SELECT id FROM assessments WHERE period_id = ?");
        $stmt->execute([$id]);
        $assessmentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete related data for each assessment
        foreach ($assessmentIds as $assessmentId) {
            // Delete scores
            $stmt = $conn->prepare("DELETE FROM assessment_scores WHERE assessment_id = ?");
            $stmt->execute([$assessmentId]);
            
            // Delete attachments/files (if table exists)
            try {
                $stmt = $conn->prepare("DELETE FROM assessment_files WHERE assessment_id = ?");
                $stmt->execute([$assessmentId]);
            } catch (Exception $e) {
                // Table might not exist, continue anyway
            }
            
            // Delete performance reports (if table exists)
            try {
                $stmt = $conn->prepare("DELETE FROM assessment_performance_reports WHERE assessment_id = ?");
                $stmt->execute([$assessmentId]);
            } catch (Exception $e) {
                // Table might not exist, continue anyway
            }
        }
        
        // Delete assessments
        $stmt = $conn->prepare("DELETE FROM assessments WHERE period_id = ?");
        $stmt->execute([$id]);
        
        // Delete period
        $stmt = $conn->prepare("DELETE FROM assessment_periods WHERE id = ?");
        $stmt->execute([$id]);
        
        $conn->commit();
        
        $count = count($assessmentIds);
        logActivity($_SESSION['user_id'], 'delete_period_with_assessments', 'ลบรอบการประเมิน ID: ' . $id . ' พร้อมการประเมิน ' . $count . ' รายการ ถาวร');
        
        return ['success' => true, 'message' => 'ลบรอบการประเมินและการประเมินทั้งหมด (' . $count . ' รายการ) เรียบร้อยแล้ว'];
    } catch (Exception $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Toggle show_auditor_results flag for a period
 * เปิด/ปิดการแสดงผลการประเมินจากกรรมการให้บริษัทดู
 */
function toggleShowAuditorResults($id) {
    $db = getDB();
    try {
        // Get current value
        $stmt = $db->getConnection()->prepare("SELECT show_auditor_results, name, year FROM assessment_periods WHERE id = ?");
        $stmt->execute([$id]);
        $period = $stmt->fetch();
        
        if (!$period) {
            return ['success' => false, 'message' => 'ไม่พบรอบการประเมิน'];
        }
        
        $newValue = $period['show_auditor_results'] ? 0 : 1;
        
        $stmt = $db->getConnection()->prepare("UPDATE assessment_periods SET show_auditor_results = ? WHERE id = ?");
        $stmt->execute([$newValue, $id]);
        
        $actionLabel = $newValue ? 'เปิด' : 'ปิด';
        logActivity($_SESSION['user_id'], 'toggle_auditor_results', 
            $actionLabel . 'แสดงผลกรรมการ รอบ "' . $period['name'] . ' (' . $period['year'] . ')"');
        
        return [
            'success' => true, 
            'show_auditor_results' => $newValue,
            'message' => $actionLabel . 'แสดงผลการประเมินจากกรรมการเรียบร้อยแล้ว'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * Toggle results_announced flag for a period
 * เปิด/ปิดการประกาศผลคะแนน — เมื่อเปิด จะแสดง Leaderboard ในทุก Dashboard
 * บริษัทจะเห็นคะแนนและอันดับของตนเอง
 */
function toggleResultsAnnounced($id) {
    $db = getDB();
    try {
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT results_announced, results_announced_at, show_leaderboard, name, year FROM assessment_periods WHERE id = ?");
        $stmt->execute([$id]);
        $period = $stmt->fetch();
        
        if (!$period) {
            return ['success' => false, 'message' => 'ไม่พบรอบการประเมิน'];
        }
        
        $newValue = $period['results_announced'] ? 0 : 1;
        
        $conn->beginTransaction();
        
        if ($newValue) {
            // Turn OFF all other periods first (only one can be announced)
            $conn->prepare("UPDATE assessment_periods SET results_announced = 0 WHERE id != ?")->execute([$id]);
            // Turn ON this period + enable leaderboard + set announced_at timestamp
            $conn->prepare("UPDATE assessment_periods SET results_announced = 1, show_leaderboard = 1, results_announced_at = NOW() WHERE id = ?")->execute([$id]);
        } else {
            // Turn OFF — keep results_announced_at set so auto-announce won't re-trigger
            // Admin สั่งปิดเอง = ไม่ต้องการให้ระบบเปิดอัตโนมัติอีก
            $conn->prepare("UPDATE assessment_periods SET results_announced = 0 WHERE id = ?")->execute([$id]);
        }
        
        $conn->commit();
        
        $actionLabel = $newValue ? 'เปิด' : 'ปิด';
        logActivity($_SESSION['user_id'], 'toggle_results_announced', 
            $actionLabel . 'ประกาศผลคะแนน รอบ "' . $period['name'] . ' (' . $period['year'] . ')"');
        
        return [
            'success' => true, 
            'results_announced' => $newValue,
            'message' => $actionLabel . 'ประกาศผลคะแนนเรียบร้อยแล้ว'
        ];
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

/**
 * ตรวจสอบและอัปเดตสถานะรอบประเมินอัตโนมัติตามวันที่
 * 
 * Logic:
 *   draft   + ถึง start_date           → open  (เปิดรับสมัครอัตโนมัติ)
 *   open    + เลย submission_deadline   → closed (ปิดรับส่งอัตโนมัติ)
 *   open    + เลย end_date (fallback)   → closed
 *   closed  + เลย evaluation_end_date   → completed (จบโครงการอัตโนมัติ)
 *   closed  + เลย end_date (fallback)   → completed
 *
 * มี session throttle: ตรวจสอบได้ไม่เกิน 1 ครั้ง ต่อ 5 นาที
 * 
 * @param bool $force บังคับตรวจทันที (ไม่สนใจ throttle)
 * @return array ['changes' => [...], 'checked' => bool]
 */
function checkAndUpdatePeriodStatuses($force = false) {
    // Session throttle: ตรวจไม่เกินทุก 5 นาที
    if (!$force && isset($_SESSION['_period_status_checked'])) {
        $lastCheck = $_SESSION['_period_status_checked'];
        if (time() - $lastCheck < 300) { // 5 minutes
            return ['changes' => [], 'checked' => false, 'throttled' => true];
        }
    }
    $_SESSION['_period_status_checked'] = time();
    
    $db = getDB();
    $today = date('Y-m-d');
    $changes = [];
    
    try {
        $conn = $db->getConnection();
        
        // ดึง period ที่ active และยังไม่ completed
        $stmt = $conn->prepare("
            SELECT id, name, year, status, start_date, end_date, 
                   submission_deadline, evaluation_end_date
            FROM assessment_periods 
            WHERE is_active = 1 AND status NOT IN ('completed')
            ORDER BY year DESC, start_date DESC
        ");
        $stmt->execute();
        $periods = $stmt->fetchAll();
        
        foreach ($periods as $period) {
            $newStatus = null;
            $reason = '';
            
            switch ($period['status']) {
                case 'draft':
                    // draft → open: เมื่อถึงวัน start_date
                    if (!empty($period['start_date']) && $today >= $period['start_date']) {
                        $newStatus = 'open';
                        $reason = 'ถึงวันเริ่มโครงการ (' . date('d/m/Y', strtotime($period['start_date'])) . ')';
                    }
                    break;
                    
                case 'open':
                case 'evaluating':
                    // open → closed: เมื่อเลยวัน submission_deadline หรือ end_date
                    $deadline = $period['submission_deadline'] ?: $period['end_date'];
                    if (!empty($deadline) && $today > $deadline) {
                        $newStatus = 'closed';
                        $reason = 'เลยกำหนดส่ง (' . date('d/m/Y', strtotime($deadline)) . ')';
                    }
                    break;
                    
                case 'closed':
                    // closed → completed: เมื่อเลยวัน evaluation_end_date หรือ end_date
                    $evalEnd = $period['evaluation_end_date'] ?: $period['end_date'];
                    if (!empty($evalEnd) && $today > $evalEnd) {
                        $newStatus = 'completed';
                        $reason = 'เลยกำหนดประเมิน (' . date('d/m/Y', strtotime($evalEnd)) . ')';
                    }
                    break;
            }
            
            if ($newStatus) {
                $stmt2 = $conn->prepare("UPDATE assessment_periods SET status = ? WHERE id = ?");
                $stmt2->execute([$newStatus, $period['id']]);
                
                $statusLabelMap = [
                    'open' => 'กำลังดำเนินการ',
                    'closed' => 'ปิดรับแบบประเมิน',
                    'completed' => 'เสร็จสิ้น',
                ];
                
                $changes[] = [
                    'id' => $period['id'],
                    'name' => $period['name'],
                    'year' => $period['year'],
                    'from' => $period['status'],
                    'to' => $newStatus,
                    'to_label' => $statusLabelMap[$newStatus] ?? $newStatus,
                    'reason' => $reason
                ];
                
                logActivity($_SESSION['user_id'] ?? 0, 'auto_period_status', 
                    "เปลี่ยนสถานะรอบ \"{$period['name']}\" อัตโนมัติ: {$period['status']} → {$newStatus} ({$reason})"
                );
                
                // ถ้าเปลี่ยนเป็น open: ปิดรอบอื่นที่เปิดอยู่โดยอัตโนมัติ + แจ้งเตือนบริษัท
                if ($newStatus === 'open') {
                    // Auto-close other open/evaluating periods (เปิดได้ทีละรอบเดียว)
                    try {
                        $stmtOther = $conn->prepare("
                            SELECT id, name, year FROM assessment_periods 
                            WHERE status IN ('open', 'evaluating') AND id != ? AND is_active = 1
                        ");
                        $stmtOther->execute([$period['id']]);
                        $otherOpen = $stmtOther->fetchAll();
                        
                        if (!empty($otherOpen)) {
                            $stmtClose = $conn->prepare("
                                UPDATE assessment_periods SET status = 'closed' 
                                WHERE status IN ('open', 'evaluating') AND id != ? AND is_active = 1
                            ");
                            $stmtClose->execute([$period['id']]);
                            
                            foreach ($otherOpen as $closed) {
                                $changes[] = [
                                    'id' => $closed['id'],
                                    'name' => $closed['name'],
                                    'year' => $closed['year'],
                                    'from' => 'open/evaluating',
                                    'to' => 'closed',
                                    'to_label' => 'ปิดรับสมัคร',
                                    'reason' => 'ปิดอัตโนมัติเนื่องจากรอบ "' . $period['name'] . '" ถูกเปิด (ระบบเปิดได้ทีละรอบเดียว)'
                                ];
                                
                                logActivity($_SESSION['user_id'] ?? 0, 'auto_period_status',
                                    "ปิดรอบ \"{$closed['name']}\" อัตโนมัติ เนื่องจากรอบ \"{$period['name']}\" ถูกเปิด"
                                );
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Auto-close other periods error: " . $e->getMessage());
                    }
                    
                    try {
                        require_once __DIR__ . '/notification.php';
                        notifyCompaniesNewPeriod($period['id']);
                    } catch (Exception $e) {
                        error_log("Auto-open notification error: " . $e->getMessage());
                    }
                }
            }
        }
        
        // =============================================
        // AUTO-ANNOUNCE: ถึงวันเวลาประกาศผล → เปิด results_announced อัตโนมัติ
        // =============================================
        try {
            $now = date('Y-m-d H:i:s');
            $stmtAnn = $conn->prepare("
                SELECT id, name, year, announcement_date, results_announced
                FROM assessment_periods 
                WHERE is_active = 1 
                  AND announcement_date IS NOT NULL 
                  AND announcement_date <= ?
                  AND results_announced = 0
                  AND results_announced_at IS NULL
                ORDER BY year DESC
            ");
            $stmtAnn->execute([$now]);
            $readyToAnnounce = $stmtAnn->fetchAll();
            
            foreach ($readyToAnnounce as $annPeriod) {
                // Check if there are evaluated/completed assessments (มีผลคะแนนจริง)
                $stmtHasScores = $conn->prepare("
                    SELECT COUNT(*) FROM assessments 
                    WHERE period_id = ? AND status IN ('evaluated', 'completed') AND final_score > 0
                ");
                $stmtHasScores->execute([$annPeriod['id']]);
                $hasScores = $stmtHasScores->fetchColumn() > 0;
                
                if ($hasScores) {
                    // Turn off other announced periods first (เปิดได้ทีละรอบ)
                    $conn->prepare("UPDATE assessment_periods SET results_announced = 0 WHERE id != ? AND results_announced = 1")
                         ->execute([$annPeriod['id']]);
                    
                    // Turn on this period + set timestamp
                    $conn->prepare("UPDATE assessment_periods SET results_announced = 1, show_leaderboard = 1, results_announced_at = NOW() WHERE id = ?")
                         ->execute([$annPeriod['id']]);
                    
                    $changes[] = [
                        'id' => $annPeriod['id'],
                        'name' => $annPeriod['name'],
                        'year' => $annPeriod['year'],
                        'from' => 'results_announced=0',
                        'to' => 'results_announced=1',
                        'to_label' => '🏆 ประกาศผลอัตโนมัติ',
                        'reason' => 'ถึงวันเวลาประกาศผล (' . date('d/m/Y H:i', strtotime($annPeriod['announcement_date'])) . ')'
                    ];
                    
                    logActivity($_SESSION['user_id'] ?? 0, 'auto_results_announced',
                        "ประกาศผลคะแนนรอบ \"{$annPeriod['name']}\" อัตโนมัติ — ถึงวันเวลาประกาศผล ({$annPeriod['announcement_date']})"
                    );
                }
            }
        } catch (Exception $e) {
            error_log("Auto-announce results error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        error_log("checkAndUpdatePeriodStatuses error: " . $e->getMessage());
    }
    
    return ['changes' => $changes, 'checked' => true];
}
?>
