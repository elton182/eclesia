# ADR-0006 — Locais de celebração no calendário oficial

**Status:** accepted  
**Data:** 2026-09-18  
**Relacionado:** [ADR-0002](./ADR-0002-hierarquia-tenant-igreja.md), [SPEC-012](../specs/SPEC-012-calendario-oficial.md)

## Contexto

O calendário oficial da paróquia usa várias “colunas” de local (Matriz, capelas, cemitério, etc.). O ADR-0002 define Igreja como menor unidade operacional e exclui sub-nível comunidade/capela na hierarquia.

## Decisão

- Introduzir `calendario_locais` como **catálogo de rótulos da grade** scoped por `igreja_id` (nome, ordem, ativo).
- Não criar entidade organizacional nem escopo de permissões por local.
- Não alterar a hierarquia Tenant → Igreja.

## Consequências

- A grade do PDF usa locais do catálogo; texto livre em itens legados permanece possível via nome do local.
- Futuro módulo de pastorais não reutiliza `calendario_locais` como hierarquia.
