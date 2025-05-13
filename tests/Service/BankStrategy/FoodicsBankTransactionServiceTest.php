<?php

namespace Tests\Service\BankStrategy;

use App\Models\Client;
use App\Models\Transaction;
use App\Services\BankStrategy\FoodicsBankTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FoodicsBankTransactionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FoodicsBankTransactionService $service;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FoodicsBankTransactionService();
        // Create a test client
        $this->client = Client::firstOrCreate(['id' => 1], ['name' => 'Test Client']);
    }

    public function testImportValidFoodicsTransaction()
    {
        $line = '202403151234,56#12345#note/key1/value1/key2/value2/type/PAYMENT';
        $data = ['client_id' => $this->client->id];

        $this->service->import($line, $data);

        $transaction = Transaction::where('reference', '12345')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('12345', $transaction->reference);
        $this->assertEquals('1234.56', $transaction->amount);
        $this->assertEquals('2024-03-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals($this->client->id, $transaction->client_id);
        
        $metadata = $transaction->metadata;
        $this->assertEquals('key1/value1/key2/value2', $metadata['note']);
        $this->assertEquals('PAYMENT', $metadata['type']);
        $this->assertEquals('foodics', $metadata['bank']);
    }

    public function testImportInvalidFoodicsTransaction()
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
        $line = '202403151234,56#12345#note/key1/value1/type/PAYMENT';
        $data = ['client_id' => $this->client->id];

        // First import
        $this->service->import($line, $data);
        
        // Second import of the same transaction
        $this->service->import($line, $data);

        // Should only have one transaction in the database
        $this->assertEquals(1, Transaction::where('reference', '12345')->count());
    }
} 