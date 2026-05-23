<?php
/**
 * HICM V2025 Assessment System - Assessment Result Page (Company)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();

$user = getCurrentUser();

if (!hasRole(ROLE_COMPANY)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$companyId = $user['company_id'] ?? null;
if (!$companyId) {
    setFlashMessage('ไม่พบข้อมูลสถานประกอบการ', 'error');
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// Support viewing results from any period via URL parameter
$requestedPeriodId = isset($_GET['period_id']) ? intval($_GET['period_id']) : null;

if ($requestedPeriodId) {
    // Load specific period's assessment (even if period is closed/completed)
    $db_check = getDB();
    $stmt_check = $db_check->prepare("
        SELECT id FROM assessments 
        WHERE company_id = ? AND period_id = ?
    ");
    $stmt_check->execute([$companyId, $requestedPeriodId]);
    $existingAssessment = $stmt_check->fetch();
    
    if ($existingAssessment) {
        $assessmentId = $existingAssessment['id'];
    } else {
        setFlashMessage('ไม่พบข้อมูลการประเมินในรอบที่เลือก', 'error');
        redirect(getBaseUrl() . '/pages/assessment-result.php');
    }
} else {
    // Default: try current open period first, then fall back to latest assessment
    $assessmentResult = getOrCreateAssessment($companyId);
    if ($assessmentResult['success']) {
        $assessmentId = $assessmentResult['assessment']['id'];
    } else {
        // Fallback: get the most recent assessment for this company
        $db_fallback = getDB();
        $stmt_fb = $db_fallback->prepare("
            SELECT a.id FROM assessments a
            JOIN assessment_periods ap ON a.period_id = ap.id
            WHERE a.company_id = ? AND a.status IN ('submitted', 'under_review', 'evaluated', 'completed')
            ORDER BY ap.year DESC, ap.start_date DESC
            LIMIT 1
        ");
        $stmt_fb->execute([$companyId]);
        $latestAssessment = $stmt_fb->fetch();
        
        if ($latestAssessment) {
            $assessmentId = $latestAssessment['id'];
        } else {
            setFlashMessage('ไม่พบข้อมูลการประเมิน', 'error');
            redirect(getBaseUrl() . '/pages/dashboard.php');
        }
    }
}

$assessment = getAssessmentWithScores($assessmentId);

if (!$assessment) {
    setFlashMessage('ไม่พบข้อมูลแบบประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// Fetch history (using Self Score instead of Final Score which might include Auditor score)
// We might need to adjust the history query if it relies on final_score in table
$history = getCompanyAssessmentHistory($companyId);

// Preview mode — hide navbar/sidebar when rendered inside preview.php iframe
$isPreview = !empty($_GET['_preview']);

function getLevelInfo($level) {
    $levels = [
        1 => ['name' => 'เริ่มต้น', 'name_en' => 'Emerging', 'color' => '#EF4444', 'bg' => '#FEE2E2'],
        2 => ['name' => 'กำลังพัฒนา', 'name_en' => 'Developing', 'color' => '#F59E0B', 'bg' => '#FEF3C7'],
        3 => ['name' => 'พัฒนาดี', 'name_en' => 'Performing', 'color' => '#3B82F6', 'bg' => '#DBEAFE'],
        4 => ['name' => 'เป็นเลิศ', 'name_en' => 'Excellence', 'color' => '#8B5CF6', 'bg' => '#EDE9FE'],
        5 => ['name' => 'ระดับโลก', 'name_en' => 'World-Class', 'color' => '#10B981', 'bg' => '#D1FAE5']
    ];
    return $levels[$level] ?? $levels[1];
}

$sText = 'รอดำเนินการ';
if ($assessment['status'] === 'submitted') $sText = 'ส่งแล้ว';
else if ($assessment['status'] === 'evaluated') $sText = 'ประเมินแล้ว';
else if ($assessment['status'] === 'completed') $sText = 'เสร็จสมบูรณ์';
else if ($assessment['status'] === 'draft') $sText = 'ฉบับร่าง';
else if ($assessment['status'] === 'under_review') $sText = 'อยู่ระหว่างการตรวจสอบ';

// Use Self Score for display in this view
$displayScore = $assessment['self_total_score'];
$displayLevel = calculateHICMLevel($displayScore);

// Combined mode: check if ?combined=1 AND period has show_auditor_results enabled
$isCombinedMode = false;
$isCombinedRequest = isset($_GET['combined']);
$auditorResultsLocked = false;
$announcementDate = null;
$auditorDisplayScore = 0;
$auditorDisplayLevel = 1;
$evaluatorsList = $assessment['evaluators'] ?? [];

if ($isCombinedRequest) {
    // Check if this period has show_auditor_results enabled
    $dbCheck = getDB();
    $stmtCheck = $dbCheck->prepare("SELECT show_auditor_results, announcement_date FROM assessment_periods WHERE id = ?");
    $stmtCheck->execute([$assessment['period_id']]);
    $periodRow = $stmtCheck->fetch();
    
    if ($periodRow && $periodRow['show_auditor_results']) {
        $isCombinedMode = true;
        
        // Calculate auditor total score — use total active count for weight distribution
        // so partial scoring doesn't inflate the total
        $auditorTotalScore = 0;
        foreach ($assessment['pillars'] as $pCode => $pData) {
            // Total active (non-NA) indicators in this pillar
            $allActiveInds = array_filter($pData['indicators'], fn($i) => !$i['is_na']);
            $totalActiveCount = count($allActiveInds);
            // Only indicators that have actual auditor scores
            $scoredInds = array_filter($pData['indicators'], function($i) {
                return !$i['is_na'] && !$i['auditor_is_na'] 
                    && $i['auditor_score'] !== null && $i['auditor_score'] !== '';
            });
            $scoredCount = count($scoredInds);
            if ($scoredCount > 0 && $totalActiveCount > 0) {
                $ppi = $pData['weight'] / $totalActiveCount;
                foreach ($scoredInds as $ind) {
                    $auditorTotalScore += floatval($ind['auditor_score']) * $ppi;
                }
            }
        }
        $auditorDisplayScore = round($auditorTotalScore, 1);
        $auditorDisplayLevel = calculateHICMLevel($auditorDisplayScore);
    } else {
        // Period doesn't allow showing auditor results — show inline banner instead of flash
        $auditorResultsLocked = true;
        $announcementDate = $periodRow['announcement_date'] ?? null;
    }
}

// Fetch all attachments for this assessment to avoid N+1 queries during loop
// and to populate the modal
$db = getDB();
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
    $allAttachments = $stmt->fetchAll();
    
    foreach ($allAttachments as $file) {
        $indId = $file['indicator_id'];
        if (!isset($attachments[$indId])) {
            $attachments[$indId] = [];
        }
        $attachments[$indId][] = [
            'id' => $file['id'],
            'name' => $file['file_original_name'],
            'size' => $file['file_size'],
            'type' => $file['file_type'],
            'date' => date('d/m/Y H:i', strtotime($file['created_at']))
        ];
    }
} catch (Exception $e) {
    error_log("Fetch attachments error: " . $e->getMessage());
}

// Pre-calculate pillar scores for radar chart
$pillarChartData = [];
$pillarOrder = ['H1', 'I2', 'C3', 'M4'];
$pillarLabels = [
    'H1' => 'H1: สุขภาพ',
    'I2' => 'I2: ความปลอดภัย', 
    'C3' => 'C3: ชุมชน/สังคม',
    'M4' => 'M4: บริหารจัดการ'
];
$pillarColors = [
    'H1' => '#10B981',
    'I2' => '#3B82F6',
    'C3' => '#F59E0B',
    'M4' => '#8B5CF6'
];

foreach ($pillarOrder as $code) {
    $pillar = $assessment['pillars'][$code] ?? null;
    if ($pillar) {
        $activeIndicators = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
        $activeCount = count($activeIndicators);
        $selfTotal = array_sum(array_column($activeIndicators, 'self_score'));
        $percentage = $activeCount > 0 ? ($selfTotal / $activeCount) * 100 : 0;
        
        // Weighted score calculation
        $pointPerIndicator = $activeCount > 0 ? ($pillar['weight'] / $activeCount) : 0;
        $earnedPoints = 0;
        foreach ($pillar['indicators'] as $ind) {
            if (!$ind['is_na']) $earnedPoints += $ind['self_score'] * $pointPerIndicator;
        }
        
        $pillarChartData[$code] = [
            'label' => $pillarLabels[$code],
            'percentage' => round($percentage, 1),
            'earned' => round($earnedPoints, 1),
            'weight' => $pillar['weight'],
            'count' => count($pillar['indicators']),
            'activeCount' => $activeCount,
            'color' => $pillarColors[$code]
        ];
        
        // Add auditor scores for combined mode
        if ($isCombinedMode) {
            // Only count indicators that actually have auditor scores (not NULL)
            $auditorScoredInds = array_filter($pillar['indicators'], function($i) {
                return !$i['is_na'] && !$i['auditor_is_na'] 
                    && $i['auditor_score'] !== null && $i['auditor_score'] !== '';
            });
            $auditorScoredCount = count($auditorScoredInds);
            $auditorScoreSum = 0;
            $auditorEarned = 0;
            // Use total active count for weight distribution (not just scored count)
            foreach ($auditorScoredInds as $ai) {
                $as = floatval($ai['auditor_score']);
                $auditorScoreSum += $as;
                if ($activeCount > 0) {
                    $auditorEarned += $as * ($pillar['weight'] / $activeCount);
                }
            }
            $auditorPct = $auditorScoredCount > 0 ? ($auditorScoreSum / $auditorScoredCount) * 100 : 0;
            $pillarChartData[$code]['auditor_percentage'] = round($auditorPct, 1);
            $pillarChartData[$code]['auditor_earned'] = round($auditorEarned, 1);
            $pillarChartData[$code]['auditor_scored_count'] = $auditorScoredCount;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isCombinedMode ? 'ผลการประเมินรวม' : ($auditorResultsLocked ? 'ผลการประเมินรวม (รอเปิดเผย)' : 'ผลการประเมินตนเอง'); ?> - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/pdf-export.js"></script>
</head>
<body class="<?php echo $isPreview ? '' : 'has-sidebar'; ?>">
    <?php if (!$isPreview): ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="sidebar-overlay"></div>
    <?php endif; ?>
    
    <style>
        :root {
            --pro-primary: #6366f1;
            --pro-primary-light: #818cf8;
            --pro-primary-dark: #4f46e5;
            --pro-secondary: #64748b;
            --pro-success: #10b981;
            --pro-info: #0ea5e9;
            --pro-warning: #f59e0b;
            --pro-danger: #ef4444;
            --pro-purple: #8b5cf6;
            --pro-bg: #f8fafc;
            --pro-card-bg: rgba(255, 255, 255, 0.9);
            --pro-border: #e2e8f0;
            --pro-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            --radius-xl: 20px;
            --radius-2xl: 32px;
        }

        .main-wrapper {
            background-color: var(--pro-bg);
            min-height: 100vh;
        }

        /* Result Hero Box */
        .result-hero {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: var(--radius-2xl);
            padding: 3.5rem 2.5rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.3);
        }

        .result-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-main {
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .hero-title {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--pro-primary-light);
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hero-company {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
            background: linear-gradient(to right, #fff 30%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-meta {
            margin-top: 1rem;
            display: flex;
            gap: 1.5rem;
            color: #94a3b8;
            font-size: 0.95rem;
        }

        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Score & Level Display */
        .hero-score-zone {
            display: flex;
            align-items: center;
            gap: 3rem;
            position: relative;
            z-index: 1;
        }

        .score-circle {
            text-align: center;
        }

        .score-value {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            display: block;
        }

        .score-label {
            font-size: 0.875rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.5rem;
        }

        .level-seal {
            padding: 1.5rem;
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 180px;
            text-align: center;
        }

        .level-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.2);
        }

        .level-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
        }

        .level-name-en {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* Pro Cards */
        .pro-card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
            transition: all 0.3s ease;
        }

        .pro-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.08);
        }

        .card-header-pro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .card-title-pro {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        /* Animations */
        @keyframes reveal {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .reveal {
            animation: reveal 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        /* Standardized Export Button Styles (Hero overlay) */
        .result-hero .btn-print {
            background: rgba(16, 185, 129, 0.25) !important;
            border: 1px solid rgba(16, 185, 129, 0.5) !important;
            color: white;
            backdrop-filter: blur(5px);
        }
        .result-hero .btn-print:hover {
            background: rgba(16, 185, 129, 0.4) !important;
        }
        .result-hero .btn-pdf {
            background: rgba(239, 68, 68, 0.25) !important;
            border: 1px solid rgba(239, 68, 68, 0.5) !important;
            color: white;
            backdrop-filter: blur(5px);
        }
        .result-hero .btn-pdf:hover {
            background: rgba(239, 68, 68, 0.4) !important;
        }
        .result-hero .btn-excel {
            background: rgba(245, 158, 11, 0.25) !important;
            border: 1px solid rgba(245, 158, 11, 0.5) !important;
            color: white;
            backdrop-filter: blur(5px);
        }
        .result-hero .btn-excel:hover {
            background: rgba(245, 158, 11, 0.4) !important;
        }

        /* Utility */
        .grid-pro {
            display: grid;
            gap: 2rem;
        }

        /* ========== Responsive ========== */
        @media (max-width: 1024px) {
            .result-hero { flex-direction: column; text-align: center; gap: 2rem; padding: 2.5rem 2rem; }
            .hero-meta { justify-content: center; flex-wrap: wrap; }
            .hero-score-zone { flex-direction: column; gap: 1.5rem; }
            .hero-company { font-size: 2rem; }
        }

        @media (max-width: 768px) {
            /* Hero */
            .result-hero { padding: 1.75rem 1.25rem; margin-bottom: 1.5rem; border-radius: 20px; }
            .result-hero::before { width: 300px; height: 300px; }
            .hero-company { font-size: 1.5rem; }
            .hero-title { font-size: 0.75rem; justify-content: center; }
            .hero-meta { gap: 0.75rem; font-size: 0.8rem; flex-direction: column; align-items: center; }
            .score-value { font-size: 3rem !important; }
            .score-label { font-size: 0.75rem; }
            .level-seal { min-width: 140px; padding: 1rem; border-radius: 18px; }
            .level-circle { width: 48px; height: 48px; font-size: 1.25rem; }
            .level-name { font-size: 0.95rem; }
            .hero-score-zone { gap: 1.25rem; }

            /* Period selector */
            .result-hero select { min-width: 150px !important; font-size: 0.8rem !important; }

            /* Action buttons in hero */
            .result-hero .btn { font-size: 0.75rem !important; padding: 0.4rem 0.75rem !important; }

            /* Pro Cards */
            .pro-card { border-radius: 18px; padding: 1.25rem; }
            .pro-card:hover { transform: none; }
            .card-header-pro { margin-bottom: 1.25rem; flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .card-title-pro { font-size: 1rem; gap: 0.75rem; }
            .card-title-pro > div:first-child { width: 34px; height: 34px; border-radius: 10px; }
            .card-title-pro > div:first-child svg { width: 16px; height: 16px; }

            /* Grid overrides */
            .grid-pro { gap: 1.25rem; }

            /* Radar / Charts */
            .radar-layout {
                flex-direction: column !important;
            }
            .radar-legend-panel {
                width: 100% !important;
                flex-direction: row !important;
                flex-wrap: wrap !important;
            }
            .radar-legend-panel > div {
                flex: 1 1 45%;
            }

            /* Pillar card header */
            .pillar-header {
                flex-direction: column !important;
                text-align: center !important;
                gap: 1rem !important;
                padding: 1.5rem 1.25rem !important;
            }
            .pillar-header-left {
                flex-direction: column !important;
                align-items: center !important;
            }
            .pillar-header-right {
                text-align: center !important;
            }

            /* Table */
            .pro-card table th { padding: 0.75rem 0.75rem !important; font-size: 0.6rem !important; min-width: auto !important; }
            .pro-card table td { padding: 0.75rem 0.75rem !important; }

            /* Indicator name cell */
            .pro-card table td:first-child { min-width: 180px; }
            .pro-card table td:first-child > div { gap: 0.5rem !important; }
            .pro-card table td:first-child .indicator-desc { display: none !important; }

            /* Score cells */
            .pro-card table td:nth-child(2),
            .pro-card table td:nth-child(3) { min-width: 70px; }
        }

        @media (max-width: 480px) {
            /* Hero compact */
            .result-hero { padding: 1.25rem 1rem; border-radius: 16px; }
            .hero-company { font-size: 1.2rem; }
            .score-value { font-size: 2.5rem !important; }
            .level-seal { min-width: 120px; padding: 0.75rem; border-radius: 14px; }
            .level-circle { width: 40px; height: 40px; font-size: 1rem; margin-bottom: 0.5rem; }
            .level-name { font-size: 0.85rem; }
            .level-name-en { font-size: 0.65rem; }

            /* Cards grid → single column */
            .grid.grid-cols-1.lg\\:grid-cols-4 { grid-template-columns: 1fr !important; }
            .grid.grid-cols-1.lg\\:grid-cols-2 { grid-template-columns: 1fr !important; }

            /* Chart height */
            .pro-card canvas { max-height: 250px; }

            /* Radar legend 1-col */
            .radar-legend-panel > div { flex: 1 1 100% !important; }

            /* Pillar header → stacked compact */
            .pillar-header-left > div:first-child {
                width: 40px !important; height: 40px !important; border-radius: 12px !important;
            }
            .pillar-header-left > div:first-child svg { width: 20px !important; height: 20px !important; }
            .pillar-header-left h3 { font-size: 1.1rem !important; }

            /* Table font sizes */
            .pro-card table th { font-size: 0.55rem !important; padding: 0.5rem !important; }
            .pro-card table td { font-size: 0.8rem !important; padding: 0.5rem 0.5rem !important; }

            /* Auditor tooltip → wider allowance */
            .auditor-tooltip { white-space: normal !important; min-width: 160px; }

            /* Auditor icons even smaller on mobile */
            .auditor-icon-chip { width: 22px !important; height: 22px !important; }
            .auditor-icon-chip svg { width: 10px !important; height: 10px !important; }
            .auditor-icon-chip > span:last-of-type { width: 10px !important; height: 10px !important; font-size: 0.35rem !important; }

            /* Action buttons wrap */
            .result-hero > div:last-child > div:last-child,
            div[style*="display: flex"][style*="gap: 0.75rem"][style*="flex-wrap: wrap"] {
                justify-content: center;
            }
        }

        /* ========== Print Styles ========== */
        @media print {
            @page { size: A4; margin: 10mm 8mm; }

            /* Hide interactive elements */
            .hero-meta-item select,
            .modal-overlay, .preview-modal,
            button, [onclick],
            canvas {
                display: none !important;
            }

            /* Hero section: compact for paper */
            .result-hero {
                background: #1e3a5f !important;
                color: white !important;
                padding: 1.5rem !important;
                border-radius: 0 !important;
                margin: 0 !important;
                page-break-after: avoid;
            }

            .result-hero * {
                color: white !important;
                -webkit-text-fill-color: white !important;
            }

            .hero-score-zone {
                flex-direction: row !important;
                gap: 1.5rem !important;
            }

            .score-value {
                font-size: 2.5rem !important;
            }

            .level-seal {
                background: rgba(255,255,255,0.1) !important;
                border: 1px solid rgba(255,255,255,0.2) !important;
                padding: 0.75rem 1rem !important;
            }

            /* Summary cards grid */
            .grid-pro {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }

            .pro-card {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                border-radius: 8px !important;
                page-break-inside: avoid;
            }

            /* Radar chart → print fallback */
            .radar-layout {
                display: none !important;
            }

            /* Pillar detail tables */
            .pro-card table {
                font-size: 8pt !important;
                width: 100% !important;
            }

            .pro-card table th {
                background-color: #f1f5f9 !important;
                font-size: 7pt !important;
                padding: 0.5rem !important;
                text-transform: uppercase;
            }

            .pro-card table td {
                padding: 0.5rem !important;
                font-size: 8pt !important;
                border-bottom: 1px solid #e2e8f0 !important;
            }

            /* Pillar card headers */
            .pro-card > div:first-child {
                padding: 1rem !important;
            }

            /* Historical chart → hide (not printable canvas) */
            .pro-card:has(canvas) {
                display: none !important;
            }

            /* Verification Files buttons */
            button[onclick*="showAttachments"] {
                display: none !important;
            }

            /* Show attachment count as text instead */
            td:has(button[onclick*="showAttachments"])::after {
                content: attr(data-attachment-text);
            }

            /* Reveal helper class */
            .reveal {
                opacity: 1 !important;
                transform: none !important;
            }
        }
    </style>

    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>

            <!-- Print-Only Document Header -->
            <div class="print-only print-doc-header">
                <h1>รายงานผลการประเมิน HICM V2025</h1>
                <p><?php echo htmlspecialchars($assessment['company_name']); ?> — รอบ <?php echo htmlspecialchars($assessment['period_name']); ?> (<?php echo $assessment['year']; ?>)</p>
                <p>วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?> น.</p>
            </div>
            
            <!-- Result Hero Section -->
            <div class="result-hero reveal">
                <div class="hero-main">
                    <div class="hero-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <?php echo $isCombinedMode ? 'Combined Assessment Result' : ($auditorResultsLocked ? 'Combined Assessment Result — Self Score Only' : 'Official Assessment Result'); ?>
                    </div>
                    <h1 class="hero-company"><?php echo htmlspecialchars($assessment['company_name']); ?></h1>
                    <div class="hero-meta">
                        <div class="hero-meta-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <?php echo htmlspecialchars($assessment['period_name']); ?> (<?php echo $assessment['year']; ?>)
                        </div>
                        <div class="hero-meta-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <?php echo $isCombinedMode ? 'Self + Auditor Evaluation' : ($auditorResultsLocked ? 'Self Evaluation (Auditor: 🔒)' : 'Self Evaluation'); ?>
                        </div>
                    </div>
                    
                    <!-- Period Selector -->
                    <?php if (count($history) > 1): ?>
                    <div style="margin-top: 1.5rem;">
                        <div style="display: inline-flex; align-items: center; gap: 0.75rem; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 14px; padding: 0.5rem 0.75rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7; flex-shrink: 0;">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                            <span style="font-size: 0.8rem; opacity: 0.8; white-space: nowrap;">ดูรอบอื่น:</span>
                            <select onchange="if(this.value) location.href='?period_id='+this.value+'<?php echo $isCombinedRequest ? '&combined=1' : ''; ?>'" 
                                    style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 8px; padding: 0.4rem 0.75rem; font-size: 0.85rem; font-family: 'Prompt', sans-serif; cursor: pointer; outline: none; min-width: 180px;">
                                <?php foreach ($history as $h): 
                                    $isLocked = $isCombinedRequest && empty($h['show_auditor_results']);
                                    $lockIcon = $isLocked ? '🔒 ' : ($isCombinedRequest ? '✅ ' : '');
                                ?>
                                <option value="<?php echo $h['period_id']; ?>" 
                                        <?php echo ($h['id'] == $assessmentId) ? 'selected' : ''; ?>
                                        style="color: #1e293b; background: white;">
                                    <?php echo $lockIcon . htmlspecialchars($h['period_name']); ?> (<?php echo $h['year']; ?>) — <?php echo number_format($h['self_total_score'] ?? 0, 0); ?> คะแนน
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons in Hero -->
                    <div style="margin-top: 2.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <button onclick="HICM_PDF.print()" class="btn btn-sm btn-print" title="พิมพ์ (Ctrl+P)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            พิมพ์
                        </button>
                        <button onclick="downloadResultPDF()" class="btn btn-sm btn-pdf" title="ดาวน์โหลด PDF">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="12" y1="18" x2="12" y2="12"/>
                                <polyline points="9 15 12 18 15 15"/>
                            </svg>
                            PDF
                        </button>
                        <button onclick="triggerExport()" class="btn btn-sm btn-excel" title="Export Excel">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/>
                            </svg>
                            Excel
                        </button>
                    </div>
                </div>

                <div class="hero-score-zone">
                    <?php if ($isCombinedMode): ?>
                    <!-- Combined Mode: Self + Auditor Scores Side by Side -->
                    <div style="display: flex; gap: 2rem; align-items: center;">
                        <div class="score-circle" style="text-align: center;">
                            <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; font-weight: 600;">ประเมินตนเอง</span>
                            <span class="score-value" style="font-size: 3rem; display: block;"><?php echo number_format($displayScore, 0); ?></span>
                            <span class="score-label" style="font-size: 0.75rem;">/ 1,000</span>
                        </div>
                        <div style="width: 1px; height: 80px; background: rgba(255,255,255,0.15);"></div>
                        <div class="score-circle" style="text-align: center;">
                            <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; color: #fbbf24; font-weight: 600;">กรรมการประเมิน</span>
                            <span class="score-value" style="font-size: 3rem; display: block; background: linear-gradient(to right, #fbbf24, #f59e0b); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"><?php echo number_format($auditorDisplayScore, 0); ?></span>
                            <span class="score-label" style="font-size: 0.75rem;">/ 1,000</span>
                        </div>
                    </div>
                    
                    <?php 
                    $auditorLevelInfo = getLevelInfo($auditorDisplayLevel);
                    ?>
                    <div class="level-seal">
                        <div class="level-circle" style="background: <?php echo $auditorLevelInfo['color']; ?>;"><?php echo $auditorDisplayLevel; ?></div>
                        <div class="level-name">HICM <?php echo $auditorLevelInfo['name']; ?></div>
                        <div class="level-name-en"><?php echo $auditorLevelInfo['name_en']; ?> Level</div>
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 0.35rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 0.35rem;">ระดับจากกรรมการ</div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Self Only Mode -->
                    <div class="score-circle">
                        <span class="score-value"><?php echo number_format($displayScore, 0); ?></span>
                        <span class="score-label">/ 1,000 Points</span>
                    </div>

                    <?php $levelInfo = getLevelInfo($displayLevel); ?>
                    <div class="level-seal">
                        <div class="level-circle" style="background: <?php echo $levelInfo['color']; ?>;"><?php echo $displayLevel; ?></div>
                        <div class="level-name">HICM <?php echo $levelInfo['name']; ?></div>
                        <div class="level-name-en"><?php echo $levelInfo['name_en']; ?> Level</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($auditorResultsLocked): ?>
            <!-- Auditor Results Locked Banner -->
            <div class="reveal" style="
                background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
                border: 1px solid #fde68a;
                border-left: 4px solid #f59e0b;
                border-radius: 16px;
                padding: 1.25rem 1.5rem;
                margin-bottom: 1.5rem;
                display: flex;
                align-items: flex-start;
                gap: 1rem;
                box-shadow: 0 2px 12px rgba(245,158,11,0.08);
            ">
                <div style="
                    width: 44px; height: 44px; min-width: 44px;
                    background: linear-gradient(135deg, #f59e0b, #d97706);
                    border-radius: 12px;
                    display: flex; align-items: center; justify-content: center;
                    color: white; font-size: 1.2rem;
                    box-shadow: 0 4px 12px rgba(245,158,11,0.3);
                ">🔒</div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; color: #92400e; font-size: 0.95rem; margin-bottom: 0.35rem;">
                        ผลการประเมินจากคณะกรรมการ "<?php echo htmlspecialchars($assessment['period_name']); ?>"
                    </div>
                    <div style="color: #a16207; font-size: 0.85rem; line-height: 1.6;">
                        <?php if ($announcementDate): ?>
                            <?php 
                            $annDate = new DateTime($announcementDate);
                            $today = new DateTime();
                            $diff = $today->diff($annDate);
                            $isPast = $annDate < $today;
                            $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                            $annDay = intval($annDate->format('d'));
                            $annMonth = $thaiMonths[intval($annDate->format('m'))];
                            $annYear = intval($annDate->format('Y')) + 543;
                            ?>
                            <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                กำหนดประกาศผล: <strong><?php echo "{$annDay} {$annMonth} {$annYear}"; ?></strong>
                                <?php if (!$isPast): ?>
                                    <span style="background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 0.15rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #92400e; margin-left: 0.25rem;">
                                        อีก <?php echo $diff->days; ?> วัน
                                    </span>
                                <?php else: ?>
                                    <span style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 0.15rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #991b1b; margin-left: 0.25rem;">
                                        รอประกาศผล
                                    </span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            กรุณารอการประกาศผลจากผู้ดูแลระบบ
                        <?php endif; ?>
                    </div>
                    <div style="color: #b45309; font-size: 0.8rem; margin-top: 0.5rem; opacity: 0.8;">
                        ขณะนี้แสดงเฉพาะผลการประเมินตนเอง (Self Evaluation) เท่านั้น
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dashboard Widgets -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-8">
                <!-- Status Card -->
                <div class="pro-card reveal" style="animation-delay: 0.1s;">
                    <div style="color: var(--pro-secondary); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">Submission Status</div>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span class="badge <?php 
                            if ($assessment['status'] === 'completed') echo 'badge-success';
                            elseif ($assessment['status'] === 'evaluated') echo 'badge-info';
                            elseif ($assessment['status'] === 'submitted' || $assessment['status'] === 'under_review') echo 'badge-warning';
                            else echo 'badge-secondary';
                        ?>" style="padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.95rem;">
                            <?php echo $sText; ?>
                        </span>
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: var(--pro-primary);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Submission Date -->
                <div class="pro-card reveal" style="animation-delay: 0.2s;">
                    <div style="color: var(--pro-secondary); font-size: 0.875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem;">Submitted On</div>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">
                            <?php echo $assessment['submitted_at'] ? date('d M Y', strtotime($assessment['submitted_at'])) : 'No record'; ?>
                        </div>
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: var(--pro-info);">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Improvements might go here if we want more stats -->
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- History Chart -->
                <div class="pro-card reveal" style="animation-delay: 0.3s;">
                    <div class="card-header-pro">
                        <div class="card-title-pro">
                            <div style="width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #eef2ff, #e0e7ff); display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--pro-primary)" stroke-width="2.5">
                                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                                </svg>
                            </div>
                            <div>
                                <div style="font-size: 1.1rem;">Historical Performance</div>
                                <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 500; margin-top: 0.1rem;">ประวัติคะแนนย้อนหลัง</div>
                            </div>
                        </div>
                    </div>
                    <div style="height: 350px; position: relative;">
                        <canvas id="historyChart"></canvas>
                    </div>
                </div>

                <!-- Radar Chart (Spider Chart) -->
                <div class="pro-card reveal" style="animation-delay: 0.4s;">
                    <div class="card-header-pro">
                        <div class="card-title-pro">
                            <div style="width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, #ecfdf5, #d1fae5); display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--pro-success)" stroke-width="2.5">
                                    <polygon points="12 2 2 7 2 17 12 22 22 17 22 7 12 2"/>
                                </svg>
                            </div>
                            <div>
                                <div style="font-size: 1.1rem;">Balanced Scorecard</div>
                                <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 500; margin-top: 0.1rem;"><?php echo $isCombinedMode ? 'เปรียบเทียบผลประเมินตนเอง vs กรรมการ' : 'ผลประเมิน 4 เสาหลัก'; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="radar-layout" style="display: flex; gap: 1rem; align-items: stretch;">
                        <!-- Radar Canvas -->
                        <div style="flex: 1; height: 340px; min-width: 0; position: relative;">
                            <canvas id="resultRadarChart"></canvas>
                        </div>
                        <!-- Pillar Legend / Stats -->
                        <div class="radar-legend-panel" style="width: 180px; display: flex; flex-direction: column; gap: 0.65rem; justify-content: center; flex-shrink: 0;">
                            <?php foreach ($pillarChartData as $code => $pData): ?>
                            <div style="padding: 0.7rem 0.85rem; border-radius: 14px; background: <?php echo $code === 'H1' ? '#ecfdf5' : ($code === 'I2' ? '#eff6ff' : ($code === 'C3' ? '#fffbeb' : '#f5f3ff')); ?>; border: 1px solid <?php echo $code === 'H1' ? '#d1fae5' : ($code === 'I2' ? '#dbeafe' : ($code === 'C3' ? '#fef3c7' : '#ede9fe')); ?>;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $pData['color']; ?>;"></div>
                                    <span style="font-size: 0.7rem; font-weight: 700; color: #475569; letter-spacing: 0.02em;"><?php echo $code; ?></span>
                                </div>
                                <div style="font-size: 1.3rem; font-weight: 800; color: #1e293b; line-height: 1;"><?php echo $pData['percentage']; ?>%</div>
                                <div style="font-size: 0.65rem; color: #64748b; margin-top: 0.15rem;"><?php echo $pData['earned']; ?>/<?php echo $pData['weight']; ?> pts</div>
                                <?php if ($isCombinedMode && isset($pData['auditor_percentage'])): ?>
                                <div style="margin-top: 0.4rem; padding-top: 0.35rem; border-top: 1px dashed <?php echo $code === 'H1' ? '#a7f3d0' : ($code === 'I2' ? '#bfdbfe' : ($code === 'C3' ? '#fde68a' : '#ddd6fe')); ?>;">
                                    <div style="font-size: 0.6rem; color: #f59e0b; font-weight: 600; text-transform: uppercase;">กรรมการ</div>
                                    <div style="font-size: 1rem; font-weight: 800; color: #92400e; line-height: 1; margin-top: 0.1rem;"><?php echo $pData['auditor_percentage']; ?>%</div>
                                    <div style="font-size: 0.6rem; color: #b45309;"><?php echo $pData['auditor_earned']; ?>/<?php echo $pData['weight']; ?> pts</div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assessment Details - Grouped by Pillar -->
            <div style="margin-bottom: 4rem;">
                <div class="reveal" style="margin-bottom: 2rem;">
                    <h2 style="font-size: 1.75rem; font-weight: 800; color: #1e293b; letter-spacing: -0.01em;">Detailed Pillar Breakdown</h2>
                    <p style="color: #64748b; margin-top: 0.5rem;"><?php echo $isCombinedMode ? 'เปรียบเทียบผลคะแนนประเมินตนเองและกรรมการ ทุกตัวชี้วัด' : 'A comprehensive view of your performance across each strategic area.'; ?></p>
                </div>
                
                <div class="grid-pro">
                    <?php
                    $pillarInfo = [
                        'H1' => ['name_en' => 'Health Promotion',               'name_th' => 'การส่งเสริมสุขภาพ',              'color' => '#10B981', 'bg' => '#ecfdf5', 'icon' => 'heart'],
                        'I2' => ['name_en' => 'Industrial Safety & Environment', 'name_th' => 'ความปลอดภัยและสิ่งแวดล้อม',     'color' => '#3B82F6', 'bg' => '#eff6ff', 'icon' => 'shield'],
                        'C3' => ['name_en' => 'Community Engagement',            'name_th' => 'การมีส่วนร่วมกับชุมชน',         'color' => '#F59E0B', 'bg' => '#fffbeb', 'icon' => 'users'],
                        'M4' => ['name_en' => 'Management & Sustainability',     'name_th' => 'การบริหารจัดการและความยั่งยืน', 'color' => '#8B5CF6', 'bg' => '#f5f3ff', 'icon' => 'activity']
                    ];
                    
                    foreach ($assessment['pillars'] as $pillarCode => $pillar):
                        $info = $pillarInfo[$pillarCode];
                        $activeIndicators = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
                        $activeCount = count($activeIndicators);
                    ?>
                        <div class="pro-card reveal" style="padding: 0; overflow: hidden; border-radius: 28px;">
                            <!-- Pillar Header -->
                            <div class="pillar-header" style="padding: 2rem; background: <?php echo $info['bg']; ?>; border-bottom: 1px solid var(--pro-border); display: flex; align-items: center; justify-content: space-between;">
                                <div class="pillar-header-left" style="display: flex; align-items: center; gap: 1.25rem;">
                                    <div style="width: 56px; height: 56px; border-radius: 16px; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; color: <?php echo $info['color']; ?>;">
                                        <?php if ($pillarCode === 'H1'): ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                                        <?php elseif ($pillarCode === 'I2'): ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                                        <?php elseif ($pillarCode === 'C3'): ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                        <?php else: ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 1.5rem; font-weight: 800; color: #1e293b; margin: 0;"><?php echo $pillarCode . ': ' . $info['name_en']; ?></h3>
                                        <div style="font-size: 0.78rem; color: #64748b; font-weight: 500; margin-top: 0.2rem; letter-spacing: 0.01em;"><?php echo $info['name_th']; ?></div>
                                        <div style="font-size: 0.875rem; color: <?php echo $info['color']; ?>; font-weight: 600; margin-top: 0.15rem; display: flex; align-items: center; gap: 0.35rem;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            Weight: <?php echo $pillar['weight']; ?> pts
                                        </div>
                                    </div>
                                </div>
                                <div class="pillar-header-right" style="text-align: right;">
                                    <div style="font-size: 2rem; font-weight: 800; color: #1e293b; line-height: 1;">
                                        <?php 
                                            $activeIndicators = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
                                            $activeCount = count($activeIndicators);
                                            $pointPerIndicator = $activeCount > 0 ? ($pillar['weight'] / $activeCount) : 0;
                                            
                                            $pillarTotal = 0;
                                            foreach($pillar['indicators'] as $ind) if(!$ind['is_na']) $pillarTotal += $ind['self_score'] * $pointPerIndicator;
                                            echo number_format($pillarTotal, 1);
                                        ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin-top: 0.25rem;">
                                        <?php echo $isCombinedMode ? 'Self Score' : 'Earned Points'; ?>
                                    </div>
                                    <?php if ($isCombinedMode): ?>
                                    <?php
                                        // Only count actually scored indicators (not NULL)
                                        $auditorScoredInds = array_filter($pillar['indicators'], function($i) {
                                            return !$i['is_na'] && !$i['auditor_is_na'] 
                                                && $i['auditor_score'] !== null && $i['auditor_score'] !== '';
                                        });
                                        $auditorScoredCount = count($auditorScoredInds);
                                        // Use activeCount for weight distribution so partial scoring is proportional
                                        $auditorPpi = $activeCount > 0 ? ($pillar['weight'] / $activeCount) : 0;
                                        $auditorPillarTotal = 0;
                                        foreach ($auditorScoredInds as $ai) {
                                            $auditorPillarTotal += floatval($ai['auditor_score']) * $auditorPpi;
                                        }
                                    ?>
                                    <div style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed <?php echo $info['color']; ?>33;">
                                        <div style="font-size: 1.5rem; font-weight: 800; color: #92400e; line-height: 1;">
                                            <?php echo $auditorScoredCount > 0 ? number_format($auditorPillarTotal, 1) : '—'; ?>
                                        </div>
                                        <div style="font-size: 0.7rem; color: #b45309; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; margin-top: 0.15rem;">
                                            Auditor Avg. (<?php echo $auditorScoredCount; ?>/<?php echo $activeCount; ?> scored)
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Indicators Table -->
                            <div style="overflow-x: auto;">
                                <table class="table" style="margin-bottom: 0; border: none; width: 100%; border-collapse: separate; border-spacing: 0;">
                                    <thead>
                                        <tr style="background-color: #f8fafc; color: #475569; text-transform: uppercase; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em;">
                                            <th style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--pro-border); min-width: 280px;">ตัวชี้วัด</th>
                                            <th style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--pro-border); text-align: center; min-width: 100px;">
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.15rem;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                    <span>ประเมินตนเอง</span>
                                                </div>
                                            </th>
                                            <?php if ($isCombinedMode): ?>
                                            <th style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--pro-border); text-align: center; background: #fffdf7; min-width: 140px;">
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.15rem; color: #92400e;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                                    <span>กรรมการ<?php echo count($evaluatorsList) > 1 ? ' (' . count($evaluatorsList) . ' ท่าน)' : ''; ?></span>
                                                </div>
                                            </th>
                                            <?php endif; ?>
                                            <th style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--pro-border); min-width: 160px;">หลักฐาน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        foreach ($pillar['indicators'] as $index => $indicator): 
                                            $isLast = ($index === count($pillar['indicators']) - 1);
                                            $borderStyle = $isLast ? 'none' : '1px solid #f1f5f9';
                                        ?>
                                            <tr style="<?php echo $indicator['is_na'] ? 'background-color: #f8fafc; opacity: 0.6;' : ''; ?>">
                                                <!-- Indicator Name -->
                                                <td style="padding: 1.25rem 1.5rem; border-bottom: <?php echo $borderStyle; ?>; vertical-align: top;">
                                                    <div style="display: flex; gap: 0.75rem;">
                                                        <div style="font-weight: 700; color: <?php echo $info['color']; ?>; font-size: 0.75rem; min-width: 38px; padding: 0.2rem 0.4rem; background: <?php echo $info['bg']; ?>; border-radius: 6px; text-align: center; height: fit-content; line-height: 1.4;"><?php echo $indicator['indicator_code']; ?></div>
                                                        <div style="min-width: 0;">
                                                            <div style="font-weight: 600; color: #334155; line-height: 1.5; font-size: 0.875rem;"><?php echo $indicator['indicator_name']; ?></div>
                                                            <div class="indicator-desc" style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                                <?php echo $indicator['description']; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Self Score -->
                                                <td style="padding: 1.25rem 1.25rem; border-bottom: <?php echo $borderStyle; ?>; vertical-align: top; text-align: center;">
                                                    <?php if ($indicator['is_na']): ?>
                                                        <span style="display: inline-block; padding: 0.25rem 0.6rem; background: #f1f5f9; color: #94a3b8; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">N/A</span>
                                                    <?php else: ?>
                                                        <div style="font-size: 1.35rem; font-weight: 800; color: #1e293b; line-height: 1;">
                                                            <?php echo number_format($indicator['self_score'] * $pointPerIndicator, 1); ?>
                                                        </div>
                                                        <div style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.25rem;">[<?php echo number_format($indicator['self_score'], 2); ?>]</div>
                                                    <?php endif; ?>
                                                </td>
                                                
                                <?php if ($isCombinedMode): ?>
                                <!-- Auditor Score(s) — anonymous icon-based display for company view -->
                                <td style="padding: 1rem 1.25rem; border-bottom: <?php echo $borderStyle; ?>; vertical-align: top; background: <?php echo $indicator['is_na'] ? '#fafaf9' : '#fffef7'; ?>;">
                                    <?php if ($indicator['is_na'] || $indicator['auditor_is_na']): ?>
                                        <div style="text-align: center;">
                                            <span style="display: inline-block; padding: 0.25rem 0.6rem; background: #f5f5f4; color: #a8a29e; border-radius: 6px; font-size: 0.7rem; font-weight: 700;">N/A</span>
                                        </div>
                                    <?php elseif (!empty($indicator['evaluator_scores'])): ?>
                                        <?php
                                            $evalScores = $indicator['evaluator_scores'];
                                            $avgAuditorScore = $indicator['auditor_score'];
                                            $selfVal = floatval($indicator['self_score']);
                                            $auditorColors = ['#f59e0b','#8b5cf6','#3b82f6','#10b981','#ef4444','#ec4899','#14b8a6','#f97316'];
                                        ?>
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem;">
                                            <!-- Average score display (top) -->
                                            <?php if ($avgAuditorScore !== null && $avgAuditorScore !== ''): ?>
                                            <?php
                                                $avgVal = floatval($avgAuditorScore);
                                                $diff = $avgVal - $selfVal;
                                                $diffColor = $diff > 0.001 ? '#059669' : ($diff < -0.001 ? '#dc2626' : '#6b7280');
                                                $diffSign = $diff > 0.001 ? '+' : '';
                                                $auditorPpiForAvg = $activeCount > 0 ? ($pillar['weight'] / $activeCount) : 0;
                                            ?>
                                            <div style="text-align: center;">
                                                <div style="font-size: 1.35rem; font-weight: 800; color: #92400e; line-height: 1;">
                                                    <?php echo number_format($avgVal * $auditorPpiForAvg, 1); ?>
                                                </div>
                                                <div style="font-size: 0.6rem; color: #a8a29e; margin-top: 0.2rem;">[<?php echo number_format($avgVal, 2); ?>]</div>
                                                <?php if (abs($diff) > 0.001): ?>
                                                <?php
                                                    $diffPts = ($avgVal * $auditorPpiForAvg) - ($selfVal * $pointPerIndicator);
                                                    $diffPtsColor = $diffPts > 0.01 ? '#059669' : ($diffPts < -0.01 ? '#dc2626' : '#6b7280');
                                                    $diffPtsSign = $diffPts > 0.01 ? '+' : '';
                                                ?>
                                                <div style="display: inline-block; font-size: 0.6rem; color: <?php echo $diffPtsColor; ?>; font-weight: 700; margin-top: 0.2rem; padding: 0.1rem 0.35rem; background: <?php echo $diffPts > 0 ? '#dcfce7' : '#fef2f2'; ?>; border-radius: 4px;">
                                                    <?php echo $diffPtsSign . number_format($diffPts, 1); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php else: ?>
                                            <span style="color: #d6d3d1; font-size: 0.7rem; font-style: italic;">ยังไม่ได้ประเมิน</span>
                                            <?php endif; ?>

                                            <!-- Auditor icons row (bottom, smaller) -->
                                            <div style="display: flex; gap: 0.25rem; flex-wrap: wrap; justify-content: center;">
                                                <?php foreach ($evalScores as $eIdx => $es): 
                                                    $auditorNum = $eIdx + 1;
                                                    $iconColor = $auditorColors[$eIdx % count($auditorColors)];
                                                    $hasScore = !$es['is_na'] && $es['score'] !== null;
                                                    $esCalcPts = $hasScore ? floatval($es['score']) * $pointPerIndicator : 0;
                                                    $scoreText = $es['is_na'] ? 'N/A' : ($hasScore ? number_format($esCalcPts, 1) . ' [' . number_format(floatval($es['score']), 2) . ']' : '—');
                                                    $tooltipParts = ["กรรมการ {$auditorNum}: {$scoreText}"];
                                                    if (!empty($es['comment'])) $tooltipParts[] = "💬 " . $es['comment'];
                                                    $tooltip = implode("\n", $tooltipParts);
                                                ?>
                                                <div class="auditor-icon-chip" title="<?php echo htmlspecialchars($tooltip); ?>"
                                                     style="position: relative; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: default;
                                                            background: <?php echo $hasScore ? $iconColor . '18' : '#f1f5f9'; ?>; 
                                                            border: 1.5px solid <?php echo $hasScore ? $iconColor : '#e2e8f0'; ?>; 
                                                            transition: all 0.2s;"
                                                     onmouseenter="this.querySelector('.auditor-tooltip').style.opacity='1'; this.querySelector('.auditor-tooltip').style.visibility='visible'; this.querySelector('.auditor-tooltip').style.transform='translateX(-50%) translateY(0)';"
                                                     onmouseleave="this.querySelector('.auditor-tooltip').style.opacity='0'; this.querySelector('.auditor-tooltip').style.visibility='hidden'; this.querySelector('.auditor-tooltip').style.transform='translateX(-50%) translateY(4px)';">
                                                    <?php if ($hasScore): ?>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?php echo $iconColor; ?>" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                    <?php elseif ($es['is_na']): ?>
                                                        <span style="font-size: 0.45rem; font-weight: 800; color: #a8a29e;">N/A</span>
                                                    <?php else: ?>
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                    <?php endif; ?>
                                                    <span style="position: absolute; bottom: -2px; right: -3px; width: 12px; height: 12px; border-radius: 50%; font-size: 0.4rem; font-weight: 800; display: flex; align-items: center; justify-content: center; color: white; background: <?php echo $hasScore ? $iconColor : '#cbd5e1'; ?>; border: 1px solid white;"><?php echo $auditorNum; ?></span>
                                                    
                                                    <!-- Hover tooltip -->
                                                    <div class="auditor-tooltip" style="position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%) translateY(4px); 
                                                            background: #1e293b; color: white; padding: 0.5rem 0.75rem; border-radius: 10px; font-size: 0.7rem; 
                                                            white-space: nowrap; z-index: 50; pointer-events: none; opacity: 0; visibility: hidden; 
                                                            transition: all 0.2s ease; box-shadow: 0 8px 25px rgba(0,0,0,0.2);">
                                                        <div style="font-weight: 700; margin-bottom: <?php echo !empty($es['comment']) ? '0.25rem' : '0'; ?>;">
                                                            กรรมการ <?php echo $auditorNum; ?>:
                                                            <?php if ($hasScore): ?>
                                                            <span style="color: #fbbf24; font-size: 0.95rem; margin-left: 0.25rem;">
                                                                <?php echo number_format($esCalcPts, 1); ?>
                                                            </span>
                                                            <span style="color: #64748b; font-size: 0.7rem; margin-left: 0.15rem;">[<?php echo number_format(floatval($es['score']), 2); ?>]</span>
                                                            <?php else: ?>
                                                            <span style="color: #94a3b8; font-size: 0.85rem; margin-left: 0.25rem;">
                                                                <?php echo $es['is_na'] ? 'N/A' : '—'; ?>
                                                            </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($es['comment'])): ?>
                                                        <div style="font-size: 0.6rem; color: #94a3b8; max-width: 200px; white-space: normal; line-height: 1.4; border-top: 1px solid #334155; padding-top: 0.25rem; margin-top: 0.1rem;">
                                                            💬 <?php echo htmlspecialchars(mb_strimwidth($es['comment'], 0, 120, '…', 'UTF-8')); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div style="position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); width: 10px; height: 10px; background: #1e293b; clip-path: polygon(50% 100%, 0 0, 100% 0);"></div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                    <?php elseif ($indicator['auditor_score'] !== null && $indicator['auditor_score'] !== ''): ?>
                                        <!-- Fallback: no evaluator_scores detail but has aggregate auditor_score -->
                                        <?php
                                            $auditorVal = floatval($indicator['auditor_score']);
                                            $selfVal2 = floatval($indicator['self_score']);
                                            $diff = $auditorVal - $selfVal2;
                                            $diffColor = $diff > 0.001 ? '#059669' : ($diff < -0.001 ? '#dc2626' : '#6b7280');
                                            $diffSign = $diff > 0.001 ? '+' : '';
                                            $auditorPpiFallback = $activeCount > 0 ? ($pillar['weight'] / $activeCount) : 0;
                                        ?>
                                        <div style="text-align: center;">
                                            <div style="font-size: 1.35rem; font-weight: 800; color: #92400e; line-height: 1;"><?php echo number_format($auditorVal * $auditorPpiFallback, 1); ?></div>
                                            <div style="font-size: 0.6rem; color: #a8a29e; margin-top: 0.2rem;">[<?php echo number_format($auditorVal, 2); ?>]</div>
                                            <?php if (abs($diff) > 0.001): ?>
                                            <?php
                                                $diffPtsFb = ($auditorVal * $auditorPpiFallback) - ($selfVal2 * $pointPerIndicator);
                                                $diffPtsFbColor = $diffPtsFb > 0.01 ? '#059669' : ($diffPtsFb < -0.01 ? '#dc2626' : '#6b7280');
                                                $diffPtsFbSign = $diffPtsFb > 0.01 ? '+' : '';
                                            ?>
                                            <div style="display: inline-block; font-size: 0.6rem; color: <?php echo $diffPtsFbColor; ?>; font-weight: 700; margin-top: 0.2rem; padding: 0.1rem 0.35rem; background: <?php echo $diffPtsFb > 0 ? '#dcfce7' : '#fef2f2'; ?>; border-radius: 4px;">
                                                <?php echo $diffPtsFbSign . number_format($diffPtsFb, 1); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="text-align: center;">
                                            <span style="color: #d6d3d1; font-size: 0.7rem; font-style: italic;">ยังไม่ได้ประเมิน</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
 
                                                <!-- Evidence & Files -->
                                                <td style="padding: 1.25rem 1.25rem; border-bottom: <?php echo $borderStyle; ?>; vertical-align: top;">
                                                    <?php if (!empty($indicator['self_evidence'])): ?>
                                                        <div style="margin-bottom: 0.5rem; font-size: 0.8rem; color: #475569; line-height: 1.5;">
                                                            <strong style="color: #1e293b; font-size: 0.7rem;">NOTE:</strong> <?php echo nl2br(htmlspecialchars(mb_strimwidth($indicator['self_evidence'], 0, 200, '…', 'UTF-8'))); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($indicator['attachment_count'] > 0): ?>
                                                        <button type="button" onclick="showAttachments(<?php echo $indicator['indicator_id']; ?>)" 
                                                                style="display: inline-flex; align-items: center; gap: 0.4rem; background: white; color: var(--pro-primary); padding: 0.35rem 0.75rem; border-radius: 8px; font-size: 0.7rem; font-weight: 700; border: 1px solid var(--pro-border); cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03);"
                                                                onmouseover="this.style.borderColor='var(--pro-primary)'; this.style.backgroundColor='#f5f3ff'" 
                                                                onmouseout="this.style.borderColor='var(--pro-border)'; this.style.backgroundColor='white'">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                                            </svg>
                                                            <?php echo $indicator['attachment_count']; ?> ไฟล์
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color: #cbd5e1; font-size: 0.75rem; font-style: italic;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Print-Only Signature & Footer -->
            <div class="print-only print-signature-area">
                <div class="print-signature-block">
                    <div class="print-signature-line"></div>
                    <div class="print-signature-label">ผู้ประเมิน (บริษัท)</div>
                    <div class="print-signature-sublabel">วันที่ ........./........./.........</div>
                </div>
                <div class="print-signature-block">
                    <div class="print-signature-line"></div>
                    <div class="print-signature-label">กรรมการตรวจประเมิน</div>
                    <div class="print-signature-sublabel">วันที่ ........./........./.........</div>
                </div>
            </div>

            <div class="print-only print-doc-footer">
                HICM V2025 Assessment System — <?php echo htmlspecialchars($assessment['company_name']); ?> — Printed <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
    </main>

    <style>
        /* Preview Modal Styles */
        .preview-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.85);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.2s ease-out;
        }
        
        .preview-content {
            background: white;
            width: 90%;
            max-width: 1000px;
            height: 90vh;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
        
        .preview-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            z-index: 10;
        }
        
        .preview-body {
            flex: 1;
            overflow: auto;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8fafc;
        }
        
        .preview-body img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }
        
        .preview-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
            border-radius: 4px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>

    <!-- Attachment Modal -->
    <div id="attachmentModal" class="modal-overlay">
        <div class="modal animate-fade-in-up">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="
                        width: 40px; 
                        height: 40px; 
                        background: #f0fdf4; 
                        border-radius: 10px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        color: #15803d;
                    ">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                            <circle cx="12" cy="14" r="2"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="modal-title">รายการไฟล์แนบ</h3>
                        <p class="text-sm text-gray-500" style="margin: 0; color: var(--gray-500);">เอกสารประกอบการประเมิน</p>
                    </div>
                </div>
                <button onclick="closeModal()" style="
                    background: transparent; 
                    border: none; 
                    color: var(--gray-400); 
                    cursor: pointer; 
                    padding: 0.5rem; 
                    border-radius: 8px;
                    transition: all 0.2s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                "
                onmouseover="this.style.backgroundColor='#f1f5f9'; this.style.color='var(--gray-600)'"
                onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--gray-400)'">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div id="fileList" style="display: flex; flex-direction: column; gap: 0;">
                    <!-- Javascript will populate this -->
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="btn btn-outline" style="padding: 0.5rem 1rem;">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="preview-modal">
        <div class="preview-content">
            <div class="preview-header">
                <div style="min-width: 0; margin-right: 1rem;">
                    <h3 id="previewTitle" style="font-size: 1.125rem; font-weight: 600; color: var(--gray-900); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin: 0;">ตัวอย่างไฟล์</h3>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                    <button type="button" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;" onclick="downloadCurrentFile()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>ดาวน์โหลด</span>
                    </button>
                    <button type="button" onclick="closePreview()" style="background: transparent; border: none; padding: 0.5rem; border-radius: 8px; cursor: pointer; color: var(--gray-500); transition: all 0.2s;" onmouseover="this.style.backgroundColor='#f1f5f9'; this.style.color='var(--gray-900)'" onmouseout="this.style.backgroundColor='transparent'; this.style.color='var(--gray-500)'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="previewBody" class="preview-body">
                <!-- Preview content injected here -->
            </div>
        </div>
    </div>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global Chart Defaults
            if (typeof Chart !== 'undefined') {
                Chart.defaults.font.family = "'Prompt', sans-serif";
                Chart.defaults.color = '#64748b';
                Chart.defaults.plugins.tooltip.padding = 12;
                Chart.defaults.plugins.tooltip.borderRadius = 8;
                Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.9)';
            }

            const proColors = {
                primary: '#6366f1',
                success: '#10b981',
                info: '#0ea5e9',
                warning: '#f59e0b',
                danger: '#ef4444',
                purple: '#8b5cf6',
                grey: '#94a3b8'
            };

            // History Chart — Timeline sorted, current period highlighted
            const historyCanvas = document.getElementById('historyChart');
            <?php if (!empty($history) && count($history) > 0): ?>
            if (historyCanvas && typeof Chart !== 'undefined') {
                <?php
                // Sort history chronologically (should already be, but ensure)
                $sortedHistory = $history;
                usort($sortedHistory, function($a, $b) {
                    if ($a['year'] !== $b['year']) return $a['year'] - $b['year'];
                    return ($a['period_id'] ?? 0) - ($b['period_id'] ?? 0);
                });
                
                // Find the current assessment index in sorted array
                $currentIndex = -1;
                foreach ($sortedHistory as $idx => $h) {
                    if ($h['id'] == $assessmentId) {
                        $currentIndex = $idx;
                        break;
                    }
                }
                
                // Build per-point colors & sizes: highlight the current period
                $pointBgColors = [];
                $pointBorderColors = [];
                $pointRadii = [];
                $pointBorderWidths = [];
                foreach ($sortedHistory as $idx => $h) {
                    if ($idx === $currentIndex) {
                        $pointBgColors[] = '#f59e0b';
                        $pointBorderColors[] = '#fff';
                        $pointRadii[] = 10;
                        $pointBorderWidths[] = 4;
                    } else {
                        $pointBgColors[] = '#6366f1';
                        $pointBorderColors[] = '#fff';
                        $pointRadii[] = 5;
                        $pointBorderWidths[] = 3;
                    }
                }
                
                // Build labels with year
                $chartLabels = array_map(function($h) { 
                    return $h['year']; 
                }, $sortedHistory);
                
                $chartScores = array_map(function($h) { 
                    return $h['self_total_score'] ?? 0; 
                }, $sortedHistory);
                
                // Period IDs for click navigation
                $chartPeriodIds = array_map(function($h) { return $h['period_id']; }, $sortedHistory);
                $chartPeriodNames = array_map(function($h) { return $h['period_name']; }, $sortedHistory);
                ?>
                
                // HICM Level zone background plugin
                const levelZonesPlugin = {
                    id: 'levelZones',
                    beforeDraw(chart) {
                        const { ctx, chartArea: { left, right, top, bottom }, scales: { y } } = chart;
                        const zones = [
                            { min: 0, max: 200, color: 'rgba(239,68,68,0.04)', label: 'Lv.1 เริ่มต้น', labelColor: '#fca5a5' },
                            { min: 200, max: 400, color: 'rgba(245,158,11,0.04)', label: 'Lv.2 พัฒนา', labelColor: '#fcd34d' },
                            { min: 400, max: 600, color: 'rgba(59,130,246,0.04)', label: 'Lv.3 ดี', labelColor: '#93c5fd' },
                            { min: 600, max: 800, color: 'rgba(139,92,246,0.04)', label: 'Lv.4 เป็นเลิศ', labelColor: '#c4b5fd' },
                            { min: 800, max: 1000, color: 'rgba(16,185,129,0.04)', label: 'Lv.5 ระดับโลก', labelColor: '#6ee7b7' }
                        ];
                        zones.forEach(zone => {
                            const yTop = y.getPixelForValue(zone.max);
                            const yBot = y.getPixelForValue(zone.min);
                            ctx.save();
                            ctx.fillStyle = zone.color;
                            ctx.fillRect(left, yTop, right - left, yBot - yTop);
                            // Level label on right edge
                            ctx.fillStyle = zone.labelColor;
                            ctx.font = "600 9px 'Prompt', sans-serif";
                            ctx.textAlign = 'right';
                            ctx.textBaseline = 'top';
                            ctx.fillText(zone.label, right - 6, yTop + 4);
                            ctx.restore();
                        });
                    }
                };
                
                // Gradient fill
                const historyCtx = historyCanvas.getContext('2d');
                const gradient = historyCtx.createLinearGradient(0, 0, 0, 350);
                gradient.addColorStop(0, 'rgba(99, 102, 241, 0.15)');
                gradient.addColorStop(0.6, 'rgba(99, 102, 241, 0.03)');
                gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                
                const historyPeriodIds = <?php echo json_encode(array_values($chartPeriodIds)); ?>;
                const historyPeriodNames = <?php echo json_encode(array_values($chartPeriodNames)); ?>;
                const currentIdx = <?php echo $currentIndex; ?>;
                
                const historyChart = new Chart(historyCanvas, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode(array_values($chartLabels)); ?>,
                        datasets: [{
                            label: 'Self Score',
                            data: <?php echo json_encode(array_values($chartScores)); ?>,
                            borderColor: proColors.primary,
                            backgroundColor: gradient,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.35,
                            pointRadius: <?php echo json_encode(array_values($pointRadii)); ?>,
                            pointHoverRadius: 11,
                            pointBackgroundColor: <?php echo json_encode(array_values($pointBgColors)); ?>,
                            pointBorderColor: <?php echo json_encode(array_values($pointBorderColors)); ?>,
                            pointBorderWidth: <?php echo json_encode(array_values($pointBorderWidths)); ?>,
                            pointHitRadius: 20
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 10, right: 12, bottom: 4 } },
                        onClick: (evt, elements) => {
                            if (elements.length > 0) {
                                const idx = elements[0].index;
                                const pid = historyPeriodIds[idx];
                                if (pid) window.location.href = '?period_id=' + pid + '<?php echo $isCombinedRequest ? "&combined=1" : ""; ?>';
                            }
                        },
                        onHover: (evt, elements) => {
                            historyCanvas.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                                titleFont: { size: 13, weight: '700', family: "'Prompt', sans-serif" },
                                bodyFont: { size: 12, family: "'Prompt', sans-serif" },
                                footerFont: { size: 10, style: 'italic', family: "'Prompt', sans-serif" },
                                padding: 14,
                                borderRadius: 12,
                                borderColor: 'rgba(255,255,255,0.1)',
                                borderWidth: 1,
                                displayColors: false,
                                callbacks: {
                                    title: (items) => {
                                        const idx = items[0].dataIndex;
                                        return historyPeriodNames[idx] + ' (' + items[0].label + ')';
                                    },
                                    label: (item) => {
                                        const score = item.raw;
                                        const level = score >= 800 ? 5 : (score >= 600 ? 4 : (score >= 400 ? 3 : (score >= 200 ? 2 : 1)));
                                        const levelNames = { 1: 'เริ่มต้น', 2: 'กำลังพัฒนา', 3: 'พัฒนาดี', 4: 'เป็นเลิศ', 5: 'ระดับโลก' };
                                        return `คะแนน: ${Number(score).toLocaleString()} / 1,000  •  Level ${level} ${levelNames[level]}`;
                                    },
                                    footer: (items) => {
                                        const idx = items[0].dataIndex;
                                        if (idx === currentIdx) return '◆ กำลังดูอยู่';
                                        return 'คลิกเพื่อดูผลรอบนี้';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                min: 0,
                                max: 1000,
                                grid: { color: 'rgba(241,245,249,0.8)', lineWidth: 1 },
                                border: { display: false },
                                ticks: {
                                    padding: 10,
                                    stepSize: 200,
                                    font: { size: 10, weight: '600' },
                                    color: '#94a3b8',
                                    callback: v => v.toLocaleString()
                                }
                            },
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: {
                                    padding: 10,
                                    font: { size: 11, weight: '600', family: "'Prompt', sans-serif" },
                                    color: function(context) {
                                        return context.index === currentIdx ? '#f59e0b' : '#64748b';
                                    }
                                }
                            }
                        }
                    },
                    plugins: [levelZonesPlugin]
                });
            }
            <?php else: ?>
            if (historyCanvas) {
                const container = historyCanvas.closest('.pro-card');
                if (container) container.style.display = 'none';
            }
            <?php endif; ?>

            // Radar Chart (Spider Chart) — Fixed with correct pillar percentage data
            const radarCanvas = document.getElementById('resultRadarChart');
            if (radarCanvas && typeof Chart !== 'undefined') {
                const pillarData = <?php echo json_encode(array_values(array_map(function($p) { return $p['percentage']; }, $pillarChartData))); ?>;
                const pillarLabels = <?php echo json_encode(array_values(array_map(function($p) { return $p['label']; }, $pillarChartData))); ?>;
                const pillarColors = <?php echo json_encode(array_values(array_map(function($p) { return $p['color']; }, $pillarChartData))); ?>;
                
                const radarDatasets = [{
                    label: 'Self Assessment',
                    data: pillarData,
                    borderColor: proColors.primary,
                    backgroundColor: 'rgba(99, 102, 241, 0.12)',
                    borderWidth: 3,
                    pointRadius: 7,
                    pointHoverRadius: 10,
                    pointBackgroundColor: pillarColors,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointHitRadius: 12,
                    fill: true
                }];
                
                <?php if ($isCombinedMode): ?>
                // Add auditor dataset
                const auditorPillarData = <?php echo json_encode(array_values(array_map(function($p) { return $p['auditor_percentage'] ?? 0; }, $pillarChartData))); ?>;
                radarDatasets.push({
                    label: 'Auditor Assessment',
                    data: auditorPillarData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.08)',
                    borderWidth: 3,
                    borderDash: [6, 4],
                    pointRadius: 7,
                    pointHoverRadius: 10,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 3,
                    pointHitRadius: 12,
                    fill: true
                });
                <?php endif; ?>
                
                new Chart(radarCanvas, {
                    type: 'radar',
                    data: {
                        labels: pillarLabels,
                        datasets: radarDatasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 16, bottom: 16, left: 10, right: 10 } },
                        scales: {
                            r: {
                                beginAtZero: true,
                                min: 0,
                                max: 100,
                                ticks: { 
                                    stepSize: 25,
                                    backdropColor: 'transparent', 
                                    font: { size: 9, weight: '600' },
                                    color: '#94a3b8',
                                    callback: v => v + '%',
                                    z: 1
                                },
                                grid: { 
                                    color: 'rgba(148, 163, 184, 0.2)',
                                    lineWidth: 1.5,
                                    circular: true
                                },
                                angleLines: { 
                                    color: 'rgba(148, 163, 184, 0.15)',
                                    lineWidth: 1.5
                                },
                                pointLabels: {
                                    font: { size: 11, weight: '700', family: "'Prompt', sans-serif" },
                                    color: '#334155',
                                    padding: 14
                                }
                            }
                        },
                        plugins: {
                            legend: { 
                                display: <?php echo $isCombinedMode ? 'true' : 'false'; ?>,
                                position: 'bottom',
                                labels: {
                                    font: { size: 11, weight: '600', family: "'Prompt', sans-serif" },
                                    padding: 16,
                                    usePointStyle: true,
                                    pointStyle: 'circle'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.92)',
                                titleFont: { size: 13, weight: '700', family: "'Prompt', sans-serif" },
                                bodyFont: { size: 12, family: "'Prompt', sans-serif" },
                                padding: 14,
                                borderRadius: 12,
                                borderColor: 'rgba(255,255,255,0.1)',
                                borderWidth: 1,
                                displayColors: true,
                                callbacks: {
                                    title: (items) => items[0].label,
                                    label: (item) => `${item.dataset.label}: ${item.raw.toFixed(1)}%`
                                }
                            }
                        },
                        elements: {
                            line: {
                                tension: 0.15
                            }
                        }
                    }
                });
            }

            // Reveal animations delay
            document.querySelectorAll('.reveal').forEach((el, index) => {
                el.style.animationDelay = (index * 0.1) + 's';
            });
        });

        // Global data & functions
        const assessmentAttachments = <?php echo json_encode($attachments); ?>;
        const baseUrl = "<?php echo getBaseUrl(); ?>";

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function getFileIcon(type) {
            const size = 24;
            const stroke = 1.5;
            if (type === 'application/pdf') {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="${stroke}"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>`;
            }
            if (type.startsWith('image/')) {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="${stroke}"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
            }
            if (type.includes('msword') || type.includes('wordprocessingml')) {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="${stroke}"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
            }
            if (type.includes('excel') || type.includes('spreadsheetml')) {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="${stroke}"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
            }
            return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="${stroke}"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>`;
        }

        function showAttachments(indicatorId) {
            const modal = document.getElementById('attachmentModal');
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            const files = assessmentAttachments[indicatorId] || [];
            
            if (files.length === 0) {
                fileList.innerHTML = `<div style="text-align: center; color: var(--gray-500); padding: 3rem;">ไม่พบไฟล์แนบ</div>`;
            } else {
                files.forEach((file) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.style.cssText = `display: flex; align-items: center; justify-content: space-between; padding: 1.25rem; border-bottom: 1px solid #f1f5f9;`;
                    const fileUrl = `${baseUrl}/api/get-attachment.php?id=${file.id}`;
                    fileItem.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #f8fafc; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                ${getFileIcon(file.type)}
                            </div>
                            <div>
                                <div style="font-weight: 500; color: #1e293b;">${file.name}</div>
                                <div style="font-size: 0.75rem; color: #64748b;">${formatBytes(file.size)} • ${file.date}</div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" onclick="previewFile('${file.id}', '${file.name}', '${fileUrl}', '${file.type}')" class="btn btn-sm btn-outline" style="padding: 0.25rem 0.5rem;">Preview</button>
                            <a href="${fileUrl}" target="_blank" class="btn btn-sm btn-outline" style="padding: 0.25rem 0.5rem;">Download</a>
                        </div>
                    `;
                    fileList.appendChild(fileItem);
                });
            }
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('attachmentModal').classList.remove('active');
        }

        var currentDownloadUrl = '';
        function previewFile(fileId, fileName, fileUrl, fileType) {
            const modal = document.getElementById('previewModal');
            document.getElementById('previewTitle').textContent = fileName;
            currentDownloadUrl = fileUrl;
            const body = document.getElementById('previewBody');
            body.innerHTML = '';
            
            if (fileType.startsWith('image/')) {
                body.innerHTML = `<img src="${fileUrl}&inline=1" style="max-width: 100%; height: auto;">`;
            } else if (fileType === 'application/pdf') {
                body.innerHTML = `<iframe src="${fileUrl}&inline=1" style="width: 100%; height: 600px; border: none;"></iframe>`;
            } else {
                body.innerHTML = `<div style="text-align: center; padding: 2rem;">ขออภัย ไม่สามารถแสดงตัวอย่างไฟล์นี้ได้</div>`;
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

        function triggerExport() {
            const data = [
                ["Indicator", "Self Score", "Evidence"],
                <?php foreach ($assessment['pillars'] as $pillar): ?>
                    <?php foreach ($pillar['indicators'] as $indicator): ?>
                        [
                            "<?php echo addslashes($indicator['indicator_name']); ?>",
                            "<?php echo $indicator['is_na'] ? 'N/A' : $indicator['self_score']; ?>",
                            "<?php echo addslashes(str_replace("\n", " ", $indicator['self_evidence'] ?? '')); ?>"
                        ],
                    <?php endforeach; ?>
                <?php endforeach; ?>
            ];
            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Self Assessment");
            XLSX.writeFile(wb, "self_assessment.xlsx");
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('attachmentModal')) closeModal();
            if (event.target == document.getElementById('previewModal')) closePreview();
        }

        // PDF Download
        function downloadResultPDF() {
            const companyName = <?php echo json_encode($assessment['company_name']); ?>;
            const year = <?php echo json_encode($assessment['year']); ?>;
            const filename = 'HICM_Result_' + companyName.replace(/\s+/g, '_') + '_' + year + '.pdf';
            HICM_PDF.download('.main-content', filename);
        }
    </script>
</body>
</html>
