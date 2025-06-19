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

# 📦 Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 🧾 Copy composer files first and install deps early
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# 📁 Copy rest of the application code
COPY . .

# 🛡 Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

# ⚙️ Copy Nginx and Supervisor configs
COPY nginx.conf /etc/nginx/sites-available/default
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY deploy.sh /deploy.sh
RUN chmod +x /deploy.sh

# ✅ Configure PHP-FPM to listen on 0.0.0.0
RUN echo "listen = 0.0.0.0:9000" > /usr/local/etc/php-fpm.d/zz-docker.conf

# 🚪 Expose port for Railway (Nginx)
EXPOSE 8080

# 🚀 Start Supervisor (manages PHP-FPM + Nginx + your deploy script)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
