<?php
/**
 * HICM V2025 Assessment System - Reports Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);
$isCEO = hasRole('ceo');

// Preview mode — hide navbar/sidebar when rendered inside preview.php iframe
$isPreview = !empty($_GET['_preview']);

// Get report data
$periodId = $_GET['period'] ?? null;
$pillarFilter = $_GET['pillar'] ?? '';
$levelFilter = $_GET['level'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$stats = getAssessmentStatistics($periodId);
$scoreDistribution = getScoreDistribution($periodId);

// Get assessments with filters
$filters = ['period_id' => $periodId, 'status' => $statusFilter];
$assessments = getAllAssessments($filters);

// Apply level filter to results if set
if ($levelFilter) {
    $assessments = array_filter($assessments, fn($a) => $a['hicm_level'] == $levelFilter);
}

// Apply search filter
if ($searchFilter) {
    $assessments = array_filter($assessments, fn($a) => stripos($a['company_name'], $searchFilter) !== false);
}

$periods = getAllPeriods();

// Get top performers by pillar
function getTopPillarPerformers($periodId = null, $limit = 5) {
    $db = getDB();
    try {
        // Query per pillar to avoid global LIMIT cutting off later pillars (M4)
        $pillarStmt = $db->prepare("SELECT id, code FROM pillars ORDER BY display_order");
        $pillarStmt->execute();
        $pillars = $pillarStmt->fetchAll();
        
        $topPerformers = [];
        
        foreach ($pillars as $pillar) {
            $sql = "
                SELECT 
                    ? as pillar_code,
                    c.company_name,
                    a.id as assessment_id,
                    AVG(CAST(s.self_score AS DECIMAL(10,2))) as avg_score,
                    COUNT(s.id) as total_indicators
                FROM assessment_scores s
                JOIN indicators i ON s.indicator_id = i.id AND i.pillar_id = ?
                JOIN assessments a ON s.assessment_id = a.id
                JOIN companies c ON a.company_id = c.id
                WHERE s.self_score > 0 AND s.is_na = 0
            ";
            
            $params = [$pillar['code'], $pillar['id']];
            if ($periodId) {
                $sql .= " AND a.period_id = ?";
                $params[] = $periodId;
            }
            
            $sql .= " GROUP BY a.id, c.id, c.company_name
                      ORDER BY avg_score DESC
                      LIMIT ?";
            $params[] = $limit;
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            
            if (!empty($results)) {
                $topPerformers[$pillar['code']] = $results;
            }
        }
        
        return $topPerformers;
    } catch (Exception $e) {
        error_log("Get top pillar performers error: " . $e->getMessage());
        return [];
    }
}

$topPillarPerformers = getTopPillarPerformers($periodId, 5);

// Get top overall performers
function getTopOverallPerformers($periodId = null, $limit = 10) {
    $db = getDB();
    try {
        $sql = "
            SELECT 
                a.id as assessment_id,
                c.company_name, c.logo,
                owner.avatar as company_owner_avatar,
                a.final_score,
                a.hicm_level,
                ap.year,
                ap.name as period_name
            FROM assessments a
            JOIN companies c ON a.company_id = c.id
            JOIN assessment_periods ap ON a.period_id = ap.id
            LEFT JOIN users owner ON c.user_id = owner.id
            WHERE a.final_score > 0
        ";
        
        $params = [];
        if ($periodId) {
            $sql .= " AND a.period_id = ?";
            $params[] = $periodId;
        }
        
        $sql .= " ORDER BY a.final_score DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get top overall performers error: " . $e->getMessage());
        return [];
    }
}

$topOverallPerformers = getTopOverallPerformers($periodId, 10);

function getAllPeriods() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM assessment_periods ORDER BY year DESC, start_date DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

$levelInfo = [
    1 => ['name' => 'เริ่มต้น', 'color' => '#EF4444'],
    2 => ['name' => 'กำลังพัฒนา', 'color' => '#F59E0B'],
    3 => ['name' => 'พัฒนาดี', 'color' => '#3B82F6'],
    4 => ['name' => 'เป็นเลิศ', 'color' => '#8B5CF6'],
    5 => ['name' => 'ระดับโลก', 'color' => '#10B981']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงาน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/pdf-export.js"></script>
    <style>
        /* ========== Professional Report Page Styles ========== */
        
        /* Hero Section */
        .report-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
            color: white;
            padding: 2.5rem;
            border-radius: 24px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .report-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .report-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .report-hero-info h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .report-hero-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .report-period-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 1rem 1.5rem;
            border-radius: 16px;
            text-align: center;
        }
        .report-period-badge .period-label {
            font-size: 0.75rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .report-period-badge .period-value {
            font-size: 1.25rem;
            font-weight: 700;
            margin-top: 0.25rem;
        }

        /* Animation Keyframes */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(251, 191, 36, 0); }
            100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0); }
        }
        
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }
        
        .animate-in {
            animation: fadeInUp 0.6s ease forwards;
        }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
        .delay-4 { animation-delay: 0.4s; opacity: 0; }

        /* Action Bar */
        .action-bar {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .action-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .action-bar .btn:hover {
            transform: translateY(-2px);
        }
        .btn-excel {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-pdf {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        .btn-print {
            background: white;
            color: var(--gray-700);
            border: 2px solid var(--gray-200);
        }
        .btn-print:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
        }
        .filter-section h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        .filter-group label {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .filter-actions {
            display: flex;
            gap: 0.5rem;
        }
        .filter-actions .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-weight: 500;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        .stat-card-pro {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }
        .stat-card-pro:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        }
        .stat-card-pro::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        .stat-card-pro.stat-total::before { background: #3B82F6; }
        .stat-card-pro.stat-avg::before { background: #8B5CF6; }
        .stat-card-pro.stat-max::before { background: #10B981; }
        .stat-card-pro.stat-complete::before { background: #F59E0B; }
        
        .stat-card-pro .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .stat-card-pro .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
        }
        .stat-card-pro .stat-label {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.5rem;
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
        }
        .chart-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
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
        .chart-card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        .chart-card-body {
            padding: 1.5rem;
        }
        .chart-container {
            position: relative;
            height: 280px;
        }

        /* Leaderboard Section */
        .leaderboard-section {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.05);
            margin-bottom: 2rem;
        }
        .leaderboard-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        }
        .leaderboard-header {
            padding: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.02);
        }
        .trophy-icon {
            font-size: 2rem;
            animation: float 3s ease-in-out infinite;
            filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.5));
        }
        .leaderboard-title {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(to right, #ffffff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        .leaderboard-body {
            padding: 2rem;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 2rem;
        }
        @media (max-width: 1024px) {
            .leaderboard-body { grid-template-columns: 1fr; }
        }

        /* Top 3 Performers */
        .top-performers {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .performer-card {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1.25rem;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }
        .performer-card:hover {
            transform: translateY(-4px) scale(1.01);
            background: rgba(255, 255, 255, 0.06);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        .rank-badge {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 800;
            border-radius: 12px;
        }
        .rank-1 {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
            animation: pulseGlow 2s infinite;
        }
        .rank-2 {
            background: linear-gradient(135deg, #94A3B8, #64748B);
            color: white;
        }
        .rank-3 {
            background: linear-gradient(135deg, #B45309, #78350F);
            color: white;
        }
        .performer-info {
            flex: 1;
            min-width: 0;
        }
        .performer-name {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .performer-meta {
            font-size: 0.8rem;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .performer-score {
            text-align: right;
        }
        .performer-score .score-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #38bdf8;
            text-shadow: 0 0 20px rgba(56, 189, 248, 0.3);
        }
        .performer-score .score-label {
            font-size: 0.7rem;
            color: #64748B;
        }

        /* Ranking List */
        .ranking-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .ranking-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.02);
            transition: all 0.2s;
        }
        .ranking-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(5px);
        }
        .rank-number {
            width: 32px;
            font-weight: 600;
            color: #64748B;
            text-align: center;
        }
        .ranking-info {
            flex: 1;
            padding: 0 1rem;
        }
        .ranking-name {
            font-weight: 500;
            font-size: 0.9rem;
            color: #e2e8f0;
        }
        .ranking-score {
            font-weight: 700;
            color: #38bdf8;
        }
        .score-bar-bg {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 6px;
        }
        .score-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #38bdf8, #8B5CF6);
            border-radius: 2px;
            transition: width 1s ease-out;
        }

        /* Pillar Cards */
        .pillar-section {
            margin-bottom: 2rem;
        }
        .pillar-section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .pillar-section-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        .pillar-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        @media (max-width: 1200px) {
            .pillar-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .pillar-grid { grid-template-columns: 1fr; }
        }
        .pillar-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            overflow: hidden;
            transition: all 0.3s;
        }
        .pillar-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        }
        .pillar-card-header {
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .pillar-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        .pillar-card-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0;
        }
        .pillar-card-body {
            padding: 0.75rem;
        }
        .pillar-rank-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .pillar-rank-item:hover {
            background: var(--gray-50);
        }
        .pillar-rank-badge {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: white;
            flex-shrink: 0;
        }
        .pillar-rank-info {
            flex: 1;
            min-width: 0;
        }
        .pillar-rank-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-800);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pillar-rank-score {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* Data Table */
        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        .table-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .table-card-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-card-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .table-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
            max-width: 350px;
        }
        .search-box input {
            width: 100%;
            padding: 0.625rem 1rem 0.625rem 2.5rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .search-box svg {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }
        .column-toggle-dropdown {
            position: relative;
        }
        .column-toggle-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1rem;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.875rem;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s;
        }
        .column-toggle-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .column-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            padding: 0.5rem;
            min-width: 200px;
            z-index: 50;
            display: none;
        }
        .column-menu.show {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .column-menu label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            border-radius: 8px;
            transition: background 0.15s;
            font-size: 0.875rem;
            color: var(--gray-700);
        }
        .column-menu label:hover {
            background: var(--gray-50);
        }
        
        /* Table Styles */
        .report-table {
            font-size: 0.875rem;
            width: 100%;
        }
        .report-table th {
            background: var(--gray-50);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            padding: 1rem;
            text-align: left;
        }
        .report-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .report-table tr:hover td {
            background: var(--gray-50);
        }
        .sortable {
            cursor: pointer;
            user-select: none;
            transition: background 0.15s;
        }
        .sortable:hover {
            background: var(--gray-100);
        }
        .sort-icon::after {
            content: '↕';
            margin-left: 5px;
            opacity: 0.3;
            font-size: 0.8em;
        }
        .sortable.asc .sort-icon::after {
            content: '↑';
            opacity: 1;
        }
        .sortable.desc .sort-icon::after {
            content: '↓';
            opacity: 1;
        }
        
        .level-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .industry-tag {
            display: inline-block;
            font-size: 0.7rem;
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            margin: 0.1rem;
        }

        /* Print Styles */
        @media print {
            @page { size: A4 landscape; margin: 8mm; }

            .navbar, .sidebar, .sidebar-overlay,
            .action-bar, .filter-section, .no-print,
            button, [onclick], select, canvas {
                display: none !important;
            }

            body.has-sidebar { margin-left: 0 !important; }

            .main-wrapper { margin: 0 !important; padding: 0 !important; }

            .main-content {
                margin-left: 0 !important;
                padding: 0.5rem !important;
            }

            .card, .chart-card, .table-card {
                box-shadow: none !important;
                border: 1px solid #d1d5db !important;
                break-inside: avoid;
                page-break-inside: avoid;
                border-radius: 6px !important;
            }

            /* Hero */
            .report-hero {
                background: #1e3a5f !important;
                color: white !important;
                padding: 1rem 1.5rem !important;
                border-radius: 0 !important;
                margin: 0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .report-hero, .report-hero * {
                color: white !important;
            }

            .report-hero h1 { font-size: 14pt !important; }

            /* Summary stats */
            .stat-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 0.5rem !important;
            }

            /* Tables */
            .table {
                font-size: 8pt !important;
                border-collapse: collapse !important;
            }

            .table th {
                background: #f1f5f9 !important;
                padding: 0.4rem !important;
                font-size: 7pt !important;
            }

            .table td {
                padding: 0.35rem 0.4rem !important;
                font-size: 8pt !important;
            }

            /* Chart cards: hide canvas-based charts, show fallback */
            .chart-card canvas {
                display: none !important;
            }

            /* Animations */
            .animate-in {
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
            }

            /* Links */
            a[href]::after { content: none !important; }
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
            <!-- Print-Only Document Header -->
            <div class="print-only print-doc-header">
                <h1>รายงานสรุปผลการประเมิน HICM V2025</h1>
                <p>วันที่พิมพ์: <?php echo date('d/m/Y H:i'); ?> น.</p>
            </div>

            <!-- Report Hero -->
            <div class="report-hero animate-in">
                <div class="report-hero-content">
                    <div class="report-hero-info">
                        <h1>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            รายงานสรุปผลการประเมิน HICM
                        </h1>
                        <p>ภาพรวมและวิเคราะห์ผลการประเมินคุณภาพสถานประกอบการทั้งหมด</p>
                    </div>
                    <div class="report-period-badge">
                        <div class="period-label">รอบการประเมิน</div>
                        <div class="period-value"><?php echo $periodId ? ($periods[array_search($periodId, array_column($periods, 'id'))]['name'] ?? 'ทั้งหมด') : 'ทั้งหมด'; ?></div>
                        <div style="font-size: 0.8rem; opacity: 0.8; margin-top: 0.25rem;">
                            วันที่: <?php echo date('d/m/Y'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Bar -->
            <div class="action-bar no-print animate-in delay-1">
                <button class="btn btn-excel" onclick="exportToExcel()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <path d="M8 13h2v6H8zm4-3h2v9h-2zm4-2h2v11h-2z"/>
                    </svg>
                    Export Excel
                </button>
                <button class="btn btn-pdf" onclick="downloadReportPDF()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="12" y1="18" x2="12" y2="12"/>
                        <polyline points="9 15 12 18 15 15"/>
                    </svg>
                    Export PDF
                </button>
                <button class="btn btn-print" onclick="HICM_PDF.print()" title="พิมพ์ (Ctrl+P)">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    พิมพ์รายงาน
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filter-section no-print animate-in delay-1">
                <h4>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    ตัวกรองข้อมูล
                </h4>
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>ค้นหา</label>
                            <input type="text" name="search" placeholder="ชื่อสถานประกอบการ..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                        </div>
                        <div class="filter-group">
                            <label>รอบการประเมิน</label>
                            <select name="period">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($periods as $period): ?>
                                    <option value="<?php echo $period['id']; ?>" <?php echo $periodId == $period['id'] ? 'selected' : ''; ?>>
                                        <?php echo $period['name']; ?> (<?php echo $period['year']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>สถานะ</label>
                            <select name="status">
                                <option value="">ทั้งหมด</option>
                                <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>ร่าง</option>
                                <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>รอตรวจสอบ</option>
                                <option value="evaluated" <?php echo $statusFilter === 'evaluated' ? 'selected' : ''; ?>>ประเมินแล้ว</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>เสร็จสิ้น</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>ระดับ HICM</label>
                            <select name="level">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($levelInfo as $level => $info): ?>
                                    <option value="<?php echo $level; ?>" <?php echo $levelFilter == $level ? 'selected' : ''; ?>>
                                        Level <?php echo $level; ?>: <?php echo $info['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                                กรอง
                            </button>
                            <a href="reports.php" class="btn btn-outline">รีเซ็ต</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid animate-in delay-2">
                <div class="stat-card-pro stat-total">
                    <div class="stat-icon" style="background: #DBEAFE; color: #3B82F6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
                    <div class="stat-label">สถานประกอบการ</div>
                </div>
                <div class="stat-card-pro stat-avg">
                    <div class="stat-icon" style="background: #EDE9FE; color: #8B5CF6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['avg_score'] ?? 0, 1); ?></div>
                    <div class="stat-label">คะแนนเฉลี่ย</div>
                </div>
                <div class="stat-card-pro stat-max">
                    <div class="stat-icon" style="background: #D1FAE5; color: #10B981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['max_score'] ?? 0, 0); ?></div>
                    <div class="stat-label">คะแนนสูงสุด</div>
                </div>
                <div class="stat-card-pro stat-complete">
                    <div class="stat-icon" style="background: #FEF3C7; color: #F59E0B;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['completed_count'] ?? 0); ?></div>
                    <div class="stat-label">ประเมินเสร็จสิ้น</div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="charts-grid animate-in delay-2">
                <div class="chart-card">
                    <div class="chart-card-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2">
                            <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                            <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                        </svg>
                        <h3>การกระจายตามระดับ HICM</h3>
                    </div>
                    <div class="chart-card-body">
                        <div class="chart-container">
                            <canvas id="levelChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-card-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        <h3>สถานะการประเมิน</h3>
                    </div>
                    <div class="chart-card-body">
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Form Leaderboard -->
            <div class="leaderboard-section animate-in delay-3">
                <div class="leaderboard-header">
                    <div class="trophy-icon">🏆</div>
                    <h2 class="leaderboard-title">Top 10 Leaderboard</h2>
                </div>
                
                <div class="leaderboard-body">
                    <?php if (!empty($topOverallPerformers)): ?>
                        <!-- Top 3 Performers -->
                        <div class="top-performers">
                            <?php foreach (array_slice($topOverallPerformers, 0, 3) as $index => $performer): 
                                $rank = $index + 1;
                                $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉');
                            ?>
                                <div class="performer-card">
                                    <div class="rank-badge rank-<?php echo $rank; ?>" style="overflow: hidden; padding: 0; position: relative;">
                                        <?php 
                                        $avatar = $performer['company_owner_avatar'] ?? $performer['logo'] ?? 'default';
                                        if ($avatar && $avatar !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatar)):
                                        ?>
                                            <img src="<?php echo getBaseUrl(); ?>/assets/uploads/avatars/<?php echo $avatar; ?>" alt="Rank <?php echo $rank; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            <div style="position: absolute; bottom: 0; right: 0; background: rgba(0,0,0,0.6); color: white; width: 18px; height: 18px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; border-radius: 6px 0 0 0;">
                                                <?php echo $rank; ?>
                                            </div>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="performer-info">
                                        <div class="performer-name"><?php echo htmlspecialchars($performer['company_name']); ?></div>
                                        <div class="performer-meta">
                                            <span>Level <?php echo $performer['hicm_level']; ?></span>
                                            <span>•</span>
                                            <span><?php echo $performer['period_name']; ?></span>
                                        </div>
                                    </div>
                                    <div class="performer-score">
                                        <div class="score-value"><?php echo number_format($performer['final_score'], 0); ?></div>
                                        <div class="score-label">POINTS</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Ranks 4-10 -->
                        <div class="ranking-list">
                            <?php foreach (array_slice($topOverallPerformers, 3) as $index => $performer): 
                                $rank = $index + 4;
                                $delay = ($index * 0.1) . 's';
                                $scorePercent = min(($performer['final_score'] / 1000) * 100, 100);
                            ?>
                                <div class="ranking-item" style="animation-delay: <?php echo $delay; ?>">
                                    <div class="rank-number">#<?php echo $rank; ?></div>
                                    <div class="ranking-info">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div class="ranking-name"><?php echo htmlspecialchars($performer['company_name']); ?></div>
                                            <div class="ranking-score"><?php echo number_format($performer['final_score'], 0); ?></div>
                                        </div>
                                        <div class="score-bar-bg">
                                            <div class="score-bar-fill" style="width: 0%" data-width="<?php echo $scorePercent; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; opacity: 0.5;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem;">
                                <circle cx="12" cy="8" r="7"/>
                                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                            </svg>
                            <p>ยังไม่มีข้อมูลการประเมิน</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
                // Animate progress bars on load
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => {
                        const bars = document.querySelectorAll('.score-bar-fill');
                        bars.forEach(bar => {
                            bar.style.width = bar.getAttribute('data-width');
                        });
                    }, 500);
                });
            </script>

            <!-- Pillar Rankings -->
            <div class="pillar-section animate-in delay-3">
                <div class="pillar-section-header">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2">
                        <circle cx="12" cy="8" r="7"/>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                    </svg>
                    <h2>อันดับดีเด่นตามเสาหลัก HICM</h2>
                </div>
                <div class="pillar-grid">
                    <?php
                    $pillarInfo = [
                        'H1' => ['name_en' => 'Health Promotion',               'name_th' => 'การส่งเสริมสุขภาพ',              'color' => '#10B981', 'bg' => '#D1FAE5', 'icon' => '❤️'],
                        'I2' => ['name_en' => 'Industrial Safety & Environment', 'name_th' => 'ความปลอดภัยและสิ่งแวดล้อม',     'color' => '#3B82F6', 'bg' => '#DBEAFE', 'icon' => '🛡️'],
                        'C3' => ['name_en' => 'Community Engagement',            'name_th' => 'การมีส่วนร่วมกับชุมชน',         'color' => '#F59E0B', 'bg' => '#FEF3C7', 'icon' => '🌿'],
                        'M4' => ['name_en' => 'Management & Sustainability',     'name_th' => 'การบริหารจัดการและความยั่งยืน', 'color' => '#8B5CF6', 'bg' => '#EDE9FE', 'icon' => '⚙️']
                    ];
                    
                    foreach ($pillarInfo as $pillarCode => $pillarData):
                        $performers = $topPillarPerformers[$pillarCode] ?? [];
                    ?>
                        <div class="pillar-card">
                            <div class="pillar-card-header" style="background: <?php echo $pillarData['bg']; ?>;">
                                <div class="pillar-icon" style="background: white;">
                                    <?php echo $pillarData['icon']; ?>
                                </div>
                                <h3 style="color: <?php echo $pillarData['color']; ?>; margin: 0; font-size: 0.9rem; font-weight: 700;"><?php echo $pillarCode . ': ' . $pillarData['name_en']; ?></h3>
                                <p style="font-size: 0.68rem; color: #6B7280; font-weight: 500; margin: 0.15rem 0 0;"><?php echo $pillarData['name_th']; ?></p>
                            </div>
                            <div class="pillar-card-body">
                                <?php if (!empty($performers)): ?>
                                    <?php foreach ($performers as $index => $perf): ?>
                                        <div class="pillar-rank-item">
                                            <div class="pillar-rank-badge" style="background: <?php echo $pillarData['color']; ?>;">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <div class="pillar-rank-info">
                                                <div class="pillar-rank-name"><?php echo htmlspecialchars($perf['company_name']); ?></div>
                                                <div class="pillar-rank-score">คะแนนเฉลี่ย: <?php echo number_format($perf['avg_score'] * 100, 1); ?>%</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="text-align: center; padding: 1.5rem; color: var(--gray-400);">
                                        <p style="margin: 0; font-size: 0.85rem;">ไม่มีข้อมูล</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Data Table -->
            <div class="table-card animate-in delay-4" id="reportTable">
                <div class="table-card-header">
                    <div class="table-card-header-top">
                        <h3>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            รายละเอียดผลการประเมิน
                        </h3>
                        <span class="badge badge-primary" style="font-size: 0.8rem;"><?php echo count($assessments); ?> รายการ</span>
                    </div>
                    
                    <div class="table-controls">
                        <div class="search-box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="tableSearch" placeholder="ค้นหาในตาราง..." onkeyup="filterTable()">
                        </div>
                        
                        <div class="column-toggle-dropdown">
                            <button class="column-toggle-btn" onclick="toggleColumnMenu()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
                                เลือกคอลัมน์
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="column-menu" id="columnMenu">
                                <label><input type="checkbox" checked onchange="toggleColumn(0)"> ลำดับ</label>
                                <label><input type="checkbox" checked onchange="toggleColumn(1)"> สถานประกอบการ</label>
                                <label><input type="checkbox" checked onchange="toggleColumn(2)"> ประเภทอุตสาหกรรม</label>
                                <label><input type="checkbox" checked onchange="toggleColumn(3)"> คะแนนรวม</label>
                                <label><input type="checkbox" checked onchange="toggleColumn(4)"> ระดับ</label>
                                <label><input type="checkbox" checked onchange="toggleColumn(5)"> สถานะ</label>
                                <label><input type="checkbox" checked onchange="toggleColumn(6)"> วันที่ส่ง</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-container">
                    <table class="table report-table" id="dataTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable(0)" class="sortable">ลำดับ <span class="sort-icon"></span></th>
                                <th onclick="sortTable(1)" class="sortable">สถานประกอบการ <span class="sort-icon"></span></th>
                                <th onclick="sortTable(2)" class="sortable">ประเภทอุตสาหกรรม <span class="sort-icon"></span></th>
                                <th onclick="sortTable(3)" class="sortable">คะแนนรวม <span class="sort-icon"></span></th>
                                <th onclick="sortTable(4)" class="sortable">ระดับ <span class="sort-icon"></span></th>
                                <th onclick="sortTable(5)" class="sortable">สถานะ <span class="sort-icon"></span></th>
                                <th onclick="sortTable(6)" class="sortable">วันที่ส่ง <span class="sort-icon"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assessments as $index => $item): 
                                $level = $item['hicm_level'] ?? 1;
                                $levelData = $levelInfo[$level];
                            ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['company_name']); ?></strong>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                            <?php 
                                            $repIndustries = explode('|', $item['industry_type'] ?? '');
                                            if ($item['industry_type']):
                                                foreach ($repIndustries as $rind):
                                            ?>
                                                <span class="industry-tag"><?php echo htmlspecialchars(trim($rind)); ?></span>
                                            <?php 
                                                endforeach;
                                            else:
                                            ?>
                                                <span style="color: var(--gray-400);">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($item['final_score'], 0); ?></strong>
                                    </td>
                                    <td>
                                        <span class="level-badge" style="background: <?php echo $levelData['color']; ?>20; color: <?php echo $levelData['color']; ?>;">
                                            Level <?php echo $level; ?>: <?php echo $levelData['name']; ?>
                                        </span>
                                    </td>
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
                                    <td><?php echo $item['submitted_at'] ? formatDate($item['submitted_at']) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        // Level Distribution Chart
        const levelCtx = document.getElementById('levelChart').getContext('2d');
        const levelLabels = [
            'Level 1: เริ่มต้น',
            'Level 2: กำลังพัฒนา',
            'Level 3: พัฒนาดี',
            'Level 4: เป็นเลิศ',
            'Level 5: ระดับโลก'
        ];
        
        const levelData = [];
        const scoreDistribution = <?php echo json_encode($scoreDistribution); ?>;
        
        for (let i = 1; i <= 5; i++) {
            const found = scoreDistribution.find(d => d['hicm_level'] == i);
            levelData.push(found ? found['count'] : 0);
        }
        
        new Chart(levelCtx, {
            type: 'doughnut',
            data: {
                labels: levelLabels,
                datasets: [{
                    data: levelData,
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
                        labels: { padding: 15, font: { size: 11 } }
                    }
                }
            }
        });
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const stats = <?php echo json_encode($stats); ?>;
        
        new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: ['ร่าง', 'รอตรวจสอบ', 'กำลังตรวจสอบ', 'ประเมินแล้ว', 'เสร็จสิ้น'],
                datasets: [{
                    label: 'จำนวน',
                    data: [
                        stats.draft_count || 0,
                        stats.submitted_count || 0,
                        stats.under_review_count || 0,
                        stats.evaluated_count || 0,
                        stats.completed_count || 0
                    ],
                    backgroundColor: ['#9CA3AF', '#F59E0B', '#3B82F6', '#8B5CF6', '#10B981'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });
        
        // Export to Excel
        function exportToExcel() {
            const table = document.querySelector('.report-table');
            const wb = XLSX.utils.table_to_book(table, { sheet: 'Report' });
            
            // Add metadata
            const ws = wb.Sheets['Report'];
            XLSX.utils.sheet_add_aoa(ws, [
                ['รายงานสรุปผลการประเมิน HICM V2025'],
                ['วันที่: ' + new Date().toLocaleDateString('th-TH')],
                ['']
            ], { origin: 'A1' });
            
            XLSX.writeFile(wb, 'HICM_Report_' + new Date().toISOString().slice(0,10) + '.xlsx');
        }
        
        // Export to PDF (jsPDF + html2canvas)
        function downloadReportPDF() {
            HICM_PDF.download('.main-content', 'HICM_Report_' + new Date().toISOString().slice(0,10) + '.pdf', {
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            });
        }

        // Table Functionality
        function filterTable() {
            const input = document.getElementById('tableSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('dataTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell) {
                        const text = cell.textContent || cell.innerText;
                        if (text.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }

        function toggleColumnMenu() {
            const menu = document.getElementById('columnMenu');
            menu.classList.toggle('show');
            
            // Close when clicking outside
            document.addEventListener('click', function closeMenu(e) {
                if (!e.target.closest('.column-toggle-dropdown')) {
                    menu.classList.remove('show');
                    document.removeEventListener('click', closeMenu);
                }
            });
        }

        function toggleColumn(index) {
            const table = document.getElementById('dataTable');
            const rows = table.rows;
            const checkboxes = document.querySelectorAll('.column-menu input[type="checkbox"]');
            const isChecked = checkboxes[index].checked;
            
            // Toggle header
            if (rows[0].cells[index]) {
                rows[0].cells[index].style.display = isChecked ? '' : 'none';
            }
            
            // Toggle body cells
            for (let i = 1; i < rows.length; i++) {
                if (rows[i].cells[index]) {
                    rows[i].cells[index].style.display = isChecked ? '' : 'none';
                }
            }
        }

        function sortTable(n) {
            const table = document.getElementById("dataTable");
            let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            switching = true;
            dir = "asc";
            
            // Reset icons
            const headers = table.querySelectorAll('th.sortable');
            headers.forEach(th => {
                th.classList.remove('asc', 'desc');
            });
            
            while (switching) {
                switching = false;
                rows = table.rows;
                
                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    
                    // Handle numeric sorting for score
                    let xVal = x.textContent.toLowerCase();
                    let yVal = y.textContent.toLowerCase();
                    
                    // Clean up values for comparison
                    if (n === 0 || n === 3) { // Rank or Score
                        xVal = parseFloat(xVal.replace(/,/g, '')) || 0;
                        yVal = parseFloat(yVal.replace(/,/g, '')) || 0;
                    }
                    
                    if (dir == "asc") {
                        if (xVal > yVal) {
                            shouldSwitch = true;
                            break;
                        }
                    } else if (dir == "desc") {
                        if (xVal < yVal) {
                            shouldSwitch = true;
                            break;
                        }
                    }
                }
                
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount++;
                } else {
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
            
            // Update icon
            headers[n].classList.add(dir);
        }
    </script>
</body>
</html>
