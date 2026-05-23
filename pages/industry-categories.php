<?php
/**
 * HICM V2025 Assessment System - Industry Categories Management (Admin Only)
 * จัดการหมวดหมู่อุตสาหกรรม
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);

$errors = [];
$success = false;
$db = getDB();

// Check if industry_categories table exists, if not create it
try {
    $stmt = $db->prepare("SHOW TABLES LIKE 'industry_categories'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        // Create table
        $db->prepare("
            CREATE TABLE industry_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name_th VARCHAR(255) NOT NULL,
                name_en VARCHAR(255) NOT NULL,
                description TEXT,
                icon VARCHAR(50) DEFAULT 'industry',
                color VARCHAR(20) DEFAULT '#6366F1',
                display_order INT DEFAULT 0,
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ")->execute();
        
        // Insert default categories
        $defaultCategories = [
            ['อาหารและเกษตร', 'Food & Beverage, Agricultural', 'อุตสาหกรรมอาหาร เครื่องดื่ม และเกษตรกรรม', 'seedling', '#10B981', 1],
            ['ยานยนต์และโลหะ', 'Automotive, Metal, Machinery', 'อุตสาหกรรมยานยนต์ โลหะ และเครื่องจักรกล', 'car', '#3B82F6', 2],
            ['อิเล็กทรอนิกส์', 'Electronics, Electrical, Semiconductor', 'อุตสาหกรรมอิเล็กทรอนิกส์ ไฟฟ้า และเซมิคอนดักเตอร์', 'microchip', '#8B5CF6', 3],
            ['เคมีและพลาสติก', 'Chemical, Plastic, Petrochemical', 'อุตสาหกรรมเคมี พลาสติก และปิโตรเคมี', 'flask', '#F59E0B', 4],
            ['เครื่องนุ่งห่มและสิ่งทอ', 'Textile, Garment, Footwear', 'อุตสาหกรรมสิ่งทอ เครื่องนุ่งห่ม และรองเท้า', 'tshirt', '#EC4899', 5],
            ['ก่อสร้างและวัสดุ', 'Cement, Building Materials, Wood', 'อุตสาหกรรมก่อสร้าง วัสดุก่อสร้าง และไม้', 'hard-hat', '#6366F1', 6],
            ['โลจิสติกส์และบริการ', 'Warehouse, Logistics, Packaging', 'อุตสาหกรรมคลังสินค้า โลจิสติกส์ และบรรจุภัณฑ์', 'truck', '#14B8A6', 7],
            ['อื่นๆ', 'Others', 'อุตสาหกรรมอื่นๆ ที่ไม่อยู่ในหมวดหมู่ข้างต้น', 'ellipsis-h', '#6B7280', 8]
        ];
        
        $insertStmt = $db->prepare("INSERT INTO industry_categories (name_th, name_en, description, icon, color, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($defaultCategories as $cat) {
            $insertStmt->execute($cat);
        }
    }
} catch (Exception $e) {
    $errors[] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_category'])) {
        $nameTh = sanitizeInput($_POST['name_th'] ?? '');
        $nameEn = sanitizeInput($_POST['name_en'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $icon = sanitizeInput($_POST['icon'] ?? 'industry');
        $color = sanitizeInput($_POST['color'] ?? '#6366F1');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        
        if (empty($nameTh)) $errors[] = 'กรุณากรอกชื่อหมวดหมู่ภาษาไทย';
        if (empty($nameEn)) $errors[] = 'กรุณากรอกชื่อหมวดหมู่ภาษาอังกฤษ';
        
        if (empty($errors)) {
            $stmt = $db->prepare("INSERT INTO industry_categories (name_th, name_en, description, icon, color, display_order) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nameTh, $nameEn, $description, $icon, $color, $displayOrder]);
            $success = 'เพิ่มหมวดหมู่สำเร็จ';
        }
    }
    
    if (isset($_POST['update_category'])) {
        $id = intval($_POST['id']);
        $nameTh = sanitizeInput($_POST['name_th'] ?? '');
        $nameEn = sanitizeInput($_POST['name_en'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $icon = sanitizeInput($_POST['icon'] ?? 'industry');
        $color = sanitizeInput($_POST['color'] ?? '#6366F1');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $stmt = $db->prepare("UPDATE industry_categories SET name_th = ?, name_en = ?, description = ?, icon = ?, color = ?, display_order = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$nameTh, $nameEn, $description, $icon, $color, $displayOrder, $isActive, $id]);
        $success = 'อัปเดตหมวดหมู่สำเร็จ';
    }
    
    if (isset($_POST['delete_category'])) {
        $id = intval($_POST['id']);
        $stmt = $db->prepare("DELETE FROM industry_categories WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'ลบหมวดหมู่สำเร็จ';
    }
}

// Get all categories
$stmt = $db->prepare("SELECT * FROM industry_categories ORDER BY display_order, id");
$stmt->execute();
$categories = $stmt->fetchAll();

// Count usage
function getCategoryUsage($db, $categoryName) {
    // Count auditors using this category
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'auditor' AND expertise LIKE ?");
    $stmt->execute(['%' . $categoryName . '%']);
    $auditorCount = $stmt->fetch()['cnt'];
    
    // Count companies using this category
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM companies WHERE industry_type LIKE ?");
    $stmt->execute(['%' . $categoryName . '%']);
    $companyCount = $stmt->fetch()['cnt'];
    
    return ['auditors' => $auditorCount, 'companies' => $companyCount];
}

// Get members list for a category
function getCategoryMembers($db, $categoryName) {
    // Get auditors using this category
    $stmt = $db->prepare("SELECT id, name, email, phone FROM users WHERE role = 'auditor' AND expertise LIKE ? AND is_active = 1 ORDER BY name");
    $stmt->execute(['%' . $categoryName . '%']);
    $auditors = $stmt->fetchAll();
    
    // Get companies using this category
    $stmt = $db->prepare("SELECT c.id, c.company_name, c.province, u.email 
                          FROM companies c 
                          LEFT JOIN users u ON c.user_id = u.id 
                          WHERE c.industry_type LIKE ? 
                          ORDER BY c.company_name");
    $stmt->execute(['%' . $categoryName . '%']);
    $companies = $stmt->fetchAll();
    
    return ['auditors' => $auditors, 'companies' => $companies];
}

// Pre-fetch members for all categories (for modal data)
$categoryMembersData = [];
foreach ($categories as $cat) {
    $fullName = $cat['name_th'] . ' (' . $cat['name_en'] . ')';
    $categoryMembersData[$cat['id']] = getCategoryMembers($db, $fullName);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หมวดหมู่อุตสาหกรรม - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .page-header {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-800));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::after {
            content: "\f1b3";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            top: 50%;
            right: 5%;
            transform: translateY(-50%);
            font-size: 8rem;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .category-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .category-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .category-card.inactive {
            opacity: 0.6;
        }
        
        .category-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .category-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }
        
        .category-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }
        
        .category-info p {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin: 0;
        }
        
        .category-body {
            padding: 1.5rem;
        }
        
        .category-description {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .category-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .category-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        .category-stat i {
            color: var(--gray-400);
        }
        
        .category-stat.clickable {
            cursor: pointer;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius-md);
            margin: -0.5rem -0.75rem;
            transition: all 0.2s;
        }
        
        .category-stat.clickable:hover {
            background: var(--primary-50);
            color: var(--primary-600);
        }
        
        .category-stat.clickable:hover i {
            color: var(--primary-500);
        }
        
        .category-footer {
            padding: 1rem 1.5rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-order {
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        
        .category-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--gray-600);
        }
        
        .btn-icon:hover {
            border-color: var(--primary-500);
            color: var(--primary-600);
            background: var(--primary-50);
        }
        
        .btn-icon.danger:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: #FEF2F2;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #D1FAE5;
            color: #059669;
        }
        
        .status-inactive {
            background: var(--gray-100);
            color: var(--gray-500);
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: none;
            cursor: pointer;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background: var(--gray-50);
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .color-preview {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .color-input {
            width: 60px;
            height: 40px;
            padding: 0;
            border: none;
            cursor: pointer;
            border-radius: var(--radius-md);
        }
        
        .icon-select {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .icon-option {
            width: 40px;
            height: 40px;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }
        
        .icon-option:hover {
            border-color: var(--primary-300);
            background: var(--primary-50);
        }
        
        .icon-option.selected {
            border-color: var(--primary-500);
            background: var(--primary-100);
            color: var(--primary-600);
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        
        .alert-danger {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary-600);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-700);
        }
        
        .btn-outline {
            background: white;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        
        .btn-danger {
            background: #DC2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #B91C1C;
        }
        
        /* Members List Styles */
        .members-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .member-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200);
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        
        .member-item:hover {
            border-color: var(--primary-200);
            background: var(--primary-50);
        }
        
        .member-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }
        
        .member-avatar.auditor {
            background: linear-gradient(135deg, #F59E0B, #D97706);
        }
        
        .member-avatar.company {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
        }
        
        .member-info {
            flex: 1;
            min-width: 0;
        }
        
        .member-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .member-detail {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        .member-detail i {
            width: 16px;
            margin-right: 0.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            padding: 0.25rem;
            background: var(--gray-100);
            border-radius: var(--radius-lg);
        }
        
        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .tab-btn:hover {
            color: var(--gray-900);
        }
        
        .tab-btn.active {
            background: white;
            color: var(--primary-600);
            box-shadow: var(--shadow-sm);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">หมวดหมู่อุตสาหกรรม</h1>
                    <p class="page-subtitle">จัดการประเภทอุตสาหกรรมสำหรับบริษัทและความเชี่ยวชาญของกรรมการ</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('createModal')" style="background: white; color: var(--primary-700);">
                    <i class="fas fa-plus"></i>
                    เพิ่มหมวดหมู่ใหม่
                </button>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo implode('<br>', $errors); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary-500);">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count($categories); ?></div>
                        <div class="stat-label">หมวดหมู่ทั้งหมด</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #10B981;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo count(array_filter($categories, fn($c) => $c['is_active'])); ?></div>
                        <div class="stat-label">หมวดหมู่ใช้งาน</div>
                    </div>
                </div>
                <?php
                $totalAuditors = 0;
                $totalCompanies = 0;
                foreach ($categories as $cat) {
                    $fullName = $cat['name_th'] . ' (' . $cat['name_en'] . ')';
                    $usage = getCategoryUsage($db, $fullName);
                    $totalAuditors += $usage['auditors'];
                    $totalCompanies += $usage['companies'];
                }
                ?>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #F59E0B;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $totalAuditors; ?></div>
                        <div class="stat-label">การใช้งานโดยกรรมการ</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #3B82F6;">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <div class="stat-value"><?php echo $totalCompanies; ?></div>
                        <div class="stat-label">การใช้งานโดยบริษัท</div>
                    </div>
                </div>
            </div>
            
            <!-- Categories Grid -->
            <div class="categories-grid">
                <?php foreach ($categories as $cat): 
                    $fullName = $cat['name_th'] . ' (' . $cat['name_en'] . ')';
                    $usage = getCategoryUsage($db, $fullName);
                ?>
                <div class="category-card <?php echo $cat['is_active'] ? '' : 'inactive'; ?>">
                    <div class="category-header">
                        <div class="category-icon" style="background: <?php echo htmlspecialchars($cat['color']); ?>;">
                            <i class="fas fa-<?php echo htmlspecialchars($cat['icon']); ?>"></i>
                        </div>
                        <div class="category-info">
                            <h3><?php echo htmlspecialchars($cat['name_th']); ?></h3>
                            <p><?php echo htmlspecialchars($cat['name_en']); ?></p>
                        </div>
                    </div>
                    <div class="category-body">
                        <p class="category-description"><?php echo htmlspecialchars($cat['description'] ?: 'ไม่มีคำอธิบาย'); ?></p>
                        <div class="category-stats">
                            <div class="category-stat clickable" onclick="showMembers(<?php echo $cat['id']; ?>, 'auditors', '<?php echo htmlspecialchars($cat['name_th'], ENT_QUOTES); ?>')" title="คลิกเพื่อดูรายชื่อกรรมการ">
                                <i class="fas fa-user-tie"></i>
                                <span><?php echo $usage['auditors']; ?> กรรมการ</span>
                            </div>
                            <div class="category-stat clickable" onclick="showMembers(<?php echo $cat['id']; ?>, 'companies', '<?php echo htmlspecialchars($cat['name_th'], ENT_QUOTES); ?>')" title="คลิกเพื่อดูรายชื่อบริษัท">
                                <i class="fas fa-building"></i>
                                <span><?php echo $usage['companies']; ?> บริษัท</span>
                            </div>
                        </div>
                    </div>
                    <div class="category-footer">
                        <div class="category-order">
                            ลำดับ: <?php echo $cat['display_order']; ?>
                            <span class="status-badge <?php echo $cat['is_active'] ? 'status-active' : 'status-inactive'; ?>" style="margin-left: 0.5rem;">
                                <?php echo $cat['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน'; ?>
                            </span>
                        </div>
                        <div class="category-actions">
                            <button class="btn-icon" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon danger" onclick="confirmDelete(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name_th']); ?>')" title="ลบ">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    
    <!-- Create Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">เพิ่มหมวดหมู่อุตสาหกรรม</h3>
                <button class="modal-close" onclick="closeModal('createModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="create_category" value="1">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name_th" class="form-input" required placeholder="เช่น อาหารและเกษตร">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name_en" class="form-input" required placeholder="เช่น Food & Beverage, Agricultural">
                    </div>
                    <div class="form-group">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea name="description" class="form-textarea" placeholder="อธิบายรายละเอียดของหมวดหมู่นี้"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ไอคอน</label>
                        <input type="hidden" name="icon" id="createIcon" value="industry">
                        <div class="icon-select" id="createIconSelect">
                            <?php 
                            $icons = ['industry', 'seedling', 'car', 'microchip', 'flask', 'tshirt', 'hard-hat', 'truck', 'cogs', 'box', 'utensils', 'bolt'];
                            foreach ($icons as $icon): 
                            ?>
                            <div class="icon-option <?php echo $icon === 'industry' ? 'selected' : ''; ?>" data-icon="<?php echo $icon; ?>" onclick="selectIcon('create', '<?php echo $icon; ?>')">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">สี</label>
                        <div class="color-preview">
                            <input type="color" name="color" id="createColor" class="color-input" value="#6366F1">
                            <span id="createColorHex">#6366F1</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ลำดับการแสดง</label>
                        <input type="number" name="display_order" class="form-input" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">เพิ่มหมวดหมู่</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">แก้ไขหมวดหมู่อุตสาหกรรม</h3>
                <button class="modal-close" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="update_category" value="1">
                <input type="hidden" name="id" id="editId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาไทย <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name_th" id="editNameTh" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ชื่อภาษาอังกฤษ <span style="color: var(--danger);">*</span></label>
                        <input type="text" name="name_en" id="editNameEn" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">คำอธิบาย</label>
                        <textarea name="description" id="editDescription" class="form-textarea"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ไอคอน</label>
                        <input type="hidden" name="icon" id="editIcon" value="industry">
                        <div class="icon-select" id="editIconSelect">
                            <?php foreach ($icons as $icon): ?>
                            <div class="icon-option" data-icon="<?php echo $icon; ?>" onclick="selectIcon('edit', '<?php echo $icon; ?>')">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">สี</label>
                        <div class="color-preview">
                            <input type="color" name="color" id="editColor" class="color-input" value="#6366F1">
                            <span id="editColorHex">#6366F1</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ลำดับการแสดง</label>
                        <input type="number" name="display_order" id="editDisplayOrder" class="form-input" min="0">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="editIsActive" value="1">
                            <span>เปิดใช้งาน</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">ยืนยันการลบ</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="delete_category" value="1">
                <input type="hidden" name="id" id="deleteId">
                <div class="modal-body">
                    <div style="text-align: center;">
                        <div style="width: 64px; height: 64px; background: #FEE2E2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                            <i class="fas fa-trash" style="font-size: 1.5rem; color: #DC2626;"></i>
                        </div>
                        <p style="color: var(--gray-600);">คุณต้องการลบหมวดหมู่ <strong id="deleteName"></strong> ใช่หรือไม่?</p>
                        <p style="color: var(--gray-500); font-size: 0.85rem;">การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ลบหมวดหมู่</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Members Modal -->
    <div class="modal-overlay" id="membersModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title" id="membersModalTitle">รายชื่อสมาชิก</h3>
                <button class="modal-close" onclick="closeModal('membersModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <!-- Tab Buttons -->
                <div class="tab-buttons">
                    <button class="tab-btn active" onclick="switchTab('auditors')" id="tabAuditors">
                        <i class="fas fa-user-tie"></i>
                        <span>กรรมการ (<span id="auditorCount">0</span>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('companies')" id="tabCompanies">
                        <i class="fas fa-building"></i>
                        <span>บริษัท (<span id="companyCount">0</span>)</span>
                    </button>
                </div>
                
                <!-- Auditors List -->
                <div class="tab-content active" id="contentAuditors">
                    <div class="members-list" id="auditorsList"></div>
                </div>
                
                <!-- Companies List -->
                <div class="tab-content" id="contentCompanies">
                    <div class="members-list" id="companiesList"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('membersModal')">ปิด</button>
            </div>
        </div>
    </div>
    
    <script>
        // Category members data from PHP
        const categoryMembersData = <?php echo json_encode($categoryMembersData, JSON_UNESCAPED_UNICODE); ?>;
        
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        function selectIcon(prefix, icon) {
            // Update hidden input
            document.getElementById(prefix + 'Icon').value = icon;
            
            // Update visual selection
            const container = document.getElementById(prefix + 'IconSelect');
            container.querySelectorAll('.icon-option').forEach(opt => {
                opt.classList.remove('selected');
                if (opt.dataset.icon === icon) {
                    opt.classList.add('selected');
                }
            });
        }
        
        function editCategory(cat) {
            document.getElementById('editId').value = cat.id;
            document.getElementById('editNameTh').value = cat.name_th;
            document.getElementById('editNameEn').value = cat.name_en;
            document.getElementById('editDescription').value = cat.description || '';
            document.getElementById('editDisplayOrder').value = cat.display_order;
            document.getElementById('editColor').value = cat.color;
            document.getElementById('editColorHex').textContent = cat.color;
            document.getElementById('editIsActive').checked = cat.is_active == 1;
            
            selectIcon('edit', cat.icon);
            
            openModal('editModal');
        }
        
        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteName').textContent = name;
            openModal('deleteModal');
        }
        
        // Show members modal
        function showMembers(categoryId, initialTab, categoryName) {
            const data = categoryMembersData[categoryId];
            if (!data) return;
            
            // Update modal title
            document.getElementById('membersModalTitle').textContent = 'รายชื่อในหมวด: ' + categoryName;
            
            // Update counts
            document.getElementById('auditorCount').textContent = data.auditors.length;
            document.getElementById('companyCount').textContent = data.companies.length;
            
            // Render auditors list
            const auditorsList = document.getElementById('auditorsList');
            if (data.auditors.length === 0) {
                auditorsList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>ไม่มีกรรมการในหมวดหมู่นี้</p>
                    </div>
                `;
            } else {
                auditorsList.innerHTML = data.auditors.map(a => `
                    <div class="member-item">
                        <div class="member-avatar auditor">
                            ${a.name.charAt(0)}
                        </div>
                        <div class="member-info">
                            <div class="member-name">${escapeHtml(a.name)}</div>
                            <div class="member-detail">
                                ${a.email ? `<i class="fas fa-envelope"></i>${escapeHtml(a.email)}` : ''}
                                ${a.phone ? `<span style="margin-left: 1rem;"><i class="fas fa-phone"></i>${escapeHtml(a.phone)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            // Render companies list
            const companiesList = document.getElementById('companiesList');
            if (data.companies.length === 0) {
                companiesList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <p>ไม่มีบริษัทในหมวดหมู่นี้</p>
                    </div>
                `;
            } else {
                companiesList.innerHTML = data.companies.map(c => `
                    <div class="member-item">
                        <div class="member-avatar company">
                            ${c.company_name.charAt(0)}
                        </div>
                        <div class="member-info">
                            <div class="member-name">${escapeHtml(c.company_name)}</div>
                            <div class="member-detail">
                                ${c.province ? `<i class="fas fa-map-marker-alt"></i>${escapeHtml(c.province)}` : ''}
                                ${c.email ? `<span style="margin-left: 1rem;"><i class="fas fa-envelope"></i>${escapeHtml(c.email)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            // Switch to initial tab
            switchTab(initialTab);
            
            openModal('membersModal');
        }
        
        // Switch tab
        function switchTab(tab) {
            // Update buttons
            document.getElementById('tabAuditors').classList.toggle('active', tab === 'auditors');
            document.getElementById('tabCompanies').classList.toggle('active', tab === 'companies');
            
            // Update content
            document.getElementById('contentAuditors').classList.toggle('active', tab === 'auditors');
            document.getElementById('contentCompanies').classList.toggle('active', tab === 'companies');
        }
        
        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Color input preview
        document.getElementById('createColor').addEventListener('input', function() {
            document.getElementById('createColorHex').textContent = this.value.toUpperCase();
        });
        
        document.getElementById('editColor').addEventListener('input', function() {
            document.getElementById('editColorHex').textContent = this.value.toUpperCase();
        });
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>