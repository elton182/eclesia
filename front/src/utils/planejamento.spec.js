import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelStatusPlanejamento,
  labelStatusSolicitacao,
  labelPapelMembro,
  proximosStatusPlanejamento,
  groupEventosByDay,
  filterEventosPorMes,
  canCriarEventoPlanejamento,
  canEditarEventoPlanejamento,
  canAlterarStatusSolicitacao,
  formatConflitosAlert,
  labelEventoPlanejamentoPreview,
  formatHoraCurta,
  filterUsersBySearch,
  unwrapList,
  unwrapItem,
  pastoralIdsDoUsuario,
} from './planejamento.js'

describe('planejamento', () => {
  it('labels de status do ano e solicitação', () => {
    assert.equal(labelStatusPlanejamento('coleta'), 'Coleta')
    assert.equal(labelStatusPlanejamento('revisao'), 'Revisão')
    assert.equal(labelStatusSolicitacao('ajuste_solicitado'), 'Ajuste solicitado')
    assert.equal(labelPapelMembro('coordenador'), 'Coordenador')
  })

  it('transições de status do ano', () => {
    assert.deepEqual(proximosStatusPlanejamento('rascunho'), ['coleta'])
    assert.deepEqual(proximosStatusPlanejamento('coleta'), ['revisao', 'rascunho'])
    assert.deepEqual(proximosStatusPlanejamento('revisao'), ['fechado', 'coleta'])
    assert.deepEqual(proximosStatusPlanejamento('fechado'), ['revisao'])
    assert.deepEqual(proximosStatusPlanejamento('x'), [])
  })

  it('groupEventosByDay e filterEventosPorMes', () => {
    const eventos = [
      { id: '1', data_inicio: '2026-03-10', titulo: 'A' },
      { id: '2', data_inicio: '2026-03-10', titulo: 'B' },
      { id: '3', data_inicio: '2026-04-01', titulo: 'C' },
    ]
    const byDay = groupEventosByDay(eventos)
    assert.equal(byDay.get('2026-03-10').length, 2)
    assert.equal(filterEventosPorMes(eventos, 3).length, 2)
    assert.equal(filterEventosPorMes(eventos, 4).length, 1)
  })

  it('canCriarEventoPlanejamento por papel e status', () => {
    assert.equal(canCriarEventoPlanejamento('coleta', { canPropor: true }), true)
    assert.equal(canCriarEventoPlanejamento('revisao', { canPropor: true }), false)
    assert.equal(canCriarEventoPlanejamento('coleta', { canGerir: true }), true)
    assert.equal(canCriarEventoPlanejamento('fechado', { canGerir: true }), false)
    assert.equal(canCriarEventoPlanejamento('rascunho', { canGerir: true }), true)
  })

  it('canEditarEventoPlanejamento respeita escopo e ajuste', () => {
    const ev = { pastoral_id: 'p1', status_solicitacao: 'proposta' }
    assert.equal(
      canEditarEventoPlanejamento('coleta', ev, { canPropor: true, pastoralIds: ['p1'] }),
      true,
    )
    assert.equal(
      canEditarEventoPlanejamento('coleta', ev, { canPropor: true, pastoralIds: ['p2'] }),
      false,
    )
    assert.equal(
      canEditarEventoPlanejamento('revisao', ev, { canPropor: true, pastoralIds: ['p1'] }),
      false,
    )
    assert.equal(
      canEditarEventoPlanejamento(
        'revisao',
        { ...ev, status_solicitacao: 'ajuste_solicitado' },
        { canPropor: true, pastoralIds: ['p1'] },
      ),
      true,
    )
    assert.equal(canEditarEventoPlanejamento('fechado', ev, { canGerir: true }), false)
    assert.equal(canEditarEventoPlanejamento('coleta', ev, { canGerir: true }), true)
  })

  it('canAlterarStatusSolicitacao só gestor em status abertos', () => {
    assert.equal(canAlterarStatusSolicitacao('revisao', { canGerir: true }), true)
    assert.equal(canAlterarStatusSolicitacao('fechado', { canGerir: true }), false)
    assert.equal(canAlterarStatusSolicitacao('coleta', { canGerir: false }), false)
  })

  it('formatConflitosAlert resume conflitos', () => {
    assert.equal(formatConflitosAlert([]), '')
    const msg = formatConflitosAlert([
      { titulo: 'Retiro', pastoral_nome: 'Jovens', data_inicio: '2026-05-01' },
    ])
    assert.match(msg, /Retiro/)
    assert.match(msg, /Jovens/)
    assert.match(msg, /gravação foi mantida/)
  })

  it('preview e hora curta', () => {
    assert.equal(formatHoraCurta('19:30:00'), '19:30')
    assert.equal(
      labelEventoPlanejamentoPreview({
        hora_inicio: '09:00',
        titulo: 'Reunião',
        pastoral: { nome: 'Liturgia' },
      }),
      '09:00 · Reunião',
    )
  })

  it('filterUsersBySearch e unwrap', () => {
    const users = [
      { id: '1', name: 'Ana Silva', email: 'ana@x.com' },
      { id: '2', name: 'Bruno', email: 'bruno@paroquia.org' },
    ]
    assert.equal(filterUsersBySearch(users, 'ana').length, 1)
    assert.equal(filterUsersBySearch(users, 'paroquia').length, 1)
    assert.deepEqual(unwrapList({ data: [{ id: 1 }] }), [{ id: 1 }])
    assert.deepEqual(unwrapItem({ data: { id: 'a' } }), { id: 'a' })
    assert.deepEqual(pastoralIdsDoUsuario({ pastorais: [{ id: 'p1' }] }), ['p1'])
    assert.deepEqual(pastoralIdsDoUsuario({ pastoral_ids: ['p2'] }), ['p2'])
  })
})
