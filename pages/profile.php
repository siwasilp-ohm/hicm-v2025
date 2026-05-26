<?php
/**
 * HICM V2025 Assessment System - User Profile Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$user = getCurrentUser();
$userData = getUserById($user['id']);
$isAuditor = hasRole(ROLE_AUDITOR);

// Get organizations for auditor
$organizations = [];
if ($isAuditor) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, short_name FROM organizations WHERE is_active = 1 ORDER BY display_order");
    $stmt->execute();
    $organizations = $stmt->fetchAll();
}

$errors = [];
$success = false;

// Define avatar options
// Define avatar options
$avatarOptions = [
    ['value' => 'default', 'label' => 'ใช้อักษรแรก', 'type' => 'initials'],
    ['value' => 'avatar1.png', 'label' => 'Avatar 1', 'type' => 'image'],
    ['value' => 'avatar2.png', 'label' => 'Avatar 2', 'type' => 'image'],
    ['value' => 'avatar3.png', 'label' => 'Avatar 3', 'type' => 'image'],
    ['value' => 'avatar4.png', 'label' => 'Avatar 4', 'type' => 'image'],
    ['value' => 'avatar5.png', 'label' => 'Avatar 5', 'type' => 'image'],
    ['value' => 'avatar6.png', 'label' => 'Avatar 6', 'type' => 'image'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $expertise = $_POST['expertise'] ?? []; // Handle array from checkboxes
        $avatarName = $userData['avatar']; 
        
        $expertiseStr = is_array($expertise) ? implode('|', $expertise) : '';

        if (empty($name)) {
            $errors[] = 'กรุณากรอกชื่อ';
        }

        if (empty($email)) {
            $errors[] = 'กรุณากรอกอีเมล';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        } else {
            // Check if email is already in use
            $db = getDB();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'อีเมลนี้ถูกใช้งานแล้ว';
            }
        }

        // Handle Avatar Upload or Selection
        // Priority 1: File upload (most important)
        if (!empty($_FILES['avatar']['name'])) {
            // File upload
            $file = $_FILES['avatar'];
            $fileName = $file['name'];
            $fileTmp = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileType = $file['type'];
            $fileError = $file['error'];
            
            // Check file upload errors
            if ($fileError !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป (exceeds php.ini limit)',
                    UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินไป (exceeds form limit)',
                    UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัปโหลดบางส่วน',
                    UPLOAD_ERR_NO_FILE => 'ไม่ได้เลือกไฟล์',
                    UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบ temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ได้',
                ];
                $errors[] = 'เกิดข้อผิดพลาดในการอัปโหลด: ' . ($uploadErrors[$fileError] ?? 'Unknown error');
            } else {
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = 'รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF) เท่านั้น';
                } elseif ($fileSize > 2 * 1024 * 1024) { // 2MB
                    $errors[] = 'ขนาดไฟล์ต้องไม่เกิน 2MB (ขนาดปัจจุบัน: ' . round($fileSize / 1024 / 1024, 2) . 'MB)';
                } else {
                    // Generate unique filename
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $newFileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
                    $uploadPath = getUploadPath() . 'avatars/';
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadPath)) {
                        if (!mkdir($uploadPath, 0755, true)) {
                            $errors[] = 'ไม่สามารถสร้าง directory สำหรับเก็บรูปภาพได้';
                        }
                    }
                    
                    if (empty($errors)) {
                        if (move_uploaded_file($fileTmp, $uploadPath . $newFileName)) {
                            // Delete old avatar if exists and is a file (not emoji)
                            if (!empty($userData['avatar']) && strpos($userData['avatar'], 'avatar_') === 0) {
                                $oldPath = $uploadPath . $userData['avatar'];
                                if (file_exists($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                            $avatarName = $newFileName;
                        } else {
                            $errors[] = 'เกิดข้อผิดพลาดในการย้ายไฟล์ไปยัง directory สำหรับเก็บรูปภาพ';
                        }
                    }
                }
            }
        } 
        // Priority 2: Emoji avatar selection (only if no file was uploaded)
        elseif (!empty($_POST['avatar_choice'])) {
            // Using emoji avatar
            $avatarName = $_POST['avatar_choice'];
        }
        
        if (empty($errors)) {
            $db = getDB();
            try {
                $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, email = ?, avatar = ?, expertise = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $email, $avatarName, $expertiseStr, $user['id']]);
                
                $_SESSION['user_name'] = $name;
                $_SESSION['user_avatar'] = $avatarName;
                $success = true;
                $userData = getUserById($user['id']);
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบัน';
        if (empty($newPassword)) $errors[] = 'กรุณากรอกรหัสผ่านใหม่';
        if (strlen($newPassword) < 6) $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        if ($newPassword !== $confirmPassword) $errors[] = 'รหัสผ่านใหม่ไม่ตรงกัน';
        
        if (empty($errors)) {
            $result = changePassword($user['id'], $currentPassword, $newPassword);
            if ($result['success']) {
                $success = true;
            } else {
                $errors[] = $result['message'];
            }
        }
    }
    
    // Update Auditor Organization
    if (isset($_POST['update_auditor_info']) && $isAuditor) {
        $organizationId = intval($_POST['organization_id'] ?? 0);
        $hicmExpertise = $_POST['hicm_expertise'] ?? [];
        $hicmExpertiseStr = implode('|', $hicmExpertise);
        
        $db = getDB();
        try {
            $stmt = $db->prepare("UPDATE users SET organization_id = ?, hicm_expertise = ? WHERE id = ?");
            $stmt->execute([$organizationId ?: null, $hicmExpertiseStr, $user['id']]);
            $success = true;
            $userData = getUserById($user['id']);
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

// Helper function to get avatar display
// Helper function to get avatar display
function getAvatarDisplay($avatar, $name) {
    if (empty($avatar) || $avatar === 'default') {
        return mb_substr($name, 0, 1, 'UTF-8');
    }

    if (strpos($avatar, 'avatar') === 0 && file_exists(APP_UPLOAD_PATH . 'avatars/' . $avatar)) {
        return '<img src="' . getUploadUrl() . 'avatars/' . $avatar . '" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
    }

    return mb_substr($name, 0, 1, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .profile-hero {
            background: linear-gradient(135deg, var(--primary-600) 0%, var(--primary-800) 100%);
            color: white;
            padding: 3rem 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            position: relative;
            z-index: 1;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        
        .profile-avatar-section {
            text-align: center;
        }
        
        .avatar-display {
            width: 140px;
            height: 140px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255,255,255,0.3);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .avatar-display img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .profile-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .profile-email {
            opacity: 0.9;
            font-size: 0.9rem;
            word-break: break-all;
        }
        
        .tabs-container {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--gray-200);
            flex-wrap: wrap;
        }
        
        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            color: var(--gray-500);
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all var(--transition-fast);
        }
        
        .tab-button:hover {
            color: var(--primary-600);
        }
        
        .tab-button.active {
            color: var(--primary-600);
            border-bottom-color: var(--primary-600);
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-section {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        
        .form-section h3 {
            margin-bottom: 1.5rem;
            color: var(--gray-900);
        }
        
        .avatar-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--gray-50) 100%);
            border-radius: var(--radius-xl);
            border: 2px dashed var(--primary-200);
        }
        
        .avatar-option {
            position: relative;
            cursor: pointer;
            text-align: center;
        }
        
        .avatar-option input {
            display: none;
        }
        
        .avatar-option-display {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            border: 3px solid var(--gray-300);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
        }
        
        .avatar-option:hover .avatar-option-display {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            border-color: var(--primary-400);
        }
        
        .avatar-option input:checked + .avatar-option-display {
            border-color: var(--primary-600);
            border-width: 4px;
            box-shadow: 0 0 0 6px var(--primary-100), 0 8px 24px rgba(0,0,0,0.12);
            transform: scale(1.1);
            background: linear-gradient(135deg, var(--primary-50), white);
        }
        
        .avatar-option input:checked + .avatar-option-display::after {
            content: '✓';
            position: absolute;
            top: -8px;
            right: -8px;
            width: 32px;
            height: 32px;
            background: var(--primary-600);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .avatar-option-display img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .avatar-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-top: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
            height: 2.4rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Image Preview Modal */
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .preview-modal.active {
            display: flex;
        }
        
        .preview-modal-content {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .preview-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .preview-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--gray-900);
        }
        
        .preview-image-wrapper {
            width: 200px;
            height: 200px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--primary-600);
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        #previewImage {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform-origin: center;
        }
        
        .preview-controls {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .control-group {
            text-align: left;
        }
        
        .control-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-700);
        }
        
        .control-group input[type="range"] {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: var(--gray-300);
            outline: none;
            -webkit-appearance: none;
        }
        
        .control-group input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary-600);
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .control-group input[type="range"]::-moz-range-thumb {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--primary-600);
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .zoom-value {
            display: inline-block;
            background: var(--primary-100);
            color: var(--primary-700);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.8rem;
            font-weight: 600;
            float: right;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .button-group button {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
        }
        
        .btn-cancel {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-cancel:hover {
            background: var(--gray-300);
        }
        
        .btn-confirm {
            background: var(--primary-600);
            color: white;
        }
        
        .btn-confirm:hover {
            background: var(--primary-700);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .info-card {
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            border-left: 4px solid var(--primary-600);
        }
        
        .info-label {
            display: block;
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            word-break: break-all;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">โปรไฟล์ผู้ใช้</h1>
                <p class="page-subtitle">จัดการข้อมูลส่วนตัวและการตั้งค่าบัญชี</p>
            </div>
            
            <?php echo getFlashMessage(); ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-6">
                    <?php foreach ($errors as $error): ?>
                        <div>✕ <?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success mb-6">
                    ✓ บันทึกข้อมูลเรียบร้อยแล้ว
                </div>
            <?php endif; ?>
            
            <!-- Profile Hero Section -->
            <div class="profile-hero">
                <div class="profile-container">
                    <div class="profile-avatar-section">
                        <div class="avatar-display">
                            <?php echo getAvatarDisplay($userData['avatar'], $userData['name']); ?>
                        </div>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?php echo htmlspecialchars($userData['name']); ?></div>
                        <div class="profile-meta">
                            <span class="profile-badge">
                                <?php 
                                $roleLabels = ['admin' => 'ผู้ดูแลระบบ', 'auditor' => 'กรรมการ', 'company' => 'บริษัท'];
                                echo $roleLabels[$userData['role']] ?? $userData['role'];
                                ?>
                            </span>
                            <span class="profile-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <?php echo isset($userData['last_login']) ? 'ออนไลน์' : 'ออฟไลน์'; ?>
                            </span>
                        </div>
                        <div class="profile-email"><?php echo htmlspecialchars($userData['email']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <button class="tab-button active" onclick="switchTab(event, 'info')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem; vertical-align: -2px;">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    ข้อมูลส่วนตัว
                </button>
                <button class="tab-button" onclick="switchTab(event, 'edit')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem; vertical-align: -2px;">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    แก้ไขข้อมูล
                </button>
                <button class="tab-button" onclick="switchTab(event, 'password')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem; vertical-align: -2px;">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                    </svg>
                    เปลี่ยนรหัสผ่าน
                </button>
                <?php if ($isAuditor): ?>
                <button class="tab-button" onclick="switchTab(event, 'auditor')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem; vertical-align: -2px;">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    ข้อมูลกรรมการ
                </button>
                <?php endif; ?>
            </div>
            
            <!-- Tab: ข้อมูลส่วนตัว -->
            <div id="info" class="tab-content active">
                <div class="form-section">
                    <h3>ข้อมูลบัญชีผู้ใช้</h3>
                    <div class="info-grid">
                        <div class="info-card">
                            <span class="info-label">ชื่อผู้ใช้</span>
                            <span class="info-value" style="font-family: 'Courier New', monospace; font-size: 0.95rem;"><?php echo htmlspecialchars($userData['username']); ?></span>
                        </div>
                        <div class="info-card">
                            <span class="info-label">ชื่อ-นามสกุล</span>
                            <span class="info-value"><?php echo htmlspecialchars($userData['name']); ?></span>
                        </div>
                        <div class="info-card">
                            <span class="info-label">อีเมล</span>
                            <span class="info-value"><?php echo htmlspecialchars($userData['email']); ?></span>
                        </div>
                        <div class="info-card">
                            <span class="info-label">เบอร์โทรศัพท์</span>
                            <span class="info-value"><?php echo htmlspecialchars($userData['phone'] ?? '-'); ?></span>
                        </div>
                        <div class="info-card">
                            <span class="info-label">บทบาท</span>
                            <span class="info-value"><?php echo $roleLabels[$userData['role']] ?? $userData['role']; ?></span>
                        </div>
                        <?php if ($isAuditor): ?>
                        <div class="info-card">
                            <span class="info-label">หน่วยงานภาคีเครือข่าย</span>
                            <span class="info-value">
                                <?php 
                                if (!empty($userData['organization_id'])) {
                                    foreach ($organizations as $org) {
                                        if ($org['id'] == $userData['organization_id']) {
                                            echo htmlspecialchars($org['name']);
                                            break;
                                        }
                                    }
                                } else {
                                    echo '<span style="color: var(--gray-400);">ไม่ระบุหน่วยงาน / อิสระ / ผู้เชี่ยวชาญ</span>';
                                }
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="info-card">
                            <span class="info-label">วันที่สมัคร</span>
                            <span class="info-value"><?php echo formatDate($userData['created_at']); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($userData['company_name']): ?>
                    <div style="margin-top: 2.5rem; padding-top: 2rem; border-top: 2px solid var(--gray-200);">
                        <h3 style="margin-bottom: 1.5rem;">ข้อมูลบริษัท</h3>
                        <div class="info-grid">
                            <div class="info-card">
                                <span class="info-label">ชื่อบริษัท</span>
                                <span class="info-value"><?php echo htmlspecialchars($userData['company_name']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab: แก้ไขข้อมูล -->
            <div id="edit" class="tab-content">
                <div class="form-section">
                    <h3>แก้ไขข้อมูลส่วนตัว</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <!-- Avatar Selection -->
                        <div style="margin-bottom: 2.5rem;">
                            <label class="form-label" style="display: block; margin-bottom: 1.5rem; font-weight: 600; font-size: 1.1rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.7rem; vertical-align: -3px;">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                </svg>
                                เลือกรูปประจำตัว
                            </label>
                            
                            <!-- Avatar Gallery -->
                            <div class="avatar-gallery">
                                <?php foreach ($avatarOptions as $option): ?>
                                    <label class="avatar-option" title="<?php echo $option['label']; ?>">
                                        <input type="radio" name="avatar_choice" value="<?php echo $option['value']; ?>" 
                                               <?php echo ($userData['avatar'] === $option['value'] || ($userData['avatar'] === '' && $option['value'] === 'default')) ? 'checked' : ''; ?>>
                                        <div class="avatar-option-display" style="<?php echo $option['type'] === 'initials' ? 'background: linear-gradient(135deg, var(--primary-100) 0%, var(--primary-50) 100%);' : 'background: white; padding: 0;'; ?>">
                                            <?php if ($option['type'] === 'initials'): ?>
                                                <?php echo mb_substr($userData['name'], 0, 1, 'UTF-8'); ?>
                                            <?php else: ?>
                                                <img src="<?php echo getAvatarUrl($option['value']); ?>" alt="<?php echo $option['label']; ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                            <?php endif; ?>
                                        </div>
                                        <span class="avatar-label"><?php echo $option['label']; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Divider -->
                            <div style="margin: 2.5rem 0; padding: 0 1rem;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="flex: 1; height: 1px; background: var(--gray-300);"></div>
                                    <span style="color: var(--gray-500); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;">หรือ</span>
                                    <div style="flex: 1; height: 1px; background: var(--gray-300);"></div>
                                </div>
                            </div>
                            
                            <!-- File Upload Option -->
                            <div style="padding: 2rem; background: white; border-radius: var(--radius-lg); border: 2px dashed var(--gray-300); transition: all 0.3s ease; position: relative;" 
                                 ondragover="this.style.borderColor='var(--primary-400)'; this.style.backgroundColor='var(--primary-50)';"
                                 ondragleave="this.style.borderColor='var(--gray-300)'; this.style.backgroundColor='white';"
                                 ondrop="handleDrop(event)">
                                <div style="text-align: center;">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 1rem; color: var(--primary-500); opacity: 0.7;">
                                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                    <label for="avatarFile" style="cursor: pointer; display: block;">
                                        <span style="display: block; font-weight: 600; color: var(--primary-600); margin-bottom: 0.5rem;">
                                            📸 อัปโหลดรูปภาพของคุณ
                                        </span>
                                        <span style="display: block; font-size: 0.85rem; color: var(--gray-500);">
                                            ลากรูปมาวางหรือคลิกเพื่อเลือก
                                        </span>
                                    </label>
                                    <input type="file" name="avatar" id="avatarFile" class="form-input" accept="image/jpeg,image/png,image/gif" 
                                           style="display: none;">
                                    <p class="form-hint" style="margin-top: 1rem; margin-bottom: 0;">JPG, PNG หรือ GIF • ขนาดไม่เกิน 2MB</p>
                                    <div id="fileNameDisplay" style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--success); font-weight: 500; display: none;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Name -->
                        <div class="form-group">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($userData['name']); ?>" required>
                        </div>
                        
                        <!-- Phone -->
                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" placeholder="เช่น 081-2345-6789">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
                        </div>

                        <?php if ($userData['role'] === 'auditor'): ?>
                        <div class="form-group" style="margin-top: 2rem;">
                            <label class="form-label" style="font-weight: 700; color: var(--primary-700); display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                <i class="fas fa-star"></i> ความเชี่ยวชาญ / สายงานที่ถนัด
                            </label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; background: var(--gray-50); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200);">
                                <?php 
                                $userExpertise = explode('|', $userData['expertise'] ?? '');
                                foreach (AUDITOR_EXPERTISE as $exp): 
                                ?>
                                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.25rem; transition: all 0.2s;" class="hover:text-primary-600">
                                        <input type="checkbox" name="expertise[]" value="<?php echo htmlspecialchars($exp); ?>" 
                                            <?php echo in_array($exp, $userExpertise) ? 'checked' : ''; ?>
                                            style="width: 18px; height: 18px; border-radius: 4px; border: 2px solid var(--gray-300); cursor: pointer;">
                                        <span style="font-size: 0.95rem;"><?php echo htmlspecialchars($exp); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-hint" style="margin-top: 0.75rem;">คุณสามารถเลือกความเชี่ยวชาญได้มากกว่า 1 อย่าง เพื่อให้ระบบช่วยจับคู่บริษัทที่เหมาะสม</p>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem;">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            </svg>
                            บันทึกข้อมูล
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Tab: เปลี่ยนรหัสผ่าน -->
            <div id="password" class="tab-content">
                <div class="form-section">
                    <h3>เปลี่ยนรหัสผ่าน</h3>
                    <form method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" name="new_password" class="form-input" required minlength="6">
                            <p class="form-hint">ต้องมีอย่างน้อย 6 ตัวอักษร</p>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem;">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
                            </svg>
                            เปลี่ยนรหัสผ่าน
                        </button>
                    </form>
                </div>
            </div>
            
            <?php if ($isAuditor): ?>
            <!-- Tab: ข้อมูลกรรมการ -->
            <div id="auditor" class="tab-content">
                <div class="form-section">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                        </div>
                        <div>
                            <h3 style="margin: 0; color: var(--gray-900);">ข้อมูลกรรมการประเมิน</h3>
                            <p style="margin: 0.25rem 0 0; color: var(--gray-500); font-size: 0.875rem;">เลือกหน่วยงานภาคีเครือข่ายที่สังกัด</p>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="update_auditor_info" value="1">
                        
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 1rem; display: block;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem; vertical-align: -3px;">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                </svg>
                                หน่วยงานภาคีเครือข่าย
                            </label>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0.75rem;">
                                <label class="org-option" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: <?php echo empty($userData['organization_id']) ? 'var(--primary-50)' : 'var(--gray-50)'; ?>; border: 2px solid <?php echo empty($userData['organization_id']) ? 'var(--primary-500)' : 'var(--gray-200)'; ?>; border-radius: var(--radius-lg); cursor: pointer; transition: all 0.2s;">
                                    <input type="radio" name="organization_id" value="" <?php echo empty($userData['organization_id']) ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary-600);">
                                    <div>
                                        <div style="font-weight: 600; color: var(--gray-700);">ไม่ระบุหน่วยงาน</div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);">อิสระ / ผู้เชี่ยวชาญ</div>
                                    </div>
                                </label>
                                
                                <?php foreach ($organizations as $org): ?>
                                <label class="org-option" style="display: flex; align-items: center; gap: 0.75rem; padding: 1rem; background: <?php echo $userData['organization_id'] == $org['id'] ? 'var(--primary-50)' : 'var(--gray-50)'; ?>; border: 2px solid <?php echo $userData['organization_id'] == $org['id'] ? 'var(--primary-500)' : 'var(--gray-200)'; ?>; border-radius: var(--radius-lg); cursor: pointer; transition: all 0.2s;">
                                    <input type="radio" name="organization_id" value="<?php echo $org['id']; ?>" <?php echo $userData['organization_id'] == $org['id'] ? 'checked' : ''; ?> style="width: 18px; height: 18px; accent-color: var(--primary-600);">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-weight: 600; color: var(--gray-700); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($org['name']); ?>">
                                            <?php echo htmlspecialchars($org['name']); ?>
                                        </div>
                                        <?php if (!empty($org['short_name'])): ?>
                                        <div style="font-size: 0.75rem; color: var(--gray-500);"><?php echo htmlspecialchars($org['short_name']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- HICM Pillars Selection -->
                        <div class="form-group" style="margin-top: 2rem;">
                            <label class="form-label" style="font-weight: 600; margin-bottom: 1rem; display: block;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem; vertical-align: -3px;">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                ความเชี่ยวชาญ HICM Pillars
                            </label>
                            <p style="font-size: 0.85rem; color: var(--gray-500); margin-bottom: 1rem;">เลือก Pillar ที่คุณมีความเชี่ยวชาญ (สามารถเลือกได้หลายรายการ)</p>
                            
                            <?php $currentHicmExpertise = explode('|', $userData['hicm_expertise'] ?? ''); ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                                <?php foreach (PILLARS as $code => $pillar): ?>
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; background: <?php echo in_array($code, $currentHicmExpertise) ? $pillar['color'] . '15' : 'var(--gray-50)'; ?>; border: 2px solid <?php echo in_array($code, $currentHicmExpertise) ? $pillar['color'] : 'var(--gray-200)'; ?>; border-radius: var(--radius-lg); cursor: pointer; transition: all 0.2s;">
                                    <input type="checkbox" name="hicm_expertise[]" value="<?php echo $code; ?>" <?php echo in_array($code, $currentHicmExpertise) ? 'checked' : ''; ?> style="width: 20px; height: 20px; accent-color: <?php echo $pillar['color']; ?>; margin-top: 2px;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: <?php echo $pillar['color']; ?>; color: white; border-radius: 6px; font-weight: 700; font-size: 0.85rem;"><?php echo $code; ?></span>
                                            <span style="font-weight: 600; color: var(--gray-800);"><?php echo $pillar['name_th']; ?></span>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--gray-500);"><?php echo $pillar['name_en']; ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline; margin-right: 0.5rem;">
                                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                            </svg>
                            บันทึกข้อมูลกรรมการ
                        </button>
                    </form>
                </div>
                
                <!-- Current Organization Info -->
                <?php 
                $currentOrg = null;
                if (!empty($userData['organization_id'])) {
                    foreach ($organizations as $org) {
                        if ($org['id'] == $userData['organization_id']) {
                            $currentOrg = $org;
                            break;
                        }
                    }
                }
                ?>
                
                <div class="form-section" style="margin-top: 1.5rem; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #93c5fd;">
                    <h4 style="margin: 0 0 1rem; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        สถานะปัจจุบัน
                    </h4>
                    
                    <?php if ($currentOrg): ?>
                    <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: white; border-radius: var(--radius-lg);">
                        <div style="width: 50px; height: 50px; background: var(--primary-100); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-600); font-weight: bold; font-size: 1.25rem;">
                            <?php echo mb_substr($currentOrg['name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: var(--gray-900);"><?php echo htmlspecialchars($currentOrg['name']); ?></div>
                            <div style="font-size: 0.875rem; color: var(--gray-500);"><?php echo htmlspecialchars($currentOrg['short_name'] ?? ''); ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="padding: 1rem; background: white; border-radius: var(--radius-lg); color: var(--gray-600); text-align: center;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 0.5rem; display: block; opacity: 0.5;">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        ยังไม่ได้ระบุหน่วยงาน
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Image Preview Modal -->
    <div id="previewModal" class="preview-modal">
        <div class="preview-modal-content">
            <div class="preview-container">
                <h3 class="preview-title">ปรับแต่งรูปภาพ</h3>
                
                <div class="preview-image-wrapper">
                    <img id="previewImage" src="" alt="Preview">
                </div>
                
                <div class="preview-controls">
                    <div class="control-group">
                        <label>
                            ขยาย/ย่อ
                            <span class="zoom-value"><span id="zoomValue">100</span>%</span>
                        </label>
                        <input type="range" id="zoomSlider" min="100" max="300" value="100" step="10">
                    </div>
                </div>
                
                <div class="button-group">
                    <button class="btn-cancel" onclick="closePreviewModal()">ยกเลิก</button>
                    <button class="btn-confirm" onclick="confirmPreview()">บันทึกรูปภาพ</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        // File input handling with preview
        const avatarFileInput = document.getElementById('avatarFile');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const previewModal = document.getElementById('previewModal');
        const previewImage = document.getElementById('previewImage');
        const zoomSlider = document.getElementById('zoomSlider');
        const zoomValue = document.getElementById('zoomValue');
        
        let selectedFile = null;
        
        if (avatarFileInput) {
            avatarFileInput.addEventListener('change', function(e) {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    
                    // Validate file
                    if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                        alert('กรุณาเลือกไฟล์รูปภาพเท่านั้น (JPG, PNG, GIF)');
                        this.value = '';
                        return;
                    }
                    
                    if (file.size > 2 * 1024 * 1024) {
                        alert('ขนาดไฟล์ต้องไม่เกิน 2MB');
                        this.value = '';
                        return;
                    }
                    
                    selectedFile = file;
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        zoomSlider.value = 100;
                        zoomValue.textContent = '100';
                        updatePreviewZoom();
                        openPreviewModal();
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Zoom slider handling
        if (zoomSlider) {
            zoomSlider.addEventListener('input', function() {
                zoomValue.textContent = this.value;
                updatePreviewZoom();
            });
        }
        
        function updatePreviewZoom() {
            const zoom = zoomSlider.value / 100;
            previewImage.style.transform = `scale(${zoom})`;
        }
        
        function openPreviewModal() {
            previewModal.classList.add('active');
        }
        
        function closePreviewModal() {
            previewModal.classList.remove('active');
            selectedFile = null;
            // Do NOT clear the file input here - we need it for form submission
        }
        
        function confirmPreview() {
            if (selectedFile) {
                // Show filename
                fileNameDisplay.textContent = '✓ เลือกไฟล์: ' + selectedFile.name;
                fileNameDisplay.style.display = 'block';
                
                // Clear any emoji avatar selection when file is chosen
                const emojiRadios = document.querySelectorAll('input[name="avatar_choice"]');
                emojiRadios.forEach(radio => {
                    radio.checked = false;
                });
                
                // Important: Set a marker that file is selected
                const fileInputField = document.getElementById('avatarFile');
                if (fileInputField && fileInputField.files.length > 0) {
                    fileInputField.dataset.selected = 'true';
                }
            }
            closePreviewModal();
        }
        
        function handleDrop(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const dropZone = event.currentTarget;
            dropZone.style.borderColor = 'var(--gray-300)';
            dropZone.style.backgroundColor = 'white';
            
            if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
                avatarFileInput.files = event.dataTransfer.files;
                
                // Trigger change event
                const changeEvent = new Event('change', { bubbles: true });
                avatarFileInput.dispatchEvent(changeEvent);
            }
        }
        
        function switchTab(event, tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Add active to clicked button
            event.target.closest('.tab-button').classList.add('active');
        }
        
        // Close modal when clicking outside
        if (previewModal) {
            previewModal.addEventListener('click', function(e) {
                if (e.target === previewModal) {
                    closePreviewModal();
                }
            });
        }
    </script>
</body>
</html>
