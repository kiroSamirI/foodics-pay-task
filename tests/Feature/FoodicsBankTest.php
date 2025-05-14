<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoodicsBankTest extends TestCase
{
    /**
     * Test the POST /webhooks/foodics-bank endpoint.
     */
    public function test_foodics_bank_webhook(): void
    {
        // Ensure a client with id 1 exists
        $client = \App\Models\Client::create(['name' => 'Test Client']);

        $payload = "20250615156,50#202506159000001#note/debt/payment/credit";
        $encryptionService = new \App\Services\WebhookEncryptionService();

        $encryptedData = $encryptionService->encryptAndSign('foodics', [
            'payload' => $payload,
            'client_id' => $client->id
        ]);

        $response = $this->postJson('/api/webhooks', [], [
            'X-Bank-Identifier' => 'foodics',
            'X-Encrypted-Data' => $encryptedData['data'],
            'X-Signature' => $encryptedData['signature']
        ]);

        $response->assertStatus(200);
    }


}
