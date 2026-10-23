# SPEC-025 — ECC: PDF de equipes (piloto e login)

**Status:** approved  
**Data:** 2026-10-23  
**Depende de:** SPEC-002, SPEC-021  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)

## Contexto

Na gestão de equipes (`/ecc/equipes`), o admin precisa de um extrato impresso/compartilhável com o nome de cada equipe, o casal piloto e o login do acesso compartilhado.

## Objetivo

Endpoint e botão na tela de gestão que geram um PDF (DomPDF) listando, para a igreja atual:

| Coluna | Origem |
|--------|--------|
| Equipe | `ecc_equipes.nome` |
| Piloto | Casal(is) com `piloto=true` na equipe — nomes `pessoa A & pessoa B` |
| Login | `acesso_username` (apenas equipes que já têm acesso) |

## Critérios de aceite (testáveis)

- [x] `GET /ecc/equipes/pdf` requer autenticação e `ecc.equipes.manage`.
- [x] Resposta `application/pdf` com download (filename `equipes-{slug-igreja-ou-data}.pdf`).
- [x] Inclui só equipes da igreja atual **com** `acesso_user_id` (login gerado), ordenadas por nome.
- [x] Equipes sem acesso não aparecem no PDF.
- [x] Piloto: se vários, junta com `; `; se nenhum, `—`.
- [x] Login: username do `acesso_user_id`.
- [x] Sem equipes com login: PDF válido com mensagem de lista vazia.
- [x] 401 sem auth; 403 sem `ecc.equipes.manage`.
- [x] Front: botão “Gerar PDF” na listagem `/ecc/equipes` (quem tem manage).

## Fora de escopo

- Senha no PDF.
- Filtro por busca da tela (PDF sempre lista completa).
- Personalização avançada de layout/branding.

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — path `GET /ecc/equipes/pdf`.
