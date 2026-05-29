<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerTransactionController
{
    public function __invoke(int $playerId): JsonResponse
    {
        $player = Player::with('wallet')->findOrFail($playerId);

        if ($player->wallet === null) {
            return response()->json(['data' => []]);
        }

        $transactions = $player->wallet->transactions()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'provider_transaction_id', 'type', 'amount', 'status', 'original_transaction_id', 'created_at']);

        return response()->json(['data' => $transactions]);
    }
}
