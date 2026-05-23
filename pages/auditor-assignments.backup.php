<?php
/**
 * HICM V2025 Assessment System - Auditor Assignment Management
 * หน้าจัดการจับคู่กรรมการประเมิน - Redesigned Pro Version
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/assessment.php';

requireAuth();
requireRole(ROLE_ADMIN);

// ============================================
// Helper Functions
// ============================================

function getComprehensiveAssignmentList($periodId = null) {
    $db = getDB();
    
    $sql = "
        SELECT 
            c.id as company_id,
            c.company_name,
            c.industry_type,
            c.user_id,
            c.default_evaluator_id,
            u_default.name as default_evaluator_name,
            u_default.expertise as default_expertise,
            o_default.short_name as default_org_name,
            a.id as assessment_id,
            a.evaluator_id as assigned_id,
            u_assigned.name as assigned_name,
            u_assigned.expertise as assigned_expertise,
            o_assigned.short_name as assigned_org_name,
            a.status as assessment_status
        FROM companies c
        LEFT JOIN users u_default ON c.default_evaluator_id = u_default.id
        LEFT JOIN organizations o_default ON u_default.organization_id = o_default.id
        LEFT JOIN assessments a ON c.id = a.company_id " . ($periodId ? "AND a.period_id = ?" : "") . "
        LEFT JOIN users u_assigned ON a.evaluator_id = u_assigned.id
        LEFT JOIN organizations o_assigned ON u_assigned.organization_id = o_assigned.id
        ORDER BY c.company_name
    ";
    
    $stmt = $db->prepare($sql);
    if ($periodId) {
        $stmt->execute([$periodId]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

function getAuditorsWithDetails() {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.expertise, u.organization_id,
               o.short_name as org_name,
               (SELECT COUNT(*) FROM assessments WHERE evaluator_id = u.id) as total_assignments
        FROM users u 
        LEFT JOIN organizations o ON u.organization_id = o.id
        WHERE u.role = 'auditor' AND u.is_active = 1 
        ORDER BY u.name
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getAllPeriods() {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, year, status FROM assessment_periods ORDER BY year DESC, start_date DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getMatchingScore($companyIndustry, $auditorExpertise) {
    if (empty($companyIndustry) || empty($auditorExpertise)) return 0;
    
    $companyTypes = array_map('trim', explode(',', strtolower($companyIndustry)));
    $auditorTypes = array_map('trim', explode(',', strtolower($auditorExpertise)));
    
    $matches = 0;
    foreach ($companyTypes as $cType) {
        foreach ($auditorTypes as $aType) {
            // Check for partial match
            $cShort = preg_replace('/\s*\([^)]+\)/', '', $cType);
            $aShort = preg_replace('/\s*\([^)]+\)/', '', $aType);
            if (strpos($aShort, $cShort) !== false || strpos($cShort, $aShort) !== false) {
                $matches++;
                break;
            }
        }
    }
    
    return $matches > 0 ? min(100, round(($matches / count($companyTypes)) * 100)) : 0;
}

// ============================================
// Handle POST Actions
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    // Smart Match All
    if (isset($_POST['smart_match_all'])) {
        $periodId = intval($_POST['period_id']);
        $isDefaultMode = isset($_POST['match_mode']) && $_POST['match_mode'] === 'default';
        
        $companies = getComprehensiveAssignmentList($periodId);
        $auditors = getAuditorsWithDetails();
        
        $unassigned = array_filter($companies, fn($c) => $isDefaultMode ? empty($c['default_evaluator_id']) : empty($c['assigned_id']));
        
        if (empty($unassigned)) {
            setFlashMessage('ไม่มีรายการที่รอการจับคู่', 'info');
        } else {
            $count = 0;
            foreach ($unassigned as $comp) {
                $bestAuditor = null;
                $highestScore = -1;
                
                foreach ($auditors as $aud) {
                    $score = getMatchingScore($comp['industry_type'], $aud['expertise']);
                    if ($score > $highestScore) {
                        $highestScore = $score;
                        $bestAuditor = $aud['id'];
                    }
                }
                
                if ($bestAuditor) {
                    if ($isDefaultMode) {
                        $stmt = $db->prepare("UPDATE companies SET default_evaluator_id = ? WHERE id = ?");
                        $stmt->execute([$bestAuditor, $comp['company_id']]);
                        $count++;
                    } else {
                        $assessmentId = $comp['assessment_id'];
                        if (!$assessmentId) {
                            $res = getOrCreateAssessment($comp['company_id'], $periodId);
                            if ($res['success']) {
                                $assessmentId = $res['assessment']['id'] ?? $db->lastInsertId();
                            }
                        }
                        
                        if ($assessmentId) {
                            $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
                            $stmt->execute([$bestAuditor, $assessmentId]);
                            $count++;
                        }
                    }
                }
            }
            setFlashMessage("✅ Smart Match สำเร็จ! จับคู่ได้ {$count} รายการ", 'success');
        }
        redirect(getBaseUrl() . '/pages/auditor-assignments.php?mode=' . ($isDefaultMode ? 'default' : 'period') . ($periodId ? "&period={$periodId}" : ''));
    }

    // Save Staged Assignments
    if (isset($_POST['save_staged_assignments'])) {
        $assignments_data = json_decode($_POST['assignments_data'], true);
        $period_id = intval($_POST['period_id']);
        $isDefaultMode = isset($_POST['is_default_mode']) && $_POST['is_default_mode'] == '1';
        $count = 0;
        
        foreach ($assignments_data as $companyId => $auditorId) {
            if ($isDefaultMode) {
                $stmt = $db->prepare("UPDATE companies SET default_evaluator_id = ? WHERE id = ?");
                $stmt->execute([$auditorId ?: null, $companyId]);
                $count++;
            } else {
                $res = getOrCreateAssessment($companyId, $period_id);
                if ($res['success']) {
                    $assessmentId = $res['assessment']['id'] ?? null;
                    if ($assessmentId) {
                        $stmt = $db->prepare("UPDATE assessments SET evaluator_id = ? WHERE id = ?");
                        $stmt->execute([$auditorId ?: null, $assessmentId]);
                        $count++;
                    }
                }
            }
        }
        setFlashMessage("✅ บันทึกสำเร็จ! อัพเดท {$count} รายการ", 'success');
        redirect(getBaseUrl() . '/pages/auditor-assignments.php?mode=' . ($isDefaultMode ? 'default' : 'period') . ($period_id ? "&period={$period_id}" : ''));
    }
}

// ============================================
// Get Data
// ============================================

$mode = $_GET['mode'] ?? 'period';
$periodId = $_GET['period'] ?? null;

if (!$periodId && $mode === 'period') {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM assessment_periods WHERE status IN ('open', 'evaluating') ORDER BY start_date DESC LIMIT 1");
    $stmt->execute();
    $period = $stmt->fetch();
    $periodId = $period['id'] ?? null;
}

$assignments = getComprehensiveAssignmentList($periodId);
$auditors = getAuditorsWithDetails();
$periods = getAllPeriods();

// Stats
$totalCompanies = count($assignments);
$assignedCount = count(array_filter($assignments, fn($a) => $mode === 'default' ? !empty($a['default_evaluator_id']) : !empty($a['assigned_id'])));
$unassignedCount = $totalCompanies - $assignedCount;
$matchRate = $totalCompanies > 0 ? round(($assignedCount / $totalCompanies) * 100) : 0;

// Current Period Info
$currentPeriod = null;
foreach ($periods as $p) {
    if ($p['id'] == $periodId) {
        $currentPeriod = $p;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จับคู่การประเมิน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        /* ============================================
           Hero Header
           ============================================ */
        .assign-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(12, 74, 110, 0.4);
        }
        
        .assign-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: translate(30%, -30%);
        }
        
        .assign-hero::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(6,182,212,0.2) 0%, transparent 70%);
            transform: translate(-30%, 30%);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 2rem;
        }
        
        .hero-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .hero-title svg {
            width: 32px;
            height: 32px;
            opacity: 0.9;
        }
        
        .hero-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
            margin: 0;
        }
        
        .hero-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn-smart-match {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 10px 25px -5px rgba(6, 182, 212, 0.5);
            transition: all 0.3s;
        }
        
        .btn-smart-match:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(6, 182, 212, 0.6);
        }
        
        /* ============================================
           Stats Cards
           ============================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--gray-100);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--stat-color, var(--gray-300));
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.1);
        }
        
        .stat-card.primary { --stat-color: #0284c7; }
        .stat-card.success { --stat-color: #10b981; }
        .stat-card.warning { --stat-color: #f59e0b; }
        .stat-card.info { --stat-color: #8b5cf6; }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }
        
        .stat-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .stat-card.primary .stat-icon { background: #e0f2fe; color: #0284c7; }
        .stat-card.success .stat-icon { background: #d1fae5; color: #10b981; }
        .stat-card.warning .stat-icon { background: #fef3c7; color: #f59e0b; }
        .stat-card.info .stat-icon { background: #ede9fe; color: #8b5cf6; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        /* ============================================
           Filter Bar
           ============================================ */
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            border: 1px solid var(--gray-100);
        }
        
        .mode-toggle {
            display: flex;
            background: var(--gray-100);
            border-radius: 10px;
            padding: 4px;
        }
        
        .mode-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-500);
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mode-btn:hover {
            color: var(--gray-700);
        }
        
        .mode-btn.active {
            background: white;
            color: var(--primary-600);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .period-select {
            padding: 0.5rem 2rem 0.5rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-700);
            background: white;
            cursor: pointer;
            min-width: 200px;
        }
        
        .search-box {
            position: relative;
            flex: 1;
            max-width: 300px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        .search-box svg {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: var(--gray-400);
        }
        
        /* ============================================
           Assignment Grid
           ============================================ */
        .assign-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
        }
        
        @media (max-width: 640px) {
            .assign-grid { grid-template-columns: 1fr; }
        }
        
        .assign-card {
            background: white;
            border-radius: 16px;
            border: 2px solid var(--gray-100);
            overflow: hidden;
            transition: all 0.3s;
            animation: cardFadeIn 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .assign-card:hover {
            border-color: var(--primary-200);
            box-shadow: 0 12px 32px -8px rgba(0,0,0,0.12);
        }
        
        .assign-card.matched {
            border-color: #a7f3d0;
        }
        
        .assign-card.staged {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .company-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .industry-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }
        
        .industry-tag {
            padding: 0.2rem 0.5rem;
            background: var(--gray-100);
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .status-badge.matched {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.waiting {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.staged {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .card-body {
            padding: 1.25rem 1.5rem;
            max-height: 320px;
            overflow-y: auto;
        }
        
        .card-body::-webkit-scrollbar {
            width: 4px;
        }
        
        .card-body::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }
        
        .auditor-list-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        
        .auditor-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem;
            border: 2px solid var(--gray-100);
            border-radius: 12px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--gray-50);
        }
        
        .auditor-option:hover {
            border-color: var(--primary-200);
            background: white;
        }
        
        .auditor-option.selected {
            border-color: var(--primary-500);
            background: var(--primary-50);
        }
        
        .auditor-option input[type="radio"] {
            display: none;
        }
        
        .auditor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .auditor-info {
            flex: 1;
            min-width: 0;
        }
        
        .auditor-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.9rem;
        }
        
        .auditor-meta {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.125rem;
        }
        
        .match-score {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .match-score.high { background: #d1fae5; color: #065f46; }
        .match-score.medium { background: #fef3c7; color: #92400e; }
        .match-score.low { background: var(--gray-100); color: var(--gray-500); }
        
        .check-icon {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        
        .auditor-option.selected .check-icon {
            background: var(--primary-500);
            border-color: var(--primary-500);
            color: white;
        }
        
        /* ============================================
           Floating Save Bar
           ============================================ */
        .save-bar {
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%) translateY(120px);
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            z-index: 1000;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .save-bar.visible {
            transform: translateX(-50%) translateY(0);
        }
        
        .save-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .save-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .save-icon svg {
            width: 22px;
            height: 22px;
        }
        
        .save-text h4 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .save-text p {
            margin: 0.125rem 0 0;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .save-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-cancel {
            padding: 0.625rem 1.25rem;
            border: 1px solid rgba(255,255,255,0.2);
            background: transparent;
            color: white;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .btn-save {
            padding: 0.625rem 1.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }
        
        .btn-save:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.5);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }
        
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
            opacity: 0.5;
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

            <!-- Hero Header -->
            <div class="assign-hero">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <polyline points="17 11 19 13 23 9"/>
                            </svg>
                            <?php echo $mode === 'default' ? 'จับคู่ล่วงหน้า (Default Matching)' : 'จับคู่การประเมินรายรอบ'; ?>
                        </h1>
                        <p class="hero-subtitle">
                            <?php if ($mode === 'default'): ?>
                                กำหนดกรรมการเริ่มต้นให้แต่ละบริษัท ใช้สำหรับทุกรอบการประเมิน
                            <?php else: ?>
                                <?php echo $currentPeriod ? "รอบ: " . htmlspecialchars($currentPeriod['name']) . " (" . $currentPeriod['year'] . ")" : 'กรุณาเลือกรอบการประเมิน'; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="hero-actions">
                        <form method="POST">
                            <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
                            <input type="hidden" name="match_mode" value="<?php echo $mode; ?>">
                            <button type="submit" name="smart_match_all" class="btn-smart-match" onclick="return confirm('🤖 Smart Match จะวิเคราะห์ประเภทธุรกิจและความเชี่ยวชาญของกรรมการ เพื่อจับคู่ที่เหมาะสมที่สุด\n\nยืนยันดำเนินการ?')">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                                </svg>
                                Smart Match ทั้งหมด
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo $totalCompanies; ?></div>
                    <div class="stat-label">บริษัททั้งหมด</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo $assignedCount; ?></div>
                    <div class="stat-label">จับคู่แล้ว</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo $unassignedCount; ?></div>
                    <div class="stat-label">รอจับคู่</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                    <div class="stat-value"><?php echo $matchRate; ?>%</div>
                    <div class="stat-label">อัตราการจับคู่</div>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="mode-toggle">
                        <a href="?mode=period<?php echo $periodId ? "&period={$periodId}" : ''; ?>" class="mode-btn <?php echo $mode === 'period' ? 'active' : ''; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            รายรอบ
                        </a>
                        <a href="?mode=default" class="mode-btn <?php echo $mode === 'default' ? 'active' : ''; ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/>
                            </svg>
                            ค่าเริ่มต้น
                        </a>
                    </div>
                    
                    <?php if ($mode === 'period' && !empty($periods)): ?>
                    <form method="GET" style="display: flex; align-items: center;">
                        <input type="hidden" name="mode" value="period">
                        <select name="period" class="period-select" onchange="this.form.submit()">
                            <?php foreach ($periods as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $periodId == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?> (<?php echo $p['year']; ?>)
                                <?php if ($p['status'] === 'open'): ?> - กำลังเปิด<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="searchInput" placeholder="ค้นหาบริษัท..." onkeyup="filterCards()">
                </div>
            </div>

            <!-- Assignment Grid -->
            <?php if (empty($assignments)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                <h3>ไม่พบข้อมูลบริษัท</h3>
                <p>กรุณาเพิ่มบริษัทในระบบก่อน</p>
            </div>
            <?php else: ?>
            <div class="assign-grid" id="assignGrid">
                <?php foreach ($assignments as $index => $a): ?>
                <?php 
                    $currentAssignedId = ($mode === 'default') ? $a['default_evaluator_id'] : $a['assigned_id'];
                    $currentAssignedName = ($mode === 'default') ? $a['default_evaluator_name'] : $a['assigned_name'];
                    $isMatched = !empty($currentAssignedId);
                    $industries = array_filter(array_map('trim', explode(',', $a['industry_type'] ?? '')));
                ?>
                <div class="assign-card <?php echo $isMatched ? 'matched' : ''; ?>" 
                     id="card_<?php echo $a['company_id']; ?>"
                     data-company="<?php echo htmlspecialchars(strtolower($a['company_name'])); ?>"
                     style="animation-delay: <?php echo $index * 0.05; ?>s">
                    
                    <div class="card-header">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;">
                            <div style="flex: 1; min-width: 0;">
                                <h3 class="company-name" title="<?php echo htmlspecialchars($a['company_name']); ?>">
                                    <?php echo htmlspecialchars($a['company_name']); ?>
                                </h3>
                                <div class="industry-tags">
                                    <?php if (!empty($industries)): ?>
                                        <?php foreach (array_slice($industries, 0, 3) as $ind): ?>
                                        <span class="industry-tag"><?php echo htmlspecialchars(preg_replace('/\s*\([^)]+\)/', '', $ind)); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($industries) > 3): ?>
                                        <span class="industry-tag">+<?php echo count($industries) - 3; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="industry-tag">ทั่วไป</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $isMatched ? 'matched' : 'waiting'; ?>" id="status_<?php echo $a['company_id']; ?>">
                                <?php if ($isMatched): ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    จับคู่แล้ว
                                <?php else: ?>
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="12" y1="8" x2="12" y2="12"/>
                                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                                    </svg>
                                    รอจับคู่
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="auditor-list-label">เลือกกรรมการผู้ประเมิน</div>
                        
                        <?php foreach ($auditors as $aud): ?>
                        <?php 
                            $score = getMatchingScore($a['industry_type'], $aud['expertise']);
                            $scoreClass = $score >= 70 ? 'high' : ($score >= 30 ? 'medium' : 'low');
                            $isSelected = ($currentAssignedId == $aud['id']);
                        ?>
                        <label class="auditor-option <?php echo $isSelected ? 'selected' : ''; ?>" 
                               onclick="selectAuditor(<?php echo $a['company_id']; ?>, <?php echo $aud['id']; ?>, this)">
                            <input type="radio" name="auditor_<?php echo $a['company_id']; ?>" 
                                   value="<?php echo $aud['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                            <div class="auditor-avatar">
                                <?php echo mb_substr($aud['name'], 0, 1, 'UTF-8'); ?>
                            </div>
                            <div class="auditor-info">
                                <div class="auditor-name"><?php echo htmlspecialchars($aud['name']); ?></div>
                                <div class="auditor-meta">
                                    <?php if ($aud['org_name']): ?>
                                    <span><?php echo htmlspecialchars($aud['org_name']); ?></span>
                                    <span>•</span>
                                    <?php endif; ?>
                                    <span><?php echo $aud['total_assignments']; ?> งาน</span>
                                </div>
                            </div>
                            <?php if ($score > 0): ?>
                            <span class="match-score <?php echo $scoreClass; ?>"><?php echo $score; ?>%</span>
                            <?php endif; ?>
                            <div class="check-icon">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Floating Save Bar -->
    <div class="save-bar" id="saveBar">
        <div class="save-info">
            <div class="save-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
            </div>
            <div class="save-text">
                <h4>มีการเปลี่ยนแปลง</h4>
                <p><span id="changeCount">0</span> รายการรอบันทึก</p>
            </div>
        </div>
        <form method="POST" id="saveForm" class="save-actions">
            <input type="hidden" name="period_id" value="<?php echo $periodId; ?>">
            <input type="hidden" name="is_default_mode" value="<?php echo $mode === 'default' ? '1' : '0'; ?>">
            <input type="hidden" name="assignments_data" id="assignmentsData">
            <button type="button" class="btn-cancel" onclick="cancelChanges()">ยกเลิก</button>
            <button type="submit" name="save_staged_assignments" class="btn-save">บันทึกทั้งหมด</button>
        </form>
    </div>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        let stagedChanges = {};
        
        function selectAuditor(companyId, auditorId, element) {
            // Update visual selection
            const card = document.getElementById('card_' + companyId);
            card.querySelectorAll('.auditor-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            
            // Update status badge
            const statusBadge = document.getElementById('status_' + companyId);
            statusBadge.className = 'status-badge staged';
            statusBadge.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>รอบันทึก';
            
            // Update card style
            card.classList.remove('matched');
            card.classList.add('staged');
            
            // Stage the change
            stagedChanges[companyId] = auditorId;
            updateSaveBar();
        }
        
        function updateSaveBar() {
            const count = Object.keys(stagedChanges).length;
            document.getElementById('changeCount').textContent = count;
            document.getElementById('assignmentsData').value = JSON.stringify(stagedChanges);
            
            const saveBar = document.getElementById('saveBar');
            if (count > 0) {
                saveBar.classList.add('visible');
            } else {
                saveBar.classList.remove('visible');
            }
        }
        
        function cancelChanges() {
            if (Object.keys(stagedChanges).length > 0) {
                if (confirm('ยกเลิกการเปลี่ยนแปลงทั้งหมด?')) {
                    window.location.reload();
                }
            }
        }
        
        function filterCards() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.assign-card');
            
            cards.forEach(card => {
                const company = card.getAttribute('data-company') || '';
                card.style.display = company.includes(query) ? '' : 'none';
            });
        }
        
        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (Object.keys(stagedChanges).length > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>
