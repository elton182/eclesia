# SPEC-016 — Planejamento Anual (pastorais)

**Status:** approved  
**Data:** 2026-10-13  
**Aprovado:** 2026-10-13  
**Plano:** [PLAN-004](../plans/PLAN-004-agenda-paroquial-planejamento-anual.md)  
**Relacionado:** cadastro `pastorais` ([SPEC-005](SPEC-005-site-publico-tenant.md)); Agenda Paroquial = rename do Calendário oficial ([SPEC-012](SPEC-012-calendario-oficial.md))

## Contexto

A paróquia precisa planejar o ano civil com as pastorais: cada pastoral propõe datas/eventos; o padre/admin revisa, reorganiza (incl. drag & drop) e fecha o plano. Isso é **separado** da Agenda Paroquial (missas, casamentos, batismos — SPEC-012) e do módulo Eventos gerais.

O cadastro de pastorais é **único**: tabela `pastorais` já usada pelo CMS do Site. A área de pastorais no Planejamento Anual usa o mesmo CRUD/recurso.

## Objetivo

1. Módulo **Planejamento Anual** no launcher (ícone próprio, distinto da Agenda Paroquial).
2. Padre/admin **abre o ano civil** (jan–dez) e libera coleta das pastorais.
3. Pastoral contribui via **login** (permissão) **ou link tokenizado** da pastoral no ano.
4. Tela principal: calendário estilo Google Calendar; clique no dia abre modal (criar/listar eventos do dia).
5. Lateral: filtros por pastoral (mostrar/ocultar na grade).
6. Padre: revisar status, alterar data por **drag & drop**, fechar o ano.
7. Tags coloridas por status do evento (e indicação visual do status do ano).

## Decisões de produto (fechadas)

| Tema | Decisão |
|------|---------|
| Agenda Paroquial | Rename do Calendário oficial (SPEC-012); rotas API `/calendario/*` podem permanecer |
| Módulo Eventos | Permanece **separado** por hora |
| Pastorais | Mesmo cadastro CMS (`pastorais`); UI no módulo também CRUD nesse recurso |
| Acesso pastoral | Login **e** link público tokenizado |
| Ano | **Civil** (1 jan – 31 dez) |
| Sync Agenda / `evento_agenda` | **Fora de escopo** por hora (mundos separados) |
| Status evento | `proposta` → `em_revisao` → `aprovado` → `rejeitado` |
| Status ano | `aberto` → `coleta` → `fechado` |
| Tags | Cores por status na UI |

## Modelo (tenant)

```
Pastoral                    (já existe — SPEC-005)
PastoralUser                (pivot pastoral_id × user_id — vínculo para `planejamento.propor`)
PlanejamentoAnual
  ├─ igreja_id, ano (unique por igreja)
  ├─ status: aberto | coleta | fechado
  ├─ aberto_em?, coleta_em?, fechado_em?
  └─ eventos[]  PlanejamentoEvento
        ├─ pastoral_id
        ├─ titulo, descricao?
        ├─ data_inicio, data_fim? (ou só data + hora opcional)
        ├─ dia_inteiro (bool)
        ├─ status: proposta | em_revisao | aprovado | rejeitado
        ├─ criado_via: login | link
        └─ notas_revisao? (padre)
PlanejamentoPastoralToken
  ├─ planejamento_anual_id
  ├─ pastoral_id
  ├─ token (hash em DB; plain só na criação/rotação)
  ├─ expires_at?
  └─ revogado_em?
```

- ULID nas URLs públicas.
- Scoped por `igreja_id`.
- Ano fechado: pastoral não cria/edita; padre só leitura (exceto reabrir — se permitido).

### Tags de status (UI)

| Status evento | Cor sugerida (token / classe) |
|---------------|-------------------------------|
| `proposta` | neutro / muted |
| `em_revisao` | cobre / warning |
| `aprovado` | verde sucesso |
| `rejeitado` | vermelho / danger |

Status do ano: chip no cabeçalho (`aberto` / `coleta` / `fechado`).

## Fluxo

