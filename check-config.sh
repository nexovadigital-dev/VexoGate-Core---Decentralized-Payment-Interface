#!/bin/bash

echo "================================================"
echo "🔍 Verificación de Configuración VexoGate"
echo "================================================"

echo ""
echo "📋 Variables de Sesión en .env:"
echo "================================"
grep -E "SESSION_|APP_DEBUG|APP_ENV" .env

echo ""
echo "📋 Estado de Base de Datos:"
echo "================================"
php artisan db:show 2>/dev/null || echo "⚠️  No se pudo conectar a la base de datos"

echo ""
echo "📋 Verificar tabla sessions:"
echo "================================"
php artisan tinker --execute="echo \Schema::hasTable('sessions') ? '✅ Tabla sessions existe' : '❌ Tabla sessions NO existe';"

echo ""
echo "📋 Verificar usuario admin:"
echo "================================"
php artisan tinker --execute="echo 'Total usuarios: ' . \App\Models\User::count();"

echo ""
echo "📋 Archivos y Permisos:"
echo "================================"
ls -la storage/framework/sessions/ | head -5
echo "..."
ls -la storage/logs/

echo ""
echo "================================================"
