# SPEC-009 — Caixa do evento ECC (conta corrente + relatório)

**Status:** approved  
**Data:** 2026-09-17  
**Depende de:** SPEC-008  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

Eventos do ECC precisam de lista de compras e **conta corrente** (caixa): doações em dinheiro de casais/equipes entram; compra de item gera saída. Relatório resume saldo, extrato e itens.

## Objetivo

1. Todo evento com `origem=ecc` tem **lista de compras** (independente do tipo).
2. Conta corrente por evento: lançamentos `entrada` | `saida`.
3. Doação em dinheiro: casal, equipe **ou** nome livre (`doador_nome` — padre, paróquia, voluntário…) (+ valor, descrição opcional).
4. Comprar item: grava `valor_gasto`, status `comprado` e **saída** no caixa (vinculada ao item).
5. Doar item físico: não mexe no caixa.
6. Desfazer compra: remove a saída vinculada.
7. Relatório: saldo, totais, extrato, resumo de itens; **impressão** no detalhe com layout elaborado e opções separadas (completo, participantes, compras, extrato).
8. Exclusão do evento no detalhe com confirmação digitando `deletar`.

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| LancamentoCaixa | `ecc_evento_lancamentos` | `tipo` entrada\|saida, `valor`, `descricao?`, `casal_id?`, `ecc_equipe_id?`, `doador_nome?`, `ecc_item_compra_id?` |

Regras: doação exige exatamente um de `casal_id`, `ecc_equipe_id` ou `doador_nome`. Compra exige saldo ≥ `valor_gasto` (422 se insuficiente).

## Critérios de aceite

- [ ] Migration `ecc_evento_lancamentos`.
- [ ] ECC (`origem=ecc`) sempre permite compras/caixa.
- [ ] `POST .../caixa/doacoes` — entrada.
- [ ] `POST .../itens-compra/{id}/comprar` cria saída + atualiza item.
- [ ] `POST .../desfazer` remove saída se houver.
- [ ] `GET .../caixa` relatório (saldo, entradas, saídas, extrato, itens).
- [ ] Front: caixa + extrato + doação + relatório no detalhe ECC.
- [ ] Testes API.

## Fora de escopo

- Integração bancária / pagamento online.
- Caixa geral da igreja (módulo Financeiro).

## Contrato

`api/docs/specs/openapi.yaml` — paths `/ecc/eventos/{id}/caixa*`.
