<?php

declare(strict_types=1);

namespace App\Services\Callbacks;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Player;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Callbacks\Contracts\CallbackHandler;
use App\Services\Callbacks\Data\CallbackData;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class BaseHandler implements CallbackHandler
{
    public function __construct(
        protected readonly WalletService $walletService,
    ) {}

    abstract protected function type(): TransactionType;

    abstract protected function applyWalletOperation(Wallet $wallet, string $amount): Wallet;

    public function handle(CallbackData $data): JsonResponse
    {
        $player = Player::query()->where('external_id', $data->playerExternalId)->firstOrFail();
        $wallet = $player->wallet ?? $this->createWallet($player, $data->currency);

        try {
            $result = DB::transaction(function () use ($data, $wallet): array {
                $lockedWallet = Wallet::query()->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = Transaction::query()
                    ->where('provider_transaction_id', $data->providerTransactionId)
                    ->where('type', $this->type())
                    ->first();

                if ($existing !== null) {
                    return [
                        'existing' => $existing,
                        'wallet' => $lockedWallet,
                    ];
                }

                $this->applyWalletOperation($lockedWallet, $data->amount);

                $transaction = Transaction::query()->create([
                    'wallet_id' => $lockedWallet->id,
                    'provider_transaction_id' => $data->providerTransactionId,
                    'type' => $this->type(),
                    'amount' => $data->amount,
                    'status' => TransactionStatus::Completed,
                ]);

                return [
                    'transaction' => $transaction,
                    'wallet' => $lockedWallet,
                ];
            }, 5);
        } catch (Throwable $thr) {
            return response()->json(['error' => $thr->getMessage()], 422);
        }

        $transaction = $result['existing'] ?? $result['transaction'];

        return response()->json([
            'status' => 'ok',
            'transaction_id' => $transaction->id,
            'balance' => $result['wallet']->balance,
        ]);
    }

    private function createWallet(Player $player, string $currency): Wallet
    {
        return Wallet::query()->create([
            'player_id' => $player->id,
            'balance' => 0,
            'currency' => $currency,
        ]);
    }
}
