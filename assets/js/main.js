/**
 * HICM V2025 Assessment System - Main JavaScript
 * ระบบแบบประเมินสถานประกอบการ HICM V2025
 */

// ============================================
// DOM Ready
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    // Initialize all modules
    initNavigation();
    initScoreSelectors();
    initFileUploads();
    initAnimations();
    initTooltips();
    initModals();
    initConfirmDialogs();
    initAutoSave();
    initCharts();
});

// ============================================
// Navigation
// ============================================
function initNavigation() {
    // Mobile menu toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        });

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
            });
        }
    }

    // Active nav link
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link, .sidebar-link').forEach(link => {
        if (link.getAttribute('href') && currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ============================================
// Score Selectors
// ============================================
function initScoreSelectors() {
    const scoreSelectors = document.querySelectorAll('.score-selector');

    scoreSelectors.forEach(selector => {
        const inputs = selector.querySelectorAll('input[type="radio"]');
        const indicatorId = selector.dataset.indicatorId;

        inputs.forEach(input => {
            input.addEventListener('change', function () {
                const score = parseFloat(this.value);
                updateScoreDisplay(indicatorId, score);
                calculatePillarScore(selector.closest('.assessment-pillar'));
                calculateTotalScore();

                // Trigger auto-save
                triggerAutoSave();

                // Show toast notification
                showToast('บันทึกคะแนนเรียบร้อย', 'success');
            });
        });
    });
}

function updateScoreDisplay(indicatorId, score) {
    const display = document.querySelector(`[data-score-display="${indicatorId}"]`);
    if (display) {
        display.textContent = score.toFixed(2);
        display.className = 'score-display ' + getScoreClass(score);
    }
}

function getScoreClass(score) {
    if (score >= 0.75) return 'score-excellent';
    if (score >= 0.5) return 'score-good';
    if (score >= 0.25) return 'score-average';
    return 'score-poor';
}

function calculatePillarScore(pillarElement) {
    if (!pillarElement) return;

    const selectors = pillarElement.querySelectorAll('.score-selector');
    let totalScore = 0;
    let count = 0;

    selectors.forEach(selector => {
        const checked = selector.querySelector('input[type="radio"]:checked');
        if (checked) {
            totalScore += parseFloat(checked.value);
            count++;
        }
    });

    const averageScore = count > 0 ? (totalScore / selectors.length) * 20 : 0;
    const pillarCode = pillarElement.dataset.pillarCode;

    // Update pillar score display
    const pillarScoreDisplay = document.querySelector(`[data-pillar-score="${pillarCode}"]`);
    if (pillarScoreDisplay) {
        pillarScoreDisplay.textContent = averageScore.toFixed(1);
    }

    // Update progress bar
    const progressBar = document.querySelector(`[data-pillar-progress="${pillarCode}"]`);
    if (progressBar) {
        const percentage = (totalScore / selectors.length) * 100;
        progressBar.style.width = percentage + '%';
    }
}

function calculateTotalScore() {
    const pillars = document.querySelectorAll('.assessment-pillar');
    let totalWeightedScore = 0;
    const pillarWeights = { H1: 300, I2: 300, C3: 200, M4: 200 };

    pillars.forEach(pillar => {
        const pillarCode = pillar.dataset.pillarCode;
        const selectors = pillar.querySelectorAll('.score-selector');
        let pillarTotal = 0;

        selectors.forEach(selector => {
            const checked = selector.querySelector('input[type="radio"]:checked');
            if (checked) {
                pillarTotal += parseFloat(checked.value);
            }
        });

        const maxScore = selectors.length;
        const weightedScore = (pillarTotal / maxScore) * pillarWeights[pillarCode];
        totalWeightedScore += weightedScore;
    });

    // Update total score display
    const totalScoreDisplay = document.querySelector('[data-total-score]');
    if (totalScoreDisplay) {
        totalScoreDisplay.textContent = totalWeightedScore.toFixed(0);
    }

    // Update HICM level
    updateHICMLevel(totalWeightedScore);
}

function updateHICMLevel(score) {
    const levelDisplay = document.querySelector('[data-hicm-level]');
    if (!levelDisplay) return;

    const levels = [
        { min: 0, max: 599, level: 1, name: 'เริ่มต้น', nameEn: 'Emerging' },
        { min: 600, max: 699, level: 2, name: 'กำลังพัฒนา', nameEn: 'Developing' },
        { min: 700, max: 799, level: 3, name: 'พัฒนาดี', nameEn: 'Performing' },
        { min: 800, max: 899, level: 4, name: 'เป็นเลิศ', nameEn: 'Excellence' },
        { min: 900, max: 1000, level: 5, name: 'ระดับโลก', nameEn: 'World-Class' }
    ];

    const currentLevel = levels.find(l => score >= l.min && score <= l.max) || levels[0];

    levelDisplay.innerHTML = `
        <div class="text-center">
            <div class="text-3xl font-bold text-primary-600 mb-1">Level ${currentLevel.level}</div>
            <div class="text-lg font-semibold text-gray-700">${currentLevel.name}</div>
            <div class="text-sm text-gray-500">${currentLevel.nameEn}</div>
        </div>
    `;
}

// ============================================
// File Uploads
// ============================================
function initFileUploads() {
    const uploadZones = document.querySelectorAll('.file-upload');

    uploadZones.forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        const fileList = zone.nextElementSibling;

        if (!input) return;

        // Click to upload
        zone.addEventListener('click', (e) => {
            // Prevent triggering if clicked on the input itself (to avoid double dialog)
            if (e.target !== input) {
                input.click();
            }
        });

        // Drag and drop
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');

            const files = e.dataTransfer.files;
            handleFiles(files, fileList, zone.dataset.indicatorId, zone.dataset.assessmentId);
        });

        // File input change
        input.addEventListener('change', (e) => {
            handleFiles(e.target.files, fileList, zone.dataset.indicatorId, zone.dataset.assessmentId);
        });
    });
}

