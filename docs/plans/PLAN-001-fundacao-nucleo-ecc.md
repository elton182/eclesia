# PLAN-001 — Fundação multi-tenant, núcleo e módulo ECC

**Status:** em execução (etapa 1 parcial + fatia ECC equipes/casais)  
**Data:** 2026-09-03  
**Brief:** [docs/product/BRIEF.md](../product/BRIEF.md)  
**ADRs:** [ADR-0001](../architecture/ADR-0001-multitenancy-lgpd.md), [ADR-0002](../architecture/ADR-0002-hierarquia-tenant-igreja.md)  
**Specs:** [SPEC-001](../specs/SPEC-001-admin-tenants-identidade.md), [SPEC-002](../specs/SPEC-002-ecc-equipes-casais.md), [SPEC-003](../specs/SPEC-003-usuarios-papeis-tenant.md)

## Objetivo

Entregar a fundação SaaS multi-tenant (banco por tenant), o núcleo compartilhado (igrejas, pessoas, shell modular) e o **módulo ECC** completo no front existente — sem implementar os demais módulos do roadmap.

## Princípios

- Spec-driven / TDD conforme `AGENTS.md`.
- Validar com o time **ao fim de cada etapa** antes de avançar.
- Decisões em aberto do brief: **perguntar** antes de assumir.
- Não recriar o front; não cobrar assinatura nesta fase.

## Progresso

- [x] SPEC-001 / SPEC-002 aprovadas
- [x] Identidade visual (cores logo + shell inspirado no protótipo)
- [x] Super-admin + CRUD tenants (header `X-Tenant`)
- [x] ECC: equipes, casais, importação Excel (MVP)
- [ ] Seletor de igreja / multi-igreja completo
- [x] spatie/permission com teams (SPEC-003)
- [ ] Demais telas ECC (escala, caixa, perseverança…)

## Etapas

### Etapa 1 — Fundação multi-tenant + shell modular

- Alinhar `stancl/tenancy` ao ADR-0002: conexões central/tenant, slug, identificação header/path, `routes/tenant.php`, migrations tenant, pipeline `TenantCreated`.
- Remover/desativar dependência de identificação por domínio no fluxo principal.
- Contexto de igreja + global scope `igreja_id`.
- spatie/permission com teams=`igreja_id` e cache por tenant.
- Estrutura de módulos (`Controller → Service → Model`) e registro de paths de migration de módulo no tenancy.
- Reaproveitar auth e shell do `front/` (seletores de igreja/módulo — esqueleto).

**Checkpoint:** validação antes da etapa 2.

### Etapa 2 — Núcleo (banco do tenant)

- Migrations/models: Igreja, Pessoa, PessoaIgreja, Família, Casal, User, papéis, Módulo (ativação por igreja), EventoAgenda, auditoria.
- Shell: seletor de igreja e de módulo; painel agregador.
- Seed demo: **1 tenant com 2 igrejas**.

**Checkpoint:** isolamento por igreja demonstrável.

### Etapa 3 — Módulo ECC

- Migrations/models `ecc_*` com `igreja_id`, referenciando núcleo.
- Services com regras (escala em 3 camadas, caixa do evento, etc.).
- Permissões `ecc.*`.
- Telas no front (ordem sugerida): Comunidade → Equipes de serviço → Escala → Eventos → Perseverança → Caixa/compras → Painel.
- Identidade visual ECC dentro do design system existente.
- Specs OpenAPI + specs em `docs/specs/` conforme for abrindo cada fatia.

**Checkpoint:** fluxos principais do protótipo cobertos.

### Etapa 4 — Testes

- Isolamento por igreja (global scope).
- Sugestão de escala.
- Movimentação de caixa.
- Coverage mínimo 80% onde aplicável.

## Fora deste plano

- Catequese, Sacramentos, Dízimo, Financeiro, Pastorais, Gestão (além do necessário ao núcleo).
- Billing/gateway, pagamentos online, app nativo.
- Recriar front ou trocar scaffold.

## Decisões em aberto (bloqueiam detalhes da etapa 3)

Ver seção correspondente no [BRIEF](../product/BRIEF.md). Resolver antes de fechar specs do ECC.
