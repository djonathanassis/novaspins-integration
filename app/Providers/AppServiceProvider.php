<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\TransactionType;
use App\Http\Controllers\Api\ProviderCallbackController;
use App\Models\Wallet;
use App\Observers\WalletObserver;
use App\Services\Callbacks\BetHandler;
use App\Services\Callbacks\RollbackHandler;
use App\Services\Callbacks\WinHandler;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->when(ProviderCallbackController::class)
            ->needs('$handlers')
            ->give(function (Application $app): array {
                return [
                    TransactionType::Bet->value => $app->make(BetHandler::class),
                    TransactionType::Win->value => $app->make(WinHandler::class),
                    TransactionType::Rollback->value => $app->make(RollbackHandler::class),
                ];
            });
    }

    public function boot(): void
    {
        Wallet::observe(WalletObserver::class);

        RateLimiter::for('novaspins-callback', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('novaspins-replay', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip() ?? 'unknown');
        });
    }
}
