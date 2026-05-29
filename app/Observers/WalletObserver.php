<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Wallet;

/**
 * Defensive normalization for wallet writes.
 *
 * Runs on every Wallet save (create + update). Keeps the persisted shape
 * consistent regardless of which call site mutated the model:
 *
 * - Currency code is uppercased so "brl" and "BRL" don't end up as two
 *   distinct currencies for the same player.
 * - Balance is trimmed to 2 decimal places so any bcmath residue that
 *   escapes the service layer (e.g. an intermediate value of 99.9999 from a
 *   higher-scale computation) does not leak into storage. Currency-specific
 *   scale is enforced at the service boundary; this is the last-line guard.
 */
class WalletObserver
{
    public function saving(Wallet $wallet): void
    {
        if (! empty($wallet->currency)) {
            $wallet->currency = strtoupper((string) $wallet->currency);
        }

        if ($wallet->balance !== null) {
            $wallet->balance = (float) number_format((float) $wallet->balance, 2, '.', '');
        }
    }
}
