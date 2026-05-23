# HICM V2025 Assessment System

ระบบแบบประเมินสถานประกอบการตามเกณฑ์ HICM V2025 (Health Industrial Community Model)

## คุณสมบัติหลัก

### 1. ระบบจัดการผู้ใช้ (User Management)
- **ผู้ดูแลระบบ (Admin)**: จัดการบัญชีผู้ใช้ ตั้งค่าตัวชี้วัด รอบการประเมิน
- **กรรมการ (Auditor)**: ตรวจสอบข้อมูล ให้คะแนน ประเมินผล
- **บริษัท (Company)**: ทำการประเมินตนเอง แนบไฟล์หลักฐาน ดูคะแนน

### 2. ระบบแบบประเมิน (Assessment Module)
- รองรับ 4 Pillars (H1, I2, C3, M4) รวม 60 ตัวชี้วัด
- ระบบคะแนนแบบก้าวหน้า (0, 0.25, 0.5, 0.75, 1.0)
- คำนวณคะแนน Real-time
- ระบบแนบไฟล์หลักฐาน (รูปภาพ, PDF, เอกสาร)

### 3. ระบบรายงาน (Reporting)
- Dashboard แสดงผลคะแนนแบบ Real-time
- กราฟ Radar Chart แสดงความสมดุล 4 Pillars
- Export ข้อมูลเป็น PDF และ Excel

## โครงสร้างระบบ

```
hicm-v2025/
├── assets/
│   ├── css/           # ไฟล์ CSS
│   ├── js/            # ไฟล์ JavaScript
│   ├── images/        # รูปภาพ
│   └── uploads/       # ไฟล์ที่อัปโหลด
├── config/
│   ├── config.php     # การตั้งค่าระบบ
│   └── database.php   # การเชื่อมต่อฐานข้อมูล
├── includes/
│   ├── auth.php       # ฟังก์ชันการเข้าสู่ระบบ
│   ├── assessment.php # ฟังก์ชันการประเมิน
│   ├── navbar.php     # เมนูนำทาง
│   └── sidebar.php    # เมนูข้าง
├── pages/
│   ├── login.php      # หน้าเข้าสู่ระบบ
│   ├── dashboard.php  # หน้าแดชบอร์ด
│   ├── assessment-form.php  # แบบฟอร์มประเมิน
│   └── ...
├── api/
│   ├── upload.php     # API อัปโหลดไฟล์
│   └── ...
├── database/
│   └── schema.sql     # โครงสร้างฐานข้อมูล
└── index.php          # หน้าแรก
```

## การติดตั้ง

### 1. ความต้องการของระบบ
- PHP 7.4 หรือสูงกว่า
- MySQL 5.7 หรือสูงกว่า
- Apache/Nginx
- XAMPP (สำหรับทดสอบในเครื่อง)

### 2. ขั้นตอนการติดตั้ง

#### 2.1 ติดตั้งบน XAMPP

1. คัดลอกโฟลเดอร์ `hicm-v2025` ไปยัง `C:\xampp\htdocs\`

2. สร้างฐานข้อมูล:
   - เปิด phpMyAdmin (http://localhost/phpmyadmin)
   - สร้างฐานข้อมูลชื่อ `hicm_v2025`
   - นำเข้าไฟล์ `database/schema.sql`

3. แก้ไขการตั้งค่าฐานข้อมูล:
   - เปิดไฟล์ `config/database.php`
   - แก้ไขค่าตามการตั้งค่าของคุณ:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USERNAME', 'root');
     define('DB_PASSWORD', '');
     define('DB_NAME', 'hicm_v2025');
     ```

4. สร้างโฟลเดอร์สำหรับอัปโหลด:
   ```
   mkdir assets/uploads
   chmod 755 assets/uploads
   ```

5. เข้าใช้งานระบบ:
   - URL: http://localhost/hicm-v2025
   - อีเมล: admin@hicm.gov.th
   - รหัสผ่าน: admin123

### 3. การตั้งค่าเพิ่มเติม

