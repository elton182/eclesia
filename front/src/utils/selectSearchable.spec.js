import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { filterOptionsByLabel } from './selectSearchable.js'

describe('filterOptionsByLabel', () => {
  const opts = [
    { value: '1', label: 'Liturgia' },
    { value: '2', label: 'Catequese' },
    { value: '3', label: 'Caridade' },
    { value: '4', label: 'Juventude' },
  ]

  it('devolve todas sem termo', () => {
    assert.deepEqual(filterOptionsByLabel(opts, ''), opts)
    assert.deepEqual(filterOptionsByLabel(opts, '  '), opts)
  })

  it('devolve todas quando o termo é o rótulo selecionado', () => {
    assert.deepEqual(filterOptionsByLabel(opts, 'Liturgia', 'Liturgia'), opts)
  })

  it('filtra por substring case-insensitive', () => {
    assert.deepEqual(filterOptionsByLabel(opts, 'cat'), [
      { value: '2', label: 'Catequese' },
    ])
    assert.deepEqual(filterOptionsByLabel(opts, 'JUV'), [
      { value: '4', label: 'Juventude' },
    ])
  })

  it('aceita lista vazia ou inválida', () => {
    assert.deepEqual(filterOptionsByLabel(null, 'x'), [])
    assert.deepEqual(filterOptionsByLabel(undefined, ''), [])
  })
})
