<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class CallbackTransactionSafetyTest extends TestCase
{
    // Verifica se os handlers usam DB::transaction + lockForUpdate
    public function test_callback_handlers_use_transaction_and_lock_for_update(): void
    {
        $handlerFiles = [
            'app/Services/Callbacks/BaseHandler.php',
            'app/Services/Callbacks/RollbackHandler.php',
        ];

        foreach ($handlerFiles as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertNotFalse($source, "Could not read {$file}");
            $this->assertStringContainsString('DB::transaction', $source, "Missing DB::transaction in {$file}");
            $this->assertStringContainsString('lockForUpdate', $source, "Missing lockForUpdate in {$file}");
        }
    }
}
