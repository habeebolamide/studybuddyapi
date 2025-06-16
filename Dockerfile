# Stage 1: Build frontend assets with Node
FROM node:20 as frontend

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
FROM php:8.2-cli

# Install deps
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

# Make deploy script executable
RUN chmod +x ./deploy.sh

EXPOSE 8080

# Run deploy script and start app
CMD ["./deploy.sh"]