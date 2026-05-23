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

$assessmentResult = getOrCreateAssessment($companyId);
if (!$assessmentResult['success']) {
    setFlashMessage('ไม่พบข้อมูลการประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/dashboard.php');
}


$assessmentId = $assessmentResult['assessment']['id'];
$assessment = getAssessmentWithScores($assessmentId);

if (!$assessment) {
    setFlashMessage('ไม่พบข้อมูลแบบประเมิน', 'error');
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// ดึงข้อมูลบริษัทสำหรับ card
require_once __DIR__ . '/../includes/companies.php';
$companyInfo = getCompanyById($companyId);

// Fetch history (using Self Score instead of Final Score which might include Auditor score)
// We might need to adjust the history query if it relies on final_score in table
$history = getCompanyAssessmentHistory($companyId);

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
// Re-calculate level based on self score for display? Or use the official level?
// Usually if companies can't see auditor score, they see their own projected level.
// But the database 'hicm_level' might be based on auditor.
// Let's calculate self-level for display purposes to be consistent.
$displayLevel = calculateHICMLevel($displayScore);

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

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผลการประเมินตนเอง - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <script src="<?php echo getBaseUrl(); ?>/assets/js/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>
            
            <div class="page-header mb-6">
                <div>
                    <h1 class="page-title text-3xl font-bold">ผลการประเมินตนเอง</h1>
                    <nav class="flex items-center gap-2 text-sm text-gray-500 mt-2">
                        <a href="<?php echo getBaseUrl(); ?>/pages/dashboard.php" class="hover:text-primary transition-colors">หน้าหลัก</a>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                        <span class="text-gray-900">ผลการประเมิน</span>
                    </nav>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.print()" class="btn btn-outline flex items-center gap-2">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><rect x="2" y="9" width="20" height="11"/><path d="M6 19v2a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                        <span>พิมพ์</span>
                    </button>
                    <button onclick="triggerExport()" class="btn btn-outline flex items-center gap-2">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
                        <span>Export Excel</span>
                    </button>
                </div>
            </div>

            <!-- Hero Dashboard: Pro Version -->
            <div class="dashboard-hero-pro animate-fade-in-up">
                <!-- Background Elements -->
                <div class="hero-bg-accent"></div>
                <div class="hero-bg-pattern"></div>
                
                <div class="hero-grid-pro">
                    <!-- 1. Company Identity (Left) -->
                    <div class="company-identity-card">
                        <div class="company-avatar-pro">
                            <?php 
                            $avatarFile = $companyInfo['company_owner_avatar'] ?? $companyInfo['logo'] ?? 'default';
                            $hasAvatar = ($avatarFile && $avatarFile !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatarFile));
                            
                            if ($hasAvatar): 
                            ?>
                                <img src="<?php echo getBaseUrl() . '/assets/uploads/avatars/' . $avatarFile; ?>" alt="Logo">
                            <?php else: ?>
                                <div class="avatar-placeholder-pro">
                                    <span><?php echo mb_substr($companyInfo['company_name'], 0, 1); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-badge-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                            </div>
                        </div>
                        
                        <div class="company-text-pro">
                            <h2 class="company-name-title"><?php echo htmlspecialchars($companyInfo['company_name']); ?></h2>
                            <?php if (!empty($companyInfo['company_name_en'])): ?>
                                <p class="company-name-sub"><?php echo htmlspecialchars($companyInfo['company_name_en']); ?></p>
                            <?php endif; ?>
                            
                            <div class="company-tags-pro">
                                <?php if(!empty($companyInfo['industry_type'])): ?>
                                    <?php 
                                    $industries = is_array($companyInfo['industry_type']) ? $companyInfo['industry_type'] : explode('|', $companyInfo['industry_type']);
                                    foreach(array_slice($industries, 0, 2) as $ind): 
                                        if(empty($ind)) continue;
                                    ?>
                                        <span class="tag-pro tag-glass">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4 8 4v14M8 21v-4h8v4"/></svg>
                                            <?php echo htmlspecialchars($ind); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <span class="tag-pro tag-light">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?php echo htmlspecialchars($companyInfo['contact_name']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Score Center Gauge (Center) -->
                    <div class="score-center-card">
                        <div class="score-gauge-container">
                            <!-- SVG Gauge -->
                            <svg class="score-svg" viewBox="0 0 200 200">
                                <!-- Track -->
                                <circle cx="100" cy="100" r="85" fill="none" stroke="#e2e8f0" stroke-width="15" stroke-linecap="round" stroke-dasharray="400" stroke-dashoffset="100" transform="rotate(135 100 100)" />
                                
                                <!-- Progress -->
                                <?php 
                                    // Calculate Gauge
                                    // Arc is 270 degrees (from 135 to 45) -> 3/4 circle
                                    // Circumference 2*pi*85 = 534
                                    // Max dasharray for 270deg = 534 * 0.75 = 400
                                    $maxVal = 400;
                                    $scorePct = $displayScore / 1000;
                                    $dashOffset = $maxVal - ($scorePct * $maxVal);
                                ?>
                                <defs>
                                    <linearGradient id="scoreGradientMain" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" stop-color="#4f46e5" />
                                        <stop offset="50%" stop-color="#8b5cf6" />
                                        <stop offset="100%" stop-color="#ec4899" />
                                    </linearGradient>
                                    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                                        <feGaussianBlur stdDeviation="3" result="blur"/>
                                        <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                                    </filter>
                                </defs>
                                <circle cx="100" cy="100" r="85" fill="none" stroke="url(#scoreGradientMain)" stroke-width="15" stroke-linecap="round" 
                                        stroke-dasharray="<?php echo $maxVal; ?>" 
                                        stroke-dashoffset="<?php echo $maxVal; /* Start empty */ ?>" 
                                        transform="rotate(135 100 100)"
                                        class="gauge-progress-anim"
                                        style="--target-offset: <?php echo $dashOffset; ?>;" />
                                        
                                <!-- Inner Decorative Ring -->
                                <circle cx="100" cy="100" r="65" fill="none" stroke="rgba(99, 102, 241, 0.1)" stroke-width="2" stroke-dasharray="4 4" />
                            </svg>
                            
                            <div class="score-gauge-content">
                                <div class="score-label-mini">HICM SCORE</div>
                                <div class="score-value-big"><?php echo number_format($displayScore, 0); ?></div>
                                <div class="score-total-mini">/ 1,000</div>
                            </div>
                        </div>
                        
                        <?php $levelInfo = getLevelInfo($displayLevel); ?>
                        <div class="level-badge-pro" style="background: <?php echo $levelInfo['bg']; ?>; color: <?php echo $levelInfo['color']; ?>; border: 1px solid <?php echo $levelInfo['color']; ?>30;">
                            <span class="level-dot" style="background: <?php echo $levelInfo['color']; ?>;"></span>
                            Level <?php echo $displayLevel; ?>: <?php echo $levelInfo['name_en']; ?>
                        </div>
                    </div>

                    <!-- 3. Key Stats (Right) -->
                    <div class="stats-column-pro">
                        <!-- Card 1: Status -->
                        <div class="stat-item-pro">
                            <div class="stat-icon-box <?php echo $assessment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="stat-info-pro">
                                <div class="stat-label-pro">สถานะปัจจุบัน</div>
                                <div class="stat-value-pro"><?php echo $sText; ?></div>
                            </div>
                        </div>

                        <!-- Card 2: Year -->
                        <div class="stat-item-pro">
                            <div class="stat-icon-box info">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="stat-info-pro">
                                <div class="stat-label-pro">รอบการประเมิน</div>
                                <div class="stat-value-pro">ปี <?php echo $assessment['year']; ?></div>
                                <div class="stat-sub-pro"><?php echo htmlspecialchars($assessment['period_name']); ?></div>
                            </div>
                        </div>

                        <!-- Card 3: Submitted Date -->
                        <div class="stat-item-pro">
                             <div class="stat-icon-box purple">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                            <div class="stat-info-pro">
                                <div class="stat-label-pro">ส่งเมื่อ</div>
                                <div class="stat-value-pro"><?php echo $assessment['submitted_at'] ? date('d/m/Y', strtotime($assessment['submitted_at'])) : '-'; ?></div>
                                <div class="stat-sub-pro"><?php echo $assessment['submitted_at'] ? date('H:i', strtotime($assessment['submitted_at'])) . ' น.' : ''; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics Section: Pro Version -->
            <div class="analytics-section-pro mb-8">
                <div class="analytics-header">
                    <h3 class="section-title-pro">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        ผลการวิเคราะห์ข้อมูล
                    </h3>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- History Chart -->
                    <div class="card card-pro animate-fade-in-up" style="animation-delay: 0.1s;">
                        <div class="card-header-pro">
                            <h4 class="card-title-pro">พัฒนาการคะแนน (History)</h4>
                            <div class="card-action-pro">
                                <span class="badge-soft-primary">รายปี</span>
                            </div>
                        </div>
                        <div class="card-body-pro">
                            <div class="chart-container" style="height: 320px;">
                                <canvas id="historyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Radar Chart -->
                    <div class="card card-pro animate-fade-in-up" style="animation-delay: 0.2s;">
                        <div class="card-header-pro">
                            <h4 class="card-title-pro">ความสมดุล 4 มิติ (Balance)</h4>
                            <div class="card-action-pro">
                                <span class="badge-soft-purple">4 Pillars</span>
                            </div>
                        </div>
                        <div class="card-body-pro">
                            <div class="chart-container" style="height: 320px;">
                                <canvas id="resultRadarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Detailed Breakdown: Pro Version -->
             <div class="mb-6 flex items-center justify-between">
                <div class="analytics-header mb-0">
                    <h3 class="section-title-pro">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        รายละเอียดผลการประเมินรายด้าน
                    </h3>
                </div>
            </div>
            
            <div class="space-y-8">
                <?php
                // Re-use pillar info array
                $pillarInfo = [
                    'H1' => ['name_en' => 'Health Promotion',               'name_th' => 'การส่งเสริมสุขภาพ',              'color' => '#10B981', 'bg' => '#ECFDF5', 'border' => '#A7F3D0', 'icon' => 'heart'],
                    'I2' => ['name_en' => 'Industrial Safety & Environment', 'name_th' => 'ความปลอดภัยและสิ่งแวดล้อม',     'color' => '#3B82F6', 'bg' => '#EFF6FF', 'border' => '#BFDBFE', 'icon' => 'shield'],
                    'C3' => ['name_en' => 'Community Engagement',            'name_th' => 'การมีส่วนร่วมกับชุมชน',         'color' => '#F59E0B', 'bg' => '#FFFBEB', 'border' => '#FDE68A', 'icon' => 'users'],
                    'M4' => ['name_en' => 'Management & Sustainability',     'name_th' => 'การบริหารจัดการและความยั่งยืน', 'color' => '#8B5CF6', 'bg' => '#F5F3FF', 'border' => '#DDD6FE', 'icon' => 'chart']
                ];
                
                foreach ($assessment['pillars'] as $pillarCode => $pillar):
                    $info = $pillarInfo[$pillarCode];
                    $activeIndicators = array_filter($pillar['indicators'], fn($i) => !$i['is_na']);
                    $activeCount = count($activeIndicators);
                    
                    // Count self score for this pillar overview
                    $pillarSelfScore = array_sum(array_column($activeIndicators, 'self_score'));
                ?>
                    <div class="pillar-card-pro animate-fade-in-up" style="border-left: 5px solid <?php echo $info['color']; ?>;">
                        <div class="pillar-header-pro" style="background: linear-gradient(to right, <?php echo $info['bg']; ?>, #ffffff);">
                            <div class="flex items-center gap-4">
                                <div class="pillar-icon-box" style="background: white; color: <?php echo $info['color']; ?>; border: 1px solid <?php echo $info['border']; ?>;">
                                    <?php if ($pillarCode === 'H1'): ?>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                                    <?php elseif ($pillarCode === 'I2'): ?>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                                    <?php elseif ($pillarCode === 'C3'): ?>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                                    <?php else: ?>
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h3 class="pillar-title-pro" style="color: <?php echo $info['color']; ?>;"><?php echo $pillarCode . ': ' . $info['name_en']; ?></h3>
                                    <p style="font-size: 0.72rem; color: #6B7280; font-weight: 500; margin: 0.1rem 0 0; letter-spacing: 0.01em;"><?php echo $info['name_th']; ?></p>
                                    <p class="pillar-subtitle-pro">จำนวน <?php echo $activeCount; ?> ตัวชี้วัด</p>
                                </div>
                            </div>
                            <!-- Pillar Score Summary -->
                            <div class="text-right hidden sm:block">
                                <div class="text-xs text-gray-500 uppercase font-bold tracking-wider">คะแนนรวม</div>
                                <div class="text-3xl font-bold" style="color: <?php echo $info['color']; ?>;"><?php echo number_format($pillarSelfScore, 2); ?></div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse table-pro">
                                <thead>
                                    <tr>
                                        <th class="w-[50%]">ตัวชี้วัด</th>
                                        <th class="w-[20%] text-center">ผลการประเมิน</th>
                                        <th class="w-[30%]">หลักฐานแนบ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pillar['indicators'] as $indicator): ?>
                                    <tr class="indicator-row-pro <?php echo $indicator['is_na'] ? 'is-na' : ''; ?>">
                                        <td class="align-top">
                                            <div class="flex gap-4">
                                                <div class="indicator-code-box"><?php echo $indicator['indicator_code']; ?></div>
                                                <div>
                                                    <div class="indicator-name-pro"><?php echo $indicator['indicator_name']; ?></div>
                                                    <div class="indicator-desc-pro"><?php echo $indicator['description']; ?></div>
                                                    <?php if (!empty($indicator['self_evidence'])): ?>
                                                        <div class="evidence-box-pro">
                                                            <strong class="text-gray-700 block mb-1">คำอธิบายประกอบ:</strong>
                                                            <?php echo nl2br(htmlspecialchars($indicator['self_evidence'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="align-top text-center">
                                            <?php if ($indicator['is_na']): ?>
                                                <span class="badge-na">N/A</span>
                                            <?php else: ?>
                                                <div class="score-pill">
                                                    <span class="score-val"><?php echo number_format($indicator['self_score'], 1); ?></span>
                                                    <span class="score-max">/ <?php echo $indicator['weight'] ?? 1; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-top">
                                            <?php if ($indicator['attachment_count'] > 0): ?>
                                                <button onclick="showAttachments(<?php echo $indicator['indicator_id']; ?>)" 
                                                        class="file-btn-pro group">
                                                    <div class="file-icon-bg">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><circle cx="12" cy="14" r="2"/></svg>
                                                    </div>
                                                    <span>ดู <?php echo $indicator['attachment_count']; ?> ไฟล์</span>
                                                </button>
                                            <?php else: ?>
                                                <div class="no-file-pro">
                                                    <span>- ไม่มีไฟล์แนบ -</span>
                                                </div>
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

        /* Pro Dashboard Styles */
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        }

        .dashboard-hero {
            position: relative;
            border-radius: 24px;
            background: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 100%);
            overflow: hidden;
            box-shadow: var(--glass-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.5);
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            padding: 2.5rem;
            position: relative;
            z-index: 10;
        }

        @media (min-width: 1024px) {
            .hero-content {
                grid-template-columns: 350px 1fr 300px;
                align-items: center;
            }
        }

        .company-profile-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding-right: 0;
        }

        @media (min-width: 1024px) {
            .company-profile-section {
                align-items: flex-start;
                text-align: left;
                padding-right: 2rem;
                border-right: 1px solid rgba(0,0,0,0.05);
            }
        }

        .company-logo-ring {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            padding: 4px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }

        .company-logo-ring img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .score-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .score-circle-container {
            position: relative;
            width: 200px;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .score-value {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .score-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card-mini {
            background: rgba(255,255,255,0.6);
            padding: 1rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
        }
        
        .stat-card-mini:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .pillar-card {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
            background: #fff;
        }

        .pillar-header {
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-bottom: 1px solid var(--gray-100);
        }

        .indicator-row {
            transition: background-color 0.15s;
        }
        
        .indicator-row:hover {
            background-color: #f8fafc;
        }

        .score-badge {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            min-width: 80px;
            padding: 0.5rem;
            border-radius: 12px;
            background: #f0fdf4;
            color: #166534;
        }

        .file-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .file-pill:hover {
            background: #dbeafe;
        }


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
                    <a id="previewDownload" href="#" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem;" download>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span>ดาวน์โหลด</span>
                    </a>
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
        // Assessment Attachments Data
        const assessmentAttachments = <?php echo json_encode($attachments); ?>;
        const baseUrl = "<?php echo getBaseUrl(); ?>";

        function getFileIcon(type) {
            const size = 24;
            const stroke = 1.5;
            // PDF
            if (type === 'application/pdf') {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="${stroke}"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>`;
            }
            // Image
            if (type.startsWith('image/')) {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="${stroke}"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
            }
            // Word
            if (type.includes('msword') || type.includes('wordprocessingml')) {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="${stroke}"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
            }
            // Excel
            if (type.includes('excel') || type.includes('spreadsheetml')) {
                return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="${stroke}"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>`;
            }
            // Default File
            return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="${stroke}"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>`;
        }

        function showAttachments(indicatorId) {
            const modal = document.getElementById('attachmentModal');
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = ''; // Clear previous content

            const files = assessmentAttachments[indicatorId] || [];
            
            if (files.length === 0) {
                fileList.innerHTML = `
                    <div style="text-align: center; color: var(--gray-500); padding: 3rem; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0;">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="color: #cbd5e1; margin-bottom: 1rem;">
                            <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/>
                            <polyline points="13 2 13 9 20 9"/>
                        </svg>
                        <div>ไม่พบไฟล์แนบ</div>
                    </div>`;
            } else {
                files.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.style.cssText = `
                        display: flex; 
                        align-items: center; 
                        justify-content: space-between; 
                        padding: 1.25rem; 
                        background: white; 
                        border: 1px solid #f1f5f9; 
                        border-bottom: 1px solid #e2e8f0;
                        transition: all 0.2s;
                    `;
                    // First item gets top radius, last gets bottom radius if we want rounded list
                    if (index === 0) fileItem.style.borderTopLeftRadius = '12px';
                    if (index === 0) fileItem.style.borderTopRightRadius = '12px';
                    if (index === files.length - 1) fileItem.style.borderBottomLeftRadius = '12px';
                    if (index === files.length - 1) fileItem.style.borderBottomRightRadius = '12px';
                    if (index === files.length - 1) fileItem.style.borderBottom = '1px solid #f1f5f9';

                    fileItem.onmouseover = function() { 
                        this.style.backgroundColor = '#f8fafc';
                        this.style.zIndex = '1';
                    };
                    fileItem.onmouseout = function() { 
                        this.style.backgroundColor = 'white';
                        this.style.zIndex = '0';
                    };

                    const isImageOrPdf = file.type === 'application/pdf' || file.type.startsWith('image/');
                    const iconHtml = getFileIcon(file.type);
                    const fileUrl = `${baseUrl}/api/get-attachment.php?id=${file.id}`;
                    
                    fileItem.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 1.25rem; overflow: hidden; flex: 1;">
                            <div style="
                                width: 48px; 
                                height: 48px; 
                                background: #f8fafc; 
                                border-radius: 12px; 
                                display: flex; 
                                align-items: center; 
                                justify-content: center; 
                                flex-shrink: 0;
                                border: 1px solid #e2e8f0;
                            ">
                                ${iconHtml}
                            </div>
                            <div style="min-width: 0; flex: 1;">
                                <div style="font-weight: 500; color: var(--gray-900); font-size: 0.95rem; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${file.name}">
                                    ${file.name}
                                </div>
                                <div style="font-size: 0.8rem; color: var(--gray-500); display: flex; gap: 0.75rem;">
                                    <span>${formatBytes(file.size)}</span>
                                    <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%; align-self: center;"></span>
                                    <span>${file.date}</span>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.75rem; flex-shrink: 0; margin-left: 1rem;">
                            <!-- Preview Button -->
                            <button type="button" onclick="previewFile('${file.id}', '${file.name}', '${fileUrl}', '${file.type}')" 
                               style="
                                   display: inline-flex;
                                   align-items: center;
                                   justify-content: center;
                                   width: 36px;
                                   height: 36px;
                                   border: none;
                                   border-radius: 8px;
                                   cursor: pointer;
                                   color: var(--primary);
                                   background-color: #eff6ff;
                                   transition: all 0.2s;
                               "
                               title="ดูตัวอย่าง"
                               onmouseover="this.style.backgroundColor='#dbeafe'"
                               onmouseout="this.style.backgroundColor='#eff6ff'">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            
                            <!-- Download Button -->
                            <a href="${fileUrl}" target="_blank" 
                               style="
                                   display: inline-flex;
                                   align-items: center;
                                   justify-content: center;
                                   width: 36px;
                                   height: 36px;
                                   border-radius: 8px;
                                   color: var(--gray-600);
                                   background-color: #f1f5f9;
                                   transition: all 0.2s;
                               "
                               title="ดาวน์โหลด"
                               onmouseover="this.style.backgroundColor='#e2e8f0'; this.style.color='var(--gray-900)'"
                               onmouseout="this.style.backgroundColor='#f1f5f9'; this.style.color='var(--gray-600)'">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                            </a>
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

        function previewFile(fileId, fileName, fileUrl, fileType) {
            const modal = document.getElementById('previewModal');
            const title = document.getElementById('previewTitle');
            const body = document.getElementById('previewBody');
            const downloadBtn = document.getElementById('previewDownload');
            
            title.textContent = fileName;
            downloadBtn.href = fileUrl;
            body.innerHTML = '';
            
            // Close attachment modal temporarily or keep it open?? 
            // Usually previews overlay everything. The z-index of preview is 9999, attachment is 1000. So it sits on top.
            
            if (fileType.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = fileUrl + '&inline=1'; 
                img.onload = function() {
                    // Optional: adjust size if needed
                };
                img.onerror = function() {
                    body.innerHTML = '<div style="color: var(--danger);">ไม่สามารถโหลดรูปภาพได้</div>';
                };
                body.appendChild(img);
            } else if (fileType === 'application/pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = fileUrl + '&inline=1';
                body.appendChild(iframe);
            } else {
                body.innerHTML = `
                    <div style="text-align: center; padding: 2rem;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5" style="margin-bottom: 1rem;">
                            <path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><polyline points="13 2 13 9 20 9"/>
                        </svg>
                        <p style="color: var(--gray-600); margin-bottom: 1.5rem; font-size: 1.1rem;">ไม่สามารถแสดงตัวอย่างไฟล์ประเภทนี้ได้โดยตรง</p>
                        <a href="${fileUrl}" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;" download>
                             <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            ดาวน์โหลดไฟล์เพื่อเปิดอ่าน
                        </a>
                    </div>
                `;
            }
            
            modal.style.display = 'flex';
        }
        
        function closePreview() {
            document.getElementById('previewModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const attachmentModal = document.getElementById('attachmentModal');
            const previewModal = document.getElementById('previewModal');
            
            if (event.target == attachmentModal) {
                closeModal();
            }
            if (event.target == previewModal) {
                closePreview();
            }
        }

        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

    </script>
    <script>
        // History Chart
        <?php if (!empty($history)): ?>
        const historyCtx = document.getElementById('historyChart').getContext('2d');
        new Chart(historyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($history, 'year')); ?>,
                datasets: [{
                    label: 'คะแนนตนเอง',
                    data: <?php echo json_encode(array_column($history, 'self_total_score')); ?>,
                    borderColor: 'var(--primary)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'var(--primary)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        max: 1000
                    }
                }
            }
        });
        <?php else: ?>
        const historyChart = document.getElementById('historyChart');
        if (historyChart) {
            historyChart.closest('.card').style.display = 'none';
        }
        <?php endif; ?>

        // Radar Chart (Self Only)
        const radarCtx = document.getElementById('resultRadarChart').getContext('2d');
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
                    pointBackgroundColor: 'var(--primary)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'var(--primary)'
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

        // Trigger Excel Export
        function triggerExport() {
            const data = [
                ["ตัวชี้วัด", "คะแนนตนเอง", "รายละเอียด/หลักฐาน"],
                <?php foreach ($assessment['pillars'] as $pillar): ?>
                    <?php foreach ($pillar['indicators'] as $indicator): ?>
                        [
                            "<?php echo addslashes($indicator['indicator_name']); ?>",
                            "<?php echo $indicator['is_na'] ? 'N/A' : $indicator['self_score']; ?>",
                            "<?php echo addslashes(str_replace("\n", " ", $indicator['self_evidence'] ?? '')); ?> <?php echo $indicator['attachment_count'] > 0 ? '(มีไฟล์แนบ ' . $indicator['attachment_count'] . ' ไฟล์)' : ''; ?>"
                        ],
                    <?php endforeach; ?>
                <?php endforeach; ?>
            ];

            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Self Assessment");
            XLSX.writeFile(wb, "self_assessment_<?php echo $assessmentId; ?>.xlsx");
        }

        document.querySelectorAll('.animate-fade-in-up').forEach((el, index) => {
            el.style.animationDelay = (index * 0.1) + 's';
        });
    </script>
        /* =========================================
           PRO DASHBOARD STYLES (NEW)
           ========================================= */
        :root {
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            --neon-glow: 0 0 10px rgba(99, 102, 241, 0.5);
        }

        .dashboard-hero-pro {
            position: relative;
            border-radius: 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            overflow: hidden;
            box-shadow: 0 20px 50px -12px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.8);
        }

        .hero-bg-accent {
            position: absolute;
            top: -50%;
            right: -10%;
            width: 70%;
            height: 200%;
            background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(-15deg);
            z-index: 0;
            pointer-events: none;
        }

        .hero-bg-pattern {
            position: absolute;
            inset: 0;
            opacity: 0.4;
            background-image: radial-gradient(#6366f1 0.5px, transparent 0.5px);
            background-size: 20px 20px;
            z-index: 0;
            pointer-events: none;
        }

        .hero-grid-pro {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        @media (min-width: 1024px) {
            .hero-grid-pro {
                grid-template-columns: 1.2fr 1fr 1fr;
                align-items: center;
                gap: 3rem;
            }
        }

        /* 1. Company Identity */
        .company-identity-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1.5rem;
        }

        @media (min-width: 1024px) {
            .company-identity-card {
                flex-direction: row;
                text-align: left;
                align-items: center;
            }
        }

        .company-avatar-pro {
            position: relative;
            width: 90px;
            height: 90px;
            flex-shrink: 0;
        }

        .company-avatar-pro img,
        .avatar-placeholder-pro {
            width: 100%;
            height: 100%;
            border-radius: 24px;
            object-fit: cover;
            box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.3);
            border: 4px solid white;
        }

        .avatar-placeholder-pro {
            background: linear-gradient(135deg, #4f46e5, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .avatar-badge-icon {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 28px;
            height: 28px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .company-name-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
            margin-bottom: 0.25rem;
            background: linear-gradient(90deg, #1e293b, #475569);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .company-name-sub {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 0.75rem;
        }

        .company-tags-pro {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
        }

        @media (min-width: 1024px) {
            .company-tags-pro {
                justify-content: flex-start;
            }
        }

        .tag-pro {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 99px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .tag-glass {
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(0,0,0,0.05);
            color: #475569;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }

        .tag-light {
            background: #f1f5f9;
            color: #64748b;
        }

        /* 2. Score Center Gauge */
        .score-center-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .score-gauge-container {
            position: relative;
            width: 220px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .score-svg {
            width: 100%;
            height: 100%;
            overflow: visible;
        }

        .gauge-progress-anim {
            stroke-dashoffset: var(--target-offset);
            transition: stroke-dashoffset 2s cubic-bezier(0.2, 0, 0, 1);
        }

        .score-gauge-content {
            position: absolute;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            top: 55%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .score-label-mini {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .score-value-big {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 0.9;
            background: linear-gradient(135deg, #4f46e5 0%, #ec4899 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 10px rgba(99, 102, 241, 0.2));
        }

        .score-total-mini {
            font-size: 0.85rem;
            color: #cbd5e1;
            font-weight: 500;
            margin-top: 0.25rem;
        }

        .level-badge-pro {
            margin-top: -1.5rem;
            position: relative;
            z-index: 10;
            padding: 0.5rem 1.25rem;
            border-radius: 99px;
            font-weight: 700;
            font-size: 0.9rem;
            background: white;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .level-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            box-shadow: 0 0 5px currentColor;
        }

        /* 3. Stats Column */
        .stats-column-pro {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 640px) and (max-width: 1023px) {
            .stats-column-pro {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .stat-item-pro {
            background: white;
            padding: 1rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #f1f5f9;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
        }

        .stat-item-pro:hover {
            transform: translateX(5px);
            border-color: #e2e8f0;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.05);
        }

        .stat-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #f8fafc;
            color: #64748b;
        }

        .stat-icon-box.success { background: #ecfdf5; color: #10b981; }
        .stat-icon-box.warning { background: #fffbeb; color: #f59e0b; }
        .stat-icon-box.info { background: #eff6ff; color: #3b82f6; }
        .stat-icon-box.purple { background: #f5f3ff; color: #8b5cf6; }

        .stat-info-pro {
            flex: 1;
            min-width: 0;
        }

        .stat-label-pro {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .stat-value-pro {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.2;
        }

        .stat-sub-pro {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.1rem;
        }
        /* 4. Analytics Section */
        .analytics-section-pro {
            margin-top: 2rem;
        }

        .analytics-header {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title-pro {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title-pro svg {
            color: #6366f1;
        }

        .card-pro {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px -10px rgba(0,0,0,0.05);
            background: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: visible;
        }
        
        .card-pro:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        }

        .card-header-pro {
            padding: 1.5rem;
            border-bottom: 1px solid #f8fafc;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title-pro {
            font-size: 1.1rem;
            font-weight: 700;
            color: #334155;
        }

        .card-body-pro {
            padding: 1.5rem;
        }

        .badge-soft-primary { background: #eff6ff; color: #3b82f6; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }
        .badge-soft-purple { background: #f5f3ff; color: #8b5cf6; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; }

        /* 5. Pillar Cards (Redesigned) */
        .pillar-card-pro {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05); /* Softer shadow */
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
        }

        .pillar-card-pro:hover {
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .pillar-header-pro {
            padding: 1.5rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0,0,0,0.03);
        }

        .pillar-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .pillar-title-pro {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .pillar-subtitle-pro {
            font-size: 0.875rem;
            color: #64748b;
        }

        /* Pro Table Styles */
        .table-pro {
            width: 100%;
        }

        .table-pro th {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            font-weight: 600;
            padding: 1.25rem 2rem;
            background: #f8fafc;
            border-bottom: 2px solid #f1f5f9;
        }

        .indicator-row-pro {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .indicator-row-pro:last-child {
            border-bottom: none;
        }

        .indicator-row-pro:hover {
            background: #f8fafc;
        }

        .indicator-row-pro td {
            padding: 1.5rem 2rem;
        }

        .indicator-row-pro.is-na {
            background: #fcfcfc;
            opacity: 0.6;
        }

        .indicator-code-box {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #cbd5e1;
            font-size: 1.1rem;
            min-width: 40px;
        }

        .indicator-name-pro {
            font-size: 1rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .indicator-desc-pro {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .evidence-box-pro {
            background: #f8fafc;
            border: 1px dashed #e2e8f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            color: #475569;
        }

        /* Score & File Elements */
        .score-pill {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            background: #f0f9ff;
            color: #0369a1;
            padding: 0.5rem 1.5rem;
            border-radius: 12px;
            border: 1px solid #e0f2fe;
        }

        .score-val {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
        }

        .score-max {
            font-size: 0.75rem;
            opacity: 0.7;
            font-weight: 600;
            margin-top: 0.1rem;
        }

        .badge-na {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #f1f5f9;
            color: #94a3b8;
            font-weight: 700;
            border-radius: 6px;
            font-size: 0.8rem;
        }

        .file-btn-pro {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: #475569;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            width: 100%;
            max-width: 200px;
        }

        .file-btn-pro:hover {
            border-color: #6366f1;
            color: #6366f1;
            background: #eef2ff;
            transform: translateX(3px);
        }

        .file-icon-bg {
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            transition: background 0.2s, color 0.2s;
        }

        .file-btn-pro:hover .file-icon-bg {
            background: #6366f1;
            color: white;
        }

        .no-file-pro {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #cbd5e1;
            font-size: 0.875rem;
            font-style: italic;
        }
    </style>
</body>
</html>
