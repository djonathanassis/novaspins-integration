# Bruno collection

## How to load it

Bruno's **Import** menu only accepts Postman / Insomnia / OpenAPI files — not Bruno's own folder format, so use **Open Collection** instead:

1. Open Bruno
2. Click **Open Collection** (not Import)
3. Select this `bruno/` folder
4. In the top-right environment picker, choose **Local**

## What's in it

- `Health Check` -> `GET /up`
- `Player Wallet` -> `GET /api/players/{{playerId}}/wallet`
- `Player Transactions` -> `GET /api/players/{{playerId}}/transactions`
- `Provider Callback/`
  - `Bet`, `Win`, `Rollback` — auto-sign the body with `HMAC-SHA256(body, hmacSecret)` in a pre-request script and set `X-Signature`
  - `Bad Signature` — sends a fake `X-Signature` so you can confirm the `provider.callback` middleware rejects it (expect 401)

## Environment variables (`environments/Local.bru`)

| var | default | notes |
|---|---|---|
| `baseUrl` | `http://localhost:8080` | matches `APP_URL` in `.env` |
| `hmacSecret` | `please-change-me-in-production` | must match `NOVASPINS_HMAC_SECRET` in `.env` |
| `playerId` | `1` | internal numeric id used in `/players/{id}/...` |
| `playerExternalId` | `player-001` | external id sent in callback bodies |

If you rotate `NOVASPINS_HMAC_SECRET` in `.env`, mirror it here or signatures will fail.
