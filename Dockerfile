# syntax=docker/dockerfile:1.7
#
# Supershop — production image (PHP 8.3 + Apache).
#
#   docker build -t supershop .
#   docker compose up -d          # app + MySQL, see docker-compose.yml
#
# The PHP dependencies and front-end assets are platform independent, so they
# are built once on the build machine ($BUILDPLATFORM) and copied into the
# runtime image for every target architecture (amd64 / arm64).

############################################
# 1. PHP dependencies (no dev packages)
############################################
FROM --platform=$BUILDPLATFORM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --no-progress --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

############################################
# 2. Front-end assets (Tailwind + Alpine via Vite)
############################################
FROM --platform=$BUILDPLATFORM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY tailwind.config.js postcss.config.js vite.config.js ./
COPY resources ./resources
COPY app/Models ./app/Models
# Tailwind also scans Laravel's pagination views for class names.
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

############################################
# 3. Runtime
############################################
FROM php:8.3-apache-bookworm

LABEL org.opencontainers.image.title="Supershop" \
      org.opencontainers.image.description="Supermarket management: online shop, point of sale with barcode scanning, inventory ledger, orders and reports (Laravel 11)." \
      org.opencontainers.image.source="https://github.com/Rafi-Siddiki/SuperShop-Management-App"

# PHP extensions: MySQL, images/barcodes (gd), PDFs, zip, intl, opcache.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl zip bcmath opcache

# PHP and Apache configuration.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-supershop.ini"
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers \
 && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Discover packages (writes bootstrap/cache), link public storage, prepare writable dirs.
RUN php artisan package:discover --ansi \
 && ln -sfn ../storage/app/public public/storage \
 && mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
             storage/framework/sessions storage/framework/views storage/logs \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/supershop-entrypoint
# Strip Windows line endings in case the repo was checked out with CRLF.
RUN sed -i 's/\r$//' /usr/local/bin/supershop-entrypoint && chmod +x /usr/local/bin/supershop-entrypoint

# Sensible container defaults; override with environment variables.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_TIMEZONE=Asia/Dhaka \
    LOG_CHANNEL=stderr \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_MAILER=log \
    DB_CONNECTION=mysql \
    DB_PORT=3306

EXPOSE 80
VOLUME ["/var/www/html/storage"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'

ENTRYPOINT ["supershop-entrypoint"]
CMD ["apache2-foreground"]
