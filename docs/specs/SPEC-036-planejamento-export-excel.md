# SPEC-036 — Planejamento anual: exportação Excel por mês

**Status:** approved  
**Data:** 2026-11-16  
**Emenda:** [SPEC-018](./SPEC-018-pastorais-planejamento-anual.md)  
**Relacionado:** [SPEC-016](./SPEC-016-ecc-financeiro.md) (padrão client-side `xlsx`)

## Contexto

O planejamento anual já exporta PDF da agenda do mês e PDF de links/QR. A consolidação histórica era em planilha; o gestor precisa baixar a lista do mês (e do ano, uma aba por mês) em Excel para revisar, imprimir ou compartilhar fora do sistema.

## Objetivo

1. Exportar a agenda do **mês selecionado** como `.xlsx` (colunas alinhadas ao PDF da agenda).
2. Exportar o **ano completo** em um único `.xlsx` com **uma aba por mês** (Jan–Dez).
3. Geração no front (biblioteca `xlsx` já usada no ECC financeiro) — sem novo endpoint na API.
4. Disponível nos mesmos status em que o PDF já é permitido: `coleta`, `revisao`, `fechado` (não em `rascunho`).

## Critérios de aceite (testáveis)

- [x] Na tela `/planejamento/:id`, botão **Excel mês** gera `planejamento-agenda-{ano}-{mm}.xlsx` com os eventos do mês em visão (ordem cronológica).
- [x] Botão **Excel ano** gera `planejamento-agenda-{ano}.xlsx` com 12 abas nomeadas pelos meses.
- [x] Colunas: Data | Horário | Pastoral | Título | Local | Recursos | Participantes | Status | Observações.
- [x] Eventos sem ocorrência no mês → planilha só com cabeçalho (ou aba vazia com cabeçalho no export anual).
- [x] Botões desabilitados em `rascunho`.
- [x] Testes unitários do utilitário de montagem das linhas/workbook.

## Fora de escopo

- Endpoint API `/excel` ou PhpSpreadsheet.
- Importação de Excel de volta para o planejamento.
- Formatação rica (cores por pastoral, merge de células, etc.).

## Contrato de API

Nenhuma alteração OpenAPI — exportação 100% client-side a partir dos eventos já carregados via `GET /planejamento/anuais/{id}/eventos`.

## Notas

- Mesmo critério de “mês” do PDF/filtros: `data_inicio` no mês (1–12) do ano do planejamento.
- Labels de status usam os mesmos de `labelStatusSolicitacao` na UI.
