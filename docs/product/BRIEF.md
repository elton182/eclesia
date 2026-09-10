# Brief do produto — Eclesia

**Status:** draft (visão aprovada para documentação; implementação ainda não iniciada nesta fase)  
**Data:** 2026-09-03  
**Fonte:** prompt de produto / brief de plataforma SaaS de gestão eclesial

> Plataforma **SaaS multi-tenant com um banco de dados por tenant** (via **`stancl/tenancy`**). Cada **tenant** administra **uma ou mais igrejas**. É **modular** (ECC, Catequese, Sacramentos, Dízimo, Financeiro, Pastorais, Gestão…), sobre um **núcleo compartilhado**.
>
> **O desenvolvimento começa pelo módulo ECC**, sobre o núcleo multi-tenant.
> Quando encontrar uma **decisão em aberto**, perguntar antes de assumir.

---

## Papel e objetivo

Plataforma web SaaS, responsiva (com toque de PWA), para gestão de igrejas, **modular** e **multi-tenant com banco por tenant**. Cada tenant administra **várias igrejas**. Todos os módulos compartilham um **núcleo** comum — sobretudo o **cadastro único de pessoas dentro do tenant**.

**Primeira fase:** (1) fundação **multi-tenant** + **shell modular**; (2) **módulo ECC** completo. Os demais módulos são roadmap — modelar o núcleo pensando neles, mas **não** implementá-los agora. Avançar por etapas, começando pela fundação, pedindo validação a cada etapa.

Stack: engenharia sênior **Laravel + Vue** (front já existente no monorepo).

---

## Hierarquia

```
Banco CENTRAL (landlord)
  └── Tenant (organização assinante: diocese, congregação, grupo de paróquias, administração…)

Banco DO TENANT (um por tenant)
  └── Igreja (paróquia/comunidade administrada)   [1..N]
        └── dados operacionais e módulos ativos (ECC, catequese, dízimo…)  — particionados por igreja_id
```

| Conceito | Papel |
|----------|--------|
| **Tenant** | Fronteira de isolamento e de assinatura. Tem o próprio banco de dados. |
| **Igreja** | Unidade operacional **dentro** do banco do tenant. Um tenant tem 1..N igrejas. |

Comunidades/capelas como sub-nível de igreja: **fora de escopo** (igreja é a menor unidade — ver decisões já definidas).

---

## Arquitetura

- **Monólito modular multi-tenant**: um app Laravel dividido em módulos com fronteiras claras. **Não** microserviços.
- **Núcleo (shared kernel)**: `Igreja`, `Pessoa`, `Família`, `Casal`, `User`, papéis/permissões, registro/ativação de módulos, agenda unificada, busca de pessoas, layout/navegação (seletor de igreja e de módulo), auditoria. No banco central: apenas `Tenant`, plano e super-admin.
- **Módulos** auto-contidos: tabelas prefixadas (`ecc_`, `catequese_`), models, **services**, controllers, rotas, telas (no front existente), policies, seeders e service provider. Dependem **apenas do núcleo**, nunca das tabelas de outro módulo (comunicação por interface publicada).
- **Módulos habilitáveis por igreja**: cada igreja liga/desliga módulos; navegação e painel mostram só os ativos daquela igreja.

### Multi-tenancy (banco por tenant)

- **Central (landlord):** `tenants` (com **identificador/slug** usado no header/path), plano/assinatura, **super-admins da plataforma**. Sem tabela de `domains` (identificação não é por domínio).
- **Por tenant:** todo o resto (igrejas, pessoas, casais, usuários, permissões, módulos, agenda, ECC…).
- **Sem `tenant_id` nas tabelas operacionais** — o banco já *é* o tenant. Tabelas do tenant usam **`igreja_id`** quando pertencem a uma igreja.
- **Dois escopos:**
  1. **Tenant** → `stancl/tenancy` por **header** (`X-Tenant`, `InitializeTenancyByRequestData`) **ou path** (`/{tenant}/...`, `InitializeTenancyByPath`) — **não** por subdomínio.
  2. **Igreja atual** → escopo de aplicação: **global scope** por `igreja_id`, igreja atual via **serviço de contexto** (sessão), escolhida no seletor do shell. Nunca escopar por `igreja_id` vindo direto do request — derivar do contexto autenticado + permissões.
- **Migrations:** centrais vs `database/migrations/tenant/`; criar tenant provisiona banco e roda migrations de tenant.
- **Consolidação entre igrejas:** relatórios do tenant com join/agregação sobre `igreja_id`.

### Convenções stancl/tenancy

