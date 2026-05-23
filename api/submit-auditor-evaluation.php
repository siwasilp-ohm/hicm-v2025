<?php
/**
 * AJAX Endpoint - Submit Auditor Evaluation
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

header('Content-Type: application/json');

// Check authentication and role
if (!isLoggedIn() || !hasRole(ROLE_AUDITOR)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user = getCurrentUser();
$assessmentId = intval($_POST['assessment_id'] ?? 0);

if (!$assessmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing assessment ID']);
    exit;
}

// Verify auditor is assigned to this assessment
$db = Database::getInstance()->getConnection();
$checkStmt = $db->prepare("
    SELECT ae.submitted_at, p.evaluation_end_date, p.status as period_status
    FROM assessment_evaluators ae
    JOIN assessments a ON ae.assessment_id = a.id
    JOIN assessment_periods p ON a.period_id = p.id
    WHERE ae.assessment_id = ? AND ae.user_id = ?
");
$checkStmt->execute([$assessmentId, $user['id']]);
$evaluatorRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (!$evaluatorRow) {
    echo json_encode(['success' => false, 'message' => 'Not assigned to this assessment']);
    exit;
}

// Check deadline & period status for re-submit
// Note: 'closed' (ปิดรับสมัคร) only blocks company submissions — auditors can still evaluate/submit
$isClosed = ($evaluatorRow['period_status'] === 'completed');
$isDeadlinePassed = false;
if ($evaluatorRow['evaluation_end_date']) {
    $deadline = strtotime($evaluatorRow['evaluation_end_date'] . ' 23:59:59');
    $isDeadlinePassed = ($deadline < time());
}

// If already submitted, check if re-submit is allowed
$isResubmit = ($evaluatorRow['submitted_at'] !== null);
if ($isResubmit && ($isClosed || $isDeadlinePassed)) {
    echo json_encode(['success' => false, 'message' => 'เลยกำหนดประเมินแล้ว ไม่สามารถส่งใหม่ได้']);
    exit;
}

try {
    $db->beginTransaction();

    // Save final auditor milestone
    $milestoneMsg = $isResubmit ? 'ส่งการประเมินใหม่อีกครั้งโดยกรรมการ' : 'ส่งการประเมินโดยกรรมการ';
    $milestoneResult = saveMilestone($assessmentId, 'auditor', $milestoneMsg);
    if (!$milestoneResult['success']) {
        throw new Exception($milestoneResult['message']);
    }

    // Mark THIS evaluator as submitted (per-evaluator tracking)
    $stmt = $db->prepare("UPDATE assessment_evaluators SET submitted_at = NOW() WHERE assessment_id = ? AND user_id = ? AND submitted_at IS NULL");
    $stmt->execute([$assessmentId, $user['id']]);

    // Check if ALL assigned evaluators have now submitted
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) as submitted
        FROM assessment_evaluators 
        WHERE assessment_id = ?
    ");
    $stmt->execute([$assessmentId]);
    $evalStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $allSubmitted = ((int)$evalStatus['submitted'] >= (int)$evalStatus['total']);

    if ($allSubmitted) {
        // All evaluators done → mark assessment as fully 'evaluated'
        $stmt = $db->prepare("UPDATE assessments SET status = 'evaluated', evaluated_at = NOW(), updated_at = NOW(), evaluated_by = ? WHERE id = ?");
        $stmt->execute([$user['id'], $assessmentId]);
    } else {
        // Not all evaluators done → set to 'under_review' so other auditors can still see it
        $stmt = $db->prepare("UPDATE assessments SET status = 'under_review', updated_at = NOW() WHERE id = ? AND status = 'submitted'");
        $stmt->execute([$assessmentId]);
    }

    $db->commit();
    
    // Notify only when all evaluators are done
    if ($allSubmitted) {
        require_once __DIR__ . '/../includes/notification.php';
        notifyCompanyAssessmentCompleted($assessmentId);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $allSubmitted 
            ? 'การประเมินเสร็จสมบูรณ์ (กรรมการทุกท่านส่งผลแล้ว)' 
            : 'ส่งผลการประเมินเรียบร้อย (รอกรรมการท่านอื่นส่งผล ' . ($evalStatus['total'] - $evalStatus['submitted']) . ' ท่าน)',
        'redirect' => getBaseUrl() . '/pages/my-assessments.php',
        'all_submitted' => $allSubmitted,
        'submitted_count' => (int)$evalStatus['submitted'],
        'total_evaluators' => (int)$evalStatus['total']
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
