<?php

namespace App\Services\BankStrategy;

interface BankStrategyInterface
{
    /**
     * Import a transaction line into the database
     */
    public function import(string $line, array $data): bool;

    /**
     * Generate XML for money transfer
     * 
     * @param array $data {
     *     @type string $reference
     *     @type string $date
     *     @type float $amount
     *     @type string $currency
     *     @type string $senderAccount
     *     @type string $receiverBankCode
     *     @type string $receiverAccount
     *     @type string $receiverName
     *     @type array|null $notes
     *     @type string|null $paymentType
     *     @type string|null $chargeDetails
     * }
     * @return string XML string
     */
    public function generateXml(string $line): string;
}