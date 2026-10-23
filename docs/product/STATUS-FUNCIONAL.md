# Status funcional — Eclesia

**O que já existe e funciona no produto** (código + telas), descrito do ponto de vista de uso.  
Não é roadmap: para visão futura, ver [`BRIEF.md`](BRIEF.md). Para arquitetura, ver ADRs em [`../architecture/`](../architecture/). Para tabela resumida de engenharia, ver o [`README`](../../README.md) da raiz.

**Última revisão:** 2026-10-13

---

## Como o sistema se organiza

```
Plataforma (super-admin)
  └── Tenant (organização assinante — 1 banco próprio)
        └── Igreja 1..N (paróquia/comunidade)
              └── Módulos e cadastros daquela igreja
```

| Conceito | No dia a dia |
|----------|----------------|
| **Tenant** | Organização (diocese, congregação, grupo de paróquias…). Identificada pelo **slug** (header `X-Tenant` ou path). |
| **Igreja** | Unidade operacional. O usuário escolhe no seletor do topo; o app envia `X-Igreja`. |
| **Launcher** | Tela `/inicio` após o login: escolhe o módulo (ECC, Calendário, Site…). |
| **Papéis** | Spatie com *teams* = igreja (exceto papéis de organização como `gestor-site`). |

### Entradas principais (front)

| Quem | Onde entra | Depois |
|------|------------|--------|
| Super-admin da plataforma | `/admin/login` | `/admin/tenants` |
| Usuário do tenant | `/entrar` (informa o slug) | `/inicio` (ou `/ecc/minha-equipe` se for só líder de equipe) |
| Visitante | `/site/{slug}` | Site público do tenant |
| Colaborador externo (calendário) | `/calendario/coleta/{token}` | Preenche disponibilidade sem login |

### Papéis seedados (resumo)

`admin-tenant`, `gestor-site`, `admin-igreja`, `cadastros-usuarios`, `cadastros-equipes`, `cadastros-casais`, `cadastros-eventos`, `cadastros-calendario`, `coordenador-pastoral`, `lider-equipe`.

Permissões são namespaced (`users.*`, `igrejas.*`, `ecc.*`, `calendario.*`, `pastorais.*`, `planejamento.*`, `site.*`, `auditoria.view`, `telas.*`).

---

## Legenda de status

| Status | Significado |
|--------|-------------|
| **Funcionando** | Fluxo usável ponta a ponta (API + tela, ou público) |
| **Parcial** | Parte relevante pronta; faltam pedaços do brief/spec |
| **Só API** | Comportamento existe no backend; sem UI dedicada |
| **Removido** | Existiu e foi retirado |

---

## 1. Administração da plataforma

**Status:** Funcionando  
**Quem usa:** Super-admin

| Funcionalidade | Como funciona |
|----------------|---------------|
| Login admin | `/admin/login` — autenticação no banco central |
| CRUD de tenants | Nome, slug, aliases — `/admin/tenants` |
| Entrar no tenant | A partir da lista, “entra” no contexto (slug + token) e abre o launcher ou usuários |

**Não tem (ainda):** UI para gerenciar outros SuperAdmins; identificação por domínio/subdomínio (modelo é slug/header).

---

## 2. Autenticação do tenant

**Status:** Funcionando  
**Quem usa:** Qualquer usuário da organização

| Funcionalidade | Como funciona |
|----------------|---------------|
| Login | `/entrar` — slug do tenant + e-mail + senha. E-mail é PII criptografada (LGPD). |
<<<<<<< HEAD
| Sessão | Access **30 dias** + refresh **sem expiração** (`CookieManager`; cookie refresh forever); Bearer no front |
| Me / logout / refresh | Mantém ou encerra a sessão; front chama `/web/refresh` no 401 |
=======
| Sessão | Access **8 h** + refresh **7 dias** (`CookieManager`); cookie Sanctum + Bearer no front |
| Me / logout / refresh | Mantém ou encerra a sessão; front chama `/web/refresh` automaticamente no 401 |
>>>>>>> origin/main
| Multi-dispositivo | **Só API:** login novo **não** derruba outras sessões; logout vale só no dispositivo atual |

**Não tem (ainda):** tela para listar/revogar sessões ativas.

---

## 3. Shell, launcher e seletor de igreja

**Status:** Funcionando (parcial no catálogo de cards)  
**Quem usa:** Usuário autenticado do tenant

| Funcionalidade | Como funciona |
|----------------|---------------|
| Launcher `/inicio` | Cards dos módulos ativos/visíveis para o perfil |
| Seletor de igreja | No topo (`TopNavbar`); troca o contexto (`X-Igreja`) |
| Administração no launcher | Atalhos: igrejas, usuários, marca do app, auditoria |

