<?php
/**
 * HICM V2025 Assessment System - Assessment View Page (Admin/Auditor)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);

if (!$isAdmin && !$isAuditor) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$assessmentId = intval($_GET['id'] ?? 0);
if (!$assessmentId) {
    setFlashMessage('ไม่พบข้อมูลแบบประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/assessments.php');
}

$assessment = getAssessmentWithScores($assessmentId);
if (!$assessment) {
    setFlashMessage('ไม่พบข้อมูลแบบประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/assessments.php');
}

// Fetch navigation and history
$adjacentIds = getAdjacentAssessmentIds($assessmentId, $assessment['company_id']);
$history = getCompanyAssessmentHistory($assessment['company_id']);

// Preview mode — hide navbar/sidebar when rendered inside preview.php iframe
$isPreview = !empty($_GET['_preview']);

// Get HICM level info
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
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ดูแบบประเมิน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/pdf-export.js"></script>
    <style>
        /* Professional Assessment View Styles */
        .assessment-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .assessment-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-content {
            position: relative;
            z-index: 1;
        }
        .hero-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .hero-nav .btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(10px);
        }
        .hero-nav .btn:hover {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }
        /* Standardized Export Button Styles */
        .hero-nav .btn-print {
            background: rgba(16, 185, 129, 0.25) !important;
            border-color: rgba(16, 185, 129, 0.5) !important;
        }
        .hero-nav .btn-print:hover {
            background: rgba(16, 185, 129, 0.4) !important;
        }
        .hero-nav .btn-pdf {
            background: rgba(239, 68, 68, 0.25) !important;
            border-color: rgba(239, 68, 68, 0.5) !important;
        }
        .hero-nav .btn-pdf:hover {
            background: rgba(239, 68, 68, 0.4) !important;
        }
        .hero-nav .btn-preview {
            background: rgba(59, 130, 246, 0.25) !important;
            border-color: rgba(59, 130, 246, 0.5) !important;
        }
        .hero-nav .btn-preview:hover {
            background: rgba(59, 130, 246, 0.4) !important;
        }
        .hero-nav .btn-excel {
            background: rgba(245, 158, 11, 0.25) !important;
            border-color: rgba(245, 158, 11, 0.5) !important;
        }
        .hero-nav .btn-excel:hover {
            background: rgba(245, 158, 11, 0.4) !important;
        }
        .hero-company {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .hero-avatar {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
        }
        .hero-info h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
        }
        .hero-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .hero-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Score Showcase */
        .score-showcase {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .score-main-card {
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
        }
        .score-main-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3B82F6, #8B5CF6, #10B981);
        }
        .score-ring {
            width: 160px;
            height: 160px;
            margin: 0 auto 1.5rem;
            position: relative;
        }
        .score-ring svg {
            transform: rotate(-90deg);
        }
        .score-ring-bg {
            fill: none;
            stroke: var(--gray-100);
            stroke-width: 12;
        }
        .score-ring-fill {
            fill: none;
            stroke: url(#scoreGradient);
            stroke-width: 12;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease-out;
        }
        .score-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .score-value .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
        }
        .score-value .unit {
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        .level-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .stars-row {
            display: flex;
            justify-content: center;
            gap: 0.25rem;
            margin-top: 1rem;
        }
        
        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
        }
        .info-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .info-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .info-card-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--gray-800);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.625rem 0;
            border-bottom: 1px dashed var(--gray-100);
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        .info-value {
            font-weight: 500;
            color: var(--gray-800);
            font-size: 0.875rem;
        }
        
        /* Pillar Progress Cards */
        .pillar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 1200px) {
            .pillar-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .pillar-grid { grid-template-columns: 1fr; }
        }
        .pillar-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .pillar-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        }
        .pillar-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
        }
        .pillar-card.h1::before { background: #10B981; }
        .pillar-card.i2::before { background: #3B82F6; }
        .pillar-card.c3::before { background: #F59E0B; }
        .pillar-card.m4::before { background: #8B5CF6; }
        
        .pillar-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .pillar-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .pillar-name {
            font-weight: 600;
            color: var(--gray-800);
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .pillar-score {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .pillar-progress {
            height: 8px;
            background: var(--gray-100);
            border-radius: 999px;
            overflow: hidden;
            margin: 0.75rem 0;
        }
        .pillar-progress-fill {
            height: 100%;
            border-radius: 999px;
            transition: width 0.8s ease-out;
        }
        .pillar-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 900px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        .chart-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        .chart-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .chart-card-body {
            padding: 1.5rem;
        }
        
        /* Detail Accordion */
        .detail-section {
            margin-bottom: 2rem;
        }
        .detail-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .detail-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .detail-card-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background 0.2s;
        }
        .detail-card-header:hover {
            background: var(--gray-50);
        }
        .detail-card-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .detail-card-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .detail-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Person Card */
        .person-card {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--gray-50);
            border-radius: 10px;
        }
        .person-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .person-info {
            min-width: 0;
        }
        .person-name {
            font-weight: 500;
            font-size: 0.875rem;
            color: var(--gray-800);
        }
        .person-sub {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-in {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }

        /* ========== Print & PDF Styles ========== */

        /* PDF page-break helpers */
        .pdf-page-break-before { page-break-before: always; }
        .pdf-page-break-after  { page-break-after: always; }
        .pdf-avoid-break       { page-break-inside: avoid; }

        /* Print-only elements */
        .print-only { display: none; }

        /* Print-only document header */
        .print-doc-header h1 {
            font-size: 16pt;
            font-weight: 700;
            color: #1e3a5f;
            margin: 0 0 4px 0;
        }
        .print-doc-header p {
            font-size: 9pt;
            color: #64748b;
            margin: 2px 0;
        }

        /* Signature area */
        .print-signature-area {
            display: flex;
            justify-content: space-around;
            gap: 40px;
            margin: 30px 20px 10px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        .print-signature-block {
            text-align: center;
            flex: 1;
            max-width: 250px;
        }
        .print-signature-line {
            border-bottom: 1px solid #334155;
            margin-bottom: 6px;
            height: 50px;
        }
        .print-signature-label {
            font-size: 9pt;
            font-weight: 600;
            color: #334155;
        }
        .print-signature-sublabel {
            font-size: 8pt;
            color: #64748b;
            margin-top: 4px;
        }

        /* Document footer */
        .print-doc-footer {
            text-align: center;
            font-size: 7pt;
            color: #64748b;
            padding: 8px 0;
            margin-top: 10px;
            border-top: 1px solid #e2e8f0;
        }

        @media print {
            @page { size: A4; margin: 12mm 10mm 15mm 10mm; }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* Show/Hide */
            .print-only { display: block !important; }
            .hero-nav, .modal, .modal-overlay,
            button[onclick], a[onclick], select,
            #evidenceModal, #filePreviewModal,
            .navbar, .sidebar, .sidebar-overlay,
            .toast-container {
                display: none !important;
            }

            /* Full-width */
            .main-wrapper, .main-content {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
            }

            /* Hero */
            .assessment-hero {
                background: #1e3a5f !important;
                color: white !important;
                padding: 14px 18px !important;
                border-radius: 8px !important;
                margin: 10px 0 12px 0 !important;
                page-break-after: avoid;
            }
            .assessment-hero::before { display: none !important; }
            .assessment-hero, .assessment-hero *,
            .hero-content *, .hero-info *, .hero-company *,
            .hero-meta * {
                color: white !important;
            }
            .hero-avatar { width: 48px !important; height: 48px !important; font-size: 1rem !important; }
            .hero-info h1 { font-size: 13pt !important; }
            .hero-meta span { font-size: 8pt !important; }

            /* Score showcase */
            .score-showcase {
                display: grid !important;
                grid-template-columns: 1fr 1fr 1fr !important;
                gap: 10px !important;
                page-break-inside: avoid;
                margin: 0 0 12px 0 !important;
            }
            .score-main-card {
                box-shadow: none !important;
                border: 1.5px solid #e2e8f0 !important;
                padding: 12px !important;
            }
            .score-ring { width: 100px !important; height: 100px !important; margin-bottom: 8px !important; }
            .score-ring svg { width: 100px !important; height: 100px !important; }
            .score-value .number { font-size: 1.6rem !important; }
            .info-card {
                box-shadow: none !important;
                border: 1.5px solid #e2e8f0 !important;
                font-size: 8pt !important;
            }

            /* Pillar grid */
            .pillar-grid {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin-bottom: 14px !important;
                page-break-inside: avoid;
            }
            .pillar-card {
                box-shadow: none !important;
                border: 1.5px solid #d1d5db !important;
                page-break-inside: avoid;
            }

            /* Charts */
            .charts-grid { page-break-inside: avoid; margin-bottom: 14px !important; }
            .chart-card { box-shadow: none !important; border: 1.5px solid #e2e8f0 !important; }

            /* Detail cards */
            .card.animate-fade-in-up { 
                box-shadow: none !important; 
                border: 1.5px solid #e2e8f0 !important; 
                margin-bottom: 12px !important;
            }

            /* Tables */
            .table { font-size: 8pt !important; }
            .table thead th {
                background: #1e3a5f !important;
                color: white !important;
                font-size: 7.5pt !important;
                padding: 5px 8px !important;
            }
            .table tbody td {
                padding: 4px 8px !important;
                font-size: 7.5pt !important;
            }
            .table tbody tr { page-break-inside: avoid; }

            /* Evidence buttons → hide */
            .table tbody td button.btn { display: none !important; }

            /* Animations */
            .animate-in, .animate-fade-in-up {
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body class="<?php echo $isPreview ? '' : 'has-sidebar'; ?>">
    <?php if (!$isPreview): ?>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    <div class="sidebar-overlay"></div>
    <?php endif; ?>

    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>

            <!-- Print-Only Document Header -->
            <div class="print-only print-doc-header" style="text-align:center; padding:12px 16px 10px; margin-bottom:0; border-bottom:3px solid #1e3a5f; position:relative;">
                <div style="display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:6px;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#1e3a5f" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                    <h1 style="font-size:16pt; font-weight:700; color:#1e3a5f; margin:0; letter-spacing:0.5px;">รายงานผลการประเมิน HICM V2025</h1>
                </div>
                <p style="font-size:9pt; color:#475569; margin:2px 0;">
                    <strong><?php echo htmlspecialchars($assessment['company_name']); ?></strong> 
                    &nbsp;•&nbsp; <?php echo htmlspecialchars($assessment['period_name']); ?> (<?php echo $assessment['year']; ?>) 
                    &nbsp;•&nbsp; สถานะ: <?php echo $sText; ?>
                </p>
                <p style="font-size:7.5pt; color:#94a3b8; margin:2px 0;">
                    เลขที่การประเมิน: #<?php echo $assessmentId; ?> &nbsp;|&nbsp; วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?> น.
                </p>
                <div style="position:absolute; bottom:-6px; left:15%; right:15%; height:1px; background:#e2e8f0;"></div>
            </div>

            <!-- Hero Section -->
            <div class="assessment-hero animate-in">
                <div class="hero-content">
                    <div class="hero-nav">
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <a href="<?php echo getBaseUrl(); ?>/pages/assessments.php" class="btn btn-sm">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                                </svg>
                                ย้อนกลับ
                            </a>
                            <?php if ($adjacentIds['prev']): ?>
                            <a href="<?php echo getBaseUrl(); ?>/pages/assessment-view.php?id=<?php echo $adjacentIds['prev']; ?>" class="btn btn-sm">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                            </a>
                            <?php endif; ?>
                            <?php if ($adjacentIds['next']): ?>
                            <a href="<?php echo getBaseUrl(); ?>/pages/assessment-view.php?id=<?php echo $adjacentIds['next']; ?>" class="btn btn-sm">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button onclick="HICM_PDF.print()" class="btn btn-sm btn-print" title="พิมพ์ (Ctrl+P)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                                </svg>
                                พิมพ์
                            </button>
                            <button onclick="downloadAssessmentPDF()" class="btn btn-sm btn-pdf" title="ดาวน์โหลด PDF">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="12" y1="18" x2="12" y2="12"/>
                                    <polyline points="9 15 12 18 15 15"/>
                                </svg>
                                PDF
                            </button>
                            <a href="preview.php?source=assessment-view&id=<?php echo $assessmentId; ?>" target="_blank" class="btn btn-sm btn-preview" title="ดูตัวอย่างก่อน Export (เปิดแท็บใหม่)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                ตัวอย่าง
                            </a>
                            <a href="export-report.php?id=<?php echo $assessmentId; ?>&format=excel" class="btn btn-sm btn-excel">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/>
                                </svg>
                                Excel
                            </a>
                        </div>
                    </div>
                    
                    <div class="hero-company">
                        <div class="hero-avatar">
                            <?php
                            $avatarFile = $assessment['company_owner_avatar'] ?? $assessment['logo'] ?? 'default';
                            if ($avatarFile && $avatarFile !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatarFile)) {
                                $avatarPath = '../assets/uploads/avatars/' . $avatarFile;
                                echo '<img src="' . $avatarPath . '" alt="Avatar" style="width:100%;height:100%;border-radius:16px;object-fit:cover;">';
                            } else {
                                $companyName = $assessment['company_name'] ?? '';
                                $initials = '';
                                $parts = explode(' ', trim($companyName));
                                foreach (array_slice($parts, 0, 2) as $part) {
                                    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                                }
                                echo $initials ?: '?';
                            }
                            ?>
                        </div>
                        <div class="hero-info">
                            <h1><?php echo htmlspecialchars($assessment['company_name']); ?></h1>
                            <div class="hero-meta">
                                <span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    <?php echo htmlspecialchars($assessment['period_name']); ?> (<?php echo $assessment['year']; ?>)
                                </span>
                                <?php if (!empty($assessment['industry_type'])): ?>
                                <span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                                    <?php echo htmlspecialchars($assessment['industry_type']); ?>
                                </span>
                                <?php endif; ?>
                                <span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php echo number_format($assessment['employee_count']); ?> คน
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Score Showcase -->
            <div class="score-showcase">
                <!-- Main Score Card -->
                <div class="score-main-card animate-in delay-1">
                    <div class="score-ring">
                        <svg width="160" height="160" viewBox="0 0 160 160">
                            <defs>
                                <linearGradient id="scoreGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#3B82F6"/>
                                    <stop offset="50%" style="stop-color:#8B5CF6"/>
                                    <stop offset="100%" style="stop-color:#10B981"/>
                                </linearGradient>
                            </defs>
                            <circle class="score-ring-bg" cx="80" cy="80" r="70"/>
                            <circle class="score-ring-fill" cx="80" cy="80" r="70" 
                                stroke-dasharray="<?php echo 2 * 3.14159 * 70; ?>" 
                                stroke-dashoffset="<?php echo 2 * 3.14159 * 70 * (1 - min($assessment['final_score'], 1000) / 1000); ?>"/>
                        </svg>
                        <div class="score-value">
                            <div class="number"><?php echo number_format($assessment['final_score'], 0); ?></div>
                            <div class="unit">/ 1,000</div>
                        </div>
                    </div>
                    
                    <?php $levelInfo = getLevelInfo($assessment['hicm_level']); ?>
                    <div class="level-badge" style="background: <?php echo $levelInfo['bg']; ?>; color: <?php echo $levelInfo['color']; ?>;">
                        <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $levelInfo['color']; ?>;"></span>
                        Level <?php echo $assessment['hicm_level']; ?>: <?php echo $levelInfo['name']; ?>
                    </div>
                    
                    <div class="stars-row">
                        <?php for($i=1; $i<=5; $i++): ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="<?php echo $i <= $assessment['hicm_level'] ? '#FBBF24' : '#E5E7EB'; ?>">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Quick Stats Card -->
                <div class="info-card animate-in delay-2">
                    <div class="info-card-header">
                        <div class="info-card-icon" style="background: #DBEAFE; color: #3B82F6;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        </div>
                        <span class="info-card-title">ข้อมูลการประเมิน</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">สถานะ</span>
                        <span class="badge <?php echo $assessment['status'] === 'completed' ? 'badge-success' : ($assessment['status'] === 'evaluated' ? 'badge-info' : 'badge-warning'); ?>">
                            <?php echo $sText; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">วันที่ส่ง</span>
                        <span class="info-value"><?php echo $assessment['submitted_at'] ? date('d/m/Y', strtotime($assessment['submitted_at'])) : '-'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">วันที่ประเมิน</span>
                        <span class="info-value"><?php echo $assessment['evaluated_at'] ? date('d/m/Y', strtotime($assessment['evaluated_at'])) : '-'; ?></span>
                    </div>
                    
                    <?php if (!empty($assessment['evaluators'])): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-100);">
                        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem; font-weight: 600;">กรรมการผู้ประเมิน</div>
                        <?php foreach ($assessment['evaluators'] as $evaluator): ?>
                        <div class="person-card" style="margin-bottom: 0.5rem;">
                            <div class="person-avatar" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                                <?php 
                                $name = $evaluator['name'] ?? '';
                                $eInitials = '';
                                $eParts = explode(' ', trim($name));
                                foreach (array_slice($eParts, 0, 2) as $part) {
                                    $eInitials .= strtoupper(substr($part, 0, 1));
                                }
                                echo $eInitials ?: '?';
                                ?>
                            </div>
                            <div class="person-info">
                                <div class="person-name"><?php echo htmlspecialchars($evaluator['name'] ?? '-'); ?></div>
                                <div class="person-sub"><?php echo htmlspecialchars($evaluator['email'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Company Info Card -->
                <div class="info-card animate-in delay-3">
                    <div class="info-card-header">
                        <div class="info-card-icon" style="background: #D1FAE5; color: #10B981;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <span class="info-card-title">ข้อมูลบริษัท</span>
                    </div>
                    
                    <?php if (!empty($assessment['website'])): ?>
                    <div class="info-row">
                        <span class="info-label">🌐 เว็บไซต์</span>
                        <a href="<?php echo htmlspecialchars($assessment['website']); ?>" target="_blank" class="info-value" style="color: var(--primary); text-decoration: none;">
                            <?php echo htmlspecialchars(preg_replace('#^https?://#', '', $assessment['website'])); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($assessment['phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">📞 โทรศัพท์</span>
                        <span class="info-value"><?php echo htmlspecialchars($assessment['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($assessment['province'])): ?>
                    <div class="info-row">
                        <span class="info-label">📍 จังหวัด</span>
                        <span class="info-value"><?php echo htmlspecialchars($assessment['province']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($assessment['contact_name'])): ?>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-100);">
                        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem; font-weight: 600;">👤 ผู้ติดต่อ</div>
                        <div class="person-card">
                            <div class="person-avatar" style="background: linear-gradient(135deg, #10B981, #059669);">
                                <?php 
                                $contactName = $assessment['contact_name'] ?? '';
                                $cInitials = '';
                                $cParts = explode(' ', trim($contactName));
                                foreach (array_slice($cParts, 0, 2) as $part) {
                                    $cInitials .= mb_strtoupper(mb_substr($part, 0, 1));
                                }
                                echo $cInitials ?: '?';
                                ?>
                            </div>
                            <div class="person-info">
                                <div class="person-name"><?php echo htmlspecialchars($assessment['contact_name']); ?></div>
                                <?php if (!empty($assessment['contact_phone'])): ?>
                                <div class="person-sub"><?php echo htmlspecialchars($assessment['contact_phone']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pillar Progress -->
            <div class="pillar-grid animate-in delay-4">
                <?php
                $pillarColors = [
                    'H1' => ['color' => '#10B981', 'bg' => '#D1FAE5', 'class' => 'h1', 'shortName' => 'H1: สุขภาพ'],
                    'I2' => ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'class' => 'i2', 'shortName' => 'I2: ความปลอดภัย'],
                    'C3' => ['color' => '#F59E0B', 'bg' => '#FEF3C7', 'class' => 'c3', 'shortName' => 'C3: ชุมชน'],
                    'M4' => ['color' => '#8B5CF6', 'bg' => '#EDE9FE', 'class' => 'm4', 'shortName' => 'M4: บริหารจัดการ']
                ];
                foreach ($assessment['pillars'] as $pillarCode => $pillar):
                    $pc = $pillarColors[$pillarCode];
                    
                    // Self Score Stats
                    $selfActive = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
                    $selfTotal = array_sum(array_column($selfActive, 'self_score'));
                    $selfMaxRaw = count($selfActive);
                    $selfPercent = $selfMaxRaw > 0 ? ($selfTotal / $selfMaxRaw) * 100 : 0;
                    $selfWeighted = $selfMaxRaw > 0 ? ($selfTotal / $selfMaxRaw) * $pillar['weight'] : 0;
                    
                    // Auditor Score Stats
                    $auditorActive = array_filter($pillar['indicators'], fn($i) => !$i['auditor_is_na']);
                    $auditorTotal = array_sum(array_column($auditorActive, 'auditor_score'));
                    $auditorMaxRaw = count($auditorActive);
                    $auditorPercent = $auditorMaxRaw > 0 ? ($auditorTotal / $auditorMaxRaw) * 100 : 0;
                    $auditorWeighted = $auditorMaxRaw > 0 ? ($auditorTotal / $auditorMaxRaw) * $pillar['weight'] : 0;
                    
                    $hasAuditorScores = $assessment['status'] !== 'submitted' && $assessment['status'] !== 'draft';
                ?>
                <div class="pillar-card <?php echo $pc['class']; ?>">
                    <div class="pillar-header">
                        <div class="pillar-icon" style="background: <?php echo $pc['bg']; ?>; color: <?php echo $pc['color']; ?>;">
                            <?php if ($pillarCode === 'H1'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                            <?php elseif ($pillarCode === 'I2'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <?php elseif ($pillarCode === 'C3'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                            <?php else: ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div class="pillar-name"><?php echo $pc['shortName']; ?></div>
                            <div class="pillar-score">
                                <span style="color: var(--gray-500); font-size: 0.9rem; font-weight: 600;">บริษัท:</span> 
                                <span style="color: <?php echo $pc['color']; ?>;"><?php echo number_format($selfWeighted, 0); ?></span>
                                <?php if ($hasAuditorScores): ?>
                                <br>
                                <span style="color: var(--gray-500); font-size: 0.9rem; font-weight: 600;">กรรมการ (เฉลี่ย):</span> 
                                <span style="color: #6366f1;"><?php echo number_format($auditorWeighted, 1); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="font-size: 0.7rem; color: var(--gray-500); margin-bottom: 2px;">บริษัท (Self)</div>
                    <div class="pillar-progress" style="margin: 0 0 8px 0;">
                        <div class="pillar-progress-fill" style="width: <?php echo $selfPercent; ?>%; background: <?php echo $pc['color']; ?>;"></div>
                    </div>
                    
                    <?php if ($hasAuditorScores): ?>
                    <div style="font-size: 0.7rem; color: var(--gray-500); margin-bottom: 2px;">กรรมการ (Auditor)</div>
                    <div class="pillar-progress" style="margin: 0 0 8px 0; background: #e0e7ff;">
                        <div class="pillar-progress-fill" style="width: <?php echo $auditorPercent; ?>%; background: #6366f1;"></div>
                    </div>
                    <?php endif; ?>

                    <div class="pillar-stats">
                        <span><?php echo number_format($hasAuditorScores ? $auditorPercent : $selfPercent, 0); ?>%</span>
                        <span><?php echo $pillar['weight']; ?> คะแนน</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Charts -->
            <div class="charts-grid" style="grid-template-columns: 1fr;">
                <div class="chart-card animate-in delay-5">
                    <div class="chart-card-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>
                        </svg>
                        <span style="font-weight: 600; color: var(--gray-800);">แผนภาพความสมดุล 4 ด้าน</span>
                    </div>
                    <div class="chart-card-body">
                        <div style="height: 300px; max-width: 500px; margin: 0 auto;">
                            <canvas id="radarChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assessment Details - Grouped by Pillar -->
            <div style="margin-bottom: 2rem;">
                <h2 class="text-2xl font-semibold mb-6" style="color: var(--gray-900);">รายละเอียดการประเมิน</h2>
                
                <div class="grid grid-cols-1 gap-6">
                    <?php
                    $pillarInfo = [
                        'H1' => ['name_en' => 'Health Promotion',               'name_th' => 'การส่งเสริมสุขภาพ',              'color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => 'heart'],
                        'I2' => ['name_en' => 'Industrial Safety & Environment', 'name_th' => 'ความปลอดภัยและสิ่งแวดล้อม',     'color' => '#3B82F6', 'bg' => '#DBEAFE', 'icon' => 'shield'],
                        'C3' => ['name_en' => 'Community Engagement',            'name_th' => 'การมีส่วนร่วมกับชุมชน',         'color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => 'users'],
                        'M4' => ['name_en' => 'Management & Sustainability',     'name_th' => 'การบริหารจัดการและความยั่งยืน', 'color' => '#8B5CF6', 'bg' => '#EDE9FE', 'icon' => 'chart']
                    ];
                    
                    foreach ($assessment['pillars'] as $pillarCode => $pillar):
                        $info = $pillarInfo[$pillarCode];
                        $activeIndicators = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
                        $totalScore = array_sum(array_column($activeIndicators, 'self_score'));
                        $completedCount = count(array_filter($activeIndicators, fn($i) => $i['self_score'] > 0));
                        $totalCount = count($activeIndicators);
                        
                        // Effective weight per indicator for this pillar
                        $unitWeight = $totalCount > 0 ? ($pillar['weight'] / $totalCount) : 0;
                        
                        // Effective weight for auditor (might differ if auditor marks N/A differently)
                        $activeAudIndicators = array_filter($pillar['indicators'], fn($i) => !$i['auditor_is_na']);
                        $audUnitWeight = count($activeAudIndicators) > 0 ? ($pillar['weight'] / count($activeAudIndicators)) : 0;
                    ?>
                        <div class="card animate-fade-in-up">
                            <!-- Card Header -->
                            <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="width: 48px; height: 48px; border-radius: 12px; background-color: <?php echo $info['bg']; ?>; display: flex; align-items: center; justify-content: center; color: <?php echo $info['color']; ?>;">
                                        <?php if ($pillarCode === 'H1'): ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                            </svg>
                                        <?php elseif ($pillarCode === 'I2'): ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                                <path d="M9 12l2 2 4-4"/>
                                            </svg>
                                        <?php elseif ($pillarCode === 'C3'): ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                                                <path d="M16 3.13a4 4 0 010 7.75"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="20" x2="18" y2="10"/>
                                                <line x1="12" y1="20" x2="12" y2="4"/>
                                                <line x1="6" y1="20" x2="6" y2="14"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--gray-900); margin: 0;"><?php echo $pillarCode . ': ' . $info['name_en']; ?></h3>
                                        <p style="font-size: 0.72rem; color: <?php echo $info['color']; ?>; font-weight: 600; margin: 0.1rem 0 0; letter-spacing: 0.01em;"><?php echo $info['name_th']; ?></p>
                                        <p style="color: var(--gray-500); margin: 0.2rem 0 0 0; font-size: 0.875rem;">
                                            <?php echo $completedCount; ?>/<?php echo $totalCount; ?> ตัวชี้วัดเสร็จสมบูรณ์
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Score Summary -->
                                <div style="text-align: right; display: flex; gap: 1.5rem; align-items: center;">
                                    <div>
                                        <div style="font-size: 1.25rem; font-weight: 700; color: <?php echo $info['color']; ?>;">
                                            <?php 
                                            $selfWeightedTotal = $totalCount > 0 ? ($totalScore / $totalCount) * $pillar['weight'] : 0;
                                            echo number_format($selfWeightedTotal, 1); 
                                            ?>
                                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--gray-400); margin-left: 2px;">
                                                [<?php echo number_format($totalScore, 1); ?>]
                                            </span>
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--gray-500);">บริษัท</div>
                                    </div>
                                    <?php 
                                    $audTotal = array_sum(array_column(array_filter($pillar['indicators'], fn($i) => !$i['auditor_is_na']), 'auditor_score'));
                                    if ($hasAuditorScores): 
                                    ?>
                                    <div style="padding-left: 1rem; border-left: 1px solid var(--border-light);">
                                        <div style="font-size: 1.25rem; font-weight: 700; color: #6366f1;">
                                            <?php 
                                            $audEffectiveCount = count($activeAudIndicators);
                                            $audWeightedTotal = $audEffectiveCount > 0 ? ($audTotal / $audEffectiveCount) * $pillar['weight'] : 0;
                                            echo number_format($audWeightedTotal, 1); 
                                            ?>
                                            <span style="font-size: 0.8rem; font-weight: 600; color: var(--gray-400); margin-left: 2px;">
                                                [<?php echo number_format($audTotal, 1); ?>]
                                            </span>
                                        </div>
                                        <div style="font-size: 0.7rem; color: var(--gray-500);">กรรมการ (เฉลี่ย)</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Indicators Table -->
                            <div style="overflow-x: auto;">
                                <table class="table" style="margin-bottom: 0;">
                                        <thead>
                                            <tr>
                                            <th style="width: 30%;">ตัวชี้วัด</th>
                                            <th class="text-center" style="width: 12%;">บริษัท</th>
                                            <th class="text-center" style="width: 12%;">กรรมการ (เฉลี่ย)</th>
                                            <th class="text-center" style="width: 12%;">สถานะ</th>
                                            <th class="text-center" style="width: 10%;">หลักฐาน</th>
                                            <th style="width: 22%;">ความเห็น/หลักฐาน</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($pillar['indicators'] as $indicator): ?>
                                            <tr style="<?php echo $indicator['is_na'] ? 'opacity: 0.6; background-color: var(--gray-50);' : ''; ?>">
                                                <td>
                                                    <div style="font-weight: 500; color: var(--gray-900);"><?php echo $indicator['indicator_name'] ?? $indicator['name'] ?? ''; ?></div>
                                                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-top: 0.25rem;"><?php echo $indicator['description'] ?? ''; ?></div>
                                                    <?php if ($indicator['is_na']): ?>
                                                        <div style="font-size: 0.75rem; color: var(--gray-400); margin-top: 0.5rem; font-style: italic;">ไม่นำมาคิด (N/A)</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($indicator['is_na']): ?>
                                                        <span style="color: var(--gray-400);">-</span>
                                                    <?php elseif ($indicator['self_score'] !== null): ?>
                                                        <?php $selfWeighted = $indicator['self_score'] * $unitWeight; ?>
                                                        <div style="font-weight: 700; color: <?php echo $info['color']; ?>; font-size: 0.95rem;">
                                                            <?php echo number_format($selfWeighted, 2); ?>
                                                        </div>
                                                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 2px;">
                                                            [<?php echo number_format($indicator['self_score'], 2); ?>]
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: var(--gray-400);">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($indicator['auditor_is_na']): ?>
                                                        <span style="color: var(--gray-400); font-size: 0.8rem;">N/A</span>
                                                    <?php elseif ($indicator['auditor_score'] !== null): ?>
                                                        <!-- Multi-Auditor Professional View -->
                                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.4rem;">
                                                            <!-- Average Badge -->
                                                            <?php $audWeighted = $indicator['auditor_score'] * $audUnitWeight; ?>
                                                            <div style="background-color: #eef2ff; color: #4338ca; padding: 0.3rem 0.6rem; border-radius: 8px; border: 1px solid #c7d2fe; box-shadow: 0 1px 2px rgba(67, 56, 202, 0.05);">
                                                                <div style="font-weight: 800; font-size: 0.95rem;"><?php echo number_format($audWeighted, 2); ?></div>
                                                                <div style="font-size: 0.7rem; font-weight: 600; opacity: 0.7; border-top: 1px solid rgba(67, 56, 202, 0.1); margin-top: 2px; padding-top: 1px;">
                                                                    AVG [<?php echo number_format($indicator['auditor_score'], 2); ?>]
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if (!empty($indicator['evaluator_scores'])): ?>
                                                                <!-- Individual Details -->
                                                                <div style="display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; margin-top: 2px;">
                                                                    <?php foreach ($indicator['evaluator_scores'] as $es): ?>
                                                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 1px;">
                                                                            <div style="width: 16px; height: 16px; border-radius: 50%; background: #c7d2fe; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 0.5rem; font-weight: 800; cursor: help;"
                                                                                 title="<?php echo htmlspecialchars($es['auditor_name']); ?>">
                                                                                <?php echo strtoupper(substr($es['auditor_name'], 0, 1)); ?>
                                                                            </div>
                                                                            <div style="font-size: 0.6rem; font-weight: 700; color: #6366f1;">
                                                                                <?php echo $es['is_na'] ? 'N/A' : number_format($es['score'], 2); ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: var(--gray-400);">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($indicator['is_na'] || $indicator['auditor_is_na']): ?>
                                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background-color: var(--gray-100); color: var(--gray-500);">
                                                            ไม่นำมาคิด
                                                        </span>
                                                    <?php elseif ($indicator['auditor_score'] !== null): ?>
                                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background-color: var(--success-light); color: var(--success);">
                                                            ประเมินแล้ว
                                                        </span>
                                                    <?php elseif ($indicator['self_score'] > 0): ?>
                                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background-color: #e0f2fe; color: #0369a1;">
                                                            ส่งแล้ว
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.3rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background-color: var(--warning-light); color: var(--warning);">
                                                            รอดำเนินการ
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php 
                                                    $hasEvidence = !empty($indicator['self_evidence']) || ($indicator['attachment_count'] ?? 0) > 0;
                                                    $scoreId = $indicator['score_id'] ?? 0;
                                                    $attachCount = $indicator['attachment_count'] ?? 0;
                                                    $evidenceJson = htmlspecialchars(json_encode($indicator['self_evidence'] ?? ''), ENT_QUOTES);
                                                    $indicatorNameSafe = htmlspecialchars($indicator['indicator_name'] ?? $indicator['name'] ?? '', ENT_QUOTES);
                                                    ?>
                                                    <?php if ($hasEvidence && !$indicator['is_na']): ?>
                                                        <button class="btn btn-sm btn-outline" style="padding: 0.25rem 0.5rem; position: relative;" onclick="showEvidence(<?php echo intval($scoreId); ?>, '<?php echo $indicatorNameSafe; ?>', <?php echo $evidenceJson; ?>)">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                                <circle cx="12" cy="12" r="3"/>
                                                            </svg>
                                                            <?php if ($attachCount > 0): ?>
                                                                <span style="position: absolute; top: -5px; right: -5px; background: var(--primary); color: white; font-size: 0.6rem; width: 14px; height: 14px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;"><?php echo $attachCount; ?></span>
                                                            <?php endif; ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color: var(--gray-300); font-size: 0.8rem;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                                                        <!-- Company Evidence/Comment -->
                                                        <?php if (!empty($indicator['self_evidence'])): ?>
                                                            <div style="font-size: 0.75rem; color: #0369a1; line-height: 1.4; background: #f0f9ff; padding: 0.5rem 0.7rem; border-radius: 6px; border-left: 3px solid #0ea5e9;">
                                                                <div style="margin-bottom: 2px;"><strong style="color: #0369a1; font-size: 0.65rem; text-transform: uppercase;">บริษัท:</strong></div>
                                                                <?php echo htmlspecialchars($indicator['self_evidence']); ?>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Auditor Comments Thread -->
                                                        <?php if (!empty($indicator['evaluator_scores'])): ?>
                                                            <?php foreach ($indicator['evaluator_scores'] as $es): ?>
                                                                <?php if (!empty($es['comment'])): ?>
                                                                    <div style="font-size: 0.7rem; color: var(--gray-600); line-height: 1.4; background: #f8fafc; padding: 0.4rem 0.6rem; border-radius: 6px; border-left: 3px solid #6366f1; border-top: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                                                            <strong style="color: #4338ca;"><?php echo htmlspecialchars($es['auditor_name']); ?></strong>
                                                                            <span style="font-size: 0.6rem; color: var(--gray-400);"><?php echo date('d/m/Y', strtotime($es['evaluated_at'])); ?></span>
                                                                        </div>
                                                                        <?php echo htmlspecialchars($es['comment']); ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        <?php elseif (!empty($indicator['auditor_comment'])): ?>
                                                            <div style="font-size: 0.75rem; color: var(--gray-600); line-height: 1.4; background: #f8fafc; padding: 0.5rem; border-radius: 6px; border-left: 3px solid #6366f1;">
                                                                <?php echo htmlspecialchars($indicator['auditor_comment']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                            <!-- Card Footer with Summary -->
                            <div style="padding: 1.5rem; background-color: var(--gray-50); border-top: 1px solid var(--border-light); display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                                <div style="text-align: center;">
                                    <div style="font-size: 0.875rem; color: var(--gray-500); margin-bottom: 0.5rem;">ตัวชี้วัดทั้งหมด</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--gray-900);"><?php echo count($pillar['indicators']); ?></div>
                                </div>
                                <div style="text-align: center;">
                                    <div style="font-size: 0.875rem; color: var(--gray-500); margin-bottom: 0.5rem;">บริษัท (ประเมินแล้ว)</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--success);"><?php echo $completedCount; ?></div>
                                </div>
                                <?php if ($hasAuditorScores): ?>
                                <div style="text-align: center;">
                                    <div style="font-size: 0.875rem; color: var(--gray-500); margin-bottom: 0.5rem;">กรรมการ (ประเมินแล้ว)</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: #6366f1;"><?php echo count(array_filter($pillar['indicators'], fn($i) => $i['auditor_score'] !== null)); ?></div>
                                </div>
                                <?php endif; ?>
                                <div style="text-align: center;">
                                    <div style="font-size: 0.875rem; color: var(--gray-500); margin-bottom: 0.5rem;">ไม่นำมาคิด</div>
                                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--gray-400);"><?php echo count(array_filter($pillar['indicators'], fn($i) => $i['is_na'] || $i['auditor_is_na'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Print-Only Signature & Footer -->
            <div class="print-only print-signature-area" style="display:none; justify-content:space-around; gap:40px; margin:40px 30px 10px; padding-top:24px; border-top:2px solid #e2e8f0;">
                <div class="print-signature-block" style="text-align:center; flex:1; max-width:260px;">
                    <div class="print-signature-line" style="border-bottom:1px solid #334155; margin-bottom:8px; height:55px;"></div>
                    <div class="print-signature-label" style="font-size:9pt; font-weight:700; color:#1e293b;">ผู้ประเมิน (บริษัท)</div>
                    <div class="print-signature-sublabel" style="font-size:7.5pt; color:#64748b; margin-top:3px;">ชื่อ: ................................................................</div>
                    <div class="print-signature-sublabel" style="font-size:7.5pt; color:#64748b; margin-top:3px;">ตำแหน่ง: ........................................................</div>
                    <div class="print-signature-sublabel" style="font-size:7.5pt; color:#64748b; margin-top:3px;">วันที่ ........../........../..........  </div>
                </div>
                <div class="print-signature-block" style="text-align:center; flex:1; max-width:260px;">
                    <div class="print-signature-line" style="border-bottom:1px solid #334155; margin-bottom:8px; height:55px;"></div>
                    <div class="print-signature-label" style="font-size:9pt; font-weight:700; color:#1e293b;">กรรมการตรวจประเมิน</div>
                    <div class="print-signature-sublabel" style="font-size:7.5pt; color:#64748b; margin-top:3px;">ชื่อ: ................................................................</div>
                    <div class="print-signature-sublabel" style="font-size:7.5pt; color:#64748b; margin-top:3px;">ตำแหน่ง: ........................................................</div>
                    <div class="print-signature-sublabel" style="font-size:7.5pt; color:#64748b; margin-top:3px;">วันที่ ........../........../..........  </div>
                </div>
            </div>

            <div class="print-only print-doc-footer" style="display:none; text-align:center; font-size:7pt; color:#94a3b8; padding:10px 0; margin-top:8px; border-top:1px solid #e2e8f0;">
                <div style="margin-bottom:2px;">📋 HICM V2025 Assessment System — Healthy Industry Creator Matrix</div>
                <div><?php echo htmlspecialchars($assessment['company_name']); ?> — เอกสารฉบับที่ #<?php echo $assessmentId; ?> — พิมพ์เมื่อ <?php echo date('d/m/Y H:i'); ?> น.</div>
                <div style="font-size:6pt; margin-top:2px; color:#cbd5e1;">เอกสารนี้สร้างโดยระบบอัตโนมัติ ข้อมูลเป็นปัจจุบัน ณ วันที่พิมพ์</div>
            </div>
        </div>
    </main>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        // Radar Chart - Destroy existing chart if any before creating new one
        const radarCanvas = document.getElementById('radarChart');
        const existingChart = Chart.getChart(radarCanvas);
        if (existingChart) {
            existingChart.destroy();
        }
        const radarCtx = radarCanvas.getContext('2d');
        new Chart(radarCtx, {
            type: 'radar',
            data: {
                labels: [
                    'H1: ส่งเสริมสุขภาพ',
                    'I2: ความปลอดภัย',
                    'C3: ชุมชน',
                    'M4: บริหารจัดการ'
                ],
                datasets: [{
                    label: 'คะแนนประเมินตนเอง (บริษัท)',
                    data: [
                        <?php 
                        foreach ($assessment['pillars'] as $pillar) {
                            $activeIndicators = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
                            $selfTotal = array_sum(array_column($activeIndicators, 'self_score'));
                            $maxScore = count($activeIndicators);
                            $percentage = $maxScore > 0 ? ($selfTotal / $maxScore) * 100 : 0;
                            echo $percentage . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'var(--primary)',
                    fill: true
                }
                <?php if ($hasAuditorScores): ?>,
                {
                    label: 'คะแนนประเมินโดยกรรมการ',
                    data: [
                        <?php 
                        foreach ($assessment['pillars'] as $pillar) {
                            $activeAud = array_filter($pillar['indicators'], fn($i) => !$i['auditor_is_na']);
                            $audTotal = array_sum(array_column($activeAud, 'auditor_score'));
                            $maxScore = count($activeAud);
                            $percentage = $maxScore > 0 ? ($audTotal / $maxScore) * 100 : 0;
                            echo $percentage . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    borderColor: '#6366f1',
                    fill: true
                }
                <?php endif; ?>
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { stepSize: 20 }
                    }
                }
            }
        });

        // Evidence Modal - Full rewrite with proper styles
        function showEvidence(scoreId, indicatorName, selfEvidence) {
            console.log('showEvidence called:', { scoreId, indicatorName, selfEvidence });
            
            // Create and show evidence modal
            let modal = document.getElementById('evidenceModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'evidenceModal';
                modal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 1rem;';
                modal.innerHTML = `
                    <div style="background: white; border-radius: 12px; max-width: 900px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid #e5e7eb;">
                            <h3 id="evidenceModalTitle" style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #111827;">หลักฐานประกอบ</h3>
                            <button onclick="closeEvidenceModal()" style="background: none; border: none; cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div id="evidenceContent" style="flex: 1; overflow-y: auto; padding: 1.5rem;"></div>
                    </div>
                `;
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) closeEvidenceModal();
                });
                document.body.appendChild(modal);
            }
            
            document.getElementById('evidenceModalTitle').textContent = 'หลักฐาน: ' + indicatorName;
            const contentDiv = document.getElementById('evidenceContent');
            contentDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #6b7280;"><span style="display: inline-block; animation: spin 1s linear infinite;">⏳</span> กำลังโหลด...</div>';
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Build content
            let html = '';
            
            // Show text evidence if exists
            if (selfEvidence && selfEvidence.trim()) {
                html += `
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="font-weight: 600; color: #0369a1; margin-bottom: 0.5rem; font-size: 0.85rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 0.3rem;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            ความเห็น/หลักฐานเพิ่มเติม
                        </div>
                        <div style="color: #0c4a6e; white-space: pre-wrap; line-height: 1.6;">${escapeHtml(selfEvidence)}</div>
                    </div>
                `;
            }
            
            // Fetch attachments
            if (scoreId) {
                fetch('<?php echo getBaseUrl(); ?>/api/get_attachments.php?score_id=' + scoreId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.attachments && data.attachments.length > 0) {
                            html += `<div style="font-weight: 600; color: var(--gray-700); margin-bottom: 1rem; font-size: 0.9rem;">ไฟล์แนบ (${data.attachments.length} ไฟล์)</div>`;
                            html += '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">';
                            
                            data.attachments.forEach(att => {
                                const isImage = att.file_type && att.file_type.startsWith('image/');
                                const isPDF = att.file_type === 'application/pdf';
                                const previewUrl = '<?php echo getBaseUrl(); ?>/api/get-attachment.php?id=' + att.id + '&inline=1';
                                const downloadUrl = '<?php echo getBaseUrl(); ?>/api/get-attachment.php?id=' + att.id;
                                
                                html += `
                                    <div style="border: 1px solid var(--gray-200); border-radius: 8px; overflow: hidden; background: white;">
                                        <div style="height: 140px; background: var(--gray-100); display: flex; align-items: center; justify-content: center; cursor: pointer;" onclick="previewFile('${previewUrl}', '${escapeHtml(att.file_original_name)}', '${att.file_type}')">
                                            ${isImage ? 
                                                `<img src="${previewUrl}" style="max-width: 100%; max-height: 100%; object-fit: contain;" alt="${escapeHtml(att.file_original_name)}">` :
                                                isPDF ?
                                                `<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="1.5">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                    <polyline points="14,2 14,8 20,8"/>
                                                    <text x="12" y="16" text-anchor="middle" font-size="6" fill="#dc2626" stroke="none">PDF</text>
                                                </svg>` :
                                                `<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--gray-400)" stroke-width="1.5">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                    <polyline points="14,2 14,8 20,8"/>
                                                </svg>`
                                            }
                                        </div>
                                        <div style="padding: 0.75rem;">
                                            <div style="font-size: 0.8rem; font-weight: 500; color: var(--gray-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.5rem;" title="${escapeHtml(att.file_original_name)}">
                                                ${escapeHtml(att.file_original_name)}
                                            </div>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button onclick="previewFile('${previewUrl}', '${escapeHtml(att.file_original_name)}', '${att.file_type}')" class="btn btn-sm btn-outline" style="flex: 1; padding: 0.3rem;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                    ดู
                                                </button>
                                                <a href="${downloadUrl}" class="btn btn-sm btn-primary" style="flex: 1; padding: 0.3rem; text-decoration: none;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                        <polyline points="7 10 12 15 17 10"/>
                                                        <line x1="12" y1="15" x2="12" y2="3"/>
                                                    </svg>
                                                    ดาวน์โหลด
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            html += '</div>';
                        } else if (!selfEvidence || !selfEvidence.trim()) {
                            html += '<div style="text-align: center; padding: 2rem; color: var(--gray-500);">ไม่มีหลักฐานประกอบ</div>';
                        }
                        
                        contentDiv.innerHTML = html || '<div style="text-align: center; padding: 2rem; color: var(--gray-500);">ไม่มีหลักฐานประกอบ</div>';
                    })
                    .catch(err => {
                        console.error('Error fetching attachments:', err);
                        contentDiv.innerHTML = html || '<div style="text-align: center; padding: 2rem; color: var(--gray-500);">ไม่มีหลักฐานประกอบ</div>';
                    });
            } else {
                contentDiv.innerHTML = html || '<div style="text-align: center; padding: 2rem; color: var(--gray-500);">ไม่มีหลักฐานประกอบ</div>';
            }
        }
        
        function closeEvidenceModal() {
            const modal = document.getElementById('evidenceModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Preview file in new modal/window
        function previewFile(url, filename, fileType) {
            console.log('previewFile called:', { url, filename, fileType });
            
            // Create preview modal
            let previewModal = document.getElementById('filePreviewModal');
            if (!previewModal) {
                previewModal = document.createElement('div');
                previewModal.id = 'filePreviewModal';
                previewModal.style.cssText = 'position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 10001; padding: 1rem;';
                previewModal.innerHTML = `
                    <div style="background: white; border-radius: 12px; max-width: 95vw; width: 95vw; max-height: 95vh; height: 95vh; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border-bottom: 1px solid #e5e7eb; flex-shrink: 0;">
                            <h3 id="previewModalTitle" style="margin: 0; font-size: 1rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #111827;"></h3>
                            <button onclick="closeFilePreview()" style="background: none; border: none; cursor: pointer; padding: 0.5rem; border-radius: 6px; flex-shrink: 0; transition: background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div id="previewContent" style="flex: 1; overflow: auto; background: #1a1a1a; display: flex; align-items: center; justify-content: center;"></div>
                    </div>
                `;
                previewModal.addEventListener('click', function(e) {
                    if (e.target === previewModal) closeFilePreview();
                });
                document.body.appendChild(previewModal);
            }
            
            document.getElementById('previewModalTitle').textContent = filename;
            const contentDiv = document.getElementById('previewContent');
            
            const isImage = fileType && fileType.startsWith('image/');
            const isPDF = fileType === 'application/pdf';
            
            if (isImage) {
                contentDiv.innerHTML = `<img src="${url}" style="max-width: 100%; max-height: 100%; object-fit: contain;" alt="${escapeHtml(filename)}">`;
            } else if (isPDF) {
                contentDiv.innerHTML = `<iframe src="${url}" style="width: 100%; height: 100%; border: none;"></iframe>`;
            } else {
                // For other files, offer download
                contentDiv.innerHTML = `
                    <div style="text-align: center; color: white; padding: 2rem;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                        </svg>
                        <div style="margin-bottom: 1rem;">ไม่สามารถ preview ไฟล์ประเภทนี้ได้</div>
                        <a href="${url.replace('&inline=1', '')}" class="btn btn-primary">ดาวน์โหลดไฟล์</a>
                    </div>
                `;
            }
            
            previewModal.style.display = 'flex';
        }
        
        function closeFilePreview() {
            const modal = document.getElementById('filePreviewModal');
            if (modal) {
                modal.style.display = 'none';
                document.getElementById('previewContent').innerHTML = '';
            }
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFilePreview();
                closeEvidenceModal();
            }
        });

        // Excel Export Functionality
        function triggerExport() {
            // Prepare data
            const data = [
                ["ตัวชี้วัด", "คะแนนบริษัท", "คะแนนกรรมการ", "สถานะการประเมิน", "หลักฐาน / ความเห็นกรรมการ"],
                <?php foreach ($assessment['pillars'] as $pillar): ?>
                    <?php foreach ($pillar['indicators'] as $indicator): ?>
                        <?php if (!$indicator['is_na'] || !$indicator['auditor_is_na']): ?>
                            [
                                "<?php echo addslashes($indicator['indicator_name'] ?? $indicator['name'] ?? ''); ?>",
                                <?php echo $indicator['self_score'] > 0 ? $indicator['self_score'] : '0'; ?>,
                                <?php echo $indicator['auditor_score'] !== null ? $indicator['auditor_score'] : '0'; ?>,
                                "<?php echo $indicator['auditor_score'] !== null ? 'ประเมินแล้ว' : ($indicator['self_score'] > 0 ? 'ส่งแล้ว' : 'รอดำเนินการ'); ?>",
                                "<?php echo addslashes(($indicator['self_evidence'] ? 'หลักฐาน: ' . $indicator['self_evidence'] . ' | ' : '') . ($indicator['auditor_comment'] ? 'ความเห็น: ' . $indicator['auditor_comment'] : '')); ?>"
                            ],
        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            ];

            // Convert to worksheet
            const ws = XLSX.utils.aoa_to_sheet(data);

            // Create a new workbook and name the sheet
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Assessment Data");

            // Export to Excel file
            XLSX.writeFile(wb, "assessment_data_<?php echo $assessmentId; ?>.xlsx");
        }

        // Add stagger animation delays
        document.querySelectorAll('.animate-fade-in-up').forEach((el, index) => {
            el.style.animationDelay = (index * 0.1) + 's';
        });

        // PDF Download — professional layout handled by HICM_PDF module
        function downloadAssessmentPDF() {
            const companyName = <?php echo json_encode($assessment['company_name']); ?>;
            const year = <?php echo json_encode($assessment['year']); ?>;
            const filename = 'HICM_Assessment_' + companyName.replace(/[^a-zA-Z0-9\u0E00-\u0E7F_\- ]/g, '').replace(/\s+/g, '_') + '_' + year + '.pdf';
            
            HICM_PDF.download('.main-content', filename);
        }

        // Auto-trigger PDF download if ?pdf=1 or ?print=1
        <?php if (!empty($_GET['pdf'])): ?>
        window.addEventListener('load', function() {
            setTimeout(function() { downloadAssessmentPDF(); }, 1200);
        });
        <?php elseif (!empty($_GET['print'])): ?>
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 800);
        });
        <?php endif; ?>
    </script>
</body>
</html>
