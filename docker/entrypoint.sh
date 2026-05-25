#!/bin/bash
# HICM V2025 - Docker Entrypoint Script
set -e

MYSQL_OPTS="-h${DB_HOST:-db} -u${DB_USERNAME:-root} -p${DB_PASSWORD:-hicm_secret} --ssl=0"

echo "============================================"
echo "  HICM V2025 Assessment System"
echo "  Starting up..."
echo "============================================"

# ── Fix upload directory permissions ─────────────────────────────────────────
echo "[1/3] Setting upload directory permissions..."
mkdir -p /var/www/html/assets/uploads/avatars
mkdir -p /var/www/html/assets/uploads/manual_refs
mkdir -p /var/www/html/assets/uploads/2026
chown -R www-data:www-data /var/www/html/assets/uploads
chmod -R 775 /var/www/html/assets/uploads

# ── Wait for MariaDB to be ready ─────────────────────────────────────────────
echo "[2/3] Waiting for database connection..."
MAX_TRIES=30
COUNT=0
until mysqladmin ping $MYSQL_OPTS --silent 2>/dev/null; do
    COUNT=$((COUNT+1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "ERROR: Database did not become ready in time. Exiting."
        exit 1
    fi
    echo "  → Waiting for database... ($COUNT/$MAX_TRIES)"
    sleep 2
done
echo "  ✓ Database is ready."

# ── Check and seed database ───────────────────────────────────────────────────
echo "[3/3] Checking database..."
TABLE_COUNT=$(mysql $MYSQL_OPTS \
    -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME:-hicm_v2025}';" \
    --skip-column-names 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt "5" ]; then
    echo "  → Database is empty. Importing initial data..."
    mysql $MYSQL_OPTS "${DB_NAME:-hicm_v2025}" < /var/www/html/database/schema.sql
    mysql $MYSQL_OPTS "${DB_NAME:-hicm_v2025}" < /var/www/html/database/insert_indicators.sql
    echo "  ✓ Database imported successfully."
else
    echo "  ✓ Database already has data ($TABLE_COUNT tables). Skipping import."
fi

echo ""
echo "============================================"
echo "  HICM V2025 is ready!"
echo "  URL: http://localhost:${APP_PORT:-8080}"
echo "============================================"
echo ""

# ── Start Apache ──────────────────────────────────────────────────────────────
exec apache2-foreground
