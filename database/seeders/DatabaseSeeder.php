<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Player;
use App\Enums\TransactionType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $player = Player::firstOrCreate(
            ['external_id' => 'player-001'],
            ['name' => 'Joao da Silva']
        );

        $wallet = Wallet::firstOrCreate(
            ['player_id' => $player->id],
            ['balance' => 1000.00, 'currency' => 'BRL']
        );

        $history = [
            ['type' => TransactionType::Bet, 'amount' => 25.00],
            ['type' => TransactionType::Win, 'amount' => 60.00],
            ['type' => TransactionType::Bet, 'amount' => 10.50],
            ['type' => TransactionType::Bet, 'amount' => 5.25],
            ['type' => TransactionType::Win, 'amount' => 12.75],
        ];

        foreach ($history as $i => $entry) {
            Transaction::firstOrCreate(
                ['provider_transaction_id' => 'seed-tx-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'wallet_id' => $wallet->id,
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'status' => TransactionStatus::Completed,
                ]
            );
        }
    }
}
