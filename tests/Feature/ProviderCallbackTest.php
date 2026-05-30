<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.novaspins.hmac_secret' => self::SECRET]);
    }

    public function test_bet_callback_debits_wallet(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $payload = $this->payload([
            'type' => 'bet',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-bet-1',
            'amount' => 50.00,
        ]);

        $response = $this->postCallback($payload);

        $response->assertOk()->assertJsonPath('status', 'ok');

        $this->assertEquals(450.00, $player->wallet->refresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'provider_transaction_id' => 'tx-bet-1',
            'type' => 'bet',
        ]);
    }

    public function test_win_callback_credits_wallet(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $payload = $this->payload([
            'type' => 'win',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-win-1',
            'amount' => 75.00,
        ]);

        $response = $this->postCallback($payload);

        $response->assertOk()->assertJsonPath('status', 'ok');

        $this->assertEquals(575.00, $player->wallet->refresh()->balance);
    }

    public function test_rollback_callback_reverses_a_bet(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $bet = Transaction::create([
            'wallet_id' => $player->wallet->id,
            'provider_transaction_id' => 'tx-bet-rollback',
            'type' => Transaction::TYPE_BET,
            'amount' => 100.00,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
        $player->wallet->decrement('balance', 100.00);

        $payload = $this->payload([
            'type' => 'rollback',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-rollback-1',
            'original_transaction_id' => $bet->provider_transaction_id,
            'amount' => 100.00,
        ]);

        $response = $this->postCallback($payload);

        $response->assertOk()->assertJsonPath('status', 'ok');

        $this->assertEquals(500.00, $player->wallet->refresh()->balance);
        $this->assertEquals(Transaction::STATUS_REVERSED, $bet->refresh()->status);
    }

    public function test_callback_with_invalid_signature_is_rejected(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $body = json_encode([
            'type' => 'bet',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-unsigned',
            'amount' => 10,
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/providers/novaspins/callback',
            [],
            [],
            [],
            [
                'HTTP_X-Signature' => 'definitely-wrong',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );

        $response->assertStatus(401);
    }

    public function test_replay_callback_without_signature_is_rejected_and_does_not_mutate_balance(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $body = json_encode([
            'type' => 'win',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-replay-unsigned',
            'amount' => 10.00,
            'currency' => 'BRL',
        ], JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/api/providers/novaspins/callback/replay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );

        $response->assertStatus(401);

        $this->assertEquals(500.00, $player->wallet->refresh()->balance);
        $this->assertDatabaseMissing('transactions', [
            'provider_transaction_id' => 'tx-replay-unsigned',
        ]);
    }

    public function test_bet_with_negative_amount_is_rejected_and_does_not_credit_wallet(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $payload = $this->payload([
            'type' => 'bet',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-bet-negative',
            'amount' => -50.00,
            'currency' => 'BRL',
        ]);

        $response = $this->postCallback($payload);

        $response->assertStatus(422);

        $this->assertEquals(500.00, $player->wallet->refresh()->balance);
        $this->assertDatabaseMissing('transactions', [
            'provider_transaction_id' => 'tx-bet-negative',
        ]);
    }

    public function test_win_callback_is_idempotent_on_same_provider_transaction_id(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $payload = $this->payload([
            'type' => 'win',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-win-dupe',
            'amount' => 30.00,
        ]);

        $this->postCallback($payload)->assertOk();
        $this->postCallback($payload)->assertOk();

        $this->assertEquals(530.00, $player->wallet->refresh()->balance);
    }

    public function test_bet_callback_is_idempotent_on_same_provider_transaction_id(): void
    {
        $player = $this->makePlayerWithBalance(500.00);

        $payload = $this->payload([
            'type' => 'bet',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-bet-dupe',
            'amount' => 30.00,
            'currency' => 'BRL',
        ]);

        $this->postCallback($payload)->assertOk();
        $this->postCallback($payload)->assertOk();

        $this->assertEquals(470.00, $player->wallet->refresh()->balance);
        $this->assertDatabaseCount('transactions', 1);
    }

    private function makePlayerWithBalance(float $balance): Player
    {
        $player = Player::create([
            'external_id' => 'ext-' . uniqid(),
            'name' => 'Test Player',
        ]);

        Wallet::create([
            'player_id' => $player->id,
            'balance' => $balance,
            'currency' => 'BRL',
        ]);

        return $player->load('wallet');
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{body: string, signature: string}
     */
    private function payload(array $overrides): array
    {
        $body = json_encode($overrides, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, self::SECRET);

        return ['body' => $body, 'signature' => $signature];
    }

    /**
     * @param array{body: string, signature: string} $payload
     */
    private function postCallback(array $payload)
    {
        return $this->call(
            'POST',
            '/api/providers/novaspins/callback',
            [],
            [],
            [],
            [
                'HTTP_X-Signature' => $payload['signature'],
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload['body'],
        );
    }
}
