<?php

declare(strict_types=1);

namespace App\Services\Callbacks;

use App\Enums\TransactionType;
use App\Models\Wallet;

class WinHandler extends BaseHandler
{
    protected function type(): TransactionType
    {
        return TransactionType::Win;
    }

    protected function applyWalletOperation(Wallet $wallet, string $amount): Wallet
    {
        return $this->walletService->credit($wallet, $amount);
    }
}
