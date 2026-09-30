# ─── Stage 1: Build frontend assets ──────────────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ─── Stage 2: PHP production runtime ─────────────────────────────────────────
FROM php:8.2-fpm-alpine

# ── System packages ───────────────────────────────────────────────────────────
RUN apk add --no-cache \
        nginx \
        supervisor \
        sqlite \
        sqlite-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libxml2-dev \
        oniguruma-dev \
        icu-dev \
        zip \
        unzip \
        curl \
        gettext \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
          pdo pdo_sqlite zip opcache mbstring gd dom xml intl bcmath \
    && rm -rf /var/cache/apk/*

# ── OPcache tuning ────────────────────────────────────────────────────────────
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.save_comments=1'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ── Upload limits (school logo, sign-in photo, visit photos, patient imports) ─
# Must stay below nginx client_max_body_size (20M).
RUN { \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=16M'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ── PHP dependencies (layer cached until composer.json / composer.lock change) ─
# Retries up to 3 times to handle transient GitHub/Packagist 504 errors.
COPY composer.json composer.lock ./
RUN for i in 1 2 3; do \
        composer install \
            --no-dev \
            --no-scripts \
            --no-autoloader \
            --no-interaction \
            --no-progress \
        && break; \
        echo "==> composer attempt $i/3 failed, retrying in 15 s..." && sleep 15; \
    done

# ── Application source ────────────────────────────────────────────────────────
COPY . .

# Copy compiled assets from the node stage
COPY --from=assets /app/public/build ./public/build

# Finalise autoloader now that full source is present
RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi

# The database is NOT created at build time. docker/start.sh creates/migrates
# it on boot inside the persistent data directory so data survives deploys.

# ── Runtime Docker config ─────────────────────────────────────────────────────
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/supervisord.conf    /etc/supervisord.conf
COPY docker/start.sh            /start.sh
RUN chmod +x /start.sh

# ── Permissions ───────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache \
    && chmod 664 database/database.sqlite

EXPOSE 8080

CMD ["/start.sh"]
