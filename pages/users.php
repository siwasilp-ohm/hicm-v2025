<?php
/**
 * HICM V2025 Assessment System - User Management Page (Admin Only)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/companies.php';

requireRole(ROLE_ADMIN);

// AJAX: Check Username Availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_username'])) {
    header('Content-Type: application/json');
    $username = sanitizeInput($_POST['username'] ?? '');
    if (empty($username)) {
        echo json_encode(['available' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_.]{3,20}$/', $username)) {
        echo json_encode(['available' => false, 'message' => 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . (3-20 ตัว)']);
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['available' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว']);
    } else {
        echo json_encode(['available' => true, 'message' => 'ชื่อผู้ใช้นี้สามารถใช้ได้']);
    }
    exit;
}

// AJAX: Check Email Availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_email'])) {
    header('Content-Type: application/json');
    $email = sanitizeInput($_POST['email'] ?? '');
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['available' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['available' => false, 'message' => 'อีเมลนี้มีผู้ใช้งานแล้ว']);
    } else {
        echo json_encode(['available' => true, 'message' => 'อีเมลนี้สามารถใช้ได้']);
    }
    exit;
}

$errors = [];
$success = false;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $role = sanitizeInput($_POST['role'] ?? '');
        $data = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'name' => sanitizeInput($_POST['name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'role' => $role,
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'expertise' => $_POST['expertise'] ?? [],
            'hicm_expertise' => $_POST['hicm_expertise'] ?? [],
            'organization_id' => isset($_POST['organization_id']) ? (intval($_POST['organization_id']) ?: null) : null,
        ];

        // Company-specific fields
        if ($role === 'company') {
            $data['company_name'] = sanitizeInput($_POST['company_name'] ?? '');
            $data['company_name_en'] = sanitizeInput($_POST['company_name_en'] ?? '');
            $data['tax_id'] = sanitizeInput($_POST['tax_id'] ?? '');
            $data['address'] = sanitizeInput($_POST['address'] ?? '');
            $data['province'] = sanitizeInput($_POST['province'] ?? '');
            $data['district'] = sanitizeInput($_POST['district'] ?? '');
            $data['postal_code'] = sanitizeInput($_POST['postal_code'] ?? '');
            $data['company_phone'] = sanitizeInput($_POST['company_phone'] ?? '');
            $data['website'] = sanitizeInput($_POST['website'] ?? '');
            $data['company_size'] = sanitizeInput($_POST['company_size'] ?? '');
            $data['employee_count'] = intval($_POST['employee_count'] ?? 0);
            $data['established_year'] = intval($_POST['established_year'] ?? 0);
            $data['contact_position'] = sanitizeInput($_POST['contact_position'] ?? '');
            $data['description'] = sanitizeInput($_POST['description'] ?? '');
        }
        
        if (empty($data['username'])) $errors[] = 'กรุณากรอกชื่อผู้ใช้';
        if (empty($data['name'])) $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
        if (empty($data['email'])) $errors[] = 'กรุณากรอกอีเมล';
        if (empty($data['password'])) $errors[] = 'กรุณากรอกรหัสผ่าน';
        if (empty($data['role'])) $errors[] = 'กรุณาเลือกบทบาท';
        if ($role === 'company' && empty($data['company_name'])) $errors[] = 'กรุณากรอกชื่อบริษัท';
        
        if (empty($errors)) {
            $result = createUser($data);
            if ($result['success']) {
                $success = 'สร้างผู้ใช้สำเร็จ';
            } else {
                $errors[] = $result['message'];
            }
        }
    }
    
    if (isset($_POST['update_user'])) {
        $userId = intval($_POST['user_id']);
        $data = [
            'username' => sanitizeInput($_POST['username'] ?? ''),
            'name' => sanitizeInput($_POST['name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'role' => sanitizeInput($_POST['role'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'expertise' => $_POST['expertise'] ?? [],
            'hicm_expertise' => $_POST['hicm_expertise'] ?? [],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'organization_id' => isset($_POST['organization_id']) ? (intval($_POST['organization_id']) ?: null) : null,
            'company_name' => sanitizeInput($_POST['company_name'] ?? ''),
        ];
        
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        $result = updateUser($userId, $data);
        if ($result['success']) {
            $success = 'อัปเดตข้อมูลสำเร็จ';
        } else {
            $errors[] = $result['message'];
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $userId = intval($_POST['user_id']);
        $result = deleteUser($userId);
        if ($result['success']) {
            $success = 'ลบผู้ใช้สำเร็จ';
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Get users
$roleFilter = $_GET['role'] ?? '';
$search = $_GET['search'] ?? '';
$users = getAllUsers($roleFilter, $search);

$roleLabels = [
    'admin' => ['label' => 'ผู้ดูแลระบบ', 'class' => 'badge-danger'],
    'auditor' => ['label' => 'กรรมการ', 'class' => 'badge-warning'],
    'company' => ['label' => 'บริษัท', 'class' => 'badge-success'],
    'ceo' => ['label' => 'CEO', 'class' => 'badge-primary']
];

// Get organizations for auditor assignment
$db = getDB();
$stmt = $db->prepare("SELECT id, name, short_name FROM organizations WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$organizations = $stmt->fetchAll();

// Get company sizes for company creation
$companySizes = getCompanySizes();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .report-header {
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

        .report-header::after {
            content: "\f500";
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
        
        .report-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .report-meta {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        /* Dashboard-style Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-200);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.2;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .filter-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 1.5rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(226, 232, 240, 0.8);
            position: relative;
            overflow: hidden;
        }
        
        .filter-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-500), var(--primary-400), #8b5cf6);
        }
        
        .search-box-pro {
            position: relative;
            flex-grow: 1;
            min-width: 300px;
        }
        
        .search-box-pro .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .search-box-pro input {
            width: 100%;
            height: 52px;
            padding: 0 1rem 0 3rem;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.95rem;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }
        
        .search-box-pro input:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1), 0 4px 12px rgba(59, 130, 246, 0.08);
        }
        
        .search-box-pro input:focus + .search-icon,
        .search-box-pro input:not(:placeholder-shown) + .search-icon {
            color: var(--primary-500);
        }
        
        .search-box-pro input::placeholder {
            color: #94a3b8;
        }
        
        .filter-select-pro {
            position: relative;
            min-width: 180px;
        }
        
        .filter-select-pro select {
            width: 100%;
            height: 52px;
            padding: 0 2.5rem 0 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }
        
        .filter-select-pro select:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .filter-select-pro::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            pointer-events: none;
            transition: transform 0.2s ease;
        }
        
        .filter-select-pro:focus-within::after {
            transform: translateY(-50%) rotate(180deg);
            color: var(--primary-500);
        }
        
        .filter-btn-pro {
            height: 52px;
            padding: 0 1.5rem;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        
        .filter-btn-primary {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .filter-btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
            box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
            transform: translateY(-1px);
        }
        
        .filter-btn-outline {
            background: white;
            color: #64748b;
            border: 2px solid #e2e8f0;
        }
        
        .filter-btn-outline:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #475569;
        }
        
        .filter-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .search-box-pro {
                min-width: 100%;
            }
            .filter-select-pro {
                flex: 1;
                min-width: 140px;
            }
            .filter-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        .user-avatar-lg {
            width: 42px;
            height: 42px;
            min-width: 42px;
            min-height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary-100), var(--primary-200));
            color: var(--primary-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            flex-shrink: 0;
            object-fit: cover;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active-pill {
            background-color: #ecfdf5;
            color: #059669;
        }

        .status-inactive-pill {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .dot-active { background-color: #10b981; }
        .dot-inactive { background-color: #9ca3af; }

        .table-pro thead th {
            background-color: var(--gray-50);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: var(--gray-600);
            padding: 1.25rem 1rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .table-pro tbody td {
            padding: 1.25rem 1rem;
            vertical-align: middle;
        }

        /* Modal Header Positioning Fix */
        .modal-header {
            position: relative;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .modal-close-abs {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: var(--gray-50);
            color: var(--gray-400);
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-close-abs:hover {
            background: #fee2e2;
            color: #ef4444;
            transform: rotate(90deg);
        }

        .btn-plus {
            background-color: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            backdrop-filter: blur(4px);
            transition: all 0.3s;
        }

        .btn-plus:hover {
            background-color: white;
            color: var(--primary-700);
            transform: translateY(-2px);
        }

        /* === Role Selection Cards === */
        .role-card {
            cursor: pointer;
            display: block;
        }
        .role-card-inner {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s ease;
            background: white;
        }
        .role-card-inner i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }
        .role-card:hover .role-card-inner {
            border-color: var(--primary-300);
            background: var(--primary-50);
        }
        .role-card input:checked + .role-card-inner {
            border-color: var(--primary-500);
            background: var(--primary-50);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        /* === Form Sections === */
        .form-section {
            background: var(--gray-50);
            border: 1px solid var(--gray-100);
            border-radius: var(--radius-lg);
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .form-section-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--primary-700);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .form-section-title i {
            color: var(--primary-500);
            font-size: 0.75rem;
        }
        .form-section .form-group {
            margin-bottom: 0.6rem;
        }
        .form-section .form-group:last-child {
            margin-bottom: 0;
        }
        .form-section .form-label {
            font-size: 0.78rem;
            margin-bottom: 0.25rem;
        }
        .form-section .form-input,
        .form-section .form-select,
        .form-section textarea {
            font-size: 0.82rem;
            padding: 0.4rem 0.6rem;
        }

        /* Slide-in animation for role sections */
        .create-role-section {
            animation: slideIn 0.25s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="sidebar-overlay"></div>
    
    <main class="main-wrapper">
        <div class="main-content">
            <!-- Report Header style -->
            <div class="report-header">
                <div>
                    <h1 class="report-title">จัดการผู้ใช้งาน</h1>
                    <p class="report-meta">สร้าง แก้ไข และจัดการบัญชีผู้ใช้ทั้งหมดในระบบ HICM V2025</p>
                </div>
                <button class="btn btn-plus px-4 py-2 d-flex align-items-center gap-2" onclick="openModal('createUserModal')">
                    <i class="fas fa-plus"></i>
                    <span>เพิ่มผู้ใช้ใหม่</span>
                </button>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem;"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                    <?php foreach ($errors as $error) echo $error . '<br>'; ?>
                </div>
            <?php endif; ?>
            
            <!-- Summary Statistics (Dashboard Style) -->
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo count($users); ?></div>
                            <div class="stat-label">ผู้ใช้ทั้งหมด</div>
                        </div>
                        <div class="stat-icon" style="background-color: var(--primary-50); color: var(--primary-600);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value text-danger"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></div>
                            <div class="stat-label">ผู้ดูแลระบบ</div>
                        </div>
                        <div class="stat-icon" style="background-color: #fee2e2; color: #ef4444;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value text-warning"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'auditor')); ?></div>
                            <div class="stat-label">กรรมการ</div>
                        </div>
                        <div class="stat-icon" style="background-color: #fef3c7; color: #f59e0b;">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value text-success"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'company')); ?></div>
                            <div class="stat-label">บริษัท</div>
                        </div>
                        <div class="stat-icon" style="background-color: #d1fae5; color: #10b981;">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="search-box-pro">
                        <input type="text" name="search" placeholder="ค้นหาชื่อ, ชื่อผู้ใช้ หรืออีเมล..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    <div class="filter-select-pro">
                        <select name="role" onchange="this.form.submit()">
                            <option value="">ทุกบทบาท</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>ผู้ดูแลระบบ</option>
                            <option value="auditor" <?php echo $roleFilter === 'auditor' ? 'selected' : ''; ?>>กรรมการ</option>
                            <option value="company" <?php echo $roleFilter === 'company' ? 'selected' : ''; ?>>บริษัท</option>
                            <option value="ceo" <?php echo $roleFilter === 'ceo' ? 'selected' : ''; ?>>CEO</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn-pro filter-btn-primary">
                            <i class="fas fa-search"></i>
                            <span>ค้นหา</span>
                        </button>
                        <a href="users.php" class="filter-btn-pro filter-btn-outline" title="รีเซ็ต">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="card overflow-hidden">
                <div class="table-container">
                    <table class="table-pro w-full">
                        <thead>
                            <tr>
                                <th>ข้อมูลผู้ใช้งาน</th>
                                <th>ชื่อผู้ใช้</th>
                                <th>อีเมล</th>
                                <th>บทบาท</th>
                                <th>สถานะ</th>
                                <th>เข้าสู่ระบบล่าสุด</th>
                                <th class="text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <?php
                                                $avatarText = ($user['role'] === 'company' && $user['company_name']) ? $user['company_name'] : $user['name'];
                                                $avatarExists = !empty($user['avatar']) && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $user['avatar']);
                                            ?>
                                            <div class="user-avatar-lg" style="<?php echo $avatarExists ? 'background: none; border: none; overflow: hidden;' : ''; ?>">
                                                <?php if ($avatarExists): ?>
                                                    <img src="<?php echo getBaseUrl() . '/assets/uploads/avatars/' . $user['avatar']; ?>"
                                                         alt="Profile"
                                                         style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <?php 
                                                    $avatarText = ($user['role'] === 'company' && $user['company_name']) ? $user['company_name'] : $user['name'];
                                                    echo mb_substr($avatarText, 0, 1, 'UTF-8'); 
                                                    ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <?php if ($user['role'] === 'company' && $user['company_name']): ?>
                                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($user['company_name']); ?></div>
                                                    <div class="text-xs text-gray-400 font-medium flex items-center gap-1">
                                                        <i class="fas fa-user text-gray-300"></i>
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <?php endif; ?>
                                                <?php 
                                                $displayExpertise = ($user['role'] === 'auditor') ? ($user['expertise'] ?? '') : ($user['industry_type'] ?? '');
                                                if ($displayExpertise): 
                                                ?>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php 
                                                        $exps = explode('|', $displayExpertise);
                                                        foreach ($exps as $exp):
                                                            if (trim($exp) === '') continue;
                                                        ?>
                                                            <span class="text-xs bg-primary-50 text-primary-700 px-2 py-0.5 rounded-md border border-primary-100 flex items-center gap-1">
                                                                <i class="fas fa-tag opacity-50"></i>
                                                                <?php echo htmlspecialchars(trim($exp)); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="px-2 py-1 bg-gray-100 rounded text-xs font-mono text-gray-600">
                                            @<?php echo htmlspecialchars($user['username']); ?>
                                        </span>
                                    </td>
                                    <td class="text-sm text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $roleLabels[$user['role']]['class'] ?? 'badge-secondary'; ?> rounded-pill px-3">
                                            <?php echo $roleLabels[$user['role']]['label'] ?? $user['role']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="status-pill <?php echo $user['is_active'] ? 'status-active-pill' : 'status-inactive-pill'; ?>">
                                            <div class="dot <?php echo $user['is_active'] ? 'dot-active' : 'dot-inactive'; ?>"></div>
                                            <?php echo $user['is_active'] ? 'Active' : 'Disabled'; ?>
                                        </div>
                                    </td>
                                    <td class="text-xs text-gray-500">
                                        <?php if ($user['last_login']): ?>
                                            <div class="flex flex-column">
                                                <span><?php echo date('d/m/Y', strtotime($user['last_login'])); ?></span>
                                                <span class="opacity-75"><?php echo date('H:i', strtotime($user['last_login'])); ?> น.</span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-300">- ไม่เคยเข้าใช้งาน -</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex gap-2 justify-end">
                                            <button class="btn btn-sm btn-outline-primary px-3 rounded-lg border-primary-100 hover:bg-primary-50" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="แก้ไขข้อมูล">
                                                <i class="fas fa-edit mr-1"></i> แก้ไข
                                            </button>
                                            <button class="btn btn-sm px-2 rounded-lg" onclick="adminClearNotifications(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" title="ล้างการแจ้งเตือนของผู้ใช้นี้"
                                                style="border:1px solid var(--warning-300,#fcd34d);color:var(--warning-700,#b45309);background:var(--warning-50,#fffbeb);">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-danger px-2 rounded-lg border-danger-100 hover:bg-danger-50" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" title="ลบผู้ใช้">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <div class="modal-overlay" id="createUserModal">
        <div class="modal" id="createUserModalDialog" style="max-width: 520px; transition: max-width 0.3s ease;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title"><i class="fas fa-user-plus" style="color: var(--primary-500);"></i> เพิ่มผู้ใช้ใหม่</h3>
                    <p class="text-xs text-gray-500 mb-0" id="createRoleHint">เลือกบทบาทเพื่อแสดงฟอร์มที่เหมาะสม</p>
                </div>
                <button class="modal-close-abs" onclick="closeModal('createUserModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="createUserForm">
                    <input type="hidden" name="create_user" value="1">

                    <!-- ===== Step 1: Role Selection (Always visible) ===== -->
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label" style="font-weight: 600;">บทบาท <span class="required">*</span></label>
                        <div id="createRoleCards" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem;">
                            <label class="role-card" data-role="admin">
                                <input type="radio" name="role" value="admin" style="display:none;">
                                <div class="role-card-inner">
                                    <i class="fas fa-user-shield" style="color: #EF4444;"></i>
                                    <span>ผู้ดูแลระบบ</span>
                                </div>
                            </label>
                            <label class="role-card" data-role="auditor">
                                <input type="radio" name="role" value="auditor" style="display:none;">
                                <div class="role-card-inner">
                                    <i class="fas fa-clipboard-check" style="color: #F59E0B;"></i>
                                    <span>กรรมการ</span>
                                </div>
                            </label>
                            <label class="role-card" data-role="company">
                                <input type="radio" name="role" value="company" style="display:none;">
                                <div class="role-card-inner">
                                    <i class="fas fa-building" style="color: #10B981;"></i>
                                    <span>บริษัท</span>
                                </div>
                            </label>
                            <label class="role-card" data-role="ceo">
                                <input type="radio" name="role" value="ceo" style="display:none;">
                                <div class="role-card-inner">
                                    <i class="fas fa-crown" style="color: #3B82F6;"></i>
                                    <span>CEO</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- ===== Dynamic Form Sections ===== -->
                    <div id="createFormFields" style="display: none;">

                        <!-- Section: Account Info (All roles) -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-key"></i> ข้อมูลบัญชี</div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label class="form-label">ชื่อผู้ใช้ <span class="required">*</span></label>
                                    <input type="text" name="username" id="createUsername" class="form-input" required 
                                           pattern="^[a-zA-Z0-9_.]{3,20}$" minlength="3" maxlength="20"
                                           placeholder="a-z, 0-9, _ , ."
                                           autocomplete="off"
                                           oninput="this.value=this.value.replace(/[^a-zA-Z0-9_.]/g,''); validateCreateUsername();"
                                           onblur="checkCreateUsernameAvailability()">
                                    <small id="createUsername_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">รหัสผ่าน <span class="required">*</span></label>
                                    <div style="position:relative;">
                                        <input type="password" name="password" id="createPassword" class="form-input" required placeholder="กรอกรหัสผ่าน"
                                               oninput="validateCreatePassword()">
                                        <button type="button" onclick="togglePasswordVisibility('createPassword', this)" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray-400);padding:4px;" title="แสดง/ซ่อนรหัสผ่าน">
                                            <i class="fas fa-eye" id="createPassword_icon"></i>
                                        </button>
                                    </div>
                                    <small id="createPassword_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Personal Info (All roles) -->
                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-user"></i> ข้อมูลส่วนตัว</div>
                            <div class="form-group">
                                <label class="form-label">ชื่อ-นามสกุล <span class="required">*</span></label>
                                <input type="text" name="name" id="createName" class="form-input" required placeholder="ชื่อ นามสกุล"
                                       oninput="validateCreateName()">
                                <small id="createName_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label class="form-label">อีเมล <span class="required">*</span></label>
                                    <input type="email" name="email" id="createEmail" class="form-input" required placeholder="email@example.com"
                                           oninput="validateCreateEmail()" onblur="checkCreateEmailAvailability()">
                                    <small id="createEmail_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">เบอร์โทรศัพท์</label>
                                    <input type="tel" name="phone" class="form-input" placeholder="0xxxxxxxxx"
                                           pattern="^[0-9]{9,10}$" maxlength="10"
                                           oninput="autoFormatPhoneUser(this)" onblur="validatePhoneUser(this)">
                                    <small id="createPhone_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Company Info (Company role only) -->
                        <div id="createCompanySection" class="create-role-section" style="display: none;">
                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-building"></i> ข้อมูลสถานประกอบการ</div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                    <div class="form-group">
                                        <label class="form-label">ชื่อบริษัท (ไทย) <span class="required">*</span></label>
                                        <input type="text" name="company_name" class="form-input" placeholder="บริษัท xxx จำกัด">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ชื่อบริษัท (อังกฤษ)</label>
                                        <input type="text" name="company_name_en" class="form-input" placeholder="Company Ltd.">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                    <div class="form-group">
                                        <label class="form-label">เลขทะเบียนนิติบุคคล</label>
                                        <input type="text" name="tax_id" id="createTaxId" class="form-input" placeholder="13 หลัก"
                                               pattern="^[0-9]{13}$" maxlength="13"
                                               oninput="this.value=this.value.replace(/[^0-9]/g,''); validateCreateTaxId();"
                                               onblur="validateCreateTaxId()">
                                        <small id="createTaxId_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ตำแหน่งผู้ติดต่อ</label>
                                        <input type="text" name="contact_position" class="form-input" placeholder="เช่น ผู้จัดการ">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-industry"></i> ข้อมูลธุรกิจ</div>
                                <div class="form-group">
                                    <label class="form-label">ประเภทอุตสาหกรรม <small class="text-muted">(เลือกได้หลายรายการ)</small></label>
                                    <div style="display: grid; grid-template-columns: 1fr; gap: 0.4rem; background: var(--gray-50); padding: 0.75rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); max-height: 180px; overflow-y: auto;">
                                        <?php foreach (AUDITOR_EXPERTISE as $exp): ?>
                                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-md); transition: background 0.15s;" onmouseover="this.style.background='var(--primary-50)'" onmouseout="this.style.background='transparent'">
                                                <input type="checkbox" name="expertise[]" value="<?php echo htmlspecialchars($exp); ?>" style="width: 16px; height: 16px; accent-color: var(--primary-600);">
                                                <span><?php echo htmlspecialchars($exp); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                                    <div class="form-group">
                                        <label class="form-label">ขนาดบริษัท</label>
                                        <select name="company_size" class="form-select">
                                            <option value="">-- เลือก --</option>
                                            <?php foreach ($companySizes as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">จำนวนพนักงาน</label>
                                        <input type="number" name="employee_count" class="form-input" min="0" placeholder="0">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">ปีที่ก่อตั้ง</label>
                                        <input type="number" name="established_year" class="form-input" min="1900" max="<?php echo date('Y') + 543; ?>" placeholder="25xx">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่</div>
                                <div class="form-group">
                                    <label class="form-label">ที่อยู่</label>
                                    <textarea name="address" class="form-input" rows="2" placeholder="เลขที่ ถนน ตำบล..." style="resize: vertical;"></textarea>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                                    <div class="form-group">
                                        <label class="form-label">จังหวัด</label>
                                        <input type="text" name="province" class="form-input" placeholder="เช่น นครราชสีมา">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">อำเภอ</label>
                                        <input type="text" name="district" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">รหัสไปรษณีย์</label>
                                        <input type="text" name="postal_code" id="createPostalCode" class="form-input" maxlength="5"
                                               pattern="^[0-9]{5}$" placeholder="xxxxx"
                                               oninput="this.value=this.value.replace(/[^0-9]/g,''); validateCreatePostalCode();"
                                               onblur="validateCreatePostalCode()">
                                        <small id="createPostalCode_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                    <div class="form-group">
                                        <label class="form-label">โทรศัพท์บริษัท</label>
                                        <input type="tel" name="company_phone" class="form-input" placeholder="0xxxxxxxxx"
                                               pattern="^[0-9]{9,10}$" maxlength="10"
                                               oninput="autoFormatPhoneUser(this)" onblur="validatePhoneUser(this)">
                                        <small id="createCompanyPhone_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">เว็บไซต์</label>
                                        <input type="url" name="website" class="form-input" placeholder="https://...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Auditor Info (Auditor role only) -->
                        <div id="createAuditorSection" class="create-role-section" style="display: none;">
                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-building-columns"></i> หน่วยงาน</div>
                                <div class="form-group">
                                    <label class="form-label">หน่วยงานภาคีเครือข่าย</label>
                                    <select name="organization_id" class="form-select">
                                        <option value="">-- ไม่ระบุ / อิสระ / ผู้เชี่ยวชาญ --</option>
                                        <?php foreach ($organizations as $org): ?>
                                        <option value="<?php echo $org['id']; ?>"><?php echo htmlspecialchars($org['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-industry"></i> ความเชี่ยวชาญอุตสาหกรรม</div>
                                <div class="form-group">
                                    <div style="display: grid; grid-template-columns: 1fr; gap: 0.4rem; background: var(--gray-50); padding: 0.75rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); max-height: 180px; overflow-y: auto;">
                                        <?php foreach (AUDITOR_EXPERTISE as $exp): ?>
                                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; padding: 0.25rem 0.5rem; border-radius: var(--radius-md); transition: background 0.15s;" onmouseover="this.style.background='var(--primary-50)'" onmouseout="this.style.background='transparent'">
                                                <input type="checkbox" name="expertise[]" value="<?php echo htmlspecialchars($exp); ?>" style="width: 16px; height: 16px; accent-color: var(--primary-600);">
                                                <span><?php echo htmlspecialchars($exp); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title"><i class="fas fa-award"></i> ความเชี่ยวชาญ HICM Pillars</div>
                                <div class="form-group">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: var(--gray-50); padding: 0.75rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200);">
                                        <?php foreach (PILLARS as $code => $pillar): ?>
                                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; background: white; border-radius: var(--radius-md); border: 1px solid <?php echo $pillar['color']; ?>30; transition: all 0.15s;">
                                            <input type="checkbox" name="hicm_expertise[]" value="<?php echo $code; ?>" style="width: 18px; height: 18px; accent-color: <?php echo $pillar['color']; ?>;">
                                            <span style="display: flex; align-items: center; gap: 0.375rem;">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: <?php echo $pillar['color']; ?>; color: white; border-radius: 4px; font-size: 0.7rem; font-weight: 700;"><?php echo $code; ?></span>
                                                <span style="font-size: 0.8rem;"><?php echo $pillar['name_th']; ?></span>
                                            </span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>
            <div class="modal-footer" id="createFormFooter" style="display: none;">
                <button type="button" class="btn btn-outline" onclick="closeModal('createUserModal')">ยกเลิก</button>
                <button type="submit" form="createUserForm" class="btn btn-primary" id="createSubmitBtn">
                    <i class="fas fa-plus-circle"></i> สร้างผู้ใช้
                </button>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="editUserModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">แก้ไขผู้ใช้</h3>
                    <p class="text-xs text-gray-500 mb-0">ปรับปรุงข้อมูลและสิทธิ์การเข้าถึงของผู้ใช้งาน</p>
                </div>
                <button class="modal-close-abs" onclick="closeModal('editUserModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editUserForm">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="form-group" id="editCompanyNameGroup" style="display: none;">
                        <label class="form-label">ชื่อบริษัท <span class="required">*</span></label>
                        <input type="text" name="company_name" id="editUserCompanyName" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" name="username" id="editUserUsername" class="form-input" required
                               pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" id="editUserName" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">อีเมล</label>
                        <input type="email" name="email" id="editUserEmail" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">รหัสผ่านใหม่ (เว้นว่างหากไม่ต้องการเปลี่ยน)</label>
                        <input type="password" name="password" class="form-input" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">บทบาท</label>
                        <select name="role" id="editUserRole" class="form-select" required>
                            <option value="admin">ผู้ดูแลระบบ</option>
                            <option value="auditor">กรรมการ</option>
                            <option value="company">บริษัท</option>
                            <option value="ceo">CEO</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์</label>
                        <input type="tel" name="phone" id="editUserPhone" class="form-input" placeholder="0xxxxxxxxx"
                               pattern="^[0-9]{9,10}$" maxlength="10"
                               oninput="autoFormatPhoneUser(this)" onblur="validatePhoneUser(this)">
                        <small id="editPhone_status" style="display:block;margin-top:0.25rem;font-size:0.78rem;"></small>
                    </div>

                    <div class="form-group" id="editOrganizationGroup" style="display: none;">
                        <label class="form-label" style="font-weight: 600; color: var(--primary-700);">หน่วยงานภาคีเครือข่าย</label>
                        <select name="organization_id" id="editUserOrganization" class="form-select">
                            <option value="">-- ไม่ระบุ / อิสระ / ผู้เชี่ยวชาญ --</option>
                            <?php foreach ($organizations as $org): ?>
                            <option value="<?php echo $org['id']; ?>"><?php echo htmlspecialchars($org['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="editExpertiseGroup" style="display: none;">
                        <label class="form-label" style="font-weight: 600; color: var(--primary-700);">ความเชี่ยวชาญ/สายงาน (เลือกได้หลายรายการ)</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: var(--gray-50); padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200); max-height: 200px; overflow-y: auto;">
                            <?php foreach (AUDITOR_EXPERTISE as $exp): ?>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem;">
                                    <input type="checkbox" name="expertise[]" value="<?php echo htmlspecialchars($exp); ?>" class="edit-expertise-check" style="width: 16px; height: 16px;">
                                    <span><?php echo htmlspecialchars($exp); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group" id="editHicmExpertiseGroup" style="display: none;">
                        <label class="form-label" style="font-weight: 600; color: var(--primary-700);">ความเชี่ยวชาญ HICM Pillars</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: var(--gray-50); padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-200);">
                            <?php foreach (PILLARS as $code => $pillar): ?>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; background: white; border-radius: var(--radius-md); border: 1px solid <?php echo $pillar['color']; ?>30;">
                                <input type="checkbox" name="hicm_expertise[]" value="<?php echo $code; ?>" class="edit-hicm-check" style="width: 18px; height: 18px; accent-color: <?php echo $pillar['color']; ?>;">
                                <span style="display: flex; align-items: center; gap: 0.375rem;">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: <?php echo $pillar['color']; ?>; color: white; border-radius: 4px; font-size: 0.7rem; font-weight: 700;"><?php echo $code; ?></span>
                                    <span style="font-size: 0.8rem;"><?php echo $pillar['name_th']; ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="is_active" id="editUserActive" value="1" checked>
                            <span>บัญชีใช้งานได้</span>
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">ยกเลิก</button>
                <button type="submit" form="editUserForm" class="btn btn-primary">บันทึกการเปลี่ยนแปลง</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title" style="color: var(--danger);">ยืนยันการลบ</h3>
            </div>
            <div class="modal-body">
                <p>คุณแน่ใจหรือไม่ที่จะลบผู้ใช้ <strong id="deleteUserName"></strong>?</p>
                <p style="font-size: 0.875rem; color: var(--gray-500);">การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="delete_user" value="1">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ลบผู้ใช้</button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="<?php echo getBaseUrl(); ?>/assets/js/main.js"></script>
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            // Reset create form when closing
            if (modalId === 'createUserModal') {
                resetCreateForm();
            }
        }

        // ========================================
        // Create User — Role-based Dynamic Form
        // ========================================
        const roleHints = {
            admin: 'ผู้ดูแลระบบมีสิทธิ์จัดการทุกส่วนในระบบ',
            auditor: 'กรรมการสามารถประเมินสถานประกอบการที่ได้รับมอบหมาย',
            company: 'สถานประกอบการสามารถดูผลประเมินและจัดการข้อมูลบริษัท',
            ceo: 'CEO สามารถดูรายงานและภาพรวมของทั้งระบบ'
        };

        function handleCreateRoleChange(role) {
            const formFields = document.getElementById('createFormFields');
            const formFooter = document.getElementById('createFormFooter');
            const hintEl = document.getElementById('createRoleHint');
            const dialog = document.getElementById('createUserModalDialog');

            // Show form fields and footer
            formFields.style.display = 'block';
            formFooter.style.display = 'flex';
            hintEl.textContent = roleHints[role] || '';

            // Hide all role-specific sections
            document.querySelectorAll('.create-role-section').forEach(s => s.style.display = 'none');

            // Show relevant section & adjust modal width
            if (role === 'company') {
                document.getElementById('createCompanySection').style.display = 'block';
                dialog.style.maxWidth = '680px';
            } else if (role === 'auditor') {
                document.getElementById('createAuditorSection').style.display = 'block';
                dialog.style.maxWidth = '580px';
            } else {
                dialog.style.maxWidth = '520px';
            }
        }

        function resetCreateForm() {
            const form = document.getElementById('createUserForm');
            if (form) form.reset();
            document.getElementById('createFormFields').style.display = 'none';
            document.getElementById('createFormFooter').style.display = 'none';
            document.getElementById('createRoleHint').textContent = 'เลือกบทบาทเพื่อแสดงฟอร์มที่เหมาะสม';
            document.getElementById('createUserModalDialog').style.maxWidth = '520px';
            document.querySelectorAll('.create-role-section').forEach(s => s.style.display = 'none');
        }

        // Role card click handlers
        document.querySelectorAll('#createRoleCards .role-card input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                handleCreateRoleChange(this.value);
            });
        });

        // ========================================
        // Edit User
        // ========================================
        function editUser(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserUsername').value = user.username;
            document.getElementById('editUserName').value = user.name;
            document.getElementById('editUserEmail').value = user.email;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserPhone').value = user.phone || '';
            document.getElementById('editUserCompanyName').value = user.company_name || '';
            document.getElementById('editUserOrganization').value = user.organization_id || '';
            
            // Handle multi-expertise checkboxes
            const expertiseData = (user.role === 'company') ? (user.industry_type || '') : (user.expertise || '');
            const userExpertise = expertiseData.split('|');
            
            document.querySelectorAll('.edit-expertise-check').forEach(check => {
                check.checked = userExpertise.includes(check.value);
            });

            document.getElementById('editUserActive').checked = user.is_active == 1;
            
            // Handle HICM expertise checkboxes
            const userHicmExpertise = (user.hicm_expertise || '').split('|');
            document.querySelectorAll('.edit-hicm-check').forEach(check => {
                check.checked = userHicmExpertise.includes(check.value);
            });
            
            toggleEditFields(user.role);
            openModal('editUserModal');
        }

        function toggleEditFields(role) {
            if (typeof role !== 'string') role = role.value;
            
            // Company name
            document.getElementById('editCompanyNameGroup').style.display = (role === 'company') ? 'block' : 'none';
            // Expertise (auditor + company)
            document.getElementById('editExpertiseGroup').style.display = (role === 'auditor' || role === 'company') ? 'block' : 'none';
            // Organization (auditor only)
            document.getElementById('editOrganizationGroup').style.display = (role === 'auditor') ? 'block' : 'none';
            // HICM expertise (auditor only)
            document.getElementById('editHicmExpertiseGroup').style.display = (role === 'auditor') ? 'block' : 'none';
        }
        
        function deleteUser(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            openModal('deleteModal');
        }

        function adminClearNotifications(userId, userName) {
            if (!confirm(`ล้างการแจ้งเตือนทั้งหมดของ "${userName}"?\n\nการแจ้งเตือนทั้งหมดของผู้ใช้นี้จะถูกลบออกถาวร`)) return;
            fetch(window.APP_CONFIG.apiUrl + '/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'admin_clear_user', user_id: userId })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const deleted = d.deleted ?? 0;
                    alert(`ล้างการแจ้งเตือนของ "${userName}" เรียบร้อย\n(ลบ ${deleted} รายการ)`);
                } else {
                    alert('เกิดข้อผิดพลาด: ' + (d.error || 'ไม่ทราบสาเหตุ'));
                }
            })
            .catch(() => alert('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
        }

        // Edit role change listener
        document.getElementById('editUserRole').addEventListener('change', function() {
            toggleEditFields(this.value);
        });

        // Live search with debounce
        const searchInput = document.querySelector('input[name="search"]');
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
        
        // Restore focus and cursor position after reload
        window.addEventListener('load', function() {
            if (searchInput.value) {
                searchInput.focus();
                const len = searchInput.value.length;
                searchInput.setSelectionRange(len, len);
            }
        });
        // ========================================
        // Create Form — Field Validation
        // ========================================

        // --- Username ---
        let usernameCheckTimer = null;
        function validateCreateUsername() {
            const input = document.getElementById('createUsername');
            const status = document.getElementById('createUsername_status');
            const val = input.value.trim();
            if (val === '') { setStatus(status, input, '', ''); return; }
            if (val.length < 3) {
                setStatus(status, input, '💡 ต้องมีอย่างน้อย 3 ตัวอักษร (' + val.length + '/3)', '#D97706');
            } else if (!/^[a-zA-Z0-9_.]+$/.test(val)) {
                setStatus(status, input, '❌ ใช้ได้เฉพาะ a-z, 0-9, _ , .', '#DC2626');
            } else {
                setStatus(status, input, '⏳ กำลังตรวจสอบ...', '#6B7280');
                clearTimeout(usernameCheckTimer);
                usernameCheckTimer = setTimeout(() => checkCreateUsernameAvailability(), 400);
            }
        }

        function checkCreateUsernameAvailability() {
            const input = document.getElementById('createUsername');
            const status = document.getElementById('createUsername_status');
            const val = input.value.trim();
            if (val.length < 3) return;
            if (!/^[a-zA-Z0-9_.]{3,20}$/.test(val)) return;

            const formData = new FormData();
            formData.append('check_username', '1');
            formData.append('username', val);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.available) {
                        setStatus(status, input, '✅ ' + data.message, '#059669');
                    } else {
                        setStatus(status, input, '❌ ' + data.message, '#DC2626');
                    }
                })
                .catch(() => setStatus(status, input, '', ''));
        }

        // --- Password (advisory strength bar — does NOT block submit) ---
        function validateCreatePassword() {
            const input = document.getElementById('createPassword');
            const statusEl = document.getElementById('createPassword_status');
            const val = input.value;

            if (val === '') {
                statusEl.innerHTML = '';
                input.style.borderColor = '';
                return;
            }

            // Calculate strength score
            let score = 0;
            let tips = [];
            if (val.length >= 6) score++; else tips.push('≥ 6 ตัวอักษร');
            if (val.length >= 8) score++;
            if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++; else tips.push('ตัวพิมพ์เล็ก+ใหญ่');
            if (/[0-9]/.test(val)) score++; else tips.push('ตัวเลข');
            if (/[^a-zA-Z0-9]/.test(val)) score++; else tips.push('อักขระพิเศษ');

            const levels = [
                { label: 'อ่อนมาก',    barColor: '#DC2626', textColor: '#DC2626' },
                { label: 'อ่อน',       barColor: '#EF4444', textColor: '#EF4444' },
                { label: 'พอใช้',      barColor: '#F59E0B', textColor: '#D97706' },
                { label: 'ดี',         barColor: '#10B981', textColor: '#059669' },
                { label: 'แข็งแรงมาก', barColor: '#047857', textColor: '#047857' },
            ];
            const lvl = levels[Math.min(score, levels.length - 1)];
            const pct = Math.min((score / 5) * 100, 100);

            // Build visual strength bar
            let html = `<div style="display:flex;align-items:center;gap:0.5rem;margin-top:2px;">`;
            html += `<div style="flex:1;height:6px;background:var(--gray-200);border-radius:3px;overflow:hidden;">`;
            html += `<div style="width:${pct}%;height:100%;background:${lvl.barColor};border-radius:3px;transition:width .3s ease,background .3s ease;"></div>`;
            html += `</div>`;
            html += `<span style="font-size:0.72rem;font-weight:600;color:${lvl.textColor};white-space:nowrap;">${lvl.label}</span>`;
            html += `</div>`;

            // Tips (advisory)
            if (tips.length > 0 && score < 4) {
                html += `<div style="font-size:0.72rem;color:var(--gray-500);margin-top:2px;">💡 แนะนำ: ${tips.slice(0, 2).join(', ')}</div>`;
            }

            statusEl.innerHTML = html;
            input.style.borderColor = ''; // never mark border red for password strength
        }

        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // --- Name ---
        function validateCreateName() {
            const input = document.getElementById('createName');
            const status = document.getElementById('createName_status');
            const val = input.value.trim();
            if (val === '') { setStatus(status, input, '', ''); return; }
            if (val.length < 2) {
                setStatus(status, input, '💡 ชื่อ-นามสกุล สั้นเกินไป', '#D97706');
            } else if (!/\s/.test(val)) {
                setStatus(status, input, '💡 ควรมีชื่อและนามสกุล (เว้นวรรค)', '#D97706');
            } else {
                setStatus(status, input, '✅ ถูกต้อง', '#059669');
            }
        }

        // --- Email ---
        let emailCheckTimer = null;
        function validateCreateEmail() {
            const input = document.getElementById('createEmail');
            const status = document.getElementById('createEmail_status');
            const val = input.value.trim();
            if (val === '') { setStatus(status, input, '', ''); return; }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            if (!emailRegex.test(val)) {
                setStatus(status, input, '❌ รูปแบบอีเมลไม่ถูกต้อง', '#DC2626');
            } else {
                setStatus(status, input, '⏳ กำลังตรวจสอบ...', '#6B7280');
                clearTimeout(emailCheckTimer);
                emailCheckTimer = setTimeout(() => checkCreateEmailAvailability(), 400);
            }
        }

        function checkCreateEmailAvailability() {
            const input = document.getElementById('createEmail');
            const status = document.getElementById('createEmail_status');
            const val = input.value.trim();
            if (!val || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val)) return;

            const formData = new FormData();
            formData.append('check_email', '1');
            formData.append('email', val);

            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.available) {
                        setStatus(status, input, '✅ ' + data.message, '#059669');
                    } else {
                        setStatus(status, input, '❌ ' + data.message, '#DC2626');
                    }
                })
                .catch(() => setStatus(status, input, '', ''));
        }

        // --- Tax ID (13 digits) ---
        function validateCreateTaxId() {
            const input = document.getElementById('createTaxId');
            const status = document.getElementById('createTaxId_status');
            const val = input.value.trim();
            if (val === '') { setStatus(status, input, '', ''); return; }
            if (!/^[0-9]+$/.test(val)) {
                setStatus(status, input, '❌ ต้องเป็นตัวเลขเท่านั้น', '#DC2626');
            } else if (val.length < 13) {
                setStatus(status, input, '💡 เลขทะเบียนต้องมี 13 หลัก (' + val.length + '/13)', '#D97706');
            } else if (val.length === 13) {
                setStatus(status, input, '✅ เลขทะเบียนนิติบุคคลถูกต้อง', '#059669');
            }
        }

        // --- Postal Code (5 digits) ---
        function validateCreatePostalCode() {
            const input = document.getElementById('createPostalCode');
            const status = document.getElementById('createPostalCode_status');
            const val = input.value.trim();
            if (val === '') { setStatus(status, input, '', ''); return; }
            if (!/^[0-9]+$/.test(val)) {
                setStatus(status, input, '❌ ต้องเป็นตัวเลขเท่านั้น', '#DC2626');
            } else if (val.length < 5) {
                setStatus(status, input, '💡 รหัสไปรษณีย์ต้องมี 5 หลัก (' + val.length + '/5)', '#D97706');
            } else if (val.length === 5) {
                if (/^[1-9]/.test(val)) {
                    setStatus(status, input, '✅ รหัสไปรษณีย์ถูกต้อง', '#059669');
                } else {
                    setStatus(status, input, '❌ รหัสไปรษณีย์ไม่ถูกต้อง', '#DC2626');
                }
            }
        }

        // --- Helper: Set status text & border color ---
        function setStatus(statusEl, inputEl, msg, color) {
            statusEl.textContent = msg;
            statusEl.style.color = color;
            inputEl.style.borderColor = color || '';
        }

        // --- Form Submit Validation ---
        document.getElementById('createUserForm').addEventListener('submit', function(e) {
            const errors = [];
            const role = document.querySelector('#createRoleCards input[type=radio]:checked');

            // Required field checks
            const username = document.getElementById('createUsername');
            const password = document.getElementById('createPassword');
            const name = document.getElementById('createName');
            const email = document.getElementById('createEmail');

            if (!role) { errors.push('กรุณาเลือกบทบาท'); }
            if (username && username.value.trim().length < 3) { errors.push('ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร'); }
            if (username && !/^[a-zA-Z0-9_.]{3,20}$/.test(username.value.trim())) { errors.push('ชื่อผู้ใช้ใช้ได้เฉพาะ a-z, 0-9, _ , .'); }
            if (password && password.value.length < 1) { errors.push('กรุณากรอกรหัสผ่าน'); }
            if (name && name.value.trim().length < 2) { errors.push('กรุณากรอกชื่อ-นามสกุล'); }
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) { errors.push('รูปแบบอีเมลไม่ถูกต้อง'); }

            // Check if username status shows error
            const usernameStatus = document.getElementById('createUsername_status');
            if (usernameStatus && usernameStatus.textContent.includes('❌')) {
                errors.push('ชื่อผู้ใช้ไม่สามารถใช้ได้');
            }
            // Check email status
            const emailStatus = document.getElementById('createEmail_status');
            if (emailStatus && emailStatus.textContent.includes('❌')) {
                errors.push('อีเมลไม่สามารถใช้ได้');
            }

            // Company-specific checks
            if (role && role.value === 'company') {
                const taxId = document.getElementById('createTaxId');
                if (taxId && taxId.value.trim() !== '' && taxId.value.trim().length !== 13) {
                    errors.push('เลขทะเบียนนิติบุคคลต้องมี 13 หลัก');
                }
                const postal = document.getElementById('createPostalCode');
                if (postal && postal.value.trim() !== '' && postal.value.trim().length !== 5) {
                    errors.push('รหัสไปรษณีย์ต้องมี 5 หลัก');
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('กรุณาแก้ไขข้อมูลต่อไปนี้:\n\n• ' + errors.join('\n• '));
                return false;
            }
        });

        // ========================================
        // Phone Validation
        // ========================================
        function autoFormatPhoneUser(input) {
            // Strip non-digits
            let val = input.value.replace(/[^0-9]/g, '');
            // Limit length
            if (val.length > 10) val = val.slice(0, 10);
            input.value = val;
            validatePhoneUser(input);
        }

        function validatePhoneUser(input) {
            // Find the status element (sibling <small>)
            const status = input.parentElement.querySelector('small');
            if (!status) return;

            const val = input.value.trim();

            // Empty = reset
            if (val === '') {
                status.textContent = '';
                input.style.borderColor = '';
                return;
            }

            const len = val.length;
            let msg = '';
            let color = '';

            // Detect type & validate
            if (/^0[689]/.test(val)) {
                // Mobile: 06x, 08x, 09x => 10 digits
                if (len === 10) {
                    msg = '✅ เบอร์มือถือถูกต้อง';
                    color = '#059669';
                } else if (len < 10) {
                    msg = '💡 เบอร์มือถือต้องมี 10 หลัก (' + len + '/10)';
                    color = '#D97706';
                } else {
                    msg = '❌ เบอร์มือถือเกิน 10 หลัก';
                    color = '#DC2626';
                }
            } else if (/^02/.test(val)) {
                // Bangkok landline: 02x => 9 digits
                if (len === 9) {
                    msg = '✅ เบอร์กรุงเทพฯ ถูกต้อง';
                    color = '#059669';
                } else if (len < 9) {
                    msg = '💡 เบอร์กรุงเทพฯ ต้องมี 9 หลัก (' + len + '/9)';
                    color = '#D97706';
                } else {
                    msg = '❌ เบอร์กรุงเทพฯ เกิน 9 หลัก';
                    color = '#DC2626';
                }
            } else if (/^0[3-5,7]/.test(val)) {
                // Provincial landline: 0xx => 9 digits
                if (len === 9) {
                    msg = '✅ เบอร์ต่างจังหวัดถูกต้อง';
                    color = '#059669';
                } else if (len < 9) {
                    msg = '💡 เบอร์โทรศัพท์ต้องมี 9 หลัก (' + len + '/9)';
                    color = '#D97706';
                } else {
                    msg = '❌ เบอร์โทรศัพท์เกิน 9 หลัก';
                    color = '#DC2626';
                }
            } else if (/^0/.test(val)) {
                // Starts with 0 but unknown prefix
                if (len >= 9 && len <= 10) {
                    msg = '✅ รูปแบบเบอร์ถูกต้อง';
                    color = '#059669';
                } else if (len < 9) {
                    msg = '💡 เบอร์โทรศัพท์ต้องมี 9-10 หลัก (' + len + ' หลัก)';
                    color = '#D97706';
                }
            } else {
                msg = '❌ เบอร์โทรศัพท์ต้องเริ่มต้นด้วย 0';
                color = '#DC2626';
            }

            status.textContent = msg;
            status.style.color = color;
            input.style.borderColor = color;
        }

        // Validate phone on form submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const phoneInputs = form.querySelectorAll('input[type="tel"]');
                for (const input of phoneInputs) {
                    const val = input.value.trim();
                    if (val === '') continue; // optional field
                    if (!/^0[0-9]{8,9}$/.test(val)) {
                        e.preventDefault();
                        input.focus();
                        validatePhoneUser(input);
                        alert('กรุณากรอกเบอร์โทรศัพท์ให้ถูกต้อง (ตัวเลข 9-10 หลัก เริ่มต้นด้วย 0)');
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>