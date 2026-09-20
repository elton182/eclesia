# SPEC-014 — Branding do app por tenant (separado do site)

**Status:** approved  
**Data:** 2026-09-19  
**Relacionada:** [SPEC-011](SPEC-011-branding-tenant.md) (site público)

## Contexto

A SPEC-011 amarra a identidade do **site público** (título/cores/logo) como **espelho** de `tenants.name` + `app_settings`. A edição da marca do sistema fica aqui (`/configuracoes/marca`); o Site Admin só exibe leitura.

## Objetivo

1. Persistir branding do app autenticado em singleton `app_settings` (tenant), independente de `site_settings`.
2. Permitir configurar paleta (`primary`, `secondary`, `text`, `text_muted`, `on_primary`) e upload de logo com **crop/zoom no front** (saída PNG quadrado 512×512) antes do `POST`.
3. Expor branding em `/web/me` e endpoints `/app/branding*`; aplicar no front (CSS vars + logo na navbar).

## Critérios de aceite (testáveis)

- [ ] `GET/PATCH /api/v1/app/branding` lê/grava `cores` e `logo_url` (path interno não obrigatório na UI).
- [ ] Validação 422 para hex inválido ou chaves extras; só hex `#RRGGBB`.
- [ ] `POST /api/v1/app/branding/logo` (multipart) e `DELETE` removem/substituem logo.
- [ ] Mutações exigem `admin-tenant` (ou SuperAdmin); GET autenticado ok.
- [x] `POST /web/me` (e login) retorna `branding` a partir de `app_settings`, **não** de `site_settings`.
- [x] Alterar `site_settings` via PUT (campos de identidade ignorados) não altera branding do app; site espelha `app_settings` (SPEC-011).
- [ ] Front aplica CSS vars a partir das 5 cores; derivados soft/hover/dark via `color-mix`; ausência → fallback vinho.
- [ ] Navbar exibe logo do tenant quando houver; fallback para ícone Eclesia.
- [ ] Sem override por Igreja.

## Fora de escopo

- Override por Igreja
- Favicon do app
- Paletas pré-definidas / tipografia
- Mudança do contrato de cores do site (`primary`/`secondary`/`accent`)

## Contrato de API

OpenAPI: schema `AppBranding`; paths `/app/branding`, `/app/branding/logo`, `GET` signed `app/branding/logo` (servir arquivo).

## Notas

- Derivados `--color-primary-soft|hover|dark` não são persistidos.
- Fallback: primary `#4E1220`, secondary `#C88A5E`, text `#2A1418`, text_muted derivado/opaco, on_primary `#F7EDE0`.
