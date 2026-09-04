# SPEC-001 — Admin de tenants + identidade visual Eclesia

**Status:** approved  
**Data:** 2026-09-03  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

O front ainda é o scaffold Innov (azul/laranja, menu demo). A API central só expõe health. O produto precisa de uma tela administrativa (landlord) para criar tenants e de identidade visual baseada na `logo.png` (marinho + dourado), com layout inspirado no protótipo ECC.

## Objetivo

1. Identidade visual Eclesia no shell do `front/` (cores da logo; tipografia e sidebar no espírito do protótipo).
2. Autenticação de super-admin da plataforma (central).
3. CRUD de tenants no banco central, provisionando banco do tenant (stancl).
4. Identificação do tenant por header `X-Tenant` (slug), conforme ADR-0002.

## Critérios de aceite (testáveis)

- [ ] Variáveis CSS do front usam marinho (`#00234E`) e dourado (`#C5A059`) como primária/destaque.
- [ ] Login e shell exibem marca Eclesia (logo + nome), não “INNOV”.
- [ ] `POST /api/v1/admin/login` autentica super-admin e devolve token Sanctum.
- [ ] `POST /api/v1/admin/me` e `POST /api/v1/admin/logout` funcionam com Bearer token.
- [ ] Sem token, rotas admin de tenants retornam 401.
- [ ] `GET/POST /api/v1/admin/tenants` lista e cria tenant (`name`, `slug` único).
- [ ] Criar tenant provisiona o banco e roda migrations de tenant.
- [ ] `GET/PUT/DELETE /api/v1/admin/tenants/{id}` funcionam (delete remove o banco).
- [ ] Rotas de tenant exigem header `X-Tenant` com slug válido (não domínio).
- [ ] Front: rota `/tenants` com listagem + formulário de criação/edição.

## Fora de escopo

- Billing/planos/cobrança.
- Multi-igreja no seletor (seed de 1 igreja padrão basta nesta fatia).
- Auth de usuário do tenant nesta spec (já existe `web/login`).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/admin/*`.

## Notas

- Campos de criação: `name`, `slug` (kebab-case, único).
- Modelo `Tenant` sem `HasDomains` no fluxo principal.
- Seed local: um super-admin de desenvolvimento.
