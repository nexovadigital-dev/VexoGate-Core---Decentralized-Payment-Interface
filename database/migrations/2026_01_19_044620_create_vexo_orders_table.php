<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Estructura VexoGate Protocol v1.0
     */
    public function up()
    {
        Schema::create('vexo_orders', function (Blueprint $table) {
            $table->id();

            // --- 1. IDENTIFICACIÓN Y RASTREO (Anti-Fraude) ---
            $table->string('domain_origin')->index(); // Ej: tienda-cliente.com
            $table->string('merchant_order_id');      // ID original de WooCommerce
            $table->string('callback_url');           // Webhook para confirmar pago
            $table->string('client_email')->nullable(); // Para rastreo en caso de soporte
            
            // --- 2. DATOS FINANCIEROS ---
            $table->string('provider_slug');          // 'transak', 'moonpay', 'banxa', 'stripe_crypto'
            $table->string('fiat_currency', 3);       // USD, EUR, AUD
            $table->decimal('fiat_amount', 10, 2);    // Monto original
            
            // --- 3. INFRAESTRUCTURA DE CUSTODIA (Vexo Systems) ---
            $table->string('temp_wallet_address');    // La wallet generada para esta orden
            $table->text('temp_private_key');         // ENCRIPTADA (AES-256) - ¡CRÍTICO!
            $table->string('merchant_dest_wallet');   // Wallet final del cliente (donde quiere recibir)
            
            // --- 4. ESTADOS DEL PROCESO (Granularidad para Debug) ---
            // waiting_payment: Esperando que el cliente pague
            // funds_detected: Dinero visto en blockchain
            // gas_injected: Se envió MATIC para pagar fees
            // distributing: Se está enviando el dinero a destino
            // completed: Todo finalizado
            // manual_review: Bloqueado por seguridad o error
            // refunded: Devuelto al cliente
            $table->string('status')->default('waiting_payment')->index();
            
            // --- 5. LOGISTICA DE GAS Y FEES ---
            $table->decimal('crypto_received', 18, 6)->nullable(); // USDC Real recibido (Block value)
            $table->decimal('gas_cost_matic', 18, 9)->nullable();  // Costo operativo real
            $table->decimal('vexo_fee', 10, 2)->default(0);        // Tu ganancia retenida
            
            // --- 6. HUELLAS BLOCKCHAIN (Evidencia Immutable) ---
            $table->string('txid_in')->nullable();         // Hash entrada (Cliente -> Temp)
            $table->string('txid_gas')->nullable();        // Hash inyección gas (Master -> Temp)
            $table->string('txid_out_merchant')->nullable(); // Hash pago tienda (Temp -> Merchant)
            $table->string('txid_out_fee')->nullable();      // Hash pago comisión (Temp -> Vexo)

            // --- 7. CONTROL DE ERRORES ---
            $table->text('last_error_log')->nullable();      // Si falla el worker, aquí dice por qué
            $table->boolean('manual_override')->default(false); // Si tú forzaste la transacción

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('vexo_orders');
    }
};