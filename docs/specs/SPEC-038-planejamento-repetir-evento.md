# SPEC-038 — Repetir evento no planejamento anual

**Status:** approved  
**Data:** 2026-11-24  
**Relacionado:** [SPEC-018](./SPEC-018-pastorais-planejamento-anual.md), [SPEC-019](./SPEC-019-planejamento-espacos-recursos.md), [SPEC-032](./SPEC-032-planejamento-link-generico.md)

## Contexto

Na coleta do planejamento anual, pastorais (link público) e gestores (tela interna) precisam cadastrar atividades recorrentes — ex.: reunião toda segunda, ou no mesmo dia de cada mês — sem criar cada ocorrência manualmente.

A SPEC-018 tratava recorrência (RRULE / texto livre) como fora de escopo: cada ocorrência é um evento separado. Esta feature mantém esse modelo: a API **expande** na criação e grava N linhas em `planejamento_eventos`.

## Objetivo

1. Campo opcional `repetir` no create autenticado e no create público.
2. Modos: `semanal` (mesmo dia da semana até 31/12 do ano) e `mensal` (mesmo dia do mês até dezembro; dia inexistente → último dia do mês).
3. Preservar duração (`data_fim − data_inicio`) em cada ocorrência.
4. UI de “Repetir” só na **criação** (público e interno); edição/remoção permanece por ocorrência.

## Critérios de aceite (testáveis)

- [ ] `POST /planejamento/anuais/{id}/eventos` aceita `repetir: semanal|mensal`; omitido → 1 evento (shape atual).
- [ ] `POST /public/planejamento/{token}/eventos` aceita o mesmo campo.
- [ ] Com `repetir`, resposta `201` traz `data` como array de eventos + `criados: N` + `conflitos[]` agregados.
- [ ] Sem `repetir`, `data` continua objeto único (compatível).
- [ ] Semanal: a partir de `data_inicio`, só o mesmo weekday até o fim do ano do planejamento.
- [ ] Mensal: uma ocorrência por mês restante; dia 31 em fevereiro → último dia de fevereiro.
- [ ] Capacidade (SPEC-019): se qualquer ocorrência falhar → 422 e **zero** eventos gravados (transação única).
- [ ] `repetir` inválido → 422.
- [ ] PATCH/DELETE (público e autenticado) **não** aceitam `repetir` / não reexpandem.
- [ ] Front público e interno: select “Repetir” só em nova proposta/evento.

## Fora de escopo

- RRULE, série vinculada, editar/excluir “toda a série”.
- Recorrência com fim customizado (sempre até 31/12 do ano do planejamento).
- Repetir em dias da semana múltiplos (ex.: seg+qua) numa única criação.

## Contrato de API

OpenAPI: emenda de `POST /planejamento/anuais/{id}/eventos` e `POST /public/planejamento/{token}/eventos` — propriedade `repetir`.

## Notas

- Emenda à SPEC-018: “Recorrência RRULE” permanece fora; **expansão no create** passa a ser suportada por esta SPEC.
- Conflitos soft de local continuam não bloqueantes (201 + `conflitos[]`).
