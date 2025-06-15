# Stage 1: Build frontend assets with Node
FROM node:20 as frontend

WORKDIR /app

# Copy only frontend files to install first (caching optimization)
COPY package*.json ./

RUN npm install

# Copy the rest of the app
COPY . .

# Build assets
RUN npm run build

# Stage 2: Backend - Laravel with PHP
FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy built app from previous stage
COPY --from=frontend /app /app

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Make deploy.sh executable and run it
RUN chmod +x ./deploy.sh && ./deploy.sh

# Expose the default php-fpm port
EXPOSE 9000

CMD ["php-fpm"]
