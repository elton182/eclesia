import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { formatAnexoTamanho, formatMuralBadges } from './eccMural.js'

describe('eccMural', () => {
  it('formata badges de não lidos', () => {
    assert.deepEqual(formatMuralBadges({ recados: 0, reflexoes: 0 }), {
      recados: null,
      reflexoes: null,
    })
    assert.deepEqual(formatMuralBadges({ recados: 1, reflexoes: 12 }), {
      recados: '1',
      reflexoes: '9+',
    })
  })

  it('formata tamanho do anexo', () => {
    assert.equal(formatAnexoTamanho(0), '')
    assert.equal(formatAnexoTamanho(500), '500 B')
    assert.equal(formatAnexoTamanho(2048), '2.0 KB')
    assert.equal(formatAnexoTamanho(2 * 1024 * 1024), '2.0 MB')
  })
})
