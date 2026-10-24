# SPEC-029 — Mural: anexo, leitura e engajamento

**Status:** approved  
**Data:** 2026-10-24  
**Depende de:** SPEC-027, SPEC-028

## Contexto

O mural (recados/reflexões) precisa de: arquivo anexável, lista só com título + trecho, registro de leitura no servidor e visão de engajamento para admins. Contadores no menu devem refletir itens **não lidos**.

## Objetivo

1. Anexar **um arquivo** por recado/reflexão; líderes baixam autenticados.
2. Listagem: só `titulo` + `resumo` (início do texto); clique abre o detalhe completo.
3. Ao abrir o detalhe, registrar leitura (`lido_em`) por usuário; contador do menu diminui.
4. Menu (bottom nav / sidebar): badge de não lidos em **Recados** e **Reflexões**.
5. Admin: engajamento por item — quem leu (com data/hora), quem curtiu, quem comentou; totais.

## Modelo

| Entidade | Tabela / colunas | Notas |
|----------|------------------|-------|
| Anexo | `ecc_recados` / `ecc_reflexoes` | `anexo_path`, `anexo_nome`, `anexo_mime`, `anexo_tamanho` (nullable) |
| Leitura | `ecc_leituras` | `igreja_id`, `alvo_tipo`, `alvo_id`, `user_id`, `lido_em`; unique (igreja, tipo, alvo, user) |

Arquivo: PDF / DOC / DOCX / XLS / XLSX / JPG / PNG / WEBP, máx. **10 MB**, disk `public`.

## Permissões

- Anexar/substituir/remover: `ecc.recados.manage` / `ecc.reflexoes.manage`
- Baixar anexo / marcar leitura: view do alvo
- Engajamento: `ecc.interacoes.moderar` ou manage do tipo

## Critérios de aceite (testáveis)

- [x] Migration: colunas de anexo + tabela `ecc_leituras`.
- [x] Create/update multipart aceita `anexo`; `GET .../anexo` faz stream.
- [x] Listagem retorna `resumo`, `lido_por_mim`, `lido_em`, `tem_anexo` (sem corpo/texto completo para líder).
- [x] `GET .../{id}` retorna corpo completo; `POST .../{id}/ler` grava/atualiza `lido_em`.
- [x] `GET /ecc/mural/nao-lidos` → `{ recados, reflexoes }` (publicados válidos no escopo e sem leitura do user).
- [x] `GET .../{id}/engajamento` (admin): leituras, likes, comentários + contagens.
- [x] Front: cards resumidos → detalhe; badges nos dois itens do menu; download; painel engajamento.
- [x] Após ler, badge diminui sem depender só de `localStorage`.
- [x] Testes API + Vitest (resumo, badge).

## Fora de escopo

- Múltiplos anexos por item.
- Push/e-mail de não lidos.

## Contrato

`api/docs/specs/openapi.yaml` — anexo, `/ler`, `/engajamento`, `/ecc/mural/nao-lidos`.
