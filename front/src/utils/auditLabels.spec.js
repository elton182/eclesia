import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  auditActionLabel,
  auditTypeLabel,
  auditDiffSummary,
  formatAuditDate,
} from './auditLabels.js'

describe('auditLabels', () => {
  it('traduz ações conhecidas', () => {
    assert.equal(auditActionLabel('login_success'), 'Login')
    assert.equal(auditActionLabel('created'), 'Criação')
    assert.equal(auditActionLabel('roles_synced'), 'Papéis sincronizados')
    assert.equal(auditActionLabel('outro'), 'outro')
  })

  it('traduz tipos de recurso', () => {
    assert.equal(auditTypeLabel('User'), 'Usuário')
    assert.equal(auditTypeLabel('App\\Models\\Igreja'), 'Igreja')
    assert.equal(auditTypeLabel(null), '—')
  })

  it('resume campos do diff', () => {
    assert.equal(auditDiffSummary({}), '—')
    assert.equal(
      auditDiffSummary({ new_values: { nome: 'A', tipo: 'paroquia' } }),
      'nome, tipo',
    )
    assert.equal(
      auditDiffSummary({
        old_values: { a: 1, b: 2, c: 3, d: 4, e: 5 },
        new_values: {},
      }),
      'a, b, c, d (+1)',
    )
  })

  it('formata data em pt-BR', () => {
    const formatted = formatAuditDate('2026-09-19T12:00:00.000Z')
    assert.match(formatted, /\d{2}\/\d{2}\/2026/)
  })
})
