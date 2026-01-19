<?php

namespace App\Services;

use Web3\Web3;
use Web3\Contract;
use Web3\Utils;
use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Servicio de Integración con Polygon Blockchain
 * Maneja MATIC (gas) y USDC (ERC-20)
 */
class PolygonService
{
    private Web3 $web3;
    private string $rpcUrl;
    private string $usdcContractAddress;
    private int $chainId;

    // ABI mínima para interactuar con USDC (ERC-20)
    private array $erc20Abi = [
        [
            'constant' => true,
            'inputs' => [['name' => '_owner', 'type' => 'address']],
            'name' => 'balanceOf',
            'outputs' => [['name' => 'balance', 'type' => 'uint256']],
            'type' => 'function',
        ],
        [
            'constant' => false,
            'inputs' => [
                ['name' => '_to', 'type' => 'address'],
                ['name' => '_value', 'type' => 'uint256'],
            ],
            'name' => 'transfer',
            'outputs' => [['name' => '', 'type' => 'bool']],
            'type' => 'function',
        ],
        [
            'constant' => true,
            'inputs' => [],
            'name' => 'decimals',
            'outputs' => [['name' => '', 'type' => 'uint8']],
            'type' => 'function',
        ],
    ];

    public function __construct()
    {
        $this->rpcUrl = config('vexogate.polygon_rpc_url');
        $this->usdcContractAddress = config('vexogate.usdc_contract_address');
        $this->chainId = config('vexogate.polygon_chain_id', 137); // 137 = Polygon Mainnet

        $this->web3 = new Web3($this->rpcUrl);
    }

    // === CONSULTA DE BALANCES ===

