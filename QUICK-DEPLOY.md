# ⚡ VexoGate Protocol - Quick Deploy (Hostinger)

## 🚀 Comandos para Clonar en tu Sitio Hostinger

### Paso 1: Conectar vía SSH a Hostinger

```bash
ssh u123456789@tudominio.com
```

*(Reemplaza `u123456789` con tu usuario de Hostinger)*

---

### Paso 2: Clonar el Branch

```bash
cd ~/public_html

git clone --branch claude/vexogate-protocol-setup-nYq4r \
  https://github.com/nexovadigital-dev/VexoGate-Core---Decentralized-Payment-Interface.git vexogate

cd vexogate
```

---

### Paso 3: Instalar Dependencias

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

---

### Paso 4: Configurar .env

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

**Variables CRÍTICAS a configurar:**

```env
# URL de tu API
APP_URL=https://api.webxdev.pro

# Base de datos (obtener de panel Hostinger)
DB_DATABASE=nombre_bd
DB_USERNAME=usuario_bd
DB_PASSWORD=contraseña_bd

# Master Wallet (debe tener MATIC)
MASTER_WALLET_PRIVATE_KEY=0xTU_CLAVE_PRIVADA

# VexoGate Wallet (donde recibes comisiones)
VEXO_WALLET_ADDRESS=0xTU_WALLET_VEXOGATE

# API Key (generar una segura)
VEXOGATE_API_KEY_SECRET=base64:GENERA_UNA_CLAVE_SEGURA
```

💡 **Para generar API Key segura:**
```bash
php artisan key:generate --show
```

---

### Paso 5: Migrar Base de Datos

```bash
php artisan migrate --force
```

---

### Paso 6: Crear Usuario Admin

```bash
php artisan make:filament-user
```

Ingresa:
- **Name:** Tu nombre
- **Email:** tu@email.com
- **Password:** contraseña segura

---

### Paso 7: Configurar Cron Job (CRÍTICO)

```bash
crontab -e
```

Agregar esta línea (ajusta la ruta):

```cron
* * * * * cd /home/u123456789/public_html/vexogate && php artisan vexo:scan-orders >> /dev/null 2>&1
```

Verificar:
```bash
crontab -l
```

---

### Paso 8: Permisos

```bash
chmod -R 755 storage bootstrap/cache
chown -R $USER:$USER storage bootstrap/cache
```

---

### Paso 9: Configurar Document Root (Panel Hostinger)

1. Ir a **Websites → Manage**
2. **Advanced → PHP Configuration**
3. **Document Root:** `/public_html/vexogate/public`
4. Guardar cambios

---

## ✅ Verificar Instalación

### 1. Health Check
```bash
curl https://api.webxdev.pro/up
```

**Debería responder:** `{"status":"ok"}`

---

### 2. Acceder al Panel Admin

Visita: **https://api.webxdev.pro/admin**

Inicia sesión con las credenciales del Paso 6.

---

### 3. Test de API

```bash
curl -X POST https://api.webxdev.pro/api/v1/initiate \
  -H "X-VexoGate-API-Key: base64:TU_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_order_id": "TEST-001",
    "domain_origin": "test.com",
    "callback_url": "https://test.com/webhook",
    "merchant_wallet": "0xTU_WALLET_DESTINO",
    "fiat_amount": 100,
    "fiat_currency": "USD",
    "client_email": "test@example.com"
  }'
```

**Debería responder con:**
```json
{
  "success": true,
  "data": {
    "order_id": 1,
    "temp_wallet": "0x...",
    "redirect_url": "https://global.transak.com/?..."
  }
}
```

---

## 🔧 Verificar Worker

Ejecutar manualmente para debug:

```bash
php artisan vexo:scan-orders --dry-run
```

Ver logs:
```bash
tail -f storage/logs/laravel.log
```

---

## 🎯 URLs Principales

| Recurso | URL |
|---------|-----|
| API Base | `https://api.webxdev.pro/api/v1` |
| Panel Admin | `https://api.webxdev.pro/admin` |
| Health Check | `https://api.webxdev.pro/up` |
| Iniciar Pago | `POST /api/v1/initiate` |
| Consultar Estado | `GET /api/v1/order/{id}/status` |

---

## ⚠️ Checklist Pre-Launch

- [ ] `.env` configurado correctamente
- [ ] Master Wallet tiene MATIC (mínimo 10 MATIC)
- [ ] VexoGate Wallet configurada
- [ ] Migraciones ejecutadas
- [ ] Usuario admin creado
- [ ] Cron job activo (verificar con `crontab -l`)
- [ ] Document Root apunta a `/public`
- [ ] API responde correctamente
- [ ] Panel admin accesible
- [ ] Logs monitoreados

---

## 📞 Soporte

Si encuentras problemas:

1. Revisa logs: `storage/logs/laravel.log`
2. Verifica configuración: `php artisan config:show vexogate`
3. Consulta documentación completa: [DEPLOYMENT.md](DEPLOYMENT.md)

---

**🎉 ¡VexoGate Protocol está listo para aceptar pagos!**
