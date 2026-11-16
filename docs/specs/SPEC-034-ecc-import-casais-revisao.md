# SPEC-034 — ECC: importação de casais com revisão seletiva

**Status:** approved  
**Data:** 2026-11-16  
**Depende de:** SPEC-002, SPEC-015  
**Plano:** evolução do import Excel do módulo ECC

## Contexto

Hoje `POST /ecc/casais/import` **cria** casais a partir da planilha (EQUIPES ATIVAS / modelo detalhado). Reimportar a mesma planilha gera duplicatas; não há comparação com o cadastro já existente no sistema, que pode estar mais atualizado que a planilha.

A secretaria precisa **carregar uma planilha nova**, ver o que bate com o cadastro e **escolher** o que sobrescrever e o que manter.

## Objetivo

1. Pré-visualizar o upload: cada linha classificada como **novo**, **match**, **idêntico**, **ambíguo** ou **inválido**.
2. Em matches com diferenças, listar campos divergentes (sistema × planilha).
3. Confirmar só as ações escolhidas (criar novos; atualizar com campos marcados; pular).
4. Manter o endpoint legado `POST /ecc/casais/import` (cria sempre) para compatibilidade/testes; a UI de Casais passa a usar o fluxo de revisão.

## Matching

- Escopo: casais da **igreja atual** do tenant.
- Chave: nomes dos cônjuges normalizados (minúsculas, sem acento, só alfanumérico), **ordem indiferente** (`João|Maria` = `Maria|João`).
- **1 candidato** → `match` (ou `identical` se não houver diff).
- **2+ candidatos** → `ambiguous` (usuário escolhe o casal ou pula).
- **0 candidatos** → `new`.
- Linha sem nomes válidos → `invalid` (não commitável como create/update).

## Diffs e defaults na UI

Campos escalares do mapeamento de import + blocos `etapas` / `atividades` / `preferencias` (quando a planilha trouxer).

Sugestão inicial de `apply_fields` no preview:

- Sistema **vazio** e planilha **preenchida** → sugerir aplicar (preencher lacuna).
- Ambos preenchidos e **diferentes** → **não** sugerir (mantém sistema até o usuário marcar).
- Iguais → fora da lista de diffs.

Ação sugerida:

| status | suggested_action |
|--------|------------------|
| new | `create` |
| identical | `skip` |
| match | `update` |
| ambiguous | `skip` |
| invalid | `skip` |

## Critérios de aceite (testáveis)

- [x] `POST /ecc/casais/import/preview` com `rows` devolve `items[]` + `summary` (contagens por status).
- [x] Match por nomes normalizados (ordem indiferente); ambíguo quando há duplicata no banco.
- [x] Diff lista só campos com valor distinto; booleans/datas normalizados.
- [x] `POST /ecc/casais/import/commit` aplica decisões: `create` / `update` (+ `casal_id` + `apply_fields`) / `skip`.
- [x] Update parcial: só campos em `apply_fields` sobrescrevem o cadastro; demais permanecem.
- [x] Create na commit não duplica quando a decisão for `skip` em match.
- [x] 401 sem auth; 403 sem `ecc.casais.manage`; 422 validação.
- [x] Front `/ecc/casais`: upload abre tela de revisão; usuário confirma; lista atualiza.
- [x] Testes API (Pest/PHPUnit) + util Vitest/node no front.

## Fora de escopo

- Matching por telefone/e-mail como chave primária (pode enriquecer depois).
- Merge inteligente de arrays (etapas/atividades): aplicar = substituir o bloco inteiro.
- Import financeiro (SPEC-016).
- Desfazer (rollback) após commit.

## Contrato de API

Ver `api/docs/specs/openapi.yaml`:

- `POST /ecc/casais/import/preview`
- `POST /ecc/casais/import/commit`
- Legado: `POST /ecc/casais/import`

## Notas

- Stateless: o commit reenvia as mesmas `rows` da planilha + `decisions[]` (sem sessão server-side).
- PII continua criptografada nos models; preview/commit só expõem o que o Resource/ficha já expõem a quem tem `ecc.casais.manage`.
