# SPEC-026 — Usuários: login com username sem e-mail

**Status:** approved  
**Data:** 2026-10-23  
**Depende de:** SPEC-003, SPEC-021

## Contexto

O CRUD de usuários do tenant (`POST/PATCH /users`) exige e-mail. Já existe login por username (SPEC-021) e contas de equipe usam e-mail sintético. Falta permitir criar usuários “normais” só com login (username), sem e-mail real — comum para monitores, secretaria operacional e acessos compartilhados que não são equipe ECC.

## Objetivo

Permitir criar e atualizar usuários do tenant com **e-mail e/ou username**, exigindo ao menos um dos dois. O login web continua aceitando e-mail ou username.

## Critérios de aceite (testáveis)

- [x] `POST /users` exige `name`, `password` e **ao menos um** de `email` | `username`.
- [x] Com só `username`: grava username normalizado (slug), e-mail sintético `{username}@login.local` (UNIQUE + LGPD); resposta devolve `email: null` e `username`.
- [x] Com só `email`: comportamento atual (`username` null); resposta devolve o e-mail.
- [x] Com ambos: grava os dois; login funciona por qualquer um.
- [x] Username duplicado ou e-mail duplicado → 422.
- [x] Username inválido (vazio após normalização) → 422.
- [x] `PATCH /users/{id}` aceita `username` e `email` opcionais; não permite ficar sem identificador de login (sem username e sem e-mail real).
- [x] Ao alterar username de usuário com e-mail sintético `@login.local`, o e-mail sintético acompanha.
- [x] `POST /web/login` com `login` = username do usuário criado sem e-mail autentica.
- [x] Front `/usuarios`: e-mail não obrigatório; campo login/username; lista mostra username ou e-mail.
- [x] `GET /users` paginado (`page`, `per_page`) com busca `q` (nome, e-mail real ou username); front lista com paginação e busca no servidor.

## Fora de escopo

- Tornar a coluna `users.email` nullable no banco (permanece preenchida com sintético quando não há e-mail real).
- Alterar fluxo de acesso da equipe ECC (continua `@equipe.local` — SPEC-021/024).
- Recuperação de senha por e-mail para contas só-username.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — `User`, `POST /users`, `PATCH /users/{id}`.

## Notas

- Domínios sintéticos: `@login.local` (CRUD usuários) e `@equipe.local` (acesso equipe). A API omite esses valores no campo `email` da resource (retorna `null`).
- Normalização de username: slug minúsculo (letras, números, hífens), máx. 120 — alinhado a SPEC-021/024.
