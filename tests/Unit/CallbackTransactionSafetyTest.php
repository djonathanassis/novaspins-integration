<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class CallbackTransactionSafetyTest extends TestCase
{
    public function test_callback_controller_uses_transaction_and_lock_for_update(): void
    {
        $source = file_get_contents(base_path('app/Http/Controllers/Api/ProviderCallbackController.php'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('DB::transaction', $source);
        $this->assertStringContainsString('lockForUpdate', $source);
    }
}
