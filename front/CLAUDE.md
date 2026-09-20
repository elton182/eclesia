# CLAUDE.md — Front Eclesia

## Stack

- Vue 3 + Vite 6 + Tailwind 4
- Pinia, vue-router, vue-i18n, axios, Font Awesome
- Base Innov (`innov-front`) — SPA e lib de componentes

## Estrutura

```
src/main.js, App.vue
src/router/
src/stores/          # auth, authAdmin, toast
src/services/api.js  # axios → VITE_API_URL + api/v1/
src/layouts/         # AdminLayout, AuthLayout
src/components/base/ # InnovPanel, InnovCrud, InnovModal…
src/components/form/
src/components/data/
src/views/
src/i18n/locales/pt-BR.js
```

## Convenções

- Composition API com `<script setup>`
- Reutilizar componentes `Innov*` antes de criar novos
- Design system: [`../docs/design/DESIGN-GUIDE.md`](../docs/design/DESIGN-GUIDE.md)
- Stores Pinia para estado compartilhado
- API com `withCredentials` + CSRF Sanctum
- Alinhar endpoints ao contrato OpenAPI da API (`web/login`, `web/me`, `web/logout`)
- `data-testid` em elementos testáveis; `label`/`alt` acessíveis
- Domínio do browser = tenant (multi-tenant por host)

## Comandos

```bash
npm run dev
npm run build
npm run build:lib    # build como biblioteca
```

Vitest: configurar `test:run` / `test:coverage` quando testes forem introduzidos (coverage alvo 80%).

## Não fazer

- Duplicar DTOs da API à mão (preferir tipos gerados do OpenAPI)
- Guardar PII sensível em `localStorage` além do necessário para sessão
