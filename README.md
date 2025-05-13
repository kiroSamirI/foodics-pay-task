# Foodics Payment Processing System

A Laravel-based payment processing system that handles bank transactions, webhook notifications, and payment XML generation.

## Features

- Bank transaction processing with support for multiple banks
- Webhook encryption and decryption for secure communication
- XML payment request generation
- Transaction management with metadata support
- Client management system
- Comprehensive test coverage

## Project Structure

```
app/
├── Console/         # Console commands
├── Enums/          # Enumeration classes
├── Http/           # Controllers and middleware
├── Jobs/           # Queue jobs
├── Models/         # Eloquent models
├── Providers/      # Service providers
├── Services/       # Business logic services
└── Service/        # Additional services

tests/
├── Feature/        # Feature tests
├── Unit/           # Unit tests
└── Performance/    # Performance tests
```

## Key Components

### Models
- `Transaction`: Handles payment transactions with metadata support
- `Client`: Manages client information and relationships

### Services
- `PaymentXmlService`: Generates XML for payment requests
- `WebhookEncryptionService`: Handles webhook encryption/decryption
- `BankTransactionService`: Processes bank transactions

### Tests
- Unit tests for individual components
- Feature tests for integration scenarios
- Performance tests for system optimization

## Setup

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy `.env.example` to `.env` and configure:
   ```bash
   cp .env.example .env
   ```
4. Generate application key:
   ```bash
   php artisan key:generate
   ```
5. Run migrations:
   ```bash
   php artisan migrate
   ```

## Testing

Run the test suite:
```bash
php artisan test
```

Run specific test groups:
```bash
# Unit tests
php artisan test tests/Unit

# Feature tests
php artisan test tests/Feature

# Performance tests
php artisan test tests/Performance
```

## API Documentation

### Transaction Processing

#### Create Transaction
```http
POST /api/transactions
Content-Type: application/json

{
    "reference": "REF123",
    "amount": "1000.00",
    "date": "2024-05-08",
    "metadata": {
        "type": "PAYMENT",
        "bank": "foodics"
    }
}
```


### Webhook Endpoints

#### Process Webhook
```http
POST /api/webhooks
Content-Type: application/json

{
    "encrypted_data": "...",
    "bank": "foodics"
}
```

## Security

- All webhook communications are encrypted



