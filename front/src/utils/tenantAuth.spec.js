import { describe, it, beforeEach } from 'node:test'
import assert from 'node:assert/strict'
import {
  buildTenantLoginPayload,
  validateTenantLoginForm,
  parseTenantAliases,
  extractApiError,
  persistLoginCredentials,
  loadLoginCredentials,
  rememberTenantForLogin,
  LOGIN_TENANT_STORAGE_KEY,
  LOGIN_EMAIL_STORAGE_KEY,
} from './tenantAuth.js'

const memory = new Map()
globalThis.localStorage = {
  getItem: (k) => (memory.has(k) ? memory.get(k) : null),
  setItem: (k, v) => memory.set(k, String(v)),
  removeItem: (k) => memory.delete(k),
  clear: () => memory.clear(),
}

describe('persistLoginCredentials / loadLoginCredentials', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('grava tenant e e-mail no localStorage', () => {
    persistLoginCredentials({ tenant: '  paroquia  ', email: ' a@b.com ' })
    assert.equal(localStorage.getItem(LOGIN_TENANT_STORAGE_KEY), 'paroquia')
    assert.equal(localStorage.getItem(LOGIN_EMAIL_STORAGE_KEY), 'a@b.com')
    assert.deepEqual(loadLoginCredentials(), {
      tenant: 'paroquia',
      email: 'a@b.com',
    })
  })

  it('limpa chaves quando valores vazios', () => {
    persistLoginCredentials({ tenant: 'x', email: 'y@z.com' })
    persistLoginCredentials({ tenant: '', email: '' })
    assert.equal(localStorage.getItem(LOGIN_TENANT_STORAGE_KEY), null)
    assert.equal(localStorage.getItem(LOGIN_EMAIL_STORAGE_KEY), null)
    assert.deepEqual(loadLoginCredentials(), { tenant: '', email: '' })
  })
})

describe('rememberTenantForLogin', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('grava o slug sem apagar o e-mail lembrado', () => {
    persistLoginCredentials({ tenant: 'antiga', email: 'a@b.com' })
    rememberTenantForLogin('  paroquia-nova  ')
    assert.equal(localStorage.getItem(LOGIN_TENANT_STORAGE_KEY), 'paroquia-nova')
    assert.equal(localStorage.getItem(LOGIN_EMAIL_STORAGE_KEY), 'a@b.com')
  })

  it('ignora slug vazio', () => {
    persistLoginCredentials({ tenant: 'paroquia', email: 'a@b.com' })
    rememberTenantForLogin('   ')
    assert.equal(localStorage.getItem(LOGIN_TENANT_STORAGE_KEY), 'paroquia')
  })
})

describe('buildTenantLoginPayload', () => {
  it('monta payload com tenant e-mail e senha', () => {
    const p = buildTenantLoginPayload('  paroquia  ', 'a@b.com', 'secret')
    assert.deepEqual(p, {
      tenant: 'paroquia',
      email: 'a@b.com',
      password: 'secret',
    })
  })
})

describe('validateTenantLoginForm', () => {
  it('exige organização', () => {
    const r = validateTenantLoginForm({ tenant: '', email: 'a@b.com', password: 'x' })
    assert.equal(r.ok, false)
  })

  it('aceita formulário completo', () => {
    const r = validateTenantLoginForm({
      tenant: 'demo',
      email: 'a@b.com',
      password: 'x',
    })
    assert.equal(r.ok, true)
  })
})

describe('parseTenantAliases', () => {
  it('separa por vírgula', () => {
    assert.deepEqual(parseTenantAliases('psj, São José'), ['psj', 'São José'])
  })
})

describe('extractApiError', () => {
  it('prioriza message da API', () => {
    assert.equal(
      extractApiError({ response: { data: { message: 'slug já existe' } } }),
      'slug já existe',
    )
  })

  it('junta errors de validação', () => {
    assert.equal(
      extractApiError({
        response: { data: { errors: { slug: ['inválido'], name: ['obrigatório'] } } },
      }),
      'inválido obrigatório',
    )
  })

  it('usa fallback quando não há resposta', () => {
    assert.equal(extractApiError({}, 'Falha ao salvar'), 'Falha ao salvar')
  })
})
