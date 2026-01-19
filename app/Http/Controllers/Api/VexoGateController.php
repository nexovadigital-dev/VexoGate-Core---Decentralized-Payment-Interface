<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VexoOrder;
use App\Services\WalletGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * VexoGate API Controller
 * Endpoint principal para inicialización de pagos
 */
class VexoGateController extends Controller
{
    private WalletGenerator $walletGenerator;

    public function __construct(WalletGenerator $walletGenerator)
    {
        $this->walletGenerator = $walletGenerator;
    }

    /**
     * POST /api/v1/initiate
     *
     * Iniciar proceso de pago descentralizado
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiate(Request $request)
    {
        // Validar API Key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key',
            ], 401);
        }

        // Validar datos de entrada
        $validator = Validator::make($request->all(), [
            'merchant_order_id' => 'required|string|max:255',
            'domain_origin' => 'required|string|max:255',
            'callback_url' => 'required|url',
            'merchant_wallet' => 'required|string|size:42', // Dirección Ethereum
            'fiat_amount' => 'required|numeric|min:1',
            'fiat_currency' => 'required|string|size:3',
            'client_email' => 'nullable|email',
            'provider_slug' => 'nullable|string|in:transak,moonpay,banxa',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Validar wallet de merchant
            if (!$this->walletGenerator->isValidAddress($request->merchant_wallet)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid merchant wallet address',
                ], 422);
            }

            // Generar wallet temporal para esta orden
            $tempWallet = $this->walletGenerator->generate();

            // Calcular comisión VexoGate
            $vexoFee = $this->calculateVexoFee($request->fiat_amount);

            // Determinar proveedor
            $provider = $request->provider_slug ?? config('vexogate.default_provider');

            // Crear orden en base de datos
            $order = VexoOrder::create([
                'domain_origin' => $request->domain_origin,
                'merchant_order_id' => $request->merchant_order_id,
                'callback_url' => $request->callback_url,
                'client_email' => $request->client_email,
                'provider_slug' => $provider,
                'fiat_currency' => strtoupper($request->fiat_currency),
                'fiat_amount' => $request->fiat_amount,
                'temp_wallet_address' => $tempWallet['address'],
                'temp_private_key' => $tempWallet['private_key'], // Se encripta automáticamente
                'merchant_dest_wallet' => $request->merchant_wallet,
                'vexo_fee' => $vexoFee,
                'status' => 'waiting_payment',
            ]);

            // Verificar si requiere aprobación manual
            if ($order->requiresManualApproval() || config('vexogate.force_manual_approval')) {
                $order->updateStatus('manual_review', 'Amount exceeds auto-approval threshold');
            }

            // Generar URL de pago según proveedor
            $paymentUrl = $this->generatePaymentUrl($order, $provider);

            // Respuesta de éxito
            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'temp_wallet' => $tempWallet['address'],
                    'redirect_url' => $paymentUrl,
                    'status' => $order->status,
                    'amount' => [
                        'fiat' => $order->fiat_amount,
                        'currency' => $order->fiat_currency,
                        'vexo_fee' => $vexoFee,
                    ],
                ],
                'legal' => config('vexogate.legal_disclaimer'),
            ], 201);

        } catch (\Exception $e) {
            // Log error
            \Log::error('VexoGate Initiate Error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error. Please try again.',
            ], 500);
        }
    }

    /**
     * GET /api/v1/order/{id}/status
     *
     * Consultar estado de una orden
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request, int $id)
    {
        // Validar API Key
        if (!$this->validateApiKey($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key',
            ], 401);
        }

        $order = VexoOrder::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'status' => $order->status,
                'merchant_order_id' => $order->merchant_order_id,
                'fiat_amount' => $order->fiat_amount,
                'fiat_currency' => $order->fiat_currency,
                'crypto_received' => $order->crypto_received,
                'temp_wallet' => $order->temp_wallet_address,
                'transactions' => [
                    'incoming' => $order->txid_in,
                    'gas_injection' => $order->txid_gas,
                    'merchant_payout' => $order->txid_out_merchant,
                    'fee_payout' => $order->txid_out_fee,
                ],
                'created_at' => $order->created_at->toIso8601String(),
                'updated_at' => $order->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /api/v1/webhook/callback
     *
     * Recibir notificaciones de proveedores de pago
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        // Implementar según proveedor específico (Transak, MoonPay, etc.)
        // Por ahora, solo registrar la notificación

        \Log::info('VexoGate Webhook Received', [
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        return response()->json(['success' => true]);
    }

    // === MÉTODOS PRIVADOS ===

    /**
     * Validar API Key del merchant
     */
    private function validateApiKey(Request $request): bool
    {
        $apiKey = $request->header('X-VexoGate-API-Key') ?? $request->input('api_key');

        if (!$apiKey) {
            return false;
        }

        // En producción, validar contra base de datos de merchants
        // Por ahora, validar contra .env
        $validKey = config('vexogate.api_key_secret');

        return $apiKey === $validKey;
    }

    /**
     * Calcular comisión de VexoGate
     */
    private function calculateVexoFee(float $amount): float
    {
        $percentage = config('vexogate.vexo_fee_percentage', 2.5);
        $minimum = config('vexogate.vexo_fee_minimum', 1.0);

        $fee = ($amount * $percentage) / 100;

        return max($fee, $minimum);
    }

    /**
     * Generar URL de pago según proveedor
     */
    private function generatePaymentUrl(VexoOrder $order, string $provider): string
    {
        $providers = config('vexogate.supported_providers');

        if (!isset($providers[$provider])) {
            $provider = config('vexogate.default_provider');
        }

        // URLs de ejemplo - en producción, integrar con APIs reales
        $baseUrls = [
            'transak' => 'https://global.transak.com/?',
            'moonpay' => 'https://buy.moonpay.com/?',
            'banxa' => 'https://banxa.com/buy/?',
        ];

        $params = http_build_query([
            'walletAddress' => $order->temp_wallet_address,
            'fiatCurrency' => $order->fiat_currency,
            'fiatAmount' => $order->fiat_amount,
            'cryptoCurrency' => 'USDC',
            'network' => 'polygon',
            'email' => $order->client_email ?? '',
        ]);

        return $baseUrls[$provider] . $params;
    }
}
