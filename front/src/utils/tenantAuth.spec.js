import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  buildTenantLoginPayload,
  validateTenantLoginForm,
  parseTenantAliases,
  extractApiError,
} from './tenantAuth.js'

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
