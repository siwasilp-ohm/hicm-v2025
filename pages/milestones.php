<?php
/**
 * HICM V2025 - My Milestones Page
 * หน้าแสดงกราฟพัฒนาการ (Milestone/Checkpoint)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireRole(ROLE_COMPANY);

$user = getCurrentUser();

// Get company's assessment
$assessmentResult = getOrCreateAssessment($user['company_id']);
$noActivePeriod = !$assessmentResult['success'];

// Even without active period, load historical data for viewing
$allPeriodMilestones = getMilestonesAcrossPeriods($user['company_id'], 'self');
$pastHistory = getCompanyAssessmentHistory($user['company_id']);

if ($noActivePeriod) {
    // Group historical milestones by period for display
    $histPeriodGroups = [];
    foreach ($allPeriodMilestones as $ms) {
        $periodKey = $ms['year'] . '_' . $ms['period_id'];
        if (!isset($histPeriodGroups[$periodKey])) {
            $histPeriodGroups[$periodKey] = [
                'year' => $ms['year'],
                'period_name' => $ms['period_name'],
                'period_id' => $ms['period_id'],
                'milestones' => []
            ];
        }
        $histPeriodGroups[$periodKey]['milestones'][] = $ms;
    }
    $hasHistoricalData = !empty($allPeriodMilestones) || !empty($pastHistory);
    $hasAnalysisData = count($pastHistory) >= 2;
    
    // Level info helper
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
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Milestones - <?php echo APP_NAME; ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
        <?php if ($hasHistoricalData): ?>
        <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
        <?php endif; ?>
        <style>
            /* ── Alert Card ── */
            .np-alert {
                background: #fff; border-radius: 20px; max-width: 640px;
                margin: 0 auto 2.5rem; text-align: center; padding: 3rem 2.5rem 2.5rem;
                box-shadow: 0 12px 40px rgba(30,58,95,0.08), 0 2px 8px rgba(0,0,0,0.03);
                position: relative; overflow: hidden;
            }
            .np-alert::before {
                content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
                background: linear-gradient(90deg, #F59E0B, #EF4444, #F59E0B);
            }
            .np-icon-ring {
                width: 88px; height: 88px; border-radius: 50%;
                background: linear-gradient(135deg, #FEF3C7, #FDE68A);
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 1.5rem;
                box-shadow: 0 6px 20px rgba(245,158,11,0.16);
                animation: np-pulse 2.5s ease-in-out infinite;
            }
            @keyframes np-pulse {
                0%,100% { box-shadow: 0 6px 20px rgba(245,158,11,0.16); }
                50%     { box-shadow: 0 6px 32px rgba(245,158,11,0.30); }
            }
            .np-icon-ring svg { width: 40px; height: 40px; color: #D97706; }
            .np-title {
                font-family: 'Prompt', sans-serif; font-size: 1.35rem;
                font-weight: 700; color: #1e293b; margin: 0 0 .6rem;
            }
            .np-desc {
                font-family: 'Prompt', sans-serif; font-size: .9rem;
                color: #64748b; line-height: 1.7; margin: 0 0 1.5rem;
            }
            .np-info-box {
                background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 12px;
                padding: .85rem 1.1rem; margin-bottom: 1.5rem;
                display: flex; align-items: flex-start; gap: .65rem; text-align: left;
            }
            .np-info-box svg { width: 18px; height: 18px; color: #D97706; flex-shrink: 0; margin-top: 2px; }
            .np-info-box p {
                font-family: 'Prompt', sans-serif; font-size: .82rem;
                color: #92400E; margin: 0; line-height: 1.6;
            }
            .np-actions { display: flex; gap: .65rem; justify-content: center; flex-wrap: wrap; }
            .np-btn {
                font-family: 'Prompt', sans-serif; font-weight: 600; font-size: .85rem;
                padding: .6rem 1.3rem; border-radius: 10px; border: none; cursor: pointer;
                display: inline-flex; align-items: center; gap: .45rem;
                transition: all .2s ease; text-decoration: none;
            }
            .np-btn svg { width: 16px; height: 16px; }
            .np-btn-primary {
                background: linear-gradient(135deg, #1e3a5f, #0369a1); color: #fff;
                box-shadow: 0 4px 12px rgba(30,58,95,0.22);
            }
            .np-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(30,58,95,0.32); }
            .np-btn-ghost { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
            .np-btn-ghost:hover { background: #e2e8f0; }

            /* ── History Section ── */
            .np-history { box-sizing: border-box; }
            .np-history-header {
                display: flex; align-items: center; gap: .75rem;
                margin-bottom: 1.5rem; padding-bottom: 1rem;
                border-bottom: 2px solid #e2e8f0;
            }
            .np-history-header-icon {
                width: 40px; height: 40px; border-radius: 10px;
                background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
                display: flex; align-items: center; justify-content: center;
            }
            .np-history-header-icon svg { width: 20px; height: 20px; color: #7C3AED; }
            .np-history-title {
                font-family: 'Prompt', sans-serif; font-size: 1.1rem;
                font-weight: 700; color: #1e293b; margin: 0;
            }
            .np-history-sub {
                font-family: 'Prompt', sans-serif; font-size: .78rem;
                color: #94a3b8; margin: 2px 0 0;
            }

            /* Period cards */
            .np-period-card {
                background: #fff; border-radius: 16px; margin-bottom: 1.25rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.04); border: 1px solid #f1f5f9;
                overflow: hidden; transition: box-shadow .2s;
            }
            .np-period-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.07); }
            .np-period-head {
                display: flex; align-items: center; justify-content: space-between;
                padding: 1rem 1.25rem; cursor: pointer; user-select: none;
            }
            .np-period-head:hover { background: #f8fafc; }
            .np-period-label { display: flex; align-items: center; gap: .6rem; }
            .np-period-year {
                background: linear-gradient(135deg, #1e3a5f, #0369a1); color: #fff;
                padding: .25rem .6rem; border-radius: 6px;
                font-family: 'Prompt', sans-serif; font-weight: 700; font-size: .8rem;
            }
            .np-period-name {
                font-family: 'Prompt', sans-serif; font-weight: 600; font-size: .92rem; color: #1e293b;
            }
            .np-period-badge {
                font-family: 'Prompt', sans-serif; font-size: .75rem;
                padding: .2rem .6rem; border-radius: 6px;
                background: #f1f5f9; color: #64748b; font-weight: 500;
            }
            .np-period-chevron { transition: transform .25s ease; color: #94a3b8; }
            .np-period-chevron.open { transform: rotate(180deg); }
            .np-period-body { display: none; padding: 0 1.25rem 1.25rem; }
            .np-period-body.open { display: block; }

            /* Milestone table */
            .np-ms-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .np-ms-table {
                width: 100%; border-collapse: collapse; font-family: 'Prompt', sans-serif;
                min-width: 560px;
            }
            .np-ms-table thead th {
                font-size: .75rem; font-weight: 600; color: #64748b;
                text-align: center; padding: .5rem .4rem;
                border-bottom: 2px solid #e2e8f0; text-transform: uppercase;
            }
            .np-ms-table thead th:first-child { text-align: left; }
            .np-ms-table tbody td {
                padding: .55rem .4rem; font-size: .82rem; color: #334155;
                text-align: center; border-bottom: 1px solid #f1f5f9;
            }
            .np-ms-table tbody td:first-child { text-align: left; font-weight: 500; }
            .np-ms-table tbody tr:hover { background: #f8fafc; }
            .np-score-pill {
                display: inline-block; padding: .15rem .5rem; border-radius: 6px;
                font-weight: 600; font-size: .78rem; min-width: 42px;
            }

            /* Mini chart container */
            .np-chart-wrap {
                background: #f8fafc; border-radius: 12px; padding: 1rem;
                margin-top: 1rem; height: 200px; position: relative;
            }
            .np-empty-history {
                text-align: center; padding: 3rem 2rem;
                color: #94a3b8; font-family: 'Prompt', sans-serif;
            }
            .np-empty-history svg { width: 48px; height: 48px; margin-bottom: 1rem; opacity: .5; }
            .np-empty-history p { font-size: .9rem; margin: 0; }

            /* ── Analysis Chart Card ── */
            .np-analysis-card {
                background: #fff; border-radius: 20px; margin-top: 2rem;
                box-shadow: 0 4px 24px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;
                overflow: hidden;
            }
            .np-analysis-header {
                background: linear-gradient(135deg, #1e3a5f, #0369a1);
                padding: 1.25rem 1.5rem; color: #fff;
                display: flex; align-items: center; gap: .75rem;
            }
            .np-analysis-header-icon {
                width: 40px; height: 40px; border-radius: 10px;
                background: rgba(255,255,255,.15);
                display: flex; align-items: center; justify-content: center;
            }
            .np-analysis-header-icon svg { width: 20px; height: 20px; color: #fff; }
            .np-analysis-header-title {
                font-family: 'Prompt', sans-serif; font-size: 1.05rem;
                font-weight: 700; margin: 0;
            }
            .np-analysis-header-sub {
                font-family: 'Prompt', sans-serif; font-size: .75rem;
                opacity: .8; margin: 2px 0 0;
            }
            .np-analysis-body { padding: 1.5rem; }

            /* Stats row */
            .np-stats-row {
                display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem;
                margin-bottom: 1.5rem;
            }
            .np-stat-card {
                background: #f8fafc; border-radius: 14px; padding: 1rem;
                text-align: center; border: 1px solid #f1f5f9;
                transition: all .2s;
            }
            .np-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
            .np-stat-icon {
                width: 36px; height: 36px; border-radius: 10px;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto .5rem; font-size: 1.1rem;
            }
            .np-stat-value {
                font-family: 'Prompt', sans-serif; font-size: 1.4rem;
                font-weight: 800; line-height: 1;
            }
            .np-stat-label {
                font-family: 'Prompt', sans-serif; font-size: .7rem;
                color: #94a3b8; margin-top: .25rem; font-weight: 500;
            }

            /* Chart containers */
            .np-charts-grid {
                display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
                margin-bottom: 1.5rem;
            }
            .np-chart-card {
                background: #f8fafc; border-radius: 14px; padding: 1rem;
                border: 1px solid #f1f5f9;
            }
            .np-chart-card-title {
                font-family: 'Prompt', sans-serif; font-size: .82rem;
                font-weight: 600; color: #475569; margin-bottom: .75rem;
                display: flex; align-items: center; gap: .4rem;
            }
            .np-chart-card-body { height: 220px; position: relative; }

            /* History timeline */
            .np-history-timeline { margin-top: 1.25rem; }
            .np-timeline-item {
                display: flex; align-items: center; gap: 1rem;
                padding: .75rem 1rem; border-radius: 12px;
                transition: all .2s; margin-bottom: .25rem;
            }
            .np-timeline-item:hover { background: #f8fafc; }
            .np-timeline-dot {
                width: 10px; height: 10px; border-radius: 50%;
                flex-shrink: 0; border: 2px solid;
            }
            .np-timeline-info { flex: 1; min-width: 0; }
            .np-timeline-name {
                font-family: 'Prompt', sans-serif; font-size: .85rem;
                font-weight: 600; color: #1e293b;
            }
            .np-timeline-year {
                font-family: 'Prompt', sans-serif; font-size: .72rem;
                color: #94a3b8;
            }
            .np-timeline-score {
                font-family: 'Prompt', sans-serif; font-weight: 700;
                font-size: .95rem; min-width: 60px; text-align: right;
            }
            .np-timeline-level {
                display: inline-flex; align-items: center; gap: .25rem;
                padding: .2rem .6rem; border-radius: 8px;
                font-family: 'Prompt', sans-serif; font-size: .7rem;
                font-weight: 600;
            }
            .np-timeline-change {
                font-family: 'Prompt', sans-serif; font-size: .72rem;
                font-weight: 600; min-width: 50px; text-align: right;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .np-alert { padding: 2rem 1.25rem 1.5rem; }
                .np-period-head { padding: .85rem 1rem; flex-wrap: wrap; gap: .5rem; }
                .np-period-body { padding: 0 .75rem 1rem; }
                .np-history-header { flex-wrap: wrap; }
                .np-stats-row { grid-template-columns: repeat(2, 1fr); }
                .np-charts-grid { grid-template-columns: 1fr; }
            }

        </style>
    </head>
    <body class="has-sidebar">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="sidebar-overlay"></div>

        <main class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">My Milestones</h1>
                <p class="page-subtitle">ระบบติดตามความก้าวหน้าการประเมิน</p>
            </div>

                <!-- ═══ Alert Card ═══ -->
                <div class="np-alert">
                    <div class="np-icon-ring">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <h1 class="np-title">ไม่พบรอบการประเมินที่เปิดอยู่</h1>
                    <p class="np-desc">
                        ขณะนี้ยังไม่มีรอบการประเมินที่เปิดรับข้อมูล<br>
                        กรุณารอการประกาศรอบใหม่จากผู้ดูแลระบบ
                    </p>
                    <div class="np-info-box">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p>
                            <?php if ($hasHistoricalData): ?>
                                ท่านสามารถดูข้อมูล Milestone จากรอบการประเมินที่ผ่านมาได้ด้านล่าง
                            <?php else: ?>
                                เมื่อผู้ดูแลระบบเปิดรอบการประเมินใหม่ ท่านจะสามารถสร้าง Milestone ได้ทันที
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="np-actions">
                        <a href="<?php echo getBaseUrl(); ?>/pages/dashboard.php" class="np-btn np-btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                            กลับหน้าแดชบอร์ด
                        </a>
                        <a href="<?php echo getBaseUrl(); ?>/pages/my-assessments.php" class="np-btn np-btn-ghost">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V19.5a2.25 2.25 0 0 0 2.25 2.25h.75" /></svg>
                            ดูประวัติการประเมิน
                        </a>
                    </div>
                </div>

                <?php if ($hasHistoricalData): ?>
                <!-- ═══ Historical Milestones ═══ -->
                <div class="np-history">
                    <div class="np-history-header">
                        <div class="np-history-header-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        </div>
                        <div>
                            <h2 class="np-history-title">ข้อมูล Milestone จากรอบที่ผ่านมา</h2>
                            <p class="np-history-sub">รวม <?php echo count($histPeriodGroups); ?> รอบ &middot; <?php echo count($allPeriodMilestones); ?> checkpoint</p>
                        </div>
                    </div>

                    <?php if (!empty($histPeriodGroups)): ?>
                        <?php $idx = 0; foreach ($histPeriodGroups as $key => $group): $idx++; ?>
                        <div class="np-period-card">
                            <div class="np-period-head" onclick="togglePeriod('period-<?php echo $idx; ?>')">
                                <div class="np-period-label">
                                    <span class="np-period-year"><?php echo htmlspecialchars($group['year']); ?></span>
                                    <span class="np-period-name"><?php echo htmlspecialchars($group['period_name']); ?></span>
                                    <span class="np-period-badge"><?php echo count($group['milestones']); ?> checkpoint</span>
                                </div>
                                <svg class="np-period-chevron" id="chevron-<?php echo $idx; ?>" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                            </div>
                            <div class="np-period-body" id="period-<?php echo $idx; ?>">
                              <div class="np-ms-table-wrap">
                                <table class="np-ms-table">
                                    <thead>
                                        <tr>
                                            <th>Checkpoint</th>
                                            <th>รวม</th>
                                            <th style="color:#10B981;">H1</th>
                                            <th style="color:#3B82F6;">I2</th>
                                            <th style="color:#F59E0B;">C3</th>
                                            <th style="color:#8B5CF6;">M4</th>
                                            <th>วันที่บันทึก</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['milestones'] as $ms): ?>
                                        <tr>
                                            <td>CP#<?php echo $ms['version']; ?></td>
                                            <td>
                                                <span class="np-score-pill" style="background:#EEF2FF;color:#4338CA;">
                                                    <?php echo number_format($ms['total_score'], 1); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($ms['h1_score'], 1); ?></td>
                                            <td><?php echo number_format($ms['i2_score'], 1); ?></td>
                                            <td><?php echo number_format($ms['c3_score'], 1); ?></td>
                                            <td><?php echo number_format($ms['m4_score'], 1); ?></td>
                                            <td style="font-size:.78rem;color:#94a3b8;">
                                                <?php echo date('d/m/Y H:i', strtotime($ms['saved_at'])); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                              </div>

                                <?php if (count($group['milestones']) >= 2): ?>
                                <div class="np-chart-wrap">
                                    <canvas id="chart-<?php echo $idx; ?>"></canvas>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    <?php elseif (!empty($pastHistory)): ?>
                        <!-- Has assessments but no milestones -->
                        <div class="np-empty-history">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                            <p>พบประวัติการประเมิน <?php echo count($pastHistory); ?> รอบ แต่ยังไม่มีข้อมูล Milestone</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($pastHistory) && count($pastHistory) >= 2): ?>
                <!-- ═══ Assessment Analysis Chart Card ═══ -->
                <?php
                // Prepare analysis data
                $analysisLabels = [];
                $analysisScores = [];
                $analysisLevels = [];
                $maxScore = 0;
                $minScore = PHP_INT_MAX;
                $totalSum = 0;
                $bestPeriod = null;
                $latestPeriod = null;
                $firstPeriod = null;

                foreach ($pastHistory as $i => $h) {
                    $score = $h['self_total_score'] ?? $h['final_score'] ?? 0;
                    $analysisLabels[] = $h['period_name'] . ' (' . $h['year'] . ')';
                    $analysisScores[] = $score;
                    $analysisLevels[] = $h['hicm_level'] ?? 1;
                    $totalSum += $score;
                    if ($score > $maxScore) { $maxScore = $score; $bestPeriod = $h; }
                    if ($score < $minScore) { $minScore = $score; }
                    if ($i === 0) $firstPeriod = $h;
                    $latestPeriod = $h;
                }
                $avgScore = count($pastHistory) > 0 ? round($totalSum / count($pastHistory)) : 0;
                $firstScore = $firstPeriod ? ($firstPeriod['self_total_score'] ?? $firstPeriod['final_score'] ?? 0) : 0;
                $latestScore = $latestPeriod ? ($latestPeriod['self_total_score'] ?? $latestPeriod['final_score'] ?? 0) : 0;
                $growthScore = $latestScore - $firstScore;
                $growthPercent = $firstScore > 0 ? round(($growthScore / $firstScore) * 100, 1) : 0;
                $latestLevel = getLevelInfo($latestPeriod['hicm_level'] ?? 1);
                ?>
                <div class="np-analysis-card">
                    <div class="np-analysis-header">
                        <div class="np-analysis-header-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" /></svg>
                        </div>
                        <div>
                            <h2 class="np-analysis-header-title">ประวัติและวิเคราะห์การประเมิน</h2>
                            <p class="np-analysis-header-sub">สรุปผลการประเมิน <?php echo count($pastHistory); ?> รอบ</p>
                        </div>
                    </div>
                    <div class="np-analysis-body">
                        <!-- Stats Summary -->
                        <div class="np-stats-row">
                            <div class="np-stat-card">
                                <div class="np-stat-icon" style="background: linear-gradient(135deg, #EDE9FE, #DDD6FE);">📊</div>
                                <div class="np-stat-value" style="color: #7C3AED;"><?php echo count($pastHistory); ?></div>
                                <div class="np-stat-label">รอบที่ประเมิน</div>
                            </div>
                            <div class="np-stat-card">
                                <div class="np-stat-icon" style="background: linear-gradient(135deg, #DBEAFE, #BFDBFE);">🏆</div>
                                <div class="np-stat-value" style="color: #2563EB;"><?php echo number_format($maxScore); ?></div>
                                <div class="np-stat-label">คะแนนสูงสุด</div>
                            </div>
                            <div class="np-stat-card">
                                <div class="np-stat-icon" style="background: linear-gradient(135deg, #FEF3C7, #FDE68A);">📈</div>
                                <div class="np-stat-value" style="color: #D97706;"><?php echo number_format($avgScore); ?></div>
                                <div class="np-stat-label">คะแนนเฉลี่ย</div>
                            </div>
                            <div class="np-stat-card">
                                <div class="np-stat-icon" style="background: linear-gradient(135deg, <?php echo $growthScore >= 0 ? '#D1FAE5' : '#FEE2E2'; ?>, <?php echo $growthScore >= 0 ? '#A7F3D0' : '#FECACA'; ?>);">
                                    <?php echo $growthScore >= 0 ? '🚀' : '📉'; ?>
                                </div>
                                <div class="np-stat-value" style="color: <?php echo $growthScore >= 0 ? '#059669' : '#DC2626'; ?>;">
                                    <?php echo ($growthScore >= 0 ? '+' : '') . number_format($growthScore); ?>
                                </div>
                                <div class="np-stat-label">พัฒนาการ (<?php echo ($growthPercent >= 0 ? '+' : '') . $growthPercent; ?>%)</div>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="np-charts-grid">
                            <!-- Score Trend Line Chart -->
                            <div class="np-chart-card">
                                <div class="np-chart-card-title">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                    แนวโน้มคะแนน
                                </div>
                                <div class="np-chart-card-body">
                                    <canvas id="analysisScoreTrend"></canvas>
                                </div>
                            </div>
                            <!-- Level Bar Chart -->
                            <div class="np-chart-card">
                                <div class="np-chart-card-title">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2"><path d="M3 3v18h18"/><rect x="7" y="10" width="3" height="8" rx="1"/><rect x="14" y="6" width="3" height="12" rx="1"/></svg>
                                    ระดับ HICM แต่ละรอบ
                                </div>
                                <div class="np-chart-card-body">
                                    <canvas id="analysisLevelBar"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- History Timeline -->
                        <div class="np-history-timeline">
                            <div style="font-family: 'Prompt', sans-serif; font-size: .82rem; font-weight: 600; color: #475569; margin-bottom: .75rem; display: flex; align-items: center; gap: .4rem;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                ลำดับเวลาการประเมิน
                            </div>
                            <?php foreach (array_reverse($pastHistory) as $i => $h):
                                $hScore = $h['self_total_score'] ?? $h['final_score'] ?? 0;
                                $hLevel = $h['hicm_level'] ?? 1;
                                $hLevelInfo = getLevelInfo($hLevel);
                                // Calculate change from previous
                                $prevIdx = count($pastHistory) - 1 - $i - 1; // index in original (non-reversed) array
                                $change = null;
                                if ($prevIdx >= 0 && $prevIdx < count($pastHistory)) {
                                    $prevScore = $pastHistory[$prevIdx]['self_total_score'] ?? $pastHistory[$prevIdx]['final_score'] ?? 0;
                                    $change = $hScore - $prevScore;
                                }
                            ?>
                            <div class="np-timeline-item">
                                <div class="np-timeline-dot" style="background: <?php echo $hLevelInfo['bg']; ?>; border-color: <?php echo $hLevelInfo['color']; ?>;"></div>
                                <div class="np-timeline-info">
                                    <div class="np-timeline-name"><?php echo htmlspecialchars($h['period_name']); ?></div>
                                    <div class="np-timeline-year">ปี <?php echo $h['year']; ?></div>
                                </div>
                                <div class="np-timeline-score" style="color: <?php echo $hLevelInfo['color']; ?>;">
                                    <?php echo number_format($hScore); ?>
                                    <span style="font-size: .7rem; color: #94a3b8; font-weight: 400;">/1,000</span>
                                </div>
                                <span class="np-timeline-level" style="background: <?php echo $hLevelInfo['bg']; ?>; color: <?php echo $hLevelInfo['color']; ?>;">
                                    <?php echo $hLevelInfo['name']; ?>
                                </span>
                                <?php if ($change !== null): ?>
                                <div class="np-timeline-change" style="color: <?php echo $change >= 0 ? '#059669' : '#DC2626'; ?>;">
                                    <?php echo ($change >= 0 ? '▲ +' : '▼ ') . number_format(abs($change)); ?>
                                </div>
                                <?php else: ?>
                                <div class="np-timeline-change" style="color: #94a3b8;">— เริ่มต้น</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

        </div>
        </main>

        <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
        <?php if ($hasHistoricalData && !empty($histPeriodGroups)): ?>
        <script>
        function togglePeriod(id) {
            const body = document.getElementById(id);
            const num = id.replace('period-', '');
            const chevron = document.getElementById('chevron-' + num);
            body.classList.toggle('open');
            chevron.classList.toggle('open');
        }

        // Auto-expand first period
        document.addEventListener('DOMContentLoaded', function() {
            togglePeriod('period-1');
        });

        // Render mini charts for periods with 2+ milestones
        <?php $idx2 = 0; foreach ($histPeriodGroups as $key => $group): $idx2++;
            if (count($group['milestones']) >= 2):
                $labels = []; $totals = []; $h1 = []; $i2 = []; $c3 = []; $m4 = [];
                foreach ($group['milestones'] as $m) {
                    $labels[] = 'CP#' . $m['version'];
                    $totals[] = $m['total_score'];
                    $h1[] = $m['h1_score'];
                    $i2[] = $m['i2_score'];
                    $c3[] = $m['c3_score'];
                    $m4[] = $m['m4_score'];
                }
        ?>
        (function(){
            var ctx = document.getElementById('chart-<?php echo $idx2; ?>');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [
                        { label: 'รวม', data: <?php echo json_encode($totals); ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,.08)', borderWidth: 2.5, tension: .35, fill: true, pointRadius: 4 },
                        { label: 'H1', data: <?php echo json_encode($h1); ?>, borderColor: '#10B981', borderWidth: 1.5, tension: .35, pointRadius: 3, borderDash: [4,2] },
                        { label: 'I2', data: <?php echo json_encode($i2); ?>, borderColor: '#3B82F6', borderWidth: 1.5, tension: .35, pointRadius: 3, borderDash: [4,2] },
                        { label: 'C3', data: <?php echo json_encode($c3); ?>, borderColor: '#F59E0B', borderWidth: 1.5, tension: .35, pointRadius: 3, borderDash: [4,2] },
                        { label: 'M4', data: <?php echo json_encode($m4); ?>, borderColor: '#8B5CF6', borderWidth: 1.5, tension: .35, pointRadius: 3, borderDash: [4,2] }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 16, font: { size: 11 } } } },
                    scales: { y: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
                }
            });
        })();
        <?php endif; endforeach; ?>
        </script>
        <?php endif; ?>

        <?php if (!empty($pastHistory) && count($pastHistory) >= 2): ?>
        <script>
        // ═══ Assessment Analysis Charts ═══
        document.addEventListener('DOMContentLoaded', function() {
            var analysisLabels = <?php echo json_encode(array_map(function($h) { return $h['period_name']; }, $pastHistory)); ?>;
            var analysisScores = <?php echo json_encode(array_map(function($h) { return $h['self_total_score'] ?? $h['final_score'] ?? 0; }, $pastHistory)); ?>;
            var analysisLevels = <?php echo json_encode(array_map(function($h) { return $h['hicm_level'] ?? 1; }, $pastHistory)); ?>;
            var levelColors = ['#EF4444', '#F59E0B', '#3B82F6', '#8B5CF6', '#10B981'];
            var levelBgs = ['rgba(239,68,68,.15)', 'rgba(245,158,11,.15)', 'rgba(59,130,246,.15)', 'rgba(139,92,246,.15)', 'rgba(16,185,129,.15)'];
            var levelNames = ['เริ่มต้น', 'กำลังพัฒนา', 'พัฒนาดี', 'เป็นเลิศ', 'ระดับโลก'];

            // Score Trend Chart
            var ctx1 = document.getElementById('analysisScoreTrend');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: analysisLabels,
                        datasets: [{
                            label: 'คะแนนรวม',
                            data: analysisScores,
                            borderColor: '#6366f1',
                            backgroundColor: function(context) {
                                var chart = context.chart;
                                var ctx = chart.ctx, area = chart.chartArea;
                                if (!area) return 'rgba(99,102,241,.1)';
                                var gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
                                gradient.addColorStop(0, 'rgba(99,102,241,.25)');
                                gradient.addColorStop(1, 'rgba(99,102,241,.02)');
                                return gradient;
                            },
                            borderWidth: 3,
                            tension: .4,
                            fill: true,
                            pointRadius: 5,
                            pointBackgroundColor: '#fff',
                            pointBorderColor: '#6366f1',
                            pointBorderWidth: 2.5,
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: '#6366f1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                titleFont: { size: 12, weight: '600' },
                                bodyFont: { size: 11 },
                                padding: 10,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(ctx) { return 'คะแนน: ' + ctx.parsed.y.toLocaleString() + ' / 1,000'; }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 1000,
                                grid: { color: '#f1f5f9', drawBorder: false },
                                ticks: { font: { size: 10 }, color: '#94a3b8', stepSize: 200 }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 30 }
                            }
                        }
                    }
                });
            }

            // Level Bar Chart
            var ctx2 = document.getElementById('analysisLevelBar');
            if (ctx2) {
                var barColors = analysisLevels.map(function(l) { return levelColors[(l || 1) - 1]; });
                var barBgs = analysisLevels.map(function(l) { return levelBgs[(l || 1) - 1]; });
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: analysisLabels,
                        datasets: [{
                            label: 'ระดับ HICM',
                            data: analysisLevels,
                            backgroundColor: barColors.map(function(c) { return c + '33'; }),
                            borderColor: barColors,
                            borderWidth: 2,
                            borderRadius: 8,
                            borderSkipped: false,
                            maxBarThickness: 40
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                titleFont: { size: 12, weight: '600' },
                                bodyFont: { size: 11 },
                                padding: 10,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(ctx) {
                                        var lvl = ctx.parsed.y;
                                        return 'ระดับ ' + lvl + ' — ' + levelNames[(lvl || 1) - 1];
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 5,
                                grid: { color: '#f1f5f9', drawBorder: false },
                                ticks: {
                                    font: { size: 10 }, color: '#94a3b8', stepSize: 1,
                                    callback: function(val) { return levelNames[val - 1] || ''; }
                                }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 30 }
                            }
                        }
                    }
                });
            }
        });
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

