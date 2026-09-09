import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import {
  persistIgrejaId,
  loadIgrejaId,
  resolveCurrentIgrejaId,
  igrejaTipoLabel,
  STORAGE_KEY,
} from './igrejaContext.js'

const memory = new Map()
globalThis.localStorage = {
  getItem: (k) => (memory.has(k) ? memory.get(k) : null),
  setItem: (k, v) => memory.set(k, String(v)),
  removeItem: (k) => memory.delete(k),
  clear: () => memory.clear(),
}

describe('igrejaContext', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('persistIgrejaId grava e limpa localStorage', () => {
    persistIgrejaId('01ABC')
    assert.equal(localStorage.getItem(STORAGE_KEY), '01ABC')
    assert.equal(loadIgrejaId(), '01ABC')
    persistIgrejaId('')
    assert.equal(localStorage.getItem(STORAGE_KEY), null)
  })

  it('resolveCurrentIgrejaId usa preferência válida', () => {
    const list = [{ id: 'a' }, { id: 'b' }]
    assert.equal(resolveCurrentIgrejaId(list, 'b'), 'b')
  })

  it('resolveCurrentIgrejaId cai no storage ou na primeira', () => {
    const list = [{ id: 'a' }, { id: 'b' }]
    persistIgrejaId('b')
    assert.equal(resolveCurrentIgrejaId(list, 'x'), 'b')
    localStorage.clear()
    assert.equal(resolveCurrentIgrejaId(list, ''), 'a')
    assert.equal(resolveCurrentIgrejaId([], ''), '')
  })

  it('igrejaTipoLabel traduz tipos', () => {
    assert.equal(igrejaTipoLabel('paroquia'), 'Paróquia')
    assert.equal(igrejaTipoLabel('comunidade'), 'Comunidade')
    assert.equal(igrejaTipoLabel('outro'), 'Outro')
  })
})