**Cards “em breve” / stubs:** Financeiro paroquial genérico no launcher; no Site: Mídias / Domínio e SEO / Histórico; no ECC: Relatórios.

---

## 4. Núcleo — igrejas, usuários e papéis

**Status:** Funcionando

### Igrejas

| Funcionalidade | Como funciona |
|----------------|---------------|
| CRUD de igrejas | `/igrejas` — cadastro no banco do tenant |
| Escopo | Operações de módulo usam a igreja selecionada |

### Usuários e papéis

| Funcionalidade | Como funciona |
|----------------|---------------|
| CRUD de usuários | `/usuarios` |
| Atribuir papéis | Por igreja (team) ou organização (`gestor-site`) |
| Listar papéis/permissões | API + UI de assign na tela de usuários |

---

## 5. Pessoas e fotos

**Status:** Parcial

| Funcionalidade | Como funciona |
|----------------|---------------|
| Pessoa (modelo) | Cadastro usado pelos módulos; PII criptografada |
| Foto da pessoa | Upload / remoção / URL assinada — usada na ficha do casal ECC e na home do líder |

**Não tem (ainda):** tela genérica “Pessoas” nem CRUD isolado de pessoa fora do ECC. Família / PessoaIgreja do brief ainda não são produto completo neste inventário.

---

## 6. Módulo ECC

**Status:** Parcial (núcleo operacional forte; faltam escala de encontro, perseverança e relatórios)

Entrada típica: card **ECC** no launcher → rotas sob `/ecc/*`.

### 6.1 Equipes e casais — Funcionando

| Funcionalidade | Como funciona |
|----------------|---------------|
| Equipes de serviço | Cadastro/gestão (painel ligado a casais) |
| Casais | CRUD em `/ecc/casais`; ficha em `/ecc/casais/:id` |
| Import Excel | Carga em lote de casais |
| Swap Ele/Ela | Inverte posições na ficha |
| Ficha enriquecida | Etapas, preferências, atividades/serviço |
| Filtro por equipe | Na listagem de casais |

### 6.2 Eventos, compras e caixa — Funcionando

| Funcionalidade | Como funciona |
|----------------|---------------|
| Eventos ECC | CRUD em `/ecc/eventos` (+ detalhe `/:id`) |
| Participantes | Vinculados ao evento |
| Lista de compras | Doar / comprar / desfazer |
| Caixa do evento | Doações em dinheiro (entradas) |
| Módulo Eventos | `/eventos` — mesma API, visão/tipos de evento no shell |

### 6.3 Financeiro ECC — Funcionando

| Funcionalidade | Como funciona |
|----------------|---------------|
| Contas e lançamentos | `/ecc/financeiro` |
| Transferências / transporte de saldo | Entre contas / períodos |
| Import / zerar / anos | Operações de manutenção do livro |

> Não confundir com o card **Financeiro** genérico do launcher (placeholder).

### 6.4 Home do líder de equipe — Parcial

| Funcionalidade | Como funciona |
|----------------|---------------|
| Minha equipe | Papel `lider-equipe` cai em `/ecc/minha-equipe` |
| Casais da equipe | Lista e atualização de ficha (permissão limitada) |
| Shell reduzido | Menos navegação administrativa |

**SPEC-017** ainda em draft; fluxo já existe no código.

### ECC — o que falta vs brief

- Escala de encontro (sugestão Preferência → Histórico → Disponíveis)
- Equipes / fluxo de perseverança
- Relatórios (menu stub)
- Reuniões, presença, atas, avaliação (roadmap do BRIEF)

---

## 7. Site público + CMS

**Status:** Funcionando (stubs em partes do menu admin)

| Funcionalidade | Como funciona |
|----------------|---------------|
| Site público | Visitante em `/site/{slug}` (+ páginas) |
| SEO / HTML | Rotas HTML e sitemap por path (sem precisar de `X-Tenant` no browser) |
| Atalho PWA de acesso | `/site/{slug}/instalar` — login primeiro, depois instalar; atalho abre `/entrar?tenant={slug}` (SPEC-023) |
| CMS admin | `/site` — publicação, menu, cores/marca do **site**, SEO, contato |
| Páginas, comunicados, pastorais na vitrine | CRUD no admin |
| Formulários | Definição + recebimento de submissions |
| Papel `gestor-site` | Foco na gestão do site (org) |

**Stubs / incompleto na nav:** Mídias, Domínio e SEO dedicado, Histórico.

Marca do **site** ≠ marca do **app** (ver branding abaixo).

---

## 8. Calendário oficial (Agenda Paroquial)

**Status:** Funcionando  
**Quem usa:** `cadastros-calendario`, admins; colaboradores externos via link