$assessment = getAssessmentWithScores($assessmentResult['assessment']['id']);

// Get milestones for current period
$milestones = getMilestones($assessment['id'], 'self');

// Get milestones across all periods for comparison
$allPeriodMilestones = getMilestonesAcrossPeriods($user['company_id'], 'self');

// Group milestones by period
$periodGroups = [];
foreach ($allPeriodMilestones as $ms) {
    $periodKey = $ms['year'] . '_' . $ms['period_id'];
    if (!isset($periodGroups[$periodKey])) {
        $periodGroups[$periodKey] = [
            'year' => $ms['year'],
            'period_name' => $ms['period_name'],
            'milestones' => []
        ];
    }
    $periodGroups[$periodKey]['milestones'][] = $ms;
}

// Prepare chart data
$chartLabels = [];
$chartTotalScores = [];
$chartH1Scores = [];
$chartI2Scores = [];
$chartC3Scores = [];
$chartM4Scores = [];

foreach ($milestones as $ms) {
    $chartLabels[] = 'CP#' . $ms['version'];
    $chartTotalScores[] = $ms['total_score'];
    $chartH1Scores[] = $ms['h1_score'];
    $chartI2Scores[] = $ms['i2_score'];
    $chartC3Scores[] = $ms['c3_score'];
    $chartM4Scores[] = $ms['m4_score'];
}