function handleFiles(files, fileListContainer, indicatorId, assessmentId = null) {
    if (!fileListContainer) return;

    Array.from(files).forEach(file => {
        // Validate file
        if (!validateFile(file)) return;

        // Create file item
        const fileItem = createFileItem(file, indicatorId);
        fileListContainer.appendChild(fileItem);

        // Upload file
        uploadFile(file, indicatorId, fileItem, assessmentId);
    });
}

function validateFile(file) {
    const allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];

    const maxSize = 10 * 1024 * 1024; // 10MB

    if (!allowedTypes.includes(file.type)) {
        showToast('ไฟล์ไม่รองรับ กรุณาอัปโหลดไฟล์รูปภาพ, PDF, หรือเอกสาร Office', 'error');
        return false;
    }

    if (file.size > maxSize) {
        showToast('ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 10MB)', 'error');
        return false;
    }

    return true;
}

function createFileItem(file, indicatorId) {
    const div = document.createElement('div');
    div.className = 'file-item animate-fade-in';
    div.dataset.fileName = file.name;

    const fileIcon = getFileIcon(file.type);
    const fileSize = formatFileSize(file.size);

    div.innerHTML = `
        <div class="file-item-icon">
            ${fileIcon}
        </div>
        <div class="file-item-info">
            <div class="file-item-name">${escapeHtml(file.name)}</div>
            <div class="file-item-size">${fileSize}</div>
        </div>
        <div class="file-item-actions">
            <button type="button" class="btn-view-file" style="display: none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                ดูไฟล์
            </button>
            <div class="file-item-status">
                <span class="loading-spinner loading-spinner-sm"></span>
            </div>
            <div class="upload-progress-container active">
                <div class="upload-progress-bar" style="width: 0%"></div>
            </div>
            <button type="button" class="btn-delete-file" onclick="deleteFile(null, this)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/>
                </svg>
            </button>
        </div>
    `;

    return div;
}

function getFileIcon(mimeType) {
    if (mimeType.startsWith('image/')) {
        return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <path d="M21 15l-5-5L5 21"/>
        </svg>`;
    }
    if (mimeType === 'application/pdf') {
        return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
        </svg>`;
    }
    return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
    </svg>`;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function uploadFile(file, indicatorId, fileItem, assessmentId = null) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('indicator_id', indicatorId);
    if (assessmentId) {
        formData.append('assessment_id', assessmentId);
    }

    const apiUrl = window.APP_CONFIG ? window.APP_CONFIG.apiUrl : 'api';
    const xhr = new XMLHttpRequest();

    // Progress handler
    xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            const progressBar = fileItem.querySelector('.upload-progress-bar');
            if (progressBar) {
                progressBar.style.width = percentComplete + '%';
            }
        }
    });

    // Load handler (success/error)
    xhr.addEventListener('load', () => {
        const progressBarContainer = fileItem.querySelector('.upload-progress-container');
        if (progressBarContainer) {
            setTimeout(() => {
                progressBarContainer.classList.remove('active');
            }, 500);
        }

        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const result = JSON.parse(xhr.responseText);
                if (result.success) {
                    const statusEl = fileItem.querySelector('.file-item-status');
                    statusEl.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>`;
                    fileItem.dataset.fileId = result.file_id;

                    // Show and update view button
                    const viewBtn = fileItem.querySelector('.btn-view-file');
                    if (viewBtn) {
                        const baseUrl = window.APP_CONFIG ? window.APP_CONFIG.baseUrl : '';
                        const downloadUrl = `${baseUrl}/api/download.php?id=${result.file_id}`;
                        viewBtn.style.display = 'flex';
                        viewBtn.onclick = () => previewFile(result.file_id, result.original_name, downloadUrl, result.file_type);
                    }

                    showToast('อัปโหลดไฟล์สำเร็จ', 'success');
                } else {
                    handleUploadError(fileItem, result.message);
                }
            } catch (e) {
                handleUploadError(fileItem, 'Invalid response');
            }
        } else {
            handleUploadError(fileItem, 'HTTP Error ' + xhr.status);
        }
    });

    // Error handler
    xhr.addEventListener('error', () => {
        handleUploadError(fileItem, 'Network Error');
    });

    xhr.open('POST', `${apiUrl}/upload.php`);
    xhr.send(formData);
}

