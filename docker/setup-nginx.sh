#!/bin/bash
# =============================================================================
#  Patch frontdoor nginx to proxy /assessment → hicm_app
#  Usage: sudo bash docker/setup-nginx.sh
# =============================================================================

CONTAINER="hicm-application-frontdoor-1"
CONF="/etc/nginx/conf.d/default.conf"

echo "▶ Patching nginx frontdoor for /assessment..."

# Already configured?
if docker exec "$CONTAINER" grep -q "location /assessment" "$CONF" 2>/dev/null; then
    echo "[OK] /assessment already configured in nginx"
    docker exec "$CONTAINER" nginx -s reload
    exit 0
fi

# Backup
docker exec "$CONTAINER" cp "$CONF" "${CONF}.bak"
echo "[OK] Backed up to ${CONF}.bak"

# Write location block to temp file inside container
docker exec "$CONTAINER" sh -c 'cat >> /tmp/assessment.conf << '\''NGINX_BLOCK'\''

    # ── HICM V2025 Assessment ─────────────────────────────────────────────
    location = /assessment {
        return 301 https://hicm.sut.ac.th/assessment/;
    }
    location /assessment/ {
        proxy_pass         http://hicm_app/;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
    }
    # ─────────────────────────────────────────────────────────────────────
NGINX_BLOCK
'

# Inject before the last closing brace of the https server block
docker exec "$CONTAINER" sh -c "
    BLOCK=\$(cat /tmp/assessment.conf)
    # Insert before the last line containing only '}'
    sed -i '0,/^}/! { /^}/{
        i\\
\$BLOCK
    }' $CONF
    rm /tmp/assessment.conf
"

# Test config
echo "▶ Testing nginx config..."
if docker exec "$CONTAINER" nginx -t; then
    docker exec "$CONTAINER" nginx -s reload
    echo ""
    echo "✅ Done! https://hicm.sut.ac.th/assessment is now active"
else
    echo "[ERROR] nginx config test failed — restoring backup"
    docker exec "$CONTAINER" cp "${CONF}.bak" "$CONF"
    exit 1
fi
