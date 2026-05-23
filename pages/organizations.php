<?php
/**
 * HICM V2025 - Organizations Management (Admin)
 * หน้าจัดการหน่วยงานภาคีเครือข่าย
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$user = getCurrentUser();
$db = getDB();

$errors = [];
$success = false;
$successMessage = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new organization
    if (isset($_POST['add_organization'])) {
        $name = sanitizeInput($_POST['name'] ?? '');
        $shortName = sanitizeInput($_POST['short_name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');
        $contactEmail = sanitizeInput($_POST['contact_email'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $website = sanitizeInput($_POST['website'] ?? '');
        
        if (empty($name)) {
            $errors[] = 'กรุณากรอกชื่อหน่วยงาน';
        }
        
        if (empty($errors)) {
            try {
                // Get max display_order
                $maxOrder = $db->prepare("SELECT MAX(display_order) FROM organizations")->fetchColumn() ?? 0;
                
                $stmt = $db->prepare("
                    INSERT INTO organizations (name, short_name, description, contact_phone, contact_email, address, website, display_order, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $shortName, $description, $contactPhone, $contactEmail, $address, $website, $maxOrder + 1, $user['id']]);
                
                $success = true;
                $successMessage = 'เพิ่มหน่วยงานเรียบร้อยแล้ว';
                logActivity($user['id'], 'add_organization', "เพิ่มหน่วยงาน: $name");
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
    
    // Update organization
    if (isset($_POST['update_organization'])) {
        $id = intval($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $shortName = sanitizeInput($_POST['short_name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');
        $contactEmail = sanitizeInput($_POST['contact_email'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $website = sanitizeInput($_POST['website'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($name)) {
            $errors[] = 'กรุณากรอกชื่อหน่วยงาน';
        }
        
        if (empty($errors) && $id > 0) {
            try {
                $stmt = $db->prepare("
                    UPDATE organizations 
                    SET name = ?, short_name = ?, description = ?, contact_phone = ?, 
                        contact_email = ?, address = ?, website = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $shortName, $description, $contactPhone, $contactEmail, $address, $website, $isActive, $id]);
                
                $success = true;
                $successMessage = 'อัปเดตหน่วยงานเรียบร้อยแล้ว';
                logActivity($user['id'], 'update_organization', "อัปเดตหน่วยงาน ID: $id");
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
    
    // Delete organization
    if (isset($_POST['delete_organization'])) {
        $id = intval($_POST['id'] ?? 0);
        
        if ($id > 0) {
            try {
                // Check if any users are assigned
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE organization_id = ?");
                $stmt->execute([$id]);
                $userCount = $stmt->fetchColumn();
                
                if ($userCount > 0) {
                    $errors[] = "ไม่สามารถลบได้ เนื่องจากมีผู้ใช้สังกัดหน่วยงานนี้ $userCount คน";
                } else {
                    $stmt = $db->prepare("DELETE FROM organizations WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $success = true;
                    $successMessage = 'ลบหน่วยงานเรียบร้อยแล้ว';
                    logActivity($user['id'], 'delete_organization', "ลบหน่วยงาน ID: $id");
                }
            } catch (Exception $e) {
                $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
    
    // Reorder organizations
    if (isset($_POST['reorder'])) {
        $orders = $_POST['order'] ?? [];
        try {
            foreach ($orders as $id => $order) {
                $stmt = $db->prepare("UPDATE organizations SET display_order = ? WHERE id = ?");
                $stmt->execute([intval($order), intval($id)]);
            }
            $success = true;
            $successMessage = 'เรียงลำดับเรียบร้อยแล้ว';
        } catch (Exception $e) {
            $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
        }
    }
}

// Get all organizations with members
$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM users WHERE organization_id = o.id) as user_count
    FROM organizations o
    ORDER BY o.display_order
");
$stmt->execute();
$organizations = $stmt->fetchAll();

// Get members for each organization
$orgMembers = [];
foreach ($organizations as $org) {
    $stmt = $db->prepare("SELECT id, name, email, phone FROM users WHERE organization_id = ? AND role = 'auditor' ORDER BY name");
    $stmt->execute([$org['id']]);
    $orgMembers[$org['id']] = $stmt->fetchAll();
}

// Get auditors without organization
$stmt = $db->prepare("SELECT id, name, email, phone FROM users WHERE role = 'auditor' AND (organization_id IS NULL OR organization_id = 0) ORDER BY name");
$stmt->execute();
$unassignedAuditors = $stmt->fetchAll();

// Get edit organization if requested
$editOrg = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM organizations WHERE id = ?");
    $stmt->execute([$editId]);
    $editOrg = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหน่วยงาน - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .org-header {
            background: linear-gradient(135deg, #0c4a6e 0%, #0369a1 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .org-header h1 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .org-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.875rem;
        }
        
        .org-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.25rem;
        }
        
        .org-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .org-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-4px);
        }
        
        .org-card.inactive {
            opacity: 0.6;
            border-color: var(--gray-300);
        }
        
        .org-card-header {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid #bae6fd;
        }
        
        .org-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0284c7, #0369a1);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .org-title {
            font-weight: 600;
            color: #0c4a6e;
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .org-short-name {
            font-size: 0.75rem;
            color: #0369a1;
            margin-top: 0.25rem;
        }
        
        .org-card-body {
            padding: 1.25rem;
        }
        
        .org-info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .org-info-row svg {
            width: 16px;
            height: 16px;
            color: var(--gray-400);
            flex-shrink: 0;
        }
        
        .org-card-footer {
            padding: 1rem 1.25rem;
            background: var(--gray-50);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--gray-200);
        }
        
        .org-user-count {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        .org-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .org-actions .btn {
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
        }
        
        .form-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-card h3 {
            margin: 0 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-900);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .org-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .order-input {
            width: 60px;
            text-align: center;
            padding: 0.25rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        
        .members-section {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px dashed var(--gray-200);
        }
        
        .members-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--primary-600);
            cursor: pointer;
            padding: 0.25rem 0;
        }
        
        .members-toggle:hover {
            color: var(--primary-700);
        }
        
        .members-list {
            display: none;
            margin-top: 0.75rem;
        }
        
        .members-list.show {
            display: block;
        }
        
        .member-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            margin-bottom: 0.35rem;
            font-size: 0.8rem;
        }
        
        .member-avatar {
            width: 28px;
            height: 28px;
            background: linear-gradient(135deg, #06b6d4, #0891b2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .member-info {
            flex: 1;
            min-width: 0;
        }
        
        .member-name {
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .member-contact {
            font-size: 0.7rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .unassigned-section {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px dashed #f59e0b;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .unassigned-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .unassigned-header h3 {
            margin: 0;
            color: #92400e;
            font-size: 1rem;
        }
        
        .unassigned-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        
        .unassigned-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
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
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-6">
                    <?php foreach ($errors as $error): ?>
                        <div>✕ <?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success mb-6">
                    ✓ <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="org-header">
                <div>
                    <h1>
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        จัดการหน่วยงานภาคีเครือข่าย
                    </h1>
                    <p>เพิ่ม ลบ แก้ไขข้อมูลหน่วยงานสำหรับกรรมการประเมิน</p>
                </div>
                <button class="btn btn-light" onclick="showAddModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    เพิ่มหน่วยงาน
                </button>
            </div>
            
            <!-- Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="card" style="padding: 1.25rem; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary-600);"><?php echo count($organizations); ?></div>
                    <div style="font-size: 0.875rem; color: var(--gray-500);">หน่วยงานทั้งหมด</div>
                </div>
                <div class="card" style="padding: 1.25rem; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?php echo count(array_filter($organizations, fn($o) => $o['is_active'])); ?></div>
                    <div style="font-size: 0.875rem; color: var(--gray-500);">เปิดใช้งาน</div>
                </div>
                <div class="card" style="padding: 1.25rem; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?php echo array_sum(array_column($organizations, 'user_count')); ?></div>
                    <div style="font-size: 0.875rem; color: var(--gray-500);">กรรมการในสังกัด</div>
                </div>
                <div class="card" style="padding: 1.25rem; text-align: center;">
                    <div style="font-size: 2rem; font-weight: 700; color: #ef4444;"><?php echo count($unassignedAuditors); ?></div>
                    <div style="font-size: 0.875rem; color: var(--gray-500);">ยังไม่มีสังกัด</div>
                </div>
            </div>
            
            <!-- Unassigned Auditors -->
            <?php if (!empty($unassignedAuditors)): ?>
            <div class="unassigned-section">
                <div class="unassigned-header">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#92400e" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <h3>กรรมการที่ยังไม่มีสังกัดหน่วยงาน (<?php echo count($unassignedAuditors); ?> คน)</h3>
                </div>
                <div class="unassigned-grid">
                    <?php foreach ($unassignedAuditors as $auditor): ?>
                    <div class="unassigned-item">
                        <div class="member-avatar" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <?php echo mb_substr($auditor['name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div class="member-info">
                            <div class="member-name"><?php echo htmlspecialchars($auditor['name']); ?></div>
                            <?php if (!empty($auditor['email'])): ?>
                            <div class="member-contact"><?php echo htmlspecialchars($auditor['email']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Organizations Grid -->
            <div class="org-grid">
                <?php foreach ($organizations as $org): ?>
                <div class="org-card <?php echo $org['is_active'] ? '' : 'inactive'; ?>">
                    <div class="org-card-header">
                        <div class="org-avatar">
                            <?php echo mb_substr($org['name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div class="org-title"><?php echo htmlspecialchars($org['name']); ?></div>
                            <?php if (!empty($org['short_name'])): ?>
                            <div class="org-short-name"><?php echo htmlspecialchars($org['short_name']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="org-card-body">
                        <?php if (!empty($org['contact_phone'])): ?>
                        <div class="org-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                            <?php echo htmlspecialchars($org['contact_phone']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($org['contact_email'])): ?>
                        <div class="org-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            <?php echo htmlspecialchars($org['contact_email']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($org['website'])): ?>
                        <div class="org-info-row">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                            </svg>
                            <a href="<?php echo htmlspecialchars($org['website']); ?>" target="_blank" style="color: var(--primary-600);">
                                <?php echo htmlspecialchars(parse_url($org['website'], PHP_URL_HOST) ?: $org['website']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($org['contact_phone']) && empty($org['contact_email']) && empty($org['website'])): ?>
                        <div class="org-info-row" style="color: var(--gray-400); font-style: italic;">
                            ไม่มีข้อมูลติดต่อ
                        </div>
                        <?php endif; ?>
                        
                        <!-- Members Section -->
                        <?php if (!empty($orgMembers[$org['id']])): ?>
                        <div class="members-section">
                            <div class="members-toggle" onclick="toggleMembers(<?php echo $org['id']; ?>)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                                </svg>
                                <span id="toggleText<?php echo $org['id']; ?>">ดูรายชื่อสมาชิก (<?php echo count($orgMembers[$org['id']]); ?>)</span>
                                <svg id="toggleIcon<?php echo $org['id']; ?>" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="transition: transform 0.2s;">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="members-list" id="members<?php echo $org['id']; ?>">
                                <?php foreach ($orgMembers[$org['id']] as $member): ?>
                                <div class="member-item">
                                    <div class="member-avatar">
                                        <?php echo mb_substr($member['name'], 0, 1, 'UTF-8'); ?>
                                    </div>
                                    <div class="member-info">
                                        <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                                        <?php if (!empty($member['phone'])): ?>
                                        <div class="member-contact">📞 <?php echo htmlspecialchars($member['phone']); ?></div>
                                        <?php elseif (!empty($member['email'])): ?>
                                        <div class="member-contact">✉️ <?php echo htmlspecialchars($member['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="org-card-footer">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span class="org-user-count">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                </svg>
                                <?php echo $org['user_count']; ?> คน
                            </span>
                            <span class="status-badge <?php echo $org['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $org['is_active'] ? '● เปิดใช้งาน' : '○ ปิดใช้งาน'; ?>
                            </span>
                        </div>
                        <div class="org-actions">
                            <button class="btn btn-sm btn-outline" onclick="editOrganization(<?php echo htmlspecialchars(json_encode($org)); ?>)">
                                แก้ไข
                            </button>
                            <?php if ($org['user_count'] == 0): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('ยืนยันการลบหน่วยงานนี้?');">
                                <input type="hidden" name="delete_organization" value="1">
                                <input type="hidden" name="id" value="<?php echo $org['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">ลบ</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($organizations)): ?>
            <div class="card" style="padding: 3rem; text-align: center; color: var(--gray-500);">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 1rem; opacity: 0.5;">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
                <h3>ยังไม่มีหน่วยงาน</h3>
                <p>คลิกปุ่ม "เพิ่มหน่วยงาน" เพื่อเริ่มต้น</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Add/Edit Modal -->
    <div id="orgModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle" style="margin: 0;">เพิ่มหน่วยงาน</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="orgForm">
                <input type="hidden" name="add_organization" id="formAction" value="1">
                <input type="hidden" name="id" id="orgId" value="">
                
                <div class="form-group">
                    <label class="form-label">ชื่อหน่วยงาน <span style="color: red;">*</span></label>
                    <input type="text" name="name" id="orgName" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">ชื่อย่อ</label>
                    <input type="text" name="short_name" id="orgShortName" class="form-input" placeholder="เช่น สสจ., อบจ.">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">โทรศัพท์</label>
                        <input type="text" name="contact_phone" id="orgPhone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">อีเมล</label>
                        <input type="email" name="contact_email" id="orgEmail" class="form-input">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">เว็บไซต์</label>
                    <input type="url" name="website" id="orgWebsite" class="form-input" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">ที่อยู่</label>
                    <textarea name="address" id="orgAddress" class="form-input" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">รายละเอียด</label>
                    <textarea name="description" id="orgDescription" class="form-input" rows="2"></textarea>
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="orgActive" checked>
                        เปิดใช้งาน
                    </label>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal()" style="flex: 1;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'เพิ่มหน่วยงาน';
            document.getElementById('formAction').name = 'add_organization';
            document.getElementById('orgId').value = '';
            document.getElementById('orgName').value = '';
            document.getElementById('orgShortName').value = '';
            document.getElementById('orgPhone').value = '';
            document.getElementById('orgEmail').value = '';
            document.getElementById('orgWebsite').value = '';
            document.getElementById('orgAddress').value = '';
            document.getElementById('orgDescription').value = '';
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('orgModal').classList.add('active');
        }
        
        function editOrganization(org) {
            document.getElementById('modalTitle').textContent = 'แก้ไขหน่วยงาน';
            document.getElementById('formAction').name = 'update_organization';
            document.getElementById('orgId').value = org.id;
            document.getElementById('orgName').value = org.name || '';
            document.getElementById('orgShortName').value = org.short_name || '';
            document.getElementById('orgPhone').value = org.contact_phone || '';
            document.getElementById('orgEmail').value = org.contact_email || '';
            document.getElementById('orgWebsite').value = org.website || '';
            document.getElementById('orgAddress').value = org.address || '';
            document.getElementById('orgDescription').value = org.description || '';
            document.getElementById('orgActive').checked = org.is_active == 1;
            document.getElementById('statusGroup').style.display = 'block';
            document.getElementById('orgModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('orgModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('orgModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        function toggleMembers(orgId) {
            const list = document.getElementById('members' + orgId);
            const icon = document.getElementById('toggleIcon' + orgId);
            const text = document.getElementById('toggleText' + orgId);
            
            if (list.classList.contains('show')) {
                list.classList.remove('show');
                icon.style.transform = 'rotate(0deg)';
                text.textContent = text.textContent.replace('ซ่อน', 'ดู');
            } else {
                list.classList.add('show');
                icon.style.transform = 'rotate(180deg)';
                text.textContent = text.textContent.replace('ดู', 'ซ่อน');
            }
        }
    </script>
</body>
</html>
