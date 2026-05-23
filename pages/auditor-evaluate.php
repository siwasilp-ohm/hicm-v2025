<?php
/**
 * HICM V2025 Assessment System - Auditor Evaluation Page
 * Professional design with hint mode and file preview
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();
requireRole(ROLE_AUDITOR);

$assessmentId = intval($_GET['id'] ?? 0);
$user = getCurrentUser();

if (!$assessmentId) {
    setFlashMessage('ไม่พบข้อมูลการประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/my-assessments.php');
}

$assessment = getAssessmentWithScores($assessmentId, $user['id']);
if (!$assessment) {
    setFlashMessage('ไม่พบข้อมูลการประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/my-assessments.php');
}

$db = Database::getInstance()->getConnection();
$checkStmt = $db->prepare("
    SELECT ae.submitted_at, p.evaluation_end_date, p.status as period_status
    FROM assessment_evaluators ae
    JOIN assessments a ON ae.assessment_id = a.id
    JOIN assessment_periods p ON a.period_id = p.id
    WHERE ae.assessment_id = ? AND ae.user_id = ?
");
$checkStmt->execute([$assessmentId, $user['id']]);
$evaluatorInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
if (!$evaluatorInfo) {
    setFlashMessage('คุณไม่ได้รับมอบหมายให้ประเมินรายการนี้', 'error');
    redirect(getBaseUrl() . '/pages/my-assessments.php');
}

// Determine if this evaluator already submitted & if editable
$hasSubmitted = ($evaluatorInfo['submitted_at'] !== null);
// 'closed' (ปิดรับสมัคร) = auditors can still evaluate; 'completed' (จบโครงการ) = fully locked
$evalIsClosed = ($evaluatorInfo['period_status'] === 'completed');
$evalDeadlinePassed = false;
if ($evaluatorInfo['evaluation_end_date']) {
    $evalDeadline = strtotime($evaluatorInfo['evaluation_end_date'] . ' 23:59:59');
    $evalDeadlinePassed = ($evalDeadline < time());
}
$canResubmit = ($hasSubmitted && !$evalIsClosed && !$evalDeadlinePassed);

// Fetch attachments
$attachments = [];
try {
    $stmt = $db->prepare("
        SELECT f.*, s.indicator_id 
        FROM attachments f
        JOIN assessment_scores s ON f.assessment_score_id = s.id
        WHERE s.assessment_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$assessmentId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $attachments[$row['indicator_id']][] = $row;
    }
} catch (Exception $e) {
    error_log("Fetch attachments error: " . $e->getMessage());
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $indicatorId = intval($_POST['indicator_id'] ?? 0);
    $score = floatval($_POST['score'] ?? 0);
    $comment = sanitizeInput($_POST['comment'] ?? '');
    $isNa = ($_POST['score'] ?? '') === 'na' ? 1 : 0;
    
    if ($indicatorId) {
        $result = saveAuditorScore($assessmentId, $indicatorId, $isNa ? null : $score, $comment, $user['id'], $isNa);
        if ($result['success']) {
            setFlashMessage('บันทึกคะแนนเรียบร้อย', 'success');
        }
        redirect(getBaseUrl() . '/pages/auditor-evaluate.php?id=' . $assessmentId . '#indicator-' . $indicatorId);
    }
}

$companyStmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
$companyStmt->execute([$assessment['company_id']]);
$company = $companyStmt->fetch(PDO::FETCH_ASSOC);

$scoreLevels = [
    '0' => ['label' => 'ไม่มีการดำเนินงาน', 'color' => '#EF4444'],
    '0.25' => ['label' => 'เริ่มดำเนินการ', 'color' => '#F97316'],
    '0.5' => ['label' => 'ดำเนินงานบางส่วน', 'color' => '#EAB308'],
    '0.75' => ['label' => 'ดำเนินงานครอบคลุม', 'color' => '#22C55E'],
    '1' => ['label' => 'ดำเนินงานยั่งยืน', 'color' => '#10B981']
];

$pillarColors = [
    'H1' => ['color' => '#10B981', 'bg' => 'linear-gradient(135deg, #10B981, #059669)', 'light' => '#D1FAE5'],
    'I2' => ['color' => '#3B82F6', 'bg' => 'linear-gradient(135deg, #3B82F6, #2563EB)', 'light' => '#DBEAFE'],
    'C3' => ['color' => '#F59E0B', 'bg' => 'linear-gradient(135deg, #F59E0B, #D97706)', 'light' => '#FEF3C7'],
    'M4' => ['color' => '#8B5CF6', 'bg' => 'linear-gradient(135deg, #8B5CF6, #7C3AED)', 'light' => '#EDE9FE']
];

$totalIndicators = 0;
$totalEvaluated = 0;
foreach ($assessment['pillars'] as $pillar) {
    foreach ($pillar['indicators'] as $ind) {
        $totalIndicators++;
        if ($ind['auditor_score'] !== null || $ind['auditor_is_na']) $totalEvaluated++;
    }
}
$progressPercent = $totalIndicators > 0 ? round($totalEvaluated / $totalIndicators * 100) : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประเมินผล - <?php echo htmlspecialchars($company['company_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        :root {
            --pro-primary: #0f172a;
            --pro-primary-light: #334155;
            --pro-primary-dark: #020617;
            --pro-accent: #3b82f6;
            --pro-success: #10b981;
            --pro-warning: #f59e0b;
            --pro-danger: #ef4444;
            --pro-info: #0ea5e9;
            --pro-bg: #f8fafc;
            --pro-card-bg: #ffffff;
            --pro-border: #e2e8f0;
            --pro-text-main: #1e293b;
            --pro-text-muted: #64748b;
            --pro-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --pro-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--pro-bg);
            color: var(--pro-text-main);
            font-family: 'Prompt', sans-serif;
        }

        /* ========== Pro Utility Classes ========== */
        .reveal {
            opacity: 0;
            transform: translateY(20px);
            animation: reveal-in 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes reveal-in {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pro-card {
            background: var(--pro-card-bg);
            border-radius: 20px;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        /* ========== Management Hero ========== */
        .management-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 24px;
            padding: 2.5rem 3rem;
            color: white;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        .management-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 65%;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #93c5fd;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hero-title {
            font-size: 2.25rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            letter-spacing: -0.02em;
            color: white;
            line-height: 1.2;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.25rem;
            font-size: 0.9rem;
            color: #94a3b8;
        }

        .hero-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hero-meta i {
            color: var(--pro-accent);
        }

        /* ========== Progress Summary Strip ========== */
        .progress-summary-strip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            padding: 1.25rem 2rem;
            background: white;
            border-radius: 20px;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
            margin-bottom: 2rem;
        }

        .strip-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-left: auto;
        }

        .save-status {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--pro-text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .save-status.saving {
            color: var(--pro-accent);
        }

        .save-status.saved {
            color: var(--pro-success);
        }

        .save-status.error {
            color: var(--pro-danger);
        }

        .strip-label {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--pro-primary);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .strip-label i {
            color: var(--pro-accent);
        }

        .pillar-pills {
            display: flex;
            gap: 1rem;
            flex: 1;
            overflow-x: auto;
            padding: 0.25rem 0;
            scrollbar-width: none;
        }

        .pillar-pills::-webkit-scrollbar { display: none; }

        .pillar-pill {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .pill-progress-bar {
            width: 40px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .pill-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .strip-total {
            text-align: right;
            padding-left: 1.5rem;
            border-left: 1px solid var(--pro-border);
        }

        .total-val {
            display: block;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--pro-accent);
            line-height: 1;
        }

        .total-lbl {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--pro-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        /* ========== Pillar Navigation ========== */
        .pillar-nav-pro {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding: 0.25rem 0;
            scrollbar-width: none;
        }

        .pillar-nav-pro::-webkit-scrollbar { display: none; }

        .pillar-btn-pro {
            flex: 1;
            min-width: 180px;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem 1.5rem;
            background: white;
            border-radius: 18px;
            border: 2px solid transparent;
            box-shadow: var(--pro-shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-align: left;
        }

        .pillar-btn-pro:hover {
            transform: translateY(-4px);
            box-shadow: var(--pro-shadow-lg);
            border-color: #cbd5e1;
        }

        .pillar-btn-pro.active {
            background: var(--pro-primary);
            color: white;
            border-color: var(--pro-accent);
        }

        .p-icon-pro {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .p-info-pro {
            flex: 1;
            min-width: 0;
        }

        .p-name-pro {
            display: block;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 0.15rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .p-status-pro {
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0.6;
        }

        /* ========== Evaluation Cards ========== */
        .indicator-card-pro {
            margin-bottom: 2.5rem;
        }

        .ind-header-pro {
            padding: 2rem 2.5rem;
            background: white;
            border-radius: 20px 20px 0 0;
            border: 1px solid var(--pro-border);
            display: flex;
            gap: 1.5rem;
            align-items: flex-start;
        }

        .ind-num-pro {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            flex-shrink: 0;
        }

        .ind-title-pro {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--pro-primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .ind-desc-pro {
            font-size: 1rem;
            color: var(--pro-text-muted);
            line-height: 1.6;
            margin: 0;
        }

        .ind-body-pro {
            padding: 2.5rem;
            background: white;
            border: 1px solid var(--pro-border);
            border-top: none;
            border-radius: 0 0 20px 20px;
        }

        /* ========== Criteria Grid ========== */
        .criteria-row-pro {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.75rem;
            margin-bottom: 2rem;
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 18px;
            border: 1px solid #f1f5f9;
        }

        body.criteria-hidden .criteria-row-pro { display: none; }

        .criteria-cell-pro {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .c-val-pro {
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .c-lbl-pro {
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
            line-height: 1.3;
        }

        /* ========== Company Response ========== */
        .company-panel-pro {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 18px;
            padding: 1.75rem 2rem;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .company-panel-pro.hidden-mode {
            filter: blur(12px);
            pointer-events: none;
            user-select: none;
        }

        .panel-header-pro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .panel-title-pro {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
            font-weight: 700;
            color: #0369a1;
        }

        .comp-score-badge-pro {
            background: white;
            padding: 0.65rem 1.25rem;
            border-radius: 14px;
            border: 2px solid #3b82f6;
            color: #1e40af;
            font-weight: 800;
            font-size: 1.2rem;
            box-shadow: 0 8px 16px -4px rgba(59, 130, 246, 0.2);
        }

        .comp-evidence-pro {
            background: white;
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            border: 1px solid #bae6fd;
            font-size: 0.95rem;
            line-height: 1.7;
            color: #334155;
            white-space: pre-wrap;
        }

        /* ========== Score Selection Radio Cards ========== */
        .score-box-pro {
            margin-bottom: 2rem;
        }

        .score-label-pro {
            display: block;
            font-weight: 700;
            color: var(--pro-primary);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .score-cards-pro {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.75rem;
        }

        .score-card-item {
            position: relative;
        }

        .score-card-item input {
            display: none;
        }

        .score-card-item label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 0.5rem;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100px;
        }

        .score-card-item label:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: var(--pro-shadow);
        }

        .score-card-item input:checked + label {
            border-color: var(--pro-accent);
            background: #eff6ff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .score-card-item.company-pick label::after {
            content: '\f005';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 6px;
            right: 8px;
            font-size: 0.8rem;
            color: #f59e0b;
        }

        .sc-val-pro {
            display: block;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .sc-lbl-pro {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--pro-text-muted);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ========== Comment Area ========== */
        .comment-area-pro {
            margin-top: 2rem;
        }

        .comment-area-pro label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            color: var(--pro-primary);
            margin-bottom: 0.75rem;
        }

        .comment-area-pro textarea {
            width: 100%;
            border: 2px solid #e2e8f0;
            border-radius: 18px;
            padding: 1.25rem;
            min-height: 120px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.2s;
            background: #fcfcfc;
        }

        .comment-area-pro textarea:focus {
            outline: none;
            border-color: var(--pro-accent);
            background: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        /* ========== Buttons & Modals ========== */
        .btn-pro {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.85rem 1.75rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }

        .btn-pro-primary {
            background: var(--pro-accent);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.39);
        }

        .btn-pro-primary:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-pro-outline {
            background: white;
            color: var(--pro-text-main);
            border: 1px solid var(--pro-border);
        }

        .btn-pro-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .pro-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            z-index: 1000;
            padding: 2rem;
            align-items: center;
            justify-content: center;
        }

        .pro-modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .pro-modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--pro-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }

        .pro-modal-header h3 {
            margin: 0;
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--pro-primary);
        }

        .close-pro {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid var(--pro-border);
            cursor: pointer;
            transition: all 0.2s;
        }

        .close-pro:hover {
            background: var(--pro-danger);
            color: white;
            border-color: var(--pro-danger);
        }

        .pro-modal-body {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #f8fafc;
        }

        .pro-modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--pro-border);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background: white;
        }

        /* ========== Controls Bar ========== */
        .controls-bar-pro {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 0 0.5rem;
        }

        .toggle-group-pro {
            display: flex;
            background: #f1f5f9;
            padding: 0.35rem;
            border-radius: 14px;
            gap: 0.25rem;
        }

        .toggle-item-pro {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }

        .toggle-item-pro.active {
            background: white;
            color: var(--pro-primary);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        /* ========== Pillar Sections Visibility ========== */
        .pillar-section {
            display: none;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.4s ease-out;
        }

        .pillar-section.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        /* ========== Toast Notification ========== */
        .toast-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .toast-pro {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-left: 4px solid var(--pro-accent);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--pro-text-main);
            transform: translateX(120%);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-width: 250px;
        }

        .toast-pro.show {
            transform: translateX(0);
        }

        .toast-pro.success { border-left-color: var(--pro-success); }
        .toast-pro.error { border-left-color: var(--pro-danger); }
        .toast-pro.info { border-left-color: var(--pro-info); }

        .toast-pro i {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: white;
        }

        .toast-pro.success i { background: var(--pro-success); }
        .toast-pro.error i { background: var(--pro-danger); }
        .toast-pro.info i { background: var(--pro-info); }

        /* ========== Submit Confirmation Modal ========== */
        .submit-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .submit-modal-content {
            background: white;
            border-radius: 28px;
            width: 100%;
            max-width: 550px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .submit-modal-header {
            padding: 2.5rem 2rem 1.5rem;
            text-align: center;
        }

        .submit-icon-circle {
            width: 80px;
            height: 80px;
            background: #eff6ff;
            color: var(--pro-accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }

        .submit-modal-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--pro-primary);
            margin-bottom: 0.5rem;
        }

        .submit-modal-subtitle {
            font-size: 1rem;
            color: var(--pro-text-muted);
            line-height: 1.5;
        }

        .submit-modal-body {
            padding: 0 2rem 2rem;
        }

        .submit-summary-pro {
            background: #f8fafc;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--pro-border);
        }

        .summary-header-pro {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            font-weight: 800;
            color: var(--pro-primary);
        }

        .pillar-summary-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .pillar-summary-row:last-child { margin-bottom: 0; }

        .ps-code {
            width: 32px;
            font-weight: 800;
            font-size: 0.85rem;
        }

        .ps-bar-wrap {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .ps-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .ps-count {
            width: 45px;
            text-align: right;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--pro-primary-light);
        }

        .submit-warning-card {
            background: #fffbeb;
            border-left: 4px solid #f97316;
            padding: 1rem;
            border-radius: 12px;
            display: flex;
            gap: 0.75rem;
            font-size: 0.85rem;
            color: #92400e;
            font-weight: 600;
        }

        .submit-modal-footer {
            padding: 1.5rem 2rem 2.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn-modal-pro {
            padding: 1rem;
            border-radius: 14px;
            font-weight: 800;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-modal-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-modal-cancel:hover { background: #e2e8f0; }

        .btn-modal-confirm {
            background: var(--pro-accent);
            color: white;
            box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4);
        }

        .btn-modal-confirm:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        /* ========== No Horizontal Scroll Constraints ========== */
        body.eval-page-body { overflow-x: hidden; }
        .main-content { max-width: 100%; overflow-x: hidden; }

        /* ========== Submitted Banner ========== */
        .submitted-banner {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #bbf7d0;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
        }

        .submitted-banner-icon {
            width: 44px;
            height: 44px;
            background: #dcfce7;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #16a34a;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .submitted-banner-content { flex: 1; }

        .submitted-banner-title {
            font-weight: 700;
            color: #15803d;
            font-size: 0.95rem;
        }

        .submitted-banner-detail {
            font-size: 0.8rem;
            color: #4ade80;
            margin-top: 0.15rem;
            font-weight: 500;
        }

        .submitted-banner-actions {
            flex-shrink: 0;
        }

        .btn-withdraw {
            background: white !important;
            color: #dc2626 !important;
            border: 1px solid #fecaca !important;
            box-shadow: none !important;
            font-size: 0.85rem !important;
            padding: 0.6rem 1.2rem !important;
        }

        .btn-withdraw:hover {
            background: #fef2f2 !important;
            border-color: #fca5a5 !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15) !important;
        }
        
        @media (max-width: 768px) {
            .management-hero { padding: 2rem; flex-direction: column; align-items: stretch; text-align: center; }
            .hero-content { max-width: 100%; }
            .hero-title { font-size: 1.75rem; }
            .hero-meta { justify-content: center; }
            .progress-summary-strip { flex-direction: column; align-items: stretch; }
            .strip-total { border-left: none; border-top: 1px solid var(--pro-border); padding-left: 0; padding-top: 1rem; text-align: center; }
            .score-cards-pro { grid-template-columns: repeat(3, 1fr); }
            .criteria-row-pro { grid-template-columns: repeat(2, 1fr); }
        }

        /* ========== Print Styles ========== */
        @media print {
            @page { size: A4; margin: 10mm 8mm; }

            /* Hide interactive elements */
            .submitted-banner, .strip-actions,
            .pillar-btn-pro, .pillar-nav-pro,
            .custom-modal-overlay, #submitEvalModal,
            #previewModal, .modal,
            button, [onclick], select,
            .score-cards-pro input,
            .criteria-row-pro, .criteria-grid-pro,
            .hint-toggle-bar {
                display: none !important;
            }

            /* Hero compact */
            .management-hero {
                background: #1e3a5f !important;
                color: white !important;
                padding: 1rem 1.5rem !important;
                border-radius: 0 !important;
                margin: 0 !important;
            }

            .management-hero, .management-hero * {
                color: white !important;
            }

            /* Progress strip */
            .progress-summary-strip {
                padding: 0.5rem !important;
                margin-bottom: 0.5rem !important;
            }

            /* Show all pillar sections */
            .pillar-section {
                display: block !important;
                page-break-inside: avoid;
            }

            /* Score cards: show values only */
            .score-cards-pro {
                display: grid !important;
                grid-template-columns: repeat(5, 1fr) !important;
                gap: 0.25rem !important;
            }

            .score-card-pro {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                padding: 0.5rem !important;
            }

            /* Indicator cards */
            .indicator-card-pro, .card-pro {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                page-break-inside: avoid;
                margin-bottom: 0.5rem !important;
            }

            .reveal {
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
            }

            a[href]::after { content: none !important; }
        }
    </style>
</head>
<body class="has-sidebar eval-page-body">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper eval-page">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>
            
            <!-- Management Hero -->
            <div class="management-hero reveal">
                <div class="hero-content">
                    <div class="hero-tag">
                        <i class="fas fa-clipboard-check"></i> Auditor Workspace
                    </div>
                    <h1 class="hero-title"><?php echo htmlspecialchars($company['company_name']); ?></h1>
                    <div class="hero-meta">
                        <span><i class="far fa-calendar-alt"></i> <?php echo $assessment['period_name']; ?> (<?php echo $assessment['year']; ?>)</span>
                        <span><i class="fas fa-industry"></i> <?php echo htmlspecialchars($company['industry_type'] ?? '-'); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['province'] ?? '-'); ?></span>
                    </div>
                </div>
                <!-- Replaced radial progress with something more compact if needed, but keeping the card logic -->
            </div>

            <?php if ($hasSubmitted): ?>
            <!-- Submitted Status Banner -->
            <div class="submitted-banner reveal" style="animation-delay: 0.05s;">
                <div class="submitted-banner-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="submitted-banner-content">
                    <div class="submitted-banner-title">คุณได้ส่งผลการประเมินนี้แล้ว</div>
                    <div class="submitted-banner-detail">
                        ส่งเมื่อ <?php echo formatDateTime($evaluatorInfo['submitted_at']); ?>
                        <?php if ($canResubmit): ?>
                            — คุณสามารถแก้ไขคะแนนและส่งใหม่ได้จนถึงวันปิดการประเมิน
                        <?php else: ?>
                            — ปิดรับการแก้ไขแล้ว
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canResubmit): ?>
                <div class="submitted-banner-actions">
                    <button type="button" class="btn-pro btn-withdraw" onclick="handleWithdraw()">
                        <i class="fas fa-undo"></i> ยกเลิกการส่ง
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- RELOCATED: Progress Summary Strip -->
            <div class="progress-summary-strip reveal" style="animation-delay: 0.1s;">
                <div class="strip-label">
                    <i class="fas fa-chart-line"></i> ความคืบหน้า
                </div>
                <div class="pillar-pills">
                    <?php foreach ($assessment['pillars'] as $pCode => $pillar): 
                        $pEval = 0;
                        $pTotal = count($pillar['indicators']);
                        foreach ($pillar['indicators'] as $ind) {
                            if ($ind['auditor_score'] !== null || $ind['auditor_is_na']) $pEval++;
                        }
                        $pPercent = $pTotal > 0 ? round($pEval / $pTotal * 100) : 0;
                        $pc = $pillarColors[$pCode];
                    ?>
                    <div class="pillar-pill" id="pill-<?php echo $pCode; ?>" data-pillar="<?php echo $pCode; ?>">
                        <span style="color: <?php echo $pc['color']; ?>;"><?php echo $pCode; ?></span>
                        <div class="pill-progress-bar">
                            <div class="pill-progress-fill" style="width: <?php echo $pPercent; ?>%; background: <?php echo $pc['bg']; ?>;"></div>
                        </div>
                        <span class="pill-count" style="font-size: 0.7rem; opacity: 0.7;"><?php echo $pEval; ?>/<?php echo $pTotal; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="strip-total">
                    <span class="total-val" id="globalProgressText"><?php echo $totalEvaluated; ?>/<?php echo $totalIndicators; ?></span>
                    <span class="total-lbl">ประเมินแล้ว</span>
                </div>
                
                <!-- NEW: Saving Indicator and Submit Button -->
                <div class="strip-actions">
                    <div id="saveStatus" class="save-status"></div>
                    <?php if ($hasSubmitted && !$canResubmit): ?>
                        <span class="btn-pro" style="background: #f1f5f9; color: #94a3b8; cursor: default; box-shadow: none;">
                            <i class="fas fa-lock"></i> ส่งผลแล้ว
                        </span>
                    <?php elseif ($hasSubmitted && $canResubmit): ?>
                        <button type="button" class="btn-pro" style="background: #f59e0b; color: white; box-shadow: 0 4px 14px rgba(245,158,11,0.3);" onclick="submitEvaluation()" id="btnSubmitEval">
                            <i class="fas fa-rotate"></i> ส่งใหม่อีกครั้ง
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-pro btn-pro-primary" onclick="submitEvaluation()" id="btnSubmitEval">
                            <i class="fas fa-paper-plane"></i> ส่งคะแนนทั้งหมด
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Controls & Pillar Nav Row -->
            <div class="controls-bar-pro reveal" style="animation-delay: 0.2s;">
                <div class="toggle-group-pro">
                    <div class="toggle-item-pro" id="hintToggleBtn" onclick="toggleHintMode(!isHintMode)">
                        <i class="fas fa-eye-slash"></i> ซ่อนคะแนนบริษัท
                    </div>
                    <div class="toggle-item-pro" id="criteriaToggleBtn" onclick="toggleCriteriaMode(!isCriteriaMode)">
                        <i class="fas fa-list-check"></i> ซ่อนเกณฑ์
                    </div>
                </div>
                <a href="<?php echo getBaseUrl(); ?>/pages/my-assessments.php" class="btn-pro btn-pro-outline">
                    <i class="fas fa-arrow-left"></i> กลับไปรายการของฉัน
                </a>
            </div>

            <!-- Pillar Navigation Pro -->
            <div class="pillar-nav-pro reveal" style="animation-delay: 0.3s;">
                <?php foreach ($assessment['pillars'] as $pillarCode => $pillar): 
                    $pc = $pillarColors[$pillarCode];
                    $pEval = 0;
                    foreach ($pillar['indicators'] as $ind) {
                        if ($ind['auditor_score'] !== null || $ind['auditor_is_na']) $pEval++;
                    }
                ?>
                <div class="pillar-btn-pro <?php echo $pillarCode === 'H1' ? 'active' : ''; ?>" 
                     data-pillar="<?php echo $pillarCode; ?>"
                     onclick="switchPillar('<?php echo $pillarCode; ?>')">
                    <div class="p-icon-pro" style="background: <?php echo $pc['light']; ?>; color: <?php echo $pc['color']; ?>;">
                        <?php if ($pillarCode === 'H1'): ?><i class="fas fa-heartbeat"></i>
                        <?php elseif ($pillarCode === 'I2'): ?><i class="fas fa-shield-alt"></i>
                        <?php elseif ($pillarCode === 'C3'): ?><i class="fas fa-users"></i>
                        <?php else: ?><i class="fas fa-chart-pie"></i><?php endif; ?>
                    </div>
                    <div class="p-info-pro">
                        <span class="p-name-pro"><?php echo $pillar['name']; ?></span>
                        <span class="p-status-pro" data-pillar="<?php echo $pillarCode; ?>"><?php echo $pEval; ?> / <?php echo count($pillar['indicators']); ?> เสร็จสิ้น</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="eval-layout">
                <div class="eval-main">
                    <!-- Pillar Sections -->
                    <?php foreach ($assessment['pillars'] as $pillarCode => $pillar): 
                        $pc = $pillarColors[$pillarCode];
                    ?>
                    <div class="pillar-section <?php echo $pillarCode === 'H1' ? 'active' : ''; ?>" id="section-<?php echo $pillarCode; ?>">
                        <?php foreach ($pillar['indicators'] as $index => $indicator): 
                            $selfScore = $indicator['self_score'];
                            $currentScore = $indicator['auditor_score'];
                            $hasEvaluated = $currentScore !== null || $indicator['auditor_is_na'];
                            $indAttachments = $attachments[$indicator['indicator_id']] ?? [];
                        ?>
                        <div class="indicator-card-pro pro-card reveal" id="indicator-<?php echo $indicator['indicator_id']; ?>">
                            <div class="ind-header-pro">
                                <div class="ind-num-pro" style="background: <?php echo $pc['bg']; ?>;">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="ind-info-pro">
                                    <div class="ind-title-pro">
                                        <?php echo $indicator['indicator_code']; ?>: <?php echo $indicator['indicator_name']; ?>
                                        <?php if ($hasEvaluated): ?>
                                        <span class="badge-evaluated" style="vertical-align: middle; margin-left: 10px;">
                                            <i class="fas fa-check-circle"></i> ประเมินแล้ว
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="ind-desc-pro"><?php echo $indicator['description']; ?></p>
                                </div>
                            </div>
                            
                            <div class="ind-body-pro">
                                <!-- Criteria Grid -->
                                <div class="criteria-row-pro">
                                    <?php foreach ($scoreLevels as $score => $level): 
                                        $criteriaKey = 'criteria_' . str_replace('.', '', $score);
                                        $criteriaText = $indicator[$criteriaKey] ?? $level['label'];
                                    ?>
                                    <div class="criteria-cell-pro">
                                        <div class="c-val-pro" style="color: <?php echo $level['color']; ?>;"><?php echo $score; ?></div>
                                        <div class="c-lbl-pro"><?php echo $criteriaText; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Company Response -->
                                <div class="company-panel-pro" data-company>
                                    <div class="panel-header-pro">
                                        <div class="panel-title-pro">
                                            <i class="fas fa-building"></i> ข้อมูลและหลักฐานจากบริษัท
                                        </div>
                                        <div class="comp-score-badge-pro">
                                            <?php echo number_format($selfScore * 100, 0); ?>%
                                            <span style="font-weight: 500; font-size: 0.8rem; color: #64748b; margin-left: 5px;">
                                                (<?php echo $selfScore; ?> คะแนน)
                                            </span>
                                        </div>
                                    </div>
                                    <div class="comp-evidence-pro"><?php echo !empty($indicator['self_evidence']) ? nl2br(htmlspecialchars($indicator['self_evidence'])) : '<i class="text-muted">ไม่มีคำอธิบายประกอบ</i>'; ?></div>
                                    
                                    <?php if (!empty($indAttachments)): ?>
                                    <div class="attachments-grid">
                                        <?php foreach ($indAttachments as $file): 
                                            $isImage = in_array(strtolower(pathinfo($file['file_original_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                            $isPdf = strtolower(pathinfo($file['file_original_name'], PATHINFO_EXTENSION)) === 'pdf';
                                            $previewUrl = getBaseUrl() . '/api/get-attachment.php?id=' . $file['id'];
                                        ?>
                                        <div class="attachment-card" onclick="openPreview('<?php echo htmlspecialchars($file['file_original_name']); ?>', '<?php echo $previewUrl; ?>', '<?php echo $isImage ? 'image' : ($isPdf ? 'pdf' : 'file'); ?>')">
                                            <div class="attachment-preview">
                                                <?php if ($isImage): ?>
                                                <img src="<?php echo $previewUrl; ?>" alt="Preview">
                                                <?php elseif ($isPdf): ?>
                                                <i class="fas fa-file-pdf fa-2x" style="color: #ef4444;"></i>
                                                <?php else: ?>
                                                <i class="fas fa-file-alt fa-2x" style="color: #64748b;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="attachment-name"><?php echo htmlspecialchars($file['file_original_name']); ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Auditor Score Selection -->
                                <div class="score-box-pro">
                                    <label class="score-label-pro"><i class="fas fa-tasks"></i> ประเมินคะแนนโดยกรรมการ</label>
                                    <div class="score-cards-pro">
                                        <?php foreach ($scoreLevels as $score => $level): 
                                            $isCompanyPick = (float)$selfScore === (float)$score;
                                            $isAuditorPick = $hasEvaluated && !$indicator['auditor_is_na'] && (float)$currentScore === (float)$score;
                                        ?>
                                        <div class="score-card-item <?php echo $isCompanyPick ? 'company-pick' : ''; ?>" data-company-pick>
                                            <input type="radio" name="score_<?php echo $indicator['indicator_id']; ?>" 
                                                   id="s_<?php echo $indicator['indicator_id']; ?>_<?php echo str_replace('.', '_', $score); ?>"
                                                   value="<?php echo $score; ?>" <?php echo $isAuditorPick ? 'checked' : ''; ?>
                                                   onchange="autoSave(<?php echo $indicator['indicator_id']; ?>)">
                                            <label for="s_<?php echo $indicator['indicator_id']; ?>_<?php echo str_replace('.', '_', $score); ?>">
                                                <span class="sc-val-pro" style="color: <?php echo $level['color']; ?>;"><?php echo $score; ?></span>
                                                <span class="sc-lbl-pro"><?php echo $level['label']; ?></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                        <!-- N/A Option - Only show if indicator allows N/A -->
                                        <?php if (!empty($indicator['allow_na'])): ?>
                                        <div class="score-card-item">
                                            <input type="radio" name="score_<?php echo $indicator['indicator_id']; ?>" 
                                                   id="s_<?php echo $indicator['indicator_id']; ?>_na"
                                                   value="na" <?php echo $indicator['auditor_is_na'] ? 'checked' : ''; ?>
                                                   onchange="autoSave(<?php echo $indicator['indicator_id']; ?>)">
                                            <label for="s_<?php echo $indicator['indicator_id']; ?>_na">
                                                <span class="sc-val-pro" style="color: #94a3b8;">N/A</span>
                                                <span class="sc-lbl-pro">ไม่เข้าเกณฑ์</span>
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="comment-area-pro">
                                    <label><i class="far fa-comment-dots"></i> ความคิดเห็นกรรมการ</label>
                                    <textarea name="comment_<?php echo $indicator['indicator_id']; ?>" 
                                              placeholder="ระบุเหตุผลหรือข้อเสนอแนะสำหรับการประเมินครั้งนี้..."
                                              onblur="autoSave(<?php echo $indicator['indicator_id']; ?>)"><?php echo htmlspecialchars($indicator['auditor_comment'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Removed old bottom summary section because it's now at the top -->
        </div>
    </main>
    
    <!-- Pro Preview Modal -->
    <div class="pro-modal-overlay" id="previewModal">
        <div class="pro-modal-content">
            <div class="pro-modal-header">
                <h3 id="previewTitle">File Preview</h3>
                <button type="button" class="close-pro" onclick="closePreview()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="pro-modal-body" id="previewBody" style="display: flex; align-items: center; justify-content: center; min-height: 400px;">
                <!-- Content will be injected here -->
            </div>
            <div class="pro-modal-footer">
                <a href="#" id="downloadLink" class="btn-pro btn-pro-primary" target="_blank">
                    <i class="fas fa-download"></i> ดาวน์โหลดไฟล์
                </a>
            </div>
        </div>
    </div>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Submit Confirmation Modal -->
    <div class="submit-modal-overlay" id="submitEvalModal">
        <div class="submit-modal-content">
            <div class="submit-modal-header">
                <div class="submit-icon-circle">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <h2 class="submit-modal-title">ยืนยันการส่งผล</h2>
                <p class="submit-modal-subtitle">กรุณาตรวจสอบความถูกต้องก่อนการส่งผล<br>ข้อมูลจะถูกบันทึกเป็น Milestone และไม่สามารถแก้ไขได้</p>
            </div>
            
            <div class="submit-modal-body">
                <div class="submit-summary-pro" id="submitModalSummary">
                    <div class="summary-header-pro">
                        <span>รายการที่ประเมินแล้ว</span>
                        <span id="modalTotalProgress">0/0</span>
                    </div>
                    <div id="modalPillarList">
                        <!-- Dynamic Pillar Rows -->
                    </div>
                </div>
                
                <div class="submit-warning-card">
                    <i class="fas fa-exclamation-triangle" style="margin-top: 2px;"></i>
                    <span>หากส่งแล้ว สถานะจะเปลี่ยนเป็น "ประเมินแล้ว" และจะส่งไปยังขั้นตอนการพิจารณาถัดไป</span>
                </div>
            </div>
            
            <div class="submit-modal-footer">
                <button type="button" class="btn-modal-pro btn-modal-cancel" onclick="closeSubmitModal()">ยกเลิก</button>
                <button type="button" class="btn-modal-pro btn-modal-confirm" id="btnModalConfirm" onclick="handleFinalSubmit()">ยืนยันและส่งผล</button>
            </div>
        </div>
    </div>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
    let isHintMode = localStorage.getItem('auditorHintMode') === 'true';
    let isCriteriaMode = localStorage.getItem('auditorCriteriaMode') === 'true';
    const assessmentId = <?php echo $assessmentId; ?>;

    // Toast Notification System
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast-pro ${type}`;
        
        const icon = type === 'success' ? 'fa-check' : (type === 'error' ? 'fa-exclamation' : 'fa-info');
        
        toast.innerHTML = `
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        // Force reflow
        toast.offsetHeight;
        
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 600);
        }, 3000);
    }

    // Auto Save functionality
    function autoSave(indicatorId) {
        const scoreEl = document.querySelector(`input[name="score_${indicatorId}"]:checked`);
        const commentEl = document.querySelector(`textarea[name="comment_${indicatorId}"]`);
        
        if (!scoreEl) return;

        const score = scoreEl.value;
        const comment = commentEl ? commentEl.value : '';
        const statusEl = document.getElementById('saveStatus');
        
        statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';
        statusEl.classList.add('saving');

        const formData = new FormData();
        formData.append('assessment_id', assessmentId);
        formData.append('indicator_id', indicatorId);
        formData.append('score', score);
        formData.append('comment', comment);

        fetch('<?php echo getBaseUrl(); ?>/api/save-auditor-score.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusEl.innerHTML = '<i class="fas fa-check-circle"></i> บันทึกเรียบร้อย';
                statusEl.classList.remove('saving');
                statusEl.classList.add('saved');
                
                // Update Badge in card
                const card = document.getElementById(`indicator-${indicatorId}`);
                if (card) {
                    let badge = card.querySelector('.badge-evaluated');
                    if (!badge) {
                        const title = card.querySelector('.ind-title-pro');
                        badge = document.createElement('span');
                        badge.className = 'badge-evaluated';
                        badge.style.verticalAlign = 'middle';
                        badge.style.marginLeft = '10px';
                        badge.innerHTML = '<i class="fas fa-check-circle"></i> ประเมินแล้ว';
                        title.appendChild(badge);
                    }
                }

                // Update Progress Summary
                document.getElementById('globalProgressText').textContent = `${data.totalEvaluated}/${data.totalIndicators}`;
                
                // Update Pillar Nav counters
                if (data.pillarProgress) {
                    Object.keys(data.pillarProgress).forEach(pillarCode => {
                        const pp = data.pillarProgress[pillarCode];
                        // Update pill count in progress strip
                        const pillCount = document.querySelector(`.pillar-pill[data-pillar="${pillarCode}"] .pill-count`);
                        if (pillCount) {
                            pillCount.textContent = `${pp.evaluated}/${pp.total}`;
                        }
                        // Update pill progress bar
                        const pillFill = document.querySelector(`.pillar-pill[data-pillar="${pillarCode}"] .pill-progress-fill`);
                        if (pillFill) {
                            pillFill.style.width = `${pp.percent}%`;
                        }
                        // Update pillar button status text
                        const statusPro = document.querySelector(`.p-status-pro[data-pillar="${pillarCode}"]`);
                        if (statusPro) {
                            statusPro.textContent = `${pp.evaluated} / ${pp.total} เสร็จสิ้น`;
                        }
                    });
                }
                
                // Show Toast Notification
                showToast('บันทึกคะแนนเรียบร้อยแล้ว', 'success');
                
                setTimeout(() => {
                    statusEl.innerHTML = '';
                    statusEl.classList.remove('saved');
                }, 2000);
            } else {
                statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> บันทึกผิดพลาด';
                statusEl.classList.add('error');
                console.error('Save error:', data.message);
            }
        })
        .catch(err => {
            statusEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> ข้อผิดพลาดเครือข่าย';
            console.error('Network error:', err);
        });
    }

    function openSubmitModal() {
        const modal = document.getElementById('submitEvalModal');
        const modalTotal = document.getElementById('modalTotalProgress');
        const modalPillarList = document.getElementById('modalPillarList');
        
        // Use existing counters
        const totalEvaluated = document.getElementById('globalProgressText').textContent;
        modalTotal.textContent = totalEvaluated;
        
        // Collect pillar data from the UI
        modalPillarList.innerHTML = '';
        document.querySelectorAll('.pillar-pill').forEach(pill => {
            const code = pill.querySelector('span:nth-child(1)').textContent;
            const count = pill.querySelector('.pill-count').textContent;
            const fill = pill.querySelector('.pill-progress-fill');
            const percent = fill.style.width;
            const color = fill.style.background;
            
            const row = document.createElement('div');
            row.className = 'pillar-summary-row';
            row.innerHTML = `
                <div class="ps-code" style="color: ${pill.querySelector('span:nth-child(1)').style.color}">${code}</div>
                <div class="ps-bar-wrap">
                    <div class="ps-bar-fill" style="width: ${percent}; background: ${color}"></div>
                </div>
                <div class="ps-count">${count}</div>
            `;
            modalPillarList.appendChild(row);
        });
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeSubmitModal() {
        document.getElementById('submitEvalModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    function submitEvaluation() {
        // Now opens the custom modal instead of confirm()
        openSubmitModal();
    }

    function handleFinalSubmit() {
        const btn = document.getElementById('btnModalConfirm');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังส่ง...';

        const formData = new FormData();
        formData.append('assessment_id', assessmentId);

        fetch('<?php echo getBaseUrl(); ?>/api/submit-auditor-evaluation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - keep button disabled and redirect
                btn.innerHTML = '<i class="fas fa-check"></i> ส่งสำเร็จ';
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 800);
            } else {
                alert('เกิดข้อผิดพลาด: ' + data.message);
                btn.disabled = false;
                btn.innerHTML = 'ยืนยันและส่งผล';
            }
        })
        .catch(err => {
            alert('ข้อผิดพลาดเครือข่าย');
            btn.disabled = false;
            btn.innerHTML = 'ยืนยันและส่งผล';
        });
    }

    // Pillar Navigation
    function switchPillar(pillarCode) {
        document.querySelectorAll('.pillar-btn-pro').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.pillar === pillarCode) {
                btn.classList.add('active');
            }
        });

        document.querySelectorAll('.pillar-section').forEach(section => {
            section.classList.remove('active');
            if (section.id === 'section-' + pillarCode) {
                section.classList.add('active');
            }
        });
    }
    
    // Hint Mode
    function toggleHintMode(enabled) {
        isHintMode = enabled;
        document.querySelectorAll('[data-company]').forEach(el => el.classList.toggle('hidden-mode', enabled));
        document.querySelectorAll('[data-company-pick]').forEach(el => {
            if (enabled) {
                el.classList.remove('company-pick');
            } else {
                const wasCompanyPick = el.getAttribute('data-original-pick') === 'true';
                if (wasCompanyPick) el.classList.add('company-pick');
            }
        });
        
        const btn = document.getElementById('hintToggleBtn');
        if (enabled) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-eye"></i> แสดงคะแนนบริษัท';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> ซ่อนคะแนนบริษัท';
        }
        
        localStorage.setItem('auditorHintMode', enabled);
    }

    function toggleCriteriaMode(enabled) {
        isCriteriaMode = enabled;
        document.body.classList.toggle('criteria-hidden', enabled);
        
        const btn = document.getElementById('criteriaToggleBtn');
        if (enabled) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-list"></i> แสดงเกณฑ์';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-list-check"></i> ซ่อนเกณฑ์';
        }
        
        localStorage.setItem('auditorCriteriaMode', enabled);
    }
    
    // Store original company picks
    document.querySelectorAll('.company-pick').forEach(el => {
        el.setAttribute('data-original-pick', 'true');
    });
    
    // Initialize modes from storage
    if (isHintMode) toggleHintMode(true);
    if (isCriteriaMode) toggleCriteriaMode(true);
    
    // Preview Modal
    function openPreview(name, url, type) {
        const modal = document.getElementById('previewModal');
        const body = document.getElementById('previewBody');
        const title = document.getElementById('previewTitle');
        const download = document.getElementById('downloadLink');
        
        title.textContent = name;
        download.href = url;
        
        if (type === 'image') {
            body.innerHTML = `<img src="${url}" alt="${name}" style="max-width: 100%; max-height: 70vh; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">`;
        } else if (type === 'pdf') {
            body.innerHTML = `<iframe src="${url}" style="width: 100%; height: 70vh; border: none; border-radius: 12px;"></iframe>`;
        } else {
            body.innerHTML = `
                <div style="text-align: center; padding: 3rem;">
                    <div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                        <i class="fas fa-file-alt fa-3x" style="color: #94a3b8;"></i>
                    </div>
                    <p style="color: #64748b; font-weight: 600;">ไม่สามารถแสดงตัวอย่างไฟล์นี้ได้<br><span style="font-weight: 400; font-size: 0.9rem;">กรุณาดาวน์โหลดเพื่อเปิดดูในเครื่องของคุณ</span></p>
                </div>`;
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closePreview() {
        document.getElementById('previewModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('previewModal');
        if (e.target === modal) closePreview();
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePreview();
    });

    // Withdraw (recall) submitted evaluation
    function handleWithdraw() {
        if (!confirm('คุณต้องการยกเลิกการส่งผลการประเมินนี้หรือไม่?\n\nคะแนนที่คุณให้ไว้จะยังคงอยู่ แต่สถานะจะกลับเป็น "รอประเมิน" และคุณสามารถแก้ไขแล้วส่งใหม่ได้')) return;

        const formData = new FormData();
        formData.append('assessment_id', assessmentId);

        fetch('<?php echo getBaseUrl(); ?>/api/withdraw-auditor-evaluation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload to reflect withdrawn state
                window.location.reload();
            } else {
                alert('เกิดข้อผิดพลาด: ' + data.message);
            }
        })
        .catch(err => {
            alert('ข้อผิดพลาดเครือข่าย: ไม่สามารถยกเลิกการส่งได้');
        });
    }
    </script>
</body>
</html>
