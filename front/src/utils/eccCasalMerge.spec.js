import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  casalEquipesLabel,
  resolveMergePair,
  toggleMergeSelection,
} from './eccCasalMerge.js'

describe('eccCasalMerge', () => {
  it('limita seleção a 2 casais', () => {
    let set = toggleMergeSelection([], 'a', true)
    set = toggleMergeSelection(set, 'b', true)
    set = toggleMergeSelection(set, 'c', true)
    assert.deepEqual([...set].sort(), ['a', 'b'])
  })

  it('resolve par keep/absorb', () => {
    const pair = resolveMergePair(
      [{ id: '1', nome: 'A' }, { id: '2', nome: 'B' }],
      ['1', '2'],
    )
    assert.equal(pair.keep.id, '1')
    assert.equal(pair.absorb.id, '2')
  })

  it('rótulo de equipes', () => {
    assert.equal(
      casalEquipesLabel({ equipes: [{ nome: 'A' }, { nome: 'B' }] }),
      'A · B',
    )
    assert.equal(casalEquipesLabel({ equipe_nome: 'X' }), 'X')
  })
})
