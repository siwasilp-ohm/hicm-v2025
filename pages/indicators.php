<?php
/**
 * HICM V2025 Assessment System - Indicators Management
 * Legacy Premium "Wow" Version - Integrated with Global Template
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/indicators.php';

requireAuth();

// Only Admin can access
if (!hasRole(ROLE_ADMIN)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- PILLAR ACTIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_pillar') {
        $result = createPillar([
            'code' => sanitizeInput($_POST['code']),
            'name_th' => sanitizeInput($_POST['name_th']),
            'name_en' => sanitizeInput($_POST['name_en']),
            'description' => sanitizeInput($_POST['description']),
            'weight' => intval($_POST['weight']),
            'color' => sanitizeInput($_POST['color']),
            'icon' => sanitizeInput($_POST['icon']),
            'display_order' => intval($_POST['display_order'])
        ]);
        if ($result['success']) setFlashMessage('เพิ่ม Pillar สำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_pillar') {
        $result = updatePillar(intval($_POST['id']), [
            'code' => sanitizeInput($_POST['code']),
            'name_th' => sanitizeInput($_POST['name_th']),
            'name_en' => sanitizeInput($_POST['name_en']),
            'description' => sanitizeInput($_POST['description']),
            'weight' => intval($_POST['weight']),
            'color' => sanitizeInput($_POST['color']),
            'icon' => sanitizeInput($_POST['icon']),
            'display_order' => intval($_POST['display_order'])
        ]);
        if ($result['success']) setFlashMessage('อัปเดต Pillar สำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_pillar') {
        $result = deletePillar(intval($_POST['id']));
        if ($result['success']) setFlashMessage('ลบ Pillar สำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php');
    }

    // --- INDICATOR ACTIONS ---
    if (isset($_POST['action']) && $_POST['action'] === 'create_indicator') {
        $result = createIndicator([
            'pillar_id' => intval($_POST['pillar_id']),
            'code' => sanitizeInput($_POST['code']),
            'name_th' => sanitizeInput($_POST['name_th']),
            'name_en' => sanitizeInput($_POST['name_en']),
            'description' => sanitizeInput($_POST['description']),
            'criteria_0' => sanitizeInput($_POST['criteria_0']),
            'criteria_025' => sanitizeInput($_POST['criteria_025']),
            'criteria_05' => sanitizeInput($_POST['criteria_05']),
            'criteria_075' => sanitizeInput($_POST['criteria_075']),
            'criteria_1' => sanitizeInput($_POST['criteria_1']),
            'criteria_na' => sanitizeInput($_POST['criteria_na']),
            'allow_na' => isset($_POST['allow_na']),
            'has_performance_report' => isset($_POST['has_performance_report']),
            'has_evidence_file' => isset($_POST['has_evidence_file']),
            'display_order' => intval($_POST['display_order'])
        ]);
        if ($result['success']) setFlashMessage('เพิ่มตัวชี้วัดสำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        
        // Redirect back to the specific tab/pillar
        redirect(getBaseUrl() . '/pages/indicators.php?pillar_id=' . intval($_POST['pillar_id']));
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_indicator') {
        $result = updateIndicator(intval($_POST['id']), [
            'pillar_id' => intval($_POST['pillar_id']), // Usually keep same pillar
            'code' => sanitizeInput($_POST['code']),
            'name_th' => sanitizeInput($_POST['name_th']),
            'name_en' => sanitizeInput($_POST['name_en']),
            'description' => sanitizeInput($_POST['description']),
            'criteria_0' => sanitizeInput($_POST['criteria_0']),
            'criteria_025' => sanitizeInput($_POST['criteria_025']),
            'criteria_05' => sanitizeInput($_POST['criteria_05']),
            'criteria_075' => sanitizeInput($_POST['criteria_075']),
            'criteria_1' => sanitizeInput($_POST['criteria_1']),
            'criteria_na' => sanitizeInput($_POST['criteria_na']),
            'allow_na' => isset($_POST['allow_na']),
            'has_performance_report' => isset($_POST['has_performance_report']),
            'has_evidence_file' => isset($_POST['has_evidence_file']),
            'display_order' => intval($_POST['display_order'])
        ]);
        if ($result['success']) setFlashMessage('อัปเดตตัวชี้วัดสำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php?pillar_id=' . intval($_POST['pillar_id']));
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_indicator') {
        $ind = getIndicatorById(intval($_POST['id']));
        $result = deleteIndicator(intval($_POST['id']));
        if ($result['success']) setFlashMessage('ลบตัวชี้วัดสำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php?pillar_id=' . ($ind['pillar_id'] ?? ''));
    }
}

$pillars = getAllPillars();
$activePillarId = isset($_GET['pillar_id']) ? intval($_GET['pillar_id']) : ($pillars[0]['id'] ?? 0);

// Get currents
$currentPillar = null;
foreach($pillars as $p) {
    if ($p['id'] == $activePillarId) {
        $currentPillar = $p;
        break;
    }
}

// Get indicators for the active pillar
$indicators = getIndicatorsByPillar($activePillarId);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการตัวชี้วัด - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Premium Layout Styles */
        :root {
            --card-radius: 24px;
            --transition-speed: 0.4s;
            --active-color: <?php echo $currentPillar['color'] ?? 'var(--primary-600)'; ?>;
        }
        
        /* Sticky Header Consolidated */
        .sticky-header-container {
            position: sticky;
            top: 64px;
            z-index: 90;
            background: rgba(249, 250, 251, 0.85);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1rem 3rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .header-top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-main-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .breadcrumb-pro {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0;
        }

        .breadcrumb-pro a { color: inherit; text-decoration: none; transition: color 0.2s; }
        .breadcrumb-pro a:hover { color: var(--primary-600); }

        .page-title-pro {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.04em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            white-space: nowrap;
            line-height: 1.2;
        }

        .pillar-subtitle-pro {
            font-size: 0.825rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0;
            max-width: 600px;
            white-space: normal;
            line-height: 1.4;
            opacity: 0.8;
        }

        /* Compact Pillar Dock */
        .pillar-dock {
            display: flex;
            background: rgba(255, 255, 255, 0.5);
            padding: 0.35rem;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            gap: 0.25rem;
        }

        .pillar-dock-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 16px;
            text-decoration: none !important;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .pillar-dock-item:hover {
            background: rgba(241, 245, 249, 0.8);
            color: #1e293b;
        }

        .pillar-dock-item.active {
            background: var(--active-color);
            color: white !important;
            box-shadow: 0 4px 12px -2px var(--active-color);
        }

        .btn-pillar-settings-pro {
            border: none;
            background: transparent;
            color: #94a3b8;
            padding: 0.5rem 0.75rem;
            border-radius: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-pillar-settings-pro:hover {
            background: rgba(0,0,0,0.04);
            color: #1e293b;
        }

        /* Integrated Action Bar */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            justify-content: flex-end;
        }

        .search-pro {
            position: relative;
            width: 300px;
        }

        .search-pro input {
            width: 100%;
            background: rgba(241, 245, 249, 0.8);
            border: 1px solid transparent;
            padding: 0.6rem 1rem 0.6rem 2.75rem;
            border-radius: 14px;
            font-size: 0.875rem;
            transition: all 0.3s;
        }

        .search-pro input:focus {
            background: white;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .search-pro i {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        /* View Switcher */
        .view-switcher-pro {
            display: flex;
            background: #f1f5f9;
            padding: 0.35rem;
            border-radius: 16px;
            gap: 0.25rem;
        }

        .view-btn-pro {
            padding: 0.6rem 1rem;
            border-radius: 12px;
            border: none;
            background: transparent;
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .view-btn-pro.active {
            background: white;
            color: #1e293b;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Indicator Cards Grid/List */
        .indicators-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 2rem;
        }

        .card-pro {
            background: white;
            border-radius: 32px;
            padding: 2.25rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: visible;
            display: flex;
            flex-direction: column;
        }

        .card-pro:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1);
        }

        .card-pro-accent {
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: var(--active-color);
            opacity: 0.8;
        }

        .indicator-id-pro {
            font-weight: 900;
            font-size: 2.5rem;
            color: var(--active-color);
            opacity: 0.28;
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            line-height: 0.8;
            pointer-events: none;
            text-align: right;
        }

        .indicator-actions-pro {
            position: absolute;
            top: 3.1rem;
            right: 1.5rem;
            display: flex;
            gap: 0.5rem;
            z-index: 10;
        }

        .btn-icon-pro {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            color: #64748b;
            transition: all 0.2s;
            cursor: pointer;
            padding: 0;
            font-size: 0.75rem;
        }

        .btn-icon-pro:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border-color: #e2e8f0;
        }

        /* Shared Details */
        .badge-premium {
            background: rgba(37, 99, 235, 0.08);
            color: #2563eb;
            padding: 0.45rem 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(37, 99, 235, 0.1);
        }

        .badge-premium-info {
            background: rgba(14, 165, 233, 0.08);
            color: #0ea5e9;
            padding: 0.45rem 1rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(14, 165, 233, 0.1);
        }

        .btn-criteria-pro {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            padding: 0.6rem 1.25rem;
            border-radius: 99px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-criteria-pro:hover {
            background: white;
            color: #1e293b;
            border-color: #e2e8f0;
            transform: translateY(-1px);
        }

        .badge-score-pro {
            min-width: 36px;
            padding: 0.15rem 0.4rem;
            border-radius: 6px;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            text-align: center;
        }

        /* List View Modifier */
        .view-list {
            grid-template-columns: 1fr !important;
            gap: 1rem !important;
        }
        
        .view-list .card-pro {
            flex-direction: column !important;
            align-items: stretch !important;
            padding: 2rem !important;
            border-radius: 24px !important;
        }

        /* .view-list .indicator-id-pro { display: none; } */
        .view-list .card-pro > .flex-grow-1 { margin-bottom: 0 !important; }

        /* Detail/Module View */
        .view-module-container {
            display: grid;
            grid-template-columns: minmax(300px, 400px) 1fr;
            gap: 2rem;
            height: calc(100vh - 350px);
        }

        .module-list {
            overflow-y: auto;
            background: white;
            border-radius: 24px;
            padding: 1rem;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .module-item {
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
            border: 1px solid transparent;
        }

        .module-item:hover { background: #f8fafc; }
        .module-item.active { background: #eff6ff; border-color: #dbeafe; }

        .module-detail {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            overflow-y: auto;
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Form Controls */
        .form-control-pro {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            font-size: 0.95rem;
        }
        .form-control-pro:focus {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .rotate-180 { transform: rotate(180deg); }
        .truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* Pillar Modal Specifics */
        .pillar-modal-header {
            padding: 2.5rem 2.5rem 1.5rem;
            border-bottom: 0;
            position: relative;
            background: var(--active-color);
            border-top-left-radius: 24px;
            border-top-right-radius: 24px;
            color: white;
            overflow: hidden;
        }

        .pillar-modal-header::after {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            z-index: 0;
        }

        .pillar-preview-container {
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 20px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2.5rem;
            position: relative;
            transition: all 0.3s ease;
        }

        .pillar-preview-label {
            position: absolute;
            top: -12px;
            left: 20px;
            background: white;
            padding: 0 10px;
            font-size: 0.75rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .preview-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            border-radius: 99px;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            animation: previewPulse 2s infinite ease-in-out;
        }

        @keyframes previewPulse {
            0% { transform: scale(1); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
            50% { transform: scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); }
            100% { transform: scale(1); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        }

        .color-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .color-preview-circle {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 4px solid white;
            box-shadow: 0 0 0 1px #e2e8f0;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .color-preview-circle:hover { transform: scale(1.1); }

        .modal-btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 14px;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-btn-delete:hover {
            background: #ef4444;
            color: white;
        }

        /* Icon Selector Grid */
        .icon-selector-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 0.5rem;
            max-height: 120px;
            overflow-y: auto;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-top: 0.75rem;
        }

        .icon-option {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .icon-option:hover {
            background: var(--active-color);
            color: white;
            border-color: var(--active-color);
            transform: scale(1.1);
        }

        .icon-option.active {
            background: var(--active-color);
            color: white;
            border-color: var(--active-color);
        }

        .header-action-btn-pro {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            transition: all 0.2s;
            cursor: pointer;
            backdrop-filter: blur(4px);
        }

        .header-action-btn-pro:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Custom Scrollbar for Icon Grid */
        .icon-selector-grid::-webkit-scrollbar { width: 4px; }
        .icon-selector-grid::-webkit-scrollbar-track { background: transparent; }
        .icon-selector-grid::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

        /* Entrance Animations */
        .indicator-item {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        <?php for($i=1; $i<=24; $i++): ?>
            .indicator-item:nth-child(<?php echo $i; ?>) { animation-delay: <?php echo $i * 0.05; ?>s; }
        <?php endfor; ?>

        /* ============================================ */
        /* RESPONSIVE DESIGN                            */
        /* ============================================ */

        /* Tablet (≤1200px) */
        @media (max-width: 1200px) {
            .sticky-header-container {
                padding: 1rem 1.5rem;
            }
            .container-fluid.px-5 {
                padding-left: 1.5rem !important;
                padding-right: 1.5rem !important;
            }
            .indicators-grid {
                grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
                gap: 1.5rem;
            }
            .search-pro {
                width: 240px;
            }
            .view-module-container {
                grid-template-columns: minmax(250px, 320px) 1fr;
            }
        }

        /* Small Tablet (≤992px) */
        @media (max-width: 992px) {
            .sticky-header-container {
                padding: 0.75rem 1rem;
                top: 56px;
            }
            .header-top-row {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }
            .pillar-dock {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                flex-wrap: nowrap;
                justify-content: flex-start;
                padding: 0.3rem;
            }
            .pillar-dock::-webkit-scrollbar { display: none; }
            .pillar-dock-item {
                white-space: nowrap;
                flex-shrink: 0;
                padding: 0.45rem 0.85rem;
                font-size: 0.8rem;
            }
            .header-actions {
                justify-content: space-between;
                width: 100%;
            }
            .search-pro {
                flex: 1;
                width: auto;
                min-width: 0;
                max-width: 280px;
            }
            .header-main-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .page-title-pro {
                font-size: 1.3rem;
                white-space: normal;
            }
            .indicators-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1.25rem;
            }
            .card-pro {
                padding: 1.75rem;
                border-radius: 24px;
            }
            .indicator-id-pro {
                font-size: 2rem;
            }
            .view-module-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .module-list {
                max-height: 250px;
            }
            /* Modals */
            .modal-overlay .modal {
                max-width: 95vw !important;
                margin: 1rem;
            }
        }

        /* Mobile (≤768px) */
        @media (max-width: 768px) {
            .sticky-header-container {
                padding: 0.6rem 0.75rem;
                margin-bottom: 1rem;
                gap: 0.5rem;
            }
            .header-top-row {
                gap: 0.5rem;
            }
            .pillar-dock {
                border-radius: 14px;
                gap: 0.15rem;
                padding: 0.25rem;
            }
            .pillar-dock-item {
                padding: 0.4rem 0.7rem;
                font-size: 0.78rem;
                border-radius: 12px;
                gap: 0.35rem;
            }
            .pillar-dock-item span {
                display: inline;
            }
            .header-actions {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .search-pro {
                max-width: 100%;
                flex-basis: 100%;
                order: 3;
            }
            .view-switcher-pro {
                order: 1;
                border-radius: 12px;
            }
            .view-btn-pro {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
                border-radius: 10px;
            }
            .view-btn-pro span,
            .view-btn-pro .btn-text {
                display: none;
            }
            .header-actions > .btn {
                order: 2;
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
                border-radius: 12px;
            }
            .page-title-pro {
                font-size: 1.15rem;
                gap: 0.5rem;
            }
            .pillar-subtitle-pro {
                margin-left: 0 !important;
                font-size: 0.78rem;
            }
            .container-fluid.px-5 {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .indicators-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .card-pro {
                padding: 1.25rem;
                border-radius: 20px;
            }
            .card-pro:hover {
                transform: translateY(-4px);
            }
            .indicator-id-pro {
                font-size: 1.6rem;
                top: 0.75rem;
                right: 1rem;
            }
            .indicator-actions-pro {
                top: 2.25rem;
                right: 1rem;
            }
            .card-pro > .mb-4 {
                margin-top: 2rem !important;
            }
            .card-pro h5 {
                font-size: 1rem !important;
            }
            .badge-premium,
            .badge-premium-info {
                padding: 0.35rem 0.7rem;
                font-size: 0.72rem;
            }
            .badge-premium span,
            .badge-premium-info span {
                display: inline;
            }
            .criteria-details-pro .p-4 {
                padding: 0.75rem !important;
            }
            /* Modals responsive */
            .modal-overlay .modal {
                max-width: 100vw !important;
                max-height: 95vh;
                margin: 0.5rem;
                border-radius: 20px !important;
            }
            .pillar-modal-header {
                padding: 1.5rem 1.25rem 1rem !important;
                border-top-left-radius: 20px !important;
                border-top-right-radius: 20px !important;
            }
            .modal-body.p-4,
            .modal-body.p-md-5 {
                padding: 1.25rem !important;
            }
            .modal-footer.p-4,
            .modal-footer.px-md-5 {
                padding: 1rem 1.25rem !important;
                border-bottom-left-radius: 20px !important;
                border-bottom-right-radius: 20px !important;
            }
            .modal-footer {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            .modal-footer .d-flex.gap-2 {
                flex: 1;
            }
            .modal-footer .d-flex.gap-2 .btn {
                flex: 1;
            }
            .pillar-preview-container {
                padding: 1.25rem;
                border-radius: 16px;
                margin-bottom: 1.5rem;
            }
            .icon-selector-grid {
                grid-template-columns: repeat(auto-fill, minmax(36px, 1fr));
                gap: 0.35rem;
                max-height: 100px;
            }
            .color-input-wrapper {
                flex-wrap: wrap;
            }
            /* Indicator modal criteria */
            .criteria-grid-wrapper .row .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            /* Settings box in indicator modal */
            .d-flex.flex-column.flex-md-row.gap-4 {
                flex-direction: column !important;
                gap: 0.75rem !important;
                padding: 1rem !important;
            }
            .d-flex.flex-column.flex-md-row.gap-4 .ml-md-auto {
                margin-left: 0 !important;
            }
        }

        /* Very Small Mobile (≤480px) */
        @media (max-width: 480px) {
            .sticky-header-container {
                top: 50px;
                padding: 0.5rem 0.5rem;
            }
            .pillar-dock-item {
                padding: 0.35rem 0.55rem;
                font-size: 0.72rem;
            }
            .page-title-pro {
                font-size: 1rem;
            }
            .page-title-pro i {
                font-size: 0.9rem;
            }
            .search-pro input {
                font-size: 0.82rem;
                padding: 0.5rem 0.75rem 0.5rem 2.25rem;
            }
            .card-pro {
                padding: 1rem;
                border-radius: 16px;
            }
            .indicator-id-pro {
                font-size: 1.3rem;
            }
            .card-pro h5 {
                font-size: 0.92rem !important;
            }
            .card-pro .text-secondary {
                font-size: 0.85rem !important;
            }
            .d-flex.flex-wrap.gap-2 {
                gap: 0.35rem !important;
            }
            .badge-premium,
            .badge-premium-info {
                padding: 0.3rem 0.5rem;
                font-size: 0.68rem;
                gap: 0.3rem;
                border-radius: 8px;
            }
            .badge-score-pro {
                font-size: 0.6rem;
                min-width: 30px;
                padding: 0.1rem 0.3rem;
            }
            /* Modal full-screen on very small */
            .modal-overlay .modal {
                margin: 0;
                border-radius: 0 !important;
                max-height: 100vh;
                height: 100%;
            }
            .pillar-modal-header {
                border-radius: 0 !important;
            }
            .modal-footer {
                border-radius: 0 !important;
            }
            .modal-overlay .modal .modal-body {
                max-height: calc(100vh - 180px) !important;
            }
            /* Form layout on very small */
            .row.g-4 .col-md-3,
            .row.g-4 .col-md-5,
            .row.g-4 .col-md-7,
            .row.g-4 .col-md-9 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* Print: hide sticky header chrome */
        @media print {
            .sticky-header-container { position: static; backdrop-filter: none; }
            .pillar-dock, .header-actions, .view-switcher-pro { display: none !important; }
            .card-pro { break-inside: avoid; box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <div class="container-fluid px-0">
                <!-- Status Messages -->
                <?php echo getFlashMessage(); ?>
            
                <div class="sticky-header-container">
                    <div class="header-top-row">
                        <nav class="pillar-dock">
                            <button class="btn-pillar-settings-pro" onclick='openPillarModal(<?php echo json_encode($currentPillar); ?>)' title="ตั้งค่า Pillar">
                                <i class="fas fa-cog"></i>
                            </button>
                            <div style="width: 1px; height: 20px; background: rgba(0,0,0,0.08); margin: auto 0.25rem;"></div>
                            <?php foreach ($pillars as $p): ?>
                                <a href="indicators.php?pillar_id=<?php echo $p['id']; ?>" 
                                   class="pillar-dock-item <?php echo $activePillarId == $p['id'] ? 'active' : ''; ?>"
                                   style="--active-color: <?php echo $p['color'] ?? 'var(--primary-600)'; ?>">
                                    <i class="fas fa-<?php echo !empty($p['icon']) ? $p['icon'] : 'layer-group'; ?>"></i>
                                    <span><?php echo htmlspecialchars($p['code']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                        
                        <div class="header-actions">
                            <div class="view-switcher-pro">
                                <button class="view-btn-pro active" data-view="grid">
                                    <i class="fas fa-th-large"></i> <span class="btn-text">การ์ด</span>
                                </button>
                                <button class="view-btn-pro" data-view="list">
                                    <i class="fas fa-list"></i> <span class="btn-text">รายการ</span>
                                </button>
                            </div>

                            <button class="btn btn-primary" style="background: var(--active-color); border: none; padding: 0.6rem 1.25rem; border-radius: 14px;" onclick="openIndicatorModal()">
                                <i class="fas fa-plus"></i> เพิ่มตัวชี้วัด
                            </button>
                        </div>
                    </div>

                    <div class="header-main-row">
                        <div class="d-flex flex-column gap-1">
                            <h2 class="page-title-pro">
                                <i class="fas fa-layer-group" style="color: var(--active-color);"></i>
                                <?php echo htmlspecialchars($currentPillar['name_th'] ?? 'Indicator Management'); ?>
                            </h2>
                            <?php if (!empty($currentPillar['description'])): ?>
                                <div class="pillar-subtitle-pro" style="margin-left: 2.45rem; margin-top: 0.25rem;">
                                    <?php echo htmlspecialchars($currentPillar['description']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Main Display Area -->
                <div class="container-fluid px-5 pb-5" id="mainIndicatorArea">
                    <?php if (empty($indicators)): ?>
                        <div class="card p-5 text-center bg-white" style="border-radius: 24px; border: 1px solid rgba(0,0,0,0.05);">
                            <div class="mb-4">
                                <i class="far fa-folder-open fa-4x text-light"></i>
                            </div>
                            <h4 class="font-weight-bold">ยังไม่มีตัวชี้วัดในหมวดนี้</h4>
                            <p class="text-muted mb-4">เริ่มต้นด้วยการเพิ่มตัวชี้วัดใหม่สำหรับการประเมิน</p>
                            <button class="btn btn-outline-primary px-4 rounded-pill" onclick="openIndicatorModal()">เพิ่มตัวชี้วัดแรก</button>
                        </div>
                    <?php else: ?>
                        <div id="indicatorsContainer" style="--current-color: <?php echo $currentPillar['color'] ?? '#ccc'; ?>">
                            <!-- Standard View (Grid/List) -->
                            <div id="standardViewWrapper" class="indicators-grid">
                                <?php foreach ($indicators as $ind): ?>
                                    <div class="card-pro indicator-item" 
                                         data-code="<?php echo htmlspecialchars($ind['code']); ?>"
                                         data-name="<?php echo htmlspecialchars($ind['name_th'] . ' ' . $ind['name_en']); ?>"
                                         data-ind-data='<?php echo json_encode($ind, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                        
                                        <div class="card-pro-accent"></div>
                                        <div class="indicator-id-pro"><?php echo htmlspecialchars($ind['code']); ?></div>
                                        
                                        <div class="indicator-actions-pro">
                                            <button class="btn btn-icon-pro" onclick='editIndicator(<?php echo json_encode($ind, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="แก้ไข">
                                                <i class="fas fa-edit text-primary"></i>
                                            </button>
                                            <button class="btn btn-icon-pro" onclick="confirmDeleteIndicator(<?php echo $ind['id']; ?>, '<?php echo $ind['code']; ?>')" title="ลบ">
                                                <i class="fas fa-trash-alt text-danger"></i>
                                            </button>
                                        </div>

                                        <div class="mb-4" style="position: relative; z-index: 1; margin-top: 3rem;">
                                            <div class="d-flex align-items-center gap-3">
                                                <div>
                                                    <h5 class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #1e293b;"><?php echo htmlspecialchars($ind['name_th']); ?></h5>
                                                    <div class="text-muted small font-weight-medium mt-1" style="opacity: 0.7;"><?php echo htmlspecialchars($ind['name_en']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex-grow-1 px-1">
                                            <div class="text-secondary mb-4" style="font-size: 0.925rem; line-height: 1.6; min-height: 3em;">
                                                <?php echo nl2br(htmlspecialchars($ind['description'])); ?>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2 mb-4">
                                                <?php if($ind['has_performance_report']): ?>
                                                    <div class="badge-premium">
                                                        <i class="fas fa-file-alt"></i> <span>ต้องรายงานผล</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if($ind['has_evidence_file']): ?>
                                                    <div class="badge-premium-info">
                                                        <i class="fas fa-paperclip"></i> <span>ต้องแนบไฟล์</span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if($ind['allow_na']): ?>
                                                    <div class="badge-premium" style="background: #dbeafe; color: #1e40af;">
                                                        <i class="fas fa-check-circle"></i> <span>N/A ไม่นำมาคำนวณ</span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>


                                        <div class="criteria-details-pro mt-3">
                                            <div class="p-4 rounded-3xl" style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 16px;">
                                                <?php 
                                                    $scorePoints = [
                                                        ['val' => '0.00', 'key' => 'criteria_0', 'color' => '#94a3b8'],
                                                        ['val' => '0.25', 'key' => 'criteria_025', 'color' => '#38bdf8'],
                                                        ['val' => '0.50', 'key' => 'criteria_05', 'color' => '#6366f1'],
                                                        ['val' => '0.75', 'key' => 'criteria_075', 'color' => '#f59e0b'],
                                                        ['val' => '1.00', 'key' => 'criteria_1', 'color' => '#10b981'],
                                                        ['val' => 'N/A', 'key' => 'criteria_na', 'color' => '#64748b']
                                                    ];
                                                    foreach($scorePoints as $sp):
                                                        $text = !empty($ind[$sp['key']]) ? htmlspecialchars($ind[$sp['key']]) : ($sp['val'] == 'N/A' ? 'ไม่นำมาคำนวณ' : '-');
                                                ?>
                                                    <div class="d-flex align-items-baseline gap-2 mb-2">
                                                        <span class="badge-score-pro" style="background: <?php echo $sp['color']; ?>;"><?php echo $sp['val']; ?></span>
                                                        <span class="text-slate-600 small font-medium"><?php echo $text; ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modals (Pillar, Indicator, Delete) - Condensed and styled to match overall premium workspace -->
    <!-- Pillar Modal -->
    <div class="modal-overlay" id="pillarModalOverlay">
        <div class="modal" style="max-width: 700px; border-radius: 24px;">
            <div class="pillar-modal-header d-flex flex-column gap-2 border-bottom-0">
                <div class="d-flex justify-content-between align-items-center" style="position: relative; z-index: 1;">
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="modal-title font-weight-bold mb-0" id="pillarModalTitle">จัดการ Pillar</h4>
                    </div>
                </div>
                
                <!-- Absolute Header Actions -->
                <div style="position: absolute; top: 1.5rem; right: 1.5rem; display: flex; gap: 0.75rem; z-index: 10;">
                    <button type="submit" form="pillarForm" class="header-action-btn-pro" title="บันทึกข้อมูล">
                        <i class="fas fa-save fa-lg"></i>
                    </button>
                    <button type="button" class="header-action-btn-pro" onclick="closePillarModal()" title="ปิด">
                        <i class="fas fa-times fa-lg"></i>
                    </button>
                </div>

                <p class="mb-0 small" style="opacity: 0.8; position: relative; z-index: 1; max-width: 80%;">ตั้งค่ากลุ่มตัวชี้วัด สีหลัก และไอคอนประจำหมวดหมู่</p>
            </div>

            <form method="POST" id="pillarForm">
                <div class="modal-body p-4 p-md-5" style="max-height: 65vh; overflow-y: auto;">
                    <input type="hidden" name="action" id="pillarAction" value="create_pillar">
                    <input type="hidden" name="id" id="pillarId">
                    
                    <!-- Live Preview -->
                    <div class="pillar-preview-container">
                        <div class="pillar-preview-label">Live Preview</div>
                        <div id="pillarPreviewPill" class="preview-pill" style="background: var(--active-color);">
                            <i id="previewIcon" class="fas fa-layer-group"></i>
                            <span id="previewCode">CODE</span>
                        </div>
                        <div id="previewName" class="text-muted small font-weight-bold mt-2">Pillar Name</div>
                    </div>

                    <div class="row g-4">
                        <!-- Importance Row 1: Code & Name TH -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">รหัส (Code)</label>
                                <input type="text" name="code" id="pillarCode" class="form-control-pro w-100" placeholder="เช่น H1" required oninput="updatePillarPreview()">
                            </div>
                        </div>
                        <div class="col-12 col-md-9">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">ชื่อหมวดหมู่ (ไทย)</label>
                                <input type="text" name="name_th" id="pillarNameTh" class="form-control-pro w-100" placeholder="ระบุชื่อหมวดหมู่ภาษาไทย" required oninput="updatePillarPreview()">
                            </div>
                        </div>

                        <!-- Importance Row 2: Weight & Name EN -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">น้ำหนัก (%)</label>
                                <input type="number" name="weight" id="pillarWeight" class="form-control-pro w-100" placeholder="25" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-9">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">ชื่อหมวดหมู่ (English)</label>
                                <input type="text" name="name_en" id="pillarNameEn" class="form-control-pro w-100" placeholder="Category Name in English" required>
                            </div>
                        </div>

                        <!-- Row 3: Visuals & Style -->
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">ไอคอน (Font Awesome)</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light border-right-0" style="border-radius: 12px 0 0 12px; border-color: #e2e8f0;">fas fa-</span>
                                    </div>
                                    <input type="text" name="icon" id="pillarIcon" class="form-control-pro border-left-0" style="border-radius: 0 12px 12px 0;" placeholder="heartbeat" oninput="updatePillarPreview()">
                                </div>
                                
                                <div class="icon-selector-grid" id="iconSelectionGrid">
                                    <?php 
                                        $commonIcons = ['layer-group', 'heartbeat', 'award', 'user-shield', 'chart-line', 'clipboard-list', 'shield-alt', 'medkit', 'hospital', 'star', 'fire', 'leaf', 'tint', 'cloud', 'sun', 'moon', 'pills', 'briefcase-medical', 'user-md', 'stethoscope'];
                                        foreach($commonIcons as $icon):
                                    ?>
                                        <div class="icon-option" onclick="selectPillarIcon('<?php echo $icon; ?>')" title="<?php echo $icon; ?>">
                                            <i class="fas fa-<?php echo $icon; ?>"></i>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">สีหลัก (Branding Color)</label>
                                <div class="color-input-wrapper">
                                    <div id="colorPreviewBox" class="color-preview-circle" onclick="$('#pillarColor').click()" style="background: var(--active-color);"></div>
                                    <input type="color" name="color" id="pillarColor" class="d-none" oninput="updateColorPreview(this.value)">
                                    <div class="small font-weight-bold text-muted" id="colorText">#000000</div>
                                </div>
                                <div class="text-muted small mt-2">ใช้สำหรับแยกประเภทหัวข้อ</div>
                            </div>
                        </div>

                        <!-- Row 4: Layout & Description -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">ลำดับการแสดง</label>
                                <input type="number" name="display_order" id="pillarOrder" class="form-control-pro w-100" placeholder="1">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="form-group">
                                <label class="form-label font-weight-bold text-slate-700">คำอธิบาย</label>
                                <textarea name="description" id="pillarDesc" class="form-control-pro w-100" rows="3" placeholder="ระบุรายละเอียดสั้นๆ..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer p-4 px-md-5 border-top d-flex justify-content-between align-items-center bg-light" style="border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                    <div>
                        <button type="button" id="btnDeletePillar" class="modal-btn-delete d-none" onclick="confirmDeletePillar()">
                            <i class="fas fa-trash-alt text-sm"></i> ลบข้อมูล
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary px-4" onclick="closePillarModal()" style="border-radius: 12px; font-weight: 600;">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-5" style="border-radius: 12px; background: var(--active-color); border: none; font-weight: 700;">บันทึกข้อมูล</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Indicator Modal -->
    <div class="modal-overlay" id="indicatorModalOverlay">
        <div class="modal" style="max-width: 800px; border-radius: 24px; overflow: hidden;">
            <!-- Premium Header -->
            <div class="pillar-modal-header d-flex flex-column gap-2 border-bottom-0" style="padding: 2rem 2.5rem 1.5rem; background: var(--active-color);">
                <div class="d-flex justify-content-between align-items-center" style="position: relative; z-index: 1;">
                     <h4 class="modal-title font-weight-bold mb-0 text-white" id="indicatorModalTitle">จัดการตัวชี้วัด</h4>
                </div>
                <!-- Absolute Header Actions -->
                <div style="position: absolute; top: 1.5rem; right: 1.5rem; display: flex; gap: 0.75rem; z-index: 10;">
                    <button type="submit" form="indicatorForm" class="header-action-btn-pro" title="บันทึก">
                        <i class="fas fa-save fa-lg"></i>
                    </button>
                    <button type="button" class="header-action-btn-pro" onclick="closeIndicatorModal()" title="ปิด">
                        <i class="fas fa-times fa-lg"></i>
                    </button>
                </div>
                <p class="mb-0 small text-white-50" style="position: relative; z-index: 1;">กำหนดรายละเอียดและเกณฑ์การให้คะแนนสำหรับการประเมิน</p>
            </div>

            <form method="POST" id="indicatorForm">
                <div class="modal-body p-4 p-md-5" style="max-height: 65vh; overflow-y: auto;">
                    <input type="hidden" name="action" id="indicatorAction" value="create_indicator">
                    <input type="hidden" name="id" id="indicatorId">
                    <input type="hidden" name="pillar_id" value="<?php echo $activePillarId; ?>">
                    
                    <!-- Two Column Layout -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                             <div class="form-group mb-0">
                                 <label class="form-label font-weight-bold text-slate-700 small text-uppercase">รหัส</label>
                                 <input type="text" name="code" id="indCode" class="form-control-pro w-100" placeholder="เช่น 1.1" required>
                             </div>
                        </div>
                        <div class="col-md-9">
                             <div class="form-group mb-0">
                                 <label class="form-label font-weight-bold text-slate-700 small text-uppercase">ชื่อตัวชี้วัด (ไทย)</label>
                                 <input type="text" name="name_th" id="indNameTh" class="form-control-pro w-100" placeholder="ระบุชื่อตัวชี้วัด..." required>
                             </div>
                        </div>
                        <div class="col-md-12">
                             <div class="form-group mb-0">
                                 <label class="form-label font-weight-bold text-slate-700 small text-uppercase">ชื่อตัวชี้วัด (English)</label>
                                 <input type="text" name="name_en" id="indNameEn" class="form-control-pro w-100" placeholder="Indicator Name in English">
                             </div>
                        </div>
                        <div class="col-md-12">
                             <div class="form-group mb-0">
                                 <label class="form-label font-weight-bold text-slate-700 small text-uppercase">คำอธิบาย / รายละเอียด</label>
                                 <textarea name="description" id="indDesc" class="form-control-pro w-100" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                             </div>
                        </div>
                    </div>

                    <!-- Criteria Section -->
                    <div class="mb-4">
                        <div class="section-title mb-3 d-flex align-items-center gap-2 text-primary font-weight-bold small text-uppercase" style="color: var(--active-color) !important;">
                            <i class="fas fa-ruler-combined"></i> เกณฑ์การให้คะแนน
                        </div>
                        
                        <div class="criteria-grid-wrapper p-3" style="background: #f8fafc; border-radius: 16px; border: 1px solid #e2e8f0;">
                            <div class="row g-3">
                                <?php 
                                    $pts = [
                                        ['score' => '0.00', 'key' => 'criteria_0', 'color' => '#94a3b8'],
                                        ['score' => '0.25', 'key' => 'criteria_025', 'color' => '#38bdf8'],
                                        ['score' => '0.50', 'key' => 'criteria_05', 'color' => '#6366f1'],
                                        ['score' => '0.75', 'key' => 'criteria_075', 'color' => '#f59e0b'],
                                        ['score' => '1.00', 'key' => 'criteria_1', 'color' => '#10b981'],
                                        ['score' => 'N/A', 'key' => 'criteria_na', 'color' => '#64748b']
                                    ];
                                    foreach($pts as $p):
                                ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-2 bg-white p-2 rounded-lg border-sm" style="border: 1px solid #f1f5f9; border-radius: 12px;">
                                        <span class="badge-score-pro flex-shrink-0" style="background: <?php echo $p['color']; ?>; width: 45px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem;"><?php echo $p['score']; ?></span>
                                        <input type="text" name="<?php echo $p['key']; ?>" id="ind_<?php echo $p['key']; ?>" class="form-control-pro border-0 w-100 py-1 pl-1" placeholder="ระบุเกณฑ์การให้คะแนน..." style="background: transparent; font-size: 0.9rem;">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Settings Box -->
                    <div class="d-flex flex-column flex-md-row gap-4 p-4 bg-white rounded-xl border border-slate-100 shadow-sm" style="border-radius: 16px; border: 1px solid #e2e8f0;">
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center gap-3 cursor-pointer mb-0 p-2 rounded hover-bg-slate-50 transition-all">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="allow_na" id="allowNa" class="custom-control-input">
                                    <label class="custom-control-label" for="allowNa"></label>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-slate-700" style="font-size: 0.9rem;">อนุญาต N/A</div>
                                    <div class="text-muted small">บริษัทสามารถเลือก N/A ได้</div>
                                </div>
                            </label>
                        </div>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center gap-3 cursor-pointer mb-0 p-2 rounded hover-bg-slate-50 transition-all">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="has_performance_report" id="hasPerformance" class="custom-control-input" checked>
                                    <label class="custom-control-label" for="hasPerformance"></label>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-slate-700" style="font-size: 0.9rem;">ผลการดำเนินงาน</div>
                                    <div class="text-muted small">ต้องกรอกรายงานผล</div>
                                </div>
                            </label>
                        </div>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center gap-3 cursor-pointer mb-0 p-2 rounded hover-bg-slate-50 transition-all">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="has_evidence_file" id="hasEvidence" class="custom-control-input" checked>
                                    <label class="custom-control-label" for="hasEvidence"></label>
                                </div>
                                <div>
                                    <div class="font-weight-bold text-slate-700" style="font-size: 0.9rem;">แนบไฟล์หลักฐาน</div>
                                    <div class="text-muted small">ต้องอัปโหลดไฟล์</div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="ml-md-auto pt-3 pt-md-0 d-flex align-items-center">
                            <div class="d-flex align-items-center gap-2 bg-slate-50 p-2 rounded-lg border-sm" style="border-radius: 12px;">
                                <span class="small font-weight-bold text-muted pl-2">ลำดับ:</span>
                                <input type="number" name="display_order" id="indOrder" class="form-control-pro border-0 py-1 px-2 text-center" style="width: 70px; background: transparent;" placeholder="Auto">
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer p-4 px-md-5 border-top d-flex justify-content-between align-items-center bg-light" style="border-bottom-left-radius: 24px; border-bottom-right-radius: 24px;">
                     <div>
                        <button type="button" id="btnDeleteIndicator" class="modal-btn-delete d-none" onclick="triggerDeleteIndicatorWithinModal()">
                            <i class="fas fa-trash-alt text-sm"></i> ลบตัวชี้วัด
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary px-4" onclick="closeIndicatorModal()" style="border-radius: 12px; font-weight: 600;">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary px-5" style="border-radius: 12px; background: var(--active-color); border: none; font-weight: 700;">บันทึก</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModalOverlay">
        <div class="modal" style="max-width: 400px; border-radius: 24px;">
            <form method="POST">
                <div class="modal-body text-center p-5">
                    <div class="text-danger mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x"></i>
                    </div>
                    <h4 class="font-weight-bold mb-2">ยืนยันการลบ?</h4>
                    <p class="text-muted">คุณต้องการลบตัวชี้วัด <strong id="deleteCode"></strong> ใช่หรือไม่?</p>
                    <input type="hidden" name="action" value="delete_indicator">
                    <input type="hidden" name="id" id="deleteId">
                </div>
                <div class="modal-footer justify-content-center border-top-0 pb-5">
                    <button type="button" class="btn btn-outline px-4" onclick="closeDeleteModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger px-4">ยืนยันการลบ</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCriteria(el) {
            const card = el.closest('.card-pro');
            const details = card.querySelector('.criteria-details-pro');
            details.classList.toggle('d-none');
            const icon = el.querySelector('i');
            icon.classList.toggle('rotate-180');
        }
        
        // Modal Controls
        function openPillarModal(data) {
            if (data) {
                $('#pillarModalTitle').text('แก้ไข Pillar: ' + data.code);
                $('#pillarAction').val('update_pillar');
                $('#pillarId').val(data.id);
                $('#pillarCode').val(data.code);
                $('#pillarNameTh').val(data.name_th);
                $('#pillarNameEn').val(data.name_en);
                $('#pillarDesc').val(data.description);
                $('#pillarWeight').val(data.weight);
                updateColorPreview(data.color || '#3b82f6');
                $('#pillarIcon').val(data.icon || 'layer-group');
                $('#pillarOrder').val(data.display_order);
                $('#btnDeletePillar').removeClass('d-none');
            } else {
                $('#pillarModalTitle').text('เพิ่ม Pillar ใหม่');
                $('#pillarAction').val('create_pillar');
                $('#pillarForm')[0].reset();
                updateColorPreview('#3b82f6');
                $('#btnDeletePillar').addClass('d-none');
            }
            updatePillarPreview();
            $('#pillarModalOverlay').addClass('active');
        }

        function updateColorPreview(val) {
            $('#pillarColor').val(val);
            $('#colorPreviewBox').css('background', val);
            $('#colorText').text(val.toUpperCase());
            $('#pillarPreviewPill').css('background', val);
        }

        function updatePillarPreview() {
            const code = $('#pillarCode').val() || 'CODE';
            const name = $('#pillarNameTh').val() || 'Pillar Name';
            const icon = $('#pillarIcon').val() || 'layer-group';
            
            $('#previewCode').text(code);
            $('#previewName').text(name);
            $('#previewIcon').attr('class', 'fas fa-' + icon);

            // Update active state in grid
            $('.icon-option').removeClass('active');
            $('.icon-option[title="' + icon + '"]').addClass('active');
        }

        function selectPillarIcon(icon) {
            $('#pillarIcon').val(icon);
            updatePillarPreview();
        }

        function confirmDeletePillar() {
            const id = $('#pillarId').val();
            const code = $('#pillarCode').val();
            if (confirm('คุณต้องการลบ Pillar "' + code + '" ใช่หรือไม่?\nการลบ Pillar อาจส่งผลกระทบต่อตัวชี้วัดที่อยู่ในกลุ่มนี้')) {
                const form = $('<form method="POST">');
                form.append('<input type="hidden" name="action" value="delete_pillar">');
                form.append('<input type="hidden" name="id" value="' + id + '">');
                $('body').append(form);
                form.submit();
            }
        }
        function closePillarModal() {
            $('#pillarModalOverlay').removeClass('active');
        }
        function openIndicatorModal(data = null) {
            if (data) {
                // Edit Mode
                $('#indicatorModalTitle').text('แก้ไขตัวชี้วัด');
                $('#indicatorAction').val('update_indicator');
                $('#indicatorId').val(data.id);
                
                // Fields
                $('#indCode').val(data.code);
                $('#indNameTh').val(data.name_th);
                $('#indNameEn').val(data.name_en);
                $('#indDesc').val(data.description);
                $('#indOrder').val(data.display_order);

                // Criteria Inputs
                const criteriaKeys = ['criteria_0', 'criteria_025', 'criteria_05', 'criteria_075', 'criteria_1', 'criteria_na'];
                criteriaKeys.forEach(key => {
                    $('#ind_' + key).val(data[key]);
                });

                // Checkboxes
                $('#hasPerformance').prop('checked', data.has_performance_report == 1);
                $('#hasEvidence').prop('checked', data.has_evidence_file == 1);
                $('#allowNa').prop('checked', data.allow_na == 1);

                // Show Delete Button
                $('#btnDeleteIndicator').removeClass('d-none');
            } else {
                // Create Mode
                $('#indicatorModalTitle').text('เพิ่มตัวชี้วัด');
                $('#indicatorAction').val('create_indicator');
                $('#indicatorForm')[0].reset();
                $('#indicatorId').val(''); // Clear ID
                
                // Hide Delete Button
                $('#btnDeleteIndicator').addClass('d-none');
            }
            $('#indicatorModalOverlay').addClass('active');
        }

        function closeIndicatorModal() {
            $('#indicatorModalOverlay').removeClass('active');
        }

        function closeDeleteModal() {
            $('#deleteModalOverlay').removeClass('active');
        }

        function editIndicator(data) {
            openIndicatorModal(data);
        }

        function triggerDeleteIndicatorWithinModal() {
            const id = $('#indicatorId').val();
            const code = $('#indCode').val();
            closeIndicatorModal();
            setTimeout(() => {
                confirmDeleteIndicator(id, code);
            }, 300);
        }

        function confirmDeleteIndicator(id, code) {
            $('#deleteId').val(id);
            $('#deleteCode').text(code);
            $('#deleteModalOverlay').addClass('active');
        }

        // View Toggles & Search
        $(function() {
            let currentView = 'grid';
            const standardView = $('#standardViewWrapper');

            $('.view-btn-pro').on('click', function() {
                const view = $(this).data('view');
                $('.view-btn-pro').removeClass('active');
                $(this).addClass('active');
                currentView = view;

                if (view === 'grid') {
                    standardView.removeClass('d-none view-list').addClass('indicators-grid');
                } else if (view === 'list') {
                    standardView.removeClass('d-none indicators-grid').addClass('view-list');
                }
            });

            $('#indicatorSearch').on('input', function() {
                const query = $(this).val().toLowerCase();
                $('.indicator-item').each(function() {
                    const code = $(this).data('code').toString().toLowerCase();
                    const name = $(this).data('name').toLowerCase();
                    if (code.includes(query) || name.includes(query)) {
                        $(this).removeClass('d-none');
                    } else {
                        $(this).addClass('d-none');
                    }
                });
            });

            function renderCriteriaItem(val, text, color) {
                return `
                    <div class="list-group-item d-flex align-items-baseline p-3 border-0">
                        <span class="badge-score-pro mr-3" style="background: ${color}; min-width: 45px;">${val}</span>
                        <span class="small font-medium">${text || '-'}</span>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>
