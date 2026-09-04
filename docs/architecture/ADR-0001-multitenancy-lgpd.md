# ADR-0001 — Multi-tenant e LGPD desde o dia zero

**Status:** accepted (parcialmente supersedido)  
**Data:** 2026-09-03  
**Supersedido em parte por:** [ADR-0002](./ADR-0002-hierarquia-tenant-igreja.md) (hierarquia Tenant→Igreja e identificação do tenant)

## Contexto

O Eclesia atende múltiplas organizações eclesiais com dados sensíveis de pessoas físicas. É necessário isolamento forte entre assinantes (tenants) e criptografia de dados pessoais em repouso (LGPD).

## Decisão

1. **Multi-tenancy com banco separado** via `stancl/tenancy` v3:
   - Banco central: `tenants`, planos/assinatura, super-admins (e demais artefatos do landlord)
   - Banco por tenant: dados operacionais da organização (igrejas, pessoas, módulos…)
   - Prefixo de banco: `tenant` + identificador do tenant
   - ~~Identificação por domínio (`InitializeTenancyByDomain` / tabela `domains`)~~ → **ver ADR-0002** (header/path; sem domínio como mecanismo principal)
   - ~~Tenant = uma igreja~~ → **ver ADR-0002** (tenant = organização; 1..N igrejas no banco do tenant)

2. **Criptografia em repouso** via `elgibor-solution/laravel-database-encryption` *(permanece)*:
   - Trait `EncryptedAttribute` + `$encryptable` nos models
   - Colunas TEXT para campos criptografados
   - Busca com `whereEncrypted` / `orWhereEncrypted`
   - Validação com `unique_encrypted` / `exists_encrypted`
   - Login não usa `Auth::attempt` com e-mail; resolve usuário via `whereEncrypted`

3. Config de referência: `api/config/lgpd.php`

## Consequências

- Cada nova feature com PII deve declarar `$encryptable` e schema TEXT
- Migrations de domínio de negócio vão em `database/migrations/tenant/`
- Rotas de negócio em `routes/tenant.php`; landlord em `routes/api.php`
- Troca de `APP_KEY` invalida dados criptografados — backup e rotação planejados
- Hierarquia operacional e identificação do tenant: seguir **ADR-0002** e o [BRIEF](../product/BRIEF.md)
