import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  LIDER_EQUIPE_TOUR_KEY,
  beginLiderTour,
  liderEquipeTourSteps,
  markLiderTourSeen,
  shouldAutoStartLiderTour,
} from './liderEquipeTour.js'

function memoryStorage(initial = {}) {
  const data = { ...initial }
  return {
    getItem: (key) => (Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null),
    setItem: (key, value) => {
      data[key] = String(value)
    },
  }
}

describe('liderEquipeTour', () => {
  it('abre na primeira visita e grava a flag ao fechar', () => {
    const storage = memoryStorage()
    const session = beginLiderTour({ storage })
    assert.equal(session.open, true)
    assert.equal(session.index, 0)
    assert.equal(shouldAutoStartLiderTour(storage), true)

    markLiderTourSeen(storage)
    assert.equal(storage.getItem(LIDER_EQUIPE_TOUR_KEY), '1')
    assert.equal(shouldAutoStartLiderTour(storage), false)
    assert.equal(beginLiderTour({ storage }).open, false)
  })

  it('Ver tour reabre do passo 1 mesmo com a flag gravada', () => {
    const storage = memoryStorage({ [LIDER_EQUIPE_TOUR_KEY]: '1' })
    const session = beginLiderTour({ storage, force: true })
    assert.equal(session.open, true)
    assert.equal(session.index, 0)
    assert.equal(session.steps[0].id, 'titulo')
    assert.equal(session.steps.at(-1).target, 'rever')
  })

  it('inclui o seletor só quando há mais de uma equipe', () => {
    const uma = liderEquipeTourSteps({ multipleEquipes: false })
    const varias = liderEquipeTourSteps({ multipleEquipes: true })
    assert.equal(uma.some((step) => step.id === 'seletor'), false)
    assert.equal(varias.some((step) => step.id === 'seletor'), true)
    assert.equal(varias.at(-1).id, 'rever')
  })
})
