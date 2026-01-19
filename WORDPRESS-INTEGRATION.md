# 🔌 Integración WordPress Plugin ↔ Laravel API

## 📅 Fecha: 2026-01-19

---

## 🎯 Problema Actual

El plugin de WordPress v2.0.0 envía requests al Laravel API pero falla con el error:

```
Se produjo un error al procesar tu pedido.
Comprueba si hay cargos en tu método de pago...
```

**Causa más probable:** La API Key no está configurada en el `.env` del Laravel.

---

## ✅ Solución Paso a Paso

### En el Servidor Hostinger (Laravel API)

Conéctate vía SSH:

```bash
cd ~/public_html/vexogate
```

### **Paso 1: Configurar API Key**

Ejecuta el script de configuración:

```bash
bash configure-wordpress-integration.sh
```

Este script:
- ✅ Configura `VEXOGATE_API_KEY_SECRET` en tu `.env`
- ✅ Configura `DEFAULT_PAYMENT_PROVIDER=transak`
- ✅ Limpia las caches de Laravel
- ✅ Verifica que la configuración sea correcta

### **Paso 2: Verificar que funciona**

Ejecuta el test de integración:

```bash
bash test-wordpress-integration.sh
```

**Resultado esperado:**

```json
HTTP Status: 201

{
  "success": true,
  "data": {
    "order_id": 1,
    "temp_wallet": "0xABC...",
    "redirect_url": "https://global.transak.com/?walletAddress=...",
    "status": "waiting_payment"
  }
}
```

Si ves `"success": true` y `"redirect_url"`, ¡funciona! ✅

### **Paso 3: Probar desde WordPress**

Ahora intenta hacer un pedido desde tu tienda WordPress:

1. Ve a tu tienda en el navegador
2. Agrega un producto al carrito
3. Ve a checkout
4. Selecciona **VexoGate - Transak** (o MoonPay/Banxa)
5. Completa los datos de facturación
6. Haz clic en **Place Order**

**Resultado esperado:**
- Deberías ser redirigido a Transak para completar el pago
- Ya NO debes ver el error "Se produjo un error al procesar tu pedido"

---

## 🔍 Diagnóstico Detallado

### Si el test manual (cURL) funciona pero WordPress sigue fallando:

#### 1. Verificar API Key en WordPress

El plugin usa esta API Key hardcodeada:
```
X-VexoGate-API-Key: base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso=
```

Debe coincidir EXACTAMENTE con `VEXOGATE_API_KEY_SECRET` en tu `.env` de Laravel.

#### 2. Verificar configuración del Gateway en WordPress

Ve a: **WooCommerce → Settings → Payments → VexoGate - Transak → Manage**

Campos requeridos:
- **Enable/Disable:** ✅ Activado
- **Destination Wallet:** Tu wallet USDC Polygon (0x...)

**IMPORTANTE:** El wallet debe ser:
- ✅ Formato válido: `0x` + 40 caracteres hexadecimales
- ❌ NO usar contratos USDC: `0x2791Bca...` o `0x3c499c...`
- ✅ Usar TU wallet personal donde recibirás los fondos

#### 3. Activar Debug en WordPress

Edita `wp-config.php` y agrega:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Luego intenta hacer un pedido y revisa:
```
/wp-content/debug.log
```

Busca líneas que contengan `VexoGate` para ver el error exacto.

#### 4. Verificar CORS

Si el problema es CORS, agrega esto en Laravel:

Edita `config/cors.php`:

```php
'allowed_origins' => ['*'], // Temporalmente para testing
'allowed_headers' => ['*'],
'exposed_headers' => [],
```

Limpia cache:
```bash
php artisan config:clear
php artisan config:cache
```

---

## 🧪 Tests Adicionales

### Test 1: Verificar que Laravel está recibiendo requests

```bash
# En el servidor, monitorear logs en tiempo real
tail -f storage/logs/laravel.log
```

Deja esta terminal abierta, luego desde WordPress intenta hacer un pedido.

**Si NO ves NADA en los logs:** El problema es de red/firewall/CORS.

**Si ves un error 401 Unauthorized:** La API Key no coincide.

**Si ves un error 422 Validation:** Falta algún campo en el payload.

### Test 2: Verificar con Postman/Insomnia

