<?php
/**
 * HICM V2025 Assessment System - Top Form Leaderboard
 * หน้าแสดง Leaderboard — แสดงเฉพาะเมื่อ Admin เปิดประกาศผล (results_announced = 1)
 * ใช้ Logic เดียวกับ Dashboard เพื่อความสอดคล้อง
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$user = getCurrentUser();
$db = getDB();

// ========================================================
// Find the period that has results_announced = 1 (ตรงกับ toggle ประกาศผล)
// ใช้ logic เดียวกับ dashboard.php
// ========================================================
$stmt = $db->prepare("
    SELECT id, name, year, status, announcement_date,
           results_announced, show_leaderboard, leaderboard_top_n 
    FROM assessment_periods 
    WHERE results_announced = 1 AND is_active = 1
    ORDER BY year DESC, start_date DESC 
    LIMIT 1
");
$stmt->execute();
$currentPeriod = $stmt->fetch();

// Leaderboard จะแสดงก็ต่อเมื่อ results_announced = 1 AND show_leaderboard = 1
$leaderboardEnabled = !empty($currentPeriod) && !empty($currentPeriod['show_leaderboard']);
$topN = $currentPeriod['leaderboard_top_n'] ?? 10;

// Get Leaderboard Data
$leaderboardData = [];
$myCompanyRank = null;
$myCompanyData = null;

if ($leaderboardEnabled && $currentPeriod) {
    $stmt = $db->prepare("
        SELECT 
            c.id as company_id,
            c.company_name,
            c.industry_type,
            a.final_score,
            a.hicm_level,
            a.status as assessment_status
        FROM assessments a
        JOIN companies c ON a.company_id = c.id
        WHERE a.period_id = ? AND a.status IN ('evaluated', 'completed') AND a.final_score > 0
        ORDER BY a.final_score DESC, a.hicm_level DESC
    ");
    $stmt->execute([$currentPeriod['id']]);
    $allData = $stmt->fetchAll();
    
    // Find my company rank
    if ($user['company_id']) {
        foreach ($allData as $index => $item) {
            if ($item['company_id'] == $user['company_id']) {
                $myCompanyRank = $index + 1;
                $myCompanyData = $item;
                break;
            }
        }
    }
    
    // Show all companies
    $leaderboardData = $allData;
}

// HICM Level Colors
$levelColors = [
    1 => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'name' => 'เริ่มต้น'],
    2 => ['bg' => '#FEF3C7', 'color' => '#92400E', 'name' => 'พัฒนา'],
    3 => ['bg' => '#D1FAE5', 'color' => '#065F46', 'name' => 'ก้าวหน้า'],
    4 => ['bg' => '#DBEAFE', 'color' => '#1E40AF', 'name' => 'ยั่งยืน'],
    5 => ['bg' => '#EDE9FE', 'color' => '#5B21B6', 'name' => 'เป็นเลิศ'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Form Leaderboard - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 50%, #5B21B6 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
            text-align: center;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .page-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #FFD700;
        }
        
        .page-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .page-subtitle { opacity: 0.9; font-size: 1rem; }
        
        .period-badge {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.5rem 1.5rem;
            background: rgba(255,255,255,0.2);
            border-radius: var(--radius-full);
            font-size: 0.9rem;
        }
        
        /* My Rank Card */
        .my-rank-card {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #F59E0B;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .my-rank-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .my-rank-number {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: #92400E;
            box-shadow: var(--shadow-md);
        }
        
        .my-rank-details h3 {
            margin: 0 0 0.25rem 0;
            color: #92400E;
        }
        
        .my-rank-details p {
            margin: 0;
            color: #B45309;
            font-size: 0.9rem;
        }
        
        .my-score {
            text-align: right;
        }
        
        .my-score .score-value {
            font-size: 2rem;
            font-weight: 700;
            color: #92400E;
        }
        
        .my-score .score-label {
            font-size: 0.85rem;
            color: #B45309;
        }
        
        /* Leaderboard */
        .leaderboard-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .leaderboard-header {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .leaderboard-header h2 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--gray-700);
        }
        
        .leaderboard-list {
            padding: 1rem;
        }
        
        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 0.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            transition: all 0.2s;
        }
        
        .leaderboard-item:hover {
            background: var(--gray-100);
            transform: translateX(4px);
        }
        
        .leaderboard-item.rank-1 {
            background: linear-gradient(135deg, #FEF3C7, #FDE68A);
            border: 2px solid #F59E0B;
        }
        
        .leaderboard-item.rank-2 {
            background: linear-gradient(135deg, #F3F4F6, #E5E7EB);
            border: 2px solid #9CA3AF;
        }
        
        .leaderboard-item.rank-3 {
            background: linear-gradient(135deg, #FED7AA, #FDBA74);
            border: 2px solid #EA580C;
        }
        
        .leaderboard-item.my-company {
            border: 2px solid #8B5CF6;
            background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
        }
        
        .rank-badge {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            border-radius: var(--radius-lg);
            background: white;
            color: var(--gray-600);
        }
        
        .rank-1 .rank-badge {
            background: #F59E0B;
            color: white;
        }
        
        .rank-2 .rank-badge {
            background: #9CA3AF;
            color: white;
        }
        
        .rank-3 .rank-badge {
            background: #EA580C;
            color: white;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }
        
        .company-industry {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        .score-badge {
            text-align: center;
            min-width: 100px;
        }
        
        .score-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #8B5CF6;
        }
        
        .level-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        /* Disabled State */
        .leaderboard-disabled {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--gray-50);
            border-radius: var(--radius-xl);
        }
        
        .leaderboard-disabled i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }
        
        .leaderboard-disabled h2 {
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .leaderboard-disabled p {
            color: var(--gray-500);
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <i class="fas fa-trophy"></i>
                <h1 class="page-title">Top Form Leaderboard</h1>
                <p class="page-subtitle">อันดับบริษัทที่มีผลการประเมินดีเด่น</p>
                <?php if ($currentPeriod): ?>
                <div class="period-badge">
                    <i class="fas fa-calendar"></i>
                    <?php echo htmlspecialchars($currentPeriod['name']); ?> (<?php echo $currentPeriod['year']; ?>)
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($leaderboardEnabled): ?>
                <!-- My Company Rank -->
                <?php if ($myCompanyData && $myCompanyRank): ?>
                <div class="my-rank-card">
                    <div class="my-rank-info">
                        <div class="my-rank-number">
                            #<?php echo $myCompanyRank; ?>
                        </div>
                        <div class="my-rank-details">
                            <h3><?php echo htmlspecialchars($myCompanyData['company_name']); ?></h3>
                            <p><i class="fas fa-building"></i> อันดับของบริษัทคุณ</p>
                        </div>
                    </div>
                    <div class="my-score">
                        <div class="score-value"><?php echo number_format($myCompanyData['final_score'], 2); ?></div>
                        <div class="score-label">คะแนน • Level <?php echo $myCompanyData['hicm_level']; ?></div>
                    </div>
                </div>
                <?php elseif ($user['company_id']): ?>
                <div class="my-rank-card" style="background: var(--gray-100); border-color: var(--gray-300);">
                    <div class="my-rank-info">
                        <div class="my-rank-number" style="color: var(--gray-500);">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="my-rank-details">
                            <h3 style="color: var(--gray-700);">รอประกาศผล</h3>
                            <p style="color: var(--gray-500);">บริษัทของคุณยังไม่มีในอันดับ Leaderboard</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Leaderboard -->
                <div class="leaderboard-container">
                    <div class="leaderboard-header">
                        <h2><i class="fas fa-medal"></i> อันดับทั้งหมด</h2>
                        <span style="color: var(--gray-500); font-size: 0.9rem;">
                            <?php echo count($leaderboardData); ?> บริษัท
                        </span>
                    </div>
                    <div class="leaderboard-list">
                        <?php if (empty($leaderboardData)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--gray-500);">
                            <i class="fas fa-chart-bar" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                            <p>ยังไม่มีข้อมูล Leaderboard</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($leaderboardData as $index => $item): 
                                $rank = $index + 1;
                                $rankClass = '';
                                if ($rank == 1) $rankClass = 'rank-1';
                                elseif ($rank == 2) $rankClass = 'rank-2';
                                elseif ($rank == 3) $rankClass = 'rank-3';
                                
                                $isMyCompany = $user['company_id'] && $item['company_id'] == $user['company_id'];
                                $levelInfo = $levelColors[$item['hicm_level']] ?? $levelColors[1];
                            ?>
                            <div class="leaderboard-item <?php echo $rankClass; ?> <?php echo $isMyCompany ? 'my-company' : ''; ?>">
                                <div class="rank-badge">
                                    <?php if ($rank == 1): ?>
                                        <i class="fas fa-trophy"></i>
                                    <?php elseif ($rank == 2): ?>
                                        <i class="fas fa-medal"></i>
                                    <?php elseif ($rank == 3): ?>
                                        <i class="fas fa-award"></i>
                                    <?php else: ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="company-info">
                                    <div class="company-name">
                                        <?php echo htmlspecialchars($item['company_name']); ?>
                                        <?php if ($isMyCompany): ?>
                                        <span style="color: #8B5CF6; font-size: 0.8rem;"> (บริษัทของคุณ)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="company-industry"><?php echo htmlspecialchars($item['industry_type'] ?? '-'); ?></div>
                                </div>
                                <div class="score-badge">
                                    <div class="score-value"><?php echo number_format($item['final_score'], 2); ?></div>
                                    <div class="level-badge" style="background: <?php echo $levelInfo['bg']; ?>; color: <?php echo $levelInfo['color']; ?>;">
                                        Level <?php echo $item['hicm_level']; ?> - <?php echo $levelInfo['name']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Leaderboard Disabled / Not Announced -->
                <div class="leaderboard-disabled">
                    <i class="fas fa-lock"></i>
                    <h2>Leaderboard ยังไม่เปิดให้แสดง</h2>
                    <p>ผู้ดูแลระบบยังไม่ได้ประกาศผลคะแนนในขณะนี้<br>Leaderboard จะแสดงเมื่อมีการประกาศผลคะแนนอย่างเป็นทางการ</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
</body>
</html>
