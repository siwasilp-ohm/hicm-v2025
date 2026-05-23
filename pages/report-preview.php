<?php
/**
 * HICM V2025 - Assessment Report Preview & Export
 * หน้า preview และ export รายงานการประเมิน
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/report_generator.php';

requireAuth();

if (!hasRole(ROLE_ADMIN)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$assessmentId = $_GET['assessment_id'] ?? null;
$format = $_GET['format'] ?? 'html'; // html, csv, xlsx, pdf
$action = $_GET['action'] ?? 'preview'; // preview, download

if (!$assessmentId) {
    setFlashMessage('Assessment ID is required', 'error');
    redirect(getBaseUrl() . '/pages/output-management.php');
}

try {
    $generator = new HICMReportGenerator($assessmentId);
    
    if ($action === 'download') {
        switch ($format) {
            case 'csv':
                $generator->exportCSV();
                exit;
            case 'xlsx':
                $generator->exportExcel();
                exit;
            case 'pdf':
                $generator->exportPDF();
                exit;
            default:
                setFlashMessage('Invalid format', 'error');
                redirect(getBaseUrl() . '/pages/output-management.php');
        }
    } else {
        // Preview mode
        $htmlContent = $generator->getHTMLPreview();
    }
} catch (Exception $e) {
    setFlashMessage('Error: ' . $e->getMessage(), 'error');
    redirect(getBaseUrl() . '/pages/output-management.php');
}

$baseUrl = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Preview - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { 
            font-family: "Prompt", sans-serif; 
            background: #f1f5f9; 
            color: #333; 
            height: 100%;
            width: 100%;
            overflow: hidden;
        }
        
        body {
            display: flex;
            flex-direction: column;
        }
        
        .toolbar {
            position: relative;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        
        .toolbar-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.65rem 1.25rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #3b82f6;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #eff6ff;
            border-color: #3b82f6;
        }
        
        .btn-print {
            background: #10b981;
            color: #fff;
        }
        
        .btn-print:hover {
            background: #059669;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #fff;
        }
        
        .btn-pdf:hover {
            background: linear-gradient(135deg, #DC2626, #B91C1C);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3);
        }
        
        .btn-download {
            background: #f59e0b;
            color: #fff;
        }
        
        .btn-download:hover {
            background: #d97706;
        }
        
        .preview-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            padding: 1.5rem;
            gap: 0;
            overflow-y: auto;
        }
        
        .preview-frame {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: visible;
            display: flex;
            flex-direction: column;
        }
        
        .preview-content {
            padding: 2rem;
            overflow: visible;
            background: #fff;
        }
        
        .format-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .format-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #fff;
            color: #475569;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .format-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        .format-btn.active {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }
        
        @media print {
            * { margin: 0; padding: 0; }
            html, body { 
                height: auto; 
                width: 100%; 
                overflow: auto; 
                background: white; 
            }
            body { display: block; }
            .toolbar { display: none !important; }
            .preview-container { 
                flex: none; 
                margin: 0; 
                padding: 0; 
                max-width: 100%; 
                overflow: visible; 
                background: white;
            }
            .preview-frame { 
                border: none; 
                box-shadow: none; 
                overflow: visible;
                display: block;
            }
            .preview-content {
                padding: 0;
                overflow: visible;
                background: white;
            }
            .page {
                page-break-after: auto;
                max-width: 100%;
                margin: 0;
                padding: 10mm 15mm;
                background: white;
            }
            .scores-section {
                page-break-inside: avoid;
            }
            .pillar-title {
                page-break-after: avoid;
            }
            .signature-section {
                margin-top: 2rem;
                page-break-inside: avoid;
            }
            img { max-width: 100%; }
            a { text-decoration: none; color: #000; }
        }
        
        .divider { width: 1px; height: 24px; background: #cbd5e1; }
    </style>
</head>
<body>
    <div class="toolbar">
        <div class="toolbar-title">📋 Report Preview & Export</div>
        <div class="toolbar-actions">
            <button class="btn btn-print" title="Print (Ctrl+P)" onclick="handlePrint()">
                🖨️ Print
            </button>
            <button class="btn btn-pdf" title="Download PDF" onclick="downloadPreviewPDF()">
                📄 Download PDF
            </button>
            
            <div style="position: relative; display: inline-block;">
                <button class="btn btn-download" onclick="toggleExportMenu()">
                    📥 Export ▼
                </button>
                <div id="export-menu" style="display: none; position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; box-shadow: 0 8px 16px rgba(0,0,0,0.15); min-width: 150px; z-index: 1000;">
                    <a href="?assessment_id=<?php echo $assessmentId; ?>&format=csv&action=download" 
                       style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none; border-bottom: 1px solid #f1f5f9;">
                        📊 CSV
                    </a>
                    <a href="?assessment_id=<?php echo $assessmentId; ?>&format=xlsx&action=download" 
                       style="display: block; padding: 0.75rem 1rem; color: #1e293b; text-decoration: none;">
                        📑 Excel
                    </a>
                </div>
            </div>
            
            <a href="<?php echo $baseUrl; ?>/pages/output-management.php" class="btn btn-secondary">
                ← Back
            </a>
        </div>
    </div>
    
    <div class="preview-container">
        <div class="preview-frame">
            <div class="preview-content">
                <?php echo $htmlContent; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/pdf-export.js"></script>
    <script>
        function toggleExportMenu() {
            const menu = document.getElementById('export-menu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('export-menu');
            if (!e.target.closest('[onclick="toggleExportMenu()"]') && !e.target.closest('#export-menu')) {
                menu.style.display = 'none';
            }
        });
        
        // Print functionality
        function handlePrint() {
            HICM_PDF.print();
        }
        
        // Download PDF using HICM_PDF module — landscape A4 fit
        function downloadPreviewPDF() {
            HICM_PDF.download('.preview-content', 'HICM_Report_Preview.pdf', {
                margin: [10, 8, 12, 8],
                html2canvas: {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff',
                    windowWidth: 1120
                },
                jsPDF: {
                    unit: 'mm',
                    format: 'a4',
                    orientation: 'landscape'
                },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            });
        }
        
        // Keyboard shortcut: Ctrl+P or Cmd+P
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                handlePrint();
            }
        });
    </script>
</body>
</html>
