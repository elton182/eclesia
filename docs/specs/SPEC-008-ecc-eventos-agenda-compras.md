# SPEC-008 — ECC: eventos, agenda unificada e compras

**Status:** approved  
**Data:** 2026-09-16  
**Depende de:** SPEC-002 (casais), SPEC-004 (igreja), `evento_agenda` (núcleo)  
**Plano:** [PLAN-001](../plans/PLAN-001-fundacao-nucleo-ecc.md)  
**ADR:** [ADR-0005](../architecture/ADR-0005-escalas-nucleo.md) (superseded — só o store `EventoAgenda` permanece)

## Contexto

O BRIEF prevê agenda unificada no núcleo (`EventoAgenda`) e tela **Eventos (Lista | Calendário)** no ECC, com tipos `encontro`, `anual`, `servos`, `perseveranca`, `formacao`. O store `evento_agenda` vive no núcleo. Esta fatia faz o ECC **publicar** nesse store e adiciona lista de compras do evento **anual**. Caixa e recorrência ficam fora.

## Objetivo

No banco do tenant, escopado por `igreja_id`:

1. CRUD de **eventos ECC** com tipos do protótipo.
2. Ao criar/atualizar/excluir: sincronizar projeção em **`evento_agenda`** (`dono_modulo=ecc`).
3. **Participantes** = casais (N:N).
4. **Lista de compras** só para `tipo=anual` (statuses `pendente` | `doado` | `comprado`); comprar grava `valor_gasto` **sem** caixa.
5. Telas no front: Eventos Lista | Calendário; detalhe; compras no anual.

## Modelo

| Entidade | Tabela | Notas |
|----------|--------|-------|
| EventoAgenda | `evento_agenda` | Já existe; ECC usa `dono_modulo=ecc`, `tipo` = tipo do evento, `referencia_tipo=ecc_evento` |
| EccEvento | `ecc_eventos` | `titulo`, `tipo`, `inicia_em`, `termina_em?`, `local?`, `casal_compras_id?`, `evento_agenda_id` |
| Participante | `ecc_evento_casal` | `ecc_evento_id` + `casal_id` (unique) |
| ItemCompra | `ecc_itens_compra` | `nome`, `qtd`, `unidade`, `status`, `doador_casal_id?`, `valor_gasto?` |

Tipos: `encontro` \| `anual` \| `servos` \| `perseveranca` \| `formacao`.

## Regras

1. Isolamento por `igreja_id` (`IgrejaContext` / `X-Igreja`).
2. Create/update/delete de `ecc_eventos` sincroniza `evento_agenda`; delete remove a projeção.
3. Participante duplicado no mesmo evento → **422**.
4. Endpoints de itens-compra → **422** se o evento não for `anual`.
5. Doar: só de `pendente` → `doado` + `doador_casal_id`.
6. Comprar: só de `pendente` → `comprado` + `valor_gasto` (≥ 0); **não** cria movimento de caixa.
7. Desfazer: volta a `pendente`, limpa doador e `valor_gasto`.
8. Permissões: `telas.eventos`, `ecc.eventos.view`, `ecc.eventos.manage`.

## Critérios de aceite (testáveis)

- [x] Migration tenant: `ecc_eventos`, `ecc_evento_casal`, `ecc_itens_compra`.
- [x] CRUD `/api/v1/ecc/eventos` com `X-Tenant` (+ `X-Igreja`); listagem com `from`/`to`/`tipo`.
- [x] Create/update/delete sincroniza `evento_agenda` (`dono_modulo=ecc`).
- [x] Participantes: POST/DELETE sob `/ecc/eventos/{id}/participantes`.
- [x] Itens-compra (anual): CRUD + doar / comprar / desfazer; 422 se tipo ≠ anual.
- [x] 401 sem auth; 403 sem permissão; isolamento por igreja/tenant.
- [x] Seed: `telas.eventos`, `ecc.eventos.view`, `ecc.eventos.manage` em admin-tenant / admin-igreja; líder vê (`view`).
- [x] Front: nav Eventos; `/ecc/eventos` Lista \| Calendário; detalhe; compras no anual.
- [x] Testes API (PHPUnit) + util front (filtros/agenda).

## Fora de escopo

- Caixa, doações em dinheiro, extrato, saldo.
- Recorrência / geração automática de reuniões de perseverança.
- Equipes tipadas de perseverança, escala ECC, ata/presença.
- Agenda pública do Site (`agenda_eventos` CMS).
- Misturar eventos de `escalas` na UI ECC (visão multi-módulo futura).

## Contrato de API

Ver `api/docs/specs/openapi.yaml` — paths `/ecc/eventos*`.

## Notas

- ULIDs em IDs públicos.
- UX de referência: `prototipo/ecc-gestao-prototipo.html` (seção Eventos + lista de compras do anual).
- `valor_gasto` fica no item para a futura spec de caixa consumir.
- **Tipos cadastráveis** em `evento_tipos` (`/api/v1/eventos/tipos`); escopo `ecc` \| `geral` \| `ambos`.
- **Origem** do evento: `ecc` (`/ecc/eventos`) ou `geral` (`/eventos` no launcher).
- Participante: campo `convidados` (nº de pessoas externas convidadas pelo casal).
