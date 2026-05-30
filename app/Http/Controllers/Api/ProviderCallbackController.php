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

        $existing = Transaction::where('provider_transaction_id', $data['provider_transaction_id'])
            ->where('type', Transaction::TYPE_BET)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'status' => 'ok',
                'transaction_id' => $existing->id,
                'balance' => $wallet->balance,
            ]);
        }

        try {
            $this->walletService->debit($wallet, (string) $data['amount']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $transaction = Transaction::create([
            'wallet_id' => $wallet->id,
            'provider_transaction_id' => $data['provider_transaction_id'],
            'type' => Transaction::TYPE_BET,
            'amount' => (float) $data['amount'],
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $wallet->balance,
        ]);
    }

    private function handleWin(array $data): JsonResponse
    {
        $player = Player::where('external_id', $data['player_external_id'])->firstOrFail();
        $wallet = $player->wallet ?? $this->createWallet($player, $data['currency'] ?? null);

        $existing = Transaction::where('provider_transaction_id', $data['provider_transaction_id'])
            ->where('type', Transaction::TYPE_WIN)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'status' => 'ok',
                'transaction_id' => $existing->id,
                'balance' => $wallet->balance,
            ]);
        }

        $this->walletService->credit($wallet, (string) $data['amount']);

        $transaction = Transaction::create([
            'wallet_id' => $wallet->id,
            'provider_transaction_id' => $data['provider_transaction_id'],
            'type' => Transaction::TYPE_WIN,
            'amount' => (float) $data['amount'],
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $wallet->balance,
        ]);
    }

    private function handleRollback(array $data): JsonResponse
    {
        $original = Transaction::where('provider_transaction_id', $data['original_transaction_id'])->first();

        if ($original === null) {
            Log::warning('Rollback received for unknown transaction', [
                'provider_transaction_id' => $data['original_transaction_id'],
            ]);

            return response()->json(['error' => 'original transaction not found'], 404);
        }

        $wallet = Wallet::findOrFail($original->wallet_id);

        try {
            $this->walletService->reverse($wallet, $original);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $original->status = Transaction::STATUS_REVERSED;
        $original->save();

        $rollback = Transaction::create([
            'wallet_id' => $wallet->id,
            'provider_transaction_id' => $data['provider_transaction_id'],
            'type' => Transaction::TYPE_ROLLBACK,
            'amount' => (float) $data['amount'],
            'status' => Transaction::STATUS_COMPLETED,
            'original_transaction_id' => $original->id,
        ]);

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $rollback->id,
            'balance' => $wallet->balance,
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
