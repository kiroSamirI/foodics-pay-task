<?php

namespace App\Jobs;

use App\Services\BankStrategy\BankStrategyFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $line,
        private array $data,
        private string $bank
    ) {}

    public function handle(): void
    {
        try {
            Log::info('Processing transaction job', [
                'line' => $this->line,
                'bank' => $this->bank,
                'data' => $this->data
            ]);

            $strategy = BankStrategyFactory::create($this->bank);
            $result = $strategy->import($this->line, $this->data);

            Log::info('Transaction processed successfully', [
                'result' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error processing transaction job', [
                'error' => $e->getMessage(),
                'line' => $this->line,
                'bank' => $this->bank
            ]);
            throw $e;
        }
    }
} 