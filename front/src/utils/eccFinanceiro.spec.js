import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  saldoLancamentos,
  totaisPorConta,
  acumuladosMensais,
  groupByMes,
  formatMoney,
  nomeMes,
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
})
