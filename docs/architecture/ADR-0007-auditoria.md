# ADR-0007 — Auditoria no banco do tenant

**Status:** accepted  
**Data:** 2026-09-19  
**Spec:** [SPEC-013](../specs/SPEC-013-auditoria.md)

## Contexto

Precisamos saber quem criou/editou/excluiu cadastros e quem fez login/logout, sem vazar PII em claro e sem misturar tenants.

## Decisão

1. **Tabela `audit_logs` no banco do tenant** (não no central). Isolamento = o próprio banco.
2. **Implementação própria** (service + trait Eloquent) — sem pacote externo de activity log.
3. **Trait `AuditsActivity`** nos models sensíveis; observer interno em `created`/`updated`/`deleted`.
4. **Sanitização LGPD:** nunca gravar `password`/`remember_token`; atributos em `$encryptable` (ou lista `$auditHidden`) entram no diff só como `"alterado"` / `"removido"`, sem valor.
5. **Auth:** `AuditLogger` chamado em login sucesso/falha e logout.
6. **Consulta:** `GET /api/v1/audit-logs` com permissão `auditoria.view`; UI com `telas.auditoria`.
7. **Sem** `created_by`/`updated_by` nas tabelas de domínio nesta fase — a trilha responde “quem”.

## Consequências

- Novos models sensíveis devem usar `AuditsActivity` (ou registrar ação manual via `AuditLogger`).
- Volume de logs cresce com o uso; retenção fica para fase futura.
- SuperAdmin no tenant aparece como `actor_type=super_admin` com label do e-mail da plataforma (sem PII do tenant).
