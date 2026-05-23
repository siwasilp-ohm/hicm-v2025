<?php
/**
 * HICM V2025 Assessment System - Indicators Management
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
            'has_performance_report' => isset($_POST['has_performance_report']),
            'has_evidence_file' => isset($_POST['has_evidence_file']),
            'display_order' => intval($_POST['display_order'])
        ]);
        if ($result['success']) setFlashMessage('เพิ่มตัวชี้วัดสำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        
        // Redirect back to the specific tab/pillar if possible, for now just reload
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
            'has_performance_report' => isset($_POST['has_performance_report']),
            'has_evidence_file' => isset($_POST['has_evidence_file']),
            'display_order' => intval($_POST['display_order'])
        ]);
        if ($result['success']) setFlashMessage('อัปเดตตัวชี้วัดสำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php?pillar_id=' . intval($_POST['pillar_id']));
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_indicator') {
        // Need pillar_id for redirect
        $ind = getIndicatorById(intval($_POST['id']));
        $result = deleteIndicator(intval($_POST['id']));
        if ($result['success']) setFlashMessage('ลบตัวชี้วัดสำเร็จ', 'success');
        else setFlashMessage('Error: ' . $result['message'], 'error');
        redirect(getBaseUrl() . '/pages/indicators.php?pillar_id=' . ($ind['pillar_id'] ?? ''));
    }
}

$pillars = getAllPillars();
$activePillarId = isset($_GET['pillar_id']) ? intval($_GET['pillar_id']) : ($pillars[0]['id'] ?? 0);

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
        :root {
            --card-radius: 16px;
            --transition-speed: 0.3s;
            --primary-light: #eff6ff;
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-600: #2563eb;
            --info-light: #e0f2fe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-800: #111827;
        }
        
        body {
            background-color: #f3f4f6;
            font-family: 'Prompt', sans-serif;
        }

        .page-title {
            letter-spacing: -0.025em;
        }

        .card {
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: var(--card-radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            background: white;
        }

        /* Pillar Navigation */
        .pillar-tabs-container {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(15px);
            padding: 0.75rem;
            border-radius: 999px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid rgba(255, 255, 255, 0.5);
            margin-bottom: 3rem;
        }

        .pillar-tabs {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .pillar-tabs::-webkit-scrollbar {
            display: none;
        }

        .pillar-tab-item {
            display: flex;
            align-items: center;
            padding: 0.6rem 1.25rem;
            border-radius: 999px;
            color: var(--gray-500);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .pillar-tab-item:hover {
            color: var(--gray-800);
            background: var(--gray-50);
        }

        .pillar-tab-item.active {
            background: var(--active-color);
            color: white !important;
            box-shadow: 0 8px 20px -5px var(--active-color);
            transform: translateY(-2px);
        }

        .indicator-card {
            border-radius: 20px !important;
            padding: 1.75rem 2rem !important;
            margin-bottom: 2rem !important;
            background: white;
            border: 1px solid rgba(0,0,0,0.02);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }

        .indicator-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 35px -12px rgba(0, 0, 0, 0.1);
        }

        .criteria-details {
            animation: fadeInDown 0.3s ease-out;
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .btn-primary {
            background-color: var(--primary-600);
            border-color: var(--primary-600);
            border-radius: 10px;
            font-weight: 600;
            padding: 0.6rem 1.25rem;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
            transform: translateY(-1px);
        }

        .badge-soft-primary { background: var(--primary-50); color: var(--primary-600); }
        .badge-soft-info { background: #e0f2fe; color: #0369a1; }
        
        .focus-none:focus { box-shadow: none !important; }

        .transition-transform {
            transition: transform 0.2s ease;
        }
        .rotate-180 {
            transform: rotate(180deg);
        }

        /* Layout & Sticky Header */
        .main-content {
            padding: 0 !important; /* Managed by inner containers */
            margin-left: 260px;
            min-height: 100vh;
            background-color: #f8fafc;
        }

        .sticky-header-container {
            position: sticky;
            top: 64px;
            z-index: 100;
            background: rgba(248, 250, 252, 0.85);
            backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 1.5rem 3rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .breadcrumb-pro {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .breadcrumb-pro a { color: inherit; text-decoration: none; transition: color 0.2s; }
        .breadcrumb-pro a:hover { color: var(--primary-600); }

        .page-title-pro {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.04em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Floating Pillar Dock */
        .pillar-dock-wrapper {
            display: flex;
            justify-content: center;
            margin: 0 3rem 3rem;
        }

        .pillar-dock {
            display: flex;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(15px);
            padding: 0.6rem;
            border-radius: 28px;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08);
            border: 1px solid rgba(255, 255, 255, 0.5);
            gap: 0.5rem;
            max-width: fit-content;
        }

        .pillar-dock-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            border-radius: 22px;
            text-decoration: none !important;
            color: #64748b;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid transparent;
        }

        .pillar-dock-item:hover {
            background: rgba(0,0,0,0.03);
            color: #1e293b;
            transform: translateY(-2px);
        }

        .pillar-dock-item.active {
            background: var(--active-color);
            color: white !important;
            box-shadow: 0 10px 25px -5px var(--active-color);
            transform: translateY(-4px) scale(1.02);
        }

        /* Dashboard Action Bar */
        .action-bar-wrapper {
            margin: 0 3rem 2.5rem;
        }

        .action-bar {
            display: flex;
            align-items: center;
            background: white;
            padding: 0.75rem 1.25rem;
            border-radius: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 10px 15px -3px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.04);
            gap: 1.25rem;
        }

        .search-pro {
            position: relative;
            flex: 1;
        }

        .search-pro input {
            width: 100%;
            background: #f1f5f9;
            border: none;
            padding: 0.85rem 1rem 0.85rem 3.25rem;
            border-radius: 18px;
            font-size: 0.95rem;
            transition: all 0.3s;
            color: #1e293b;
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

        /* View Toggles Pro */
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

        /* Indicator Card Overhaul */
        .indicators-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
            gap: 2rem;
            padding: 0 3rem 4rem;
        }

        .card-pro {
            background: white;
            border-radius: 32px;
            padding: 2.25rem;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02), 0 20px 25px -5px rgba(0,0,0,0.03);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .card-pro:hover {
            transform: translateY(-10px) scale(1.01);
            box-shadow: 0 30px 60px -12px rgba(0,0,0,0.12);
            border-color: rgba(37, 99, 235, 0.1);
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
            opacity: 0.08;
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            line-height: 0.8;
            pointer-events: none;
        }

        /* Premium Utilities */
        .rounded-xl { border-radius: 18px !important; }
        .rounded-2xl { border-radius: 24px !important; }
        .rounded-3xl { border-radius: 32px !important; }
        
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

        .btn-icon-pro {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid #f1f5f9;
            background: #f8fafc;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-icon-pro:hover {
            background: #f1f5f9;
            color: #1e293b;
            border-color: #cbd5e1;
            transform: scale(1.05);
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            transform: translateY(-1px);
        }

        .badge-score-pro {
            min-width: 48px;
            padding: 0.25rem 0.5rem;
            border-radius: 8px;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
            line-height: 1.4;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .bg-slate-50 { background-color: #f8fafc; }
        .border-slate-100 { border-color: #f1f5f9; }
        .text-slate-600 { color: #475569; }

        /* Detail/Module View Styles */
        .view-module-container {
            display: grid;
            grid-template-columns: minmax(320px, 400px) 1fr;
            gap: 2.5rem;
            height: calc(100vh - 350px);
            padding: 0 3rem 4rem;
        }

        /* Entrance Animations */
        .indicator-item {
            animation: fadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        <?php for($i=1; $i<=24; $i++): ?>
            .indicator-item:nth-child(<?php echo $i; ?>) { animation-delay: <?php echo $i * 0.05; ?>s; }
        <?php endfor; ?>

        .search-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 3rem;
            border-radius: 14px;
            border: 1px solid var(--gray-200);
            background: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .search-input:focus {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            background: white;
            outline: none;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }

        .view-switcher {
            display: flex;
            background: white;
            padding: 0.25rem;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
        }

        .view-btn {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: none;
            background: transparent;
            color: var(--gray-500);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .view-btn.active {
            background: var(--primary-600);
            color: white;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        /* View-specific container styles */
        .view-list {
            display: grid !important;
            grid-template-columns: 1fr !important;
            gap: 0.75rem !important;
            padding: 0 3rem 4rem !important;
        }

        .view-list .card-pro {
            flex-direction: row !important;
            align-items: center !important;
            padding: 1rem 2rem !important;
            border-radius: 20px !important;
        }

        .view-list .card-pro-accent { width: 4px; }
        .view-list .indicator-id-pro { display: none; }
        .view-list .card-pro > div:first-of-type { margin-bottom: 0 !important; flex: 0 0 450px; }
        .view-list .flex-grow-1 { display: flex; align-items: center; gap: 2rem; margin-bottom: 0 !important; }
        .view-list .text-secondary { margin-bottom: 0 !important; min-height: 0 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 400px; }
        .view-list .badge-premium, .view-list .badge-premium-info { padding: 0.25rem 0.5rem; font-size: 0.7rem; }
        .view-list .mt-auto { margin-top: 0 !important; padding-top: 0 !important; border-top: none !important; }
        .view-list .btn-criteria-pro { display: none; }

        /* Detail/Module View Styles */
        .view-module-container {
            display: grid;
            grid-template-columns: minmax(300px, 400px) 1fr;
            gap: 3rem;
            height: calc(100vh - 350px);
            padding: 0 3rem 4rem;
        }

        .module-list {
            overflow-y: auto;
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.04);
        }

        .module-list {
            overflow-y: auto;
            border-right: 1px solid var(--gray-200);
            padding-right: 0.5rem;
        }

        .module-detail {
            background: white;
            border-radius: var(--card-radius);
            padding: 2rem;
            overflow-y: auto;
            position: relative;
        }

        .module-item {
            padding: 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
            border: 1px solid transparent;
        }

        .module-item:hover {
            background: var(--gray-50);
        }

        .module-item.active {
            background: var(--primary-50);
            border-color: var(--primary-200);
        }

        @media (max-width: 992px) {
            .view-module-container {
                grid-template-columns: 1fr;
            }
            .module-list {
                border-right: none;
                height: 300px;
            }
        }

        /* Modal Enhancements */
        .modal-lg { max-width: 1000px; }
        .modal-section-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--gray-500);
            margin-top: 2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .modal-section-title:first-child { margin-top: 0; }
        .modal-section-title i { color: var(--primary-600); }
        
        .form-label-pro {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .form-control-pro {
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            padding: 0.85rem 1.15rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            background: #fff;
            width: 100%;
            display: block;
        }

        .form-control-pro:focus {
            background: white;
            border-color: var(--primary-600);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
            outline: none;
        }

        textarea.form-control-pro {
            min-height: 100px;
            line-height: 1.6;
            resize: vertical;
        }

        .criteria-row {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1rem;
            align-items: center;
        }

        .criteria-score-badge {
            min-width: 75px;
            padding: 0.6rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .criteria-input-group {
            flex: 1;
        }

        .custom-switch-pro {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-100);
            transition: all 0.2s;
            cursor: pointer;
            margin-bottom: 0.75rem;
        }

        .custom-switch-pro:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }

        .custom-switch-pro input { display: none; }
        
        .switch-slider {
            width: 40px;
            height: 22px;
            background: var(--gray-300);
            border-radius: 20px;
            position: relative;
            transition: all 0.3s;
        }

        .switch-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .custom-switch-pro input:checked + .switch-slider {
            background: var(--primary-600);
        }

        .custom-switch-pro input:checked + .switch-slider::before {
            transform: translateX(18px);
        }
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <div class="container-fluid px-0">
                <!-- Toast-style Flash Message -->
                <?php if ($msg = getFlashMessage()): ?>
                    <div id="statusToast" class="position-fixed" style="top: 80px; right: 20px; z-index: 9999; animation: slideInRight 0.3s ease-out;">
                        <?php echo $msg; ?>
                    </div>
                    <script>setTimeout(() => { document.getElementById('statusToast')?.style.setProperty('opacity', '0'); setTimeout(() => document.getElementById('statusToast')?.remove(), 500); }, 3000);</script>
                <?php endif; ?>
            
            <!-- Ultra-Premium Sticky Header & Breadcrumbs -->
            <div class="sticky-header-container">
                <div class="breadcrumb-pro">
                    <a href="dashboard.php">Dashboard</a>
                    <i class="fas fa-chevron-right mx-1" style="font-size: 0.7rem; opacity: 0.5;"></i>
                    <span>Indicators</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="page-title-pro">
                        <i class="fas fa-layer-group" style="color: <?php echo $currentPillar['color'] ?? 'var(--primary-600)'; ?>;"></i>
                        <?php echo htmlspecialchars($currentPillar['name_th'] ?? 'Indicator Management'); ?>
                    </h2>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-none border-0 bg-light" onclick="openPillarModal()" title="Pillar Settings">
                            <i class="fas fa-cog mr-1"></i> Pillar Settings
                        </button>
                    </div>
                </div>
            </div>

            <!-- Floating Pillar Dock Navigation -->
            <div class="pillar-dock-wrapper">
                <nav class="pillar-dock">
                    <?php foreach ($pillars as $p): ?>
                        <a href="indicators.php?pillar_id=<?php echo $p['id']; ?>" 
                           class="pillar-dock-item <?php echo $activePillarId == $p['id'] ? 'active' : ''; ?>"
                           style="--active-color: <?php echo $p['color'] ?? 'var(--primary-600)'; ?>">
                            <i class="fas fa-<?php echo !empty($p['icon']) ? $p['icon'] : 'layer-group'; ?>"></i>
                            <span><?php echo htmlspecialchars($p['code']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Dashboard Action Bar -->
            <div class="action-bar-wrapper">
                <div class="action-bar">
                    <div class="search-pro">
                        <i class="fas fa-search"></i>
                        <input type="text" id="indicatorSearch" placeholder="Search indicator code or title...">
                    </div>
                    
                    <div class="view-switcher-pro">
                        <button class="view-btn-pro active" data-view="grid">
                            <i class="fas fa-th-large"></i> Grid
                        </button>
                        <button class="view-btn-pro" data-view="list">
                            <i class="fas fa-list"></i> List
                        </button>
                        <button class="view-btn-pro" data-view="module">
                            <i class="fas fa-columns"></i> Detail
                        </button>
                    </div>

                    <button class="btn btn-primary rounded-pill px-4 shadow-sm d-flex align-items-center gap-2" 
                            style="background: var(--active-color); border: none;"
                            onclick="openIndicatorModal()">
                        <i class="fas fa-plus"></i>
                        <span>Add Indicator</span>
                    </button>
                </div>
            </div>

            <div class="container-fluid px-5">
                <?php if (!empty($currentPillar['description'])): ?>
                    <div class="alert alert-light border-0 bg-white shadow-sm mb-5 p-4 rounded-xl d-flex align-items-center mx-3" style="border-left: 4px solid var(--active-color) !important;">
                        <div class="rounded-lg mr-4 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: <?php echo $currentPillar['color'] ?? '#000'; ?>10; color: <?php echo $currentPillar['color'] ?? '#000'; ?>;">
                            <i class="fas fa-info-circle fa-lg"></i>
                        </div>
                        <div>
                            <div class="font-weight-bold mb-1" style="font-size: 0.95rem; color: #1e293b;">Pillar Information</div>
                            <div class="text-secondary small font-weight-medium" style="line-height: 1.5;"><?php echo htmlspecialchars($currentPillar['description']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                    <?php if (empty($indicators)): ?>
                        <div class="empty-state card border-0 shadow-sm py-5 text-center bg-white rounded-xl">
                            <div class="mb-4">
                                <i class="far fa-folder-open fa-4x text-light"></i>
                            </div>
                            <h4 class="font-weight-bold">ยังไม่มีตัวชี้วัดในหมวดนี้</h4>
                            <p class="text-muted mb-4">เริ่มต้นด้วยการเพิ่มตัวชี้วัดใหม่สำหรับการประเมิน</p>
                            <button class="btn btn-outline-primary px-4 rounded-pill" onclick="openIndicatorModal()">เพิ่มตัวชี้วัดแรก</button>
                        </div>
                    <?php else: ?>
                        <div class="indicator-list" id="indicatorsContainer" style="--current-color: <?php echo $currentPillar['color'] ?? '#ccc'; ?>">
                                    <!-- Module View Wrapper (Hidden initially) -->
                                    <div id="moduleViewWrapper" class="view-module-container d-none">
                                        <div class="module-list" id="moduleList">
                                            <!-- List items injected here by JS -->
                                        </div>
                                        <div class="module-detail" id="moduleDetail">
                                            <div class="text-center py-5 text-muted">
                                                <i class="fas fa-mouse-pointer fa-3x mb-3"></i>
                                                <h5>เลือกตัวชี้วัดเพื่อดูรายละเอียด</h5>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Standard View (Grid/List) -->
                                    <div id="standardViewWrapper" class="indicators-grid">
                                        <?php foreach ($indicators as $ind): ?>
                                            <div class="card-pro indicator-item" 
                                                 data-code="<?php echo htmlspecialchars($ind['code']); ?>"
                                                 data-name="<?php echo htmlspecialchars($ind['name_th'] . ' ' . $ind['name_en']); ?>"
                                                 data-ind-data='<?php echo json_encode($ind, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                                
                                                <div class="card-pro-accent"></div>
                                                <div class="indicator-id-pro"><?php echo htmlspecialchars($ind['code']); ?></div>
                                                
                                                <div class="d-flex justify-content-between align-items-start mb-4" style="position: relative; z-index: 1;">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="rounded-xl d-flex align-items-center justify-content-center font-weight-bold" 
                                                             style="width: 48px; height: 48px; background: <?php echo $currentPillar['color'] ?? '#666'; ?>15; color: <?php echo $currentPillar['color'] ?? '#666'; ?>; font-size: 1.1rem; border: 1px solid <?php echo $currentPillar['color'] ?? '#666'; ?>20;">
                                                            <?php echo htmlspecialchars($ind['code']); ?>
                                                        </div>
                                                        <div>
                                                            <h5 class="mb-0 font-weight-bold" style="font-size: 1.15rem; color: #1e293b;"><?php echo htmlspecialchars($ind['name_th']); ?></h5>
                                                            <div class="text-muted small font-weight-medium mt-1" style="opacity: 0.7;"><?php echo htmlspecialchars($ind['name_en']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="dropdown">
                                                        <button class="btn btn-icon-pro" id="dropdownMenu<?php echo $ind['id']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-right border-0 shadow-lg rounded-xl" aria-labelledby="dropdownMenu<?php echo $ind['id']; ?>">
                                                            <a class="dropdown-item py-2 px-4 d-flex align-items-center gap-3" href="javascript:void(0)" onclick='editIndicator(<?php echo json_encode($ind, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                                <i class="fas fa-edit text-primary"></i> <span>Edit Detail</span>
                                                            </a>
                                                            <div class="dropdown-divider mx-3 opacity-50"></div>
                                                            <a class="dropdown-item py-2 px-4 d-flex align-items-center gap-3 text-danger" href="javascript:void(0)" onclick="confirmDeleteIndicator(<?php echo $ind['id']; ?>, '<?php echo $ind['code']; ?>')">
                                                                <i class="fas fa-trash-alt"></i> <span>Delete</span>
                                                            </a>
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
                                                                <i class="fas fa-file-alt"></i> <span>Performance Required</span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if($ind['has_evidence_file']): ?>
                                                            <div class="badge-premium-info">
                                                                <i class="fas fa-paperclip"></i> <span>Evidence Required</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="mt-auto pt-4 border-top d-flex justify-content-between align-items-center" style="border-top-style: dashed !important; border-top-color: #e2e8f0 !important;">
                                                    <button class="btn-criteria-pro" onclick="toggleCriteria(this)">
                                                        <span>Assessment Criteria</span>
                                                        <i class="fas fa-chevron-down ml-2"></i>
                                                    </button>
                                                </div>

                                                <div class="criteria-details-pro d-none mt-3">
                                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100">
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
                                                                $text = !empty($ind[$sp['key']]) ? htmlspecialchars($ind[$sp['key']]) : ($sp['val'] == 'N/A' ? 'Not Applicable' : '-');
                                                        ?>
                                                            <div class="d-flex align-items-baseline gap-3 mb-2 last:mb-0">
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
            </div>
            </div>
        </div>
    </main>

    <!-- Pillar Modal -->
    <div class="modal-overlay" id="pillarModalOverlay">
        <div class="modal">
            <div class="modal-header d-flex justify-content-between align-items-center px-4 py-3 border-bottom-0">
                <h5 class="modal-title font-weight-bold text-xl" id="pillarModalTitle">จัดการ Pillar</h5>
                <button type="button" class="btn btn-link text-muted p-0" onclick="closePillarModal()">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="pillarAction" value="create_pillar">
                    <input type="hidden" name="id" id="pillarId">
                    
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">Code</label>
                                <input type="text" name="code" id="pillarCode" class="form-control-pro" required placeholder="e.g. H1">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">ชื่อ (ไทย)</label>
                                <input type="text" name="name_th" id="pillarNameTh" class="form-control-pro" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label class="form-label-pro">ชื่อ (English)</label>
                        <input type="text" name="name_en" id="pillarNameEn" class="form-control-pro" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">น้ำหนัก (%)</label>
                                <input type="number" name="weight" id="pillarWeight" class="form-control-pro" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">สี (Hex)</label>
                                <input type="color" name="color" id="pillarColor" class="form-control-pro" style="height: 50px; padding: 5px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">Icon Class</label>
                                <input type="text" name="icon" id="pillarIcon" class="form-control-pro" placeholder="fa-heart">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label-pro">ลำดับการแสดงผล</label>
                        <input type="number" name="display_order" id="pillarOrder" class="form-control-pro" value="0">
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label-pro">คำอธิบาย</label>
                        <textarea name="description" id="pillarDesc" class="form-control-pro" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 border-top-0">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" onclick="closePillarModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-5 rounded-pill shadow-sm">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Indicator Modal -->
    <div class="modal-overlay" id="indicatorModalOverlay">
        <div class="modal modal-lg">
            <div class="modal-header d-flex justify-content-between align-items-center px-4 py-3 border-bottom-0">
                <h5 class="modal-title font-weight-bold text-xl" id="indicatorModalTitle">จัดการตัวชี้วัด</h5>
                <button type="button" class="btn btn-link text-muted p-0" onclick="closeIndicatorModal()">
                    <i class="fas fa-times fa-lg"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" id="indicatorAction" value="create_indicator">
                    <input type="hidden" name="id" id="indicatorId">
                    <input type="hidden" name="pillar_id" value="<?php echo $activePillarId; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">รหัส (Code)</label>
                                <input type="text" name="code" id="indCode" class="form-control-pro w-100" required placeholder="e.g. 1.1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="form-label-pro">ลำดับการแสดงผล</label>
                                <input type="number" name="display_order" id="indOrder" class="form-control-pro w-100" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label-pro">ชื่อตัวชี้วัด (ไทย)</label>
                        <input type="text" name="name_th" id="indNameTh" class="form-control-pro w-100" required placeholder="ระบุชื่อภาษาไทยแบบเต็ม...">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label-pro">ชื่อตัวชี้วัด (English)</label>
                        <input type="text" name="name_en" id="indNameEn" class="form-control-pro w-100" placeholder="ระบุชื่อภาษาอังกฤษแบบเต็ม...">
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-label-pro">คำอธิบาย</label>
                        <textarea name="description" id="indDesc" class="form-control-pro" placeholder="ระบุรายละเอียดหรือความหมายเพิ่มเติมของตัวชี้วัด..."></textarea>
                    </div>
                    
                    <div class="criteria-grid mt-4">
                        <h6 class="font-weight-bold mb-3 text-dark"><i class="fas fa-list-ol mr-2"></i>เกณฑ์การให้คะแนน</h6>
                        
                        <div class="form-group row no-gutters mb-2 align-items-center">
                            <label class="col-md-2 text-secondary font-weight-bold mb-0">0.00 pts</label>
                            <div class="col-md-10">
                                <input type="text" name="criteria_0" id="crit0" class="form-control-pro" placeholder="เกณฑ์สำหรับคะแนน 0...">
                            </div>
                        </div>
                        <div class="form-group row no-gutters mb-2 align-items-center">
                            <label class="col-md-2 text-info font-weight-bold mb-0">0.25 pts</label>
                            <div class="col-md-10">
                                <input type="text" name="criteria_025" id="crit025" class="form-control-pro" placeholder="เกณฑ์สำหรับคะแนน 0.25...">
                            </div>
                        </div>
                        <div class="form-group row no-gutters mb-2 align-items-center">
                            <label class="col-md-2 text-primary font-weight-bold mb-0">0.50 pts</label>
                            <div class="col-md-10">
                                <input type="text" name="criteria_05" id="crit05" class="form-control-pro" placeholder="เกณฑ์สำหรับคะแนน 0.50...">
                            </div>
                        </div>
                        <div class="form-group row no-gutters mb-2 align-items-center">
                            <label class="col-md-2 text-warning font-weight-bold mb-0">0.75 pts</label>
                            <div class="col-md-10">
                                <input type="text" name="criteria_075" id="crit075" class="form-control-pro" placeholder="เกณฑ์สำหรับคะแนน 0.75...">
                            </div>
                        </div>
                        <div class="form-group row no-gutters mb-2 align-items-center">
                            <label class="col-md-2 text-success font-weight-bold mb-0">1.00 pts</label>
                            <div class="col-md-10">
                                <input type="text" name="criteria_1" id="crit1" class="form-control-pro" placeholder="เกณฑ์สำหรับคะแนน 1.00...">
                            </div>
                        </div>
                        <div class="form-group row no-gutters mb-0 align-items-center">
                            <label class="col-md-2 text-secondary font-weight-bold mb-0">N/A</label>
                            <div class="col-md-10">
                                <input type="text" name="criteria_na" id="critNA" class="form-control-pro" placeholder="ระบุเกณฑ์การประเมินสำหรับระดับนี้...">
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded-lg border">
                        <h6 class="font-weight-bold mb-3 text-dark"><i class="fas fa-check-square mr-2"></i>ข้อกำหนดเพิ่มเติม</h6>
                        <div class="d-flex gap-4">
                            <div class="custom-control custom-checkbox mr-4">
                                <input type="checkbox" class="custom-control-input" name="has_performance_report" id="hasPerformance">
                                <label class="custom-control-label font-weight-bold text-muted" for="hasPerformance">ต้องการผลการดำเนินงาน (Text)</label>
                            </div>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="has_evidence_file" id="hasEvidence">
                                <label class="custom-control-label font-weight-bold text-muted" for="hasEvidence">ต้องการหลักฐานประกอบ (Upload)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" onclick="closeIndicatorModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModalOverlay">
        <div class="modal">
            <div class="modal-header border-bottom-0 pb-0">
                <button type="button" class="modal-close" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body text-center pt-0 pb-4">
                    <div class="text-danger mb-3">
                        <i class="fas fa-exclamation-circle fa-4x"></i>
                    </div>
                    <h4 class="modal-title mb-3">ยืนยันการลบ?</h4>
                    <input type="hidden" name="action" value="delete_indicator">
                    <input type="hidden" name="id" id="deleteId">
                    <p class="text-muted">คุณต้องการลบตัวชี้วัด <strong id="deleteCode" class="text-dark"></strong> ใช่หรือไม่?<br>การกระทำนี้ไม่สามารถเรียกคืนได้</p>
                </div>
                <div class="modal-footer bg-light justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" onclick="closeDeleteModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger px-4">ยืนยันลบข้อมูล</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleCriteria(el) {
            const details = el.parentElement.nextElementSibling;
            details.classList.toggle('d-none');
            const icon = el.querySelector('i');
            icon.classList.toggle('rotate-180');
        }
        
        // --- Pillar Modal ---
        function openPillarModal() {
            document.getElementById('pillarModalTitle').innerText = 'เพิ่ม Pillar';
            document.getElementById('pillarAction').value = 'create_pillar';
            document.getElementById('pillarId').value = '';
            document.getElementById('pillarCode').value = '';
            document.getElementById('pillarNameTh').value = '';
            document.getElementById('pillarNameEn').value = '';
            document.getElementById('pillarWeight').value = '';
            document.getElementById('pillarColor').value = '#3B82F6';
            document.getElementById('pillarIcon').value = '';
            document.getElementById('pillarOrder').value = '0';
            document.getElementById('pillarDesc').value = '';
            document.getElementById('pillarModalOverlay').classList.add('active');
        }

        function editPillar(e, data) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('pillarModalTitle').innerText = 'แก้ไข Pillar';
            document.getElementById('pillarAction').value = 'update_pillar';
            document.getElementById('pillarId').value = data.id;
            document.getElementById('pillarCode').value = data.code;
            document.getElementById('pillarNameTh').value = data.name_th;
            document.getElementById('pillarNameEn').value = data.name_en;
            document.getElementById('pillarWeight').value = data.weight;
            document.getElementById('pillarColor').value = data.color;
            document.getElementById('pillarIcon').value = data.icon;
            document.getElementById('pillarOrder').value = data.display_order;
            document.getElementById('pillarDesc').value = data.description;
            document.getElementById('pillarModalOverlay').classList.add('active');
        }

        function closePillarModal() {
            document.getElementById('pillarModalOverlay').classList.remove('active');
        }

        // --- Indicator Modal ---
        function openIndicatorModal() {
            document.getElementById('indicatorModalTitle').innerText = 'เพิ่มตัวชี้วัด';
            document.getElementById('indicatorAction').value = 'create_indicator';
            document.getElementById('indicatorId').value = '';
            document.getElementById('indCode').value = '';
            document.getElementById('indNameTh').value = '';
            document.getElementById('indNameEn').value = '';
            document.getElementById('indOrder').value = '0';
            document.getElementById('indDesc').value = '';
            document.getElementById('crit0').value = '';
            document.getElementById('crit025').value = '';
            document.getElementById('crit05').value = '';
            document.getElementById('crit075').value = '';
            document.getElementById('crit1').value = '';
            document.getElementById('critNA').value = '';
            document.getElementById('hasPerformance').checked = false;
            document.getElementById('hasEvidence').checked = false;
            document.getElementById('indicatorModalOverlay').classList.add('active');
        }

        function editIndicator(data) {
            document.getElementById('indicatorModalTitle').innerText = 'แก้ไขตัวชี้วัด';
            document.getElementById('indicatorAction').value = 'update_indicator';
            document.getElementById('indicatorId').value = data.id;
            document.getElementById('indCode').value = data.code;
            document.getElementById('indNameTh').value = data.name_th;
            document.getElementById('indNameEn').value = data.name_en;
            document.getElementById('indOrder').value = data.display_order;
            document.getElementById('indDesc').value = data.description;
            document.getElementById('crit0').value = data.criteria_0;
            document.getElementById('crit025').value = data.criteria_025;
            document.getElementById('crit05').value = data.criteria_05;
            document.getElementById('crit075').value = data.criteria_075;
            document.getElementById('crit1').value = data.criteria_1;
            document.getElementById('critNA').value = data.criteria_na;
            document.getElementById('hasPerformance').checked = data.has_performance_report == 1;
            document.getElementById('hasEvidence').checked = data.has_evidence_file == 1;
            document.getElementById('indicatorModalOverlay').classList.add('active');
        }

        function closeIndicatorModal() {
            document.getElementById('indicatorModalOverlay').classList.remove('active');
        }

        // --- Delete ---
        function confirmDeleteIndicator(id, code) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteCode').innerText = code;
            document.getElementById('deleteModalOverlay').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.remove('active');
        }

        // --- View & Search Logic ---
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('indicatorSearch');
            const standardViewWrapper = document.getElementById('standardViewWrapper');
            const moduleViewWrapper = document.getElementById('moduleViewWrapper');
            const indicatorItems = document.querySelectorAll('.indicator-item');
            const viewBtns = document.querySelectorAll('.view-btn-pro');
            const moduleList = document.getElementById('moduleList');
            const moduleDetail = document.getElementById('moduleDetail');

            let currentView = localStorage.getItem('indicator_view_v2') || 'grid';
            
            function updateView(view) {
                currentView = view;
                localStorage.setItem('indicator_view_v2', view);
                
                // Toggle active button
                viewBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.view === view));

                // Reset standard views
                standardViewWrapper.classList.remove('view-list');
                standardViewWrapper.classList.add('d-none');
                moduleViewWrapper.classList.add('d-none');

                if (view === 'grid') {
                    standardViewWrapper.classList.remove('d-none');
                    standardViewWrapper.classList.add('indicators-grid');
                } else if (view === 'list') {
                    standardViewWrapper.classList.add('view-list');
                    standardViewWrapper.classList.remove('indicators-grid');
                    standardViewWrapper.classList.remove('d-none');
                } else if (view === 'module') {
                    moduleViewWrapper.classList.remove('d-none');
                    renderModuleList();
                }
            }

            // Search logic
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                
                if (currentView === 'module') {
                    renderModuleList(query);
                } else {
                    indicatorItems.forEach(item => {
                        const code = item.dataset.code.toLowerCase();
                        const name = item.dataset.name.toLowerCase();
                        if (code.includes(query) || name.includes(query)) {
                            item.classList.remove('d-none');
                            item.style.animation = 'none'; // Reset animation on filter
                            setTimeout(() => item.style.animation = '', 10);
                        } else {
                            item.classList.add('d-none');
                        }
                    });
                }
            });

            // View switcher clicks
            viewBtns.forEach(btn => {
                btn.addEventListener('click', () => updateView(btn.dataset.view));
            });

            // Initialize view
            updateView(currentView);

            // Module View Rendering
            function renderModuleList(filter = '') {
                moduleList.innerHTML = '';
                const items = Array.from(indicatorItems);
                
                items.forEach(item => {
                    const data = JSON.parse(item.dataset.indData);
                    if (filter && !data.code.toLowerCase().includes(filter) && !data.name_th.toLowerCase().includes(filter)) return;

                    const div = document.createElement('div');
                    div.className = 'module-item';
                    div.innerHTML = `
                        <div class="d-flex align-items-center">
                            <span class="badge mr-2" style="background: ${getComputedStyle(indicatorsContainer).getPropertyValue('--current-color')}20; color: ${getComputedStyle(indicatorsContainer).getPropertyValue('--current-color')}">${data.code}</span>
                            <span class="font-weight-medium truncate">${data.name_th}</span>
                        </div>
                    `;
                    div.onclick = () => showDetail(data, div);
                    moduleList.appendChild(div);
                });
            }

            function showDetail(data, element) {
                document.querySelectorAll('.module-item').forEach(i => i.classList.remove('active'));
                element.classList.add('active');

                moduleDetail.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <span class="badge badge-primary px-3 py-1 mb-2" style="background: ${getComputedStyle(indicatorsContainer).getPropertyValue('--current-color')}">${data.code}</span>
                            <h2 class="h3 font-weight-bold mb-1">${data.name_th}</h2>
                            <p class="text-muted">${data.name_en || ''}</p>
                        </div>
                        <div class="d-flex gap-2">
                             <button class="btn btn-outline-primary btn-sm" onclick='editIndicator(${JSON.stringify(data).replace(/'/g, "&#39;")})'><i class="fas fa-edit"></i> แก้ไข</button>
                             <button class="btn btn-outline-danger btn-sm" onclick="confirmDeleteIndicator(${data.id}, '${data.code}')"><i class="fas fa-trash"></i> ลบ</button>
                        </div>
                    </div>
                    
                    <div class="alert alert-light border mb-4">
                        <label class="d-block font-weight-bold tiny text-uppercase text-muted mb-1">คำอธิบาย</label>
                        <p class="mb-0">${data.description || 'ไม่มีคำอธิบาย'}</p>
                    </div>

                    <h5 class="font-weight-bold mb-3">เกณฑ์การประเมิน</h5>
                    <div class="list-group list-group-flush border rounded-lg">
                        <div class="list-group-item d-flex align-items-baseline">
                            <span class="badge badge-secondary mr-3" style="width: 50px;">0.00</span>
                            <span>${data.criteria_0 || '-'}</span>
                        </div>
                        <div class="list-group-item d-flex align-items-baseline">
                            <span class="badge badge-info mr-3" style="width: 50px;">0.25</span>
                            <span>${data.criteria_025 || '-'}</span>
                        </div>
                        <div class="list-group-item d-flex align-items-baseline">
                            <span class="badge badge-primary mr-3" style="width: 50px;">0.50</span>
                            <span>${data.criteria_05 || '-'}</span>
                        </div>
                        <div class="list-group-item d-flex align-items-baseline">
                            <span class="badge badge-warning mr-3" style="width: 50px;">0.75</span>
                            <span>${data.criteria_075 || '-'}</span>
                        </div>
                        <div class="list-group-item d-flex align-items-baseline">
                            <span class="badge badge-success mr-3" style="width: 50px;">1.00</span>
                            <span>${data.criteria_1 || '-'}</span>
                        </div>
                        <div class="list-group-item d-flex align-items-baseline">
                            <span class="badge badge-secondary mr-3" style="width: 50px; background: #94a3b8">N/A</span>
                            <span>${data.criteria_na || 'ไม่นำมาคำนวณคะแนน'}</span>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded-lg border">
                         <h6 class="font-weight-bold mb-2">ข้อกำหนดเพิ่มเติม</h6>
                         <div class="d-flex gap-3">
                             ${data.has_performance_report ? '<span class="badge badge-soft-primary p-2"><i class="fas fa-file-alt mr-1"></i> ต้องการผลการดำเนินงาน</span>' : ''}
                             ${data.has_evidence_file ? '<span class="badge badge-soft-info p-2"><i class="fas fa-paperclip mr-1"></i> ต้องการหลักฐานประกอบ</span>' : ''}
                         </div>
                    </div>
                `;
            }

            // Initialize view
            updateView(currentView);
        });
    </script>
</body>
</html>
