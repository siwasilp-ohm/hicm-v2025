<?php
/**
 * AJAX Endpoint - Withdraw/Recall Auditor Evaluation Submission
 * Allows an auditor to "undo" their submission before the evaluation deadline
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

header('Content-Type: application/json');

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

$db = Database::getInstance()->getConnection();

// Verify auditor is assigned and has already submitted
$checkStmt = $db->prepare("
    SELECT ae.submitted_at, p.evaluation_end_date, p.status as period_status
    FROM assessment_evaluators ae
    JOIN assessments a ON ae.assessment_id = a.id
    JOIN assessment_periods p ON a.period_id = p.id
    WHERE ae.assessment_id = ? AND ae.user_id = ?
");
$checkStmt->execute([$assessmentId, $user['id']]);
$row = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลการมอบหมาย']);
    exit;
}

if ($row['submitted_at'] === null) {
    echo json_encode(['success' => false, 'message' => 'คุณยังไม่ได้ส่งผลการประเมินนี้']);
    exit;
}

// Check if period is completed (จบโครงการ) — auditors can still withdraw during 'closed' (ปิดรับสมัคร)
if ($row['period_status'] === 'completed') {
    echo json_encode(['success' => false, 'message' => 'รอบประเมินจบโครงการแล้ว ไม่สามารถยกเลิกการส่งได้']);
    exit;
}

// Check if evaluation deadline has passed
if ($row['evaluation_end_date']) {
    $deadline = strtotime($row['evaluation_end_date'] . ' 23:59:59');
    if ($deadline < time()) {
        echo json_encode(['success' => false, 'message' => 'เลยกำหนดประเมินแล้ว ไม่สามารถยกเลิกการส่งได้']);
        exit;
    }
}

try {
    $db->beginTransaction();

    // Clear this evaluator's submitted_at
    $stmt = $db->prepare("UPDATE assessment_evaluators SET submitted_at = NULL WHERE assessment_id = ? AND user_id = ?");
    $stmt->execute([$assessmentId, $user['id']]);

    // Revert assessment status back to under_review (since at least one evaluator hasn't submitted now)
    $stmt = $db->prepare("UPDATE assessments SET status = 'under_review', updated_at = NOW() WHERE id = ? AND status IN ('evaluated', 'under_review')");
    $stmt->execute([$assessmentId]);

    // Save milestone
    saveMilestone($assessmentId, 'auditor', 'ยกเลิกการส่งผลโดยกรรมการ (เรียกคืน)');

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => 'ยกเลิกการส่งเรียบร้อย คุณสามารถแก้ไขคะแนนและส่งใหม่ได้'
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
