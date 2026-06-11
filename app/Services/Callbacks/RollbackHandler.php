<?php

declare(strict_types=1);

namespace App\Services\Callbacks;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Callbacks\Contracts\CallbackHandler;
use App\Services\Callbacks\Data\CallbackData;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class RollbackHandler implements CallbackHandler
{
    public function __construct(
        private WalletService $walletService,
    ) {
    }

    public function handle(CallbackData $data): JsonResponse
    {
        try {
            $result = DB::transaction(function () use ($data): array {
                $original = Transaction::query()
                    ->where('provider_transaction_id', $data->originalTransactionId)
                    ->lockForUpdate()
                    ->first();

                if ($original === null) {
                    return ['not_found' => true];
                }

                $lockedWallet = Wallet::query()->whereKey($original->wallet_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingRollback = Transaction::query()
                    ->where('type', TransactionType::Rollback)
                    ->where('original_transaction_id', $original->id)
                    ->first();

                if ($existingRollback !== null) {
                    return [
                        'existing' => $existingRollback,
                        'wallet' => $lockedWallet,
                    ];
                }

                $this->walletService->reverse($lockedWallet, $original);

                $original->status = TransactionStatus::Reversed;
                $original->save();

                $rollback = Transaction::query()->create([
                    'wallet_id' => $lockedWallet->id,
                    'provider_transaction_id' => $data->providerTransactionId,
                    'type' => TransactionType::Rollback,
                    'amount' => $data->amount,
                    'status' => TransactionStatus::Completed,
                    'original_transaction_id' => $original->id,
                ]);

                return [
                    'rollback' => $rollback,
                    'wallet' => $lockedWallet,
                ];
            }, 5);
        } catch (Throwable $thr) {
            return response()->json(['error' => $thr->getMessage()], 422);
        }

        if (isset($result['not_found'])) {
            Log::warning('Rollback received for unknown transaction', [
                'provider_transaction_id' => $data->originalTransactionId,
            ]);

            return response()->json(['error' => 'original transaction not found'], 404);
        }

        $transaction = $result['existing'] ?? $result['rollback'];

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $result['wallet']->balance,
        ]);
    }
}
