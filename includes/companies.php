<?php
/**
 * Company Management Functions
 */

require_once __DIR__ . '/../config/database.php';

function getAllCompanies($filters = []) {
    $db = getDB();
    $sql = "SELECT c.*, u.username, u.is_active, u.created_at, u.avatar as company_owner_avatar 
            FROM companies c 
            JOIN users u ON c.user_id = u.id 
            WHERE 1=1";
    $params = [];

    if (isset($filters['status']) && $filters['status'] !== '') {
        if ($filters['status'] === 'all') {
            // Show all companies, no active filter
        } elseif ($filters['status'] === 'active' || $filters['status'] === '1' || $filters['status'] === 1) {
            $sql .= " AND u.is_active = 1 AND c.is_active = 1";
        } elseif ($filters['status'] === 'inactive' || $filters['status'] === '0' || $filters['status'] === 0) {
            $sql .= " AND (u.is_active = 0 OR c.is_active = 0)";
        }
    } else {
        // Default: show only active companies
        $sql .= " AND u.is_active = 1 AND c.is_active = 1";
    }

    if (!empty($filters['industry'])) {
        $sql .= " AND FIND_IN_SET(?, c.industry_type)";
        $params[] = $filters['industry'];
    }

    if (!empty($filters['size'])) {
        // Filter by employee count: small < 200, medium 200-500, large > 500
        if ($filters['size'] === 'small') {
            $sql .= " AND c.employee_count < 200";
        } elseif ($filters['size'] === 'medium') {
            $sql .= " AND c.employee_count >= 200 AND c.employee_count <= 500";
        } elseif ($filters['size'] === 'large') {
            $sql .= " AND c.employee_count > 500";
        }
    }

    if (!empty($filters['search'])) {
        $search = '%' . $filters['search'] . '%';
        $sql .= " AND (c.company_name LIKE ? OR c.company_name_en LIKE ? OR c.tax_id LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    $sql .= " ORDER BY c.company_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCompanyById($id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, u.username, u.email as contact_email, u.name as contact_name, u.phone as contact_phone, u.is_active, u.avatar as company_owner_avatar 
        FROM companies c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function createCompany($data) {
    $db = getDB();
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว'];
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['contact_email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'อีเมลนี้มีอยู่ในระบบแล้ว'];
    }

    try {
        $db->beginTransaction();

        // 1. Create User
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, password_hash, email, name, role, phone, is_active) 
            VALUES (?, ?, ?, ?, 'company', ?, 1)
        ");
        $stmt->execute([
            $data['username'],
            $passwordHash,
            $data['contact_email'],
            $data['contact_name'],
            $data['contact_phone']
        ]);
        $userId = $db->lastInsertId();

        // 2. Create Company
        $stmt = $db->prepare("
            INSERT INTO companies (
                user_id, company_name, company_name_en, tax_id, address, province, district, 
                postal_code, phone, fax, website, industry_type, company_size, 
                employee_count, established_year, contact_name, contact_position, 
                contact_email, contact_phone, description
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $userId,
            $data['company_name'],
            $data['company_name_en'] ?? '',
            $data['tax_id'] ?? '',
            $data['address'] ?? '',
            $data['province'] ?? '',
            $data['district'] ?? '',
            $data['postal_code'] ?? '',
            $data['phone'] ?? '',
            $data['fax'] ?? '',
            $data['website'] ?? '',
            is_array($data['industry_type'] ?? []) ? implode('|', $data['industry_type'] ?? []) : ($data['industry_type'] ?? ''),
            $data['company_size'] ?? '',
            $data['employee_count'] ?? 0,
            $data['established_year'] ?? 0,
            $data['contact_name'] ?? '',
            $data['contact_position'] ?? '',
            $data['contact_email'] ?? '',
            $data['contact_phone'] ?? '',
            $data['description'] ?? ''
        ]);

        $companyId = $db->lastInsertId();
        
        $db->commit();
        logActivity($_SESSION['user_id'], 'create_company', 'สร้างบริษัท: ' . $data['company_name']);
        
        // Auto Smart Match: จับคู่กรรมการอัตโนมัติถ้ามีรอบที่เปิด auto_smart_match
        require_once __DIR__ . '/assessment.php';
        $matchResult = autoSmartMatchNewCompany($companyId);
        
        return [
            'success' => true, 
            'auto_matched' => $matchResult['matched'] ?? false,
            'match_info' => $matchResult
        ];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function updateCompany($id, $data) {
    $db = getDB();
    
    $company = getCompanyById($id);
    if (!$company) {
        return ['success' => false, 'message' => 'ไม่พบข้อมูลบริษัท'];
    }

    try {
        $db->beginTransaction();

        // 1. Update User (Contact Info)
        $stmt = $db->prepare("
            UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?
        ");
        $stmt->execute([
            $data['contact_name'] ?? $company['contact_name'],
            $data['contact_email'] ?? $company['contact_email'],
            $data['contact_phone'] ?? $company['contact_phone'],
            $company['user_id']
        ]);

        // 2. Update Company
        $stmt = $db->prepare("
            UPDATE companies SET 
                company_name = ?, company_name_en = ?, tax_id = ?, address = ?, 
                province = ?, district = ?, postal_code = ?, phone = ?, fax = ?, 
                website = ?, industry_type = ?, company_size = ?, employee_count = ?, 
                established_year = ?, contact_name = ?, contact_email = ?, contact_phone = ?,
                contact_position = ?, description = ?, latitude = ?, longitude = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['company_name'] ?? $company['company_name'],
            $data['company_name_en'] ?? $company['company_name_en'],
            $data['tax_id'] ?? $company['tax_id'],
            $data['address'] ?? $company['address'],
            $data['province'] ?? $company['province'],
            $data['district'] ?? $company['district'],
            $data['postal_code'] ?? $company['postal_code'],
            $data['phone'] ?? $company['phone'],
            $data['fax'] ?? $company['fax'],
            $data['website'] ?? $company['website'],
            isset($data['industry_type']) ? (is_array($data['industry_type']) ? implode('|', $data['industry_type']) : $data['industry_type']) : $company['industry_type'],
            $data['company_size'] ?? $company['company_size'],
            $data['employee_count'] ?? $company['employee_count'],
            $data['established_year'] ?? $company['established_year'],
            $data['contact_name'] ?? $company['contact_name'],
            $data['contact_email'] ?? $company['contact_email'],
            $data['contact_phone'] ?? $company['contact_phone'],
            $data['contact_position'] ?? $company['contact_position'],
            $data['description'] ?? $company['description'],
            array_key_exists('latitude', $data) ? $data['latitude'] : ($company['latitude'] ?? null),
            array_key_exists('longitude', $data) ? $data['longitude'] : ($company['longitude'] ?? null),
            $id
        ]);

        $db->commit();
        logActivity($_SESSION['user_id'], 'update_company', 'อัปเดตบริษัท: ' . $data['company_name']);
        
        return ['success' => true];

    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function deleteCompany($id) {
    $db = getDB();
    $company = getCompanyById($id);
    
    if (!$company) {
        return ['success' => false, 'message' => 'ไม่พบข้อมูลบริษัท'];
    }

    try {
        $db->beginTransaction();
        
        // Soft delete: deactivate both user and company
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$company['user_id']]);
        
        $stmt = $db->prepare("UPDATE companies SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        logActivity($_SESSION['user_id'], 'delete_company', 'ลบ(ปิดใช้งาน)บริษัท: ' . $company['company_name']);
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function restoreCompany($id) {
    $db = getDB();
    $company = getCompanyById($id);
    
    if (!$company) {
        return ['success' => false, 'message' => 'ไม่พบข้อมูลบริษัท'];
    }

    try {
        $db->beginTransaction();
        
        // Restore: reactivate both user and company
        $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
        $stmt->execute([$company['user_id']]);
        
        $stmt = $db->prepare("UPDATE companies SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        logActivity($_SESSION['user_id'], 'restore_company', 'เปิดใช้งานบริษัท: ' . $company['company_name']);
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function getIndustryTypes() {
    // We can either return a static list or use the AUDITOR_EXPERTISE constant
    // For consistency, let's use the constant if it exists
    if (defined('AUDITOR_EXPERTISE')) {
        $types = [];
        foreach (AUDITOR_EXPERTISE as $exp) {
            $types[$exp] = $exp;
        }
        return $types;
    }
    
    return [
        'manufacturing' => 'การผลิต',
        'service' => 'การบริการ',
        ' trading' => 'การค้า',
        'technology' => 'เทคโนโลยี',
        'agriculture' => 'การเกษตร',
        'steel_parts' => 'ผลิตชิ้นส่วนเหล็ก',
        'automotive' => 'ยานยนต์',
        'flour' => 'แป้ง',
        'food_beverage' => 'อาหารและเครื่องดื่ม',
        'logistics' => 'ขนส่ง',
        'electrical' => 'ไฟฟ้า',
        'chemical' => 'เคมี',
        'steel' => 'เหล็ก',
        'metal' => 'โลหะ',
        'textile_plastic' => 'สิ่งทอและพลาสติก',
        'assembly' => 'ประกอบชิ้นส่วน',
        'automotive_parts' => 'ชิ้นส่วนยานยนต์',
        'other' => 'อื่นๆ'
    ];
}

function getCompanySizes() {
    return [
        'size_0_50' => 'ไม่เกิน 50 คน',
        'size_50_100' => '50-100 คน',
        'size_101_200' => '101-200 คน',
        'size_201_500' => '201-500 คน',
        'size_501_1000' => '501-1,000 คน',
        'size_1000_plus' => '1,000 คน ขึ้นไป'
    ];
}
?>
