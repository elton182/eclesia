# SPEC-018 — Pastorais e calendário anual de planejamento

**Status:** approved  
**Data:** 2026-10-08  
**Plano:** [PLAN-004](../plans/PLAN-004-pastorais-planejamento-anual.md)  
**ADR:** [ADR-0003](../architecture/ADR-0003-site-publico-tenant.md) (entidade `pastorais`), [ADR-0006](../architecture/ADR-0006-calendario-locais-celebracao.md) (locais como catálogo de espaço, não hierarquia)  
**Relacionado:** [SPEC-005](./SPEC-005-site-publico-tenant.md), [SPEC-012](./SPEC-012-calendario-oficial.md)

## Contexto

Hoje o planejamento anual das pastorais é feito por formulário (Google Forms) + planilha: cada coordenador propõe atividades (data, horário, local, participantes); o padre revisa status (“Confirmar com o Padre”, “Reagendar…”) e consolida mês a mês.

O **calendário oficial mensal** (SPEC-012) cobre missas/celebrações do padre. Esta feature cobre o **calendário de planejamento pastoral do ano**, com contribuição por pastoral e revisão global pelo padre.

A tabela `pastorais` já existe para o site público (SPEC-005 / ADR-0003) e evolui aqui para cadastro de domínio + vínculo de usuários.

## Objetivo

1. Cadastro de pastorais/movimentos por igreja (reuso da entidade `pastorais`), com membros/coordenadores (usuários).
2. Calendário anual de planejamento por igreja + ano, com status: `rascunho` → `coleta` → `revisao` → `fechado`.
3. Cada pastoral (usuário vinculado) propõe eventos só da(s) sua(s) pastoral(is) durante `coleta` (e ajustes pedidas em `revisao`).
4. Padre/gestor vê o global, destaca conflitos (mesmo local + horário sobreposto), pede ajuste ou edita, e fecha o ano.
5. Conflito **não bloqueia** gravação — o padre decide (alerta na UI/API).
6. Sem ligação automática com o calendário oficial (SPEC-012) nesta versão.
7. Exportação lista/PDF do ano (ou por mês) quando `fechado` (ou em `revisao`).

## Critérios de aceite (testáveis)

### Cadastro de pastorais

- [ ] CRUD `pastorais` scoped por `igreja_id` (já parcial via site); campos de domínio: `nome`, `ativa`, `ordem`; campos de site (`descricao_publica`, `contato_publico`, `publicado_no_site`) permanecem.
- [ ] Pivot `pastoral_user` (`pastoral_id`, `user_id`, `papel` ∈ `coordenador` \| `membro`); único por par user+pastoral.
- [ ] Admin com `pastorais.manage` associa/desassocia usuários à pastoral.
- [ ] Isolamento tenant/igreja; ULID nas URLs.

### Acesso por pastoral

- [ ] Papel `coordenador-pastoral` com `telas.pastorais`, `pastorais.view`, `planejamento.propor`.
- [ ] Escopo: usuário com `planejamento.propor` só cria/edita/exclui eventos das pastorais em que está no pivot.
- [ ] `planejamento.gerir` (padre/`cadastros-calendario`/`admin-igreja`): vê e edita todos os eventos do ano; transiciona status do calendário anual; altera status de solicitação do evento.
- [ ] Seed: permissões + papel; `admin-tenant` / `admin-igreja` / `cadastros-calendario` recebem `planejamento.gerir` + gestão de pastorais conforme papel.

### Calendário anual

- [ ] CRUD `planejamento/anuais` (único por `igreja_id` + `ano`); cria em `rascunho`.
- [ ] Transições: `rascunho` → `coleta` → `revisao` → `fechado` (e reabertura só com `planejamento.gerir`: `fechado` → `revisao`).
- [ ] Em `fechado`, coordenador não edita eventos; gestor pode reabrir.
- [ ] Em `coleta`, coordenador CRUD eventos da própria pastoral; gestor CRUD qualquer.
- [ ] Em `revisao`, coordenador só edita eventos com status `ajuste_solicitado` da própria pastoral; gestor edita qualquer.

### Eventos

