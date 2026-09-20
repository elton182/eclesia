# ADR-0005 — Escalas no núcleo (não ECC)

**Status:** superseded  
**Data:** 2026-09-16  
**Supersedido em:** 2026-09-20  
**Relacionados:** [ADR-0002](./ADR-0002-hierarquia-tenant-igreja.md), [SPEC-007](../specs/SPEC-007-escalas-genericas.md) (rejected)

## Contexto

O BRIEF lista “Escala” dentro do módulo ECC (casal × equipe de serviço no encontro). Havia também a hipótese de um módulo de escalas genéricas da igreja (liturgia, coroinhas, etc.) com ocorrências por horário e agenda própria.

## Decisão original (revogada)

1. Escalas genéricas no núcleo (`escala_*` + publicação em `evento_agenda`).
2. UI própria no launcher (`telas.escalas`).
3. ECC consumiria o mesmo motor no futuro.

## Decisão atual

1. **O módulo Escalas genéricas foi removido.** Compromissos por horário e agenda da igreja ficam em **Eventos** e **Calendário**.
2. **`evento_agenda` permanece** no núcleo como store unificado (publicado por ECC/eventos e demais módulos).
3. **Escala do ECC** (casal × equipe de serviço no encontro) continua no roadmap do módulo ECC (`ecc.escala.editar`); **não** é este domínio removido.

## Consequências

- Tabelas `escala_*`, rotas `/api/v1/escalas/*`, permissões `telas.escalas` / `escalas.*` e papel `cadastros-escalas` foram retirados.
- Migration `drop_escalas_tables` remove resíduos em tenants já provisionados.
- SPEC-007 marcada como **rejected**.
