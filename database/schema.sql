-- ============================================
-- HICM V2025 Assessment System - Database Schema
-- ระบบแบบประเมินสถานประกอบการ HICM V2025
-- ============================================

-- Create Database
CREATE DATABASE IF NOT EXISTS hicm_v2025 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE hicm_v2025;

-- ============================================
-- Table: users
-- ผู้ใช้งานระบบ
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'auditor', 'company', 'ceo') NOT NULL DEFAULT 'company',
    phone VARCHAR(20),
    avatar VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: companies
-- ข้อมูลสถานประกอบการ
-- ============================================
CREATE TABLE companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    company_name_en VARCHAR(255),
    tax_id VARCHAR(20),
    address TEXT,
    province VARCHAR(100),
    district VARCHAR(100),
    postal_code VARCHAR(10),
    phone VARCHAR(20),
    fax VARCHAR(20),
    website VARCHAR(255),
    industry_type TEXT,
    company_size ENUM('small', 'medium', 'large') NOT NULL,
    employee_count INT,
    established_year INT,
    contact_name VARCHAR(255),
    contact_position VARCHAR(100),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(20),
    logo VARCHAR(255),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_industry (industry_type(255)),
    INDEX idx_company_size (company_size),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: assessment_periods
-- รอบการประเมิน
-- ============================================
CREATE TABLE assessment_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    submission_deadline DATE,
    evaluation_start_date DATE,
    evaluation_end_date DATE,
    announcement_date DATETIME,
    status ENUM('draft', 'open', 'closed', 'evaluating', 'completed') DEFAULT 'draft',
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_year (year),
    INDEX idx_status (status),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: pillars
-- 4 Pillars ของ HICM
-- ============================================
CREATE TABLE pillars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name_th VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description TEXT,
    weight INT NOT NULL,
    indicators_count INT NOT NULL DEFAULT 15,
    color VARCHAR(7) DEFAULT '#3B82F6',
    icon VARCHAR(50),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: indicators
-- ตัวชี้วัดทั้ง 60 ข้อ
-- ============================================
CREATE TABLE indicators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pillar_id INT NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    name_th VARCHAR(255) NOT NULL,
    name_en VARCHAR(255),
    description TEXT,
    criteria_0 TEXT,
    criteria_025 TEXT,
    criteria_05 TEXT,
    criteria_075 TEXT,
    criteria_1 TEXT,
    max_score DECIMAL(3,2) DEFAULT 1.00,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pillar_id (pillar_id),
    INDEX idx_code (code),
    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (pillar_id) REFERENCES pillars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: assessments
