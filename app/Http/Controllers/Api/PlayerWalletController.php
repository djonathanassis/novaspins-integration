<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Player;
use Illuminate\Http\JsonResponse;

class PlayerWalletController
{
    public function __invoke(int $playerId): JsonResponse
    {
        $player = Player::with('wallet')->findOrFail($playerId);

        if ($player->wallet === null) {
            return response()->json(['error' => 'wallet not found'], 404);
        }

        return response()->json([
            'player_id' => $player->id,
            'external_id' => $player->external_id,
            'balance' => $player->wallet->balance,
            'currency' => $player->wallet->currency,
        ]);
    }
}
