# ADR-0005 — Escalas no núcleo (não ECC)

**Status:** accepted  
**Data:** 2026-09-16  
**Relacionados:** [ADR-0002](./ADR-0002-hierarquia-tenant-igreja.md), [SPEC-007](../specs/SPEC-007-escalas-genericas.md)

## Contexto

O BRIEF lista “Escala” dentro do módulo ECC (casal × equipe de serviço no encontro). O produto precisa também de escalas genéricas da igreja (liturgia, coroinhas, etc.) reutilizáveis por Pastorais/Catequese. Colocar o domínio só em `ecc_*` acoplaria missas e ministérios paroquiais ao ECC.

## Decisão

1. **Escalas genéricas vivem no núcleo**, tabelas `escala_*` + `evento_agenda`, escopo `igreja_id`.
2. **UI própria** no launcher (`telas.escalas`), não sob `/ecc/*`.
3. **ECC não é dono** deste domínio nesta fase. No futuro o ECC pode **consumir** o motor (tipo “Serviço do Encontro” + regras de sugestão) sem criar um segundo modelo de escala.
4. Atribuição aceita **pessoa avulsa ou casal**; múltiplos papéis na mesma ocorrência são permitidos.
5. Ocorrências **publicam** em `EventoAgenda` (`dono_modulo=escalas`) para agenda unificada.

## Consequências

- Stub `ecc.escala.editar` permanece para o fluxo ECC futuro; permissões de produto genérico são `escalas.*` / `telas.escalas`.
- Módulos futuros dependem só do núcleo, não de `ecc_*`.
- Calendário do ECC continua podendo ser visão filtrada de `evento_agenda` quando o ECC publicar eventos próprios.
