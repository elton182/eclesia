# SPEC-023 — Atalho PWA de acesso por tenant

**Status:** approved  
**Data:** 2026-10-22  
**ADR:** [ADR-0009](../architecture/ADR-0009-atalho-pwa-acesso-tenant.md)  
**Relacionadas:** [SPEC-005](SPEC-005-site-publico-tenant.md), [SPEC-011](SPEC-011-branding-tenant.md)

## Contexto

O login do tenant exige o identificador da organização (slug). Membros da paróquia muitas vezes não o lembram. O site público já conhece o slug na URL (`/site/{tenantSlug}`) e grava o valor ao clicar em Entrar — mas isso não sobrevive a outro aparelho nem ao atalho genérico do Eclésias.

## Objetivo

1. Permitir **instalar um atalho** a partir do site público da organização.
2. O atalho abre o app com a organização **já amarrada** (`/entrar?tenant={slug}`), sem o usuário precisar digitar o código.
3. Nome (e tema) do atalho refletem a marca da organização; ícones padrão da plataforma no MVP se não houver assets PWA próprios.

## Critérios de aceite (testáveis)

- [x] `GET /api/v1/public/site/manifest/{tenantSlug}` (tenancy pelo path, sem `X-Tenant`) devolve `application/manifest+json` com:
  - `id` = `/acesso/{tenantSlug}`
  - `name` / `short_name` = nome público do tenant
  - `start_url` = `/entrar?tenant={tenantSlug}`
  - `scope` = `/`
  - `display` = `standalone`
  - `theme_color` a partir da marca (fallback vinho)
  - ícones absolutos no host do front (`FRONTEND_URL/icons/...`)
- [x] 404 se site não publicado; tenant inexistente → erro de identificação (mesmo padrão HTML/sitemap).
- [x] HTML SEO da home/página inclui `<link rel="manifest" href="…/site/{slug}/manifest.webmanifest">` (URL no host do front).
- [x] Front (dev/preview): `GET /site/{slug}/manifest.webmanifest` faz proxy do JSON da API (produção: proxy equivalente).
- [x] Rota dedicada `/site/{slug}/instalar` (`instalar` é segmento reservado, não é pageSlug do CMS).
- [x] Fluxo em dois passos: **1)** login (org = slug da URL + e-mail/usuário + senha); **2)** só após sessão válida, CTA de instalação (prompt nativo ou passo a passo iOS/Android). Se `must_change_password`, redireciona para troca de senha.
- [x] No site público: link compacto no header aponta para `/instalar`; banner genérico “Instalar Eclésias” não aparece em `/site/*`.
- [x] A página `/instalar` serve HTML com `<link rel="manifest">` do tenant no **primeiro paint**
  (`GET /api/v1/public/site/install-html/{slug}` + proxy front).
- [x] `GET /entrar?tenant={slug}` pré-preenche e persiste a organização (`rememberTenantForLogin`); campo organização fica somente leitura enquanto o query estiver presente.

## Fora de escopo

- PWA offline do CMS / conteúdo do site (continua fora de SPEC-005).
- Domínio custom (`paroquia.org`) — ADR futuro; não invalida o path MVP.
- Ícones gerados sob medida a partir do logo (usar ícones padrão + nome da org).
- App Stores / TWA.

## Contrato de API

OpenAPI: `GET /public/site/manifest/{tenantSlug}`.

## Notas

- Manifest e página de instalação precisam ser **same-origin** com o front; a API gera o JSON, o front (ou proxy) o serve em `/site/{slug}/manifest.webmanifest`.
- Vários atalhos no mesmo domínio usam `id` distintos por slug (ADR-0009).
