<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_send_money_to_another_wallet_and_transactions_are_recorded()
    {
        // Create sender user and client
        $senderUser = User::factory()->create(['name' => 'SenderUser']);
        $senderClient = Client::factory()->create([
            'name' => 'SenderUser',
            'balance' => 200.00,
            'status' => 'active',
        ]);

        // Create receiver client
        $receiverClient = Client::factory()->create([
            'name' => 'ReceiverUser',
            'balance' => 50.00,
            'status' => 'active',
        ]);

        $payload = [
            'receiver_name' => 'ReceiverUser',
            'amount' => 100.00,
            'reference' => 'REF-TEST-001',
            'date' => '2024-06-10',
            'currency' => 'SAR',
            'payment_type' => '01',
            'charge_details' => 'OUR',
        ];

        $response = $this->actingAs($senderUser)->postJson('/api/transactions/xml', $payload);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $this->assertStringContainsString('<BeneficiaryName>ReceiverUser</BeneficiaryName>', $response->getContent());

        // Refresh models
        $senderClient->refresh();
        $receiverClient->refresh();

        // Check balances
        $this->assertEquals(100.00, $senderClient->balance);
        $this->assertEquals(150.00, $receiverClient->balance);

        // Check transactions
        $this->assertDatabaseHas('transactions', [
            'reference' => 'REF-TEST-001-DEBIT',
            'client_id' => $senderClient->id,
            'type' => 'debit',
            'amount' => -100.00,
            'from' => 'SenderUser',
            'to' => 'ReceiverUser',
        ]);
        $this->assertDatabaseHas('transactions', [
            'reference' => 'REF-TEST-001-CREDIT',
            'client_id' => $receiverClient->id,
            'type' => 'credit',
            'amount' => 100.00,
            'from' => 'SenderUser',
            'to' => 'ReceiverUser',
        ]);
    }
} 