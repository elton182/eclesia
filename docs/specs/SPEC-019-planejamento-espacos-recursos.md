# SPEC-019 — Espaços e recursos no Planejamento Anual

**Status:** approved  
**Data:** 2026-10-14  
**ADR:** [ADR-0008](../architecture/ADR-0008-espacos-recursos-planejamento.md)  
**Emenda:** [SPEC-018](./SPEC-018-pastorais-planejamento-anual.md) (conflito de espaço/recurso passa a bloquear)

## Contexto

No Planejamento Anual (SPEC-018) várias pastorais propõem eventos no mesmo dia. Dois eventos podem coexistir desde que usem **espaços diferentes** (salas, salão, auditório) e que os **recursos** (projetor, som, cadeiras extras) sejam suficientes. Um projetor móvel circula por várias salas, mas se a paróquia só tem 1, ele não pode estar em dois eventos ao mesmo tempo.

Os "Locais" da Agenda Paroquial (`calendario_locais`, ADR-0006) são colunas da grade de celebrações (Matriz, capelas) e não representam salas internas nem equipamentos.

## Objetivo

1. Catálogo por igreja de **espaços** e **recursos**, cada item com `quantidade`.
2. Alocação desses itens nos eventos do planejamento, por data/horário.
3. Bloqueio na gravação quando a alocação ultrapassa o disponível no intervalo.
4. Consulta de disponibilidade para a UI (autenticada e link público da pastoral).

## Critérios de aceite (testáveis)

### Catálogo

- [x] Tabela `planejamento_recursos` (tenant): `id` ULID, `igreja_id`, `nome`, `tipo` ∈ `espaco` \| `recurso`, `quantidade` ≥ 1, `descricao`, `ativo`, `ordem`; único (`igreja_id`, `nome`).
- [x] Espaço tem sempre `quantidade = 1`.
- [x] `GET /api/v1/planejamento/recursos`: quem vê o planejamento lista; sem `planejamento.gerir` só itens ativos.
- [x] `POST|PATCH|DELETE /api/v1/planejamento/recursos/{id}` exigem `planejamento.gerir`. DELETE de item já usado em evento apenas inativa (`ativo = false`).
- [x] Isolamento por igreja.

### Alocação e bloqueio

- [x] Evento aceita `recursos: [{recurso_id, quantidade}]` (pivot `planejamento_evento_recurso`). Para espaço, quantidade é 1.
- [x] Ocupação de um item = soma das quantidades dos outros eventos do mesmo ano que usam o item, têm intervalo sobreposto e não estão `recusado`.
- [x] `ocupado + pedido > quantidade` → HTTP 422 com `errors.recursos` e `conflitos_recursos[]`.
- [x] Intervalo: `[data_inicio + hora_inicio, (data_fim ?? data_inicio) + hora_fim)`. Sem hora = dia inteiro; só `hora_inicio` = 1h. Intervalos encostados (10h–11h e 11h–12h) não conflitam.
- [x] Regra aplicada em criação, edição (inclui mudança de data por drag & drop), criação via link público e mudança de status saindo de `recusado`.
- [x] Item inativo não pode ser alocado em novos eventos.
- [x] Espaço alocado satisfaz a exigência de "local ou texto de local" da SPEC-018.

### Disponibilidade

- [x] `GET /api/v1/planejamento/anuais/{id}/disponibilidade?data_inicio&data_fim&hora_inicio&hora_fim&ignorar_evento_id` → `[{recurso_id, nome, tipo, quantidade, disponivel}]` dos itens ativos.
- [x] `GET /api/v1/public/planejamento/{token}/disponibilidade` com os mesmos parâmetros.
- [x] No link público, `conflitos_recursos[]` traz apenas `recurso_id`, `nome`, `disponivel` e `pedido` — sem eventos de outras pastorais.

### UI

- [x] Tela "Espaços e recursos" (`/planejamento/recursos`) para gestor: lista, criar/editar, inativar.
- [x] Modal do evento: espaços em checkbox (ocupados desabilitados) e recursos com quantidade e "x de N livres", consultando disponibilidade ao mudar data/hora.
- [x] 422 de recurso mostra mensagem com o motivo; drag & drop para horário ocupado volta o evento à data original.
- [x] Link público: mesma seleção no formulário do modal do dia.

## Fora de escopo

- Reserva de espaços/recursos pela Agenda Paroquial (SPEC-012) ou módulo Eventos.
- Recurso "preso" a um espaço (ex.: projetor fixo do auditório) — cadastrar como recurso com quantidade própria.
- Manutenção/indisponibilidade programada de itens.

## Contrato de API

[`api/docs/specs/openapi.yaml`](../../api/docs/specs/openapi.yaml) — paths `/planejamento/recursos*`, `/planejamento/anuais/{id}/disponibilidade`, `/public/planejamento/{token}/disponibilidade` e campo `recursos` nos eventos.

## Notas

- O alerta de conflito por `calendario_locais` da SPEC-018 continua não bloqueante; o bloqueio vale só para espaços/recursos desta spec.
- Concorrência: validação dentro de transação com `lockForUpdate` nas linhas dos itens envolvidos.
