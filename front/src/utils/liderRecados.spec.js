import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  LIDER_RECADOS_LAST_SEEN_KEY,
  defaultRecadosDesde,
  formatRecadosBadge,
  getRecadosLastSeen,
  markRecadosSeen,
} from './liderRecados.js'

function memoryStorage(initial = {}) {
  const data = { ...initial }
  return {
    getItem: (key) => (Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null),
    setItem: (key, value) => {
      data[key] = String(value)
    },
    _data: data,
  }
}

describe('liderRecados', () => {
  it('grava e lê lastSeen em ISO', () => {
    const storage = memoryStorage()
    assert.equal(getRecadosLastSeen(storage), null)
    markRecadosSeen(storage, new Date('2026-10-23T15:00:00.000Z'))
    assert.equal(storage.getItem(LIDER_RECADOS_LAST_SEEN_KEY), '2026-10-23T15:00:00.000Z')
    assert.equal(getRecadosLastSeen(storage), '2026-10-23T15:00:00.000Z')
  })

  it('ignora valor inválido no storage', () => {
    const storage = memoryStorage({ [LIDER_RECADOS_LAST_SEEN_KEY]: 'nao-e-data' })
    assert.equal(getRecadosLastSeen(storage), null)
  })

  it('formata badge discreto', () => {
    assert.equal(formatRecadosBadge(0), null)
    assert.equal(formatRecadosBadge(1), '1')
    assert.equal(formatRecadosBadge(9), '9')
    assert.equal(formatRecadosBadge(10), '9+')
  })

  it('defaultRecadosDesde é epoch', () => {
    assert.equal(defaultRecadosDesde(), new Date(0).toISOString())
  })
})
