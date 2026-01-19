#!/bin/bash

echo "================================================"
echo "🧪 Test de Integración WordPress → Laravel API"
echo "================================================"

echo ""
echo "📋 1. Verificar API Key en .env"
echo "================================"
grep "VEXOGATE_API_KEY_SECRET" .env

echo ""
echo "📋 2. Verificar configuración actual"
echo "================================"
php artisan tinker --execute="
echo 'API Key configurada: ' . (config('vexogate.api_key_secret') ? '✅ SÍ' : '❌ NO') . PHP_EOL;
echo 'API Key valor: ' . config('vexogate.api_key_secret') . PHP_EOL;
echo 'Default Provider: ' . config('vexogate.default_provider') . PHP_EOL;
"

echo ""
echo "📋 3. Test manual del endpoint"
echo "================================"
echo "Enviando request simulada..."

curl -X POST https://api.webxdev.pro/api/v1/initiate \
  -H "Content-Type: application/json" \
  -H "X-VexoGate-API-Key: base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso=" \
  -d '{
    "domain_origin": "webxdev.pro",
    "merchant_wallet": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
    "merchant_order_id": "TEST_001",
    "fiat_amount": 10.00,
    "fiat_currency": "USD",
    "client_email": "test@example.com",
    "callback_url": "https://webxdev.pro/wp-json/vexogate/v1/callback",
    "language": "es",
    "provider_slug": "transak"
  }' \
  -w "\n\nHTTP Status: %{http_code}\n" \
  -s

echo ""
echo "📋 4. Verificar últimos logs de Laravel"
echo "================================"
tail -n 20 storage/logs/laravel.log

echo ""
echo "================================================"
echo "✅ Test completado"
echo "================================================"
