<?php
/**
 * HICM V2025 Assessment System - System Settings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';

requireAuth();

if (!hasRole(ROLE_ADMIN)) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Test Email
    if (isset($_POST['action']) && $_POST['action'] === 'test_email') {
        require_once __DIR__ . '/../includes/email.php';
        
        $testEmail = sanitizeInput($_POST['test_email']);
        $templateKey = sanitizeInput($_POST['template_key'] ?? 'welcome');
        
        if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            // Sample variables for testing
            $variables = [
                'user_name' => 'ผู้ทดสอบ',
                'user_email' => $testEmail,
                'user_role' => 'ผู้ดูแลระบบ',
                'app_name' => getVal('app_name', 'HICM V2025'),
                'login_url' => getBaseUrl(),
                'contact_email' => getVal('contact_email'),
                'change_time' => date('Y-m-d H:i:s'),
                'account_status' => 'เปิดใช้งาน',
                'reason' => 'ทดสอบระบบ',
                'assessment_name' => 'การประเมินทดสอบ',
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d', strtotime('+30 days')),
                'assessment_url' => getBaseUrl(),
                'deadline_date' => date('Y-m-d', strtotime('+7 days')),
                'days_remaining' => '7',
                'submit_time' => date('Y-m-d H:i:s'),
                'score' => '85',
                'level' => 'ดีเยี่ยม',
                'results_url' => getBaseUrl(),
                'update_title' => 'อัปเดตระบบเวอร์ชัน 2.0',
                'update_details' => 'เพิ่มฟีเจอร์ใหม่และปรับปรุงประสิทธิภาพ',
                'update_date' => date('Y-m-d'),
                'start_time' => date('Y-m-d H:i:s'),
                'end_time' => date('Y-m-d H:i:s', strtotime('+2 hours'))
            ];
            
            if (sendTemplatedEmail($testEmail, $templateKey, $variables)) {
                setFlashMessage('ส่งอีเมลทดสอบไปยัง ' . $testEmail . ' เรียบร้อยแล้ว', 'success');
            } else {
                setFlashMessage('ไม่สามารถส่งอีเมลได้ กรุณาตรวจสอบการตั้งค่า SMTP', 'error');
            }
        } else {
            setFlashMessage('รูปแบบอีเมลไม่ถูกต้อง', 'error');
        }
        
        redirect(getBaseUrl() . '/pages/settings.php');
        exit;
    }
    
    // Process setting sections individually
    $action = $_POST['action'] ?? '';
    $settingsToUpdate = [];
    $message = 'บันทึกการตั้งค่าเรียบร้อยแล้ว';
    
    if ($action === 'general_settings') {
        $keys = ['app_name', 'app_desc', 'contact_email', 'contact_phone', 'current_period_year', 'max_upload_size'];
        foreach ($keys as $key) if (isset($_POST[$key])) $settingsToUpdate[$key] = sanitizeInput($_POST[$key]);
        $message = 'บันทึกข้อมูลทั่วไปเรียบร้อยแล้ว';
    } 
    elseif ($action === 'smtp_settings') {
        $keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_name'];
        foreach ($keys as $key) if (isset($_POST[$key])) $settingsToUpdate[$key] = sanitizeInput($_POST[$key]);
        $settingsToUpdate['smtp_enabled'] = isset($_POST['smtp_enabled']) ? 'true' : 'false';
        $message = 'บันทึกการตั้งค่า SMTP เรียบร้อยแล้ว';
    } 
    elseif ($action === 'notifications') {
        $keys = [
            'email_notify_registration', 'email_notify_password_reset', 'email_notify_account_status',
            'email_notify_assessment_open', 'email_notify_assessment_deadline', 'email_notify_assessment_submitted',
            'email_notify_assessment_results', 'email_notify_system_updates', 'email_notify_maintenance'
        ];
        foreach ($keys as $key) $settingsToUpdate[$key] = isset($_POST[$key]) ? 'true' : 'false';
        $message = 'บันทึกการแจ้งเตือนเรียบร้อยแล้ว';
    } 
    elseif ($action === 'system_control') {
        $settingsToUpdate['assessment_open'] = isset($_POST['assessment_open']) ? 'true' : 'false';
        $settingsToUpdate['maintenance_mode'] = isset($_POST['maintenance_mode']) ? 'true' : 'false';
        $settingsToUpdate['demo_accounts_enabled'] = isset($_POST['demo_accounts_enabled']) ? 'true' : 'false';
        $message = 'บันทึกการควบคุมระบบเรียบร้อยแล้ว';
    }
    elseif ($action === 'system_info') {
        if (isset($_POST['app_version'])) $settingsToUpdate['app_version'] = sanitizeInput($_POST['app_version']);
        $message = 'บันทึกข้อมูลเวอร์ชันเรียบร้อยแล้ว';
    }

    if (!empty($settingsToUpdate)) {
        $result = updateMultipleSettings($settingsToUpdate);
        if ($result['success']) {
            setFlashMessage($message, 'success');
        } else {
            setFlashMessage('เกิดข้อผิดพลาด: ' . $result['message'], 'error');
        }
    }
    
    redirect(getBaseUrl() . '/pages/settings.php');
    exit;
}

$settingsList = getAllSettings();
$settings = [];
foreach ($settingsList as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// Helpers for value
function getVal($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Get the latest update timestamp from settings
$stmtLastUpdate = getDB()->getConnection()->query("SELECT MAX(updated_at) as last_update FROM settings");
$lastUpdateRow = $stmtLastUpdate->fetch(PDO::FETCH_ASSOC);
$lastUpdate = ($lastUpdateRow && $lastUpdateRow['last_update']) ? $lastUpdateRow['last_update'] : 'ไม่ระบุ';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .settings-section {
            margin-bottom: 2rem;
        }
        
        .settings-section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .settings-section-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-lg);
            font-size: 1.25rem;
        }
        
        .settings-section-icon.blue {
            background: var(--primary-50);
            color: var(--primary-500);
        }
        
        .settings-section-icon.green {
            background: var(--success-light);
            color: var(--success);
        }
        
        .settings-section-icon.orange {
            background: var(--warning-light);
            color: var(--warning);
        }
        
        .settings-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }
        
        .settings-section-desc {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin: 0;
        }
        
        .notification-group {
            margin-bottom: 1.5rem;
        }
        
        .notification-group-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: var(--radius-lg);
            transition: background-color var(--transition-base);
        }
        
        .notification-item:hover {
            background-color: var(--gray-50);
        }
        
        .notification-checkbox {
            margin-top: 0.25rem;
        }
        
        .notification-label {
            flex: 1;
        }
        
        .notification-title {
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .notification-desc {
            font-size: 0.8125rem;
            color: var(--gray-500);
        }
        
        .info-card {
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--primary-100) 100%);
            border: 1px solid var(--primary-200);
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--primary-200);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .info-value {
            color: var(--gray-900);
            font-family: 'Courier New', monospace;
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
                <h1 class="page-title">ตั้งค่าระบบ</h1>
                <p class="page-subtitle">จัดการการตั้งค่าพื้นฐานของระบบและการแจ้งเตือนทางอีเมล</p>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- General Settings -->
                    <form method="POST" onsubmit="return showSaveConfirm(this, 'บันทึกข้อมูลทั่วไป', 'คุณต้องการบันทึกข้อมูลพื้นฐานของระบบและช่องทางติดต่อใช่หรือไม่?');">
                        <input type="hidden" name="action" value="general_settings">
                        <div class="card settings-section">
                            <div class="card-body">
                                <div class="settings-section-header" style="justify-content: space-between; align-items: center; display: flex;">
                                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                                        <div class="settings-section-icon blue">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="3"></circle>
                                                <path d="M12 1v6m0 6v6m8.66-15.66l-4.24 4.24m-8.48 8.48l-4.24 4.24M23 12h-6m-6 0H1m20.66 8.66l-4.24-4.24m-8.48-8.48l-4.24-4.24"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-section-title">ข้อมูลทั่วไป</h3>
                                            <p class="settings-section-desc">ข้อมูลพื้นฐานของระบบและช่องทางติดต่อ</p>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary" title="บันทึกข้อมูลทั่วไป" style="padding: 0.5rem; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label required">ชื่อระบบ (Application Name)</label>
                                    <input type="text" name="app_name" class="form-input" value="<?php echo htmlspecialchars(getVal('app_name')); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">คำอธิบายระบบ</label>
                                    <textarea name="app_desc" class="form-textarea" rows="2"><?php echo htmlspecialchars(getVal('app_desc')); ?></textarea>
                                    <small class="form-hint">แสดงในหน้าแรกและหน้าเข้าสู่ระบบ</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label required">อีเมลผู้ติดต่อ</label>
                                            <input type="email" name="contact_email" class="form-input" value="<?php echo htmlspecialchars(getVal('contact_email')); ?>" required>
                                            <small class="form-hint">ใช้เป็นที่อยู่ผู้ส่งในอีเมลระบบทั้งหมด</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">เบอร์โทรศัพท์ผู้ติดต่อ</label>
                                            <input type="text" name="contact_phone" class="form-input" value="<?php echo htmlspecialchars(getVal('contact_phone')); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label class="form-label">ปีการประเมินปัจจุบัน</label>
                                            <input type="number" name="current_period_year" class="form-input" value="<?php echo htmlspecialchars(getVal('current_period_year')); ?>">
                                            <small class="form-hint">ใช้สำหรับกำหนดรอบปัจจุบันเริ่มต้น</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label class="form-label">ขนาดไฟล์อัปโหลดสูงสุด (Bytes)</label>
                                            <input type="number" name="max_upload_size" class="form-input" value="<?php echo htmlspecialchars(getVal('max_upload_size', 10485760)); ?>">
                                            <small class="form-hint">10 MB = 10485760 bytes</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- SMTP Configuration -->
                    <form method="POST" onsubmit="return showSaveConfirm(this, 'บันทึกการตั้งค่า SMTP', 'คุณต้องการบันทึกการตั้งค่าเซิร์ฟเวอร์สำหรับส่งอีเมลใช่หรือไม่?');">
                        <input type="hidden" name="action" value="smtp_settings">
                        <div class="card settings-section">
                            <div class="card-body">
                                <div class="settings-section-header" style="justify-content: space-between; align-items: center; display: flex;">
                                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                                        <div class="settings-section-icon" style="background: var(--warning-light); color: var(--warning);">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-section-title">การตั้งค่า SMTP</h3>
                                            <p class="settings-section-desc">กำหนดค่าเซิร์ฟเวอร์สำหรับส่งอีเมล</p>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary" title="บันทึกการตั้งค่า SMTP" style="padding: 0.5rem; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                            <polyline points="7 3 7 8 15 8"></polyline>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="smtpEnabled" name="smtp_enabled" <?php echo getVal('smtp_enabled') == 'true' ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="smtpEnabled">เปิดใช้งาน SMTP</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">หากปิด จะใช้ฟังก์ชัน mail() ของ PHP</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label class="form-label">SMTP Host</label>
                                            <input type="text" name="smtp_host" class="form-input" value="<?php echo htmlspecialchars(getVal('smtp_host', 'smtp.gmail.com')); ?>" placeholder="smtp.gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label">Port</label>
                                            <input type="number" name="smtp_port" class="form-input" value="<?php echo htmlspecialchars(getVal('smtp_port', '587')); ?>" placeholder="587">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">SMTP Username</label>
                                            <input type="text" name="smtp_username" class="form-input" value="<?php echo htmlspecialchars(getVal('smtp_username')); ?>" placeholder="your-email@gmail.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label">SMTP Password</label>
                                            <input type="password" name="smtp_password" class="form-input" value="<?php echo htmlspecialchars(getVal('smtp_password')); ?>" placeholder="••••••••">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label class="form-label">Encryption</label>
                                            <select name="smtp_encryption" class="form-input">
                                                <option value="tls" <?php echo getVal('smtp_encryption') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo getVal('smtp_encryption') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo getVal('smtp_encryption') == 'none' ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group mb-0">
                                            <label class="form-label">ชื่อผู้ส่ง</label>
                                            <input type="text" name="smtp_from_name" class="form-input" value="<?php echo htmlspecialchars(getVal('smtp_from_name', 'HICM V2025 System')); ?>" placeholder="HICM V2025">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                        
                        <!-- Email Test -->
                        <div class="card settings-section" style="border: 2px solid var(--success); background: linear-gradient(135deg, var(--success-light) 0%, #f0fdf4 100%);">
                            <div class="card-body" style="padding: 1.25rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                    <div class="settings-section-header" style="margin-bottom: 0;">
                                        <div class="settings-section-icon green">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                <polyline points="22,6 12,13 2,6"></polyline>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="settings-section-title">ทดสอบการส่งอีเมล</h3>
                                            <p class="settings-section-desc">ส่งอีเมลทดสอบเพื่อตรวจสอบการตั้งค่าและเทมเพลต</p>
                                        </div>
                                    </div>
                                    <a href="<?php echo getBaseUrl(); ?>/pages/email_templates.php" class="btn btn-sm" style="background: white; color: var(--success); font-size: 0.875rem; padding: 0.5rem 1rem;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                                            <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        จัดการเทมเพลต
                                    </a>
                                </div>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="test_email">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label class="form-label">เลือกเทมเพลต</label>
                                                <select name="template_key" class="form-input">
                                                    <option value="welcome">ยินดีต้อนรับผู้ใช้ใหม่</option>
                                                    <option value="password_reset">การเปลี่ยนรหัสผ่าน</option>
                                                    <option value="account_status">การเปลี่ยนสถานะบัญชี</option>
                                                    <option value="assessment_open">เปิดรอบการประเมิน</option>
                                                    <option value="assessment_deadline">เตือนกำหนดส่ง</option>
                                                    <option value="assessment_submitted">ยืนยันการรับแบบประเมิน</option>
                                                    <option value="assessment_results">ประกาศผลการประเมิน</option>
                                                    <option value="system_update">อัปเดตระบบ</option>
                                                    <option value="maintenance">แจ้งปิดปรับปรุงระบบ</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label class="form-label">อีเมลสำหรับทดสอบ</label>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <input type="email" name="test_email" class="form-input" placeholder="example@email.com" required style="flex: 1;">
                                                    <button type="submit" class="btn btn-success" style="white-space: nowrap;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.25rem;">
                                                            <line x1="22" y1="2" x2="11" y2="13"></line>
                                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                        </svg>
                                                        ส่งอีเมล
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Email Notification Preferences -->
                        <form method="POST" onsubmit="return showSaveConfirm(this, 'บันทึกการแจ้งเตือน', 'คุณต้องการบันทึกการตั้งค่าประเภทการส่งอีเมลแจ้งเตือนใช่หรือไม่?');">
                            <input type="hidden" name="action" value="notifications">
                            <div class="card settings-section">
                                <div class="card-body">
                                    <div class="settings-section-header" style="justify-content: space-between; align-items: center; display: flex;">
                                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                                            <div class="settings-section-icon green">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                    <polyline points="22,6 12,13 2,6"></polyline>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="settings-section-title">การแจ้งเตือนทางอีเมล</h3>
                                                <p class="settings-section-desc">เลือกประเภทการแจ้งเตือนที่ต้องการส่งไปยังผู้ใช้</p>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary" title="บันทึกการแจ้งเตือน" style="padding: 0.5rem; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                                <polyline points="7 3 7 8 15 8"></polyline>
                                            </svg>
                                        </button>
                                    </div>
                                
                                <!-- User Management Notifications -->
                                <div class="notification-group">
                                    <div class="notification-group-title">การจัดการผู้ใช้</div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_registration" id="notify_registration" class="notification-checkbox" <?php echo getVal('email_notify_registration') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_registration" class="notification-label">
                                            <div class="notification-title">อีเมลต้อนรับผู้ใช้ใหม่</div>
                                            <div class="notification-desc">ส่งอีเมลต้อนรับพร้อมข้อมูลการเข้าใช้งานเมื่อมีการลงทะเบียนผู้ใช้ใหม่</div>
                                        </label>
                                    </div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_password_reset" id="notify_password" class="notification-checkbox" <?php echo getVal('email_notify_password_reset') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_password" class="notification-label">
                                            <div class="notification-title">ยืนยันการเปลี่ยนรหัสผ่าน</div>
                                            <div class="notification-desc">ส่งอีเมลยืนยันเมื่อมีการเปลี่ยนรหัสผ่านหรือรีเซ็ตรหัสผ่าน</div>
                                        </label>
                                    </div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_account_status" id="notify_account" class="notification-checkbox" <?php echo getVal('email_notify_account_status') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_account" class="notification-label">
                                            <div class="notification-title">การเปลี่ยนแปลงสถานะบัญชี</div>
                                            <div class="notification-desc">แจ้งเตือนเมื่อบัญชีถูกเปิดใช้งาน ปิดใช้งาน หรือเปลี่ยนสิทธิ์การเข้าถึง</div>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Assessment Notifications -->
                                <div class="notification-group">
                                    <div class="notification-group-title">การประเมิน</div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_assessment_open" id="notify_open" class="notification-checkbox" <?php echo getVal('email_notify_assessment_open') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_open" class="notification-label">
                                            <div class="notification-title">เปิดรอบการประเมิน</div>
                                            <div class="notification-desc">แจ้งเตือนเมื่อเปิดรอบการประเมินใหม่</div>
                                        </label>
                                    </div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_assessment_deadline" id="notify_deadline" class="notification-checkbox" <?php echo getVal('email_notify_assessment_deadline') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_deadline" class="notification-label">
                                            <div class="notification-title">เตือนก่อนถึงกำหนดส่ง</div>
                                            <div class="notification-desc">ส่งการแจ้งเตือนก่อนถึงกำหนดส่งแบบประเมิน</div>
                                        </label>
                                    </div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_assessment_submitted" id="notify_submitted" class="notification-checkbox" <?php echo getVal('email_notify_assessment_submitted') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_submitted" class="notification-label">
                                            <div class="notification-title">ยืนยันการรับแบบประเมิน</div>
                                            <div class="notification-desc">ส่งอีเมลยืนยันเมื่อได้รับแบบประเมินที่ส่งแล้ว</div>
                                        </label>
                                    </div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_assessment_results" id="notify_results" class="notification-checkbox" <?php echo getVal('email_notify_assessment_results') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_results" class="notification-label">
                                            <div class="notification-title">ประกาศผลการประเมิน</div>
                                            <div class="notification-desc">แจ้งเตือนเมื่อมีการประกาศผลการประเมิน</div>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- System Notifications -->
                                <div class="notification-group">
                                    <div class="notification-group-title">ระบบ</div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_system_updates" id="notify_updates" class="notification-checkbox" <?php echo getVal('email_notify_system_updates') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_updates" class="notification-label">
                                            <div class="notification-title">อัปเดตระบบที่สำคัญ</div>
                                            <div class="notification-desc">ประกาศการอัปเดตระบบและฟีเจอร์ใหม่ที่สำคัญ</div>
                                        </label>
                                    </div>
                                    
                                    <div class="notification-item">
                                        <input type="checkbox" name="email_notify_maintenance" id="notify_maintenance" class="notification-checkbox" <?php echo getVal('email_notify_maintenance') == 'true' ? 'checked' : ''; ?>>
                                        <label for="notify_maintenance" class="notification-label">
                                            <div class="notification-title">การปิดปรับปรุงระบบ</div>
                                            <div class="notification-desc">แจ้งเตือนล่วงหน้าเมื่อมีการปิดปรับปรุงระบบ</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- System Control -->
                        <form method="POST" onsubmit="return showSaveConfirm(this, 'บันทึกการควบคุมระบบ', 'คุณต้องการบันทึกการเปลี่ยนแปลงสิทธิ์การเข้าใช้งานระบบใช่หรือไม่?');">
                            <input type="hidden" name="action" value="system_control">
                            <div class="card settings-section">
                                <div class="card-body">
                                    <div class="settings-section-header" style="justify-content: space-between; align-items: center; display: flex;">
                                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                                            <div class="settings-section-icon orange">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                                </svg>
                                            </div>
                                            <div>
                                                <h3 class="settings-section-title">ควบคุมระบบ</h3>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary" title="บันทึกการควบคุมระบบ" style="padding: 0.5rem; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                                <polyline points="7 3 7 8 15 8"></polyline>
                                            </svg>
                                        </button>
                                    </div>
                                
                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="assessmentOpen" name="assessment_open" <?php echo getVal('assessment_open') == 'true' ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="assessmentOpen">เปิดรับการประเมิน</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">หากปิด บริษัทจะไม่สามารถแก้ไขข้อมูลการประเมินได้</small>
                                </div>
                                
                                <hr>
                                
                                <div class="form-group mb-0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="maintenanceMode" name="maintenance_mode" <?php echo getVal('maintenance_mode') == 'true' ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="maintenanceMode">โหมดปิดปรับปรุง</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">หากเปิด ผู้ใช้ทั่วไปจะไม่สามารถเข้าใช้งานได้</small>
                                </div>
                                
                                <hr>
                                
                                <div class="form-group mb-0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="demoAccountsEnabled" name="demo_accounts_enabled" <?php echo getVal('demo_accounts_enabled') == 'true' ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="demoAccountsEnabled">เปิด Demo Accounts บนหน้า Login</label>
                                    </div>
                                    <small class="text-muted d-block mt-1">หากเปิด ผู้ใช้จะเห็นบัญชี Demo ที่สามารถเข้าใช้บนหน้าเข้าสู่ระบบ</small>
                                </div>
                                </div>
                            </div>
                        </form>

                        <!-- System Info -->
                        <form method="POST" onsubmit="return showSaveConfirm(this, 'บันทึกข้อมูลเวอร์ชัน', 'คุณต้องการแก้ไขเวอร์ชันของแอปพลิเคชันใช่หรือไม่?');">
                            <input type="hidden" name="action" value="system_info">
                            <div class="card info-card mt-3">
                                <div class="card-body" style="padding: 1.25rem;">
                                    <div style="justify-content: space-between; align-items: center; display: flex; margin-bottom: 1.25rem;">
                                        <h6 style="font-weight: 600; margin: 0; color: var(--gray-900);">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                            </svg>
                                            ข้อมูลระบบ
                                        </h6>
                                        <button type="submit" class="btn btn-sm btn-primary" title="บันทึกข้อมูลระบบ" style="padding: 0.4rem; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                                <polyline points="7 3 7 8 15 8"></polyline>
                                            </svg>
                                        </button>
                                    </div>
                                <div class="info-item" style="padding: 0.5rem 0;">
                                    <span class="info-label">Version:</span>
                                    <input type="text" name="app_version" class="form-input" style="padding: 2px 8px; height: auto; display: inline-block; width: 100px; margin-left: 10px;" value="<?php echo htmlspecialchars(getVal('app_version', '1.0.0')); ?>">
                                </div>
                                <div class="info-item" style="padding: 0.5rem 0;">
                                    <span class="info-label">Last Updated:</span>
                                    <span class="info-value" style="font-size: 0.8rem; color: var(--gray-600);"><?php echo htmlspecialchars($lastUpdate); ?></span>
                                </div>
                                <hr>
                                <div class="info-item" style="padding: 0.25rem 0;">
                                    <span class="info-label">PHP:</span>
                                    <span class="info-value"><?php echo phpversion(); ?></span>
                                </div>
                                <div class="info-item" style="padding: 0.25rem 0; border-bottom: none;">
                                    <span class="info-label">Server Time:</span>
                                    <span class="info-value"><?php echo date('H:i:s'); ?></span>
                                </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        </div>
    </main>

    <!-- Custom Confirmation Modal -->
    <div class="modal-overlay" id="confirmModalOverlay">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body" style="text-align: center; padding: 2.5rem 2rem;">
                <div id="modalIconContainer" style="width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; background: var(--primary-50); color: var(--primary-500);">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                </div>
                <h3 id="modalTitleText" style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.75rem; color: var(--gray-900);">ยืนยันการบันทึก</h3>
                <p id="modalMessageText" style="color: var(--gray-500); margin-bottom: 2rem; line-height: 1.6;">คุณต้องการบันทึกการเปลี่ยนแปลงในส่วนนี้ใช่หรือไม่?</p>
                <div style="display: flex; justify-content: center; gap: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()" style="min-width: 100px;">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="modalConfirmBtn" style="min-width: 100px;">ยืนยัน</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        let currentFormToSubmit = null;

        function showSaveConfirm(form, title, message) {
            currentFormToSubmit = form;
            document.getElementById('modalTitleText').innerText = title || 'ยืนยันการบันทึก';
            document.getElementById('modalMessageText').innerText = message || 'คุณต้องการบันทึกการเปลี่ยนแปลงใช่หรือไม่?';
            document.getElementById('confirmModalOverlay').classList.add('active');
            return false;
        }

        function closeConfirmModal() {
            document.getElementById('confirmModalOverlay').classList.remove('active');
            currentFormToSubmit = null;
        }

        document.getElementById('modalConfirmBtn').addEventListener('click', function() {
            if (currentFormToSubmit) {
                currentFormToSubmit.submit();
            }
        });
    </script>
</body>
</html>
