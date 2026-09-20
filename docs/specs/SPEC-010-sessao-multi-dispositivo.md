# SPEC-010 — Sessão multi-dispositivo (auth web)

**Status:** approved  
**Data:** 2026-09-18  
**Plano:** [PLAN-003](../plans/PLAN-003-calendario-oficial-sessao-branding.md)

## Contexto

O login web revogava todos os tokens `access-token`/`refresh-token` do usuário (`revokeTokensForLogin`), derrubando sessões em outros dispositivos (site vs app).

## Objetivo

1. Permitir múltiplas sessões web paralelas por usuário.
2. Logout e refresh afetam apenas a sessão atual (par access/refresh).

## Critérios de aceite (testáveis)

- [ ] `POST /api/v1/web/login` **não** revoga tokens de outras sessões.
- [ ] Dois logins consecutivos: ambos access tokens permanecem válidos em `GET /web/me`.
- [ ] `POST /api/v1/web/logout` revoga apenas o par de tokens da sessão atual; outras sessões seguem válidas.
- [ ] `POST /api/v1/web/refresh` renova apenas o access token da sessão do refresh enviado (não apaga access de outras sessões).
- [ ] Cada par access/refresh compartilha um identificador de sessão (ability Sanctum).

## Fora de escopo

- Listagem/revogação de sessões pela UI.
- Limite máximo de sessões simultâneas.
- Alteração do fluxo admin (`/admin/login`).

## Contrato de API

Sem mudança de paths; comportamento de login/logout/refresh conforme OpenAPI (notas em `/web/login`).

## Notas

- Par de tokens criado com ability `session:{ulid}`.
- Logout: resolve sessão do token autenticado (ou refresh cookie) e apaga tokens com a mesma ability.
