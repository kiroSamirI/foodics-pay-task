<?php

namespace Tests\Service\BankStrategy;

use App\Models\Client;
use App\Models\Transaction;
use App\Services\BankStrategy\AcmeTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcmeTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcmeTransactionService $service;

    private Client $client;
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AcmeTransactionService();
        // Create a test client
        $this->client = Client::firstOrCreate(['id' => 1], ['name' => 'Test Client']);
    }

    public function testImportValidAcmeTransaction()
    {
        $line = '1234,56//12345//20240315';
        $data = ['client_id' => $this->client->id];

        $this->service->import($line, $data);

        $transaction = Transaction::where('reference', '12345')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('12345', $transaction->reference);
        $this->assertEquals('1234.56', $transaction->amount);
        $this->assertEquals('2024-03-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals($this->client->id, $transaction->client_id);
        $this->assertEquals(['bank' => 'acme'], $transaction->metadata);
    }

    public function testImportInvalidAcmeTransaction()
    {
        $line = 'invalid_format';
        $data = ['client_id' => $this->client->id];

        $this->service->import($line, $data);

        $this->assertDatabaseMissing('transactions', [
            'client_id' => $this->client->id,
        ]);
    }

    public function testImportDuplicateTransaction()
    {
        $line = '1234,56//12345//20240315';
        $data = ['client_id' => $this->client->id];

        // Ensure client exists

        // First import
        $this->service->import($line, $data);
        
        // Second import of the same transaction
        $this->service->import($line, $data);

        // Should only have one transaction in the database
        $this->assertEquals(1, Transaction::where('reference', '12345')->count());
    }
} 