// Calculate improvements
$improvement = null;
if (count($milestones) >= 2) {
    $first = $milestones[0];
    $last = end($milestones);
    $improvement = [
        'total' => $last['total_score'] - $first['total_score'],
        'h1' => $last['h1_score'] - $first['h1_score'],
        'i2' => $last['i2_score'] - $first['i2_score'],
        'c3' => $last['c3_score'] - $first['c3_score'],
        'm4' => $last['m4_score'] - $first['m4_score'],
        'first_date' => $first['saved_at'],
        'last_date' => $last['saved_at']
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Milestones - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
    <style>
        :root {
            --pro-primary: #6366f1;
            --pro-primary-light: #e0e7ff;
            --pro-secondary: #0ea5e9;
            --pro-bg: #f8fafc;
            --pro-card-bg: rgba(255, 255, 255, 0.9);
            --pro-text-main: #1e293b;
            --pro-text-muted: #64748b;
            --pro-border: #e2e8f0;
            --pro-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.05);
            --pro-shadow-hover: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
        }

        .main-wrapper {
            background-color: var(--pro-bg);
            min-height: 10vh;
        }

        /* Compact Header */
        .milestone-hero {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
            box-shadow: 0 4px 12px -2px rgba(0,0,0,0.15);
        }

        .milestone-hero::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%);
            transform: translate(30%, -30%);
        }

        .hero-icon-wrapper {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #818cf8;
            flex-shrink: 0;
        }

        .hero-content h1 {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 0.25rem;
            color: white;
        }

        .hero-subtitle {
            font-size: 0.85rem;
            color: #94a3b8;
            font-weight: 400;
        }

        /* Compact Stats Grid */
        .improvement-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1024px) {
            .improvement-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 640px) {
            .improvement-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-card {
            background: var(--pro-card-bg);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--pro-shadow-hover);
        }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--pro-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pro-text-main);
            display: flex;
            align-items: baseline;
            gap: 0.15rem;
        }

        .stat-trend {
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-neutral { color: var(--pro-text-muted); }

        /* Compact Chart Section */
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1024px) {
            .chart-grid { grid-template-columns: 1fr; }
        }

        .pro-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--pro-border);
        }

        .card-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--pro-text-main);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .canvas-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

        .canvas-container.radar-lg {
            height: 400px;
        }

        /* Compact Timeline */
        .timeline-pro {
            position: relative;
            margin-top: 1rem;
        }

        .timeline-pro::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0; bottom: 0;
            width: 2px;
            background: var(--pro-border);
        }

        .timeline-card {
            margin-left: 2.5rem;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
            position: relative;
            transition: all 0.2s;
        }

        .timeline-card:hover {
            transform: translateX(4px);
            border-color: var(--pro-primary);
        }

        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 1rem;
            width: 16px;
            height: 16px;
            background: white;
            border: 3px solid var(--pro-primary);
            border-radius: 50%;
            z-index: 2;
        }

        .timeline-card.latest {
            background: linear-gradient(to right, #fff, #f5f3ff);
            border-left: 3px solid var(--pro-primary);
        }

        .timeline-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .version-badge {
            background: var(--pro-primary-light);
            color: var(--pro-primary);
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .score-pills {
            display: flex;
            gap: 0.35rem;
            flex-wrap: wrap;
        }

        .score-pill {
            padding: 0.15rem 0.5rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .empty-visual {
            text-align: center;
            padding: 3rem 1.5rem;
            background: white;
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
        }

        /* Animations */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .reveal {
            animation: slideIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
            
            <!-- Compact Hero Section -->
            <div class="milestone-hero reveal">
                <div class="hero-icon-wrapper">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <polyline points="2 17 12 22 22 17"/>
                        <polyline points="2 12 12 17 22 12"/>
                    </svg>
                </div>
                <div class="hero-content">
                    <h1>Performance Milestones</h1>
                    <p class="hero-subtitle">
                        HICM Assessment Progress
                        <?php if (!empty($milestones)): ?>
                        • <span style="font-weight: 600; color: white;"><?php echo count($milestones); ?> Checkpoints</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <?php if (empty($milestones)): ?>
            <!-- Empty State -->
            <div class="empty-visual reveal" style="animation-delay: 0.2s;">
                <div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: #94a3b8;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <polyline points="2 17 12 22 22 17"/>
                        <polyline points="2 12 12 17 22 12"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">No Checkpoints Yet</h3>
                <p style="color: #64748b; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    Save a checkpoint from the assessment form to track your progress.
                </p>
                <a href="<?php echo getBaseUrl(); ?>/pages/assessment-form.php" class="btn btn-primary" style="border-radius: 8px; padding: 0.5rem 1.5rem; font-size: 0.85rem;">
                    Start First Checkpoint
                </a>
            </div>
            
            <?php else: ?>
            
            <!-- Compact Stats Grid -->
            <?php if ($improvement): ?>
            <div class="improvement-grid reveal" style="animation-delay: 0.1s;">
                <div class="stat-card">
                    <span class="stat-label">Overall</span>
                    <div class="stat-value">
                        <?php echo number_format(abs($improvement['total']), 1); ?>
                        <span style="font-size: 0.75rem; color: var(--pro-text-muted); font-weight: 500;">pts</span>
                    </div>
                    <div class="stat-trend <?php echo $improvement['total'] >= 0 ? 'trend-up' : 'trend-down'; ?>">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <path d="<?php echo $improvement['total'] >= 0 ? 'M7 17l9.2-9.2M17 17V7H7' : 'M7 7l9.2 9.2M7 17h10V7'; ?>"/>
                        </svg>
                        <?php echo $improvement['total'] >= 0 ? 'Up' : 'Down'; ?>
                    </div>
                </div>

                <!-- Pillar Stats -->
                <?php 
                $pillars = [
                    ['label' => 'H1', 'key' => 'h1', 'color' => '#10b981'],
                    ['label' => 'I2', 'key' => 'i2', 'color' => '#3b82f6'],
                    ['label' => 'C3', 'key' => 'c3', 'color' => '#f59e0b'],
                    ['label' => 'M4', 'key' => 'm4', 'color' => '#8b5cf6'],
                ];
                foreach ($pillars as $idx => $p): 
                    $val = $improvement[$p['key']];
                ?>
                <div class="stat-card reveal" style="animation-delay: <?php echo 0.15 + ($idx * 0.05); ?>s;">
                    <span class="stat-label"><?php echo $p['label']; ?></span>
                    <div class="stat-value" style="color: <?php echo $p['color']; ?>;">
                        <?php echo ($val > 0 ? '+' : '') . number_format($val, 1); ?>
                        <span style="font-size: 0.7rem; color: var(--pro-text-muted); font-weight: 500;">%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Compact Chart Grid -->
            <div class="chart-grid reveal" style="animation-delay: 0.2s;">
                <!-- Growth Chart -->
                <div class="pro-card">
                    <div class="card-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                            </svg>
                            Score Growth
                        </h3>
                    </div>
                    <div class="canvas-container">
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>

                <!-- Pillar Growth -->
                <div class="pro-card">
                    <div class="card-header">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 20V10M12 20V4M6 20v-6"/>
                            </svg>
                            Pillar Trends
                        </h3>
                    </div>
                    <div class="canvas-container">
                        <canvas id="pillarChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Radar Comparison -->
            <div class="pro-card reveal" style="margin-bottom: 1.5rem; animation-delay: 0.3s;">
                <div class="card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 2 7 2 17 12 22 22 17 22 7 12 2"/>
                        </svg>
                        Checkpoint Comparison
                    </h3>
                </div>
                <div class="canvas-container radar-lg">
                    <canvas id="radarCompareChart"></canvas>
                </div>
            </div>

            <!-- Compact Timeline Section -->
            <div class="pro-card reveal" style="animation-delay: 0.4s;">
                <div class="card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        Timeline
                    </h3>
                </div>
                
                <div class="timeline-pro">
                    <?php foreach (array_reverse($milestones) as $idx => $ms): ?>
                    <div class="timeline-card <?php echo $idx === 0 ? 'latest' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-info">
                            <div>
                                <span class="version-badge">v<?php echo $ms['version']; ?></span>
                                <h4 style="margin: 0.35rem 0 0.15rem; font-weight: 600; font-size: 0.85rem; color: var(--pro-text-main);">
                                    <?php echo date('d M Y, H:i', strtotime($ms['saved_at'])); ?>
                                </h4>
                                <p style="font-size: 0.75rem; color: var(--pro-text-muted); margin: 0;">
                                    by <?php echo htmlspecialchars($ms['saved_by_name']); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.25rem; font-weight: 700; color: var(--pro-primary);">
                                    <?php echo number_format($ms['total_score'], 1); ?>
                                </div>
                                <div style="font-size: 0.65rem; color: var(--pro-text-muted); font-weight: 600;">SCORE</div>
                            </div>
                        </div>

                        <div class="score-pills">
                            <span class="score-pill">H1: <?php echo number_format($ms['h1_score'], 1); ?>%</span>
                            <span class="score-pill">I2: <?php echo number_format($ms['i2_score'], 1); ?>%</span>
                            <span class="score-pill">C3: <?php echo number_format($ms['c3_score'], 1); ?>%</span>
                            <span class="score-pill">M4: <?php echo number_format($ms['m4_score'], 1); ?>%</span>
                            <span class="score-pill" style="background: #f8fafc; border-style: dashed;">
                                <?php echo $ms['answered_count']; ?>/60
                            </span>
                        </div>

                        <?php if (!empty($ms['note'])): ?>
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #f1f5f9; font-size: 0.8rem; color: #475569; font-style: italic;">
                            💬 <?php echo htmlspecialchars($ms['note']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (count($periodGroups) > 1): ?>
            <div class="pro-card reveal" style="margin-top: 1.5rem; animation-delay: 0.5s;">
                <div class="card-header">
                    <h3>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Historical Comparison
                    </h3>
                </div>
                <div class="canvas-container">
                    <canvas id="periodCompareChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </main>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    
    <?php if (!empty($milestones)): ?>
    <script>
    // Global Chart.js Defaults
    Chart.defaults.font.family = "'Prompt', sans-serif";
    Chart.defaults.color = '#6b7280';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.borderRadius = 8;
    
    // Chart.js configuration
    const chartLabels = <?php echo json_encode($chartLabels); ?>;
    const totalScores = <?php echo json_encode($chartTotalScores); ?>;
    const h1Scores = <?php echo json_encode($chartH1Scores); ?>;
    const i2Scores = <?php echo json_encode($chartI2Scores); ?>;
    const c3Scores = <?php echo json_encode($chartC3Scores); ?>;
    const m4Scores = <?php echo json_encode($chartM4Scores); ?>;
    
    const proColors = {
        primary: '#6366f1',
        success: '#10b981',
        info: '#3b82f6',
        warning: '#f59e0b',
        danger: '#ef4444',
        purple: '#8b5cf6',
        grey: '#94a3b8'
    };

    // Progress Line Chart
    new Chart(document.getElementById('progressChart'), {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Total Score',
                data: totalScores,
                borderColor: proColors.warning,
                backgroundColor: 'rgba(245, 158, 11, 0.05)',
                borderWidth: 4,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBackgroundColor: proColors.warning,
                pointBorderColor: '#fff',
                pointBorderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: { padding: 10 }
                },
                x: {
                    grid: { display: false },
                    ticks: { padding: 10 }
                }
            }
        }
    });
    
    // Pillar Progress Chart
    new Chart(document.getElementById('pillarChart'), {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [
                { label: 'Health', data: h1Scores, borderColor: proColors.success },
                { label: 'Safety', data: i2Scores, borderColor: proColors.info },
                { label: 'Community', data: c3Scores, borderColor: proColors.warning },
                { label: 'Management', data: m4Scores, borderColor: proColors.purple }
            ].map(ds => ({
                ...ds,
                borderWidth: 3,
                tension: 0.4,
                pointRadius: 4,
                backgroundColor: ds.borderColor,
                fill: false
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, usePointStyle: true, pointStyle: 'circle', padding: 25 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: '#f1f5f9' },
                    ticks: { callback: v => v + '%' }
                },
                x: { grid: { display: false } }
            }
        }
    });
    
    // Radar Comparison Chart
    <?php if (count($milestones) >= 1): ?>
    const radarColors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#0ea5e9', '#ec4899', '#14b8a6'];

    new Chart(document.getElementById('radarCompareChart'), {
        type: 'radar',
        data: {
            labels: ['Health & Well-being', 'Safety & Environment', 'Community & Society', 'Management System'],
            datasets: [
                <?php foreach ($milestones as $index => $ms): 
                    $isLatest = ($index === count($milestones) - 1);
                ?>
                {
                    label: 'Checkpoint #<?php echo $ms['version']; ?><?php echo $isLatest ? ' (Latest)' : ''; ?>',
                    data: [<?php echo $ms['h1_score']; ?>, <?php echo $ms['i2_score']; ?>, <?php echo $ms['c3_score']; ?>, <?php echo $ms['m4_score']; ?>],
                    borderColor: <?php echo $isLatest ? 'proColors.primary' : 'radarColors[' . ($index % 8) . ']' ?>,
                    backgroundColor: <?php echo $isLatest ? '"rgba(99, 102, 241, 0.1)"' : '"rgba(0,0,0,0)"' ?>,
                    borderWidth: <?php echo $isLatest ? 4 : 2 ?>,
                    pointRadius: <?php echo $isLatest ? 5 : 3 ?>,
                    pointBackgroundColor: <?php echo $isLatest ? 'proColors.primary' : 'radarColors[' . ($index % 8) . ']' ?>,
                    pointBorderColor: '#fff',
                    pointBorderWidth: <?php echo $isLatest ? 2 : 1 ?>
                },
                <?php endforeach; ?>
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: 40 },
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: { stepSize: 20, backdropColor: 'transparent', font: { size: 10 } },
                    grid: { color: '#e2e8f0' },
                    angleLines: { color: '#e2e8f0' },
                    pointLabels: {
                        font: { size: 14, weight: '600' },
                        color: '#475569'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 20, font: { size: 11, weight: '600' }, usePointStyle: true }
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.9)',
                    padding: 12,
                    borderRadius: 8
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (count($periodGroups) > 1): ?>
    // Period Comparison Chart
    const periodData = <?php echo json_encode(array_values($periodGroups)); ?>;
    const periodLabels = periodData.map(p => p.period_name + ' (' + p.year + ')');
    const datasets = [
        { label: 'Health', key: 'h1_score', color: proColors.success },
        { label: 'Safety', key: 'i2_score', color: proColors.info },
        { label: 'Community', key: 'c3_score', color: proColors.warning },
        { label: 'Management', key: 'm4_score', color: proColors.purple }
    ].map(p => ({
        label: p.label,
        backgroundColor: p.color,
        borderRadius: 6,
        data: periodData.map(group => group.milestones[group.milestones.length - 1][p.key])
    }));

    new Chart(document.getElementById('periodCompareChart'), {
        type: 'bar',
        data: { labels: periodLabels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 25 } }
            },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            }
        }
    });
    <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
