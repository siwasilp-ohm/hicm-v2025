/**
 * HICM V2025 — Professional PDF Export Utility v2.0
 * ─────────────────────────────────────────────────
 * Uses html2pdf.js (html2canvas + jsPDF) for Thai-compatible PDF generation
 * with professional layout transformations for clean, report-grade output.
 *
 * Usage:
 *   HICM_PDF.download('#content-area', 'filename.pdf', options);
 *   HICM_PDF.preview('#content-area', options);
 *   HICM_PDF.print();
 */

const HICM_PDF = (function () {
    'use strict';

    /* ═══════════════════════════════════════════════
       CONSTANTS & DEFAULTS
       ═══════════════════════════════════════════════ */
    const DEFAULTS = {
        margin:       [10, 8, 12, 8],   // top, right, bottom, left (mm) — tighter for landscape
        image:        { type: 'jpeg', quality: 0.95 },
        html2canvas:  {
            scale: 2,
            useCORS: true,
            logging: false,
            letterRendering: true,
            scrollX: 0,
            scrollY: 0,
            windowWidth: 1120,   // matches A4 landscape usable width (~281mm → ~1064px @96dpi, with buffer)
            backgroundColor: '#ffffff',
            removeContainer: true
        },
        jsPDF: {
            unit: 'mm',
            format: 'a4',
            orientation: 'landscape',   // ← landscape A4 by default for better fit
            compress: true
        },
        pagebreak: {
            mode: ['css', 'legacy'],
            before: ['.pdf-page-break-before'],
            avoid: [
                '.pillar-card', '.info-card', '.score-main-card',
                '.card', '.person-card', '.pdf-avoid-break',
                '.pdf-summary-row', '.pdf-section-header'
            ]
        },
        enableLinks: false
    };

    /* Brand colors */
    const BRAND = {
        primary:    '#1e3a5f',
        secondary:  '#0369a1',
        accent:     '#3B82F6',
        success:    '#10B981',
        warning:    '#F59E0B',
        purple:     '#8B5CF6',
        gray100:    '#f1f5f9',
        gray200:    '#e2e8f0',
        gray500:    '#64748b',
        gray700:    '#334155',
        gray900:    '#0f172a'
    };

    let _busy = false;

    /* ═══════════════════════════════════════════════
       TOAST HELPER
       ═══════════════════════════════════════════════ */
    function toast(message, type) {
        if (typeof showToast === 'function') {
            showToast(message, type);
        } else {
            console[type === 'error' ? 'error' : 'log']('[HICM_PDF]', message);
        }
    }

    /* ═══════════════════════════════════════════════
       LOADING OVERLAY — professional progress UI
       ═══════════════════════════════════════════════ */
    function createOverlay(message) {
        removeOverlay();
        const overlay = document.createElement('div');
        overlay.id = 'hicm-pdf-overlay';
        overlay.innerHTML = `
            <div style="
                position:fixed;inset:0;z-index:99999;
                background:rgba(15,23,42,0.7);
                backdrop-filter:blur(6px);
                display:flex;align-items:center;justify-content:center;
            ">
                <div style="
                    background:white;border-radius:16px;
                    padding:2.5rem 3rem;text-align:center;
                    box-shadow:0 25px 60px rgba(0,0,0,0.3);
                    max-width:400px;width:92%;
                ">
                    <div style="
                        width:52px;height:52px;
                        border:4px solid #e2e8f0;border-top-color:#3b82f6;
                        border-radius:50%;
                        animation:hicm-spin .7s linear infinite;
                        margin:0 auto 1.25rem;
                    "></div>
                    <div id="hicm-pdf-overlay-title" style="font-family:'Prompt',sans-serif;font-weight:600;font-size:1.1rem;color:#1e293b;margin-bottom:.5rem;">
                        ${message || 'กำลังสร้างไฟล์ PDF...'}
                    </div>
                    <div id="hicm-pdf-overlay-sub" style="font-family:'Prompt',sans-serif;font-size:.85rem;color:#64748b;">
                        กรุณารอสักครู่ อาจใช้เวลา 10-30 วินาที
                    </div>
                    <div style="margin-top:1.25rem;background:#f1f5f9;border-radius:999px;height:6px;overflow:hidden;">
                        <div id="hicm-pdf-progress" style="height:100%;width:0%;background:linear-gradient(90deg,#3B82F6,#8B5CF6);border-radius:999px;transition:width .4s ease;"></div>
                    </div>
                </div>
            </div>
            <style>@keyframes hicm-spin{to{transform:rotate(360deg)}}</style>
        `;
        document.body.appendChild(overlay);
        return overlay;
    }

    function updateOverlay(title, sub, progress) {
        var t = document.getElementById('hicm-pdf-overlay-title');
        var s = document.getElementById('hicm-pdf-overlay-sub');
        var p = document.getElementById('hicm-pdf-progress');
        if (t && title) t.textContent = title;
        if (s && sub)   s.textContent = sub;
        if (p && typeof progress === 'number') p.style.width = Math.min(progress, 100) + '%';
    }

    function removeOverlay() {
        var el = document.getElementById('hicm-pdf-overlay');
        if (el) el.remove();
    }

    /* ═══════════════════════════════════════════════
       CANVAS → IMAGE (Chart.js support)
       ═══════════════════════════════════════════════ */
    function convertCanvasesToImages(container) {
        var canvases = container.querySelectorAll('canvas');
        var restorations = [];
        canvases.forEach(function(canvas) {
            try {
                var dataUrl = canvas.toDataURL('image/png');
                var img = document.createElement('img');
                img.src = dataUrl;
                img.style.cssText = 'max-width:100%;height:auto;display:block;margin:0 auto;';
                img.className = 'hicm-pdf-canvas-img';
                canvas.style.display = 'none';
                canvas.parentNode.insertBefore(img, canvas.nextSibling);
                restorations.push(function() {
                    canvas.style.display = '';
                    img.remove();
                });
            } catch (e) {
                console.warn('[HICM_PDF] Could not convert canvas:', e);
            }
        });
        return restorations;
    }

    /* ═══════════════════════════════════════════════
       INJECT PDF STYLESHEET into cloned document
       This is the KEY to professional PDF layout.
       ═══════════════════════════════════════════════ */
    function injectPDFStyles(doc) {
        var style = doc.createElement('style');
        style.id = 'hicm-pdf-injected-styles';
        style.textContent = `
            /* ─── PDF Global Reset — FIT A4 LANDSCAPE ─── */
            * { 
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-sizing: border-box;
            }
            html, body {
                font-family: 'Prompt', 'Sarabun', 'Noto Sans Thai', sans-serif !important;
                font-size: 8.5pt !important;
                line-height: 1.45 !important;
                color: #1e293b !important;
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
            }

            /* ─── MASTER FIT RULE: force all content within container ─── */
            .main-wrapper, .main-content, .main-content > * {
                max-width: 100% !important;
                overflow-x: hidden !important;
            }
            img, svg, canvas, video, iframe, table {
                max-width: 100% !important;
            }
            table {
                table-layout: fixed !important;
                word-wrap: break-word !important;
            }

            /* ─── Hide UI elements ─── */
            .hero-nav, .no-print, .action-bar, .filter-section, 
            .page-actions, .modal, .modal-overlay,
            .sidebar, .navbar, .sidebar-overlay, .toast-container,
            #evidenceModal, #filePreviewModal, #hicm-pdf-overlay,
            .btn[onclick], button[onclick] {
                display: none !important;
            }

            /* ─── Main content full-width ─── */
            .main-wrapper, .main-content {
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            /* ─── Print-only elements visible ─── */
            .print-only { display: block !important; }

            /* ─── Animations killed ─── */
            .animate-in, .animate-fade-in-up {
                opacity: 1 !important;
                transform: none !important;
                animation: none !important;
            }

            /* ═══════════════════════════════════════
               PDF DOCUMENT HEADER (print-only)
               ═══════════════════════════════════════ */
            .print-doc-header {
                text-align: center;
                padding: 12px 16px 10px;
                margin-bottom: 0;
                border-bottom: 3px solid ${BRAND.primary};
                position: relative;
            }
            .print-doc-header::after {
                content: '';
                position: absolute;
                bottom: -6px;
                left: 20%;
                right: 20%;
                height: 1px;
                background: ${BRAND.gray200};
            }
            .print-doc-header h1 {
                font-size: 16pt !important;
                font-weight: 700 !important;
                color: ${BRAND.primary} !important;
                margin: 0 0 4px 0 !important;
                letter-spacing: 0.5px;
            }
            .print-doc-header p {
                font-size: 8.5pt !important;
                color: ${BRAND.gray500} !important;
                margin: 2px 0 !important;
                line-height: 1.4;
            }

            /* ═══════════════════════════════════════
               HERO SECTION — compact, professional
               ═══════════════════════════════════════ */
            .assessment-hero {
                background: linear-gradient(135deg, ${BRAND.primary} 0%, ${BRAND.secondary} 100%) !important;
                border-radius: 10px !important;
                padding: 14px 18px !important;
                margin: 12px 0 14px 0 !important;
                color: white !important;
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            .assessment-hero::before {
                display: none !important;
            }
            .assessment-hero, .assessment-hero *,
            .hero-content, .hero-content *,
            .hero-info *, .hero-company *,
            .hero-meta, .hero-meta * {
                color: white !important;
            }
            .hero-company {
                gap: 12px !important;
            }
            .hero-avatar {
                width: 50px !important;
                height: 50px !important;
                border-radius: 10px !important;
                font-size: 1.2rem !important;
                flex-shrink: 0;
            }
            .hero-avatar img {
                border-radius: 10px !important;
            }
            .hero-info h1 {
                font-size: 13pt !important;
                font-weight: 700 !important;
                margin: 0 0 4px 0 !important;
                line-height: 1.3;
            }
            .hero-meta {
                gap: 12px !important;
                font-size: 8pt !important;
                opacity: 0.92 !important;
            }
            .hero-meta span {
                font-size: 8pt !important;
                gap: 4px !important;
            }
            .hero-meta svg {
                width: 12px !important;
                height: 12px !important;
            }

            /* ═══════════════════════════════════════
               SCORE SHOWCASE — side by side layout
               ═══════════════════════════════════════ */
            .score-showcase {
                display: grid !important;
                grid-template-columns: 1fr 1fr 1fr !important;
                gap: 12px !important;
                margin: 0 0 14px 0 !important;
                page-break-inside: avoid;
                max-width: 100% !important;
            }
            .score-main-card {
                background: #fff !important;
                border-radius: 10px !important;
                padding: 14px 12px !important;
                text-align: center;
                box-shadow: none !important;
                border: 1.5px solid ${BRAND.gray200} !important;
                position: relative;
                overflow: hidden;
            }
            .score-main-card::after {
                height: 3px !important;
                background: linear-gradient(90deg, ${BRAND.accent}, ${BRAND.purple}, ${BRAND.success}) !important;
            }
            .score-ring {
                width: 110px !important;
                height: 110px !important;
                margin: 0 auto 8px !important;
            }
            .score-ring svg {
                width: 110px !important;
                height: 110px !important;
            }
            .score-value .number {
                font-size: 1.8rem !important;
                font-weight: 800 !important;
            }
            .score-value .unit {
                font-size: 0.7rem !important;
            }
            .level-badge {
                font-size: 8pt !important;
                padding: 3px 10px !important;
            }
            .stars-row {
                margin-top: 6px !important;
                gap: 2px !important;
            }
            .stars-row svg {
                width: 16px !important;
                height: 16px !important;
            }

            /* ─── Info Cards ─── */
            .info-card {
                background: #fff !important;
                border-radius: 10px !important;
                padding: 12px 14px !important;
                box-shadow: none !important;
                border: 1.5px solid ${BRAND.gray200} !important;
                font-size: 8.5pt !important;
            }
            .info-card-header {
                margin-bottom: 8px !important;
                padding-bottom: 8px !important;
                gap: 8px !important;
            }
            .info-card-icon {
                width: 28px !important;
                height: 28px !important;
                border-radius: 6px !important;
            }
            .info-card-icon svg {
                width: 14px !important;
                height: 14px !important;
            }
            .info-card-title {
                font-size: 9pt !important;
                font-weight: 700 !important;
            }
            .info-row {
                padding: 4px 0 !important;
                font-size: 8pt !important;
            }
            .info-label { font-size: 8pt !important; }
            .info-value { font-size: 8pt !important; }

            /* Person cards compact */
            .person-card {
                padding: 5px 8px !important;
                gap: 6px !important;
                border-radius: 6px !important;
                margin-bottom: 4px !important;
            }
            .person-avatar {
                width: 24px !important;
                height: 24px !important;
                font-size: 0.55rem !important;
            }
            .person-name { font-size: 8pt !important; }
            .person-sub  { font-size: 7pt !important; }

            /* ═══════════════════════════════════════
               PILLAR PROGRESS CARDS — 4-column grid
               ═══════════════════════════════════════ */
            .pillar-grid {
                display: grid !important;
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 8px !important;
                margin-bottom: 16px !important;
                page-break-inside: avoid;
            }
            .pillar-card {
                background: #fff !important;
                border-radius: 8px !important;
                padding: 10px !important;
                box-shadow: none !important;
                border: 1.5px solid ${BRAND.gray200} !important;
                page-break-inside: avoid;
            }
            .pillar-card:hover {
                transform: none !important;
                box-shadow: none !important;
            }
            .pillar-card::before {
                width: 4px !important;
                border-radius: 0 !important;
            }
            .pillar-header {
                margin-bottom: 6px !important;
                gap: 6px !important;
            }
            .pillar-icon {
                width: 28px !important;
                height: 28px !important;
                border-radius: 6px !important;
            }
            .pillar-icon svg {
                width: 14px !important;
                height: 14px !important;
            }
            .pillar-name {
                font-size: 8pt !important;
                font-weight: 700 !important;
            }
            .pillar-score {
                font-size: 10pt !important;
            }
            .pillar-score span[style*="font-size: 0.9rem"] {
                font-size: 7pt !important;
            }
            .pillar-progress {
                height: 5px !important;
                margin: 4px 0 !important;
            }
            .pillar-stats {
                font-size: 7pt !important;
                margin-top: 2px;
            }

            /* ═══════════════════════════════════════
               RADAR CHART
               ═══════════════════════════════════════ */
            .charts-grid {
                margin-bottom: 16px !important;
                page-break-inside: avoid;
                max-width: 100% !important;
                overflow: hidden !important;
            }
            .chart-card {
                border-radius: 10px !important;
                box-shadow: none !important;
                border: 1.5px solid ${BRAND.gray200} !important;
            }
            .chart-card-header {
                padding: 8px 14px !important;
                font-size: 9pt !important;
                gap: 6px !important;
            }
            .chart-card-header svg {
                width: 16px !important;
                height: 16px !important;
            }
            .chart-card-body {
                padding: 10px 14px !important;
            }
            .chart-card-body > div {
                height: 220px !important;
                max-width: 360px !important;
            }
            .hicm-pdf-canvas-img {
                max-height: 220px !important;
            }

            /* ═══════════════════════════════════════
               DETAIL SECTION TITLE
               ═══════════════════════════════════════ */
            .pdf-section-header {
                background: linear-gradient(135deg, ${BRAND.primary}, ${BRAND.secondary}) !important;
                color: white !important;
                padding: 8px 16px !important;
                border-radius: 8px !important;
                margin: 8px 0 10px 0 !important;
                font-size: 11pt !important;
                font-weight: 700 !important;
                page-break-after: avoid;
            }

            /* Section heading (detail) */
            h2.text-2xl {
                font-size: 12pt !important;
                color: ${BRAND.primary} !important;
                margin: 4px 0 10px 0 !important;
                padding-bottom: 6px !important;
                border-bottom: 2px solid ${BRAND.accent} !important;
                font-weight: 700 !important;
            }

            /* ═══════════════════════════════════════
               PILLAR DETAIL CARDS (tables)
               ═══════════════════════════════════════ */
            .card.animate-fade-in-up,
            .grid.grid-cols-1 > .card {
                border-radius: 10px !important;
                box-shadow: none !important;
                border: 1.5px solid ${BRAND.gray200} !important;
                margin-bottom: 14px !important;
                overflow: hidden;
                page-break-inside: avoid;
            }

            /* Pillar card header */
            .card.animate-fade-in-up > div:first-child,
            .grid.grid-cols-1 > .card > div:first-child {
                padding: 10px 14px !important;
                background: ${BRAND.gray100} !important;
            }
            .card.animate-fade-in-up > div:first-child h3,
            .grid.grid-cols-1 > .card > div:first-child h3 {
                font-size: 10.5pt !important;
                font-weight: 700 !important;
            }
            .card.animate-fade-in-up > div:first-child p,
            .grid.grid-cols-1 > .card > div:first-child p {
                font-size: 7.5pt !important;
            }

            /* Icon in pillar header */
            .card.animate-fade-in-up > div:first-child > div > div:first-child,
            .grid.grid-cols-1 > .card > div:first-child > div > div:first-child {
                width: 36px !important;
                height: 36px !important;
                border-radius: 8px !important;
            }
            .card.animate-fade-in-up > div:first-child svg,
            .grid.grid-cols-1 > .card > div:first-child svg {
                width: 20px !important;
                height: 20px !important;
            }

            /* Score display in header */
            .card.animate-fade-in-up > div:first-child > div:last-child > div,
            .grid.grid-cols-1 > .card > div:first-child > div:last-child > div {
                font-size: 10pt !important;
            }
            .card.animate-fade-in-up > div:first-child > div:last-child span[style*="font-size: 0.8rem"],
            .grid.grid-cols-1 > .card > div:first-child > div:last-child span[style*="font-size: 0.8rem"] {
                font-size: 7pt !important;
            }

            /* ═══════════════════════════════════════
               TABLES — clean, professional formatting
               ═══════════════════════════════════════ */
            .table {
                width: 100% !important;
                max-width: 100% !important;
                border-collapse: collapse !important;
                font-size: 7.5pt !important;
                margin: 0 !important;
                table-layout: fixed !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
            }
            .table thead tr {
                background: ${BRAND.primary} !important;
            }
            .table thead th {
                color: white !important;
                font-weight: 700 !important;
                font-size: 7.5pt !important;
                padding: 6px 6px !important;
                text-align: center !important;
                border: none !important;
                white-space: normal !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
            }
            .table thead th:first-child {
                text-align: left !important;
                border-radius: 0 !important;
                width: 35% !important;
            }
            .table tbody tr {
                border-bottom: 1px solid ${BRAND.gray200} !important;
                page-break-inside: avoid;
            }
            .table tbody tr:nth-child(even) {
                background: #f8fafc !important;
            }
            .table tbody tr:hover {
                background: transparent !important;
            }
            .table tbody td {
                padding: 5px 6px !important;
                font-size: 7.5pt !important;
                vertical-align: top !important;
                border: none !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
            }
            .table tbody td:first-child {
                width: 35% !important;
            }
            .table tbody td:first-child > div:first-child {
                font-size: 7.5pt !important;
                font-weight: 600 !important;
                line-height: 1.4 !important;
            }
            .table tbody td:first-child > div:nth-child(2) {
                font-size: 6.5pt !important;
                line-height: 1.3 !important;
                color: ${BRAND.gray500} !important;
                margin-top: 1px !important;
            }

            /* Score cells */
            .table tbody td .text-center,
            .table tbody td[class*="text-center"] {
                font-size: 8pt !important;
            }

            /* Auditor score badge */
            .table tbody td div[style*="background-color: #eef2ff"] {
                padding: 2px 5px !important;
                border-radius: 4px !important;
                font-size: 7.5pt !important;
            }
            .table tbody td div[style*="background-color: #eef2ff"] > div:first-child {
                font-size: 8pt !important;
            }
            .table tbody td div[style*="background-color: #eef2ff"] > div:last-child {
                font-size: 6pt !important;
            }

            /* Individual auditor scores */
            .table tbody td div[style*="width: 16px"] {
                width: 12px !important;
                height: 12px !important;
                font-size: 5pt !important;
            }

            /* Status badges */
            .table tbody td span[style*="border-radius: 9999px"] {
                font-size: 6.5pt !important;
                padding: 2px 6px !important;
            }

            /* Evidence button — hide in PDF */
            .table tbody td button.btn {
                display: none !important;
            }

            /* Evidence & comment blocks */
            .table tbody td div[style*="border-left: 3px solid"] {
                font-size: 6.5pt !important;
                padding: 3px 6px !important;
                border-radius: 4px !important;
                line-height: 1.3 !important;
                margin-bottom: 3px !important;
            }
            .table tbody td div[style*="border-left: 3px solid"] strong {
                font-size: 6pt !important;
            }

            /* ═══════════════════════════════════════
               CARD FOOTER (Summary Stats)
               ═══════════════════════════════════════ */
            .card > div[style*="background-color: var(--gray-50)"] {
                padding: 8px 12px !important;
                gap: 8px !important;
            }
            .card > div[style*="background-color: var(--gray-50)"] > div {
                text-align: center;
            }
            .card > div[style*="background-color: var(--gray-50)"] > div > div:first-child {
                font-size: 7pt !important;
                margin-bottom: 2px !important;
            }
            .card > div[style*="background-color: var(--gray-50)"] > div > div:last-child {
                font-size: 12pt !important;
            }

            /* ═══════════════════════════════════════
               SIGNATURE AREA
               ═══════════════════════════════════════ */
            .print-signature-area {
                display: flex !important;
                justify-content: space-around;
                gap: 40px;
                margin: 30px 20px 10px 20px;
                padding-top: 20px;
                border-top: 2px solid ${BRAND.gray200};
                page-break-inside: avoid;
            }
            .print-signature-block {
                text-align: center;
                flex: 1;
                max-width: 250px;
            }
            .print-signature-line {
                border-bottom: 1px solid ${BRAND.gray700};
                margin-bottom: 6px;
                height: 40px;
            }
            .print-signature-label {
                font-size: 8.5pt;
                font-weight: 600;
                color: ${BRAND.gray700};
            }
            .print-signature-sublabel {
                font-size: 7.5pt;
                color: ${BRAND.gray500};
                margin-top: 4px;
            }

            /* ═══════════════════════════════════════
               DOCUMENT FOOTER
               ═══════════════════════════════════════ */
            .print-doc-footer {
                text-align: center;
                font-size: 7pt;
                color: ${BRAND.gray500};
                padding: 8px 0;
                margin-top: 10px;
                border-top: 1px solid ${BRAND.gray200};
            }

            /* ═══════════════════════════════════════
               PAGE BREAK HELPERS
               ═══════════════════════════════════════ */
            .pdf-page-break-before { page-break-before: always; }
            .pdf-page-break-after  { page-break-after: always; }
            .pdf-avoid-break       { page-break-inside: avoid; }

            /* Force avoid page-break in key areas */
            .score-showcase,
            .pillar-grid,
            .charts-grid { 
                page-break-inside: avoid !important; 
            }

            /* Pillar detail card: allow break but not within header or footer */
            .card.animate-fade-in-up > div:first-child {
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            .card.animate-fade-in-up > div:last-child {
                page-break-inside: avoid;
            }

            /* Each table row avoids break */
            tr { page-break-inside: avoid; }

            /* ─── Report/Export tables (non .table class) ─── */
            .report-table, .data-table, table:not(.table) {
                width: 100% !important;
                max-width: 100% !important;
                table-layout: fixed !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                border-collapse: collapse !important;
            }
            .report-table th, .data-table th, table:not(.table) th {
                white-space: normal !important;
                word-wrap: break-word !important;
                font-size: 7.5pt !important;
                padding: 5px 4px !important;
            }
            .report-table td, .data-table td, table:not(.table) td {
                white-space: normal !important;
                word-wrap: break-word !important;
                font-size: 7.5pt !important;
                padding: 4px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            /* ─── Grid containers — fit within page ─── */
            .pillar-grid, .score-showcase, .charts-grid, .filter-grid {
                max-width: 100% !important;
                overflow: hidden !important;
            }

            /* ─── Table container overflow fix ─── */
            .table-container, .table-responsive, [style*="overflow-x"] {
                overflow: visible !important;
                max-width: 100% !important;
            }
        `;
        doc.head.appendChild(style);
    }

    /* ═══════════════════════════════════════════════
       CLONE DOCUMENT TRANSFORMER
       — transforms the cloned DOM for professional output
       ═══════════════════════════════════════════════ */
    function transformClonedDoc(clonedDoc) {
        var content = clonedDoc.querySelector('.main-content');
        if (!content) return;

        /* 1. Inject professional PDF styles */
        injectPDFStyles(clonedDoc);

        /* 2. Remove unwanted elements from clone */
        content.querySelectorAll('.hero-nav').forEach(function(el) { el.remove(); });
        clonedDoc.querySelectorAll('#evidenceModal, #filePreviewModal, .modal, .toast-container').forEach(function(el) { el.remove(); });
        clonedDoc.querySelectorAll('.navbar, .sidebar, .sidebar-overlay').forEach(function(el) { el.remove(); });

        /* 3. Ensure print-only elements are visible */
        content.querySelectorAll('.print-only').forEach(function(el) {
            el.style.display = 'block';
        });

        /* 4. Kill all animations */
        content.querySelectorAll('.animate-in, .animate-fade-in-up').forEach(function(el) {
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.animation = 'none';
        });

        /* 5. Fix main-content layout — force fit A4 width */
        var mw = clonedDoc.querySelector('.main-wrapper');
        if (mw) {
            mw.style.margin = '0';
            mw.style.padding = '0';
            mw.style.maxWidth = '100%';
            mw.style.width = '100%';
            mw.style.overflowX = 'hidden';
        }
        content.style.margin = '0';
        content.style.padding = '0 4px';
        content.style.maxWidth = '100%';
        content.style.width = '100%';
        content.style.overflowX = 'hidden';

        /* 6. Fix body — force width constraints */
        clonedDoc.body.style.margin = '0';
        clonedDoc.body.style.padding = '0';
        clonedDoc.body.style.width = '100%';
        clonedDoc.body.style.maxWidth = '100%';
        clonedDoc.body.style.overflowX = 'hidden';
        clonedDoc.body.className = '';

        /* 6b. Force all tables to fit within container */
        content.querySelectorAll('table').forEach(function(tbl) {
            tbl.style.width = '100%';
            tbl.style.maxWidth = '100%';
            tbl.style.tableLayout = 'fixed';
            tbl.style.wordWrap = 'break-word';
        });

        /* 6c. Force all grids/flex to wrap and not overflow */
        content.querySelectorAll('[style*="grid-template-columns"], [style*="display: grid"], [style*="display:grid"]').forEach(function(el) {
            el.style.maxWidth = '100%';
            el.style.overflowX = 'hidden';
        });

        /* 7. Add page break before detail section for clean pagination */
        var detailSection = content.querySelector('div[style*="margin-bottom: 2rem"] > h2.text-2xl');
        if (detailSection) {
            var wrapper = detailSection.closest('div[style*="margin-bottom: 2rem"]');
            if (wrapper) {
                wrapper.classList.add('pdf-page-break-before');
            }
        }

        /* 8. Ensure evidence button column shows "-" instead of button */
        content.querySelectorAll('td button.btn.btn-sm.btn-outline').forEach(function(btn) {
            var td = btn.closest('td');
            if (td) {
                td.innerHTML = '<span style="color:#94a3b8;font-size:7pt;">—</span>';
            }
        });
    }

    /* ═══════════════════════════════════════════════
       PREPARE / RESTORE DOM for capture
       (minimal changes — onclone does the heavy lifting)
       ═══════════════════════════════════════════════ */
    function prepareDOM(container) {
        var saved = {
            elements: [],
            globalElements: [],
            mainContent: null,
            animated: []
        };

        /* Show print-only elements */
        container.querySelectorAll('.print-only').forEach(function(el) {
            saved.elements.push({ el: el, prop: 'display', val: el.style.display });
            el.style.display = 'block';
        });

        /* Hide interactive elements */
        var hideSelectors = '.no-print, .action-bar, .filter-section, .page-actions, .hero-nav';
        container.querySelectorAll(hideSelectors).forEach(function(el) {
            saved.elements.push({ el: el, prop: 'display', val: el.style.display });
            el.style.display = 'none';
        });

        /* Hide global chrome */
        document.querySelectorAll('.navbar, .sidebar, .sidebar-overlay, .modal, .toast-container, #hicm-pdf-overlay').forEach(function(el) {
            saved.globalElements.push({ el: el, display: el.style.display });
            el.style.display = 'none';
        });

        /* Full-width content */
        var mc = document.querySelector('.main-content');
        if (mc) {
            saved.mainContent = { el: mc, ml: mc.style.marginLeft, mt: mc.style.marginTop, mw: mc.style.maxWidth, pd: mc.style.padding };
            mc.style.marginLeft = '0';
            mc.style.marginTop = '0';
            mc.style.maxWidth = '100%';
            mc.style.padding = '0 8px';
        }

        /* Kill animations */
        container.querySelectorAll('.animate-in, .animate-fade-in-up').forEach(function(el) {
            saved.animated.push({ el: el, opacity: el.style.opacity, transform: el.style.transform, animation: el.style.animation });
            el.style.opacity = '1';
            el.style.transform = 'none';
            el.style.animation = 'none';
        });

        return saved;
    }

    function restoreDOM(saved) {
        if (!saved) return;
        saved.elements.forEach(function(item) { item.el.style[item.prop] = item.val; });
        saved.globalElements.forEach(function(item) { item.el.style.display = item.display; });
        if (saved.mainContent) {
            var mc = saved.mainContent;
            mc.el.style.marginLeft = mc.ml;
            mc.el.style.marginTop = mc.mt;
            mc.el.style.maxWidth = mc.mw;
            mc.el.style.padding = mc.pd;
        }
        saved.animated.forEach(function(item) {
            item.el.style.opacity = item.opacity;
            item.el.style.transform = item.transform;
            item.el.style.animation = item.animation;
        });
    }

    /* ═══════════════════════════════════════════════
       MERGE OPTIONS with onclone integration
       ═══════════════════════════════════════════════ */
    function mergeOptions(userOptions) {
        var opts = JSON.parse(JSON.stringify(DEFAULTS));

        /* Deep merge user options */
        if (userOptions) {
            Object.keys(userOptions).forEach(function(key) {
                if (key === 'html2canvas' && typeof userOptions[key] === 'object') {
                    Object.assign(opts.html2canvas, userOptions[key]);
                } else if (key === 'jsPDF' && typeof userOptions[key] === 'object') {
                    Object.assign(opts.jsPDF, userOptions[key]);
                } else if (key === 'pagebreak' && typeof userOptions[key] === 'object') {
                    Object.assign(opts.pagebreak, userOptions[key]);
                } else if (key === 'image' && typeof userOptions[key] === 'object') {
                    Object.assign(opts.image, userOptions[key]);
                } else {
                    opts[key] = userOptions[key];
                }
            });
        }

        /* Save user's onclone (if any) and wrap with our transformer */
        var userOnclone = opts.html2canvas.onclone || null;
        opts.html2canvas.onclone = function(clonedDoc) {
            transformClonedDoc(clonedDoc);
            if (typeof userOnclone === 'function') {
                userOnclone(clonedDoc);
            }
        };

        return opts;
    }

    /* ═══════════════════════════════════════════════
       CORE: Download PDF
       ═══════════════════════════════════════════════ */
    async function download(selector, filename, userOptions) {
        if (_busy) {
            toast('กำลังสร้าง PDF อยู่ กรุณารอ...', 'warning');
            return;
        }

        if (typeof html2pdf === 'undefined') {
            toast('ไม่สามารถโหลด PDF Library ได้ กรุณา refresh แล้วลองใหม่', 'error');
            console.error('[HICM_PDF] html2pdf.js is not loaded');
            return;
        }

        var container = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;

        if (!container) {
            toast('ไม่พบเนื้อหาที่ต้องการสร้าง PDF', 'error');
            return;
        }

        _busy = true;
        var savedDOM = null;
        var canvasRestorations = [];

        var opts = mergeOptions(userOptions);
        opts.filename = filename || opts.filename || 'HICM_Report.pdf';

        window.scrollTo(0, 0);
        opts.html2canvas.scrollY = 0;

        var overlay = createOverlay('กำลังเตรียมข้อมูล...');

        try {
            /* Step 1 — Prepare DOM */
            updateOverlay('กำลังเตรียมข้อมูล...', 'จัดเตรียมหน้าสำหรับสร้าง PDF', 10);
            savedDOM = prepareDOM(container);

            /* Step 2 — Convert canvases */
            updateOverlay('กำลังแปลงกราฟ...', 'แปลง Chart เป็นรูปภาพ', 25);
            canvasRestorations = convertCanvasesToImages(container);

            /* Wait for DOM repaint */
            await new Promise(function(r) { setTimeout(r, 500); });

            /* Step 3 — Generate PDF */
            updateOverlay('กำลังสร้าง PDF...', 'กำลังแปลงเนื้อหาเป็น PDF คุณภาพสูง', 50);

            await html2pdf()
                .set(opts)
                .from(container)
                .save();

            updateOverlay('เสร็จสิ้น!', 'ดาวน์โหลดไฟล์เรียบร้อยแล้ว', 100);
            await new Promise(function(r) { setTimeout(r, 600); });

            toast('ดาวน์โหลด PDF สำเร็จ', 'success');

        } catch (err) {
            console.error('[HICM_PDF] Download error:', err);
            toast('เกิดข้อผิดพลาดในการสร้าง PDF: ' + (err.message || ''), 'error');
        } finally {
            canvasRestorations.forEach(function(fn) { fn(); });
            restoreDOM(savedDOM);
            removeOverlay();
            _busy = false;
        }
    }

    /* ═══════════════════════════════════════════════
       PREVIEW: Open PDF in new browser tab
       ═══════════════════════════════════════════════ */
    async function preview(selector, userOptions) {
        if (_busy) {
            toast('กำลังสร้าง PDF อยู่ กรุณารอ...', 'warning');
            return;
        }

        if (typeof html2pdf === 'undefined') {
            toast('ไม่สามารถโหลด PDF Library ได้ กรุณา refresh แล้วลองใหม่', 'error');
            return;
        }

        var container = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;

        if (!container) {
            toast('ไม่พบเนื้อหาที่ต้องการสร้าง PDF', 'error');
            return;
        }

        _busy = true;
        var savedDOM = null;
        var canvasRestorations = [];

        var opts = mergeOptions(userOptions);
        opts.filename = 'preview.pdf';

        window.scrollTo(0, 0);
        opts.html2canvas.scrollY = 0;

        var overlay = createOverlay('กำลังสร้างตัวอย่าง PDF...');

        try {
            updateOverlay('กำลังเตรียมข้อมูล...', 'จัดเตรียมหน้าสำหรับสร้างตัวอย่าง', 10);
            savedDOM = prepareDOM(container);

            updateOverlay('กำลังแปลงกราฟ...', 'แปลง Chart เป็นรูปภาพ', 25);
            canvasRestorations = convertCanvasesToImages(container);

            await new Promise(function(r) { setTimeout(r, 500); });

            updateOverlay('กำลังสร้าง PDF...', 'กำลังแปลงเนื้อหาเป็น PDF คุณภาพสูง', 50);

            var pdfBlob = await html2pdf()
                .set(opts)
                .from(container)
                .output('blob');

            var url = URL.createObjectURL(pdfBlob);
            window.open(url, '_blank');
            setTimeout(function() { URL.revokeObjectURL(url); }, 30000);

            updateOverlay('เสร็จสิ้น!', 'เปิดตัวอย่าง PDF ในแท็บใหม่แล้ว', 100);
            await new Promise(function(r) { setTimeout(r, 500); });

            toast('เปิดตัวอย่าง PDF สำเร็จ', 'success');
        } catch (err) {
            console.error('[HICM_PDF] Preview error:', err);
            toast('เกิดข้อผิดพลาดในการสร้างตัวอย่าง PDF', 'error');
        } finally {
            canvasRestorations.forEach(function(fn) { fn(); });
            restoreDOM(savedDOM);
            removeOverlay();
            _busy = false;
        }
    }

    /* ═══════════════════════════════════════════════
       PRINT
       ═══════════════════════════════════════════════ */
    function print() {
        window.print();
    }

    /* ═══════════════════════════════════════════════
       PUBLIC API
       ═══════════════════════════════════════════════ */
    return {
        download: download,
        preview:  preview,
        print:    print,
        DEFAULTS: DEFAULTS
    };
})();
