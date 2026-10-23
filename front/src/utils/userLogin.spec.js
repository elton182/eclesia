import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  normalizeUsername,
  isSyntheticEmail,
  displayUserLogin,
  validateUserLoginFields,
} from './userLogin.js'

describe('userLogin', () => {
  it('normalizeUsername gera slug', () => {
    assert.equal(normalizeUsername('Monitor Sala!'), 'monitor-sala')
  })

  it('isSyntheticEmail detecta domínios internos', () => {
    assert.equal(isSyntheticEmail('a@login.local'), true)
    assert.equal(isSyntheticEmail('a.b@equipe.local'), true)
    assert.equal(isSyntheticEmail('pessoa@igreja.org'), false)
    assert.equal(isSyntheticEmail(null), false)
  })

  it('displayUserLogin prefere username', () => {
    assert.equal(displayUserLogin({ username: 'secretaria', email: 'a@b.com' }), 'secretaria')
    assert.equal(displayUserLogin({ username: null, email: 'a@b.com' }), 'a@b.com')
    assert.equal(displayUserLogin({ username: null, email: 'x@login.local' }), '—')
  })

  it('validateUserLoginFields exige email ou username', () => {
    const bad = validateUserLoginFields({ email: '', username: '' })
    assert.equal(bad.ok, false)
    assert.match(bad.message, /e-mail ou login/i)
  })

  it('validateUserLoginFields aceita só username', () => {
    assert.deepEqual(validateUserLoginFields({ username: 'Monitor A' }), {
      ok: true,
      email: null,
      username: 'monitor-a',
    })
  })

  it('validateUserLoginFields rejeita username inválido', () => {
    const bad = validateUserLoginFields({ username: '@@@' })
    assert.equal(bad.ok, false)
    assert.match(bad.message, /login válido/i)
  })

  it('validateUserLoginFields aceita só email', () => {
    assert.deepEqual(validateUserLoginFields({ email: 'a@b.com' }), {
      ok: true,
      email: 'a@b.com',
      username: null,
    })
  })
})
