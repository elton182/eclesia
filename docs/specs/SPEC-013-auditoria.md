# SPEC-013 — Auditoria de cadastros, mudanças e logins

**Status:** approved  
**Data:** 2026-09-19  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)  
**ADR:** [ADR-0007](../architecture/ADR-0007-auditoria.md)

## Contexto

O BRIEF e o PLAN-001 listam **auditoria** no núcleo (quem criou/editou registros sensíveis e eventos de autenticação). Ainda não há trilha no banco do tenant.

## Objetivo

1. Registrar create/update/delete de entidades sensíveis do núcleo e ECC no banco do tenant.
2. Registrar login bem-sucedido, falha de login e logout.
3. Expor listagem filtrável para admins (`admin-tenant` / `admin-igreja`).
4. Tela no launcher de administração para consultar o histórico.

## Critérios de aceite (testáveis)

- [ ] Migration tenant cria tabela `audit_logs` com ULID público.
- [ ] Models auditáveis (User, Pessoa, Igreja, Casal, EccEquipe, EccEvento) geram log em create/update/delete com ator, ação, tipo/id do recurso e diff sanitizado.
- [ ] Senha e campos `$encryptable` **não** aparecem em claro no diff (marcados como alterados ou omitidos).
- [ ] `POST /web/login` sucesso → log `login_success`; falha (senha/usuário) → `login_failed`; `POST /web/logout` → `logout`.
- [ ] `GET /api/v1/audit-logs` lista paginada; filtros: `action`, `auditable_type`, `actor_user_ulid`, `desde`, `ate`, `q`.
- [ ] Sem auth → 401; sem `auditoria.view` → 403; SuperAdmin / admin-tenant / admin-igreja com permissão → 200.
- [ ] Isolamento: logs de um tenant não aparecem em outro.
- [ ] Permissões `auditoria.view` e `telas.auditoria`; sync em `admin-tenant` e `admin-igreja`.
- [ ] Front: rota `/auditoria`, item no launcher admin e sidebar; listagem com filtros básicos.

## Fora de escopo

- Auditoria de ações SuperAdmin no banco central (tenants).
- Retenção/purge automático e export CSV.
- Diff campo a campo em pivôs N:N (roles sync gera log no User quando aplicável via update de relações explícitas — sync de papéis pode logar ação `roles_synced` no User).
- Colunas denormalizadas `created_by`/`updated_by` em cada tabela (a trilha cobre “quem”).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — path `/audit-logs`.

## Notas

- Logs só no DB do tenant (ADR-0002).
- Ator: usuário autenticado Sanctum; falha de login = anônimo; SuperAdmin operando no tenant = `actor_type=super_admin`.
- IP e user-agent gravados para eventos de auth e mutações.
