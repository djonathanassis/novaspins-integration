<?php

declare(strict_types=1);

namespace Tests\Feature\Unit;

use App\Services\HmacValidator;
use Tests\TestCase;

class HmacValidatorTest extends TestCase
{
    public function test_hmac_validator_uses_hash_equals_for_signature_comparison(): void
    {
        $source = file_get_contents(base_path('app/Services/HmacValidator.php'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString('hash_equals', $source);
    }

    public function test_hmac_validator_accepts_valid_signature(): void
    {
        $validator = new HmacValidator('test-secret');
        $payload = '{"type":"bet","amount":10.00}';
        $signature = hash_hmac('sha256', $payload, 'test-secret');

        $this->assertTrue($validator->isValid($payload, $signature));
    }
}
