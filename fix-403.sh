#!/bin/bash

echo "================================================"
echo "🔧 VexoGate - Fix 403 FORBIDDEN"
echo "================================================"

# Paso 1: Actualizar código
echo ""
echo "📥 Paso 1: Actualizando código desde GitHub..."
git pull origin claude/vexogate-protocol-setup-nYq4r

# Paso 2: Limpiar TODAS las caches
echo ""
echo "🧹 Paso 2: Limpiando todas las caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
php artisan event:clear
php artisan optimize:clear

# Paso 3: Borrar archivos de sesión antiguos
echo ""
echo "🗑️  Paso 3: Eliminando sesiones antiguas..."
rm -rf storage/framework/sessions/*
rm -rf storage/framework/cache/*
rm -rf storage/framework/views/*

# Paso 4: Recrear caches optimizadas
echo ""
echo "⚡ Paso 4: Recreando caches optimizadas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Paso 5: Verificar permisos
echo ""
echo "🔐 Paso 5: Verificando permisos..."
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/framework/sessions
chmod -R 775 storage/framework/cache
chmod -R 775 storage/framework/views
chmod -R 775 storage/logs

# Paso 6: Verificar owner
echo ""
echo "👤 Paso 6: Ajustando propietario..."
chown -R $USER:$USER storage bootstrap/cache

echo ""
echo "================================================"
echo "✅ Proceso completado!"
echo "================================================"
echo ""
echo "Ahora intenta acceder a: https://api.webxdev.pro/admin"
echo ""
