# SPEC-015 — ECC: ficha enriquecida do casal (pessoa, etapas, serviço)

**Status:** approved  
**Data:** 2026-09-20  
**Depende de:** SPEC-002  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

O cadastro de casal no ECC cobre contato básico, endereço, perfil (`ecc_origem`, `funcao_dirigente`…) e vínculo com equipe de **perseverança** (`ecc_equipes`), além da ficha estruturada (etapas, atividades, preferências).

O movimento precisa de uma ficha mais completa: dados opcionais por cônjuge, etapas estruturadas (nº do ECC + data + local), textos de engajamento/habilidades, **histórico de equipes de trabalho** por encontro e **preferências** de equipe — base também para a futura sugestão de escala (BRIEF).

## Objetivo

No banco do tenant, escopado por `igreja_id`:

1. Ampliar `Pessoa` com campos opcionais por cônjuge.
2. Estruturar as **etapas** do casal (1ª / 2ª / 3ª) com nº do ECC, data e local.
3. Campos livres no casal: engajamento paroquial e habilidades.
4. Catálogo de **equipes de serviço** (padrão seedável + customizáveis).
5. Lista de **histórico de atividades** (casal × nº ECC × equipe × status).
6. Lista de **preferências** de equipe do casal.
7. Expor tudo no CRUD de casais (API + formulário/ficha no front).

## Modelo de dados

### Pessoa — campos novos (opcionais)

| Campo | Tipo | LGPD |
|-------|------|------|
| `nome_usual` | TEXT | `$encryptable` (apelido) |
| `profissao` | TEXT | `$encryptable` |
| `religiao` | TEXT | `$encryptable` |
| `endereco_profissional` | TEXT | `$encryptable` |
| `telefone_profissional` | TEXT | `$encryptable` |

O telefone pessoal (`telefone`) permanece; o profissional é separado.

### Casal — campos novos (opcionais)

| Campo | Tipo | Notas |
|-------|------|--------|
| `engajamento_paroquial` | TEXT | Campo livre; `$encryptable` |
| `habilidades` | TEXT | Campo livre; `$encryptable` |

### Etapas do casal — tabela `ecc_casal_etapas`

| Campo | Tipo | Notas |
|-------|------|--------|
| `casal_id` | ULID FK | |
| `etapa` | tinyint | `1`, `2` ou `3` |
| `ecc_numero` | string(20) nullable | Ex.: `34`, `12º` |
| `data` | date nullable | |
| `local` | string(255) nullable | |
| unique | `(casal_id, etapa)` | |

Unique `(casal_id, etapa)`. Fonte estruturada das etapas 1–3 (nº do ECC, data, local).

### Equipes de serviço — tabela `ecc_equipes_servico`

Catálogo **distinto** de `ecc_equipes` (perseverança). Usado em histórico, preferências e (futuro) escala.

| Campo | Tipo |
|-------|------|
| `igreja_id` | FK |
| `nome` | string |
| `slug` | string | unique por igreja |
| `ordem` | int | |
| `ativo` | bool | |

**Seed padrão por igreja** (ordem sugerida):

1. Coordenação Geral  
2. Sala  
3. Liturgia/Vigília  
4. Círculos  
5. Café e Minimercado  
6. Cozinha  
7. Ordem e Limpeza  
8. Visitação  
9. Acolhida  
10. Secretaria  
11. Compras  
12. Palestras  

Permitir criar/renomear equipes adicionais da igreja (não só as 12).

### Histórico de atividades — tabela `ecc_casal_atividades`

Registro: “serviu no ECC nº N na equipe X com status S”.

| Campo | Tipo | Notas |
|-------|------|--------|
| `casal_id` | ULID FK | |
| `ecc_numero` | string(20) | Ex.: `34` |
| `ecc_equipe_servico_id` | FK | |
| `status` | string(5) | ver enum |
| `observacao` | text nullable | opcional |
| unique | `(casal_id, ecc_numero, ecc_equipe_servico_id)` | |

**Status (enum):**

| Código | Significado |
|--------|-------------|
| `A` | Aceitou |
| `IC` | Indicado para coordenação |
| `C` | Coordenou |
| `N` | Não aceitou |
| `NA` | Não aceita *(esta indicação)* |
| `NN` | Não aceita equipe de trabalho |

### Preferências — tabela `ecc_casal_preferencias`

| Campo | Tipo | Notas |
|-------|------|--------|
| `casal_id` | ULID FK | |
| `ecc_equipe_servico_id` | FK | |
| `ordem` | int nullable | 1 = mais preferida |
| unique | `(casal_id, ecc_equipe_servico_id)` | |

Lista ordenada de equipes preferidas (sem status).

## Campos removidos (legado)

Colunas `experiencia_servico`, `preferencia_funcao`, `etapa_2` e `etapa_3` foram **removidas**. Fonte única: `etapas[]`, `atividades[]`, `preferencias[]`.

