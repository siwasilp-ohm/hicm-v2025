<?php
/**
 * HICM V2025 Assessment System - Announcements & Leaderboard Management
 * ปรับปรุง: เชื่อมต่อ Toggle ประกาศผล (results_announced) สัมพันธ์กับ periods.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/news.php';
require_once __DIR__ . '/../includes/periods.php';

// Check if user is admin
requireAuth();
if (!hasRole(ROLE_ADMIN)) {
    header('Location: dashboard.php');
    exit;
}

$user = getCurrentUser();
$db = getDB();

// Handle POST actions FIRST (before auto-check, to prevent flip-flop)
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Toggle results_announced — ใช้ toggleResultsAnnounced() ตัวเดียวกับ periods.php
        if ($action === 'toggle_results_announced') {
            $periodId = intval($_POST['period_id']);
            $result = toggleResultsAnnounced($periodId);
            if ($result['success']) {
                $icon = $result['results_announced'] ? '🏆' : '🔒';
                $success = $icon . ' ' . $result['message'];
            } else {
                $errors[] = $result['message'];
            }
        }
        
        // Save leaderboard display settings (top N, show/hide)
        elseif ($action === 'save_leaderboard_settings') {
            $periodId = intval($_POST['period_id']);
            $showLeaderboard = isset($_POST['show_leaderboard']) ? 1 : 0;
            $topN = intval($_POST['leaderboard_top_n'] ?? 10);
            
            try {
                $stmt = $db->prepare("UPDATE assessment_periods SET show_leaderboard = ?, leaderboard_top_n = ? WHERE id = ?");
                $stmt->execute([$showLeaderboard, $topN, $periodId]);
                $success = "บันทึกการตั้งค่า Leaderboard สำเร็จ";
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
    
    // Legacy support
    elseif (isset($_POST['save_leaderboard_settings'])) {
        $periodId = intval($_POST['period_id']);
        $showLeaderboard = isset($_POST['show_leaderboard']) ? 1 : 0;
        $topN = intval($_POST['leaderboard_top_n'] ?? 10);
        
        try {
            $stmt = $db->prepare("UPDATE assessment_periods SET show_leaderboard = ?, leaderboard_top_n = ? WHERE id = ?");
            $stmt->execute([$showLeaderboard, $topN, $periodId]);
            $success = "บันทึกการตั้งค่า Leaderboard สำเร็จ";
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
    
    // Redirect to prevent resubmit (PRG pattern)
    if (!empty($success)) {
        setFlashMessage($success, 'success');
        header('Location: ' . getBaseUrl() . '/pages/announcements.php');
        exit;
    }
    if (!empty($errors)) {
        setFlashMessage(implode('<br>', $errors), 'error');
        header('Location: ' . getBaseUrl() . '/pages/announcements.php');
        exit;
    }
}

// Force check auto-announce AFTER POST handling (prevent flip-flop on toggle)
checkAndUpdatePeriodStatuses(true);

// Get flash messages
$flashMsg = getFlashMessage();

$announcements = getAnnouncements();

// Get ALL periods with announcement + leaderboard data
$stmt = $db->prepare("
    SELECT id, name, year, status, 
           announcement_date, results_announced, results_announced_at,
           show_leaderboard, leaderboard_top_n,
           is_active
    FROM assessment_periods 
    WHERE is_active = 1
    ORDER BY year DESC, start_date DESC
");
$stmt->execute();
$periods = $stmt->fetchAll();

// Find the currently announced period (results_announced = 1)
$announcedPeriod = null;
foreach ($periods as $p) {
    if (!empty($p['results_announced'])) {
        $announcedPeriod = $p;
        break;
    }
}

// Find periods that are ready to announce (have scores)
$periodsWithScores = [];
foreach ($periods as $p) {
    $stmtScores = $db->prepare("
        SELECT COUNT(*) FROM assessments 
        WHERE period_id = ? AND status IN ('evaluated', 'completed') AND final_score > 0
    ");
    $stmtScores->execute([$p['id']]);
    $scoreCount = $stmtScores->fetchColumn();
    $p['has_scores'] = $scoreCount > 0;
    $p['score_count'] = $scoreCount;
    $periodsWithScores[] = $p;
}

// Get selected period for preview (default: announced > first with scores > first)
$selectedPeriodId = isset($_GET['preview_period']) ? intval($_GET['preview_period']) : null;
$selectedPeriod = null;

if ($selectedPeriodId) {
    foreach ($periodsWithScores as $p) {
        if ($p['id'] == $selectedPeriodId) { $selectedPeriod = $p; break; }
    }
}
if (!$selectedPeriod && $announcedPeriod) {
    $selectedPeriod = $announcedPeriod;
    foreach ($periodsWithScores as $p) {
        if ($p['id'] == $announcedPeriod['id']) { $selectedPeriod = $p; break; }
    }
}
if (!$selectedPeriod) {
    foreach ($periodsWithScores as $p) {
        if ($p['has_scores']) { $selectedPeriod = $p; break; }
    }
}
if (!$selectedPeriod && !empty($periodsWithScores)) {
    $selectedPeriod = $periodsWithScores[0];
}

// Get Leaderboard Data for preview
$leaderboardData = [];
if ($selectedPeriod) {
    $topN = $selectedPeriod['leaderboard_top_n'] ?: 10;
    $stmt = $db->prepare("
        SELECT 
            c.company_name,
            c.industry_type,
            a.final_score,
            a.hicm_level,
            a.status as assessment_status
        FROM assessments a
        JOIN companies c ON a.company_id = c.id
        WHERE a.period_id = ? AND a.status IN ('evaluated', 'completed') AND a.final_score > 0
        ORDER BY a.final_score DESC, a.hicm_level DESC
        LIMIT 20
    ");
    $stmt->execute([$selectedPeriod['id']]);
    $leaderboardData = $stmt->fetchAll();
}

// Thai month names
$thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการประกาศ & Leaderboard - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ============================================
           ANNOUNCE TOGGLE SWITCH (sync with periods.php)
           ============================================ */
        .announce-toggle {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            cursor: pointer;
        }
        .announce-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
            position: absolute;
        }
        .announce-toggle-slider {
            width: 44px;
            height: 24px;
            background: var(--gray-300);
            border-radius: 12px;
            position: relative;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        .announce-toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: white;
            top: 3px;
            left: 3px;
            transition: all 0.3s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .announce-toggle input:checked + .announce-toggle-slider {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        .announce-toggle input:checked + .announce-toggle-slider::before {
            transform: translateX(20px);
        }
        .announce-toggle-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: var(--gray-500);
            min-width: 24px;
        }
        .announce-toggle input:checked ~ .announce-toggle-label {
            color: var(--success, #10b981);
        }
        
        /* ============================================
           PERIOD ANNOUNCE CARD
           ============================================ */
        .period-announce-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-xl, 12px);
            padding: 1.25rem 1.5rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
            background: white;
        }
        .period-announce-card.is-announced {
            border-color: #10b981;
            background: linear-gradient(135deg, #ecfdf5, #f0fdf4);
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.2);
        }
        .period-announce-card.is-upcoming {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
        }
        .period-announce-card .period-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .period-announce-card .period-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--gray-800);
        }
        .period-announce-card .period-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.375rem;
        }
        .period-announce-card .meta-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-weight: 600;
            white-space: nowrap;
        }
        .period-announce-card .announce-datetime {
            font-size: 0.8rem;
            color: var(--gray-600);
            display: flex;
            align-items: center;
            gap: 0.375rem;
            flex-wrap: wrap;
            margin-top: 0.375rem;
        }
        .period-announce-card .countdown-inline {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
        }
        
        /* Status badges */
        .status-live { background: #d1fae5; color: #065f46; }
        .status-scheduled { background: #fef3c7; color: #92400e; }
        .status-off { background: var(--gray-100); color: var(--gray-600); }
        .status-no-scores { background: #fee2e2; color: #991b1b; }
        
        /* Toggle Switch for leaderboard settings */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--gray-300);
            transition: .3s;
            border-radius: 28px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px; width: 22px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: var(--shadow-sm);
        }
        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #10B981, #059669);
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
        .leaderboard-status {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full, 999px);
            font-size: 0.8rem;
            font-weight: 600;
        }
        .leaderboard-status.on { background: #D1FAE5; color: #059669; }
        .leaderboard-status.off { background: var(--gray-200); color: var(--gray-600); }
        
        /* Leaderboard Preview */
        .leaderboard-preview {
            display: grid;
            gap: 0.5rem;
            max-height: 400px;
            overflow-y: auto;
        }
        .leaderboard-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg, 8px);
            transition: all 0.2s;
        }
        .leaderboard-item:hover { background: var(--gray-100); }
        .leaderboard-item.medal-1 { background: linear-gradient(135deg, #FEF3C7, #FDE68A); border: 1px solid #F59E0B; }
        .leaderboard-item.medal-2 { background: linear-gradient(135deg, #F3F4F6, #E5E7EB); border: 1px solid #9CA3AF; }
        .leaderboard-item.medal-3 { background: linear-gradient(135deg, #FED7AA, #FDBA74); border: 1px solid #EA580C; }
        .leaderboard-item .rank {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1rem; color: var(--gray-600);
        }
        .leaderboard-item .company-info { flex: 1; }
        .leaderboard-item .company-name { font-weight: 600; color: var(--gray-800); }
        .leaderboard-item .industry { font-size: 0.8rem; color: var(--gray-500); }
        .leaderboard-item .score-info { text-align: right; }
        .leaderboard-item .score { font-weight: 700; font-size: 1.1rem; color: #8B5CF6; }
        .leaderboard-item .level { font-size: 0.75rem; color: var(--gray-500); }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg, 8px);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #10B981; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #EF4444; }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="page-title">จัดการประกาศ & Leaderboard</h1>
                    <p class="page-subtitle">ควบคุมการประกาศผลคะแนน, Leaderboard และข่าวประชาสัมพันธ์</p>
                </div>
                <button onclick="openModal()" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    สร้างประกาศใหม่
                </button>
            </div>
            
            <!-- Flash Messages -->
            <?php if ($flashMsg): ?>
            <div style="margin-bottom: 1.5rem;">
                <?php echo $flashMsg; ?>
            </div>
            <?php endif; ?>
            
            <!-- ============================================
                 SECTION 1: ประกาศผลคะแนน (results_announced)
                 ============================================ -->
            <div class="card" style="margin-bottom: 2rem; border: 2px solid #10b981;">
                <div class="card-header" style="background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 1rem 1.5rem;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-bullhorn"></i>
                        ประกาศผลคะแนน
                        <?php if ($announcedPeriod): ?>
                        <span style="background: rgba(255,255,255,0.25); padding: 0.2rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">
                            🟢 LIVE
                        </span>
                        <?php else: ?>
                        <span style="background: rgba(255,255,255,0.15); padding: 0.2rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;">
                            ⚫ ปิดอยู่
                        </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <p style="color: var(--gray-600); margin-bottom: 1.25rem; font-size: 0.875rem;">
                        <i class="fas fa-info-circle" style="color: var(--info, #0ea5e9);"></i>
                        เมื่อเปิดประกาศผล → Leaderboard จะแสดงใน Dashboard ของผู้ใช้ทุกคน · เปิดได้ทีละ 1 รอบ · Toggle นี้สัมพันธ์กับ Toggle ในหน้ารอบการประเมิน
                    </p>
                    
                    <?php if (empty($periodsWithScores)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--gray-500); background: var(--gray-50); border-radius: var(--radius-lg, 8px);">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p>ไม่พบรอบการประเมิน</p>
                    </div>
                    <?php else: ?>
                    
                    <?php foreach ($periodsWithScores as $p): 
                        $isAnnounced = !empty($p['results_announced']);
                        $hasScores = $p['has_scores'];
                        $annDatetime = $p['announcement_date'];
                        $annTs = $annDatetime ? strtotime($annDatetime) : null;
                        $nowTs = time();
                        
                        // Compute state
                        $isPast = $annTs && $annTs <= $nowTs;
                        $isToday = $annTs && date('Y-m-d', $annTs) === date('Y-m-d');
                        $isFuture = $annTs && !$isPast;
                        
                        // Card class
                        $cardClass = '';
                        if ($isAnnounced) $cardClass = 'is-announced';
                        elseif ($isToday || ($isPast && $hasScores && empty($p['results_announced_at']))) $cardClass = 'is-upcoming';
                        
                        // Format date
                        $annDateFormatted = '';
                        $annTimeFormatted = '';
                        if ($annTs) {
                            $annDay = date('j', $annTs);
                            $annMonth = $thaiMonths[(int)date('n', $annTs)];
                            $annYear = (int)date('Y', $annTs) + 543;
                            $annDateFormatted = $annDay . ' ' . $annMonth . ' ' . $annYear;
                            $annTimeFormatted = date('H:i', $annTs) . ' น.';
                        }
                        
                        // Days remaining
                        $daysToAnnounce = $annTs ? intval(round((strtotime(date('Y-m-d', $annTs)) - strtotime(date('Y-m-d'))) / 86400)) : null;
                        
                        // Status badge
                        $statusLabel = '';
                        $statusClass = '';
                        if ($isAnnounced) {
                            $statusLabel = '🏆 LIVE ประกาศผลอยู่';
                            $statusClass = 'status-live';
                        } elseif (!$hasScores) {
                            $statusLabel = '⚠️ ไม่มีผลคะแนน';
                            $statusClass = 'status-no-scores';
                        } elseif ($isPast && empty($p['results_announced_at'])) {
                            $statusLabel = '⏰ ครบกำหนด — รอเปิด';
                            $statusClass = 'status-scheduled';
                        } elseif ($isPast && !empty($p['results_announced_at'])) {
                            $statusLabel = 'ปิดประกาศแล้ว (เคยเปิด)';
                            $statusClass = 'status-off';
                        } elseif ($isFuture) {
                            $statusLabel = '📅 กำหนดวันประกาศ';
                            $statusClass = 'status-scheduled';
                        } else {
                            $statusLabel = 'ยังไม่กำหนดวัน';
                            $statusClass = 'status-off';
                        }
                        
                        // Period status badge
                        $periodStatusMap = [
                            'draft' => ['ฉบับร่าง', '#64748b'],
                            'open' => ['เปิดรับสมัคร', '#3b82f6'],
                            'evaluating' => ['กำลังประเมิน', '#f59e0b'],
                            'closed' => ['ปิดรับแบบประเมิน', '#ef4444'],
                            'completed' => ['เสร็จสิ้น', '#10b981'],
                        ];
                        $pStatus = $periodStatusMap[$p['status']] ?? ['ไม่ทราบ', '#6b7280'];
                    ?>
                    <div class="period-announce-card <?php echo $cardClass; ?>">
                        <div class="period-info">
                            <div style="flex: 1; min-width: 0;">
                                <div class="period-name">
                                    <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['year']; ?>)
                                </div>
                                <div class="period-meta">
                                    <span class="meta-badge" style="background: <?php echo $pStatus[1]; ?>20; color: <?php echo $pStatus[1]; ?>;">
                                        <?php echo $pStatus[0]; ?>
                                    </span>
                                    <span class="meta-badge <?php echo $statusClass; ?>">
                                        <?php echo $statusLabel; ?>
                                    </span>
                                    <?php if ($hasScores): ?>
                                    <span class="meta-badge" style="background: #ede9fe; color: #7c3aed;">
                                        <?php echo $p['score_count']; ?> บริษัท
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($annDateFormatted): ?>
                                <div class="announce-datetime">
                                    <i class="fas fa-calendar-check" style="color: #10b981; font-size: 0.75rem;"></i>
                                    <?php echo $annDateFormatted; ?>
                                    <span style="color: var(--gray-300);">|</span>
                                    <i class="fas fa-clock" style="color: #f59e0b; font-size: 0.75rem;"></i>
                                    <?php echo $annTimeFormatted; ?>
                                    <?php if ($daysToAnnounce !== null && !$isAnnounced): ?>
                                    <span style="color: var(--gray-300);">|</span>
                                    <?php if ($daysToAnnounce > 0): ?>
                                    <span class="countdown-inline status-scheduled">เหลือ <?php echo $daysToAnnounce; ?> วัน</span>
                                    <?php elseif ($daysToAnnounce === 0): ?>
                                    <span class="countdown-inline status-live">🎯 วันนี้!</span>
                                    <?php else: ?>
                                    <span class="countdown-inline status-off">ผ่านไป <?php echo abs($daysToAnnounce); ?> วัน</span>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Toggle Switch -->
                            <div style="text-align: center; flex-shrink: 0;">
                                <?php if ($hasScores): ?>
                                <form method="POST" style="margin: 0;" id="announceForm-<?php echo $p['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_results_announced">
                                    <input type="hidden" name="period_id" value="<?php echo $p['id']; ?>">
                                    <label class="announce-toggle" title="<?php echo $isAnnounced ? 'ปิดประกาศผล' : 'เปิดประกาศผล'; ?>">
                                        <input type="checkbox" <?php echo $isAnnounced ? 'checked' : ''; ?> 
                                               onchange="confirmToggleAnnounce(this, <?php echo $p['id']; ?>, <?php echo $isAnnounced ? 'true' : 'false'; ?>, '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?> (<?php echo $p['year']; ?>)')">
                                        <span class="announce-toggle-slider"></span>
                                        <span class="announce-toggle-label"><?php echo $isAnnounced ? 'ON' : 'OFF'; ?></span>
                                    </label>
                                </form>
                                <?php else: ?>
                                <span style="font-size: 0.7rem; color: var(--gray-400); line-height: 1.3;">ไม่สามารถเปิดได้<br>(ไม่มีคะแนน)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div style="margin-top: 1rem; padding: 0.75rem 1rem; background: var(--gray-50); border-radius: var(--radius-md, 6px); font-size: 0.8rem; color: var(--gray-500);">
                        <i class="fas fa-lightbulb" style="color: #f59e0b;"></i>
                        <strong>Auto-announce:</strong> ระบบจะเปิดประกาศผลอัตโนมัติเมื่อถึงวัน-เวลาที่กำหนด (ต้องมีผลคะแนนอย่างน้อย 1 บริษัท) · 
                        สามารถเปิด/ปิดด้วย Toggle ได้ตลอดเวลา · Toggle นี้สัมพันธ์กับหน้ารอบการประเมิน
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ============================================
                 SECTION 2: Leaderboard Settings & Preview
                 ============================================ -->
            <?php if ($selectedPeriod): ?>
            <div class="card" style="margin-bottom: 2rem; border: 2px solid #8B5CF6;">
                <div class="card-header" style="background: linear-gradient(135deg, #8B5CF6, #6D28D9); color: white; padding: 1rem 1.5rem;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-trophy"></i>
                        Top Form Leaderboard Settings
                    </h3>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <form method="POST" style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
                        <input type="hidden" name="action" value="save_leaderboard_settings">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <label style="font-weight: 500; color: var(--gray-700);">
                                <i class="fas fa-calendar" style="color: #8B5CF6;"></i>
                                รอบ:
                            </label>
                            <select onchange="window.location='?preview_period='+this.value" style="padding: 0.375rem 0.75rem; border-radius: var(--radius-md, 6px); border: 1px solid var(--gray-300); font-size: 0.875rem;">
                                <?php foreach ($periodsWithScores as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $selectedPeriod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['year']; ?>)
                                    <?php echo !empty($p['results_announced']) ? ' 🏆 LIVE' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <label style="font-weight: 500; color: var(--gray-700);">
                                <i class="fas fa-eye" style="color: #8B5CF6;"></i>
                                Leaderboard:
                            </label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="show_leaderboard" <?php echo !empty($selectedPeriod['show_leaderboard']) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="leaderboard-status <?php echo !empty($selectedPeriod['show_leaderboard']) ? 'on' : 'off'; ?>">
                                <?php echo !empty($selectedPeriod['show_leaderboard']) ? 'เปิด' : 'ปิด'; ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <label style="font-weight: 500; color: var(--gray-700);">
                                <i class="fas fa-list-ol" style="color: #8B5CF6;"></i>
                                อันดับ:
                            </label>
                            <select name="leaderboard_top_n" class="form-select" style="width: auto;">
                                <?php foreach ([5, 10, 15, 20, 30, 50] as $n): ?>
                                <option value="<?php echo $n; ?>" <?php echo ($selectedPeriod['leaderboard_top_n'] ?? 10) == $n ? 'selected' : ''; ?>>
                                    Top <?php echo $n; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="background: #8B5CF6;">
                            <i class="fas fa-save"></i>
                            บันทึก
                        </button>
                    </form>
                    
                    <!-- Leaderboard Preview -->
                    <?php if (!empty($leaderboardData)): ?>
                    <div style="margin-top: 1.5rem; border-top: 1px solid var(--gray-200); padding-top: 1.5rem;">
                        <h4 style="margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-eye" style="color: var(--gray-500);"></i>
                            Preview Leaderboard (Top <?php echo min(count($leaderboardData), $selectedPeriod['leaderboard_top_n'] ?? 10); ?>)
                        </h4>
                        <div class="leaderboard-preview">
                            <?php 
                            $topN = $selectedPeriod['leaderboard_top_n'] ?? 10;
                            foreach (array_slice($leaderboardData, 0, $topN) as $index => $item): 
                                $rank = $index + 1;
                                $medalClass = $rank <= 3 ? "medal-{$rank}" : "";
                            ?>
                            <div class="leaderboard-item <?php echo $medalClass; ?>">
                                <div class="rank">
                                    <?php if ($rank == 1): ?>
                                        <i class="fas fa-trophy" style="color: #FFD700;"></i>
                                    <?php elseif ($rank == 2): ?>
                                        <i class="fas fa-medal" style="color: #C0C0C0;"></i>
                                    <?php elseif ($rank == 3): ?>
                                        <i class="fas fa-medal" style="color: #CD7F32;"></i>
                                    <?php else: ?>
                                        <?php echo $rank; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="company-info">
                                    <div class="company-name"><?php echo htmlspecialchars($item['company_name']); ?></div>
                                    <div class="industry"><?php echo htmlspecialchars($item['industry_type'] ?? '-'); ?></div>
                                </div>
                                <div class="score-info">
                                    <div class="score"><?php echo number_format($item['final_score'], 2); ?></div>
                                    <div class="level">Level <?php echo $item['hicm_level']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="margin-top: 1.5rem; padding: 2rem; text-align: center; color: var(--gray-500); background: var(--gray-50); border-radius: var(--radius-lg, 8px);">
                        <i class="fas fa-chart-bar" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                        <p>ยังไม่มีข้อมูล Leaderboard<br><small>จะแสดงเมื่อมีบริษัทที่ผ่านการประเมินแล้ว</small></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ============================================
                 SECTION 3: ข่าวประชาสัมพันธ์
                 ============================================ -->
            <div class="card">
                <div class="card-header" style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0;">
                        <i class="fas fa-newspaper" style="color: var(--primary-500);"></i>
                        ข่าวประชาสัมพันธ์
                    </h3>
                </div>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>หัวข้อ</th>
                                <th style="width: 150px;">สถานะ</th>
                                <th style="width: 180px;">วันที่สร้าง</th>
                                <th style="width: 150px;">ผู้สร้าง</th>
                                <th style="width: 120px;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem; color: var(--gray-500);">ไม่พบข้อมูลข่าวสาร</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($item['title']); ?></div>
                                            <div style="font-size: 0.875rem; color: var(--gray-500); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 300px;">
                                                <?php echo htmlspecialchars(mb_substr($item['content'], 0, 100)) . '...'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($item['status'] === 'active'): ?>
                                                <span class="badge badge-success">แสดงผล</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">ซ่อน</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $date = new DateTime($item['created_at']);
                                            echo $date->format('d/m/Y H:i'); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['author_name'] ?? '-'); ?></td>
                                        <td>
                                            <div class="action-buttons" style="justify-content: flex-start;">
                                                <button onclick='editAnnouncement(<?php echo json_encode($item); ?>)' class="btn btn-icon btn-secondary" title="แก้ไข">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                    </svg>
                                                </button>
                                                <button onclick="deleteAnnouncement(<?php echo $item['id']; ?>)" class="btn btn-icon btn-danger" style="background: var(--danger-light); color: var(--danger);" title="ลบ">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <polyline points="3 6 5 6 21 6"/>
                                                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- News Modal -->
    <div id="newsModal" class="modal-overlay">
        <div class="modal animate-fade-in-up">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">สร้างประกาศใหม่</h3>
            </div>
            <div class="modal-body">
                <form id="newsForm">
                    <input type="hidden" id="newsId" name="id" value="0">
                    <div class="form-group">
                        <label class="form-label required">หัวข้อข่าว</label>
                        <input type="text" class="form-input" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">เนื้อหาข่าว</label>
                        <textarea class="form-textarea" id="content" name="content" rows="6" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">สถานะ</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active">แสดงผล</option>
                            <option value="inactive">ซ่อน</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="btn btn-secondary">ยกเลิก</button>
                <button onclick="saveAnnouncement()" class="btn btn-primary">บันทึก</button>
            </div>
        </div>
    </div>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        const modal = document.getElementById('newsModal');
        const form = document.getElementById('newsForm');

        function openModal() {
            document.getElementById('newsId').value = '0';
            document.getElementById('modalTitle').textContent = 'สร้างประกาศใหม่';
            form.reset();
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
        }

        function editAnnouncement(item) {
            document.getElementById('newsId').value = item.id;
            document.getElementById('title').value = item.title;
            document.getElementById('content').value = item.content;
            document.getElementById('status').value = item.status;
            document.getElementById('modalTitle').textContent = 'แก้ไขประกาศ';
            modal.classList.add('active');
        }

        // =============================================
        // TOGGLE ANNOUNCE — with confirmation (same UX as periods.php)
        // =============================================
        function confirmToggleAnnounce(checkbox, periodId, isCurrentlyOn, periodName) {
            const action = isCurrentlyOn ? 'ปิด' : 'เปิด';
            const icon = isCurrentlyOn ? '🔒' : '🏆';
            const color = isCurrentlyOn ? '#ef4444' : '#10b981';
            
            let html = '';
            if (!isCurrentlyOn) {
                html = `
                    <div style="text-align:left; font-size:0.875rem; margin-top:0.5rem;">
                        <div style="padding:0.5rem 0.75rem; background:#ecfdf5; border-radius:8px; margin-bottom:0.5rem; color:#065f46;">
                            🏆 เปิดประกาศผลรอบ "<strong>${periodName}</strong>"
                        </div>
                        <div style="padding:0.5rem 0.75rem; background:#f0f9ff; border-radius:8px; margin-bottom:0.5rem; color:#0c4a6e;">
                            📊 Leaderboard จะแสดงใน Dashboard ของทุกผู้ใช้
                        </div>
                        <div style="padding:0.5rem 0.75rem; background:#fef3c7; border-radius:8px; color:#92400e;">
                            ⚠️ รอบอื่นที่เปิดอยู่จะถูกปิดอัตโนมัติ (เปิดได้ทีละ 1 รอบ)
                        </div>
                    </div>`;
            } else {
                html = `
                    <div style="text-align:left; font-size:0.875rem; margin-top:0.5rem;">
                        <div style="padding:0.5rem 0.75rem; background:#fee2e2; border-radius:8px; margin-bottom:0.5rem; color:#991b1b;">
                            🔒 ปิดประกาศผลรอบ "<strong>${periodName}</strong>"
                        </div>
                        <div style="padding:0.5rem 0.75rem; background:#f8fafc; border-radius:8px; color:#475569;">
                            📊 Leaderboard จะหายจาก Dashboard ของผู้ใช้
                        </div>
                    </div>`;
            }
            
            Swal.fire({
                title: `${icon} ${action}ประกาศผลคะแนน`,
                html: html,
                showCancelButton: true,
                confirmButtonText: `${action}ประกาศผล`,
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: color,
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('announceForm-' + periodId).submit();
                } else {
                    // Revert checkbox
                    checkbox.checked = isCurrentlyOn;
                }
            });
        }

        async function saveAnnouncement() {
            if (!form.reportValidity()) return;

            const formData = new FormData(form);
            
            try {
                const response = await fetch('<?php echo getBaseUrl(); ?>/api/save-news.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: result.message
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: 'ไม่สามารถเชื่อต่อกับเซิร์ฟเวอร์ได้'
                });
            }
        }

        async function deleteAnnouncement(id) {
            const result = await Swal.fire({
                title: 'ยืนยันการลบ',
                text: "คุณต้องการลบประกาศนี้ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'ลบข้อมูล',
                cancelButtonText: 'ยกเลิก'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('<?php echo getBaseUrl(); ?>/api/delete-news.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    
                    const res = await response.json();
                    
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'ลบสำเร็จ',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: res.message
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: 'ไม่สามารถเชื่อต่อกับเซิร์ฟเวอร์ได้'
                    });
                }
            }
        }

        // Close modal on outside click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>
