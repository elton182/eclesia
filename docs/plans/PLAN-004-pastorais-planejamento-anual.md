# PLAN-004 — Pastorais e calendário anual de planejamento

**Status:** concluído  
**Data:** 2026-10-08  
**Specs:** [SPEC-018](../specs/SPEC-018-pastorais-planejamento-anual.md)  
**ADRs:** [ADR-0003](../architecture/ADR-0003-site-publico-tenant.md), [ADR-0006](../architecture/ADR-0006-calendario-locais-celebracao.md)  
**Relacionado:** [SPEC-012](../specs/SPEC-012-calendario-oficial.md) (calendário oficial — sem sync nesta fase)

## Objetivo

1. Elevar `pastorais` de vitrine do site a cadastro de domínio com membros (usuários).
2. Entregar calendário anual de planejamento: coleta por pastoral → revisão do padre (conflitos em alerta) → fechamento.
3. Manter o calendário oficial mensal (SPEC-012) independente.

## Decisões fechadas (2026-10-08)

| # | Decisão |
|---|--------|
| 1 | Criar/completar cadastro de pastorais (reuso da tabela existente). |
| 2 | Sem ligação com o calendário oficial por enquanto. |
| 3 | Conflito: padre decide (alerta, sem bloqueio). |
| 4 | Acesso autenticado por pastoral (papel + pivot). |
| 5 | Entra na fase atual (após/paralelo ao calendário oficial). |

## Ordem de implementação

1. [x] Aprovar SPEC-018 (sem TODO pendente).
2. [x] OpenAPI (`/pastorais`, `/planejamento/*`).
3. [x] API TDD: pastorais + membros → anuais → eventos + conflitos → PDF → permissões.
4. [x] Front: cadastro pastorais/membros → calendário planejamento (visão gestor e coordenador).
5. [x] Seed demo: pastorais + um ano em coleta (`DatabaseSeeder`).

## Aceite

- Coordenador logado propõe eventos só da sua pastoral em `coleta`.
- Padre vê global, vê alertas de conflito, pede ajuste ou edita, fecha o ano.
- Ano `fechado` trava edição do coordenador; PDF exportável.
- Calendário oficial inalterado e sem sync.

## Fora deste plano

- Sync com SPEC-012 / `evento_agenda`
- Bloqueio hard de conflito
- Coleta por link público
- Importador da planilha Google Forms
