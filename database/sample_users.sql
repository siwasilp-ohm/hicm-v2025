-- ============================================
-- HICM V2025 - Sample Users Data
-- ตัวอย่างผู้ใช้งานระบบ
-- ============================================

USE hicm_v2025;

-- รหัสผ่านทั้งหมดคือ: 123
-- Hash ของรหัสผ่าน "123" สำหรับ bcrypt
-- $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

-- ============================================
-- 1. ผู้ดูแลระบบ (Admin) - 2 คน
-- ============================================
INSERT INTO users (username, email, password_hash, name, role, phone, is_active, created_at) VALUES
('admin1', 'admin1@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'แอดมิน หนึ่ง', 'admin', '081-111-1111', 1, NOW()),
('admin2', 'admin2@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'แอดมิน สอง', 'admin', '081-222-2222', 1, NOW());

-- ============================================
-- 2. กรรมการ (Auditor) - 3 คน
-- ============================================
INSERT INTO users (username, email, password_hash, name, role, phone, is_active, created_at) VALUES
('aud1', 'aud1@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'กรรมการ หนึ่ง', 'auditor', '082-111-1111', 1, NOW()),
('aud2', 'aud2@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'กรรมการ สอง', 'auditor', '082-222-2222', 1, NOW()),
('aud3', 'aud3@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'กรรมการ สาม', 'auditor', '082-333-3333', 1, NOW());

-- ============================================
-- 3. บริษัท (Company) - 5 บริษัท
-- ============================================
-- สร้าง user สำหรับแต่ละบริษัท
INSERT INTO users (username, email, password_hash, name, role, phone, is_active, created_at) VALUES
('com1', 'contact@company1.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ติดต่อ บริษัทหนึ่ง', 'company', '083-111-1111', 1, NOW()),
('com2', 'contact@company2.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ติดต่อ บริษัทสอง', 'company', '083-222-2222', 1, NOW()),
('com3', 'contact@company3.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ติดต่อ บริษัทสาม', 'company', '083-333-3333', 1, NOW()),
('com4', 'contact@company4.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ติดต่อ บริษัทสี่', 'company', '083-444-4444', 1, NOW()),
('com5', 'contact@company5.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ติดต่อ บริษัทห้า', 'company', '083-555-5555', 1, NOW());

-- สร้างข้อมูลบริษัท
INSERT INTO companies (user_id, company_name, company_name_en, tax_id, address, province, district, postal_code, phone, industry_type, company_size, employee_count, established_year, contact_name, contact_position, contact_email, contact_phone, is_active, created_at) VALUES
-- บริษัทที่ 1
(8, 'บริษัท เอบีซี อินดัสตรี้ จำกัด', 'ABC Industry Co., Ltd.', '1234567890123', '123 ถนนสุขุมวิท', 'กรุงเทพมหานคร', 'คลองเตย', '10110', '02-111-1111', 'อาหารและเครื่องดื่ม', 'large', 350, 1995, 'คุณสมชาย ใจดี', 'ผู้จัดการฝ่ายบุคคล', 'contact@company1.com', '083-111-1111', 1, NOW()),

-- บริษัทที่ 2
(9, 'บริษัท เอ็กซ์วายแซด แมนูแฟคเจอริ่ง จำกัด', 'XYZ Manufacturing Co., Ltd.', '2345678901234', '456 ถนนพระราม 9', 'กรุงเทพมหานคร', 'ห้วยขวาง', '10310', '02-222-2222', 'อิเล็กทรอนิกส์', 'medium', 120, 2005, 'คุณสมหญิง รักดี', 'หัวหน้าฝ่าย CSR', 'contact@company2.com', '083-222-2222', 1, NOW()),

-- บริษัทที่ 3
(10, 'บริษัท ไทยเท็กซ์ไทล์ จำกัด (มหาชน)', 'Thai Textile Public Co., Ltd.', '3456789012345', '789 ถนนเพชรบุรี', 'กรุงเทพมหานคร', 'ราชเทวี', '10400', '02-333-3333', 'สิ่งทอและเครื่องนุ่งห่ม', 'large', 500, 1988, 'คุณประเสริฐ วัฒนา', 'รองผู้จัดการใหญ่', 'contact@company3.com', '083-333-3333', 1, NOW()),

-- บริษัทที่ 4
(11, 'บริษัท กรีนเคมีคอล จำกัด', 'Green Chemical Co., Ltd.', '4567890123456', '321 ถนนวิภาวดีรังสิต', 'กรุงเทพมหานคร', 'จตุจักร', '10900', '02-444-4444', 'เคมีภัณฑ์', 'medium', 80, 2010, 'คุณวิไลวรรณ สุขสันต์', 'ผู้จัดการฝ่ายความปลอดภัย', 'contact@company4.com', '083-444-4444', 1, NOW()),

-- บริษัทที่ 5
(12, 'บริษัท ออโต้พาร์ทส ไทยแลนด์ จำกัด', 'Auto Parts Thailand Co., Ltd.', '5678901234567', '654 ถนนบางนา-ตราด', 'สมุทรปราการ', 'บางพลี', '10540', '02-555-5555', 'ยานยนต์และชิ้นส่วน', 'small', 45, 2015, 'คุณมานพ กล้าหาญ', 'เจ้าของกิจการ', 'contact@company5.com', '083-555-5555', 1, NOW());

-- ============================================
-- 4. CEO - 2 คน (ดูสรุปภาพรวมทั้งหมด)
-- ============================================
INSERT INTO users (username, email, password_hash, name, role, phone, is_active, created_at) VALUES
('ceo1', 'ceo1@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ซีอีโอ หนึ่ง', 'ceo', '084-111-1111', 1, NOW()),
('ceo2', 'ceo2@hicm.gov.th', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ซีอีโอ สอง', 'ceo', '084-222-2222', 1, NOW());

-- ============================================
-- สรุปผู้ใช้งานทั้งหมด
-- ============================================
-- Admin: admin1/123, admin2/123
-- Auditor: aud1/123, aud2/123, aud3/123
-- Company: com1/123, com2/123, com3/123, com4/123, com5/123
-- CEO: ceo1/123, ceo2/123
-- ============================================
