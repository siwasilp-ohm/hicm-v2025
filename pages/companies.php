<?php
/**
 * HICM V2025 Assessment System - Companies Management Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/companies.php';

requireAuth();

$user = getCurrentUser();
$isAdmin = hasRole(ROLE_ADMIN);
$isAuditor = hasRole(ROLE_AUDITOR);
$isCEO = hasRole('ceo');

// Only Admin, Auditor, and CEO can access
if (!$isAdmin && !$isAuditor && !$isCEO) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

// AJAX: Check Username Availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_username'])) {
    header('Content-Type: application/json');
    $username = sanitizeInput($_POST['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['available' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้']);
        exit;
    }
    
    // Check username pattern
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
        echo json_encode(['available' => false, 'message' => 'ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษ 3-30 ตัว (a-z, 0-9, ._-)']);
        exit;
    }
    
    // Check if username exists
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo json_encode(['available' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว']);
    } else {
        echo json_encode(['available' => true, 'message' => 'ชื่อผู้ใช้นี้พร้อมใช้งาน']);
    }
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_company'])) {
        $data = [
            'username' => sanitizeInput($_POST['username']),
            'password' => $_POST['password'],
            'company_name' => sanitizeInput($_POST['company_name']),
            'company_name_en' => sanitizeInput($_POST['company_name_en'] ?? ''),
            'tax_id' => sanitizeInput($_POST['tax_id'] ?? ''),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'province' => sanitizeInput($_POST['province'] ?? ''),
            'district' => sanitizeInput($_POST['district'] ?? ''),
            'postal_code' => sanitizeInput($_POST['postal_code'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'fax' => sanitizeInput($_POST['fax'] ?? ''),
            'website' => sanitizeInput($_POST['website'] ?? ''),
            'industry_type' => $_POST['industry_type'] ?? [],
            'company_size' => sanitizeInput($_POST['company_size'] ?? ''),
            'employee_count' => intval($_POST['employee_count'] ?? 0),
            'established_year' => intval($_POST['established_year'] ?? 0),
            'contact_name' => sanitizeInput($_POST['contact_name']),
            'contact_position' => sanitizeInput($_POST['contact_position'] ?? ''),
            'contact_email' => sanitizeInput($_POST['contact_email']),
            'contact_phone' => sanitizeInput($_POST['contact_phone'] ?? ''),
            'description' => sanitizeInput($_POST['description'] ?? '')
        ];

        $result = createCompany($data);
        if ($result['success']) {
            $msg = 'สร้างสถานประกอบการเรียบร้อยแล้ว';
            if (!empty($result['auto_matched'])) {
                $info = $result['match_info'];
                $msg .= " ✨ Auto Smart Match จับคู่ {$info['auditors_count']} กรรมการ ในรอบ {$info['period']} (Coverage: {$info['coverage']}%)";
            }
            setFlashMessage($msg, 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        redirect(getBaseUrl() . '/pages/companies.php');
    }

    if (isset($_POST['update_company'])) {
        $companyId = intval($_POST['company_id']);
        $data = [
            'company_name' => sanitizeInput($_POST['company_name']),
            'company_name_en' => sanitizeInput($_POST['company_name_en'] ?? ''),
            'tax_id' => sanitizeInput($_POST['tax_id'] ?? ''),
            'address' => sanitizeInput($_POST['address'] ?? ''),
            'province' => sanitizeInput($_POST['province'] ?? ''),
            'district' => sanitizeInput($_POST['district'] ?? ''),
            'postal_code' => sanitizeInput($_POST['postal_code'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'fax' => sanitizeInput($_POST['fax'] ?? ''),
            'website' => sanitizeInput($_POST['website'] ?? ''),
            'industry_type' => $_POST['industry_type'] ?? [],
            'company_size' => sanitizeInput($_POST['company_size'] ?? ''),
            'employee_count' => intval($_POST['employee_count'] ?? 0),
            'established_year' => intval($_POST['established_year'] ?? 0),
            'contact_name' => sanitizeInput($_POST['contact_name']),
            'contact_position' => sanitizeInput($_POST['contact_position'] ?? ''),
            'contact_email' => sanitizeInput($_POST['contact_email']),
            'contact_phone' => sanitizeInput($_POST['contact_phone'] ?? ''),
            'description' => sanitizeInput($_POST['description'] ?? '')
        ];

        $result = updateCompany($companyId, $data);
        if ($result['success']) {
            setFlashMessage('อัปเดตสถานประกอบการเรียบร้อยแล้ว', 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        redirect(getBaseUrl() . '/pages/companies.php');
    }

    if (isset($_POST['delete_company'])) {
        $companyId = intval($_POST['company_id']);

        $result = deleteCompany($companyId);
        if ($result['success']) {
            setFlashMessage('ลบสถานประกอบการเรียบร้อยแล้ว', 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        redirect(getBaseUrl() . '/pages/companies.php');
    }

    if (isset($_POST['restore_company'])) {
        $companyId = intval($_POST['company_id']);

        $result = restoreCompany($companyId);
        if ($result['success']) {
            setFlashMessage('เปิดใช้งานสถานประกอบการเรียบร้อยแล้ว', 'success');
        } else {
            setFlashMessage($result['message'], 'error');
        }
        redirect(getBaseUrl() . '/pages/companies.php');
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$industryFilter = $_GET['industry'] ?? '';
$sizeFilter = $_GET['size'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Load all companies (JS handles client-side filtering)
$filters = ['status' => 'all'];
if ($industryFilter) $filters['industry'] = $industryFilter;
if ($sizeFilter) $filters['size'] = $sizeFilter;
if ($searchFilter) $filters['search'] = $searchFilter;

$companies = getAllCompanies($filters);
$industryTypes = getIndustryTypes();
$companySizes = getCompanySizes();

// Get statistics - Calculate size by employee_count: <200=small, 200-500=medium, >500=large
$db = getDB();
$stmtStats = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN c.is_active = 1 THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN c.is_active = 0 THEN 1 ELSE 0 END) as inactive,
    SUM(CASE WHEN c.is_active = 1 AND c.employee_count < 200 THEN 1 ELSE 0 END) as small_count,
    SUM(CASE WHEN c.is_active = 1 AND c.employee_count >= 200 AND c.employee_count <= 500 THEN 1 ELSE 0 END) as medium_count,
    SUM(CASE WHEN c.is_active = 1 AND c.employee_count > 500 THEN 1 ELSE 0 END) as large_count
    FROM companies c
    JOIN users u ON c.user_id = u.id");
$stmtStats->execute();
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Helper function to determine company size by employee count
function getCompanySizeByEmployee($employeeCount) {
    if ($employeeCount > 500) return ['class' => 'large', 'label' => 'ใหญ่'];
    if ($employeeCount >= 200) return ['class' => 'medium', 'label' => 'กลาง'];
    return ['class' => 'small', 'label' => 'เล็ก'];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการสถานประกอบการ - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== Professional Page Styles ===== */
        
        /* Hero Header */
        .page-hero {
            background: linear-gradient(135deg, #1e3a5f 0%, #0c4a6e 50%, #0369a1 100%);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 50%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .page-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-hero-info h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-hero-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .btn-hero {
            padding: 0.875rem 1.75rem;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-hero:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon svg {
            width: 28px;
            height: 28px;
        }
        .stat-content {
            flex: 1;
            min-width: 0;
        }
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }
        
        /* Filter Card - Clean Design */
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            margin-bottom: 1.5rem;
        }
        
        /* Filter Form Row */
        .filter-form .filter-row {
            display: flex;
            align-items: flex-end;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            min-width: 140px;
        }
        
        .filter-group.filter-search {
            flex: 2;
            min-width: 200px;
        }
        
        .filter-group.filter-actions {
            flex: 0 0 auto;
            min-width: auto;
        }
        
        .filter-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Search Input */
        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .search-input-wrapper .search-icon {
            position: absolute;
            left: 0.875rem;
            width: 18px;
            height: 18px;
            color: var(--gray-400);
            pointer-events: none;
        }
        
        .filter-input {
            width: 100%;
            height: 44px;
            padding: 0 1rem 0 2.5rem;
            font-size: 0.9rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            background: var(--gray-50);
            transition: all 0.2s;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .filter-input::placeholder {
            color: var(--gray-400);
        }
        
        /* Filter Select */
        .filter-select {
            height: 44px;
            padding: 0 2rem 0 1rem;
            font-size: 0.9rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            background: var(--gray-50) url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e") no-repeat right 0.75rem center;
            background-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            -webkit-appearance: none;
            appearance: none;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .filter-select:hover {
            border-color: var(--gray-300);
        }
        
        /* Filter Buttons */
        .filter-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-btn {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .filter-btn svg {
            width: 20px;
            height: 20px;
        }
        
        .filter-btn.btn-reset {
            background: var(--gray-100);
            color: var(--gray-600);
            border: 2px solid var(--gray-200);
        }
        
        .filter-btn.btn-reset:hover {
            background: var(--gray-200);
            border-color: var(--gray-300);
            transform: translateY(-2px);
        }
        
        .filter-btn.btn-map {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary);
            border: 2px solid rgba(59, 130, 246, 0.2);
        }
        
        .filter-btn.btn-map:hover {
            background: rgba(59, 130, 246, 0.2);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        /* Filter Footer */
        .filter-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-100);
        }
        
        .results-text {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        .results-text strong {
            color: var(--primary);
            font-size: 1rem;
        }
        
        .clear-filters-link {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8rem;
            color: var(--danger);
            text-decoration: none;
            padding: 0.35rem 0.75rem;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .clear-filters-link:hover {
            background: #FEE2E2;
        }
        
        /* Size count text */
        .size-count {
            font-size: 0.7rem;
            color: var(--gray-500);
            margin-top: 2px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-form .filter-row {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
        }
        
        /* Legacy filter-header support */
        .filter-header {
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        .quick-action-btn:hover {
            background: rgba(59, 130, 246, 0.2);
            transform: translateY(-2px);
        }
        .quick-action-btn svg {
            width: 18px;
            height: 18px;
        }
        .results-count {
            font-size: 0.9rem;
            color: var(--gray-500);
        }
        .count-number {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        /* Legacy filter-header support */
        .filter-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-100);
        }
        .filter-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        .filter-header svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }
        .filter-form .form-row {
            display: flex;
            align-items: flex-end;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .filter-form .form-group {
            margin-bottom: 0 !important;
            flex: 1 1 150px;
            min-width: 0;
        }
        .filter-form .form-group.col-search {
            flex: 2 1 250px;
        }
        .filter-form .form-group.col-actions {
            flex: 0 0 auto;
            display: flex;
            gap: 0.5rem;
        }
        .filter-form label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-form .form-control {
            height: 44px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            background: var(--gray-50);
            transition: all 0.2s;
        }
        .filter-form .form-control:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        .filter-form .btn {
            height: 44px;
            min-width: 44px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .filter-form .btn:hover {
            transform: translateY(-2px);
        }
        .filter-form .btn-outline-secondary {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
        }
        .filter-form .btn-outline-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }
        .filter-form .btn-outline-primary {
            background: white;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        .filter-form .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid var(--gray-100);
            overflow: hidden;
        }
        .table-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-50);
        }
        .table-card-header h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-count {
            font-size: 0.85rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
        }
        .table-container {
            overflow-x: auto;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: var(--gray-50);
            border-bottom: 2px solid var(--gray-200);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-600);
            padding: 1rem;
            white-space: nowrap;
        }
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-100);
        }
        .table tbody tr:hover {
            background: var(--gray-50);
        }
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Company Name Cell */
        .company-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .company-avatar {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: linear-gradient(135deg, #3B82F6, #1D4ED8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .company-info h5 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0 0 0.25rem 0;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .company-info small {
            font-size: 0.75rem;
            color: var(--gray-500);
        }
        
        /* Badge Styles */
        .size-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .size-badge.small { background: #D1FAE5; color: #059669; }
        .size-badge.medium { background: #FEF3C7; color: #D97706; }
        .size-badge.large { background: #FEE2E2; color: #DC2626; }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-badge.active { background: #D1FAE5; color: #059669; }
        .status-badge.inactive { background: #FEE2E2; color: #DC2626; }
        
        .industry-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 220px;
        }
        .industry-tag {
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            color: #4338ca;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid #c7d2fe;
            cursor: default;
            white-space: nowrap;
        }
        
        /* ====================================== */
        /* Premium Multi-Select Tag Component     */
        /* ====================================== */
        .ms-container {
            position: relative;
            font-family: inherit;
        }
        
        /* Trigger / Display area */
        .ms-trigger {
            min-height: 46px;
            padding: 6px 40px 6px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(.4,0,.2,1);
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-items: center;
            position: relative;
        }
        .ms-trigger::after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            transition: transform 0.25s ease;
            pointer-events: none;
        }
        .ms-trigger:hover { border-color: #94a3b8; }
        .ms-container.open .ms-trigger {
            border-color: var(--primary-500, #3b82f6);
            box-shadow: 0 0 0 4px rgba(59,130,246,0.08);
        }
        .ms-container.open .ms-trigger::after {
            transform: translateY(-50%) rotate(180deg);
        }
        
        /* Placeholder */
        .ms-placeholder {
            color: #94a3b8;
            font-size: 0.875rem;
            user-select: none;
        }
        
        /* Selected Tags */
        .ms-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            color: #4338ca;
            padding: 3px 8px 3px 10px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 600;
            border: 1px solid #c7d2fe;
            animation: tagPop 0.2s cubic-bezier(.34,1.56,.64,1);
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            line-height: 1.5;
        }
        @keyframes tagPop {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .ms-tag-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 6px;
            background: rgba(67,56,202,0.12);
            color: #4338ca;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
            line-height: 1;
        }
        .ms-tag-remove:hover {
            background: #ef4444;
            color: white;
            transform: scale(1.1);
        }
        .ms-tag-more {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            color: #15803d;
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid #bbf7d0;
            cursor: default;
        }
        
        /* Dropdown Panel */
        .ms-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 48px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.06);
            z-index: 9999;
            display: none;
            overflow: hidden;
        }
        .ms-container.open .ms-dropdown {
            display: block;
            animation: msSlideIn 0.25s cubic-bezier(.4,0,.2,1);
        }
        @keyframes msSlideIn {
            from { opacity: 0; transform: translateY(-10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        /* Search */
        .ms-search {
            padding: 12px 14px 10px;
            border-bottom: 1px solid #f1f5f9;
            position: relative;
        }
        .ms-search i {
            position: absolute;
            left: 26px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
            pointer-events: none;
        }
        .ms-search input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
            background: #f8fafc;
        }
        .ms-search input:focus {
            border-color: var(--primary-500, #3b82f6);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.06);
        }
        
        /* Options List */
        .ms-options {
            max-height: 240px;
            overflow-y: auto;
            padding: 6px;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .ms-options::-webkit-scrollbar { width: 5px; }
        .ms-options::-webkit-scrollbar-track { background: transparent; }
        .ms-options::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .ms-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.85rem;
            color: #334155;
            user-select: none;
            position: relative;
        }
        .ms-option:hover {
            background: #f1f5f9;
        }
        .ms-option.active {
            background: linear-gradient(135deg, #eef2ff, #e8e0ff);
            color: #4338ca;
            font-weight: 600;
        }
        .ms-option.active::after {
            content: '\f00c';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 12px;
            font-size: 12px;
            color: #4338ca;
        }
        .ms-option.hidden { display: none; }
        
        /* Custom checkbox look */
        .ms-check {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            background: #fff;
        }
        .ms-check i {
            font-size: 10px;
            color: white;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s ease;
        }
        .ms-option.active .ms-check {
            background: linear-gradient(135deg, #6366f1, #4338ca);
            border-color: #4338ca;
        }
        .ms-option.active .ms-check i {
            opacity: 1;
            transform: scale(1);
        }
        
        .ms-option-text {
            flex: 1;
            line-height: 1.4;
        }
        .ms-option-text small {
            display: block;
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 400;
            margin-top: 1px;
        }
        
        /* Footer */
        .ms-footer {
            padding: 10px 14px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
        }
        .ms-count {
            font-size: 0.78rem;
            color: #64748b;
        }
        .ms-count strong {
            color: #4338ca;
            font-weight: 700;
        }
        .ms-actions {
            display: flex;
            gap: 8px;
        }
        .ms-btn-select-all, .ms-btn-clear {
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 6px;
            transition: all 0.15s ease;
            border: none;
            background: none;
        }
        .ms-btn-select-all {
            color: #4338ca;
            background: rgba(67,56,202,0.08);
        }
        .ms-btn-select-all:hover {
            background: rgba(67,56,202,0.15);
        }
        .ms-btn-clear {
            color: #ef4444;
            background: rgba(239,68,68,0.08);
        }
        .ms-btn-clear:hover {
            background: rgba(239,68,68,0.15);
        }
        
        /* Empty state */
        .ms-empty {
            padding: 24px;
            text-align: center;
            color: #94a3b8;
            font-size: 0.85rem;
        }
        .ms-empty i {
            font-size: 24px;
            margin-bottom: 6px;
            display: block;
            color: #cbd5e1;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.35rem;
            justify-content: center;
        }
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .action-btn.btn-view {
            background: linear-gradient(135deg, #3B82F6, #1D4ED8);
            color: white;
        }
        .action-btn.btn-edit {
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: white;
        }
        .action-btn.btn-delete {
            background: linear-gradient(135deg, #EF4444, #DC2626);
            color: white;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: fadeInUp 0.4s ease-out forwards;
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        
        /* Legacy dropdown support */
        .dropdown-menu {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 160px;
        }
        .dropdown-menu.show {
            display: block;
        }
        .dropdown-item {
            display: block;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            border-bottom: 1px solid #eee;
        }
        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .dropdown-item:last-child {
            border-bottom: none;
        }
        .dropdown-divider {
            height: 1px;
            margin: 4px 0;
            background-color: #dee2e6;
        }
        .company-row.hidden {
            display: none;
        }
        .company-row {
            animation: fadeIn 0.3s ease;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Modern Action Buttons */
        .action-buttons {
            display: flex;
            gap: 6px;
            justify-content: center;
        }
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .action-btn.btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .action-btn.btn-edit {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .action-btn.btn-star {
            background: linear-gradient(135deg, #e0e0e0 0%, #9e9e9e 100%);
            color: white;
        }
        .action-btn.btn-star.starred {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        }
        .action-btn.btn-star.starred i {
            font-weight: 900;
        }
        .action-btn.btn-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #c92a2a 100%);
            color: white;
        }
        .action-btn.btn-restore {
            background: linear-gradient(135deg, #51cf66 0%, #2f9e44 100%);
            color: white;
        }
        .action-btn i {
            pointer-events: none;
        }

        /* Company Name Truncation */
        .company-name {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }

        /* Clickable headers */
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 25px !important;
            transition: background 0.2s;
        }
        .sortable:hover {
            background-color: rgba(0,0,0,0.05);
        }
        .sortable i {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #ccc;
            font-size: 0.8em;
        }
        .sortable.active {
            color: var(--primary);
        }
        .sortable.active i {
            color: var(--primary);
        }
        .sortable.asc i::before { content: "\f0de"; } /* sort-up */
        .sortable.desc i::before { content: "\f0dd"; } /* sort-down */

        .page-subtitle {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 0;
        }

        /* Column Visibility Button */
        .column-toggle-dropdown {
            position: relative;
            display: inline-block;
        }
        .column-toggle-menu {
            position: absolute;
            top: 100%;
            right: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 12px;
            margin-top: 8px;
            background-color: #ffffff;
            border: 2px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        .column-toggle-menu.show {
            display: block;
        }
        .column-toggle-menu h6 {
            color: #667eea;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        /* Column Visibility Button */
        .column-toggle-btn {
            background: white;
            border: 2px solid #e0e0e0;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #333;
        }
        .column-toggle-btn:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
        }
        .column-toggle-btn i {
            margin-right: 6px;
        }

        /* Modal Section Styling */
        .info-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .section-title {
            color: #667eea;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        .section-title i {
            margin-right: 8px;
        }
        .form-group label {
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-group label i.fa-star {
            font-size: 0.5rem;
            vertical-align: super;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 14px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }
        .modal-xl {
            max-width: 1000px;
        }
        .info-item {
            margin-bottom: 12px;
            line-height: 1.6;
            color: #333;
        }
        .info-item strong {
            color: #667eea;
            display: inline-block;
            min-width: 150px;
        }
        .info-item i {
            margin-right: 6px;
            color: #667eea;
        }
        .info-item a {
            color: #667eea;
            text-decoration: none;
        }
        .info-item a:hover {
            text-decoration: underline;
        }
        .description-text {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
            color: #555;
            line-height: 1.8;
        }

        /* Modal Header and Close Button */
        .modal-header {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 10;
        }
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        /* Table Styling */
        .column-toggle-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            color: #2c3e50 !important;
            font-weight: 500;
        }
        .column-toggle-item label {
            color: #2c3e50 !important;
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .column-toggle-item:hover {
            background-color: #f0f4ff;
            color: #667eea !important;
        }
        .column-toggle-item:hover label {
            color: #667eea !important;
        }
        .column-toggle-item input {
            margin-right: 10px;
            cursor: pointer;
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .table-container {
            overflow-x: auto;
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

            <!-- Hero Header -->
            <div class="page-hero animate-in">
                <div class="page-hero-content">
                    <div class="page-hero-info">
                        <h1 style="color: white;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            สถานประกอบการ
                        </h1>
                        <p style="color: rgba(255,255,255,0.9);">จัดการข้อมูลสถานประกอบการที่เข้าร่วมการประเมินในระบบ HICM</p>
                    </div>
                    <?php if (!$isCEO): ?>
                    <button type="button" class="btn-hero" onclick="openCreateModal()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        เพิ่มบริษัทใหม่
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid animate-in delay-1">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #DBEAFE; color: #3B82F6;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                        <div class="stat-label">บริษัทที่ใช้งาน</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #FEE2E2; color: #EF4444;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['inactive']); ?></div>
                        <div class="stat-label">ปิดใช้งาน</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #DCFCE7; color: #22C55E;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['small_count']); ?></div>
                        <div class="stat-label">ขนาดเล็ก</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #FEF3C7; color: #F59E0B;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['medium_count']); ?></div>
                        <div class="stat-label">ขนาดกลาง</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #EDE9FE; color: #8B5CF6;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['large_count']); ?></div>
                        <div class="stat-label">ขนาดใหญ่</div>
                    </div>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="filter-card animate-in delay-2">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <!-- Search Input -->
                        <div class="filter-group filter-search">
                            <label>ค้นหา</label>
                            <div class="search-input-wrapper">
                                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                                </svg>
                                <input type="text" name="search" id="search" class="filter-input" placeholder="ชื่อบริษัท, ผู้ติดต่อ..." value="<?php echo htmlspecialchars($searchFilter); ?>" autocomplete="off">
                            </div>
                        </div>
                        
                        <!-- Industry Dropdown -->
                        <div class="filter-group">
                            <label>อุตสาหกรรม</label>
                            <select name="industry" id="industry" class="filter-select">
                                <option value="">ทั้งหมด</option>
                                <?php foreach ($industryTypes as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $industryFilter == $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($value); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Size Dropdown -->
                        <div class="filter-group">
                            <label>ขนาด</label>
                            <select name="size" id="size" class="filter-select">
                                <option value="">ทั้งหมด</option>
                                <option value="small" <?php echo $sizeFilter == 'small' ? 'selected' : ''; ?>>เล็ก (&lt;200 คน)</option>
                                <option value="medium" <?php echo $sizeFilter == 'medium' ? 'selected' : ''; ?>>กลาง (200-500 คน)</option>
                                <option value="large" <?php echo $sizeFilter == 'large' ? 'selected' : ''; ?>>ใหญ่ (&gt;500 คน)</option>
                            </select>
                        </div>
                        
                        <!-- Status Dropdown -->
                        <div class="filter-group">
                            <label>สถานะ</label>
                            <select name="status" id="status" class="filter-select">
                                <option value="">ใช้งาน</option>
                                <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                                <option value="0" <?php echo $statusFilter == '0' ? 'selected' : ''; ?>>ไม่ใช้งาน</option>
                            </select>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="filter-group filter-actions">
                            <label>&nbsp;</label>
                            <div class="filter-btns">
                                <a href="<?php echo getBaseUrl(); ?>/pages/companies.php" class="filter-btn btn-reset" title="ล้างตัวกรอง">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                </a>
                                <a href="<?php echo getBaseUrl(); ?>/pages/company-locations.php" class="filter-btn btn-map" title="ดูแผนที่">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Results Count -->
                    <div class="filter-footer">
                        <span class="results-text">แสดง <strong id="visibleCount"><?php echo count($companies); ?></strong> รายการ</span>
                        <?php if ($industryFilter || $sizeFilter || $statusFilter || $searchFilter): ?>
                            <a href="<?php echo getBaseUrl(); ?>/pages/companies.php" class="clear-filters-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                ล้างตัวกรองทั้งหมด
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Companies Table -->
            <div class="table-card animate-in delay-3">
                <div class="table-card-header">
                    <h4>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        รายชื่อสถานประกอบการ
                    </h4>
                    <span class="table-count"><?php echo count($companies); ?> รายการ</span>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="name">ชื่อบริษัท <i class="fas fa-sort"></i></th>
                                <th class="sortable" data-sort="industry" data-col-id="industry">อุตสาหกรรม <i class="fas fa-sort"></i></th>
                                <th class="sortable" data-sort="size" data-col-id="size">ขนาด <i class="fas fa-sort"></i></th>
                                <th class="sortable" data-sort="contact" data-col-id="contact">ผู้ติดต่อ <i class="fas fa-sort"></i></th>
                                <th class="sortable" data-sort="status" data-col-id="status">สถานะ <i class="fas fa-sort"></i></th>
                                <th style="text-align: center;"><?php echo $isCEO ? 'ดูข้อมูล' : 'การดำเนินการ'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($companies)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem;">
                                        <div style="color: var(--gray-400);">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem;">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                            </svg>
                                            <p style="margin: 0; font-size: 1rem;">ไม่พบข้อมูลสถานประกอบการ</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($companies as $company): 
                                    $initials = '';
                                    $parts = explode(' ', trim($company['company_name']));
                                    foreach (array_slice($parts, 0, 2) as $part) {
                                        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
                                    }
                                    $empCount = intval($company['employee_count'] ?? 0);
                                    
                                    // Avatar logic
                                    $avatarFile = $company['company_owner_avatar'] ?? $company['logo'] ?? 'default';
                                    $hasAvatar = ($avatarFile && $avatarFile !== 'default' && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $avatarFile));
                                    $avatarUrl = $hasAvatar ? getBaseUrl() . '/assets/uploads/avatars/' . $avatarFile : '';
                                ?>
                                    <tr class="company-row" 
                                        data-name="<?php echo htmlspecialchars($company['company_name']); ?>"
                                        data-industry="<?php echo htmlspecialchars($company['industry_type']); ?>"
                                        data-employees="<?php echo $empCount; ?>"
                                        data-contact="<?php echo htmlspecialchars($company['contact_name']); ?>"
                                        data-status="<?php echo $company['is_active']; ?>"
                                        data-date="<?php echo strtotime($company['created_at']); ?>">
                                        <td>
                                            <div class="company-cell">
                                                <div class="company-avatar" style="<?php echo $hasAvatar ? 'background: none; padding: 0;' : ''; ?>">
                                                    <?php if ($hasAvatar): ?>
                                                        <img src="<?php echo $avatarUrl; ?>" alt="Logo" style="width: 100%; height: 100%; border-radius: 10px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <?php echo $initials ?: '?'; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="company-info">
                                                    <h5 title="<?php echo htmlspecialchars($company['company_name']); ?>">
                                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                                    </h5>
                                                    <?php if ($company['province']): ?>
                                                        <small><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($company['province']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td data-col-id="industry">
                                            <div class="industry-tags">
                                                <?php 
                                                $companyIndustries = array_filter(array_map('trim', explode('|', $company['industry_type'] ?? '')));
                                                if (!empty($companyIndustries)):
                                                    $shown = 0;
                                                    foreach ($companyIndustries as $cind):
                                                        if ($shown >= 2) { echo '<span class="industry-tag" title="' . htmlspecialchars(implode(', ', array_map(function($v){ return explode(' (', $v)[0]; }, array_slice($companyIndustries, 2)))) . '">+' . (count($companyIndustries) - 2) . '</span>'; break; }
                                                        $shortInd = explode(' (', trim($cind))[0];
                                                ?>
                                                    <span class="industry-tag" title="<?php echo htmlspecialchars(trim($cind)); ?>"><?php echo htmlspecialchars(mb_substr($shortInd, 0, 18)); ?></span>
                                                <?php 
                                                        $shown++;
                                                    endforeach;
                                                else:
                                                ?>
                                                    <span style="color: var(--gray-400);">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-col-id="size">
                                            <?php 
                                            $empCount = intval($company['employee_count'] ?? 0);
                                            $sizeInfo = getCompanySizeByEmployee($empCount);
                                            ?>
                                            <span class="size-badge <?php echo $sizeInfo['class']; ?>">
                                                <?php echo $sizeInfo['label']; ?>
                                            </span>
                                            <div class="size-count"><?php echo number_format($empCount); ?> คน</div>
                                        </td>
                                        <td data-col-id="contact">
                                            <div style="font-weight: 500; color: var(--gray-800);"><?php echo htmlspecialchars($company['contact_name'] ?: '-'); ?></div>
                                            <?php if ($company['contact_phone']): ?>
                                                <small style="color: var(--gray-500);"><i class="fas fa-phone" style="font-size: 0.7rem;"></i> <?php echo htmlspecialchars($company['contact_phone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td data-col-id="status">
                                            <?php if ($company['is_active']): ?>
                                                <span class="status-badge active">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                                                    ใช้งาน
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge inactive">
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                                                    ปิดใช้งาน
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="action-btn btn-view" onclick="openViewModal(<?php echo $company['id']; ?>)" title="ดูรายละเอียด">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if (!$isCEO): ?>
                                                <?php if ($company['is_active']): ?>
                                                <button type="button" class="action-btn btn-edit" onclick="openEditModal(<?php echo $company['id']; ?>)" title="แก้ไข">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="action-btn btn-delete" onclick="confirmDelete(<?php echo $company['id']; ?>, '<?php echo addslashes($company['company_name']); ?>')" title="ลบ">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="action-btn btn-restore" onclick="confirmRestore(<?php echo $company['id']; ?>, '<?php echo addslashes($company['company_name']); ?>')" title="เปิดใช้งานอีกครั้ง">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Create Company Modal -->
    <div class="modal-overlay" id="createModalOverlay">
        <div class="modal modal-xl">
            <div class="modal-header" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-plus-circle"></i> เพิ่มสถานประกอบการใหม่</h5>
                <button type="button" class="modal-close" onclick="closeCreateModal()" style="color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding: 24px;">

                    <!-- Section 1: Account & Contact -->
                    <div class="info-section" style="border-left-color: #10B981;">
                        <h6 class="section-title" style="color: #059669;"><i class="fas fa-user-plus"></i> ข้อมูลบัญชีและผู้ติดต่อ</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="createUsername"><i class="fas fa-star text-danger"></i> ชื่อผู้ใช้</label>
                                    <input type="text" name="username" id="createUsername" class="form-control" required 
                                        pattern="^[a-zA-Z0-9_.-]{3,30}$" 
                                        placeholder="ภาษาอังกฤษ 3-30 ตัว (a-z, 0-9, ._-)"
                                        title="ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษ ตัวเลข จุด ขีด หรือ underscore เท่านั้น (3-30 ตัว)"
                                        onchange="checkUsernameAvailability()">
                                    <small id="usernameStatus" class="form-text d-block mt-1" style="display: none;"></small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="createPassword"><i class="fas fa-star text-danger"></i> รหัสผ่าน</label>
                                    <input type="password" name="password" id="createPassword" class="form-control" required placeholder="อย่างน้อย 6 ตัว">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="createContactName"><i class="fas fa-star text-danger"></i> ชื่อผู้ติดต่อ</label>
                                    <input type="text" name="contact_name" id="createContactName" class="form-control" required placeholder="ชื่อ-นามสกุล">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="createContactPosition">ตำแหน่ง</label>
                                    <input type="text" name="contact_position" id="createContactPosition" class="form-control" placeholder="เช่น ผู้จัดการ">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="createContactEmail"><i class="fas fa-star text-danger"></i> อีเมล</label>
                                    <input type="email" name="contact_email" id="createContactEmail" class="form-control" required placeholder="email@company.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="createContactPhone">โทรศัพท์ผู้ติดต่อ</label>
                                    <input type="tel" name="contact_phone" id="createContactPhone" class="form-control" 
                                        pattern="^[0-9]{9,10}$|^$"
                                        placeholder="0891234567 (ไม่บังคับ)"
                                        title="กรอกตัวเลข 9-10 หลัก"
                                        maxlength="10"
                                        oninput="autoFormatPhone(this)">
                                    <small id="createContactPhone_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Company Information -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-building"></i> ข้อมูลบริษัท</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="createCompanyName"><i class="fas fa-star text-danger"></i> ชื่อบริษัท (ไทย)</label>
                                    <input type="text" name="company_name" id="createCompanyName" class="form-control" required placeholder="บริษัท xxx จำกัด">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="createCompanyNameEn">ชื่อบริษัท (อังกฤษ)</label>
                                    <input type="text" name="company_name_en" id="createCompanyNameEn" class="form-control" placeholder="Company Ltd.">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createTaxId">เลขทะเบียนนิติบุคคล</label>
                                    <input type="text" name="tax_id" id="createTaxId" class="form-control" 
                                        pattern="^[0-9]{10}([0-9]{3})?$|^$"
                                        placeholder="13 หรือ 10 ตัวเลข (ไม่บังคับ)"
                                        title="เลขทะเบียนต้องเป็นตัวเลข 10 หรือ 13 ตัว"
                                        oninput="validateField(this, 'taxid')">
                                    <small id="createTaxId_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createSize">ขนาดบริษัท</label>
                                    <select name="company_size" id="createSize" class="form-control">
                                        <option value="">เลือกขนาด</option>
                                        <?php foreach ($companySizes as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><i class="fas fa-industry" style="color: var(--primary-500, #3b82f6);"></i> อุตสาหกรรม <small class="text-muted">(เลือกได้หลายรายการ)</small></label>
                                    <div class="ms-container" id="createIndustrySelect">
                                        <div class="ms-trigger" data-ms-id="createIndustrySelect">
                                            <span class="ms-placeholder"><i class="fas fa-plus-circle"></i> คลิกเพื่อเลือก...</span>
                                        </div>
                                        <div class="ms-dropdown">
                                            <div class="ms-search">
                                                <i class="fas fa-search"></i>
                                                <input type="text" placeholder="พิมพ์เพื่อค้นหา..." data-ms-search="createIndustrySelect">
                                            </div>
                                            <div class="ms-options">
                                                <?php foreach (AUDITOR_EXPERTISE as $idx => $exp): 
                                                    $shortName = explode(' (', $exp)[0];
                                                    $engName = '';
                                                    if (preg_match('/\(([^)]+)\)/', $exp, $m)) $engName = $m[1];
                                                ?>
                                                <div class="ms-option" data-value="<?php echo htmlspecialchars($exp); ?>" data-ms-id="createIndustrySelect">
                                                    <div class="ms-check"><i class="fas fa-check"></i></div>
                                                    <div class="ms-option-text">
                                                        <?php echo htmlspecialchars($shortName); ?>
                                                        <?php if ($engName): ?><small><?php echo htmlspecialchars($engName); ?></small><?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="ms-footer">
                                                <span class="ms-count">เลือก <strong>0</strong> / <?php echo count(AUDITOR_EXPERTISE); ?></span>
                                                <div class="ms-actions">
                                                    <button type="button" class="ms-btn-select-all" data-ms-id="createIndustrySelect"><i class="fas fa-check-double"></i> เลือกทั้งหมด</button>
                                                    <button type="button" class="ms-btn-clear" data-ms-id="createIndustrySelect"><i class="fas fa-times"></i> ล้าง</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="createEmployeeCount">จำนวนพนักงาน</label>
                                    <input type="number" name="employee_count" id="createEmployeeCount" class="form-control" min="0" placeholder="เช่น 150">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="createEstablishedYear">ปีที่ก่อตั้ง</label>
                                    <input type="number" name="established_year" id="createEstablishedYear" class="form-control" min="1900" max="<?php echo date('Y'); ?>" placeholder="<?php echo date('Y'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Address -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่</h6>
                        <div class="form-group">
                            <label for="createAddress">ที่อยู่</label>
                            <input type="text" name="address" id="createAddress" class="form-control" placeholder="เลขที่ ถนน ซอย">
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createProvince">จังหวัด</label>
                                    <input type="text" name="province" id="createProvince" class="form-control" placeholder="เช่น กรุงเทพมหานคร">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createDistrict">อำเภอ/เขต</label>
                                    <input type="text" name="district" id="createDistrict" class="form-control" placeholder="เช่น บางกะปิ">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createPostalCode">รหัสไปรษณีย์</label>
                                    <input type="text" name="postal_code" id="createPostalCode" class="form-control" placeholder="10240">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Additional -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลเพิ่มเติม</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createPhone">โทรศัพท์บริษัท</label>
                                    <input type="tel" name="phone" id="createPhone" class="form-control" 
                                        pattern="^[0-9]{9,10}$|^$"
                                        placeholder="0891234567 (ไม่บังคับ)"
                                        title="กรอกตัวเลข 9-10 หลัก"
                                        maxlength="10"
                                        oninput="autoFormatPhone(this)">
                                    <small id="createPhone_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createWebsite">เว็บไซต์</label>
                                    <input type="url" name="website" id="createWebsite" class="form-control" placeholder="https://www.example.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="createFax">แฟกซ์</label>
                                    <input type="tel" name="fax" id="createFax" class="form-control" 
                                        pattern="^[0-9]{9,10}$|^$"
                                        placeholder="0212345678 (ไม่บังคับ)"
                                        title="กรอกตัวเลข 9-10 หลัก"
                                        maxlength="10"
                                        oninput="autoFormatPhone(this)">
                                    <small id="createFax_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="createDescription">คำอธิบาย</label>
                            <textarea name="description" id="createDescription" class="form-control" rows="2" placeholder="รายละเอียดเกี่ยวกับบริษัท..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f0fdf4; padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">
                        <i class="fas fa-times"></i> ยกเลิก
                    </button>
                    <button type="submit" name="create_company" class="btn btn-primary" style="background: linear-gradient(135deg, #10B981, #059669); border: none;">
                        <i class="fas fa-plus-circle"></i> สร้างสถานประกอบการ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <div class="modal-overlay" id="editModalOverlay">
        <div class="modal modal-xl">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit"></i> แก้ไขสถานประกอบการ</h5>
                <button type="button" class="modal-close" onclick="closeEditModal()" style="color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding: 24px;">
                    <input type="hidden" name="company_id" id="editCompanyId">
                    
                    <!-- Section 1: Company Information -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-building"></i> ข้อมูลบริษัท</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editCompanyName"><i class="fas fa-star text-danger"></i> ชื่อบริษัท (ไทย)</label>
                                    <input type="text" name="company_name" id="editCompanyName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editCompanyNameEn">ชื่อบริษัท (อังกฤษ)</label>
                                    <input type="text" name="company_name_en" id="editCompanyNameEn" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editTaxId">เลขทะเบียนนิติบุคคล</label>
                                    <input type="text" name="tax_id" id="editTaxId" class="form-control" 
                                        pattern="^[0-9]{10}([0-9]{3})?$|^$"
                                        placeholder="13 หรือ 10 ตัวเลข"
                                        title="เลขทะเบียนต้องเป็นตัวเลข 10 หรือ 13 ตัว"
                                        maxlength="13"
                                        oninput="validateField(this, 'taxid')">
                                    <small id="editTaxId_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editSize">ขนาดบริษัท</label>
                                    <select name="company_size" id="editSize" class="form-control">
                                        <option value="">เลือกขนาด</option>
                                        <?php foreach ($companySizes as $key => $value): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><i class="fas fa-industry" style="color: var(--primary-500, #3b82f6);"></i> อุตสาหกรรม <small class="text-muted">(เลือกได้หลายรายการ)</small></label>
                                    <div class="ms-container" id="editIndustrySelect">
                                        <div class="ms-trigger" data-ms-id="editIndustrySelect">
                                            <span class="ms-placeholder"><i class="fas fa-plus-circle"></i> คลิกเพื่อเลือก...</span>
                                        </div>
                                        <div class="ms-dropdown">
                                            <div class="ms-search">
                                                <i class="fas fa-search"></i>
                                                <input type="text" placeholder="พิมพ์เพื่อค้นหา..." data-ms-search="editIndustrySelect">
                                            </div>
                                            <div class="ms-options">
                                                <?php foreach (AUDITOR_EXPERTISE as $idx => $exp): 
                                                    $shortName = explode(' (', $exp)[0];
                                                    $engName = '';
                                                    if (preg_match('/\(([^)]+)\)/', $exp, $m)) $engName = $m[1];
                                                ?>
                                                <div class="ms-option" data-value="<?php echo htmlspecialchars($exp); ?>" data-ms-id="editIndustrySelect">
                                                    <div class="ms-check"><i class="fas fa-check"></i></div>
                                                    <div class="ms-option-text">
                                                        <?php echo htmlspecialchars($shortName); ?>
                                                        <?php if ($engName): ?><small><?php echo htmlspecialchars($engName); ?></small><?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="ms-footer">
                                                <span class="ms-count">เลือก <strong>0</strong> / <?php echo count(AUDITOR_EXPERTISE); ?></span>
                                                <div class="ms-actions">
                                                    <button type="button" class="ms-btn-select-all" data-ms-id="editIndustrySelect"><i class="fas fa-check-double"></i> เลือกทั้งหมด</button>
                                                    <button type="button" class="ms-btn-clear" data-ms-id="editIndustrySelect"><i class="fas fa-times"></i> ล้าง</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editEmployeeCount">จำนวนพนักงาน</label>
                                    <input type="number" name="employee_count" id="editEmployeeCount" class="form-control" min="0" placeholder="เช่น 150">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editEstablishedYear">ปีที่ก่อตั้ง</label>
                                    <input type="number" name="established_year" id="editEstablishedYear" class="form-control" min="1900" max="<?php echo date('Y'); ?>" placeholder="<?php echo date('Y'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Contact Information -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้ติดต่อ</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="editContactName"><i class="fas fa-star text-danger"></i> ชื่อผู้ติดต่อ</label>
                                    <input type="text" name="contact_name" id="editContactName" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="editContactPosition">ตำแหน่ง</label>
                                    <input type="text" name="contact_position" id="editContactPosition" class="form-control" placeholder="เช่น ผู้จัดการ">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="editContactEmail"><i class="fas fa-star text-danger"></i> อีเมล</label>
                                    <input type="email" name="contact_email" id="editContactEmail" class="form-control" required placeholder="email@company.com">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="editContactPhone">โทรศัพท์ผู้ติดต่อ</label>
                                    <input type="tel" name="contact_phone" id="editContactPhone" class="form-control" 
                                        pattern="^[0-9]{9,10}$|^$"
                                        placeholder="0891234567"
                                        title="กรอกตัวเลข 9-10 หลัก"
                                        maxlength="10"
                                        oninput="autoFormatPhone(this)">
                                    <small id="editContactPhone_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Address -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่</h6>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="editAddress">ที่อยู่</label>
                                    <input type="text" name="address" id="editAddress" class="form-control" placeholder="เลขที่ ถนน ซอย">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editProvince">จังหวัด</label>
                                    <input type="text" name="province" id="editProvince" class="form-control" placeholder="เช่น กรุงเทพมหานคร">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editDistrict">อำเภอ/เขต</label>
                                    <input type="text" name="district" id="editDistrict" class="form-control" placeholder="เช่น บางกะปิ">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editPostalCode">รหัสไปรษณีย์</label>
                                    <input type="text" name="postal_code" id="editPostalCode" class="form-control" 
                                        pattern="^[0-9]{5}$|^$"
                                        placeholder="10240"
                                        title="รหัสไปรษณีย์ 5 หลัก"
                                        maxlength="5"
                                        oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Additional Information -->
                    <div class="info-section">
                        <h6 class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลเพิ่มเติม</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editPhone">โทรศัพท์บริษัท</label>
                                    <input type="tel" name="phone" id="editPhone" class="form-control" 
                                        pattern="^[0-9]{9,10}$|^$"
                                        placeholder="0212345678"
                                        title="กรอกตัวเลข 9-10 หลัก"
                                        maxlength="10"
                                        oninput="autoFormatPhone(this)">
                                    <small id="editPhone_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editWebsite">เว็บไซต์</label>
                                    <input type="url" name="website" id="editWebsite" class="form-control" placeholder="https://www.example.com">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="editFax">แฟกซ์</label>
                                    <input type="tel" name="fax" id="editFax" class="form-control" 
                                        pattern="^[0-9]{9,10}$|^$"
                                        placeholder="0212345678"
                                        title="กรอกตัวเลข 9-10 หลัก"
                                        maxlength="10"
                                        oninput="autoFormatPhone(this)">
                                    <small id="editFax_status" class="form-text d-block mt-1" style="display:none;"></small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="editDescription">คำอธิบาย</label>
                            <textarea name="description" id="editDescription" class="form-control" rows="2" placeholder="รายละเอียดเกี่ยวกับบริษัท..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f8f9fa; padding: 16px 24px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> ยกเลิก
                    </button>
                    <button type="submit" name="update_company" class="btn btn-primary">
                        <i class="fas fa-save"></i> บันทึกการเปลี่ยนแปลง
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Company Modal -->
    <div class="modal-overlay" id="viewModalOverlay">
        <div class="modal modal-xl">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title"><i class="fas fa-eye"></i> รายละเอียดสถานประกอบการ</h5>
                <button type="button" class="modal-close" onclick="closeViewModal()" style="color: white;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody" style="padding: 30px;">
                <!-- Company details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการลบ</h5>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="company_id" id="deleteCompanyId">
                    <p>คุณต้องการลบสถานประกอบการ "<span id="deleteCompanyName"></span>" ใช่หรือไม่?</p>
                    <p class="text-danger"><small>หมายเหตุ: การลบนี้จะเป็นการปิดใช้งานสถานประกอบการ ไม่ใช่การลบข้อมูลถาวร</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">ยกเลิก</button>
                    <button type="submit" name="delete_company" class="btn btn-danger">ลบ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal-overlay" id="restoreModalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h5 class="modal-title">ยืนยันการเปิดใช้งาน</h5>
                <button type="button" class="modal-close" onclick="closeRestoreModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="company_id" id="restoreCompanyId">
                    <p>คุณต้องการเปิดใช้งานสถานประกอบการ "<span id="restoreCompanyName"></span>" อีกครั้งใช่หรือไม่?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRestoreModal()">ยกเลิก</button>
                    <button type="submit" name="restore_company" class="btn btn-success">เปิดใช้งาน</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ================================================
        // Premium Multi-Select Component (ms-*)
        // Must be defined first, before any function that calls msSetValues etc.
        // ================================================
        const msState = {};
        
        function msGetState(id) {
            if (!msState[id]) msState[id] = { selected: new Set(), open: false };
            return msState[id];
        }
        
        function msToggle(id, forceClose) {
            const container = document.getElementById(id);
            if (!container) return;
            const state = msGetState(id);
            
            if (forceClose || state.open) {
                container.classList.remove('open');
                state.open = false;
            } else {
                document.querySelectorAll('.ms-container.open').forEach(c => {
                    if (c.id !== id) msToggle(c.id, true);
                });
                container.classList.add('open');
                state.open = true;
                const search = container.querySelector('[data-ms-search]');
                if (search) { search.value = ''; msFilter(id, ''); search.focus(); }
            }
        }
        
        function msToggleOption(id, value) {
            const state = msGetState(id);
            if (state.selected.has(value)) {
                state.selected.delete(value);
            } else {
                state.selected.add(value);
            }
            msRenderOptions(id);
            msRenderTrigger(id);
            msSyncHiddenInputs(id);
        }
        
        function msRemove(id, value, evt) {
            if (evt) { evt.stopPropagation(); evt.preventDefault(); }
            const state = msGetState(id);
            state.selected.delete(value);
            msRenderOptions(id);
            msRenderTrigger(id);
            msSyncHiddenInputs(id);
        }
        
        function msSelectAll(id) {
            const container = document.getElementById(id);
            const state = msGetState(id);
            container.querySelectorAll('.ms-option:not(.hidden)').forEach(opt => {
                state.selected.add(opt.dataset.value);
            });
            msRenderOptions(id);
            msRenderTrigger(id);
            msSyncHiddenInputs(id);
        }
        
        function msClear(id) {
            const state = msGetState(id);
            state.selected.clear();
            msRenderOptions(id);
            msRenderTrigger(id);
            msSyncHiddenInputs(id);
        }
        
        function msSetValues(id, values) {
            const state = msGetState(id);
            state.selected = new Set(values);
            msRenderOptions(id);
            msRenderTrigger(id);
            msSyncHiddenInputs(id);
        }
        
        function msGetValues(id) {
            return Array.from(msGetState(id).selected);
        }
        
        function msFilter(id, term) {
            const container = document.getElementById(id);
            const normalized = term.toLowerCase().trim();
            let visibleCount = 0;
            container.querySelectorAll('.ms-option').forEach(opt => {
                const text = opt.querySelector('.ms-option-text').textContent.toLowerCase();
                const match = !normalized || text.includes(normalized);
                opt.classList.toggle('hidden', !match);
                if (match) visibleCount++;
            });
            let emptyEl = container.querySelector('.ms-empty');
            if (visibleCount === 0) {
                if (!emptyEl) {
                    emptyEl = document.createElement('div');
                    emptyEl.className = 'ms-empty';
                    emptyEl.innerHTML = '<i class="fas fa-search"></i>ไม่พบรายการที่ค้นหา';
                    container.querySelector('.ms-options').appendChild(emptyEl);
                }
                emptyEl.style.display = '';
            } else if (emptyEl) {
                emptyEl.style.display = 'none';
            }
        }
        
        function msRenderTrigger(id) {
            const container = document.getElementById(id);
            const trigger = container.querySelector('.ms-trigger');
            const state = msGetState(id);
            const selected = Array.from(state.selected);
            const count = selected.length;
            const maxShow = 2;
            
            trigger.querySelectorAll('.ms-tag, .ms-tag-more, .ms-placeholder').forEach(el => el.remove());
            
            if (count === 0) {
                const ph = document.createElement('span');
                ph.className = 'ms-placeholder';
                ph.innerHTML = '<i class="fas fa-plus-circle"></i> คลิกเพื่อเลือกอุตสาหกรรม...';
                trigger.appendChild(ph);
            } else {
                selected.slice(0, maxShow).forEach(val => {
                    const shortName = val.split(' (')[0];
                    const tag = document.createElement('span');
                    tag.className = 'ms-tag';
                    tag.title = val;
                    tag.innerHTML = `${msEscapeHtml(shortName)} <span class="ms-tag-remove" onclick="msRemove('${id}', '${msEscapeJs(val)}', event)">×</span>`;
                    trigger.appendChild(tag);
                });
                if (count > maxShow) {
                    const more = document.createElement('span');
                    more.className = 'ms-tag-more';
                    more.title = selected.slice(maxShow).map(v => v.split(' (')[0]).join(', ');
                    more.textContent = `+${count - maxShow} อื่นๆ`;
                    trigger.appendChild(more);
                }
            }
            
            const countEl = container.querySelector('.ms-count strong');
            if (countEl) countEl.textContent = count;
        }
        
        function msRenderOptions(id) {
            const container = document.getElementById(id);
            const state = msGetState(id);
            container.querySelectorAll('.ms-option').forEach(opt => {
                opt.classList.toggle('active', state.selected.has(opt.dataset.value));
            });
        }
        
        function msSyncHiddenInputs(id) {
            const container = document.getElementById(id);
            const state = msGetState(id);
            container.querySelectorAll('input[type="hidden"][name="industry_type[]"]').forEach(el => el.remove());
            state.selected.forEach(val => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'industry_type[]';
                input.value = val;
                container.appendChild(input);
            });
        }
        
        function msEscapeHtml(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
        
        function msEscapeJs(str) {
            return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }
        
        // Multi-Select Event Delegation
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.ms-trigger');
            if (trigger) { e.preventDefault(); msToggle(trigger.dataset.msId); return; }
            if (e.target.closest('.ms-tag-remove')) return;
            const option = e.target.closest('.ms-option');
            if (option) { e.preventDefault(); msToggleOption(option.dataset.msId, option.dataset.value); return; }
            const selectAllBtn = e.target.closest('.ms-btn-select-all');
            if (selectAllBtn) { e.preventDefault(); msSelectAll(selectAllBtn.dataset.msId); return; }
            const clearBtn = e.target.closest('.ms-btn-clear');
            if (clearBtn) { e.preventDefault(); msClear(clearBtn.dataset.msId); return; }
            if (e.target.closest('.ms-dropdown')) return;
            document.querySelectorAll('.ms-container.open').forEach(c => msToggle(c.id, true));
        });
        document.addEventListener('input', function(e) {
            if (e.target.matches('[data-ms-search]')) msFilter(e.target.dataset.msSearch, e.target.value);
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.querySelectorAll('.ms-container.open').forEach(c => msToggle(c.id, true));
        });

        // ================================================
        // Page Functions
        // ================================================
        function toggleDropdown(companyId) {
            const dropdown = document.getElementById('dropdown-' + companyId);
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            allDropdowns.forEach(d => {
                if (d !== dropdown) {
                    d.classList.remove('show');
                }
            });
            dropdown.classList.toggle('show');
        }

        function openCreateModal() {
            document.getElementById('createModalOverlay').classList.add('active');
        }

        function closeCreateModal() {
            document.getElementById('createModalOverlay').classList.remove('active');
        }

        // Check Username Availability
        function checkUsernameAvailability() {
            const username = document.getElementById('createUsername').value.trim();
            const statusElement = document.getElementById('usernameStatus');
            const submitBtn = document.querySelector('.modal-footer .btn-primary');
            
            if (!username) {
                statusElement.style.display = 'none';
                return;
            }
            
            // Check pattern first
            if (!/^[a-zA-Z0-9_.-]{3,30}$/.test(username)) {
                statusElement.textContent = '❌ ชื่อผู้ใช้ไม่ถูกต้อง (ภาษาอังกฤษ 3-30 ตัว)';
                statusElement.style.color = '#dc2626';
                statusElement.style.display = 'block';
                submitBtn.disabled = true;
                return;
            }
            
            // Check availability via AJAX
            const formData = new FormData();
            formData.append('check_username', '1');
            formData.append('username', username);
            
            statusElement.textContent = '⏳ กำลังตรวจสอบ...';
            statusElement.style.color = '#f59e0b';
            statusElement.style.display = 'block';
            
            fetch('<?php echo getBaseUrl(); ?>/pages/companies.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    statusElement.textContent = '✅ ' + data.message;
                    statusElement.style.color = '#10b981';
                    submitBtn.disabled = false;
                } else {
                    statusElement.textContent = '❌ ' + data.message;
                    statusElement.style.color = '#dc2626';
                    submitBtn.disabled = true;
                }
                statusElement.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                statusElement.textContent = '⚠️ เกิดข้อผิดพลาดในการตรวจสอบ';
                statusElement.style.color = '#f59e0b';
                statusElement.style.display = 'block';
            });
        }

        // Auto-format phone/fax: digits only, strip non-numeric
        function autoFormatPhone(input) {
            let digits = input.value.replace(/[^0-9]/g, '');
            
            // Limit max digits based on type
            if (/^0[689]/.test(digits)) {
                digits = digits.substring(0, 10); // mobile 10 digits
            } else if (digits.startsWith('02')) {
                digits = digits.substring(0, 9);  // Bangkok 9 digits
            } else if (/^0[3-7]/.test(digits)) {
                digits = digits.substring(0, 9);  // provincial 9 digits
            } else {
                digits = digits.substring(0, 10);
            }
            
            input.value = digits;
            validateField(input, 'phone');
        }

        // Real-time field validation
        function validateField(input, type) {
            const val = input.value.trim();
            const statusEl = document.getElementById(input.id + '_status');
            if (!statusEl) return;
            
            // Empty = OK (optional fields)
            if (!val) {
                statusEl.style.display = 'none';
                input.style.borderColor = '';
                return;
            }
            
            let isValid = false;
            let errorMsg = '';
            let successMsg = '';
            let hint = '';
            
            switch (type) {
                case 'phone':
                case 'fax': {
                    const label = type === 'fax' ? 'แฟกซ์' : 'เบอร์โทร';
                    let digits = val.replace(/[^0-9]/g, '');
                    if (digits !== val) {
                        errorMsg = '❌ ' + label + ' ต้องเป็นตัวเลขเท่านั้น';
                        break;
                    }
                    
                    // Determine expected length
                    let expectedDigits = 10;
                    let typeLabel = 'มือถือ';
                    if (/^0[689]/.test(digits)) {
                        expectedDigits = 10;
                        typeLabel = 'มือถือ';
                    } else if (digits.startsWith('02')) {
                        expectedDigits = 9;
                        typeLabel = 'กรุงเทพฯ';
                    } else if (/^0[3-7]/.test(digits)) {
                        expectedDigits = 9;
                        typeLabel = 'ต่างจังหวัด';
                    }
                    
                    if (digits.length === expectedDigits) {
                        isValid = true;
                        successMsg = '✅ ' + label + 'ถูกต้อง (' + typeLabel + ' ' + expectedDigits + ' หลัก)';
                    } else if (digits.length < expectedDigits) {
                        hint = '💡 ' + digits.length + '/' + expectedDigits + ' หลัก (' + typeLabel + ')';
                    } else {
                        errorMsg = '❌ เกินจำนวนหลัก (' + digits.length + '/' + expectedDigits + ')';
                    }
                    break;
                }
                case 'taxid': {
                    let digits = val.replace(/[^0-9]/g, '');
                    if (digits !== val) {
                        errorMsg = '❌ เลขทะเบียนต้องเป็นตัวเลขเท่านั้น';
                    } else if (digits.length < 10) {
                        hint = '💡 กรอกตัวเลข ' + digits.length + '/13 หลัก';
                    } else if (digits.length === 10 || digits.length === 13) {
                        isValid = true;
                        successMsg = '✅ เลขทะเบียน ' + digits.length + ' หลัก ถูกต้อง';
                    } else if (digits.length > 10 && digits.length < 13) {
                        hint = '💡 กรอกตัวเลข ' + digits.length + '/13 หลัก';
                    } else {
                        errorMsg = '❌ เลขทะเบียนต้องเป็น 10 หรือ 13 หลัก (ปัจจุบัน ' + digits.length + ' หลัก)';
                    }
                    break;
                }
            }
            
            if (isValid) {
                statusEl.textContent = successMsg;
                statusEl.style.color = '#10b981';
                input.style.borderColor = '#10b981';
            } else if (errorMsg) {
                statusEl.textContent = errorMsg;
                statusEl.style.color = '#dc2626';
                input.style.borderColor = '#dc2626';
            } else if (hint) {
                statusEl.textContent = hint;
                statusEl.style.color = '#f59e0b';
                input.style.borderColor = '#f59e0b';
            }
            statusEl.style.display = 'block';
        }

        function openEditModal(companyId) {
            console.log('Opening edit modal for company ID:', companyId);
            // Load company data via AJAX
            fetch(`<?php echo getBaseUrl(); ?>/api/get_company.php?id=${companyId}`)
                .then(response => {
                    console.log('Edit modal response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Edit modal data:', data);
                    if (data.success && data.company) {
                        const company = data.company;
                        document.getElementById('editCompanyId').value = company.id;
                        document.getElementById('editCompanyName').value = company.company_name || '';
                        document.getElementById('editCompanyNameEn').value = company.company_name_en || '';
                        document.getElementById('editTaxId').value = company.tax_id || '';
                        
                        // Handle industry multi-select
                        const industries = (company.industry_type || '').split('|').map(s => s.trim()).filter(Boolean);
                        msSetValues('editIndustrySelect', industries);

                        document.getElementById('editSize').value = company.company_size || '';
                        document.getElementById('editEmployeeCount').value = company.employee_count || '';
                        document.getElementById('editEstablishedYear').value = company.established_year || '';
                        document.getElementById('editPhone').value = company.phone || '';
                        document.getElementById('editAddress').value = company.address || '';
                        document.getElementById('editProvince').value = company.province || '';
                        document.getElementById('editDistrict').value = company.district || '';
                        document.getElementById('editPostalCode').value = company.postal_code || '';
                        document.getElementById('editWebsite').value = company.website || '';
                        document.getElementById('editFax').value = company.fax || '';
                        document.getElementById('editContactName').value = company.contact_name || '';
                        document.getElementById('editContactPosition').value = company.contact_position || '';
                        document.getElementById('editContactEmail').value = company.contact_email || '';
                        document.getElementById('editContactPhone').value = company.contact_phone || '';
                        document.getElementById('editDescription').value = company.description || '';

                        document.getElementById('editModalOverlay').classList.add('active');
                    } else {
                        console.error('Invalid data structure:', data);
                        alert('ไม่พบข้อมูลสถานประกอบการ: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error loading company data:', error);
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + error.message);
                });
        }

        function closeEditModal() {
            document.getElementById('editModalOverlay').classList.remove('active');
        }

        function openViewModal(companyId) {
            console.log('Opening view modal for company ID:', companyId);
            // Load company details via AJAX
            fetch(`<?php echo getBaseUrl(); ?>/api/get_company.php?id=${companyId}`)
                .then(response => {
                    console.log('View modal response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('View modal data:', data);
                    if (data.success && data.company) {
                        const company = data.company;
                        
                        // Map labels
                        const industryMap = <?php echo json_encode($industryTypes); ?>;
                        const sizeMap = <?php echo json_encode($companySizes); ?>;
                        
                        const industryLabel = industryMap[company.industry_type] || company.industry_type || '-';
                        const sizeLabel = sizeMap[company.company_size] || company.company_size || '-';

                        let html = `
                            <!-- Section 1: Company Information -->
                            <div class="info-section">
                                <h6 class="section-title"><i class="fas fa-building"></i> ข้อมูลบริษัท</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="info-item"><strong><i class="fas fa-tag"></i> ชื่อบริษัท (ไทย):</strong> ${company.company_name || '-'}</p>
                                        <p class="info-item"><strong><i class="fas fa-globe"></i> ชื่อบริษัท (อังกฤษ):</strong> ${company.company_name_en || '-'}</p>
                                        <p class="info-item"><strong><i class="fas fa-id-card"></i> เลขทะเบียน:</strong> ${company.tax_id || '-'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <strong><i class="fas fa-industry"></i> อุตสาหกรรม:</strong> 
                                            <div class="mt-1 d-flex flex-wrap gap-1">
                                                ${(company.industry_type || '').split('|').filter(s => s.trim()).map(ind => {
                                                    const short = ind.trim().split(' (')[0];
                                                    return `<span class="badge px-2" style="font-size: 0.72rem; background: linear-gradient(135deg, #eef2ff, #e0e7ff); color: #4338ca; border: 1px solid #c7d2fe; border-radius: 6px;" title="${ind.trim()}">${short}</span>`;
                                                }).join('') || '<span class="text-muted">-</span>'}
                                            </div>
                                        </div>
                                        <p class="info-item mt-2"><strong><i class="fas fa-chart-bar"></i> ขนาด:</strong> ${sizeLabel}</p>
                                        <p class="info-item"><strong><i class="fas fa-users"></i> จำนวนพนักงาน:</strong> ${company.employee_count || '-'} คน</p>
                                        <p class="info-item"><strong><i class="fas fa-calendar-alt"></i> ปีที่ก่อตั้ง:</strong> ${company.established_year || '-'}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Contact Information -->
                            <div class="info-section">
                                <h6 class="section-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้ติดต่อ</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="info-item"><strong><i class="fas fa-user"></i> ชื่อผู้ติดต่อ:</strong> ${company.contact_name || '-'}</p>
                                        <p class="info-item"><strong><i class="fas fa-briefcase"></i> ตำแหน่ง:</strong> ${company.contact_position || '-'}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="info-item"><strong><i class="fas fa-envelope"></i> อีเมล:</strong> ${company.contact_email ? '<a href="mailto:' + company.contact_email + '">' + company.contact_email + '</a>' : '-'}</p>
                                        <p class="info-item"><strong><i class="fas fa-mobile-alt"></i> โทรศัพท์:</strong> ${company.contact_phone || '-'}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Address & Location -->
                            <div class="info-section">
                                <h6 class="section-title"><i class="fas fa-map-marker-alt"></i> ที่อยู่และสถานที่ตั้ง</h6>
                                <p class="info-item"><strong><i class="fas fa-map"></i> ที่อยู่:</strong> ${company.address || '-'}</p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="info-item"><strong>จังหวัด:</strong> ${company.province || '-'}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="info-item"><strong>อำเภอ/เขต:</strong> ${company.district || '-'}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="info-item"><strong>รหัสไปรษณีย์:</strong> ${company.postal_code || '-'}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 4: Additional Information -->
                            <div class="info-section">
                                <h6 class="section-title"><i class="fas fa-info-circle"></i> ข้อมูลเพิ่มเติม</h6>
                                <div class="row">
                                    <div class="col-md-4">
                                        <p class="info-item"><strong><i class="fas fa-phone"></i> โทรศัพท์บริษัท:</strong> ${company.phone || '-'}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="info-item"><strong><i class="fas fa-link"></i> เว็บไซต์:</strong> ${company.website ? '<a href="' + company.website + '" target="_blank">' + company.website + '</a>' : '-'}</p>
                                    </div>
                                    <div class="col-md-4">
                                        <p class="info-item"><strong><i class="fas fa-fax"></i> แฟกซ์:</strong> ${company.fax || '-'}</p>
                                    </div>
                                </div>
                                ${company.description ? '<div class="mt-3"><p class="info-item"><strong><i class="fas fa-file-alt"></i> คำอธิบาย:</strong></p><p class="description-text">' + company.description + '</p></div>' : ''}
                            </div>
                        `;
                        document.getElementById('viewModalBody').innerHTML = html;
                        document.getElementById('viewModalOverlay').classList.add('active');
                    } else {
                        alert('ไม่พบข้อมูลสถานประกอบการ');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                });
        }

        function closeViewModal() {
            document.getElementById('viewModalOverlay').classList.remove('active');
        }

        function confirmDelete(companyId, companyName) {
            // Close any open ms-dropdowns first
            document.querySelectorAll('.ms-container.open').forEach(c => msToggle(c.id, true));
            document.getElementById('deleteCompanyId').value = companyId;
            document.getElementById('deleteCompanyName').textContent = companyName;
            document.getElementById('deleteModalOverlay').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.remove('active');
        }

        function openDeleteModal(companyId) {
            // Fetch company name for the confirmation dialog
            fetch(`${window.BASE_URL || ''}/api/get_company.php?id=${companyId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.company) {
                        confirmDelete(companyId, data.company.company_name);
                    } else {
                        confirmDelete(companyId, 'สถานประกอบการ #' + companyId);
                    }
                })
                .catch(() => {
                    confirmDelete(companyId, 'สถานประกอบการ #' + companyId);
                });
        }

        function confirmRestore(companyId, companyName) {
            document.getElementById('restoreCompanyId').value = companyId;
            document.getElementById('restoreCompanyName').textContent = companyName;
            document.getElementById('restoreModalOverlay').classList.add('active');
        }

        function closeRestoreModal() {
            document.getElementById('restoreModalOverlay').classList.remove('active');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.btn-group')) {
                const dropdowns = document.querySelectorAll('.dropdown-menu');
                dropdowns.forEach(dropdown => dropdown.classList.remove('show'));
            }
        });

        // Close modals when clicking overlay
        document.getElementById('createModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCreateModal();
            }
        });

        document.getElementById('editModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        document.getElementById('viewModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });

        document.getElementById('deleteModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        document.getElementById('restoreModalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRestoreModal();
            }
        });

        // --- Live Search & Filter Implementation (Dropdown) ---
        const searchInput = document.getElementById('search');
        const industrySelect = document.getElementById('industry');
        const sizeSelect = document.getElementById('size');
        const statusSelect = document.getElementById('status');
        const tableBody = document.querySelector('.table tbody');
        const rows = Array.from(document.querySelectorAll('.company-row'));
        const noDataRow = document.querySelector('tr td[colspan="6"]')?.parentElement;
        const headers = document.querySelectorAll('th.sortable');
        const visibleCountEl = document.getElementById('visibleCount');

        let currentSort = { field: 'date', order: 'desc' };

        // Calculate size from employee count
        function getSizeFromEmployeeCount(count) {
            if (count > 500) return 'large';
            if (count >= 200) return 'medium';
            return 'small';
        }

        function updateTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const industry = industrySelect.value;
            const size = sizeSelect.value;
            const status = statusSelect.value;

            let visibleCount = 0;

            // 1. Filtering
            rows.forEach(row => {
                const name = row.dataset.name.toLowerCase();
                const contact = row.dataset.contact.toLowerCase();
                const rowIndustry = row.dataset.industry;
                const empCount = parseInt(row.dataset.employees || 0);
                const rowSize = getSizeFromEmployeeCount(empCount);
                const rowStatus = row.dataset.status;

                const matchSearch = name.includes(searchTerm) || contact.includes(searchTerm);
                const matchIndustry = !industry || rowIndustry.includes(industry);
                const matchSize = !size || rowSize === size;
                const matchStatus = status === 'all' || (status === '' ? rowStatus === '1' : rowStatus === status);

                if (matchSearch && matchIndustry && matchSize && matchStatus) {
                    row.classList.remove('hidden');
                    visibleCount++;
                } else {
                    row.classList.add('hidden');
                }
            });

            // Update visible count
            if (visibleCountEl) {
                visibleCountEl.textContent = visibleCount;
            }

            // Show/Hide "No Data" row
            if (noDataRow) {
                noDataRow.style.display = (visibleCount === 0) ? '' : 'none';
            }

            // 2. Sorting
            const sortedRows = rows.slice().sort((a, b) => {
                let valA, valB;
                const field = currentSort.field;
                
                if (field === 'date') {
                    valA = parseInt(a.dataset.date);
                    valB = parseInt(b.dataset.date);
                } else if (field === 'size') {
                    // Sort by employee count for size
                    valA = parseInt(a.dataset.employees || 0);
                    valB = parseInt(b.dataset.employees || 0);
                } else {
                    valA = a.dataset[field] || '';
                    valB = b.dataset[field] || '';
                }

                let comparison = 0;
                if (typeof valA === 'number') {
                    comparison = valA - valB;
                } else {
                    comparison = valA.localeCompare(valB, 'th');
                }

                return currentSort.order === 'asc' ? comparison : -comparison;
            });

            // Re-append sorted rows to DOM
            sortedRows.forEach(row => tableBody.appendChild(row));
        }

        // --- Header Sorting Logic ---
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const field = header.dataset.sort;
                
                // Toggle order or change field
                if (currentSort.field === field) {
                    currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.field = field;
                    currentSort.order = 'asc';
                }

                // Update UI Header icons
                headers.forEach(h => {
                    h.classList.remove('active', 'asc', 'desc');
                    h.querySelector('i').className = 'fas fa-sort';
                });
                header.classList.add('active', currentSort.order);
                header.querySelector('i').className = `fas fa-sort-${currentSort.order === 'asc' ? 'up' : 'down'}`;

                updateTable();
            });
        });

        // Debounce search for performance
        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(updateTable, 150);
        });

        // Dropdown change handlers
        industrySelect.addEventListener('change', updateTable);
        sizeSelect.addEventListener('change', updateTable);
        statusSelect.addEventListener('change', updateTable);

        // Prevent form submission since it's now live
        document.querySelector('.filter-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            updateTable();
        });

        // Initialize table state
        updateTable();

        // --- Column Visibility Logic ---
        const columnToggleBtn = document.getElementById('columnToggleBtn');
        const columnToggleMenu = document.getElementById('columnToggleMenu');
        const columnCheckboxes = document.querySelectorAll('.column-toggle-item input');

        if (columnToggleBtn) {
            columnToggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (columnToggleMenu) columnToggleMenu.classList.toggle('show');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (columnToggleMenu && columnToggleBtn && !columnToggleMenu.contains(e.target) && e.target !== columnToggleBtn) {
                columnToggleMenu.classList.remove('show');
            }
        });

        columnCheckboxes.forEach(cb => {
            const toggleCol = () => {
                const colId = cb.dataset.column;
                const isVisible = cb.checked;
                document.querySelectorAll(`[data-col-id="${colId}"]`).forEach(el => {
                    el.style.display = isVisible ? '' : 'none';
                });
            };

            cb.addEventListener('change', toggleCol);
            
            // Initial state check
            if (!cb.checked) toggleCol();
        });

        // --- Improved Dropdown Logic for Actions ---
        window.toggleDropdown = function(id) {
            const menu = document.getElementById(`dropdown-${id}`);
            const allMenus = document.querySelectorAll('.dropdown-menu-list');
            
            allMenus.forEach(m => {
                if (m.id !== `dropdown-${id}`) m.classList.remove('show');
            });
            
            menu.classList.toggle('show');
        };

        // Close all dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.btn-group')) {
                document.querySelectorAll('.dropdown-menu-list').forEach(m => m.classList.remove('show'));
            }
        });

        // Action functions
        window.viewCompany = function(id) {
            openViewModal(id);
            document.querySelectorAll('.dropdown-menu-list').forEach(m => m.classList.remove('show'));
        };

        window.editCompany = function(id) {
            openEditModal(id);
            document.querySelectorAll('.dropdown-menu-list').forEach(m => m.classList.remove('show'));
        };

        window.deleteCompany = function(id) {
            openDeleteModal(id);
            document.querySelectorAll('.dropdown-menu-list').forEach(m => m.classList.remove('show'));
        };

        // --- Star/Favorite Toggle ---
        window.toggleStar = function(id) {
            const btn = event.target.closest('.btn-star');
            const icon = btn.querySelector('i');
            
            if (btn.classList.contains('starred')) {
                btn.classList.remove('starred');
                icon.classList.remove('fas');
                icon.classList.add('far');
                // Optional: Save to localStorage or send to server
                localStorage.removeItem(`company_star_${id}`);
            } else {
                btn.classList.add('starred');
                icon.classList.remove('far');
                icon.classList.add('fas');
                // Optional: Save to localStorage or send to server
                localStorage.setItem(`company_star_${id}`, 'true');
            }
        };

        // Load starred states on page load
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.btn-star').forEach(btn => {
                const companyId = btn.getAttribute('onclick').match(/\d+/)[0];
                if (localStorage.getItem(`company_star_${companyId}`) === 'true') {
                    btn.classList.add('starred');
                    const icon = btn.querySelector('i');
                    icon.classList.remove('far');
                    icon.classList.add('fas');
                }
            });
        });
    </script>
</body>
</html>