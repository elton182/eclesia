# SPEC-021 — ECC: gestão de equipes e acesso da equipe

**Status:** approved  
**Data:** 2026-10-19  
**Depende de:** SPEC-002, SPEC-003  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

A gestão de equipes de perseverança ficou secundária em Casais (`/ecc/equipes` redirecionava). Falta tela dedicada, sincronização em lote de casais (modal dual-panel) e conta compartilhada da equipe (username + senha temporária) para troca de liderança operacional, com troca obrigatória no primeiro acesso.

## Objetivo

1. Tela dedicada `/ecc/equipes` (CRUD nome/cor + membros + acesso).
2. `PUT /ecc/equipes/{id}/casais` para definir o conjunto de casais da equipe.
3. Conta de **Acesso da Equipe**: criar (username derivado do nome + senha temporária) ou resetar senha se já existir.
4. Login por **e-mail ou username**; flag `must_change_password` com endpoint de troca e bloqueio das demais rotas até trocar.

## Critérios de aceite (testáveis)

- [ ] Migration tenant: `users.username` (nullable unique), `users.must_change_password`; `ecc_equipes.acesso_user_id` FK nullable.
- [ ] `PUT /ecc/equipes/{id}/casais` com `casal_ids[]`: listados ficam na equipe; quem saiu fica `ecc_equipe_id = null`. Requer `ecc.equipes.manage`.
- [x] `POST /ecc/equipes/{id}/acesso`: cria user (`name` = nome da equipe, `username` = slug único, e-mail sintético), senha inicial `1234`, `must_change_password=true`, papel `lider-equipe` + vínculo N:M, grava `acesso_user_id`. Retorna `username` + `password` uma vez. Se já tem acesso → 422.
- [x] `POST /ecc/equipes/{id}/acesso/reset-senha`: senha volta para `1234` + `must_change_password=true` + revoga tokens. Sem acesso → 422.
- [ ] List/show equipe inclui `tem_acesso` e `acesso_username` (sem senha).
- [ ] Renomear equipe **não** altera `username` já criado.
- [ ] `POST /web/login` aceita `login` (e-mail ou username); `email` permanece como alias.
- [ ] `GET`/`POST /web/me` expõe `must_change_password`.
- [ ] `POST /web/change-password` limpa a flag; com flag ativa demais rotas autenticadas → 403 `must_change_password` (exceto me, change-password, logout, refresh).
- [ ] Front: `/ecc/equipes` com lista, form, modal dual-panel de casais, botão Acesso da Equipe; login “E-mail ou usuário”; tela de troca de senha no primeiro acesso.
- [ ] 401 sem auth; 403 sem permissão; isolamento por tenant.

## Fora de escopo

- Equipes de serviço / escala.
- Esqueci minha senha self-service.
- Renomear username ao editar nome da equipe (alteração explícita: SPEC-024).
- Remover líderes pessoais cadastrados em Usuários (continuam válidos).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/ecc/equipes*`, `/web/login`, `/web/me`, `/web/change-password`.

## Notas

- Unidade de membro = **casal** (`ecc_equipe_id`).
- Username: slug do nome (ex. `EQUIPE A` → `equipe-a`); colisão → sufixo numérico.
- E-mail sintético interno `{username}.{igreja_curto}@equipe.local` só para UNIQUE + LGPD; login principal do acesso é o username.
- Conta compartilhada (`acesso_user_id`) é distinta de vínculos extras em `ecc_equipe_user`.
