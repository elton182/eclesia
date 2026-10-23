import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { normalizeEquipeUsername, validateEquipeUsername } from './eccEquipeAcesso.js'

describe('eccEquipeAcesso', () => {
  it('normalizeEquipeUsername gera slug', () => {
    assert.equal(normalizeEquipeUsername('Cana Nova!'), 'cana-nova')
    assert.equal(normalizeEquipeUsername('  EQUIPE A  '), 'equipe-a')
    assert.equal(normalizeEquipeUsername('@@@'), '')
  })

  it('validateEquipeUsername rejeita vazio após slug', () => {
    const bad = validateEquipeUsername('!!!')
    assert.equal(bad.ok, false)
    assert.match(bad.message, /login válido/i)
  })

  it('validateEquipeUsername aceita slug válido', () => {
    const ok = validateEquipeUsername('Equipe Caná')
    assert.deepEqual(ok, { ok: true, username: 'equipe-cana' })
  })
})
