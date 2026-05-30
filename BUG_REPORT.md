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
hash: ab1b179

## Bug 5: comparação HMAC com `===` em vez de `hash_equals`

### Severidade
P1 alto
Justificativa: comparação de assinatura fora de constant-time aumenta superfície para timing attack.

### Causa raiz
`HmacValidator::isValid()` comparava assinatura esperada e recebida com `===`.

### Arquivo(s) e linha(s)
- `app/Services/HmacValidator.php:L21`
- `tests/Feature/Unit/HmacValidatorTest.php:L12-L27`

### Ticket(s)
#4521

### Impacto
Hardening de segurança incompleto na validação de callbacks financeiros.

### Como reproduzir
Pré-condição: código atual da validação HMAC.
1. Executar teste de segurança do validator.
2. Verificar presença de API de comparação constant-time.
   Esperado: uso de `hash_equals`.
   Obtido  : uso de `===`.

### Evidência antes do fix
- `FAILED ... To contain: hash_equals` em `test_hmac_validator_uses_hash_equals_for_signature_comparison`.

### Fix aplicado
Substituída comparação `===` por `hash_equals($expected, $signature)` no `HmacValidator`.

### Evidência depois do fix
- `HmacValidatorTest`: 2 passed.
- QA manual: `HTTP=401 | before=1036 | after=1036` para assinatura inválida.

### Testes
- `test_hmac_validator_uses_hash_equals_for_signature_comparison` — FAILED antes / PASSED depois
- `test_hmac_validator_accepts_valid_signature` — PASSED
- Suíte completa: 11 passed

### Trade-offs
- Alternativa descartada: manter `===` por simplicidade; descartada por risco de timing side-channel.

### Commit
fix(security): use hash_equals in hmac validation
hash: 0493465

## Bug 6: precisão monetária com float/double

### Severidade
P1 alto
Justificativa: uso de float/double em saldo e transações pode gerar divergência de centavos e inconsistência contábil.

### Causa raiz
Schema monetário em `float/double`, casts de model em `float` e cast explícito `(float)` após `bcadd/bcsub` no service.

### Arquivo(s) e linha(s)
- `database/migrations/2026_05_30_033736_change_wallets_and_transactions_to_decimal.php`
- `app/Models/Wallet.php:L23`
- `app/Models/Transaction.php:L32`
- `app/Services/WalletService.php:L25,L36`
- `app/Observers/WalletObserver.php:L31`
- `tests/Feature/Unit/MonetaryPrecisionConfigurationTest.php:L12-L29`

### Ticket(s)
#4507

### Impacto
Perda/mascaramento de centavos em operações financeiras e risco de reconciliação divergente em relatórios.

### Como reproduzir
Pré-condição: configuração original com tipos flutuantes.
1. Executar `MonetaryPrecisionConfigurationTest`.
2. Validar casts e presença de `(float) bc*` no service.
   Esperado: casts decimais e ausência de cast float após bcmath.
   Obtido  : casts `float` e cast explícito `(float) bc*`.

### Evidência antes do fix
- `FAILED ... -'decimal:2' +'float'`.
- `FAILED ... Not to contain: (float) bcsub`.

### Fix aplicado
- Criada **nova migration** para alterar `wallets.balance` e `transactions.amount` para `decimal(18,2)` com `->change()`.
- Models `Wallet` e `Transaction` convertidos para cast `decimal:2`.
- `WalletService` removeu cast para float após bcmath.
- `WalletObserver` passou a normalizar com `bcadd(..., '0', 2)`.

### Evidência depois do fix
- Schema MySQL: `wallets.balance = decimal(18,2)` e `transactions.amount = decimal(18,2)`.
- QA manual: `HTTP=200 | before=1036.00 | after=1036.10` para `win` de `0.10`.
- Banco: `SELECT balance` retornou `1036.10`.

### Testes
- `MonetaryPrecisionConfigurationTest` — FAILED antes / PASSED depois
- Suíte completa: 13 passed

### Trade-offs
- Alternativa descartada: manter schema antigo e corrigir só no PHP; descartada porque o risco de precisão permaneceria na persistência.

### Commit
fix(money): migrate monetary fields to decimal precision
hash: a224019

## Bug 7: rollback sem original_transaction_id retorna 500

### Severidade
P1 alto
Justificativa: payload inválido de rollback gerava erro interno 500 e acionava retries desnecessários.

