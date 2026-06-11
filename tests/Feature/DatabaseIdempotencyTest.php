<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = "test-secret";

    protected function setUp(): void
    {
        parent::setUp();
        config(["services.novaspins.hmac_secret" => self::SECRET]);
    }

    public function test_transactions_table_has_idempotency_unique_indexes(): void
    {
        $indexes = Schema::getIndexes("transactions");
        $indexNames = array_column($indexes, "name");

        $this->assertContains(
            "transactions_provider_type_unique",
            $indexNames,
            "Missing unique index on provider_transaction_id + type"
        );

        $this->assertContains(
            "transactions_original_type_unique",
            $indexNames,
            "Missing unique index on original_transaction_id + type"
        );
    }

    public function test_duplicate_provider_transaction_id_and_type_is_rejected_by_database(): void
    {
        $player = Player::create(["external_id" => "dup-test-1", "name" => "Dup Test"]);
        $wallet = Wallet::create(["player_id" => $player->id, "balance" => 1000, "currency" => "BRL"]);

        Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "dup-tx-1",
            "type" => Transaction::TYPE_BET,
            "amount" => "50.00",
            "status" => Transaction::STATUS_COMPLETED,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "dup-tx-1",
            "type" => Transaction::TYPE_BET,
            "amount" => "25.00",
            "status" => Transaction::STATUS_COMPLETED,
        ]);
    }

    public function test_duplicate_rollback_for_same_original_is_rejected_by_database(): void
    {
        $player = Player::create(["external_id" => "dup-test-2", "name" => "Dup Test 2"]);
        $wallet = Wallet::create(["player_id" => $player->id, "balance" => 1000, "currency" => "BRL"]);

        $original = Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "orig-rollback-dup",
            "type" => Transaction::TYPE_BET,
            "amount" => "50.00",
            "status" => Transaction::STATUS_COMPLETED,
        ]);

        Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "rb-dup-1",
            "type" => Transaction::TYPE_ROLLBACK,
            "amount" => "50.00",
            "status" => Transaction::STATUS_COMPLETED,
            "original_transaction_id" => $original->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "rb-dup-2",
            "type" => Transaction::TYPE_ROLLBACK,
            "amount" => "50.00",
            "status" => Transaction::STATUS_COMPLETED,
            "original_transaction_id" => $original->id,
        ]);
    }

    public function test_multiple_bets_with_different_provider_transaction_ids_are_allowed(): void
    {
        $player = Player::create(["external_id" => "multi-bet-test", "name" => "Multi Bet"]);
        $wallet = Wallet::create(["player_id" => $player->id, "balance" => 1000, "currency" => "BRL"]);

        Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "bet-a",
            "type" => Transaction::TYPE_BET,
            "amount" => "10.00",
            "status" => Transaction::STATUS_COMPLETED,
        ]);

        Transaction::create([
            "wallet_id" => $wallet->id,
            "provider_transaction_id" => "bet-b",
            "type" => Transaction::TYPE_BET,
            "amount" => "20.00",
            "status" => Transaction::STATUS_COMPLETED,
        ]);

        $this->assertEquals(2, Transaction::where("type", Transaction::TYPE_BET)->count());
    }

    public function test_idempotency_tests_still_pass_after_unique_indexes(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $betPayload = $this->payload([
            "type" => "bet",
            "player_external_id" => $player->external_id,
            "provider_transaction_id" => "tx-idem-bet",
            "amount" => 30.00,
        ]);

        $this->postCallback($betPayload)->assertOk();
        $this->postCallback($betPayload)->assertOk();

        $this->assertEquals(470.00, $player->wallet->refresh()->balance);
        $this->assertDatabaseCount("transactions", 1);
    }

    private function makePlayerWithBalance(float $balance): Player
    {
        $player = Player::create(["external_id" => "idem-" . uniqid(), "name" => "Idem Test"]);
        Wallet::create(["player_id" => $player->id, "balance" => $balance, "currency" => "BRL"]);
        return $player->load("wallet");
    }

    private function payload(array $overrides): array
    {
        $body = json_encode($overrides, JSON_THROW_ON_ERROR);
        $signature = hash_hmac("sha256", $body, self::SECRET);
        return ["body" => $body, "signature" => $signature];
    }

    private function postCallback(array $payload)
    {
        return $this->call(
            "POST",
            "/api/providers/novaspins/callback",
            [],
            [],
            [],
            [
                "HTTP_X-Signature" => $payload["signature"],
                "CONTENT_TYPE" => "application/json",
            ],
            $payload["body"],
        );
    }
}
