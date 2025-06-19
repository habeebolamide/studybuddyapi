#!/bin/bash
set -e

echo "🔧 Running Laravel deployment tasks..."

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan migrate --force
php artisan storage:link || true

echo "✅ Deployment complete."