#### 3.1 การตั้งค่า PHP (php.ini)
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

#### 3.2 การตั้งค่า Apache (.htaccess)
ไฟล์ `.htaccess` มีการตั้งค่าพื้นฐานแล้ว สามารถปรับแต่งได้ตามต้องการ

## การใช้งาน

### สำหรับบริษัท (Company User)
1. ลงทะเบียนบัญชีใหม่ หรือเข้าสู่ระบบ
2. กรอกข้อมูลสถานประกอบการ
3. ทำแบบประเมิน 4 Pillars (60 ตัวชี้วัด)
4. แนบไฟล์หลักฐานประกอบ
5. ส่งแบบประเมิน

### สำหรับกรรมการ (Auditor)
1. เข้าสู่ระบบ
2. ดูรายการบริษัทที่ส่งประเมิน
3. ตรวจสอบข้อมูลและไฟล์แนบ
4. ให้คะแนนประเมิน
5. สรุปผลการประเมิน

### สำหรับผู้ดูแลระบบ (Admin)
1. จัดการบัญชีผู้ใช้
2. ตั้งค่าตัวชี้วัด (Pillars/Indicators)
3. จัดการรอบการประเมิน
4. ดูรายงานสรุป
5. ส่งออกข้อมูล

## ระบบคะแนน

### ระดับคะแนน
- **0**: ไม่มีการดำเนินงาน
- **0.25**: เริ่มดำเนินการเบื้องต้น
- **0.5**: มีการดำเนินงานบางส่วนพร้อมหลักฐาน
- **0.75**: ดำเนินงานครอบคลุมและมีการติดตาม
- **1.0**: ดำเนินงานครบถ้วนและยั่งยืน

### น้ำหนักคะแนน
- H1: Health Promotion - 300 คะแนน
- I2: Industrial Safety & Environment - 300 คะแนน
- C3: Community Engagement - 200 คะแนน
- M4: Management & Sustainability - 200 คะแนน
- **รวมทั้งหมด: 1,000 คะแนน**

### ระดับการรับรอง HICM
- **Level 1** (< 600): เริ่มต้น (Emerging)
- **Level 2** (600-699): กำลังพัฒนา (Developing)
- **Level 3** (700-799): พัฒนาดี (Performing)
- **Level 4** (800-899): เป็นเลิศ (Excellence)
- **Level 5** (900-1000): ระดับโลก (World-Class)

## ความปลอดภัย

- การเข้ารหัสรหัสผ่านด้วย bcrypt
- ป้องกัน SQL Injection ด้วย Prepared Statements
- ป้องกัน XSS ด้วย htmlspecialchars
- ตรวจสอบสิทธิ์การเข้าถึง
- จำกัดขนาดไฟล์อัปโหลด
- ตรวจสอบประเภทไฟล์

## การพัฒนา

### โครงสร้างโค้ด
- ใช้ PHP 7.4+ แบบ OOP
- ใช้ PDO สำหรับการเชื่อมต่อฐานข้อมูล
- ใช้ Tailwind CSS สำหรับการออกแบบ
- ใช้ Chart.js สำหรับกราฟ

### การเพิ่มตัวชี้วัดใหม่
1. เพิ่มข้อมูลในตาราง `indicators`
2. ระบุ pillar_id, code, name, criteria ต่างๆ
3. ตั้งค่า display_order ให้ถูกต้อง

## การสนับสนุน

หากมีปัญหาในการใช้งาน กรุณาติดต่อ:
- อีเมล: support@hicm.gov.th
- โทร: 02-XXX-XXXX

## ลิขสิทธิ์

Copyright © 2025 HICM V2025 Assessment System. All rights reserved.

## อัปเดตล่าสุด

- Version 1.0.0 (2025-01-30)
  - ระบบแบบประเมิน HICM V2025 เวอร์ชันแรก
  - รองรับ 4 Pillars 60 ตัวชี้วัด
  - ระบบคะแนนแบบก้าวหน้า
  - ระบบแนบไฟล์หลักฐาน
  - Dashboard และรายงาน
