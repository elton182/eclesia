# SPEC-033 — ECC: dashboard de cadastros e acessos

**Status:** approved  
**Data:** 2026-11-12  
**Depende de:** SPEC-002, SPEC-013, SPEC-021

## Contexto

Coordenação precisa acompanhar se as equipes estão entrando no sistema e se as fichas dos casais estão sendo atualizadas. A trilha de auditoria (SPEC-013) e as contas de acesso das equipes (SPEC-021) já existem, mas não há visão agregada no módulo ECC.

## Objetivo

1. Endpoint agregado `GET /ecc/relatorios/cadastros-acessos` com resumo, acessos por equipe, quem atualizou fichas e atualizações recentes.
2. Denormalizar `users.last_login_at` no login bem-sucedido (com fallback histórico via `audit_logs`).
3. Tela no ECC (`/ecc/relatorios/cadastros-acessos`) para admins/cadastros — não para líder de equipe.
4. Permissões `ecc.relatorios.view` e `telas.relatorios`.

## Modelo

| Peça | Notas |
|------|-------|
| `users.last_login_at` | timestamp nullable; gravado em `login_success` |
| Agregação | `audit_logs` (`updated`/`created` em `Casal`) + `ecc_equipes.acesso_user_id` + `last_login_at` |

## Resposta (visão)

- **resumo:** totais de casais/equipes, atualizados na janela, sem acesso, nunca acessaram, inativos na janela.
- **equipes_acessos:** por equipe — `tem_acesso`, username, `ultimo_acesso_em`, `status_acesso` (`ativo` \| `inativo` \| `nunca_acessou` \| `sem_acesso`), contagem de casais.
- **quem_atualizou:** atores com contagem de updates de casal na janela + última data.
- **atualizacoes_recentes:** últimos eventos create/update de casal (ator, casal, quando).
- **casais_estagnados:** casais sem update desde antes da janela (ou só `created_at`), limitados.

Query: `janela_dias` (default 30, min 7, max 365).

## Permissões

- `ecc.relatorios.view` + `telas.relatorios`
- Sync em `admin-tenant`, `admin-igreja`, `cadastros-casais`, `cadastros-equipes`
- Líder de equipe → 403

## Critérios de aceite (testáveis)

- [x] Migration tenant: `users.last_login_at` + sync de permissões.
- [x] Login sucesso grava `last_login_at`.
- [x] `GET /ecc/relatorios/cadastros-acessos` retorna estrutura acima; 401/403; isolamento por igreja/tenant.
- [x] Equipe com acesso e login recente → `status_acesso=ativo`; sem login → `nunca_acessou`; sem conta → `sem_acesso`.
- [x] Front: rota, item de nav ECC, dashboard com resumo + tabelas; Vitest dos helpers.
- [x] Testes API Feature cobrindo auth, permissão e payload.

## Fora de escopo

- Export CSV/PDF do relatório.
- Push/e-mail de inatividade.
- Diff campo a campo na tela (usar Auditoria genérica).
- Visão do líder sobre a própria equipe nesta tela.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — path `/ecc/relatorios/cadastros-acessos`.
