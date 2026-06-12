<?php

declare(strict_types=1);

namespace App\Services\Callbacks\Data;

use App\Enums\TransactionType;

readonly class CallbackData
{
    public function __construct(
        public TransactionType $type,
        public string $playerExternalId,
        public string $providerTransactionId,
        public string $amount,
        public string $currency,
        public ?string $originalTransactionId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: TransactionType::from($data['type']),
            playerExternalId: $data['player_external_id'],
            providerTransactionId: $data['provider_transaction_id'],
            amount: (string) $data['amount'],
            currency: $data['currency'] ?? (string) config('services.novaspins.default_currency'),
            originalTransactionId: $data['original_transaction_id'] ?? null,
        );
    }
}
