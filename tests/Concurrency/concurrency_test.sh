#!/usr/bin/env bash
#=============================================================================
# Teste de concorrência para callbacks NovaSpins
# Simula múltiplos requests paralelos com o mesmo provider_transaction_id
# para validar idempotência sob carga concorrente.
#
# Uso:
#   bash tests/Concurrency/concurrency_test.sh
#=============================================================================

set -euo pipefail

BASE_URL="http://localhost:8080/api/providers/novaspins/callback"
WALLET_URL="http://localhost:8080/api/players/1/wallet"
CONCURRENCY=10
TS="$(date +%s)-${RANDOM}"

# Lê secret do .env
NOVASPINS_HMAC_SECRET=$(grep -E '^NOVASPINS_HMAC_SECRET=' .env | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'")
if [ -z "$NOVASPINS_HMAC_SECRET" ]; then
  echo "❌ NOVASPINS_HMAC_SECRET não encontrado no .env"
  exit 1
fi

# Função de assinatura HMAC
sign() {
  printf '%s' "$1" | openssl dgst -sha256 -hmac "$NOVASPINS_HMAC_SECRET" | awk '{print $2}'
}

echo "========================================================"
echo "🚀 TESTE DE CONCORRÊNCIA — $(date)"
echo "========================================================"
echo "Concorrência: ${CONCURRENCY}x requests paralelos"
echo ""

# ─── Teste 1: Bet concorrente (mesmo provider_transaction_id) ───
echo "━━━ Teste 1: ${CONCURRENCY}x bet paralelos (mesmo provider_transaction_id) ━━━"

BODY_BET=$(printf '{"type":"bet","player_external_id":"player-001","provider_transaction_id":"concurrent-bet-%s","amount":10.00,"currency":"BRL"}' "$TS")
SIG_BET=$(sign "$BODY_BET")

BEFORE=$(curl -s "$WALLET_URL" | jq -r '.balance')
echo "Saldo antes: $BEFORE"

RESULTS_FILE=$(mktemp)
for i in $(seq 1 $CONCURRENCY); do
  (
    curl -s -o /dev/null -w '%{http_code}' \
      -X POST "$BASE_URL" \
      -H 'Content-Type: application/json' \
      -H "X-Signature: $SIG_BET" \
      --data "$BODY_BET" 2>/dev/null
    echo
  ) >> "$RESULTS_FILE" &
done

wait

AFTER=$(curl -s "$WALLET_URL" | jq -r '.balance')
HTTP_200=$(grep -c '^200' "$RESULTS_FILE" 2>/dev/null || echo 0)

echo "Respostas HTTP 200: $HTTP_200 de $CONCURRENCY"
echo "Saldo depois: $AFTER"

# Validar: saldo deve ter diminuído exatamente 10.00
EXPECTED=$(echo "$BEFORE - 10.00" | bc)
DIFF=$(echo "$AFTER - $BEFORE" | bc)
if [ "$(echo "$DIFF == -10.00" | bc)" -eq 1 ] 2>/dev/null; then
  echo "✅ Teste 1 PASS — Saldo debitado exatamente 1 vez (10.00)"
else
  echo "❌ Teste 1 FAIL — Esperado $EXPECTED, obtido $AFTER (diff=$DIFF)"
fi

echo ""

# ─── Teste 2: Rollback concorrente (mesmo original) ───
echo "━━━ Teste 2: ${CONCURRENCY}x rollback paralelos (mesmo original) ━━━"

BODY_ORIG=$(printf '{"type":"bet","player_external_id":"player-001","provider_transaction_id":"concurrent-orig-%s","amount":15.00,"currency":"BRL"}' "$TS")
SIG_ORIG=$(sign "$BODY_ORIG")
curl -s -X POST "$BASE_URL" \
  -H 'Content-Type: application/json' \
  -H "X-Signature: $SIG_ORIG" \
  --data "$BODY_ORIG" > /dev/null 2>&1

BEFORE2=$(curl -s "$WALLET_URL" | jq -r '.balance')

BODY_RB=$(printf '{"type":"rollback","player_external_id":"player-001","provider_transaction_id":"concurrent-rb-%s","original_transaction_id":"concurrent-orig-%s","amount":15.00,"currency":"BRL"}' "$TS" "$TS")
SIG_RB=$(sign "$BODY_RB")

RESULTS_FILE2=$(mktemp)
for i in $(seq 1 $CONCURRENCY); do
  (
    curl -s -o /dev/null -w '%{http_code}' \
      -X POST "$BASE_URL" \
      -H 'Content-Type: application/json' \
      -H "X-Signature: $SIG_RB" \
      --data "$BODY_RB" 2>/dev/null
    echo
  ) >> "$RESULTS_FILE2" &
done
wait

AFTER2=$(curl -s "$WALLET_URL" | jq -r '.balance')
RB_200=$(grep -c '^200' "$RESULTS_FILE2" 2>/dev/null || echo 0)

echo "Respostas HTTP 200 no rollback: $RB_200 de $CONCURRENCY"
echo "Saldo antes rollback: $BEFORE2"
echo "Saldo depois rollback: $AFTER2"

DIFF2=$(echo "$AFTER2 - $BEFORE2" | bc)
if [ "$(echo "$DIFF2 == 15.00" | bc)" -eq 1 ] 2>/dev/null; then
  echo "✅ Teste 2 PASS — Rollback aplicado exatamente 1 vez (15.00)"
else
  echo "❌ Teste 2 FAIL — Variação esperada 15.00, obtida $DIFF2"
fi

echo ""

# ─── Resumo ───
echo "========================================================"
echo "📋 RESUMO"
echo "========================================================"
echo "Requests paralelos: $CONCURRENCY"
echo ""
T1_PASS=$(echo "$DIFF == -10.00" | bc)
T2_PASS=$(echo "$DIFF2 == 15.00" | bc)
echo "Teste 1 (bet concorrente):  $([ "$T1_PASS" -eq 1 ] && echo '✅ PASS' || echo '❌ FAIL')"
echo "Teste 2 (rollback conc.):   $([ "$T2_PASS" -eq 1 ] && echo '✅ PASS' || echo '❌ FAIL')"
echo "========================================================"

rm -f "$RESULTS_FILE" "$RESULTS_FILE2"
