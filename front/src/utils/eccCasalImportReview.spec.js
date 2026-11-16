import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import {
  buildInitialDecisions,
  countPendingActions,
  formatDiffValue,
  formatPreviewSummary,
  importFieldLabel,
  patchDecision,
  statusBadgeLabel,
  toggleApplyField,
} from './eccCasalImportReview.js'

describe('eccCasalImportReview', () => {
  it('monta decisões iniciais a partir do preview', () => {
    const decisions = buildInitialDecisions([
      {
        row_index: 0,
        status: 'new',
        suggested_action: 'create',
        suggested_casal_id: null,
        suggested_apply_fields: [],
      },
      {
        row_index: 1,
        status: 'match',
        suggested_action: 'update',
        suggested_casal_id: 'abc',
        suggested_apply_fields: ['telefone'],
      },
    ])

    assert.equal(decisions[0].action, 'create')
    assert.equal(decisions[1].action, 'update')
    assert.equal(decisions[1].casal_id, 'abc')
    assert.deepEqual(decisions[1].apply_fields, ['telefone'])
  })

  it('alterna campos apply e promove skip→update', () => {
    let decisions = [
      { row_index: 2, action: 'skip', casal_id: 'x', apply_fields: [] },
    ]
    decisions = toggleApplyField(decisions, 2, 'endereco', true)
    assert.equal(decisions[0].action, 'update')
    assert.deepEqual(decisions[0].apply_fields, ['endereco'])

    decisions = toggleApplyField(decisions, 2, 'endereco', false)
    assert.deepEqual(decisions[0].apply_fields, [])
  })

  it('patchDecision limpa apply_fields ao criar/pular', () => {
    const base = [
      { row_index: 0, action: 'update', casal_id: '1', apply_fields: ['a'] },
    ]
    const skipped = patchDecision(base, 0, { action: 'skip' })
    assert.deepEqual(skipped[0].apply_fields, [])

    const created = patchDecision(base, 0, { action: 'create' })
    assert.equal(created[0].casal_id, null)
    assert.deepEqual(created[0].apply_fields, [])
  })

  it('conta só updates efetivos', () => {
    const counts = countPendingActions([
      { action: 'create', apply_fields: [] },
      { action: 'update', casal_id: '1', apply_fields: ['endereco'] },
      { action: 'update', casal_id: '2', apply_fields: [] },
      { action: 'skip', apply_fields: [] },
    ])
    assert.deepEqual(counts, { create: 1, update: 1, skip: 2 })
  })

  it('formata summary, labels e valores', () => {
    assert.match(formatPreviewSummary({ new: 2, match: 1 }), /2 novo/)
    assert.equal(importFieldLabel('endereco'), 'Endereço')
    assert.equal(formatDiffValue(null), '—')
    assert.equal(formatDiffValue(true), 'Sim')
    assert.equal(statusBadgeLabel('ambiguous'), 'Ambíguo')
  })
})
