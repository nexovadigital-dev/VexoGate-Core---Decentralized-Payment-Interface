# 💎 VexoGate Protocol

**Pasarela de Pagos Descentralizada Self-Hosted para Comercios de Alto Riesgo**

VexoGate Protocol permite a comercios aceptar pagos en USDC (Polygon) usando tarjetas de crédito a través de proveedores como Transak/MoonPay, manteniendo custodia temporal de los fondos con seguridad blockchain.

---

## 🌟 Características Principales

- ✅ **Generación Nativa de Wallets:** Crea wallets Ethereum/Polygon usando curva elíptica secp256k1
- ✅ **Custodia Transitoria Segura:** Claves privadas encriptadas con AES-256
- ✅ **Gas Station Automatizado:** Inyección automática de MATIC para procesar transacciones USDC
- ✅ **Estados Granulares:** Sistema de seguimiento detallado del ciclo de vida de cada transacción
- ✅ **Panel de Administración:** Interfaz visual completa con FilamentPHP
- ✅ **Protocolos de Emergencia:** Acciones manuales para forzar, reembolsar o rescatar fondos
- ✅ **API RESTful:** Endpoints documentados para integración con e-commerce
- ✅ **Self-Hosted:** Control total de tu infraestructura de pagos

---

## 🚀 Quick Start

### 1. Instalación

```bash
git clone --branch claude/vexogate-protocol-setup-nYq4r URL
cd VexoGate-Core---Decentralized-Payment-Interface
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan make:filament-user
```

### 2. Configuración

Edita `.env` con tus credenciales blockchain.

### 3. Iniciar Worker

```bash
* * * * * cd /path && php artisan vexo:scan-orders
```

---

## 📡 API Endpoints

- **POST** `/api/v1/initiate` - Iniciar orden
- **GET** `/api/v1/order/{id}/status` - Consultar estado
- **GET** `/admin` - Panel de administración

Ver [DEPLOYMENT.md](DEPLOYMENT.md) para guía completa.

---

**🎯 VexoGate Protocol - Pagos descentralizados seguros**

*"VexoGate is a decentralized gateway interface. Tech maintained by V.D.S. Labs."*
