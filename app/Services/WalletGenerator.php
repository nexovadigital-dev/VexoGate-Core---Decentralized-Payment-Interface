<?php

namespace App\Services;

use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Generador Nativo de Wallets Ethereum/Polygon
 * Utiliza curva elíptica secp256k1 sin dependencias externas
 */
class WalletGenerator
{
    private EC $ec;

    public function __construct()
    {
        // Inicializar curva secp256k1 (mismo que Bitcoin/Ethereum)
        $this->ec = new EC('secp256k1');
    }

    /**
     * Generar un nuevo par de llaves Ethereum
     *
     * @return array{address: string, private_key: string, public_key: string}
     */
    public function generate(): array
    {
        // Generar par de llaves
        $keyPair = $this->ec->genKeyPair();

        // Obtener clave privada (hex)
        $privateKey = $keyPair->getPrivate('hex');

        // Obtener clave pública sin comprimir (sin el prefijo 04)
        $publicKey = $keyPair->getPublic('hex');

        // Generar dirección Ethereum desde la clave pública
        $address = $this->publicKeyToAddress($publicKey);

        return [
            'address' => $address,
            'private_key' => '0x' . $privateKey,
            'public_key' => '0x' . $publicKey,
        ];
    }

    /**
     * Convertir clave pública a dirección Ethereum
     *
     * @param string $publicKey Clave pública en hex
     * @return string Dirección Ethereum con checksum
     */
    private function publicKeyToAddress(string $publicKey): string
    {
        // Remover el prefijo '04' si existe (indica clave no comprimida)
        $publicKey = str_starts_with($publicKey, '04')
            ? substr($publicKey, 2)
            : $publicKey;

        // Aplicar Keccak-256 a la clave pública
        $hash = Keccak::hash(hex2bin($publicKey), 256);

        // Tomar los últimos 20 bytes (40 caracteres hex)
        $address = '0x' . substr($hash, -40);

        // Aplicar EIP-55 checksum
        return $this->toChecksumAddress($address);
    }

    /**
     * Aplicar EIP-55 Checksum a una dirección
     *
     * @param string $address Dirección sin checksum
     * @return string Dirección con checksum
     */
    private function toChecksumAddress(string $address): string
    {
        $address = strtolower(str_replace('0x', '', $address));
        $hash = Keccak::hash($address, 256);

        $checksum = '0x';
        for ($i = 0; $i < strlen($address); $i++) {
            $checksum .= (intval($hash[$i], 16) >= 8)
                ? strtoupper($address[$i])
                : $address[$i];
        }

        return $checksum;
    }

    /**
     * Validar si una dirección Ethereum es válida
     *
     * @param string $address
     * @return bool
     */
    public function isValidAddress(string $address): bool
    {
        // Verificar formato básico
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            return false;
        }

        // Verificar checksum si tiene mayúsculas
        if ($address !== strtolower($address) && $address !== strtoupper($address)) {
            return $address === $this->toChecksumAddress($address);
        }

        return true;
    }

    /**
     * Importar wallet desde clave privada
     *
     * @param string $privateKey Clave privada en hex (con o sin 0x)
     * @return array{address: string, private_key: string, public_key: string}
     */
    public function fromPrivateKey(string $privateKey): array
    {
        // Limpiar formato
        $privateKey = str_replace('0x', '', $privateKey);

        // Regenerar par de llaves desde privada
        $keyPair = $this->ec->keyFromPrivate($privateKey, 'hex');

        // Obtener clave pública
        $publicKey = $keyPair->getPublic('hex');

        // Generar dirección
        $address = $this->publicKeyToAddress($publicKey);

        return [
            'address' => $address,
            'private_key' => '0x' . $privateKey,
            'public_key' => '0x' . $publicKey,
        ];
    }
}
