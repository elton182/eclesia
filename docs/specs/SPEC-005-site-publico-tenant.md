# SPEC-005 — Site público por Tenant (CMS + vitrine)

**Status:** approved  
**Data:** 2026-09-09  
**Plano:** Site público por Tenant (CMS + vitrine)  
**ADR:** [ADR-0003](../architecture/ADR-0003-site-publico-tenant.md)

## Contexto

Cada organização (tenant) precisa de um site público com informações, comunicados, pastorais e formulários, configurável no app autenticado. Hierarquia Tenant → 1..N Igrejas ([ADR-0002](../architecture/ADR-0002-hierarquia-tenant-igreja.md)): o site é **por tenant**; igrejas entram como conteúdo publicável.

## Objetivo

1. Site público path `/site/{tenantSlug}` (+ páginas `/site/{tenantSlug}/{pageSlug}`).
2. CMS por páginas + blocos tipados (hero/banner, richtext, listagens, formulário).
3. Conteúdo estruturado: comunicados, pastorais (fatia), igrejas publicáveis.
4. Formulários com submissions (LGPD + rate limit).
5. Área admin + papel `gestor-site` e permissões `site.*`.

## Critérios de aceite (testáveis)

- [ ] `site_settings` singleton: publicar/despublicar; site não publicado → API pública 404.
- [ ] `GET /api/v1/public/site` (header `X-Tenant`) retorna settings + home (páginas/blocos publicados).
- [ ] `GET /api/v1/public/site/pages/{slug}` só páginas `publicado`.
- [ ] Blocos tipados: `hero`, `banner`, `richtext`, `igrejas_list`, `comunicados_list`, `pastorais_list`, `form`, `html`.
- [ ] CRUD admin de settings, pages, blocks, media, comunicados, pastorais, forms/fields.
- [ ] `POST .../public/site/forms/{slug}/submissions` com validação, honeypot e rate limit; payload PII criptografado.
- [ ] Submissions só com `site.forms.submissions.view`.
- [ ] Igreja: `slug`, `publicado_no_site`, `descricao_publica`, `horario_missas`; listagem pública só `publicado_no_site`.
- [ ] Pastoral com `igreja_id`; pública só se `publicado_no_site` e `ativa`.
- [ ] Comunicado: `igreja_id` nullable; público só `status=publicado`.
- [ ] Seed: permissões `site.*` + `telas.site`; papel `gestor-site` (team null).
- [ ] `admin-igreja`: comunicados/pastorais da própria igreja; sem settings/pages/forms globais.
- [ ] Isolamento entre tenants; 401 sem auth nas rotas admin; 403 sem permissão.
- [ ] Front público renderiza blocos; front admin em `/site` gated por `telas.site`.
- [ ] `GET /api/v1/public/site/html/{tenantSlug}` e `.../html/{tenantSlug}/{pageSlug}` devolvem HTML com `<title>`, meta description, canonical, Open Graph e texto dos blocos publicados (mesmo documento para pessoa e buscador); 404 se site/página não publicados; bloco `html` só com texto (`strip_tags`).
- [ ] `GET /api/v1/public/site/sitemap/{tenantSlug}` lista URLs canônicas das páginas `publicado` (base `FRONTEND_URL`); 404 se site não publicado.
- [ ] Front (dev/preview): `GET /site/{tenantSlug}` e `/{pageSlug}` fazem proxy do HTML da API + script do Vue; produção com `dist/` estático exige proxy equivalente (`/site/` → rota HTML da API).

## Fora de escopo

- Domínio custom (`paroquia.org`)
- Blog completo, e-mail marketing, multi-idioma do site
- Editor drag-and-drop avançado; PWA do site
- Membros/reuniões de pastorais
- Coedição do CMS global por admin-igreja
- JSON-LD / dados estruturados; editor “Domínio e SEO” (stub)

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/public/site*` e `/site/*`.

## Notas

- Tenancy pública JSON: middleware `X-Tenant` (slug da URL no front).
- Exceção SEO: rotas `public/site/html/*` e `public/site/sitemap/*` inicializam tenancy pelo **slug no path** (crawler não envia `X-Tenant`) — ADR-0003.
- Prefixo de tabelas `site_*`; entidade `pastorais` sem prefixo (evolui para módulo Pastorais).
- ULID em URLs públicas de recursos.
- HTML SEO é derivado do conteúdo publicado (sem coluna de snapshot); cache curto invalidado ao salvar settings, páginas/blocos, comunicados, pastorais ou igreja publicável.
