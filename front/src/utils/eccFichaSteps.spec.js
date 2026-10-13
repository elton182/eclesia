import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  FICHA_STEPS,
  fichaStepAt,
  fichaStepProgress,
  fichaStepsCount,
  isFirstFichaStep,
  isLastFichaStep,
  validateFichaStep,
} from './eccFichaSteps.js'

describe('eccFichaSteps', () => {
  it('define as 5 etapas do plano', () => {
    assert.equal(fichaStepsCount(), 5)
    assert.deepEqual(
      FICHA_STEPS.map((s) => s.id),
      ['contato', 'endereco', 'pessoas', 'ecc', 'servico'],
    )
    assert.deepEqual(
      FICHA_STEPS.map((s) => s.title),
      ['Contato', 'Endereço', 'Pessoas', 'ECC', 'Serviço'],
    )
  })

  it('progresso e limites de índice', () => {
    assert.equal(fichaStepProgress(0), '1 de 5')
    assert.equal(fichaStepProgress(4), '5 de 5')
    assert.equal(isFirstFichaStep(0), true)
    assert.equal(isFirstFichaStep(1), false)
    assert.equal(isLastFichaStep(4), true)
    assert.equal(isLastFichaStep(3), false)
    assert.equal(fichaStepAt(-1), null)
    assert.equal(fichaStepAt(5), null)
    assert.equal(fichaStepAt(2)?.id, 'pessoas')
  })

  it('exige nomes de Ele e Ela só na etapa Contato', () => {
    assert.deepEqual(validateFichaStep(0, { nome: '', nome_conjuge: 'Maria' }), {
      ok: false,
      error: 'Informe o nome de Ele e de Ela.',
    })
    assert.deepEqual(validateFichaStep(0, { nome: 'João', nome_conjuge: '  ' }), {
      ok: false,
      error: 'Informe o nome de Ele e de Ela.',
    })
    assert.deepEqual(validateFichaStep(0, { nome: 'João', nome_conjuge: 'Maria' }), {
      ok: true,
    })
    assert.deepEqual(validateFichaStep(1, { nome: '' }), { ok: true })
    assert.deepEqual(validateFichaStep(4, {}), { ok: true })
  })
})
