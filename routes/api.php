<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PlayerTransactionController;
use App\Http\Controllers\Api\PlayerWalletController;
use App\Http\Controllers\Api\ProviderCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/providers/novaspins/callback', ProviderCallbackController::class)
    ->middleware('provider.callback');

// Manual replay endpoint used by ops to re-drive callbacks from the provider's
// audit log when our worker missed the live webhook (network blips, deploys).
// Payloads here come from NovaSpins' reconciliation tool, which already
// verifies their authenticity upstream — so we only need the log trail here.
Route::post('/providers/novaspins/callback/replay', ProviderCallbackController::class)
    ->middleware('provider.callback.replay');

Route::get('/players/{id}/wallet', PlayerWalletController::class)
    ->whereNumber('id');

Route::get('/players/{id}/transactions', PlayerTransactionController::class)
    ->whereNumber('id');