function handleUploadError(fileItem, message) {
    const statusEl = fileItem.querySelector('.file-item-status');
    statusEl.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2">
        <circle cx="12" cy="12" r="10"/>
        <line x1="15" y1="9" x2="9" y2="15"/>
        <line x1="9" y1="9" x2="15" y2="15"/>
    </svg>`;

    const progressBarContainer = fileItem.querySelector('.upload-progress-container');
    if (progressBarContainer) {
        progressBarContainer.classList.remove('active');
    }

    showToast('อัปโหลดไฟล์ไม่สำเร็จ: ' + message, 'error');
}

// Helper for custom confirmation modal
function showCustomConfirm() {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');

        if (!modal || !okBtn || !cancelBtn) {
            resolve(confirm('คุณแน่ใจหรือไม่ที่จะลบไฟล์นี้?'));
            return;
        }

        const onOk = () => {
            modal.classList.remove('active');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(true);
        };

        const onCancel = () => {
            modal.classList.remove('active');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(false);
        };

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);

        modal.classList.add('active');
    });
}

async function deleteFile(fileId, button) {
    const fileItem = button.closest('.file-item');
    const id = fileId || fileItem.dataset.fileId;

    if (id) {
        const confirmed = await showCustomConfirm();
        if (!confirmed) return;

        const apiUrl = window.APP_CONFIG ? window.APP_CONFIG.apiUrl : 'api';
        fetch(`${apiUrl}/delete-file.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file_id: id })
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    fileItem.remove();
                    showToast('ลบไฟล์สำเร็จ', 'success');
                } else {
                    showToast('ลบไฟล์ไม่สำเร็จ: ' + result.message, 'error');
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                showToast('เกิดข้อผิดพลาดในการลบไฟล์', 'error');
            });
    } else {
        fileItem.remove();
    }
}

// ============================================
// Animations
// ============================================
function initAnimations() {
    // Intersection Observer for scroll animations
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe elements with data-animate attribute
    document.querySelectorAll('[data-animate]').forEach(el => {
        el.style.opacity = '0';
        observer.observe(el);
    });

    // Counter animation
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter);
        animateCounter(el, target);
    });
}

function animateCounter(element, target, duration = 1000) {
    const start = 0;
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const current = Math.floor(start + (target - start) * easeOutQuart);

        element.textContent = current.toLocaleString();

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            element.textContent = target.toLocaleString();
        }
    }

    requestAnimationFrame(update);
}

// ============================================
// Tooltips
// ============================================
function initTooltips() {
    const tooltipTriggers = document.querySelectorAll('[data-tooltip]');

    tooltipTriggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', showTooltip);
        trigger.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const text = this.dataset.tooltip;
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;

    document.body.appendChild(tooltip);

    const rect = this.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    tooltip.style.left = rect.left + (rect.width / 2) - (tooltipRect.width / 2) + 'px';
    tooltip.style.top = rect.top - tooltipRect.height - 8 + 'px';

    this._tooltip = tooltip;
}

function hideTooltip() {
    if (this._tooltip) {
        this._tooltip.remove();
        this._tooltip = null;
    }
}

