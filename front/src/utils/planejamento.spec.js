import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelStatusPlanejamento,
  labelStatusSolicitacao,
  styleStatusSolicitacao,
  labelPapelMembro,
  proximosStatusPlanejamento,
  groupEventosByDay,
  filterEventosPorMes,
  filterEventosPorSemana,
  filterEventosPorPastoraisVisiveis,
  togglePastoralVisivel,
  corPastoralPorIndice,
  mapaCoresPastorais,
  localIdsDoEvento,
  intervaloEventoMs,
  anotarConflitosEventos,
  contarEventosComConflito,
  canCriarEventoPlanejamento,
  canEditarEventoPlanejamento,
  canEditarEventoPlanejamentoPublico,
  canAlterarStatusSolicitacao,
  formatConflitosAlert,
  labelEventoPlanejamentoPreview,
  formatHoraCurta,
  filterUsersBySearch,
  unwrapList,
  unwrapItem,
  pastoralIdsDoUsuario,
  mapPastoralLinks,
  horasGradeSemana,
  minutosDoDia,
  layoutEventoGradeSemana,
  particionarEventosGradeSemana,
} from './planejamento.js'

describe('planejamento', () => {
  it('labels de status do ano e solicitação', () => {
    assert.equal(labelStatusPlanejamento('coleta'), 'Coleta')
    assert.equal(labelStatusPlanejamento('revisao'), 'Revisão')
    assert.equal(labelStatusSolicitacao('ajuste_solicitado'), 'Ajuste solicitado')
    assert.equal(styleStatusSolicitacao('confirmado').fg, '#1f6b3a')
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

  it('filterEventosPorSemana Dom–Sáb', () => {
    const eventos = [
      { id: '1', data_inicio: '2026-10-10' }, // sáb anterior
      { id: '2', data_inicio: '2026-10-11' }, // dom
      { id: '3', data_inicio: '2026-10-14' },
      { id: '4', data_inicio: '2026-10-18' }, // dom seguinte
    ]
    const semana = filterEventosPorSemana(eventos, '2026-10-11')
    assert.deepEqual(
      semana.map((e) => e.id),
      ['2', '3'],
    )
  })

  it('filtro e toggle de pastorais visíveis + cores', () => {
    const eventos = [
      { id: '1', pastoral_id: 'p1' },
      { id: '2', pastoral_id: 'p2' },
      { id: '3', pastoral_id: 'p1' },
    ]
    assert.equal(filterEventosPorPastoraisVisiveis(eventos, ['p1']).length, 2)
    assert.equal(filterEventosPorPastoraisVisiveis(eventos, new Set()).length, 0)
    assert.equal(filterEventosPorPastoraisVisiveis(eventos, null).length, 3)
    const toggled = togglePastoralVisivel(['p1', 'p2'], 'p1')
    assert.equal(toggled.has('p1'), false)
    assert.equal(toggled.has('p2'), true)
    assert.equal(corPastoralPorIndice(0), '#4E1220')
    assert.equal(mapaCoresPastorais([{ id: 'p1' }, { id: 'p2' }]).p2, corPastoralPorIndice(1))
  })

  it('anotarConflitosEventos detecta sobreposição de local/horário', () => {
    assert.deepEqual(localIdsDoEvento({ locais: [{ id: 'l1' }] }), ['l1'])
    const intervalo = intervaloEventoMs({
      data_inicio: '2026-05-01',
      hora_inicio: '19:00',
      hora_fim: '21:00',
    })
    assert.ok(intervalo)
    assert.equal(intervalo.fim - intervalo.inicio, 2 * 60 * 60 * 1000)

    const anotados = anotarConflitosEventos([
      {
        id: 'a',
        titulo: 'Retiro',
        pastoral_id: 'p1',
        data_inicio: '2026-05-01',
        hora_inicio: '19:00',
        hora_fim: '21:00',
        local_ids: ['sala'],
      },
      {
        id: 'b',
        titulo: 'Ensaio',
        pastoral_id: 'p2',
        pastoral_nome: 'Música',
        data_inicio: '2026-05-01',
        hora_inicio: '20:00',
        hora_fim: '22:00',
        local_ids: ['sala'],
      },
      {
        id: 'c',
        titulo: 'Outro local',
        pastoral_id: 'p3',
        data_inicio: '2026-05-01',
        hora_inicio: '20:00',
        hora_fim: '22:00',
        local_ids: ['outro'],
      },
    ])
    assert.equal(anotados.find((e) => e.id === 'a').tem_conflito, true)
    assert.equal(anotados.find((e) => e.id === 'b').tem_conflito, true)
    assert.equal(anotados.find((e) => e.id === 'c').tem_conflito, false)
    assert.equal(contarEventosComConflito(anotados), 2)
    assert.match(anotados.find((e) => e.id === 'a').conflitos[0].titulo, /Ensaio/)
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

  it('canEditarEventoPlanejamentoPublico segue coleta e ajuste', () => {
    const ev = { status_solicitacao: 'proposta' }
    assert.equal(canEditarEventoPlanejamentoPublico('coleta', ev), true)
    assert.equal(canEditarEventoPlanejamentoPublico('revisao', ev), false)
    assert.equal(
      canEditarEventoPlanejamentoPublico('revisao', { status_solicitacao: 'ajuste_solicitado' }),
      true,
    )
    assert.equal(canEditarEventoPlanejamentoPublico('fechado', ev), false)
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

  it('mapPastoralLinks mantém só links ativos com token', () => {
    assert.deepEqual(
      mapPastoralLinks([
        { pastoral_id: 'p1', token: 'abc', ativo: true },
        { pastoral_id: 'p2', token: null, ativo: true },
        { pastoral_id: 'p3', token: 'zzz', ativo: false },
        null,
      ]),
      { p1: 'abc' },
    )
    assert.deepEqual(mapPastoralLinks(undefined), {})
  })

  it('grade semanal com horas posiciona eventos', () => {
    assert.deepEqual(horasGradeSemana(6, 8), ['06:00', '07:00', '08:00'])
    assert.equal(minutosDoDia('19:30:00'), 19 * 60 + 30)
    assert.equal(minutosDoDia(null), null)

    const layout = layoutEventoGradeSemana(
      { hora_inicio: '08:00', hora_fim: '10:00' },
      { inicioHora: 6, fimHora: 22 },
    )
    assert.ok(layout)
    assert.ok(layout.topPct > 0)
    assert.ok(layout.heightPct > 0)
    assert.equal(layoutEventoGradeSemana({ titulo: 'Sem hora' }), null)

    const parts = particionarEventosGradeSemana([
      { id: '1', hora_inicio: '19:00' },
      { id: '2', hora_inicio: null },
    ])
    assert.equal(parts.comHora.length, 1)
    assert.equal(parts.semHora.length, 1)
  })
})
