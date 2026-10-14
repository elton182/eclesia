# SPEC-019 — Simular visão de líder de equipe

**Status:** draft
**Data:** 2026-10-14
**Depende de:** SPEC-003, SPEC-017

## Contexto

Admin da organização ou da igreja precisa validar a experiência do papel `lider-equipe` (home, escopo de equipes/casais e atualização de ficha) sem impersonar outro usuário nem criar conta de teste.

## Objetivo

1. `admin-tenant` e `admin-igreja` ativam simulação escolhendo 1..N equipes da igreja atual.
2. Enquanto ativa, API e front comportam-se como líder exclusivo daquelas equipes (incluindo `ecc.casais.atualizar`).
3. Identidade e auditoria permanecem do admin real.
4. Saída explícita ou logout encerram a simulação; troca de igreja com equipes inválidas limpa o estado.

## Critérios de aceite (testáveis)

- [x] `POST /web/view-as/lider-equipe` com `equipe_ids` (min 1, da igreja atual) por `admin-tenant` / `admin-igreja` → 200 com payload de `/me` mascarado + `view_as`.
- [x] Mesmo endpoint sem papel admin → 403; `equipe_ids` vazio ou de outra igreja → 422.
- [x] Com simulação ativa: listagem de equipes/casais escopada; `PUT` ficha na equipe OK; casal de outra equipe → 404; `POST`/`DELETE`/import/swap de casal → 403.
- [x] `POST /web/me` com simulação ativa devolve `roles` = `lider-equipe`, permissões do seed do líder, `equipes_lideradas` das equipes escolhidas e `view_as.mode` = `lider-equipe`.
- [x] `DELETE /web/view-as` restaura papéis/permissões reais e `view_as` nulo.
- [x] `POST /web/logout` limpa o estado de simulação.
- [x] Troca de igreja (`X-Igreja`) cujo id não coincide com o da simulação limpa o estado (próximo `/me` sem máscara).
- [x] Front: entrada no launcher (Administração) “Simular líder de equipe”; modal multi-select; após ativar, vai para `/ecc/minha-equipe` com banner e botão Sair.

## Fora de escopo

- Impersonar usuário líder específico.
- Simulação de outros papéis.
- Persistência em banco / Stancl `UserImpersonation`.
- SuperAdmin de plataforma.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — `POST /web/view-as/lider-equipe`, `DELETE /web/view-as`, campo `view_as` em `/web/me`.

## Notas

- Estado em Cache: `view_as_lider:{tenantId}:{userId}` com `{ igreja_id, equipe_ids }`.
- Máscara efetiva = permissões do seed `lider-equipe` (`telas.equipes`, `telas.casais`, `ecc.equipes.view`, `ecc.casais.view`, `ecc.casais.atualizar`).
- `EccVisibilityScope` respeita a simulação antes do atalho de `admin-tenant` / manage.
