## Bug 1: replay sem assinatura altera saldo

### Severidade
P0 crítico
Justificativa: endpoint de mutação financeira aceitava payload sem autenticação HMAC, permitindo crédito/débito indevido.

### Causa raiz
O grupo de middleware `provider.callback.replay` não incluía `VerifyProviderSignature`, mas o endpoint reaproveitava o mesmo controller de mutação de saldo.

### Arquivo(s) e linha(s)
- `bootstrap/app.php:L21-L23`
- `tests/Feature/ProviderCallbackTest.php:L122-L153`

### Ticket(s)
#4521

### Impacto
Mutação de saldo sem assinatura no endpoint replay (`HTTP 200`), com criação de transação financeira e alteração real de carteira.

### Como reproduzir
Pré-condição: player `player-001` existe com wallet.
1. Enviar `POST /api/providers/novaspins/callback/replay` sem header `X-Signature` com payload válido de `win`.
2. Consultar `GET /api/players/1/wallet`.
   Esperado: `401/403` e saldo inalterado.
   Obtido  : `200` e saldo alterado.

### Evidência antes do fix
- `HTTP=200` no replay sem assinatura
- Saldo: `1050 -> 1057`
- Transação criada: `provider_transaction_id=tx-ev-1780110780107-4079-replay`

### Fix aplicado
Adicionado `VerifyProviderSignature::class` no grupo de middleware `provider.callback.replay`, mantendo logging e exigindo autenticação HMAC também no replay.

### Evidência depois do fix
- `test_replay_callback_without_signature_is_rejected_and_does_not_mutate_balance` — PASSED depois do fix.
- Antes do fix, o mesmo teste falhava com: `Expected response status code [401] but received 200`.

### Testes
- `test_replay_callback_without_signature_is_rejected_and_does_not_mutate_balance` — FAILED antes / PASSED depois
- Suíte completa: 6 passed

### Trade-offs
- Alternativa descartada: bloquear endpoint replay por completo; descartada para preservar capacidade operacional de replay com autenticação.

### Commit
fix(security): require signature on replay callback
hash: [gerado]
