#!/usr/bin/env bash
# =============================================================================
#  HICM V2025 — Auto Installer for Ubuntu 24.04 LTS
#  Usage  : sudo bash install.sh
#  Deploy : root path   → https://example.com/
#           sub-path    → https://example.com/assessment
# =============================================================================

set -euo pipefail

# ── Colors ───────────────────────────────────────────────────────────────────
RED='\033[0;31m';  GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m';  BOLD='\033[1m'; NC='\033[0m'

info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
error()   { echo -e "${RED}[ERROR]${NC} $*"; exit 1; }
step()    { echo -e "\n${BOLD}${BLUE}▶ $*${NC}"; }
ask()     { echo -e "${YELLOW}$*${NC}"; }

# ── Banner ───────────────────────────────────────────────────────────────────
clear
echo -e "${BOLD}${BLUE}"
cat << 'BANNER'
  ██╗  ██╗██╗ ██████╗███╗   ███╗    ██╗   ██╗██████╗  ██████╗ ██████╗ ███████╗
  ██║  ██║██║██╔════╝████╗ ████║    ██║   ██║╚════██╗██╔═══██╗╚════██╗██╔════╝
  ███████║██║██║     ██╔████╔██║    ██║   ██║ █████╔╝██║   ██║ █████╔╝███████╗
  ██╔══██║██║██║     ██║╚██╔╝██║    ╚██╗ ██╔╝██╔═══╝ ██║   ██║██╔═══╝ ╚════██║
  ██║  ██║██║╚██████╗██║ ╚═╝ ██║     ╚████╔╝ ███████╗╚██████╔╝███████╗███████║
  ╚═╝  ╚═╝╚═╝ ╚═════╝╚═╝     ╚═╝      ╚═══╝  ╚══════╝ ╚═════╝ ╚══════╝╚══════╝
BANNER
echo -e "${NC}"
echo -e "  ${BOLD}Auto Installer — Ubuntu 24.04 LTS${NC}  (Sub-path Edition)"
echo -e "  ระบบประเมินสถานประกอบการ Health Industrial Community Model"
echo -e "  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"

# ── Guards ───────────────────────────────────────────────────────────────────
[[ $EUID -ne 0 ]] && error "กรุณารันด้วย sudo: sudo bash install.sh"
grep -qi "ubuntu" /etc/os-release 2>/dev/null || warn "ไม่ใช่ Ubuntu — อาจเกิดปัญหา"

# ── Bootstrap: ถ้ารันแบบ standalone (wget แล้วรัน) ให้ clone repo ก่อน ────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GITHUB_REPO="https://github.com/siwasilp-ohm/hicm-v2025.git"

if [[ ! -f "${SCRIPT_DIR}/database/schema.sql" ]]; then
    info "ไม่พบไฟล์โปรเจค — กำลัง clone จาก GitHub..."
    apt-get install -y -qq git > /dev/null 2>&1
    CLONE_DIR="/tmp/hicm-v2025-src"
    rm -rf "$CLONE_DIR"
    git clone --depth=1 "$GITHUB_REPO" "$CLONE_DIR" \
        || error "Clone ไม่สำเร็จ — ตรวจสอบการเชื่อมต่อ internet"
    success "Clone เสร็จแล้ว"
    exec sudo bash "${CLONE_DIR}/install.sh"
fi

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 0: รับค่าจากผู้ใช้
# ─────────────────────────────────────────────────────────────────────────────
step "กำหนดค่าการติดตั้ง"

ask "📁 โฟลเดอร์ปลายทาง (Enter = /var/www/hicm-v2025):"
read -r INPUT_WEBROOT
WEBROOT="${INPUT_WEBROOT:-/var/www/hicm-v2025}"

ask "🌐 Domain หรือ IP ของเซิร์ฟเวอร์ (Enter = localhost):"
read -r INPUT_DOMAIN
APP_DOMAIN="${INPUT_DOMAIN:-localhost}"

ask "🔌 Port ของ Apache (Enter = 80, ใส่ 8080 ถ้า port 80 ถูกใช้งานอยู่):"
read -r INPUT_PORT
APP_PORT="${INPUT_PORT:-80}"

echo -e "${YELLOW}🔗 Sub-path (ชื่อ path หลัง domain เช่น assessment)"
ask "   เว้นว่างถ้าต้องการ deploy ที่ root / (Enter = assessment):"
read -r INPUT_SUBPATH
# ทำความสะอาด: ตัด / นำหน้าและท้าย, แปลง /foo/bar → foo/bar
APP_SUBPATH="$(echo "${INPUT_SUBPATH:-assessment}" | sed 's|^/*||; s|/*$||')"

# สร้าง APP_URL และ SCHEME
if [[ "$APP_DOMAIN" == "localhost" || "$APP_DOMAIN" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    SCHEME="http"
else
    SCHEME="https"
fi

PORT_SUFFIX=""
[[ "$APP_PORT" != "80" && "$APP_PORT" != "443" ]] && PORT_SUFFIX=":${APP_PORT}"

if [[ -n "$APP_SUBPATH" ]]; then
    APP_URL="${SCHEME}://${APP_DOMAIN}${PORT_SUFFIX}/${APP_SUBPATH}"
    DEPLOY_MODE="sub-path  (/${APP_SUBPATH})"
else
    APP_URL="${SCHEME}://${APP_DOMAIN}${PORT_SUFFIX}"
    DEPLOY_MODE="root path  (/)"
fi

ask "🗄️  ชื่อ MySQL user สำหรับระบบ (Enter = hicm_user):"
read -r INPUT_DB_USER
DB_USER="${INPUT_DB_USER:-hicm_user}"

while true; do
    ask "🔑 รหัสผ่าน MySQL user '${DB_USER}' (ห้ามเว้นว่าง):"
    read -rs DB_PASS; echo
    [[ -n "$DB_PASS" ]] && break
    warn "กรุณาใส่รหัสผ่าน"
done

DB_NAME="hicm_v2025"

ask "📦 Import ข้อมูลตัวอย่าง (sample users + demo)? [y/N]:"
read -r INPUT_SEED
IMPORT_SAMPLE="${INPUT_SEED:-n}"

echo
echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "  ${BOLD}สรุปการตั้งค่า${NC}"
echo -e "  Deploy mode : ${GREEN}${DEPLOY_MODE}${NC}"
echo -e "  Web root    : ${GREEN}${WEBROOT}${NC}"
echo -e "  Domain      : ${GREEN}${APP_DOMAIN}${NC}"
echo -e "  Port        : ${GREEN}${APP_PORT}${NC}"
echo -e "  Sub-path    : ${GREEN}/${APP_SUBPATH:-  (root)}${NC}"
echo -e "  App URL     : ${CYAN}${APP_URL}${NC}"
echo -e "  DB name     : ${GREEN}${DB_NAME}${NC}"
echo -e "  DB user     : ${GREEN}${DB_USER}${NC}"
echo -e "  Sample data : ${GREEN}${IMPORT_SAMPLE}${NC}"
echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
ask "\n▶ ยืนยันติดตั้ง? [Y/n]:"
read -r CONFIRM
[[ "${CONFIRM,,}" == "n" ]] && { info "ยกเลิกการติดตั้ง"; exit 0; }

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 1: อัปเดต Ubuntu
# ─────────────────────────────────────────────────────────────────────────────
step "1/8  อัปเดต Ubuntu Package List"

apt-get update -qq
apt-get install -y -qq \
    curl wget git unzip rsync \
    software-properties-common apt-transport-https \
    ca-certificates lsb-release gnupg2 > /dev/null

success "Package list อัปเดตแล้ว"

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 2: ติดตั้ง PHP 8.2
# ─────────────────────────────────────────────────────────────────────────────
step "2/8  ติดตั้ง PHP 8.2 และ Extensions"

if ! grep -rq "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
    info "เพิ่ม Ondrej PHP PPA..."
    add-apt-repository ppa:ondrej/php -y > /dev/null 2>&1 || true
    apt-get update -qq
fi

apt-get install -y -qq \
    php8.2 php8.2-cli php8.2-fpm \
    php8.2-mysql php8.2-pdo \
    php8.2-mbstring php8.2-xml \
    php8.2-gd php8.2-zip \
    php8.2-curl php8.2-intl \
    php8.2-bcmath php8.2-exif \
    php8.2-opcache php8.2-fileinfo \
    libapache2-mod-php8.2 > /dev/null

PHP_INI="/etc/php/8.2/apache2/php.ini"
sed -i 's/^upload_max_filesize.*/upload_max_filesize = 10M/'  "$PHP_INI"
sed -i 's/^post_max_size.*/post_max_size = 10M/'              "$PHP_INI"
sed -i 's/^max_execution_time.*/max_execution_time = 300/'    "$PHP_INI"
sed -i 's/^max_input_time.*/max_input_time = 300/'            "$PHP_INI"
sed -i 's/^memory_limit.*/memory_limit = 256M/'               "$PHP_INI"
sed -i 's/^;date.timezone.*/date.timezone = Asia\/Bangkok/'   "$PHP_INI"
sed -i 's/^display_errors.*/display_errors = Off/'            "$PHP_INI"

success "PHP $(php -r 'echo PHP_VERSION;') พร้อมใช้งาน"

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 3: ติดตั้ง Apache
# ─────────────────────────────────────────────────────────────────────────────
step "3/8  ติดตั้งและตั้งค่า Apache"

apt-get install -y -qq apache2 > /dev/null
a2enmod rewrite deflate expires headers php8.2 > /dev/null 2>&1

# เพิ่ม Listen port ถ้าไม่ใช่ 80
if [[ "$APP_PORT" != "80" ]]; then
    if ! grep -q "^Listen ${APP_PORT}" /etc/apache2/ports.conf; then
        echo "Listen ${APP_PORT}" >> /etc/apache2/ports.conf
    fi
fi

systemctl enable apache2 --quiet
systemctl start apache2

success "Apache 2.4 พร้อมใช้งาน (port ${APP_PORT})"

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 4: ติดตั้ง MySQL
# ─────────────────────────────────────────────────────────────────────────────
step "4/8  ติดตั้งและตั้งค่า MySQL"

MYSQL_ROOT_PASS="$(openssl rand -base64 24)"

apt-get install -y -qq mysql-server > /dev/null
systemctl enable mysql --quiet
systemctl start mysql

info "รอ MySQL พร้อม..."
for i in $(seq 1 20); do mysqladmin ping --silent 2>/dev/null && break; sleep 1; done

mysql --user=root << MYSQL_INIT
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASS}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
DELETE FROM mysql.user WHERE User='';
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
MYSQL_INIT

success "MySQL พร้อมใช้งาน  |  DB: ${DB_NAME}  |  User: ${DB_USER}"

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 5: คัดลอกไฟล์โปรเจค
# ─────────────────────────────────────────────────────────────────────────────
step "5/8  ติดตั้งไฟล์โปรเจค → ${WEBROOT}"

mkdir -p "$WEBROOT"

if [[ "$(realpath "$SCRIPT_DIR")" != "$(realpath "$WEBROOT")" ]]; then
    info "คัดลอกไฟล์จาก ${SCRIPT_DIR} ..."
    rsync -a --exclude='.git' --exclude='install.sh' \
        "${SCRIPT_DIR}/" "${WEBROOT}/"
    success "คัดลอกไฟล์เสร็จแล้ว"
else
    info "Script อยู่ใน web root อยู่แล้ว — ข้ามขั้นตอนคัดลอก"
fi

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 6: Import Database
# ─────────────────────────────────────────────────────────────────────────────
step "6/8  Import ฐานข้อมูล"

DB_DIR="${WEBROOT}/database"

import_sql() {
    local file="$1" label="$2"
    if [[ -f "$file" ]]; then
        mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$file" \
            && success "Import: ${label}" \
            || warn    "Import failed: ${label}"
    else
        warn "ไม่พบไฟล์: ${file}"
    fi
}

import_sql "${DB_DIR}/schema.sql"            "schema.sql (โครงสร้าง DB)"
import_sql "${DB_DIR}/insert_indicators.sql" "insert_indicators.sql (60 ตัวชี้วัด)"

if [[ "${IMPORT_SAMPLE,,}" == "y" ]]; then
    import_sql "${DB_DIR}/sample_users.sql"                   "sample_users.sql"
    import_sql "${DB_DIR}/seed_assessment_evaluators_demo.sql" "seed_demo.sql"
fi

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 7: .env + .htaccess + Apache VirtualHost
# ─────────────────────────────────────────────────────────────────────────────
step "7/8  ตั้งค่า Environment, .htaccess และ Apache VirtualHost"

# ── 7a: .env ─────────────────────────────────────────────────────────────────
ENV_FILE="${WEBROOT}/.env"
cat > "$ENV_FILE" << ENV
# HICM V2025 — Environment Configuration
# Generated by install.sh on $(date '+%Y-%m-%d %H:%M:%S')

# Application
APP_NAME="HICM V2025 Assessment System"
APP_URL=${APP_URL}
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Bangkok

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
DB_CHARSET=utf8mb4

# Upload Settings
MAX_UPLOAD_SIZE=10485760
UPLOAD_PATH=${WEBROOT}/assets/uploads/
ENV

chmod 640 "$ENV_FILE"
success ".env → APP_URL=${APP_URL}"

# ── 7b: Patch .htaccess สำหรับ sub-path ─────────────────────────────────────
HTACCESS="${WEBROOT}/.htaccess"

if [[ -n "$APP_SUBPATH" ]]; then
    info "Patch .htaccess: เพิ่ม RewriteBase และแก้ ErrorDocument..."

    # เพิ่ม RewriteBase /{subpath} ต่อจาก RewriteEngine On
    # (ถ้ายังไม่มี RewriteBase อยู่)
    if ! grep -q "^RewriteBase" "$HTACCESS"; then
        sed -i "s|^RewriteEngine On|RewriteEngine On\nRewriteBase /${APP_SUBPATH}|" "$HTACCESS"
    fi

    # แก้ ErrorDocument: /pages/... → /{subpath}/pages/...
    # จับ pattern "ErrorDocument NNN /" แล้วแทรก sub-path
    sed -i "s|ErrorDocument \([0-9]*\) /\([^[:space:]]\)|ErrorDocument \1 /${APP_SUBPATH}/\2|g" "$HTACCESS"

    success ".htaccess → RewriteBase=/${APP_SUBPATH}, ErrorDocument ✓"
else
    success ".htaccess → root path (ไม่ต้องแก้ไข)"
fi

# ── 7c: Apache VirtualHost ───────────────────────────────────────────────────
VHOST_FILE="/etc/apache2/sites-available/hicm-v2025.conf"

# ---- สร้าง VirtualHost content ตาม deploy mode ----
if [[ -n "$APP_SUBPATH" ]]; then
    # ══════════════════════════════════════════
    #  SUB-PATH MODE: Alias /sub-path → webroot
    #  DocumentRoot ยังคงเป็น /var/www/html
    #  เพื่อไม่รบกวน site อื่นบน domain เดิม
    # ══════════════════════════════════════════
    cat > "$VHOST_FILE" << VHOST
# HICM V2025 — Sub-path deployment: ${APP_URL}
# Generated by install.sh on $(date '+%Y-%m-%d %H:%M:%S')

<VirtualHost *:${APP_PORT}>
    ServerName ${APP_DOMAIN}
    DocumentRoot /var/www/html

    # ── HICM V2025 → /${APP_SUBPATH} ──────────────────────────────────────
    Alias /${APP_SUBPATH} ${WEBROOT}

    <Directory ${WEBROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # PHP settings
        <IfModule mod_php.c>
            php_value upload_max_filesize 10M
            php_value post_max_size       10M
            php_value max_execution_time  300
            php_value memory_limit        256M
        </IfModule>
    </Directory>

    # Logs
    ErrorLog  \${APACHE_LOG_DIR}/hicm_error.log
    CustomLog \${APACHE_LOG_DIR}/hicm_access.log combined

    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options        "SAMEORIGIN"
    Header always set X-XSS-Protection       "1; mode=block"
    Header always set Referrer-Policy        "strict-origin-when-cross-origin"
</VirtualHost>
VHOST

else
    # ══════════════════════════════════════════
    #  ROOT MODE: DocumentRoot → webroot
    # ══════════════════════════════════════════
    cat > "$VHOST_FILE" << VHOST
# HICM V2025 — Root deployment: ${APP_URL}
# Generated by install.sh on $(date '+%Y-%m-%d %H:%M:%S')

<VirtualHost *:${APP_PORT}>
    ServerName ${APP_DOMAIN}
    DocumentRoot ${WEBROOT}

    <Directory ${WEBROOT}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        <IfModule mod_php.c>
            php_value upload_max_filesize 10M
            php_value post_max_size       10M
            php_value max_execution_time  300
            php_value memory_limit        256M
        </IfModule>
    </Directory>

    ErrorLog  \${APACHE_LOG_DIR}/hicm_error.log
    CustomLog \${APACHE_LOG_DIR}/hicm_access.log combined

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options        "SAMEORIGIN"
    Header always set X-XSS-Protection       "1; mode=block"
    Header always set Referrer-Policy        "strict-origin-when-cross-origin"
</VirtualHost>
VHOST

fi

# เปิด site ใหม่
a2ensite hicm-v2025.conf > /dev/null 2>&1

# ปิด default site เฉพาะตอน root deployment หรือ localhost
if [[ -z "$APP_SUBPATH" || "$APP_DOMAIN" == "localhost" ]]; then
    a2dissite 000-default.conf > /dev/null 2>&1 || true
fi

# ตรวจสอบ config
apache2ctl configtest 2>&1 | grep -q "Syntax OK" \
    && success "Apache VirtualHost → ${DEPLOY_MODE}" \
    || error "Apache config มีข้อผิดพลาด ตรวจสอบด้วย: sudo apache2ctl configtest"

# ─────────────────────────────────────────────────────────────────────────────
#  SECTION 8: File Permissions
# ─────────────────────────────────────────────────────────────────────────────
step "8/8  ตั้งค่า File Permissions"

UPLOADS="${WEBROOT}/assets/uploads"
mkdir -p "${UPLOADS}/avatars" "${UPLOADS}/manual_refs" \
         "${UPLOADS}/2025"    "${UPLOADS}/2026"

chown -R www-data:www-data "$WEBROOT"
find "$WEBROOT" -type d -exec chmod 755 {} \;
find "$WEBROOT" -type f -exec chmod 644 {} \;
chmod -R 775 "$UPLOADS"
chown -R www-data:www-data "$UPLOADS"
chmod 640 "$ENV_FILE"
chown www-data:www-data "$ENV_FILE"

# เพิ่ม current user เข้า www-data group
SUDO_USER_ACTUAL="${SUDO_USER:-$USER}"
id "$SUDO_USER_ACTUAL" &>/dev/null \
    && usermod -aG www-data "$SUDO_USER_ACTUAL" 2>/dev/null || true

success "Permissions ตั้งค่าแล้ว"

# ─────────────────────────────────────────────────────────────────────────────
#  RELOAD
# ─────────────────────────────────────────────────────────────────────────────
systemctl reload apache2

# ─────────────────────────────────────────────────────────────────────────────
#  VERIFY & SUMMARY
# ─────────────────────────────────────────────────────────────────────────────
echo
echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "  ${BOLD}${GREEN}✅  ติดตั้งเสร็จสมบูรณ์!${NC}"
echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

echo -e "\n${BOLD}Services:${NC}"
systemctl is-active --quiet apache2 \
    && echo -e "  Apache  ${GREEN}● running${NC}" \
    || echo -e "  Apache  ${RED}✗ stopped${NC}"
systemctl is-active --quiet mysql \
    && echo -e "  MySQL   ${GREEN}● running${NC}" \
    || echo -e "  MySQL   ${RED}✗ stopped${NC}"

php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;dbname=${DB_NAME};charset=utf8mb4','${DB_USER}','${DB_PASS}');
    \$c = \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    echo '  DB      \033[0;32m● connected\033[0m  (users: ' . \$c . \")\n\";
} catch(Exception \$e) {
    echo '  DB      \033[0;31m✗ ' . \$e->getMessage() . \"\033[0m\n\";
}
" 2>/dev/null || true

echo -e "\n${BOLD}ข้อมูลการเข้าถึงระบบ:${NC}"
echo -e "  URL         : ${CYAN}${BOLD}${APP_URL}${NC}"
echo -e "  Deploy mode : ${GREEN}${DEPLOY_MODE}${NC}"
echo -e "  Web root    : ${WEBROOT}"
if [[ -n "$APP_SUBPATH" ]]; then
    echo -e "  Apache      : Alias /${APP_SUBPATH} → ${WEBROOT}"
fi
echo -e "  .env        : ${WEBROOT}/.env"
echo -e "  Logs        : /var/log/apache2/hicm_{error,access}.log"

echo -e "\n${BOLD}บัญชีเริ่มต้น ${YELLOW}(⚠️ เปลี่ยนรหัสผ่านทันที!):${NC}"
echo -e "  Admin    : admin@hicm.gov.th   / ${YELLOW}admin123${NC}"
if [[ "${IMPORT_SAMPLE,,}" == "y" ]]; then
    echo -e "  Auditor  : auditor@hicm.gov.th / ${YELLOW}auditor123${NC}"
    echo -e "  Company  : company@example.com / ${YELLOW}company123${NC}"
fi

if [[ "$SCHEME" == "https" ]]; then
    echo -e "\n${YELLOW}📌 HTTPS:${NC}"
    echo -e "  VirtualHost นี้ตั้งค่าไว้บน port 80 (HTTP)"
    echo -e "  ถ้าต้องการ HTTPS สามารถตั้งค่าได้ด้วย:"
    echo -e "    ${CYAN}sudo apt install certbot python3-certbot-apache${NC}"
    echo -e "    ${CYAN}sudo certbot --apache -d ${APP_DOMAIN}${NC}"
    echo -e "  หรือถ้า SSL terminate ที่ load balancer/proxy ของมหาวิทยาลัย"
    echo -e "  ไม่จำเป็นต้องตั้งค่า SSL บน Apache นี้"
fi

echo -e "\n${BOLD}คำสั่งที่มีประโยชน์:${NC}"
echo -e "  sudo systemctl restart apache2"
echo -e "  sudo systemctl restart mysql"
echo -e "  sudo tail -f /var/log/apache2/hicm_error.log"
echo -e "  sudo apache2ctl -S                  # ดู VirtualHost ทั้งหมด"
echo -e "  cat ${WEBROOT}/.htaccess            # ตรวจสอบ RewriteBase"

# บันทึก MySQL root password
ROOT_PASS_FILE="/root/.hicm_mysql_root"
printf 'MYSQL_ROOT_PASSWORD=%s\nGENERATED=%s\n' \
    "$MYSQL_ROOT_PASS" "$(date '+%Y-%m-%d %H:%M:%S')" > "$ROOT_PASS_FILE"
chmod 600 "$ROOT_PASS_FILE"

echo -e "\n${YELLOW}⚠️  MySQL root password บันทึกไว้ที่: ${ROOT_PASS_FILE}${NC}"
echo -e "${YELLOW}⚠️  กรุณาเปลี่ยนรหัสผ่านระบบก่อนใช้งานจริง!${NC}"
echo -e "\n${BOLD}${BLUE}HICM V2025 พร้อมใช้งานแล้ว 🎉  →  ${CYAN}${APP_URL}${NC}\n"
