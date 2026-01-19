<?php

namespace App\Console\Commands;

use App\Models\VexoOrder;
use App\Services\PolygonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * VexoGate Gas Station - Automated Order Processor
 *
 * Este comando debe ejecutarse cada minuto vía cron:
 * * * * * * cd /path/to/vexogate && php artisan vexo:scan-orders >> /dev/null 2>&1
 */
class ScanOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vexo:scan-orders
                            {--limit=50 : Número máximo de órdenes a procesar por ciclo}
                            {--dry-run : Ejecutar sin realizar transacciones reales}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Escanear y procesar órdenes de VexoGate automáticamente';

    private PolygonService $polygon;
    private bool $dryRun = false;

    public function __construct(PolygonService $polygon)
    {
        parent::__construct();
        $this->polygon = $polygon;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($this->dryRun) {
            $this->warn('🔶 DRY RUN MODE - No se realizarán transacciones reales');
        }

        $this->info("🚀 VexoGate Gas Station iniciado");
        $this->info("⏰ " . now()->format('Y-m-d H:i:s'));

        // Obtener órdenes procesables
        $orders = VexoOrder::processable()
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            $this->info("✅ No hay órdenes para procesar");
            return Command::SUCCESS;
        }

        $this->info("📦 Procesando {$orders->count()} órdenes...\n");

        $processed = 0;
        $errors = 0;

        foreach ($orders as $order) {
            try {
                $this->processOrder($order);
                $processed++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("❌ Error en orden #{$order->id}: {$e->getMessage()}");
                $order->markForManualReview($e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✅ Procesadas: {$processed}");
        if ($errors > 0) {
            $this->warn("⚠️  Errores: {$errors}");
        }

        return Command::SUCCESS;
    }

    /**
     * Procesar una orden según su estado
     */
    private function processOrder(VexoOrder $order)
    {
        $this->line("🔄 Orden #{$order->id} - Estado: {$order->status}");

        match ($order->status) {
            'waiting_payment' => $this->handleWaitingPayment($order),
            'funds_detected' => $this->handleFundsDetected($order),
            'gas_injected' => $this->handleGasInjected($order),
            'distributing' => $this->handleDistributing($order),
            default => $this->warn("   ⚠️  Estado desconocido: {$order->status}"),
        };
    }

    /**
     * ESTADO 1: Esperando pago del cliente
     * Monitorear si llegaron fondos USDC a la temp_wallet
     */
    private function handleWaitingPayment(VexoOrder $order)
    {
        $balance = $this->polygon->getUsdcBalance($order->temp_wallet_address);

        if ($balance > 0) {
            $this->info("   💰 Fondos detectados: {$balance} USDC");

            $order->crypto_received = $balance;
            $order->updateStatus('funds_detected');

            // Notificar al merchant (opcional)
            $this->notifyMerchant($order, 'funds_detected');
        } else {
            $this->line("   ⏳ Esperando pago...");
        }
    }

    /**
     * ESTADO 2: Fondos detectados
     * Verificar si tiene gas (MATIC) para poder mover el USDC
     */
    private function handleFundsDetected(VexoOrder $order)
    {
        $maticBalance = $this->polygon->getMaticBalance($order->temp_wallet_address);
        $requiredGas = config('vexogate.gas_injection_amount', 0.03);

        if ($maticBalance >= $requiredGas) {
            $this->info("   ⛽ Ya tiene gas suficiente: {$maticBalance} MATIC");
            $order->updateStatus('gas_injected');
        } else {
            $this->info("   💉 Inyectando gas: {$requiredGas} MATIC");

            if (!$this->dryRun) {
                $txHash = $this->polygon->sendMatic($order->temp_wallet_address, $requiredGas);

                $order->txid_gas = $txHash;
                $order->gas_cost_matic = $requiredGas;
                $order->updateStatus('gas_injected');

                $this->info("   ✅ Gas inyectado: {$txHash}");

                // Esperar confirmación
                $this->line("   ⏳ Esperando confirmación...");
                if ($this->polygon->waitForConfirmation($txHash)) {
                    $this->info("   ✅ Confirmado");
                } else {
                    throw new \Exception("Timeout esperando confirmación de gas");
                }
            }
        }
    }

    /**
     * ESTADO 3: Gas inyectado
     * Verificar que el gas fue confirmado y cambiar a distributing
     */
    private function handleGasInjected(VexoOrder $order)
    {
        // Verificar nuevamente balance de MATIC
        $maticBalance = $this->polygon->getMaticBalance($order->temp_wallet_address);

        if ($maticBalance < 0.01) {
            throw new \Exception("Gas insuficiente después de inyección: {$maticBalance} MATIC");
        }

        // Verificar si requiere aprobación manual
        if ($order->requiresManualApproval() && !$order->manual_override) {
            $this->warn("   ⚠️  Requiere aprobación manual (monto > threshold)");
            $order->updateStatus('manual_review', 'Awaiting manual approval');
            return;
        }

        $this->info("   🚀 Iniciando distribución de fondos");
        $order->updateStatus('distributing');
    }

    /**
     * ESTADO 4: Distribuyendo fondos
     * Enviar USDC al merchant y la comisión a VexoGate
     */
    private function handleDistributing(VexoOrder $order)
    {
        $merchantAmount = $order->getMerchantAmount();
        $vexoFee = $order->vexo_fee;
        $vexoWallet = config('vexogate.vexo_wallet_address');

        $this->info("   💸 Distribuyendo fondos:");
        $this->line("      Merchant: {$merchantAmount} USDC");
        $this->line("      VexoGate: {$vexoFee} USDC");

        if ($this->dryRun) {
            $this->warn("   🔶 DRY RUN - No se envían transacciones");
            return;
        }

        try {
            // 1. Enviar comisión a VexoGate primero (prioridad)
            if ($vexoFee > 0 && $vexoWallet) {
                $this->line("   📤 Enviando comisión a VexoGate...");
                $txHashFee = $this->polygon->sendUsdc(
                    $order->temp_private_key,
                    $vexoWallet,
                    $vexoFee
                );

                $order->txid_out_fee = $txHashFee;
                $order->save();

                $this->info("   ✅ Comisión enviada: {$txHashFee}");

                // Esperar confirmación
                if (!$this->polygon->waitForConfirmation($txHashFee, 30, 2)) {
                    throw new \Exception("Timeout confirmando comisión");
                }
            }

            // 2. Enviar fondos al merchant
            if ($merchantAmount > 0) {
                $this->line("   📤 Enviando fondos al merchant...");
                $txHashMerchant = $this->polygon->sendUsdc(
                    $order->temp_private_key,
                    $order->merchant_dest_wallet,
                    $merchantAmount
                );

                $order->txid_out_merchant = $txHashMerchant;
                $order->save();

                $this->info("   ✅ Pago al merchant: {$txHashMerchant}");

                // Esperar confirmación
                if (!$this->polygon->waitForConfirmation($txHashMerchant, 30, 2)) {
                    throw new \Exception("Timeout confirmando pago a merchant");
                }
            }

            // 3. Marcar como completado
            $order->updateStatus('completed');
            $this->info("   🎉 Orden completada exitosamente");

            // 4. Notificar al merchant vía callback
            $this->notifyMerchant($order, 'completed');

        } catch (\Exception $e) {
            $this->error("   ❌ Error distribuyendo: {$e->getMessage()}");
            $order->markForManualReview("Distribution failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Notificar al merchant vía webhook
     */
    private function notifyMerchant(VexoOrder $order, string $event)
    {
        if (!$order->callback_url) {
            return;
        }

        try {
            $response = Http::timeout(10)->post($order->callback_url, [
                'event' => $event,
                'order_id' => $order->merchant_order_id,
                'vexo_order_id' => $order->id,
                'status' => $order->status,
                'amount' => $order->fiat_amount,
                'currency' => $order->fiat_currency,
                'crypto_received' => $order->crypto_received,
                'txid_in' => $order->txid_in,
                'txid_out' => $order->txid_out_merchant,
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($response->successful()) {
                $this->line("   📧 Webhook enviado exitosamente");
            } else {
                $this->warn("   ⚠️  Webhook falló: HTTP {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Error enviando webhook: {$e->getMessage()}");
        }
    }
}
