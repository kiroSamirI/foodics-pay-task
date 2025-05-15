<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\TransactionType;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'reference' => $this->faker->unique()->uuid,
            'date' => $this->faker->dateTimeThisYear(),
            'amount' => $this->faker->randomFloat(2, 10, 10000),
            'metadata' => [
                'source' => $this->faker->randomElement(['web', 'mobile', 'api']),
                'ip' => $this->faker->ipv4,
                'user_agent' => $this->faker->userAgent
            ],
            'client_id' => Client::factory(),
            'type' => $this->faker->randomElement([TransactionType::DEBIT, TransactionType::CREDIT]),
        ];
    }
} 