// ============================================
// Modals
// ============================================
function initModals() {
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });

    // Close modal on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.active').forEach(closeModal);
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modal) {
    if (typeof modal === 'string') {
        modal = document.getElementById(modal);
    }
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// ============================================
// Confirm Dialogs
// ============================================
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            const message = this.dataset.confirm;
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

// ============================================
// Auto Save
// ============================================
let autoSaveTimeout;

function initAutoSave() {
    const form = document.querySelector('.assessment-form');
    if (form) {
        form.addEventListener('change', triggerAutoSave);
        form.addEventListener('input', debounce(triggerAutoSave, 2000));
    }
}

function triggerAutoSave() {
    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(saveAssessment, 3000);
}

async function saveAssessment() {
    const form = document.querySelector('.assessment-form');
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        const apiUrl = window.APP_CONFIG ? window.APP_CONFIG.apiUrl : 'api';
        const response = await fetch(`${apiUrl}/save-assessment.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            showToast('บันทึกอัตโนมัติสำเร็จ', 'success');
            updateLastSavedTime();
        }
    } catch (error) {
        console.error('Auto-save failed:', error);
    }
}

function updateLastSavedTime() {
    const el = document.querySelector('[data-last-saved]');
    if (el) {
        const now = new Date();
        el.textContent = 'บันทึกล่าสุด: ' + now.toLocaleTimeString('th-TH');
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ============================================
// Toast Notifications
// ============================================
function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${message}</span>
    `;

    container.appendChild(toast);

    // Auto remove after 3 seconds (shorter for minimal toast)
    setTimeout(() => {
        toast.style.animation = 'toastFadeOut 0.25s ease-out forwards';
        setTimeout(() => toast.remove(), 250);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
    return container;
}

// ============================================
// Charts
// ============================================
function initCharts() {
    // Radar Chart for HICM Scores
    const radarCanvas = document.getElementById('radarChart');
    if (radarCanvas && typeof Chart !== 'undefined') {
        initRadarChart(radarCanvas);
    }

    // Bar Chart for Pillar Comparison
    const barCanvas = document.getElementById('barChart');
    if (barCanvas && typeof Chart !== 'undefined') {
        initBarChart(barCanvas);
    }
}

function initRadarChart(canvas) {
    // Check if chart already exists on this canvas - skip if so
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        return; // Chart already initialized, don't create another
    }
    
    const ctx = canvas.getContext('2d');
    const scores = JSON.parse(canvas.dataset.scores || '{}');

    new Chart(ctx, {
        type: 'radar',
        data: {
            labels: [
                ['H1', 'Health Promotion'],
                ['I2', 'Industrial Safety', '& Environment'],
                ['C3', 'Community', 'Engagement'],
                ['M4', 'Management', '& Sustainability']
            ],
            datasets: [{
                label: 'คะแนนประเมินตนเอง',
                data: [
                    scores.H1?.self || 0,
                    scores.I2?.self || 0,
                    scores.C3?.self || 0,
                    scores.M4?.self || 0
                ],
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgba(59, 130, 246, 1)',
                pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(59, 130, 246, 1)'
            }, {
                label: 'คะแนนกรรมการ',
                data: [
                    scores.H1?.auditor || 0,
                    scores.I2?.auditor || 0,
                    scores.C3?.auditor || 0,
                    scores.M4?.auditor || 0
                ],
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: 'rgba(16, 185, 129, 1)',
                pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgba(16, 185, 129, 1)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function initBarChart(canvas) {
    const ctx = canvas.getContext('2d');
    const data = JSON.parse(canvas.dataset.chartData || '[]');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.name),
            datasets: [{
                label: 'คะแนนรวม',
                data: data.map(d => d.score),
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(139, 92, 246, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 1000
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// ============================================
// Export Functions
// ============================================
function exportToPDF() {
    if (typeof HICM_PDF !== 'undefined') {
        HICM_PDF.download('.main-content', 'HICM_Report_' + new Date().toISOString().slice(0,10) + '.pdf');
    } else {
        window.print();
    }
}

async function exportToExcel() {
    try {
        const apiUrl = window.APP_CONFIG ? window.APP_CONFIG.apiUrl : 'api';
        const response = await fetch(`${apiUrl}/export-excel.php`, {
            method: 'POST'
        });

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'HICM_Assessment_Report.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showToast('ส่งออก Excel สำเร็จ', 'success');
    } catch (error) {
        showToast('ส่งออกไม่สำเร็จ', 'error');
    }
}

// Function to export data to Excel using SheetJS
function exportToExcel(data, filename) {
    // Create a new workbook
    const workbook = XLSX.utils.book_new();

    // Convert data to a worksheet
    const worksheet = XLSX.utils.json_to_sheet(data);

    // Append the worksheet to the workbook
    XLSX.utils.book_append_sheet(workbook, worksheet, 'Sheet1');

    // Write the workbook and trigger download
    XLSX.writeFile(workbook, filename);
}

// Example usage: Trigger export with sample data
function triggerExport() {
    const sampleData = [
        { ID: 1, Name: 'John Doe', Role: 'Admin' },
        { ID: 2, Name: 'Jane Smith', Role: 'User' }
    ];

    exportToExcel(sampleData, 'export.xlsx');
}

// ============================================
// Utility Functions
// ============================================
function formatNumber(num) {
    return num.toLocaleString('th-TH');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('th-TH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Add CSS animation for toast fade out
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(20px); }
    }
    
    .tooltip {
        position: absolute;
        background-color: var(--gray-800);
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        white-space: nowrap;
        z-index: 1000;
        pointer-events: none;
    }
    
    .tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 5px solid transparent;
        border-top-color: var(--gray-800);
    }
`;
document.head.appendChild(style);
