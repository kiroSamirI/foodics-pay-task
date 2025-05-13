<?php

namespace Tests\Service\BankStrategy;

use App\Services\BankStrategy\AcmeTransactionService;
use App\Services\BankStrategy\BankStrategyFactory;
use App\Services\BankStrategy\FoodicsBankTransactionService;
use PHPUnit\Framework\TestCase;

class BankStrategyFactoryTest extends TestCase
{
    public function testCreateFoodicsStrategy()
    {
        $strategy = BankStrategyFactory::create('foodics');
        $this->assertInstanceOf(FoodicsBankTransactionService::class, $strategy);
    }

    public function testCreateAcmeStrategy()
    {
        $strategy = BankStrategyFactory::create('acme');
        $this->assertInstanceOf(AcmeTransactionService::class, $strategy);
    }

    public function testCreateInvalidStrategy()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported bank: invalid_bank');
        
        BankStrategyFactory::create('invalid_bank');
    }
} 