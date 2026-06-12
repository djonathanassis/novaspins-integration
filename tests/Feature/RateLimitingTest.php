<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.novaspins.hmac_secret' => self::SECRET]);
    }

    // Verifica se a rota /callback usa o grupo provider.callback (que contem throttle)
    public function test_callback_route_uses_provider_callback_group(): void
    {
        $request = request()->create('/api/providers/novaspins/callback', 'POST');
        $route = Route::getRoutes()->match($request);

        $this->assertContains(
            'provider.callback',
            $route->gatherMiddleware(),
            'Rota /callback deve usar o grupo provider.callback'
        );
    }

    // Verifica se a rota /callback/replay usa o grupo provider.callback.replay
    public function test_replay_route_uses_provider_callback_replay_group(): void
    {
        $request = request()->create('/api/providers/novaspins/callback/replay', 'POST');
        $route = Route::getRoutes()->match($request);

        $this->assertContains(
            'provider.callback.replay',
            $route->gatherMiddleware(),
            'Rota /callback/replay deve usar o grupo provider.callback.replay'
        );
    }

    // Rate limiter deve bloquear apos exceder o limite configurado (retorna HTTP 429)
    public function test_callback_exceeding_rate_limit_returns_429(): void
    {
        config(['services.novaspins.callback_rate_limit' => 1]);

        $player = $this->makePlayerWithBalance(500.00);
        $payload = $this->signPayload([
            'type' => 'bet',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-rate-limit-1',
            'amount' => 25.00,
            'currency' => 'BRL',
        ]);

        $this->call(
            'POST', '/api/providers/novaspins/callback',
            [], [], [],
            ['HTTP_X-Signature' => $payload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $payload['body'],
        )->assertOk();

        $this->call(
            'POST', '/api/providers/novaspins/callback',
            [], [], [],
            ['HTTP_X-Signature' => $payload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $payload['body'],
        )->assertStatus(429);
    }

    // Rate limiter do replay deve ter limite proprio (1/min) e retornar 429 ao exceder
    public function test_replay_rate_limiter_is_independent(): void
    {
        config(['services.novaspins.replay_rate_limit' => 1]);

        $player = $this->makePlayerWithBalance(500.00);
        $payload = $this->signPayload([
            'type' => 'win',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-replay-rate-1',
            'amount' => 10.00,
            'currency' => 'BRL',
        ]);

        $this->call(
            'POST', '/api/providers/novaspins/callback/replay',
            [], [], [],
            ['HTTP_X-Signature' => $payload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $payload['body'],
        )->assertOk();

        $this->call(
            'POST', '/api/providers/novaspins/callback/replay',
            [], [], [],
            ['HTTP_X-Signature' => $payload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $payload['body'],
        )->assertStatus(429);
    }

    // Rate limiters do callback e do replay devem ser buckets independentes
    public function test_callback_and_replay_rate_limiters_are_independent(): void
    {
        config(['services.novaspins.callback_rate_limit' => 1]);
        config(['services.novaspins.replay_rate_limit' => 1]);

        $player = $this->makePlayerWithBalance(500.00);

        $betPayload = $this->signPayload([
            'type' => 'bet',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-indep-1',
            'amount' => 10.00,
            'currency' => 'BRL',
        ]);

        $winPayload = $this->signPayload([
            'type' => 'win',
            'player_external_id' => $player->external_id,
            'provider_transaction_id' => 'tx-indep-2',
            'amount' => 10.00,
            'currency' => 'BRL',
        ]);

        // Callback excede o limite
        $this->call('POST', '/api/providers/novaspins/callback', [], [], [],
            ['HTTP_X-Signature' => $betPayload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $betPayload['body'],
        )->assertOk();

        // Callback deve estar bloqueado
        $this->call('POST', '/api/providers/novaspins/callback', [], [], [],
            ['HTTP_X-Signature' => $betPayload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $betPayload['body'],
        )->assertStatus(429);

        // Replay ainda deve funcionar (independente)
        $this->call('POST', '/api/providers/novaspins/callback/replay', [], [], [],
            ['HTTP_X-Signature' => $winPayload['signature'], 'CONTENT_TYPE' => 'application/json'],
            $winPayload['body'],
        )->assertOk();
    }

    private function makePlayerWithBalance(float $balance): Player
    {
        $player = Player::create([
            'external_id' => 'rate-'.uniqid(),
            'name' => 'Rate Limit Test',
        ]);

        Wallet::create([
            'player_id' => $player->id,
            'balance' => $balance,
            'currency' => 'BRL',
        ]);

        return $player->load('wallet');
    }

    private function signPayload(array $overrides): array
    {
        $body = json_encode($overrides, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, self::SECRET);

        return ['body' => $body, 'signature' => $signature];
    }
}
