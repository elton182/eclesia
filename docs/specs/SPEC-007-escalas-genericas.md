# SPEC-007 — Escalas genéricas (núcleo)

**Status:** approved  
**Data:** 2026-09-16  
**Depende de:** SPEC-004 (igreja), núcleo Pessoa/Casal  
**Plano:** esboço Escalas genéricas (núcleo)  
**ADR:** [ADR-0005](../architecture/ADR-0005-escalas-nucleo.md)

## Contexto

A igreja precisa montar escalas de **qualquer coisa** (liturgia, coroinhas, porteiros, limpeza…) com agenda em lista ou calendário. Isso **não** pertence ao ECC: o ECC poderá consumir o mesmo motor depois (sugestão 3 camadas fica fora desta fatia).

## Objetivo

No banco do tenant, escopado por `igreja_id`:

1. CRUD de **tipos de escala** (ex.: “Liturgia semanal”).
2. CRUD de **equipes/funções** dentro do tipo (ex.: Leitura, Acolhida).
3. CRUD de **ocorrências** (data/hora/local) e publicação em **EventoAgenda**.
4. **Atribuições** pessoa avulsa **ou** casal por equipe/ocorrência; múltiplos papéis na mesma ocorrência permitidos.
5. **Agenda** (lista + filtro por período/tipo) para calendário.
6. Telas no front: módulo Escalas no launcher (lista de tipos, detalhe, montagem, agenda).

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| TipoEscala | `escala_tipos` | `unidade_preferida`: `pessoa` \| `casal` \| `ambos`; `recorrencia`: `avulsa` \| `semanal` \| `mensal` (MVP: metadado; ocorrências criadas uma a uma ou lote simples) |
| EquipeEscala | `escala_equipes` | Papel/função do tipo; cor, ordem, `vagas_sugeridas` opcional |
| OcorrenciaEscala | `escala_ocorrencias` | `inicia_em`, `termina_em`, `local`; gera `evento_agenda` |
| AtribuicaoEscala | `escala_atribuicoes` | `pessoa_id` XOR `casal_id` + `escala_equipe_id` |
| EventoAgenda | `evento_agenda` | Agenda unificada; `dono_modulo=escalas`, `tipo=escala` |

Sem tabelas `ecc_*`. Pessoa e Casal do núcleo.

## Regras

1. Isolamento por `igreja_id` (IgrejaContext / `X-Igreja`).
2. Atribuição exige exatamente um de `pessoa_id` ou `casal_id` (422 se ambos/nenhum).
3. Mesma pessoa/casal em N equipes na mesma ocorrência: **permitido** (sem bloqueio; UI pode sinalizar).
4. Duplicata mesma pessoa/casal na **mesma** equipe+ocorrência: **422**.
5. Ao criar/atualizar/excluir ocorrência: sincronizar `evento_agenda` (cascade delete da ocorrência remove o evento).
6. Permissões: `telas.escalas`, `escalas.view`, `escalas.manage`.

## Critérios de aceite (testáveis)

- [x] Migrations tenant: `escala_tipos`, `escala_equipes`, `escala_ocorrencias`, `escala_atribuicoes`, `evento_agenda`.
- [x] CRUD tipos/equipes/ocorrências/atribuições sob `/api/v1/escalas/...` com `X-Tenant` (+ `X-Igreja`).
- [x] `GET /api/v1/escalas/agenda` filtra por `from`/`to`/`tipo_id`.
- [x] 401 sem auth; 403 sem permissão; isolamento por igreja/tenant.
- [x] Seed: permissões `telas.escalas`, `escalas.view`, `escalas.manage` em `admin-tenant` / `admin-igreja`.
- [x] Front: cartão Escalas no launcher; rotas `/escalas/*` com ModuleLayout; lista, detalhe/montagem, agenda lista\|calendário.
- [x] Testes API (Pest/PHPUnit) + teste utilitário front (permissão/nav).

## Fora de escopo

- Sugestão inteligente ECC (preferência/histórico/restrição).
- Pool de membros por equipe (MVP+).
- RRULE completo; notificações push; módulo Pastorais.
- Integração ECC como consumidor (spec futura).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/escalas/*`.

## Notas

- ULIDs em IDs públicos.
- Recorrência MVP: `POST .../ocorrencias/gerar` com `inicio`, `fim`, `frequencia` (`semanal`\|`mensal`) gera N ocorrências; ou criar avulsa.
