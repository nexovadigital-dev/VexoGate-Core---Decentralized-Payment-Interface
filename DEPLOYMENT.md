# 🚀 VexoGate Protocol - Deployment Guide (Hostinger)

Guía completa de despliegue del backend VexoGate Protocol en Hostinger.

---

## 📋 **Prerequisitos**

Antes de comenzar, asegúrate de tener:

- ✅ Acceso SSH a tu servidor Hostinger
- ✅ PHP 8.2 o superior con extensiones: `gmp`, `bcmath`, `mbstring`, `xml`, `curl`
- ✅ MySQL o PostgreSQL
- ✅ Composer instalado
- ✅ Node.js y npm (para compilar assets)
- ✅ Una wallet Polygon con MATIC para la Master Wallet
- ✅ Una wallet Polygon para recibir comisiones (Vexo Wallet)

---

## 🔧 **Paso 1: Clonar el Repositorio**

Conéctate a tu servidor Hostinger vía SSH:

```bash
ssh usuario@tudominio.com
```

Navega al directorio web (normalmente `public_html`):

```bash
cd ~/public_html
```

Clona el branch específico:

```bash
git clone --branch claude/vexogate-protocol-setup-nYq4r \
  https://github.com/nexovadigital/VexoGate-Core---Decentralized-Payment-Interface.git vexogate

cd vexogate
```

---

## 📦 **Paso 2: Instalar Dependencias**

Instala las dependencias de PHP y Node.js:

```bash
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

---

## 🔐 **Paso 3: Configurar Entorno**

Copia el archivo `.env.example` a `.env`:

```bash
cp .env.example .env
```

Genera la clave de aplicación:

```bash
php artisan key:generate
```

Edita el archivo `.env` con tus credenciales:

```bash
nano .env
```

**Configuraciones críticas que debes cambiar:**

```env
# URL de tu aplicación
APP_URL=https://api.webxdev.pro

# Base de datos
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nombre_de_tu_bd
DB_USERNAME=usuario_bd
DB_PASSWORD=contraseña_segura

# BLOCKCHAIN - Master Wallet (debe tener MATIC)
MASTER_WALLET_PRIVATE_KEY=0xTU_CLAVE_PRIVADA_MASTER

# VexoGate Wallet (donde recibes comisiones)
VEXO_WALLET_ADDRESS=0xTU_WALLET_VEXOGATE

# API Key para autenticar merchants
VEXOGATE_API_KEY_SECRET=base64:GENERA_UNA_CLAVE_SEGURA
```

💡 **Generar API Key segura:**
```bash
php artisan key:generate --show
```

---

## 🗄️ **Paso 4: Configurar Base de Datos**

Ejecuta las migraciones:

```bash
php artisan migrate --force
```

Esto creará las tablas:
- `vexo_orders` (órdenes de pago)
- `users` (para admin de Filament)
- `cache`, `jobs` (sistema)

---

## 👤 **Paso 5: Crear Usuario Administrador**

Crea un usuario para acceder al panel Filament:

```bash
php artisan make:filament-user
```

Te pedirá:
- **Name:** Tu nombre
- **Email:** tu@email.com
- **Password:** contraseña segura

Guarda estas credenciales. Las usarás para acceder al panel admin.

---

## ⚙️ **Paso 6: Configurar Cron Job (Crítico)**

El sistema necesita ejecutar el scanner de órdenes cada minuto.

Edita el crontab:

```bash
crontab -e
```

Agrega esta línea (ajusta la ruta según tu instalación):

```bash
* * * * * cd /home/usuario/public_html/vexogate && php artisan vexo:scan-orders >> /dev/null 2>&1
```

Para verificar que se agregó:

```bash
crontab -l
```

---

## 🔒 **Paso 7: Permisos de Archivos**

Configura los permisos correctos:

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

Si usas un usuario diferente (ej: `u123456789`):

```bash
chown -R u123456789:u123456789 storage bootstrap/cache
```

---

## 🌐 **Paso 8: Configurar Servidor Web**

### **Para Apache (Hostinger usa Apache)**

Crea/edita `.htaccess` en la raíz:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

Asegúrate de que el `Document Root` apunte a `/public`:

En el panel de Hostinger:
1. Websites → Manage
2. Advanced → PHP Configuration
3. Document Root: `/public_html/vexogate/public`

---

## 🧪 **Paso 9: Verificar Instalación**

### **1. Verificar API**

```bash
curl https://api.webxdev.pro/up
```

Debería responder: `{"status":"ok"}`

### **2. Verificar Panel Admin**

Visita: `https://api.webxdev.pro/admin`