## Critérios de aceite (testáveis)

- [x] Migration tenant: colunas em `pessoas`; `engajamento_paroquial` / `habilidades` em `casais`; tabelas `ecc_equipes_servico`, `ecc_casal_etapas`, `ecc_casal_atividades`, `ecc_casal_preferencias`.
- [x] Seed (ou provisionamento ao criar igreja) cria as 12 equipes de serviço padrão.
- [x] PII novos em `$encryptable` + colunas TEXT.
- [x] CRUD casal aceita nested `ele`/`ela` (ou pessoa A/B) com campos novos; `etapas[]`, `atividades[]`, `preferencias[]` (sync no update).
- [x] Validação: status só nos códigos do enum; etapa ∈ {1,2,3}; FKs de equipe de serviço da mesma igreja.
- [x] GET casal retorna pessoas enriquecidas + etapas + atividades + preferências.
- [x] Endpoint (ou include) listar equipes de serviço ativas da igreja.
- [x] Front: formulário de casal e ficha `/ecc/casais/:id` exibem os blocos (Ele/Ela extras, etapas, engajamento, habilidades, histórico, preferências).
- [x] Testes API (401/403/422/200 + isolamento) e Vitest no front para render/helpers dos novos blocos.
- [x] OpenAPI atualizado antes da implementação.

## Fora de escopo

- Escala automática / sugestão em 3 camadas (só prepara dados).
- Vincular atividade a `EccEvento` concreto (nº do ECC é texto livre nesta fatia; importação usa `hist` / ano `2023`–`2025`).
- CRUD completo de gestão de equipes de serviço além do necessário ao seed + listagem.

## Importação Excel (planilha EQUIPES ATIVAS)

Além dos campos já mapeados (SPEC-002), a importação enriquece:

| Coluna planilha | Destino estruturado |
|-----------------|---------------------|
| Qual ECC vocês fizeram? | `etapas[]` etapa 1 + `ecc_origem` |
| Tem 2ª / 3ª Etapa | `etapas[]` (ignora “Não”) |
| Já trabalharam no encontro… | `atividades[]` (`ecc_numero=hist`, status C/A) |
| Em qual função gostaria… | `preferencias[]` ordenadas |
| 2023 CONVITE PARA + ACEITOU? | `atividades[]` ano `2023` |
| Indicação 2024 + Função + Aceitou | `atividades[]` ano `2024` |
| Indicação 2025 + Função_1 + Aceitou_1 | `atividades[]` ano `2025` |

Heurística: aliases de nomes de equipe (Cozinha, Café, Vigília…) → catálogo `ecc_equipes_servico`. Texto livre ilegível não bloqueia a importação.
## Contrato de API

Ver `api/docs/specs/openapi.yaml` — `EccCasal`, `PessoaResumo`, `EccEquipeServico`, paths `/ecc/casais`, `/ecc/equipes-servico`.

Payload ilustrativo (create/update):

```json
{
  "nome": "João",
  "nome_conjuge": "Maria",
  "ele": {
    "nome_usual": "Jão",
    "profissao": "Engenheiro",
    "religiao": "Católica",
    "endereco_profissional": "Av. X, 10",
    "telefone_profissional": "1199…"
  },
  "ela": { "…": "…" },
  "engajamento_paroquial": "Catequista, conselho…",
  "habilidades": "Música, cozinha…",
  "etapas": [
    { "etapa": 1, "ecc_numero": "12", "data": "2018-05-12", "local": "Paróquia São José" },
    { "etapa": 2, "ecc_numero": "20", "data": "2020-09-01", "local": "…" }
  ],
  "atividades": [
    { "ecc_numero": "34", "equipe_servico_id": "…", "status": "A" },
    { "ecc_numero": "35", "equipe_servico_id": "…", "status": "C" }
  ],
  "preferencias": [
    { "equipe_servico_id": "…", "ordem": 1 },
    { "equipe_servico_id": "…", "ordem": 2 }
  ]
}
```

## Decisões aprovadas

1. Telefone profissional separado do pessoal.
2. Engajamento / habilidades no **casal**.
3. Etapas estruturadas 1–3.
4. `NA` = não aceita esta indicação; `NN` = não aceita equipe de trabalho.
5. Preferências = lista ordenada (sem status).
6. Tabelas separadas: serviço × perseverança.
7. Seed com grafia Liturgia/Vigília.
8. Import Excel: **enriquece** etapas/atividades/preferências a partir da planilha EQUIPES ATIVAS (heurística); sem colunas texto legado.

## Notas

- `ecc_equipes` continua sendo equipe de **perseverança** (vínculo atual do casal).
- Esta fatia materializa o início de **EquipeDeServico** + **PreferenciaDeServico** + histórico do BRIEF, sem ainda fechar a Escala.
