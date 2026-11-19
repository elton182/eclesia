# SPEC-038 — Pastorais: unificar, busca e calendário filtrado

**Status:** approved  
**Data:** 2026-11-19  
**Emenda:** [SPEC-018](./SPEC-018-pastorais-planejamento-anual.md)  
**Relacionado:** [SPEC-022](./SPEC-022-planejamento-visao-gestor-calendario.md)

## Contexto

No planejamento anual, a visão mensal só tem seletor de mês (a semanal já tem anterior/próximo). No cadastro de pastorais faltam busca e um atalho para ver o calendário só daquela pastoral. Pastorais duplicadas (mesmo movimento cadastrado duas vezes) precisam ser unificadas sem perder eventos.

## Objetivo

1. Na tela do ano (`/planejamento/:id`), botões **anterior** e **próximo** ao lado do mês (seletor e título da grade).
2. Unificar duas pastorais: manter uma, absorver a outra; eventos e membros passam para a mantida; a absorvida é removida.
3. Na lista `/pastorais`, campo de busca por nome.
4. Ao clicar numa pastoral na lista, abrir o calendário do planejamento (ano atual ou o mais recente) com filtro só dessa pastoral.

## Critérios de aceite (testáveis)

### Navegação de mês

- [x] Botões `data-testid="planejamento-mes-prev"` e `planejamento-mes-next` ao lado do seletor de mês e/ou do título mensal.
- [x] Anterior/próximo alteram o mês no intervalo 1–12 do ano do planejamento (sem mudar de ano).
- [x] Em janeiro, anterior desabilitado; em dezembro, próximo desabilitado.
- [x] Visão semanal continua com sua própria navegação.

### Unificar pastorais

- [x] `POST /api/v1/pastorais/merge` com `keep_id` e `absorb_id` (ULIDs distintos da mesma igreja).
- [x] Requer `pastorais.manage` (403 sem permissão; 401 sem auth).
- [x] Eventos de `planejamento_eventos` da absorvida passam a `pastoral_id` da mantida.
- [x] Membros: união no pivot; se o usuário já está na mantida, prevalece papel `coordenador` se qualquer um tiver.
- [x] Tokens públicos da absorvida: se a mantida já tem token no mesmo anual, remove o da absorvida; senão reaponta `pastoral_id`.
- [x] Campos vazios da mantida (`descricao_publica`, `contato_publico`) podem ser preenchidos com o da absorvida.
- [x] Absorvida é excluída; resposta 200 com a pastoral mantida.
- [x] Front `/pastorais`: modo unificar (selecionar 2) + confirmação (qual manter).

### Busca e calendário

- [x] Input de busca na lista de pastorais (`data-testid="pastorais-busca"`); filtra por nome (case-insensitive).
- [x] Clique no item abre `/planejamento/{anualId}?pastoral={id}` (anual do ano corrente se existir; senão o de maior `ano`).
- [x] Sem anual aberto: toast de aviso, sem navegar.
- [x] Detalhe do planejamento aplica `?pastoral=` deixando só essa pastoral visível no painel.
- [x] Ação “Membros” (ou equivalente) continua levando a `/pastorais/:id`.

## Fora de escopo

- Preview de campos com revisão seletiva (como casais ECC).
- Unificar mais de duas pastorais de uma vez.
- Navegar mês mudando o ano do planejamento.

## Contrato de API

OpenAPI: `POST /pastorais/merge` — body `{ keep_id, absorb_id }`.

## Notas

- Cascade de `pastoral_id` em eventos/tokens: a unificação **deve** reatribuir antes de deletar a absorvida.
- Busca reutiliza `filterPastoraisBySearch` do front (já usado no painel do planejamento).
