<?php
namespace App\Services\BankStrategy;

use App\Models\Client;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;
use App\Services\PaymentXmlService;
use Carbon\Carbon;

class AcmeTransactionService implements BankStrategyInterface
{
    private PaymentXmlService $xmlService;

    public function __construct()
    {
        $this->xmlService = new PaymentXmlService();
    }

    public function import(string $line, array $data): bool
    {
        try {
            Log::info('Processing Acme transaction', ['line' => $line]);

            // Parse the transaction line
            if (!preg_match('/^(?P<amount>\d+(,\d{2})?)\/{2}(?P<reference>\d+)\/{2}(?P<date>\d{8})$/', $line, $matches)) {
                Log::error('Invalid Acme transaction format', ['line' => $line]);
                return false;
            }

            $amount = (float) str_replace(',', '.', $matches['amount']);
            $reference = $matches['reference'];
            $date = $matches['date'];

            // Check for duplicate transaction with shared lock
            $exists = Transaction::where('reference', $reference)
                ->where('client_id', $data['client_id'])
                ->sharedLock()
                ->exists();

            if ($exists) {
                Log::info('Duplicate transaction found', ['reference' => $reference]);
                return false;
            }

            return DB::transaction(function () use ($amount, $reference, $date, $data) {
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
                        'bank' => 'acme'
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
            Log::error('Error processing Acme transaction', [
                'error' => $e->getMessage(),
                'line' => $line
            ]);
            throw $e;
        }
    }

    public function generateTransferXml(array $data): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><PaymentRequestMessage/>');
        
        // TransferInfo
        $transferInfo = $xml->addChild('TransferInfo');
        $transferInfo->addChild('Reference', $data['reference']);
        $transferInfo->addChild('Date', $data['date']);
        $transferInfo->addChild('Amount', number_format($data['amount'], 2, '.', ''));
        $transferInfo->addChild('Currency', $data['currency']);

        // SenderInfo
        $senderInfo = $xml->addChild('SenderInfo');
        $senderInfo->addChild('AccountNumber', $data['senderAccount']);

        // ReceiverInfo
        $receiverInfo = $xml->addChild('ReceiverInfo');
        $receiverInfo->addChild('BankCode', $data['receiverBankCode']);
        $receiverInfo->addChild('AccountNumber', $data['receiverAccount']);
        $receiverInfo->addChild('BeneficiaryName', $data['receiverName']);

        // Optional Notes
        if (!empty($data['notes'])) {
            $notes = $xml->addChild('Notes');
            foreach ($data['notes'] as $note) {
                $notes->addChild('Note', $note);
            }
        }

        // Optional PaymentType
        if (isset($data['paymentType']) && $data['paymentType'] !== '99') {
            $xml->addChild('PaymentType', $data['paymentType']);
        }

        // Optional ChargeDetails
        if (isset($data['chargeDetails']) && $data['chargeDetails'] !== 'SHA') {
            $xml->addChild('ChargeDetails', $data['chargeDetails']);
        }

        return $xml->asXML();
    }

    public function generateXml(string $line): string
    {
        if (!preg_match('/^(?P<amount>\d+(,\d{2})?)\/{2}(?P<reference>\d+)\/{2}(?P<date>\d{8})$/', $line, $matches)) {
            throw new \InvalidArgumentException('Invalid Acme transaction format');
        }

        $amount = (float) str_replace(',', '.', $matches['amount']);
        $reference = $matches['reference'];
        $date = $matches['date'];

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><transaction></transaction>');
        $xml->addChild('amount', $amount);
        $xml->addChild('reference', $reference);
        $xml->addChild('date', $date);
        $xml->addChild('bank', 'acme');

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