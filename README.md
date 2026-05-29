# NovaSpins Integration

Laravel 11 service that integrates the **NovaSpins** game provider with our wallet platform. Receives signed webhooks (`bet`, `win`, `rollback`) and reflects them on each player's balance.

---

## Stack

- PHP 8.3
- Laravel 11
- MySQL 8 (Docker)
- PHPUnit 11
- PHP-CS-Fixer

---

## Setup

```bash
cd novaspins-integration

cp .env.example .env

docker compose up -d --build

# wait for MySQL to be healthy, then:
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

The HTTP service will be on `http://localhost:8080`.

The seeder creates one player:

| Field        | Value          |
|--------------|----------------|
| `player.id`  | `1`            |
| `external_id`| `player-001`   |
| balance      | `1000.00 BRL`  |
| transactions | 5 historical   |

---

## Endpoints

| Method | Path                                          | Description                          |
|--------|-----------------------------------------------|--------------------------------------|
| POST   | `/api/providers/novaspins/callback`           | Receives provider webhooks           |
| GET    | `/api/players/{id}/wallet`                    | Returns current balance              |
| GET    | `/api/players/{id}/transactions`              | Lists recent transactions            |
| GET    | `/up`                                         | Health check                         |

Webhook callbacks must include a header `X-Signature: <hex>` containing the HMAC-SHA256 of the raw request body, using the shared secret defined in `NOVASPINS_HMAC_SECRET`.

---

## Bruno collection

A ready-to-run [Bruno](https://www.usebruno.com/) collection lives in `bruno/`.
Open the `bruno/` folder in Bruno, select the **Local** environment, and the
callback requests will sign their bodies automatically using the
`hmacSecret` env var.

The collection covers:

- `Provider Callback / Bet`, `Win`, `Rollback` — signed webhook requests.
- `Provider Callback / Bad Signature` — sends a bogus `X-Signature` to confirm
  the middleware rejects it.
- `Player Wallet`, `Player Transactions`, `Health Check` — read-side endpoints.

If you change `NOVASPINS_HMAC_SECRET` in `.env`, update `hmacSecret` in
`bruno/environments/Local.bru` to match.

---

## Triggering callbacks with `curl`

Set your secret first so the snippet works as-is:

```bash
export NOVASPINS_HMAC_SECRET="please-change-me-in-production"
```

### Generating an HMAC signature for a payload

```bash
sign() {
  printf '%s' "$1" | openssl dgst -sha256 -hmac "$NOVASPINS_HMAC_SECRET" | awk '{print $2}'
}
```

### `bet` — debits the player's wallet

```bash
BODY='{"type":"bet","player_external_id":"player-001","provider_transaction_id":"tx-bet-001","amount":25.00,"currency":"BRL"}'
SIG=$(sign "$BODY")

curl -s -X POST http://localhost:8080/api/providers/novaspins/callback \
  -H "Content-Type: application/json" \
  -H "X-Signature: $SIG" \
  --data "$BODY"
```

### `win` — credits the player's wallet

```bash
BODY='{"type":"win","player_external_id":"player-001","provider_transaction_id":"tx-win-001","amount":60.00,"currency":"BRL"}'
SIG=$(sign "$BODY")

curl -s -X POST http://localhost:8080/api/providers/novaspins/callback \
  -H "Content-Type: application/json" \
  -H "X-Signature: $SIG" \
  --data "$BODY"
```

### `rollback` — reverses a prior transaction

```bash
BODY='{"type":"rollback","player_external_id":"player-001","provider_transaction_id":"tx-rb-001","original_transaction_id":"tx-bet-001","amount":25.00,"currency":"BRL"}'
SIG=$(sign "$BODY")

curl -s -X POST http://localhost:8080/api/providers/novaspins/callback \
  -H "Content-Type: application/json" \
  -H "X-Signature: $SIG" \
  --data "$BODY"
```

### Reading state

```bash
curl -s http://localhost:8080/api/players/1/wallet
curl -s http://localhost:8080/api/players/1/transactions
```

---

## Running the test suite

```bash
docker compose exec app vendor/bin/phpunit
```

Style checks (also enforced in CI):

```bash
docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## Layout

```
app/
  Http/
    Controllers/Api/
    Middleware/
    Requests/
  Models/
  Services/
database/
  migrations/
  seeders/
docker/
routes/api.php
tests/Feature/
```
