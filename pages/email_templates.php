<?php
/**
 * HICM V2025 - Email Templates Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/email.php';

requireAuth();

if (!hasRole(ROLE_ADMIN)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// Handle template update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_template') {
    $templateKey = sanitizeInput($_POST['template_key']);
    $subject = sanitizeInput($_POST['subject']);
    $body = $_POST['body']; // Allow HTML
    
    if (updateEmailTemplate($templateKey, $subject, $body)) {
        setFlashMessage('อัปเดตเทมเพลตอีเมลเรียบร้อยแล้ว', 'success');
    } else {
        setFlashMessage('เกิดข้อผิดพลาดในการอัปเดตเทมเพลต', 'error');
    }
    
    redirect(getBaseUrl() . '/pages/email_templates.php');
}

$templates = getAllEmailTemplates();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเทมเพลตอีเมล - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .template-card {
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-500);
        }
        
        .template-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .template-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .template-desc {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
        }
        
        .variables-list {
            background: var(--gray-50);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        
        .variable-tag {
            display: inline-block;
            background: var(--primary-100);
            color: var(--primary-700);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-family: 'Courier New', monospace;
            margin: 0.25rem;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-large {
            background: white;
            border-radius: var(--radius-xl);
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
            
            <div class="page-header">
                <h1 class="page-title">จัดการเทมเพลตอีเมล</h1>
                <p class="page-subtitle">แก้ไขข้อความและรูปแบบอีเมลที่ส่งไปยังผู้ใช้</p>
            </div>
            
            <div class="row">
                <?php foreach ($templates as $template): 
                    $variables = json_decode($template['variables'], true) ?? [];
                ?>
                <div class="col-md-6">
                    <div class="card template-card">
                        <div class="card-body">
                            <div class="template-header">
                                <div>
                                    <div class="template-title"><?php echo htmlspecialchars($template['description']); ?></div>
                                    <small class="text-muted">Key: <?php echo htmlspecialchars($template['template_key']); ?></small>
                                </div>
                                <button class="btn btn-primary btn-sm" onclick="editTemplate('<?php echo htmlspecialchars($template['template_key']); ?>')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    แก้ไข
                                </button>
                            </div>
                            
                            <div class="template-desc">
                                <strong>หัวข้อ:</strong> <?php echo htmlspecialchars($template['subject']); ?>
                            </div>
                            
                            <?php if (!empty($variables)): ?>
                            <div class="variables-list">
                                <small style="font-weight: 600; color: var(--gray-700);">ตัวแปรที่ใช้ได้:</small><br>
                                <?php foreach ($variables as $var): ?>
                                    <span class="variable-tag">{<?php echo htmlspecialchars($var); ?>}</span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    
    <!-- Edit Template Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-large">
            <div class="modal-header">
                <h3 id="modalTitle">แก้ไขเทมเพลต</h3>
                <button type="button" class="btn-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_template">
                <input type="hidden" name="template_key" id="editTemplateKey">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label required">หัวข้ออีเมล (Subject)</label>
                        <input type="text" name="subject" id="editSubject" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">เนื้อหาอีเมล (HTML)</label>
                        <textarea name="body" id="editBody" class="form-textarea" rows="15" required></textarea>
                        <small class="form-hint">รองรับ HTML และตัวแปรในรูปแบบ {variable_name}</small>
                    </div>
                    
                    <div id="variablesHelp" class="variables-list"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        const templates = <?php echo json_encode($templates); ?>;
        
        function editTemplate(key) {
            const template = templates.find(t => t.template_key === key);
            if (!template) return;
            
            document.getElementById('modalTitle').innerText = 'แก้ไข: ' + template.description;
            document.getElementById('editTemplateKey').value = template.template_key;
            document.getElementById('editSubject').value = template.subject;
            document.getElementById('editBody').value = template.body;
            
            // Show variables
            const variables = JSON.parse(template.variables || '[]');
            const varsHelp = document.getElementById('variablesHelp');
            if (variables.length > 0) {
                varsHelp.innerHTML = '<small style="font-weight: 600; color: var(--gray-700);">ตัวแปรที่ใช้ได้:</small><br>';
                variables.forEach(v => {
                    varsHelp.innerHTML += '<span class="variable-tag">{' + v + '}</span>';
                });
            } else {
                varsHelp.innerHTML = '';
            }
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
        }
    </script>
</body>
</html>
