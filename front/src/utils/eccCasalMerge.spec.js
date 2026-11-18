import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
  buildInitialMergeApplyFields,
  casalEquipesLabel,
  formatMergeEquipes,
  formatMergeReviewSummary,
  mergeFieldLabel,
  resolveMergePair,
  toggleMergeApplyField,
  toggleMergeSelection,
} from './eccCasalMerge.js'

const here = dirname(fileURLToPath(import.meta.url))

describe('eccCasalMerge', () => {
  it('limita seleção a 2 casais', () => {
    let set = toggleMergeSelection([], 'a', true)
    set = toggleMergeSelection(set, 'b', true)
    set = toggleMergeSelection(set, 'c', true)
    assert.deepEqual([...set].sort(), ['a', 'b'])
  })

  it('resolve par keep/absorb', () => {
    const pair = resolveMergePair(
      [{ id: '1', nome: 'A' }, { id: '2', nome: 'B' }],
      ['1', '2'],
    )
    assert.equal(pair.keep.id, '1')
    assert.equal(pair.absorb.id, '2')
  })

  it('rótulo de equipes', () => {
    assert.equal(
      casalEquipesLabel({ equipes: [{ nome: 'A' }, { nome: 'B' }] }),
      'A · B',
    )
    assert.equal(casalEquipesLabel({ equipe_nome: 'X' }), 'X')
    assert.equal(formatMergeEquipes([{ nome: 'Alpha' }, { nome: 'Beta' }]), 'Alpha · Beta')
  })

  it('aplica sugestões e toggle de campos na revisão', () => {
    assert.deepEqual(
      buildInitialMergeApplyFields({ suggested_apply_fields: ['telefone', 'cidade'] }),
      ['telefone', 'cidade'],
    )
    assert.deepEqual(buildInitialMergeApplyFields(null), [])
    assert.deepEqual(toggleMergeApplyField(['telefone'], 'cidade', true).sort(), ['cidade', 'telefone'])
    assert.deepEqual(toggleMergeApplyField(['telefone', 'cidade'], 'cidade', false), ['telefone'])
    assert.equal(mergeFieldLabel('telefone'), 'Telefone (ele)')
  })

  it('resumo da revisão', () => {
    const text = formatMergeReviewSummary(
      {
        field_diffs: [{ field: 'a' }, { field: 'b' }],
        equipes_union: [{ id: '1' }],
      },
      ['a'],
    )
    assert.match(text, /2 diferença/)
    assert.match(text, /1 campo/)
    assert.match(text, /1 equipe/)
  })

  it('lista de casais abre revisão de unificação com diffs', () => {
    const view = readFileSync(join(here, '../views/EccCasaisView.vue'), 'utf8')
    assert.match(view, /merge\/preview/)
    assert.match(view, /casais-merge-review/)
    assert.match(view, /openMergeReview/)
    assert.match(view, /apply_fields/)
    assert.match(view, /Trocar mantido/)
    assert.match(view, /valor mais completo/)
  })
})
