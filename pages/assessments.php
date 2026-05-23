<?php
/**
 * HICM V2025 Assessment System - Assessments Management Page
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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $assessmentId = intval($_POST['assessment_id']);
        $newStatus = sanitizeInput($_POST['status']);

        $result = updateAssessmentStatus($assessmentId, $newStatus);
        if ($result['success']) {
            setFlashMessage('อัปเดตสถานะเรียบร้อยแล้ว', 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        redirect(getBaseUrl() . '/pages/assessments.php');
    }

    if (isset($_POST['assign_auditors'])) {
        $assessmentId = intval($_POST['assessment_id']);
        $auditorIds = $_POST['auditor_ids'] ?? [];
        $isRandom = isset($_POST['is_random']);
        $randomCount = intval($_POST['random_count'] ?? 1);

        if ($isRandom) {
            $db = getDB()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY RAND() LIMIT ?");
            $stmt->bindValue(1, $randomCount, PDO::PARAM_INT);
            $stmt->execute();
            $auditorIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($auditorIds)) {
            setFlashMessage('โปรดเลือกผู้ตรวจสอบอย่างน้อย 1 คน หรือเลือกสุ่ม', 'error');
        } else {
            $result = assignMultipleAuditors($assessmentId, $auditorIds);
            if ($result['success']) {
                // Send notifications
                notifyAuditors($assessmentId, $auditorIds);
                setFlashMessage('มอบหมายผู้ตรวจสอบ ' . count($auditorIds) . ' คน เรียบร้อยแล้ว (พร้อมส่งแจ้งเตือนถ้ามีอีเมล)', 'success');
            } else {
                setFlashMessage($result['message'], 'error');
            }
        }
        redirect(getBaseUrl() . '/pages/assessments.php');
    }

    if (isset($_POST['delete_assessment'])) {
        $assessmentId = intval($_POST['assessment_id']);
        $result = deleteAssessment($assessmentId);
        if ($result['success']) {
            setFlashMessage('ลบการประเมินเรียบร้อยแล้ว', 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        redirect(getBaseUrl() . '/pages/assessments.php');
    }
}

// Get filters
$periodId = $_GET['period'] ?? null;
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

$filters = [];
if ($periodId) $filters['period_id'] = $periodId;
if ($statusFilter) $filters['status'] = $statusFilter;
if ($searchFilter) $filters['search'] = $searchFilter;

$assessments = getAllAssessments($filters);
$periods = getAllPeriods();
$auditors = getAuditors();

function getAllPeriods() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM assessment_periods ORDER BY year DESC, start_date DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAuditors() {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name FROM users WHERE role = 'auditor' AND is_active = 1 ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

function updateAssessmentStatus($assessmentId, $status) {
    $db = getDB();

    try {
        // Check period status before allowing update
        $checkStmt = $db->prepare("
            SELECT ap.status as period_status, ap.name as period_name
            FROM assessments a
            JOIN assessment_periods ap ON a.period_id = ap.id
            WHERE a.id = ?
        ");
        $checkStmt->execute([$assessmentId]);
        $periodInfo = $checkStmt->fetch();

        if ($periodInfo && $periodInfo['period_status'] === 'completed') {
            return ['success' => false, 'message' => 'ไม่สามารถเปลี่ยนสถานะได้ เนื่องจากรอบการประเมิน "' . $periodInfo['period_name'] . '" จบโครงการแล้ว'];
        }

        $stmt = $db->prepare("UPDATE assessments SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $assessmentId]);

        if ($stmt->rowCount() > 0) {
            logActivity($_SESSION['user_id'], 'update_assessment_status', 'เปลี่ยนสถานะ assessment ID: ' . $assessmentId . ' เป็น ' . $status);
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'ไม่พบ assessment'];

    } catch (Exception $e) {
        error_log("Update assessment status error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด'];
    }
}

function assignAuditor($assessmentId, $auditorId) {
    $db = getDB();

    try {
        $stmt = $db->prepare("UPDATE assessments SET evaluated_by = ?, status = 'under_review', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$auditorId, $assessmentId]);

        if ($stmt->rowCount() > 0) {
            logActivity($_SESSION['user_id'], 'assign_auditor', 'มอบหมาย auditor ID: ' . $auditorId . ' ให้ assessment ID: ' . $assessmentId);
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'ไม่พบ assessment'];

    } catch (Exception $e) {
        error_log("Assign auditor error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด'];
    }
}

function deleteAssessment($assessmentId) {
    $db = getDB();
    try {
        $conn = $db->getConnection();
        
        // Start transaction
        $conn->beginTransaction();
        
        // Delete scores
        $stmt = $conn->prepare("DELETE FROM assessment_scores WHERE assessment_id = ?");
        $stmt->execute([$assessmentId]);
        
        // Delete attachments/files (if table exists)
        try {
            $stmt = $conn->prepare("DELETE FROM assessment_files WHERE assessment_id = ?");
            $stmt->execute([$assessmentId]);
        } catch (Exception $e) {
            // Table might not exist, continue anyway
        }
        
        // Delete performance reports (if table exists)
        try {
            $stmt = $conn->prepare("DELETE FROM assessment_performance_reports WHERE assessment_id = ?");
            $stmt->execute([$assessmentId]);
        } catch (Exception $e) {
            // Table might not exist, continue anyway
        }
        
        // Delete assessment
        $stmt = $conn->prepare("DELETE FROM assessments WHERE id = ?");
        $stmt->execute([$assessmentId]);
        
        $conn->commit();
        
        logActivity($_SESSION['user_id'], 'delete_assessment', 'ลบ assessment ID: ' . $assessmentId . ' ถาวร');
        
        return ['success' => true, 'message' => 'ลบการประเมินเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Delete assessment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแบบประเมิน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            --radius-xl: 16px;
            --radius-2xl: 24px;
        }

        .main-wrapper {
            background-color: var(--pro-bg);
            min-height: 100vh;
        }

        /* Hero Section */
        .management-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border-radius: var(--radius-2xl);
            padding: 3rem 2.5rem;
            margin-bottom: 2.5rem;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: white;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.2);
        }

        .management-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .hero-tag {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--pro-primary-light);
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hero-title {
            font-size: 2.25rem;
            font-weight: 800;
            margin: 0;
            line-height: 1.2;
            background: linear-gradient(to right, #fff 40%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            margin-top: 0.75rem;
            color: #94a3b8;
            font-size: 1rem;
            max-width: 500px;
        }

        /* Hero Stats Block */
        .hero-stats-group {
            display: flex;
            gap: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .hero-stat-box {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            min-width: 140px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .hero-stat-box:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .stat-val {
            font-size: 2rem;
            font-weight: 800;
            display: block;
            margin-bottom: 0.25rem;
        }

        .stat-lbl {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
        }

        .stat-val.primary { color: var(--pro-primary-light); }
        .stat-val.warning { color: var(--pro-warning); }
        .stat-val.success { color: var(--pro-success); }

        /* Pro Card & Table */
        .pro-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            border: 1px solid var(--pro-border);
            box-shadow: var(--pro-shadow);
            margin-bottom: 2rem;
        }

        /* Card-list layout */
        .assessment-list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .assessment-row {
            display: grid;
            grid-template-columns: 2.5fr 1.2fr 1.2fr 0.8fr 1.1fr auto;
            align-items: center;
            gap: 1rem;
            padding: 1.1rem 2rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s ease;
            position: relative;
        }

        .assessment-row:last-child {
            border-bottom: none;
        }

        .assessment-row:hover {
            background: #f8fafc;
        }

        .assessment-row:hover .ar-company-name {
            color: var(--pro-primary);
        }

        /* Header row */
        .assessment-header {
            display: grid;
            grid-template-columns: 2.5fr 1.2fr 1.2fr 0.8fr 1.1fr auto;
            gap: 1rem;
            padding: 0.85rem 2rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .assessment-header span {
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94a3b8;
        }

        .assessment-header span:last-child {
            text-align: right;
        }

        /* Company cell */
        .ar-company {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }

        .ar-num {
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 800;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .ar-company-info {
            min-width: 0;
        }

        .ar-company-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.92rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: color 0.2s;
        }

        .ar-company-meta {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.3rem;
            flex-wrap: wrap;
        }

        .ar-auditor-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.15rem 0.5rem;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 0.65rem;
            color: #64748b;
            font-weight: 600;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .ar-id {
            font-size: 0.6rem;
            color: #cbd5e1;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
        }

        /* Period cell */
        .ar-period {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.65rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .ar-period.open {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        .ar-period.closed {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #c2410c;
        }

        .ar-period.locked {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .ar-period i {
            font-size: 0.6rem;
        }

        /* Score cell */
        .ar-score {
            font-size: 1.2rem;
            font-weight: 800;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }

        .ar-score.na {
            font-size: 0.8rem;
            font-weight: 600;
            color: #cbd5e1;
        }

        /* Date cell */
        .ar-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ar-date-icon {
            width: 30px;
            height: 30px;
            background: #f1f5f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .ar-date-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            line-height: 1.3;
        }

        .ar-date-time {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        /* Actions cell */
        .ar-actions {
            display: flex;
            gap: 0.4rem;
            justify-content: flex-end;
        }

        .ar-btn {
            width: 34px;
            height: 34px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .ar-btn:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .ar-btn.view { color: #6366f1; border-color: #e0e7ff; background: #eef2ff; }
        .ar-btn.view:hover { background: #e0e7ff; }
        .ar-btn.status { color: #a16207; border-color: #fef08a; background: #fefce8; }
        .ar-btn.status:hover { background: #fef9c3; }
        .ar-btn.auditor { color: white; border-color: #10b981; background: #10b981; }
        .ar-btn.auditor:hover { background: #059669; border-color: #059669; }
        .ar-btn.delete { color: #dc2626; border-color: #fecaca; background: #fee2e2; }
        .ar-btn.delete:hover { background: #fecaca; }
        .ar-btn.evaluate { color: white; border: none; background: linear-gradient(135deg, #10b981, #059669); padding: 0 0.85rem; width: auto; font-size: 0.75rem; font-weight: 600; gap: 0.4rem; white-space: nowrap; }
        .ar-btn.evaluate:hover { background: linear-gradient(135deg, #059669, #047857); }

        /* Status + progress */
        .ar-status-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .ar-progress {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .ar-progress-bar {
            flex: 1;
            height: 3px;
            background: #e2e8f0;
            border-radius: 2px;
            min-width: 40px;
            overflow: hidden;
        }

        .ar-progress-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.5s ease;
        }

        .ar-progress-text {
            font-size: 0.58rem;
            color: #94a3b8;
            font-weight: 700;
            white-space: nowrap;
        }

        /* Table card header */
        .table-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .assessment-row,
            .assessment-header {
                grid-template-columns: 2fr 1fr 1fr 0.7fr 1fr auto;
                gap: 0.75rem;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }

        @media (max-width: 900px) {
            .assessment-header { display: none; }
            .assessment-row {
                grid-template-columns: 1fr 1fr;
                gap: 0.6rem;
                padding: 1.25rem 1.25rem;
                border-bottom: 1px solid #f1f5f9;
            }
            .ar-company { grid-column: 1 / -1; }
            .ar-actions { grid-column: 1 / -1; justify-content: flex-start; padding-top: 0.5rem; border-top: 1px solid #f8fafc; }
        }

        /* Animations */
        @keyframes reveal {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .reveal {
            animation: reveal 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        /* Filter Panel */
        .filter-panel {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1.5rem;
            align-items: flex-end;
            border: 1px solid var(--pro-border);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .form-group-pro label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .input-pro {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--pro-border);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s;
            background: #f8fafc;
        }

        .input-pro:focus {
            outline: none;
            border-color: var(--pro-primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        /* Status Pills */
        .badge-pro {
            padding: 0.4rem 0.85rem;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .badge-pro i {
            line-height: 1;
        }

        .badge-draft { background: #f1f5f9; color: #475569; }
        .badge-submitted { background: #eff6ff; color: #2563eb; }
        .badge-under_review { background: #fffbeb; color: #d97706; }
        .badge-evaluated { background: #f5f3ff; color: #7c3aed; }
        .badge-completed { background: #ecfdf5; color: #059669; }
        .badge-period-closed { background: #f1f5f9; color: #64748b; }

        @media (max-width: 1024px) {
            .management-hero { flex-direction: column; text-align: center; gap: 2.5rem; }
            .filter-panel { grid-template-columns: 1fr 1fr; }
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

            <!-- Management Hero -->
            <div class="management-hero reveal">
                <div class="hero-content">
                    <div class="hero-tag">
                        <i class="fas fa-shield-halved"></i>
                        Security & Compliance
                    </div>
                    <h1 class="hero-title">จัดการแบบประเมิน</h1>
                    <p class="hero-subtitle">ตรวจสอบ ติดตาม และมอบหมายผู้เชี่ยวชาญสำหรับแบบประเมินสถานประกอบการในระบบ HICM V2025</p>
                </div>
                <div class="hero-stats-group">
                    <div class="hero-stat-box">
                        <span class="stat-val primary"><?php echo count($assessments); ?></span>
                        <span class="stat-lbl">ทั้งหมด</span>
                    </div>
                    <div class="hero-stat-box">
                        <?php $pendingCount = count(array_filter($assessments, function($a) {
                            $pCompleted = ($a['period_status'] ?? '') === 'completed';
                            return in_array($a['status'], ['submitted', 'under_review']) && !$pCompleted;
                        })); ?>
                        <span class="stat-val warning"><?php echo $pendingCount; ?></span>
                        <span class="stat-lbl">รอตรวจสอบ</span>
                    </div>
                    <div class="hero-stat-box">
                        <?php $completeCount = count(array_filter($assessments, function($a) {
                            $pCompleted = ($a['period_status'] ?? '') === 'completed';
                            if ($a['status'] === 'completed') return true;
                            if ($a['status'] === 'evaluated' && $pCompleted) return true;
                            if (in_array($a['status'], ['submitted', 'under_review']) && $pCompleted) return true;
                            return false;
                        })); ?>
                        <span class="stat-val success"><?php echo $completeCount; ?></span>
                        <span class="stat-lbl">เสร็จสิ้น / จบโครงการ</span>
                    </div>
                </div>
            </div>

            <!-- Filter Panel -->
            <div class="pro-card reveal" style="padding: 1.5rem; margin-bottom: 2rem;">
                <form method="GET" action="" class="filter-panel" style="margin-bottom: 0; box-shadow: none; border: none; padding: 0;">
                    <div class="form-group-pro">
                        <label>ค้นหาชื่อสถานประกอบการ</label>
                        <input type="text" name="search" class="input-pro" placeholder="พิมพ์ชื่อบริษัท..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                    </div>
                    <div class="form-group-pro">
                        <label>รอบการประเมิน</label>
                        <select name="period" class="input-pro">
                            <option value="">ทุกรอบการประเมิน</option>
                            <?php foreach ($periods as $period): ?>
                                <option value="<?php echo $period['id']; ?>" <?php echo $periodId == $period['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($period['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-pro">
                        <label>สถานะ</label>
                        <select name="status" class="input-pro">
                            <option value="">ทุกสถานะ</option>
                            <option value="draft" <?php echo $statusFilter == 'draft' ? 'selected' : ''; ?>>📝 ร่าง</option>
                            <option value="submitted" <?php echo $statusFilter == 'submitted' ? 'selected' : ''; ?>>📤 ส่งแล้ว</option>
                            <option value="under_review" <?php echo $statusFilter == 'under_review' ? 'selected' : ''; ?>>🔍 กำลังตรวจสอบ</option>
                            <option value="evaluated" <?php echo $statusFilter == 'evaluated' ? 'selected' : ''; ?>>✅ ประเมินแล้ว</option>
                            <option value="completed" <?php echo $statusFilter == 'completed' ? 'selected' : ''; ?>>🎉 เสร็จสิ้น</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 0.75rem;">
                        <button type="submit" class="btn btn-primary" style="height: 45px; border-radius: 12px; padding: 0 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-filter"></i>
                            กรอง
                        </button>
                        <a href="assessments.php" class="btn btn-outline" style="height: 45px; width: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 0;">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Assessments List -->
            <div class="pro-card reveal" style="padding: 0; overflow: hidden;">
                <div class="table-card-header" style="border-bottom: 2px solid #f1f5f9; padding: 1.25rem 2rem;">
                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-list-check" style="color: var(--pro-primary);"></i>
                        รายการแบบประเมิน
                    </h3>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 0.4rem 0.85rem; border-radius: 8px;">
                        <?php echo count($assessments); ?> รายการ
                    </span>
                </div>

                <?php if (empty($assessments)): ?>
                    <div style="padding: 4rem 2rem; text-align: center;">
                        <div style="width: 72px; height: 72px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: #94a3b8;">
                            <i class="fas fa-folder-open" style="font-size: 1.75rem;"></i>
                        </div>
                        <h3 style="font-size: 1rem; font-weight: 700; color: #475569; margin: 0 0 0.4rem;">ไม่พบข้อมูลแบบประเมิน</h3>
                        <p style="font-size: 0.85rem; color: #94a3b8; margin: 0;">ไม่มีรายการที่ตรงกับเงื่อนไขที่ระบุ ลองปรับตัวกรองใหม่</p>
                    </div>
                <?php else: ?>
                    <!-- Header -->
                    <div class="assessment-header">
                        <span>สถานประกอบการ</span>
                        <span>รอบการประเมิน</span>
                        <span>สถานะ</span>
                        <span>คะแนน</span>
                        <span>อัปเดตล่าสุด</span>
                        <span style="text-align: right;">จัดการ</span>
                    </div>

                    <!-- Rows -->
                    <div class="assessment-list">
                        <?php $rowNum = 0; foreach ($assessments as $assessment): $rowNum++; ?>
                        <div class="assessment-row">
                            <!-- Company -->
                            <div class="ar-company">
                                <div class="ar-num"><?php echo $rowNum; ?></div>
                                <div class="ar-company-info">
                                    <div class="ar-company-name"><?php echo htmlspecialchars($assessment['company_name']); ?></div>
                                    <div class="ar-company-meta">
                                        <span class="ar-auditor-tag">
                                            <i class="fas fa-user-tie" style="font-size: 0.55rem;"></i>
                                            <?php 
                                                $auditorsList = $assessment['auditors_list'] ?: ($assessment['evaluator_name'] ?: 'ยังไม่ได้มอบหมาย');
                                                echo htmlspecialchars($auditorsList); 
                                            ?>
                                        </span>
                                        <span class="ar-id">#<?php echo str_pad($assessment['id'], 5, '0', STR_PAD_LEFT); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Period -->
                            <div>
                                <?php 
                                    $isPeriodCompleted = ($assessment['period_status'] ?? '') === 'completed';
                                    $isPeriodClosed = ($assessment['period_status'] ?? '') === 'closed';
                                    $isPeriodLocked = $isPeriodCompleted; // Only 'completed' fully locks
                                    $periodDisplayClass = $isPeriodCompleted ? 'locked' : ($isPeriodClosed ? 'closed' : 'open');
                                    $periodIcon = $isPeriodCompleted ? 'fa-lock' : ($isPeriodClosed ? 'fa-ban' : 'fa-calendar-check');
                                ?>
                                <span class="ar-period <?php echo $periodDisplayClass; ?>">
                                    <i class="fas <?php echo $periodIcon; ?>"></i>
                                    <?php echo htmlspecialchars($assessment['period_name']); ?>
                                </span>
                            </div>

                            <!-- Status -->
                            <div>
                                <?php
                                $rawStatus = $assessment['status'];
                                $periodSt = $assessment['period_status'] ?? '';
                                $totalEval = (int)($assessment['total_evaluators'] ?? 0);
                                $submittedEval = (int)($assessment['submitted_evaluators'] ?? 0);
                                $hasFinalScore = !empty($assessment['final_score']);
                                $displayStatus = $rawStatus;
                                // Only 'completed' (จบโครงการ) overrides display status
                                // 'closed' (ปิดรับสมัคร) still allows auditor evaluation
                                $periodCompleted = ($periodSt === 'completed');
                                if ($rawStatus === 'evaluated' && $periodCompleted) {
                                    $displayStatus = 'completed';
                                } elseif (in_array($rawStatus, ['submitted', 'under_review']) && $periodCompleted) {
                                    $displayStatus = 'period_closed';
                                }
                                $statusConfig = [
                                    'draft'         => ['class' => 'badge-draft',        'icon' => 'fa-file-pen',          'text' => 'ร่าง'],
                                    'submitted'     => ['class' => 'badge-submitted',    'icon' => 'fa-paper-plane',       'text' => 'ส่งแล้ว'],
                                    'under_review'  => ['class' => 'badge-under_review', 'icon' => 'fa-magnifying-glass',  'text' => 'กำลังตรวจสอบ'],
                                    'evaluated'     => ['class' => 'badge-evaluated',    'icon' => 'fa-clipboard-check',   'text' => 'ประเมินแล้ว'],
                                    'completed'     => ['class' => 'badge-completed',    'icon' => 'fa-circle-check',      'text' => 'เสร็จสิ้น'],
                                    'period_closed' => ['class' => 'badge-period-closed','icon' => 'fa-lock',              'text' => 'จบโครงการ'],
                                ];
                                $cfg = $statusConfig[$displayStatus] ?? $statusConfig['draft'];
                                ?>
                                <div class="ar-status-wrap">
                                    <span class="badge-pro <?php echo $cfg['class']; ?>" style="font-size: 0.68rem; gap: 0.35rem;">
                                        <i class="fas <?php echo $cfg['icon']; ?>" style="font-size: 0.55rem;"></i>
                                        <?php echo $cfg['text']; ?>
                                    </span>
                                    <?php if ($totalEval > 0 && !in_array($displayStatus, ['draft', 'completed', 'period_closed'])): ?>
                                    <div class="ar-progress">
                                        <div class="ar-progress-bar">
                                            <div class="ar-progress-fill" style="width: <?php echo $totalEval > 0 ? round(($submittedEval / $totalEval) * 100) : 0; ?>%; background: <?php echo $submittedEval >= $totalEval ? '#10b981' : '#6366f1'; ?>;"></div>
                                        </div>
                                        <span class="ar-progress-text"><?php echo $submittedEval; ?>/<?php echo $totalEval; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Score -->
                            <div>
                                <?php if ($assessment['final_score']): ?>
                                    <span class="ar-score"><?php echo number_format($assessment['final_score'], 1); ?></span>
                                <?php else: ?>
                                    <span class="ar-score na">—</span>
                                <?php endif; ?>
                            </div>

                            <!-- Date -->
                            <div>
                                <?php 
                                $dateToShow = $assessment['submitted_at'] ?: $assessment['updated_at'];
                                if ($dateToShow): 
                                ?>
                                    <div class="ar-date">
                                        <div class="ar-date-icon"><i class="far fa-clock"></i></div>
                                        <div>
                                            <div class="ar-date-text"><?php echo date('d M Y', strtotime($dateToShow)); ?></div>
                                            <div class="ar-date-time"><?php echo date('H:i', strtotime($dateToShow)); ?> น.</div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #cbd5e1; font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div class="ar-actions">
                                <a href="<?php echo getBaseUrl(); ?>/pages/assessment-view.php?id=<?php echo $assessment['id']; ?>" 
                                   class="ar-btn view" title="ดูรายละเอียด">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php 
                                $assignedAuditorIds = array_filter(explode(',', $assessment['auditor_ids'] ?? ''));
                                $isAssignedAuditor = $isAuditor && in_array($user['id'], $assignedAuditorIds);
                                ?>
                                <?php if ($isAuditor && $isAssignedAuditor): ?>
                                    <?php if (in_array($assessment['status'], ['submitted', 'under_review']) && !$isPeriodCompleted): ?>
                                    <a href="<?php echo getBaseUrl(); ?>/pages/auditor-evaluate.php?id=<?php echo $assessment['id']; ?>" 
                                       class="ar-btn evaluate" title="ทำการประเมิน">
                                        <i class="fas fa-clipboard-check"></i>
                                        ประเมิน
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <button type="button" class="ar-btn status"
                                            onclick="openStatusModal(<?php echo $assessment['id']; ?>, '<?php echo $assessment['status']; ?>', '<?php echo $assessment['period_status'] ?? ''; ?>', '<?php echo htmlspecialchars($assessment['period_name'] ?? '', ENT_QUOTES); ?>')" 
                                            title="เปลี่ยนสถานะ">
                                        <i class="fas fa-arrows-rotate"></i>
                                    </button>
                                    <?php if ($assessment['status'] == 'submitted'): ?>
                                        <button type="button" class="ar-btn auditor"
                                                onclick="openAuditorModal(<?php echo $assessment['id']; ?>)" 
                                                title="มอบหมาย Auditor">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="ar-btn delete"
                                            onclick="openDeleteModal(<?php echo $assessment['id']; ?>, '<?php echo htmlspecialchars($assessment['company_name'] ?? 'การประเมิน', ENT_QUOTES); ?>')" 
                                            title="ลบการประเมิน">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Status Update Modal - Timeline -->
    <div class="modal-overlay" id="statusModalOverlay">
        <div class="modal status-timeline-modal" style="max-width: 540px; background: white; border-radius: 20px; overflow: hidden;">
            <div class="stm-header">
                <div class="stm-header-icon">
                    <i class="fas fa-arrows-rotate"></i>
                </div>
                <div class="stm-header-text">
                    <h3>เปลี่ยนสถานะแบบประเมิน</h3>
                    <p id="stmPeriodLabel">เลื่อนขั้นตอนการตรวจสอบให้เป็นปัจจุบัน</p>
                </div>
                <button type="button" class="stm-close" onclick="closeStatusModal()">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <form method="POST" id="statusForm">
                <!-- Period Locked Banner (hidden by default) -->
                <div class="stm-locked-banner" id="stmLockedBanner" style="display: none;">
                    <div class="stm-locked-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="stm-locked-text">
                        <strong>รอบการประเมินถูกปิดแล้ว</strong>
                        <span id="stmLockedPeriodName"></span>
                    </div>
                </div>

                <div class="stm-body">
                    <input type="hidden" name="assessment_id" id="modalAssessmentId">
                    <input type="hidden" name="status" id="modalStatusValue">

                    <!-- Timeline -->
                    <div class="status-timeline" id="statusTimeline">
                        <div class="stl-step" data-status="draft" onclick="selectTimelineStatus('draft')">
                            <div class="stl-connector"><div class="stl-line"></div></div>
                            <div class="stl-node">
                                <div class="stl-dot">
                                    <i class="fas fa-file-pen"></i>
                                </div>
                                <div class="stl-content">
                                    <div class="stl-label">DRAFT</div>
                                    <div class="stl-desc">ร่างแบบประเมิน</div>
                                </div>
                                <div class="stl-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stl-step" data-status="submitted" onclick="selectTimelineStatus('submitted')">
                            <div class="stl-connector"><div class="stl-line"></div></div>
                            <div class="stl-node">
                                <div class="stl-dot">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="stl-content">
                                    <div class="stl-label">SUBMITTED</div>
                                    <div class="stl-desc">ส่งข้อมูลแล้ว</div>
                                </div>
                                <div class="stl-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stl-step" data-status="under_review" onclick="selectTimelineStatus('under_review')">
                            <div class="stl-connector"><div class="stl-line"></div></div>
                            <div class="stl-node">
                                <div class="stl-dot">
                                    <i class="fas fa-magnifying-glass"></i>
                                </div>
                                <div class="stl-content">
                                    <div class="stl-label">UNDER REVIEW</div>
                                    <div class="stl-desc">กำลังตรวจสอบ</div>
                                </div>
                                <div class="stl-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stl-step" data-status="evaluated" onclick="selectTimelineStatus('evaluated')">
                            <div class="stl-connector"><div class="stl-line"></div></div>
                            <div class="stl-node">
                                <div class="stl-dot">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="stl-content">
                                    <div class="stl-label">EVALUATED</div>
                                    <div class="stl-desc">ประเมินเสร็จสิ้น</div>
                                </div>
                                <div class="stl-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="stl-step" data-status="completed" onclick="selectTimelineStatus('completed')">
                            <div class="stl-connector"><div class="stl-line"></div></div>
                            <div class="stl-node">
                                <div class="stl-dot">
                                    <i class="fas fa-flag-checkered"></i>
                                </div>
                                <div class="stl-content">
                                    <div class="stl-label">COMPLETED</div>
                                    <div class="stl-desc">ปิดรอบโครงการ</div>
                                </div>
                                <div class="stl-check">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="stm-note" id="stmNote">
                        <i class="fas fa-circle-info"></i>
                        <span>คลิกที่ขั้นตอนที่ต้องการเพื่อเปลี่ยนสถานะ — การเปลี่ยนจะส่งผลต่อสิทธิ์การเข้าถึงของสถานประกอบการ</span>
                    </div>
                </div>
                <div class="stm-footer">
                    <button type="button" class="btn stm-btn-cancel" onclick="closeStatusModal()">ยกเลิก</button>
                    <button type="submit" name="update_status" class="btn stm-btn-submit" id="stmSubmitBtn" disabled>
                        <i class="fas fa-arrows-rotate"></i>
                        อัปเดตสถานะ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
    /* ============================================
       STATUS TIMELINE MODAL - Pro Design
       ============================================ */
    /* Modal flex layout - ensures footer is always visible */
    .status-timeline-modal {
        animation: stmSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        max-height: 90vh !important;
        display: flex !important;
        flex-direction: column !important;
    }

    .status-timeline-modal > form {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    @keyframes stmSlideIn {
        from { opacity: 0; transform: translateY(30px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Header - fixed at top */
    .stm-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.75rem;
        background: white;
        color: #0f172a;
        position: relative;
        flex-shrink: 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .stm-header-icon {
        width: 42px;
        height: 42px;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        color: var(--pro-primary);
        flex-shrink: 0;
    }

    .stm-header-text h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .stm-header-text p {
        margin: 0.2rem 0 0;
        font-size: 0.75rem;
        color: #64748b;
    }

    .stm-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 34px;
        height: 34px;
        border: none;
        background: #f1f5f9;
        color: #64748b;
        border-radius: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: all 0.2s;
    }

    .stm-close:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    /* Locked Banner */
    .stm-locked-banner {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.75rem 1.75rem;
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border-bottom: 1px solid #fcd34d;
        flex-shrink: 0;
    }

    .stm-locked-icon {
        width: 36px;
        height: 36px;
        background: #f59e0b;
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        animation: stmShake 0.6s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
    }

    @keyframes stmShake {
        10%, 90% { transform: translateX(-1px); }
        20%, 80% { transform: translateX(2px); }
        30%, 50%, 70% { transform: translateX(-3px); }
        40%, 60% { transform: translateX(3px); }
    }

    .stm-locked-text {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }

    .stm-locked-text strong {
        font-size: 0.8rem;
        color: #92400e;
    }

    .stm-locked-text span {
        font-size: 0.7rem;
        color: #a16207;
    }

    /* Body - scrollable */
    .stm-body {
        padding: 1.25rem 1.75rem;
        overflow-y: auto;
        flex: 1 1 auto;
        min-height: 0;
    }

    /* Timeline Container */
    .status-timeline {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    /* Each Step */
    .stl-step {
        position: relative;
        cursor: pointer;
        opacity: 0;
        transform: translateX(-20px);
        animation: stlFadeIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    .stl-step:nth-child(1) { animation-delay: 0.05s; }
    .stl-step:nth-child(2) { animation-delay: 0.12s; }
    .stl-step:nth-child(3) { animation-delay: 0.19s; }
    .stl-step:nth-child(4) { animation-delay: 0.26s; }
    .stl-step:nth-child(5) { animation-delay: 0.33s; }

    @keyframes stlFadeIn {
        from { opacity: 0; transform: translateX(-20px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    /* Connector Line */
    .stl-connector {
        position: absolute;
        left: 20px;
        top: 40px;
        bottom: 0;
        width: 2px;
        z-index: 0;
    }

    .stl-line {
        width: 100%;
        height: 100%;
        background: #e2e8f0;
        transition: background 0.5s ease;
    }

    .stl-step:last-child .stl-connector {
        display: none;
    }

    .stl-step.passed .stl-line {
        background: linear-gradient(180deg, var(--pro-primary-light), var(--pro-primary));
    }

    /* Node */
    .stl-node {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.55rem 1rem;
        border-radius: 12px;
        border: 2px solid transparent;
        transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        z-index: 1;
        background: white;
    }

    .stl-step:hover .stl-node {
        background: #f8fafc;
        border-color: #e2e8f0;
    }

    .stl-step.is-locked .stl-node {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .stl-step.is-locked:hover .stl-node {
        background: white;
        border-color: transparent;
    }

    /* Dot (Circle) */
    .stl-dot {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
        background: #f1f5f9;
        color: #94a3b8;
        border: 2px solid #e2e8f0;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
    }

    /* Passed state */
    .stl-step.passed .stl-dot {
        background: var(--pro-primary);
        color: white;
        border-color: var(--pro-primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
    }

    /* Current state */
    .stl-step.current .stl-dot {
        background: white;
        color: var(--pro-primary);
        border-color: var(--pro-primary);
        border-width: 3px;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
        animation: stlPulse 2s ease-in-out infinite;
    }

    @keyframes stlPulse {
        0%, 100% { box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); }
        50% { box-shadow: 0 0 0 8px rgba(99, 102, 241, 0.06); }
    }

    /* Selected state */
    .stl-step.selected .stl-node {
        background: linear-gradient(135deg, #eef2ff, #e0e7ff);
        border-color: var(--pro-primary-light);
        box-shadow: 0 4px 15px -3px rgba(99, 102, 241, 0.2);
    }

    .stl-step.selected .stl-dot {
        background: linear-gradient(135deg, var(--pro-primary), var(--pro-primary-dark));
        color: white;
        border-color: var(--pro-primary-dark);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.25);
        transform: scale(1.1);
    }

    /* Content */
    .stl-content {
        flex: 1;
    }

    .stl-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        transition: color 0.3s;
    }

    .stl-step.passed .stl-label,
    .stl-step.current .stl-label {
        color: var(--pro-primary);
    }

    .stl-step.selected .stl-label {
        color: var(--pro-primary-dark);
    }

    .stl-desc {
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
        margin-top: 0.1rem;
        transition: color 0.3s;
    }

    .stl-step.selected .stl-desc {
        color: #1e293b;
    }

    .stl-step.is-locked .stl-desc {
        color: #94a3b8;
    }

    /* Check indicator */
    .stl-check {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        background: #f1f5f9;
        color: transparent;
        flex-shrink: 0;
        transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .stl-step.passed .stl-check {
        background: #ecfdf5;
        color: #059669;
    }

    .stl-step.selected .stl-check {
        background: var(--pro-primary);
        color: white;
        transform: scale(1.15);
        box-shadow: 0 2px 8px rgba(99, 102, 241, 0.3);
    }

    /* Note */
    .stm-note {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        animation: stmNoteIn 0.6s 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    .stm-note.note-locked {
        background: #fffbeb;
        border-color: #fde68a;
    }

    .stm-note.note-locked i {
        color: #d97706 !important;
    }

    .stm-note.note-locked span {
        color: #92400e !important;
    }

    @keyframes stmNoteIn {
        from { opacity: 0; transform: translateY(10px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .stm-note i {
        color: #0284c7;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .stm-note span {
        font-size: 0.78rem;
        color: #0c4a6e;
        line-height: 1.5;
    }

    /* Footer - always visible at bottom */
    .stm-footer {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1rem 1.75rem;
        background: #f8fafc;
        border-top: 1px solid #f1f5f9;
        flex-shrink: 0;
    }

    .stm-btn-cancel {
        border-radius: 12px !important;
        font-weight: 600 !important;
        padding: 0.7rem 1.5rem !important;
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        color: #475569 !important;
        transition: all 0.2s !important;
    }

    .stm-btn-cancel:hover {
        border-color: #cbd5e1 !important;
        background: #f8fafc !important;
    }

    .stm-btn-submit {
        border-radius: 12px !important;
        font-weight: 700 !important;
        padding: 0.7rem 2rem !important;
        background: linear-gradient(135deg, var(--pro-primary), var(--pro-primary-dark)) !important;
        color: white !important;
        border: none !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        box-shadow: 0 4px 15px -3px rgba(99, 102, 241, 0.4) !important;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1) !important;
    }

    .stm-btn-submit:hover:not(:disabled) {
        transform: translateY(-1px) !important;
        box-shadow: 0 6px 20px -3px rgba(99, 102, 241, 0.5) !important;
    }

    .stm-btn-submit:disabled {
        opacity: 0.4 !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
    }

    /* Status-specific dot colors for passed steps */
    .stl-step[data-status="draft"].passed .stl-dot { background: #64748b; border-color: #64748b; }
    .stl-step[data-status="submitted"].passed .stl-dot { background: #2563eb; border-color: #2563eb; }
    .stl-step[data-status="under_review"].passed .stl-dot { background: #d97706; border-color: #d97706; }
    .stl-step[data-status="evaluated"].passed .stl-dot { background: #7c3aed; border-color: #7c3aed; }
    .stl-step[data-status="completed"].passed .stl-dot { background: #059669; border-color: #059669; }

    .stl-step[data-status="draft"].current .stl-dot { color: #64748b; border-color: #64748b; }
    .stl-step[data-status="submitted"].current .stl-dot { color: #2563eb; border-color: #2563eb; }
    .stl-step[data-status="under_review"].current .stl-dot { color: #d97706; border-color: #d97706; }
    .stl-step[data-status="evaluated"].current .stl-dot { color: #7c3aed; border-color: #7c3aed; }
    .stl-step[data-status="completed"].current .stl-dot { color: #059669; border-color: #059669; }

    /* Mobile */
    @media (max-width: 640px) {
        .status-timeline-modal { margin: 0.75rem !important; max-height: 95vh !important; }
        .stm-header { padding: 1rem 1.25rem; }
        .stm-header-icon { width: 36px; height: 36px; font-size: 0.85rem; }
        .stm-header-text h3 { font-size: 0.95rem; }
        .stm-body { padding: 1rem 1.25rem; }
        .stl-node { padding: 0.45rem 0.75rem; gap: 0.65rem; }
        .stl-dot { width: 34px; height: 34px; font-size: 0.8rem; }
        .stl-connector { left: 17px; top: 34px; }
        .stl-desc { font-size: 0.8rem; }
        .stl-check { width: 22px; height: 22px; font-size: 0.55rem; }
        .stm-footer { padding: 0.85rem 1.25rem; }
        .stm-note { padding: 0.6rem 0.85rem; margin-top: 0.75rem; }
        .stm-note span { font-size: 0.72rem; }
    }
    </style>

    <!-- Auditor Assignment Modal -->
    <div class="modal-overlay" id="auditorModalOverlay">
        <div class="modal" style="max-width: 560px; background: white;">
            <div class="modal-header" style="background: #f8fafc; padding: 1.75rem 2rem;">
                <h3 class="modal-title" style="font-weight: 800; color: #0f172a; gap: 0.75rem;">
                    <i class="fas fa-user-check" style="color: var(--pro-success);"></i>
                    มอบหมายรายชื่อผู้ตรวจสอบ
                </h3>
                <p class="modal-subtitle" style="margin-top: 0.25rem;">เลือกผู้เชี่ยวชาญเข้าตรวจประเมินสถานประกอบการ</p>
                <button type="button" class="modal-close-btn" onclick="closeAuditorModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding: 2rem;">
                    <input type="hidden" name="assessment_id" id="auditorAssessmentId">
                    
                    <div style="background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 1px solid #bae6fd; border-radius: 16px; padding: 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--pro-info); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                                <i class="fas fa-shuffle"></i>
                            </div>
                            <div>
                                <div style="font-size: 0.9rem; font-weight: 700; color: #0c4a6e;">สุ่มผู้ตรวจสอบอัตโนมัติ</div>
                                <div style="font-size: 0.75rem; color: #0369a1;">ระบบจะสุ่มรายชื่อที่พร้อมใช้งาน</div>
                            </div>
                        </div>
                        <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                            <input type="checkbox" name="is_random" id="isRandom" onchange="toggleRandom(this.checked)" style="opacity: 0; width: 0; height: 0;">
                            <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px;"></span>
                            <style>
                                input:checked + span { background-color: var(--pro-primary); }
                                span:before {
                                    position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
                                    background-color: white; transition: .4s; border-radius: 50%;
                                }
                                input:checked + span:before { transform: translateX(20px); }
                            </style>
                        </label>
                    </div>

                    <div id="manualAssignSection">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 1rem; text-transform: uppercase;">
                            เลือกผู้ที่มีความพร้อมเข้าตรวจสอบ
                        </label>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; max-height: 250px; overflow-y: auto; padding-right: 0.5rem;">
                            <?php foreach ($auditors as $auditor): ?>
                                <label style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                                    <input type="checkbox" name="auditor_ids[]" value="<?php echo $auditor['id']; ?>" style="width: 18px; height: 18px; accent-color: var(--pro-primary);">
                                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($auditor['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div id="randomAssignSection" style="display: none;">
                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 1rem; text-transform: uppercase;">
                            จำนวนที่ต้องการสุ่ม
                        </label>
                        <div style="display: flex; align-items: center; gap: 1.5rem;">
                            <input type="number" name="random_count" id="randomCount" value="1" min="1" max="5" class="input-pro" style="width: 100px; text-align: center; font-size: 1.25rem; font-weight: 800;">
                            <div style="font-size: 0.9rem; color: #64748b;">
                                คน (จากผู้ตรวจสอบทั้งหมดในระบบ <strong><?php echo count($auditors); ?></strong> คน)
                            </div>
                        </div>
                    </div>

                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 1rem; margin-top: 2rem; display: flex; gap: 0.75rem;">
                        <i class="fas fa-envelope-circle-check" style="color: #059669; margin-top: 3px;"></i>
                        <span style="font-size: 0.8rem; color: #064e3b; line-height: 1.5;">
                            ผู้ตรวจสอบที่ได้รับมอบหมายทั้งหมด จะได้รับการแจ้งเตือนผ่านช่องทางอีเมลในทันที
                        </span>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 1.25rem 2rem; background: #f8fafc;">
                    <button type="button" class="btn btn-outline" onclick="closeAuditorModal()" style="border-radius: 10px; font-weight: 600;">ยกเลิก</button>
                    <button type="submit" name="assign_auditors" class="btn btn-primary" style="background: linear-gradient(to right, var(--pro-primary), var(--pro-primary-dark)); border: none; border-radius: 10px; font-weight: 600; padding-left: 2rem; padding-right: 2rem;">
                        ยืนยันการมอบหมาย
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Assessment Modal -->
    <div class="modal-overlay" id="deleteModalOverlay">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <div style="width: 64px; height: 64px; background-color: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                        <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">ลบการประเมิน</h3>
                <p style="color: var(--gray-500); margin-bottom: 0.5rem;">คุณต้องการลบการประเมินของ</p>
                <p style="font-weight: 600; color: var(--gray-800); margin-bottom: 1.5rem;" id="deleteAssessmentName"></p>
                <div style="background: #fee2e2; border-radius: var(--radius-md); padding: 0.75rem; margin-bottom: 1.5rem;">
                    <p style="color: #991b1b; font-size: 0.875rem; margin: 0;">
                        <strong>⚠️ คำเตือน:</strong> การดำเนินการนี้จะลบข้อมูลทั้งหมด<br>
                        คะแนน ไฟล์แนบ และรายงาน จะถูกลบถาวรและไม่สามารถกู้คืนได้
                    </p>
                </div>
                <form method="POST" style="display: flex; justify-content: center; gap: 1rem;">
                    <input type="hidden" name="delete_assessment" value="1">
                    <input type="hidden" name="assessment_id" id="deleteAssessmentId">
                    <button type="button" class="btn btn-outline" onclick="closeDeleteModal()" style="border-radius: 10px; font-weight: 600;">ยกเลิก</button>
                    <button type="submit" class="btn" style="background: var(--pro-danger); color: white; border-radius: 10px; font-weight: 600;">ลบถาวร</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Status order for timeline
        const STATUS_ORDER = ['draft', 'submitted', 'under_review', 'evaluated', 'completed'];
        let currentAssessmentStatus = '';
        let selectedStatus = '';
        let isPeriodLocked = false;

        function openStatusModal(assessmentId, currentStatus, periodStatus, periodName) {
            currentAssessmentStatus = currentStatus;
            selectedStatus = '';
            // Only 'completed' (จบโครงการ) locks the status modal
            // 'closed' (ปิดรับสมัคร) still allows admin to change assessment status
            isPeriodLocked = (periodStatus === 'completed');

            document.getElementById('modalAssessmentId').value = assessmentId;
            document.getElementById('modalStatusValue').value = '';

            // Show/hide locked banner
            const lockedBanner = document.getElementById('stmLockedBanner');
            const submitBtn = document.getElementById('stmSubmitBtn');
            const note = document.getElementById('stmNote');

            if (isPeriodLocked) {
                lockedBanner.style.display = 'flex';
                document.getElementById('stmLockedPeriodName').textContent = 'รอบ: ' + periodName + ' (สถานะ: ' + periodStatus + ')';
                document.getElementById('stmPeriodLabel').textContent = periodName;
                submitBtn.disabled = true;
                note.className = 'stm-note note-locked';
                note.querySelector('span').textContent = 'รอบการประเมินนี้ถูกปิดแล้ว ไม่สามารถเปลี่ยนสถานะได้';
            } else {
                lockedBanner.style.display = 'none';
                document.getElementById('stmPeriodLabel').textContent = periodName ? 'รอบ: ' + periodName : 'เลื่อนขั้นตอนการตรวจสอบให้เป็นปัจจุบัน';
                submitBtn.disabled = true;
                note.className = 'stm-note';
                note.querySelector('span').textContent = 'คลิกที่ขั้นตอนที่ต้องการเพื่อเปลี่ยนสถานะ — การเปลี่ยนจะส่งผลต่อสิทธิ์การเข้าถึงของสถานประกอบการ';
            }

            // Render timeline states
            renderTimeline(currentStatus);

            // Show modal
            document.getElementById('statusModalOverlay').classList.add('active');
        }

        function renderTimeline(currentStatus) {
            const currentIndex = STATUS_ORDER.indexOf(currentStatus);
            const steps = document.querySelectorAll('#statusTimeline .stl-step');

            steps.forEach((step, index) => {
                // Reset classes
                step.classList.remove('passed', 'current', 'selected', 'is-locked');

                if (index < currentIndex) {
                    step.classList.add('passed');
                } else if (index === currentIndex) {
                    step.classList.add('current');
                }

                if (isPeriodLocked) {
                    step.classList.add('is-locked');
                }

                // Re-trigger animation
                step.style.animation = 'none';
                step.offsetHeight; // force reflow
                step.style.animation = '';
            });
        }

        function selectTimelineStatus(status) {
            if (isPeriodLocked) return;
            if (status === currentAssessmentStatus) return; // Can't select same status

            selectedStatus = status;
            document.getElementById('modalStatusValue').value = status;

            const steps = document.querySelectorAll('#statusTimeline .stl-step');
            const selectedIndex = STATUS_ORDER.indexOf(status);
            const currentIndex = STATUS_ORDER.indexOf(currentAssessmentStatus);

            steps.forEach((step, index) => {
                step.classList.remove('passed', 'current', 'selected');

                if (index < selectedIndex) {
                    step.classList.add('passed');
                } else if (index === selectedIndex) {
                    step.classList.add('selected');
                }

                // Show original current with subtle indicator
                if (index === currentIndex && index !== selectedIndex) {
                    step.classList.add('current');
                }
            });

            // Enable submit
            const submitBtn = document.getElementById('stmSubmitBtn');
            submitBtn.disabled = false;

            // Animate button
            submitBtn.style.transform = 'scale(0.95)';
            setTimeout(() => { submitBtn.style.transform = ''; }, 150);
        }

        function closeStatusModal() {
            document.getElementById('statusModalOverlay').classList.remove('active');
        }

        function openAuditorModal(assessmentId) {
            document.getElementById('auditorAssessmentId').value = assessmentId;
            document.getElementById('auditorModalOverlay').classList.add('active');
        }

        function closeAuditorModal() {
            document.getElementById('auditorModalOverlay').classList.remove('active');
        }

        function toggleRandom(isRandom) {
            const manual = document.getElementById('manualAssignSection');
            const random = document.getElementById('randomAssignSection');
            if (isRandom) {
                manual.style.display = 'none';
                random.style.display = 'block';
            } else {
                manual.style.display = 'block';
                random.style.display = 'none';
            }
        }

        function openDeleteModal(assessmentId, companyName) {
            document.getElementById('deleteAssessmentId').value = assessmentId;
            document.getElementById('deleteAssessmentName').innerText = companyName;
            document.getElementById('deleteModalOverlay').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.remove('active');
        }

        // Close modals when clicking overlay
        document.getElementById('statusModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeStatusModal();
        });

        document.getElementById('auditorModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeAuditorModal();
        });

        document.getElementById('deleteModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>
</body>
</html>