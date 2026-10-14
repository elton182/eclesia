import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  labelStatusDeposito,
  competenciaAtual,
  formatCompetencia,
  depositosPendentes,
  buildDepositoFormData,
} from './eccFinanceiroDepositos.js'

describe('eccFinanceiroDepositos', () => {
  it('labelStatusDeposito', () => {
    assert.equal(labelStatusDeposito('provisionado'), 'Aguardando aprovação')
    assert.equal(labelStatusDeposito('confirmado'), 'Confirmado')
    assert.equal(labelStatusDeposito('rejeitado'), 'Rejeitado')
  })

  it('competenciaAtual e formatCompetencia', () => {
    assert.equal(competenciaAtual(new Date(2026, 9, 14)), '2026-10')
    assert.equal(formatCompetencia('2026-10'), '10/2026')
  })

  it('depositosPendentes filtra provisionados', () => {
    const list = depositosPendentes([
      { id: '1', status: 'provisionado' },
      { id: '2', status: 'confirmado' },
      { id: '3', status: 'rejeitado' },
    ])
    assert.equal(list.length, 1)
    assert.equal(list[0].id, '1')
  })

  it('buildDepositoFormData monta multipart', () => {
    const file = new File(['x'], 'c.pdf', { type: 'application/pdf' })
    const fd = buildDepositoFormData({
      equipe_id: 'eq1',
      competencia: '2026-10',
      valor: 150.5,
      comprovante: file,
    })
    assert.equal(fd.get('equipe_id'), 'eq1')
    assert.equal(fd.get('competencia'), '2026-10')
    assert.equal(fd.get('valor'), '150.5')
    assert.ok(fd.get('comprovante') instanceof File)
  })
})
