# PaymentXmlService

The `PaymentXmlService` is responsible for generating XML documents for payment requests. It handles various payment types, charge details, and special character encoding.

## Usage

```php
use App\Services\PaymentXmlService;

$service = new PaymentXmlService();

$data = [
    'reference' => 'REF123',
    'date' => '2024-05-08',
    'amount' => '1000.00',
    'currency' => 'SAR',
    'bank' => 'FOODICS',
    'sender_account_number' => '1234567890',
    'receiver_bank_code' => 'ABC123',
    'receiver_account_number' => '9876543210',
    'beneficiary_name' => 'John Doe',
    'payment_type' => '01', 
    'charge_details' => 'BEN'
];

$xml = $service->generatePaymentXml($data);
```

## XML Structure

The generated XML follows this structure:

```xml
<?xml version="1.0" encoding="utf-8"?>
<PaymentRequestMessage>
    <TransferInfo>
        <Reference>REF123</Reference>
        <Date>2024-05-08</Date>
        <Amount>1000.00</Amount>
        <Currency>SAR</Currency>
        <PaymentType>01</PaymentType>
        <ChargeDetails>BEN</ChargeDetails>
    </TransferInfo>
    <SenderInfo>
        <AccountNumber>1234567890</AccountNumber>
    </SenderInfo>
    <ReceiverInfo>
        <BankCode>ABC123</BankCode>
        <AccountNumber>9876543210</AccountNumber>
        <BeneficiaryName>John Doe</BeneficiaryName>
    </ReceiverInfo>
    <Notes>
        <Note>Lorem Epsum</Note>
        <Note>Lorem Epsum 2</Note>
    </Notes>
     <PaymentType>421</PaymentType> 
    <ChargeDetails>RB</ChargeDetails> 
</PaymentRequestMessage>
```

## Features

### Special Character Handling
The service automatically handles special characters in the XML:
- `&` is converted to `&amp;`
- `<` is converted to `&lt;`
- `>` is converted to `&gt;`

### Optional Fields
The following fields are optional and will be omitted if empty:
- `payment_type`
- `charge_details`
- `sender_account_number`
- `receiver_account_number`

### Data Validation
The service validates:
- Required fields are present
- Amount format is correct
- Date format is valid

## Testing

The service includes comprehensive unit tests covering:
- Basic XML generation
- Custom payment types
- Custom charge details
- Empty value handling
- Special character encoding

Run the tests with:
```bash
php artisan test tests/Unit/PaymentXmlServiceTest.php
``` 