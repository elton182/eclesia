# SPEC-022 — Planejamento anual: visão do gestor (semana/mês, filtros e conflitos)

**Status:** approved  
**Data:** 2026-10-20  
**Emenda:** [SPEC-018](./SPEC-018-pastorais-planejamento-anual.md)  
**Relacionado:** [SPEC-019](./SPEC-019-planejamento-espacos-recursos.md)

## Contexto

Na visão do gestor do planejamento anual (`/planejamento/:id`), a grade é só mensal, o filtro de pastoral é um `<select>` único e os conflitos de local/horário só aparecem após salvar (alerta) ou no modal do dia (badge). O gestor precisa de uma leitura tipo agenda (Google Calendar): alternar semana/mês, ligar/desligar pastorais na lateral e ver conflitos destacados na grade.

## Objetivo

1. Alternar a grade entre **visão mensal** e **visão semanal**.
2. Painel lateral com **checkboxes por pastoral** (cor estável) para exibir/ocultar eventos.
3. **Destacar conflitos** de agenda (mesmo `calendario_locais` + intervalo sobreposto) na grade e na lista do dia, sem depender só do retorno do POST/PUT.

## Critérios de aceite (testáveis)

### Visão semana / mês

- [x] Toggle `Mensal` | `Semanal` na tela do ano (`data-testid="planejamento-visao-toggle"`).
- [x] Visão mensal mantém a grade Dom–Sáb do mês selecionado.
- [x] Visão semanal mostra os 7 dias da semana corrente; navegação anterior/próxima semana.
- [x] Visão semanal com **eixo de horas na lateral** (faixa padrão 06:00–22:00), eventos posicionados por `hora_inicio`/`hora_fim`; sem hora → faixa “Dia” (`data-testid="planejamento-grade-semana"`, `planejamento-semana-horas`).
- [x] Ao mudar de mês no seletor, a semana exibida passa a ser a que contém o dia 1 desse mês (se a data âncora sair do mês).
- [x] Helpers unitários cobrem grade semanal, horas e layout (`gradeSemanaCalendario`, `horasGradeSemana`, `layoutEventoNaGradeHoraria`, `addDiasIso`, `inicioSemanaDomingo`).

### Filtro por pastoral (tags laterais)

- [x] Com `planejamento.gerir` ou `planejamento.ver_global`, painel lateral lista pastorais com checkbox + swatch de cor (`data-testid="planejamento-filtro-pastorais"`).
- [x] Painel pode ser **recolhido/expandido** (`data-testid="planejamento-painel-toggle"`) para ganhar área da grade; recolhido mostra só o botão (e dots de cor no desktop).
- [x] Desmarcar uma pastoral oculta os eventos dela na grade e no modal do dia.
- [x] Ações rápidas “Todas” / “Nenhuma”.
- [x] Preferência de visibilidade / recolhido é só de UI (não altera API); estado inicial = todas marcadas, painel expandido.
- [x] Chips na grade usam a cor da pastoral (além do estilo de status quando útil).
- [x] Filtros de status e espaço existentes continuam válidos.

### Conflitos na grade

- [x] A partir da lista carregada, a UI anota eventos com conflito de local/horário (mesma regra da SPEC-018: interseção de `local_ids` + intervalos sobrepostos).
- [x] Chip/célula com conflito tem destaque visual e `data-testid="planejamento-badge-conflito"` (ou equivalente na grade: `planejamento-chip-conflito`).
- [x] Resumo opcional no topo quando houver ≥1 conflito visível no período filtrado.
- [x] Conflito continua **não bloqueante** na gravação (SPEC-018); espaços/recursos (SPEC-019) seguem bloqueando via API.

## Fora de escopo

- Sync com calendário oficial (SPEC-012).
- Drag-and-drop avançado estilo Google Calendar (já há arraste simples dia→dia).
- Persistência server-side das pastorais ocultas / preferência de visão.
- Endpoint novo de conflitos (cálculo no cliente a partir da listagem já carregada).

## Contrato de API

Sem mudança de contrato. Reusa `GET /planejamento/anuais/{id}/eventos` (com `locais` / `local_ids`) e o payload `conflitos[]` de create/update.

## Notas

- Coordenador sem `ver_global` não vê o painel de todas as pastorais; continua no escopo das suas.
- Cores de pastoral são paleta estável por índice na lista ordenada (não PII).
