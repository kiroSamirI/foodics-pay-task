<?php

namespace App\Services\BankStrategy;

class BankStrategyFactory
{
    public static function create(string $bank): BankStrategyInterface
    {
        return match ($bank) {
            'foodics' => new FoodicsBankTransactionService(),
            'acme' => new AcmeTransactionService(),
            default => throw new \InvalidArgumentException("Unsupported bank: {$bank}"),
        };
    }
} 