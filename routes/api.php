<?php

use App\Http\Controllers\WebhookController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks', [WebhookController::class, 'receive']);
Route::get('/transactions/{id}', [TransactionController::class, 'show']);