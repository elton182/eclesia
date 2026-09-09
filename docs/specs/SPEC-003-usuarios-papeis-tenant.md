# SPEC-003 — Usuários, papéis e login por tenant

**Status:** approved  
**Data:** 2026-09-08  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

A autenticação web do tenant existe (`web/login|me|logout`), mas não há gestão de usuários nem Spatie. O login da plataforma ocupa a rota `/` do front; usuários do tenant precisam informar a organização (slug, nome ou apelido) na home.

## Objetivo

1. Apelidos de tenant no banco central e resolução no login por slug | apelido | nome (único).
2. `spatie/laravel-permission` no banco do tenant com teams = `igreja_id`.
3. Papéis fixos via seed (sem CRUD de roles customizados).
4. CRUD de usuários do tenant + **N papéis** por usuário (sync) + vínculo líder↔equipes.
5. Front: `/` = login do usuário do tenant; `/admin/login` = super-admin; telas de gestão de usuários.

## Critérios de aceite (testáveis)

- [ ] Tenant aceita `aliases[]` no create/update; aliases únicos e normalizados (lowercase).
- [ ] `POST /api/v1/web/login` exige `tenant`, `email`, `password`; resolve organização sem header `X-Tenant` obrigatório no login.
- [ ] Resolução: slug → apelido → nome (case-insensitive); nome ambíguo → 422; inexistente → 404/422.
- [ ] Resposta de login bem-sucedido inclui `tenant.slug` (canônico) para o front setar `X-Tenant`.
- [ ] Tabelas Spatie existem nas migrations de tenant; `teams` = `igreja_id`.
- [ ] Seed cria papéis canônicos: `admin-tenant`, `admin-igreja`, `cadastros-usuarios`, `cadastros-equipes`, `cadastros-casais`, `lider-equipe`.
- [ ] Seed cria permissões de tela: `telas.usuarios`, `telas.equipes`, `telas.casais` (+ gestão users/roles e stubs `ecc.*`).
- [ ] Provisionar tenant cria usuário admin inicial com papel `admin-tenant`.
- [ ] `GET/POST/PATCH/DELETE /api/v1/users` (ULID público); PII `name`/`email` encryptable.
- [ ] `PUT /api/v1/users/{id}/roles` sincroniza N papéis (`igreja_id` + `roles[]`); `lider-equipe` exige `equipe_ids` (N:M em `ecc_equipe_user`).
- [ ] Líder de equipe (`lider-equipe` sem `ecc.*.manage`) lista só as equipes vinculadas e só os casais dessas equipes; show de recurso alheio → 404; create/update/delete/import → 403.
- [ ] `GET /api/v1/roles`: SuperAdmin vê `admin-tenant` + papéis de igreja; usuário do tenant não vê `admin-tenant`.
- [ ] `GET /api/v1/permissions` lista catálogo (sem CRUD).
- [ ] Sem permissão → 403; sem auth → 401; validação → 422.
- [ ] Isolamento: usuário de um tenant não acessa dados de outro.
- [ ] Front `/` login tenant; `/admin/login` super-admin; gestão de usuários com **toggles** de papéis.
- [ ] Front Casais/Equipes: ações de cadastro só com `ecc.*.manage`; líder vê apenas o retorno já filtrado da API.

## Fora de escopo

- CRUD de papéis/permissões customizados.
- Líder editar casais/equipes da própria equipe (só leitura nesta fatia; decisão em aberto no BRIEF).
- Vínculo obrigatório User ↔ Pessoa; `pessoa_id` nullable.
- Seletor de igreja completo no shell.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/web/*`, `/users*`, `/roles`, `/permissions`, aliases em `/admin/tenants`.

## Notas

- Papéis guard `web`. `admin-tenant` com `team_id` null (escopo organização).
- URLs públicas de usuário usam ULID (`users.ulid`), não id sequencial.
- Cache Spatie limpo ao inicializar tenancy.
- Um usuário pode ter vários papéis; UI usa toggles + `PUT` sync.
