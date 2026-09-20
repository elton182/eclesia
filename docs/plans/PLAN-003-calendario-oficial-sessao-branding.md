# PLAN-003 — Calendário oficial, sessão multi-device e white-label

**Status:** em execução  
**Data:** 2026-09-18  
**Specs:** [SPEC-010](../specs/SPEC-010-sessao-multi-dispositivo.md), [SPEC-011](../specs/SPEC-011-branding-tenant.md), [SPEC-012](../specs/SPEC-012-calendario-oficial.md)  
**ADR:** [ADR-0006](../architecture/ADR-0006-calendario-locais-celebracao.md)

## Objetivo

1. Permitir a mesma conta logada em site e app sem derrubar a outra sessão.
2. White-label por tenant: cores + logo (`site_settings`).
3. Módulo Calendário Oficial mensal: rascunho → coleta de indisponibilidades → montagem → fechamento → PDF.

## Fora de escopo

- Calendários de pastorais / publicação automática em `evento_agenda`
- Override de cores por Igreja
- Sub-nível organizacional comunidade/capela

## Ordem de implementação

1. SPEC-010 + auth multi-dispositivo
2. SPEC-011 + branding tenant (API + front)
3. SPEC-012 + domínio/API/front do calendário oficial + PDF

## Aceite

- Dois logins web consecutivos mantêm ambas as sessões; logout afeta só a atual.
- Tenant altera logo/cores; SPA e site público refletem (fallback vinho).
- Padre abre mês, coleta indisponibilidades, monta grade, fecha e exporta PDF.
