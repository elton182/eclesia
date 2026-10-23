# ADR-0009 — Atalho PWA de acesso por tenant

**Status:** accepted  
**Data:** 2026-10-22  
**Relacionados:** [ADR-0002](./ADR-0002-hierarquia-tenant-igreja.md), [ADR-0003](./ADR-0003-site-publico-tenant.md), [SPEC-023](../specs/SPEC-023-atalho-pwa-acesso-tenant.md)

## Contexto

A plataforma tem um PWA global (Eclésias) com `start_url: /`. O login tenant pede o slug. Queremos que quem visita o site da paróquia possa instalar um atalho que já abre o acesso daquela organização, sem lembrar o código.

Identificação continua por header/path (ADR-0002); domínio custom permanece fora de fase.

## Decisão

1. **Manifest dinâmico por tenant** gerado na API (`GET /api/v1/public/site/manifest/{tenantSlug}`), com tenancy pelo slug no path (mesmo padrão do HTML/sitemap, ADR-0003).
2. **Servir no host do front** em `/site/{tenantSlug}/manifest.webmanifest` (proxy em dev/preview e produção), porque o Web App Manifest exige same-origin com a página instalável.
3. **Identidade do app instalado:** `id: /acesso/{slug}`, `name` = nome do tenant, `start_url: /entrar?tenant={slug}`, `scope: /` (após o login o usuário precisa navegar o app inteiro).
4. **PWA global** permanece para a landing/plataforma; no site público o front aponta o `<link rel="manifest">` para o manifest do tenant.
5. **Página dedicada** `/site/{slug}/instalar` (segmento reservado) concentra o prompt nativo e o passo a passo (iOS/Android); o header do site só leva a essa rota com link compacto.
6. **Primeiro paint com manifest do tenant** via `GET /public/site/install-html/{slug}` (proxy no front). Trocar o manifest só via JS depois do load **não** costuma rearmar `beforeinstallprompt`.
7. **Same-origin obrigatório:** `<link rel="manifest">` e `icons[].src` usam paths na origem (`/site/{slug}/manifest.webmanifest`, `/icons/...`). URL absoluta com `FRONTEND_URL` divergente faz o Chrome ignorar o PWA da paróquia e só oferecer o da plataforma após o login.
8. **Ícones MVP:** assets padrão do front (`/icons/...`); nome/tema diferenciam a paróquia.
9. **Limite de plataforma:** o popup só existe onde há `BeforeInstallPromptEvent` (Chrome/Edge/Android). iOS Safari exige “Adicionar à Tela de Início” manual — não há API para forçar dialog.

## Consequências

- Vários atalhos no mesmo domínio são possíveis graças a `id` distintos.
- Produção com `dist/` estático precisa proxy de `/site/*/manifest.webmanifest` → API (além do HTML SEO).
- Domínio custom no futuro simplifica origem por paróquia, mas não substitui este MVP.
- Não cobre PWA offline do conteúdo do site (SPEC-005).
