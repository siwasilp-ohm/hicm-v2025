# คู่มือติดตั้ง HICM V2025 บน Ubuntu 24.04 LTS

> **ระบบ:** Ubuntu 24.04 LTS (Noble Numbat)  
> **Stack:** Apache 2.4 · PHP 8.2 · MySQL 8.0 / MariaDB 10.11  
> **เวลาติดตั้งโดยประมาณ:** 20–30 นาที

---

## สารบัญ

1. [ความต้องการของระบบ](#1-ความต้องการของระบบ)
2. [อัปเดต Ubuntu และติดตั้ง dependencies](#2-อัปเดต-ubuntu-และติดตั้ง-dependencies)
3. [ติดตั้ง PHP 8.2 และ Extensions](#3-ติดตั้ง-php-82-และ-extensions)
4. [ติดตั้งและตั้งค่า MySQL](#4-ติดตั้งและตั้งค่า-mysql)
5. [ติดตั้งและตั้งค่า Apache](#5-ติดตั้งและตั้งค่า-apache)
6. [ดาวน์โหลดและติดตั้งโปรเจค](#6-ดาวน์โหลดและติดตั้งโปรเจค)
7. [ตั้งค่าฐานข้อมูล](#7-ตั้งค่าฐานข้อมูล)
8. [ตั้งค่า Environment Variables](#8-ตั้งค่า-environment-variables)
9. [ตั้งค่า Apache Virtual Host](#9-ตั้งค่า-apache-virtual-host)
10. [ตั้งค่า File Permissions](#10-ตั้งค่า-file-permissions)
11. [ทดสอบการติดตั้ง](#11-ทดสอบการติดตั้ง)
12. [ข้อมูลเข้าสู่ระบบเริ่มต้น](#12-ข้อมูลเข้าสู่ระบบเริ่มต้น)
13. [การตั้งค่าเพิ่มเติม (Production)](#13-การตั้งค่าเพิ่มเติม-production)
14. [แก้ไขปัญหาที่พบบ่อย](#14-แก้ไขปัญหาที่พบบ่อย)

---

## 1. ความต้องการของระบบ

### ขั้นต่ำ
| รายการ | ความต้องการ |
|---|---|
| OS | Ubuntu 24.04 LTS (64-bit) |
| CPU | 2 vCPU |
| RAM | 2 GB |
| Disk | 10 GB |
| PHP | 8.2 หรือสูงกว่า |
| MySQL | 8.0+ หรือ MariaDB 10.6+ |
| Apache | 2.4+ |

### PHP Extensions ที่จำเป็น
```
pdo_mysql  mysqli  mbstring  gd  zip  exif  intl  bcmath  opcache  xml  json  fileinfo
```

---

## 2. อัปเดต Ubuntu และติดตั้ง Dependencies

```bash
# อัปเดต package list และ upgrade ระบบ
sudo apt update && sudo apt upgrade -y

# ติดตั้ง tools ที่จำเป็น
sudo apt install -y \
    curl \
    wget \
    git \
    unzip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    lsb-release
```

---

## 3. ติดตั้ง PHP 8.2 และ Extensions

Ubuntu 24.04 มี PHP 8.3 ใน default repo แต่แนะนำ PHP 8.2 จาก PPA ของ Ondrej เพื่อความเสถียร

```bash
# เพิ่ม PPA สำหรับ PHP (Ondrej Sury repository)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# ติดตั้ง PHP 8.2 พร้อม extensions ทั้งหมด
sudo apt install -y \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-pdo \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-gd \
    php8.2-zip \
    php8.2-curl \
    php8.2-intl \
    php8.2-bcmath \
    php8.2-exif \
    php8.2-opcache \
    php8.2-fileinfo \
    php8.2-json \
    libapache2-mod-php8.2

# ตรวจสอบเวอร์ชัน PHP
php -v
```

ผลลัพธ์ที่ควรได้:
```
PHP 8.2.x (cli) (built: ...) ...
```

### ตั้งค่า PHP สำหรับระบบ

```bash
# แก้ไข php.ini สำหรับ Apache
sudo nano /etc/php/8.2/apache2/php.ini
```

ค้นหาและแก้ไขค่าต่อไปนี้ (กด `Ctrl+W` เพื่อค้นหาใน nano):

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
date.timezone = Asia/Bangkok
```

> **Tip:** ใช้คำสั่ง `sed` แทนเพื่อความรวดเร็ว:
> ```bash
> sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 10M/' /etc/php/8.2/apache2/php.ini
> sudo sed -i 's/post_max_size = .*/post_max_size = 10M/' /etc/php/8.2/apache2/php.ini
> sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.2/apache2/php.ini
> sudo sed -i 's/memory_limit = .*/memory_limit = 256M/' /etc/php/8.2/apache2/php.ini
> sudo sed -i 's/;date.timezone.*/date.timezone = Asia\/Bangkok/' /etc/php/8.2/apache2/php.ini
> ```

---

## 4. ติดตั้งและตั้งค่า MySQL

```bash
# ติดตั้ง MySQL Server 8.0
sudo apt install -y mysql-server

# เริ่มต้นและ enable MySQL ให้รันอัตโนมัติ
sudo systemctl start mysql
sudo systemctl enable mysql

# ตรวจสอบสถานะ
sudo systemctl status mysql
```

### ตั้งค่าความปลอดภัย MySQL

```bash
sudo mysql_secure_installation
```

ตอบคำถามดังนี้:
```
Securing the MySQL server deployment.

Would you like to setup VALIDATE PASSWORD component? → Y
Password validation policy: → 0 (LOW) หรือ 1 (MEDIUM)
Remove anonymous users? → Y
Disallow root login remotely? → Y
Remove test database? → Y
Reload privilege tables? → Y
```

### สร้าง Database User สำหรับระบบ

```bash
# เข้า MySQL ด้วย root
sudo mysql -u root

# ใน MySQL prompt ให้รันคำสั่งต่อไปนี้:
```

```sql
-- สร้างฐานข้อมูล
CREATE DATABASE IF NOT EXISTS hicm_v2025
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- สร้าง user สำหรับระบบ (แนะนำ: อย่าใช้ root ใน production)
CREATE USER 'hicm_user'@'localhost' IDENTIFIED BY 'HicmStr0ng!Pass2025';

-- ให้สิทธิ์เข้าถึงฐานข้อมูล
GRANT ALL PRIVILEGES ON hicm_v2025.* TO 'hicm_user'@'localhost';

-- บันทึกสิทธิ์
FLUSH PRIVILEGES;

-- ตรวจสอบ
SHOW DATABASES;

-- ออกจาก MySQL
EXIT;
```

> **หมายเหตุ:** เปลี่ยน `HicmStr0ng!Pass2025` เป็นรหัสผ่านที่ต้องการ และจดไว้ใช้ในขั้นตอน [ตั้งค่า .env](#8-ตั้งค่า-environment-variables)

---

## 5. ติดตั้งและตั้งค่า Apache

```bash
# ติดตั้ง Apache
sudo apt install -y apache2

# เริ่มต้นและ enable Apache
sudo systemctl start apache2
sudo systemctl enable apache2

# ตรวจสอบสถานะ
sudo systemctl status apache2
```

### เปิดใช้งาน Apache Modules ที่จำเป็น

```bash
# เปิด modules ที่ระบบต้องการ
sudo a2enmod rewrite
sudo a2enmod deflate
sudo a2enmod expires
sudo a2enmod headers
sudo a2enmod ssl

# เปิดใช้งาน PHP 8.2
sudo a2enmod php8.2

# รีสตาร์ท Apache
sudo systemctl restart apache2
```

---

## 6. ดาวน์โหลดและติดตั้งโปรเจค

### วิธีที่ 1: คัดลอกจาก ZIP / SCP

```bash
# สร้างโฟลเดอร์ที่ /var/www
sudo mkdir -p /var/www/hicm-v2025

# ถ้ามีไฟล์ ZIP (แทนที่ path ด้วย path จริงของคุณ)
sudo unzip /tmp/hicm-v2025.zip -d /var/www/hicm-v2025

# หรือคัดลอกจากเครื่อง Windows ผ่าน SCP (รันบนเครื่อง local)
# scp -r /path/to/hicm-v2025 user@server:/var/www/
```

### วิธีที่ 2: Clone จาก Git Repository

```bash
cd /var/www
sudo git clone https://github.com/your-org/hicm-v2025.git hicm-v2025
```

### ตั้งค่า Ownership

```bash
# ให้ Apache (www-data) เป็นเจ้าของโฟลเดอร์
sudo chown -R www-data:www-data /var/www/hicm-v2025

# ให้ user ปัจจุบันอยู่ใน group www-data เพื่อแก้ไขไฟล์ได้
sudo usermod -aG www-data $USER
newgrp www-data
```

---

## 7. ตั้งค่าฐานข้อมูล

### Import Schema (โครงสร้างฐานข้อมูล)

```bash
# Import schema หลัก
mysql -u hicm_user -p hicm_v2025 < /var/www/hicm-v2025/database/schema.sql

# Import ตัวชี้วัด 60 ข้อ (H1/I2/C3/M4)
mysql -u hicm_user -p hicm_v2025 < /var/www/hicm-v2025/database/insert_indicators.sql

# Import ข้อมูลตัวอย่าง (สำหรับทดสอบ — ไม่บังคับสำหรับ production)
mysql -u hicm_user -p hicm_v2025 < /var/www/hicm-v2025/database/sample_users.sql
```

ระบบจะถามรหัสผ่านที่สร้างไว้ในข้อ 4

### ตรวจสอบว่า Import สำเร็จ

```bash
mysql -u hicm_user -p -e "USE hicm_v2025; SHOW TABLES;"
```

ควรเห็น tables เช่น: `users`, `companies`, `assessments`, `indicators`, `pillars`, `periods`, ...

---

## 8. ตั้งค่า Environment Variables

```bash
# คัดลอกไฟล์ .env จาก template
sudo cp /var/www/hicm-v2025/.env.example /var/www/hicm-v2025/.env

# แก้ไข .env
sudo nano /var/www/hicm-v2025/.env
```

แก้ไขค่าต่อไปนี้ใน `.env`:

```dotenv
# Application
APP_NAME="HICM V2025 Assessment System"
APP_URL=http://your-domain.com
APP_ENV=production
APP_DEBUG=false

# Database
DB_HOST=localhost
DB_NAME=hicm_v2025
DB_USERNAME=hicm_user
DB_PASSWORD=HicmStr0ng!Pass2025
DB_PORT=3306
DB_CHARSET=utf8mb4

# Upload Settings
MAX_UPLOAD_SIZE=10485760
UPLOAD_PATH=/var/www/hicm-v2025/assets/uploads/

# Timezone
APP_TIMEZONE=Asia/Bangkok
```

> **สำหรับ localhost (ทดสอบ):** ตั้ง `APP_URL=http://localhost/hicm-v2025`  
> **สำหรับ production (มี domain):** ตั้ง `APP_URL=https://hicm.your-domain.com`

---

## 9. ตั้งค่า Apache Virtual Host

### สร้าง Virtual Host Config

```bash
sudo nano /etc/apache2/sites-available/hicm-v2025.conf
```

วางเนื้อหาต่อไปนี้:

```apache
<VirtualHost *:80>
    ServerName hicm.your-domain.com
    # ServerAlias www.hicm.your-domain.com
    DocumentRoot /var/www/hicm-v2025

    <Directory /var/www/hicm-v2025>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # PHP settings override
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 300
    php_value memory_limit 256M

    # Logs
    ErrorLog ${APACHE_LOG_DIR}/hicm_error.log
    CustomLog ${APACHE_LOG_DIR}/hicm_access.log combined

    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
</VirtualHost>
```

> **สำหรับทดสอบบน localhost** ใช้ค่า `ServerName localhost` และ `DocumentRoot /var/www/hicm-v2025` แทน

### เปิดใช้งาน Virtual Host

```bash
# เปิด site ใหม่
sudo a2ensite hicm-v2025.conf

# ปิด default site (ถ้าไม่ต้องการ)
sudo a2dissite 000-default.conf

# ตรวจสอบ config ว่าไม่มี error
sudo apache2ctl configtest

# รีโหลด Apache
sudo systemctl reload apache2
```

### ตั้งค่า hosts file (สำหรับทดสอบ local เท่านั้น)

```bash
# เพิ่มบรรทัดนี้ใน /etc/hosts (ถ้า serverName ไม่ใช่ localhost)
echo "127.0.0.1 hicm.local" | sudo tee -a /etc/hosts
```

---

## 10. ตั้งค่า File Permissions

```bash
# กำหนด ownership ทั้ง project
sudo chown -R www-data:www-data /var/www/hicm-v2025

# Directories: 755 (owner rwx, group r-x, other r-x)
sudo find /var/www/hicm-v2025 -type d -exec chmod 755 {} \;

# Files: 644 (owner rw, group r, other r)
sudo find /var/www/hicm-v2025 -type f -exec chmod 644 {} \;

# Upload directories: 775 (Apache ต้องเขียนได้)
sudo chmod -R 775 /var/www/hicm-v2025/assets/uploads

# สร้างโฟลเดอร์ upload ที่จำเป็น (ถ้ายังไม่มี)
sudo mkdir -p /var/www/hicm-v2025/assets/uploads/avatars
sudo mkdir -p /var/www/hicm-v2025/assets/uploads/manual_refs
sudo mkdir -p /var/www/hicm-v2025/assets/uploads/2025
sudo mkdir -p /var/www/hicm-v2025/assets/uploads/2026

sudo chown -R www-data:www-data /var/www/hicm-v2025/assets/uploads

# .env ต้องอ่านได้เฉพาะ owner
sudo chmod 640 /var/www/hicm-v2025/.env
sudo chown www-data:www-data /var/www/hicm-v2025/.env
```

---

## 11. ทดสอบการติดตั้ง

### ตรวจสอบ Services

```bash
# ตรวจสอบ Apache
sudo systemctl status apache2

# ตรวจสอบ MySQL
sudo systemctl status mysql

# ตรวจสอบ PHP extensions ที่ load แล้ว
php -m | grep -E "pdo_mysql|mbstring|gd|zip|intl|bcmath|opcache|exif"
```

### ทดสอบ PHP ผ่าน Web

```bash
# สร้างไฟล์ทดสอบชั่วคราว
echo "<?php phpinfo();" | sudo tee /var/www/hicm-v2025/phpinfo.php

# เปิดเบราว์เซอร์ไปที่
# http://your-server-ip/phpinfo.php
# หรือ http://hicm.local/phpinfo.php
```

ตรวจสอบว่าเห็น:
- PHP 8.2.x
- PDO / pdo_mysql → enabled
- mbstring → enabled
- GD → enabled (ต้องมี FreeType + JPEG support)

**ลบไฟล์ทดสอบทิ้งทันทีหลังตรวจสอบเสร็จ:**

```bash
sudo rm /var/www/hicm-v2025/phpinfo.php
```

### ทดสอบฐานข้อมูล

```bash
# ทดสอบ connection จาก PHP CLI
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;dbname=hicm_v2025;charset=utf8mb4', 'hicm_user', 'HicmStr0ng!Pass2025');
    echo 'Database connection: OK' . PHP_EOL;
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM users');
    echo 'Users in DB: ' . \$stmt->fetchColumn() . PHP_EOL;
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage() . PHP_EOL;
}
"
```

### เปิดระบบในเบราว์เซอร์

เปิดเบราว์เซอร์ไปที่:
```
http://your-server-ip/        (ถ้า DocumentRoot ชี้ที่ hicm-v2025)
http://hicm.local/            (ถ้าใช้ ServerName hicm.local)
http://your-domain.com/       (production)
```

ควรเห็นหน้า Login ของ HICM V2025

---

## 12. ข้อมูลเข้าสู่ระบบเริ่มต้น

> **⚠️ สำคัญ: เปลี่ยนรหัสผ่านทันทีหลังเข้าสู่ระบบครั้งแรก**

| บทบาท | อีเมล | รหัสผ่าน |
|---|---|---|
| Admin | `admin@hicm.gov.th` | `admin123` |
| Auditor (ตัวอย่าง) | `auditor@hicm.gov.th` | `auditor123` |
| Company (ตัวอย่าง) | `company@example.com` | `company123` |

*(ข้อมูลตัวอย่างจาก `database/sample_users.sql` — ใช้สำหรับทดสอบเท่านั้น)*

---

## 13. การตั้งค่าเพิ่มเติม (Production)

### ติดตั้ง SSL/HTTPS ด้วย Let's Encrypt

```bash
# ติดตั้ง Certbot
sudo apt install -y certbot python3-certbot-apache

# ขอ SSL Certificate (แทนที่ด้วย domain จริง)
sudo certbot --apache -d hicm.your-domain.com

# ทดสอบ auto-renewal
sudo certbot renew --dry-run
```

### ตั้งค่า UFW Firewall

```bash
# เปิด firewall
sudo ufw enable

# อนุญาต SSH, HTTP, HTTPS
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# ตรวจสอบ
sudo ufw status
```

### ตั้งค่า MySQL สำหรับ Production

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

เพิ่ม/แก้ไข:
```ini
[mysqld]
# ไม่ให้ MySQL listen จากภายนอก
bind-address = 127.0.0.1

# Performance tuning (ปรับตาม RAM ของเซิร์ฟเวอร์)
innodb_buffer_pool_size = 512M
max_connections = 150
query_cache_size = 64M
```

```bash
sudo systemctl restart mysql
```

### ตั้งค่า OPcache เพื่อเพิ่มประสิทธิภาพ PHP

```bash
sudo nano /etc/php/8.2/apache2/conf.d/10-opcache.ini
```

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### ตั้งค่า Log Rotation

```bash
sudo nano /etc/logrotate.d/hicm-v2025
```

```
/var/log/apache2/hicm_*.log {
    weekly
    rotate 12
    compress
    delaycompress
    missingok
    notifempty
    sharedscripts
    postrotate
        /usr/bin/systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

### สำรองข้อมูลอัตโนมัติ (Cron Job)

```bash
# สร้าง script สำรองข้อมูล
sudo nano /usr/local/bin/hicm-backup.sh
```

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/hicm-v2025"
DATE=$(date +%Y%m%d_%H%M%S)
DB_USER="hicm_user"
DB_PASS="HicmStr0ng!Pass2025"
DB_NAME="hicm_v2025"

mkdir -p "$BACKUP_DIR"

# Backup database
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Backup uploads
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" /var/www/hicm-v2025/assets/uploads/

# ลบ backup เก่ากว่า 30 วัน
find "$BACKUP_DIR" -mtime +30 -delete

echo "[$DATE] Backup completed."
```

```bash
sudo chmod +x /usr/local/bin/hicm-backup.sh

# ตั้ง cron: สำรองข้อมูลทุกวัน 02:00 AM
echo "0 2 * * * root /usr/local/bin/hicm-backup.sh >> /var/log/hicm-backup.log 2>&1" | sudo tee /etc/cron.d/hicm-backup
```

---

## 14. แก้ไขปัญหาที่พบบ่อย

### ❌ หน้าเว็บแสดง 403 Forbidden

```bash
# ตรวจสอบ permissions
ls -la /var/www/hicm-v2025/

# แก้ไข: ตั้ง ownership ใหม่
sudo chown -R www-data:www-data /var/www/hicm-v2025
sudo chmod -R 755 /var/www/hicm-v2025

# ตรวจสอบว่า AllowOverride All ถูกตั้งใน VirtualHost
sudo apache2ctl -S
```

### ❌ .htaccess ไม่ทำงาน / 404 Not Found

```bash
# ตรวจสอบว่า mod_rewrite เปิดอยู่
apache2ctl -M | grep rewrite

# ถ้าไม่มี ให้เปิด
sudo a2enmod rewrite
sudo systemctl restart apache2

# ตรวจสอบว่า AllowOverride ถูกตั้งใน config
grep -r "AllowOverride" /etc/apache2/sites-enabled/
```

### ❌ ติดต่อฐานข้อมูลไม่ได้ (Database connection error)

```bash
# ตรวจสอบ MySQL รันอยู่
sudo systemctl status mysql

# ทดสอบ login
mysql -u hicm_user -p hicm_v2025

# ตรวจสอบ .env มีค่าถูกต้อง
sudo cat /var/www/hicm-v2025/.env | grep DB_

# ตรวจสอบว่า PDO MySQL extension load แล้ว
php -m | grep pdo_mysql
```

### ❌ ไม่สามารถอัปโหลดไฟล์ได้

```bash
# ตรวจสอบ permissions ของโฟลเดอร์ uploads
ls -la /var/www/hicm-v2025/assets/uploads/

# แก้ไข
sudo chown -R www-data:www-data /var/www/hicm-v2025/assets/uploads/
sudo chmod -R 775 /var/www/hicm-v2025/assets/uploads/

# ตรวจสอบ php.ini upload limit
php -i | grep upload_max_filesize
```

### ❌ ตัวอักษรภาษาไทยแสดงผิด (Encoding error)

```bash
# ตรวจสอบ MySQL charset
mysql -u hicm_user -p -e "SHOW CREATE DATABASE hicm_v2025\G"

# ถ้า charset ไม่ใช่ utf8mb4 ให้แก้ไข
mysql -u root -p -e "ALTER DATABASE hicm_v2025 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# ตรวจสอบ php.ini
php -i | grep default_charset
```

### ❌ PHP error แสดงบนหน้าเว็บ (production)

```bash
# แก้ไข .env
sudo nano /var/www/hicm-v2025/.env
# ตั้ง APP_DEBUG=false

# หรือแก้ใน php.ini
sudo sed -i 's/display_errors = On/display_errors = Off/' /etc/php/8.2/apache2/php.ini
sudo systemctl reload apache2

# ดู error จาก log แทน
sudo tail -f /var/log/apache2/hicm_error.log
```

### ❌ White screen / Blank page

```bash
# เปิด error reporting ชั่วคราวเพื่อ debug
sudo sed -i 's/display_errors = Off/display_errors = On/' /etc/php/8.2/apache2/php.ini
sudo systemctl reload apache2

# ดู PHP error log
sudo tail -100 /var/log/apache2/error.log

# ตรวจสอบ syntax errors ใน PHP
php -l /var/www/hicm-v2025/index.php
```

---

## สรุปคำสั่งสำคัญ

```bash
# รีสตาร์ท Services
sudo systemctl restart apache2
sudo systemctl restart mysql

# ดู Logs
sudo tail -f /var/log/apache2/hicm_error.log
sudo tail -f /var/log/apache2/hicm_access.log
sudo journalctl -u apache2 -f
sudo journalctl -u mysql -f

# ตรวจสอบ Config
sudo apache2ctl configtest
sudo apache2ctl -M          # list modules
php -i                      # PHP info (CLI)
php -m                      # loaded extensions
mysql -u hicm_user -p       # MySQL shell
```

---

## โครงสร้างโปรเจค

```
/var/www/hicm-v2025/
├── .env                    ← ตั้งค่า DB, URL, secrets (ห้าม commit!)
├── .htaccess               ← Apache rewrite rules + PHP settings
├── index.php               ← Entry point
├── config/
│   ├── config.php          ← Constants, helper functions
│   └── database.php        ← PDO connection class
├── database/
│   ├── schema.sql          ← โครงสร้างฐานข้อมูลทั้งหมด
│   ├── insert_indicators.sql ← ตัวชี้วัด 60 ข้อ
│   └── sample_users.sql    ← ข้อมูลตัวอย่าง (สำหรับทดสอบ)
├── includes/               ← Shared functions (auth, assessment, etc.)
├── pages/                  ← PHP pages (~35 หน้า)
├── api/                    ← API endpoints (~17 endpoints)
└── assets/
    ├── css/
    ├── js/
    ├── images/
    └── uploads/            ← ⚠️ ต้องมีสิทธิ์เขียน (www-data)
        ├── avatars/
        ├── manual_refs/
        └── {year}/
```

---

*คู่มือนี้ทดสอบบน Ubuntu 24.04 LTS (Noble Numbat) · Apache 2.4 · PHP 8.2 · MySQL 8.0*  
*หากพบปัญหาเพิ่มเติม ดู log ที่ `/var/log/apache2/` และ `/var/log/mysql/`*
