import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  saldoLancamentos,
  totaisPorConta,
  acumuladosMensais,
  groupByMes,
  formatMoney,
  nomeMes,
  chartSeriesFromLivro,
  chartMaxAbs,
  buildFinanceiroChartConfig,
} from './eccFinanceiro.js'

describe('eccFinanceiro', () => {
  const contas = [
    { id: 'banco', nome: 'Paróquia' },
    { id: 'especie', nome: 'Espécie' },
  ]

  it('saldoLancamentos soma entradas e saídas', () => {
    assert.equal(
      saldoLancamentos([
        { id: '1', conta_id: 'banco', tipo: 'entrada', valor: 100 },
        { id: '2', conta_id: 'banco', tipo: 'saida', valor: 40 },
      ]),
      60,
    )
  })

  it('transferência se anula no total geral', () => {
    const lancamentos = [
      { id: 'a', conta_id: 'especie', tipo: 'entrada', valor: 500, transferencia_id: null },
      { id: 'b', conta_id: 'especie', tipo: 'saida', valor: 300, transferencia_id: 't1' },
      { id: 'c', conta_id: 'banco', tipo: 'entrada', valor: 300, transferencia_id: 't1' },
    ]
    assert.equal(saldoLancamentos(lancamentos), 500)
    const totais = totaisPorConta(lancamentos, contas)
    assert.equal(totais.especie.saldo, 200)
    assert.equal(totais.banco.saldo, 300)
  })

  it('acumuladosMensais carrega saldo de abertura', () => {
    assert.deepEqual(acumuladosMensais(100, [50, -20, 10]), [150, 130, 140])
  })

  it('groupByMes agrupa por data', () => {
    const g = groupByMes([
      { id: '1', conta_id: 'banco', tipo: 'entrada', valor: 1, data: '2026-03-05' },
      { id: '2', conta_id: 'banco', tipo: 'saida', valor: 1, data: '2026-03-20' },
      { id: '3', conta_id: 'banco', tipo: 'entrada', valor: 1, data: '2026-04-01' },
    ])
    assert.equal(g[3].length, 2)
    assert.equal(g[4].length, 1)
    assert.equal(g[1].length, 0)
  })

  it('formatMoney e nomeMes', () => {
    assert.match(formatMoney(1234.5), /1\.234,50/)
    assert.equal(nomeMes(1), 'Janeiro')
    assert.equal(nomeMes(12), 'Dezembro')
  })

  it('chartSeriesFromLivro monta 12 meses com entradas/saídas', () => {
    const series = chartSeriesFromLivro({
      meses: [
        {
          mes: 3,
          total_mensal: 50,
          acumulado: 150,
          totais_por_conta: {
            a: { entradas: 100, saidas: 20 },
            b: { entradas: 10, saidas: 40 },
          },
        },
      ],
    })
    assert.equal(series.length, 12)
    assert.equal(series[0].entradas, 0)
    assert.equal(series[2].label, 'Março')
    assert.equal(series[2].entradas, 110)
    assert.equal(series[2].saidas, 60)
    assert.equal(series[2].acumulado, 150)
    assert.equal(chartMaxAbs([110, 60]), 121)

    const cfg = buildFinanceiroChartConfig(series)
    assert.equal(cfg.type, 'bar')
    assert.equal(cfg.data.labels.length, 12)
    assert.equal(cfg.data.datasets.length, 3)
    assert.equal(cfg.data.datasets[0].label, 'Entradas')
    assert.equal(cfg.data.datasets[1].label, 'Saídas')
    assert.equal(cfg.data.datasets[2].label, 'Acumulado')
    assert.equal(cfg.data.datasets[0].data[2], 110)
    assert.equal(cfg.data.datasets[2].data[2], 150)
  })
})
