<?php

namespace Tests\Http\Controllers;

use App\Enums\Banks;
use App\Http\Controllers\WebhookController;
use App\Services\WebhookEncryptionService;
use Illuminate\Http\Request;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    private WebhookController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new WebhookController(new WebhookEncryptionService());
    }

    public function testReceiveWithMissingBankIdentifier()
    {
        $request = new Request();

        $response = $this->controller->receive($request);

        $this->assertEquals(400, $response->status());
        $this->assertEquals(['error' => 'Bank identifier missing'], $response->getData(true));
    }

    public function testReceiveWithInvalidBank()
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_X_BANK_IDENTIFIER' => 'invalid_bank',
            'HTTP_X_ENCRYPTED_DATA' => 'encrypted_data',
            'HTTP_X_SIGNATURE' => 'signature'
        ]);

        $response = $this->controller->receive($request);

        $this->assertEquals(400, $response->status());
        $this->assertEquals(['error' => 'Invalid bank identifier'], $response->getData(true));
    }

    public function testReceiveWithMissingEncryptionData()
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_X_BANK_IDENTIFIER' => Banks::FOODICS->value
        ]);

        $response = $this->controller->receive($request);

        $this->assertEquals(400, $response->status());
        $this->assertEquals(['error' => 'Missing encryption data'], $response->getData(true));
    }
} 