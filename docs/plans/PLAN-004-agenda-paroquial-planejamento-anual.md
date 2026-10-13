# PLAN-004 — Agenda Paroquial (rename) + Planejamento Anual

**Status:** em execução  
**Data:** 2026-10-13  
**Specs:** [SPEC-012](../specs/SPEC-012-calendario-oficial.md) (rename UI), [SPEC-016](../specs/SPEC-016-planejamento-anual.md) (approved)  
**Depende de:** cadastro `pastorais` ([SPEC-005](../specs/SPEC-005-site-publico-tenant.md))

## Objetivo

1. Renomear na UI o módulo **Calendário** → **Agenda Paroquial** (missas, casamentos, batismos etc. — domínio SPEC-012).
2. Introduzir o módulo **Planejamento Anual**: pastorais propõem, padre revisa/fecha; calendário estilo Google Calendar; ano civil; acesso login + link.

## Decisões fechadas

1. Agenda Paroquial = **apenas rename** do Calendário oficial (API `/calendario/*` pode permanecer).
2. Módulo **Eventos** permanece **separado** por hora.
3. Pastorais = cadastro CMS; mesma tabela na área de pastorais do novo módulo.
4. Acesso pastoral: login **e** link tokenizado.
5. Ano **civil**.
6. Sem sync com Agenda / `evento_agenda` por hora.
7. Status com **tags coloridas**.

## Fora de escopo

- Unificar Eventos + Planejamento + Agenda
- PDF do planejamento
- Ano litúrgico
- Módulo Pastorais completo (membros/reuniões)

## Ordem de implementação

1. [x] Aprovar SPEC-016 (link sem leitura cruzada).
2. [x] OpenAPI paths `/planejamento/*` e `/public/planejamento/{token}`.
3. [x] Rename UI Agenda Paroquial (launcher, ModuleLayout, textos).
4. [x] API: migrations tenant, models, services, permissões, testes Pest.
5. [x] Front: launcher + ModuleLayout + calendário (modal dia, filtros, tags, drag & drop) + testes utils.
6. [x] Área Pastorais no módulo apontando ao CRUD `/site/pastorais`.
7. [x] Fluxo público do link (`/planejamento/publico/:token`).

## Aceite

- Launcher mostra **Agenda Paroquial** e **Planejamento Anual** como ícones distintos; **Eventos** intacto.
- Padre abre ano civil, libera coleta, pastoral lança por login ou link, padre filtra/revisa/drag & drop e fecha.
- Nenhuma duplicação da tabela `pastorais`.
