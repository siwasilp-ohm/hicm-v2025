<?php
/**
 * Pro Export Page - HICM V2025
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$exportTitle = 'ระบบส่งออกข้อมูล (Export Data)';
$exportDesc = 'จัดการและส่งออกข้อมูลในรูปแบบ PDF, Excel, CSV พร้อมระบบกรองข้อมูล';
$dateNow = date('d/m/Y H:i');

// Preview mode — hide navbar/sidebar when rendered inside preview.php iframe
$isPreview = !empty($_GET['_preview']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $exportTitle; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts & Main CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- JS Dependencies -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/pdf-export.js"></script>
    
    <style>
        /* ============================================
           EXPORT PAGE — PRO LAYOUT V2
           ============================================ */

        /* ---------- Page Header ---------- */
        .report-header {
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 60%, #312e81 100%);
            color: white;
            padding: 2.25rem 2.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.75rem;
            position: relative;
            overflow: hidden;
        }
        .report-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .report-header::after {
            content: '';
            position: absolute;
            bottom: -40%;
            left: 15%;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .report-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            position: relative;
            z-index: 1;
        }
        .report-header-left {
            flex: 1;
        }
        .report-title {
            font-size: 1.85rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            letter-spacing: -0.01em;
        }
        .report-meta {
            opacity: 0.85;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .report-meta svg { opacity: 0.7; flex-shrink: 0; }
        .report-header-icon {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,0.12);
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.15);
        }
        .report-header-icon svg { opacity: 0.9; }

        /* ---------- Stats Summary Row ---------- */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid var(--gray-100);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md, 0 4px 12px rgba(0,0,0,0.08));
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg, 12px);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon.blue   { background: #EFF6FF; color: #2563EB; }
        .stat-icon.green  { background: #ECFDF5; color: #059669; }
        .stat-icon.amber  { background: #FFFBEB; color: #D97706; }
        .stat-icon.purple { background: #F5F3FF; color: #7C3AED; }
        .stat-info { flex: 1; min-width: 0; }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.2;
        }
        .stat-label {
            font-size: 0.78rem;
            color: var(--gray-500);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ---------- Action Bar ---------- */
        .action-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
        }
        .action-bar .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: var(--radius-lg, 10px);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .action-bar .btn-pdf {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white; border: none;
            box-shadow: 0 4px 14px rgba(239,68,68,0.25);
        }
        .action-bar .btn-pdf:hover {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239,68,68,0.30);
        }
        .action-bar .btn-excel {
            background: linear-gradient(135deg, #10B981, #059669);
            color: white; border: none;
            box-shadow: 0 4px 14px rgba(16,185,129,0.25);
        }
        .action-bar .btn-excel:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16,185,129,0.30); }
        .action-bar .btn-csv {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: white; border: none;
            box-shadow: 0 4px 14px rgba(59,130,246,0.25);
        }
        .action-bar .btn-csv:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.30); }
        .action-bar .btn-print {
            background: white;
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
        }
        .action-bar .btn-print:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-50, #EFF6FF);
            transform: translateY(-2px);
        }

        /* ---------- Filter Card ---------- */
        .filter-section {
            background: white;
            padding: 1.5rem 1.75rem;
            border-radius: var(--radius-xl);
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }
        .filter-section-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .filter-section-header h3 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        .filter-section-header svg { color: var(--primary-500); flex-shrink: 0; }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        /* ---------- Custom Column Dropdown ---------- */
        .custom-dropdown { position: relative; display: inline-block; width: 100%; }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 260px;
            box-shadow: 0 12px 36px rgba(0,0,0,0.12);
            border-radius: var(--radius-lg, 10px);
            z-index: 1000;
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            padding: 0.5rem;
            margin-top: 0.5rem;
        }
        .dropdown-content.show { display: block; }
        .dropdown-item {
            padding: 0.5rem 0.65rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            transition: background 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
            user-select: none;
        }
        .dropdown-item:hover { background-color: var(--gray-50); }
        .dropdown-content::-webkit-scrollbar { width: 6px; }
        .dropdown-content::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 3px; }

        /* Drag handle */
        .col-drag-handle {
            cursor: grab;
            color: var(--gray-400);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            padding: 2px;
            border-radius: 3px;
            transition: color 0.15s ease, background 0.15s ease;
        }
        .col-drag-handle:hover { color: var(--gray-600); background: var(--gray-100); }
        .col-drag-handle:active { cursor: grabbing; }
        .dropdown-item.col-sortable { padding-left: 0.35rem; }
        .dropdown-item.col-sortable.dragging {
            opacity: 0.45;
            background: var(--primary-50, #EFF6FF);
            box-shadow: inset 0 0 0 1.5px var(--primary-300, #93c5fd);
        }
        .dropdown-item.col-sortable.drag-over-top {
            box-shadow: inset 0 2px 0 0 var(--primary-500);
        }
        .dropdown-item.col-sortable.drag-over-bottom {
            box-shadow: inset 0 -2px 0 0 var(--primary-500);
        }
        .col-order-badge {
            width: 28px; height: 22px;
            display: inline-flex; align-items: center; justify-content: center;
            background: var(--gray-100); color: var(--gray-500);
            border-radius: 4px; font-size: 0.7rem; font-weight: 600;
            flex-shrink: 0;
            border: 1.5px solid transparent;
            cursor: text;
            text-align: center;
            padding: 0;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
            -moz-appearance: textfield;
        }
        .col-order-badge::-webkit-outer-spin-button,
        .col-order-badge::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .col-order-badge:focus {
            border-color: var(--primary-500);
            box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
            background: white;
            color: var(--gray-800);
        }
        .col-order-badge:hover { background: var(--gray-200); }

        /* ---------- Table Header Drag ---------- */
        .report-table th[draggable="true"] {
            cursor: grab;
            position: relative;
        }
        .report-table th[draggable="true"]:active { cursor: grabbing; }
        .report-table th.th-dragging {
            opacity: 0.4;
            background: var(--primary-100, #DBEAFE) !important;
        }
        .report-table th.th-drag-over-left {
            box-shadow: inset 3px 0 0 0 var(--primary-500);
        }
        .report-table th.th-drag-over-right {
            box-shadow: inset -3px 0 0 0 var(--primary-500);
        }
        .report-table th .th-drag-icon {
            display: inline-block;
            opacity: 0;
            margin-right: 4px;
            vertical-align: middle;
            transition: opacity 0.15s ease;
            color: var(--gray-400);
        }
        .report-table th:hover .th-drag-icon { opacity: 0.7; }

        /* ---------- Preview / Data Table Card ---------- */
        .table-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        .table-header {
            padding: 1.25rem 1.75rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .table-header-left {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }
        .table-header-left h3 {
            font-size: 1.05rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        .table-header-left .table-icon {
            width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            background: var(--primary-50, #EFF6FF);
            border-radius: var(--radius-md);
            color: var(--primary-600);
        }
        .badge-count {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: var(--gray-100);
            color: var(--gray-600);
            padding: 0.3rem 0.85rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .badge-count.active { background: var(--primary-50, #EFF6FF); color: var(--primary-700, #1D4ED8); }
        .table-scroll { overflow-x: auto; }
        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
        }
        .report-table th {
            background: var(--gray-50);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.06em;
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 2px solid var(--gray-200);
            white-space: nowrap;
            color: var(--gray-600);
            position: sticky;
            top: 0;
            z-index: 2;
        }
        .report-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
            color: var(--gray-700);
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .report-table tbody tr {
            transition: background 0.15s ease;
        }
        .report-table tbody tr:hover {
            background-color: var(--primary-50, #EFF6FF);
        }
        .report-table tbody tr:nth-child(even) {
            background-color: var(--gray-50, #f9fafb);
        }
        .report-table tbody tr:nth-child(even):hover {
            background-color: var(--primary-50, #EFF6FF);
        }
        .table-footer {
            padding: 1rem 1.75rem;
            border-top: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            color: var(--gray-500);
            background: var(--gray-50, #f9fafb);
        }
        .table-footer-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ---------- Empty State ---------- */
        .empty-state {
            padding: 3.5rem 2rem;
            text-align: center;
        }
        .empty-state-icon {
            width: 72px; height: 72px;
            margin: 0 auto 1.25rem;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--gray-400);
        }
        .empty-state h4 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0 0 0.35rem;
        }
        .empty-state p {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0;
        }

        /* ---------- Form Controls ---------- */
        .form-control, .form-select {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: white;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .form-label {
            display: block;
            margin-bottom: 0.4rem;
            font-weight: 500;
            color: var(--gray-600);
            font-size: 0.82rem;
            letter-spacing: 0.01em;
        }

        /* ---------- Loading Overlay ---------- */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }
        .loading-card {
            background: white;
            padding: 2.5rem 3rem;
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            text-align: center;
        }
        .spinner {
            width: 44px; height: 44px;
            border: 4px solid var(--primary-100);
            border-top: 4px solid var(--primary-600);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .d-none { display: none !important; }

        /* ---------- Highlight ---------- */
        .search-highlight {
            background: #FEF3C7;
            color: #92400E;
            padding: 0.1em 0.25em;
            border-radius: 3px;
            font-weight: 500;
        }

        /* ---------- Print Styles ---------- */
        @media print {
            body {
                font-family: 'Prompt', 'TH Sarabun New', 'Sarabun', sans-serif !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .has-sidebar { margin-left: 0 !important; }
            .sidebar, .navbar, .action-bar, .filter-section, .sidebar-overlay, .loading-overlay, .stats-row { display: none !important; }
            .main-wrapper { margin: 0 !important; padding: 0 !important; }
            .main-content { padding: 20px !important; margin: 0 !important; }
            .report-header {
                background: linear-gradient(135deg, #2563eb, #1e40af) !important;
                color: white !important;
                padding: 1.5rem !important;
                margin-bottom: 1rem !important;
                border-radius: 8px !important;
            }
            .report-header-icon { display: none !important; }
            .table-container { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
            .report-table { width: 100% !important; font-size: 10pt !important; }
            .report-table th {
                background-color: #f3f4f6 !important;
                border: 1px solid #d1d5db !important;
                padding: 8px !important;
                font-size: 9pt !important;
            }
            .report-table td {
                border: 1px solid #e5e7eb !important;
                padding: 6px !important;
                font-size: 9pt !important;
                max-width: none !important;
                white-space: normal !important;
            }
            .report-table tr:nth-child(even) { background-color: #f9fafb !important; }
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 1rem;
                padding-bottom: 0.5rem;
                border-bottom: 2px solid #2563eb;
            }
            .print-header h1 { font-size: 18pt !important; margin: 0 0 0.5rem 0 !important; color: #1e40af !important; }
            .print-header p { font-size: 10pt !important; color: #6b7280 !important; margin: 0 !important; }
            .table-footer { display: none !important; }
            @page { size: A4 landscape; margin: 1cm; }
        }
        .print-header { display: none; }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .report-header { padding: 1.5rem; }
            .report-header-inner { flex-direction: column; align-items: flex-start; }
            .report-header-icon { display: none; }
            .report-title { font-size: 1.35rem; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .filter-grid { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; }
            .action-bar { flex-direction: column; }
            .action-bar .btn { justify-content: center; }
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
        <!-- Print Header (shows only when printing) -->
        <div class="print-header">
            <h1>ระบบส่งออกข้อมูล - HICM V2025</h1>
            <p>วันที่พิมพ์: <?php echo $dateNow; ?></p>
        </div>
        
        <!-- Header -->
        <div class="report-header">
            <div class="report-header-inner">
                <div class="report-header-left">
                    <h1 class="report-title"><?php echo $exportTitle; ?></h1>
                    <p class="report-meta">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <?php echo $exportDesc; ?>
                        <span style="opacity:.5;">|</span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke-width="2"/><line x1="8" y1="2" x2="8" y2="6" stroke-width="2"/><line x1="3" y1="10" x2="21" y2="10" stroke-width="2"/></svg>
                        <?php echo $dateNow; ?>
                    </p>
                </div>
                <div class="report-header-icon">
                    <svg width="36" height="36" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="statTotal">—</div>
                    <div class="stat-label">จำนวนรายการทั้งหมด</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="statFiltered">—</div>
                    <div class="stat-label">แสดงผลหลังกรอง</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="statColumns">—</div>
                    <div class="stat-label">คอลัมน์ที่แสดง</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="statType">—</div>
                    <div class="stat-label">ประเภทข้อมูล</div>
                </div>
            </div>
        </div>

        <!-- Action Bar (Matches Reports) -->
        <div class="action-bar">
            <button class="btn btn-print" onclick="HICM_PDF.print()" title="พิมพ์ (Ctrl+P)">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9" stroke-width="2"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2" stroke-width="2"/><rect x="6" y="14" width="12" height="8" stroke-width="2"/></svg>
                พิมพ์
            </button>
            <button class="btn btn-pdf" onclick="exportData('pdf')">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8" stroke-width="2"/><line x1="12" y1="18" x2="12" y2="12" stroke-width="2"/><polyline points="9 15 12 18 15 15" stroke-width="2"/></svg>
                Export PDF
            </button>
            <button class="btn btn-excel" onclick="exportData('excel')">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export Excel
            </button>
            <button class="btn btn-csv" onclick="exportData('csv')">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export CSV
            </button>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-section-header">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                <h3>ตัวกรองข้อมูล &amp; การแสดงผล</h3>
            </div>
            <div class="filter-grid">
                <!-- Data Source -->
                <div>
                    <label class="form-label">ประเภทข้อมูล</label>
                    <select class="form-select" id="dataTypeSelect">
                        <option value="assessments">แบบประเมิน (Assessments)</option>
                        <option value="companies">สถานประกอบการ (Companies)</option>
                        <option value="users">ผู้ใช้งาน (Users)</option>
                        <option value="indicators">ตัวชี้วัด (Indicators)</option>
                        <option value="user_assessments">การประเมินละเอียด (Detailed)</option>
                    </select>
                </div>

                <!-- Search -->
                <div>
                    <label class="form-label">ค้นหา</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="พิมพ์คำค้นหา...">
                </div>

                <!-- Column Toggle -->
                <div>
                    <label class="form-label">แสดงผลคอลัมน์</label>
                    <div class="custom-dropdown">
                        <button type="button" class="form-select text-start" onclick="toggleDropdown()" id="colDropdownBtn">
                            เลือกคอลัมน์ที่ต้องการแสดง
                        </button>
                        <div id="columnDropdownList" class="dropdown-content">
                            <!-- Columns injected here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table Card -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-header-left">
                    <div class="table-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </div>
                    <h3>ตัวอย่างข้อมูล (Preview)</h3>
                </div>
                <span class="badge-count active" id="recordCount">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    0 รายการ
                </span>
            </div>
            
            <div class="table-scroll">
                <table class="report-table" id="previewTable">
                    <thead>
                        <tr id="tableHead"></tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>
            <div class="table-footer" id="tableFooter" style="display:none;">
                <div class="table-footer-info">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span id="footerInfo">แสดงผลสูงสุด 100 รายการแรก</span>
                </div>
                <div id="footerTimestamp" style="font-size: 0.78rem;">โหลดข้อมูลล่าสุด: <?php echo $dateNow; ?></div>
            </div>
        </div>
    </div>
</main>

<!-- Loading Indicator -->
<div id="loadingIndicator" class="loading-overlay d-none">
    <div class="loading-card">
        <div class="spinner"></div>
        <div style="margin-top: 1.25rem; font-weight: 600; color: var(--gray-700); font-size: 1rem;">กำลังโหลดข้อมูล...</div>
        <div style="margin-top: 0.35rem; font-size: 0.82rem; color: var(--gray-500);">กรุณารอสักครู่</div>
    </div>
</div>

<script>
    // ============================================
    // State Management
    // ============================================
    let currentData = [];
    let filteredData = [];
    let visibleColumns = [];
    let allColumns = [];

    const TYPE_LABELS = {
        'assessments': 'แบบประเมิน',
        'companies': 'สถานประกอบการ',
        'users': 'ผู้ใช้งาน',
        'indicators': 'ตัวชี้วัด',
        'user_assessments': 'การประเมินละเอียด'
    };

    $(document).ready(function() {
        fetchData();
        $('#dataTypeSelect').change(fetchData);
        $('#searchInput').on('keyup', debounce(filterData, 300));

        // Close dropdown when clicking outside
        $(document).click(function(event) {
            if (!$(event.target).closest('.custom-dropdown').length) {
                $('#columnDropdownList').removeClass('show');
            }
        });
    });

    function toggleDropdown() {
        $('#columnDropdownList').toggleClass('show');
    }

    // ============================================
    // Stats Update
    // ============================================
    function updateStats() {
        const type = $('#dataTypeSelect').val();
        $('#statTotal').text(currentData.length.toLocaleString());
        $('#statFiltered').text(filteredData.length.toLocaleString());
        $('#statColumns').text(visibleColumns.length + ' / ' + allColumns.length);
        $('#statType').text(TYPE_LABELS[type] || type);
    }

    // ============================================
    // Data Fetching
    // ============================================
    function fetchData() {
        const type = $('#dataTypeSelect').val();
        showLoading(true);

        $.ajax({
            url: '../api/fetch_export_data.php',
            method: 'GET',
            data: { type: type },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    currentData = response.data;
                    initializeTable(currentData);
                } else {
                    showError(response.message || 'เกิดข้อผิดพลาด');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                showError('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            },
            complete: function() {
                showLoading(false);
                // Update footer timestamp
                const now = new Date();
                const ts = now.toLocaleDateString('th-TH') + ' ' + now.toLocaleTimeString('th-TH', {hour:'2-digit', minute:'2-digit'});
                $('#footerTimestamp').text('โหลดข้อมูลล่าสุด: ' + ts);
            }
        });
    }

    function showError(msg) {
        $('#tableHead').html('');
        $('#tableBody').html('');
        $('#tableBody').closest('.table-scroll').html(`
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h4>เกิดข้อผิดพลาด</h4>
                <p>${msg}</p>
            </div>
        `);
        currentData = [];
        filteredData = [];
        updateStats();
        $('#tableFooter').hide();
    }

    // ============================================
    // Table Initialization
    // ============================================
    function initializeTable(data) {
        // Rebuild table scroll in case showError replaced it
        const container = $('.table-container');
        if (container.find('.table-scroll').length === 0) {
            container.find('.table-header').after(`
                <div class="table-scroll">
                    <table class="report-table" id="previewTable">
                        <thead><tr id="tableHead"></tr></thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>
            `);
        }

        if (!data || data.length === 0) {
            $('#tableHead').html('');
            $('#tableBody').html('');
            $('.table-scroll').html(`
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                    </div>
                    <h4>ไม่พบข้อมูล</h4>
                    <p>ไม่พบข้อมูลสำหรับเงื่อนไขที่เลือก ลองเปลี่ยนประเภทข้อมูล</p>
                </div>
            `);
            filteredData = [];
            allColumns = [];
            visibleColumns = [];
            $('#recordCount').html(`<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg> 0 รายการ`);
            $('#tableFooter').hide();
            updateStats();
            return;
        }

        allColumns = Object.keys(data[0]);
        visibleColumns = [...allColumns];

        renderColumnToggle();
        filterData();
        $('#tableFooter').show();
    }

    // ============================================
    // Column Toggle Dropdown
    // ============================================
    function renderColumnToggle() {
        const list = $('#columnDropdownList');
        list.empty();

        list.append(`
            <div class="dropdown-item" onclick="toggleAllColumns(true)">
                <span style="font-weight: 600; color: var(--primary-600);">✅ เลือกทั้งหมด</span>
            </div>
            <div class="dropdown-item" onclick="toggleAllColumns(false)">
                <span style="font-weight: 600; color: #EF4444;">❌ ยกเลิกทั้งหมด</span>
            </div>
            <div class="dropdown-item" onclick="resetColumnOrder()">
                <span style="font-weight: 600; color: var(--gray-500);">🔄 รีเซ็ตลำดับ</span>
            </div>
            <div style="height: 1px; background: var(--gray-200); margin: 0.5rem 0;"></div>
        `);

        allColumns.forEach((col, idx) => {
            const checked = visibleColumns.includes(col) ? 'checked' : '';
            list.append(`
                <div class="dropdown-item col-sortable" draggable="true" data-col="${col}">
                    <span class="col-drag-handle" title="ลากเพื่อสลับลำดับ">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
                    </span>
                    <input type="number" class="col-order-badge" value="${idx + 1}" min="1" max="${allColumns.length}"
                           data-col="${col}" title="พิมพ์เลขลำดับแล้ว Enter"
                           onkeydown="if(event.key==='Enter'){applyOrderBadge(this);event.preventDefault();}"
                           onblur="applyOrderBadge(this)"
                           onclick="event.stopPropagation();this.select();">
                    <input type="checkbox" class="col-checkbox" value="${col}" ${checked} onchange="toggleColumn('${col}')" style="accent-color: var(--primary-600);">
                    <span style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${formatHeader(col)}</span>
                </div>
            `);
        });

        // Attach drag-and-drop handlers
        initColumnDragDrop();
    }

    // ============================================
    // Data Filtering
    // ============================================
    function filterData() {
        const term = $('#searchInput').val().toLowerCase();
        
        if (!term) {
            filteredData = [...currentData];
        } else {
            filteredData = currentData.filter(row => {
                return Object.values(row).some(val => 
                    String(val).toLowerCase().includes(term)
                );
            });
        }

        const countText = filteredData.length.toLocaleString() + ' รายการ';
        const badgeClass = filteredData.length > 0 ? 'badge-count active' : 'badge-count';
        $('#recordCount').attr('class', badgeClass).html(
            `<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg> ${countText}`
        );

        updateStats();
        renderTableRows();
    }

    // ============================================
    // Table Rendering
    // ============================================
    function renderTableRows() {
        const thead = $('#tableHead');
        thead.empty();
        visibleColumns.forEach((col, idx) => {
            thead.append(`<th draggable="true" data-col="${col}"><span class="th-drag-icon">⠿</span>${formatHeader(col)}</th>`);
        });
        initThDragDrop();

        const tbody = $('#tableBody');
        tbody.empty();

        if (filteredData.length === 0) {
            tbody.append(`<tr><td colspan="${visibleColumns.length || 1}">
                <div class="empty-state" style="padding: 2rem;">
                    <div class="empty-state-icon" style="width: 56px; height: 56px;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                    <h4>ไม่พบผลลัพธ์</h4>
                    <p>ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>
                </div>
            </td></tr>`);
            return;
        }

        const displayData = filteredData.slice(0, 100);
        const searchTerm = $('#searchInput').val();
        
        displayData.forEach((row, rowIdx) => {
            let tr = `<tr>`;
            visibleColumns.forEach(col => {
                let val = row[col];
                if (val === null || val === undefined) val = '<span style="color:var(--gray-400);">—</span>';
                else if (typeof val === 'object') val = JSON.stringify(val);
                else val = escapeHtml(String(val));

                // Highlight search term
                if (searchTerm && String(val).toLowerCase().includes(searchTerm.toLowerCase())) {
                    val = String(val).replace(
                        new RegExp(escapeRegex(searchTerm), 'gi'),
                        match => `<span class="search-highlight">${match}</span>`
                    );
                }
                tr += `<td title="${stripHtml(String(row[col] ?? ''))}">${val}</td>`;
            });
            tr += '</tr>';
            tbody.append(tr);
        });

        // Footer info
        if (filteredData.length > 100) {
            $('#footerInfo').text(`แสดงผล 100 จาก ${filteredData.length.toLocaleString()} รายการ — ส่งออกเพื่อดูข้อมูลทั้งหมด`);
        } else {
            $('#footerInfo').text(`แสดงผลทั้งหมด ${filteredData.length.toLocaleString()} รายการ`);
        }
        $('#tableFooter').show();

        // Update stats column count
        updateStats();
    }

    // ============================================
    // Column Drag-and-Drop Reorder
    // ============================================
    let dragSrcCol = null;

    function initColumnDragDrop() {
        const items = document.querySelectorAll('#columnDropdownList .col-sortable');
        items.forEach(item => {
            item.addEventListener('dragstart', handleDragStart);
            item.addEventListener('dragover', handleDragOver);
            item.addEventListener('dragenter', handleDragEnter);
            item.addEventListener('dragleave', handleDragLeave);
            item.addEventListener('drop', handleDrop);
            item.addEventListener('dragend', handleDragEnd);
        });
    }

    function handleDragStart(e) {
        dragSrcCol = this.dataset.col;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', dragSrcCol);
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        // Determine top/bottom half for indicator
        const rect = this.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        this.classList.remove('drag-over-top', 'drag-over-bottom');
        if (e.clientY < midY) {
            this.classList.add('drag-over-top');
        } else {
            this.classList.add('drag-over-bottom');
        }
    }

    function handleDragEnter(e) {
        e.preventDefault();
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over-top', 'drag-over-bottom');
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('drag-over-top', 'drag-over-bottom');

        const targetCol = this.dataset.col;
        if (!dragSrcCol || dragSrcCol === targetCol) return;

        // Determine insert position (before or after target)
        const rect = this.getBoundingClientRect();
        const midY = rect.top + rect.height / 2;
        const insertAfter = e.clientY >= midY;

        // Reorder allColumns
        const srcIdx = allColumns.indexOf(dragSrcCol);
        allColumns.splice(srcIdx, 1);
        let targetIdx = allColumns.indexOf(targetCol);
        if (insertAfter) targetIdx += 1;
        allColumns.splice(targetIdx, 0, dragSrcCol);

        // Re-sort visibleColumns to match new allColumns order
        visibleColumns = allColumns.filter(c => visibleColumns.includes(c));

        // Re-render
        renderColumnToggle();
        renderTableRows();
    }

    function handleDragEnd(e) {
        document.querySelectorAll('#columnDropdownList .col-sortable').forEach(el => {
            el.classList.remove('dragging', 'drag-over-top', 'drag-over-bottom');
        });
        dragSrcCol = null;
    }

    window.resetColumnOrder = function() {
        if (!currentData || currentData.length === 0) return;
        allColumns = Object.keys(currentData[0]);
        visibleColumns = allColumns.filter(c => visibleColumns.includes(c));
        renderColumnToggle();
        renderTableRows();
    }

    // ============================================
    // Editable Order Badge — type number to reorder
    // ============================================
    window.applyOrderBadge = function(input) {
        const col = input.dataset.col;
        let newPos = parseInt(input.value, 10);
        if (isNaN(newPos) || newPos < 1) newPos = 1;
        if (newPos > allColumns.length) newPos = allColumns.length;

        const curIdx = allColumns.indexOf(col);
        if (curIdx === -1) return;
        if (curIdx === newPos - 1) return; // same position, no-op

        // Remove from current position
        allColumns.splice(curIdx, 1);
        // Insert at new position (0-based)
        allColumns.splice(newPos - 1, 0, col);

        // Re-sync visibleColumns order
        visibleColumns = allColumns.filter(c => visibleColumns.includes(c));

        renderColumnToggle();
        renderTableRows();
    }

    // ============================================
    // Table Header (TH) Drag & Drop
    // ============================================
    let thDragSrcCol = null;

    function initThDragDrop() {
        const ths = document.querySelectorAll('#tableHead th[draggable="true"]');
        ths.forEach(th => {
            th.addEventListener('dragstart', thDragStart);
            th.addEventListener('dragover', thDragOver);
            th.addEventListener('dragenter', thDragEnter);
            th.addEventListener('dragleave', thDragLeave);
            th.addEventListener('drop', thDrop);
            th.addEventListener('dragend', thDragEnd);
        });
    }

    function thDragStart(e) {
        thDragSrcCol = this.dataset.col;
        this.classList.add('th-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', thDragSrcCol);
    }

    function thDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const rect = this.getBoundingClientRect();
        const midX = rect.left + rect.width / 2;
        this.classList.remove('th-drag-over-left', 'th-drag-over-right');
        if (e.clientX < midX) {
            this.classList.add('th-drag-over-left');
        } else {
            this.classList.add('th-drag-over-right');
        }
    }

    function thDragEnter(e) { e.preventDefault(); }

    function thDragLeave(e) {
        this.classList.remove('th-drag-over-left', 'th-drag-over-right');
    }

    function thDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('th-drag-over-left', 'th-drag-over-right');

        const targetCol = this.dataset.col;
        if (!thDragSrcCol || thDragSrcCol === targetCol) return;

        const rect = this.getBoundingClientRect();
        const midX = rect.left + rect.width / 2;
        const insertAfter = e.clientX >= midX;

        // Reorder in allColumns
        const srcIdx = allColumns.indexOf(thDragSrcCol);
        allColumns.splice(srcIdx, 1);
        let targetIdx = allColumns.indexOf(targetCol);
        if (insertAfter) targetIdx += 1;
        allColumns.splice(targetIdx, 0, thDragSrcCol);

        // Sync visibleColumns
        visibleColumns = allColumns.filter(c => visibleColumns.includes(c));

        renderColumnToggle();
        renderTableRows();
    }

    function thDragEnd(e) {
        document.querySelectorAll('#tableHead th').forEach(el => {
            el.classList.remove('th-dragging', 'th-drag-over-left', 'th-drag-over-right');
        });
        thDragSrcCol = null;
    }

    // ============================================
    // Column Toggle Actions
    // ============================================
    window.toggleColumn = function(col) {
        if (visibleColumns.includes(col)) {
            visibleColumns = visibleColumns.filter(c => c !== col);
        } else {
            visibleColumns = allColumns.filter(c => visibleColumns.includes(c) || c === col);
        }
        renderTableRows();
    }

    window.toggleAllColumns = function(check) {
        $('.col-checkbox').prop('checked', check);
        visibleColumns = check ? [...allColumns] : [];
        renderTableRows();
    }

    // ============================================
    // Export Actions
    // ============================================
    window.exportData = function(format) {
        if (format === 'pdf') {
            HICM_PDF.download('.main-content', 'HICM_Export_' + new Date().toISOString().slice(0,10) + '.pdf');
            return;
        }

        if (filteredData.length === 0) {
            alert('ไม่มีข้อมูลสำหรับส่งออก');
            return;
        }

        const exportData = filteredData.map(row => {
            const newRow = {};
            visibleColumns.forEach(col => {
                newRow[formatHeader(col)] = row[col];
            });
            return newRow;
        });

        const fileName = `export_${$('#dataTypeSelect').val()}_${new Date().toISOString().slice(0,10)}`;

        if (format === 'excel') {
            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Export");
            XLSX.writeFile(wb, `${fileName}.xlsx`);
        } else if (format === 'csv') {
            const ws = XLSX.utils.json_to_sheet(exportData);
            const csv = XLSX.utils.sheet_to_csv(ws);
            downloadCSV(csv, `${fileName}.csv`);
        }
    }

    // ============================================
    // Utilities
    // ============================================
    function downloadCSV(csv, filename) {
        const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }

    function formatHeader(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function stripHtml(str) {
        return str.replace(/<[^>]*>/g, '');
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    function showLoading(show) {
        if (show) $('#loadingIndicator').removeClass('d-none');
        else $('#loadingIndicator').addClass('d-none');
    }
</script>
</body>
</html>
