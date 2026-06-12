<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use RuntimeException;

class WalletService
{
    public function debit(Wallet $wallet, string $amount): Wallet
    {
        $this->ensureAmountIsPositive($amount);

        $wallet->refresh();

        if (bccomp($wallet->balance, $amount, 2) < 0) {
            throw new RuntimeException('Insufficient balance');
        }

        $wallet->balance = bcsub($wallet->balance, $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function credit(Wallet $wallet, string $amount): Wallet
    {
        $this->ensureAmountIsPositive($amount);

        $wallet->refresh();
        $wallet->balance = bcadd($wallet->balance, $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function reverse(Wallet $wallet, Transaction $original): Wallet
    {
        if ($original->type === TransactionType::Bet) {
            return $this->credit($wallet, $original->amount);
        }

        if ($original->type === TransactionType::Win) {
            return $this->debit($wallet, $original->amount);
        }

        throw new RuntimeException('Cannot reverse a transaction of type '.$original->type->value);
    }

    private function ensureAmountIsPositive(string $amount): void
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new RuntimeException('Amount must be greater than zero');
        }
    }
}
