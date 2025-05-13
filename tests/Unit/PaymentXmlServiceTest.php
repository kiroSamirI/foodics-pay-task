<?php

namespace Tests\Unit;

use App\Services\PaymentXmlService;
use Tests\TestCase;

class PaymentXmlServiceTest extends TestCase
{
    private PaymentXmlService $service;
    private array $baseData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentXmlService();
        
        // Setup base test data
        $this->baseData = [
            'reference' => 'REF123',
            'date' => '2024-05-08',
            'amount' => '1000.00',
            'currency' => 'SAR',
            'bank' => 'FOODICS',
            'sender_account_number' => '1234567890',
            'receiver_bank_code' => 'ABC123',
            'receiver_account_number' => '9876543210',
            'beneficiary_name' => 'John Doe'
        ];
    }

    public function testBasicPaymentXmlGeneration()
    {
        $xml = $this->service->generatePaymentXml($this->baseData);

        $this->assertBasicXmlStructure($xml);
        $this->assertBasicXmlData($xml);
    }

    public function testPaymentXmlWithCustomPaymentType()
    {
        $data = array_merge($this->baseData, ['payment_type' => '01']);
        $xml = $this->service->generatePaymentXml($data);
        
        $this->assertBasicXmlStructure($xml);
        $this->assertStringContainsString('<PaymentType>01</PaymentType>', $xml);
    }

    public function testPaymentXmlWithCustomChargeDetails()
    {
        $data = array_merge($this->baseData, ['charge_details' => 'BEN']);
        $xml = $this->service->generatePaymentXml($data);
        
        $this->assertBasicXmlStructure($xml);
        $this->assertStringContainsString('<ChargeDetails>BEN</ChargeDetails>', $xml);
    }

    public function testPaymentXmlWithEmptyValues()
    {
        $data = array_merge($this->baseData, [
            'sender_account_number' => '',
            'receiver_account_number' => null
        ]);
        
        $xml = $this->service->generatePaymentXml($data);

        // Don't assert basic structure since we're testing empty values
        $this->assertStringNotContainsString('<AccountNumber></AccountNumber>', $xml);
        $this->assertStringNotContainsString('<AccountNumber>null</AccountNumber>', $xml);
    }

    public function testPaymentXmlWithSpecialCharacters()
    {
        $data = array_merge($this->baseData, [
            'beneficiary_name' => 'John & Doe <test>',
            'notes' => ['Note & 1', 'Note < 2']
        ]);
        
        $xml = $this->service->generatePaymentXml($data);

        $this->assertBasicXmlStructure($xml);
        $this->assertStringContainsString('John &amp; Doe &lt;test&gt;', $xml);
        $this->assertStringContainsString('Note &amp; 1', $xml);
        $this->assertStringContainsString('Note &lt; 2', $xml);
    }

    private function assertBasicXmlStructure(string $xml): void
    {
        $this->assertStringContainsString('<PaymentRequestMessage>', $xml);
        $this->assertStringContainsString('<TransferInfo>', $xml);
        $this->assertStringContainsString('<SenderInfo>', $xml);
        $this->assertStringContainsString('<ReceiverInfo>', $xml);
    }

    private function assertBasicXmlData(string $xml): void
    {
        $this->assertStringContainsString('<Reference>REF123</Reference>', $xml);
        $this->assertStringContainsString('<Amount>1000.00</Amount>', $xml);
        $this->assertStringContainsString('<Currency>SAR</Currency>', $xml);
        $this->assertStringContainsString('<Bank>FOODICS</Bank>', $xml);
        $this->assertStringContainsString('<AccountNumber>1234567890</AccountNumber>', $xml);
        $this->assertStringContainsString('<BankCode>ABC123</BankCode>', $xml);
        $this->assertStringContainsString('<BeneficiaryName>John Doe</BeneficiaryName>', $xml);
    }
} 