| Funcionalidade | Como funciona |
|----------------|---------------|
| Locais e tipos | `/calendario/locais`, `/calendario/tipos` |
| Calendário mensal | `/calendario` → detalhe `/:id` |
| Ciclo de status | `rascunho → coleta → montagem → fechado` |
| Itens / observações / tempos litúrgicos | Montagem do mês |
| Indisponibilidades | Registro no fluxo de coleta |
| Copiar próximo mês | Acelera criação |
| PDF | Export do calendário |
| Coleta pública | Link `/calendario/coleta/{token}` sem login |

**Não tem:** sync automático com o planejamento anual.

---

## 9. Pastorais e planejamento anual

**Status:** Funcionando

| Funcionalidade | Como funciona |
|----------------|---------------|
| Pastorais | `/pastorais` — CRUD + membros (coordenador/membro) |
| Detalhe da pastoral | `/pastorais/:id` |
| Anos de planejamento | `/planejamento` — status `rascunho → coleta → revisao → fechado` |
| Eventos por pastoral | Propostas no detalhe do ano `/planejamento/:id` |
| Visão gestor (SPEC-022) | Toggle mensal/semanal; tags laterais por pastoral; conflitos destacados na grade |
| Conflitos de local/horário | Alerta na gravação + destaque na grade (não bloqueia) |
| PDF | Export do planejamento |
| Link/token público | Existe no código para coleta por pastoral |

**Não tem:** ligação automática com o calendário oficial; recorrência só como texto livre.

---

## 10. Branding do aplicativo

**Status:** Funcionando  
**Quem usa:** `admin-tenant` / SuperAdmin no contexto do tenant

| Funcionalidade | Como funciona |
|----------------|---------------|
| Cores e logos do app | `/configuracoes/marca` |
| Logo / logo diocese | Upload; URLs assinadas |
| Aplicação no shell | Store de branding após login |

Separado da marca visual do **site** (CMS).

---

## 11. Auditoria

**Status:** Funcionando

| Funcionalidade | Como funciona |
|----------------|---------------|
| Consulta de logs | `/auditoria` — cadastros, alterações, logins (conforme capturado) |
| Permissão | `auditoria.view` / `telas.auditoria` |

**Não tem (ainda):** export avançado / alertas dedicados na UI.

---

## 12. Removido

| Item | Status | Nota |
|------|--------|------|
| Escalas genéricas do núcleo | Removido | [ADR-0005](../architecture/ADR-0005-escalas-nucleo.md) superseded; SPEC-007 rejected |

---

## Mapa rápido de URLs

| Área | Paths |
|------|--------|
| Público | `/`, `/site/:slug`, `/site/:slug/:page`, `/calendario/coleta/:token` |
| Auth | `/entrar`, `/admin/login` |
| Plataforma | `/admin/tenants` |
| Shell | `/inicio` |
| Núcleo | `/igrejas`, `/usuarios`, `/auditoria`, `/configuracoes/marca` |
| ECC | `/ecc/minha-equipe`, `/ecc/casais`, `/ecc/casais/:id`, `/ecc/eventos`, `/ecc/eventos/:id`, `/ecc/financeiro` |
| Eventos | `/eventos`, `/eventos/:id` |
| Calendário | `/calendario`, `/calendario/:id`, `/calendario/locais`, `/calendario/tipos` |
| Pastorais | `/pastorais`, `/pastorais/:id`, `/planejamento`, `/planejamento/:id` |
| Site CMS | `/site` |

---

## Specs × presença no produto

| Spec | No produto |
|------|------------|
| SPEC-001 Admin tenants | Sim |
| SPEC-002 ECC equipes/casais | Sim |
| SPEC-003 Usuários/papéis | Sim |
| SPEC-004 Igrejas + seletor | Sim |
| SPEC-005 Site público | Sim |
| SPEC-006 Foto pessoa | Sim (via ECC) |
| SPEC-007 Escalas genéricas | Removido |
| SPEC-008 Eventos/compras | Sim |
| SPEC-009 Caixa evento | Sim |
| SPEC-010 Sessão multi-dispositivo | Sim (API) |
| SPEC-011 Branding site | Sim |
| SPEC-012 Calendário oficial | Sim |
| SPEC-013 Auditoria | Sim |
| SPEC-014 Branding app | Sim |
| SPEC-015 Ficha casal | Sim |
| SPEC-016 ECC financeiro | Sim |
| SPEC-017 Home líder | Parcial (spec draft) |
| SPEC-018 Pastorais/planejamento | Sim |
| SPEC-022 Visão gestor calendário | Sim |

---

## Manutenção deste documento

Ao entregar uma feature usável ponta a ponta:

1. Atualizar a seção do módulo (status + tabela “como funciona”).
2. Ajustar o mapa de URLs e a tabela Specs, se couber.
3. Manter o [`README`](../../README.md) alinhado na tabela “O que já está no código”.
