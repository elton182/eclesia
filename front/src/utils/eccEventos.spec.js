import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  filterEventos,
  filterCasaisBusca,
  groupEventosByMonth,
  tipoAbrev,
  tipoLabel,
  statusCompraLabel,
  canConfirmDeleteEvento,
  buildEventoRelatorioHtml,
  resolveRelatorioSecoes,
  RELATORIO_TIPOS,
} from './eccEventos.js'

const tipos = [
  { id: 't1', codigo: 'servos', nome: 'Servos', abrev: 'Serv', cor: '#3a5f86' },
  { id: 't2', codigo: 'anual', nome: 'Jornada anual', abrev: 'Anual', cor: '#C88A5E' },
]

describe('eccEventos', () => {
  const eventos = [
    { id: '1', titulo: 'Reunião de servos', tipo: 'servos', evento_tipo_id: 't1', inicia_em: '2026-10-05T20:00:00', local: 'Salão' },
    { id: '2', titulo: 'Jornada anual', tipo: 'anual', evento_tipo_id: 't2', inicia_em: '2026-11-15T09:00:00', local: 'Sítio' },
    { id: '3', titulo: 'Formação de liderança', tipo: 'formacao', inicia_em: '2026-10-20T19:00:00' },
  ]

  it('filterEventos por tipo e busca', () => {
    assert.equal(filterEventos(eventos, { tipo: 'servos', tipos }).length, 1)
    assert.equal(filterEventos(eventos, { search: 'jornada', tipos })[0].id, '2')
    assert.equal(filterEventos(eventos, { search: 'salão', tipos })[0].id, '1')
  })

  it('groupEventosByMonth agrupa outubro e novembro', () => {
    const grupos = groupEventosByMonth(eventos)
    assert.equal(grupos.length, 2)
    assert.equal(grupos[0].itens.length, 2)
    assert.equal(grupos[1].itens.length, 1)
  })

  it('labels de tipo e status', () => {
    assert.equal(tipoLabel(tipos, 't2'), 'Jornada anual')
    assert.equal(tipoAbrev(tipos, 'perseveranca'), 'pers')
    assert.equal(statusCompraLabel('doado'), 'Item doado')
  })

  it('filterCasaisBusca encontra por nome', () => {
    const casais = [
      { id: 'a', nome: 'João', nome_conjuge: 'Maria' },
      { id: 'b', ele: { nome: 'Pedro' }, ela: { nome: 'Ana' } },
    ]
    assert.equal(filterCasaisBusca(casais, 'maria').length, 1)
    assert.equal(filterCasaisBusca(casais, 'pedro')[0].id, 'b')
  })

  it('canConfirmDeleteEvento exige digitar deletar', () => {
    assert.equal(canConfirmDeleteEvento('deletar'), true)
    assert.equal(canConfirmDeleteEvento(' DELETAR '), true)
    assert.equal(canConfirmDeleteEvento('delete'), false)
    assert.equal(canConfirmDeleteEvento(''), false)
  })

  it('buildEventoRelatorioHtml inclui seções do relatório', () => {
    const base = {
      evento: {
        titulo: 'Jornada <anual>',
        tipo: 'anual',
        permite_compras: true,
        inicia_em: '2026-11-15T09:00:00-03:00',
        local: 'Sítio',
        convidados_total: 4,
        participantes: [{ casal_rotulo: 'João e Maria', convidados: 2 }],
        itens_compra: [{ nome: 'Água', qtd: 10, unidade: 'fardo', status: 'pendente' }],
      },
      caixa: {
        saldo: 50,
        total_entradas: 100,
        total_saidas: 50,
        extrato: [
          {
            id: '1',
            tipo: 'entrada',
            descricao: 'Doação',
            valor: 100,
            doador: { rotulo: 'Padre João' },
            created_at: '2026-09-17T12:00:00Z',
          },
        ],
      },
      tipoNome: 'Jornada anual',
    }

    const completo = buildEventoRelatorioHtml({ ...base, tipoRelatorio: 'completo' })
    assert.match(completo, /Jornada &lt;anual&gt;/)
    assert.match(completo, /Relatório completo/)
    assert.match(completo, /Participantes e convidados/)
    assert.match(completo, /João e Maria/)
    assert.match(completo, /Lista de compras/)
    assert.match(completo, /Água/)
    assert.match(completo, /Extrato da conta corrente/)
    assert.match(completo, /Padre João/)
    assert.match(completo, /Eclesia/)

    const soPart = buildEventoRelatorioHtml({ ...base, tipoRelatorio: 'participantes' })
    assert.match(soPart, /Participantes e convidados/)
    assert.match(soPart, /João e Maria/)
    assert.doesNotMatch(soPart, /Lista de compras/)
    assert.doesNotMatch(soPart, /Extrato da conta corrente/)

    const soExtrato = buildEventoRelatorioHtml({ ...base, tipoRelatorio: 'extrato' })
    assert.match(soExtrato, /Extrato da conta corrente/)
    assert.match(soExtrato, /Padre João/)
    assert.doesNotMatch(soExtrato, /Participantes e convidados/)
    assert.doesNotMatch(soExtrato, /Lista de compras/)
  })

  it('resolveRelatorioSecoes e catálogo de tipos', () => {
    assert.deepEqual([...resolveRelatorioSecoes('extrato')], ['extrato'])
    assert.equal(RELATORIO_TIPOS.length, 4)
    assert.ok(RELATORIO_TIPOS.every((t) => t.id && t.label && t.secoes?.length))
  })
})