- Conexões `central` e `tenant` (DatabaseTenancyBootstrapper). Models centrais com `CentralConnection` / `$connection`.
- Estender `Stancl\Tenancy\Database\Models\Tenant`. Identificação header/path dispensa model/tabela `Domain`.
- Rotas de tenant em `routes/tenant.php`; centrais em `routes/web.php` / `routes/api.php`.
- Pipeline `TenantCreated` → `CreateDatabase` → `MigrateDatabase` → `SeedDatabase`.
- Bootstrappers: Database, Cache, Filesystem, Queue (CacheTenancyBootstrapper ativo).
- **Gotcha módulos:** migrations/seeders de cada módulo no conjunto de tenant (`config/tenancy.php` ou provider do módulo).
- **Gotcha spatie/permission:** cache de permissões por tenant; tabelas nas migrations de tenant; `teams = igreja_id`; limpar cache ao inicializar tenancy.
- Storage/filas tenant-aware.

### Serviços transversais do núcleo

- Agenda unificada por igreja (módulos publicam eventos; calendário do ECC = visão filtrada).
- Busca de pessoas/famílias reutilizável, escopada pela igreja atual.
- Painel com widgets dos módulos ativos da igreja atual.

### Stack e convenções

- Laravel (LTS) + front Vue existente (`front/`). API REST já adotada no monorepo — seguir o padrão do repositório (não trocar scaffold).
- Camadas: **`Controller → Service → Model`** (regra de negócio nos Services).
- Módulos: namespace/pasta própria (ex.: `App\Modules\ECC\{Controllers,Services,Models}` ou equivalente do repo).
- **spatie/laravel-permission** com teams = `igreja_id`; permissões namespaced (`ecc.escala.editar`).
- Reutilizar front/auth existentes; identidade visual do ECC **dentro** desse front.
- MySQL ou PostgreSQL; factories e seeders (centrais e de tenant).
- PWA, mobile-first, navegação inferior no mobile, seletores de igreja e módulo no shell.
- Testes Pest: isolamento entre igrejas (global scope) e regras de negócio.
- Código, rotas, telas e UI em **português brasileiro**.
- LGPD: ver [ADR-0001](../architecture/ADR-0001-multitenancy-lgpd.md) (`$encryptable`).

---

## Núcleo — entidades

### Banco central (landlord)

- **Tenant** — organização assinante: conta, plano/assinatura, status, referência ao banco.
- **Identificador do tenant** — slug/chave no header/path (sem tabela de domínios).
- **SuperAdmin (plataforma)** — administra a plataforma e os tenants.

### Banco do tenant

- **Igreja** — paróquia/comunidade. Nome, endereço, dados eclesiásticos. 1..N por tenant.
- **Pessoa** — cadastro **único no tenant**. Vinculada a igrejas via **PessoaIgreja**. Usada por todos os módulos.
- **PessoaIgreja** — membresia, data, status (N:N pessoa ↔ igreja).
- **Família** — agrupa pessoas, por igreja.
- **Casal** — liga duas `Pessoa`; aceita uma pessoa ativa (viuvez). Usado por ECC e matrimônio.
- **User** — credencial do tenant, vinculada a `Pessoa`, papéis **por igreja** (teams).
- **Papéis/Permissões** (spatie, teams=igreja), namespaced por módulo.
- **Módulo** (registro + ativação por igreja).
- **EventoAgenda** — agenda unificada (dono = módulo, tipo).
- **Auditoria** — log de alterações sensíveis.

---

## Catálogo de módulos (roadmap)

Implementar **apenas o ECC** na primeira fase; os demais são visão de futuro.

| Módulo | Escopo |
|--------|--------|
| **ECC** *(primeiro)* | Encontro, equipes de serviço, escala, perseverança, eventos e caixa |
| Catequese | Turmas, catequistas, catequizandos, presença, etapas |
| Sacramentos | Assentos, certidões, livros |
| Dízimo | Dizimistas, contribuições, carnês, recibos |
| Financeiro | Contas, receitas/despesas, caixa geral |
| Pastorais | Pastorais/movimentos, membros, reuniões |
| Site | Site público por tenant (CMS, comunicados, pastorais na vitrine, formulários) — ver SPEC-005 / ADR-0003 |
| Gestão | Tenant, igrejas, usuários, papéis, parâmetros, comunicação |

---

## Módulo ECC (primeira fase)

Casais participam de um encontro e depois servem e se reúnem. Há **assessor/guia espiritual** (`Pessoa` sem casal). Dados no banco do tenant, escopados por **`igreja_id`**.

### Entidades (`ecc_*`, referenciando núcleo)

