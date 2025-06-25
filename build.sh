#!/bin/bash
echo "🚀 Iniciando build para Railway..."

composer install --no-dev --optimize-autoloader --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chmod 664 database/database.sqlite

echo "✅ Build completado exitosamente!"
