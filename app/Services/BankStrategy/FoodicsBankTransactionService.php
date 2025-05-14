<?php
namespace App\Services\BankStrategy;

use App\Models\Transaction;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;
use App\Services\PaymentXmlService;

class FoodicsBankTransactionService implements BankStrategyInterface
{
    private PaymentXmlService $xmlService;

    public function __construct()
    {
        $this->xmlService = new PaymentXmlService();
    }

    public function import(string $line, array $data): bool
    {
        try {
            Log::info('Processing Foodics transaction', ['line' => $line]);

            // Parse the transaction line
            if (!preg_match('/^(?P<date>\d{8})(?P<amount>\d+,\d{2})#(?P<reference>\d+)#note\/(?P<note>.*?)\/type\/(?P<type>.*?)$/', $line, $matches)) {
                Log::error('Invalid Foodics transaction format', ['line' => $line]);
                return false;
            }

            $date = $matches['date'];
            $amount = str_replace(',', '.', $matches['amount']);
            $reference = $matches['reference'];
            $note = $matches['note'];
            $type = $matches['type'];

            Log::info('Parsed transaction data', [
                'date' => $date,
                'amount' => $amount,
                'reference' => $reference,
                'note' => $note,
                'type' => $type,
                'client_id' => $data['client_id'] ?? null
            ]);

            // Check for duplicate transaction with shared lock
            $exists = Transaction::where('reference', $reference)
                ->where('client_id', $data['client_id'])
                ->sharedLock()
                ->exists();

            if ($exists) {
                Log::info('Duplicate transaction found', ['reference' => $reference]);
                return false;
            }

            return DB::transaction(function () use ($amount, $reference, $date, $note, $type, $data) {
                // Get the client with exclusive lock for update
                $client = Client::where('id', $data['client_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // Create the transaction
                $transaction = Transaction::create([
                    'reference' => $reference,
                    'date' => Carbon::createFromFormat('Ymd', $date)->toDateTimeString(),
                    'amount' => $amount,
                    'metadata' => [
                        'note' => $note,
                        'type' => $type,
                        'bank' => 'foodics'
                    ],
                    'client_id' => $data['client_id']
                ]);

                // Update client balance
                $client->increment('balance', $amount);

                Log::info('Transaction created successfully', [
                    'reference' => $reference,
                    'transaction_id' => $transaction->id,
                    'client_id' => $client->id,
                    'new_balance' => $client->balance
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Error processing Foodics transaction', [
                'error' => $e->getMessage(),
                'line' => $line,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function generateXml(string $line): string
    {
        if (!preg_match('/^(?P<date>\d{8})(?P<amount>\d+,\d{2})#(?P<reference>\d+)#note\/(?P<note>.*?)\/type\/(?P<type>.*?)$/', $line, $matches)) {
            throw new \InvalidArgumentException('Invalid Foodics transaction format');
        }

        $date = $matches['date'];
        $amount = str_replace(',', '.', $matches['amount']);
        $reference = $matches['reference'];
        $note = $matches['note'];
        $type = $matches['type'];

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><transaction></transaction>');
        $xml->addChild('amount', $amount);
        $xml->addChild('reference', $reference);
        $xml->addChild('date', $date);
        $xml->addChild('note', $note);
        $xml->addChild('type', $type);
        $xml->addChild('bank', 'foodics');

        return $xml->asXML();
    }

    public function generatePaymentXml(array $data): string
    {
        $xmlData = [
            'reference' => $data['reference'],
            'date' => $data['date'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'sender_account_number' => $data['senderAccount'],
            'receiver_bank_code' => $data['receiverBankCode'],
            'receiver_account_number' => $data['receiverAccount'],
            'beneficiary_name' => $data['receiverName'],
            'notes' => $data['notes'] ?? null,
            'payment_type' => $data['paymentType'] ?? null,
            'charge_details' => $data['chargeDetails'] ?? null
        ];

        return $this->xmlService->generatePaymentXml($xmlData);
    }
}