    /**
     * Obtener balance de MATIC (nativo)
     *
     * @param string $address Dirección de la wallet
     * @return float Balance en MATIC
     */
    public function getMaticBalance(string $address): float
    {
        $balance = 0;

        $this->web3->eth->getBalance($address, function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \Exception("Error obteniendo balance MATIC: " . $err->getMessage());
            }
            // Convertir Wei a MATIC (18 decimales)
            $balance = bcdiv($result->toString(), '1000000000000000000', 18);
        });

        return (float) $balance;
    }

    /**
     * Obtener balance de USDC (ERC-20)
     *
     * @param string $address Dirección de la wallet
     * @return float Balance en USDC
     */
    public function getUsdcBalance(string $address): float
    {
        $contract = new Contract($this->rpcUrl, json_encode($this->erc20Abi));
        $contract->at($this->usdcContractAddress);

        $balance = 0;

        $contract->call('balanceOf', $address, function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \Exception("Error obteniendo balance USDC: " . $err->getMessage());
            }
            // USDC tiene 6 decimales
            $balance = bcdiv($result[0]->toString(), '1000000', 6);
        });

        return (float) $balance;
    }

    // === TRANSACCIONES DE MATIC (Gas Injection) ===

    /**
     * Enviar MATIC desde Master Wallet a una wallet temporal
     *
     * @param string $toAddress Wallet de destino
     * @param float $amount Cantidad en MATIC
     * @return string Transaction Hash
     */
    public function sendMatic(string $toAddress, float $amount): string
    {
        $masterPrivateKey = config('vexogate.master_wallet_private_key');
        $masterWallet = $this->getAddressFromPrivateKey($masterPrivateKey);

        // Convertir MATIC a Wei
        $amountWei = bcmul((string) $amount, '1000000000000000000', 0);

        // Obtener nonce
        $nonce = $this->getNonce($masterWallet);

        // Obtener gas price
        $gasPrice = $this->getGasPrice();

        // Construir transacción
        $tx = [
            'from' => $masterWallet,
            'to' => $toAddress,
            'value' => '0x' . dechex((int) $amountWei),
            'gas' => '0x5208', // 21000 en hex (gas estándar para transferencia)
            'gasPrice' => '0x' . dechex((int) $gasPrice),
            'nonce' => '0x' . dechex($nonce),
            'chainId' => $this->chainId,
        ];

        // Firmar y enviar
        $txHash = $this->signAndSendTransaction($tx, $masterPrivateKey);

        return $txHash;
    }

    // === TRANSACCIONES DE USDC (ERC-20) ===

    /**
     * Enviar USDC desde una wallet temporal
     *
     * @param string $fromPrivateKey Clave privada de origen
     * @param string $toAddress Dirección de destino
     * @param float $amount Cantidad en USDC
     * @return string Transaction Hash
     */
    public function sendUsdc(string $fromPrivateKey, string $toAddress, float $amount): string
    {
        $fromAddress = $this->getAddressFromPrivateKey($fromPrivateKey);

        // Convertir USDC a unidades con 6 decimales
        $amountUnits = bcmul((string) $amount, '1000000', 0);

        // Construir data del contrato (transfer)
        $data = $this->buildErc20TransferData($toAddress, $amountUnits);

        // Obtener nonce
        $nonce = $this->getNonce($fromAddress);

        // Obtener gas price
        $gasPrice = $this->getGasPrice();

        // Construir transacción
        $tx = [
            'from' => $fromAddress,
            'to' => $this->usdcContractAddress,
            'value' => '0x0', // No enviamos MATIC, solo USDC
            'gas' => '0x186A0', // 100000 en hex (gas para ERC-20 transfer)
            'gasPrice' => '0x' . dechex((int) $gasPrice),
            'nonce' => '0x' . dechex($nonce),
            'data' => $data,
            'chainId' => $this->chainId,
        ];

        // Firmar y enviar
        $txHash = $this->signAndSendTransaction($tx, $fromPrivateKey);

        return $txHash;
    }

    // === UTILIDADES INTERNAS ===

    /**
     * Obtener nonce de una dirección
     */
    private function getNonce(string $address): int
    {
        $nonce = 0;

        $this->web3->eth->getTransactionCount($address, 'pending', function ($err, $result) use (&$nonce) {
            if ($err !== null) {
                throw new \Exception("Error obteniendo nonce: " . $err->getMessage());
            }
            $nonce = hexdec($result->toString());
        });

        return $nonce;
    }

    /**
     * Obtener gas price actual
     */
    private function getGasPrice(): int
    {
        $gasPrice = 0;

        $this->web3->eth->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err !== null) {
                throw new \Exception("Error obteniendo gas price: " . $err->getMessage());
            }
            $gasPrice = hexdec($result->toString());
        });

        // Agregar 20% de buffer para asegurar que se procese
        return (int) ($gasPrice * 1.2);
    }

    /**
     * Construir data para transfer de ERC-20
     */
    private function buildErc20TransferData(string $toAddress, string $amount): string
    {
        // Signature de transfer(address,uint256)
        $methodId = substr(Keccak::hash('transfer(address,uint256)', 256), 0, 8);

        // Padding de dirección (quitar 0x y rellenar a 64 caracteres)
        $addressPadded = str_pad(str_replace('0x', '', $toAddress), 64, '0', STR_PAD_LEFT);

        // Padding de cantidad (convertir a hex y rellenar)
        $amountHex = dechex((int) $amount);
        $amountPadded = str_pad($amountHex, 64, '0', STR_PAD_LEFT);

        return '0x' . $methodId . $addressPadded . $amountPadded;
    }

    /**
     * Firmar y enviar transacción
     */
    private function signAndSendTransaction(array $tx, string $privateKey): string
    {
        // Remover 0x de la clave privada
        $privateKey = str_replace('0x', '', $privateKey);

        // Serializar transacción para firma
        $rawTx = $this->serializeTransaction($tx);

        // Firmar con ECDSA
        $signature = $this->signTransaction($rawTx, $privateKey, $tx['chainId']);

        // Construir transacción firmada
        $signedTx = $this->buildSignedTransaction($tx, $signature);

        // Enviar a la blockchain
        $txHash = '';

        $this->web3->eth->sendRawTransaction('0x' . $signedTx, function ($err, $result) use (&$txHash) {
            if ($err !== null) {
                throw new \Exception("Error enviando transacción: " . $err->getMessage());
            }
            $txHash = $result;
        });

        return $txHash;
    }

    /**
     * Serializar transacción para firma (RLP encoding simplificado)
     */
    private function serializeTransaction(array $tx): string
    {
        // Implementación simplificada - en producción usar librería RLP completa
        $fields = [
            $tx['nonce'] ?? '0x0',
            $tx['gasPrice'] ?? '0x0',
            $tx['gas'] ?? '0x0',
            $tx['to'] ?? '',
            $tx['value'] ?? '0x0',
            $tx['data'] ?? '0x',
            dechex($tx['chainId']),
            '0x',
            '0x',
        ];

        return hash('sha3-256', implode('', $fields));
    }

    /**
     * Firmar transacción con ECDSA
     */
    private function signTransaction(string $hash, string $privateKey, int $chainId): array
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKey, 'hex');
        $signature = $key->sign($hash);

        return [
            'r' => $signature->r->toString(16),
            's' => $signature->s->toString(16),
            'v' => $signature->recoveryParam + $chainId * 2 + 35,
        ];
    }

    /**
     * Construir transacción firmada
     */
    private function buildSignedTransaction(array $tx, array $signature): string
    {
        // Placeholder - implementación completa requiere RLP encoding
        return bin2hex(json_encode(array_merge($tx, $signature)));
    }

    /**
     * Obtener dirección desde clave privada
     */
    private function getAddressFromPrivateKey(string $privateKey): string
    {
        $generator = new WalletGenerator();
        $wallet = $generator->fromPrivateKey($privateKey);
        return $wallet['address'];
    }

    /**
     * Esperar confirmación de transacción
     *
     * @param string $txHash Hash de la transacción
     * @param int $maxAttempts Número máximo de intentos
     * @param int $delay Segundos entre intentos
     * @return bool True si se confirmó, False si timeout
     */
    public function waitForConfirmation(string $txHash, int $maxAttempts = 30, int $delay = 2): bool
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $receipt = null;

            $this->web3->eth->getTransactionReceipt($txHash, function ($err, $result) use (&$receipt) {
                if ($err === null && $result !== null) {
                    $receipt = $result;
                }
            });

            if ($receipt !== null && isset($receipt->status)) {
                return hexdec($receipt->status) === 1;
            }

            sleep($delay);
        }

        return false;
    }
}
