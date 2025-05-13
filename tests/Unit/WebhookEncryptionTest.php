<?php

namespace Tests\Unit;

use App\Enums\Banks;
use App\Services\WebhookEncryptionService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class WebhookEncryptionTest extends TestCase
{
    private WebhookEncryptionService $service;
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyPath = storage_path('keys');
        $this->service = new WebhookEncryptionService();
        $this->generateTestKeys();
    }

    public function testBasicEncryptionAndDecryption()
    {
        foreach (Banks::cases() as $bank) {
            $data = ['test' => 'data'];
            $this->assertEncryptionAndDecryption($bank->value, $data);
        }
    }

    public function testInvalidBank()
    {
        $data = ['test' => 'data'];
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bank');
        $this->service->encryptAndSign('invalid_bank', $data);
    }

    public function testLargeData()
    {
        foreach (Banks::cases() as $bank) {
            // Generate a large data array
            $data = [];
            for ($i = 0; $i < 100; $i++) {
                $data["key_$i"] = str_repeat('test data ', 100);
            }
            $this->assertEncryptionAndDecryption($bank->value, $data);
        }
    }

    private function assertEncryptionAndDecryption(string $bank, array $data): void
    {
        $result = $this->service->encryptAndSign($bank, $data);
        
        $this->assertNotNull($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('signature', $result);

        $decrypted = $this->service->verifyAndDecrypt(
            $bank,
            $result['data'],
            $result['signature']
        );
        
        $this->assertNotNull($decrypted);
        $this->assertEquals($data, $decrypted);
    }

    private function generateTestKeys(): void
    {
        if (!File::exists($this->keyPath)) {
            File::makeDirectory($this->keyPath, 0755, true);
        }

        // Generate our private key
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($privateKey, $privateKeyPem);
        File::put($this->keyPath . '/private.pem', $privateKeyPem);

        // Get our public key
        $publicKey = openssl_pkey_get_details($privateKey)['key'];
        File::put($this->keyPath . '/public.pem', $publicKey);

        // Generate keys for each bank
        foreach (Banks::cases() as $bank) {
            $bankPrivateKey = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export($bankPrivateKey, $bankPrivateKeyPem);
            File::put($this->keyPath . "/{$bank->value}_private.pem", $bankPrivateKeyPem);

            $bankPublicKey = openssl_pkey_get_details($bankPrivateKey)['key'];
            File::put($this->keyPath . "/{$bank->value}_public.pem", $bankPublicKey);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
} 