-- การประเมินของแต่ละบริษัทในแต่ละรอบ
-- ============================================
CREATE TABLE assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    period_id INT NOT NULL,
    status ENUM('draft', 'submitted', 'under_review', 'evaluated', 'completed') DEFAULT 'draft',
    self_total_score DECIMAL(10,2) DEFAULT 0,
    auditor_total_score DECIMAL(10,2) DEFAULT 0,
    final_score DECIMAL(10,2) DEFAULT 0,
    hicm_level INT DEFAULT 1,
    submitted_at DATETIME,
    evaluated_at DATETIME,
    completed_at DATETIME,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_id (company_id),
    INDEX idx_period_id (period_id),
    INDEX idx_status (status),
    INDEX idx_hicm_level (hicm_level),
    UNIQUE KEY unique_company_period (company_id, period_id),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES assessment_periods(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: assessment_scores
-- คะแนนรายตัวชี้วัด
-- ============================================
CREATE TABLE assessment_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    indicator_id INT NOT NULL,
    self_score DECIMAL(3,2) DEFAULT 0,
    self_evidence TEXT,
    self_attachment_count INT DEFAULT 0,
    auditor_score DECIMAL(3,2) DEFAULT NULL,
    auditor_comment TEXT,
    auditor_id INT,
    evaluated_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_indicator_id (indicator_id),
    INDEX idx_auditor_id (auditor_id),
    UNIQUE KEY unique_assessment_indicator (assessment_id, indicator_id),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (indicator_id) REFERENCES indicators(id) ON DELETE CASCADE,
    FOREIGN KEY (auditor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: attachments
-- ไฟล์แนบประกอบการประเมิน
-- ============================================
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_score_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_by INT NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assessment_score_id (assessment_score_id),
    INDEX idx_uploaded_by (uploaded_by),
    FOREIGN KEY (assessment_score_id) REFERENCES assessment_scores(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: assessment_logs
-- ประวัติการทำงานของระบบประเมิน
-- ============================================
CREATE TABLE assessment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: development_plans
-- แผนพัฒนาของสถานประกอบการ
-- ============================================
CREATE TABLE development_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    pillar_id INT NOT NULL,
    strength_points TEXT,
    improvement_points TEXT,
    action_plan TEXT,
    responsible_person VARCHAR(255),
    timeline VARCHAR(100),
    budget DECIMAL(15,2),
    priority ENUM('high', 'medium', 'low') DEFAULT 'medium',
    status ENUM('planned', 'in_progress', 'completed', 'cancelled') DEFAULT 'planned',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_assessment_id (assessment_id),
    INDEX idx_pillar_id (pillar_id),
    INDEX idx_status (status),
    FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE,
    FOREIGN KEY (pillar_id) REFERENCES pillars(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: notifications
-- การแจ้งเตือนระบบ
-- ============================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    link VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: settings
-- การตั้งค่าระบบ
-- ============================================
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Insert Default Data
-- ============================================

-- Insert Admin User (password: admin123)
INSERT INTO users (email, password_hash, name, role, is_active) VALUES
('admin@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ดูแลระบบ', 'admin', TRUE);

-- Insert Pillars
INSERT INTO pillars (code, name_th, name_en, description, weight, indicators_count, color, icon, display_order) VALUES
('H1', 'การส่งเสริมสุขภาพ', 'Health Promotion', 'มุ่งเน้นการสร้างเสริมสุขภาพแบบองค์รวม ครอบคลุมสุขภาพกาย จิต สังคม และปัญญา', 300, 15, '#10B981', 'heart-pulse', 1),
('I2', 'ความปลอดภัยและสิ่งแวดล้อม', 'Industrial Safety & Environment', 'มุ่งเน้นการจัดการความปลอดภัยอาชีวอนามัยและสิ่งแวดล้อมในการทำงาน', 300, 15, '#3B82F6', 'shield-check', 2),
('C3', 'การมีส่วนร่วมกับชุมชน', 'Community Engagement', 'มุ่งเน้นการสร้างความสัมพันธ์เชิงบวกระหว่างสถานประกอบการกับชุมชนโดยรอบ', 200, 15, '#F59E0B', 'users', 3),
('M4', 'การบริหารจัดการและความยั่งยืน', 'Management & Sustainability', 'มุ่งเน้นการบริหารจัดการองค์กรแบบบูรณาการที่เชื่อมโยงกับกลยุทธ์ระยะยาว', 200, 15, '#8B5CF6', 'chart-bar', 4);

-- Insert Indicators for H1 (Health Promotion)
INSERT INTO indicators (pillar_id, code, name_th, name_en, description, criteria_0, criteria_025, criteria_05, criteria_075, criteria_1, display_order) VALUES
(1, 'H1.1', 'นโยบายสุขภาวะองค์กร', 'Organizational Health Policy', 'สถานประกอบการมีนโยบายสุขภาวะที่ชัดเจน เป็นลายลักษณ์อักษร ประกาศให้พนักงานทราบทั่วกัน', 
'ไม่มีนโยบายสุขภาวะเป็นลายลักษณ์อักษร',
'มีนโยบายสุขภาวะเป็นลายลักษณ์อักษร แต่ยังไม่ประกาศอย่างเป็นทางการ',
'มีนโยบายสุขภาวะที่ประกาศอย่างเป็นทางการ และแจ้งให้พนักงานทราบ',
'มีนโยบายสุขภาวะที่บูรณาการกับยุทธศาสตร์องค์กร มีการทบทวนเป็นประจำ',
'มีนโยบายสุขภาวะที่ครอบคลุม บูรณาการกับยุทธศาสตร์ มีการทบทวนและปรับปรุงต่อเนื่อง มีการวัดผลสัมฤทธิ์', 1),

(1, 'H1.2', 'แผนปฏิบัติการสุขภาวะประจำปี', 'Annual Health Action Plan', 'สถานประกอบการมีแผนปฏิบัติการส่งเสริมสุขภาพประจำปีที่ชัดเจน มีกิจกรรมครอบคลุม',
'ไม่มีแผนปฏิบัติการด้านสุขภาวะ',
'มีแผนปฏิบัติการเบื้องต้น แต่ยังไม่มีรายละเอียดครบถ้วน',
'มีแผนปฏิบัติการที่ชัดเจน มีงบประมาณและผู้รับผิดชอบ',
'มีแผนปฏิบัติการครอบคลุมทุกมิติของสุขภาวะ มีการติดตามผลเป็นระยะ',
'มีแผนปฏิบัติการที่บูรณาการและครบวงจร มีการประเมินผลและปรับปรุงอย่างต่อเนื่อง', 2),

(1, 'H1.3', 'ระบบข้อมูลสุขภาพพนักงาน', 'Employee Health Information System', 'สถานประกอบการมีระบบฐานข้อมูลสุขภาพพนักงานที่ครบถ้วน ปลอดภัย สามารถวิเคราะห์และนำไปใช้',
'ไม่มีระบบข้อมูลสุขภาพ หรือมีแต่ไม่เป็นระบบ',
'มีการเก็บข้อมูลสุขภาพพื้นฐาน แต่ยังไม่เป็นระบบ',
'มีระบบฐานข้อมูลสุขภาพที่เป็นระบบ มีการรักษาความลับ',
'มีระบบข้อมูลที่สามารถวิเคราะห์และออกรายงานได้ มีการอัพเดทสม่ำเสมอ',
'มีระบบข้อมูลที่ทันสมัย สามารถบูรณาการกับระบบอื่น ใช้ในการตัดสินใจเชิงนโยบาย', 3),

(1, 'H1.4', 'การตรวจสุขภาพและติดตามผล', 'Health Examination and Follow-up', 'สถานประกอบการจัดให้มีการตรวจสุขภาพประจำปีครบทุกกลุ่มเสี่ยง มีการคัดกรอง NCDs',
'ไม่มีการตรวจสุขภาพพนักงาน',
'มีการตรวจสุขภาพพนักงานบางส่วน แต่ยังไม่ครอบคลุม',
'มีการตรวจสุขภาพครบทุกกลุ่มตามกฎหมาย มีรายงานผล',
'มีการตรวจสุขภาพครอบคลุมกลุ่มเสี่ยง มีการติดตามผลผู้ป่วยและผู้เสี่ยง',
'มีการตรวจสุขภาพครบถ้วน มีระบบติดตามผลต่อเนื่อง มีการวิเคราะห์และป้องกันเชิงรุก', 4),

(1, 'H1.5', 'บริการให้คำปรึกษาและ EAP', 'Employee Assistance Program', 'สถานประกอบการจัดให้มีบริการให้คำปรึกษาด้านสุขภาพจิต ปัญหาส่วนตัว และครอบครัว',
'ไม่มีบริการให้คำปรึกษา',
'มีช่องทางให้คำปรึกษาเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีระบบให้คำปรึกษา มีช่องทางที่ชัดเจน',
'มี EAP ที่ครบครัน มีผู้เชี่ยวชาญ มีการประชาสัมพันธ์',
'มี EAP ครบวงจร มีการประเมินผลและพัฒนาอย่างต่อเนื่อง มีผลลัพธ์ที่ดี', 5),

(1, 'H1.6', 'โครงการป้องกัน NCDs', 'Non-Communicable Diseases Prevention', 'สถานประกอบการมีโครงการป้องกันและควบคุมโรคไม่ติดต่อเรื้อรัง (NCDs) อย่างครอบคลุม',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 6),

(1, 'H1.7', 'การประเมินสุขภาพจิตและปัจจัยเสี่ยงทางจิตสังคม', 'Mental Health and Psychosocial Risk Assessment', 'สถานประกอบการมีการประเมินสุขภาพจิตและปัจจัยเสี่ยงทางจิตสังคมในการทำงาน',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 7),

(1, 'H1.8', 'โครงการเลิกบุหรี่และแอลกอฮอล์', 'Tobacco and Alcohol Cessation Program', 'สถานประกอบการมีนโยบายปลอดบุหรี่และควบคุมการดื่มแอลกอฮอล์ มีโครงการช่วยเหลือพนักงาน',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 8),

(1, 'H1.9', 'กิจกรรมส่งเสริมสุขภาพ 4 มิติ', 'Holistic Health Promotion Activities', 'สถานประกอบการจัดกิจกรรมส่งเสริมสุขภาพครอบคลุม 4 มิติ',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 9),

(1, 'H1.10', 'แกนนำสุขภาพองค์กร', 'Health Champions Network', 'สถานประกอบการแต่งตั้งและอบรมแกนนำสุขภาพ (Health Champions)',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 10),

(1, 'H1.11', 'โภชนาการในที่ทำงาน', 'Nutrition at Workplace', 'สถานประกอบการจัดให้มีอาหารที่มีคุณค่าทางโภชนาการในโรงอาหารหรือร้านอาหาร',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 11),

(1, 'H1.12', 'การออกกำลังกายในองค์กร', 'Physical Activity Program', 'สถานประกอบการส่งเสริมให้พนักงานออกกำลังกายอย่างสม่ำเสมอ',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 12),

(1, 'H1.13', 'สิทธิประโยชน์ด้านสุขภาพ', 'Health Benefits and Welfare', 'สถานประกอบการจัดสวัสดิการและสิทธิประโยชน์ด้านสุขภาพให้พนักงานอย่างครอบคลุม',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 13),

(1, 'H1.14', 'การสื่อสารสุขภาพภายในองค์กร', 'Internal Health Communication', 'สถานประกอบการมีช่องทางและกลไกการสื่อสารด้านสุขภาพที่หลากหลายและมีประสิทธิภาพ',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 14),

(1, 'H1.15', 'งานวิจัยและประเมินสุขภาวะองค์กร', 'Organizational Health Research', 'สถานประกอบการสนับสนุนการทำวิจัยหรือร่วมมือกับหน่วยงานวิชาการ',
'ไม่มีการดำเนินงาน',
'มีการดำเนินงานเบื้องต้น แต่ยังไม่เป็นระบบ',
'มีการดำเนินงานที่เป็นระบบ มีหลักฐานประกอบ',
'มีการดำเนินงานครอบคลุม มีระบบติดตามและประเมินผล',
'มีการดำเนินงานครบถ้วนและมีประสิทธิภาพสูง มีผลลัพธ์ที่ดีและยั่งยืน', 15);

-- Insert Settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('app_name', 'HICM V2025 Assessment System', 'string', 'ชื่อระบบ'),
('app_version', '1.0.0', 'string', 'เวอร์ชั่นระบบ'),
('max_upload_size', '10485760', 'integer', 'ขนาดไฟล์สูงสุด (bytes)'),
('allowed_file_types', '["jpg","jpeg","png","gif","pdf","doc","docx","xls","xlsx"]', 'json', 'ประเภทไฟล์ที่อนุญาต'),
('assessment_open', 'true', 'boolean', 'เปิดรับการประเมิน'),
('current_period_year', YEAR(CURDATE()), 'integer', 'ปีการประเมินปัจจุบัน');
