# SPEC-024 — ECC: alterar login (username) do acesso da equipe

**Status:** approved  
**Data:** 2026-10-23  
**Depende de:** SPEC-021  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

SPEC-021 cria o acesso compartilhado da equipe com username derivado do nome e permite resetar a senha. Renomear a equipe **não** altera o username (proposital). Falta um fluxo explícito para o gestor trocar o login quando o username gerado não serve ou a liderança operacional precisa de outro identificador.

## Objetivo

Permitir que quem tem `ecc.equipes.manage` altere o `username` do usuário de acesso (`acesso_user_id`) de uma equipe, sem resetar a senha automaticamente.

## Critérios de aceite (testáveis)

- [x] `PUT /ecc/equipes/{id}/acesso` com body `{ "username": "..." }` exige `ecc.equipes.manage`.
- [x] Sem `acesso_user_id` → 422.
- [x] Username é normalizado (slug: minúsculas, hífens); vazio após slug → 422.
- [x] Username único no tenant (exceto o próprio usuário de acesso); colisão → 422.
- [x] Atualiza `users.username` e o e-mail sintético `{username}.{igreja_curto}@equipe.local`.
- [x] Revoga tokens Sanctum do usuário de acesso (força novo login com o login novo).
- [x] **Não** altera `must_change_password` nem a senha.
- [x] Resposta inclui `username`, `tem_acesso`, `acesso_username` e `equipe` (resource).
- [x] List/show refletem o novo `acesso_username`.
- [x] Front em `/ecc/equipes`: ação “Alterar login” quando `tem_acesso`, via prompt; sucesso atualiza a lista.
- [x] 401 sem auth; 403 sem permissão; isolamento por tenant.

## Fora de escopo

- Reset de senha (continua em `POST .../acesso/reset-senha`).
- Alterar username ao renomear a equipe (continua proibido — SPEC-021).
- Esqueci minha senha / self-service do líder.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — path `PUT /ecc/equipes/{id}/acesso`.

## Notas

- Login principal continua sendo o username (e-mail sintético só para UNIQUE + LGPD).
- Formato esperado: `regex`/`alpha_dash` após slugify (ex. `equipe-a`, `cana-2`).
