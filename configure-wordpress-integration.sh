#!/bin/bash

echo "================================================"
echo "🔧 Configurar Integración con WordPress"
echo "================================================"

echo ""
echo "Este script configurará tu .env para que funcione con el plugin de WordPress"
echo ""

# API Key que usa el plugin de WordPress
WORDPRESS_API_KEY="base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso="

echo "📝 Paso 1: Verificar si VEXOGATE_API_KEY_SECRET ya existe en .env"
if grep -q "VEXOGATE_API_KEY_SECRET" .env; then
    echo "✓ La variable ya existe. Actualizando valor..."
    sed -i "s/VEXOGATE_API_KEY_SECRET=.*/VEXOGATE_API_KEY_SECRET=\"${WORDPRESS_API_KEY}\"/" .env
else
    echo "✓ Agregando nueva variable..."
    echo "" >> .env
    echo "# WordPress Integration" >> .env
    echo "VEXOGATE_API_KEY_SECRET=\"${WORDPRESS_API_KEY}\"" >> .env
fi

echo ""
echo "📝 Paso 2: Verificar/Configurar DEFAULT_PAYMENT_PROVIDER"
if grep -q "DEFAULT_PAYMENT_PROVIDER" .env; then
    echo "✓ DEFAULT_PAYMENT_PROVIDER ya existe"
else
    echo "✓ Agregando DEFAULT_PAYMENT_PROVIDER=transak"
    echo "DEFAULT_PAYMENT_PROVIDER=transak" >> .env
fi

echo ""
echo "📝 Paso 3: Limpiar caches de Laravel"
php artisan config:clear
php artisan cache:clear
php artisan config:cache

echo ""
echo "📝 Paso 4: Verificar configuración"
php artisan tinker --execute="
echo '✅ API Key: ' . config('vexogate.api_key_secret') . PHP_EOL;
echo '✅ Default Provider: ' . config('vexogate.default_provider') . PHP_EOL;
"

echo ""
echo "================================================"
echo "✅ Configuración completada!"
echo "================================================"
echo ""
echo "Ahora ejecuta: bash test-wordpress-integration.sh"
echo ""
