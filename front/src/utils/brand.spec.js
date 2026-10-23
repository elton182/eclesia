import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { PLATFORM_BRAND } from './brand.js'

describe('PLATFORM_BRAND', () => {
  it('define nome Apostolus e tagline', () => {
    assert.equal(PLATFORM_BRAND.nome, 'Apostolus')
    assert.equal(PLATFORM_BRAND.nomeCurto, 'Apostolus')
    assert.match(PLATFORM_BRAND.tagline, /Igrejas e Paróquias/i)
  })
})
