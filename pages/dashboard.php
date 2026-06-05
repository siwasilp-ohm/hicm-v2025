<?php
/**
 * HICM V2025 Assessment System - Dashboard Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);
$isCompany = hasRole(ROLE_COMPANY);
$isCEO = hasRole('ceo');

// Get statistics based on role
$stats = [];
$recentAssessments = [];
$scoreDistribution = [];

if ($isAdmin || $isAuditor || $isCEO) {
    // Admin/Auditor view
    $stats = getAssessmentStatistics();
    $scoreDistribution = getScoreDistribution();
    $recentAssessments = getAllAssessments(['limit' => 10]);
} elseif ($isCompany && $user['company_id']) {
    // Company view
    $assessmentResult = getOrCreateAssessment($user['company_id']);
    if ($assessmentResult['success']) {
        $assessment = getAssessmentWithScores($assessmentResult['assessment']['id']);
    }
    
    // Get company details for profile card
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            c.*,
            COALESCE(c.contact_name, u.name) AS contact_name,
            COALESCE(c.contact_email, u.email) AS contact_email,
            COALESCE(c.contact_phone, u.phone) AS contact_phone,
            u.avatar AS company_owner_avatar
        FROM companies c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$user['company_id']]);
    $companyInfo = $stmt->fetch();
    
    // Get previous assessment history for this company
    $stmt = $db->prepare("
        SELECT 
            a.id,
            a.status,
            a.self_total_score,
            a.auditor_total_score,
            a.final_score,
            a.hicm_level,
            a.submitted_at,
            a.evaluated_at,
            a.completed_at,
            a.created_at,
            p.id as period_id,
            p.name as period_name,
            p.year as period_year,
            p.start_date,
            p.end_date
        FROM assessments a
        JOIN assessment_periods p ON a.period_id = p.id
        WHERE a.company_id = ?
        ORDER BY p.year DESC, p.start_date DESC
    ");
    $stmt->execute([$user['company_id']]);
    $assessmentHistory = $stmt->fetchAll();
    
    // If no current assessment, fetch upcoming/scheduled periods
    if (!isset($assessment)) {
        $stmtUpcoming = $db->prepare("
            SELECT id, name, year, description, start_date, end_date, 
                   submission_deadline, evaluation_start_date, evaluation_end_date, 
                   announcement_date, status
            FROM assessment_periods 
            WHERE is_active = 1 
              AND (status = 'draft' OR (status = 'open' AND start_date > CURDATE()))
            ORDER BY start_date ASC
        ");
        $stmtUpcoming->execute();
        $upcomingPeriods = $stmtUpcoming->fetchAll();
    }
}

// ============================================
// UNIVERSAL: Check for announced results (all roles)
// ============================================
$announcedPeriod = null;
$announcedLeaderboard = [];
$announcedMyRank = null;
$announcedMyData = null;
$announcedTotalCompanies = 0;

$dbAnn = getDB();
$stmtAnn = $dbAnn->prepare("
    SELECT id, name, year, results_announced, announcement_date, show_leaderboard, leaderboard_top_n
    FROM assessment_periods 
    WHERE results_announced = 1 AND is_active = 1
    ORDER BY year DESC, start_date DESC 
    LIMIT 1
");
$stmtAnn->execute();
$announcedPeriod = $stmtAnn->fetch();

if ($announcedPeriod) {
    // Fetch full leaderboard (all companies with scores)
    $stmtLb = $dbAnn->prepare("
        SELECT 
            c.id as company_id,
            c.company_name,
            c.industry_type,
            a.final_score,
            a.hicm_level,
            a.self_total_score,
            a.auditor_total_score,
            a.status as assessment_status
        FROM assessments a
        JOIN companies c ON a.company_id = c.id
        WHERE a.period_id = ? AND a.status IN ('evaluated', 'completed') AND a.final_score > 0
        ORDER BY a.final_score DESC, a.hicm_level DESC
    ");
    $stmtLb->execute([$announcedPeriod['id']]);
    $allLbData = $stmtLb->fetchAll();
    $announcedTotalCompanies = count($allLbData);
    
    // Find current user's company rank
    if ($isCompany && $user['company_id']) {
        foreach ($allLbData as $index => $item) {
            if ($item['company_id'] == $user['company_id']) {
                $announcedMyRank = $index + 1;
                $announcedMyData = $item;
                break;
            }
        }
    }
    
    // Top N for leaderboard display
    $topNAnn = $announcedPeriod['leaderboard_top_n'] ?? 10;
    $announcedLeaderboard = array_slice($allLbData, 0, $topNAnn);
}

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

// Helper function to adjust color brightness
function adjustBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    
    // Convert hex to RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Adjust brightness
    $r = max(0, min(255, $r + ($percent * 255 / 100)));
    $g = max(0, min(255, $g + ($percent * 255 / 100)));
    $b = max(0, min(255, $b + ($percent * 255 / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>
            
            <div class="page-header">
                <h1 class="page-title">แดชบอร์ด</h1>
                <p class="page-subtitle">
                    <?php if ($isAdmin): ?>
                        ภาพรวมระบบการประเมิน HICM V2025
                    <?php elseif ($isAuditor): ?>
                        ระบบตรวจประเมินสถานประกอบการ
                    <?php else: ?>
                        สรุปผลการประเมินของสถานประกอบการ
                    <?php endif; ?>
                </p>
            </div>
            
            <?php // ===== UNIVERSAL ANNOUNCEMENT LEADERBOARD (all roles) ===== ?>
            <?php if ($announcedPeriod && !empty($announcedLeaderboard)): ?>
            <style>
                /* ============================================
                   ANNOUNCEMENT LEADERBOARD - Pro Design
                   ============================================ */
                .ann-lb-card {
                    background: white;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 8px 24px rgba(139,92,246,0.08);
                    border: 2px solid #c4b5fd;
                    margin-bottom: 2rem;
                    animation: annLbFadeIn 0.6s ease;
                }

                @keyframes annLbFadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .ann-lb-header {
                    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 50%, #5b21b6 100%);
                    color: white;
                    padding: 1.25rem 1.5rem;
                    position: relative;
                    overflow: hidden;
                }

                .ann-lb-header::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -10%;
                    width: 200px;
                    height: 200px;
                    background: rgba(255,255,255,0.06);
                    border-radius: 50%;
                }

                .ann-lb-header-top {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    position: relative;
                    z-index: 1;
                }

                .ann-lb-header-left {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }

                .ann-lb-trophy {
                    font-size: 2rem;
                    line-height: 1;
                }

                .ann-lb-header h3 {
                    margin: 0;
                    font-size: 1.15rem;
                    font-weight: 700;
                }

                .ann-lb-header p {
                    margin: 0.15rem 0 0;
                    font-size: 0.78rem;
                    opacity: 0.85;
                }

                .ann-lb-my-rank {
                    background: rgba(255,255,255,0.18);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.25);
                    padding: 0.5rem 1.25rem;
                    border-radius: 14px;
                    text-align: center;
                }

                .ann-lb-my-rank-label {
                    font-size: 0.6rem;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    opacity: 0.9;
                    font-weight: 600;
                }

                .ann-lb-my-rank-val {
                    font-size: 1.5rem;
                    font-weight: 800;
                    line-height: 1.2;
                }

                .ann-lb-my-rank-score {
                    font-size: 0.65rem;
                    opacity: 0.85;
                }

                .ann-lb-body {
                    padding: 0;
                }

                .ann-lb-list {
                    max-height: 380px;
                    overflow-y: auto;
                }

                .ann-lb-item {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    padding: 0.65rem 1.25rem;
                    border-bottom: 1px solid #f1f5f9;
                    transition: background 0.15s;
                }

                .ann-lb-item:last-child { border-bottom: none; }
                .ann-lb-item:hover { background: #faf5ff; }

                .ann-lb-item.my-company {
                    background: linear-gradient(135deg, #ede9fe, #ddd6fe) !important;
                    border-left: 4px solid #7c3aed;
                }

                .ann-lb-item.rank-gold { background: linear-gradient(135deg, #fefce8, #fef9c3); }
                .ann-lb-item.rank-silver { background: linear-gradient(135deg, #f8fafc, #f1f5f9); }
                .ann-lb-item.rank-bronze { background: linear-gradient(135deg, #fff7ed, #ffedd5); }

                .ann-lb-rank {
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 1.2rem;
                    font-weight: 800;
                    color: #64748b;
                    flex-shrink: 0;
                }

                .ann-lb-rank.has-medal { font-size: 1.35rem; }

                .ann-lb-company {
                    flex: 1;
                    min-width: 0;
                }

                .ann-lb-company-name {
                    font-weight: 600;
                    font-size: 0.88rem;
                    color: #1e293b;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .ann-lb-company-type {
                    font-size: 0.72rem;
                    color: #94a3b8;
                }

                .ann-lb-score-col {
                    text-align: right;
                    flex-shrink: 0;
                }

                .ann-lb-score-val {
                    font-size: 1.1rem;
                    font-weight: 800;
                    color: #7c3aed;
                    line-height: 1.2;
                }

                .ann-lb-level-badge {
                    display: inline-block;
                    font-size: 0.6rem;
                    font-weight: 700;
                    padding: 0.1rem 0.45rem;
                    border-radius: 6px;
                    margin-top: 0.1rem;
                }

                .ann-lb-footer {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0.75rem 1.25rem;
                    background: #faf5ff;
                    border-top: 1px solid #ede9fe;
                    font-size: 0.78rem;
                    color: #6d28d9;
                }

                .ann-lb-footer a {
                    color: #7c3aed;
                    font-weight: 600;
                    text-decoration: none;
                    display: flex;
                    align-items: center;
                    gap: 0.35rem;
                    transition: opacity 0.2s;
                }

                .ann-lb-footer a:hover { opacity: 0.75; }

                @media (max-width: 768px) {
                    .ann-lb-header-top { flex-direction: column; gap: 0.75rem; align-items: flex-start; }
                    .ann-lb-my-rank { align-self: stretch; display: flex; align-items: center; gap: 0.75rem; justify-content: center; }
                }
            </style>

            <div class="ann-lb-card">
                <div class="ann-lb-header">
                    <div class="ann-lb-header-top">
                        <div class="ann-lb-header-left">
                            <div class="ann-lb-trophy">🏆</div>
                            <div>
                                <h3>ประกาศผลคะแนน <?php echo htmlspecialchars($announcedPeriod['name']); ?></h3>
                                <p>รอบปี <?php echo $announcedPeriod['year']; ?> — Top <?php echo $announcedPeriod['leaderboard_top_n'] ?? 10; ?> Leaderboard<?php echo $announcedTotalCompanies > 0 ? ' (จาก ' . $announcedTotalCompanies . ' สถานประกอบการ)' : ''; ?></p>
                            </div>
                        </div>
                        <?php if ($announcedMyRank && $announcedMyData): ?>
                        <div class="ann-lb-my-rank">
                            <div class="ann-lb-my-rank-label">อันดับของคุณ</div>
                            <div class="ann-lb-my-rank-val">#<?php echo $announcedMyRank; ?></div>
                            <div class="ann-lb-my-rank-score"><?php echo number_format($announcedMyData['final_score'], 1); ?> คะแนน</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ann-lb-body">
                    <div class="ann-lb-list">
                        <?php foreach ($announcedLeaderboard as $index => $lbItem):
                            $lbRank = $index + 1;
                            $lbMedal = '';
                            $lbRankClass = '';
                            if ($lbRank === 1) { $lbMedal = '🥇'; $lbRankClass = 'rank-gold'; }
                            elseif ($lbRank === 2) { $lbMedal = '🥈'; $lbRankClass = 'rank-silver'; }
                            elseif ($lbRank === 3) { $lbMedal = '🥉'; $lbRankClass = 'rank-bronze'; }
                            $lbIsMe = ($isCompany && $user['company_id'] && $lbItem['company_id'] == $user['company_id']);
                            $lbLvl = getLevelInfo($lbItem['hicm_level'] ?? 1);
                        ?>
                        <div class="ann-lb-item <?php echo $lbRankClass; ?> <?php echo $lbIsMe ? 'my-company' : ''; ?>">
                            <div class="ann-lb-rank <?php echo $lbMedal ? 'has-medal' : ''; ?>">
                                <?php echo $lbMedal ?: $lbRank; ?>
                            </div>
                            <div class="ann-lb-company">
                                <div class="ann-lb-company-name">
                                    <?php echo htmlspecialchars($lbItem['company_name']); ?>
                                    <?php if ($lbIsMe): ?>
                                    <span style="color: #7c3aed; font-size: 0.7rem; font-weight: 700;"> ← คุณ</span>
                                    <?php endif; ?>
                                </div>
                                <div class="ann-lb-company-type"><?php echo htmlspecialchars(mb_substr($lbItem['industry_type'] ?? '', 0, 40)); ?></div>
                            </div>
                            <div class="ann-lb-score-col">
                                <div class="ann-lb-score-val"><?php echo number_format($lbItem['final_score'], 1); ?></div>
                                <span class="ann-lb-level-badge" style="background: <?php echo $lbLvl['bg']; ?>; color: <?php echo $lbLvl['color']; ?>;">
                                    Lv.<?php echo $lbItem['hicm_level']; ?> <?php echo $lbLvl['name']; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ann-lb-footer">
                    <span>📊 ข้อมูลจากรอบ <?php echo htmlspecialchars($announcedPeriod['name']); ?> (<?php echo $announcedPeriod['year']; ?>)</span>
                    <a href="<?php echo getBaseUrl(); ?>/pages/leaderboard.php">
                        ดูทั้งหมด
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isAuditor && !$isAdmin): ?>
            <!-- ========== AUDITOR DASHBOARD - PRO DESIGN ========== -->
            <style>
                /* Auditor Dashboard Styles */
                .auditor-dashboard {
                    --auditor-primary: #0ea5e9;
                    --auditor-primary-light: #e0f2fe;
                    --auditor-secondary: #6366f1;
                    --auditor-success: #10b981;
                    --auditor-warning: #f59e0b;
                }
                
                .auditor-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                }
                
                .auditor-stat-card {
                    background: white;
                    border-radius: 16px;
                    padding: 1.25rem;
                    border: 1px solid #f1f5f9;
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }
                
                .auditor-stat-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
                }
                
                .auditor-stat-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    border-radius: 16px 16px 0 0;
                }
                
                .auditor-stat-card.pending::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
                .auditor-stat-card.reviewing::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
                .auditor-stat-card.completed::before { background: linear-gradient(90deg, #10b981, #34d399); }
                .auditor-stat-card.total::before { background: linear-gradient(90deg, #6366f1, #818cf8); }
                
                .auditor-stat-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 1rem;
                }
                
                .auditor-stat-icon {
                    width: 44px;
                    height: 44px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .auditor-stat-card.pending .auditor-stat-icon { background: #fef3c7; color: #d97706; }
                .auditor-stat-card.reviewing .auditor-stat-icon { background: #dbeafe; color: #2563eb; }
                .auditor-stat-card.completed .auditor-stat-icon { background: #d1fae5; color: #059669; }
                .auditor-stat-card.total .auditor-stat-icon { background: #e0e7ff; color: #4f46e5; }
                
                .auditor-stat-value {
                    font-size: 2rem;
                    font-weight: 800;
                    color: #1e293b;
                    line-height: 1;
                }
                
                .auditor-stat-label {
                    font-size: 0.8rem;
                    color: #64748b;
                    margin-top: 0.375rem;
                }
                
                .auditor-stat-change {
                    font-size: 0.7rem;
                    padding: 0.25rem 0.5rem;
                    border-radius: 20px;
                    font-weight: 600;
                }
                
                .auditor-stat-change.up { background: #d1fae5; color: #059669; }
                .auditor-stat-change.down { background: #fee2e2; color: #dc2626; }
                
                .auditor-main-grid {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 1.5rem;
                    margin-bottom: 1.5rem;
                }
                
                .auditor-card {
                    background: white;
                    border-radius: 20px;
                    border: 1px solid #f1f5f9;
                    overflow: hidden;
                }
                
                .auditor-card-header {
                    padding: 1.25rem 1.5rem;
                    border-bottom: 1px solid #f1f5f9;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .auditor-card-title {
                    font-size: 1rem;
                    font-weight: 700;
                    color: #1e293b;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .auditor-card-body {
                    padding: 1.25rem 1.5rem;
                }
                
                /* Pending Assessments List */
                .pending-assessment-item {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem;
                    border-radius: 12px;
                    margin-bottom: 0.75rem;
                    background: #fafbfc;
                    transition: all 0.2s ease;
                    border: 1px solid transparent;
                }
                
                .pending-assessment-item:hover {
                    background: #f0f9ff;
                    border-color: #bae6fd;
                }
                
                .pending-assessment-item:last-child {
                    margin-bottom: 0;
                }
                
                .pending-company-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    color: #0369a1;
                    font-size: 1.1rem;
                    flex-shrink: 0;
                }
                
                .pending-company-info {
                    flex: 1;
                    min-width: 0;
                }
                
                .pending-company-name {
                    font-weight: 600;
                    color: #1e293b;
                    font-size: 0.9rem;
                    margin-bottom: 0.25rem;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                
                .pending-company-meta {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    font-size: 0.75rem;
                    color: #64748b;
                }
                
                .pending-days {
                    padding: 0.25rem 0.625rem;
                    border-radius: 20px;
                    font-size: 0.7rem;
                    font-weight: 600;
                }
                
                .pending-days.urgent { background: #fee2e2; color: #dc2626; }
                .pending-days.warning { background: #fef3c7; color: #d97706; }
                .pending-days.normal { background: #e0f2fe; color: #0369a1; }
                
                .pending-action-btn {
                    padding: 0.5rem 1rem;
                    border-radius: 8px;
                    font-size: 0.75rem;
                    font-weight: 600;
                    background: linear-gradient(135deg, #0ea5e9, #0284c7);
                    color: white;
                    border: none;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    display: inline-flex;
                    align-items: center;
                    gap: 0.375rem;
                }
                
                .pending-action-btn:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
                }
                
                /* Quick Actions */
                .auditor-quick-actions {
                    display: grid;
                    gap: 0.75rem;
                }
                
                .auditor-action-btn {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem;
                    border-radius: 12px;
                    background: #fafbfc;
                    border: 1px solid #f1f5f9;
                    transition: all 0.2s ease;
                    text-decoration: none;
                    color: #1e293b;
                }
                
                .auditor-action-btn:hover {
                    background: white;
                    border-color: #e2e8f0;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                    transform: translateX(4px);
                }
                
                .auditor-action-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }
                
                .auditor-action-text {
                    flex: 1;
                }
                
                .auditor-action-text strong {
                    display: block;
                    font-size: 0.85rem;
                    font-weight: 600;
                    margin-bottom: 0.125rem;
                }
                
                .auditor-action-text span {
                    font-size: 0.7rem;
                    color: #64748b;
                }
                
                /* Performance Chart */
                .auditor-performance-grid {
                    display: grid;
                    grid-template-columns: repeat(5, 1fr);
                    gap: 0.75rem;
                    margin-top: 1rem;
                }
                
                .auditor-level-item {
                    text-align: center;
                    padding: 1rem 0.5rem;
                    border-radius: 12px;
                    background: #fafbfc;
                }
                
                .auditor-level-bar {
                    width: 100%;
                    height: 60px;
                    background: #e2e8f0;
                    border-radius: 8px;
                    position: relative;
                    overflow: hidden;
                    margin-bottom: 0.5rem;
                }
                
                .auditor-level-fill {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    border-radius: 8px;
                    transition: height 1s ease-out;
                }
                
                .auditor-level-item:nth-child(1) .auditor-level-fill { background: linear-gradient(to top, #ef4444, #f87171); }
                .auditor-level-item:nth-child(2) .auditor-level-fill { background: linear-gradient(to top, #f59e0b, #fbbf24); }
                .auditor-level-item:nth-child(3) .auditor-level-fill { background: linear-gradient(to top, #3b82f6, #60a5fa); }
                .auditor-level-item:nth-child(4) .auditor-level-fill { background: linear-gradient(to top, #8b5cf6, #a78bfa); }
                .auditor-level-item:nth-child(5) .auditor-level-fill { background: linear-gradient(to top, #10b981, #34d399); }
                
                .auditor-level-count {
                    font-size: 1.25rem;
                    font-weight: 700;
                    color: #1e293b;
                }
                
                .auditor-level-label {
                    font-size: 0.65rem;
                    color: #64748b;
                    margin-top: 0.25rem;
                }
                
                /* Responsive */
                @media (max-width: 1024px) {
                    .auditor-main-grid {
                        grid-template-columns: 1fr;
                    }
                    .auditor-stats-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                    .auditor-quick-stats {
                        flex-wrap: wrap;
                        justify-content: center;
                    }
                    .auditor-welcome-content {
                        flex-direction: column;
                        text-align: center;
                    }
                }
                
                @media (max-width: 768px) {
                    .auditor-stats-grid {
                        grid-template-columns: 1fr;
                    }
                    .auditor-performance-grid {
                        grid-template-columns: repeat(3, 1fr);
                    }
                }
            </style>
            
            <?php 
            // Get auditor-specific data
            $pendingForAuditor = [];
            $reviewingByAuditor = [];
            $completedByAuditor = 0;
            $periodClosedCount = 0;
            
            // Filter assessments for this auditor — respect period status
            foreach ($recentAssessments as $assessment) {
                $pStatus = $assessment['period_status'] ?? '';
                $isPeriodCompleted = ($pStatus === 'completed');
                
                if (in_array($assessment['status'], ['submitted', 'under_review']) && $isPeriodCompleted) {
                    // Period completed (จบโครงการ) — don't count as pending
                    $periodClosedCount++;
                } elseif ($assessment['status'] === 'submitted' && !$isPeriodCompleted) {
                    $pendingForAuditor[] = $assessment;
                } elseif ($assessment['status'] === 'under_review' && !$isPeriodCompleted) {
                    $reviewingByAuditor[] = $assessment;
                } elseif (in_array($assessment['status'], ['evaluated', 'completed'])) {
                    $completedByAuditor++;
                }
            }
            // Period-closed assessments count toward completed
            $completedByAuditor += $periodClosedCount;
            ?>
            
            <!-- Announcements for Auditor -->
            <?php
                require_once __DIR__ . '/../includes/news.php';
                $announcements = getAnnouncements(3, 'active');
            ?>
            <?php if (!empty($announcements)): ?>
            <div class="card mb-6 animate-fade-in-up">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to right, #fff, #f0f9ff);">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; background: #e0f2fe; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold" style="color: #0c4a6e;">ข่าวประชาสัมพันธ์</h3>
                            <p class="text-xs text-gray-500">ประกาศและข่าวสารล่าสุด</p>
                        </div>
                    </div>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php foreach ($announcements as $index => $item): ?>
                        <div style="padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; gap: 1rem; align-items: flex-start; <?php echo $index === count($announcements)-1 ? 'border-bottom: none;' : ''; ?>">
                            <div style="flex: 1;">
                                <h4 style="font-size: 1rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                    <?php if (strtotime($item['created_at']) > strtotime('-3 days')): ?>
                                        <span class="badge badge-primary" style="margin-left: 0.5rem; font-size: 0.7rem;">ใหม่</span>
                                    <?php endif; ?>
                                </h4>
                                <p style="font-size: 0.875rem; color: var(--gray-600); line-height: 1.6;">
                                    <?php echo nl2br(htmlspecialchars($item['content'])); ?>
                                </p>
                                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--gray-400); display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <?php echo (new DateTime($item['created_at']))->format('d/m/Y H:i'); ?>
                                    <span style="margin: 0 4px;">•</span>
                                    โดย <?php echo htmlspecialchars($item['author_name'] ?? 'Admin'); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="auditor-dashboard">
                <!-- Stats Grid -->
                <div class="auditor-stats-grid">
                    <div class="auditor-stat-card pending animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="auditor-stat-header">
                            <div class="auditor-stat-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                            <?php if (count($pendingForAuditor) > 0): ?>
                            <span class="auditor-stat-change up">+<?php echo count($pendingForAuditor); ?> ใหม่</span>
                            <?php endif; ?>
                        </div>
                        <div class="auditor-stat-value"><?php echo count($pendingForAuditor); ?></div>
                        <div class="auditor-stat-label">รอการตรวจสอบ</div>
                    </div>
                    
                    <div class="auditor-stat-card reviewing animate-fade-in-up" style="animation-delay: 0.2s;">
                        <div class="auditor-stat-header">
                            <div class="auditor-stat-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="auditor-stat-value"><?php echo count($reviewingByAuditor); ?></div>
                        <div class="auditor-stat-label">กำลังตรวจสอบ</div>
                    </div>
                    
                    <div class="auditor-stat-card completed animate-fade-in-up" style="animation-delay: 0.3s;">
                        <div class="auditor-stat-header">
                            <div class="auditor-stat-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </div>
                        </div>
                        <div class="auditor-stat-value"><?php echo $completedByAuditor; ?></div>
                        <div class="auditor-stat-label">ตรวจเสร็จสิ้น / จบโครงการ</div>
                    </div>
                    
                    <div class="auditor-stat-card total animate-fade-in-up" style="animation-delay: 0.4s;">
                        <div class="auditor-stat-header">
                            <div class="auditor-stat-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                            </div>
                        </div>
                        <div class="auditor-stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="auditor-stat-label">สถานประกอบการทั้งหมด</div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="auditor-main-grid">
                    <!-- Pending Assessments -->
                    <div class="auditor-card animate-fade-in-up" style="animation-delay: 0.5s;">
                        <div class="auditor-card-header">
                            <h3 class="auditor-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                                รอการตรวจสอบ
                            </h3>
                            <a href="assessments.php?status=submitted" class="btn btn-sm btn-outline">ดูทั้งหมด</a>
                        </div>
                        <div class="auditor-card-body">
                            <?php if (empty($pendingForAuditor)): ?>
                            <div style="text-align: center; padding: 2rem; color: #94a3b8;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem; opacity: 0.5;">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <p>ไม่มีรายการรอตรวจสอบ</p>
                            </div>
                            <?php else: ?>
                            <?php foreach (array_slice($pendingForAuditor, 0, 5) as $pending): 
                                $submittedDate = new DateTime($pending['submitted_at'] ?? $pending['updated_at']);
                                $now = new DateTime();
                                $daysDiff = $now->diff($submittedDate)->days;
                                $daysClass = $daysDiff > 7 ? 'urgent' : ($daysDiff > 3 ? 'warning' : 'normal');
                            ?>
                            <div class="pending-assessment-item">
                                <div class="pending-company-avatar" style="padding: 0; overflow: hidden; background: white;">
                                    <?php 
                                    $avatar = $pending['company_owner_avatar'] ?? $pending['logo'] ?? 'default';
                                    if ($avatar && $avatar !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatar)):
                                    ?>
                                        <img src="<?php echo getBaseUrl(); ?>/assets/uploads/avatars/<?php echo $avatar; ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo mb_substr($pending['company_name'], 0, 1); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="pending-company-info">
                                    <div class="pending-company-name"><?php echo htmlspecialchars($pending['company_name']); ?></div>
                                    <div class="pending-company-meta">
                                        <span><?php echo $pending['period_name']; ?> <?php echo $pending['year']; ?></span>
                                        <span class="pending-days <?php echo $daysClass; ?>">
                                            <?php echo $daysDiff; ?> วันที่แล้ว
                                        </span>
                                    </div>
                                </div>
                                <a href="assessment-view.php?id=<?php echo $pending['id']; ?>" class="pending-action-btn">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    ตรวจสอบ
                                </a>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions & Level Distribution -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <!-- Quick Actions -->
                        <div class="auditor-card animate-fade-in-up" style="animation-delay: 0.6s;">
                            <div class="auditor-card-header">
                                <h3 class="auditor-card-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"/>
                                        <rect x="14" y="3" width="7" height="7"/>
                                        <rect x="14" y="14" width="7" height="7"/>
                                        <rect x="3" y="14" width="7" height="7"/>
                                    </svg>
                                    เมนูลัด
                                </h3>
                            </div>
                            <div class="auditor-card-body">
                                <div class="auditor-quick-actions">
                                    <a href="assessments.php?status=submitted" class="auditor-action-btn">
                                        <div class="auditor-action-icon" style="background: #fef3c7; color: #d97706;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <polyline points="12 6 12 12 16 14"/>
                                            </svg>
                                        </div>
                                        <div class="auditor-action-text">
                                            <strong>รายการรอตรวจสอบ</strong>
                                            <span><?php echo count($pendingForAuditor); ?> รายการ</span>
                                        </div>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"/>
                                        </svg>
                                    </a>
                                    <a href="assessments.php" class="auditor-action-btn">
                                        <div class="auditor-action-icon" style="background: #dbeafe; color: #2563eb;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                                <polyline points="14 2 14 8 20 8"/>
                                            </svg>
                                        </div>
                                        <div class="auditor-action-text">
                                            <strong>การประเมินทั้งหมด</strong>
                                            <span>จัดการและดูรายละเอียด</span>
                                        </div>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"/>
                                        </svg>
                                    </a>
                                    <a href="reports.php" class="auditor-action-btn">
                                        <div class="auditor-action-icon" style="background: #d1fae5; color: #059669;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="20" x2="18" y2="10"/>
                                                <line x1="12" y1="20" x2="12" y2="4"/>
                                                <line x1="6" y1="20" x2="6" y2="14"/>
                                            </svg>
                                        </div>
                                        <div class="auditor-action-text">
                                            <strong>รายงานสรุป</strong>
                                            <span>ดูรายงานและสถิติ</span>
                                        </div>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Level Distribution Mini -->
                        <div class="auditor-card animate-fade-in-up" style="animation-delay: 0.7s;">
                            <div class="auditor-card-header">
                                <h3 class="auditor-card-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                                        <line x1="18" y1="20" x2="18" y2="10"/>
                                        <line x1="12" y1="20" x2="12" y2="4"/>
                                        <line x1="6" y1="20" x2="6" y2="14"/>
                                    </svg>
                                    การกระจายระดับ
                                </h3>
                            </div>
                            <div class="auditor-card-body" style="padding-top: 0.5rem;">
                                <div class="auditor-performance-grid">
                                    <?php 
                                    // Convert scoreDistribution to level counts
                                    $levelCounts = array_fill(1, 5, 0);
                                    foreach ($scoreDistribution as $dist) {
                                        if (isset($dist['hicm_level']) && isset($dist['count'])) {
                                            $levelCounts[$dist['hicm_level']] = $dist['count'];
                                        }
                                    }
                                    $levels = [1 => 'เริ่มต้น', 2 => 'พัฒนา', 3 => 'ดี', 4 => 'เลิศ', 5 => 'โลก'];
                                    $maxCount = max($levelCounts) ?: 1;
                                    foreach ($levels as $lvl => $label): 
                                        $count = $levelCounts[$lvl] ?? 0;
                                        $height = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                                    ?>
                                    <div class="auditor-level-item">
                                        <div class="auditor-level-bar">
                                            <div class="auditor-level-fill" style="height: <?php echo $height; ?>%;"></div>
                                        </div>
                                        <div class="auditor-level-count"><?php echo $count; ?></div>
                                        <div class="auditor-level-label"><?php echo $label; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Completed -->
                <div class="auditor-card animate-fade-in-up" style="animation-delay: 0.8s;">
                    <div class="auditor-card-header">
                        <h3 class="auditor-card-title">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            ตรวจสอบล่าสุด
                        </h3>
                        <a href="assessments.php?status=completed" class="btn btn-sm btn-outline">ดูทั้งหมด</a>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>สถานประกอบการ</th>
                                    <th>รอบประเมิน</th>
                                    <th>คะแนน</th>
                                    <th>ระดับ</th>
                                    <th>สถานะ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $displayAssessments = array_slice($recentAssessments, 0, 5);
                                foreach ($displayAssessments as $item): 
                                    $levelInfo = getLevelInfo($item['hicm_level']);
                                    $statusLabels = [
                                        'draft' => ['label' => 'ร่าง', 'class' => 'badge-secondary'],
                                        'submitted' => ['label' => 'รอตรวจ', 'class' => 'badge-warning'],
                                        'under_review' => ['label' => 'กำลังตรวจ', 'class' => 'badge-primary'],
                                        'evaluated' => ['label' => 'ประเมินแล้ว', 'class' => 'badge-info'],
                                        'completed' => ['label' => 'เสร็จสิ้น', 'class' => 'badge-success']
                                    ];
                                    $status = $statusLabels[$item['status']] ?? $statusLabels['draft'];
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 36px; height: 36px; border-radius: 50%; overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                                <?php 
                                                $avatar = $item['company_owner_avatar'] ?? $item['logo'] ?? 'default';
                                                if ($avatar && $avatar !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatar)):
                                                ?>
                                                    <img src="<?php echo getBaseUrl(); ?>/assets/uploads/avatars/<?php echo $avatar; ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <span style="font-weight: 700; color: #0369a1; font-size: 0.9rem;"><?php echo mb_substr($item['company_name'], 0, 1); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div style="font-weight: 600; font-size: 0.875rem;"><?php echo htmlspecialchars($item['company_name']); ?></div>
                                                <div style="font-size: 0.7rem; color: #94a3b8;"><?php echo htmlspecialchars($item['industry_type'] ?? '-'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.85rem;"><?php echo $item['period_name']; ?> <?php echo $item['year']; ?></td>
                                    <td>
                                        <span style="font-weight: 700; font-size: 1rem; color: <?php echo $item['final_score'] >= 700 ? '#059669' : ($item['final_score'] >= 500 ? '#d97706' : '#dc2626'); ?>">
                                            <?php echo number_format($item['final_score'], 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; background: <?php echo $levelInfo['bg']; ?>; color: <?php echo $levelInfo['color']; ?>">
                                            <span style="width: 6px; height: 6px; border-radius: 50%; background: <?php echo $levelInfo['color']; ?>"></span>
                                            <?php echo $levelInfo['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $status['class']; ?>" style="font-size: 0.7rem;"><?php echo $status['label']; ?></span>
                                    </td>
                                    <td>
                                        <a href="assessment-view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline" style="font-size: 0.75rem; padding: 0.375rem 0.75rem;">
                                            ดูรายละเอียด
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========== END AUDITOR DASHBOARD ========== -->
            
            <?php elseif ($isCEO): ?>
            <!-- ========== CEO DASHBOARD - PROFESSIONAL DESIGN ========== -->
            <?php
            $db = getDB();
            
            // Get current period
            $stmt = $db->prepare("
                SELECT id, name, year, show_leaderboard, leaderboard_top_n, status
                FROM assessment_periods 
                WHERE status IN ('open', 'evaluating', 'completed') 
                ORDER BY year DESC, start_date DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $currentPeriod = $stmt->fetch();
            $periodId = $currentPeriod['id'] ?? null;
            
            // Get CEO Statistics for current period
            $ceoStats = [
                'total_companies' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'not_started' => 0,
                'avg_score' => 0,
                'level_1' => 0,
                'level_2' => 0,
                'level_3' => 0,
                'level_4' => 0,
                'level_5' => 0
            ];
            
            if ($periodId) {
                // Get company count
                $stmt = $db->prepare("SELECT COUNT(*) FROM companies WHERE is_active = 1");
                $stmt->execute();
                $ceoStats['total_companies'] = $stmt->fetchColumn();
                
                // Get assessment statistics
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status IN ('evaluated', 'completed') THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status IN ('draft', 'submitted', 'under_review') THEN 1 ELSE 0 END) as in_progress,
                        AVG(CASE WHEN final_score > 0 THEN final_score ELSE NULL END) as avg_score,
                        SUM(CASE WHEN hicm_level = 1 THEN 1 ELSE 0 END) as level_1,
                        SUM(CASE WHEN hicm_level = 2 THEN 1 ELSE 0 END) as level_2,
                        SUM(CASE WHEN hicm_level = 3 THEN 1 ELSE 0 END) as level_3,
                        SUM(CASE WHEN hicm_level = 4 THEN 1 ELSE 0 END) as level_4,
                        SUM(CASE WHEN hicm_level = 5 THEN 1 ELSE 0 END) as level_5
                    FROM assessments 
                    WHERE period_id = ?
                ");
                $stmt->execute([$periodId]);
                $assessmentStats = $stmt->fetch();
                
                $ceoStats['completed'] = $assessmentStats['completed'] ?? 0;
                $ceoStats['in_progress'] = $assessmentStats['in_progress'] ?? 0;
                $ceoStats['not_started'] = $ceoStats['total_companies'] - ($ceoStats['completed'] + $ceoStats['in_progress']);
                $ceoStats['avg_score'] = $assessmentStats['avg_score'] ?? 0;
                $ceoStats['level_1'] = $assessmentStats['level_1'] ?? 0;
                $ceoStats['level_2'] = $assessmentStats['level_2'] ?? 0;
                $ceoStats['level_3'] = $assessmentStats['level_3'] ?? 0;
                $ceoStats['level_4'] = $assessmentStats['level_4'] ?? 0;
                $ceoStats['level_5'] = $assessmentStats['level_5'] ?? 0;
            }
            
            // Calculate completion rate
            $completionRate = $ceoStats['total_companies'] > 0 
                ? round(($ceoStats['completed'] / $ceoStats['total_companies']) * 100, 1) 
                : 0;
            
            // Get Leaderboard Data (Top 10)
            $leaderboardData = [];
            if ($periodId) {
                $stmt = $db->prepare("
                    SELECT 
                        c.company_name,
                        c.industry_type,
                        c.employee_count,
                        a.final_score,
                        a.hicm_level,
                        a.status
                    FROM assessments a
                    JOIN companies c ON a.company_id = c.id
                    WHERE a.period_id = ? AND a.status IN ('evaluated', 'completed') AND a.final_score > 0
                    ORDER BY a.final_score DESC, a.hicm_level DESC
                    LIMIT 10
                ");
                $stmt->execute([$periodId]);
                $leaderboardData = $stmt->fetchAll();
            }
            
            // Get average scores by pillar
            $pillarAvgScores = [];
            if ($periodId) {
                $stmt = $db->prepare("
                    SELECT 
                        p.code as pillar_code,
                        p.name_th as pillar_name,
                        AVG(CASE WHEN s.auditor_score > 0 THEN s.auditor_score 
                             WHEN s.self_score > 0 THEN s.self_score ELSE NULL END) as avg_score
                    FROM pillars p
                    LEFT JOIN indicators i ON p.id = i.pillar_id
                    LEFT JOIN assessment_scores s ON i.id = s.indicator_id
                    LEFT JOIN assessments a ON s.assessment_id = a.id AND a.period_id = ?
                    WHERE s.is_na = 0 OR s.is_na IS NULL
                    GROUP BY p.id, p.code, p.name_th
                    ORDER BY p.display_order
                ");
                $stmt->execute([$periodId]);
                $pillarAvgScores = $stmt->fetchAll();
            }
            
            $levelInfo = [
                1 => ['name' => 'เริ่มต้น', 'name_en' => 'Emerging', 'color' => '#EF4444', 'bg' => '#FEE2E2'],
                2 => ['name' => 'กำลังพัฒนา', 'name_en' => 'Developing', 'color' => '#F59E0B', 'bg' => '#FEF3C7'],
                3 => ['name' => 'พัฒนาดี', 'name_en' => 'Performing', 'color' => '#3B82F6', 'bg' => '#DBEAFE'],
                4 => ['name' => 'เป็นเลิศ', 'name_en' => 'Excellence', 'color' => '#8B5CF6', 'bg' => '#EDE9FE'],
                5 => ['name' => 'ระดับโลก', 'name_en' => 'World-Class', 'color' => '#10B981', 'bg' => '#D1FAE5']
            ];
            
            $pillarInfo = [
                'H1' => ['name_en' => 'Health Promotion',               'name_th' => 'การส่งเสริมสุขภาพ',                'color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => '❤️'],
                'I2' => ['name_en' => 'Industrial Safety & Environment', 'name_th' => 'ความปลอดภัยและสิ่งแวดล้อม',       'color' => '#3B82F6', 'bg' => '#DBEAFE', 'icon' => '🛡️'],
                'C3' => ['name_en' => 'Community Engagement',            'name_th' => 'การมีส่วนร่วมกับชุมชน',           'color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => '🌿'],
                'M4' => ['name_en' => 'Management & Sustainability',     'name_th' => 'การบริหารจัดการและความยั่งยืน',   'color' => '#8B5CF6', 'bg' => '#EDE9FE', 'icon' => '⚙️']
            ];
            ?>
            
            <style>
                /* CEO Dashboard Professional Styles */
                .ceo-dashboard {
                    --ceo-primary: #1e3a5f;
                    --ceo-secondary: #0369a1;
                    --ceo-accent: #8B5CF6;
                }
                
                /* Hero Welcome Section */
                .ceo-hero {
                    background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
                    border-radius: 24px;
                    padding: 2rem 2.5rem;
                    color: white;
                    position: relative;
                    overflow: hidden;
                    margin-bottom: 2rem;
                }
                .ceo-hero::before {
                    content: '';
                    position: absolute;
                    top: -50%;
                    right: -10%;
                    width: 400px;
                    height: 400px;
                    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                    pointer-events: none;
                }
                .ceo-hero-content {
                    position: relative;
                    z-index: 1;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 1.5rem;
                }
                .ceo-hero-info h1 {
                    font-size: 1.75rem;
                    font-weight: 700;
                    margin: 0 0 0.5rem 0;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }
                .ceo-hero-info p {
                    margin: 0;
                    opacity: 0.9;
                    font-size: 0.95rem;
                }
                .ceo-period-badge {
                    background: rgba(255,255,255,0.15);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.2);
                    padding: 1rem 1.5rem;
                    border-radius: 16px;
                    text-align: center;
                }
                .ceo-period-badge .period-label {
                    font-size: 0.75rem;
                    opacity: 0.8;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .ceo-period-badge .period-value {
                    font-size: 1.25rem;
                    font-weight: 700;
                    margin-top: 0.25rem;
                }
                
                /* Stats Grid */
                .ceo-stats-grid {
                    display: grid;
                    grid-template-columns: repeat(5, 1fr);
                    gap: 1rem;
                    margin-bottom: 2rem;
                }
                @media (max-width: 1200px) {
                    .ceo-stats-grid { grid-template-columns: repeat(3, 1fr); }
                }
                @media (max-width: 768px) {
                    .ceo-stats-grid { grid-template-columns: repeat(2, 1fr); }
                }
                .ceo-stat-card {
                    background: white;
                    border-radius: 16px;
                    padding: 1.5rem;
                    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
                    border: 1px solid var(--gray-100);
                    transition: all 0.3s;
                    position: relative;
                    overflow: hidden;
                }
                .ceo-stat-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 12px 30px rgba(0,0,0,0.1);
                }
                .ceo-stat-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                }
                .ceo-stat-card.stat-total::before { background: #3B82F6; }
                .ceo-stat-card.stat-completed::before { background: #10B981; }
                .ceo-stat-card.stat-progress::before { background: #F59E0B; }
                .ceo-stat-card.stat-score::before { background: #8B5CF6; }
                .ceo-stat-card.stat-excellence::before { background: #EC4899; }
                
                .ceo-stat-icon {
                    width: 48px;
                    height: 48px;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 1rem;
                }
                .ceo-stat-value {
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--gray-900);
                    line-height: 1;
                }
                .ceo-stat-label {
                    font-size: 0.85rem;
                    color: var(--gray-500);
                    margin-top: 0.5rem;
                }
                .ceo-stat-change {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.25rem;
                    font-size: 0.75rem;
                    padding: 0.25rem 0.5rem;
                    border-radius: 999px;
                    margin-top: 0.5rem;
                }
                .ceo-stat-change.positive { background: #D1FAE5; color: #059669; }
                .ceo-stat-change.neutral { background: #F3F4F6; color: #6B7280; }
                
                /* Main Content Grid */
                .ceo-main-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 1.5rem;
                    margin-bottom: 2rem;
                }
                @media (max-width: 1024px) {
                    .ceo-main-grid { grid-template-columns: 1fr; }
                }
                
                /* Card Styles */
                .ceo-card {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
                    border: 1px solid var(--gray-100);
                    overflow: hidden;
                }
                .ceo-card-header {
                    padding: 1.25rem 1.5rem;
                    border-bottom: 1px solid var(--gray-100);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                .ceo-card-header h3 {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: var(--gray-800);
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }
                .ceo-card-header .badge {
                    font-size: 0.75rem;
                    padding: 0.35rem 0.75rem;
                    border-radius: 999px;
                }
                .ceo-card-body {
                    padding: 1.5rem;
                }
                
                /* Leaderboard Styles */
                .leaderboard-header {
                    background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%);
                    color: white;
                    padding: 1.25rem 1.5rem;
                }
                .leaderboard-list {
                    max-height: 480px;
                    overflow-y: auto;
                }
                .leaderboard-item {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    padding: 1rem 1.5rem;
                    border-bottom: 1px solid var(--gray-100);
                    transition: all 0.2s;
                }
                .leaderboard-item:last-child { border-bottom: none; }
                .leaderboard-item:hover { background: var(--gray-50); }
                .leaderboard-item.rank-1 { background: linear-gradient(135deg, #FEF3C7, #FDE68A); }
                .leaderboard-item.rank-2 { background: linear-gradient(135deg, #F3F4F6, #E5E7EB); }
                .leaderboard-item.rank-3 { background: linear-gradient(135deg, #FFEDD5, #FED7AA); }
                
                .rank-badge {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 700;
                    font-size: 1rem;
                    background: white;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    flex-shrink: 0;
                }
                .rank-badge.gold { background: linear-gradient(135deg, #FCD34D, #F59E0B); color: white; }
                .rank-badge.silver { background: linear-gradient(135deg, #D1D5DB, #9CA3AF); color: white; }
                .rank-badge.bronze { background: linear-gradient(135deg, #FDBA74, #F97316); color: white; }
                
                .leaderboard-company {
                    flex: 1;
                    min-width: 0;
                }
                .leaderboard-company h4 {
                    font-size: 0.95rem;
                    font-weight: 600;
                    color: var(--gray-800);
                    margin: 0 0 0.25rem 0;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                .leaderboard-company span {
                    font-size: 0.75rem;
                    color: var(--gray-500);
                }
                
                .leaderboard-score {
                    text-align: right;
                }
                .leaderboard-score .score {
                    font-size: 1.25rem;
                    font-weight: 700;
                    color: #8B5CF6;
                }
                .leaderboard-score .level {
                    font-size: 0.7rem;
                    padding: 0.2rem 0.5rem;
                    border-radius: 999px;
                    margin-top: 0.25rem;
                    display: inline-block;
                }
                
                /* Level Distribution Chart */
                .level-chart {
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                }
                .level-bar-item {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                }
                .level-bar-label {
                    width: 100px;
                    font-size: 0.85rem;
                    font-weight: 500;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .level-bar-track {
                    flex: 1;
                    height: 32px;
                    background: var(--gray-100);
                    border-radius: 8px;
                    overflow: hidden;
                    position: relative;
                }
                .level-bar-fill {
                    height: 100%;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: flex-end;
                    padding-right: 0.75rem;
                    font-weight: 600;
                    font-size: 0.85rem;
                    color: white;
                    transition: width 0.5s ease;
                }
                .level-bar-count {
                    width: 50px;
                    text-align: right;
                    font-weight: 600;
                    color: var(--gray-700);
                }
                
                /* Pillar Performance */
                .pillar-performance {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }
                @media (max-width: 768px) {
                    .pillar-performance { grid-template-columns: 1fr; }
                }
                .pillar-card {
                    background: var(--gray-50);
                    border-radius: 12px;
                    padding: 1.25rem;
                    border-left: 4px solid;
                    transition: all 0.2s;
                }
                .pillar-card:hover {
                    background: white;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                }
                .pillar-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 0.75rem;
                }
                .pillar-name {
                    font-weight: 600;
                    font-size: 0.9rem;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .pillar-score {
                    font-size: 1.5rem;
                    font-weight: 700;
                }
                .pillar-bar {
                    height: 8px;
                    background: var(--gray-200);
                    border-radius: 4px;
                    overflow: hidden;
                }
                .pillar-bar-fill {
                    height: 100%;
                    border-radius: 4px;
                    transition: width 0.5s ease;
                }
                
                /* Quick Actions */
                .ceo-actions {
                    display: flex;
                    gap: 1rem;
                    flex-wrap: wrap;
                }
                .ceo-action-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.875rem 1.5rem;
                    border-radius: 12px;
                    font-weight: 600;
                    font-size: 0.9rem;
                    text-decoration: none;
                    transition: all 0.2s;
                }
                .ceo-action-btn.primary {
                    background: linear-gradient(135deg, #3B82F6, #1D4ED8);
                    color: white;
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
                }
                .ceo-action-btn.primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
                }
                .ceo-action-btn.outline {
                    background: white;
                    color: var(--gray-700);
                    border: 2px solid var(--gray-200);
                }
                .ceo-action-btn.outline:hover {
                    border-color: var(--primary);
                    color: var(--primary);
                    transform: translateY(-2px);
                }
                .ceo-action-btn svg {
                    width: 20px;
                    height: 20px;
                }
                
                /* Animation */
                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-in { animation: fadeInUp 0.5s ease forwards; }
                .delay-1 { animation-delay: 0.1s; opacity: 0; }
                .delay-2 { animation-delay: 0.2s; opacity: 0; }
                .delay-3 { animation-delay: 0.3s; opacity: 0; }
            </style>
            
            <div class="ceo-dashboard">
                <!-- Hero Section -->
                <div class="ceo-hero animate-in">
                    <div class="ceo-hero-content">
                        <div class="ceo-hero-info">
                            <h1>
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                CEO Executive Dashboard
                            </h1>
                            <p>ภาพรวมผลการประเมินคุณภาพสถานประกอบการ HICM V2025</p>
                        </div>
                        <div class="ceo-period-badge">
                            <div class="period-label">รอบประเมินปัจจุบัน</div>
                            <div class="period-value"><?php echo htmlspecialchars($currentPeriod['name'] ?? 'ไม่มีข้อมูล'); ?></div>
                            <div style="font-size: 0.8rem; opacity: 0.8; margin-top: 0.25rem;">ปี <?php echo $currentPeriod['year'] ?? '-'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Grid -->
                <div class="ceo-stats-grid animate-in delay-1">
                    <div class="ceo-stat-card stat-total">
                        <div class="ceo-stat-icon" style="background: #DBEAFE; color: #3B82F6;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <div class="ceo-stat-value"><?php echo number_format($ceoStats['total_companies']); ?></div>
                        <div class="ceo-stat-label">สถานประกอบการทั้งหมด</div>
                    </div>
                    
                    <div class="ceo-stat-card stat-completed">
                        <div class="ceo-stat-icon" style="background: #D1FAE5; color: #10B981;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <div class="ceo-stat-value"><?php echo number_format($ceoStats['completed']); ?></div>
                        <div class="ceo-stat-label">ประเมินเสร็จสิ้น</div>
                        <div class="ceo-stat-change positive">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
                            <?php echo $completionRate; ?>%
                        </div>
                    </div>
                    
                    <div class="ceo-stat-card stat-progress">
                        <div class="ceo-stat-icon" style="background: #FEF3C7; color: #F59E0B;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="ceo-stat-value"><?php echo number_format($ceoStats['in_progress']); ?></div>
                        <div class="ceo-stat-label">กำลังดำเนินการ</div>
                    </div>
                    
                    <div class="ceo-stat-card stat-score">
                        <div class="ceo-stat-icon" style="background: #EDE9FE; color: #8B5CF6;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div class="ceo-stat-value"><?php echo number_format($ceoStats['avg_score'], 1); ?></div>
                        <div class="ceo-stat-label">คะแนนเฉลี่ย</div>
                    </div>
                    
                    <div class="ceo-stat-card stat-excellence">
                        <div class="ceo-stat-icon" style="background: #FCE7F3; color: #EC4899;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="8" r="7"/>
                                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                            </svg>
                        </div>
                        <div class="ceo-stat-value"><?php echo number_format($ceoStats['level_4'] + $ceoStats['level_5']); ?></div>
                        <div class="ceo-stat-label">ระดับเป็นเลิศขึ้นไป</div>
                    </div>
                </div>
                
                <!-- Main Content Grid -->
                <div class="ceo-main-grid">
                    <!-- Leaderboard -->
                    <div class="ceo-card animate-in delay-2">
                        <div class="leaderboard-header">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600; display: flex; align-items: center; gap: 0.75rem;">
                                    🏆 Top 10 Leaderboard
                                </h3>
                                <span style="font-size: 0.8rem; opacity: 0.9;">คะแนนสูงสุด</span>
                            </div>
                        </div>
                        <div class="leaderboard-list">
                            <?php if (empty($leaderboardData)): ?>
                            <div style="padding: 3rem; text-align: center; color: var(--gray-400);">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem; opacity: 0.5;">
                                    <circle cx="12" cy="8" r="7"/>
                                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                                </svg>
                                <p style="margin: 0;">ยังไม่มีข้อมูล Leaderboard</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($leaderboardData as $index => $item): 
                                    $rank = $index + 1;
                                    $rankClass = '';
                                    $badgeClass = '';
                                    $medal = '';
                                    if ($rank === 1) { $rankClass = 'rank-1'; $badgeClass = 'gold'; $medal = '🥇'; }
                                    elseif ($rank === 2) { $rankClass = 'rank-2'; $badgeClass = 'silver'; $medal = '🥈'; }
                                    elseif ($rank === 3) { $rankClass = 'rank-3'; $badgeClass = 'bronze'; $medal = '🥉'; }
                                    $lvl = $levelInfo[$item['hicm_level']] ?? $levelInfo[1];
                                ?>
                                <div class="leaderboard-item <?php echo $rankClass; ?>">
                                    <div class="rank-badge <?php echo $badgeClass; ?>">
                                        <?php echo $medal ?: $rank; ?>
                                    </div>
                                    <div class="leaderboard-company">
                                        <h4><?php echo htmlspecialchars($item['company_name']); ?></h4>
                                        <span><?php echo htmlspecialchars(mb_substr($item['industry_type'] ?? '-', 0, 35)); ?></span>
                                    </div>
                                    <div class="leaderboard-score">
                                        <div class="score"><?php echo number_format($item['final_score'], 1); ?></div>
                                        <div class="level" style="background: <?php echo $lvl['bg']; ?>; color: <?php echo $lvl['color']; ?>;">
                                            <?php echo $lvl['name']; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Level Distribution -->
                    <div class="ceo-card animate-in delay-2">
                        <div class="ceo-card-header">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="20" x2="18" y2="10"/>
                                    <line x1="12" y1="20" x2="12" y2="4"/>
                                    <line x1="6" y1="20" x2="6" y2="14"/>
                                </svg>
                                การกระจายตามระดับ HICM
                            </h3>
                        </div>
                        <div class="ceo-card-body">
                            <?php 
                            $totalAssessed = $ceoStats['level_1'] + $ceoStats['level_2'] + $ceoStats['level_3'] + $ceoStats['level_4'] + $ceoStats['level_5'];
                            $maxCount = max($ceoStats['level_1'], $ceoStats['level_2'], $ceoStats['level_3'], $ceoStats['level_4'], $ceoStats['level_5'], 1);
                            ?>
                            <div class="level-chart">
                                <?php foreach ($levelInfo as $level => $info): 
                                    $count = $ceoStats["level_{$level}"] ?? 0;
                                    $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                                ?>
                                <div class="level-bar-item">
                                    <div class="level-bar-label" style="color: <?php echo $info['color']; ?>;">
                                        <span style="font-weight: 700;">L<?php echo $level; ?></span>
                                        <span style="font-size: 0.75rem;"><?php echo $info['name']; ?></span>
                                    </div>
                                    <div class="level-bar-track">
                                        <div class="level-bar-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $info['color']; ?>;">
                                            <?php if ($count > 0): ?><?php echo $count; ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="level-bar-count"><?php echo $count; ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--gray-100); text-align: center;">
                                <div style="font-size: 0.85rem; color: var(--gray-500);">รวมที่ประเมินแล้ว</div>
                                <div style="font-size: 2rem; font-weight: 700; color: var(--gray-800);"><?php echo $totalAssessed; ?></div>
                                <div style="font-size: 0.8rem; color: var(--gray-400);">สถานประกอบการ</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pillar Performance -->
                <div class="ceo-card animate-in delay-3" style="margin-bottom: 2rem;">
                    <div class="ceo-card-header">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <path d="M3 9h18M9 21V9"/>
                            </svg>
                            คะแนนเฉลี่ยตามเสาหลัก HICM
                        </h3>
                    </div>
                    <div class="ceo-card-body">
                        <div class="pillar-performance">
                            <?php foreach ($pillarInfo as $code => $info): 
                                $pillarScore = 0;
                                foreach ($pillarAvgScores as $ps) {
                                    if ($ps['pillar_code'] === $code) {
                                        $pillarScore = $ps['avg_score'] ?? 0;
                                        break;
                                    }
                                }
                                $scorePercent = ($pillarScore / 5) * 100;
                            ?>
                            <div class="pillar-card" style="border-left-color: <?php echo $info['color']; ?>;">
                                <div class="pillar-header">
                                    <div>
                                        <div class="pillar-name" style="color: <?php echo $info['color']; ?>;">
                                            <?php echo $info['icon']; ?> <?php echo $code; ?>: <?php echo $info['name_en']; ?>
                                        </div>
                                        <div style="font-size: 0.68rem; color: #9CA3AF; font-weight: 500; margin-top: 0.1rem;"><?php echo $info['name_th']; ?></div>
                                    </div>
                                    <div class="pillar-score" style="color: <?php echo $info['color']; ?>;">
                                        <?php echo number_format($pillarScore, 2); ?>
                                    </div>
                                </div>
                                <div class="pillar-bar">
                                    <div class="pillar-bar-fill" style="width: <?php echo $scorePercent; ?>%; background: <?php echo $info['color']; ?>;"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="ceo-card animate-in delay-3">
                    <div class="ceo-card-header">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            เมนูลัด
                        </h3>
                    </div>
                    <div class="ceo-card-body">
                        <div class="ceo-actions">
                            <a href="reports.php" class="ceo-action-btn primary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                </svg>
                                ดูรายงานฉบับเต็ม
                            </a>
                            <a href="companies.php" class="ceo-action-btn outline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                สถานประกอบการ
                            </a>
                            <a href="company-locations.php" class="ceo-action-btn outline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                                แผนที่ที่ตั้งบริษัท
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- ========== END CEO DASHBOARD ========== -->
            
            <?php elseif ($isAdmin): ?>
                <?php 
                // Get Announcements
                require_once __DIR__ . '/../includes/news.php';
                $announcements = getAnnouncements(3, 'active');
                ?>
                
                <!-- Announcements -->
                <?php if (!empty($announcements)): ?>
                <div class="card mb-6 animate-fade-in-up">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to right, #fff, #f0f9ff);">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 40px; height: 40px; background: #e0f2fe; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold" style="color: #0c4a6e;">ข่าวประชาสัมพันธ์</h3>
                                <p class="text-xs text-gray-500">ประกาศและข่าวสารล่าสุด</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php foreach ($announcements as $index => $item): ?>
                            <div style="padding: 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; gap: 1rem; align-items: flex-start; <?php echo $index === count($announcements)-1 ? 'border-bottom: none;' : ''; ?>">
                                <div style="flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <?php if (strtotime($item['created_at']) > strtotime('-3 days')): ?>
                                            <span class="badge badge-primary" style="margin-left: 0.5rem; font-size: 0.7rem;">ใหม่</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p style="font-size: 0.875rem; color: var(--gray-600); line-height: 1.6;">
                                        <?php echo nl2br(htmlspecialchars($item['content'])); ?>
                                    </p>
                                    <div style="margin-top: 0.75rem; font-size: 0.75rem; color: var(--gray-400); display: flex; align-items: center; gap: 0.5rem;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="16" y1="2" x2="16" y2="6"></line>
                                            <line x1="8" y1="2" x2="8" y2="6"></line>
                                            <line x1="3" y1="10" x2="21" y2="10"></line>
                                        </svg>
                                        <?php echo (new DateTime($item['created_at']))->format('d/m/Y H:i'); ?>
                                        <span style="margin: 0 4px;">•</span>
                                        โดย <?php echo htmlspecialchars($item['author_name'] ?? 'Admin'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Admin/Auditor Dashboard -->
                <div class="dashboard-stats">
                    <div class="stat-card animate-fade-in-up stagger-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" data-counter="<?php echo $stats['total'] ?? 0; ?>">0</div>
                                <div class="stat-label">สถานประกอบการทั้งหมด</div>
                            </div>
                            <div class="stat-icon" style="background-color: var(--primary-100); color: var(--primary-600);">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card animate-fade-in-up stagger-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" data-counter="<?php echo $stats['submitted_count'] ?? 0; ?>">0</div>
                                <div class="stat-label">รอการตรวจสอบ</div>
                            </div>
                            <div class="stat-icon" style="background-color: var(--warning-light); color: var(--warning);">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card animate-fade-in-up stagger-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value" data-counter="<?php echo $stats['completed_count'] ?? 0; ?>">0</div>
                                <div class="stat-label">ประเมินเสร็จสิ้น</div>
                            </div>
                            <div class="stat-icon" style="background-color: var(--success-light); color: var(--success);">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card animate-fade-in-up stagger-4">
                        <div class="stat-header">
                            <div>
                                <div class="stat-value"><?php echo number_format($stats['avg_score'] ?? 0, 0); ?></div>
                                <div class="stat-label">คะแนนเฉลี่ย</div>
                            </div>
                            <div class="stat-icon" style="background-color: var(--pillar-m4-light); color: var(--pillar-m4);">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="20" x2="18" y2="10"/>
                                    <line x1="12" y1="20" x2="12" y2="4"/>
                                    <line x1="6" y1="20" x2="6" y2="14"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="card animate-fade-in-up stagger-5">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold">การกระจายตามระดับ</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="levelChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card animate-fade-in-up stagger-6">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold">สถานะการประเมิน</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Assessments -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="text-lg font-semibold">การประเมินล่าสุด</h3>
                        <a href="assessments.php" class="btn btn-sm btn-outline">ดูทั้งหมด</a>
                    </div>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>สถานประกอบการ</th>
                                    <th>รอบการประเมิน</th>
                                    <th>สถานะ</th>
                                    <th>คะแนน</th>
                                    <th>ระดับ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAssessments as $item): ?>
                                    <?php $levelInfo = getLevelInfo($item['hicm_level']); ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div style="width: 36px; height: 36px; min-width: 36px; border-radius: 50%; overflow: hidden; background: #f1f5f9; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center;">
                                                    <?php 
                                                    $avatar = $item['company_owner_avatar'] ?? $item['logo'] ?? 'default';
                                                    if ($avatar && $avatar !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatar)):
                                                    ?>
                                                        <img src="<?php echo getBaseUrl(); ?>/assets/uploads/avatars/<?php echo $avatar; ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;">
                                                    <?php else: ?>
                                                        <span style="font-weight: 700; color: #64748b; font-size: 0.85rem;"><?php echo mb_substr($item['company_name'], 0, 1); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($item['company_name']); ?></div>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php 
                                                        $dashIndustries = explode('|', $item['industry_type'] ?? '');
                                                        if ($item['industry_type']):
                                                            foreach ($dashIndustries as $dind):
                                                        ?>
                                                            <span class="text-[9px] bg-gray-100 text-gray-600 px-1 rounded"><?php echo htmlspecialchars(trim($dind)); ?></span>
                                                        <?php 
                                                            endforeach;
                                                        else:
                                                        ?>
                                                            <span class="text-[9px] text-gray-400 italic">-</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $item['period_name']; ?> (<?php echo $item['year']; ?>)</td>
                                        <td>
                                            <?php 
                                            $statusLabels = [
                                                'draft' => ['label' => 'ร่าง', 'class' => 'badge-secondary'],
                                                'submitted' => ['label' => 'รอตรวจสอบ', 'class' => 'badge-warning'],
                                                'under_review' => ['label' => 'กำลังตรวจสอบ', 'class' => 'badge-primary'],
                                                'evaluated' => ['label' => 'ประเมินแล้ว', 'class' => 'badge-info'],
                                                'completed' => ['label' => 'เสร็จสิ้น', 'class' => 'badge-success']
                                            ];
                                            $status = $statusLabels[$item['status']] ?? $statusLabels['draft'];
                                            ?>
                                            <span class="badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                                        </td>
                                        <td>
                                            <span class="score-display <?php echo $item['final_score'] >= 700 ? 'score-excellent' : ($item['final_score'] >= 600 ? 'score-good' : 'score-average'); ?>">
                                                <?php echo number_format($item['final_score'], 0); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: <?php echo $levelInfo['bg']; ?>; color: <?php echo $levelInfo['color']; ?>">
                                                <?php echo $levelInfo['name']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="assessment-view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline">ดูรายละเอียด</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Company Dashboard -->
                <?php 
                // Get Announcements
                require_once __DIR__ . '/../includes/news.php';
                $announcements = getAnnouncements(3, 'active');
                ?>
                
                <!-- Announcements -->
                <?php if (!empty($announcements)): ?>
                <div style="margin-bottom: 1.5rem;">
                    <div class="card animate-fade-in-up" style="margin: 0;">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to right, #fff, #f0f9ff);">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 40px; height: 40px; background: #e0f2fe; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #0284c7;">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold" style="color: #0c4a6e;">ข่าวประชาสัมพันธ์</h3>
                                    <p class="text-xs text-gray-500">ประกาศและข่าวสารล่าสุด</p>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="padding: 0; max-height: 400px; overflow-y: auto;">
                            <?php foreach ($announcements as $index => $item): ?>
                                <div style="padding: 1rem; border-bottom: 1px solid #f1f5f9; <?php echo $index === count($announcements)-1 ? 'border-bottom: none;' : ''; ?>">
                                    <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); margin-bottom: 0.5rem;">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                        <?php if (strtotime($item['created_at']) > strtotime('-3 days')): ?>
                                            <span class="badge badge-primary" style="margin-left: 0.5rem; font-size: 0.65rem;">ใหม่</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p style="font-size: 0.85rem; color: var(--gray-600); line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars(mb_substr($item['content'], 0, 150))); ?><?php echo mb_strlen($item['content']) > 150 ? '...' : ''; ?>
                                    </p>
                                    <div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--gray-400);">
                                        <?php echo (new DateTime($item['created_at']))->format('d/m/Y'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($assessment)): ?>
                    <style>
                        /* Company Dashboard Pro Animations */
                        @keyframes fadeInUp {
                            from { opacity: 0; transform: translateY(30px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                        @keyframes fadeInLeft {
                            from { opacity: 0; transform: translateX(-30px); }
                            to { opacity: 1; transform: translateX(0); }
                        }
                        @keyframes fadeInRight {
                            from { opacity: 0; transform: translateX(30px); }
                            to { opacity: 1; transform: translateX(0); }
                        }
                        @keyframes scaleIn {
                            from { opacity: 0; transform: scale(0.8); }
                            to { opacity: 1; transform: scale(1); }
                        }
                        @keyframes pulse {
                            0%, 100% { transform: scale(1); }
                            50% { transform: scale(1.05); }
                        }
                        @keyframes float {
                            0%, 100% { transform: translateY(0); }
                            50% { transform: translateY(-10px); }
                        }
                        @keyframes shimmer {
                            0% { background-position: -200% 0; }
                            100% { background-position: 200% 0; }
                        }
                        @keyframes countUp {
                            from { opacity: 0; transform: translateY(20px); }
                            to { opacity: 1; transform: translateY(0); }
                        }
                        @keyframes gradientShift {
                            0% { background-position: 0% 50%; }
                            50% { background-position: 100% 50%; }
                            100% { background-position: 0% 50%; }
                        }
                        @keyframes borderGlow {
                            0%, 100% { box-shadow: 0 0 5px rgba(99, 102, 241, 0.3); }
                            50% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.6); }
                        }
                        @keyframes iconBounce {
                            0%, 100% { transform: translateY(0); }
                            50% { transform: translateY(-5px); }
                        }
                        
                        .company-dashboard-grid {
                            display: grid;
                            grid-template-columns: 1fr 1fr 1fr;
                            gap: 1.25rem;
                            margin-bottom: 1.5rem;
                        }
                        
                        @media (max-width: 1024px) {
                            .company-dashboard-grid {
                                grid-template-columns: 1fr 1fr;
                            }
                            .company-dashboard-grid > :nth-child(2) {
                                grid-column: span 2;
                                order: -1;
                            }
                        }
                        
                        @media (max-width: 768px) {
                            .company-dashboard-grid {
                                grid-template-columns: 1fr;
                            }
                            .company-dashboard-grid > :nth-child(2) {
                                grid-column: span 1;
                                order: 0;
                            }
                            .score-main {
                                flex-direction: column;
                                text-align: center;
                            }
                            .score-circle-mini {
                                width: 90px;
                                height: 90px;
                            }
                            .score-number-main {
                                font-size: 1.5rem;
                            }
                            .pillar-icon {
                                width: 28px;
                                height: 28px;
                            }
                        }
                        
                        .profile-card-pro {
                            animation: fadeInLeft 0.6s ease-out both;
                            animation-delay: 0.1s;
                            border-radius: 20px;
                            overflow: hidden;
                            box-shadow: 0 10px 40px -10px rgba(99, 102, 241, 0.3);
                            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                            border: 1px solid rgba(99, 102, 241, 0.1);
                        }
                        
                        .profile-card-pro:hover {
                            transform: translateY(-5px);
                            box-shadow: 0 20px 60px -15px rgba(99, 102, 241, 0.4);
                        }
                        
                        .profile-header-gradient {
                            background: linear-gradient(-45deg, #667eea, #764ba2, #6B8DD6, #8E37D7);
                            background-size: 400% 400%;
                            animation: gradientShift 15s ease infinite;
                            position: relative;
                            padding: 2rem 1.5rem;
                            text-align: center;
                        }
                        
                        .profile-avatar-wrapper {
                            position: relative;
                            width: 90px;
                            height: 90px;
                            margin: 0 auto 1rem;
                            animation: float 4s ease-in-out infinite;
                        }
                        
                        .profile-avatar {
                            width: 100%;
                            height: 100%;
                            border-radius: 50%;
                            background: white;
                            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            overflow: hidden;
                            border: 4px solid rgba(255,255,255,0.3);
                        }
                        
                        .profile-avatar-ring {
                            position: absolute;
                            top: -5px; left: -5px; right: -5px; bottom: -5px;
                            border: 2px dashed rgba(255,255,255,0.4);
                            border-radius: 50%;
                            animation: spin 20s linear infinite;
                        }
                        
                        @keyframes spin {
                            from { transform: rotate(0deg); }
                            to { transform: rotate(360deg); }
                        }
                        
                        .profile-info-item {
                            display: flex;
                            align-items: flex-start;
                            gap: 0.75rem;
                            padding: 0.75rem;
                            border-radius: 12px;
                            transition: all 0.3s ease;
                            animation: fadeInUp 0.5s ease-out both;
                        }
                        
                        .profile-info-item:hover {
                            background: linear-gradient(135deg, var(--gray-50), white);
                            transform: translateX(5px);
                        }
                        
                        .profile-info-icon {
                            width: 36px;
                            height: 36px;
                            border-radius: 10px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-shrink: 0;
                            transition: transform 0.3s ease;
                        }
                        
                        .profile-info-item:hover .profile-info-icon {
                            transform: scale(1.1);
                            animation: iconBounce 0.5s ease;
                        }
                        
                        .score-card-pro {
                            animation: fadeInUp 0.6s ease-out both;
                            animation-delay: 0.2s;
                            border-radius: 20px;
                            overflow: hidden;
                            background: #ffffff;
                            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
                            border: 1px solid rgba(0,0,0,0.04);
                            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                            position: relative;
                            height: 100%;
                            display: flex;
                            flex-direction: column;
                        }
                        
                        .score-card-pro:hover {
                            transform: translateY(-4px);
                            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
                        }
                        
                        /* Score Header */
                        .score-header {
                            padding: 1.25rem 1.5rem;
                            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
                            border-bottom: 1px solid #f1f5f9;
                        }
                        
                        .score-header-title {
                            font-size: 1rem;
                            font-weight: 600;
                            color: #000000;
                            text-transform: uppercase;
                            letter-spacing: 1.5px;
                            display: flex;
                            align-items: center;
                            gap: 0.5rem;
                        }
                        
                        /* Main Score Section */
                        .score-main {
                            padding: 1.5rem;
                            display: flex;
                            align-items: center;
                            gap: 1.5rem;
                            border-bottom: 1px solid #f1f5f9;
                        }
                        
                        .score-circle-mini {
                            position: relative;
                            width: 100px;
                            height: 100px;
                            flex-shrink: 0;
                        }
                        
                        .score-circle-mini svg {
                            width: 100%;
                            height: 100%;
                            transform: rotate(-90deg);
                        }
                        
                        .score-circle-mini .ring-bg {
                            fill: none;
                            stroke: #f1f5f9;
                            stroke-width: 8;
                        }
                        
                        .score-circle-mini .ring-progress {
                            fill: none;
                            stroke: url(#scoreGradientMini);
                            stroke-width: 8;
                            stroke-linecap: round;
                            stroke-dasharray: 251;
                            stroke-dashoffset: 251;
                            animation: ringFill 1.5s ease-out forwards;
                            animation-delay: 0.3s;
                        }
                        
                        @keyframes ringFill {
                            to { stroke-dashoffset: var(--offset); }
                        }
                        
                        .score-circle-value {
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            text-align: center;
                        }
                        
                        .score-number-main {
                            font-size: 1.75rem;
                            font-weight: 700;
                            color: #1e293b;
                            line-height: 1;
                        }
                        
                        .score-number-sub {
                            font-size: 0.65rem;
                            color: #94a3b8;
                            margin-top: 2px;
                        }
                        
                        .score-info {
                            flex: 1;
                            min-width: 0;
                        }
                        
                        .score-level-badge {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.375rem;
                            padding: 0.375rem 0.75rem;
                            border-radius: 20px;
                            font-size: 0.7rem;
                            font-weight: 600;
                            margin-bottom: 0.5rem;
                        }
                        
                        .score-level-badge .dot {
                            width: 6px;
                            height: 6px;
                            border-radius: 50%;
                        }
                        
                        .score-level-name {
                            font-size: 1rem;
                            font-weight: 600;
                            color: #1e293b;
                            margin-bottom: 0.25rem;
                        }
                        
                        .score-level-desc {
                            font-size: 0.75rem;
                            color: #64748b;
                        }
                        
                        /* Pillar Progress Section */
                        .pillars-section {
                            padding: 1rem 1.5rem;
                            flex: 1;
                        }
                        
                        .pillar-item {
                            display: flex;
                            align-items: center;
                            gap: 0.75rem;
                            padding: 0.625rem 0;
                            border-bottom: 1px solid #f8fafc;
                        }
                        
                        .pillar-item:last-child {
                            border-bottom: none;
                        }
                        
                        .pillar-icon {
                            width: 32px;
                            height: 32px;
                            border-radius: 8px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-shrink: 0;
                        }
                        
                        .pillar-content {
                            flex: 1;
                            min-width: 0;
                        }
                        
                        .pillar-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: 0.375rem;
                        }
                        
                        .pillar-name {
                            font-size: 0.75rem;
                            font-weight: 600;
                            color: #475569;
                        }
                        
                        .pillar-percent {
                            font-size: 0.7rem;
                            font-weight: 700;
                            color: #1e293b;
                        }
                        
                        .pillar-bar {
                            height: 6px;
                            background: #f1f5f9;
                            border-radius: 3px;
                            overflow: hidden;
                        }
                        
                        .pillar-bar-fill {
                            height: 100%;
                            border-radius: 3px;
                            transition: width 1s ease-out;
                            animation: barGrow 1.2s ease-out forwards;
                        }
                        
                        @keyframes barGrow {
                            from { width: 0; }
                        }
                        
                        /* Score Action */
                        .score-action-section {
                            padding: 1rem 1.5rem;
                            background: #fafbfc;
                            margin-top: auto;
                        }
                        
                        .btn-score-action {
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 0.5rem;
                            width: 100%;
                            padding: 0.75rem 1rem;
                            border-radius: 10px;
                            font-weight: 600;
                            font-size: 0.8rem;
                            transition: all 0.3s ease;
                            text-decoration: none;
                        }
                        
                        .btn-score-primary {
                            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                            color: white;
                            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
                        }
                        
                        .btn-score-primary:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                        }
                        
                        .btn-score-success {
                            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                            color: white;
                            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
                        }
                        
                        .btn-score-success:hover {
                            transform: translateY(-1px);
                            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
                        }
                        
                        .status-waiting {
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 0.5rem;
                            padding: 0.75rem 1rem;
                            background: #fef3c7;
                            color: #92400e;
                            border-radius: 10px;
                            font-size: 0.8rem;
                            font-weight: 500;
                        }
                        
                        .status-waiting svg {
                            animation: spin 2s linear infinite;
                        }
                        
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                        
                        .quick-menu-card {
                            animation: fadeInRight 0.6s ease-out both;
                            animation-delay: 0.3s;
                            border-radius: 20px;
                            overflow: hidden;
                            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1);
                            border: 1px solid var(--gray-100);
                            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        }
                        
                        .quick-menu-card:hover {
                            transform: translateY(-5px);
                            box-shadow: 0 20px 60px -15px rgba(0,0,0,0.15);
                        }
                        
                        .quick-menu-item {
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                            padding: 1rem 1.25rem;
                            border-radius: 12px;
                            margin: 0.5rem;
                            transition: all 0.3s ease;
                            text-decoration: none;
                            color: var(--gray-700);
                            position: relative;
                            overflow: hidden;
                        }
                        
                        .quick-menu-item::before {
                            content: '';
                            position: absolute;
                            left: 0;
                            top: 0;
                            height: 100%;
                            width: 0;
                            background: linear-gradient(90deg, var(--primary-50), transparent);
                            transition: width 0.3s ease;
                        }
                        
                        .quick-menu-item:hover::before {
                            width: 100%;
                        }
                        
                        .quick-menu-item:hover {
                            background: var(--gray-50);
                            transform: translateX(5px);
                            color: var(--primary-600);
                        }
                        
                        .quick-menu-icon {
                            width: 44px;
                            height: 44px;
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transition: all 0.3s ease;
                            position: relative;
                            z-index: 1;
                        }
                        
                        .quick-menu-item:hover .quick-menu-icon {
                            transform: scale(1.1) rotate(-5deg);
                        }
                        
                        .quick-menu-text {
                            font-weight: 500;
                            position: relative;
                            z-index: 1;
                        }
                        
                        .quick-menu-arrow {
                            margin-left: auto;
                            opacity: 0;
                            transform: translateX(-10px);
                            transition: all 0.3s ease;
                            position: relative;
                            z-index: 1;
                        }
                        
                        .quick-menu-item:hover .quick-menu-arrow {
                            opacity: 1;
                            transform: translateX(0);
                        }
                    </style>
                    
                    <div class="company-dashboard-grid">
                        <!-- LEFT: Company Profile Card -->
                        <?php if (isset($companyInfo) && $companyInfo): ?>
                        <div class="card profile-card-pro">
                            <div class="profile-header-gradient">
                                <!-- Background Pattern -->
                                <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                                
                                <!-- Company Logo/Avatar -->
                                <div class="profile-avatar-wrapper">
                                    <div class="profile-avatar-ring"></div>
                                    <div class="profile-avatar">
                                        <?php 
                                        $avatarFile = $companyInfo['company_owner_avatar'] ?? $companyInfo['logo'] ?? 'default';
                                        $hasAvatar = ($avatarFile && $avatarFile !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatarFile));
                                        
                                        if ($hasAvatar): 
                                        ?>
                                            <img src="<?php echo getBaseUrl() . '/assets/uploads/avatars/' . $avatarFile; ?>" alt="Company Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); display: flex; align-items: center; justify-content: center;">
                                                <svg width="45" height="45" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.5">
                                                    <path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4M5 21V10.85M19 21V10.85M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Company Name -->
                                <h4 style="color: white; font-size: 1.1rem; font-weight: 700; margin: 0; text-shadow: 0 2px 10px rgba(0,0,0,0.2); position: relative;">
                                    <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'ชื่อบริษัท'); ?>
                                </h4>
                                <?php if (!empty($companyInfo['company_name_en'])): ?>
                                <p style="color: rgba(255,255,255,0.9); font-size: 0.75rem; margin: 0.35rem 0 0; position: relative;">
                                    <?php echo htmlspecialchars($companyInfo['company_name_en']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="card-body" style="padding: 1.25rem;">
                                <!-- Company Details -->
                                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                    
                                    <?php if (!empty($companyInfo['industry_type'])): ?>
                                    <div class="profile-info-item" style="animation-delay: 0.2s;">
                                        <div class="profile-info-icon" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2">
                                                <path d="M2 20h20M5 20V8l7-4 7 4v12M9 20v-4h6v4"/>
                                            </svg>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">ประเภทอุตสาหกรรม</div>
                                            <div style="font-size: 0.85rem; color: var(--gray-800); font-weight: 600; margin-top: 0.125rem;">
                                                <?php 
                                                $industries = is_array($companyInfo['industry_type']) 
                                                    ? $companyInfo['industry_type'] 
                                                    : explode('|', $companyInfo['industry_type'] ?? '');
                                                echo htmlspecialchars(is_array($industries) && count($industries) > 0 ? implode(', ', array_filter($industries)) : '-');
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($companyInfo['employee_count'])): ?>
                                    <div class="profile-info-item" style="animation-delay: 0.3s;">
                                        <div class="profile-info-icon" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                <circle cx="9" cy="7" r="4"/>
                                                <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                                            </svg>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">จำนวนพนักงาน</div>
                                            <div style="font-size: 0.85rem; color: var(--gray-800); font-weight: 600; margin-top: 0.125rem;">
                                                <?php echo number_format($companyInfo['employee_count']); ?> คน
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($companyInfo['contact_name'])): ?>
                                    <div class="profile-info-item" style="animation-delay: 0.4s;">
                                        <div class="profile-info-icon" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
                                                <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                                <circle cx="12" cy="7" r="4"/>
                                            </svg>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">ผู้ติดต่อ</div>
                                            <div style="font-size: 0.85rem; color: var(--gray-800); font-weight: 600; margin-top: 0.125rem;">
                                                <?php echo htmlspecialchars($companyInfo['contact_name']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($companyInfo['contact_phone'])): ?>
                                    <div class="profile-info-item" style="animation-delay: 0.5s;">
                                        <div class="profile-info-icon" style="background: linear-gradient(135deg, #ede9fe, #ddd6fe);">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2">
                                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                                            </svg>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">เบอร์โทร</div>
                                            <div style="font-size: 0.85rem; color: var(--gray-800); font-weight: 600; margin-top: 0.125rem;">
                                                <?php echo htmlspecialchars($companyInfo['contact_phone']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                </div>
                                
                                <!-- Edit Profile Button -->
                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-100);">
                                    <a href="company-profile.php" class="btn btn-outline w-full" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 12px; font-weight: 600; transition: all 0.3s ease;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        แก้ไขข้อมูลบริษัท
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                        
                        <!-- CENTER: Overall Score Card -->
                        <?php 
                        $scorePercent = min(100, (($assessment['final_score'] ?? 0) / 1000) * 100);
                        $progressOffset = 251 - (251 * $scorePercent / 100);
                        $levelInfo = getLevelInfo($assessment['hicm_level'] ?? 1); 
                        
                        // Pillar colors & data
                        $pillarConfig = [
                            'H1' => ['name' => 'สุขภาพ', 'color' => '#10B981', 'bg' => '#D1FAE5', 'max' => 250],
                            'I2' => ['name' => 'ความปลอดภัย', 'color' => '#3B82F6', 'bg' => '#DBEAFE', 'max' => 250],
                            'C3' => ['name' => 'การมีส่วนร่วมกับชุมชน', 'color' => '#F59E0B', 'bg' => '#FEF3C7', 'max' => 250],
                            'M4' => ['name' => 'การบริหาร', 'color' => '#8B5CF6', 'bg' => '#EDE9FE', 'max' => 250]
                        ];
                        $pillarsData = $assessment['pillars'] ?? [];
                        ?>
                        <div class="card score-card-pro">
                            <!-- Header -->
                            <div class="score-header">
                                <div class="score-header-title">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                    คะแนนรวมการประเมิน
                                </div>
                            </div>
                            
                            <!-- Main Score -->
                            <div class="score-main">
                                <div class="score-circle-mini">
                                    <svg viewBox="0 0 100 100">
                                        <defs>
                                            <linearGradient id="scoreGradientMini" x1="0%" y1="0%" x2="100%" y2="100%">
                                                <stop offset="0%" stop-color="#3b82f6"/>
                                                <stop offset="100%" stop-color="#8b5cf6"/>
                                            </linearGradient>
                                        </defs>
                                        <circle class="ring-bg" cx="50" cy="50" r="40"/>
                                        <circle class="ring-progress" cx="50" cy="50" r="40" 
                                                style="--offset: <?php echo $progressOffset; ?>"/>
                                    </svg>
                                    <div class="score-circle-value">
                                        <div class="score-number-main"><?php echo number_format($assessment['final_score'] ?? 0, 0); ?></div>
                                        <div class="score-number-sub">/ 1,000</div>
                                    </div>
                                </div>
                                <div class="score-info">
                                    <div class="score-level-badge" style="background: <?php echo $levelInfo['bg']; ?>; color: <?php echo $levelInfo['color']; ?>;">
                                        <span class="dot" style="background: <?php echo $levelInfo['color']; ?>;"></span>
                                        Level <?php echo $assessment['hicm_level'] ?? 1; ?>
                                    </div>
                                    <div class="score-level-name"><?php echo $levelInfo['name']; ?></div>
                                    <div class="score-level-desc"><?php echo $levelInfo['name_en']; ?></div>
                                </div>
                            </div>
                            
                            <!-- Pillar Progress Bars -->
                            <div class="pillars-section">
                                <?php if (!empty($pillarsData)): ?>
                                <?php foreach ($pillarsData as $pillarCode => $pillar): 
                                    $cfg = $pillarConfig[$pillarCode] ?? ['name' => $pillarCode, 'color' => '#6B7280', 'bg' => '#F3F4F6'];
                                    $indicators = $pillar['indicators'] ?? [];
                                    $selfTotal = array_sum(array_column($indicators, 'self_score'));
                                    $maxScore = count($indicators);
                                    $percentage = $maxScore > 0 ? round(($selfTotal / $maxScore) * 100) : 0;
                                ?>
                                <div class="pillar-item">
                                    <div class="pillar-icon" style="background: <?php echo $cfg['bg']; ?>;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?php echo $cfg['color']; ?>" stroke-width="2">
                                            <?php if ($pillarCode === 'H1'): ?>
                                                <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                            <?php elseif ($pillarCode === 'I2'): ?>
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                            <?php elseif ($pillarCode === 'C3'): ?>
                                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                            <?php else: ?>
                                                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                                            <?php endif; ?>
                                        </svg>
                                    </div>
                                    <div class="pillar-content">
                                        <div class="pillar-header">
                                            <span class="pillar-name"><?php echo $pillarCode; ?> <?php echo $cfg['name']; ?></span>
                                            <span class="pillar-percent"><?php echo $percentage; ?>%</span>
                                        </div>
                                        <div class="pillar-bar">
                                            <div class="pillar-bar-fill" style="width: <?php echo $percentage; ?>%; background: linear-gradient(90deg, <?php echo $cfg['color']; ?>, <?php echo $cfg['color']; ?>dd);"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div style="text-align: center; padding: 1rem; color: #94a3b8; font-size: 0.8rem;">
                                    ยังไม่มีข้อมูลการประเมิน
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Action Button -->
                            <div class="score-action-section">
                                <?php if (($assessment['status'] ?? 'draft') === 'draft'): ?>
                                    <a href="assessment-form.php" class="btn-score-action btn-score-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        ทำแบบประเมิน
                                    </a>
                                <?php elseif (($assessment['status'] ?? '') === 'submitted'): ?>
                                    <div class="status-waiting">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        รอการตรวจสอบจากกรรมการ
                                    </div>
                                <?php else: ?>
                                    <a href="assessment-result.php" class="btn-score-action btn-score-success">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        ดูผลการประเมิน
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- RIGHT: Quick Actions -->
                        <div class="card quick-menu-card">
                            <div class="card-header" style="background: linear-gradient(135deg, var(--gray-50), white); border-bottom: 1px solid var(--gray-100);">
                                <h3 style="font-size: 1rem; font-weight: 700; color: var(--gray-800); display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-500)" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"/>
                                        <rect x="14" y="3" width="7" height="7"/>
                                        <rect x="14" y="14" width="7" height="7"/>
                                        <rect x="3" y="14" width="7" height="7"/>
                                    </svg>
                                    เมนูลัด
                                </h3>
                            </div>
                            <div class="card-body" style="padding: 0.5rem;">
                                <a href="assessment-form.php" class="quick-menu-item">
                                    <div class="quick-menu-icon" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                    </div>
                                    <span class="quick-menu-text">ทำแบบประเมิน</span>
                                    <svg class="quick-menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </a>
                                <a href="assessment-result.php" class="quick-menu-item">
                                    <div class="quick-menu-icon" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                    </div>
                                    <span class="quick-menu-text">ดูผลการประเมิน</span>
                                    <svg class="quick-menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </a>
                                <a href="company-profile.php" class="quick-menu-item">
                                    <div class="quick-menu-icon" style="background: linear-gradient(135deg, #ede9fe, #ddd6fe);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                    </div>
                                    <span class="quick-menu-text">ข้อมูลบริษัท</span>
                                    <svg class="quick-menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </a>
                                <a href="change-password.php" class="quick-menu-item">
                                    <div class="quick-menu-icon" style="background: linear-gradient(135deg, #fef3c7, #fde68a);">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0110 0v4"/>
                                        </svg>
                                    </div>
                                    <span class="quick-menu-text">เปลี่ยนรหัสผ่าน</span>
                                    <svg class="quick-menu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pillar Scores -->
                    <div class="card mb-6">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold">คะแนนแยกตามด้าน</h3>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php 
                                $pillarColors = [
                                    'H1' => ['color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => 'heart-pulse'],
                                    'I2' => ['color' => '#3B82F6', 'bg' => '#DBEAFE', 'icon' => 'shield-check'],
                                    'C3' => ['color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => 'users'],
                                    'M4' => ['color' => '#8B5CF6', 'bg' => '#EDE9FE', 'icon' => 'chart-bar']
                                ];
                                $pillarsList = $assessment['pillars'] ?? [];
                                if (!empty($pillarsList)):
                                foreach ($pillarsList as $pillarCode => $pillar): 
                                    $pc = $pillarColors[$pillarCode] ?? ['color' => '#6B7280', 'bg' => '#F3F4F6'];
                                    $indicators = $pillar['indicators'] ?? [];
                                    $selfTotal = array_sum(array_column($indicators, 'self_score'));
                                    $maxScore = count($indicators);
                                    $percentage = $maxScore > 0 ? ($selfTotal / $maxScore) * 100 : 0;
                                    $weightedScore = $maxScore > 0 ? ($selfTotal / $maxScore) * ($pillar['weight'] ?? 250) : 0;
                                ?>
                                    <div class="pillar-score-card <?php echo strtolower($pillarCode); ?>" style="border-left-color: <?php echo $pc['color']; ?>">
                                        <div class="pillar-score-header">
                                            <div class="pillar-score-icon" style="background-color: <?php echo $pc['bg']; ?>; color: <?php echo $pc['color']; ?>">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <?php if ($pillarCode === 'H1'): ?>
                                                        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                                                    <?php elseif ($pillarCode === 'I2'): ?>
                                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                                        <path d="M9 12l2 2 4-4"/>
                                                    <?php elseif ($pillarCode === 'C3'): ?>
                                                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                                        <circle cx="9" cy="7" r="4"/>
                                                        <path d="M23 21v-2a4 4 0 00-3-3.87"/>
                                                        <path d="M16 3.13a4 4 0 010 7.75"/>
                                                    <?php else: ?>
                                                        <line x1="18" y1="20" x2="18" y2="10"/>
                                                        <line x1="12" y1="20" x2="12" y2="4"/>
                                                        <line x1="6" y1="20" x2="6" y2="14"/>
                                                    <?php endif; ?>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="pillar-score-title"><?php echo $pillar['name']; ?></div>
                                                <div class="pillar-score-value" style="color: <?php echo $pc['color']; ?>">
                                                    <?php echo number_format($weightedScore, 0); ?> / <?php echo $pillar['weight']; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="progress-bar" style="margin-top: 1rem;">
                                            <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $pc['color']; ?>"></div>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.875rem; color: var(--gray-500);">
                                            <span>ความคืบหน้า <?php echo number_format($percentage, 0); ?>%</span>
                                            <span><?php echo count(array_filter($indicators, fn($i) => ($i['self_score'] ?? 0) > 0)); ?>/<?php echo count($indicators); ?> ตัวชี้วัด</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="col-span-2 text-center text-gray-500 py-4">ยังไม่มีข้อมูลการประเมิน</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Radar Chart -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="text-lg font-semibold">แผนภาพความสมดุล 4 ด้าน</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="radarChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assessment History Section -->
                    <?php if (!empty($assessmentHistory) && count($assessmentHistory) > 0): ?>
                    <div class="card" style="margin-top: 1.5rem;">
                        <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                            <h3 style="display: flex; align-items: center; gap: 0.5rem; font-size: 1.1rem; font-weight: 600; color: var(--gray-800); margin: 0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-500)" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                ประวัติการประเมินของบริษัท
                            </h3>
                            <span style="font-size: 0.85rem; color: var(--gray-500);">
                                ทั้งหมด <?php echo count($assessmentHistory); ?> รอบ
                            </span>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <style>
                                .history-table {
                                    width: 100%;
                                    border-collapse: collapse;
                                }
                                .history-table th {
                                    background: var(--gray-50);
                                    padding: 0.875rem 1rem;
                                    text-align: left;
                                    font-size: 0.75rem;
                                    font-weight: 600;
                                    color: var(--gray-600);
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                    border-bottom: 1px solid var(--gray-200);
                                }
                                .history-table td {
                                    padding: 1rem;
                                    border-bottom: 1px solid var(--gray-100);
                                    vertical-align: middle;
                                }
                                .history-table tr:last-child td {
                                    border-bottom: none;
                                }
                                .history-table tr:hover {
                                    background: var(--gray-50);
                                }
                                .history-period {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 0.25rem;
                                }
                                .history-period-name {
                                    font-weight: 600;
                                    color: var(--gray-800);
                                    font-size: 0.9rem;
                                }
                                .history-period-date {
                                    font-size: 0.75rem;
                                    color: var(--gray-500);
                                }
                                .history-score {
                                    display: flex;
                                    align-items: center;
                                    gap: 0.5rem;
                                }
                                .history-score-value {
                                    font-size: 1.25rem;
                                    font-weight: 700;
                                }
                                .history-score-max {
                                    font-size: 0.75rem;
                                    color: var(--gray-400);
                                }
                                .history-level {
                                    display: inline-flex;
                                    align-items: center;
                                    gap: 0.375rem;
                                    padding: 0.375rem 0.75rem;
                                    border-radius: 9999px;
                                    font-size: 0.8rem;
                                    font-weight: 600;
                                }
                                .history-status {
                                    display: inline-flex;
                                    align-items: center;
                                    padding: 0.25rem 0.75rem;
                                    border-radius: 9999px;
                                    font-size: 0.75rem;
                                    font-weight: 500;
                                }
                                .history-status.draft { background: var(--gray-100); color: var(--gray-600); }
                                .history-status.submitted { background: #fef3c7; color: #d97706; }
                                .history-status.under_review { background: #dbeafe; color: #2563eb; }
                                .history-status.evaluated { background: #ede9fe; color: #7c3aed; }
                                .history-status.completed { background: #d1fae5; color: #059669; }
                                .history-actions {
                                    display: flex;
                                    gap: 0.5rem;
                                }
                                .history-action-btn {
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    width: 32px;
                                    height: 32px;
                                    border-radius: 8px;
                                    border: 1px solid var(--gray-200);
                                    background: white;
                                    color: var(--gray-500);
                                    cursor: pointer;
                                    transition: all 0.2s;
                                    text-decoration: none;
                                }
                                .history-action-btn:hover {
                                    background: var(--primary-50);
                                    border-color: var(--primary-300);
                                    color: var(--primary-600);
                                }
                                .history-progress {
                                    display: flex;
                                    flex-direction: column;
                                    gap: 0.25rem;
                                }
                                .history-progress-bar {
                                    width: 100px;
                                    height: 6px;
                                    background: var(--gray-200);
                                    border-radius: 3px;
                                    overflow: hidden;
                                }
                                .history-progress-fill {
                                    height: 100%;
                                    border-radius: 3px;
                                    transition: width 0.3s ease;
                                }
                                .history-current {
                                    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
                                }
                                .history-current td {
                                    background: linear-gradient(135deg, rgba(59,130,246,0.05), rgba(139,92,246,0.05));
                                }
                                @media (max-width: 768px) {
                                    .history-table thead { display: none; }
                                    .history-table tr {
                                        display: block;
                                        padding: 1rem;
                                        border-bottom: 1px solid var(--gray-200);
                                    }
                                    .history-table td {
                                        display: flex;
                                        justify-content: space-between;
                                        padding: 0.5rem 0;
                                        border: none;
                                    }
                                    .history-table td::before {
                                        content: attr(data-label);
                                        font-weight: 600;
                                        color: var(--gray-600);
                                        font-size: 0.8rem;
                                    }
                                }
                            </style>
                            
                            <div style="overflow-x: auto;">
                                <table class="history-table">
                                    <thead>
                                        <tr>
                                            <th>รอบการประเมิน</th>
                                            <th>คะแนน</th>
                                            <th>ระดับ HICM</th>
                                            <th>สถานะ</th>
                                            <th>วันที่ส่ง</th>
                                            <th style="text-align: center;">ดูรายละเอียด</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $currentPeriodId = $assessment['period_id'] ?? null;
                                        foreach ($assessmentHistory as $idx => $hist): 
                                            $isCurrent = ($hist['period_id'] == $currentPeriodId);
                                            $histLevelInfo = getLevelInfo($hist['hicm_level'] ?? 1);
                                            $scorePercent = min(100, (($hist['final_score'] ?? 0) / 1000) * 100);
                                            
                                            $statusLabels = [
                                                'draft' => 'ฉบับร่าง',
                                                'submitted' => 'รอตรวจสอบ',
                                                'under_review' => 'กำลังตรวจ',
                                                'evaluated' => 'ประเมินแล้ว',
                                                'completed' => 'เสร็จสิ้น'
                                            ];
                                        ?>
                                        <tr class="<?php echo $isCurrent ? 'history-current' : ''; ?>">
                                            <td data-label="รอบการประเมิน">
                                                <div class="history-period">
                                                    <span class="history-period-name">
                                                        <?php echo htmlspecialchars($hist['period_name']); ?>
                                                        <?php if ($isCurrent): ?>
                                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; margin-left: 0.5rem; padding: 0.125rem 0.5rem; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: white; border-radius: 9999px; font-size: 0.65rem; font-weight: 600;">
                                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                                ปัจจุบัน
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="history-period-date">
                                                        ปี <?php echo $hist['period_year']; ?> 
                                                        (<?php echo date('d/m/Y', strtotime($hist['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($hist['end_date'])); ?>)
                                                    </span>
                                                </div>
                                            </td>
                                            <td data-label="คะแนน">
                                                <div class="history-progress">
                                                    <div class="history-score">
                                                        <span class="history-score-value" style="color: <?php echo $histLevelInfo['color']; ?>;">
                                                            <?php echo number_format($hist['final_score'] ?? 0, 0); ?>
                                                        </span>
                                                        <span class="history-score-max">/ 1,000</span>
                                                    </div>
                                                    <div class="history-progress-bar">
                                                        <div class="history-progress-fill" style="width: <?php echo $scorePercent; ?>%; background: <?php echo $histLevelInfo['color']; ?>;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td data-label="ระดับ HICM">
                                                <span class="history-level" style="background: <?php echo $histLevelInfo['bg']; ?>; color: <?php echo $histLevelInfo['color']; ?>;">
                                                    <span style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $histLevelInfo['color']; ?>;"></span>
                                                    Level <?php echo $hist['hicm_level'] ?? 1; ?> - <?php echo $histLevelInfo['name']; ?>
                                                </span>
                                            </td>
                                            <td data-label="สถานะ">
                                                <span class="history-status <?php echo $hist['status']; ?>">
                                                    <?php echo $statusLabels[$hist['status']] ?? $hist['status']; ?>
                                                </span>
                                            </td>
                                            <td data-label="วันที่ส่ง">
                                                <?php if ($hist['submitted_at']): ?>
                                                    <span style="font-size: 0.85rem; color: var(--gray-600);">
                                                        <?php echo date('d/m/Y', strtotime($hist['submitted_at'])); ?>
                                                    </span>
                                                    <br>
                                                    <span style="font-size: 0.75rem; color: var(--gray-400);">
                                                        <?php echo date('H:i น.', strtotime($hist['submitted_at'])); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="font-size: 0.8rem; color: var(--gray-400);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="ดูรายละเอียด" style="text-align: center;">
                                                <div class="history-actions" style="justify-content: center;">
                                                    <?php if ($isCurrent): ?>
                                                        <a href="assessment-form.php?period_id=<?php echo $hist['period_id']; ?>" class="history-action-btn" title="ทำแบบประเมิน">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                                <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                            </svg>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="assessment-result.php?id=<?php echo $hist['id']; ?>" class="history-action-btn" title="ดูผลการประเมิน">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                            <circle cx="12" cy="12" r="3"/>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (count($assessmentHistory) > 1): ?>
                            <!-- Score Trend Chart -->
                            <div style="padding: 1.5rem; border-top: 1px solid var(--gray-200);">
                                <h4 style="font-size: 0.9rem; font-weight: 600; color: var(--gray-700); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-500)" stroke-width="2">
                                        <line x1="18" y1="20" x2="18" y2="10"/>
                                        <line x1="12" y1="20" x2="12" y2="4"/>
                                        <line x1="6" y1="20" x2="6" y2="14"/>
                                    </svg>
                                    แนวโน้มคะแนนตามรอบการประเมิน
                                </h4>
                                <div style="height: 200px;">
                                    <canvas id="historyTrendChart"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <!-- No Active Period — with Company Profile -->
                <?php if (isset($companyInfo) && $companyInfo): ?>
                <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; margin-bottom: 1.5rem; animation: fadeInUp 0.6s ease-out both;">
                    <!-- Company Profile Card -->
                    <div style="
                        background: white;
                        border-radius: 20px;
                        overflow: hidden;
                        box-shadow: 0 10px 40px -10px rgba(99, 102, 241, 0.25);
                        border: 1px solid rgba(99, 102, 241, 0.1);
                    ">
                        <div style="
                            background: linear-gradient(-45deg, #667eea, #764ba2, #6B8DD6, #8E37D7);
                            background-size: 400% 400%;
                            position: relative;
                            padding: 2rem 1.5rem;
                            text-align: center;
                        ">
                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; opacity: 0.1; background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
                            <div style="position: relative; width: 90px; height: 90px; margin: 0 auto 1rem;">
                                <div style="position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px; border: 2px dashed rgba(255,255,255,0.4); border-radius: 50%;"></div>
                                <div style="width: 100%; height: 100%; border-radius: 50%; background: white; box-shadow: 0 8px 25px rgba(0,0,0,0.25); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 4px solid rgba(255,255,255,0.3);">
                                    <?php 
                                    $avatarFile = $companyInfo['company_owner_avatar'] ?? $companyInfo['logo'] ?? 'default';
                                    $hasAvatar = ($avatarFile && $avatarFile !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatarFile));
                                    if ($hasAvatar): 
                                    ?>
                                        <img src="<?php echo getBaseUrl() . '/assets/uploads/avatars/' . $avatarFile; ?>" alt="Company Logo" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #e0e7ff, #c7d2fe); display: flex; align-items: center; justify-content: center;">
                                            <svg width="45" height="45" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.5">
                                                <path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4M5 21V10.85M19 21V10.85M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h4 style="color: white; font-size: 1.1rem; font-weight: 700; margin: 0; text-shadow: 0 2px 10px rgba(0,0,0,0.2); position: relative;">
                                <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'ชื่อบริษัท'); ?>
                            </h4>
                            <?php if (!empty($companyInfo['company_name_en'])): ?>
                            <p style="color: rgba(255,255,255,0.9); font-size: 0.75rem; margin: 0.35rem 0 0; position: relative;">
                                <?php echo htmlspecialchars($companyInfo['company_name_en']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div style="padding: 1.25rem;">
                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                <?php if (!empty($companyInfo['industry_type'])): ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border-radius: 12px; transition: all 0.3s;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: linear-gradient(135deg, #fef3c7, #fde68a);">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M2 20h20M5 20V8l7-4 7 4v12M9 20v-4h6v4"/></svg>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">ประเภทอุตสาหกรรม</div>
                                        <div style="font-size: 0.85rem; color: #1e293b; font-weight: 600; margin-top: 0.125rem;">
                                            <?php 
                                            $industries = is_array($companyInfo['industry_type']) 
                                                ? $companyInfo['industry_type'] 
                                                : explode('|', $companyInfo['industry_type'] ?? '');
                                            echo htmlspecialchars(is_array($industries) && count($industries) > 0 ? implode(', ', array_filter($industries)) : '-');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($companyInfo['employee_count'])): ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border-radius: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">จำนวนพนักงาน</div>
                                        <div style="font-size: 0.85rem; color: #1e293b; font-weight: 600; margin-top: 0.125rem;"><?php echo number_format($companyInfo['employee_count']); ?> คน</div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($companyInfo['contact_name'])): ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border-radius: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: linear-gradient(135deg, #dcfce7, #bbf7d0);">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">ผู้ติดต่อ</div>
                                        <div style="font-size: 0.85rem; color: #1e293b; font-weight: 600; margin-top: 0.125rem;"><?php echo htmlspecialchars($companyInfo['contact_name']); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($companyInfo['contact_phone'])): ?>
                                <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border-radius: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: linear-gradient(135deg, #ede9fe, #ddd6fe);">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">เบอร์โทร</div>
                                        <div style="font-size: 0.85rem; color: #1e293b; font-weight: 600; margin-top: 0.125rem;"><?php echo htmlspecialchars($companyInfo['contact_phone']); ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                                <a href="company-profile.php" class="btn btn-outline w-full" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; border-radius: 12px; font-weight: 600;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    แก้ไขข้อมูลบริษัท
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Right: No Period + Upcoming -->
                    <div>
                <?php else: ?>
                <div style="animation: fadeInUp 0.6s ease-out both;">
                <?php endif; ?>

                <!-- No Active Period Card -->
                <div style="animation: fadeInUp 0.6s ease-out both;">
                    <div style="
                        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                        border: 2px dashed #cbd5e1;
                        border-radius: 24px;
                        padding: 3rem 2rem;
                        text-align: center;
                        margin-bottom: 1.5rem;
                    ">
                        <div style="
                            width: 80px; height: 80px;
                            background: linear-gradient(135deg, #e0f2fe, #bae6fd);
                            border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;
                            margin: 0 auto 1.5rem;
                            font-size: 2.5rem;
                        ">📋</div>
                        <h2 style="font-size: 1.35rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">ยังไม่มีรอบการประเมินที่เปิดอยู่</h2>
                        <p style="color: #64748b; font-size: 0.9rem; max-width: 420px; margin: 0 auto; line-height: 1.6;">
                            กรุณารอการประกาศจากทางผู้ดูแลระบบ...
                        </p>
                    </div>

                    <?php if (!empty($upcomingPeriods)): ?>
                    <div style="
                        background: white;
                        border-radius: 20px;
                        border: 1px solid #e2e8f0;
                        overflow: hidden;
                        box-shadow: 0 4px 24px rgba(0,0,0,0.04);
                        margin-bottom: 1.5rem;
                    ">
                        <div style="
                            padding: 1.25rem 1.5rem;
                            background: linear-gradient(135deg, #0c4a6e, #0369a1);
                            color: white;
                            display: flex; align-items: center; gap: 0.75rem;
                        ">
                            <div style="width: 36px; height: 36px; background: rgba(255,255,255,0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">🗓️</div>
                            <div>
                                <div style="font-weight: 600; font-size: 1rem;">รอบการประเมินที่กำหนดไว้ล่วงหน้า</div>
                                <div style="font-size: 0.75rem; opacity: 0.8;">กำหนดการที่จะเปิดรับการประเมิน</div>
                            </div>
                        </div>
                        <div style="padding: 0;">
                            <?php 
                            $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                            foreach ($upcomingPeriods as $idx => $up): 
                                $startDt = new DateTime($up['start_date']);
                                $endDt = new DateTime($up['end_date']);
                                $today = new DateTime();
                                $daysUntilStart = $today->diff($startDt)->days;
                                $isStartInFuture = $startDt > $today;
                                
                                $startDay = intval($startDt->format('d'));
                                $startMonth = $thaiMonths[intval($startDt->format('m'))];
                                $startYear = intval($startDt->format('Y')) + 543;
                                
                                $endDay = intval($endDt->format('d'));
                                $endMonth = $thaiMonths[intval($endDt->format('m'))];
                                $endYear = intval($endDt->format('Y')) + 543;
                                
                                $deadlineTxt = '';
                                if (!empty($up['submission_deadline'])) {
                                    $dlDt = new DateTime($up['submission_deadline']);
                                    $deadlineTxt = intval($dlDt->format('d')) . ' ' . $thaiMonths[intval($dlDt->format('m'))] . ' ' . (intval($dlDt->format('Y')) + 543);
                                }
                                
                                $announceTxt = '';
                                if (!empty($up['announcement_date'])) {
                                    $annDt = new DateTime($up['announcement_date']);
                                    $announceTxt = intval($annDt->format('d')) . ' ' . $thaiMonths[intval($annDt->format('m'))] . ' ' . (intval($annDt->format('Y')) + 543) . ' เวลา ' . $annDt->format('H:i') . ' น.';
                                }
                            ?>
                            <div style="
                                padding: 1.25rem 1.5rem;
                                <?php echo $idx > 0 ? 'border-top: 1px solid #f1f5f9;' : ''; ?>
                                display: flex; align-items: flex-start; gap: 1rem;
                                transition: background 0.2s;
                            " onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                                <!-- Date Badge -->
                                <div style="
                                    min-width: 56px; text-align: center;
                                    background: linear-gradient(135deg, #eff6ff, #dbeafe);
                                    border-radius: 12px; padding: 0.5rem;
                                    border: 1px solid #bfdbfe;
                                ">
                                    <div style="font-size: 1.3rem; font-weight: 800; color: #1d4ed8; line-height: 1;"><?php echo $startDay; ?></div>
                                    <div style="font-size: 0.65rem; font-weight: 600; color: #3b82f6; text-transform: uppercase;"><?php echo $startMonth; ?></div>
                                    <div style="font-size: 0.6rem; color: #60a5fa;"><?php echo $startYear; ?></div>
                                </div>
                                
                                <!-- Details -->
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                        <h4 style="font-size: 0.95rem; font-weight: 600; color: #1e293b; margin: 0;">
                                            <?php echo htmlspecialchars($up['name']); ?>
                                        </h4>
                                        <?php if ($isStartInFuture): ?>
                                        <span style="
                                            background: linear-gradient(135deg, #fef3c7, #fde68a);
                                            color: #92400e; font-size: 0.7rem; font-weight: 600;
                                            padding: 0.15rem 0.5rem; border-radius: 6px;
                                            border: 1px solid #fde68a;
                                        ">อีก <?php echo $daysUntilStart; ?> วัน</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($up['description'])): ?>
                                    <p style="font-size: 0.8rem; color: #64748b; margin: 0.35rem 0 0; line-height: 1.5;">
                                        <?php echo htmlspecialchars(mb_substr($up['description'], 0, 100)); ?><?php echo mb_strlen($up['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <!-- Timeline -->
                                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; margin-top: 0.65rem;">
                                        <div style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; color: #3b82f6;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                            <span>เปิดรับ: <strong><?php echo "{$startDay} {$startMonth} {$startYear}"; ?></strong> — <strong><?php echo "{$endDay} {$endMonth} {$endYear}"; ?></strong></span>
                                        </div>
                                        <?php if ($deadlineTxt): ?>
                                        <div style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; color: #ef4444;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            <span>กำหนดส่ง: <strong><?php echo $deadlineTxt; ?></strong></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($announceTxt): ?>
                                        <div style="display: flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; color: #10b981;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                            <span>ประกาศผล: <strong><?php echo $announceTxt; ?></strong></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($assessmentHistory)): ?>
                    <!-- Previous History (still show even without current period) -->
                    <div style="
                        background: white; border-radius: 16px;
                        border: 1px solid #e2e8f0; padding: 1.25rem 1.5rem;
                        box-shadow: 0 2px 12px rgba(0,0,0,0.03);
                    ">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                            <span style="font-size: 1.1rem;">📊</span>
                            <h3 style="font-size: 1rem; font-weight: 600; color: #1e293b; margin: 0;">ผลการประเมินรอบที่ผ่านมา</h3>
                        </div>
                        <?php foreach (array_slice($assessmentHistory, 0, 5) as $hist): 
                            $hScore = $hist['self_total_score'] ?? $hist['final_score'] ?? 0;
                            $hLevel = $hist['hicm_level'] ?? 1;
                            $hLevelInfo = getLevelInfo($hLevel);
                        ?>
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid #f8fafc;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo $hLevelInfo['color']; ?>;"></div>
                            <div style="flex: 1;">
                                <div style="font-weight: 500; font-size: 0.85rem; color: #334155;"><?php echo htmlspecialchars($hist['period_name']); ?> (<?php echo $hist['period_year']; ?>)</div>
                            </div>
                            <div style="font-weight: 700; font-size: 0.9rem; color: <?php echo $hLevelInfo['color']; ?>;">
                                <?php echo number_format($hScore, 0); ?> <span style="font-size: 0.7rem; color: #94a3b8; font-weight: 400;">/1,000</span>
                            </div>
                            <a href="<?php echo getBaseUrl(); ?>/pages/assessment-result.php?period_id=<?php echo $hist['period_id']; ?>" 
                               style="font-size: 0.75rem; color: #3b82f6; text-decoration: none; white-space: nowrap;" 
                               title="ดูผลการประเมิน">ดูผล →</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div><!-- closes right column or standalone div -->
                <?php if (isset($companyInfo) && $companyInfo): ?>
                </div><!-- close grid -->
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 900px) {
            div[style*="grid-template-columns: 320px 1fr"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        <?php if ($isAdmin): ?>
        // Level Distribution Chart
        const levelCtx = document.getElementById('levelChart').getContext('2d');
        new Chart(levelCtx, {
            type: 'doughnut',
            data: {
                labels: ['Level 1: เริ่มต้น', 'Level 2: กำลังพัฒนา', 'Level 3: พัฒนาดี', 'Level 4: เป็นเลิศ', 'Level 5: ระดับโลก'],
                datasets: [{
                    data: [
                        <?php 
                        $levelCounts = array_fill(1, 5, 0);
                        foreach ($scoreDistribution as $dist) {
                            $levelCounts[$dist['hicm_level']] = $dist['count'];
                        }
                        echo implode(', ', $levelCounts);
                        ?>
                    ],
                    backgroundColor: ['#EF4444', '#F59E0B', '#3B82F6', '#8B5CF6', '#10B981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 20 }
                    }
                }
            }
        });
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['ร่าง', 'รอตรวจสอบ', 'กำลังตรวจสอบ', 'ประเมินแล้ว', 'เสร็จสิ้น'],
                datasets: [{
                    label: 'จำนวน',
                    data: [
                        <?php echo ($stats['draft_count'] ?? 0); ?>,
                        <?php echo ($stats['submitted_count'] ?? 0); ?>,
                        <?php echo $stats['under_review_count'] ?? 0; ?>,
                        <?php echo $stats['evaluated_count'] ?? 0; ?>,
                        <?php echo $stats['completed_count'] ?? 0; ?>
                    ],
                    backgroundColor: ['#9CA3AF', '#F59E0B', '#3B82F6', '#8B5CF6', '#10B981'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
        
        // Add click handler to status chart
        const statusCanvasContainer = document.getElementById('statusChart').parentElement;
        statusCanvasContainer.style.cursor = 'pointer';
        
        statusCanvasContainer.addEventListener('click', function(evt) {
            const points = statusChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, false);
            if (points.length > 0) {
                const datasetIndex = points[0].datasetIndex;
                const index = points[0].index;
                const statusLabels = ['draft', 'submitted', 'under_review', 'evaluated', 'completed'];
                const statusLabel = statusLabels[index];
                
                if (statusLabel && statusChart.data.datasets[datasetIndex].data[index] > 0) {
                    window.location.href = '<?php echo getBaseUrl(); ?>/pages/assessments.php?status=' + statusLabel;
                }
            }
        });
        <?php endif; ?>
        
        <?php if ($isCompany && isset($assessment) && !empty($assessment['pillars'])): ?>
        // Radar Chart for Company
        const radarCtx = document.getElementById('radarChart').getContext('2d');
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
                    label: 'คะแนนประเมินตนเอง',
                    data: [
                        <?php 
                        foreach ($assessment['pillars'] as $pillar) {
                            $indicators = $pillar['indicators'] ?? [];
                            $selfTotal = array_sum(array_column($indicators, 'self_score'));
                            $maxScore = count($indicators);
                            $percentage = $maxScore > 0 ? ($selfTotal / $maxScore) * 100 : 0;
                            echo $percentage . ',';
                        }
                        ?>
                    ],
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(59, 130, 246, 1)'
                }]
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
        
        // History Trend Chart
        <?php if (!empty($assessmentHistory) && count($assessmentHistory) > 1): ?>
        const trendCtx = document.getElementById('historyTrendChart');
        if (trendCtx) {
            new Chart(trendCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                        $reversedHistory = array_reverse($assessmentHistory);
                        foreach ($reversedHistory as $hist) {
                            echo '"' . htmlspecialchars($hist['period_name']) . '",';
                        }
                        ?>
                    ],
                    datasets: [{
                        label: 'คะแนนรวม',
                        data: [
                            <?php 
                            foreach ($reversedHistory as $hist) {
                                echo ($hist['final_score'] ?? 0) . ',';
                            }
                            ?>
                        ],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'คะแนน: ' + context.parsed.y.toLocaleString() + ' / 1,000';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 1000,
                            ticks: {
                                stepSize: 200,
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(0,0,0,0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
