# Stage 1: Build frontend assets with Node
FROM node:20 as frontend

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# Stage 2: Laravel Backend - PHP
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy built project from frontend stage
COPY --from=frontend /app /app

# Make deploy.sh executable
RUN chmod +x ./deploy.sh

# Run Laravel setup in build (optional but helpful)
RUN composer install --no-dev --optimize-autoloader \
 && php artisan config:clear \
 && php artisan config:cache

# Start Laravel server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