- **EquipeDeServico** — nome, cor, descrição, casal coordenador (opcional).
- **Escala** — casal × equipe num encontro/evento.
- **PreferenciaDeServico** / **AptidaoRestricao**
- **EquipeDePerseveranca** — nome (Caná, Emaús…), casal líder, membros, recorrência mensal.
- **Evento** (agenda) — tipos: `encontro`, `anual`, `servos`, `perseveranca`, `formacao`.
- **Reuniao / Presenca**
- **Contribuicao** — (ver decisão em aberto)
- **Ata** / **Avaliacao**
- **Conta/Caixa** (por evento) — saldo, entradas, saídas.
- **Doacao** — casal doador + valor.
- **ItemDeCompra** — `pendente` \| `doado` \| `comprado`.

### Regras de negócio (validadas em protótipo)

1. O **casal** é a unidade; aceitar pessoa sem casal (assessor, viúvo).
2. Sugestão de escala em 3 camadas: Preferência → Histórico → Disponíveis; sinalizar Restrição; conflito no mesmo encontro.
3. Caixa do evento: doação em dinheiro = entrada; baixa em item = saída; doar item físico não mexe no dinheiro.
4. Calendário: etiquetas abreviadas por tipo, clicáveis.

### Telas

1. Painel do ECC · 2. Comunidade · 3. Equipes de serviço · 4. Escala · 5. Perseverança · 6. Eventos (Lista \| Calendário) · 7. Caixa & compras.

Protótipo de referência: `prototipo/ecc-gestao-prototipo.html`.

---

## Papéis e permissões

| Papel | Escopo |
|-------|--------|
| Super-admin (plataforma) | Banco central; tenants |
| Admin do tenant | Todas as igrejas do tenant |
| Admin da igreja | Uma igreja e módulos ativos |
| Coordenador de módulo | Um módulo numa igreja |
| Secretaria | Cadastros e lançamentos |
| Líder de equipe (ECC/perseverança) | A própria equipe |
| Servo / Membro | Leitura/participação (ver decisão em aberto) |

---

## Identidade visual

**Shell / plataforma (logo.png):** marinho `#00234E` + dourado `#C5A059` + fundo `#F4F6F9`. Sidebar marinho, acento dourado, tipografia Figtree + Fraunces.

**Módulo ECC (protótipo):** bordô/vinho `#7A2231` + dourado litúrgico `#B0812F` pode coexistir depois como tema do módulo; nesta fase o shell usa as cores da logo.

- Avatar de casal: **dois discos sobrepostos** (marinho + dourado no shell atual).
- Cartões com sombra suave e cantos arredondados.
- Roadmap: tema por tenant.

Aplicar **dentro** do design system do `front/` existente.

---

## Decisões — já definidas

| Tema | Decisão |
|------|---------|
| Tenancy | `stancl/tenancy`, banco por tenant |
| Identificação do tenant | Header (`X-Tenant`) **ou** path (`/{tenant}/...`) — nunca subdomínio |
| Menor unidade operacional | **Igreja** — sem sub-nível comunidade/capela |
| Camadas | `Controller → Service → Model` |
| Front | Reutilizar Vue existente; não recriar |
| Ordem dos módulos | Só ECC agora; demais no futuro |

Ver também: [ADR-0002](../architecture/ADR-0002-hierarquia-tenant-igreja.md).

---

## Decisões em aberto — perguntar antes de assumir

1. **Pessoa entre igrejas do mesmo tenant:** N:N ou 1 igreja? *Enquanto indefinido, modelar N:N via `PessoaIgreja`.*
2. **Contribuição mensal (ECC):** valor fixo ou caso a caso? Só perseverança ou geral?
3. **Permissões finas:** líder edita só a própria equipe? Membro “assume” item de compra no celular ou só secretaria?
4. **Reuniões recorrentes:** gerar datas mensais automaticamente ou lançar uma a uma?
5. **Escala:** um casal em só um ministério por encontro? Escalar pessoa avulsa (sem casal)?

---

## Etapas (plano)

Ver [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md).

1. Fundação multi-tenant + shell modular (validar antes de seguir).
2. Núcleo no banco do tenant + seed demo (1 tenant, 2 igrejas).
3. Módulo ECC (telas na ordem: Comunidade → Equipes → Escala → Eventos → Perseverança → Caixa → Painel).
4. Testes: isolamento por igreja; sugestão de escala; caixa.

---

## Fora de escopo (por enquanto)

- Módulos além do ECC (só modelar o núcleo pensando neles).
- Recriar o front-end / trocar scaffold / design system.
- Billing/gateway (modelar Tenant/plano, sem cobrança).
- Pagamentos online / integração bancária.
- App nativo (web responsivo + PWA).