```
POST https://api.webxdev.pro/api/v1/initiate

Headers:
  Content-Type: application/json
  X-VexoGate-API-Key: base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso=

Body (JSON):
{
  "domain_origin": "webxdev.pro",
  "merchant_wallet": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
  "merchant_order_id": "TEST_123",
  "fiat_amount": 10.00,
  "fiat_currency": "USD",
  "client_email": "test@example.com",
  "callback_url": "https://webxdev.pro/wp-json/vexogate/v1/callback",
  "language": "es",
  "provider_slug": "transak"
}
```

---

## 📝 Checklist de Verificación

### Laravel API

- [ ] `VEXOGATE_API_KEY_SECRET` configurado en `.env`
- [ ] API Key = `base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso=`
- [ ] `DEFAULT_PAYMENT_PROVIDER=transak` en `.env`
- [ ] Caches limpiadas (`php artisan config:cache`)
- [ ] Test manual con cURL funciona (HTTP 201)
- [ ] `storage/logs/laravel.log` muestra requests entrantes

### WordPress Plugin

- [ ] Plugin instalado y activado
- [ ] Gateway habilitado (VexoGate - Transak)
- [ ] Destination Wallet configurado (formato válido)
- [ ] Destination Wallet NO es un contrato USDC
- [ ] WP_DEBUG activado para ver logs
- [ ] No hay errores de JavaScript en consola del navegador

### Red y Conectividad

- [ ] WordPress puede alcanzar `api.webxdev.pro` (test con cURL desde WP)
- [ ] No hay firewall bloqueando requests
- [ ] CORS configurado correctamente en Laravel
- [ ] HTTPS funcionando (certificado SSL válido)

---

## 🆘 Si Nada Funciona

### Opción 1: Debug Avanzado en WordPress

Agrega esto temporalmente en `class-vexogate-base-gateway.php` línea 155:

```php
$response = $this->send_api_request( $payload );

// DEBUG: Ver response exacto
error_log( 'VexoGate API Response: ' . print_r( $response, true ) );
if ( ! is_wp_error( $response ) ) {
    $body = wp_remote_retrieve_body( $response );
    $code = wp_remote_retrieve_response_code( $response );
    error_log( 'VexoGate API Body: ' . $body );
    error_log( 'VexoGate API Status: ' . $code );
}
```

Revisa `/wp-content/debug.log` después de intentar un pedido.

### Opción 2: Test desde el servidor WordPress

Conéctate al servidor de WordPress vía SSH:

```bash
curl -X POST https://api.webxdev.pro/api/v1/initiate \
  -H "Content-Type: application/json" \
  -H "X-VexoGate-API-Key: base64:3APmWaqUdfPuEMWo4fRveo758xx4RAQvawDljHZsLso=" \
  -d '{
    "domain_origin": "webxdev.pro",
    "merchant_wallet": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
    "merchant_order_id": "TEST",
    "fiat_amount": 10.00,
    "fiat_currency": "USD",
    "client_email": "test@example.com",
    "callback_url": "https://webxdev.pro/wp-json/vexogate/v1/callback",
    "language": "es",
    "provider_slug": "transak"
  }'
```

Si funciona desde el servidor WordPress, el problema NO es de red.

---

## 📞 Información para Soporte

Si necesitas ayuda adicional, recopila:

1. ✅ Output de `bash test-wordpress-integration.sh`
2. ✅ Últimas 50 líneas de `storage/logs/laravel.log` (Laravel)
3. ✅ Contenido de `/wp-content/debug.log` (WordPress)
4. ✅ Screenshot del error en el checkout
5. ✅ Configuración del gateway (Settings → Payments)

---

## 🎯 Resultado Final Esperado

**Cuando todo funcione correctamente:**

1. Cliente selecciona gateway en checkout
2. Cliente hace clic en "Place Order"
3. WordPress envía POST a Laravel API ✅
4. Laravel retorna `redirect_url` ✅
5. Cliente es redirigido a Transak/MoonPay/Banxa ✅
6. Cliente completa pago con tarjeta o crypto ✅
7. Laravel procesa el pago y envía webhook a WordPress ✅
8. WordPress marca la orden como "Processing" o "Completed" ✅
9. Cliente recibe email de confirmación ✅

---

**Mantener este documento actualizado según se resuelvan problemas.**
