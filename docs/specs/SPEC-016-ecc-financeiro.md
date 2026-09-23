# SPEC-016 — Financeiro do ECC (livro-caixa anual)

**Status:** approved  
**Data:** 2026-09-23  
**Depende de:** SPEC-009 (caixa de evento permanece separado)  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

A comunidade ECC controla o dinheiro do ano numa planilha de fluxo de caixa: duas ou mais contas em paralelo, lançamentos com data e histórico, fechamento mensal e saldo transportado. Isso é distinto do caixa por evento (SPEC-009) e do módulo Financeiro da igreja (roadmap).

## Objetivo

1. Livro anual por igreja: contas, lançamentos, totais mensais e acumulado.
2. Contas configuráveis (`banco` | `especie`); no primeiro acesso semear **Conta ECC (paróquia)** e **Espécie / conta particular**.
3. Lançamento manual: data, histórico, tipo `entrada`|`saida`, valor, conta.
4. Transferência entre contas: par vinculado (`transferencia_id`); no total geral do mês o par se anula.
5. Transportar saldo do ano anterior para 1/1 do ano alvo (lançamentos `abertura`).
6. Saldo negativo permitido.
7. Tela **Financeiro** no ECC (`/ecc/financeiro`).

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| ContaFinanceiro | `ecc_financeiro_contas` | `igreja_id`, `nome`, `tipo` banco\|especie, `ordem`, `ativa` |
| LancamentoFinanceiro | `ecc_financeiro_lancamentos` | `igreja_id`, `ecc_financeiro_conta_id`, `data`, `historico` (TEXT + `$encryptable`), `tipo` entrada\|saida, `valor`, `transferencia_id?`, `abertura` |

Regras:

- Escopo `igreja_id`; ULID na URL.
- Não excluir conta com lançamento — desativar.
- Transferência: origem ≠ destino; valor > 0; editar/excluir um lado altera o par.
- Transportar: 422 se o ano já tem abertura.

## Critérios de aceite

- [ ] Migrations `ecc_financeiro_contas` e `ecc_financeiro_lancamentos`.
- [ ] Seed de duas contas padrão no primeiro acesso.
- [ ] CRUD de contas; desativar em vez de excluir com movimento.
- [ ] `GET /ecc/financeiro?ano=` — livro do ano.
- [ ] `POST /ecc/financeiro/lancamentos` — entrada/saída.
- [ ] `POST /ecc/financeiro/transferencias` — par vinculado.
- [ ] `PUT|DELETE /ecc/financeiro/lancamentos/{id}` — transferência em par.
- [ ] `POST /ecc/financeiro/transportar` — aberturas em 1/1.
- [ ] Permissões `ecc.financeiro.view|manage`, `telas.financeiro` (admin igreja/tenant; líder não vê).
- [ ] Front: item Financeiro + tela `/ecc/financeiro`.
- [ ] Testes API + util de totais no front.

## Fora de escopo

- Ligação automática com caixa do evento (SPEC-009).
- Contribuição mensal por casal.
- Importação da planilha histórica.
- Integração bancária / PIX / módulo Financeiro da igreja no launcher.

## Contrato

`api/docs/specs/openapi.yaml` — paths `/ecc/financeiro*`.
