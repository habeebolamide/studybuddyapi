# 🐘 Use official PHP image with FPM
FROM php:8.2-fpm

# 🛠 Set working directory
WORKDIR /var/www

# 🧰 Install system dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libjpeg-dev \
    libfreetype6-dev \
    supervisor \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip exif pcntl gd

# 📦 Install Composer (via multi-stage)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 📁 Copy full Laravel app (including composer.json, app/, bootstrap/, etc.)
COPY . .

# 📦 Install PHP dependencies *after* all app files are copied
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# 🛡 Set proper permissions for Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# ⚙️ Configure PHP-FPM to listen on 0.0.0.0:9000
RUN echo "listen = 0.0.0.0:9000" > /usr/local/etc/php-fpm.d/zz-docker.conf

# 🔧 Copy config files
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY deploy.sh /deploy.sh
RUN chmod +x /deploy.sh

# 🚪 Expose Nginx port (Railway uses 8080)
EXPOSE 8080

# 🚀 Start everything via Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
