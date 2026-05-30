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
hash: f90a8d5

## Bug 2: bet com amount negativo credita carteira

### Severidade
P0 crítico
Justificativa: payload malicioso com valor negativo permitia aumentar saldo em vez de debitar.

### Causa raiz
`WalletService::debit`/`credit` não validavam positividade do `amount`, e o callback de `bet` encaminhava o valor sem guarda de domínio.

### Arquivo(s) e linha(s)
- `app/Services/WalletService.php:L13-L60`
- `app/Http/Controllers/Api/ProviderCallbackController.php:L41,L78`
- `tests/Feature/ProviderCallbackTest.php:L156-L175`

### Ticket(s)
#4515

### Impacto
Aposta com valor negativo produzia crédito de saldo (`1000 -> 1050` na evidência inicial), permitindo fraude financeira.

### Como reproduzir
Pré-condição: player com wallet ativa.
1. Enviar `POST /api/providers/novaspins/callback` assinado com `type=bet` e `amount=-50.00`.
2. Consultar saldo da carteira.
   Esperado: `422` e saldo inalterado.
   Obtido  : `200` e saldo aumentado.

### Evidência antes do fix
- `FAILED  ... Expected response status code [422] but received 200` em `test_bet_with_negative_amount_is_rejected_and_does_not_credit_wallet`.

### Fix aplicado
`WalletService` passou a operar com `string $amount`, validação central `ensureAmountIsPositive()` com `bccomp($amount, '0', 2) <= 0`, e chamadas do controller atualizadas para enviar string.

### Evidência depois do fix
- QA manual: `HTTP=422 | before=1047 | after=1047` com retorno `{ "error": "Amount must be greater than zero" }`.
- Teste do bug: PASSED.

### Testes
- `test_bet_with_negative_amount_is_rejected_and_does_not_credit_wallet` — FAILED antes / PASSED depois
- Suíte completa: 7 passed

### Trade-offs
- Alternativa descartada: validar só no FormRequest; descartada porque chamadas diretas ao service manteriam a vulnerabilidade.

### Commit
fix(wallet): reject non-positive callback amounts
hash: eb7e731

## Bug 3: bet duplicado sem idempotência

### Severidade
P0 crítico
Justificativa: retries legítimos do provider causavam débito duplicado ao player.

### Causa raiz
`handleBet()` não tinha guarda por `provider_transaction_id` + `type`, diferente do fluxo de `win`.

### Arquivo(s) e linha(s)
- `app/Http/Controllers/Api/ProviderCallbackController.php:L40-L53`
- `tests/Feature/ProviderCallbackTest.php:L194-L210`

### Ticket(s)
#4488

### Impacto
Mesma aposta era debitada duas vezes quando callback era reenviado.

### Como reproduzir
Pré-condição: wallet com saldo > valor da aposta.
1. Enviar o mesmo payload `bet` assinado duas vezes.
2. Consultar saldo e transações.
   Esperado: segunda chamada idempotente sem novo débito.
   Obtido  : segundo débito aplicado.

### Evidência antes do fix
- `FAILED ... Failed asserting that 440.0 matches expected 470.0` em `test_bet_callback_is_idempotent_on_same_provider_transaction_id`.

### Fix aplicado
Adicionada guarda idempotente em `handleBet()` para retornar a transação existente quando `provider_transaction_id` + `type=bet` já existem.

### Evidência depois do fix
- QA manual: `HTTP1=200 HTTP2=200 | before=1047 mid=1036 after=1036`.
- Ambas respostas retornaram o mesmo `transaction_id` (`14`).

### Testes
- `test_bet_callback_is_idempotent_on_same_provider_transaction_id` — FAILED antes / PASSED depois
- Suíte completa: 8 passed

### Trade-offs
- Alternativa descartada: depender apenas de índice único no banco; descartada para manter resposta idempotente de aplicação sem erro para retries já existentes.

### Commit
fix(callback): make bet callback idempotent
hash: 2e5d1c5

## Bug 4: rollback duplicado no mesmo original credita novamente

### Severidade
P0 crítico
Justificativa: múltiplos callbacks de rollback para o mesmo original causavam crédito duplicado.

### Causa raiz
`handleRollback()` não tinha guarda idempotente por `original_transaction_id` já revertido e sempre executava `reverse()`.

### Arquivo(s) e linha(s)
- `app/Http/Controllers/Api/ProviderCallbackController.php:L109-L120`
- `tests/Feature/ProviderCallbackTest.php:L96-L130`

### Ticket(s)
#4501

### Impacto
Estorno em duplicidade para uma única transação original, inflando saldo do player.

### Como reproduzir
Pré-condição: existir bet original válido.
1. Enviar rollback para o original.
2. Enviar segundo rollback com outro `provider_transaction_id` para o mesmo original.
   Esperado: segunda chamada idempotente sem nova mutação.
   Obtido  : novo crédito aplicado.

### Evidência antes do fix
- `FAILED ... Failed asserting that 600.0 matches expected 500.0` em `test_rollback_callback_is_idempotent_for_same_original_transaction`.

### Fix aplicado
Adicionada guarda por rollback existente (`type=rollback` + `original_transaction_id`) retornando resposta idempotente antes de chamar `reverse()`.

### Evidência depois do fix
- QA manual: `HTTP1=200 HTTP2=200 | before=1023 mid=1036 after=1036`.
- Ambas respostas retornaram o mesmo `transaction_id` (`16`).

### Testes
- `test_rollback_callback_is_idempotent_for_same_original_transaction` — FAILED antes / PASSED depois
- Suíte completa: 9 passed

### Trade-offs
- Alternativa descartada: bloquear por `provider_transaction_id` somente; descartada porque o incidente ocorre com IDs diferentes para o mesmo original.

### Commit
fix(callback): enforce rollback idempotency by original transaction
hash: [gerado]
