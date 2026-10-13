# ============================================================================
# NOVURA MARKETPLACE - DOCKERFILE (PHP 8.3 + Apache)
# Path: Dockerfile (repository root)
#
# Configure the app with environment variables on your host (never in code):
#   NOVURA_APP_ENV=production
#   NOVURA_BASE_URL=https://your-app.onrender.com      (no trailing slash)
#   NOVURA_DB_HOST / NOVURA_DB_PORT / NOVURA_DB_NAME / NOVURA_DB_USER / NOVURA_DB_PASS
#   NOVURA_PAYSTACK_PUBLIC_KEY / NOVURA_PAYSTACK_SECRET_KEY
# ============================================================================

FROM php:8.3-apache

# PDO MySQL driver (the app talks to MySQL through PDO)
RUN docker-php-ext-install pdo_mysql \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Apache hardening + HTTPS detection behind the host's proxy
RUN printf '%s\n' \
    'ServerName localhost' \
    'ServerTokens Prod' \
    'ServerSignature Off' \
    '' \
    '# Treat requests as HTTPS when the host proxy says so (secure cookies, HSTS)' \
    'SetEnvIf X-Forwarded-Proto "^https$" HTTPS=on' \
    '' \
    '# No directory listings; no dotfiles or data files over the web' \
    '<Directory /var/www/html>' \
    '    Options -Indexes +FollowSymLinks' \
    '</Directory>' \
    '<FilesMatch "(^\.|\.(sql|md|log|dist|lock)$)">' \
    '    Require all denied' \
    '</FilesMatch>' \
    '' \
    '# Application code must never be requested directly' \
    '<DirectoryMatch "^/var/www/html/(config|includes|database|logs)(/|$)">' \
    '    Require all denied' \
    '</DirectoryMatch>' \
    '' \
    '# Uploaded files are served as plain files and can never run as PHP' \
    '<Directory /var/www/html/uploads>' \
    '    php_admin_flag engine off' \
    '    Options -Indexes -ExecCGI' \
    '</Directory>' \
    > /etc/apache2/conf-available/novura.conf \
    && a2enconf novura

WORKDIR /var/www/html
COPY . /var/www/html

# Writable folders for PHP error logs and vendor/banner/product uploads
RUN mkdir -p logs uploads/products uploads/vendors uploads/banners \
    && chown -R www-data:www-data logs uploads

# Render (and similar hosts) tell the container which port to listen on via $PORT
ENV PORT=10000
EXPOSE 10000

CMD sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf \
    && exec apache2-foreground
