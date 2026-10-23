import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { resolveInstallPageStep } from './siteAccessInstall.js'

describe('resolveInstallPageStep', () => {
  it('pede login quando não autenticado', () => {
    assert.equal(resolveInstallPageStep({}), 'login')
    assert.equal(resolveInstallPageStep({ authenticated: false }), 'login')
  })

  it('mostra instalação após login', () => {
    assert.equal(resolveInstallPageStep({ authenticated: true }), 'install')
  })

  it('prioriza troca de senha obrigatória', () => {
    assert.equal(
      resolveInstallPageStep({ authenticated: true, mustChangePassword: true }),
      'change-password',
    )
  })
})
