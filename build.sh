#!/bin/bash
echo "🚀 Iniciando build para Railway..."

# Instalar dependencias
composer install --no-dev --optimize-autoloader --no-interaction

# Crear directorio de base de datos si no existe
mkdir -p database

# Crear base de datos SQLite si no existe
if [ ! -f database/database.sqlite ]; then
    echo "📦 Creando base de datos SQLite..."
    touch database/database.sqlite
fi

# Ejecutar migraciones
php artisan migrate --force

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Configurar permisos
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chmod 664 database/database.sqlite

echo "✅ Build completado exitosamente!"