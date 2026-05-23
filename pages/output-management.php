<?php
/**
 * HICM V2025 - Output Management & Report Generation
 * Admin panel สำหรับจัดการและส่งออกรายงานการประเมิน
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';
require_once __DIR__ . '/../includes/export_helpers.php';

requireAuth();

if (!hasRole(ROLE_ADMIN)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$baseUrl = getBaseUrl();

// Get all periods
try {
    $db = getDB()->getConnection();
    $stmt = $db->prepare("
        SELECT p.*, COUNT(a.id) as total_assessments, 
               SUM(CASE WHEN a.status IN ('evaluated', 'completed') THEN 1 ELSE 0 END) as completed_assessments
        FROM assessment_periods p
        LEFT JOIN assessments a ON p.id = a.period_id
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY p.year DESC, p.start_date DESC
    ");
    $stmt->execute();
    $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $periods = [];
    setFlashMessage('Error loading periods: ' . $e->getMessage(), 'error');
}

// Get detailed assessments if period selected
$selectedPeriodId = $_GET['period_id'] ?? null;
$periodAssessments = [];
$selectedPeriod = null;

if ($selectedPeriodId) {
    foreach ($periods as $p) {
        if ($p['id'] == $selectedPeriodId) {
            $selectedPeriod = $p;
            break;
        }
    }
    
    if ($selectedPeriod) {
        try {
            $stmt = $db->prepare("
                SELECT a.*, c.company_name, c.company_name_en, c.logo,
                       a.final_score, a.hicm_level,
                       u.name as evaluator_name,
                       COUNT(DISTINCT att.id) as attachment_count,
                       CASE WHEN a.status IN ('evaluated', 'completed') THEN 1 ELSE 0 END as is_complete,
                       SUM(CASE WHEN s.auditor_score IS NOT NULL THEN 1 ELSE 0 END) as evaluator_score_count,
                       SUM(CASE WHEN s.self_score IS NOT NULL THEN 1 ELSE 0 END) as self_scored_count,
                       SUM(CASE WHEN s.is_na = 1 THEN 1 ELSE 0 END) as self_na_count,
                       ROUND(AVG(CASE WHEN s.auditor_score IS NOT NULL AND s.auditor_is_na = 0 THEN s.auditor_score ELSE NULL END), 2) as auditor_avg_raw,
                       SUM(CASE WHEN s.auditor_score IS NOT NULL THEN 1 ELSE 0 END) as auditor_scored_count,
                       SUM(CASE WHEN s.auditor_is_na = 1 THEN 1 ELSE 0 END) as auditor_na_count
                FROM assessments a
                LEFT JOIN companies c ON a.company_id = c.id
                LEFT JOIN users u ON u.id = COALESCE(a.evaluator_id, a.evaluated_by)
                LEFT JOIN assessment_scores s ON a.id = s.assessment_id
                LEFT JOIN attachments att ON s.id = att.assessment_score_id
                WHERE a.period_id = ?
                GROUP BY a.id
                ORDER BY a.updated_at DESC
            ");
            $stmt->execute([$selectedPeriodId]);
            $periodAssessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch all evaluators per assessment with per-pillar weighted scores
            $assessmentIds = array_column($periodAssessments, 'id');
            $evaluatorsMap = []; // assessment_id => [evaluator details]
            if (!empty($assessmentIds)) {
                $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));
                // Get per-evaluator per-pillar data for weighted calculation
                $stmtEval = $db->prepare("
                    SELECT ae.assessment_id, ae.user_id, u.name as auditor_name,
                           ae.submitted_at,
                           p.code as pillar_code, p.weight as pillar_weight,
                           COUNT(CASE WHEN es.is_na = 0 THEN es.id END) as scored_count,
                           ROUND(SUM(CASE WHEN es.is_na = 0 THEN es.score ELSE 0 END), 2) as pillar_raw_sum
                    FROM assessment_evaluators ae
                    JOIN users u ON ae.user_id = u.id
                    LEFT JOIN assessment_evaluator_scores es ON es.assessment_id = ae.assessment_id AND es.user_id = ae.user_id
                    LEFT JOIN indicators i ON es.indicator_id = i.id
                    LEFT JOIN pillars p ON i.pillar_id = p.id
                    WHERE ae.assessment_id IN ({$placeholders})
                    GROUP BY ae.assessment_id, ae.user_id, u.name, ae.submitted_at, p.code, p.weight
                    ORDER BY ae.assessment_id, ae.assigned_at, p.code
                ");
                $stmtEval->execute($assessmentIds);
                $rawEvaluators = $stmtEval->fetchAll(PDO::FETCH_ASSOC);
                
                // Aggregate per-evaluator weighted totals
                $evalTemp = [];
                foreach ($rawEvaluators as $row) {
                    $key = $row['assessment_id'] . '_' . $row['user_id'];
                    if (!isset($evalTemp[$key])) {
                        $evalTemp[$key] = [
                            'assessment_id' => $row['assessment_id'],
                            'user_id' => $row['user_id'],
                            'auditor_name' => $row['auditor_name'],
                            'submitted_at' => $row['submitted_at'],
                            'total_scored' => 0,
                            'weighted_total' => 0,
                        ];
                    }
                    if ($row['pillar_code'] && $row['scored_count'] > 0) {
                        $weighted = ($row['pillar_raw_sum'] / $row['scored_count']) * $row['pillar_weight'];
                        $evalTemp[$key]['weighted_total'] += $weighted;
                        $evalTemp[$key]['total_scored'] += $row['scored_count'];
                    }
                }
                foreach ($evalTemp as $ev) {
                    $ev['weighted_total'] = round($ev['weighted_total'], 2);
                    $evaluatorsMap[$ev['assessment_id']][] = $ev;
                }
            }
            
            // Fetch self-evidence summary and auditor comments per assessment
            $commentsMap = []; // assessment_id => ['self_comments'=>[...], 'auditor_comments'=>[...]]
            if (!empty($assessmentIds)) {
                $placeholders = implode(',', array_fill(0, count($assessmentIds), '?'));
                $stmtComments = $db->prepare("
                    SELECT s.assessment_id, i.code as indicator_code,
                           s.self_score, s.self_evidence,
                           s.auditor_score, s.auditor_comment
                    FROM assessment_scores s
                    JOIN indicators i ON s.indicator_id = i.id
                    WHERE s.assessment_id IN ({$placeholders})
                    ORDER BY s.assessment_id, i.pillar_id, i.display_order
                ");
                $stmtComments->execute($assessmentIds);
                $allComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);
                foreach ($allComments as $c) {
                    $commentsMap[$c['assessment_id']][] = $c;
                }
            }
        } catch (Exception $e) {
            setFlashMessage('Error loading assessments: ' . $e->getMessage(), 'error');
        }
    }
}

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_type'])) {
    $exportType = $_POST['export_type']; // 'summary', 'detail', 'all'
    $periodId = $_POST['period_id'] ?? null;
    
    if ($exportType === 'summary' && $periodId) {
        exportPeriodSummary($periodId);
        exit;
    } elseif ($exportType === 'detail' && isset($_POST['assessment_id'])) {
        exportAssessmentDetail($_POST['assessment_id']);
        exit;
    } elseif ($exportType === 'all' && $periodId) {
        exportPeriodAllReports($periodId);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Output Management - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/css/style.css">
    <style>
        .output-hero {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: #fff;
            padding: 3rem 2rem;
            border-radius: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .output-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.15), transparent);
        }
        .output-hero h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            color: #ffffff;
        }
        .output-hero p {
            opacity: 0.9;
            font-size: 1.05rem;
            position: relative;
            z-index: 1;
            color: #ffffff;
        }

        .period-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .period-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            padding: 1.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .period-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            transition: left 0.5s ease;
        }

        .period-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -10px rgba(59, 130, 246, 0.2);
            border-color: #3b82f6;
        }

        .period-card:hover::before {
            left: 100%;
        }

        .period-badge {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .period-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .period-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #3b82f6;
            display: block;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #34d399);
            transition: width 0.5s ease;
        }

        .period-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-action {
            flex: 1;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            border: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-view {
            background: #f1f5f9;
            color: #3b82f6;
            border: 1px solid #e2e8f0;
        }

        .btn-view:hover {
            background: #eff6ff;
            border-color: #3b82f6;
        }

        .btn-export {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
        }

        /* Assessment List Modal */
        .assessment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .assessment-table th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .assessment-table td {
            border-bottom: 1px solid #f1f5f9;
            padding: 1rem;
            vertical-align: middle;
        }

        .assessment-table tr:hover td {
            background: #f8fafc;
        }

        .company-name {
            font-weight: 600;
            color: #1e293b;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .score-display {
            font-weight: 700;
            color: #3b82f6;
            font-size: 1.1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: #f8fafc;
            border-radius: 1rem;
            border: 2px dashed #e2e8f0;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #475569;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #94a3b8;
        }

        /* Evaluator chips in table */
        .evaluator-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .evaluator-chip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 0.65rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            transition: all 0.15s;
        }
        .evaluator-chip:hover {
            background: #eff6ff;
            border-color: #93c5fd;
        }
        .evaluator-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }
        .evaluator-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
            flex: 1;
        }
        .evaluator-name {
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .evaluator-meta {
            font-size: 0.7rem;
            color: #64748b;
        }
        .evaluator-score {
            font-weight: 700;
            font-size: 0.85rem;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            white-space: nowrap;
        }
        .evaluator-score.high { background: #d1fae5; color: #065f46; }
        .evaluator-score.mid  { background: #fef3c7; color: #92400e; }
        .evaluator-score.low  { background: #fee2e2; color: #991b1b; }
        .evaluator-score.none { background: #f1f5f9; color: #94a3b8; }

        .assessment-table td.col-evaluators {
            min-width: 240px;
            padding: 0.5rem 0.75rem;
        }

        /* Score column styling */
        .score-cell {
            text-align: center;
            white-space: nowrap;
        }
        .score-cell .score-main {
            font-weight: 800;
            font-size: 1.15rem;
            line-height: 1.2;
        }
        .score-cell .score-sub {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 1px;
        }
        .score-self .score-main { color: #059669; }
        .score-auditor .score-main { color: #3b82f6; }

        /* Detail toggle button */
        .btn-detail-toggle {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.75rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .btn-detail-toggle:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #334155;
        }
        .btn-detail-toggle.active {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #2563eb;
        }

        /* Detail row */
        .detail-row td {
            padding: 0 !important;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        .detail-row.hidden { display: none; }
        .detail-content {
            background: #f8fafc;
            padding: 1rem 1.5rem;
            max-height: 400px;
            overflow-y: auto;
        }
        .detail-content h4 {
            font-size: 0.85rem;
            color: #475569;
            margin: 0 0 0.5rem 0;
            font-weight: 700;
        }
        .detail-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        .detail-table th {
            background: #f1f5f9;
            padding: 0.5rem 0.75rem;
            text-align: left;
            font-weight: 700;
            color: #475569;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-table td {
            padding: 0.4rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }
        .detail-table tr:hover td { background: #fafbfc; }
        .detail-table .col-code { width: 60px; font-weight: 600; color: #6366f1; }
        .detail-table .col-score { width: 50px; text-align: center; font-weight: 700; }
        .detail-table .col-comment { color: #64748b; font-size: 0.72rem; }

        @media (max-width: 1024px) {
            .period-grid { grid-template-columns: 1fr; }
            .output-hero h1 { font-size: 1.5rem; }
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
            
            <div class="output-hero">
                <h1>📊 ศูนย์จัดการ Output & Report</h1>
                <p>สร้าง ดาวน์โหลด และจัดการรายงานการประเมิน HICM ตามรอบการประเมิน</p>
            </div>

            <?php if (empty($periods)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3>ยังไม่มีรอบการประเมิน</h3>
                    <p>สร้างรอบการประเมินใหม่ใน Periods Management ก่อน</p>
                </div>
            <?php else: ?>
                <div class="period-grid">
                    <?php foreach ($periods as $period): 
                        $completion = $period['total_assessments'] > 0 
                            ? round(($period['completed_assessments'] / $period['total_assessments']) * 100)
                            : 0;
                    ?>
                        <div class="period-card">
                            <div class="period-badge">
                                <span><?php echo $period['year']; ?></span>
                            </div>
                            <div class="period-title"><?php echo htmlspecialchars($period['name']); ?></div>
                            
                            <div class="period-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?php echo $period['total_assessments']; ?></span>
                                    <span class="stat-label">ทั้งหมด</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" style="color: #10b981;">
                                        <?php echo $period['completed_assessments']; ?>
                                    </span>
                                    <span class="stat-label">เสร็จสิ้น</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number" style="color: #f59e0b;">
                                        <?php echo ($period['total_assessments'] - $period['completed_assessments']); ?>
                                    </span>
                                    <span class="stat-label">คงค้าง</span>
                                </div>
                            </div>

                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $completion; ?>%"></div>
                            </div>
                            <p style="text-align: center; color: #64748b; font-size: 0.9rem; margin: 0.5rem 0;">
                                ความคืบหน้า: <strong><?php echo $completion; ?>%</strong>
                            </p>

                            <div class="period-actions">
                                <a href="?period_id=<?php echo $period['id']; ?>" class="btn-action btn-view">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    ดูรายการ
                                </a>
                                <form method="POST" style="flex: 1;">
                                    <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                    <input type="hidden" name="export_type" value="summary">
                                    <button type="submit" class="btn-action btn-export" style="width: 100%;">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                        </svg>
                                        สรุปผล
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($selectedPeriod && !empty($periodAssessments)): ?>
                    <section style="background: #fff; padding: 2rem; border-radius: 1.25rem; border: 1px solid #e2e8f0; margin-top: 2rem;">
                        <h2 style="margin-top: 0; color: #1e293b; font-size: 1.5rem; margin-bottom: 1.5rem;">
                            📋 รายการประเมิน: <?php echo htmlspecialchars($selectedPeriod['name']); ?> (<?php echo $selectedPeriod['year']; ?>)
                        </h2>

                        <div style="overflow-x: auto;">
                            <table class="assessment-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>สถานประกอบการ</th>
                                        <th style="text-align:center;">คะแนนตนเอง</th>
                                        <th>กรรมการผู้ประเมิน</th>
                                        <th style="text-align:center;">คะแนนกรรมการ</th>
                                        <th style="text-align:center;">ระดับ</th>
                                        <th>สถานะ</th>
                                        <th style="text-align:center;">ความเห็น</th>
                                        <th>ดำเนินการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $avatarColors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#84cc16'];
                                    $rowNum = 0;
                                    foreach ($periodAssessments as $assessment): 
                                        $rowNum++;
                                        $assessEvaluators = $evaluatorsMap[$assessment['id']] ?? [];
                                        $assessComments = $commentsMap[$assessment['id']] ?? [];
                                    ?>
                                        <tr>
                                            <!-- # -->
                                            <td style="color:#94a3b8; font-size:0.8rem; text-align:center;"><?php echo $rowNum; ?></td>
                                            <!-- Company name -->
                                            <td class="company-name">
                                                <?php echo htmlspecialchars($assessment['company_name']); ?>
                                                <?php if (!empty($assessment['company_name_en'])): ?>
                                                    <span style="display:block; font-size:0.7rem; color:#94a3b8; font-weight:400;"><?php echo htmlspecialchars($assessment['company_name_en']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Self Score (calculated/weighted) -->
                                            <td class="score-cell score-self">
                                                <?php if ($assessment['self_total_score'] > 0): ?>
                                                    <span class="score-main"><?php echo number_format($assessment['self_total_score'], 0); ?></span>
                                                    <div class="score-sub">
                                                        <?php echo intval($assessment['self_scored_count']); ?> ข้อ<?php if (intval($assessment['self_na_count']) > 0): ?> · N/A <?php echo intval($assessment['self_na_count']); ?><?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Auditor list -->
                                            <td class="col-evaluators">
                                                <?php if (!empty($assessEvaluators)): ?>
                                                    <div class="evaluator-list">
                                                        <?php foreach ($assessEvaluators as $ei => $ev): 
                                                            $color = $avatarColors[$ei % count($avatarColors)];
                                                            $wTotal = $ev['weighted_total'];
                                                            $scored = intval($ev['total_scored']);
                                                            // Determine score class based on weighted total (max 1000)
                                                            if ($scored == 0) {
                                                                $scoreClass = 'none';
                                                                $scoreLabel = 'รอ';
                                                            } elseif ($wTotal >= 800) {
                                                                $scoreClass = 'high';
                                                                $scoreLabel = number_format($wTotal, 0);
                                                            } elseif ($wTotal >= 600) {
                                                                $scoreClass = 'mid';
                                                                $scoreLabel = number_format($wTotal, 0);
                                                            } else {
                                                                $scoreClass = 'low';
                                                                $scoreLabel = number_format($wTotal, 0);
                                                            }
                                                        ?>
                                                            <div class="evaluator-chip">
                                                                <div class="evaluator-avatar" style="background:<?php echo $color; ?>;">
                                                                    <?php echo ($ei + 1); ?>
                                                                </div>
                                                                <div class="evaluator-info">
                                                                    <span class="evaluator-name"><?php echo htmlspecialchars($ev['auditor_name']); ?></span>
                                                                    <span class="evaluator-meta">
                                                                        <?php if ($scored > 0): ?>
                                                                            <?php echo $scored; ?>/60 ข้อ
                                                                        <?php else: ?>
                                                                            รอประเมิน
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                                <span class="evaluator-score <?php echo $scoreClass; ?>"><?php echo $scoreLabel; ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8; font-size: 0.8rem;">ยังไม่มอบหมาย</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Auditor Total Score (calculated/weighted) -->
                                            <td class="score-cell score-auditor">
                                                <?php if ($assessment['is_complete']): ?>
                                                    <span class="score-main"><?php echo number_format($assessment['auditor_total_score'] ?? 0, 0); ?></span>
                                                    <div class="score-sub">
                                                        <?php echo intval($assessment['auditor_scored_count']); ?> ข้อ<?php if (intval($assessment['auditor_na_count']) > 0): ?> · N/A <?php echo intval($assessment['auditor_na_count']); ?><?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- HICM Level -->
                                            <td style="text-align:center;">
                                                <?php if ($assessment['is_complete']): ?>
                                                    <?php 
                                                    $level = $assessment['hicm_level'] ?? 1;
                                                    $levelNames = [1=>'เริ่มต้น',2=>'กำลังพัฒนา',3=>'พัฒนาดี',4=>'เป็นเลิศ',5=>'ระดับโลก'];
                                                    $levelColors = [1=>'#94a3b8',2=>'#f59e0b',3=>'#10b981',4=>'#3b82f6',5=>'#8b5cf6'];
                                                    ?>
                                                    <div style="display:flex; align-items:center; justify-content:center; gap:0.3rem;">
                                                        <span style="width:26px; height:26px; border-radius:50%; background:<?php echo $levelColors[$level]; ?>; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem;"><?php echo $level; ?></span>
                                                    </div>
                                                    <div style="font-size:0.65rem; font-weight:600; color:<?php echo $levelColors[$level]; ?>; margin-top:2px;"><?php echo $levelNames[$level]; ?></div>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Status -->
                                            <td>
                                                <span class="status-badge <?php echo $assessment['is_complete'] ? 'status-completed' : 'status-pending'; ?>">
                                                    <?php echo $assessment['is_complete'] ? '✓ เสร็จ' : '⏳ รอ'; ?>
                                                </span>
                                            </td>
                                            <!-- Comments toggle -->
                                            <td style="text-align:center;">
                                                <?php if (!empty($assessComments)): ?>
                                                    <button class="btn-detail-toggle" onclick="toggleDetail(<?php echo $assessment['id']; ?>, this)">
                                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
                                                        ดู
                                                    </button>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <!-- Actions -->
                                            <td>
                                                <?php if ($assessment['is_complete']): ?>
                                                    <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                                        <a href="<?php echo $baseUrl; ?>/pages/report-preview.php?assessment_id=<?php echo $assessment['id']; ?>" 
                                                           style="background:#3b82f6; color:#fff; padding:0.4rem 0.6rem; border-radius:6px; font-weight:600; font-size:0.75rem; text-decoration:none; white-space:nowrap;">
                                                            👁️
                                                        </a>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                                                            <input type="hidden" name="assessment_id" value="<?php echo $assessment['id']; ?>">
                                                            <input type="hidden" name="export_type" value="detail">
                                                            <button type="submit" style="background:#10b981; color:#fff; border:none; padding:0.4rem 0.6rem; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.75rem;">📥</button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color:#cbd5e1;">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <!-- Expandable detail row -->
                                        <tr class="detail-row hidden" id="detail-<?php echo $assessment['id']; ?>">
                                            <td colspan="9">
                                                <div class="detail-content">
                                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                                                        <!-- Self Assessment Comments -->
                                                        <div>
                                                            <h4>📝 ความเห็น/หลักฐาน ประเมินตนเอง</h4>
                                                            <table class="detail-table">
                                                                <thead><tr><th>ข้อ</th><th>คะแนน</th><th>หลักฐาน/ความเห็น</th></tr></thead>
                                                                <tbody>
                                                                    <?php foreach ($assessComments as $c): ?>
                                                                        <?php if (!empty($c['self_evidence'])): ?>
                                                                        <tr>
                                                                            <td class="col-code"><?php echo $c['indicator_code']; ?></td>
                                                                            <td class="col-score"><?php echo number_format($c['self_score'], 2); ?></td>
                                                                            <td class="col-comment"><?php echo htmlspecialchars(mb_strimwidth($c['self_evidence'], 0, 120, '...')); ?></td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <!-- Auditor Comments -->
                                                        <div>
                                                            <h4>🔍 ความเห็นกรรมการ</h4>
                                                            <table class="detail-table">
                                                                <thead><tr><th>ข้อ</th><th>คะแนน</th><th>ความเห็นกรรมการ</th></tr></thead>
                                                                <tbody>
                                                                    <?php foreach ($assessComments as $c): ?>
                                                                        <?php if (!empty($c['auditor_comment'])): ?>
                                                                        <tr>
                                                                            <td class="col-code"><?php echo $c['indicator_code']; ?></td>
                                                                            <td class="col-score"><?php echo $c['auditor_score'] !== null ? number_format($c['auditor_score'], 2) : '-'; ?></td>
                                                                            <td class="col-comment"><?php echo htmlspecialchars(mb_strimwidth($c['auditor_comment'], 0, 150, '...')); ?></td>
                                                                        </tr>
                                                                        <?php endif; ?>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Toggle detail/comment row
        function toggleDetail(assessmentId, btn) {
            const row = document.getElementById('detail-' + assessmentId);
            if (!row) return;
            const isHidden = row.classList.contains('hidden');
            // Close all other open details
            document.querySelectorAll('.detail-row:not(.hidden)').forEach(r => {
                r.classList.add('hidden');
            });
            document.querySelectorAll('.btn-detail-toggle.active').forEach(b => {
                b.classList.remove('active');
                b.querySelector('svg').style.transform = '';
            });
            if (isHidden) {
                row.classList.remove('hidden');
                btn.classList.add('active');
                btn.querySelector('svg').style.transform = 'rotate(180deg)';
            }
        }

        // Auto-submit for period selection
        document.querySelectorAll('[data-filter-period]').forEach(btn => {
            btn.addEventListener('click', function() {
                window.location.href = '?period_id=' + this.dataset.filterPeriod;
            });
        });
    </script>
</body>
</html>
