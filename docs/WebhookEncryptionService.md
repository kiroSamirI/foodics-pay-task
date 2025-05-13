# WebhookEncryptionService

The `WebhookEncryptionService` provides secure encryption and decryption of webhook data for different banks. It uses asymmetric encryption (public/private key pairs) to ensure secure communication.

## Usage

```php
use App\Services\WebhookEncryptionService;

$service = new WebhookEncryptionService();

// Encrypt data
$data = [
    'transaction_id' => '12345',
    'status' => 'completed',
    'amount' => '1000.00'
];

$encrypted = $service->encrypt($data, 'foodics');

// Decrypt data
$decrypted = $service->decrypt($encrypted, 'foodics');
```

## Supported Banks

The service supports multiple banks, each with its own key pair:
- Foodics Bank
- Other banks can be added by implementing the `BankStrategy` interface

## Key Management

### Generating Keys
```php
$service->generateTestKeys('foodics');
```

This will create:
- `storage/app/keys/foodics_public.pem`
- `storage/app/keys/foodics_private.pem`

### Key Storage
- Public keys are stored in `storage/app/keys/{bank}_public.pem`
- Private keys are stored in `storage/app/keys/{bank}_private.pem`
- Keys are automatically loaded when needed

## Security Features

### Encryption
- Uses RSA encryption
- Keys are 2048 bits
- Data is base64 encoded after encryption

### Decryption
- Validates encrypted data format
- Handles base64 decoding
- Verifies data integrity

## Error Handling

The service handles various error cases:
- Invalid bank name
- Missing keys
- Invalid encrypted data
- Decryption failures

## Testing

The service includes comprehensive unit tests covering:
- Basic encryption/decryption
- Large data handling
- Special character handling
- Unicode character support
- Error cases

Run the tests with:
```bash
php artisan test tests/Unit/WebhookEncryptionTest.php
```

## Best Practices

1. Always use the service for webhook data
2. Keep private keys secure
3. Rotate keys periodically
4. Monitor encryption/decryption failures
5. Log security events

## Integration Example

```php
use App\Services\WebhookEncryptionService;

class WebhookController extends Controller
{
    private WebhookEncryptionService $encryptionService;

    public function __construct(WebhookEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    public function handleWebhook(Request $request)
    {
        try {
            $bank = $request->input('bank');
            $encryptedData = $request->input('encrypted_data');
            
            $data = $this->encryptionService->decrypt($encryptedData, $bank);
            
            // Process the decrypted data
            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid webhook data'], 400);
        }
    }
}
```

## API Endpoints

### Encrypt Data
```http
POST /api/webhooks/encrypt
Content-Type: application/json

{
    "data": {
        "transaction_id": "12345",
        "status": "completed",
        "amount": "1000.00"
    },
    "bank": "foodics"
}
```

### Decrypt Data
```http
POST /api/webhooks/decrypt
Content-Type: application/json

{
    "encrypted_data": "base64_encoded_data",
    "bank": "foodics"
}
```

## Security Considerations

1. **Key Storage**
   - Private keys should be stored securely
   - Consider using a key management service
   - Implement key rotation policies

2. **Data Validation**
   - Validate all input data
   - Sanitize output data
   - Handle special characters properly