- [ ] Campos: `pastoral_id`, `titulo`, `data_inicio`, `data_fim` (nullable = mesmo dia), `hora_inicio`, `hora_fim` (nullable), `participantes_media` (nullable), `recorrencia_texto` (nullable), `observacoes` (nullable), `status_solicitacao`, `motivo_ajuste` (nullable).
- [ ] Locais: N:N com `calendario_locais` (catálogo de espaço da igreja — ADR-0006); pelo menos um local ou texto livre `local_texto` (legado/externo).
- [ ] `status_solicitacao` ∈ `proposta` \| `confirmado` \| `ajuste_solicitado` \| `reagendado` \| `recusado` \| `cotidiano`.
- [ ] Gestor altera `status_solicitacao` + `motivo_ajuste`; coordenador não altera status (exceto implícito `proposta` ao criar/reenviar após ajuste).
- [ ] Ao salvar, API pode retornar `conflitos[]` (outros eventos mesmo local + intervalo sobreposto no mesmo ano); HTTP 2xx mesmo com conflitos.

### Visões e export

- [ ] `GET` listagem filtrável: por ano, mês, pastoral_id, status_solicitacao, local_id.
- [ ] Visão agregada para gestor (todas as pastorais) e filtrada para coordenador (suas pastorais + leitura do global em `coleta`/`revisao`/`fechado` se tiver `planejamento.ver_global` — ver notas).
- [ ] `GET .../anuais/{id}/pdf` retorna PDF (grade/lista anual ou mensal via query `mes`).

### Front

- [ ] Tela cadastro de pastorais + membros (gated `telas.pastorais` / manage).
- [ ] Tela calendário de planejamento anual (visão mês/ano); coordenador propõe; gestor revisa com badge de conflito.
- [ ] Menu distinto do calendário oficial (SPEC-012).

## Fora de escopo

- Ligação / publicação automática no calendário oficial (SPEC-012) ou em `evento_agenda`.
- Bloqueio hard de conflito na gravação.
- Link público sem login (coleta só com usuário autenticado da pastoral).
- Recorrência estruturada (RRULE); só texto livre nesta versão.
- App nativo; importação automática da planilha Google (pode ser seed/manual depois).
- Sub-hierarquia comunidade/capela (ADR-0002).

## Contrato de API

OpenAPI: paths `/pastorais/*`, `/pastorais/{id}/membros`, `/planejamento/anuais*`, `/planejamento/anuais/{id}/eventos*`, `/planejamento/anuais/{id}/pdf`.

## Modelo

```
Pastoral (existente)
  └─ membros[]  pastoral_user (user_id, papel)

PlanejamentoAnual (igreja_id, ano, status)
  └─ eventos[]
        pastoral_id → Pastoral
        locais[] → calendario_locais (N:N)
        local_texto?
        status_solicitacao, motivo_ajuste
```

### Status do ano

| Status | Quem propõe | Quem revisa |
|--------|-------------|-------------|
| `rascunho` | gestor prepara (opcional) | — |
| `coleta` | coordenador (sua pastoral) | gestor vê global |
| `revisao` | coordenador só se `ajuste_solicitado` | gestor ajusta/confirma |
| `fechado` | ninguém (coordenador) | gestor pode reabrir → `revisao` |

## Notas

- **Dois calendários:** oficial mensal (padre/missas) ≠ planejamento anual (pastorais). Sem sync nesta versão.
- **Conflito:** heurística = interseção de intervalo `[data_inicio+hora_inicio, data_fim+hora_fim]` no mesmo `calendario_local_id`. Eventos só com `local_texto` não entram na detecção automática.
- **Visão global do coordenador:** por padrão o coordenador **vê** o calendário global em leitura (para evitar propor em choque óbvio) e **edita** só o seu. Permissão `planejamento.ver_global` no papel `coordenador-pastoral` (seed). Se a paróquia quiser ocultar, remove-se do papel.
- **Site:** `site.pastorais.*` continua para CMS; gestão de membros e planejamento usa `pastorais.*` / `planejamento.*`. Mesma tabela `pastorais`.
- **PII:** contato público do site permanece; telefone/nome de coordenador via `User`/`Pessoa` já criptografados — não duplicar PII no evento.
