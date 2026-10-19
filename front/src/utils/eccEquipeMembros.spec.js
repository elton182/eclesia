import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  addCasaisToEquipe,
  removeCasaisFromEquipe,
  initialEquipeCasalIds,
} from './eccEquipeMembros.js'

describe('eccEquipeMembros', () => {
  it('initialEquipeCasalIds filtra pela equipe', () => {
    const ids = initialEquipeCasalIds(
      [
        { id: 'a', equipe_id: 'eq1' },
        { id: 'b', equipe_id: 'eq2' },
        { id: 'c', equipe_id: 'eq1' },
      ],
      'eq1',
    )
    assert.deepEqual([...ids].sort(), ['a', 'c'])
  })

  it('addCasaisToEquipe move selecionados para a equipe', () => {
    const next = addCasaisToEquipe(new Set(['a']), ['b', 'c'])
    assert.deepEqual([...next].sort(), ['a', 'b', 'c'])
  })

  it('removeCasaisFromEquipe remove selecionados', () => {
    const next = removeCasaisFromEquipe(new Set(['a', 'b', 'c']), ['b'])
    assert.deepEqual([...next].sort(), ['a', 'c'])
  })
})
