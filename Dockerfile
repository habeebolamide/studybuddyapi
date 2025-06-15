# Stage 1: Build frontend assets with Node
FROM node:20 as frontend

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# Stage 2: PHP backend
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy app from frontend stage
COPY --from=frontend /app /app

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Make deploy script executable
RUN chmod +x ./deploy.sh

# Run deployment setup (optional: move parts of deploy.sh here if you want more control)

# Expose Railway port
EXPOSE 8080

# Start Laravel's web server
CMD ["sh", "-c", "./deploy.sh && php artisan serve --host=0.0.0.0 --port=8080"]
