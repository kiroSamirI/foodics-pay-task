<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\Client;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private Transaction $transaction;
    private array $testData;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transaction = new Transaction();
        
        // Setup common test data
        $this->testData = [
            'reference' => 'REF123',
            'date' => '2024-05-08 12:00:00',
            'amount' => '1234.56',
            'metadata' => ['key' => 'value', 'nested' => ['data' => 123]],
            'client_id' => null,
            'type' => 'debit',
        ];

        // Create a client for relationship tests
        $this->client = Client::factory()->create();
    }

    public function testFillableAttributes()
    {
        $expectedFillable = [
            'reference',
            'date',
            'amount',
            'metadata',
            'client_id',
            'type',
            'from',
            'to'
        ];
        $this->assertEquals($expectedFillable, $this->transaction->getFillable());
    }

    public function testAttributeCasting()
    {
        $expectedCasts = [
            'id' => 'int',
            'date' => 'datetime',
            'amount' => 'decimal:2',
            'metadata' => 'json'
        ];
        $this->assertEquals($expectedCasts, $this->transaction->getCasts());
    }

    public function testClientRelationship()
    {
        $transaction = $this->createTransactionWithClient();
        
        $this->assertInstanceOf(Client::class, $transaction->client);
        $this->assertEquals($this->client->id, $transaction->client->id);
    }

    public function testDateCasting()
    {
        $transaction = $this->createTransactionWithClient();
        
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals($this->testData['date'], $transaction->date->format('Y-m-d H:i:s'));
    }

    public function testAmountCasting()
    {
        $transaction = $this->createTransactionWithClient();
        
        $this->assertIsString($transaction->amount);
        $this->assertEquals($this->testData['amount'], $transaction->amount);
    }

    public function testMetadataCasting()
    {
        $transaction = $this->createTransactionWithClient();
        
        $this->assertIsArray($transaction->metadata);
        $this->assertEquals($this->testData['metadata'], $transaction->metadata);
    }

    private function createTransactionWithClient(): Transaction
    {
        $data = array_merge($this->testData, [
            'client_id' => $this->client->id,
            'type' => 'debit',
        ]);
        return Transaction::factory()->create($data);
    }
} 