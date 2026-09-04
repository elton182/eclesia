# CLAUDE.md — API Eclesia

## Stack

- PHP 8.2+, Laravel 12, Sanctum
- `stancl/tenancy` — multi-tenant (DB por igreja)
- `elgibor-solution/laravel-database-encryption` — LGPD em repouso

## Estrutura relevante

```
app/Http/Controllers/Api/   # Controllers finos
app/Http/Requests/          # Validação
app/Http/Middleware/        # cookie.to.token
app/Services/               # AuthToken, WebAuth, CookieManager
app/Models/                 # User (encryptable), Tenant
routes/api.php              # Central
routes/tenant.php           # Tenant (domínio)
database/migrations/        # Central (tenants, domains, cache, jobs)
database/migrations/tenant/ # Dados da igreja
docs/specs/openapi.yaml
config/tenancy.php
config/lgpd.php
tests/Feature, tests/Unit
```

## Convenções

- Controllers finos; lógica em Services
- Form Requests + API Resources
- PII: `$encryptable` + TEXT + `whereEncrypted`
- Novas migrations de negócio → pasta `tenant/`
- Prefixo `/api/v1/`
- `declare(strict_types=1);` em arquivos novos
- Sem `$request->all()`; sem alterar migration já aplicada em prod

## Auth

Login tenant: `User::whereEncrypted('email', …)` + `Hash::check` + cookies Sanctum.
Não usar `Auth::attempt` com e-mail criptografado.

## Testes

```bash
php artisan test
php artisan test --filter=NomeDoTeste
php artisan test --coverage --min=80
```

Casos mínimos por endpoint: 401, 403, 422, sucesso; isolamento entre tenants.

## Tenancy

```bash
php artisan migrate
php artisan tenants:migrate
```

Criar tenant: model `Tenant` + domínio associado. Ver ADR-0001.
