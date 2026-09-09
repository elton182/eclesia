# SPEC-004 — Cadastro de igrejas + seletor multi-igreja

**Status:** approved  
**Data:** 2026-09-09  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

A entidade `Igreja` existe só com `nome` e `GET /igrejas`. O contexto (`IgrejaContext`) sempre usa a primeira igreja. Falta CRUD, campos de endereço/contato, seletor no shell e permissões.

## Objetivo

1. Expandir o cadastro de Igreja (paróquia/comunidade): nome, tipo, endereço, contatos.
2. CRUD REST com matriz híbrida de permissões.
3. Header `X-Igreja` validado → `IgrejaContext` (escopo real multi-igreja).
4. Front: seletor no shell + tela de gestão.

## Critérios de aceite (testáveis)

- [ ] Migration tenant adiciona a `igrejas`: `tipo`, endereço (`endereco`, `bairro`, `cidade`, `uf`, `cep`), contatos (`telefone`, `email`).
- [ ] `tipo` ∈ `paroquia` | `comunidade` | `outro`; default `paroquia`.
- [ ] PII criptografada: `endereco`, `bairro`, `cidade`, `cep`, `telefone`, `email` (TEXT + `$encryptable`).
- [ ] Permissões Spatie: `telas.igrejas`, `igrejas.view`, `igrejas.create`, `igrejas.update`, `igrejas.delete`.
- [ ] `admin-tenant` / SuperAdmin: CRUD em qualquer igreja; seletor com todas.
- [ ] `admin-igreja`: `view` + `update` + `telas.igrejas`; update só na igreja do próprio team; sem create/delete.
- [ ] Demais papéis: `GET` / `X-Igreja` só igrejas com papel; sem menu de cadastro.
- [ ] `GET/POST/PUT/DELETE /api/v1/igrejas` (ULID); listagem filtrada pelo acesso do ator.
- [ ] Header `X-Igreja` define o contexto; sem header → primeira igreja acessível; inválida/sem acesso → 403.
- [ ] Delete bloqueado (409) se houver vínculos em `pessoas`, `ecc_equipes` ou `casais`.
- [ ] Sem auth → 401; sem permissão → 403; validação → 422.
- [ ] Front: seletor no TopNavbar; store Pinia + `localStorage`; envia `X-Igreja`; reset ao trocar.
- [ ] Front: tela `/igrejas` com UI condicionada às permissões.
- [ ] Seed demo: 1 tenant com 2 igrejas; provisionamento normal continua com 1 igreja.

## Fora de escopo

- Dados eclesiásticos.
- Capelas / subnível de igreja.
- `PessoaIgreja` / familles.
- Global scope Eloquent em massa (serviços ECC seguem filtro via `IgrejaContext`).
- Módulos habilitáveis por igreja.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/igrejas`, `/igrejas/{id}` e header `X-Igreja`.

## Notas

- Entidade de domínio permanece `Igreja`; UI pode rotular “Paróquia / Comunidade”.
- Não confiar em `igreja_id` do body para escopo de dados operacionais — derivar de `IgrejaContext`.
- `admin-tenant` com `team_id` null (organização); papéis de igreja usam `teams = igreja_id`.
