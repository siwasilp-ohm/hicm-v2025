<?php
/**
 * HICM V2025 Assessment System - Company Profile
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/companies.php';

requireAuth();

// Only Company Role can access this? Or Admin too?
// Usually company users edit their own profile here.
if (!hasRole(ROLE_COMPANY)) {
    // If admin wants to view/edit, they should use companies.php list. 
    // But let's allow generic access if logic supports it, though usually profile implies "my profile"
    // For now, restrict to company or maybe redirect admin to their own profile if we had one.
    // Let's assume this page is for the logged-in company user.
}

$user = getCurrentUser();
$company = null;

// Find company associated with this user
// We can use getCompanyByUserId if it exists, otherwise query.
// I'll check if function exists or query directly.
// Ideally usage: $company = getCompanyByUserId($user['id']);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$user['id']]);
$company = $stmt->fetch();

if (!$company && hasRole(ROLE_COMPANY)) {
    // Should not happen for active company users, but handle gracefully
    setFlashMessage('ไม่พบข้อมูลบริษัทของคุณ กรุณาติดต่อผู้ดูแลระบบ', 'error');
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $company) {
    $action = $_POST['action'] ?? '';
    $updateData = [];
    $successMsg = '';
    
    switch ($action) {
        case 'update_company_info':
            $updateData = [
                'company_name' => sanitizeInput($_POST['company_name']),
                'company_name_en' => sanitizeInput($_POST['company_name_en']),
                'tax_id' => sanitizeInput($_POST['tax_id']),
                'company_size' => sanitizeInput($_POST['company_size']),
                'employee_count' => intval($_POST['employee_count'])
            ];
            $successMsg = 'บันทึกข้อมูลบริษัทเรียบร้อยแล้ว';
            break;
            
        case 'update_contact_info':
            $updateData = [
                'contact_name' => sanitizeInput($_POST['contact_name']),
                'contact_email' => sanitizeInput($_POST['contact_email']),
                'contact_phone' => sanitizeInput($_POST['contact_phone']),
                'address' => sanitizeInput($_POST['address'])
            ];
            $successMsg = 'บันทึกข้อมูลผู้ติดต่อเรียบร้อยแล้ว';
            break;
            
        case 'update_industry_type':
            $updateData = [
                'industry_type' => $_POST['industry_type'] ?? []
            ];
            $successMsg = 'บันทึกประเภทธุรกิจเรียบร้อยแล้ว';
            break;
            
        case 'update_location':
            $updateData = [
                'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
                'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null
            ];
            $successMsg = 'บันทึกตำแหน่งที่ตั้งเรียบร้อยแล้ว';
            break;
    }
    
    if (!empty($updateData)) {
        $result = updateCompany($company['id'], $updateData);
        
        if ($result['success']) {
            setFlashMessage($successMsg, 'success');
            // Refresh data
            $stmt->execute([$user['id']]);
            $company = $stmt->fetch();
        } else {
            setFlashMessage('เกิดข้อผิดพลาด: ' . $result['message'], 'error');
        }
    }
}

// Get dropdown options
$industryTypes = getIndustryTypes();
$companySizes = getCompanySizes();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลบริษัท - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        /* Map Styles */
        .map-container {
            width: 100%;
            height: 350px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--gray-200);
            position: relative;
        }
        #company-map {
            width: 100%;
            height: 100%;
        }
        .map-controls {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .map-btn {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            background: white;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .map-btn:hover {
            background: var(--gray-50);
            border-color: var(--primary);
        }
        .map-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .coords-display {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .coord-input {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .coord-input label {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-600);
            min-width: 40px;
        }
        .coord-input input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--gray-50);
        }
        .map-hint {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .map-hint svg {
            color: var(--primary);
        }
        
        /* Professional Company Profile Styles */
        .profile-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .hero-avatar {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255,255,255,0.3);
            flex-shrink: 0;
        }
        .hero-info {
            flex: 1;
            min-width: 200px;
        }
        .hero-info h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.25rem 0;
        }
        .hero-info .subtitle {
            opacity: 0.9;
            font-size: 1rem;
            margin-bottom: 0.75rem;
        }
        .hero-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .hero-badge {
            padding: 0.375rem 0.75rem;
            background: rgba(255,255,255,0.2);
            border-radius: 999px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        /* Form Section */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        .profile-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--gray-50);
        }
        .profile-card-header .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        .profile-card-body {
            padding: 1.5rem;
        }
        
        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .form-label .required {
            color: #EF4444;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: white;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }
        
        /* Checkbox Grid */
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            background: var(--gray-50);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            max-height: 200px;
            overflow-y: auto;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.85rem;
            padding: 0.25rem;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .checkbox-item:hover {
            background: var(--gray-100);
        }
        .checkbox-item input {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
        }
        
        /* Quick Stats */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 900px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 500px) {
            .stats-row { grid-template-columns: 1fr; }
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid var(--gray-100);
            text-align: center;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
            margin-bottom: 0.25rem;
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        /* Submit Button */
        .submit-section {
            display: flex;
            justify-content: center;
            gap: 1rem;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 16px;
            margin-top: 1.5rem;
            border: 1px solid var(--gray-200);
        }
        .btn-save {
            padding: 1rem 3rem;
            background: linear-gradient(135deg, #10B981, #059669);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        .btn-save:active {
            transform: translateY(-1px);
        }
        
        /* Card Footer & Save Button */
        .card-footer {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
            display: flex;
            justify-content: flex-end;
        }
        .btn-card-save {
            padding: 0.625rem 1.25rem;
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
        }
        .btn-card-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }
        .btn-card-save:active {
            transform: translateY(0);
        }

        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <?php echo getFlashMessage(); ?>
            
            <?php if ($company): ?>
            
            <!-- Hero Section -->
            <div class="profile-hero animate-in">
                <div class="hero-content">
                    <div class="hero-avatar">
                        <?php 
                        // Display user avatar (same as navbar)
                        if (!empty($user['avatar']) && $user['avatar'] !== 'default' && strpos($user['avatar'], 'avatar') === 0 && file_exists(APP_UPLOAD_PATH . 'avatars/' . $user['avatar'])) {
                            echo '<img src="' . getUploadUrl() . 'avatars/' . htmlspecialchars($user['avatar']) . '" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
                        } else {
                            echo mb_substr($user['name'], 0, 1, 'UTF-8');
                        }
                        ?>
                    </div>
                    <div class="hero-info">
                        <h1><?php echo htmlspecialchars($company['company_name']); ?></h1>
                        <?php if (!empty($company['company_name_en'])): ?>
                        <div class="subtitle"><?php echo htmlspecialchars($company['company_name_en']); ?></div>
                        <?php endif; ?>
                        <div class="hero-badges">
                            <span class="hero-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                                <?php 
                                $sizeLabels = ['small' => 'ขนาดเล็ก', 'medium' => 'ขนาดกลาง', 'large' => 'ขนาดใหญ่'];
                                echo $sizeLabels[$company['company_size']] ?? 'ไม่ระบุ';
                                ?>
                            </span>
                            <?php if (!empty($company['employee_count'])): ?>
                            <span class="hero-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                <?php echo number_format($company['employee_count']); ?> คน
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($company['tax_id'])): ?>
                            <span class="hero-badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo htmlspecialchars($company['tax_id']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="stats-row animate-in delay-1">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #DBEAFE; color: #3B82F6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <div class="stat-value"><?php echo number_format($company['employee_count'] ?? 0); ?></div>
                    <div class="stat-label">พนักงาน</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #D1FAE5; color: #10B981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="stat-value"><?php echo count(explode('|', $company['industry_type'] ?? '')); ?></div>
                    <div class="stat-label">ประเภทธุรกิจ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #FEF3C7; color: #F59E0B;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="stat-value"><?php echo date('Y'); ?></div>
                    <div class="stat-label">ปีปัจจุบัน</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #EDE9FE; color: #8B5CF6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="stat-value">HICM</div>
                    <div class="stat-label">ระบบประเมิน</div>
                </div>
            </div>
            
            <!-- Cards Grid -->
            <div class="profile-grid animate-in delay-2">
                <!-- Company Info Card -->
                <form method="POST" class="profile-card">
                    <input type="hidden" name="action" value="update_company_info">
                    <div class="profile-card-header">
                        <div class="icon" style="background: #DBEAFE; color: #3B82F6;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                        </div>
                        <h3>ข้อมูลบริษัท</h3>
                    </div>
                    <div class="profile-card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">ชื่อบริษัท (ไทย) <span class="required">*</span></label>
                                <input type="text" name="company_name" class="form-input" value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">ชื่อบริษัท (อังกฤษ)</label>
                                <input type="text" name="company_name_en" class="form-input" value="<?php echo htmlspecialchars($company['company_name_en']); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">เลขประจำตัวผู้เสียภาษี</label>
                                <input type="text" name="tax_id" class="form-input" value="<?php echo htmlspecialchars($company['tax_id']); ?>" placeholder="0-0000-00000-00-0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">ขนาดองค์กร <span class="required">*</span></label>
                                <select name="company_size" class="form-select" required>
                                    <?php foreach ($companySizes as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $company['company_size'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">จำนวนพนักงาน (คน)</label>
                            <input type="number" name="employee_count" class="form-input" value="<?php echo htmlspecialchars($company['employee_count']); ?>" min="1">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn-card-save">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            บันทึกข้อมูลบริษัท
                        </button>
                    </div>
                </form>
                
                <!-- Contact Info Card -->
                <form method="POST" class="profile-card">
                    <input type="hidden" name="action" value="update_contact_info">
                    <div class="profile-card-header">
                        <div class="icon" style="background: #D1FAE5; color: #10B981;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h3>ข้อมูลผู้ติดต่อ</h3>
                    </div>
                    <div class="profile-card-body">
                        <div class="form-group">
                            <label class="form-label">ชื่อผู้ติดต่อ</label>
                            <input type="text" name="contact_name" class="form-input" value="<?php echo htmlspecialchars($company['contact_name']); ?>" placeholder="ชื่อ-นามสกุล">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">อีเมล</label>
                                <input type="email" name="contact_email" class="form-input" value="<?php echo htmlspecialchars($company['contact_email']); ?>" placeholder="email@company.com">
                            </div>
                            <div class="form-group">
                                <label class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" name="contact_phone" class="form-input" value="<?php echo htmlspecialchars($company['contact_phone']); ?>" placeholder="0xx-xxx-xxxx">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ที่อยู่</label>
                            <textarea name="address" class="form-input" rows="3" placeholder="ที่อยู่บริษัท"><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn-card-save" style="background: linear-gradient(135deg, #10B981, #059669);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            บันทึกข้อมูลผู้ติดต่อ
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Industry Types Card (Full Width) -->
            <form method="POST" class="profile-card animate-in delay-3" style="margin-bottom: 2rem;">
                <input type="hidden" name="action" value="update_industry_type">
                <div class="profile-card-header">
                    <div class="icon" style="background: #FEF3C7; color: #F59E0B;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    </div>
                    <h3>ประเภทธุรกิจ / ความเชี่ยวชาญ</h3>
                </div>
                <div class="profile-card-body">
                    <p style="font-size: 0.85rem; color: var(--gray-500); margin-bottom: 1rem;">เลือกประเภทธุรกิจที่ตรงกับบริษัทของคุณ (เลือกได้หลายรายการ)</p>
                    <div class="checkbox-grid">
                        <?php 
                        $selectedIndustries = explode('|', $company['industry_type'] ?? '');
                        foreach (AUDITOR_EXPERTISE as $exp): 
                        ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="industry_type[]" value="<?php echo htmlspecialchars($exp); ?>" 
                                <?php echo in_array($exp, $selectedIndustries) ? 'checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($exp); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn-card-save" style="background: linear-gradient(135deg, #F59E0B, #D97706);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        บันทึกประเภทธุรกิจ
                    </button>
                </div>
            </form>
            
            <!-- Map Location Card (Full Width) - ล่างสุด -->
            <form method="POST" class="profile-card animate-in delay-3" style="margin-bottom: 2rem;">
                <input type="hidden" name="action" value="update_location">
                <div class="profile-card-header">
                    <div class="icon" style="background: #FEE2E2; color: #EF4444;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <h3>ตำแหน่งที่ตั้งบริษัท</h3>
                </div>
                <div class="profile-card-body">
                    <p style="font-size: 0.85rem; color: var(--gray-500); margin-bottom: 1rem;">คลิกที่แผนที่เพื่อปักหมุดตำแหน่งบริษัทของคุณ หรือใช้ปุ่ม "ใช้ตำแหน่งปัจจุบัน"</p>
                    
                    <div class="map-container">
                        <div id="company-map"></div>
                    </div>
                    
                    <div class="map-controls">
                        <button type="button" class="map-btn" onclick="getCurrentLocation()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/></svg>
                            ใช้ตำแหน่งปัจจุบัน
                        </button>
                        <button type="button" class="map-btn" onclick="searchAddress()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            ค้นหาจากที่อยู่
                        </button>
                        <button type="button" class="map-btn" onclick="clearLocation()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            ล้างตำแหน่ง
                        </button>
                    </div>
                    
                    <div class="coords-display">
                        <div class="coord-input">
                            <label>Lat:</label>
                            <input type="text" name="latitude" id="latitude" value="<?php echo htmlspecialchars($company['latitude'] ?? ''); ?>" placeholder="ละติจูด" readonly>
                        </div>
                        <div class="coord-input">
                            <label>Long:</label>
                            <input type="text" name="longitude" id="longitude" value="<?php echo htmlspecialchars($company['longitude'] ?? ''); ?>" placeholder="ลองจิจูด" readonly>
                        </div>
                    </div>
                    
                    <div class="map-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        คลิกที่แผนที่เพื่อเลือกตำแหน่ง หรือลากหมุดเพื่อปรับตำแหน่ง
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn-card-save" style="background: linear-gradient(135deg, #EF4444, #DC2626);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        บันทึกตำแหน่งที่ตั้ง
                    </button>
                </div>
            </form>
            
            <?php else: ?>
            <div class="profile-card" style="text-align: center; padding: 3rem;">
                <div style="width: 80px; height: 80px; background: #FEF3C7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <h3 style="margin: 0 0 0.5rem; color: var(--gray-800);">ไม่พบข้อมูลบริษัท</h3>
                <p style="color: var(--gray-500); margin: 0;">ไม่พบข้อมูลบริษัทสำหรับบัญชี <?php echo htmlspecialchars($user['username']); ?><br>กรุณาติดต่อผู้ดูแลระบบ</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        // Initialize Map
        let map, marker;
        const defaultLat = <?php echo !empty($company['latitude']) ? $company['latitude'] : '13.7563'; ?>;
        const defaultLng = <?php echo !empty($company['longitude']) ? $company['longitude'] : '100.5018'; ?>;
        const hasExistingLocation = <?php echo (!empty($company['latitude']) && !empty($company['longitude'])) ? 'true' : 'false'; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the map
            map = L.map('company-map').setView([defaultLat, defaultLng], hasExistingLocation ? 15 : 6);
            
            // Add tile layer (OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);
            
            // Add existing marker if location exists
            if (hasExistingLocation) {
                addMarker(defaultLat, defaultLng);
            }
            
            // Click on map to add/move marker
            map.on('click', function(e) {
                addMarker(e.latlng.lat, e.latlng.lng);
            });
        });
        
        function addMarker(lat, lng) {
            // Remove existing marker
            if (marker) {
                map.removeLayer(marker);
            }
            
            // Create custom icon
            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: '<div style="background: #EF4444; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"><div style="background: white; width: 10px; height: 10px; border-radius: 50%; margin: 7px;"></div></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });
            
            // Add new marker
            marker = L.marker([lat, lng], {
                draggable: true,
                icon: customIcon
            }).addTo(map);
            
            // Update coordinates
            updateCoordinates(lat, lng);
            
            // Handle marker drag
            marker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });
            
            // Center map on marker
            map.setView([lat, lng], Math.max(map.getZoom(), 13));
        }
        
        function updateCoordinates(lat, lng) {
            document.getElementById('latitude').value = lat.toFixed(8);
            document.getElementById('longitude').value = lng.toFixed(8);
        }
        
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        addMarker(lat, lng);
                        map.setView([lat, lng], 16);
                    },
                    function(error) {
                        alert('ไม่สามารถระบุตำแหน่งได้: ' + error.message);
                    },
                    { enableHighAccuracy: true }
                );
            } else {
                alert('เบราว์เซอร์ของคุณไม่รองรับการระบุตำแหน่ง');
            }
        }
        
        function searchAddress() {
            const address = document.querySelector('textarea[name="address"]').value;
            if (!address) {
                alert('กรุณากรอกที่อยู่ก่อน');
                return;
            }
            
            // Use Nominatim for geocoding
            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(address + ', Thailand'))
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        addMarker(lat, lng);
                        map.setView([lat, lng], 15);
                    } else {
                        alert('ไม่พบตำแหน่งจากที่อยู่ที่ระบุ');
                    }
                })
                .catch(error => {
                    alert('เกิดข้อผิดพลาดในการค้นหา');
                });
        }
        
        function clearLocation() {
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
            document.getElementById('latitude').value = '';
            document.getElementById('longitude').value = '';
            map.setView([13.7563, 100.5018], 6);
        }
    </script>
</body>
</html>
