<?php

namespace App\Http\Controllers;

use App\Enums\Banks;
use App\Jobs\ProcessTransactionJob;
use App\Repositories\TransactionRepository;
use App\Services\PaymentXmlService;
use App\Services\WebhookEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    private WebhookEncryptionService $encryptionService;

    public function __construct(WebhookEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function receive(Request $request)
    {
        try {
            // Get the bank identifier from the request
            $bank = $request->header('X-Bank-Identifier');
            if (!$bank) {
                return response()->json(['error' => 'Bank identifier missing'], 400);
            }

            // Validate bank identifier
            if (!Banks::tryFrom($bank)) {
                return response()->json(['error' => 'Invalid bank identifier'], 400);
            }

            // Get encrypted data and signature
            $encryptedData = $request->header('X-Encrypted-Data');
            $signature = $request->header('X-Signature');

            if (!$encryptedData || !$signature) {
                return response()->json(['error' => 'Missing encryption data'], 400);
            }

            // Verify and decrypt the data
            $decryptedData = $this->encryptionService->verifyAndDecrypt(
                $bank,
                $encryptedData,
                $signature
            );

            if (!$decryptedData) {
                return response()->json(['error' => 'Invalid or tampered data'], 401);
            }

            // Process the transactions
            $lines = explode("\n", $decryptedData['payload']);
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                
                ProcessTransactionJob::dispatch($line, [
                    'client_id' => $decryptedData['client_id'] ?? null,
                ], $bank);
            }

            // Encrypt and sign the response
            $responseData = ['status' => 'ok'];
            $encryptedResponse = $this->encryptionService->encryptAndSign($bank, $responseData);

            if (!$encryptedResponse) {
                return response()->json(['error' => 'Failed to encrypt response'], 500);
            }

            return response()->json($encryptedResponse);
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

}
