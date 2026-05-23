<?php
/**
 * HICM V2025 Assessment System - Authentication Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Login user
 */
function login($username, $password, $remember = false) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT u.*, c.id as company_id 
            FROM users u 
            LEFT JOIN companies c ON u.id = c.user_id 
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'];
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['login_time'] = time();
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Log login
        logActivity($user['id'], 'login', 'เข้าสู่ระบบ');
        
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            setcookie('remember_token', $token, $expiry, '/', '', false, true);
            
            // Save token to database
            $stmt = $db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
        }
        
        return ['success' => true, 'user' => $user];
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'];
    }
}

/**
 * Logout user
 */
function logout() {
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'logout', 'ออกจากระบบ');
    }
    
    // Clear session
    $_SESSION = [];
    session_destroy();
    
    // Clear remember cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    return true;
}

/**
 * Register new company user
 */
function registerCompany($data) {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'];
        }
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'];
        }
        
        // Create user
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, name, role, phone, created_by) 
            VALUES (?, ?, ?, ?, 'company', ?, ?)
        ");
        $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['contact_name'],
            $data['contact_phone'],
            $data['created_by'] ?? null
        ]);
        $userId = $db->lastInsertId();
        
        // Create company
        $stmt = $db->prepare("
            INSERT INTO companies (
                user_id, company_name, company_name_en, tax_id, address, 
                province, district, postal_code, phone, fax, website,
                industry_type, company_size, employee_count, established_year,
                contact_name, contact_position, contact_email, contact_phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['company_name'],
            $data['company_name_en'] ?? null,
            $data['tax_id'] ?? null,
            $data['address'] ?? null,
            $data['province'] ?? null,
            $data['district'] ?? null,
            $data['postal_code'] ?? null,
            $data['phone'] ?? null,
            $data['fax'] ?? null,
            $data['website'] ?? null,
            $data['industry_type'] ?? null,
            $data['company_size'],
            $data['employee_count'] ?? null,
            $data['established_year'] ?? null,
            $data['contact_name'],
            $data['contact_position'] ?? null,
            $data['email'],
            $data['contact_phone']
        ]);
        
        $companyId = $db->lastInsertId();
        
        $db->commit();
        
        logActivity($userId, 'register', 'ลงทะเบียนบริษัทใหม่: ' . $data['company_name']);
        
        // Auto Smart Match: จับคู่กรรมการอัตโนมัติถ้ามีรอบที่เปิด auto_smart_match
        require_once __DIR__ . '/assessment.php';
        $matchResult = autoSmartMatchNewCompany($companyId);
        
        return [
            'success' => true, 
            'user_id' => $userId,
            'auto_matched' => $matchResult['matched'] ?? false,
            'match_info' => $matchResult
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลงทะเบียน'];
    }
}

/**
 * Create user (for admin)
 */
function createUser($data) {
    $db = getDB();
    
    try {
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'];
        }
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'อีเมลนี้ถูกใช้งานแล้ว'];
        }
        
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $expertise = $data['expertise'] ?? null;
        if (is_array($expertise)) {
            $expertise = implode('|', $expertise);
        }

        $hicmExpertise = $data['hicm_expertise'] ?? null;
        if (is_array($hicmExpertise)) {
            $hicmExpertise = implode('|', $hicmExpertise);
        }

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, name, role, phone, expertise, hicm_expertise, organization_id, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['username'],
            $data['email'],
            $passwordHash,
            $data['name'],
            $data['role'],
            $data['phone'] ?? null,
            $expertise,
            $hicmExpertise,
            $data['organization_id'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);
        
        $userId = $db->lastInsertId();
        
        // Create company record if role is company
        if ($data['role'] === ROLE_COMPANY) {
            $industryType = $data['expertise'] ?? [];
            if (is_array($industryType)) $industryType = implode('|', $industryType);
            
            $companyName = !empty($data['company_name']) ? $data['company_name'] : ('บริษัท ID: ' . $userId);
            
            $stmt = $db->prepare("
                INSERT INTO companies (
                    user_id, company_name, company_name_en, tax_id, address, province, district,
                    postal_code, phone, website, industry_type, company_size, 
                    employee_count, established_year, contact_name, contact_position, 
                    contact_email, contact_phone, description, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $userId,
                $companyName,
                $data['company_name_en'] ?? '',
                $data['tax_id'] ?? '',
                $data['address'] ?? '',
                $data['province'] ?? '',
                $data['district'] ?? '',
                $data['postal_code'] ?? '',
                $data['company_phone'] ?? $data['phone'] ?? '',
                $data['website'] ?? '',
                $industryType,
                $data['company_size'] ?? '',
                intval($data['employee_count'] ?? 0),
                intval($data['established_year'] ?? 0),
                $data['name'],
                $data['contact_position'] ?? '',
                $data['email'],
                $data['phone'] ?? '',
                $data['description'] ?? ''
            ]);
        }

        $db->commit();
        logActivity($userId, 'create_user', 'สร้างผู้ใช้ใหม่: ' . $data['name'] . ' (' . $data['role'] . ')');
        
        return ['success' => true, 'user_id' => $userId];
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Create user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการสร้างผู้ใช้: ' . $e->getMessage()];
    }
}

/**
 * Update user
 */
