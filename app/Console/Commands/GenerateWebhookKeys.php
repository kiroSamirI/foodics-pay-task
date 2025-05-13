<?php

namespace App\Console\Commands;

use App\Enums\Banks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateWebhookKeys extends Command
{
    protected $signature = 'webhook:generate-keys';
    protected $description = 'Generate public/private keys for webhook encryption';

    public function handle()
    {
        $keyPath = storage_path('keys');
        if (!File::exists($keyPath)) {
            File::makeDirectory($keyPath, 0755, true);
        }

        // Generate our private key
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($privateKey, $privateKeyPem);
        File::put($keyPath . '/private.pem', $privateKeyPem);

        // Get our public key
        $publicKey = openssl_pkey_get_details($privateKey)['key'];
        File::put($keyPath . '/public.pem', $publicKey);

        // Generate keys for each bank
        foreach (Banks::cases() as $bank) {
            $bankPrivateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($bankPrivateKey, $bankPrivateKeyPem);
            File::put($keyPath . "/{$bank->value}_private.pem", $bankPrivateKeyPem);

            $bankPublicKey = openssl_pkey_get_details($bankPrivateKey)['key'];
            File::put($keyPath . "/{$bank->value}_public.pem", $bankPublicKey);
        }

        $this->info('Keys generated successfully in ' . $keyPath);
        $this->info('Please share the following public keys with the respective banks:');
        foreach (Banks::cases() as $bank) {
            $this->info("{$bank->value}_public.pem");
        }
    }
} 