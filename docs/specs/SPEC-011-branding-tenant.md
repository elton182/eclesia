# SPEC-011 — Identidade do site público (espelho da marca do tenant)

**Status:** approved  
**Data:** 2026-09-18  
**Atualizado:** 2026-09-19 (espelha marca do app + `tenants.name`; sem subtítulo)  
**Plano:** [PLAN-003](../plans/PLAN-003-calendario-oficial-sessao-branding.md)  
**Relacionada:** [SPEC-014](SPEC-014-branding-app-tenant.md) (fonte da marca)

## Contexto

A identidade visual do site público (título, cores, logo) deve refletir a marca já configurada na organização — não um segundo editor. A edição continua em `/configuracoes/marca` (`app_settings`, SPEC-014). O título público é o nome do tenant (`tenants.name`).

## Objetivo

1. Em `GET /site/settings` e `GET /public/site` (settings), expor identidade **espelhada**:
   - `titulo` ← `tenants.name`
   - `cores` / `logo_path` / `logo_url` ← `app_settings` (mesmo contrato de SPEC-014)
2. Remover `subtitulo` do contrato de settings do site.
3. `PUT /site/settings` **não** aceita (ignora) `titulo`, `subtitulo`, `cores`, `logo_path`, `favicon_path` — só publicação, menu, SEO, contato etc.
4. Front: aba Identidade do Site Admin é **somente leitura** + link para Marca do sistema; site público aplica CSS vars (`applyBrandCores`) e usa `var(--color-*)` no chrome e blocos (sem hex fixo de marca).

## Critérios de aceite (testáveis)

- [x] `GET /api/v1/public/site` → `settings.titulo` igual a `tenants.name` (não o valor persistido em `site_settings.titulo`).
- [x] Após `PATCH /app/branding` com cores, `GET /public/site` e `GET /site/settings` devolvem as mesmas `cores` (e `logo_url` quando houver logo).
- [x] `PUT /site/settings` com `titulo`/`cores` não altera a identidade espelhada nem grava esses campos.
- [x] Resposta de settings **não** inclui `subtitulo`.
- [x] Site público: chrome e blocos usam `var(--color-primary|secondary|ink|…)` após `applyBrandCores(settings.cores)`.
- [x] Sem tema por Igreja.

## Fora de escopo

- Editor de identidade no módulo Site (só espelho).
- Favicon do site.
- Override por Igreja.
- Tipografia global.

## Contrato de API

OpenAPI: schema `SiteSettings` — `titulo` (espelho), `cores`/`logo_*` espelham `AppBranding`; sem `subtitulo`. Paths `/site/settings`, `/public/site`.

## Notas

- Colunas legadas `site_settings.titulo|subtitulo|logo_path|cores` podem permanecer no DB; a API não as usa mais para identidade.
- Fallback de cores: paleta vinho (ADR-0004) via `applyBrandCores` quando `app_settings.cores` for null.
