<?php
/**
 * HICM V2025 - Universal Preview Page
 * หน้า preview แบบเต็มหน้าจอก่อน export — ใช้ได้กับทุกหน้าในระบบ
 * 
 * Usage: preview.php?source=assessment-view&id=123
 *        preview.php?source=assessment-result&period_id=5
 *        preview.php?source=reports&year=2025
 *        preview.php?source=export
 *        preview.php?source=user-manual
 *        preview.php?source=report-preview&assessment_id=123
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$source = $_GET['source'] ?? '';
$baseUrl = getBaseUrl();

// Define allowed sources and their configurations
$sources = [
    'assessment-view' => [
        'title' => 'ดูตัวอย่างแบบประเมิน (Admin/Auditor)',
        'icon' => '📋',
        'back_url' => 'assessments.php',
        'back_label' => 'กลับ',
        'filename_prefix' => 'HICM_Assessment',
        'supports_excel' => true,
    ],
    'assessment-result' => [
        'title' => 'ดูตัวอย่างผลการประเมิน',
        'icon' => '📊',
        'back_url' => 'dashboard.php',
        'back_label' => 'กลับ',
        'filename_prefix' => 'HICM_Result',
        'supports_excel' => true,
    ],
    'reports' => [
        'title' => 'ดูตัวอย่างรายงาน',
        'icon' => '📈',
        'back_url' => 'reports.php',
        'back_label' => 'กลับ',
        'filename_prefix' => 'HICM_Report',
        'supports_excel' => true,
        'landscape' => true,
    ],
    'export' => [
        'title' => 'ดูตัวอย่างข้อมูลส่งออก',
        'icon' => '📤',
        'back_url' => 'export.php',
        'back_label' => 'กลับ',
        'filename_prefix' => 'HICM_Export',
        'supports_excel' => true,
        'supports_csv' => true,
    ],
    'user-manual' => [
        'title' => 'ดูตัวอย่างคู่มือการใช้งาน',
        'icon' => '📚',
        'back_url' => 'user-manual.php',
        'back_label' => 'กลับ',
        'filename_prefix' => 'HICM_User_Manual',
        'supports_excel' => false,
    ],
    'report-preview' => [
        'title' => 'Report Preview',
        'icon' => '📋',
        'back_url' => 'output-management.php',
        'back_label' => 'กลับ',
        'filename_prefix' => 'HICM_Report_Preview',
        'supports_excel' => true,
        'supports_csv' => true,
    ],
];

if (!isset($sources[$source])) {
    setFlashMessage('ไม่พบหน้าที่ต้องการ Preview', 'error');
    redirect($baseUrl . '/pages/dashboard.php');
}

$config = $sources[$source];

// Build the iframe source URL — pass all GET params except 'source'
$params = $_GET;
unset($params['source']);
$params['_preview'] = '1'; // Signal to hide navbar/sidebar
$iframeUrl = $baseUrl . '/pages/' . $source . '.php?' . http_build_query($params);

// Build back URL with original params
$backParams = $_GET;
unset($backParams['source']);
unset($backParams['_preview']);
$backUrl = $baseUrl . '/pages/' . $config['back_url'];
if (!empty($backParams)) {
    $backUrl .= '?' . http_build_query($backParams);
}

$pdfFilename = $config['filename_prefix'] . '_' . date('Y-m-d') . '.pdf';
$isLandscape = !empty($config['landscape']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['title']); ?> - <?php echo APP_NAME; ?></title>
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
        
        /* ─── Toolbar ─── */
        .toolbar {
            position: relative;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            flex-shrink: 0;
            gap: 1rem;
        }
        
        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 0;
        }
        
        .toolbar-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .toolbar-info {
            min-width: 0;
        }
        
        .toolbar-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .toolbar-subtitle {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.1rem;
        }
        
        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        
        /* ─── Buttons ─── */
        .btn {
            padding: 0.55rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
        }
        .btn-print:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: #fff;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.25);
        }
        .btn-pdf:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
        }
        
        .btn-excel {
            background: linear-gradient(135deg, #10B981, #059669);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
        }
        .btn-excel:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }
        
        .btn-csv {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
        }
        .btn-csv:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3);
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-back:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        
        .divider {
            width: 1px;
            height: 24px;
            background: #e2e8f0;
            flex-shrink: 0;
        }
        
        /* ─── Zoom Controls ─── */
        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.15rem;
        }
        
        .zoom-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: transparent;
            border-radius: 0.35rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-size: 1rem;
            font-weight: 700;
            transition: all 0.15s;
            font-family: inherit;
        }
        .zoom-btn:hover { background: #e2e8f0; color: #1e293b; }
        
        .zoom-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            min-width: 36px;
            text-align: center;
        }
        
        /* ─── Preview Container ─── */
        .preview-container {
            flex: 1;
            overflow: auto;
            padding: 1.5rem;
            display: flex;
            justify-content: center;
            background: #e2e8f0;
        }
        
        .preview-frame {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 24px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.04);
            overflow: hidden;
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            transform-origin: top center;
        }
        
        .preview-frame iframe {
            width: 100%;
            flex: 1;
            border: none;
            min-height: 600px;
        }

        /* ─── Loading overlay ─── */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(4px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            transition: opacity 0.4s;
        }
        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e2e8f0;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .loading-text {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 500;
        }
        
        /* ─── Print ─── */
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
                padding: 0;
                overflow: visible;
                background: white;
            }
            .preview-frame {
                border: none;
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
        }
        
        /* ─── Responsive ─── */
        @media (max-width: 768px) {
            .toolbar {
                padding: 0.75rem 1rem;
                flex-wrap: wrap;
            }
            .toolbar-actions {
                width: 100%;
                justify-content: flex-end;
            }
            .preview-container {
                padding: 0.75rem;
            }
            .toolbar-subtitle { display: none; }
            .zoom-controls { display: none; }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text">กำลังโหลดตัวอย่าง...</div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="toolbar-left">
            <span class="toolbar-icon"><?php echo $config['icon']; ?></span>
            <div class="toolbar-info">
                <div class="toolbar-title"><?php echo htmlspecialchars($config['title']); ?></div>
                <div class="toolbar-subtitle">Preview ก่อนดำเนินการ — ตรวจสอบความถูกต้องก่อน Export</div>
            </div>
        </div>
        <div class="toolbar-actions">
            <!-- Zoom Controls -->
            <div class="zoom-controls">
                <button class="zoom-btn" onclick="adjustZoom(-10)" title="ลดขนาด">−</button>
                <span class="zoom-label" id="zoomLabel">100%</span>
                <button class="zoom-btn" onclick="adjustZoom(10)" title="ขยาย">+</button>
                <button class="zoom-btn" onclick="resetZoom()" title="รีเซ็ตขนาด" style="font-size:0.7rem;">↺</button>
            </div>
            
            <div class="divider"></div>
            
            <!-- Action Buttons -->
            <button class="btn btn-print" onclick="handlePrint()" title="พิมพ์ (Ctrl+P)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                </svg>
                พิมพ์
            </button>
            <button class="btn btn-pdf" onclick="handleDownloadPDF()" title="ดาวน์โหลด PDF">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <polyline points="9 15 12 18 15 15"/>
                </svg>
                ดาวน์โหลด PDF
            </button>
            
            <?php if (!empty($config['supports_excel'])): ?>
            <button class="btn btn-excel" onclick="handleExportExcel()" title="Export Excel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <path d="M8 13h2v6H8zm4-3h2v9h-2zm4-2h2v11h-2z"/>
                </svg>
                Excel
            </button>
            <?php endif; ?>
            
            <?php if (!empty($config['supports_csv'])): ?>
            <button class="btn btn-csv" onclick="handleExportCSV()" title="Export CSV">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="8" y1="13" x2="16" y2="13"/>
                    <line x1="8" y1="17" x2="16" y2="17"/>
                </svg>
                CSV
            </button>
            <?php endif; ?>
            
            <div class="divider"></div>
            
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                <?php echo htmlspecialchars($config['back_label']); ?>
            </a>
        </div>
    </div>
    
    <!-- Preview Content -->
    <div class="preview-container">
        <div class="preview-frame" id="previewFrame">
            <iframe id="previewIframe" 
                    src="<?php echo htmlspecialchars($iframeUrl); ?>" 
                    onload="onIframeLoad()"
                    title="Preview Content"></iframe>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/3.0.3/jspdf.umd.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/pdf-export.js"></script>
    <script>
        const FILENAME = <?php echo json_encode($pdfFilename); ?>;
        const IS_LANDSCAPE = <?php echo $isLandscape ? 'true' : 'false'; ?>;
        const SOURCE = <?php echo json_encode($source); ?>;
        let currentZoom = 100;
        
        // ─── Iframe loaded ───
        function onIframeLoad() {
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.add('hidden');
            
            // Auto-resize iframe height based on content
            try {
                const iframe = document.getElementById('previewIframe');
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const contentHeight = iframeDoc.documentElement.scrollHeight;
                iframe.style.minHeight = Math.max(contentHeight + 50, 600) + 'px';
            } catch (e) {
                // Cross-origin or other error — fallback to fixed height
                document.getElementById('previewIframe').style.minHeight = '2000px';
            }
        }
        
        // ─── Zoom ───
        function adjustZoom(delta) {
            currentZoom = Math.max(50, Math.min(200, currentZoom + delta));
            applyZoom();
        }
        
        function resetZoom() {
            currentZoom = 100;
            applyZoom();
        }
        
        function applyZoom() {
            const frame = document.getElementById('previewFrame');
            frame.style.transform = `scale(${currentZoom / 100})`;
            document.getElementById('zoomLabel').textContent = currentZoom + '%';
        }
        
        // ─── Print ───
        function handlePrint() {
            try {
                const iframe = document.getElementById('previewIframe');
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } catch (e) {
                window.print();
            }
        }
        
        // ─── Download PDF (jsPDF + html2canvas) ───
        async function handleDownloadPDF() {
            try {
                const iframe = document.getElementById('previewIframe');
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                
                const mainContent = iframeDoc.querySelector('.main-content') 
                                 || iframeDoc.querySelector('.preview-content')
                                 || iframeDoc.querySelector('.manual-main')
                                 || iframeDoc.body;
                
                const clone = mainContent.cloneNode(true);
                clone.querySelectorAll('.no-print, .sidebar, .navbar, .hero-nav, .action-bar, .filter-section').forEach(el => el.remove());
                
                const tempDiv = document.createElement('div');
                tempDiv.style.position = 'fixed';
                tempDiv.style.left = '-9999px';
                tempDiv.style.top = '0';
                tempDiv.style.width = '297mm';
                tempDiv.style.maxWidth = '297mm';
                tempDiv.style.background = '#fff';
                tempDiv.style.fontFamily = '"Prompt", sans-serif';
                tempDiv.style.overflowX = 'hidden';
                tempDiv.appendChild(clone);
                document.body.appendChild(tempDiv);
                
                const iframeStyles = iframeDoc.querySelectorAll('style, link[rel="stylesheet"]');
                iframeStyles.forEach(s => {
                    const clonedStyle = s.cloneNode(true);
                    tempDiv.insertBefore(clonedStyle, tempDiv.firstChild);
                });

                tempDiv.querySelectorAll('table').forEach(t => {
                    t.style.tableLayout = 'fixed';
                    t.style.width = '100%';
                    t.style.maxWidth = '100%';
                    t.style.wordWrap = 'break-word';
                });
                
                showProgress('กำลังสร้าง PDF...');
                
                try {
                    const pdf = await HICM_PDF.fromElement(tempDiv, {
                        margin: [10, 8, 12, 8],
                        image: { type: 'jpeg', quality: 0.95 },
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
                        }
                    });
                    pdf.save(FILENAME);
                    document.body.removeChild(tempDiv);
                    hideProgress();
                } catch (err) {
                    console.error('PDF Error:', err);
                    document.body.removeChild(tempDiv);
                    hideProgress();
                    fallbackPDF();
                }
            } catch (e) {
                console.error('Preview PDF Error:', e);
                fallbackPDF();
            }
        }
        
        function fallbackPDF() {
            // Redirect to original page with ?pdf=1
            const params = new URLSearchParams(window.location.search);
            params.delete('source');
            params.delete('_preview');
            params.set('pdf', '1');
            const originalPage = '<?php echo $source; ?>.php?' + params.toString();
            window.open(originalPage, '_blank');
        }
        
        // ─── Excel Export ───
        function handleExportExcel() {
            try {
                const iframe = document.getElementById('previewIframe');
                const iframeWin = iframe.contentWindow;
                
                // Try to call the export function in the iframe
                if (SOURCE === 'assessment-view' && typeof iframeWin.triggerExport === 'function') {
                    iframeWin.triggerExport();
                } else if (SOURCE === 'assessment-result' && typeof iframeWin.triggerExport === 'function') {
                    iframeWin.triggerExport();
                } else if (SOURCE === 'reports' && typeof iframeWin.exportToExcel === 'function') {
                    iframeWin.exportToExcel();
                } else if (SOURCE === 'export' && typeof iframeWin.exportData === 'function') {
                    iframeWin.exportData('excel');
                } else if (SOURCE === 'report-preview') {
                    // Redirect to download action
                    const params = new URLSearchParams(window.location.search);
                    const assessmentId = params.get('assessment_id');
                    if (assessmentId) {
                        window.location.href = 'report-preview.php?assessment_id=' + assessmentId + '&format=xlsx&action=download';
                    }
                } else {
                    alert('ฟังก์ชัน Export Excel ยังไม่พร้อมสำหรับหน้านี้');
                }
            } catch (e) {
                console.error('Excel export error:', e);
                alert('ไม่สามารถ Export Excel ได้: ' + e.message);
            }
        }
        
        // ─── CSV Export ───
        function handleExportCSV() {
            try {
                const iframe = document.getElementById('previewIframe');
                const iframeWin = iframe.contentWindow;
                
                if (SOURCE === 'export' && typeof iframeWin.exportData === 'function') {
                    iframeWin.exportData('csv');
                } else if (SOURCE === 'report-preview') {
                    const params = new URLSearchParams(window.location.search);
                    const assessmentId = params.get('assessment_id');
                    if (assessmentId) {
                        window.location.href = 'report-preview.php?assessment_id=' + assessmentId + '&format=csv&action=download';
                    }
                } else {
                    alert('ฟังก์ชัน Export CSV ยังไม่พร้อมสำหรับหน้านี้');
                }
            } catch (e) {
                console.error('CSV export error:', e);
                alert('ไม่สามารถ Export CSV ได้: ' + e.message);
            }
        }
        
        // ─── Progress UI ───
        function showProgress(text) {
            const overlay = document.getElementById('loadingOverlay');
            overlay.querySelector('.loading-text').textContent = text || 'กำลังดำเนินการ...';
            overlay.classList.remove('hidden');
        }
        
        function hideProgress() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }
        
        // ─── Keyboard Shortcuts ───
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                handlePrint();
            }
            if (e.key === 'Escape') {
                window.location.href = <?php echo json_encode($backUrl); ?>;
            }
            // Ctrl + / - for zoom
            if ((e.ctrlKey || e.metaKey) && (e.key === '=' || e.key === '+')) {
                e.preventDefault();
                adjustZoom(10);
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '-') {
                e.preventDefault();
                adjustZoom(-10);
            }
            if ((e.ctrlKey || e.metaKey) && e.key === '0') {
                e.preventDefault();
                resetZoom();
            }
        });
    </script>
</body>
</html>
