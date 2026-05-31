<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CallbackRequest;
use App\Models\Player;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProviderCallbackController
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function __invoke(Request $request, CallbackRequest $callback): JsonResponse
    {
        $data = $callback->validated();

        return match ($data['type']) {
            'bet' => $this->handleBet($data),
            'win' => $this->handleWin($data),
            'rollback' => $this->handleRollback($data),
        };
    }

    private function handleBet(array $data): JsonResponse
    {
        $player = Player::where('external_id', $data['player_external_id'])->firstOrFail();
        $wallet = $player->wallet ?? $this->createWallet($player, $data['currency'] ?? null);

        try {
            $operation = DB::transaction(function () use ($data, $wallet): array {
                $lockedWallet = Wallet::whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = Transaction::where('provider_transaction_id', $data['provider_transaction_id'])
                    ->where('type', Transaction::TYPE_BET)
                    ->first();

                if ($existing !== null) {
                    return [
                        'existing' => $existing,
                        'wallet' => $lockedWallet,
                    ];
                }

                $this->walletService->debit($lockedWallet, (string) $data['amount']);

                $transaction = Transaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'provider_transaction_id' => $data['provider_transaction_id'],
                    'type' => Transaction::TYPE_BET,
                    'amount' => (string) $data['amount'],
                    'status' => Transaction::STATUS_COMPLETED,
                ]);

                return [
                    'transaction' => $transaction,
                    'wallet' => $lockedWallet,
                ];
            }, 5);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $transaction = $operation['existing'] ?? $operation['transaction'];

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $operation['wallet']->balance,
        ]);
    }

    private function handleWin(array $data): JsonResponse
    {
        $player = Player::where('external_id', $data['player_external_id'])->firstOrFail();
        $wallet = $player->wallet ?? $this->createWallet($player, $data['currency'] ?? null);

        try {
            $operation = DB::transaction(function () use ($data, $wallet): array {
                $lockedWallet = Wallet::whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = Transaction::where('provider_transaction_id', $data['provider_transaction_id'])
                    ->where('type', Transaction::TYPE_WIN)
                    ->first();

                if ($existing !== null) {
                    return [
                        'existing' => $existing,
                        'wallet' => $lockedWallet,
                    ];
                }

                $this->walletService->credit($lockedWallet, (string) $data['amount']);

                $transaction = Transaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'provider_transaction_id' => $data['provider_transaction_id'],
                    'type' => Transaction::TYPE_WIN,
                    'amount' => (string) $data['amount'],
                    'status' => Transaction::STATUS_COMPLETED,
                ]);

                return [
                    'transaction' => $transaction,
                    'wallet' => $lockedWallet,
                ];
            }, 5);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $transaction = $operation['existing'] ?? $operation['transaction'];

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $operation['wallet']->balance,
        ]);
    }

    private function handleRollback(array $data): JsonResponse
    {
        try {
            $operation = DB::transaction(function () use ($data): array {
                $original = Transaction::where('provider_transaction_id', $data['original_transaction_id'])
                    ->lockForUpdate()
                    ->first();

                if ($original === null) {
                    return ['not_found' => true];
                }

                $lockedWallet = Wallet::whereKey($original->wallet_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingRollback = Transaction::where('type', Transaction::TYPE_ROLLBACK)
                    ->where('original_transaction_id', $original->id)
                    ->first();

                if ($existingRollback !== null) {
                    return [
                        'existing' => $existingRollback,
                        'wallet' => $lockedWallet,
                    ];
                }

                $this->walletService->reverse($lockedWallet, $original);

                $original->status = Transaction::STATUS_REVERSED;
                $original->save();

                $rollback = Transaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'provider_transaction_id' => $data['provider_transaction_id'],
                    'type' => Transaction::TYPE_ROLLBACK,
                    'amount' => (string) $data['amount'],
                    'status' => Transaction::STATUS_COMPLETED,
                    'original_transaction_id' => $original->id,
                ]);

                return [
                    'rollback' => $rollback,
                    'wallet' => $lockedWallet,
                ];
            }, 5);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if (isset($operation['not_found'])) {
            Log::warning('Rollback received for unknown transaction', [
                'provider_transaction_id' => $data['original_transaction_id'],
            ]);

            return response()->json(['error' => 'original transaction not found'], 404);
        }

        $transaction = $operation['existing'] ?? $operation['rollback'];

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $operation['wallet']->balance,
        ]);
    }

    private function createWallet(Player $player, ?string $currency): Wallet
    {
        return Wallet::create([
            'player_id' => $player->id,
            'balance' => 0,
            'currency' => $currency ?? (string) config('services.novaspins.default_currency'),
        ]);
    }
}
