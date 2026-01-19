<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VexoGate Protocol Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración del sistema de pagos descentralizado VexoGate
    |
    */

    // === BLOCKCHAIN CONFIGURATION ===

    /**
     * Polygon RPC URL (Infura, Alchemy, QuickNode, etc.)
     */
    'polygon_rpc_url' => env('POLYGON_RPC_URL', 'https://polygon-rpc.com'),

    /**
     * Network: mainnet or testnet (mumbai)
     */
    'polygon_network' => env('POLYGON_NETWORK', 'mainnet'),

    /**
     * Chain ID: 137 (Mainnet) o 80001 (Mumbai Testnet)
     */
    'polygon_chain_id' => env('POLYGON_CHAIN_ID', 137),

    /**
     * USDC Contract Address
     * Mainnet: 0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174
     * Mumbai: 0x0FA8781a83E46826621b3BC094Ea2A0212e71B23
     */
    'usdc_contract_address' => env('USDC_CONTRACT_ADDRESS', '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174'),

    // === MASTER WALLET (Gas Station) ===

    /**
     * Master Wallet Private Key (para inyección de gas)
     * CRÍTICO: Debe guardarse en .env, NUNCA en código
     */
    'master_wallet_private_key' => env('MASTER_WALLET_PRIVATE_KEY'),

    /**
     * Cantidad de MATIC a inyectar por orden
     */
    'gas_injection_amount' => env('GAS_INJECTION_AMOUNT', 0.03),

    // === VEXOGATE WALLET (Fee Collection) ===

    /**
     * Wallet de VexoGate para recibir comisiones
     */
    'vexo_wallet_address' => env('VEXO_WALLET_ADDRESS'),

    /**
     * Comisión de VexoGate (porcentaje)
     */
    'vexo_fee_percentage' => env('VEXO_FEE_PERCENTAGE', 2.5),

    /**
     * Comisión mínima en USD
     */
    'vexo_fee_minimum' => env('VEXO_FEE_MINIMUM', 1.0),

    // === SECURITY & COMPLIANCE ===

    /**
     * Umbral para aprobación manual (USD)
     * Órdenes mayores a este monto requieren revisión manual
     */
    'manual_approval_threshold' => env('MANUAL_APPROVAL_THRESHOLD', 500),

    /**
     * Habilitar aprobación manual para todas las órdenes
     */
    'force_manual_approval' => env('FORCE_MANUAL_APPROVAL', false),

    /**
     * API Key para autenticación de merchants
     */
    'api_key_secret' => env('VEXOGATE_API_KEY_SECRET'),

    /**
     * Timeout para esperar confirmación de transacciones (segundos)
     */
    'transaction_timeout' => env('TRANSACTION_TIMEOUT', 60),

    // === PAYMENT PROVIDERS ===

    /**
     * Proveedores de rampa fiat->crypto soportados
     */
    'supported_providers' => [
        'transak' => [
            'name' => 'Transak',
            'url' => 'https://global.transak.com',
        ],
        'moonpay' => [
            'name' => 'MoonPay',
            'url' => 'https://www.moonpay.com',
        ],
        'banxa' => [
            'name' => 'Banxa',
            'url' => 'https://banxa.com',
        ],
    ],

    /**
     * Proveedor por defecto
     */
    'default_provider' => env('DEFAULT_PAYMENT_PROVIDER', 'transak'),

    // === WORKER CONFIGURATION ===

    /**
     * Intervalo de escaneo del worker (minutos)
     */
    'scan_interval' => env('VEXO_SCAN_INTERVAL', 1),

    /**
     * Número máximo de órdenes a procesar por ciclo
     */
    'max_orders_per_cycle' => env('MAX_ORDERS_PER_CYCLE', 50),

    /**
     * Reintentos máximos para transacciones fallidas
     */
    'max_transaction_retries' => env('MAX_TRANSACTION_RETRIES', 3),

    // === LEGAL & COMPLIANCE ===

    /**
     * Texto legal para mostrar en respuestas de API
     */
    'legal_disclaimer' => 'VexoGate is a decentralized gateway interface. We do not provide financial custody. Tech maintained by V.D.S. Labs.',

    /**
     * Nombre público del protocolo
     */
    'protocol_name' => 'VexoGate Protocol',

    /**
     * Versión de la API
     */
    'api_version' => 'v1',

];
