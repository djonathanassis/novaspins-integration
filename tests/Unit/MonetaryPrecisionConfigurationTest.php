<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\Wallet;
use Tests\TestCase;

class MonetaryPrecisionConfigurationTest extends TestCase
{
    public function test_wallet_and_transaction_model_casts_are_decimal_strings(): void
    {
        $walletCasts = (new Wallet)->getCasts();
        $transactionCasts = (new Transaction)->getCasts();

        $this->assertSame('decimal:2', $walletCasts['balance']);
        $this->assertSame('decimal:2', $transactionCasts['amount']);
    }

    public function test_wallet_service_does_not_cast_bc_math_result_to_float(): void
    {
        $source = file_get_contents(base_path('app/Services/WalletService.php'));

        $this->assertNotFalse($source);
        $this->assertStringNotContainsString('(float) bcsub', $source);
        $this->assertStringNotContainsString('(float) bcadd', $source);
    }
}
