# SPEC-002 — ECC: equipes, casais e importação Excel

**Status:** approved  
**Data:** 2026-09-03  
**Depende de:** SPEC-001  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

Primeira fatia do módulo ECC: cadastrar equipes e casais (com equipe), e importar a planilha usada hoje no movimento. Demais telas do protótipo (escala, caixa, etc.) ficam para specs futuras.

## Objetivo

No banco do tenant, escopado por `igreja_id`:

1. CRUD de **equipes** (ex.: EQUIPE A).
2. CRUD de **casais** vinculados a uma equipe, com os campos do modelo Excel.
3. Endpoint de **importação** em lote (JSON das linhas da planilha).
4. Telas no front para listar/criar/editar casais (com gestão secundária de equipes) e importar.

## Modelo de importação (colunas)

### Formato A — planilha detalhada (modelo anexado inicialmente)

| Coluna Excel | Destino |
|--------------|---------|
| equipe | Nome da equipe (cria se não existir) |
| nome | Pessoa A — nome |
| e-mail | Pessoa A — email |
| telefone | Pessoa A — telefone |
| endereco | Casal — endereço |
| bairro | Casal — bairro |
| cidade | Casal — cidade |
| uf | Casal — UF |
| cep | Casal — CEP |
| data nascimento | Pessoa A — data nascimento |
| nome conjuge | Pessoa B — nome |
| e-mail conjuge | Pessoa B — email |
| telefone conjuge | Pessoa B — telefone |
| data nascimento conjuge | Pessoa B — data nascimento |
| data casamento | Casal — data casamento |
| filhos | Casal — filhos (texto) |
| observacoes | Casal — observações |

### Formato B — `ecc.xlsx` operacional (aceito)

| Coluna | Destino |
|--------|---------|
| Equipe | Equipe (cria se não existir) |
| Nome | Casal no formato `Fulano e Ciclana` → Pessoa A + Pessoa B |
| Endereço | Casal — endereço |
| Telefone | `tel1 / tel2` → telefones A/B |
| Têm filhos?… | Casal — filhos |
| Demais colunas relevantes | Observações |

`#` / `Nº` são ignorados.

## Critérios de aceite (testáveis)

- [ ] Migrations tenant: `igrejas`, `pessoas`, `casais`, `ecc_equipes` (+ FKs).
- [ ] Ao criar tenant, seed cria 1 igreja padrão.
- [ ] PII em `pessoas` e contatos usam `$encryptable` + colunas TEXT.
- [ ] CRUD equipes e casais sob `/api/v1/ecc/...` com `X-Tenant`.
- [ ] Importação: cria/reusa equipe pelo nome; cria 2 pessoas + casal; retorna contagem e erros por linha.
- [ ] 401 sem auth; isolamento por tenant (outro slug não vê dados).
- [ ] Front: página **Casais** (`/ecc/casais`) com gestão secundária de equipes, import Excel/CSV e ficha `/ecc/casais/:id`. `/ecc/equipes` redireciona para Casais.

## Fora de escopo

- Escala, perseverança tipada, caixa, eventos, preferências.
- Normalização completa de Família / PessoaIgreja N:N (casal fica ligado à igreja atual via `igreja_id`).
- Papéis spatie/permission finos (auth tenant existente basta nesta fatia).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/ecc/equipes`, `/ecc/casais`, `/ecc/casais/import`.

## Notas

- Unidade: casal = duas `Pessoa` + registro `Casal` com `ecc_equipe_id`.
- Datas aceitas: `Y-m-d` ou `d/m/Y`.
- Igreja atual: primeira igreja do tenant até existir seletor (SPEC futura).