1. Padre cria/abre `PlanejamentoAnual` do ano → status `aberto`.
2. Transição para `coleta`: gera/ativa tokens por pastoral (ou sob demanda); pastorais podem lançar.
3. Pastoral (login ou link) cria eventos em `proposta`.
4. Padre move para `em_revisao` / `aprovado` / `rejeitado`; pode drag & drop mudar data; filtros laterais por pastoral.
5. Padre fecha ano → `fechado` (sem novas propostas; edição restrita).

## Critérios de aceite (testáveis)

- [ ] CRUD ano por igreja (`ano` único); transições `aberto` → `coleta` → `fechado` válidas; `fechado` bloqueia criação por pastoral.
- [ ] CRUD eventos do planejamento com `pastoral_id`, datas, status; isolamento tenant/igreja; ULID.
- [ ] Padre com `planejamento.gerir`: listar/filtrar, mudar status, drag & drop (PATCH data), abrir/fechar ano.
- [ ] Usuário com `planejamento.propor` (ou papel de pastoral): criar/editar só eventos da(s) pastoral(is) vinculada(s) enquanto ano em `coleta` (e próprios em `proposta` / regras finas abaixo).
- [ ] Link público: `GET/POST /public/planejamento/{token}/...` — listar/criar eventos da pastoral do token sem auth; token revogável/rotacionável.
- [ ] Cadastro de pastorais no módulo = mesmos endpoints/recurso `pastorais` do Site (sem tabela duplicada).
- [ ] Front launcher: ícone **Planejamento Anual**; calendário principal + modal do dia + filtros laterais + tags coloridas.
- [ ] Drag & drop só para quem tem `planejamento.gerir` (padre/admin).
- [ ] Testes API (401/403/422/sucesso/isolamento) + Vitest nas views críticas do calendário/filtros/status.

### Regras finas

- Pastoral no link: só CRUD dos eventos daquela `pastoral_id` do token; **sem** leitura cruzada de outras pastorais.
- Usuário com `planejamento.propor`: criar/editar só eventos das pastorais às quais está vinculado; só enquanto ano em `coleta`; edição própria limitada a status `proposta` (padre altera demais status).
- Padre (`planejamento.gerir`) em `aberto`/`coleta`: vê todas as pastorais; pode editar qualquer evento e alterar datas (drag & drop).
- Ano `fechado`: pastoral não cria/edita; drag & drop e edição de eventos bloqueados (somente leitura); reabrir ano exige `planejamento.gerir` (transição `fechado` → `coleta` ou `aberto`).

## Fora de escopo

- Publicação em Agenda Paroquial / `evento_agenda`.
- Integração com módulo Eventos gerais.
- Ano litúrgico.
- App nativo.
- Membros/reuniões completas do roadmap “Pastorais” (só cadastro compartilhado + propostas de datas).
- PDF do planejamento (pode ser fatia futura).

## Contrato de API

OpenAPI: `api/docs/specs/openapi.yaml` — paths `/planejamento/*` e `/public/planejamento/{token}`.

Pastorais: reutilizar `/site/pastorais` (mesmo model `Pastoral`).

## UI (front)

- Rota módulo: `/planejamento` (ModuleLayout).
- Nav: Calendário (principal) · Pastorais (CRUD compartilhado) · (opcional) Tokens/links.
- Calendário: grade mensal (e/ou semana) semelhante a Google Calendar; eventos como blocos/chips com cor de status (+ cor/etiqueta da pastoral se útil).
- Clique no dia → modal: lista do dia + formulário de novo evento.
- Lateral: checklist de pastorais (toggle visibilidade).
- Empty state quando o ano ainda não foi aberto.

## Notas

- SPEC-012 continua sendo a fonte do domínio “oficial mensal”; só o **nome de produto/UI** passa a **Agenda Paroquial** (ver PLAN-004).
- Permissões seed: `planejamento.gerir`, `planejamento.propor` (+ leitura se necessário).
- PII no link público: evitar expor dados pessoais de usuários; título/descrição do evento são conteúdo operacional da pastoral (não criptografar a menos que haja PII — se houver nome de pessoa no texto, tratar como dado operacional por hora).
