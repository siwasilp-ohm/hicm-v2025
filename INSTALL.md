# คู่มือการติดตั้ง HICM V2025 Assessment System

## ความต้องการของระบบ

- PHP 7.4 หรือสูงกว่า
- MySQL 5.7 หรือสูงกว่า หรือ MariaDB 10.2+
- Apache 2.4+ พร้อม mod_rewrite
- XAMPP (สำหรับทดสอบในเครื่อง)

## ขั้นตอนการติดตั้งบน XAMPP

### 1. ติดตั้ง XAMPP
1. ดาวน์โหลด XAMPP จาก https://www.apachefriends.org/
2. ติดตั้ง XAMPP ที่โฟลเดอร์ `C:\xampp`
3. เปิด XAMPP Control Panel
4. Start Apache และ MySQL

### 2. ติดตั้งระบบ HICM V2025

#### 2.1 คัดลอกไฟล์
```
คัดลอกโฟลเดอร์ hicm-v2025 ไปยัง C:\xampp\htdocs\
```

#### 2.2 สร้างฐานข้อมูล
1. เปิดเบราว์เซอร์ไปที่ http://localhost/phpmyadmin
2. คลิก "New" หรือ "สร้างฐานข้อมูลใหม่"
3. ใส่ชื่อฐานข้อมูล: `hicm_v2025`
4. เลือก Collation: `utf8mb4_unicode_ci`
5. คลิก "Create"

#### 2.3 นำเข้าโครงสร้างฐานข้อมูล
1. เลือกฐานข้อมูล `hicm_v2025`
2. คลิกแท็บ "Import"
3. เลือกไฟล์ `C:\xampp\htdocs\hicm-v2025\database\schema.sql`
4. คลิก "Go" หรือ "นำเข้า"

#### 2.4 นำเข้าตัวชี้วัดเพิ่มเติม
1. คลิกแท็บ "Import"
2. เลือกไฟล์ `C:\xampp\htdocs\hicm-v2025\database\insert_indicators.sql`
3. คลิก "Go"

### 3. ตั้งค่าการเชื่อมต่อฐานข้อมูล

เปิดไฟล์ `C:\xampp\htdocs\hicm-v2025\config\database.php`

ตรวจสอบการตั้งค่า (ค่าเริ่มต้นสำหรับ XAMPP):
```php
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'hicm_v2025');
```

หากตั้งรหัสผ่าน MySQL ให้แก้ไข DB_PASSWORD

### 4. ตั้งค่าโฟลเดอร์อัปโหลด

```cmd
cd C:\xampp\htdocs\hicm-v2025
mkdir assets\uploads
```

ตรวจสอบสิทธิ์โฟลเดอร์:
- คลิกขวาที่โฟลเดอร์ `uploads`
- Properties > Security
- ตรวจสอบว่า Everyone มีสิทธิ์ Read/Write

### 5. ตั้งค่า PHP (ถ้าจำเป็น)

เปิดไฟล์ `C:\xampp\php\php.ini`

แก้ไขค่า:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

รีสตาร์ท Apache หลังจากแก้ไข

### 6. เข้าใช้งานระบบ

1. เปิดเบราว์เซอร์ไปที่: http://localhost/hicm-v2025
2. เข้าสู่ระบบด้วย:
   - อีเมล: `admin@hicm.gov.th`
   - รหัสผ่าน: `admin123`

## การแก้ไขปัญหาเบื้องต้น

### ปัญหา 1: ไม่สามารถเชื่อมต่อฐานข้อมูลได้
**วิธีแก้:**
- ตรวจสอบว่า MySQL กำลังทำงานอยู่
- ตรวจสอบการตั้งค่าใน `config/database.php`
- ตรวจสอบว่าสร้างฐานข้อมูล `hicm_v2025` แล้ว

### ปัญหา 2: หน้าเว็บแสดงเป็นข้อความล้วน (ไม่มี CSS)
**วิธีแก้:**
- ตรวจสอบว่า Apache กำลังทำงานอยู่
- ตรวจสอบว่าโฟลเดอร์ `assets/css/` มีไฟล์ `style.css`

### ปัญหา 3: อัปโหลดไฟล์ไม่ได้
**วิธีแก้:**
- ตรวจสอบว่าโฟลเดอร์ `assets/uploads/` มีสิทธิ์เขียน
- ตรวจสอบค่า `upload_max_filesize` ใน php.ini
- ตรวจสอบประเภทไฟล์ที่อนุญาต

### ปัญหา 4: 404 Not Found
**วิธีแก้:**
- ตรวจสอบว่า mod_rewrite เปิดใช้งานแล้ว
- ตรวจสอบไฟล์ `.htaccess` อยู่ในโฟลเดอร์ root
- แก้ไข `httpd.conf` ของ Apache:
  ```
  LoadModule rewrite_module modules/mod_rewrite.so
  ```
  และ
  ```
  <Directory "C:/xampp/htdocs">
      AllowOverride All
  </Directory>
  ```

## การสำรองข้อมูล

### สำรองฐานข้อมูล
1. เปิด phpMyAdmin
2. เลือกฐานข้อมูล `hicm_v2025`
3. คลิกแท็บ "Export"
4. เลือก "Custom"
5. คลิก "Go"

### สำรองไฟล์อัปโหลด
คัดลอกโฟลเดอร์ `assets/uploads/` ไปเก็บไว้

## การอัปเดตระบบ

1. สำรองฐานข้อมูลและไฟล์อัปโหลด
2. แทนที่ไฟล์ระบบด้วยเวอร์ชันใหม่
3. รันไฟล์อัปเดตฐานข้อมูล (ถ้ามี)
4. ตรวจสอบการทำงาน

## ติดต่อสอบถาม

หากมีปัญหาในการติดตั้ง กรุณาติดต่อ:
- อีเมล: support@hicm.gov.th
