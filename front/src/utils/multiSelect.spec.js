import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  filterMultiSelectOptions,
  multiSelectSummary,
  selectedOptionsFromValues,
  toggleMultiSelectValue,
} from './multiSelect.js'

describe('multiSelect', () => {
  const options = [
    { value: 'a', label: 'Alpha' },
    { value: 'b', label: 'Beta' },
    { value: 'c', label: 'Gamma' },
  ]

  it('seleciona opções a partir dos valores', () => {
    assert.deepEqual(
      selectedOptionsFromValues(options, ['b', 'a']).map((o) => o.value),
      ['a', 'b'],
    )
  })

  it('alterna valores no conjunto', () => {
    assert.deepEqual(toggleMultiSelectValue(['a'], 'b', true).sort(), ['a', 'b'])
    assert.deepEqual(toggleMultiSelectValue(['a', 'b'], 'a', false), ['b'])
  })

  it('filtra por rótulo', () => {
    assert.equal(filterMultiSelectOptions(options, 'ga').length, 1)
    assert.equal(filterMultiSelectOptions(options, '').length, 3)
  })

  it('resume contagem', () => {
    assert.equal(
      multiSelectSummary(0, 'equipe', 'equipes', 'Nenhuma equipe'),
      'Nenhuma equipe',
    )
    assert.equal(multiSelectSummary(1, 'equipe', 'equipes'), '1 equipe')
    assert.equal(multiSelectSummary(3, 'equipe', 'equipes'), '3 equipes')
  })
})
