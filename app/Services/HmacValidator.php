<?php

declare(strict_types=1);

namespace App\Services;

class HmacValidator
{
    public function __construct(private readonly string $secret)
    {
    }

    public function isValid(string $payload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->secret);

        return hash_equals($expected, $signature);
    }
}
