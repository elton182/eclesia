# SPEC-032 — Link genérico do planejamento anual

**Status:** approved  
**Data:** 2026-11-10  
**Relacionado:** [SPEC-018](./SPEC-018-pastorais-planejamento-anual.md), [SPEC-019](./SPEC-019-planejamento-espacos-recursos.md)

## Contexto

Hoje cada pastoral tem um link tokenizado próprio (`planejamento_pastoral_tokens`). Para coletas amplas (ex.: reunião com várias pastorais), o gestor precisa de **um único link genérico** do ano: quem abre informa o nome da pastoral e propõe eventos nela.

## Objetivo

1. Um link público genérico por `planejamento_anual` (gerar / rotacionar / revogar), paralelo aos links por pastoral.
2. Na página pública, campo central **Nome da Pastoral** obrigatório.
3. Listagem e criação de eventos escopadas à pastoral informada.
4. Se a pastoral ainda não existir na igreja do ano: criar com `ativa=true` e `publicado_no_site=false` (apenas na **criação** de evento; listagem não cria).
5. Match de nome: `trim` + case-insensitive.

## Critérios de aceite (testáveis)

- [ ] Gestor com `planejamento.gerir` gera/consulta/revoga `GET|POST|DELETE /planejamento/anuais/{id}/token-generico`.
- [ ] No máximo um token genérico ativo por ano (regenerar = rotacionar).
- [ ] `GET /public/planejamento/{token}` distingue `modo: pastoral|generico`.
- [ ] Em modo genérico, `GET .../eventos` exige query `pastoral_nome`; sem nome → 422; nome inexistente → lista vazia (não cria pastoral).
- [ ] Em modo genérico, `POST .../eventos` exige `pastoral_nome`; cria pastoral se necessário e evento `proposta` nela; só com ano em `coleta`.
- [ ] Nome existente com casing diferente reutiliza a mesma pastoral.
- [ ] Isolamento: só eventos da pastoral informada; link por pastoral continua intacto.
- [ ] Front: bloco “Link genérico” no detalhe do ano; view pública com campo central e gate sem nome.

## Fora de escopo

- Autocomplete / lista pública de pastorais no link.
- Unicidade formal de `nome` no banco (match lógico case-insensitive basta nesta versão).
- Alterar o modelo de tokens por pastoral.

## Contrato de API

OpenAPI: `/planejamento/anuais/{id}/token-generico` e emenda de `/public/planejamento/{token}*`.

## Notas

- Tabela `planejamento_anual_tokens` (tenant), mesmo padrão de hash/cifrado dos tokens por pastoral.
- Lookup unificado na URL pública existente `/planejamento/publico/:token`.
