<?php
/**
 * HICM V2025 Assessment System - Company Registration Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(getBaseUrl() . '/pages/dashboard.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $username = sanitizeInput($_POST['username'] ?? '');
    $companyName = sanitizeInput($_POST['company_name'] ?? '');
    $companyNameEn = sanitizeInput($_POST['company_name_en'] ?? '');
    $taxId = sanitizeInput($_POST['tax_id'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $province = sanitizeInput($_POST['province'] ?? '');
    $district = sanitizeInput($_POST['district'] ?? '');
    $postalCode = sanitizeInput($_POST['postal_code'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $industryType = sanitizeInput($_POST['industry_type'] ?? '');
    $companySize = sanitizeInput($_POST['company_size'] ?? '');
    $employeeCount = intval($_POST['employee_count'] ?? 0);
    $establishedYear = intval($_POST['established_year'] ?? 0);
    
    $contactName = sanitizeInput($_POST['contact_name'] ?? '');
    $contactPosition = sanitizeInput($_POST['contact_position'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $contactPhone = sanitizeInput($_POST['contact_phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username)) $errors[] = 'กรุณากรอกชื่อผู้ใช้';
    if (strlen($username) < 3) $errors[] = 'ชื่อผู้ใช้ต้องมีอย่างน้อย 3 ตัวอักษร';
    if (preg_match('/[^a-zA-Z0-9_]/', $username)) $errors[] = 'ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษ ตัวเลข หรือ _ เท่านั้น';
    if (empty($companyName)) $errors[] = 'กรุณากรอกชื่อบริษัท';
    if (empty($contactName)) $errors[] = 'กรุณากรอกชื่อผู้ติดต่อ';
    if (empty($email)) $errors[] = 'กรุณากรอกอีเมล';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
    if (empty($password)) $errors[] = 'กรุณากรอกรหัสผ่าน';
    if (strlen($password) < 6) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    if ($password !== $confirmPassword) $errors[] = 'รหัสผ่านไม่ตรงกัน';
    if (empty($companySize)) $errors[] = 'กรุณาเลือกขนาดบริษัท';
    
    if (empty($errors)) {
        $result = registerCompany([
            'username' => $username,
            'company_name' => $companyName,
            'company_name_en' => $companyNameEn,
            'tax_id' => $taxId,
            'address' => $address,
            'province' => $province,
            'district' => $district,
            'postal_code' => $postalCode,
            'phone' => $phone,
            'industry_type' => $industryType,
            'company_size' => $companySize,
            'employee_count' => $employeeCount,
            'established_year' => $establishedYear,
            'contact_name' => $contactName,
            'contact_position' => $contactPosition,
            'email' => $email,
            'contact_phone' => $contactPhone,
            'password' => $password
        ]);
        
        if ($result['success']) {
            $success = true;
        } else {
            $errors[] = $result['message'];
        }
    }
}

// Industry types
$industries = [
    'อาหารและเครื่องดื่ม' => 'อาหารและเครื่องดื่ม',
    'สิ่งทอและเครื่องนุ่งห่ม' => 'สิ่งทอและเครื่องนุ่งห่ม',
    'อิเล็กทรอนิกส์' => 'อิเล็กทรอนิกส์',
    'ยานยนต์และชิ้นส่วน' => 'ยานยนต์และชิ้นส่วน',
    'เคมีภัณฑ์' => 'เคมีภัณฑ์',
    'ก่อสร้าง' => 'ก่อสร้าง',
    'โลหะและเครื่องจักร' => 'โลหะและเครื่องจักร',
    'พลาสติกและยาง' => 'พลาสติกและยาง',
    'เภสัชกรรม' => 'เภสัชกรรม',
    'อื่นๆ' => 'อื่นๆ'
];

$companySizes = [
    'small' => 'Small (1-50 คน)',
    'medium' => 'Medium (51-200 คน)',
    'large' => 'Large (201+ คน)'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนบริษัท - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
    <style>
        .register-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem 1rem;
        }
        
        .register-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .register-logo svg {
            width: 32px;
            height: 32px;
            color: var(--primary-600);
        }
        
        .register-title {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .register-subtitle {
            opacity: 0.9;
            font-size: var(--font-size-sm);
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .form-section-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-section-title svg {
            color: var(--primary-500);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        @media (min-width: 640px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-label .required {
            color: var(--danger);
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            transition: all var(--transition-fast);
            background: white;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-500);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .password-strength {
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all var(--transition-fast);
        }
        
        .password-strength-bar.weak { width: 33%; background: var(--danger); }
        .password-strength-bar.medium { width: 66%; background: var(--warning); }
        .password-strength-bar.strong { width: 100%; background: var(--success); }
        
        .btn-register {
            background: linear-gradient(135deg, var(--primary-500), var(--primary-600));
            color: white;
            padding: 1rem 2rem;
            font-size: var(--font-size-base);
            font-weight: 600;
            border: none;
            border-radius: var(--radius-lg);
            cursor: pointer;
            width: 100%;
            transition: all var(--transition-base);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }
        
        .success-message {
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success-light);
            color: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }
        
        .success-icon svg {
            width: 40px;
            height: 40px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-100);
        }
    </style>
</head>
<body class="register-page">
    <div class="register-container">
        <div class="register-card animate-scale-in">
            <?php if ($success): ?>
                <div class="success-message">
                    <div class="success-icon animate-bounce">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem;">ลงทะเบียนสำเร็จ!</h2>
                    <p style="color: var(--gray-600); margin-bottom: 2rem;">
                        บัญชีของคุณถูกสร้างเรียบร้อยแล้ว<br>
                        คุณสามารถเข้าสู่ระบบเพื่อเริ่มทำแบบประเมินได้ทันที
                    </p>
                    <a href="login.php" class="btn btn-primary btn-lg" style="display: inline-flex;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
                            <polyline points="10 17 15 12 10 7"/>
                            <line x1="15" y1="12" x2="3" y2="12"/>
                        </svg>
                        เข้าสู่ระบบ
                    </a>
                </div>
            <?php else: ?>
                <div class="register-header">
                    <div class="register-logo">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4"/>
                            <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
                            <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
                            <path d="M12 21c0-1 1-3 3-3s3 2 3 3-1 3-3 3-3-2-3-3"/>
                            <path d="M12 3c0 1-1 3-3 3s-3-2-3-3 1-3 3-3 3 2 3 3"/>
                        </svg>
                    </div>
                    <h1 class="register-title">ลงทะเบียนสถานประกอบการ</h1>
                    <p class="register-subtitle">เข้าร่วมระบบประเมิน HICM V2025</p>
                </div>
                
                <div class="register-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="15" y1="9" x2="9" y2="15"/>
                                        <line x1="9" y1="9" x2="15" y2="15"/>
                                    </svg>
                                    <?php echo $error; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <!-- Account Information -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0110 0v4"/>
                                </svg>
                                ตั้งค่าบัญชีผู้ใช้
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="regUsername">ชื่อผู้ใช้ <span class="required">*</span></label>
                                    <input type="text" name="username" id="regUsername" class="form-input" value="<?php echo $_POST['username'] ?? ''; ?>" 
                                           placeholder="ตัวอักษร a-z, ตัวเลข 0-9, _" 
                                           pattern="[a-zA-Z0-9_]+" minlength="3" maxlength="20" required>
                                    <p class="form-hint">ใช้สำหรับเข้าสู่ระบบ (ภาษาอังกฤษ ตัวเลข _)</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="regEmail">อีเมล <span class="required">*</span></label>
                                    <input type="email" name="email" id="regEmail" class="form-input" value="<?php echo $_POST['email'] ?? ''; ?>" 
                                           placeholder="ใช้สำหรับติดต่อ" required>
                                    <p class="form-hint">ใช้สำหรับติดต่อและรับข่าวสาร</p>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="regPassword">รหัสผ่าน <span class="required">*</span></label>
                                    <input type="password" name="password" id="regPassword" class="form-input" required minlength="6">
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="strengthBar"></div>
                                    </div>
                                    <p class="form-hint">รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร</p>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="regConfirmPassword">ยืนยันรหัสผ่าน <span class="required">*</span></label>
                                    <input type="password" name="confirm_password" id="regConfirmPassword" class="form-input" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Company Information -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                                ข้อมูลสถานประกอบการ
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="regCompanyName">ชื่อบริษัท (ภาษาไทย) <span class="required">*</span></label>
                                    <input type="text" name="company_name" id="regCompanyName" class="form-input" value="<?php echo $_POST['company_name'] ?? ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="regCompanyNameEn">ชื่อบริษัท (ภาษาอังกฤษ)</label>
                                    <input type="text" name="company_name_en" id="regCompanyNameEn" class="form-input" value="<?php echo $_POST['company_name_en'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="regTaxId">เลขประจำตัวผู้เสียภาษี</label>
                                    <input type="text" name="tax_id" id="regTaxId" class="form-input" value="<?php echo $_POST['tax_id'] ?? ''; ?>" maxlength="13">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="regIndustryType">ประเภทอุตสาหกรรม</label>
                                    <select name="industry_type" id="regIndustryType" class="form-select">
                                        <option value="">-- เลือกประเภท --</option>
                                        <?php foreach ($industries as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($_POST['industry_type'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ขนาดบริษัท <span class="required">*</span></label>
                                    <select name="company_size" class="form-select" required>
                                        <option value="">-- เลือกขนาด --</option>
                                        <?php foreach ($companySizes as $key => $label): ?>
                                            <option value="<?php echo $key; ?>" <?php echo ($_POST['company_size'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">จำนวนพนักงาน</label>
                                    <input type="number" name="employee_count" class="form-input" value="<?php echo $_POST['employee_count'] ?? ''; ?>" min="1">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ปีที่ก่อตั้ง</label>
                                    <input type="number" name="established_year" class="form-input" value="<?php echo $_POST['established_year'] ?? ''; ?>" min="1900" max="<?php echo date('Y'); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">โทรศัพท์บริษัท</label>
                                    <input type="tel" name="phone" class="form-input" value="<?php echo $_POST['phone'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ที่อยู่</label>
                                <textarea name="address" class="form-textarea"><?php echo $_POST['address'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">จังหวัด</label>
                                    <input type="text" name="province" class="form-input" value="<?php echo $_POST['province'] ?? ''; ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">อำเภอ/เขต</label>
                                    <input type="text" name="district" class="form-input" value="<?php echo $_POST['district'] ?? ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">รหัสไปรษณีย์</label>
                                <input type="text" name="postal_code" class="form-input" value="<?php echo $_POST['postal_code'] ?? ''; ?>" maxlength="5">
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="form-section">
                            <h3 class="form-section-title">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                ข้อมูลผู้ติดต่อ
                            </h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">ชื่อผู้ติดต่อ <span class="required">*</span></label>
                                    <input type="text" name="contact_name" class="form-input" value="<?php echo $_POST['contact_name'] ?? ''; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ตำแหน่ง</label>
                                    <input type="text" name="contact_position" class="form-input" value="<?php echo $_POST['contact_position'] ?? ''; ?>" placeholder="เช่น ผู้จัดการฝ่ายบุคคล">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">โทรศัพท์ผู้ติดต่อ</label>
                                    <input type="tel" name="contact_phone" class="form-input" value="<?php echo $_POST['contact_phone'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-register">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                                <circle cx="8.5" cy="7" r="4"/>
                                <line x1="20" y1="8" x2="20" y2="14"/>
                                <line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                            ลงทะเบียน
                        </button>
                        
                        <div class="login-link">
                            <p style="color: var(--gray-600);">
                                มีบัญชีอยู่แล้ว? 
                                <a href="login.php" style="color: var(--primary-600); font-weight: 500;">เข้าสู่ระบบ</a>
                            </p>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password strength indicator
        document.getElementById('regPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });
    </script>
</body>
</html>
