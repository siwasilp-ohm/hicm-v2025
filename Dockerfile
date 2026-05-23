# ============================================================
#  HICM V2025 Assessment System
#  Base: PHP 8.2 + Apache (matches XAMPP dev environment)
# ============================================================

FROM php:8.2-apache

LABEL maintainer="HICM V2025" \
      description="HICM V2025 - Healthy Industry Certificate Model Assessment System" \
      version="1.0.0"

# ── System dependencies ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
        default-mysql-client \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── PHP Extensions ────────────────────────────────────────────────────────────
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        exif \
        opcache \
        intl \
        bcmath

# ── Apache configuration ──────────────────────────────────────────────────────
RUN a2enmod rewrite deflate expires headers

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# ── PHP configuration ─────────────────────────────────────────────────────────
COPY docker/php.ini /usr/local/etc/php/conf.d/hicm.ini

# ── Application files ─────────────────────────────────────────────────────────
WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

# ── Upload directory permissions ──────────────────────────────────────────────
RUN mkdir -p assets/uploads/avatars \
             assets/uploads/manual_refs \
             assets/uploads/2026 \
    && chown -R www-data:www-data assets/uploads \
    && chmod -R 775 assets/uploads

# ── Entrypoint ────────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
