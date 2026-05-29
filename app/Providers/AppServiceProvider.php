<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Wallet;
use App\Observers\WalletObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Wallet::observe(WalletObserver::class);
    }
}
