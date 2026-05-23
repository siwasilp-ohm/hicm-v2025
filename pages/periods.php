<?php
/**
 * HICM V2025 Assessment System - Assessment Periods Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/periods.php';

requireAuth();

if (!hasRole(ROLE_ADMIN)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create') {
            // Combine announcement date + time into DATETIME
            $annDate = $_POST['announcement_date'];
            $annTime = !empty($_POST['announcement_time']) ? $_POST['announcement_time'] : '09:00';
            $announcementDatetime = $annDate . ' ' . $annTime . ':00';
            
            $data = [
                'year' => intval($_POST['year']),
                'name' => sanitizeInput($_POST['name']),
                'description' => sanitizeInput($_POST['description']),
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'submission_deadline' => $_POST['submission_deadline'],
                'evaluation_start_date' => $_POST['evaluation_start_date'],
                'evaluation_end_date' => $_POST['evaluation_end_date'],
                'announcement_date' => $announcementDatetime
            ];
            $result = createPeriod($data);
            if ($result['success']) {
                $msg = 'สร้างรอบการประเมินเรียบร้อยแล้ว';
                if (!empty($result['warnings'])) {
                    $msg .= ' ⚠️ ' . implode(' | ', $result['warnings']);
                }
                setFlashMessage($msg, 'success');
            }
            else setFlashMessage($result['message'], 'error');
        } 
        elseif ($action === 'update') {
            $id = intval($_POST['id']);
            // Combine announcement date + time into DATETIME
            $annDate = $_POST['announcement_date'];
            $annTime = !empty($_POST['announcement_time']) ? $_POST['announcement_time'] : '09:00';
            $announcementDatetime = $annDate . ' ' . $annTime . ':00';
            
            $data = [
                'year' => intval($_POST['year']),
                'name' => sanitizeInput($_POST['name']),
                'description' => sanitizeInput($_POST['description']),
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'submission_deadline' => $_POST['submission_deadline'],
                'evaluation_start_date' => $_POST['evaluation_start_date'],
                'evaluation_end_date' => $_POST['evaluation_end_date'],
                'announcement_date' => $announcementDatetime
            ];
            $result = updatePeriod($id, $data);
            if ($result['success']) setFlashMessage('อัปเดตรอบการประเมินเรียบร้อยแล้ว', 'success');
            else setFlashMessage($result['message'], 'error');
        }
        elseif ($action === 'update_status') {
            $id = intval($_POST['id']);
            $status = sanitizeInput($_POST['status']);
            $result = updatePeriodStatus($id, $status);
            if ($result['success']) {
                $msg = 'เปลี่ยนสถานะเรียบร้อยแล้ว';
                if (!empty($result['auto_closed'])) {
                    $msg .= ' (ปิดรอบเดิมอัตโนมัติ: ' . implode(', ', $result['auto_closed']) . ')';
                }
                setFlashMessage($msg, 'success');
            } else {
                setFlashMessage($result['message'], 'error');
            }
        }
        elseif ($action === 'archive') {
            $id = intval($_POST['id']);
            $result = archivePeriod($id);
            if ($result['success']) setFlashMessage($result['message'], 'success');
            else setFlashMessage($result['message'], 'error');
        }
        elseif ($action === 'restore') {
            $id = intval($_POST['id']);
            $result = restorePeriod($id);
            if ($result['success']) setFlashMessage($result['message'], 'success');
            else setFlashMessage($result['message'], 'error');
        }
        elseif ($action === 'delete') {
            $id = intval($_POST['id']);
            $result = deletePeriod($id);
            if ($result['success']) setFlashMessage($result['message'], 'success');
            else setFlashMessage($result['message'], 'error');
        }
        elseif ($action === 'delete_with_assessments') {
            $id = intval($_POST['id']);
            $result = deleteAllAssessmentsAndPeriod($id);
            if ($result['success']) setFlashMessage($result['message'], 'success');
            else setFlashMessage($result['message'], 'error');
        }
        elseif ($action === 'toggle_auditor_results') {
            $id = intval($_POST['id']);
            $result = toggleShowAuditorResults($id);
            if ($result['success']) {
                $icon = $result['show_auditor_results'] ? '👁️' : '🔒';
                setFlashMessage($icon . ' ' . $result['message'], 'success');
            } else {
                setFlashMessage($result['message'], 'error');
            }
        }
        elseif ($action === 'toggle_results_announced') {
            $id = intval($_POST['id']);
            $result = toggleResultsAnnounced($id);
            if ($result['success']) {
                $icon = $result['results_announced'] ? '🏆' : '🔒';
                setFlashMessage($icon . ' ' . $result['message'], 'success');
            } else {
                setFlashMessage($result['message'], 'error');
            }
        }
        
        redirect(getBaseUrl() . '/pages/periods.php' . (isset($_GET['show_archived']) ? '?show_archived=1' : ''));
    }
}

// Check if showing archived
$showArchived = isset($_GET['show_archived']);

// ตรวจสอบและอัปเดตสถานะรอบประเมินตามวันที่ (force check ทุกครั้งในหน้านี้)
$autoStatusResult = checkAndUpdatePeriodStatuses(true);
if (!empty($autoStatusResult['changes'])) {
    $autoMsgs = [];
    $hasAutoAnnounce = false;
    foreach ($autoStatusResult['changes'] as $ch) {
        if ($ch['to'] === 'results_announced=1') $hasAutoAnnounce = true;
        $autoMsgs[] = "\"{$ch['name']}\" → {$ch['to_label']} ({$ch['reason']})";
    }
    $flashIcon = $hasAutoAnnounce ? '🏆' : '⏰';
    $flashType = $hasAutoAnnounce ? 'success' : 'info';
    setFlashMessage($flashIcon . ' ระบบอัปเดตอัตโนมัติ: ' . implode(' | ', $autoMsgs), $flashType);
}

$periods = getAllPeriods($showArchived ? ['include_archived' => true] : []);

// Find currently open period(s) for auto-close warning
$currentlyOpenPeriods = array_filter($periods, function($p) {
    return in_array($p['status'], ['open', 'evaluating']) && $p['is_active'];
});
$currentlyOpenPeriodsJson = json_encode(array_values(array_map(function($p) {
    return ['id' => $p['id'], 'name' => $p['name'], 'year' => $p['year']];
}, $currentlyOpenPeriods)));

// All active periods for overlap checking in JS
$allPeriodsForOverlap = array_values(array_filter($periods, function($p) {
    return $p['is_active'] && !in_array($p['status'], ['completed']);
}));
$allPeriodsOverlapJson = json_encode(array_map(function($p) {
    return [
        'id' => $p['id'],
        'name' => $p['name'],
        'year' => $p['year'],
        'status' => $p['status'],
        'start_date' => $p['start_date'],
        'end_date' => $p['end_date']
    ];
}, $allPeriodsForOverlap));

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รอบการประเมิน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .period-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: visible !important; /* Allow dropdown to overflow */
            border: 1px solid var(--gray-200);
            transition: all var(--transition-base);
        }
        
        .period-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-300);
            z-index: 10;
        }
        
        /* Container to clip decorations like the big year number */
        .period-decoration-clip {
            position: absolute;
            inset: 0;
            overflow: hidden;
            border-radius: var(--radius-xl);
            pointer-events: none;
            z-index: 0;
        }
        
        .period-status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 2;
        }
        
        .period-year {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--gray-100);
            position: absolute;
            bottom: -0.5rem;
            right: -0.5rem;
            line-height: 1;
            z-index: 0;
        }
        
        .period-content {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .timeline-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .timeline-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: var(--gray-100);
            color: var(--gray-500);
            flex-shrink: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background-color: white;
            border-radius: var(--radius-xl);
            border: 2px dashed var(--gray-200);
        }

        /* Status Colors */
        .status-draft .timeline-icon { color: var(--gray-500); background: var(--gray-100); }
        .status-open .timeline-icon { color: var(--success); background: var(--success-light); }
        .status-closed .timeline-icon { color: var(--danger); background: var(--danger-light); }
        .status-evaluating .timeline-icon { color: var(--warning); background: var(--warning-light); }
        .status-completed .timeline-icon { color: var(--primary-500); background: var(--primary-50); }
        
        /* Active period animation (open or evaluating = กำลังดำเนินการ) */
        .period-card.period-active {
            border-color: var(--success);
            box-shadow: 0 0 0 1px var(--success), var(--shadow-md);
        }
        
        .period-active-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(16,185,129,0.04));
            border: 1px solid rgba(16,185,129,0.2);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: var(--success-dark, #065f46);
            font-weight: 500;
        }
        
        .pulse-dot {
            width: 10px;
            height: 10px;
            background: var(--success);
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
        }
        
        .pulse-dot::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            background: var(--success);
            opacity: 0.4;
            animation: pulse-ring 2s ease-in-out infinite;
        }
        
        @keyframes pulse-ring {
            0% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.8); opacity: 0; }
            100% { transform: scale(1); opacity: 0; }
        }
        
        .period-card.period-active:hover {
            border-color: var(--success);
            box-shadow: 0 0 0 2px rgba(16,185,129,0.3), var(--shadow-lg);
        }
        
        /* Grid Layout */
        .periods-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.5rem;
        }
        
        @media (min-width: 768px) {
            .periods-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1280px) {
            .periods-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* Dropdown custom */
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            z-index: 100;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--gray-200);
            min-width: 180px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .dropdown-item {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .dropdown-item:hover {
            background-color: var(--gray-50);
        }
        
        .dropdown-item.text-success { color: var(--success); }
        .dropdown-item.text-warning { color: var(--warning); }
        .dropdown-item.text-danger { color: var(--danger); }
        .dropdown-item.text-primary { color: var(--primary-500); }
        
        /* Utility overrides */
        .w-full { width: 100%; }
        
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .gap-2 { gap: 0.5rem; }
        .gap-4 { gap: 1rem; }
        .mb-4 { margin-bottom: 1rem; }

        .period-assessment-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.5rem 0.75rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            text-decoration: none;
            color: var(--gray-600);
            transition: all var(--transition-base);
            border: 1px solid transparent;
        }

        .period-assessment-link:hover {
            background: var(--primary-50);
            color: var(--primary-600);
            border-color: var(--primary-200);
            transform: scale(1.02);
        }

        .period-assessment-link svg {
            color: var(--gray-500);
            transition: color var(--transition-base);
        }

        .period-assessment-link:hover svg {
            color: var(--primary-500);
        }

        /* =============================================
           ANNOUNCEMENT COUNTDOWN WIDGET
           ============================================= */
        .announce-widget {
            margin-top: 0.75rem;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
        }

        /* State: upcoming (date in future) */
        .announce-widget.announce-upcoming {
            background: linear-gradient(135deg, #fefce8 0%, #fef9c3 100%);
            border: 1px solid #fde68a;
        }
        .announce-upcoming .announce-header { color: #a16207; }
        .announce-upcoming .announce-header-icon { background: #fef08a; color: #a16207; }
        .announce-upcoming .announce-date-relative { background: rgba(245,158,11,0.15); color: #b45309; }
        .announce-upcoming .announce-cd-val { color: #b45309; }
        .announce-upcoming .announce-progress-fill { background: linear-gradient(90deg, #f59e0b, #eab308); }

        /* State: today */
        .announce-widget.announce-today {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #6ee7b7;
            animation: announceGlow 2s ease-in-out infinite;
        }
        .announce-today .announce-header { color: #047857; }
        .announce-today .announce-header-icon { background: #a7f3d0; color: #047857; }
        .announce-today .announce-date-relative { background: rgba(16,185,129,0.15); color: #047857; }
        .announce-today .announce-cd-val { color: #047857; }
        .announce-today .announce-progress-fill { background: linear-gradient(90deg, #10b981, #34d399); }

        /* State: passed (date passed, not yet announced) */
        .announce-widget.announce-passed {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #7dd3fc;
        }
        .announce-passed .announce-header { color: #0369a1; }
        .announce-passed .announce-header-icon { background: #bae6fd; color: #0369a1; }
        .announce-passed .announce-date-relative { background: rgba(14,165,233,0.15); color: #0369a1; }
        .announce-passed .announce-progress-fill { background: linear-gradient(90deg, #0ea5e9, #38bdf8); }

        /* State: waiting (too early, >14 days) */
        .announce-widget.announce-waiting {
            background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%);
            border: 1px solid #d8b4fe;
        }
        .announce-waiting .announce-header { color: #7c3aed; }
        .announce-waiting .announce-header-icon { background: #e9d5ff; color: #7c3aed; }

        /* State: announced (toggle ON) — overrides all date-based states */
        .announce-widget.announced-active {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%) !important;
            border: 2px solid #34d399 !important;
            box-shadow: 0 0 0 2px rgba(16,185,129,0.15), 0 4px 16px rgba(16,185,129,0.12);
            animation: none;
        }
        .announced-active .announce-header { color: #047857 !important; }
        .announced-active .announce-header-icon { background: #a7f3d0 !important; color: #047857 !important; }
        .announced-active .announce-date-relative { background: rgba(16,185,129,0.15) !important; color: #047857 !important; }
        .announced-active .announce-progress-fill { background: linear-gradient(90deg, #10b981, #34d399) !important; width: 100% !important; }

        /* Announced celebration body */
        .announce-celebrated {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }
        .announce-celebrated-icon {
            font-size: 1.5rem;
            line-height: 1;
            animation: announceBounce 2s ease infinite;
        }
        @keyframes announceBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        .announce-celebrated-text {
            font-size: 0.8rem;
            font-weight: 600;
            color: #047857;
        }
        .announce-celebrated-sub {
            font-size: 0.68rem;
            color: #059669;
            margin-top: 0.1rem;
        }

        /* Auto-announce countdown reached zero animation */
        @keyframes countdownZeroPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }
        @keyframes countdownZeroGlow {
            0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.4); }
            50% { box-shadow: 0 0 20px 8px rgba(16,185,129,0.15); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        @keyframes autoToggleSlide {
            0% { transform: translateX(0); }
            60% { transform: translateX(18px); }
            80% { transform: translateX(14px); }
            100% { transform: translateX(16px); }
        }
        @keyframes confettiDrop {
            0% { opacity: 1; transform: translateY(0) rotate(0deg); }
            100% { opacity: 0; transform: translateY(60px) rotate(360deg); }
        }
        .announce-widget.auto-transitioning {
            animation: countdownZeroPulse 0.6s ease, countdownZeroGlow 1.5s ease;
        }
        .announce-widget.auto-transitioning .announce-toggle-slider {
            background: #10b981 !important;
            transition: background 0.5s ease;
        }
        .announce-widget.auto-transitioning .announce-toggle-slider::before {
            animation: autoToggleSlide 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        .auto-confetti-container {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 10;
        }
        .auto-confetti-piece {
            position: absolute;
            width: 6px;
            height: 6px;
            border-radius: 2px;
            animation: confettiDrop 1.2s ease forwards;
        }

        /* Reload progress animation when countdown reaches zero */
        .reload-progress-container {
            margin-top: 0.75rem;
            padding: 1rem;
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }
        .reload-progress-container::before {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 200%; height: 100%;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%);
            animation: reloadShimmer 2.5s ease-in-out infinite;
        }
        @keyframes reloadShimmer {
            0% { transform: translateX(-50%); }
            100% { transform: translateX(50%); }
        }
        .reload-progress-top {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        .reload-circle-wrap {
            position: relative;
            width: 56px;
            height: 56px;
            flex-shrink: 0;
        }
        .reload-circle-svg {
            transform: rotate(-90deg);
            width: 56px;
            height: 56px;
        }
        .reload-circle-bg {
            fill: none;
            stroke: #d1fae5;
            stroke-width: 4;
        }
        .reload-circle-fg {
            fill: none;
            stroke: #10b981;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 150.8;
            stroke-dashoffset: 150.8;
            transition: stroke-dashoffset 1s linear;
            filter: drop-shadow(0 0 3px rgba(16,185,129,0.5));
        }
        .reload-circle-text {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.75rem;
            font-weight: 700;
            color: #059669;
            line-height: 1;
            text-align: center;
        }
        .reload-circle-text .reload-secs {
            font-size: 1.1rem;
            display: block;
        }
        .reload-circle-text .reload-unit {
            font-size: 0.55rem;
            font-weight: 600;
            color: #34d399;
            letter-spacing: 0.5px;
        }
        .reload-info {
            flex: 1;
        }
        .reload-info-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .reload-info-title .reload-dot {
            width: 8px; height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: reloadDotPulse 1.5s ease-in-out infinite;
        }
        @keyframes reloadDotPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.7); }
        }
        .reload-info-sub {
            font-size: 0.75rem;
            color: #047857;
            opacity: 0.8;
        }
        .reload-bar-wrap {
            margin-top: 0.75rem;
            position: relative;
            z-index: 1;
        }
        .reload-bar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.35rem;
        }
        .reload-bar-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #059669;
        }
        .reload-bar-pct {
            font-size: 0.7rem;
            font-weight: 700;
            color: #047857;
            font-variant-numeric: tabular-nums;
        }
        .reload-bar {
            height: 6px;
            background: rgba(16,185,129,0.15);
            border-radius: 6px;
            overflow: hidden;
            position: relative;
        }
        .reload-bar-fill {
            height: 100%;
            width: 0%;
            border-radius: 6px;
            background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7);
            background-size: 200% 100%;
            animation: reloadBarGradient 2s ease infinite;
            transition: width 1s linear;
            position: relative;
        }
        .reload-bar-fill::after {
            content: '';
            position: absolute;
            right: 0;
            top: -1px;
            width: 10px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px rgba(16,185,129,0.6);
        }
        @keyframes reloadBarGradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .reload-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 0.4rem;
            position: relative;
            z-index: 1;
        }
        .reload-step {
            font-size: 0.6rem;
            color: #6ee7b7;
            font-weight: 600;
            transition: color 0.5s ease;
        }
        .reload-step.active {
            color: #059669;
        }

        /* Auto-announce badge */
        .announce-auto-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.55rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding: 0.12rem 0.4rem;
            border-radius: 4px;
            text-transform: uppercase;
            vertical-align: middle;
            margin-left: 0.35rem;
        }
        .announce-auto-badge.auto-scheduled {
            background: rgba(245,158,11,0.15);
            color: #b45309;
            border: 1px solid rgba(245,158,11,0.3);
        }
        .announce-auto-badge.auto-today {
            background: rgba(16,185,129,0.15);
            color: #047857;
            border: 1px solid rgba(16,185,129,0.3);
            animation: autoBadgePulse 2s ease infinite;
        }
        .announce-auto-badge.auto-done {
            background: rgba(16,185,129,0.15);
            color: #047857;
            border: 1px solid rgba(16,185,129,0.3);
        }
        @keyframes autoBadgePulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        /* Auto-schedule info line */
        .announce-auto-info {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.68rem;
            color: #64748b;
            margin-top: 0.4rem;
            padding: 0.3rem 0.5rem;
            background: rgba(255,255,255,0.6);
            border-radius: 6px;
            border: 1px dashed rgba(0,0,0,0.08);
        }
        .announce-auto-info svg {
            flex-shrink: 0;
        }
        .announced-active .announce-auto-info {
            background: rgba(255,255,255,0.5);
            border-color: rgba(16,185,129,0.2);
            color: #059669;
        }

        @keyframes announceGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.2); }
            50% { box-shadow: 0 0 0 6px rgba(16,185,129,0); }
        }

        .announce-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 0.85rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .announce-header-icon {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .announce-body {
            padding: 0 0.85rem 0.7rem;
        }

        .announce-date-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .announce-date-main {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e293b;
        }

        .announce-date-relative {
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.15rem 0.45rem;
            border-radius: 6px;
        }

        /* Countdown Grid */
        .announce-countdown {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.35rem;
        }

        .announce-cd-unit {
            text-align: center;
            padding: 0.35rem 0;
            border-radius: 8px;
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0,0,0,0.04);
        }

        .announce-cd-val {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }

        .announce-cd-lbl {
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-top: 0.05rem;
        }

        /* Progress bar */
        .announce-progress {
            height: 3px;
            background: rgba(0,0,0,0.06);
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .announce-progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
        }

        /* Toggle Switch */
        .announce-toggle {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            user-select: none;
        }

        .announce-toggle input {
            display: none;
        }

        .announce-toggle-slider {
            position: relative;
            width: 34px;
            height: 18px;
            background: #cbd5e1;
            border-radius: 9px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            flex-shrink: 0;
        }

        .announce-toggle-slider::before {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }

        .announce-toggle input:checked + .announce-toggle-slider {
            background: #10b981;
        }

        .announce-toggle input:checked + .announce-toggle-slider::before {
            transform: translateX(16px);
        }

        .announce-toggle-label {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: inherit;
            min-width: 20px;
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
            
            <div class="page-header flex-between mb-6">
                <div>
                    <h1 class="page-title">จัดการรอบการประเมิน</h1>
                    <p class="page-subtitle">กำหนดช่วงเวลาการประเมินประจำปีและสถานะโครงการ</p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <?php if ($showArchived): ?>
                        <a href="periods.php" class="btn btn-secondary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                            กลับหน้าหลัก
                        </a>
                    <?php else: ?>
                        <a href="periods.php?show_archived=1" class="btn btn-secondary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="21 8 21 21 3 21 3 8"/>
                                <rect x="1" y="3" width="22" height="5"/>
                                <line x1="10" y1="12" x2="14" y2="12"/>
                            </svg>
                            ดูคลังเก็บ
                        </a>
                    <?php endif; ?>
                    <button class="btn btn-primary" onclick="openModal('create')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        สร้างรอบการประเมิน
                    </button>
                </div>
            </div>
            
            <?php if ($showArchived): ?>
            <div style="background: var(--warning-light); border: 1px solid var(--warning); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--warning)" stroke-width="2">
                    <polyline points="21 8 21 21 3 21 3 8"/>
                    <rect x="1" y="3" width="22" height="5"/>
                </svg>
                <span style="color: var(--warning-dark);">กำลังแสดงรอบการประเมินทั้งหมด รวมถึงที่เก็บเข้าคลังแล้ว</span>
            </div>
            <?php endif; ?>

            <?php if (empty($periods)): ?>
                <div class="empty-state">
                    <div style="width: 80px; height: 80px; background: var(--primary-50); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary-500)" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">ยังไม่มีรอบการประเมิน</h3>
                    <p class="text-gray-500 mb-6">เริ่มต้นสร้างรอบการประเมินใหม่เพื่อเปิดให้สถานประกอบการเข้าทำแบบประเมิน</p>
                    <button class="btn btn-outline" onclick="openModal('create')">สร้างรอบการประเมินแรก</button>
                </div>
            <?php else: ?>
                <div class="periods-grid">
                    <?php foreach ($periods as $period): ?>
                        <div class="card period-card status-<?php echo $period['status']; ?><?php echo in_array($period['status'], ['open', 'evaluating']) ? ' period-active' : ''; ?>">
                            <!-- Decoration Clip -->
                            <div class="period-decoration-clip">
                                <div class="period-year"><?php echo $period['year']; ?></div>
                            </div>

                            <div class="card-body period-content">
                                <div class="period-status-badge">
                                    <?php
                                    $isActivePeriod = in_array($period['status'], ['open', 'evaluating']);
                                    $statusBadgeMap = [
                                        'draft' => 'secondary',
                                        'open' => 'success',
                                        'evaluating' => 'success',
                                        'closed' => 'danger',
                                        'completed' => 'primary',
                                    ];
                                    $statusLabelMap = [
                                        'draft' => 'ร่าง',
                                        'open' => 'กำลังดำเนินการ',
                                        'evaluating' => 'กำลังดำเนินการ',
                                        'closed' => 'ปิดรับแบบประเมิน',
                                        'completed' => 'เสร็จสิ้น',
                                    ];
                                    ?>
                                    <span class="badge badge-<?php echo $statusBadgeMap[$period['status']] ?? 'secondary'; ?>">
                                        <?php echo $statusLabelMap[$period['status']] ?? ucfirst($period['status']); ?>
                                    </span>
                                    <?php if (!$period['is_active']): ?>
                                        <span class="badge" style="background: var(--gray-200); color: var(--gray-600); margin-left: 0.5rem;">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                                                <polyline points="21 8 21 21 3 21 3 8"/>
                                                <rect x="1" y="3" width="22" height="5"/>
                                            </svg>
                                            เก็บเข้าคลัง
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="text-xl font-bold text-gray-900 mb-2" style="padding-right: 5rem;"><?php echo htmlspecialchars($period['name']); ?></h3>
                                <p class="text-sm text-gray-500 mb-4 line-clamp-2"><?php echo htmlspecialchars($period['description']); ?></p>
                                
                                <?php if (in_array($period['status'], ['open', 'evaluating'])): ?>
                                <div class="period-active-indicator">
                                    <div class="pulse-dot"></div>
                                    <span>กำลังดำเนินการ — บริษัทสามารถสมัครและทำแบบประเมินได้</span>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Assessment Count -->
                                <a href="assessments.php?period=<?php echo $period['id']; ?>" class="period-assessment-link" title="คลิกเพื่อดูรายการประเมินทั้งหมดในรอบนี้">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                    </svg>
                                    <span class="text-sm">
                                        การประเมิน: <strong><?php echo $period['assessment_count'] ?? 0; ?></strong> รายการ
                                    </span>
                                </a>
                                
                                <!-- Auditor Results Visibility Badge -->
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; padding: 0.4rem 0.75rem; border-radius: 8px; background: <?php echo $period['show_auditor_results'] ? '#ecfdf5' : '#f8fafc'; ?>; border: 1px solid <?php echo $period['show_auditor_results'] ? '#d1fae5' : '#e2e8f0'; ?>; font-size: 0.8rem; color: <?php echo $period['show_auditor_results'] ? '#059669' : '#94a3b8'; ?>;">
                                    <?php if ($period['show_auditor_results']): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>ผลกรรมการ: <strong>เปิดให้บริษัทดู</strong></span>
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                        </svg>
                                        <span>ผลกรรมการ: ยังไม่เปิดแสดง</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top: auto;">
                                    <?php
                                    $today = date('Y-m-d');
                                    $isOpen = in_array($period['status'], ['open', 'evaluating']);
                                    
                                    // คำนวณ countdown / overdue
                                    $startDate = $period['start_date'];
                                    $endDate = $period['end_date'];
                                    $submissionDeadline = $period['submission_deadline'] ?: $endDate;
                                    $evalStart = $period['evaluation_start_date'] ?: null;
                                    $evalEnd = $period['evaluation_end_date'] ?: $endDate;
                                    
                                    $daysToSubmission = (strtotime($submissionDeadline) - strtotime($today)) / 86400;
                                    $daysToEnd = (strtotime($endDate) - strtotime($today)) / 86400;
                                    ?>
                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                <line x1="16" y1="2" x2="16" y2="6"/>
                                                <line x1="8" y1="2" x2="8" y2="6"/>
                                                <line x1="3" y1="10" x2="21" y2="10"/>
                                            </svg>
                                        </div>
                                        <span>โครงการ: <?php echo date('d/m/y', strtotime($startDate)); ?> - <?php echo date('d/m/y', strtotime($endDate)); ?>
                                            <?php if ($isOpen && $daysToEnd < 0): ?>
                                                <span style="color: var(--danger); font-weight: 600; font-size: 0.75rem; margin-left: 0.25rem;">
                                                    (เลยกำหนด <?php echo abs(intval($daysToEnd)); ?> วัน)
                                                </span>
                                            <?php elseif ($isOpen && $daysToEnd <= 7): ?>
                                                <span style="color: var(--warning); font-weight: 600; font-size: 0.75rem; margin-left: 0.25rem;">
                                                    (เหลือ <?php echo intval($daysToEnd); ?> วัน)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                                <polyline points="14 2 14 8 20 8"/>
                                                <line x1="12" y1="18" x2="12" y2="12"/>
                                                <line x1="9" y1="15" x2="15" y2="15"/>
                                            </svg>
                                        </div>
                                        <span class="<?php echo ($daysToSubmission < 0) ? 'text-danger' : ''; ?>" style="<?php echo ($daysToSubmission < 0) ? 'font-weight:600;' : ''; ?>">
                                            ส่งภายใน: <?php echo date('d/m/y', strtotime($submissionDeadline)); ?>
                                            <?php if ($isOpen && $daysToSubmission < 0): ?>
                                                <span style="font-size: 0.75rem; margin-left: 0.25rem;">(เลยกำหนด <?php echo abs(intval($daysToSubmission)); ?> วัน)</span>
                                            <?php elseif ($isOpen && $daysToSubmission <= 7 && $daysToSubmission >= 0): ?>
                                                <span style="color: var(--warning); font-weight: 600; font-size: 0.75rem; margin-left: 0.25rem;">(เหลือ <?php echo intval($daysToSubmission); ?> วัน)</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if ($evalStart || $evalEnd): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10"/>
                                                <polyline points="12 6 12 12 16 14"/>
                                            </svg>
                                        </div>
                                        <span>ช่วงประเมิน: <?php
                                            echo $evalStart ? date('d/m/y', strtotime($evalStart)) : '-';
                                            echo ' - ';
                                            echo $evalEnd ? date('d/m/y', strtotime($evalEnd)) : '-';
                                        ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($period['announcement_date'])): ?>
                                    <?php
                                    $annDate = $period['announcement_date'];
                                    $annTs = strtotime($annDate);
                                    $nowTs = time();
                                    $todayTs = strtotime($today);
                                    $isAnnounced = !empty($period['results_announced']);
                                    
                                    // Use date-only comparison for state + time-aware for countdown
                                    $annDateOnly = date('Y-m-d', $annTs);
                                    $todayDateOnly = date('Y-m-d', $todayTs);
                                    $annTimeStr = date('H:i', $annTs); // Extract time part
                                    $daysToAnnounce = intval(round((strtotime($annDateOnly) - $todayTs) / 86400));
                                    
                                    // Determine base state from date
                                    if ($annDateOnly > $todayDateOnly) {
                                        $annClass = 'announce-upcoming';
                                        $annLabel = 'รอประกาศผล';
                                        $annIcon = '🔔';
                                    } elseif ($annDateOnly === $todayDateOnly) {
                                        $annClass = 'announce-today';
                                        $annLabel = '🎉 ประกาศผลวันนี้!';
                                        $annIcon = '🎊';
                                    } else {
                                        $annClass = 'announce-passed';
                                        $annLabel = 'ครบกำหนดประกาศผล';
                                        $annIcon = '📋';
                                    }
                                    
                                    // Waiting state: period still active + too far out
                                    if (in_array($period['status'], ['draft', 'open', 'evaluating']) && $daysToAnnounce > 14) {
                                        $annClass = 'announce-waiting';
                                        $annLabel = 'กำหนดประกาศผล';
                                        $annIcon = '📅';
                                    }
                                    
                                    // Thai month names
                                    $thaiMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
                                    $annDay = date('j', $annTs);
                                    $annMonth = $thaiMonths[(int)date('n', $annTs)];
                                    $annYear = (int)date('Y', $annTs) + 543;
                                    $annFormatted = $annDay . ' ' . $annMonth . ' ' . $annYear;
                                    $annFormattedWithTime = $annFormatted . ' เวลา ' . $annTimeStr . ' น.';
                                    
                                    // ISO format for countdown target (use actual time from DB)
                                    $annIsoTarget = date('Y-m-d\TH:i:s', $annTs);
                                    
                                    // Progress calculation
                                    $evalEndDate = $period['evaluation_end_date'] ?: $period['end_date'];
                                    $evalEndTs = strtotime($evalEndDate);
                                    $totalSpan = max(1, intval(round(($annTs - $evalEndTs) / 86400)));
                                    $elapsed = intval(round(($todayTs - $evalEndTs) / 86400));
                                    $progressPct = $isAnnounced ? 100 : ($daysToAnnounce <= 0 ? 100 : min(100, max(0, ($elapsed / $totalSpan) * 100)));
                                    ?>
                                    <div class="announce-widget <?php echo $annClass; ?><?php echo $isAnnounced ? ' announced-active' : ''; ?>" data-announce-date="<?php echo $annDate; ?>" data-period-id="<?php echo $period['id']; ?>">
                                        <div class="announce-header">
                                            <div class="announce-header-icon">
                                                <span style="font-size: 0.75rem;"><?php echo $isAnnounced ? '🏆' : $annIcon; ?></span>
                                            </div>
                                            <span>
                                                <?php echo $isAnnounced ? '✅ ประกาศผลแล้ว' : $annLabel; ?>
                                                <?php if ($isAnnounced): ?>
                                                <span class="announce-auto-badge auto-done">LIVE</span>
                                                <?php elseif ($daysToAnnounce === 0): ?>
                                                <span class="announce-auto-badge auto-today">⚡ AUTO วันนี้</span>
                                                <?php elseif ($daysToAnnounce > 0): ?>
                                                <span class="announce-auto-badge auto-scheduled">⏰ AUTO</span>
                                                <?php endif; ?>
                                            </span>
                                            <!-- Toggle Switch for Announce Results -->
                                            <form method="POST" id="announceForm-<?php echo $period['id']; ?>" style="margin: 0; margin-left: auto;" onclick="event.stopPropagation();">
                                                <input type="hidden" name="action" value="toggle_results_announced">
                                                <input type="hidden" name="id" value="<?php echo $period['id']; ?>">
                                                <label class="announce-toggle" title="<?php echo $isAnnounced ? 'ปิดประกาศผล' : 'เปิดประกาศผล (ก่อนกำหนด)'; ?>">
                                                    <input type="checkbox" <?php echo $isAnnounced ? 'checked' : ''; ?> onchange="this.form.submit()">
                                                    <span class="announce-toggle-slider"></span>
                                                    <span class="announce-toggle-label"><?php echo $isAnnounced ? 'ON' : 'OFF'; ?></span>
                                                </label>
                                            </form>
                                        </div>
                                        <div class="announce-body">
                                            <?php if ($isAnnounced): ?>
                                            <!-- Announced state: celebration message -->
                                            <div class="announce-celebrated">
                                                <div class="announce-celebrated-icon">🏆</div>
                                                <div>
                                                    <div class="announce-celebrated-text">Leaderboard แสดงในหน้า Dashboard ของทุกผู้ใช้</div>
                                                    <div class="announce-celebrated-sub">วันประกาศ: <?php echo $annFormattedWithTime; ?></div>
                                                </div>
                                            </div>
                                            <div class="announce-auto-info">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                <span>ปิดประกาศผลได้โดยสลับ Toggle เป็น OFF</span>
                                            </div>
                                            <?php else: ?>
                                            <!-- Not yet announced: show date + countdown -->
                                            <div class="announce-date-row">
                                                <span class="announce-date-main"><?php echo $annFormatted; ?></span>
                                                <span class="announce-date-time" style="font-size: 0.8rem; color: var(--gray-500); margin-left: 0.25rem;">🕐 <?php echo $annTimeStr; ?> น.</span>
                                                <?php if ($daysToAnnounce > 0): ?>
                                                <span class="announce-date-relative">เหลือ <?php echo $daysToAnnounce; ?> วัน</span>
                                                <?php elseif ($daysToAnnounce === 0): ?>
                                                <span class="announce-date-relative">🎯 วันนี้!</span>
                                                <?php else: ?>
                                                <span class="announce-date-relative">ผ่านไป <?php echo abs($daysToAnnounce); ?> วัน</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php
                                            // Only render countdown if announcement time is still in the future
                                            $annStillFuture = ($nowTs < $annTs);
                                            ?>
                                            <?php if ($annStillFuture && $daysToAnnounce > 0): ?>
                                            <div class="announce-countdown" data-countdown-target="<?php echo $annIsoTarget; ?>">
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-days><?php echo $daysToAnnounce; ?></div>
                                                    <div class="announce-cd-lbl">วัน</div>
                                                </div>
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-hours>00</div>
                                                    <div class="announce-cd-lbl">ชั่วโมง</div>
                                                </div>
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-mins>00</div>
                                                    <div class="announce-cd-lbl">นาที</div>
                                                </div>
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-secs>00</div>
                                                    <div class="announce-cd-lbl">วินาที</div>
                                                </div>
                                            </div>
                                            <?php elseif ($annStillFuture && $daysToAnnounce === 0): ?>
                                            <div class="announce-countdown" data-countdown-target="<?php echo $annIsoTarget; ?>">
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-days>0</div>
                                                    <div class="announce-cd-lbl">วัน</div>
                                                </div>
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-hours>00</div>
                                                    <div class="announce-cd-lbl">ชั่วโมง</div>
                                                </div>
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-mins>00</div>
                                                    <div class="announce-cd-lbl">นาที</div>
                                                </div>
                                                <div class="announce-cd-unit">
                                                    <div class="announce-cd-val" data-cd-secs>00</div>
                                                    <div class="announce-cd-lbl">วินาที</div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="announce-auto-info">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                <?php if (!$annStillFuture): ?>
                                                <span>ครบกำหนดแล้ว — รอระบบประกาศอัตโนมัติ หรือเปิดเองด้วย Toggle</span>
                                                <?php elseif ($daysToAnnounce > 0): ?>
                                                <span>ระบบจะประกาศผลอัตโนมัติเมื่อถึงวัน-เวลาที่กำหนด · หรือเปิดเองได้ด้วย Toggle</span>
                                                <?php elseif ($daysToAnnounce === 0): ?>
                                                <span>⚡ ระบบจะประกาศผลอัตโนมัติเมื่อถึงเวลา <?php echo $annTimeStr; ?> น. · หรือเปิดเองได้ทันที</span>
                                                <?php else: ?>
                                                <span>ครบกำหนดแล้ว — รอระบบประกาศอัตโนมัติ หรือเปิดเองด้วย Toggle</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="announce-progress">
                                                <div class="announce-progress-fill" style="width: <?php echo round($progressPct); ?>%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-100); display: flex; justify-content: flex-end;">
                                    <div class="relative dropdown-container" style="position: relative;">
                                        <button class="btn btn-sm btn-outline dropdown-toggle" onclick="toggleDropdown(<?php echo $period['id']; ?>)">
                                            จัดการ
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 0.25rem;">
                                                <polyline points="6 9 12 15 18 9"/>
                                            </svg>
                                        </button>
                                        <div class="dropdown-menu" id="dropdown-<?php echo $period['id']; ?>">
                                            <button class="dropdown-item" onclick='openModal("update", <?php echo json_encode($period, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                แก้ไขข้อมูล
                                            </button>
                                            
                                            <?php
                                            // Flexible status transitions
                                            // open = กำลังดำเนินการ (รับสมัคร + ประเมิน รวมเป็นสถานะเดียว)
                                            // evaluating ถูก merge เข้ากับ open แล้ว (legacy data ยังทำงานได้)
                                            $currentStatus = $period['status'];
                                            $allTransitions = [
                                                'draft' => [
                                                    ['status' => 'open', 'label' => 'เปิดดำเนินการ', 'class' => 'text-success', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
                                                ],
                                                'open' => [
                                                    ['status' => 'closed', 'label' => 'ปิดรับแบบประเมิน', 'class' => 'text-danger', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'],
                                                    ['status' => 'completed', 'label' => 'จบโครงการ', 'class' => 'text-primary', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
                                                ],
                                                'evaluating' => [
                                                    ['status' => 'closed', 'label' => 'ปิดรับแบบประเมิน', 'class' => 'text-danger', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'],
                                                    ['status' => 'completed', 'label' => 'จบโครงการ', 'class' => 'text-primary', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
                                                ],
                                                'closed' => [
                                                    ['status' => 'open', 'label' => 'เปิดดำเนินการอีกครั้ง', 'class' => 'text-success', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'],
                                                    ['status' => 'completed', 'label' => 'จบโครงการ', 'class' => 'text-primary', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'],
                                                ],
                                                'completed' => [
                                                    ['status' => 'open', 'label' => 'เปิดดำเนินการอีกครั้ง', 'class' => 'text-success', 'icon' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>'],
                                                ],
                                            ];
                                            $transitions = $allTransitions[$currentStatus] ?? [];
                                            
                                            // "ต่อเวลา" button for non-draft periods
                                            if ($currentStatus !== 'draft'): ?>
                                                <button class="dropdown-item" onclick='openExtendModal(<?php echo json_encode($period, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' style="color: var(--info, #0ea5e9);">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;">
                                                        <circle cx="12" cy="12" r="10"/>
                                                        <polyline points="12 6 12 12 16 14"/>
                                                    </svg>
                                                    ต่อเวลา / ขยายโครงการ
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Toggle Auditor Results Visibility -->
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="action" value="toggle_auditor_results">
                                                <input type="hidden" name="id" value="<?php echo $period['id']; ?>">
                                                <button type="submit" class="dropdown-item" style="color: <?php echo $period['show_auditor_results'] ? 'var(--success)' : 'var(--gray-500)'; ?>; width: 100%; text-align: left; display: flex; align-items: center;">
                                                    <?php if ($period['show_auditor_results']): ?>
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;">
                                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                            <circle cx="12" cy="12" r="3"/>
                                                        </svg>
                                                        ผลกรรมการ: เปิดอยู่
                                                    <?php else: ?>
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.5rem;">
                                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                                        </svg>
                                                        ผลกรรมการ: ปิดอยู่
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if (!empty($transitions)): ?>
                                                <div style="border-top: 1px solid var(--gray-100); margin: 0.25rem 0;"></div>
                                                <div style="padding: 0.25rem 1rem; font-size: 0.7rem; color: var(--gray-400); text-transform: uppercase; font-weight: 600;">เปลี่ยนสถานะ</div>
                                            <?php endif;
                                            
                                            foreach ($transitions as $t): ?>
                                                <button class="dropdown-item <?php echo $t['class']; ?>" onclick="updateStatus(<?php echo $period['id']; ?>, '<?php echo $t['status']; ?>')">
                                                    <?php echo $t['icon']; ?><?php echo $t['label']; ?>
                                                </button>
                                            <?php endforeach; ?>
                                            
                                            <div style="border-top: 1px solid var(--gray-100); margin: 0.25rem 0;"></div>
                                            
                                            <?php if (!$period['is_active']): ?>
                                                <!-- Archived period - show restore option -->
                                                <button class="dropdown-item text-success" onclick="confirmRestore(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['name'], ENT_QUOTES); ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                                        <polyline points="1 4 1 10 7 10"/>
                                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                                                    </svg>
                                                    กู้คืน
                                                </button>
                                            <?php elseif (($period['assessment_count'] ?? 0) > 0): ?>
                                                <!-- Has assessments - show archive option and delete all option -->
                                                <button class="dropdown-item text-warning" onclick="confirmArchive(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['name'], ENT_QUOTES); ?>', <?php echo $period['assessment_count']; ?>)">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                                        <polyline points="21 8 21 21 3 21 3 8"/>
                                                        <rect x="1" y="3" width="22" height="5"/>
                                                    </svg>
                                                    เก็บเข้าคลัง
                                                </button>
                                                <button class="dropdown-item text-danger" onclick="confirmDeleteWithAssessments(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['name'], ENT_QUOTES); ?>', <?php echo $period['assessment_count']; ?>)">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                                        <polyline points="3 6 5 6 21 6"/>
                                                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                    </svg>
                                                    ลบการประเมินถาวร
                                                </button>
                                            <?php else: ?>
                                                <!-- No assessments - can delete permanently -->
                                                <button class="dropdown-item text-danger" onclick="confirmDelete(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['name'], ENT_QUOTES); ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                                        <polyline points="3 6 5 6 21 6"/>
                                                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                                                    </svg>
                                                    ลบถาวร
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create/Edit Modal -->
    <div class="modal-overlay" id="periodModalOverlay">
        <div class="modal modal-lg" style="max-width: 800px;">
            <div class="modal-header flex-between">
                <h5 class="modal-title" id="modalTitle">สร้างรอบการประเมิน</h5>
                <button type="button" class="btn-icon" style="background:none; border:none;" onclick="closeModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="periodId">
                    
                    <!-- Section 1: General Info -->
                    <div class="mb-4">
                        <h6 style="font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; margin-bottom: 1rem;">ข้อมูลทั่วไป</h6>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                            <div style="grid-column: span 1;">
                                <div class="form-group">
                                    <label class="form-label required">ปี (พ.ศ.)</label>
                                    <input type="number" name="year" id="periodYear" class="form-input" required value="<?php echo date('Y') + 543; ?>">
                                </div>
                            </div>
                            <div style="grid-column: span 3;">
                                <div class="form-group">
                                    <label class="form-label required">ชื่อรอบการประเมิน</label>
                                    <input type="text" name="name" id="periodName" class="form-input" required placeholder="เช่น HICM Award 2025">
                                </div>
                            </div>
                            <div style="grid-column: span 4;">
                                <div class="form-group mb-0">
                                    <label class="form-label">คำอธิบาย</label>
                                    <textarea name="description" id="periodDesc" class="form-textarea" rows="2" placeholder="รายละเอียดเพิ่มเติมเกี่ยวกับรอบการประเมินนี้"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-top: 1px solid var(--gray-100); margin: 1.5rem 0;"></div>

                    <!-- Section 2: Timeline -->
                    <div>
                        <h6 style="font-size: 0.75rem; font-weight: 700; color: var(--gray-500); text-transform: uppercase; margin-bottom: 1rem;">กำหนดการโครงการ</h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                            <div class="form-group mb-0">
                                <label class="form-label required">ระยะเวลาโครงการ</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                    <div>
                                        <span class="text-xs text-gray-500 mb-1 block">วันที่เริ่มต้น</span>
                                        <input type="date" name="start_date" id="startDate" class="form-input" required>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500 mb-1 block">วันที่สิ้นสุด</span>
                                        <input type="date" name="end_date" id="endDate" class="form-input" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label required">ระยะเวลาการตรวจสอบ</label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                    <div>
                                        <span class="text-xs text-gray-500 mb-1 block">วันเริ่มตรวจสอบ</span>
                                        <input type="date" name="evaluation_start_date" id="evalStartDate" class="form-input" required>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500 mb-1 block">วันสิ้นสุดตรวจสอบ</span>
                                        <input type="date" name="evaluation_end_date" id="evalEndDate" class="form-input" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label required" style="color: var(--danger);">วันปิดรับส่งแบบประเมิน</label>
                                <input type="date" name="submission_deadline" id="deadlineDate" class="form-input" required>
                                <p class="form-hint" style="color: var(--danger);">วันสุดท้ายที่สถานประกอบการสามารถส่งข้อมูลได้</p>
                            </div>
                            <div class="form-group mb-0">
                                <label class="form-label required" style="color: var(--success);">วันประกาศผล</label>
                                <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.5rem; align-items: end;">
                                    <div>
                                        <span class="text-xs text-gray-500 mb-1 block">วันที่</span>
                                        <input type="date" name="announcement_date" id="announceDate" class="form-input" required>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500 mb-1 block">เวลา</span>
                                        <input type="time" name="announcement_time" id="announceTime" class="form-input" value="09:00" required style="min-width: 110px;">
                                    </div>
                                </div>
                                <p class="form-hint" style="color: var(--success);">ระบบจะประกาศผลอัตโนมัติเมื่อถึงวัน-เวลาที่กำหนด</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Overlap Warning -->
                    <div id="overlapWarningBox" style="display: none; margin-top: 1rem; padding: 0.875rem 1rem; background: #fff8e1; border: 1px solid #ffe082; border-radius: 0.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 0.625rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <div>
                                <div style="font-weight: 600; color: #92400e; font-size: 0.8125rem; margin-bottom: 0.25rem;" id="overlapWarningTitle">คำเตือน</div>
                                <div style="color: #92400e; font-size: 0.8125rem; line-height: 1.5;" id="overlapWarningText"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Auto-Open Warning -->
                    <div id="autoOpenWarningBox" style="display: none; margin-top: 0.75rem; padding: 0.875rem 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 0.5rem;">
                        <div style="display: flex; align-items: flex-start; gap: 0.625rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <div>
                                <div style="font-weight: 600; color: #991b1b; font-size: 0.8125rem; margin-bottom: 0.25rem;">⚡ รอบจะเปิดอัตโนมัติทันที</div>
                                <div style="color: #991b1b; font-size: 0.8125rem; line-height: 1.5;" id="autoOpenWarningText"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="periodSubmitBtn">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModalOverlay">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <div style="width: 64px; height: 64px; background-color: var(--danger-light); color: var(--danger); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                        <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">ยืนยันการลบถาวร</h3>
                <p style="color: var(--gray-500); margin-bottom: 0.5rem;">คุณต้องการลบรอบการประเมิน</p>
                <p style="font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;" id="deletePeriodName"></p>
                <p style="color: var(--danger); font-size: 0.875rem; margin-bottom: 2rem;">การกระทำนี้ไม่สามารถย้อนกลับได้</p>
                <form method="POST" style="display: flex; justify-content: center; gap: 1rem;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ยืนยันลบถาวร</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Archive Modal -->
    <div class="modal-overlay" id="archiveModalOverlay">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <div style="width: 64px; height: 64px; background-color: var(--warning-light); color: var(--warning); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="21 8 21 21 3 21 3 8"/>
                        <rect x="1" y="3" width="22" height="5"/>
                        <line x1="10" y1="12" x2="14" y2="12"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">เก็บเข้าคลัง</h3>
                <p style="color: var(--gray-500); margin-bottom: 0.5rem;">คุณต้องการเก็บรอบการประเมิน</p>
                <p style="font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;" id="archivePeriodName"></p>
                <div style="background: var(--warning-light); border-radius: var(--radius-md); padding: 0.75rem; margin-bottom: 1.5rem;">
                    <p style="color: var(--warning-dark); font-size: 0.875rem; margin: 0;">
                        <strong>หมายเหตุ:</strong> รอบนี้มีการประเมิน <span id="archiveAssessmentCount" style="font-weight: 700;"></span> รายการ<br>
                        ข้อมูลจะถูกเก็บไว้และสามารถกู้คืนได้ภายหลัง
                    </p>
                </div>
                <form method="POST" style="display: flex; justify-content: center; gap: 1rem;">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="id" id="archiveId">
                    <button type="button" class="btn btn-secondary" onclick="closeArchiveModal()">ยกเลิก</button>
                    <button type="submit" class="btn" style="background: var(--warning); color: white;">เก็บเข้าคลัง</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Assessments Permanently Modal -->
    <div class="modal-overlay" id="deleteWithAssessmentsModalOverlay">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <div style="width: 64px; height: 64px; background-color: var(--danger-light); color: var(--danger); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                        <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">ลบการประเมินถาวร</h3>
                <p style="color: var(--gray-500); margin-bottom: 0.5rem;">คุณต้องการลบรอบการประเมิน</p>
                <p style="font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem;" id="deleteWithAssessmentsPeriodName"></p>
                <div style="background: var(--danger-light); border-radius: var(--radius-md); padding: 0.75rem; margin-bottom: 1.5rem;">
                    <p style="color: var(--danger-dark); font-size: 0.875rem; margin: 0;">
                        <strong>⚠️ คำเตือน:</strong> การดำเนินการนี้จะลบการประเมิน <span id="deleteWithAssessmentsCount" style="font-weight: 700;"></span> รายการ<br>
                        ข้อมูลทั้งหมดรวมถึงคะแนนและไฟล์แนบจะถูกลบถาวรและไม่สามารถกู้คืนได้
                    </p>
                </div>
                <form method="POST" style="display: flex; justify-content: center; gap: 1rem;">
                    <input type="hidden" name="action" value="delete_with_assessments">
                    <input type="hidden" name="id" id="deleteWithAssessmentsId">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteWithAssessmentsModal()">ยกเลิก</button>
                    <button type="submit" class="btn" style="background: var(--danger); color: white;">ลบถาวร</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Restore Modal -->
    <div class="modal-overlay" id="restoreModalOverlay">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <div style="width: 64px; height: 64px; background-color: var(--success-light); color: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10"/>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">กู้คืนรอบการประเมิน</h3>
                <p style="color: var(--gray-500); margin-bottom: 0.5rem;">คุณต้องการกู้คืนรอบการประเมิน</p>
                <p style="font-weight: 600; color: var(--gray-800); margin-bottom: 1.5rem;" id="restorePeriodName"></p>
                <form method="POST" style="display: flex; justify-content: center; gap: 1rem;">
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="id" id="restoreId">
                    <button type="button" class="btn btn-secondary" onclick="closeRestoreModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-success">กู้คืน</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Status Confirmation Modal -->
    <div class="modal-overlay" id="statusModalOverlay">
        <div class="modal" style="max-width: 450px;">
            <div class="modal-body" style="text-align: center; padding: 2rem;">
                <div id="statusIcon" style="width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">เปลี่ยนสถานะ</h3>
                <p style="color: var(--gray-500); margin-bottom: 1rem;">
                    คุณต้องการเปลี่ยนสถานะเป็น <span id="statusTargetName" style="font-weight: 700; color: var(--gray-900);"></span> ใช่หรือไม่?
                </p>
                <div id="autoCloseWarning" style="display: none; background: #fef3c7; border: 1px solid #f59e0b; border-radius: var(--radius-md); padding: 0.75rem; margin-bottom: 1.5rem; text-align: left;">
                    <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" style="flex-shrink:0; margin-top:2px;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <div>
                            <p style="font-size: 0.8rem; font-weight: 600; color: #92400e; margin: 0 0 0.25rem 0;">เปิดได้ทีละรอบเดียว</p>
                            <p id="autoCloseText" style="font-size: 0.78rem; color: #92400e; margin: 0;"></p>
                        </div>
                    </div>
                </div>
                <form method="POST" style="display: flex; justify-content: center; gap: 1rem;">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id" id="statusId">
                    <input type="hidden" name="status" id="statusValue">
                    <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="statusConfirmBtn">ยืนยัน</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Extend/Reschedule Modal -->
    <div class="modal-overlay" id="extendModalOverlay">
        <div class="modal modal-lg" style="max-width: 600px;">
            <div class="modal-header flex-between" style="background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; border-radius: var(--radius-xl) var(--radius-xl) 0 0;">
                <h5 class="modal-title" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    ต่อเวลา / ขยายโครงการ
                </h5>
                <button type="button" class="btn-icon" style="background:none; border:none; color:white;" onclick="closeExtendModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="extendPeriodId">
                    <input type="hidden" name="year" id="extendYear">
                    <input type="hidden" name="name" id="extendName">
                    <input type="hidden" name="description" id="extendDesc">
                    
                    <div style="background: var(--info-light, #e0f2fe); border: 1px solid var(--info, #0ea5e9); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--info, #0ea5e9)" stroke-width="2" style="flex-shrink:0;">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <div>
                            <strong id="extendPeriodLabel" style="color: var(--gray-800);"></strong>
                            <p style="margin: 0; font-size: 0.8rem; color: var(--gray-500);">ปรับวันที่กำหนดการต่างๆ เพื่อต่อเวลาหรือขยายโครงการ สถานะปัจจุบันจะไม่เปลี่ยนแปลง</p>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group mb-0">
                            <label class="form-label required">วันเริ่มต้นโครงการ</label>
                            <input type="date" name="start_date" id="extendStartDate" class="form-input" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required">วันสิ้นสุดโครงการ</label>
                            <input type="date" name="end_date" id="extendEndDate" class="form-input" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required" style="color: var(--danger);">วันปิดรับส่งแบบประเมิน</label>
                            <input type="date" name="submission_deadline" id="extendDeadline" class="form-input" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required">วันเริ่มตรวจสอบ</label>
                            <input type="date" name="evaluation_start_date" id="extendEvalStart" class="form-input" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required">วันสิ้นสุดตรวจสอบ</label>
                            <input type="date" name="evaluation_end_date" id="extendEvalEnd" class="form-input" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required" style="color: var(--success);">วันประกาศผล</label>
                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 0.5rem;">
                                <input type="date" name="announcement_date" id="extendAnnounceDate" class="form-input" required>
                                <input type="time" name="announcement_time" id="extendAnnounceTime" class="form-input" value="09:00" required style="min-width: 110px;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeExtendModal()">ยกเลิก</button>
                    <button type="submit" class="btn" style="background: var(--info, #0ea5e9); color: white;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        บันทึกกำหนดการใหม่
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Custom Confirm/Alert Modal -->
    <div class="modal-overlay" id="customAlertOverlay" style="z-index: 10000;">
        <div class="modal" style="max-width: 440px;">
            <div class="modal-body" style="text-align: center; padding: 2rem 2rem 1.5rem;">
                <!-- Icon -->
                <div id="customAlertIcon" style="width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;"></div>
                <!-- Title -->
                <h3 id="customAlertTitle" style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.625rem; color: var(--gray-800);"></h3>
                <!-- Body -->
                <div id="customAlertBody" style="margin-bottom: 1.5rem;"></div>
                <!-- Buttons -->
                <div id="customAlertButtons" style="display: flex; justify-content: center; gap: 0.75rem;"></div>
            </div>
        </div>
    </div>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        // ── Pro Custom Alert / Confirm ──
        function showCustomAlert(options) {
            return new Promise(function(resolve) {
                const overlay = document.getElementById('customAlertOverlay');
                const iconEl = document.getElementById('customAlertIcon');
                const titleEl = document.getElementById('customAlertTitle');
                const bodyEl = document.getElementById('customAlertBody');
                const btnEl = document.getElementById('customAlertButtons');

                // Type configs
                const types = {
                    success: {
                        bg: '#ecfdf5', color: '#10b981',
                        icon: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                    },
                    warning: {
                        bg: '#fff8e1', color: '#f59e0b',
                        icon: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
                    },
                    error: {
                        bg: 'var(--danger-light, #fef2f2)', color: 'var(--danger, #ef4444)',
                        icon: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
                    },
                    info: {
                        bg: 'var(--info-light, #e0f2fe)', color: 'var(--info, #0ea5e9)',
                        icon: '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
                    }
                };
                const t = types[options.type] || types.warning;

                iconEl.style.backgroundColor = t.bg;
                iconEl.style.color = t.color;
                iconEl.innerHTML = t.icon;
                titleEl.textContent = options.title || 'คำเตือน';

                // Build body
                let bodyHTML = '';
                if (options.messages && options.messages.length > 0) {
                    bodyHTML += '<div style="text-align: left; margin: 0 auto; max-width: 360px;">';
                    options.messages.forEach(function(msg) {
                        bodyHTML += '<div style="display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.5rem 0.75rem; margin-bottom: 0.5rem; background: ' + (msg.bg || '#f9fafb') + '; border-radius: 0.375rem; font-size: 0.8125rem; color: ' + (msg.color || 'var(--gray-700)') + ';">';
                        bodyHTML += '<span style="flex-shrink: 0; font-size: 1rem; line-height: 1.25;">' + (msg.icon || '•') + '</span>';
                        bodyHTML += '<span style="line-height: 1.4;">' + msg.text + '</span>';
                        bodyHTML += '</div>';
                    });
                    bodyHTML += '</div>';
                }
                if (options.description) {
                    bodyHTML += '<p style="color: var(--gray-500); font-size: 0.8125rem; margin-top: 0.75rem;">' + options.description + '</p>';
                }
                bodyEl.innerHTML = bodyHTML;

                // Build buttons
                let btnHTML = '';
                if (options.mode === 'confirm') {
                    btnHTML += '<button type="button" class="btn btn-secondary" id="customAlertCancel" style="min-width: 100px;">ยกเลิก</button>';
                    btnHTML += '<button type="button" class="btn" id="customAlertConfirm" style="min-width: 100px; background: ' + (options.confirmColor || 'var(--primary-500)') + '; color: white;">' + (options.confirmText || 'ดำเนินการต่อ') + '</button>';
                } else {
                    btnHTML += '<button type="button" class="btn btn-primary" id="customAlertOk" style="min-width: 120px;">ตกลง</button>';
                }
                btnEl.innerHTML = btnHTML;

                // Events
                function close(result) {
                    overlay.classList.remove('active');
                    resolve(result);
                }

                if (options.mode === 'confirm') {
                    document.getElementById('customAlertCancel').addEventListener('click', function() { close(false); });
                    document.getElementById('customAlertConfirm').addEventListener('click', function() { close(true); });
                } else {
                    document.getElementById('customAlertOk').addEventListener('click', function() { close(true); });
                }

                // ESC key
                function onKeyDown(e) {
                    if (e.key === 'Escape') {
                        document.removeEventListener('keydown', onKeyDown);
                        close(false);
                    }
                }
                document.addEventListener('keydown', onKeyDown);

                overlay.classList.add('active');
            });
        }
        function openModal(mode, data = null) {
            const overlay = document.getElementById('periodModalOverlay');
            
            // Populate form
            if (mode === 'create') {
                document.getElementById('modalTitle').innerText = 'สร้างรอบการประเมิน';
                document.getElementById('formAction').value = 'create';
                document.getElementById('periodId').value = '';
                document.getElementById('periodYear').value = new Date().getFullYear() + 543;
                document.getElementById('periodName').value = '';
                document.getElementById('periodDesc').value = '';
                document.getElementById('startDate').value = '';
                document.getElementById('endDate').value = '';
                document.getElementById('deadlineDate').value = '';
                document.getElementById('evalStartDate').value = '';
                document.getElementById('evalEndDate').value = '';
                document.getElementById('announceDate').value = '';
                document.getElementById('announceTime').value = '09:00';
            } else {
                document.getElementById('modalTitle').innerText = 'แก้ไขรอบการประเมิน';
                document.getElementById('formAction').value = 'update';
                document.getElementById('periodId').value = data.id;
                document.getElementById('periodYear').value = data.year;
                document.getElementById('periodName').value = data.name;
                document.getElementById('periodDesc').value = data.description;
                document.getElementById('startDate').value = data.start_date;
                document.getElementById('endDate').value = data.end_date;
                document.getElementById('deadlineDate').value = data.submission_deadline;
                document.getElementById('evalStartDate').value = data.evaluation_start_date;
                document.getElementById('evalEndDate').value = data.evaluation_end_date;
                // Split DATETIME into date + time
                if (data.announcement_date && data.announcement_date.length > 10) {
                    document.getElementById('announceDate').value = data.announcement_date.substring(0, 10);
                    document.getElementById('announceTime').value = data.announcement_date.substring(11, 16);
                } else {
                    document.getElementById('announceDate').value = data.announcement_date || '';
                    document.getElementById('announceTime').value = '09:00';
                }
            }
            
            // Check for overlaps after populating
            setTimeout(checkDateOverlap, 0);
            
            // Show modal
            overlay.classList.add('active');
        }

        function closeModal() {
            document.getElementById('periodModalOverlay').classList.remove('active');
        }

        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deletePeriodName').innerText = '"' + name + '"';
            document.getElementById('deleteModalOverlay').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.remove('active');
        }
        
        function confirmArchive(id, name, count) {
            document.getElementById('archiveId').value = id;
            document.getElementById('archivePeriodName').innerText = '"' + name + '"';
            document.getElementById('archiveAssessmentCount').innerText = count;
            document.getElementById('archiveModalOverlay').classList.add('active');
        }
        
        function closeArchiveModal() {
            document.getElementById('archiveModalOverlay').classList.remove('active');
        }
        
        function confirmDeleteWithAssessments(id, name, count) {
            document.getElementById('deleteWithAssessmentsId').value = id;
            document.getElementById('deleteWithAssessmentsPeriodName').innerText = '"' + name + '"';
            document.getElementById('deleteWithAssessmentsCount').innerText = count;
            document.getElementById('deleteWithAssessmentsModalOverlay').classList.add('active');
        }
        
        function closeDeleteWithAssessmentsModal() {
            document.getElementById('deleteWithAssessmentsModalOverlay').classList.remove('active');
        }
        
        function confirmRestore(id, name) {
            document.getElementById('restoreId').value = id;
            document.getElementById('restorePeriodName').innerText = '"' + name + '"';
            document.getElementById('restoreModalOverlay').classList.add('active');
        }
        
        function closeRestoreModal() {
            document.getElementById('restoreModalOverlay').classList.remove('active');
        }
        
        // Currently open periods (from PHP)
        const currentlyOpenPeriods = <?php echo $currentlyOpenPeriodsJson; ?>;
        
        // All active periods for overlap checking
        const allPeriodsForOverlap = <?php echo $allPeriodsOverlapJson; ?>;
        
        // ── Overlap detection for create/edit form ──
        function checkDateOverlap() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const formAction = document.getElementById('formAction').value;
            const editingId = document.getElementById('periodId').value;
            const overlapBox = document.getElementById('overlapWarningBox');
            const overlapText = document.getElementById('overlapWarningText');
            const autoOpenBox = document.getElementById('autoOpenWarningBox');
            const autoOpenText = document.getElementById('autoOpenWarningText');
            
            // Reset
            overlapBox.style.display = 'none';
            autoOpenBox.style.display = 'none';
            
            if (!startDate || !endDate) return;
            
            // 1. Check date range overlap with existing periods
            const overlapping = allPeriodsForOverlap.filter(p => {
                // Skip self when editing
                if (formAction === 'update' && p.id == editingId) return false;
                if (!p.start_date || !p.end_date) return false;
                // Date ranges overlap if: start1 <= end2 AND end1 >= start2
                return startDate <= p.end_date && endDate >= p.start_date;
            });
            
            if (overlapping.length > 0) {
                const statusLabels = { draft: 'แบบร่าง', open: 'กำลังดำเนินการ', evaluating: 'กำลังประเมิน', closed: 'ปิดรับแบบประเมิน' };
                const names = overlapping.map(p => {
                    const sLabel = statusLabels[p.status] || p.status;
                    return '• ' + p.name + ' (' + p.year + ') — ' + sLabel + ' [' + p.start_date + ' ถึง ' + p.end_date + ']';
                });
                overlapText.innerHTML = 'ช่วงเวลาซ้อนทับกับรอบอื่น:<br>' + names.join('<br>');
                overlapBox.style.display = 'block';
            }
            
            // 2. Check if start_date <= today → will auto-open immediately
            const today = new Date().toISOString().split('T')[0];
            if (startDate <= today) {
                const openOnes = currentlyOpenPeriods.filter(p => {
                    if (formAction === 'update' && p.id == editingId) return false;
                    return true;
                });
                
                let msg = 'วันเริ่มต้นอยู่ในวันนี้หรือก่อนหน้า — รอบนี้จะถูกเปลี่ยนสถานะเป็น "กำลังดำเนินการ" โดยอัตโนมัติ';
                if (openOnes.length > 0) {
                    const names = openOnes.map(p => '"' + p.name + ' (' + p.year + ')"').join(', ');
                    msg += '<br><strong>รอบที่เปิดอยู่: ' + names + ' จะถูกปิดโดยอัตโนมัติ</strong> (ระบบเปิดได้ทีละรอบเดียว)';
                }
                autoOpenText.innerHTML = msg;
                autoOpenBox.style.display = 'block';
            }
        }
        
        // Attach date change listeners
        ['startDate', 'endDate'].forEach(function(id) {
            document.getElementById(id).addEventListener('change', checkDateOverlap);
        });
        
        // Form submit validation — confirm if warnings exist
        (function() {
            const form = document.getElementById('periodModalOverlay').querySelector('form');
            let pendingSubmit = false;
            
            form.addEventListener('submit', function(e) {
                if (pendingSubmit) { pendingSubmit = false; return; }
                
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                
                // Validate date order
                if (startDate && endDate && startDate > endDate) {
                    e.preventDefault();
                    showCustomAlert({
                        type: 'error',
                        title: 'วันที่ไม่ถูกต้อง',
                        messages: [{ icon: '❌', text: 'วันเริ่มต้นต้องอยู่ก่อนวันสิ้นสุด', bg: '#fef2f2', color: '#991b1b' }],
                        description: 'กรุณาตรวจสอบวันที่เริ่มต้นและสิ้นสุดโครงการ'
                    });
                    return;
                }
                
                const evalStart = document.getElementById('evalStartDate').value;
                const evalEnd = document.getElementById('evalEndDate').value;
                if (evalStart && evalEnd && evalStart > evalEnd) {
                    e.preventDefault();
                    showCustomAlert({
                        type: 'error',
                        title: 'วันที่ไม่ถูกต้อง',
                        messages: [{ icon: '❌', text: 'วันเริ่มตรวจสอบต้องอยู่ก่อนวันสิ้นสุดตรวจสอบ', bg: '#fef2f2', color: '#991b1b' }],
                        description: 'กรุณาตรวจสอบวันที่เริ่มต้นและสิ้นสุดการตรวจสอบ'
                    });
                    return;
                }
                
                // Check if auto-open warning or overlap warning is showing → confirm
                const autoOpenBox = document.getElementById('autoOpenWarningBox');
                const overlapBox = document.getElementById('overlapWarningBox');
                const hasAutoOpen = autoOpenBox.style.display !== 'none';
                const hasOverlap = overlapBox.style.display !== 'none';
                
                if (hasAutoOpen || hasOverlap) {
                    e.preventDefault();
                    
                    const msgs = [];
                    if (hasAutoOpen) {
                        msgs.push({ icon: '⚡', text: 'รอบนี้จะถูกเปิดอัตโนมัติทันที เนื่องจากวันเริ่มต้นถึงแล้ว', bg: '#fef2f2', color: '#991b1b' });
                        if (currentlyOpenPeriods.length > 0) {
                            const names = currentlyOpenPeriods.map(function(p) { return p.name + ' (' + p.year + ')'; }).join(', ');
                            msgs.push({ icon: '🔄', text: 'รอบที่เปิดอยู่: <strong>' + names + '</strong> จะถูกปิดโดยอัตโนมัติ', bg: '#fef2f2', color: '#991b1b' });
                        }
                    }
                    if (hasOverlap) {
                        msgs.push({ icon: '⚠️', text: 'ช่วงเวลาซ้อนทับกับรอบการประเมินอื่น', bg: '#fff8e1', color: '#92400e' });
                    }
                    
                    showCustomAlert({
                        type: 'warning',
                        mode: 'confirm',
                        title: 'ยืนยันการบันทึก',
                        messages: msgs,
                        description: 'ต้องการดำเนินการต่อหรือไม่?',
                        confirmText: 'ยืนยัน บันทึก',
                        confirmColor: 'var(--warning, #f59e0b)'
                    }).then(function(confirmed) {
                        if (confirmed) {
                            pendingSubmit = true;
                            form.requestSubmit();
                        }
                    });
                    return;
                }
            });
        })();
        
        function updateStatus(id, status) {
            const statusMap = {
                'open': { label: 'กำลังดำเนินการ', color: 'var(--success)', bg: 'var(--success-light)' },
                'closed': { label: 'ปิดรับแบบประเมิน', color: 'var(--danger)', bg: 'var(--danger-light)' },
                'evaluating': { label: 'กำลังดำเนินการ', color: 'var(--success)', bg: 'var(--success-light)' },
                'completed': { label: 'เสร็จสิ้น', color: 'var(--primary-500)', bg: 'var(--primary-50)' }
            };

            const config = statusMap[status] || { label: status, color: 'var(--primary-500)', bg: 'var(--primary-50)' };
            
            // Update Modal UI
            document.getElementById('statusTargetName').innerText = config.label;
            document.getElementById('statusTargetName').style.color = config.color;
            
            const icon = document.getElementById('statusIcon');
            icon.style.backgroundColor = config.bg;
            icon.style.color = config.color;

            // Auto-close warning: show when opening a period while another is already open
            const warningEl = document.getElementById('autoCloseWarning');
            if (status === 'open') {
                const otherOpen = currentlyOpenPeriods.filter(p => p.id != id);
                if (otherOpen.length > 0) {
                    const names = otherOpen.map(p => '"' + p.name + ' (' + p.year + ')"').join(', ');
                    document.getElementById('autoCloseText').innerText = 
                        'รอบที่เปิดอยู่: ' + names + ' จะถูกปิดโดยอัตโนมัติ เนื่องจากระบบเปิดได้ทีละรอบเดียว';
                    warningEl.style.display = 'block';
                } else {
                    warningEl.style.display = 'none';
                }
            } else {
                warningEl.style.display = 'none';
            }

            // Update Form Data
            document.getElementById('statusId').value = id;
            document.getElementById('statusValue').value = status;
            
            // Show Modal
            document.getElementById('statusModalOverlay').classList.add('active');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModalOverlay').classList.remove('active');
        }
        
        function openExtendModal(data) {
            document.getElementById('extendPeriodId').value = data.id;
            document.getElementById('extendYear').value = data.year;
            document.getElementById('extendName').value = data.name;
            document.getElementById('extendDesc').value = data.description;
            document.getElementById('extendPeriodLabel').innerText = data.name + ' (' + data.year + ')';
            document.getElementById('extendStartDate').value = data.start_date;
            document.getElementById('extendEndDate').value = data.end_date;
            document.getElementById('extendDeadline').value = data.submission_deadline;
            document.getElementById('extendEvalStart').value = data.evaluation_start_date;
            document.getElementById('extendEvalEnd').value = data.evaluation_end_date;
            // Split DATETIME into date + time
            if (data.announcement_date && data.announcement_date.length > 10) {
                document.getElementById('extendAnnounceDate').value = data.announcement_date.substring(0, 10);
                document.getElementById('extendAnnounceTime').value = data.announcement_date.substring(11, 16);
            } else {
                document.getElementById('extendAnnounceDate').value = data.announcement_date || '';
                document.getElementById('extendAnnounceTime').value = '09:00';
            }
            document.getElementById('extendModalOverlay').classList.add('active');
        }
        
        function closeExtendModal() {
            document.getElementById('extendModalOverlay').classList.remove('active');
        }

        function toggleDropdown(id) {
            const dropdown = document.getElementById('dropdown-' + id);
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            
            // Close others
            allDropdowns.forEach(d => {
                if (d !== dropdown) d.style.display = 'none';
            });
            
            // Toggle current
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown-container')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(dropdown => dropdown.style.display = 'none');
            }
        });

        // =============================================
        // ANNOUNCEMENT COUNTDOWN TIMER
        // =============================================
        (function() {
            const countdowns = document.querySelectorAll('.announce-countdown[data-countdown-target]');
            if (countdowns.length === 0) return;

            let reloadScheduled = false;

            function updateCountdowns() {
                const now = new Date().getTime();
                countdowns.forEach(cd => {
                    const widget = cd.closest('.announce-widget');
                    if (widget && widget.classList.contains('announced-active')) return;

                    const target = new Date(cd.getAttribute('data-countdown-target')).getTime();
                    const diff = target - now;

                    if (diff <= 0) {
                        const daysEl = cd.querySelector('[data-cd-days]');
                        const hoursEl = cd.querySelector('[data-cd-hours]');
                        const minsEl = cd.querySelector('[data-cd-mins]');
                        const secsEl = cd.querySelector('[data-cd-secs]');
                        if (daysEl) daysEl.textContent = '0';
                        if (hoursEl) hoursEl.textContent = '00';
                        if (minsEl) minsEl.textContent = '00';
                        if (secsEl) secsEl.textContent = '00';

                        // Countdown reached zero — schedule page reload so server auto-announce kicks in
                        if (!reloadScheduled) {
                            reloadScheduled = true;
                            const RELOAD_SECS = 120;

                            if (widget) {
                                widget.classList.remove('announce-upcoming', 'announce-waiting');
                                widget.classList.add('announce-today');
                                const headerLabel = widget.querySelector('.announce-header > span');
                                if (headerLabel && !headerLabel.textContent.includes('ประกาศผลแล้ว')) {
                                    headerLabel.innerHTML = '🎉 ถึงเวลาประกาศผลแล้ว! <span class="announce-auto-badge auto-today">⚡ AUTO</span>';
                                }

                                // Build animated reload progress UI
                                const body = widget.querySelector('.announce-body');
                                if (body) {
                                    // Keep existing date row, replace rest
                                    const dateRow = body.querySelector('.announce-date-row');
                                    const dateHTML = dateRow ? dateRow.outerHTML : '';
                                    body.innerHTML = dateHTML + `
                                    <div class="reload-progress-container">
                                        <div class="reload-progress-top">
                                            <div class="reload-circle-wrap">
                                                <svg class="reload-circle-svg" viewBox="0 0 56 56">
                                                    <circle class="reload-circle-bg" cx="28" cy="28" r="24"/>
                                                    <circle class="reload-circle-fg" id="reloadCircle" cx="28" cy="28" r="24"/>
                                                </svg>
                                                <div class="reload-circle-text">
                                                    <span class="reload-secs" id="reloadSecsText">${RELOAD_SECS}</span>
                                                    <span class="reload-unit">วินาที</span>
                                                </div>
                                            </div>
                                            <div class="reload-info">
                                                <div class="reload-info-title">
                                                    <span class="reload-dot"></span>
                                                    กำลังเตรียมประกาศผลอัตโนมัติ
                                                </div>
                                                <div class="reload-info-sub">หน้าจะรีเฟรชเพื่อเปิดใช้งาน Leaderboard</div>
                                            </div>
                                        </div>
                                        <div class="reload-bar-wrap">
                                            <div class="reload-bar-header">
                                                <span class="reload-bar-label">⏳ ความคืบหน้า</span>
                                                <span class="reload-bar-pct" id="reloadPctText">0%</span>
                                            </div>
                                            <div class="reload-bar">
                                                <div class="reload-bar-fill" id="reloadBarFill"></div>
                                            </div>
                                            <div class="reload-steps">
                                                <span class="reload-step active" id="reloadStep1">⏳ รอ</span>
                                                <span class="reload-step" id="reloadStep2">🔄 รีเฟรช</span>
                                                <span class="reload-step" id="reloadStep3">🏆 ประกาศผล</span>
                                            </div>
                                        </div>
                                    </div>
                                    `;
                                }
                            }

                            // Animate reload progress
                            const circumference = 2 * Math.PI * 24; // r=24
                            let secsLeft = RELOAD_SECS;
                            const reloadStartTime = Date.now();

                            const reloadInterval = setInterval(function() {
                                const elapsed = Math.floor((Date.now() - reloadStartTime) / 1000);
                                secsLeft = Math.max(0, RELOAD_SECS - elapsed);
                                const pct = Math.min(100, (elapsed / RELOAD_SECS) * 100);

                                // Update circular progress
                                const circle = document.getElementById('reloadCircle');
                                if (circle) circle.style.strokeDashoffset = circumference - (circumference * pct / 100);

                                // Update seconds text
                                const secsText = document.getElementById('reloadSecsText');
                                if (secsText) secsText.textContent = secsLeft;

                                // Update bar
                                const barFill = document.getElementById('reloadBarFill');
                                if (barFill) barFill.style.width = pct + '%';

                                // Update percentage text
                                const pctText = document.getElementById('reloadPctText');
                                if (pctText) pctText.textContent = Math.round(pct) + '%';

                                // Update steps
                                const step2 = document.getElementById('reloadStep2');
                                const step3 = document.getElementById('reloadStep3');
                                if (pct >= 50 && step2) step2.classList.add('active');
                                if (pct >= 90 && step3) step3.classList.add('active');

                                if (secsLeft <= 0) {
                                    clearInterval(reloadInterval);
                                    if (secsText) secsText.textContent = '✓';
                                    if (pctText) pctText.textContent = '100%';
                                    const infoTitle = widget ? widget.querySelector('.reload-info-title') : null;
                                    if (infoTitle) infoTitle.innerHTML = '<span class="reload-dot" style="background:#059669;"></span> กำลังรีเฟรชหน้า...';
                                }
                            }, 1000);

                            setTimeout(function() { window.location.reload(); }, RELOAD_SECS * 1000);
                        }
                        return;
                    }

                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const secs = Math.floor((diff % (1000 * 60)) / 1000);

                    const daysEl = cd.querySelector('[data-cd-days]');
                    const hoursEl = cd.querySelector('[data-cd-hours]');
                    const minsEl = cd.querySelector('[data-cd-mins]');
                    const secsEl = cd.querySelector('[data-cd-secs]');

                    if (daysEl) daysEl.textContent = days;
                    if (hoursEl) hoursEl.textContent = String(hours).padStart(2, '0');
                    if (minsEl) minsEl.textContent = String(mins).padStart(2, '0');
                    if (secsEl) secsEl.textContent = String(secs).padStart(2, '0');
                });
            }

            updateCountdowns();
            setInterval(updateCountdowns, 1000);
        })();
    </script>
</body>
</html>
