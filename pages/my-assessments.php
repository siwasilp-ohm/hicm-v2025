<?php
/**
 * HICM V2025 Assessment System - My Assessments (Auditor View)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();
requireRole(ROLE_AUDITOR);

$user = getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get pending assessments (assigned to this auditor, not yet submitted by THIS auditor)
// Use ae.submitted_at IS NULL to track per-evaluator status, not global assessment status
$pendingStmt = $db->prepare("
    SELECT a.id, a.status, a.submitted_at, a.self_total_score,
           c.company_name, c.industry_type,
           p.name as period_name, p.year as period_year,
           p.evaluation_end_date, p.submission_deadline,
           ae.assigned_at,
           COUNT(DISTINCT es.indicator_id) as scored_count,
           (SELECT COUNT(*) FROM assessment_scores WHERE assessment_id = a.id) as total_indicators,
           (SELECT COUNT(*) FROM assessment_evaluators WHERE assessment_id = a.id) as total_evaluators,
           (SELECT COUNT(*) FROM assessment_evaluators WHERE assessment_id = a.id AND submitted_at IS NOT NULL) as submitted_evaluators
    FROM assessments a
    JOIN assessment_evaluators ae ON a.id = ae.assessment_id AND ae.user_id = ?
    JOIN companies c ON a.company_id = c.id
    JOIN assessment_periods p ON a.period_id = p.id
    LEFT JOIN assessment_evaluator_scores es ON a.id = es.assessment_id AND es.user_id = ?
    WHERE ae.submitted_at IS NULL
    AND a.status IN ('submitted', 'under_review')
    AND p.status NOT IN ('completed')
    GROUP BY a.id
    ORDER BY ae.assigned_at DESC
");
$pendingStmt->execute([$user['id'], $user['id']]);
$pendingAssessments = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed assessments (submitted by THIS auditor)
// Use ae.submitted_at IS NOT NULL to show assessments this auditor has already submitted
$completedStmt = $db->prepare("
    SELECT a.id, a.status, a.submitted_at, a.evaluated_at,
           a.self_total_score, a.auditor_total_score, a.final_score, a.hicm_level,
           c.company_name, c.industry_type,
           p.name as period_name, p.year as period_year,
           p.evaluation_end_date, p.status as period_status,
           ae.assigned_at, ae.submitted_at as evaluator_submitted_at,
           MAX(es.evaluated_at) as auditor_evaluated_at,
           COUNT(DISTINCT es.indicator_id) as scored_count,
           (SELECT COUNT(*) FROM assessment_scores WHERE assessment_id = a.id) as total_indicators,
           (SELECT COUNT(*) FROM assessment_evaluators WHERE assessment_id = a.id) as total_evaluators,
           (SELECT COUNT(*) FROM assessment_evaluators WHERE assessment_id = a.id AND submitted_at IS NOT NULL) as submitted_evaluators
    FROM assessments a
    JOIN assessment_evaluators ae ON a.id = ae.assessment_id AND ae.user_id = ?
    JOIN companies c ON a.company_id = c.id
    JOIN assessment_periods p ON a.period_id = p.id
    LEFT JOIN assessment_evaluator_scores es ON a.id = es.assessment_id AND es.user_id = ?
    WHERE ae.submitted_at IS NOT NULL
    GROUP BY a.id
    ORDER BY COALESCE(ae.submitted_at, MAX(es.evaluated_at), a.evaluated_at) DESC
    LIMIT 50
");
$completedStmt->execute([$user['id'], $user['id']]);
$completedAssessments = $completedStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: Safe date formatting with null check
function safeFormatDate($date, $format = 'd M Y') {
    if (empty($date) || $date === '0000-00-00 00:00:00') return null;
    $ts = strtotime($date);
    if ($ts === false || $ts <= 0) return null;
    
    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $day = date('j', $ts);
    $month = (int)date('n', $ts);
    $year = (int)date('Y', $ts) + 543; // Buddhist year
    $time = date('H:i', $ts);
    return $day . ' ' . $thaiMonths[$month] . ' ' . $year . ' ' . $time . ' น.';
}

// Helper: Thai relative time
function thaiRelativeTime($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') return null;
    $ts = strtotime($date);
    if ($ts === false || $ts <= 0) return null;
    
    $now = time();
    $diff = $now - $ts;
    
    if ($diff < 0) {
        // Future date
        $absDiff = abs($diff);
        if ($absDiff < 3600) return 'อีก ' . max(1, floor($absDiff / 60)) . ' นาที';
        if ($absDiff < 86400) return 'อีก ' . floor($absDiff / 3600) . ' ชม.';
        if ($absDiff < 2592000) return 'อีก ' . floor($absDiff / 86400) . ' วัน';
        return safeFormatDate($date);
    }
    
    if ($diff < 60) return 'เมื่อสักครู่';
    if ($diff < 3600) return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400) return floor($diff / 3600) . ' ชม.ที่แล้ว';
    if ($diff < 604800) return floor($diff / 86400) . ' วันที่แล้ว';
    if ($diff < 2592000) return floor($diff / 604800) . ' สัปดาห์ที่แล้ว';
    return safeFormatDate($date);
}

// Helper: Deadline status
function getDeadlineInfo($endDate) {
    if (empty($endDate)) return ['status' => 'none', 'text' => 'ไม่มีกำหนด', 'class' => ''];
    $deadline = strtotime($endDate . ' 23:59:59');
    $now = time();
    $daysLeft = ceil(($deadline - $now) / 86400);
    
    if ($daysLeft < 0) {
        return ['status' => 'overdue', 'text' => 'เลยกำหนด ' . abs($daysLeft) . ' วัน', 'class' => 'deadline-overdue'];
    } elseif ($daysLeft <= 3) {
        return ['status' => 'urgent', 'text' => 'เหลือ ' . $daysLeft . ' วัน', 'class' => 'deadline-urgent'];
    } elseif ($daysLeft <= 7) {
        return ['status' => 'warning', 'text' => 'เหลือ ' . $daysLeft . ' วัน', 'class' => 'deadline-warning'];
    } else {
        return ['status' => 'ok', 'text' => 'เหลือ ' . $daysLeft . ' วัน', 'class' => 'deadline-ok'];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การประเมินของฉัน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
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

        .pro-card:hover {
            box-shadow: var(--pro-shadow-lg);
            transform: translateY(-4px);
            border-color: var(--pro-accent);
        }

        /* ========== Management Hero ========== */
        .management-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: 24px;
            padding: 3rem;
            color: white;
            margin-bottom: 2.5rem;
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
            max-width: 60%;
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #93c5fd;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.75rem 0;
            letter-spacing: -0.02em;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1rem;
            color: #94a3b8;
            margin: 0;
            line-height: 1.6;
        }

        .hero-stats-group {
            display: flex;
            gap: 1.5rem;
            position: relative;
            z-index: 2;
        }

        .hero-stat-box {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(12px);
            padding: 1.5rem 2rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-width: 160px;
            text-align: center;
        }

        .stat-val {
            display: block;
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .stat-lbl {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
        }

        .stat-val.primary { color: #60a5fa; }
        .stat-val.warning { color: #fbbf24; }
        .stat-val.success { color: #34d399; }

        /* ========== Assessment Cards ========== */
        .section-header-pro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding: 0 0.5rem;
        }

        .section-title-pro {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--pro-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .assessment-card-pro {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            padding: 1.5rem 2rem;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .company-info-pro {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .company-name-pro {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--pro-primary);
        }

        .meta-tags-pro {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .tag-pro {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            background: #f1f5f9;
            color: #475569;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .tag-pro.period { background: #eff6ff; color: #1d4ed8; }
        .tag-pro.score { background: #fefce8; color: #a16207; }
        .tag-pro.industry { background: #f0fdf4; color: #15803d; }
        .tag-pro.date { background: #fafafa; color: #71717a; border: 1px solid #f4f4f5; }
        .tag-pro.date i { opacity: 0.7; }
        .tag-pro.submitted { background: #f0f9ff; color: #0369a1; border: 1px solid #e0f2fe; }
        .tag-pro.assigned { background: #faf5ff; color: #7c3aed; border: 1px solid #f3e8ff; }
        .tag-pro.evaluated { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .tag-pro.deadline-ok { background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7; }
        .tag-pro.deadline-warning { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        .tag-pro.deadline-urgent { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; animation: pulse-urgent 2s infinite; }
        .tag-pro.deadline-overdue { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        
        @keyframes pulse-urgent {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; box-shadow: 0 0 8px rgba(220, 38, 38, 0.3); }
        }
        
        .date-detail {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 500;
            margin-left: 0.25rem;
        }

        /* ========== Scoring Progress ========== */
        .scoring-progress-pro {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .progress-bar-track {
            flex: 1;
            max-width: 180px;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.4s ease;
        }

        .progress-bar-fill.complete { background: linear-gradient(90deg, #10b981, #34d399); }
        .progress-bar-fill.partial { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .progress-bar-fill.empty { background: #cbd5e1; }

        .progress-text {
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .progress-text.complete { color: #10b981; }
        .progress-text.partial { color: #d97706; }
        .progress-text.empty { color: #94a3b8; }

        .tag-pro.no-score { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .score-display-pro {
            text-align: right;
            min-width: 120px;
        }

        .score-val-pro {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--pro-primary);
            line-height: 1;
        }

        .score-lbl-pro {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--pro-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        .badge-pro {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .badge-pending { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
        .badge-completed { background: #f0fdf4; color: #16a34a; border: 1px solid #dcfce7; }

        .empty-state-pro {
            padding: 4rem 2rem;
            text-align: center;
            background: white;
            border-radius: 20px;
            border: 2px dashed #e2e8f0;
        }

        .empty-icon-pro {
            width: 80px;
            height: 80px;
            background: #f8fafc;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: #cbd5e1;
            font-size: 2rem;
        }

        .empty-title-pro {
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .empty-text-pro {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .btn-pro {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.75rem 1.5rem;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.95rem;
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

        .btn-pro-withdraw {
            background: white;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-pro-withdraw:hover {
            background: #fef2f2;
            border-color: #fca5a5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Management Hero -->
            <div class="management-hero reveal">
                <div class="hero-content">
                    <div class="hero-tag">
                        <i class="fas fa-briefcase"></i>
                        Workspace for Auditor
                    </div>
                    <h1 class="hero-title">การประเมินของฉัน</h1>
                    <p class="hero-subtitle">ตรวจสอบ ติดตาม และดำเนินการประเมินสถานประกอบที่ได้รับมอบหมายในระบบ HICM V2025</p>
                </div>
                <div class="hero-stats-group">
                    <div class="hero-stat-box">
                        <span class="stat-val warning"><?php echo count($pendingAssessments); ?></span>
                        <span class="stat-lbl">รอดำเนินการ</span>
                    </div>
                    <div class="hero-stat-box">
                        <span class="stat-val success"><?php echo count($completedAssessments); ?></span>
                        <span class="stat-lbl">ประเมินแล้ว</span>
                    </div>
                </div>
            </div>
            
            <!-- Pending Assessments -->
            <div style="margin-bottom: 3rem;">
                <div class="section-header-pro reveal" style="animation-delay: 0.1s;">
                    <h2 class="section-title-pro">
                        <i class="fas fa-clock" style="color: var(--pro-warning);"></i>
                        รอดำเนินการประเมิน
                    </h2>
                    <?php if (count($pendingAssessments) > 0): ?>
                        <span class="badge-pro badge-pending"><?php echo count($pendingAssessments); ?> รายการ</span>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($pendingAssessments)): ?>
                    <div class="empty-state-pro reveal" style="animation-delay: 0.2s;">
                        <div class="empty-icon-pro">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="empty-title-pro">ไม่มีรายการรอดำเนินการ</h3>
                        <p class="empty-text-pro">เยี่ยมมาก! ทุกรายการประเมินได้รับการตรวจสอบครบถ้วนแล้ว</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingAssessments as $index => $assessment): ?>
                        <?php $deadlineInfo = getDeadlineInfo($assessment['evaluation_end_date']); ?>
                        <div class="pro-card assessment-card-pro reveal" style="animation-delay: <?php echo 0.2 + ($index * 0.1); ?>s;">
                            <div class="company-info-pro">
                                <div class="company-name-pro"><?php echo htmlspecialchars($assessment['company_name']); ?></div>
                                <div class="meta-tags-pro">
                                    <span class="tag-pro period">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo $assessment['period_name']; ?> (<?php echo $assessment['period_year']; ?>)
                                    </span>
                                    <span class="tag-pro industry">
                                        <i class="fas fa-industry"></i>
                                        <?php echo htmlspecialchars($assessment['industry_type'] ?? 'General'); ?>
                                    </span>
                                    <span class="tag-pro score">
                                        <i class="fas fa-star"></i>
                                        คะแนนบริษัท: <?php echo number_format($assessment['self_total_score'], 1); ?>
                                    </span>
                                    <?php if ($assessment['assigned_at']): ?>
                                    <span class="tag-pro assigned" title="<?php echo safeFormatDate($assessment['assigned_at']); ?>">
                                        <i class="fas fa-user-check"></i>
                                        ได้รับมอบหมาย: <?php echo thaiRelativeTime($assessment['assigned_at']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($assessment['submitted_at']): ?>
                                    <span class="tag-pro submitted" title="<?php echo safeFormatDate($assessment['submitted_at']); ?>">
                                        <i class="fas fa-paper-plane"></i>
                                        บริษัทส่งเมื่อ: <?php echo thaiRelativeTime($assessment['submitted_at']); ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($deadlineInfo['status'] !== 'none'): ?>
                                    <span class="tag-pro <?php echo $deadlineInfo['class']; ?>" title="กำหนดประเมิน: <?php echo safeFormatDate($assessment['evaluation_end_date']); ?>">
                                        <i class="fas fa-<?php echo $deadlineInfo['status'] === 'overdue' ? 'exclamation-triangle' : 'hourglass-half'; ?>"></i>
                                        <?php echo $deadlineInfo['text']; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php 
                                        $totalEval = (int)$assessment['total_evaluators'];
                                        $submittedEval = (int)$assessment['submitted_evaluators'];
                                        if ($totalEval > 1): 
                                    ?>
                                    <span class="tag-pro" style="background: #faf5ff; color: #7c3aed; border: 1px solid #f3e8ff;">
                                        <i class="fas fa-users"></i>
                                        กรรมการส่งแล้ว <?php echo $submittedEval; ?>/<?php echo $totalEval; ?> ท่าน
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                    $scored = (int)$assessment['scored_count'];
                                    $total = (int)$assessment['total_indicators'];
                                    $pct = $total > 0 ? round(($scored / $total) * 100) : 0;
                                    $progressClass = $pct >= 100 ? 'complete' : ($pct > 0 ? 'partial' : 'empty');
                                ?>
                                <?php if ($total > 0): ?>
                                <div class="scoring-progress-pro">
                                    <div class="progress-bar-track">
                                        <div class="progress-bar-fill <?php echo $progressClass; ?>" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                    <span class="progress-text <?php echo $progressClass; ?>">
                                        <i class="fas fa-<?php echo $pct >= 100 ? 'check-circle' : ($pct > 0 ? 'spinner' : 'circle'); ?>"></i>
                                        <?php echo $scored; ?>/<?php echo $total; ?> ข้อ (<?php echo $pct; ?>%)
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1.5rem;">
                                <div class="score-display-pro" style="border-right: 1px solid var(--pro-border); padding-right: 1.5rem; margin-right: 0.5rem;">
                                    <?php if ($scored > 0 && $scored < $total): ?>
                                        <div class="score-val-pro" style="color: var(--pro-warning);"><?php echo $pct; ?>%</div>
                                        <div class="score-lbl-pro">ดำเนินการ</div>
                                    <?php else: ?>
                                        <div class="score-val-pro" style="color: var(--pro-warning);">PENDING</div>
                                        <div class="score-lbl-pro">รอประเมิน</div>
                                    <?php endif; ?>
                                </div>
                                <a href="auditor-evaluate.php?id=<?php echo $assessment['id']; ?>" class="btn-pro btn-pro-primary">
                                    <i class="fas fa-pen-to-square"></i>
                                    <?php echo $scored > 0 ? 'ประเมินต่อ' : 'เริ่มการประเมิน'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Completed Assessments -->
            <div>
                <div class="section-header-pro reveal" style="animation-delay: 0.4s;">
                    <h2 class="section-title-pro">
                        <i class="fas fa-check-double" style="color: var(--pro-success);"></i>
                        ประวัติการประเมิน
                    </h2>
                    <?php if (count($completedAssessments) > 0): ?>
                        <span class="badge-pro badge-completed"><?php echo count($completedAssessments); ?> รายการ</span>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($completedAssessments)): ?>
                    <div class="empty-state-pro reveal" style="animation-delay: 0.5s;">
                        <div class="empty-icon-pro">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h3 class="empty-title-pro">ยังไม่มีประวัติการประเมิน</h3>
                        <p class="empty-text-pro">รายการที่ประเมินเสร็จสิ้นจะปรากฏที่นี่</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($completedAssessments as $index => $assessment): ?>
                        <?php 
                            // Use evaluator's own submitted_at, then score evaluated_at, then assessment evaluated_at
                            $evalDate = $assessment['evaluator_submitted_at'] ?: ($assessment['auditor_evaluated_at'] ?: $assessment['evaluated_at']);
                            $cScored = (int)$assessment['scored_count'];
                            $cTotal = (int)$assessment['total_indicators'];
                            $cPct = $cTotal > 0 ? round(($cScored / $cTotal) * 100) : 0;
                            $cTotalEval = (int)$assessment['total_evaluators'];
                            $cSubmittedEval = (int)$assessment['submitted_evaluators'];
                            $allDone = ($cSubmittedEval >= $cTotalEval);
                        ?>
                        <div class="pro-card assessment-card-pro reveal" style="animation-delay: <?php echo 0.5 + ($index * 0.1); ?>s;">
                            <div class="company-info-pro">
                                <div class="company-name-pro"><?php echo htmlspecialchars($assessment['company_name']); ?></div>
                                <div class="meta-tags-pro">
                                    <span class="tag-pro period">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo $assessment['period_name']; ?> (<?php echo $assessment['period_year']; ?>)
                                    </span>
                                    <span class="tag-pro industry">
                                        <i class="fas fa-industry"></i>
                                        <?php echo htmlspecialchars($assessment['industry_type'] ?? 'General'); ?>
                                    </span>
                                    <span class="tag-pro" style="background: #f0f9ff; color: #0369a1;">
                                        <i class="fas fa-trophy"></i>
                                        HICM Level <?php echo $assessment['hicm_level']; ?>
                                    </span>
                                    <?php if ($cScored === 0): ?>
                                    <span class="tag-pro no-score">
                                        <i class="fas fa-exclamation-circle"></i>
                                        ไม่ได้ให้คะแนน
                                    </span>
                                    <?php elseif ($cPct < 100 && $cTotal > 0): ?>
                                    <span class="tag-pro" style="background: #fffbeb; color: #d97706; border: 1px solid #fef3c7;">
                                        <i class="fas fa-pen"></i>
                                        ให้คะแนน <?php echo $cScored; ?>/<?php echo $cTotal; ?> ข้อ
                                    </span>
                                    <?php else: ?>
                                    <span class="tag-pro" style="background: #f0fdf4; color: #15803d; border: 1px solid #dcfce7;">
                                        <i class="fas fa-check-circle"></i>
                                        ให้คะแนนครบ <?php echo $cTotal; ?> ข้อ
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($evalDate): ?>
                                    <span class="tag-pro evaluated" title="<?php echo safeFormatDate($evalDate); ?>">
                                        <i class="fas fa-calendar-check"></i>
                                        ส่งผลเมื่อ: <?php echo thaiRelativeTime($evalDate); ?>
                                        <span class="date-detail">(<?php echo safeFormatDate($evalDate); ?>)</span>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($cTotalEval > 1): ?>
                                    <span class="tag-pro" style="background: <?php echo $allDone ? '#f0fdf4' : '#faf5ff'; ?>; color: <?php echo $allDone ? '#15803d' : '#7c3aed'; ?>; border: 1px solid <?php echo $allDone ? '#dcfce7' : '#f3e8ff'; ?>;">
                                        <i class="fas fa-<?php echo $allDone ? 'check-double' : 'users'; ?>"></i>
                                        <?php echo $allDone ? 'กรรมการส่งครบ ' . $cTotalEval . ' ท่าน' : 'กรรมการส่ง ' . $cSubmittedEval . '/' . $cTotalEval . ' ท่าน'; ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($assessment['submitted_at']): ?>
                                    <span class="tag-pro submitted" title="<?php echo safeFormatDate($assessment['submitted_at']); ?>">
                                        <i class="fas fa-paper-plane"></i>
                                        บริษัทส่ง: <?php echo safeFormatDate($assessment['submitted_at']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                                <?php 
                                    $isPeriodEnded = ($assessment['period_status'] === 'completed');
                                    // Consider evaluation_end_date (inclusive of the day)
                                    $isDeadlinePassed = false;
                                    if ($assessment['evaluation_end_date']) {
                                        $deadline = strtotime($assessment['evaluation_end_date'] . ' 23:59:59');
                                        if ($deadline < time()) {
                                            $isDeadlinePassed = true;
                                        }
                                    }
                                    $isEditable = (!$isPeriodEnded && !$isDeadlinePassed);
                                ?>
                                <div class="score-display-pro" style="border-right: 1px solid var(--pro-border); padding-right: 1.5rem; margin-right: 0.5rem;">
                                    <div class="score-val-pro" style="color: var(--pro-success);"><?php echo number_format($assessment['final_score'], 1); ?></div>
                                    <div class="score-lbl-pro">Final Score</div>
                                </div>
                                
                                <div style="display: flex; gap: 0.75rem;">
                                    <a href="assessment-view.php?id=<?php echo $assessment['id']; ?>" class="btn-pro btn-pro-outline">
                                        <i class="fas fa-eye"></i>
                                        ดูรายละเอียด
                                    </a>
                                    
                                    <?php if ($isEditable): ?>
                                    <a href="auditor-evaluate.php?id=<?php echo $assessment['id']; ?>" class="btn-pro btn-pro-primary">
                                        <i class="fas fa-pen-to-square"></i>
                                        แก้ไขคะแนน
                                    </a>
                                    <button type="button" class="btn-pro btn-pro-withdraw" onclick="handleWithdraw(<?php echo $assessment['id']; ?>, '<?php echo htmlspecialchars($assessment['company_name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-undo"></i>
                                        ยกเลิกการส่ง
                                    </button>
                                    <?php endif; ?>
                                </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
    function handleWithdraw(assessmentId, companyName) {
        if (!confirm('ยกเลิกการส่งผลการประเมิน "' + companyName + '" หรือไม่?\n\nคะแนนจะยังคงอยู่ แต่สถานะจะกลับเป็น "รอประเมิน"')) return;

        const formData = new FormData();
        formData.append('assessment_id', assessmentId);

        fetch('<?php echo getBaseUrl(); ?>/api/withdraw-auditor-evaluation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
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
