<?php

namespace App\Services;

use App\Enums\Banks;
use Illuminate\Support\Facades\Log;

class WebhookEncryptionService
{
    private array $bankPublicKeys = [];
    private array $bankPrivateKeys = [];
    private string $privateKey;
    private const CHUNK_SIZE = 64; // Smaller chunk size to account for RSA padding

    public function __construct()
    {
        try {
            // Load public keys for each bank
            $foodicsKey = file_get_contents(storage_path('keys/foodics_public.pem'));
            $acmeKey = file_get_contents(storage_path('keys/acme_public.pem'));
            $privateKey = file_get_contents(storage_path('keys/private.pem'));

            // Load private keys for each bank (for testing)
            $foodicsPrivateKey = file_get_contents(storage_path('keys/foodics_private.pem'));
            $acmePrivateKey = file_get_contents(storage_path('keys/acme_private.pem'));

            if (!$foodicsKey || !$acmeKey || !$privateKey || !$foodicsPrivateKey || !$acmePrivateKey) {
                throw new \RuntimeException('Failed to load one or more keys');
            }

            $this->bankPublicKeys[Banks::FOODICS->value] = $foodicsKey;
            $this->bankPublicKeys[Banks::ACME->value] = $acmeKey;
            $this->bankPrivateKeys[Banks::FOODICS->value] = $foodicsPrivateKey;
            $this->bankPrivateKeys[Banks::ACME->value] = $acmePrivateKey;
            $this->privateKey = $privateKey;

            // Verify keys are valid
            foreach ([Banks::FOODICS->value, Banks::ACME->value] as $bank) {
                $publicKey = openssl_pkey_get_public($this->bankPublicKeys[$bank]);
                $privateKey = openssl_pkey_get_private($this->bankPrivateKeys[$bank]);
                
                if (!$publicKey || !$privateKey) {
                    throw new \RuntimeException("Invalid keys for bank: {$bank}");
                }

                // Get key details
                $publicDetails = openssl_pkey_get_details($publicKey);
                $privateDetails = openssl_pkey_get_details($privateKey);

                Log::info("Key details for bank: {$bank}", [
                    'public_key_bits' => $publicDetails['bits'] ?? 0,
                    'private_key_bits' => $privateDetails['bits'] ?? 0,
                    'type' => $publicDetails['type'] ?? 'unknown'
                ]);
            }

            // Verify our private key
            $ourPrivateKey = openssl_pkey_get_private($this->privateKey);
            if (!$ourPrivateKey) {
                throw new \RuntimeException('Invalid private key');
            }

            $ourDetails = openssl_pkey_get_details($ourPrivateKey);
            Log::info('Our key details', [
                'private_key_bits' => $ourDetails['bits'] ?? 0,
                'type' => $ourDetails['type'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize WebhookEncryptionService', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function verifyAndDecrypt(string $bank, string $data, string $signature): ?array
    {
        if (!in_array($bank, [Banks::FOODICS->value, Banks::ACME->value])) {
            Log::error('Invalid bank for decryption', ['bank' => $bank]);
            return null;
        }

        Log::info('Starting decryption process', [
            'bank' => $bank,
            'data_length' => strlen($data),
            'signature_length' => strlen($signature)
        ]);

        // Get bank's public key for verification
        $publicKey = openssl_pkey_get_public($this->bankPublicKeys[$bank]);
        if (!$publicKey) {
            Log::error('Failed to load public key', ['bank' => $bank, 'error' => openssl_error_string()]);
            return null;
        }

        // Decode the signature
        $decodedSignature = base64_decode($signature);
        if ($decodedSignature === false) {
            Log::error('Failed to decode signature', ['bank' => $bank]);
            return null;
        }

        // Verify signature
        $verificationResult = openssl_verify(
            $data,
            $decodedSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verificationResult !== 1) {
            Log::error('Invalid signature', [
                'bank' => $bank,
                'openssl_error' => openssl_error_string(),
                'signature_length' => strlen($signature),
                'data_length' => strlen($data),
                'verification_result' => $verificationResult
            ]);
            return null;
        }

        Log::info('Signature verified successfully');

        // Get our private key for decryption
        $privateKey = openssl_pkey_get_private($this->bankPrivateKeys[$bank]);
        if (!$privateKey) {
            Log::error('Failed to load private key', ['error' => openssl_error_string()]);
            return null;
        }

        // Split data into chunks for decryption
        $chunks = str_split($data, 344); // Base64 encoded RSA blocks are 344 bytes
        Log::info('Processing encrypted chunks', ['chunks_count' => count($chunks)]);

        // Decrypt each chunk
        $decryptedChunks = [];
        foreach ($chunks as $index => $chunk) {
            $decodedChunk = base64_decode($chunk);
            if ($decodedChunk === false) {
                Log::error('Failed to decode chunk', ['chunk_index' => $index]);
                return null;
            }

            $decrypted = '';
            if (!openssl_private_decrypt($decodedChunk, $decrypted, $privateKey)) {
                Log::error('Failed to decrypt chunk', [
                    'chunk_index' => $index,
                    'error' => openssl_error_string()
                ]);
                return null;
            }
            $decryptedChunks[] = $decrypted;
        }

        // Combine decrypted chunks
        $decryptedData = implode('', $decryptedChunks);
        Log::info('Data decrypted successfully', ['decrypted_length' => strlen($decryptedData)]);

        // Decode JSON
        $decodedData = json_decode($decryptedData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to decode JSON', [
                'error' => json_last_error_msg(),
                'decrypted_data' => $decryptedData
            ]);
            return null;
        }

        Log::info('JSON decoded successfully');
        return $decodedData;
    }

    public function encryptAndSign(string $bank, array $data): array
    {
        if (!in_array($bank, [Banks::FOODICS->value, Banks::ACME->value])) {
            Log::error('Invalid bank for encryption', ['bank' => $bank]);
            throw new \InvalidArgumentException('Invalid bank');
        }

        Log::info('Starting encryption process', ['bank' => $bank, 'data' => $data]);

        // Encode data to JSON
        $jsonData = json_encode($data);
        if ($jsonData === false) {
            Log::error('Failed to encode data to JSON', ['error' => json_last_error_msg()]);
            throw new \RuntimeException('Failed to encode data to JSON');
        }
        Log::info('Data encoded to JSON', ['data_length' => strlen($jsonData), 'bank' => $bank]);

        // Get bank's public key
        $publicKey = openssl_pkey_get_public($this->bankPublicKeys[$bank]);
        if (!$publicKey) {
            Log::error('Failed to load public key', ['bank' => $bank, 'error' => openssl_error_string()]);
            throw new \RuntimeException('Failed to load public key');
        }

        // Split data into chunks if it's too large
        $chunks = str_split($jsonData, 64);
        Log::info('Splitting data into chunks', ['chunks_count' => count($chunks), 'chunk_size' => 64]);

        // Encrypt each chunk
        $encryptedChunks = [];
        foreach ($chunks as $chunk) {
            $encrypted = '';
            if (!openssl_public_encrypt($chunk, $encrypted, $publicKey)) {
                Log::error('Failed to encrypt chunk', ['error' => openssl_error_string()]);
                throw new \RuntimeException('Failed to encrypt data');
            }
            $encryptedChunks[] = base64_encode($encrypted);
        }

        // Combine encrypted chunks
        $combinedEncrypted = implode('', $encryptedChunks);
        Log::info('Data encrypted successfully', ['chunks_count' => count($encryptedChunks), 'total_length' => strlen($combinedEncrypted)]);

        // Get our private key for signing
        $privateKey = openssl_pkey_get_private($this->bankPrivateKeys[$bank]);
        if (!$privateKey) {
            Log::error('Failed to load private key', ['error' => openssl_error_string()]);
            throw new \RuntimeException('Failed to load private key');
        }

        // Sign the combined encrypted data
        $signature = '';
        if (!openssl_sign($combinedEncrypted, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('Failed to sign data', ['error' => openssl_error_string()]);
            throw new \RuntimeException('Failed to sign data');
        }

        // Base64 encode the signature
        $encodedSignature = base64_encode($signature);
        Log::info('Data signed successfully', ['signature_length' => strlen($encodedSignature)]);

        return [
            'data' => $combinedEncrypted,
            'signature' => $encodedSignature
        ];
    }
} 