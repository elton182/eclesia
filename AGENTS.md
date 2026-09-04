# AGENTS.md — Eclesia

Responda em **português brasileiro** neste repositório.

## O que é o projeto

**Eclesia** é uma plataforma SaaS de gestão eclesial (modular) nascida como monorepo a partir dos bases Innov (`innov-base-api` / `innov-base-front`).

Visão de produto: `docs/product/BRIEF.md`. Plano da 1ª fase: `docs/plans/PLAN-001-fundacao-nucleo-ecc.md`.

Pilares desde o dia zero:

- **Multi-tenant** (`stancl/tenancy`): banco por **tenant** (organização assinante); **1..N igrejas** no banco do tenant (`igreja_id`). Identificação por header/path — ver ADR-0002.
- **LGPD** (`elgibor-solution/laravel-database-encryption`): PII criptografada em repouso nos models Eloquent — ver ADR-0001.
- **Modular**: núcleo compartilhado + módulos (primeiro: **ECC**); demais no roadmap.
- **Spec-driven**: features passam por `docs/specs/` → OpenAPI → testes → implementação.

Protótipo de UX de referência: `prototipo/`. Em decisões em aberto do brief, **perguntar** antes de assumir.

## Aplicações

| Pasta | Stack | Papel |
|-------|-------|-------|
| `api/` | Laravel 12, PHP 8.2+, Sanctum, stancl/tenancy | API REST multi-tenant + LGPD |
| `front/` | Vue 3, Vite, Tailwind 4, Pinia, vue-i18n | SPA / UI kit Innov |

Cada app tem `AGENTS.md` e `CLAUDE.md` na própria raiz.

## Camadas para agentes

| Camada | Onde |
|--------|------|
| Contrato global | `AGENTS.md` (raiz) |
| Por app | `{app}/AGENTS.md`, `{app}/CLAUDE.md` |
| Cursor Rules | `.cursor/rules/*.mdc` |
| Cursor Hooks (TDD) | `.cursor/hooks.json` — rodar command `/setup-tdd-hooks` |

## Novas funcionalidades (spec-driven)

1. Spec em `docs/specs/` (`draft` → `approved`, sem `TODO` pendente em spec aprovada).
2. OpenAPI em `api/docs/specs/openapi.yaml` antes de mudar request/response.
3. Plano em `docs/plans/PLAN-XXX.md` se escopo grande.
4. **Testes antes ou junto** da implementação (TDD).
5. ADR em `docs/architecture/ADR-XXXX.md` se decisão de arquitetura.
6. Se a feature toca PII: declarar `$encryptable` e colunas TEXT (ver ADR-0001).
7. Se a feature é de domínio da igreja: migration em `api/database/migrations/tenant/` e rota em `routes/tenant.php`.

## Testes

| App | Comando |
|-----|---------|
| api | `php artisan test --coverage --min=80` |
| front | `npm run test:run` (e `npm run test:coverage` se existir) — Vitest a configurar se ainda ausente |

Toda alteração relevante exige testes no lado afetado:

- Alteração em `api/` → testes PHPUnit/Pest na API
- Alteração em `front/` → testes Vitest no front
- Alteração que cruza `api/` e `front/` → testes nos **dois** lados

Coverage mínimo **80%** onde aplicável.

## Commits

Conventional Commits em português: `feat(escopo): descrição`.

Tipos: `feat`, `fix`, `test`, `docs`, `refactor`, `chore`, `perf`, `ci`.

## Segurança

Nunca commitar: credenciais, `.env` com valores reais, tokens, chaves privadas, sessões de gateway.
`APP_KEY` protege a criptografia LGPD — não rotacionar sem plano de re-criptografia.

## O que agentes NÃO devem fazer

- Deletar arquivos sem pedido explícito
- Alterar migração já executada em produção
- `git push --force` em `main`/`master`
- Instalar dependências não previstas no plano/spec
- Gravar PII em texto claro em banco (sem `$encryptable`)
- Colocar dados de tenant em migrations centrais
