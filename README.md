# Eclesia

Plataforma SaaS de gestão eclesial (monorepo): API Laravel multi-tenant com LGPD e front Vue 3. Modular; primeira fase = núcleo + **módulo ECC**.

Visão de produto: [`docs/product/BRIEF.md`](docs/product/BRIEF.md). Plano: [`docs/plans/PLAN-001-fundacao-nucleo-ecc.md`](docs/plans/PLAN-001-fundacao-nucleo-ecc.md).

## Estrutura

| Pasta | Stack | Papel |
|-------|-------|-------|
| `api/` | Laravel 12, PHP 8.2+, Sanctum | API REST multi-tenant |
| `front/` | Vue 3, Vite, Tailwind | SPA / biblioteca de UI |
| `docs/` | Brief, specs, planos, ADRs | Documentação spec-driven |
| `prototipo/` | HTML | Protótipos de UX (ECC) |

## Pilares técnicos (API)

- **Multi-tenant:** `stancl/tenancy` — banco por **tenant** (organização); **1..N igrejas** no banco do tenant, escopo por `igreja_id`. Identificação por **header/path** (não subdomínio) — ver [ADR-0002](docs/architecture/ADR-0002-hierarquia-tenant-igreja.md).
- **LGPD:** `elgibor-solution/laravel-database-encryption` — PII criptografada em repouso (`$encryptable`) — ver [ADR-0001](docs/architecture/ADR-0001-multitenancy-lgpd.md).
- **Modular:** núcleo compartilhado + módulos (`ecc_`, …) habilitáveis por igreja.

> Setup abaixo reflete o scaffold atual; a fundação do ADR-0002 ainda não foi aplicada no código.

## Setup rápido (API)

```bash
cd api
cp .env.example .env
composer install
php artisan key:generate
# Configure DB_DATABASE=eclesia_central
php artisan migrate
# Crie um tenant; depois:
php artisan tenants:migrate
php artisan serve
```

## Setup rápido (Front)

```bash
cd front
cp .env.example .env
npm install
npm run dev
```

## Documentação para agentes

Ver `AGENTS.md` na raiz e em cada app. Brief em `docs/product/`. Specs em `docs/specs/`.
