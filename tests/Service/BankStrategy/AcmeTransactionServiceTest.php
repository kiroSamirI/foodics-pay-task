<?php

namespace Tests\Service\BankStrategy;

use App\Models\Client;
use App\Models\Transaction;
use App\Services\BankStrategy\AcmeTransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AcmeTransactionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AcmeTransactionService $service;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AcmeTransactionService();
        
        // Create a test client with initial balance
        $this->client = Client::create([
            'name' => 'Test Client',
            'balance' => 1000.00
        ]);
    }

    public function testImportValidAcmeTransaction()
    {
        $line = '1234,56//12345//20240315';
        $data = ['client_id' => $this->client->id];

        $result = $this->service->import($line, $data);
        $this->assertTrue($result);

        $transaction = Transaction::where('reference', '12345')->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('12345', $transaction->reference);
        $this->assertEquals('1234.56', $transaction->amount);
        $this->assertEquals('2024-03-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals($this->client->id, $transaction->client_id);

        // Verify balance was updated
        $this->client->refresh();
        $this->assertEquals(2234.56, $this->client->balance);
    }

    public function testImportInvalidAcmeTransaction()
    {
        $line = 'invalid_format';
        $data = ['client_id' => $this->client->id];

        $result = $this->service->import($line, $data);
        $this->assertFalse($result);

        $this->assertDatabaseMissing('transactions', [
            'client_id' => $this->client->id,
        ]);

        // Verify balance was not changed
        $this->client->refresh();
        $this->assertEquals(1000.00, $this->client->balance);
    }

    public function testImportDuplicateTransaction()
    {
        $line = '1234,56//12345//20240315';
        $data = ['client_id' => $this->client->id];

        // First import
        $result1 = $this->service->import($line, $data);
        $this->assertTrue($result1);
        
        // Second import of the same transaction
        $result2 = $this->service->import($line, $data);
        $this->assertFalse($result2);

        // Should only have one transaction in the database
        $this->assertEquals(1, Transaction::where('reference', '12345')->count());

        // Verify balance was only updated once
        $this->client->refresh();
        $this->assertEquals(2234.56, $this->client->balance);
    }

    public function testConcurrentTransactions()
    {
        $line1 = '100,00//12345//20240315';
        $line2 = '200,00//12346//20240315';
        $data = ['client_id' => $this->client->id];

        // Simulate concurrent transactions
        $result1 = DB::transaction(function () use ($line1, $data) {
            return $this->service->import($line1, $data);
        });
        $this->assertTrue($result1);

        $result2 = DB::transaction(function () use ($line2, $data) {
            return $this->service->import($line2, $data);
        });
        $this->assertTrue($result2);

        // Verify both transactions were created
        $this->assertEquals(2, Transaction::count());
        
        // Verify final balance is correct (1000 + 100 + 200)
        $this->client->refresh();
        $this->assertEquals(1300.00, $this->client->balance);
    }

} 