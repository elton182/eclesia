# Eclesia

Plataforma SaaS de gestão eclesial (monorepo): API Laravel multi-tenant com LGPD e front Vue 3. Modular; primeira fase = núcleo + **módulo ECC**, com calendário, pastorais, site público e branding já no código.

Visão de produto: [`docs/product/BRIEF.md`](docs/product/BRIEF.md).  
**O que já funciona (por módulo):** [`docs/product/STATUS-FUNCIONAL.md`](docs/product/STATUS-FUNCIONAL.md).  
Design system: [`docs/design/DESIGN-GUIDE.md`](docs/design/DESIGN-GUIDE.md).  
Contrato OpenAPI: [`api/docs/specs/openapi.yaml`](api/docs/specs/openapi.yaml) (v0.8.0).

## Estrutura

| Pasta | Stack | Papel |
|-------|-------|-------|
| `api/` | Laravel 12, PHP 8.2+, Sanctum, stancl/tenancy | API REST multi-tenant + LGPD |
| `front/` | Vue 3, Vite, Tailwind 4, Pinia, vue-i18n | SPA / shell modular |
| `docs/` | Brief, specs, planos, ADRs, design | Documentação spec-driven |
| `prototipo/` | HTML | Protótipos de UX (referência) |
| `scripts/` | utilitários | Scripts auxiliares |
| `dev.sh` | bash | Sobe API + front local |

## O que já está no código

| Área | Status |
|------|--------|
| Multi-tenant (banco por tenant, `X-Tenant` / path slug, `X-Igreja`) | Pronto — [ADR-0002](docs/architecture/ADR-0002-hierarquia-tenant-igreja.md) |
| LGPD (PII com `$encryptable`) | Pronto — [ADR-0001](docs/architecture/ADR-0001-multitenancy-lgpd.md) |
| Núcleo (igrejas, usuários/papéis, pessoas) | Pronto |
| ECC (equipes, casais, eventos, financeiro) | Parcial (faltam escala ECC / perseverança / relatórios) |
| Site público + admin CMS | Pronto — [ADR-0003](docs/architecture/ADR-0003-site-publico-tenant.md) |
| Calendário oficial + coleta pública | Pronto — [ADR-0006](docs/architecture/ADR-0006-calendario-locais-celebracao.md) |
| Pastorais + planejamento anual | Pronto |
| Branding / white-label do app | Pronto |
| Auditoria | Pronto — [ADR-0007](docs/architecture/ADR-0007-auditoria.md) |
| Shell launcher + marca vinho | Parcial — [ADR-0004](docs/architecture/ADR-0004-shell-launcher-marca-vinho.md) |
| Escalas genéricas (núcleo) | Removido — [ADR-0005](docs/architecture/ADR-0005-escalas-nucleo.md) superseded |

Planos: [PLAN-001](docs/plans/PLAN-001-fundacao-nucleo-ecc.md) · [PLAN-002](docs/plans/PLAN-002-redesign-vinho-launcher-ecc-site.md) · [PLAN-003](docs/plans/PLAN-003-calendario-oficial-sessao-branding.md) · [PLAN-004](docs/plans/PLAN-004-pastorais-planejamento-anual.md) (concluído). Specs em [`docs/specs/`](docs/specs/).

## Pilares técnicos (API)

- **Multi-tenant:** `stancl/tenancy` — banco por **tenant** (organização); **1..N igrejas** no banco do tenant (`igreja_id`). Identificação por **header** (`X-Tenant`) ou **path** (slug), não por subdomínio.
- **Igreja atual:** header `X-Igreja` + contexto autenticado (não confiar em `igreja_id` cru do body).
- **LGPD:** `elgibor-solution/laravel-database-encryption` — PII criptografada em repouso.
- **Modular:** núcleo compartilhado + módulos habilitáveis por igreja (`ecc_`, pastorais, calendário…).
- **Rotas:** centrais em `api/routes/api.php`; domínio da igreja em `api/routes/tenant.php`; prefixo `/api/v1/`.

## Setup rápido

### Pré-requisitos

- PHP 8.2+, Composer, Node.js, MySQL
- API: `cd api && cp .env.example .env && composer install && php artisan key:generate`
- Front: `cd front && cp .env.example .env && npm install`  
  (`VITE_API_URL=http://localhost:8000/`)

Configure o banco central (`DB_*` em `api/.env`, ex.: `DB_DATABASE=eclesia`), depois:

```bash
cd api
php artisan migrate
# Crie um tenant; depois:
php artisan tenants:migrate
```

### Ambiente local (API + front)

```bash
./dev.sh
```

| Serviço | URL |
|---------|-----|
| API | http://localhost:8000 |
| Front | http://localhost:5173 |
| Health | http://localhost:8000/api/v1/health |

Super-admin de seed (quando aplicável): `admin@eclesia.local` / `password`.

Variáveis importantes: `APP_KEY` (criptografia LGPD), `DB_*`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`, headers `X-Tenant` / `X-Igreja`.

## Testes e CI

```bash
# API
cd api && php artisan test

# Front
cd front && npm run test:run
```

CI em [`.github/workflows/ci.yml`](.github/workflows/ci.yml) (PHPUnit + testes do front + build). Deploy após CI em `main`: [`.github/workflows/deploy.yml`](.github/workflows/deploy.yml).

## Documentação para agentes

Ver [`AGENTS.md`](AGENTS.md) na raiz e em cada app (`api/`, `front/`).  
Produto: [`docs/product/BRIEF.md`](docs/product/BRIEF.md) · inventário funcional [`docs/product/STATUS-FUNCIONAL.md`](docs/product/STATUS-FUNCIONAL.md).  
Specs em `docs/specs/`. ADRs em `docs/architecture/`.
