#!/bin/bash

echo "================================================"
echo "🔒 Corregir Configuración de Seguridad"
echo "================================================"

echo ""
echo "⚠️  PROBLEMA DETECTADO:"
echo "   APP_KEY y VEXOGATE_API_KEY_SECRET tienen el mismo valor"
echo "   Esto es un riesgo de seguridad crítico."
echo ""
echo "✅ SOLUCIÓN:"
echo "   Generar un nuevo APP_KEY único para Laravel"
echo "   Mantener VEXOGATE_API_KEY_SECRET para WordPress"
echo ""

# Backup del .env actual
echo "📦 Creando backup de .env..."
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
echo "✓ Backup creado"

# Generar nuevo APP_KEY
echo ""
echo "🔑 Generando nuevo APP_KEY de Laravel..."
php artisan key:generate --force
echo "✓ Nuevo APP_KEY generado"

# Verificar que VEXOGATE_API_KEY_SECRET exista y sea correcto
echo ""
echo "🔍 Verificando VEXOGATE_API_KEY_SECRET..."
if grep -q 'VEXOGATE_API_KEY_SECRET="base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso="' .env; then
    echo "✓ VEXOGATE_API_KEY_SECRET está correcto"
else
    echo "⚠️  Corrigiendo VEXOGATE_API_KEY_SECRET..."
    if grep -q "VEXOGATE_API_KEY_SECRET" .env; then
        sed -i 's/VEXOGATE_API_KEY_SECRET=.*/VEXOGATE_API_KEY_SECRET="base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso="/' .env
    else
        echo 'VEXOGATE_API_KEY_SECRET="base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso="' >> .env
    fi
    echo "✓ VEXOGATE_API_KEY_SECRET configurado"
fi

# Verificar MASTER_WALLET_PRIVATE_KEY
echo ""
echo "🔍 Verificando MASTER_WALLET_PRIVATE_KEY..."
CURRENT_MASTER_KEY=$(grep "MASTER_WALLET_PRIVATE_KEY=" .env | cut -d '=' -f2)

if [[ $CURRENT_MASTER_KEY == 0x* ]]; then
    echo "✓ MASTER_WALLET_PRIVATE_KEY tiene prefijo 0x correcto"
else
    echo "⚠️  Agregando prefijo 0x a MASTER_WALLET_PRIVATE_KEY..."
    sed -i "s/MASTER_WALLET_PRIVATE_KEY=\(.*\)/MASTER_WALLET_PRIVATE_KEY=0x\1/" .env
    echo "✓ Prefijo 0x agregado"
fi

# Limpiar caches
echo ""
echo "🧹 Limpiando caches de Laravel..."
php artisan config:clear
php artisan cache:clear
php artisan config:cache

# Verificar configuración final
echo ""
echo "================================================"
echo "✅ Verificación Final"
echo "================================================"

NEW_APP_KEY=$(grep "APP_KEY=" .env | cut -d '=' -f2)
VEXO_API_KEY=$(grep "VEXOGATE_API_KEY_SECRET=" .env | cut -d '=' -f2 | tr -d '"')
MASTER_KEY=$(grep "MASTER_WALLET_PRIVATE_KEY=" .env | cut -d '=' -f2)

echo ""
echo "APP_KEY (Laravel encryption):"
echo "  $NEW_APP_KEY"
echo ""
echo "VEXOGATE_API_KEY_SECRET (WordPress auth):"
echo "  $VEXO_API_KEY"
echo ""
echo "MASTER_WALLET_PRIVATE_KEY:"
echo "  ${MASTER_KEY:0:10}...${MASTER_KEY: -10} (ocultado por seguridad)"
echo ""

if [[ "$NEW_APP_KEY" != "$VEXO_API_KEY" ]]; then
    echo "✅ APP_KEY y VEXOGATE_API_KEY_SECRET son diferentes (CORRECTO)"
else
    echo "❌ ERROR: APP_KEY y VEXOGATE_API_KEY_SECRET siguen siendo iguales"
    exit 1
fi

if [[ $MASTER_KEY == 0x* ]]; then
    echo "✅ MASTER_WALLET_PRIVATE_KEY tiene prefijo 0x (CORRECTO)"
else
    echo "❌ ERROR: MASTER_WALLET_PRIVATE_KEY no tiene prefijo 0x"
    exit 1
fi

echo ""
echo "================================================"
echo "🎉 Configuración de seguridad corregida!"
echo "================================================"
echo ""
echo "⚠️  IMPORTANTE: Si ya tenías órdenes creadas con private keys"
echo "   encriptadas, NO podrás desencriptarlas porque el APP_KEY cambió."
echo "   Esto es normal si es la primera vez que configuras el sistema."
echo ""
echo "📝 Próximo paso: bash test-wordpress-integration.sh"
echo ""
