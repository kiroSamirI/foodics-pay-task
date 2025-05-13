<?php
namespace App\Services\BankStrategy;

use App\Models\Transaction;
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
            if (!preg_match('/^(\d{8})(\d+,\d{2})#(\d+)#note\/(.*?)\/type\/(.*?)$/', $line, $matches)) {
                Log::error('Invalid Foodics transaction format', ['line' => $line]);
                return false;
            }

            $date = $matches[1];
            $amount = str_replace(',', '.', $matches[2]);
            $reference = $matches[3];
            $note = $matches[4];
            $type = $matches[5];

            Log::info('Parsed transaction data', [
                'date' => $date,
                'amount' => $amount,
                'reference' => $reference,
                'note' => $note,
                'type' => $type,
                'client_id' => $data['client_id'] ?? null
            ]);

            // Check for duplicate transaction
            $exists = Transaction::where('reference', $reference)
                ->where('client_id', $data['client_id'])
                ->exists();

            if ($exists) {
                Log::info('Duplicate transaction found', ['reference' => $reference]);
                return false;
            }

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

            Log::info('Transaction created successfully', [
                'reference' => $reference,
                'transaction_id' => $transaction->id
            ]);

            return true;
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
        if (!preg_match('/^(\d{8})(\d+,\d{2})#(\d+)#note\/(.*?)\/type\/(.*?)$/', $line, $matches)) {
            throw new \InvalidArgumentException('Invalid Foodics transaction format');
        }

        $date = $matches[1];
        $amount = str_replace(',', '.', $matches[2]);
        $reference = $matches[3];
        $note = $matches[4];
        $type = $matches[5];

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
            'charge_details' => $data['chargeDetails'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
            'bank' => 'foodics'
        ];

        return $this->xmlService->generatePaymentXml($xmlData);
    }
}