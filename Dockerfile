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

# 📦 Install Composer (multi-stage)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 📁 Copy Laravel app (including composer files)
COPY . .

# 📦 Install PHP dependencies after code is copied
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# 🛡 Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# ⚙️ Configure PHP-FPM to listen on all interfaces (good for Nginx)
RUN echo "listen = 0.0.0.0:9000" > /usr/local/etc/php-fpm.d/zz-docker.conf

# 🔧 Copy configuration files
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY deploy.sh /deploy.sh
RUN chmod +x /deploy.sh

# 🚪 Expose Nginx port expected by Railway
EXPOSE 8080

# 🚀 Start Supervisor to manage PHP, Nginx, and queue workers
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
