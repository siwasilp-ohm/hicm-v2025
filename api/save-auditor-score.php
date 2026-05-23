<?php
/**
 * AJAX Endpoint - Save Auditor Score
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
$indicatorId = intval($_POST['indicator_id'] ?? 0);
$score = $_POST['score'] ?? null;
$comment = sanitizeInput($_POST['comment'] ?? '');

if (!$assessmentId || !$indicatorId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Verify auditor is assigned to this assessment & period is still editable
$db = Database::getInstance()->getConnection();
$checkStmt = $db->prepare("
    SELECT ae.user_id, p.status as period_status, p.evaluation_end_date
    FROM assessment_evaluators ae
    JOIN assessments a ON ae.assessment_id = a.id
    JOIN assessment_periods p ON a.period_id = p.id
    WHERE ae.assessment_id = ? AND ae.user_id = ?
");
$checkStmt->execute([$assessmentId, $user['id']]);
$assignCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (!$assignCheck) {
    echo json_encode(['success' => false, 'message' => 'Not assigned to this assessment']);
    exit;
}

// Block saving if period is completed (จบโครงการ) or deadline passed
// Note: 'closed' (ปิดรับสมัคร) only blocks company submissions — auditors can still evaluate
if ($assignCheck['period_status'] === 'completed') {
    echo json_encode(['success' => false, 'message' => 'รอบการประเมินนี้จบแล้ว ไม่สามารถแก้ไขได้']);
    exit;
}
if ($assignCheck['evaluation_end_date']) {
    $evalDeadline = strtotime($assignCheck['evaluation_end_date'] . ' 23:59:59');
    if ($evalDeadline < time()) {
        echo json_encode(['success' => false, 'message' => 'เลยกำหนดส่งผลประเมินแล้ว ไม่สามารถแก้ไขได้']);
        exit;
    }
}

$isNa = ($score === 'na') ? 1 : 0;
$actualScore = ($score === 'na') ? null : floatval($score);

$result = saveAuditorScore($assessmentId, $indicatorId, $actualScore, $comment, $user['id'], $isNa);

if ($result['success']) {
    // Return updated totals for UI feedback - pass user_id to get this auditor's scores
    $assessment = getAssessmentWithScores($assessmentId, $user['id']);
    $totalIndicators = 0;
    $totalEvaluated = 0;
    $pillarProgress = [];
    foreach ($assessment['pillars'] as $pillarCode => $pillar) {
        $pTotal = count($pillar['indicators']);
        $pEval = 0;
        foreach ($pillar['indicators'] as $ind) {
            $totalIndicators++;
            if ($ind['auditor_score'] !== null || $ind['auditor_is_na']) {
                $totalEvaluated++;
                $pEval++;
            }
        }
        $pillarProgress[$pillarCode] = [
            'evaluated' => $pEval,
            'total' => $pTotal,
            'percent' => $pTotal > 0 ? round(($pEval / $pTotal) * 100) : 0
        ];
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Saved',
        'totalEvaluated' => $totalEvaluated,
        'totalIndicators' => $totalIndicators,
        'percent' => round(($totalEvaluated / $totalIndicators) * 100),
        'pillarProgress' => $pillarProgress
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Error saving score']);
}
