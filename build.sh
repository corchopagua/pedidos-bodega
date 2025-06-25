#!/bin/bash
echo "🚀 Iniciando build para Railway..."

# Instalar dependencias optimizadas para producción
composer install --no-dev --optimize-autoloader --no-interaction

# Verificación (opcional)
ls -lh database/database.sqlite

# Optimizar cachés para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configurar permisos (opcional pero recomendable)
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chmod 664 database/database.sqlite

echo "✅ Build completado exitosamente!"
