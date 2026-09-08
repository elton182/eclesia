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
4. CRUD de usuários do tenant + atribuição de papéis por igreja.
5. Front: `/` = login do usuário do tenant; `/admin/login` = super-admin; telas de gestão de usuários.

## Critérios de aceite (testáveis)

- [ ] Tenant aceita `aliases[]` no create/update; aliases únicos e normalizados (lowercase).
- [ ] `POST /api/v1/web/login` exige `tenant`, `email`, `password`; resolve organização sem header `X-Tenant` obrigatório no login.
- [ ] Resolução: slug → apelido → nome (case-insensitive); nome ambíguo → 422; inexistente → 404/422.
- [ ] Resposta de login bem-sucedido inclui `tenant.slug` (canônico) para o front setar `X-Tenant`.
- [ ] Tabelas Spatie existem nas migrations de tenant; `teams` = `igreja_id`.
- [ ] Seed cria papéis: `admin-tenant`, `admin-igreja`, `coordenador-modulo`, `secretaria`, `lider-equipe`, `membro`.
- [ ] Seed cria permissões de gestão: `users.view|create|update|delete`, `roles.assign`, `permissions.view` (+ stubs `ecc.*`).
- [ ] Provisionar tenant cria usuário admin inicial com papel `admin-tenant`.
- [ ] `GET/POST/PATCH/DELETE /api/v1/users` (ULID público); PII `name`/`email` encryptable.
- [ ] `POST/DELETE /api/v1/users/{id}/roles` atribui/remove papel por `igreja_id` (exceto `admin-tenant`, team null).
- [ ] `GET /api/v1/roles` e `GET /api/v1/permissions` listam catálogo (sem CRUD).
- [ ] Sem permissão → 403; sem auth → 401; validação → 422.
- [ ] Isolamento: usuário de um tenant não acessa dados de outro.
- [ ] Front `/` login tenant (organização + e-mail + senha); `/admin/login` super-admin.
- [ ] Front gestão de usuários + aliases no formulário de tenants (admin).

## Fora de escopo

- CRUD de papéis/permissões customizados.
- Permissões finas ECC (líder só na própria equipe, etc.).
- Vínculo obrigatório User ↔ Pessoa; `pessoa_id` nullable.
- Seletor de igreja completo no shell.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/web/*`, `/users*`, `/roles`, `/permissions`, aliases em `/admin/tenants`.

## Notas

- Papéis guard `web`. `admin-tenant` com `team_id` null (escopo todas as igrejas).
- URLs públicas de usuário usam ULID (`users.ulid`), não id sequencial.
- Cache Spatie limpo ao inicializar tenancy.
