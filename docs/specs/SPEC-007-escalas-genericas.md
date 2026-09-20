# SPEC-007 — Escalas genéricas (núcleo)

**Status:** rejected  
**Data:** 2026-09-16  
**Rejeitada em:** 2026-09-20  
**Plano:** —  
**ADR:** [ADR-0005](../architecture/ADR-0005-escalas-nucleo.md) (superseded)

## Motivo da rejeição

O produto **não** terá módulo de escalas genéricas por horário. Essa necessidade é atendida por **Eventos** e **Calendário**. A escala do **ECC** (casal × equipe no encontro) permanece no BRIEF/roadmap do ECC e é domínio separado.

## Histórico

Implementação anterior (tipos, equipes, ocorrências, atribuições, UI `/escalas/*`) foi removida. A tabela **`evento_agenda`** criada nesta fatia **permanece** (usada por SPEC-008 e demais módulos).
