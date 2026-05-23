<?php
/**
 * HICM V2025 Assessment System - Assessment Form Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireRole(ROLE_COMPANY);

$user = getCurrentUser();

// Allow specifying period via URL parameter (for advance periods)
$requestedPeriodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;

// Get or create assessment
$assessmentResult = getOrCreateAssessment($user['company_id'], $requestedPeriodId);
if (!$assessmentResult['success']) {
    // ── Render professional "no active period" page ──
    $pageTitle = 'แบบประเมิน - ' . APP_NAME;
    $alertMessage = $assessmentResult['message'];
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
        <style>
            .no-period-wrapper {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
            .no-period-card {
                background: #fff;
                border-radius: 24px;
                box-shadow: 0 20px 60px rgba(30,58,95,0.10), 0 4px 16px rgba(0,0,0,0.04);
                max-width: 560px;
                width: 100%;
                text-align: center;
                padding: 3.5rem 3rem 3rem;
                position: relative;
                overflow: hidden;
            }
            .no-period-card::before {
                content: '';
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 5px;
                background: linear-gradient(90deg, #F59E0B, #EF4444, #F59E0B);
            }
            .no-period-icon-wrap {
                width: 100px; height: 100px;
                border-radius: 50%;
                background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 1.75rem;
                box-shadow: 0 8px 24px rgba(245,158,11,0.18);
                animation: pulse-glow 2.5s ease-in-out infinite;
            }
            @keyframes pulse-glow {
                0%, 100% { box-shadow: 0 8px 24px rgba(245,158,11,0.18); }
                50%      { box-shadow: 0 8px 36px rgba(245,158,11,0.32); }
            }
            .no-period-icon-wrap svg {
                width: 48px; height: 48px;
                color: #D97706;
            }
            .no-period-title {
                font-family: 'Prompt', sans-serif;
                font-size: 1.5rem;
                font-weight: 700;
                color: #1e293b;
                margin: 0 0 0.75rem;
            }
            .no-period-desc {
                font-family: 'Prompt', sans-serif;
                font-size: 0.95rem;
                color: #64748b;
                line-height: 1.7;
                margin: 0 0 2rem;
            }
            .no-period-info {
                background: #FFFBEB;
                border: 1px solid #FDE68A;
                border-radius: 12px;
                padding: 1rem 1.25rem;
                margin-bottom: 2rem;
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                text-align: left;
            }
            .no-period-info svg {
                width: 20px; height: 20px;
                color: #D97706;
                flex-shrink: 0;
                margin-top: 2px;
            }
            .no-period-info p {
                font-family: 'Prompt', sans-serif;
                font-size: 0.85rem;
                color: #92400E;
                margin: 0;
                line-height: 1.6;
            }
            .no-period-actions {
                display: flex;
                gap: 0.75rem;
                justify-content: center;
                flex-wrap: wrap;
            }
            .no-period-btn {
                font-family: 'Prompt', sans-serif;
                font-weight: 600;
                font-size: 0.9rem;
                padding: 0.7rem 1.5rem;
                border-radius: 12px;
                border: none;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                transition: all 0.2s ease;
                text-decoration: none;
            }
            .no-period-btn-primary {
                background: linear-gradient(135deg, #1e3a5f, #0369a1);
                color: #fff;
                box-shadow: 0 4px 12px rgba(30,58,95,0.25);
            }
            .no-period-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(30,58,95,0.35);
            }
            .no-period-btn-secondary {
                background: #f1f5f9;
                color: #475569;
                border: 1px solid #e2e8f0;
            }
            .no-period-btn-secondary:hover {
                background: #e2e8f0;
            }
            .no-period-btn svg { width: 18px; height: 18px; }
            .no-period-footer {
                margin-top: 2rem;
                font-family: 'Prompt', sans-serif;
                font-size: 0.78rem;
                color: #94a3b8;
            }
        </style>
    </head>
    <body>
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="no-period-wrapper">
                <div class="no-period-card">
                    <!-- Icon -->
                    <div class="no-period-icon-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                        </svg>
                    </div>

                    <!-- Title & Description -->
                    <h1 class="no-period-title">ไม่พบรอบการประเมินที่เปิดอยู่</h1>
                    <p class="no-period-desc">
                        ขณะนี้ยังไม่มีรอบการประเมินที่เปิดรับข้อมูล<br>
                        กรุณารอการประกาศรอบการประเมินจากผู้ดูแลระบบ
                    </p>

                    <!-- Info Box -->
                    <div class="no-period-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p>
                            เมื่อผู้ดูแลระบบเปิดรอบการประเมินใหม่ ท่านจะสามารถเข้ามากรอกแบบประเมินได้ทันที
                            โดยระบบจะแจ้งเตือนผ่านหน้าแดชบอร์ดอัตโนมัติ
                        </p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="no-period-actions">
                        <a href="<?php echo getBaseUrl(); ?>/pages/dashboard.php" class="no-period-btn no-period-btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            กลับหน้าแดชบอร์ด
                        </a>
                        <a href="<?php echo getBaseUrl(); ?>/pages/my-assessments.php" class="no-period-btn no-period-btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 0 0 2.25 2.25h.75" />
                            </svg>
                            ดูประวัติการประเมิน
                        </a>
                    </div>

                    <!-- Footer -->
                    <div class="no-period-footer">
                        HICM V2025 Assessment System &middot; <?php echo date('d/m/Y H:i'); ?>
                    </div>
                </div>
            </div>
        </div>

        <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    </body>
    </html>
    <?php
    exit;
}

$assessment = getAssessmentWithScores($assessmentResult['assessment']['id']);

// ตรวจสอบสถานะรอบและกำหนดส่ง
$periodStatus = $assessment['period_status'] ?? 'open';
$submissionDeadline = $assessment['submission_deadline'] ?? $assessment['end_date'] ?? null;
$isPeriodClosed = !in_array($periodStatus, ['open', 'evaluating']);
$isDeadlinePassed = $submissionDeadline && (date('Y-m-d') > $submissionDeadline);
$isReadOnly = $isPeriodClosed || $isDeadlinePassed;

// Track if company has already submitted (for resubmit UI)
$assessmentStatus = $assessment['status'] ?? 'draft';
$hasSubmitted = in_array($assessmentStatus, ['submitted', 'under_review', 'evaluated']);
$canResubmit = $hasSubmitted && !$isReadOnly;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block all submissions if period is closed or deadline passed
    if ($isReadOnly) {
        $msg = $isPeriodClosed 
            ? 'รอบการประเมินนี้ปิดแล้ว ไม่สามารถบันทึกได้' 
            : 'เลยกำหนดส่ง (' . date('d/m/Y', strtotime($submissionDeadline)) . ') ไม่สามารถบันทึกได้';
        
        if (isset($_POST['save_score']) || (isset($_POST['submit_assessment']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']))) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        setFlashMessage($msg, 'error');
        redirect(getBaseUrl() . '/pages/assessment-form.php');
    }
    
    if (isset($_POST['save_score'])) {
        $indicatorId = intval($_POST['indicator_id']);
        $score = floatval($_POST['score']);
        $evidence = sanitizeInput($_POST['evidence'] ?? '');
        $isNa = intval($_POST['is_na'] ?? 0);
        
        $result = saveSelfScore($assessment['id'], $indicatorId, $score, $evidence, $isNa);
        
        if ($result['success']) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
        exit;
    }
    
    // Save Milestone/Checkpoint
    if (isset($_POST['save_milestone'])) {
        $note = sanitizeInput($_POST['milestone_note'] ?? '');
        $result = saveMilestone($assessment['id'], 'self', $note);
        
        if (isset($_POST['ajax']) && $_POST['ajax']) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result);
            exit;
        }
        
        if ($result['success']) {
            setFlashMessage($result['message'], 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        // Refresh assessment data
        $assessment = getAssessmentWithScores($assessment['id']);
    }
    
    if (isset($_POST['submit_assessment'])) {
        // Check if it's an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        $result = submitAssessment($assessment['id']);
        
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            if ($result['success']) {
                // Recalculate scores first to ensure we have latest
                recalculateAssessmentScore($assessment['id']);
                
                // Get updated assessment data from database
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT self_total_score, hicm_level FROM assessments WHERE id = ?");
                $stmt->execute([$assessment['id']]);
                $assessmentData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $totalScore = $assessmentData['self_total_score'] ?? 0;
                $level = $assessmentData['hicm_level'] ?? 1;
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'ส่งแบบประเมินเรียบร้อยแล้ว',
                    'score' => round($totalScore),
                    'level' => (int)$level
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
            }
            exit;
        }
        
        if ($result['success']) {
            setFlashMessage('ส่งแบบประเมินเรียบร้อยแล้ว', 'success');
            redirect(getBaseUrl() . '/pages/dashboard.php');
        } else {
            setFlashMessage($result['message'], 'error');
        }
    }
}

// Get milestones for this assessment
$milestones = getMilestones($assessment['id'], 'self');

// Score levels
$scoreLevels = [
    '0' => ['label' => 'ไม่มีการดำเนินงาน', 'description' => 'ไม่มีนโยบาย ไม่มีการดำเนินการใดๆ'],
    '0.25' => ['label' => 'เริ่มดำเนินการ', 'description' => 'มีนโยบายหรือแผนเบื้องต้น'],
    '0.5' => ['label' => 'ดำเนินงานบางส่วน', 'description' => 'มีการดำเนินงานและหลักฐานประกอบ'],
    '0.75' => ['label' => 'ดำเนินงานครอบคลุม', 'description' => 'มีระบบติดตามและประเมินผล'],
    '1' => ['label' => 'ดำเนินงานยั่งยืน', 'description' => 'มีการพัฒนาอย่างต่อเนื่อง']
];

// Pillar colors
$pillarColors = [
    'H1' => ['color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => 'heart-pulse'],
    'I2' => ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'icon' => 'shield-check'],
    'C3' => ['color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => 'users'],
    'M4' => ['color' => '#8B5CF6', 'bg' => '#EDE9FE', 'icon' => 'chart-bar']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบประเมิน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        /* Spinner animation */
        .animate-spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .pillar-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }
        
        .pillar-tab {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-xl);
            border: 2px solid transparent;
            background: white;
            cursor: pointer;
            transition: all var(--transition-fast);
            white-space: nowrap;
            box-shadow: var(--shadow-sm);
        }
        
        .pillar-tab:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .pillar-tab.active {
            border-color: currentColor;
        }
        
        .pillar-tab-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pillar-tab-info {
            display: flex;
            flex-direction: column;
        }
        
        .pillar-tab-name {
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .pillar-tab-score {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .indicator-card {
            animation: fadeInUp 0.3s ease-out;
        }
        
        .score-option label {
            padding: 1rem;
            min-width: 100px;
        }
        
        .score-option .score-value {
            font-size: 1.5rem;
        }
        
        .sticky-summary {
            position: sticky;
            top: 80px;
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-ring-circle {
            transition: stroke-dashoffset 0.5s ease;
        }
        
            .file-item-actions {
                display: flex;
                gap: 0.5rem;
            }
            
            .btn-view-file {
                color: var(--primary-600);
                background: var(--primary-50);
                border: 1px solid var(--primary-200);
                padding: 0.25rem 0.5rem;
                border-radius: var(--radius-md);
                font-size: 0.75rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.25rem;
            }
            
            .btn-view-file:hover {
                background: var(--primary-100);
            }

            /* File Upload & Items */
            .file-upload {
                border: 2px dashed var(--gray-300);
                border-radius: var(--radius-xl);
                padding: 2rem;
                text-align: center;
                background: var(--gray-50);
                cursor: pointer;
                transition: all var(--transition-base);
                position: relative;
                margin-bottom: 1rem;
            }

            .file-upload:hover, .file-upload.dragover {
                border-color: var(--primary-500);
                background: var(--primary-50);
            }

            .file-upload input[type="file"] {
                position: absolute;
                inset: 0;
                opacity: 0;
                cursor: pointer;
            }

            .file-upload-icon {
                width: 48px;
                height: 48px;
                color: var(--gray-400);
                margin: 0 auto 1rem;
                display: block;
            }

            .file-upload:hover .file-upload-icon {
                color: var(--primary-500);
                transform: translateY(-2px);
            }

            .file-upload-text {
                font-weight: 500;
                color: var(--gray-700);
                margin-bottom: 0.25rem;
            }

            .file-upload-hint {
                font-size: 0.75rem;
                color: var(--gray-500);
            }

            .file-list {
                margin-top: 1rem;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .file-item {
                display: flex;
                align-items: center;
                padding: 0.75rem 1rem;
                background: white;
                border: 1px solid var(--gray-200);
                border-radius: var(--radius-lg);
                gap: 1rem;
                transition: all var(--transition-base);
            }

            .file-item:hover {
                box-shadow: var(--shadow-sm);
                border-color: var(--primary-200);
            }

            .file-item-icon {
                width: 40px;
                height: 40px;
                background: var(--gray-50);
                color: var(--gray-500);
                border-radius: var(--radius-md);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .file-item-info {
                flex: 1;
                min-width: 0;
            }

            .file-item-name {
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--gray-900);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .file-item-size {
                font-size: 0.75rem;
                color: var(--gray-500);
            }

            .btn-delete-file {
                color: var(--gray-400);
                background: transparent;
                border: none;
                padding: 0.25rem;
                border-radius: var(--radius-md);
                transition: all var(--transition-fast);
            }

            .btn-delete-file:hover {
                color: var(--danger);
                background: var(--danger-light);
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(5px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .animate-fade-in {
                animation: fadeIn 0.3s ease-out;
            }

            /* Custom Confirm Modal */
            .custom-modal-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.6);
                backdrop-filter: blur(4px);
                z-index: 10000;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.2s ease-out;
            }
            
            .custom-modal {
                background: white;
                width: 90%;
                max-width: 400px;
                border-radius: var(--radius-2xl);
                padding: 2rem;
                text-align: center;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                transform: scale(0.95);
                transition: transform 0.2s ease-out;
            }
            
            .custom-modal-overlay.active {
                display: flex;
            }
            
            .custom-modal-overlay.active .custom-modal {
                transform: scale(1);
            }
            
            .custom-modal-icon {
                width: 64px;
                height: 64px;
                background: #FEE2E2;
                color: #EF4444;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
            }
            
            .custom-modal-title {
                font-size: 1.25rem;
                font-weight: 700;
                color: var(--gray-900);
                margin-bottom: 0.5rem;
            }
            
            .custom-modal-message {
                color: var(--gray-600);
                margin-bottom: 2rem;
                line-height: 1.5;
            }
            
            .custom-modal-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .btn-confirm-cancel {
                background: var(--gray-100);
                color: var(--gray-700);
                border: none;
                padding: 0.75rem;
                border-radius: var(--radius-xl);
                font-weight: 600;
                cursor: pointer;
            }
            
            .btn-confirm-delete {
                background: #EF4444;
                color: white;
                border: none;
                padding: 0.75rem;
                border-radius: var(--radius-xl);
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
            }
            
            .btn-confirm-delete:hover {
                background: #DC2626;
            }

            .btn-confirm-submit {
                background: var(--primary-600);
                color: white;
                border: none;
                padding: 0.75rem;
                border-radius: var(--radius-xl);
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
            }
            
            .btn-confirm-submit:hover {
                background: var(--primary-700);
            }

            .custom-modal-icon.submit {
                background: var(--primary-50);
                color: var(--primary-600);
            }

            /* Preview Modal */
            .preview-modal {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.8);
                z-index: 9999;
                padding: 2rem;
                align-items: center;
                justify-content: center;
            }
            
            .preview-content {
                background: white;
                width: 90%;
                max-width: 1000px;
                height: 85vh;
                border-radius: var(--radius-xl);
                display: flex;
                flex-direction: column;
                position: relative;
            }
            
            .preview-header {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid var(--gray-200);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .preview-body {
                flex: 1;
                overflow: auto;
                padding: 1rem;
                display: flex;
                justify-content: center;
                align-items: center;
                background: #f8fafc;
            }
            
            .preview-body img {
                max-width: 100%;
                max-height: 100%;
                object-fit: contain;
            }
            
            .preview-body iframe {
                width: 100%;
                height: 100%;
                border: none;
            }

            @media (max-width: 768px) {
            .pillar-tabs {
                flex-wrap: nowrap;
                -webkit-overflow-scrolling: touch;
            }
            
            .score-selector {
                flex-direction: column;
            }
            
            .score-option label {
                flex-direction: row;
                justify-content: space-between;
                min-width: auto;
            }
        }
        
        /* Milestone Styles */
        .milestone-panel {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: var(--radius-xl);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #fbbf24;
        }
        
        .milestone-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .milestone-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #d97706;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .milestone-title {
            font-weight: 600;
            color: #92400e;
            font-size: 1rem;
        }
        
        .milestone-subtitle {
            font-size: 0.75rem;
            color: #b45309;
        }
        
        .milestone-history {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .milestone-chip {
            flex-shrink: 0;
            padding: 0.5rem 0.75rem;
            background: white;
            border-radius: var(--radius-lg);
            font-size: 0.75rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        
        .milestone-chip:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .milestone-chip.active {
            border-color: #d97706;
            background: #fffbeb;
        }
        
        .milestone-chip-version {
            font-weight: 600;
            color: #92400e;
        }
        
        .milestone-chip-score {
            color: #b45309;
        }
        
        .milestone-chip-date {
            color: #a3a3a3;
            font-size: 0.65rem;
            margin-top: 0.25rem;
        }
        
        .btn-save-milestone {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-save-milestone:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        }
        
        .milestone-link {
            display: block;
            text-align: center;
            margin-top: 0.75rem;
            font-size: 0.8rem;
            color: #92400e;
            text-decoration: none;
        }
        
        .milestone-link:hover {
            text-decoration: underline;
        }

        /* ========== Submitted Status Banner ========== */
        .submitted-status-banner {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #bbf7d0;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.25rem;
            animation: fadeInDown 0.5s ease;
        }

        .submitted-status-banner.status-evaluated {
            background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
            border-color: #bfdbfe;
        }

        .submitted-status-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .submitted-status-banner .submitted-status-icon {
            background: #dcfce7;
            color: #16a34a;
        }

        .submitted-status-banner.status-evaluated .submitted-status-icon {
            background: #dbeafe;
            color: #2563eb;
        }

        .submitted-status-content { flex: 1; }

        .submitted-status-title {
            font-weight: 700;
            font-size: 0.95rem;
        }

        .submitted-status-banner .submitted-status-title { color: #15803d; }
        .submitted-status-banner.status-evaluated .submitted-status-title { color: #1d4ed8; }

        .submitted-status-detail {
            font-size: 0.8rem;
            margin-top: 0.15rem;
            font-weight: 500;
        }

        .submitted-status-banner .submitted-status-detail { color: #4ade80; }
        .submitted-status-banner.status-evaluated .submitted-status-detail { color: #60a5fa; }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ========== Print Styles ========== */
        @media print {
            @page { size: A4; margin: 10mm 8mm; }

            /* Hide interactive elements */
            .milestone-panel, .custom-modal-overlay,
            button, [onclick], select,
            input[type="radio"], input[type="text"],
            .score-choice, .score-choices,
            .file-upload-area, .file-list,
            .submitted-status-banner,
            .badge[data-last-saved] {
                display: none !important;
            }

            .page-header { margin-bottom: 0.5rem !important; }

            /* Pillar tabs */
            .pillar-tabs {
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 0.25rem !important;
            }

            .pillar-tab.active {
                background: #f1f5f9 !important;
                font-weight: 700 !important;
            }

            /* Show all pillars for print */
            .assessment-pillar {
                display: block !important;
                page-break-inside: avoid;
                margin-bottom: 1rem !important;
            }

            /* Indicator cards */
            .indicator-card, .card {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                page-break-inside: avoid;
                margin-bottom: 0.5rem !important;
            }

            /* Show score as text instead of radio buttons */
            .score-display-print {
                display: inline-block !important;
            }

            /* Evidence text */
            textarea, .evidence-text {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                font-size: 9pt !important;
            }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>
            
            <?php if ($isReadOnly): ?>
            <div style="background: linear-gradient(135deg, #fef3cd, #fff3e0); border: 1px solid #f0ad4e; border-radius: 0.75rem; padding: 1rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e65100" stroke-width="2" style="flex-shrink: 0;">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <strong style="color: #e65100;">แบบประเมินเป็นแบบอ่านอย่างเดียว</strong>
                    <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: #795548;">
                        <?php if ($isPeriodClosed): ?>
                            รอบการประเมินนี้ปิดแล้ว (สถานะ: <?php echo $periodStatus; ?>) ไม่สามารถแก้ไขหรือส่งแบบประเมินได้
                        <?php else: ?>
                            เลยกำหนดส่ง (<?php echo date('d/m/Y', strtotime($submissionDeadline)); ?>) ไม่สามารถแก้ไขหรือส่งแบบประเมินได้
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($hasSubmitted && !$isReadOnly): ?>
            <div class="submitted-status-banner <?php echo $assessmentStatus === 'evaluated' ? 'status-evaluated' : ''; ?>">
                <div class="submitted-status-icon">
                    <i class="fas fa-<?php echo $assessmentStatus === 'evaluated' ? 'clipboard-check' : 'check-circle'; ?>"></i>
                </div>
                <div class="submitted-status-content">
                    <div class="submitted-status-title">
                        <?php if ($assessmentStatus === 'evaluated'): ?>
                            แบบประเมินนี้ผ่านการตรวจประเมินแล้ว
                        <?php elseif ($assessmentStatus === 'under_review'): ?>
                            แบบประเมินอยู่ระหว่างการตรวจประเมิน
                        <?php else: ?>
                            คุณได้ส่งแบบประเมินนี้แล้ว
                        <?php endif; ?>
                    </div>
                    <div class="submitted-status-detail">
                        ส่งเมื่อ <?php echo formatDateTime($assessment['submitted_at']); ?>
                        — คุณสามารถแก้ไขข้อมูลและส่งใหม่ได้จนถึงวันปิดรับ
                        <?php if ($submissionDeadline): ?>
                            (<?php echo date('d/m/Y', strtotime($submissionDeadline)); ?>)
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 class="page-title">แบบประเมิน HICM V2025</h1>
                    <p class="page-subtitle">รอบการประเมิน: <?php echo $assessment['period_name']; ?> (<?php echo $assessment['year']; ?>)</p>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="badge badge-primary" data-last-saved>ยังไม่บันทึก</span>
                    <?php if (!$isReadOnly): ?>
                    <button type="button" class="btn btn-warning" onclick="showMilestoneModal()" title="บันทึก Checkpoint">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <polyline points="2 17 12 22 22 17"/>
                            <polyline points="2 12 12 17 22 12"/>
                        </svg>
                        Checkpoint
                    </button>
                    <?php if ($canResubmit): ?>
                    <button type="button" class="btn" style="background: #f97316; color: white; border-color: #ea580c;" onclick="submitAssessment()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 2v6h-6"/>
                            <path d="M3 12a9 9 0 0 1 15-6.7L21 8"/>
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        ส่งใหม่อีกครั้ง
                    </button>
                    <?php else: ?>
                    <button type="button" class="btn btn-success" onclick="submitAssessment()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        ส่งแบบประเมิน
                    </button>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge" style="background: #ffccbc; color: #bf360c; padding: 0.5rem 1rem; font-size: 0.85rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        อ่านอย่างเดียว
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            

            
            <!-- Milestone Progress Panel (Moved to Top) -->
            <?php if (!empty($milestones)): ?>
            <div class="milestone-panel" style="margin-bottom: 1.5rem;">
                <div class="milestone-header">
                    <div class="milestone-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <polyline points="2 17 12 22 22 17"/>
                            <polyline points="2 12 12 17 22 12"/>
                        </svg>
                    </div>
                    <div>
                        <div class="milestone-title">My Milestones</div>
                        <div class="milestone-subtitle">บันทึกพัฒนาการ <?php echo count($milestones); ?> checkpoint</div>
                    </div>
                </div>
                <div class="milestone-history">
                    <?php foreach ($milestones as $ms): ?>
                    <div class="milestone-chip" data-milestone-id="<?php echo $ms['id']; ?>">
                        <div class="milestone-chip-version">#<?php echo $ms['version']; ?></div>
                        <div class="milestone-chip-score"><?php echo number_format($ms['total_score'], 1); ?> คะแนน</div>
                        <div class="milestone-chip-date"><?php echo date('d/m H:i', strtotime($ms['saved_at'])); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo getBaseUrl(); ?>/pages/milestones.php" class="milestone-link">
                    📊 ดูกราฟพัฒนาการทั้งหมด →
                </a>
            </div>
            <?php endif; ?>

            <!-- Summary (Moved to Top) -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <div class="card-header">
                    <h3 class="text-lg font-semibold">สรุปคะแนน</h3>
                </div>
                <div class="card-body">
                    <!-- Total Score Progress Bar -->
                    <div style="margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.875rem; font-weight: 500; color: var(--gray-600);">S</span>
                            <div>
                                <span style="font-size: 0.875rem; font-weight: 600; color: var(--primary-600);" data-total-score><?php echo number_format($assessment['final_score'], 0); ?></span>
                                <span style="font-size: 0.75rem; color: var(--gray-400);">/ 1,000</span>
                            </div>
                        </div>
                        
                        <?php 
                            $totalIndicatorsCount = 0;
                            $totalCompletedCount = 0;
                            foreach ($assessment['pillars'] as $p) {
                                $totalIndicatorsCount += count($p['indicators']);
                                // Count if not null/empty OR is_na. Explicit 0 counts.
                                $totalCompletedCount += count(array_filter($p['indicators'], fn($i) => ($i['self_score'] !== null && $i['self_score'] !== '') || $i['is_na']));
                            }
                        ?>
                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-bottom: 0.5rem; text-align: right;" data-total-completed>
                            ดำเนินการ: <?php echo $totalCompletedCount; ?>/<?php echo $totalIndicatorsCount; ?> ข้อ
                        </div>
                        <div class="progress-bar" style="height: 6px; border-radius: 6px; background: var(--gray-200);">
                            <div id="totalScoreProgress" class="progress-bar-fill" style="height: 100%; border-radius: 6px; background: linear-gradient(90deg, #3B82F6, #8B5CF6); width: <?php echo ($assessment['final_score'] / 1000) * 100; ?>%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    
                    <!-- Pillar Scores -->
                    <div style="margin-bottom: 1.5rem;" id="pillarScoresSummary">
                        <?php foreach ($assessment['pillars'] as $pillarCode => $pillar): 
                            $pc = $pillarColors[$pillarCode];
                            $completed = count(array_filter($pillar['indicators'], fn($i) => $i['self_score'] > 0 || $i['is_na']));
                            $naCount = count(array_filter($pillar['indicators'], fn($i) => $i['is_na']));
                            $total = count($pillar['indicators']);
                            $percentage = $total > 0 ? ($completed / $total) * 100 : 0;
                            
                            // Calculate pillar score
                            $pillarTotal = 0;
                            $activeIndicators = $total - $naCount;
                            foreach ($pillar['indicators'] as $ind) {
                                if (!$ind['is_na']) {
                                    $pillarTotal += floatval($ind['self_score'] ?? 0);
                                }
                            }
                            $pillarWeight = $pillar['weight'] ?? 250;
                            $pillarScore = $activeIndicators > 0 ? round(($pillarTotal / $activeIndicators) * $pillarWeight, 0) : 0;
                        ?>
                            <div style="margin-bottom: 1rem;" data-pillar-summary="<?php echo $pillarCode; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                    <span style="font-size: 0.875rem; font-weight: 500;"><?php echo $pillarCode; ?></span>
                                    <div style="text-align: right;">
                                        <span style="font-size: 0.875rem; font-weight: 600; color: <?php echo $pc['color']; ?>;" data-pillar-score-value="<?php echo $pillarCode; ?>"><?php echo $pillarScore; ?></span>
                                        <span style="font-size: 0.75rem; color: var(--gray-400);">/ <?php echo $pillarWeight; ?></span>
                                    </div>
                                </div>
                                <div class="progress-bar" style="height: 6px; margin-bottom: 0.25rem;">
                                    <div class="progress-bar-fill" style="width: <?php echo $pillarWeight > 0 ? ($pillarScore / $pillarWeight) * 100 : 0; ?>%; background-color: <?php echo $pc['color']; ?>; transition: width 0.3s ease;" data-pillar-progress="<?php echo $pillarCode; ?>"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: var(--gray-400);">
                                    <span data-pillar-completed="<?php echo $pillarCode; ?>"><?php echo $completed; ?>/<?php echo $total; ?> ตัวชี้วัด</span>
                                    <?php if($naCount > 0): ?>
                                        <span data-pillar-na="<?php echo $pillarCode; ?>">N/A: <?php echo $naCount; ?></span>
                                    <?php else: ?>
                                        <span data-pillar-na="<?php echo $pillarCode; ?>" style="display: none;"></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
                    <!-- Pillar Tabs -->
                    <div class="pillar-tabs">
                        <?php foreach ($assessment['pillars'] as $pillarCode => $pillar): 
                            $pc = $pillarColors[$pillarCode];
                            $completed = count(array_filter($pillar['indicators'], fn($i) => $i['self_score'] > 0));
                            $total = count($pillar['indicators']);
                        ?>
                            <button type="button" class="pillar-tab <?php echo $pillarCode === 'H1' ? 'active' : ''; ?>" 
                                    data-pillar="<?php echo $pillarCode; ?>"
                                    style="color: <?php echo $pc['color']; ?>">
                                <div class="pillar-tab-icon" style="background-color: <?php echo $pc['bg']; ?>">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <?php if ($pillarCode === 'H1'): ?>
                                            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                        <?php elseif ($pillarCode === 'I2'): ?>
                                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        <?php elseif ($pillarCode === 'C3'): ?>
                                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                        <?php else: ?>
                                            <line x1="18" y1="20" x2="18" y2="10"/>
                                            <line x1="12" y1="20" x2="12" y2="4"/>
                                        <?php endif; ?>
                                    </svg>
                                </div>
                                <div class="pillar-tab-info">
                                    <span class="pillar-tab-name"><?php echo $pillar['name']; ?></span>
                                    <span class="pillar-tab-score"><?php echo $completed; ?>/<?php echo $total; ?> ตัวชี้วัด</span>
                                </div>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Indicators -->
                    <form id="assessmentForm" class="assessment-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <?php foreach ($assessment['pillars'] as $pillarCode => $pillar): 
                            $pc = $pillarColors[$pillarCode];
                        ?>
                            <div class="assessment-pillar" data-pillar-content="<?php echo $pillarCode; ?>" data-pillar-code="<?php echo $pillarCode; ?>" data-pillar-weight="<?php echo $pillar['weight'] ?? 250; ?>" data-pillar-indicator-count="<?php echo count($pillar['indicators']); ?>" style="display: <?php echo $pillarCode === 'H1' ? 'block' : 'none'; ?>">
                                <div class="pillar-header" style="border-left-color: <?php echo $pc['color']; ?>">
                                    <div class="pillar-icon" style="background-color: <?php echo $pc['bg']; ?>; color: <?php echo $pc['color']; ?>">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <?php if ($pillarCode === 'H1'): ?>
                                                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                            <?php elseif ($pillarCode === 'I2'): ?>
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                            <?php elseif ($pillarCode === 'C3'): ?>
                                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                            <?php else: ?>
                                                <line x1="18" y1="20" x2="18" y2="10"/>
                                                <line x1="12" y1="20" x2="12" y2="4"/>
                                            <?php endif; ?>
                                        </svg>
                                    </div>
                                    <div class="pillar-info">
                                        <h3><?php echo $pillar['name']; ?></h3>
                                        <p><?php echo $pillar['weight']; ?> คะแนน | <?php echo count($pillar['indicators']); ?> ตัวชี้วัด</p>
                                    </div>
                                </div>
                                
                                <?php foreach ($pillar['indicators'] as $index => $indicator): ?>
                                    <div class="indicator-card" data-indicator-id="<?php echo $indicator['indicator_id']; ?>">
                                        <div class="indicator-header">
                                            <div class="indicator-number" style="background: linear-gradient(135deg, <?php echo $pc['color']; ?>, <?php echo $pc['color']; ?>DD);">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div class="indicator-title">
                                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                                    <h4><?php echo $indicator['indicator_code']; ?>: <?php echo $indicator['indicator_name']; ?></h4>
                                                    <?php if ($indicator['attachment_count'] > 0): ?>
                                                        <span class="badge badge-primary" style="font-size: 0.7rem; padding: 0.1rem 0.5rem;" id="badge_<?php echo $indicator['indicator_id']; ?>">
                                                            <?php echo $indicator['attachment_count']; ?> ไฟล์
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p><?php echo $indicator['description']; ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="indicator-body">
                                            <!-- Criteria -->
                                            <div class="indicator-criteria">
                                                <h5>เกณฑ์การประเมิน</h5>
                                                <ul class="criteria-list">
                                                    <?php foreach ($scoreLevels as $score => $level): 
                                                        $criteriaKey = 'criteria_' . str_replace('.', '', $score);
                                                        $criteriaText = !empty($indicator[$criteriaKey]) ? $indicator[$criteriaKey] : '<span class="text-gray-400 italic">ไม่ได้ระบุเกณฑ์</span>';
                                                    ?>
                                                        <li>
                                                            <span class="criteria-score"><?php echo $score; ?></span>
                                                            <span><?php echo $criteriaText; ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                    
                                                    <?php if (!empty($indicator['allow_na'])): ?>
                                                        <li>
                                                            <span class="criteria-score" style="color: var(--gray-500);">N/A</span>
                                                            <span class="text-gray-500">ไม่เข้าเกณฑ์ หรือ ไม่มีกิจกรรมที่เกี่ยวข้อง</span>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                            
                                            <!-- Score Selection -->
                                            <div class="form-group">
                                                <label class="form-label">เลือกระดับคะแนน</label>
                                                <div class="score-selector" data-indicator-id="<?php echo $indicator['indicator_id']; ?>">
                                                    <?php foreach ($scoreLevels as $score => $level): ?>
                                                        <div class="score-option">
                                                            <input type="radio" 
                                                                   name="score_<?php echo $indicator['indicator_id']; ?>" 
                                                                   id="score_<?php echo $indicator['indicator_id']; ?>_<?php echo str_replace('.', '_', $score); ?>"
                                                                   value="<?php echo $score; ?>"
                                                                   <?php echo (isset($indicator['self_score']) && $indicator['self_score'] !== '' && $indicator['self_score'] !== null && floatval($indicator['self_score']) === (float)$score && !$indicator['is_na']) ? 'checked' : ''; ?>
                                                                   <?php echo $isReadOnly ? 'disabled' : ''; ?>
                                                                   onchange="saveScore(<?php echo $indicator['indicator_id']; ?>, <?php echo $score; ?>, 0)">
                                                            <label for="score_<?php echo $indicator['indicator_id']; ?>_<?php echo str_replace('.', '_', $score); ?>">
                                                                <span class="score-value"><?php echo $score; ?></span>
                                                                <span class="score-label"><?php echo $level['label']; ?></span>
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- N/A Option -->
                                                    <?php if (!empty($indicator['allow_na'])): ?>
                                                    <div class="score-option">
                                                        <input type="radio" 
                                                               name="score_<?php echo $indicator['indicator_id']; ?>" 
                                                               id="score_<?php echo $indicator['indicator_id']; ?>_na"
                                                               value="na"
                                                               <?php echo $indicator['is_na'] ? 'checked' : ''; ?>
                                                               <?php echo $isReadOnly ? 'disabled' : ''; ?>
                                                               onchange="saveScore(<?php echo $indicator['indicator_id']; ?>, 0, 1)">
                                                        <label for="score_<?php echo $indicator['indicator_id']; ?>_na" style="border-color: var(--gray-300);">
                                                            <span class="score-value" style="color: var(--gray-500);">N/A</span>
                                                            <span class="score-label">ไม่เข้าเกณฑ์</span>
                                                        </label>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Evidence -->
                                            <div class="form-group">
                                                <label class="form-label">รายละเอียด/หลักฐานเชิงประจักษ์</label>
                                                <textarea class="form-textarea" 
                                                          name="evidence_<?php echo $indicator['indicator_id']; ?>"
                                                          placeholder="ระบุรายละเอียดการดำเนินงานและหลักฐานประกอบ..."
                                                          <?php echo $isReadOnly ? 'readonly' : ''; ?>
                                                          onchange="saveEvidence(<?php echo $indicator['indicator_id']; ?>, this.value)"><?php echo htmlspecialchars($indicator['self_evidence'] ?? ''); ?></textarea>
                                            </div>
                                            
                                            <!-- File Upload -->
                                            <div class="form-group">
                                                <label class="form-label">ไฟล์แนบ (รูปภาพ, PDF, เอกสาร)</label>
                                                <div class="file-upload" data-indicator-id="<?php echo $indicator['indicator_id']; ?>" data-assessment-id="<?php echo $assessment['id']; ?>">
                                                    <input type="file" 
                                                           name="file_<?php echo $indicator['indicator_id']; ?>[]" 
                                                           multiple 
                                                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
                                                           data-indicator-id="<?php echo $indicator['indicator_id']; ?>">
                                                    <svg class="file-upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                                        <polyline points="17 8 12 3 7 8"/>
                                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                                    </svg>
                                                    <div class="file-upload-text">คลิกหรือลากไฟล์มาวางที่นี่</div>
                                                    <div class="file-upload-hint">รองรับไฟล์ JPG, PNG, PDF, DOC, XLS (สูงสุด 10MB)</div>
                                                </div>
                                                <div class="file-list" id="fileList_<?php echo $indicator['indicator_id']; ?>">
                                                    <?php 
                                                    $indicatorAttachments = getAttachmentsByScoreId($indicator['score_id']);
                                                    foreach ($indicatorAttachments as $file): 
                                                    ?>
                                                        <div class="file-item" data-file-id="<?php echo $file['id']; ?>">
                                                            <div class="file-item-icon">
                                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/>
                                                                </svg>
                                                            </div>
                                                            <div class="file-item-info">
                                                                <div class="file-item-name"><?php echo htmlspecialchars($file['file_original_name']); ?></div>
                                                                <div class="file-item-size"><?php echo round($file['file_size'] / 1024, 2); ?> KB</div>
                                                            </div>
                                                            <div class="file-item-actions">
                                                                <button type="button" class="btn-view-file" onclick="previewFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['file_original_name']); ?>', '<?php echo getBaseUrl() . '/api/download.php?id=' . $file['id']; ?>', '<?php echo $file['file_type']; ?>')">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                                                    </svg>
                                                                    ดูไฟล์
                                                                </button>
                                                                <div class="file-item-status">
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                                                                        <polyline points="20 6 9 17 4 12"/>
                                                                    </svg>
                                                                </div>
                                                                <button type="button" class="btn-delete-file" onclick="deleteFile(<?php echo $file['id']; ?>, this)">
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </form>

                

        </div>
    </main>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        // Pillar tab switching
        document.querySelectorAll('.pillar-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const pillarCode = this.dataset.pillar;
                
                // Update active tab
                document.querySelectorAll('.pillar-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                document.querySelectorAll('.assessment-pillar').forEach(content => {
                    content.style.display = content.dataset.pillarContent === pillarCode ? 'block' : 'none';
                });
            });
        });
        
        // Read-only mode flag
        const isFormReadOnly = <?php echo $isReadOnly ? 'true' : 'false'; ?>;
        
        // Save score
        async function saveScore(indicatorId, score, isNa = 0) {
            if (isFormReadOnly) {
                showToast('แบบประเมินเป็นแบบอ่านอย่างเดียว ไม่สามารถบันทึกได้', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('save_score', '1');
            formData.append('indicator_id', indicatorId);
            formData.append('score', score);
            formData.append('is_na', isNa);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('บันทึกคะแนนเรียบร้อย', 'success');
                    updateLastSavedTime();
                    calculateTotalScore();
                }
            } catch (error) {
                console.error('Error saving score:', error);
            }
        }
        
        // Save evidence
        async function saveEvidence(indicatorId, evidence) {
            if (isFormReadOnly) {
                showToast('แบบประเมินเป็นแบบอ่านอย่างเดียว ไม่สามารถบันทึกได้', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('save_score', '1');
            formData.append('indicator_id', indicatorId);
            formData.append('evidence', evidence);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    updateLastSavedTime();
                }
            } catch (error) {
                console.error('Error saving evidence:', error);
            }
        }
        
        // Submit assessment
        function submitAssessment() {
            if (isFormReadOnly) {
                showToast('แบบประเมินเป็นแบบอ่านอย่างเดียว ไม่สามารถส่งได้', 'error');
                return;
            }
            
            const modal = document.getElementById('submitModal');
            const okBtn = document.getElementById('submitOk');
            const cancelBtn = document.getElementById('submitCancel');
            const progressText = document.getElementById('submitProgressText');
            
            // Calculate completion count
            const pillars = document.querySelectorAll('.assessment-pillar');
            let totalIndicators = 0;
            let answeredCount = 0;
            
            pillars.forEach(pillar => {
                const pillarTotal = parseInt(pillar.dataset.pillarIndicatorCount) || 0;
                totalIndicators += pillarTotal;
                
                const scoreInputs = pillar.querySelectorAll('input[type="radio"]:checked');
                answeredCount += scoreInputs.length;
            });
            
            // Update progress text
            if (progressText) {
                const percentage = totalIndicators > 0 ? Math.round((answeredCount / totalIndicators) * 100) : 0;
                let progressColor = '#10b981'; // green
                if (percentage < 50) progressColor = '#ef4444'; // red
                else if (percentage < 100) progressColor = '#f59e0b'; // orange
                
                progressText.innerHTML = `<span style="color: ${progressColor};">${answeredCount}/${totalIndicators} ข้อ</span> <span style="font-size: 0.875rem; color: #6b7280;">(${percentage}%)</span>`;
            }
            
            modal.classList.add('active');
            
            const onOk = async () => {
                cleanup();
                
                // Change button to loading state
                okBtn.disabled = true;
                okBtn.innerHTML = '<span style="display:inline-flex;align-items:center;gap:0.5rem;"><svg class="animate-spin" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg>กำลังส่ง...</span>';
                
                // Submit via AJAX
                const formData = new FormData();
                formData.append('submit_assessment', '1');
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    
                    const result = await response.json();
                    
                    console.log('Submit result:', result); // Debug log
                    
                    if (result.success) {
                        // Show celebration with confetti!
                        showCelebration(result.score, result.level);
                    } else {
                        showToast(result.message || 'เกิดข้อผิดพลาด', 'error');
                        okBtn.disabled = false;
                        okBtn.textContent = '<?php echo $canResubmit ? "ใช่, ส่งใหม่" : "ใช่, ส่งข้อมูล"; ?>';
                    }
                } catch (error) {
                    console.error('Submit error:', error);
                    showToast('เกิดข้อผิดพลาดในการส่งข้อมูล', 'error');
                    okBtn.disabled = false;
                    okBtn.textContent = '<?php echo $canResubmit ? "ใช่, ส่งใหม่" : "ใช่, ส่งข้อมูล"; ?>';
                }
            };
            
            const onCancel = () => {
                cleanup();
            };
            
            const cleanup = () => {
                modal.classList.remove('active');
                okBtn.removeEventListener('click', onOk);
                cancelBtn.removeEventListener('click', onCancel);
            };
            
            okBtn.addEventListener('click', onOk);
            cancelBtn.addEventListener('click', onCancel);
        }
        
        // Update progress bar
        function updateProgressBar(score) {
            const progressBar = document.getElementById('totalScoreProgress');
            if (progressBar) {
                const percentage = (score / 1000) * 100;
                progressBar.style.width = percentage + '%';
            }
        }
        
        // Calculate total score in real-time
        function calculateTotalScore() {
            const pillars = document.querySelectorAll('.assessment-pillar');
            let totalWeightedScore = 0;
            let totalIndicatorsAll = 0;
            let totalAnsweredAll = 0;

            pillars.forEach(pillar => {
                const pillarCode = pillar.dataset.pillarCode;
                const pillarWeight = parseInt(pillar.dataset.pillarWeight) || 250;
                
                const result = calculateSinglePillarScore(pillar);
                totalWeightedScore += result.score;
                
                totalIndicatorsAll += parseInt(pillar.dataset.pillarIndicatorCount) || 0;
                totalAnsweredAll += result.answered;
            });
            
            // Update total score display again with correct value
            const totalScoreDisplay = document.querySelector('[data-total-score]');
            if (totalScoreDisplay) {
                totalScoreDisplay.textContent = totalWeightedScore.toLocaleString('th-TH', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }

            // Update completed count display
            const totalCompletedDisplay = document.querySelector('[data-total-completed]');
            if (totalCompletedDisplay) {
                 totalCompletedDisplay.textContent = `ดำเนินการ: ${totalAnsweredAll}/${totalIndicatorsAll} ข้อ`;
            }
            
            // Update progress bar
            updateProgressBar(totalWeightedScore);
            
            // Update HICM level
            updateHICMLevel(totalWeightedScore);
        }

        // Override main.js calculatePillarScore to handle N/A and custom weights
        function calculatePillarScore(pillarElement) {
            if (!pillarElement) return;
            // The logic is centralized in calculateSinglePillarScore/calculateTotalScore 
            // but main.js calls this specifically for UI updates on the pillar card
            // We can just rely on calculateTotalScore which updates EVERYTHING (pillars included)
            // But to be safe and efficient, we can reimplement it or just let calculateTotalScore do the heavy lifting
            calculateTotalScore();
        }

        // Shared logic for calculating a single pillar's score
        function calculateSinglePillarScore(pillar) {
            const pillarCode = pillar.dataset.pillarCode;
            const pillarWeight = parseInt(pillar.dataset.pillarWeight) || 250;
            const totalIndicators = parseInt(pillar.dataset.pillarIndicatorCount) || 0;
            
            // Get all score inputs for this pillar
            const scoreInputs = pillar.querySelectorAll('input[type="radio"]:checked');
            let pillarTotal = 0;
            let answeredCount = 0;
            let naCount = 0;
            
            scoreInputs.forEach(input => {
                if (input.value === 'na') {
                    naCount++;
                    answeredCount++;
                } else {
                    const val = input.value;
                    if (val !== null && val !== '') {
                        const score = parseFloat(val);
                        if (!isNaN(score)) {
                             pillarTotal += score;
                             answeredCount++;
                        }
                    }
                }
            });
            
            // Calculate weighted score for this pillar
            const activeIndicators = totalIndicators - naCount;
            let pillarScore = 0;
            
            if (activeIndicators > 0) {
                 // Formula: (Sum of Scores / Active Indicators) * Weight
                 // Note: Scores are 0-1. So Sum/Active is avg score (0-1). Multiplied by weight.
                 pillarScore = Math.round((pillarTotal / activeIndicators) * pillarWeight);
            }

            // Update DOM Elements for this pillar
            
            // 1. Score Value
            const pillarScoreEl = document.querySelector(`[data-pillar-score-value="${pillarCode}"]`);
            // Also check for main.js style selector just in case
            const pillarScoreElMain = document.querySelector(`[data-pillar-score="${pillarCode}"]`);
            
            if (pillarScoreEl) pillarScoreEl.textContent = pillarScore;
            if (pillarScoreElMain) pillarScoreElMain.textContent = pillarScore;
            
            // 2. Progress Bar
            const progressBar = document.querySelector(`[data-pillar-progress="${pillarCode}"]`);
            if (progressBar) {
                const percentage = pillarWeight > 0 ? (pillarScore / pillarWeight) * 100 : 0;
                progressBar.style.width = percentage + '%';
            }
            
            // 3. Completed Count
            const completedEl = document.querySelector(`[data-pillar-completed="${pillarCode}"]`);
            if (completedEl) {
                completedEl.textContent = `${answeredCount}/${totalIndicators} ตัวชี้วัด`;
            }
            
            // 4. N/A Count
            const naEl = document.querySelector(`[data-pillar-na="${pillarCode}"]`);
            if (naEl) {
                if (naCount > 0) {
                    naEl.textContent = `N/A: ${naCount}`;
                    naEl.style.display = '';
                } else {
                    naEl.style.display = 'none';
                }
            }
            
            // 5. Tab Score
            const tabScore = document.querySelector(`[data-pillar="${pillarCode}"] .pillar-tab-score`);
            if (tabScore) {
                tabScore.textContent = `${answeredCount}/${totalIndicators} ตัวชี้วัด`;
            }

            return {
                score: pillarScore,
                total: pillarTotal,
                answered: answeredCount,
                na: naCount
            };
        }
        
        // Update HICM Level display
        function updateHICMLevel(score) {
            const levelDisplay = document.querySelector('[data-hicm-level]');
            if (!levelDisplay) return;
            
            const levels = [
                { min: 0, max: 599, level: 1, name: 'เริ่มต้น', nameEn: 'Emerging' },
                { min: 600, max: 699, level: 2, name: 'กำลังพัฒนา', nameEn: 'Developing' },
                { min: 700, max: 799, level: 3, name: 'พัฒนาดี', nameEn: 'Performing' },
                { min: 800, max: 899, level: 4, name: 'เป็นเลิศ', nameEn: 'Excellence' },
                { min: 900, max: 1000, level: 5, name: 'ระดับโลก', nameEn: 'World-Class' }
            ];
            
            const currentLevel = levels.find(l => score >= l.min && score <= l.max) || levels[0];
            
            levelDisplay.innerHTML = `
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-600); margin-bottom: 0.25rem;">
                    Level ${currentLevel.level}
                </div>
                <div style="font-weight: 500;">${currentLevel.name}</div>
                <div style="font-size: 0.75rem; color: var(--gray-500);">${currentLevel.nameEn}</div>
            `;
        }
        
        // Preview file
        var currentDownloadUrl = '';
        function previewFile(fileId, fileName, fileUrl, fileType) {
            const modal = document.getElementById('previewModal');
            const title = document.getElementById('previewTitle');
            const body = document.getElementById('previewBody');
            
            title.textContent = fileName;
            currentDownloadUrl = fileUrl;
            body.innerHTML = '';
            
            if (fileType.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = fileUrl + '&view=1';
                body.appendChild(img);
            } else if (fileType === 'application/pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = fileUrl + '&view=1';
                body.appendChild(iframe);
            } else {
                body.innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.5" style="margin-bottom: 1rem;">
                            <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/>
                        </svg>
                        <p style="color: var(--gray-600); margin-bottom: 1.5rem;">ไม่สามารถแสดงตัวอย่างไฟล์ประเภทนี้ได้โดยตรง</p>
                        <button type="button" class="btn btn-primary" onclick="downloadCurrentFile()">
                             <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            ดาวน์โหลดไฟล์
                        </button>
                    </div>
                `;
            }
            
            modal.style.display = 'flex';
        }
        
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        function downloadCurrentFile() {
            if (currentDownloadUrl) {
                window.open(currentDownloadUrl, '_blank');
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Calculate initial scores
            calculateTotalScore();
            
            // Close modals on click outside
            window.onclick = function(event) {
                const previewModal = document.getElementById('previewModal');
                const confirmModal = document.getElementById('confirmModal');
                const submitModal = document.getElementById('submitModal');
                
                if (event.target == previewModal) {
                    closePreview();
                }
                
                if (event.target == confirmModal) {
                    document.getElementById('confirmCancel').click();
                }

                if (event.target == submitModal) {
                    document.getElementById('submitCancel').click();
                }
            }
        });
    </script>
    
    <!-- Preview Modal -->
    <div id="previewModal" class="preview-modal">
        <div class="preview-content">
            <div class="preview-header">
                <h3 id="previewTitle" class="text-lg font-semibold">ตัวอย่างไฟล์</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-outline" onclick="downloadCurrentFile()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        ดาวน์โหลด
                    </button>
                    <button type="button" class="btn btn-icon" onclick="closePreview()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="previewBody" class="preview-body">
                <!-- Preview content injected here -->
            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="confirmModal" class="custom-modal-overlay">
        <div class="custom-modal">
            <div class="custom-modal-icon">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <h3 class="custom-modal-title">ยืนยันการลบไฟล์</h3>
            <p class="custom-modal-message">คุณแน่ใจหรือไม่ที่จะลบไฟล์นี้? <br>การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
            <div class="custom-modal-actions">
                <button type="button" class="btn-confirm-cancel" id="confirmCancel">ยกเลิก</button>
                <button type="button" class="btn-confirm-delete" id="confirmOk">ใช่, ลบไฟล์</button>
            </div>
        </div>
    </div>

    <!-- Submit Confirm Modal -->
    <div id="submitModal" class="custom-modal-overlay">
        <div class="custom-modal">
            <div class="custom-modal-icon submit">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="custom-modal-title"><?php echo $canResubmit ? 'ยืนยันการส่งแบบประเมินใหม่' : 'ยืนยันการส่งแบบประเมิน'; ?></h3>
            <p class="custom-modal-message">
                <?php if ($canResubmit): ?>
                    คุณแน่ใจหรือไม่ที่จะส่งแบบประเมินใหม่? <br>
                    <?php if ($assessmentStatus === 'evaluated'): ?>
                        <span style="color: #d97706;">ผลการประเมินจากกรรมการจะถูกรีเซ็ต กรรมการจะต้องประเมินใหม่</span>
                    <?php else: ?>
                        ข้อมูลที่แก้ไขจะถูกอัปเดตและแจ้งกรรมการ
                    <?php endif; ?>
                <?php else: ?>
                    คุณแน่ใจหรือไม่ที่จะส่งแบบประเมิน? <br>คุณสามารถแก้ไขและส่งใหม่ได้จนถึงวันปิดรับ
                <?php endif; ?>
            </p>
            <div id="submitProgress" style="margin: 1rem 0; padding: 0.75rem; background: #f3f4f6; border-radius: 8px; text-align: center;">
                <div style="font-size: 0.875rem; color: #6b7280; margin-bottom: 0.25rem;">ความคืบหน้า</div>
                <div style="font-size: 1.25rem; font-weight: 600; color: #1f2937;" id="submitProgressText">-</div>
            </div>
            <div class="custom-modal-actions">
                <button type="button" class="btn-confirm-cancel" id="submitCancel">ยกเลิก</button>
                <button type="button" class="btn-confirm-submit" id="submitOk"><?php echo $canResubmit ? 'ใช่, ส่งใหม่' : 'ใช่, ส่งข้อมูล'; ?></button>
            </div>
        </div>
    </div>

    <!-- Milestone Save Modal -->
    <div id="milestoneModal" class="custom-modal-overlay">
        <div class="custom-modal" style="max-width: 450px;">
            <div class="custom-modal-icon" style="background: #fef3c7; color: #d97706;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    <polyline points="2 17 12 22 22 17"/>
                    <polyline points="2 12 12 17 22 12"/>
                </svg>
            </div>
            <h3 class="custom-modal-title">บันทึก Checkpoint</h3>
            <p class="custom-modal-message" style="margin-bottom: 1rem;">
                บันทึกสถานะปัจจุบันเป็น checkpoint #<?php echo count($milestones) + 1; ?><br>
                เพื่อติดตามพัฒนาการของคุณ
            </p>
            <div style="text-align: left; margin-bottom: 1rem;">
                <label for="milestoneNote" style="font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.5rem;">
                    หมายเหตุ (ไม่บังคับ)
                </label>
                <textarea id="milestoneNote" rows="2" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; resize: none;" placeholder="เช่น ปรับปรุงหมวด H1 เพิ่มเติม..."></textarea>
            </div>
            <div class="custom-modal-actions">
                <button type="button" class="btn-confirm-cancel" id="milestoneCancel">ยกเลิก</button>
                <button type="button" class="btn-confirm-submit" id="milestoneOk" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    💾 บันทึก Checkpoint
                </button>
            </div>
        </div>
    </div>

    <!-- Celebration Modal -->
    <div id="celebrationModal" class="celebration-overlay">
        <canvas id="confettiCanvas"></canvas>
        <div class="celebration-content">
            <div class="celebration-stars" id="celebrationStars"></div>
            <div class="celebration-score">
                <div class="score-label">คะแนนรวม</div>
                <div class="score-value" id="celebrationScore">0</div>
                <div class="score-max">/ 1,000 คะแนน</div>
            </div>
            <div class="celebration-level">
                <div class="level-badge" id="celebrationLevel">Level 1</div>
                <div class="level-name" id="celebrationLevelName">เริ่มต้น</div>
            </div>
            <h2 class="celebration-title">🎉 ยินดีด้วย!</h2>
            <p class="celebration-message">คุณส่งแบบประเมินเรียบร้อยแล้ว</p>
            <div class="celebration-countdown">
                กลับสู่หน้าหลักใน <span id="countdownTimer">30</span> วินาที
            </div>
            <button class="btn btn-primary celebration-btn" onclick="goToDashboard()">
                ไปหน้า Dashboard เลย
            </button>
        </div>
    </div>

    <style>
    /* Celebration Modal Styles */
    .celebration-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.97) 0%, rgba(30, 41, 59, 0.97) 100%);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }

    .celebration-overlay.active {
        display: flex;
        animation: fadeInCelebration 0.5s ease-out;
    }

    @keyframes fadeInCelebration {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    #confettiCanvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }

    .celebration-content {
        position: relative;
        z-index: 2;
        text-align: center;
        color: white;
        padding: 2rem;
    }

    .celebration-stars {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
        font-size: 3rem;
    }

    .celebration-stars .star {
        animation: starPulse 1s ease-in-out infinite;
        opacity: 0;
        transform: scale(0);
    }

    .celebration-stars .star.active {
        opacity: 1;
        transform: scale(1);
        animation: starAppear 0.5s ease-out forwards, starGlow 2s ease-in-out infinite;
    }

    .celebration-stars .star.inactive {
        opacity: 0.3;
        filter: grayscale(100%);
        transform: scale(0.8);
    }

    @keyframes starAppear {
        0% { opacity: 0; transform: scale(0) rotate(-180deg); }
        50% { transform: scale(1.3) rotate(0deg); }
        100% { opacity: 1; transform: scale(1) rotate(0deg); }
    }

    @keyframes starGlow {
        0%, 100% { filter: drop-shadow(0 0 10px gold); }
        50% { filter: drop-shadow(0 0 25px gold) drop-shadow(0 0 40px orange); }
    }

    .celebration-score {
        margin-bottom: 1.5rem;
    }

    .score-label {
        font-size: 1rem;
        color: #94a3b8;
        margin-bottom: 0.5rem;
    }

    .score-value {
        font-size: 5rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fbbf24, #f59e0b, #d97706);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        line-height: 1;
        animation: scoreCountUp 2s ease-out;
    }

    .score-max {
        font-size: 1.25rem;
        color: #64748b;
    }

    .celebration-level {
        margin-bottom: 2rem;
    }

    .level-badge {
        display: inline-block;
        padding: 0.75rem 2rem;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-radius: 50px;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        animation: levelBounce 0.8s ease-out 1s both;
    }

    .level-badge.level-1 { background: linear-gradient(135deg, #64748b, #94a3b8); }
    .level-badge.level-2 { background: linear-gradient(135deg, #10b981, #34d399); }
    .level-badge.level-3 { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
    .level-badge.level-4 { background: linear-gradient(135deg, #8b5cf6, #a78bfa); }
    .level-badge.level-5 { background: linear-gradient(135deg, #f59e0b, #fbbf24); box-shadow: 0 4px 30px rgba(245, 158, 11, 0.5); }

    @keyframes levelBounce {
        0% { transform: scale(0); }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); }
    }

    .level-name {
        font-size: 1.25rem;
        color: #cbd5e1;
    }

    .celebration-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: #ffffff;
        animation: titleSlideUp 0.6s ease-out 0.3s both;
    }

    @keyframes titleSlideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .celebration-message {
        font-size: 1.125rem;
        color: #94a3b8;
        margin-bottom: 2rem;
        animation: titleSlideUp 0.6s ease-out 0.5s both;
    }

    .celebration-countdown {
        font-size: 0.9rem;
        color: #64748b;
        margin-bottom: 1.5rem;
    }

    .celebration-countdown span {
        color: #fbbf24;
        font-weight: 600;
    }

    .celebration-btn {
        padding: 1rem 2.5rem;
        font-size: 1rem;
        border-radius: 50px;
        animation: btnFadeIn 0.5s ease-out 1.5s both;
    }

    @keyframes btnFadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 640px) {
        .celebration-stars { font-size: 2rem; }
        .score-value { font-size: 3.5rem; }
        .celebration-title { font-size: 1.75rem; }
        .level-badge { font-size: 1.25rem; padding: 0.5rem 1.5rem; }
    }
    </style>

    <script>
    // Milestone Modal Functions
    function showMilestoneModal() {
        const modal = document.getElementById('milestoneModal');
        modal.classList.add('active');
        
        const okBtn = document.getElementById('milestoneOk');
        const cancelBtn = document.getElementById('milestoneCancel');
        
        const onOk = async () => {
            cleanup();
            await saveMilestone();
        };
        
        const onCancel = () => {
            cleanup();
        };
        
        const cleanup = () => {
            modal.classList.remove('active');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
        };
        
        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
    }
    
    async function saveMilestone() {
        const note = document.getElementById('milestoneNote').value;
        const formData = new FormData();
        formData.append('save_milestone', '1');
        formData.append('milestone_note', note);
        formData.append('ajax', '1');
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(result.message, 'success');
                // Reload page to show new milestone
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                showToast(result.message || 'เกิดข้อผิดพลาด', 'error');
            }
        } catch (error) {
            console.error('Error saving milestone:', error);
            showToast('เกิดข้อผิดพลาดในการบันทึก', 'error');
        }
    }

    // ===== Celebration & Confetti System =====
    let confettiInterval;
    let countdownInterval;

    const levelNames = {
        1: 'เริ่มต้น',
        2: 'กำลังพัฒนา',
        3: 'พัฒนาดี',
        4: 'เป็นเลิศ',
        5: 'ระดับโลก'
    };

    function showCelebration(score, level) {
        const modal = document.getElementById('celebrationModal');
        const scoreEl = document.getElementById('celebrationScore');
        const levelEl = document.getElementById('celebrationLevel');
        const levelNameEl = document.getElementById('celebrationLevelName');
        const starsEl = document.getElementById('celebrationStars');
        
        modal.classList.add('active');
        
        // Animate score counting
        animateScore(scoreEl, score);
        
        // Set level
        levelEl.textContent = 'Level ' + level;
        levelEl.className = 'level-badge level-' + level;
        levelNameEl.textContent = levelNames[level] || 'ไม่ระบุ';
        
        // Render stars
        renderStars(starsEl, level);
        
        // Start confetti
        startConfetti();
        
        // Start countdown
        startCountdown(30);
    }

    function animateScore(element, targetScore) {
        let currentScore = 0;
        const duration = 2000;
        const stepTime = 20;
        const steps = duration / stepTime;
        const increment = targetScore / steps;
        
        const counter = setInterval(() => {
            currentScore += increment;
            if (currentScore >= targetScore) {
                currentScore = targetScore;
                clearInterval(counter);
            }
            element.textContent = Math.round(currentScore).toLocaleString();
        }, stepTime);
    }

    function renderStars(container, level) {
        container.innerHTML = '';
        for (let i = 1; i <= 5; i++) {
            const star = document.createElement('span');
            star.className = 'star';
            star.innerHTML = '⭐';
            
            setTimeout(() => {
                if (i <= level) {
                    star.classList.add('active');
                } else {
                    star.classList.add('inactive');
                }
            }, i * 200);
            
            container.appendChild(star);
        }
    }

    function startConfetti() {
        const canvas = document.getElementById('confettiCanvas');
        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const particles = [];
        const colors = ['#fbbf24', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#3b82f6', '#10b981', '#06b6d4'];

        class Particle {
            constructor() {
                this.reset();
            }

            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height - canvas.height;
                this.vx = (Math.random() - 0.5) * 4;
                this.vy = Math.random() * 3 + 2;
                this.color = colors[Math.floor(Math.random() * colors.length)];
                this.size = Math.random() * 8 + 4;
                this.rotation = Math.random() * 360;
                this.rotationSpeed = (Math.random() - 0.5) * 10;
                this.shape = Math.random() > 0.5 ? 'rect' : 'circle';
            }

            update() {
                this.x += this.vx;
                this.y += this.vy;
                this.vy += 0.05; // gravity
                this.rotation += this.rotationSpeed;

                if (this.y > canvas.height + 50) {
                    this.reset();
                }
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.rotation * Math.PI / 180);
                ctx.fillStyle = this.color;

                if (this.shape === 'rect') {
                    ctx.fillRect(-this.size / 2, -this.size / 4, this.size, this.size / 2);
                } else {
                    ctx.beginPath();
                    ctx.arc(0, 0, this.size / 2, 0, Math.PI * 2);
                    ctx.fill();
                }
                ctx.restore();
            }
        }

        // Create particles
        for (let i = 0; i < 150; i++) {
            particles.push(new Particle());
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(p => {
                p.update();
                p.draw();
            });
            confettiInterval = requestAnimationFrame(animate);
        }

        animate();

        // Add burst effect at start
        burstConfetti(canvas.width / 2, canvas.height / 2);
    }

    function burstConfetti(x, y) {
        const canvas = document.getElementById('confettiCanvas');
        const ctx = canvas.getContext('2d');
        const colors = ['#fbbf24', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#3b82f6', '#10b981'];
        
        for (let i = 0; i < 50; i++) {
            const angle = (Math.PI * 2 / 50) * i;
            const velocity = Math.random() * 10 + 5;
            const particle = {
                x: x,
                y: y,
                vx: Math.cos(angle) * velocity,
                vy: Math.sin(angle) * velocity,
                color: colors[Math.floor(Math.random() * colors.length)],
                size: Math.random() * 6 + 2,
                life: 1
            };
            
            const animateBurst = () => {
                if (particle.life <= 0) return;
                
                particle.x += particle.vx;
                particle.y += particle.vy;
                particle.vy += 0.3;
                particle.life -= 0.02;
                
                ctx.globalAlpha = particle.life;
                ctx.fillStyle = particle.color;
                ctx.beginPath();
                ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalAlpha = 1;
                
                requestAnimationFrame(animateBurst);
            };
            
            animateBurst();
        }
    }

    function startCountdown(seconds) {
        const timerEl = document.getElementById('countdownTimer');
        let remaining = seconds;
        
        countdownInterval = setInterval(() => {
            remaining--;
            timerEl.textContent = remaining;
            
            if (remaining <= 0) {
                clearInterval(countdownInterval);
                goToDashboard();
            }
        }, 1000);
    }

    function goToDashboard() {
        if (confettiInterval) cancelAnimationFrame(confettiInterval);
        if (countdownInterval) clearInterval(countdownInterval);
        window.location.href = '<?php echo getBaseUrl(); ?>/pages/dashboard.php';
    }

    // Handle window resize for canvas
    window.addEventListener('resize', () => {
        const canvas = document.getElementById('confettiCanvas');
        if (canvas) {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
    });
    </script>
</body>
</html>