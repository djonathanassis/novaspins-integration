<?php

declare(strict_types=1);

namespace App\Services\Callbacks;

use App\Enums\TransactionType;
use App\Models\Wallet;

class BetHandler extends BaseHandler
{
    protected function type(): TransactionType
    {
        return TransactionType::Bet;
    }

    protected function applyWalletOperation(Wallet $wallet, string $amount): Wallet
    {
        return $this->walletService->debit($wallet, $amount);
    }
}
