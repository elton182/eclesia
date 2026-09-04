# AGENTS.md — Front (Eclesia)

Contrato global: [`../AGENTS.md`](../AGENTS.md).

## Identidade

- **front-agent:** Vue 3 — components, composables, stores, views

Leia `CLAUDE.md` nesta pasta.

## Padrões do Front

### Origem e papel

Base `innov-base-front`: UI kit + SPA demo (Tailwind 4, Pinia, vue-router, vue-i18n, Font Awesome).
Serve como ponto de partida do Eclesia; evoluir views de demo para domínio eclesial conforme specs.

### Estrutura

| Área | Caminho |
|------|---------|
| Entry | `src/main.js`, `src/App.vue` |
| Router | `src/router/` |
| Stores Pinia | `src/stores/` (`auth`, `authAdmin`, `toast`, …) |
| HTTP | `src/services/api.js` (axios, `withCredentials`, CSRF Sanctum) |
| Layouts | `src/layouts/` (`AdminLayout`, `AuthLayout`) |
| Components base | `src/components/base/` (`InnovPanel`, `InnovCrud`, …) |
| Forms | `src/components/form/` |
| Data | `src/components/data/DataTable.vue` |
| Views | `src/views/` |
| i18n | `src/i18n/` (locale `pt-BR`) |
| Lib export | `src/index.js` (uso como pacote npm) |

### Fluxo

Spec aprovada → tipos do OpenAPI (`openapi-typescript`, quando configurado) → testes Vitest → implementação.

Não duplicar modelos da API manualmente.

### UI e testes

- `<script setup>` obrigatório
- Sem `any` — usar `unknown` + type guards (quando migrar para TS)
- `label` em inputs; `alt` em imagens; `data-testid` em elementos testáveis
- Preferir componentes `Innov*` existentes antes de criar novos
- Chamadas API sob `VITE_API_URL` + `api/v1/`; autenticação alinhada aos endpoints tenant (`web/login`, `web/me`, `web/logout`)
- Tenant: o host do front/API deve ser o domínio do tenant (não o central)

Toda alteração relevante no front exige testes Vitest. Se a mudança também afeta a `api/`, exigir testes PHPUnit/Pest na API (ver `../AGENTS.md` e `../api/AGENTS.md`).

### Multi-tenant no browser

- Requests vão ao domínio do tenant (cookie/session no escopo do host)
- Não misturar dados de igrejas no estado Pinia sem reset ao trocar contexto

## Comandos

```bash
npm run dev
npm run build
npm run test:run        # após configurar Vitest
npm run test:coverage   # se existir
```
