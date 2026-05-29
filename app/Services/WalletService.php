<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transaction;
use App\Models\Wallet;
use RuntimeException;

class WalletService
{
    public function debit(Wallet $wallet, float $amount): Wallet
    {
        $wallet->refresh();

        if ($wallet->balance < $amount) {
            throw new RuntimeException('Insufficient balance');
        }

        $wallet->balance = (float) bcsub((string) $wallet->balance, (string) $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function credit(Wallet $wallet, float $amount): Wallet
    {
        $wallet->refresh();
        $wallet->balance = (float) bcadd((string) $wallet->balance, (string) $amount, 2);
        $wallet->save();

        return $wallet;
    }

    public function reverse(Wallet $wallet, Transaction $original): Wallet
    {
        if ($original->type === Transaction::TYPE_BET) {
            return $this->credit($wallet, $original->amount);
        }

        if ($original->type === Transaction::TYPE_WIN) {
            return $this->debit($wallet, $original->amount);
        }

        throw new RuntimeException('Cannot reverse a transaction of type ' . $original->type);
    }
}
