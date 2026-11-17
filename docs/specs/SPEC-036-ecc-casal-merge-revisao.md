# SPEC-036 — ECC: unificação de casais com revisão seletiva

**Status:** approved  
**Data:** 2026-11-17  
**Depende de:** SPEC-035, SPEC-034  
**Plano:** evolução do merge manual de duplicatas

## Contexto

Hoje `POST /ecc/casais/merge` unifica dois casais de imediato: une equipes, preenche lacunas do mantido com dados do absorvido e remove o segundo registro. A secretaria não vê as diferenças nem escolhe, campo a campo, o que permanece — ao contrário da importação com revisão (SPEC-034).

## Objetivo

1. Pré-visualizar a unificação: resumo dos dois casais, união de equipes e lista de campos divergentes.
2. Permitir escolher qual registro **permanece** (`keep`) e qual é **absorvido**.
3. Em cada diferença, marcar se o valor do absorvido deve sobrescrever o mantido (como “usar planilha” na importação).
4. Só gravar após confirmação explícita.

## Preview

- `POST /ecc/casais/merge/preview` com `{ keep_id, absorb_id }`.
- Escopo: casais da igreja atual; ambos devem existir e ser distintos.
- Diffs: mesmos campos comparáveis da importação, **exceto** `equipe` (equipes sempre em união no pivot).
- Sugestão inicial de `apply_fields`: mantido vazio e absorvido preenchido → sugerir aplicar; ambos preenchidos e diferentes → não sugerir.

## Commit

- `POST /ecc/casais/merge` aceita `apply_fields: string[]` opcional.
  - Com `apply_fields` presente (inclui `[]`): aplica só esses campos a partir do absorvido; **não** preenche lacunas automaticamente além do marcado.
  - Sem `apply_fields`: mantém comportamento legado (preenche lacunas vazias do mantido).
- Sempre: une equipes, reaponta FKs, remove o absorvido (pessoas + casal).

## Critérios de aceite (testáveis)

- [ ] Preview devolve keep/absorb (rótulo + equipes), `equipes_union`, `field_diffs`, `suggested_apply_fields`.
- [ ] Diff lista só campos com valor distinto; `equipe` fora da lista.
- [ ] Merge com `apply_fields` sobrescreve só os marcados; demais campos do keep permanecem.
- [ ] Merge sem `apply_fields` continua preenchendo lacunas (compat).
- [ ] 401/403/422; ids iguais ou inexistentes → 422.
- [ ] Front: ao unificar, abre revisão; usuário escolhe keep e campos; confirma; lista atualiza.
- [ ] Testes API + utils front.

## Fora de escopo

- Merge em lote automático.
- Soft-delete / histórico de unificações.
- Escolha de fotos campo a campo (fica com o keep; lacunas de foto não entram no diff).

## Contrato de API

Ver `api/docs/specs/openapi.yaml`:

- `POST /ecc/casais/merge/preview`
- `POST /ecc/casais/merge` (com `apply_fields` opcional)
