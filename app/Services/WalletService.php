<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use RuntimeException;

class WalletService
{
    public function debit(Wallet $wallet, string $amount): Wallet
    {
        $this->ensureAmountIsPositive($amount);

        $wallet->refresh();

        if (bccomp((string) $wallet->balance, $amount, 2) < 0) {
            throw new RuntimeException('Insufficient balance');
        }

        $wallet->balance = (float) bcsub((string) $wallet->balance, $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function credit(Wallet $wallet, string $amount): Wallet
    {
        $this->ensureAmountIsPositive($amount);

        $wallet->refresh();
        $wallet->balance = (float) bcadd((string) $wallet->balance, $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function reverse(Wallet $wallet, Transaction $original): Wallet
    {
        if ($original->type === Transaction::TYPE_BET) {
            return $this->credit($wallet, (string) $original->amount);
        }

        if ($original->type === Transaction::TYPE_WIN) {
            return $this->debit($wallet, (string) $original->amount);
        }

        throw new RuntimeException('Cannot reverse a transaction of type ' . $original->type);
    }

    private function ensureAmountIsPositive(string $amount): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new RuntimeException('Amount must be greater than zero');
        }
    }
}
