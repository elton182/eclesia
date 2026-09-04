# ADR-0002 — Hierarquia Tenant → Igreja e identificação sem domínio

**Status:** accepted  
**Data:** 2026-09-03  
**Supersede (parcial):** [ADR-0001](./ADR-0001-multitenancy-lgpd.md) — apenas os pontos de *“tenant = igreja”* e *identificação por domínio*. LGPD e banco-por-tenant permanecem.

## Contexto

O ADR-0001 tratava o tenant como igreja/paróquia isolada e identificava o tenant por domínio (`InitializeTenancyByDomain`, tabela `domains`).

O brief de produto ([BRIEF](../product/BRIEF.md)) redefine o produto:

- O **tenant** é a organização assinante (diocese, congregação, grupo de paróquias…), com **banco próprio**.
- A **igreja** é a unidade operacional **dentro** do banco do tenant (1..N).
- A identificação do tenant passa a ser por **header** ou **path**, não por subdomínio/domínio.

## Decisão

1. **Hierarquia**
   - Central: `Tenant` (+ plano/assinatura, super-admins).
   - Tenant DB: `Igreja` e todo o operacional, particionado por `igreja_id` quando couber.
   - Sem `tenant_id` nas tabelas operacionais.
   - Sem sub-nível comunidade/capela nesta fase (igreja = menor unidade).

2. **Identificação do tenant**
   - `InitializeTenancyByRequestData` (header `X-Tenant` / query) e/ou `InitializeTenancyByPath` (`/{tenant}/...`).
   - **Não** usar `InitializeTenancyByDomain` nem model/tabela `Domain` como mecanismo principal.
   - Slug/identificador no próprio registro de `tenants`.

3. **Escopo de igreja**
   - Serviço de contexto de igreja (sessão) + global scope Eloquent por `igreja_id`.
   - Não confiar em `igreja_id` cru do request; derivar do contexto autenticado + permissões.

4. **Permissões**
   - `spatie/laravel-permission` com `teams = igreja_id` no banco do tenant.
   - Cache de permissões isolado por tenant (CacheTenancyBootstrapper).

5. **Arquitetura modular**
   - Núcleo compartilhado + módulos auto-contidos (prefixo de tabelas, dependência só do núcleo).
   - Camadas: `Controller → Service → Model`.
   - Migrations/seeders de módulos no conjunto de migrations de tenant.

## Consequências

- README/setup que citam `domains` e identificação por domínio ficam desatualizados até a etapa de fundação.
- Migrations centrais deixam de depender de `domains` para o fluxo principal (pode existir legado a remover na implementação).
- Seed de demonstração: **1 tenant → 2 igrejas** para provar o escopo por `igreja_id`.
- Relatórios consolidados do tenant = agregação sobre `igreja_id` no mesmo banco.
- PII e `$encryptable` continuam conforme ADR-0001.
