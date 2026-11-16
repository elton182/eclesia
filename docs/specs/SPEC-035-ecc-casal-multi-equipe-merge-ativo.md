# SPEC-035 — ECC: casal em N equipes, unificação e ativo

**Status:** approved  
**Data:** 2026-11-16  
**Depende de:** SPEC-002, SPEC-021, SPEC-034  
**PR:** evolução no mesmo PR da revisão de import

## Contexto

Hoje cada casal tem no máximo **uma** equipe de perseverança (`casais.ecc_equipe_id`). Na prática o mesmo casal pode pertencer a mais de uma equipe; a limitação gerou **duplicatas** (mesmo casal cadastrado duas vezes, uma por equipe).

Também faltam flags de **ativo/inativo** para equipe e casal (ex.: equipe encerrada, casal afastado sem apagar histórico).

## Objetivo

1. Casal ↔ equipes de perseverança em **N:N** (pivot).
2. Funcionalidade de **unificar** dois registros de casal (mesmo pessoas físicas).
3. Campo **`ativo`** em `ecc_equipes` e em `casais` (default `true`).

## Modelo

### Pivot `ecc_casal_equipe`

| Coluna | Tipo |
|--------|------|
| casal_id | ULID FK → casais cascade |
| ecc_equipe_id | ULID FK → ecc_equipes cascade |
| unique(casal_id, ecc_equipe_id) |

Migration copia vínculos existentes de `casais.ecc_equipe_id` para o pivot.

`casais.ecc_equipe_id` permanece como **equipe principal de compatibilidade** (primeira por nome entre as vinculadas; `null` se nenhuma). Escopos antigos que leem só essa coluna continuam funcionando; listagens novas usam o pivot.

### Flags

- `ecc_equipes.ativo` boolean default true  
- `casais.ativo` boolean default true  

## API

### Casal

- Resource: `equipes: [{id, nome}]`, `equipe_ids: string[]`; mantém `equipe_id` / `equipe_nome` (principal).
- Create/update aceitam `equipe_ids: string[]` e/ou `equipe` / `equipe_id` (legado → entra no conjunto).
- `ativo` boolean no create/update.
- Listagem: por padrão inclui todos; query `ativo=1|0` filtra. Filtro por equipe usa membership no pivot.
- `POST /ecc/casais/merge`  
  Body: `{ keep_id, absorb_id }`  
  - Une equipes no casal `keep`.  
  - Preenche campos vazios de `keep` com dados de `absorb`.  
  - Reaponta FKs de eventos/doações que apontavam para `absorb`.  
  - Remove `absorb` (pessoas + casal).  
  - Requer `ecc.casais.manage`.

### Equipe

- Resource/create/update: `ativo`.
- `PUT /ecc/equipes/{id}/casais`: **anexa** os `casal_ids` à equipe e **remove** da equipe quem saiu da lista — **sem** tirar o casal de outras equipes.
- Listagem de equipes: query `ativo=1|0` opcional.

### Import (SPEC-034)

Ao aplicar campo `equipe` / sync de equipes no update parcial ou create: **adiciona** a equipe ao pivot (não remove as demais).

## Critérios de aceite (testáveis)

- [ ] Migration tenant: pivot + `ativo` em equipes e casais; backfill do pivot.
- [ ] Casal pode pertencer a 2+ equipes; resource lista todas.
- [ ] syncCasais não remove o casal de outra equipe.
- [ ] Merge une equipes, preserva `keep`, apaga `absorb`, reaponta FKs sem violar unique de participantes.
- [ ] `ativo` em casal e equipe gravável e filtrável.
- [ ] 403 sem manage no merge; 422 se ids iguais / outra igreja / inexistente.
- [ ] Front: multi-select de equipes no casal; unificar na lista; toggles ativo; filtro de inativos.
- [ ] Testes API + utils front.

## Fora de escopo

- Merge automático em lote só por nome (só ação explícita keep/absorb).
- Soft-delete / histórico de unificações.
- Equipes de serviço (catálogo SPEC-015) — inalterado.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — `EccCasal`, `EccEquipe`, `POST /ecc/casais/merge`.

## Notas

- Matching de import continua por nomes; duplicatas históricas se resolvem com merge manual.
- Líder de equipe continua vendo casais cujo pivot intersecta as equipes que lidera.
