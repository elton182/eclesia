# ADR-0008 — Espaços e recursos do Planejamento Anual

**Status:** accepted  
**Data:** 2026-10-14  
**Spec:** [SPEC-019](../specs/SPEC-019-planejamento-espacos-recursos.md)  
**Relacionado:** [ADR-0006](./ADR-0006-calendario-locais-celebracao.md), [SPEC-018](../specs/SPEC-018-pastorais-planejamento-anual.md)

## Contexto

O Planejamento Anual precisa impedir que duas atividades usem a mesma sala ao mesmo tempo e que um equipamento escasso (ex.: 1 projetor móvel) seja alocado em mais eventos do que existe. A SPEC-018 tratava conflito de `calendario_locais` só como alerta.

`calendario_locais` (ADR-0006) é o catálogo de colunas da grade de celebrações da Agenda Paroquial (Matriz, capelas). Misturar salas internas e equipamentos nesse catálogo poluiria o PDF de missas e acoplaria os dois módulos.

## Decisão

1. Novo catálogo `planejamento_recursos` no banco do tenant, por `igreja_id`, com `tipo` (`espaco` \| `recurso`) e `quantidade`. Uma tabela só: espaço é um recurso de quantidade 1.
2. Alocação por evento na pivot `planejamento_evento_recurso` com `quantidade`.
3. **Bloqueio rígido** por capacidade no intervalo do evento (422), em todos os caminhos de gravação, inclusive link público. Eventos `recusado` não ocupam.
4. Validação em transação com `lockForUpdate` nos itens envolvidos para evitar dupla alocação concorrente.
5. `calendario_locais` e seu alerta não bloqueante permanecem como estão.

## Consequências

- O padre deixa de poder "forçar" uso duplo de sala/recurso; precisa mudar horário, espaço ou quantidade.
- O link público passa a consultar disponibilidade agregada (sem expor eventos de outras pastorais).
- Agenda Paroquial e módulo Eventos podem, no futuro, reutilizar o catálogo — exigirá novo ADR para unificar a ocupação.
