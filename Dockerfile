# Stage 1: Build frontend assets with Node
FROM node:20 as frontend

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# Stage 2: Laravel Backend
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy built app
COPY --from=frontend /app /app

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Clear and cache Laravel config
RUN php artisan config:clear && php artisan config:cache

# Expose port Railway expects
EXPOSE 8080

# Start Laravel's built-in web server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
