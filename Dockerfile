# Stage 1: Build frontend assets with Node
FROM node:20 as frontend

WORKDIR /app

# Copy only frontend files first (for caching)
COPY package*.json ./

RUN npm install

# Copy rest of the project and build assets
COPY . .
RUN npm run build

# Stage 2: Laravel Backend - PHP
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

# Copy built project from frontend stage
COPY --from=frontend /app /app

# Copy entrypoint scripts
COPY entrypoint.web.sh /usr/local/bin/entrypoint.web.sh
COPY entrypoint.worker.sh /usr/local/bin/entrypoint.worker.sh

# Make all shell scripts executable
RUN chmod +x ./deploy.sh \
    /usr/local/bin/entrypoint.web.sh \
    /usr/local/bin/entrypoint.worker.sh

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set default command to web entrypoint
CMD ["entrypoint.web.sh"]
