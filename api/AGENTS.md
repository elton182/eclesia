# AGENTS.md — API (Eclesia)

Contrato global: [`../AGENTS.md`](../AGENTS.md).

## Identidade

- **api-agent:** Laravel — rotas, controllers, services, jobs, tenancy, LGPD
- **qa-agent:** testes e coverage (veto se < 80% ou testes quebrados)

Leia `CLAUDE.md` nesta pasta.

## Padrões da API

### Arquitetura

| Camada | Onde | Regra |
|--------|------|-------|
| Rotas centrais | `routes/api.php` | Landlord: health, gestão de tenants |
| Rotas tenant | `routes/tenant.php` | Domínio da igreja + auth web |
| Controllers | `app/Http/Controllers/Api/` | Finos; sem regra de negócio pesada |
| Form Requests | `app/Http/Requests/` | Validação; `unique_encrypted` / `exists_encrypted` para PII |
| Services | `app/Services/` | Lógica de negócio e tokens |
| Models | `app/Models/` | Eloquent; Tenant com `HasDatabase` + `HasDomains` |
| Resources | `app/Http/Resources/` | Preferir Resources para resposta JSON |
| OpenAPI | `docs/specs/openapi.yaml` | Contrato HTTP antes da implementação |
| LGPD | `config/lgpd.php` | Checklist de campos pessoais |

### Multi-tenant (`stancl/tenancy`)

- Model: `App\Models\Tenant` (`TenantWithDatabase`)
- Central: `tenants`, `domains`, `cache`, `jobs`
- Tenant DB: `database/migrations/tenant/` (users, tokens, domínio)
- Middleware: `InitializeTenancyByDomain` + `PreventAccessFromCentralDomains`
- Env: `CENTRAL_DOMAINS`, prefixo de DB `tenant{uuid}`
- Comandos: `php artisan migrate` (central) · `php artisan tenants:migrate`

### LGPD (criptografia)

- Pacote: `elgibor-solution/laravel-database-encryption`
- Trait: `ESolution\DBEncryption\Traits\EncryptedAttribute`
- Property: `protected $encryptable = ['name', 'email', ...]`
- Schema: colunas encryptable como `TEXT`
- Query: `whereEncrypted` / `orWhereEncrypted` (não `where` em PII)
- Login: não usar `Auth::attempt` com e-mail criptografado — ver `AuthWebController`
- Comandos: `encryptable:encryptModel` / `encryptable:decryptModel`

### Auth (base Innov)

- Sanctum + cookies access/refresh (`CookieManager`, `WebAuthService`, `AuthTokenService`)
- Middleware `cookie.to.token`
- Prefixo HTTP: `/api/v1/`
- Endpoints tenant: `POST web/login`, `web/me`, `web/logout`

### PHP

- `declare(strict_types=1);` em código novo
- UUID/ULID em URLs públicas — não IDs sequenciais
- Controllers finos; sem `$request->all()`

## TDD (obrigatório em features)

1. Spec em `../docs/specs/` (ou alinhada ao OpenAPI).
2. OpenAPI antes de alterar contrato HTTP.
3. Escrever testes em `tests/` que **falhem** antes da implementação.
4. Implementar o mínimo até verde.
5. Loop: `php artisan test` → corrigir → `php artisan test --coverage --min=80`.

Toda alteração relevante na API exige testes PHPUnit/Pest. Se a mudança também afeta o `front/`, exigir testes Vitest no front (ver `../AGENTS.md` e `../front/AGENTS.md`).

### Casos mínimos por endpoint

- 401 sem autenticação
- 403 sem permissão
- 422 validação
- Sucesso com estrutura esperada
- Em rotas tenant: isolamento (outro tenant não vê dados)

## Hooks

Após `/setup-tdd-hooks`: edição em `app/` dispara geração/exigência de testes.

## Comandos

```bash
php artisan test
php artisan test --filter=NomeDoTeste
php artisan test --coverage --min=80
php artisan migrate
php artisan tenants:migrate
```
