#!/bin/bash

# Ensure script stops on errors
set -e

echo "🔧 Running Laravel deployment tasks..."

# Clear caches to prevent stale configs
php artisan optimize:clear

# Cache configs, routes, views, events for performance
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run migrations
php artisan migrate --force

# (Optional) Create storage symlink for public files
php artisan storage:link || true

echo "✅ Deployment tasks completed."

# Start PHP-FPM to keep the container running
exec php-fpm
