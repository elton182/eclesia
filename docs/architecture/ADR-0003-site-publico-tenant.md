# ADR-0003 — Site público por Tenant

**Status:** accepted  
**Data:** 2026-09-09  
**Relacionados:** [ADR-0001](./ADR-0001-multitenancy-lgpd.md), [ADR-0002](./ADR-0002-hierarquia-tenant-igreja.md), [SPEC-005](../specs/SPEC-005-site-publico-tenant.md)

## Contexto

O produto precisa de uma vitrine/CMS por organização. A hierarquia já define Tenant (organização) e Igreja (unidade operacional). Identificação do tenant é por header/path, sem domínio como mecanismo principal (ADR-0002).

## Decisão

1. **Um site por Tenant** (não por igreja). Igrejas, comunicados e pastorais são conteúdo do site.
2. **Módulo `Site`** (tabelas `site_*`) com escopo **tenant** — exceção consciente aos módulos habilitáveis só por igreja.
3. **URL pública MVP:** path no front `/site/{tenantSlug}`; API com header `X-Tenant` (mesmo mecanismo do app). Domínio custom **fora** desta fase.
4. **Rotas `/api/v1/public/site*`** sem autenticação; conteúdo só se `site_settings.publicado` e registros publicados.
5. **CMS:** `site_pages` + `site_blocks` tipados (hero/banner/richtext/listagens/form/html).
6. **`pastorais`:** entidade de domínio (sem prefixo `site_`) para reuso no módulo Pastorais; MVP só dados públicos.
7. **Formulários:** submissions com payload criptografado (LGPD), rate limit e honeypot.
8. **RBAC:** permissões `site.*` + `telas.site`; papel `gestor-site` com `team_id` null; `admin-igreja` só comunicados/pastorais da própria igreja.

## Consequências

- Front distingue landing SaaS (`/`) de site da organização (`/site/...`).
- Storage de mídia tenant-aware.
- Evolução futura de domínio custom exigirá ADR complementar (não invalida path MVP).
