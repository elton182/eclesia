import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelStatusCalendario,
  labelMesCalendario,
  proximosStatusCalendario,
  agruparItensPorData,
  indexarIndisponibilidades,
  gradeMesCalendario,
  toggleDataSelecionada,
  labelDiaCurto,
  resumoDiaMontagem,
  formatHoraCalendario,
  labelItemPreview,
  previewLinhasDia,
  secaoGradePorData,
  numeroObservacao,
  labelObservacaoOpcao,
  proximoMesCalendario,
  toDayKey,
  groupOcorrenciasByDay,
  buildMonthGrid,
  toDatetimeLocal,
} from './calendario.js'

describe('calendario', () => {
  it('toDayKey formata data local', () => {
    assert.equal(toDayKey('2026-09-20T10:00:00-03:00'), '2026-09-20')
    assert.equal(toDayKey(null), '')
  })

  it('groupOcorrenciasByDay agrupa por dia', () => {
    const map = groupOcorrenciasByDay([
      { id: '1', inicia_em: '2026-09-20T10:00:00-03:00' },
      { id: '2', inicia_em: '2026-09-20T18:00:00-03:00' },
      { id: '3', inicia_em: '2026-09-21T10:00:00-03:00' },
    ])
    assert.equal(map.get('2026-09-20').length, 2)
    assert.equal(map.get('2026-09-21').length, 1)
  })

  it('buildMonthGrid tem semanas de 7 dias', () => {
    const weeks = buildMonthGrid(2026, 8) // setembro 2026
    assert.ok(weeks.length >= 4)
    assert.equal(weeks[0].length, 7)
    const flat = weeks.flat().filter(Boolean)
    assert.equal(flat.length, 30)
  })

  it('toDatetimeLocal monta valor para input', () => {
    assert.equal(toDatetimeLocal('2026-09-20'), '2026-09-20T10:00')
    assert.equal(toDatetimeLocal('2026-09-20', '18:30'), '2026-09-20T18:30')
    assert.equal(toDatetimeLocal(''), '')
  })
  it('labels de status e mês', () => {
    assert.equal(labelStatusCalendario('rascunho'), 'Rascunho')
    assert.equal(labelMesCalendario(2026, 9), 'Setembro/2026')
  })

  it('transições de status', () => {
    assert.deepEqual(proximosStatusCalendario('rascunho'), ['coleta', 'montagem'])
    assert.deepEqual(proximosStatusCalendario('fechado'), ['montagem'])
  })

  it('agrupa itens e indisponibilidades', () => {
    const grupos = agruparItensPorData([
      { data: '2026-09-06', secao: 'fds', celebrante_nome: 'Pe. A' },
      { data: '2026-09-06', secao: 'festa', titulo: 'Festa' },
      { data: '2026-09-07', secao: 'semana' },
    ])
    assert.equal(grupos.get('2026-09-06').length, 1)
    assert.equal(grupos.get('2026-09-07').length, 1)

    const ind = indexarIndisponibilidades([
      { data: '2026-09-15', nome_exibicao: 'Diác. Gerson' },
      { data: '2026-09-15', nome_exibicao: 'Pe. Marcos' },
    ])
    assert.deepEqual(ind.get('2026-09-15'), ['Diác. Gerson', 'Pe. Marcos'])
  })

  it('monta grade Dom–Sáb do mês', () => {
    // Set/2026 começa na terça (getDay=2)
    const cells = gradeMesCalendario(2026, 9)
    assert.equal(cells.length % 7, 0)
    assert.equal(cells[0].inMonth, false)
    assert.equal(cells[1].inMonth, false)
    assert.equal(cells[2].iso, '2026-09-01')
    assert.equal(cells.filter((c) => c.inMonth).length, 30)
    assert.equal(gradeMesCalendario(0, 9).length, 0)
  })

  it('alterna datas selecionadas e formata label curto', () => {
    assert.deepEqual(toggleDataSelecionada([], '2026-09-15'), ['2026-09-15'])
    assert.deepEqual(toggleDataSelecionada(['2026-09-15'], '2026-09-15'), [])
    assert.deepEqual(toggleDataSelecionada(['2026-09-20'], '2026-09-10'), [
      '2026-09-10',
      '2026-09-20',
    ])
    assert.equal(labelDiaCurto('2026-09-15'), '15 set')
  })

  it('resumo do dia para montagem', () => {
    const itens = new Map([['2026-09-15', [{ id: 1 }]]])
    const ind = new Map([['2026-09-15', ['Pe. A']]])
    const r = resumoDiaMontagem('2026-09-15', itens, ind)
    assert.equal(r.temGrade, true)
    assert.equal(r.temIndisponivel, true)
    assert.equal(resumoDiaMontagem('2026-09-16', itens, ind).temGrade, false)
  })

  it('preview de itens com nome e horário', () => {
    assert.equal(formatHoraCalendario('19:30:00'), '19:30')
    assert.equal(
      labelItemPreview({
        hora: '19:30:00',
        celebrante_nome: 'Pe. João',
        tipo: { nome: 'Missa' },
      }),
      '19:30 · Missa · Pe. João',
    )
    assert.equal(
      labelItemPreview({ hora: '10:00', titulo: 'Solenidade', secao: 'festa' }),
      '10:00 · Solenidade',
    )
    assert.deepEqual(
      previewLinhasDia([
        { hora: '19:30', celebrante_nome: 'Pe. B', secao: 'fds', tipo: { nome: 'Missa' } },
        { hora: '07:00', titulo: 'Festa', secao: 'festa', tipo: { nome: 'Festa' } },
      ]),
      ['07:00 · Festa', '19:30 · Missa · Pe. B'],
    )
  })

  it('secao da grade por dia da semana', () => {
    assert.equal(secaoGradePorData('2026-10-13'), 'semana') // terça
    assert.equal(secaoGradePorData('2026-10-11'), 'fds') // domingo
  })

  it('numera observações e monta label', () => {
    const obs = [
      { id: 'a', titulo: 'Missa crianças', ordem: 1 },
      { id: 'b', titulo: 'ECC', ordem: 2 },
    ]
    assert.equal(numeroObservacao(obs, 'b'), 2)
    assert.equal(numeroObservacao(obs, 'x'), null)
    assert.equal(labelObservacaoOpcao({ titulo: 'ECC', numero: 2 }), '2) ECC')
  })

  it('calcula próximo mês', () => {
    assert.deepEqual(proximoMesCalendario(2026, 9), { ano: 2026, mes: 10 })
    assert.deepEqual(proximoMesCalendario(2026, 12), { ano: 2027, mes: 1 })
  })
})