Inicia sesión con las credenciales del Paso 5.

### **3. Test de API (Postman/curl)**

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

Debería responder con:
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

## 🔍 **Paso 10: Monitoreo del Worker**

Para verificar que el scanner está funcionando:

```bash
# Ver logs del worker
tail -f storage/logs/laravel.log

# Ejecutar manualmente para debug
php artisan vexo:scan-orders --dry-run
```

---

## 🛡️ **Seguridad Post-Deployment**

1. **Desactivar debug:**
   ```env
   APP_DEBUG=false
   APP_ENV=production
   ```

2. **Limitar acceso al panel admin:**
   - Configura IP whitelist en Apache
   - Usa autenticación de 2 factores

3. **Backups automáticos:**
   ```bash
   # Agregar al crontab
   0 2 * * * mysqldump -u user -p'password' vexogate_db > /backups/vexogate_$(date +\%F).sql
   ```

4. **Monitoreo de Master Wallet:**
   - Asegúrate de que siempre tenga MATIC (min. 10 MATIC)
   - Configura alertas si el balance baja de 5 MATIC

---

## 🔄 **Actualizar el Sistema**

Para actualizar a una nueva versión:

```bash
cd ~/public_html/vexogate
git pull origin claude/vexogate-protocol-setup-nYq4r
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 📊 **Endpoints Disponibles**

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/api/v1/initiate` | POST | Iniciar orden de pago |
| `/api/v1/order/{id}/status` | GET | Consultar estado de orden |
| `/api/v1/webhook/callback` | POST | Recibir notificaciones |
| `/admin` | GET | Panel de administración |
| `/up` | GET | Health check |

---

## 🐛 **Troubleshooting**

### **Error: "Database connection failed"**
```bash
php artisan config:clear
php artisan cache:clear
# Verifica credenciales en .env
```

### **Error: "Class not found"**
```bash
composer dump-autoload
php artisan optimize
```

### **Worker no ejecuta:**
```bash
# Verifica que el cron está activo
crontab -l

# Ejecuta manualmente
php artisan vexo:scan-orders
```

### **Transacciones fallan:**
- Verifica que Master Wallet tenga MATIC
- Revisa logs: `storage/logs/laravel.log`
- Confirma que POLYGON_RPC_URL esté activo

---

## 📞 **Soporte**

- Repositorio: https://github.com/nexovadigital/VexoGate-Core---Decentralized-Payment-Interface
- Documentación API: `/api/v1/docs` (si se implementa)
- Issues: https://github.com/nexovadigital/VexoGate-Core---Decentralized-Payment-Interface/issues

---

## ✅ **Checklist Final**

- [ ] Repositorio clonado y dependencias instaladas
- [ ] `.env` configurado con credenciales correctas
- [ ] Migraciones ejecutadas
- [ ] Usuario admin creado
- [ ] Cron job configurado
- [ ] Permisos de archivos correctos
- [ ] API responde correctamente
- [ ] Panel admin accesible
- [ ] Master Wallet fondeada con MATIC
- [ ] Logs monitoreados

---

**🎉 ¡VexoGate Protocol está listo para procesar pagos descentralizados!**
