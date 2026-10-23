import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import {
  PENDING_PWA_INSTALL_KEY,
  resolveInstallPageStep,
  markPendingPwaInstall,
  clearPendingPwaInstall,
  shouldOfferAppInstall,
  appInstallRoute,
} from './siteAccessInstall.js'

const memory = new Map()
globalThis.sessionStorage = {
  getItem: (k) => (memory.has(k) ? memory.get(k) : null),
  setItem: (k, v) => memory.set(k, String(v)),
  removeItem: (k) => memory.delete(k),
  clear: () => memory.clear(),
}

describe('resolveInstallPageStep', () => {
  it('pede login quando não autenticado', () => {
    assert.equal(resolveInstallPageStep({}), 'login')
  })

  it('após login manda para o app instalar', () => {
    assert.equal(resolveInstallPageStep({ authenticated: true }), 'go-app-install')
  })

  it('prioriza troca de senha', () => {
    assert.equal(
      resolveInstallPageStep({ authenticated: true, mustChangePassword: true }),
      'change-password',
    )
  })
})

describe('pending pwa install flag', () => {
  beforeEach(() => {
    sessionStorage.clear()
  })

  it('marca e detecta pendência', () => {
    assert.equal(shouldOfferAppInstall({}), false)
    markPendingPwaInstall()
    assert.equal(sessionStorage.getItem(PENDING_PWA_INSTALL_KEY), '1')
    assert.equal(shouldOfferAppInstall({}), true)
    clearPendingPwaInstall()
    assert.equal(shouldOfferAppInstall({}), false)
  })

  it('aceita query instalar=1', () => {
    assert.equal(shouldOfferAppInstall({ instalar: '1' }), true)
  })

  it('monta rota do app com query', () => {
    assert.deepEqual(appInstallRoute('/ecc/minha-equipe'), {
      path: '/ecc/minha-equipe',
      query: { instalar: '1' },
    })
  })
})
