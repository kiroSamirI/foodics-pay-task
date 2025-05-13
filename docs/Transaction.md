# Transaction Model

The `Transaction` model represents a payment transaction in the system. It includes support for metadata, client relationships, and various data types.

## Attributes

### Fillable
- `reference` (string): Unique transaction reference
- `date` (datetime): Transaction date and time
- `amount` (decimal): Transaction amount (2 decimal places)
- `metadata` (json): Additional transaction data
- `client_id` (integer): Associated client ID

### Casts
- `id` (integer)
- `date` (datetime)
- `amount` (decimal:2)
- `metadata` (json)

## Relationships

### Client
```php
public function client()
{
    return $this->belongsTo(Client::class);
}
```

## Usage

### Creating a Transaction
```php
use App\Models\Transaction;

$transaction = Transaction::create([
    'reference' => 'REF123',
    'date' => '2024-05-08',
    'amount' => '1234.56',
    'metadata' => [
        'type' => 'PAYMENT',
        'bank' => 'foodics'
    ],
    'client_id' => 1
]);
```

### Accessing Data
```php
// Get formatted date
$date = $transaction->date->format('Y-m-d');

// Get amount as string
$amount = $transaction->amount; // "1234.56"

// Access metadata
$type = $transaction->metadata['type'];
$bank = $transaction->metadata['bank'];

// Get client
$client = $transaction->client;
```

## Data Types

### Amount
- Stored as decimal with 2 decimal places
- Always returned as string
- Example: "1234.56"

### Date
- Stored as datetime
- Can be formatted using Carbon methods
- Example: "2024-05-08 12:00:00"

### Metadata
- Stored as JSON
- Can contain nested arrays and objects
- Example:
```json
{
    "type": "PAYMENT",
    "bank": "foodics",
    "notes": ["Note 1", "Note 2"]
}
```

## Testing

The model includes comprehensive unit tests covering:
- Attribute casting
- Fillable attributes
- Client relationship
- Date formatting
- Amount handling
- Metadata storage

Run the tests with:
```bash
php artisan test tests/Unit/TransactionTest.php
```

