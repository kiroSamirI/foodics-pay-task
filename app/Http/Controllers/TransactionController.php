<?php

namespace App\Http\Controllers;

use App\Repositories\TransactionRepository;
use App\Services\PaymentXmlService;
use App\Services\WebhookEncryptionService;
use App\Helpers\ArrayHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\TransactionType;

class TransactionController extends Controller
{
    public function show(Request $request, $id)
    {
        $transaction = (new TransactionRepository)->getTransactionById($id);
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $data = ArrayHelper::flatten([$transaction->toArray() , $transaction->client->toArray() ]);

        $xml = (new PaymentXmlService)->generatePaymentXml($data);
        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    public function sendXml(Request $request)
    {
        $request->validate([
            'receiver_name' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'required|string',
            'date' => 'required|date',
            'currency' => 'required|string',
            'payment_type' => 'nullable|string',
            'charge_details' => 'nullable|string',
        ]);

        // Assume authenticated user is the sender
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        // Find sender client by user name (or adjust if Client is related to User)
        $sender = \App\Models\Client::where('name', $user->name)->first();
        if (!$sender) {
            return response()->json(['error' => 'Sender client not found'], 404);
        }

        // Find receiver client by name
        $receiver = \App\Models\Client::where('name', $request->receiver_name)->first();
        if (!$receiver) {
            return response()->json(['error' => 'Receiver client not found'], 404);
        }

        // Check both clients are not pending
        if ($sender->status === 'pending' || $receiver->status === 'pending') {
            return response()->json(['error' => 'Sender or receiver is pending'], 400);
        }

        // Check sender has enough balance
        $amount = (float) $request->amount;

        // Use DB transaction for atomicity
        $result = DB::transaction(function () use ($sender, $receiver, $amount) {
            // Re-fetch with lock for update
            $sender = \App\Models\Client::where('id', $sender->id)->lockForUpdate()->first();
            $receiver = \App\Models\Client::where('id', $receiver->id)->lockForUpdate()->first();

            if ($sender->balance < $amount) {
                throw new \Exception('Insufficient balance');
            }

            $sender->balance -= $amount;
            $receiver->balance += $amount;
            $sender->save();
            $receiver->save();
            return true;
        });

        if ($result !== true) {
            return response()->json(['error' => 'Transaction failed'], 500);
        }

        // After successful transfer, record transactions for both sender (debit) and receiver (credit)
        $now = now();
        $reference = $request->reference;
        $date = $request->date;
        $currency = $request->currency;
        $paymentType = $request->payment_type ?? null;
        $chargeDetails = $request->charge_details ?? null;

        \App\Models\Transaction::create([
            'reference' => $reference . '-DEBIT',
            'date' => $date,
            'amount' => -$amount, // Debit
            'type' => TransactionType::DEBIT,
            'from' => $sender->name,
            'to' => $receiver->name,
            'metadata' => [
                'counterparty' => $receiver->name,
                'currency' => $currency,
                'payment_type' => $paymentType,
                'charge_details' => $chargeDetails,
            ],
            'client_id' => $sender->id,
        ]);

        \App\Models\Transaction::create([
            'reference' => $reference . '-CREDIT',
            'date' => $date,
            'amount' => $amount, // Credit
            'type' => TransactionType::CREDIT,
            'from' => $sender->name,
            'to' => $receiver->name,
            'metadata' => [
                'counterparty' => $sender->name,
                'currency' => $currency,
                'payment_type' => $paymentType,
                'charge_details' => $chargeDetails,
            ],
            'client_id' => $receiver->id,
        ]);

        // Prepare data for XML
        $data = [
            'reference' => $request->reference,
            'date' => $request->date,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'bank' => $request->bank ?? null,
            'sender_account_number' => null, // Not used
            'receiver_bank_code' => null, // Not used
            'receiver_account_number' => null, // Not used
            'beneficiary_name' => $receiver->name,
            'payment_type' => $request->payment_type ?? null,
            'charge_details' => $request->charge_details ?? null,
        ];

        $xml = (new PaymentXmlService)->generatePaymentXml($data);
        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
} 