function updateUser($userId, $data) {
    $db = getDB();
    
    try {
        $fields = [];
        $values = [];
        
        if (isset($data['username'])) {
            // Check if username already exists for another user
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$data['username'], $userId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'];
            }
            $fields[] = "username = ?";
            $values[] = $data['username'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $values[] = $data['email'];
        }
        
        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $values[] = $data['name'];
        }
        
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $values[] = $data['phone'];
        }
        
        if (isset($data['role'])) {
            $fields[] = "role = ?";
            $values[] = $data['role'];
        }
        
        if (isset($data['expertise'])) {
            $expertise = $data['expertise'];
            if (is_array($expertise)) {
                $expertise = implode('|', $expertise);
            }
            $fields[] = "expertise = ?";
            $values[] = $expertise;
        }
        
        if (isset($data['hicm_expertise'])) {
            $hicmExpertise = $data['hicm_expertise'];
            if (is_array($hicmExpertise)) {
                $hicmExpertise = implode('|', $hicmExpertise);
            }
            $fields[] = "hicm_expertise = ?";
            $values[] = $hicmExpertise;
        }
        
        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $values[] = $data['is_active'];
        }
        
        if (array_key_exists('organization_id', $data)) {
            $fields[] = "organization_id = ?";
            $values[] = $data['organization_id'];
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password_hash = ?";
            $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            return ['success' => false, 'message' => 'ไม่มีข้อมูลที่ต้องการอัปเดต'];
        }
        
        $values[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($values);
        
        logActivity($userId, 'update_user', 'อัปเดตข้อมูลผู้ใช้');
        
        // Sync company details if it's a company
        $stmt = $db->prepare("SELECT id FROM companies WHERE user_id = ?");
        $stmt->execute([$userId]);
        $company = $stmt->fetch();
        
        if ($company) {
            $updateFields = [];
            $updateValues = [];
            
            if (isset($data['company_name'])) {
                $updateFields[] = "company_name = ?";
                $updateValues[] = $data['company_name'];
            }
            
            if (isset($data['expertise'])) {
                $expertise = $data['expertise'];
                if (is_array($expertise)) $expertise = implode('|', $expertise);
                $updateFields[] = "industry_type = ?";
                $updateValues[] = $expertise;
            }
            
            if (!empty($updateFields)) {
                $updateValues[] = $company['id'];
                $stmt = $db->prepare("UPDATE companies SET " . implode(', ', $updateFields) . " WHERE id = ?");
                $stmt->execute($updateValues);
            }
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล'];
    }
}

/**
 * Delete user
 */
function deleteUser($userId) {
    $db = getDB();
    
    try {
        // Don't allow deleting own account
        if ($userId == $_SESSION['user_id']) {
            return ['success' => false, 'message' => 'ไม่สามารถลบบัญชีตัวเองได้'];
        }
        
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        logActivity($_SESSION['user_id'], 'delete_user', 'ลบผู้ใช้ ID: ' . $userId);
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการลบผู้ใช้'];
    }
}

/**
 * Change password
 */
function changePassword($userId, $currentPassword, $newPassword) {
    $db = getDB();
    
    try {
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
        }
        
        // Update password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        
        logActivity($userId, 'change_password', 'เปลี่ยนรหัสผ่าน');
        
        return ['success' => true];
        
    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน'];
    }
}

/**
 * Log activity
 */
function logActivity($userId, $action, $description = '', $assessmentId = null) {
    $db = getDB();
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO assessment_logs (user_id, action, description, ip_address, user_agent, assessment_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $action, $description, $ip, $userAgent, $assessmentId]);
        
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT u.*, c.id as company_id, c.company_name 
            FROM users u 
            LEFT JOIN companies c ON u.id = c.user_id 
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Get user error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all users
 */
function getAllUsers($role = null, $search = null) {
    $db = getDB();
    
    try {
        $sql = "
            SELECT u.*, c.company_name, c.industry_type 
            FROM users u 
            LEFT JOIN companies c ON u.id = c.user_id 
            WHERE 1=1
        ";
        $params = [];
        
        if ($role) {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }
        
        if ($search) {
            $sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR c.company_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $sql .= " ORDER BY u.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Get users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has permission
 */
function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $permissions = [
        'admin' => ['view_all', 'edit_all', 'delete_all', 'manage_users', 'manage_settings', 'export_data'],
        'auditor' => ['view_all', 'evaluate', 'export_data'],
        'ceo' => ['view_all', 'export_data'],
        'company' => ['view_own', 'edit_own', 'submit_assessment']
    ];
    
    return in_array($permission, $permissions[$user['role']] ?? []);
}

/**
 * Check for auto-login cookie
 */
function checkAutoLogin() {
    // If already logged in, skip
    if (isLoggedIn()) {
        return;
    }
    
    // Check if cookie exists
    if (!isset($_COOKIE['remember_token'])) {
        return;
    }
    
    $token = $_COOKIE['remember_token'];
    $db = getDB();
    
    try {
        // Find user with this token
        $stmt = $db->prepare("
            SELECT u.*, c.id as company_id 
            FROM users u 
            LEFT JOIN companies c ON u.id = c.user_id 
            WHERE u.remember_token = ? AND u.is_active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['login_time'] = time();
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            logActivity($user['id'], 'auto_login', 'เข้าสู่ระบบอัตโนมัติ');
        }
    } catch (Exception $e) {
        // Silent fail for auto-login
        error_log("Auto-login error: " . $e->getMessage());
    }
}

// Run auto-login check automatically
checkAutoLogin();
?>
