<?php

namespace App\Http\Controllers;

use App\Repositories\TransactionRepository;
use App\Services\PaymentXmlService;
use App\Services\WebhookEncryptionService;
use App\Helpers\ArrayHelper;
use Illuminate\Http\Request;

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
} 