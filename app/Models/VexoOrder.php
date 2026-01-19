<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class VexoOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_origin',
        'merchant_order_id',
        'callback_url',
        'client_email',
        'provider_slug',
        'fiat_currency',
        'fiat_amount',
        'temp_wallet_address',
        'temp_private_key',
        'merchant_dest_wallet',
        'status',
        'crypto_received',
        'gas_cost_matic',
        'vexo_fee',
        'txid_in',
        'txid_gas',
        'txid_out_merchant',
        'txid_out_fee',
        'last_error_log',
        'manual_override',
    ];

    protected $casts = [
        'fiat_amount' => 'decimal:2',
        'crypto_received' => 'decimal:6',
        'gas_cost_matic' => 'decimal:9',
        'vexo_fee' => 'decimal:2',
        'manual_override' => 'boolean',
    ];

    // === MUTATORS Y ACCESSORS ===

    /**
     * Encriptar la clave privada antes de guardar (CRÍTICO para seguridad)
     */
    public function setTempPrivateKeyAttribute($value)
    {
        $this->attributes['temp_private_key'] = Crypt::encryptString($value);
    }

    /**
     * Desencriptar la clave privada al leerla
     */
    public function getTempPrivateKeyAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    // === SCOPES PARA EL WORKER ===

    /**
     * Órdenes esperando pago del cliente
     */
    public function scopeWaitingPayment($query)
    {
        return $query->where('status', 'waiting_payment');
    }

    /**
     * Órdenes con fondos detectados pero sin gas
     */
    public function scopeFundsDetected($query)
    {
        return $query->where('status', 'funds_detected');
    }

    /**
     * Órdenes con gas inyectado, listas para distribución
     */
    public function scopeGasInjected($query)
    {
        return $query->where('status', 'gas_injected');
    }

    /**
     * Órdenes en proceso de distribución
     */
    public function scopeDistributing($query)
    {
        return $query->where('status', 'distributing');
    }

    /**
     * Órdenes que requieren revisión manual
     */
    public function scopeManualReview($query)
    {
        return $query->where('status', 'manual_review');
    }

    /**
     * Órdenes completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Órdenes procesables por el worker (excluyendo finalizadas y manuales)
     */
    public function scopeProcessable($query)
    {
        return $query->whereNotIn('status', ['completed', 'refunded', 'manual_review']);
    }

    // === MÉTODOS DE UTILIDAD ===

    /**
     * Actualizar estado y registrar log
     */
    public function updateStatus(string $status, ?string $errorLog = null)
    {
        $this->status = $status;
        if ($errorLog) {
            $this->last_error_log = $errorLog;
        }
        $this->save();
    }

    /**
     * Marcar orden como error con revisión manual
     */
    public function markForManualReview(string $reason)
    {
        $this->updateStatus('manual_review', $reason);
    }

    /**
     * Verificar si requiere aprobación manual por monto alto
     */
    public function requiresManualApproval(): bool
    {
        $threshold = config('vexogate.manual_approval_threshold', 500);
        return $this->fiat_amount > $threshold;
    }

    /**
     * Obtener URL de PolygonScan para una transacción
     */
    public function getPolygonScanUrl(string $txid): string
    {
        $network = config('vexogate.polygon_network', 'mainnet');
        $baseUrl = $network === 'mainnet'
            ? 'https://polygonscan.com/tx/'
            : 'https://mumbai.polygonscan.com/tx/';

        return $baseUrl . $txid;
    }

    /**
     * Calcular monto a enviar al merchant (total - comisión)
     */
    public function getMerchantAmount(): float
    {
        if (!$this->crypto_received) {
            return 0;
        }

        return max(0, $this->crypto_received - $this->vexo_fee);
    }

    /**
     * Verificar si la orden está en estado final
     */
    public function isFinal(): bool
    {
        return in_array($this->status, ['completed', 'refunded']);
    }
}