### Causa raiz
`CallbackRequest` permitia `original_transaction_id` nullable, enquanto `handleRollback()` acessava a chave diretamente.

### Arquivo(s) e linha(s)
- `app/Http/Requests/CallbackRequest.php:L26-L36`
- `tests/Feature/ProviderCallbackTest.php:L92-L123`

### Ticket(s)
sem ticket

### Impacto
Erro interno em vez de validação previsível, com ruído operacional e risco de retry em cascata do provider.

### Como reproduzir
Pré-condição: callback endpoint ativo.
1. Enviar payload `rollback` sem `original_transaction_id`.
2. Verificar status e corpo.
   Esperado: `422` com erro de validação.
   Obtido  : `500` por acesso de chave inexistente.

### Evidência antes do fix
- `FAILED ... Expected response status code [422] but received 500`.
- Exceção: `Undefined array key "original_transaction_id"`.

### Fix aplicado
- Regra `required_if:type,rollback` em `CallbackRequest` para `original_transaction_id`.
- Regra monetária de `amount` endurecida para `required|numeric|decimal:0,2|gte:0.01`.
- `failedValidation()` customizado para resposta JSON `422` consistente em API callback.

### Evidência depois do fix
- Teste: `test_rollback_without_original_transaction_id_returns_422` PASSED.
- QA manual: `HTTP=422 | before=1036.10 | after=1036.10`.
- Resposta: `{"message":"The given data was invalid.","errors":{"original_transaction_id":[...]}}`.
- Sem mutação financeira: nenhuma transação criada para `provider_transaction_id=tx-rollback-without-original`.
- Amount imutável no erro: nenhuma transação rollback persistida com `amount=12.00`.

### Testes
- `test_rollback_without_original_transaction_id_returns_422` — FAILED antes / PASSED depois
- Suíte completa: 14 passed

### Trade-offs
- Alternativa descartada: tratar ausência apenas no controller; descartada por espalhar regra de validação de contrato HTTP fora da camada de request.

### Commit
fix(validation): require original transaction id on rollback
hash: 56d6203

## Bug 8: risco de saldo inconsistente sob concorrência sem lock transacional

### Severidade
P2 médio
Justificativa: não houve reprodução direta de saldo negativo no ambiente de teste, mas a ausência de lock pessimista transacional em mutações financeiras mantinha risco arquitetural sob carga concorrente.

### Causa raiz
Handlers financeiros (`bet`, `win`, `rollback`) executavam leitura/checagem idempotente e mutação de saldo sem `DB::transaction` + `lockForUpdate` no registro da wallet.

### Arquivo(s) e linha(s)
- `app/Http/Controllers/Api/ProviderCallbackController.php`
- `tests/Feature/Unit/CallbackTransactionSafetyTest.php`

### Ticket(s)
#4471 (status inicial PROVÁVEL)

### Impacto
Risco de inconsistência de saldo em cenários simultâneos (race conditions), especialmente com retries do provider em janelas curtas.

### Como reproduzir
Pré-condição: código sem lock transacional.
1. Executar `CallbackTransactionSafetyTest`.
2. Verificar presença de `DB::transaction` e `lockForUpdate` no controller.
   Esperado: presença explícita dos mecanismos.
   Obtido  : ausência dos dois.

### Evidência antes do fix
- `FAILED ... To contain: DB::transaction` em `CallbackTransactionSafetyTest`.

### Fix aplicado
- `handleBet`, `handleWin` e `handleRollback` encapsulados em `DB::transaction(..., 5)`.
- Lock pessimista aplicado com `Wallet::whereKey(...)->lockForUpdate()->firstOrFail()`.
- Checagens de idempotência movidas para dentro da transação.

### Evidência depois do fix
- `CallbackTransactionSafetyTest` PASSED.
- Bateria de QA de rotas: `wallet=200 tx=200 bet=200 win=200 rb=200 nosig=401 neg=422 replay=401 type=422 rb_no=422 bet_dup=200 cent=200`.

### Testes
- `CallbackTransactionSafetyTest` — FAILED antes / PASSED depois
- Suíte completa: 15 passed

### Trade-offs
- Limitação documentada: concorrência real não é totalmente validada em SQLite de testes; comportamento de lock deve ser validado também em banco equivalente ao de produção.

### Commit
fix(concurrency): add transactional wallet locking to callbacks
hash: [gerado]
