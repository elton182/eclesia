# SPEC-037 — ECC: sugerir valor mais completo na unificação

**Status:** approved  
**Data:** 2026-11-18  
**Depende de:** SPEC-036  
**Plano:** `docs/plans/PLAN-005-ecc-casal-merge-mais-completo.md`

## Contexto

A revisão de unificação (SPEC-036) sugere `apply_fields` só quando o **mantido está vazio** e o absorvido tem valor. Quando os dois lados têm conteúdo diferente, a secretaria precisa escolher manualmente — mesmo quando um valor é claramente mais rico (telefone com DDD, endereço completo, lista com mais itens).

Decisões de produto (2026-11-18):

1. **A** — melhorar a tela de comparação (sugerir automaticamente o valor mais completo), não listar duplicatas nesta fatia.
2. **Mais informação** — texto/lista com mais conteúdo (comprimento, dígitos, quantidade de itens); revisão manual permanece (só pré-marca).

## Objetivo

1. No `POST /ecc/casais/merge/preview`, quando ambos os lados têm valor distinto, sugerir aplicar o absorvido **somente se** ele for mais completo que o mantido.
2. Lacunas continuam sugeridas (mantido vazio + absorvido preenchido → sugerir).
3. Empate de completude → **não** sugerir (fica o mantido).
4. Front: pré-marcar `suggested_apply_fields` (já faz); copy deixa claro que a sugestão é o valor mais completo.

## Heurística de completude

Score ≥ 0; vazio = 0. Absorvido sugerido se `score(absorb) > score(keep)`.

| Tipo de campo | Score |
|---------------|-------|
| Listas (`etapas`, `atividades`, `preferencias`) | quantidade de itens |
| Telefone / CEP (`telefone`, `telefone_conjuge`, `cep`) | quantidade de dígitos |
| Demais escalares (texto, datas, números, bool) | comprimento em caracteres da forma normalizada (bool/`anos_casados`: representação string) |

Equipe permanece fora do diff (união no pivot).

## Critérios de aceite (testáveis)

- [x] Preview: mantido vazio + absorvido preenchido → campo em `suggested_apply_fields`.
- [x] Preview: absorvido com texto/telefone/lista **mais completo** → sugerido; mantido mais completo ou empate → não sugerido.
- [x] Merge com `apply_fields` inalterado (só aplica o marcado).
- [x] Front: copy da revisão menciona sugestão do valor mais completo; checkboxes seguem `suggested_apply_fields`.
- [x] Testes API + util/front.

## Fora de escopo

- Detecção/listagem de possíveis duplicatas (decisão B — fatia futura).
- Merge automático sem revisão.
- Alterar sugestão da **importação** (SPEC-034 permanece “só lacunas”).

## Contrato de API

Mesmos endpoints da SPEC-036. Semântica de `suggest_apply` / `suggested_apply_fields` ampliada conforme heurística acima. Ver `api/docs/specs/openapi.yaml`.

## Notas

- A confirmação explícita continua obrigatória; a heurística só pré-seleciona.
