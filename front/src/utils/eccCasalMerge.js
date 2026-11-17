/**
 * Helpers de unificação de casais (SPEC-035 / SPEC-036).
 */

import { IMPORT_FIELD_LABELS } from './eccCasalImportReview.js'

/**
 * @param {Iterable<string>} selectedIds
 * @param {string} casalId
 * @param {boolean} checked
 * @param {number} [max=2]
 */
export function toggleMergeSelection(selectedIds, casalId, checked, max = 2) {
  const next = new Set([...selectedIds].map(String))
  const id = String(casalId)
  if (!checked) {
    next.delete(id)
    return next
  }
  if (next.has(id)) return next
  if (next.size >= max) return next
  next.add(id)
  return next
}

/**
 * @param {Array<{ id: string }>} casais
 * @param {Iterable<string>} selectedIds
 * @returns {{ keep: object|null, absorb: object|null }}
 */
export function resolveMergePair(casais, selectedIds) {
  const ids = [...selectedIds].map(String)
  if (ids.length !== 2) return { keep: null, absorb: null }
  const [a, b] = ids
  const keep = (casais || []).find((c) => String(c.id) === a) || null
  const absorb = (casais || []).find((c) => String(c.id) === b) || null
  return { keep, absorb }
}

/**
 * Rótulo curto para exibir equipes do casal.
 * @param {{ equipe_nome?: string, equipes?: Array<{ nome?: string }> }} casal
 */
export function casalEquipesLabel(casal) {
  if (Array.isArray(casal?.equipes) && casal.equipes.length) {
    return casal.equipes.map((e) => e.nome).filter(Boolean).join(' · ') || 'Sem equipe'
  }
  return casal?.equipe_nome || 'Sem equipe'
}

/**
 * @param {string} field
 */
export function mergeFieldLabel(field) {
  return IMPORT_FIELD_LABELS[field] || field
}

/**
 * @param {object|null} preview
 * @returns {string[]}
 */
export function buildInitialMergeApplyFields(preview) {
  if (Array.isArray(preview?.suggested_apply_fields)) {
    return [...preview.suggested_apply_fields]
  }
  return []
}

/**
 * @param {string[]} applyFields
 * @param {string} field
 * @param {boolean} checked
 */
export function toggleMergeApplyField(applyFields, field, checked) {
  const set = new Set(applyFields || [])
  if (checked) set.add(field)
  else set.delete(field)
  return [...set]
}

/**
 * @param {Array<{ id?: string, nome?: string }>|undefined} equipes
 */
export function formatMergeEquipes(equipes) {
  if (!Array.isArray(equipes) || !equipes.length) return 'Sem equipe'
  return equipes.map((e) => e.nome).filter(Boolean).join(' · ') || 'Sem equipe'
}

/**
 * Resumo curto do preview para o cabeçalho da revisão.
 * @param {object|null} preview
 * @param {string[]} applyFields
 */
export function formatMergeReviewSummary(preview, applyFields = []) {
  if (!preview) return 'Carregando comparação…'
  const diffs = Array.isArray(preview.field_diffs) ? preview.field_diffs.length : 0
  const selected = (applyFields || []).length
  const equipes = Array.isArray(preview.equipes_union) ? preview.equipes_union.length : 0
  const parts = []
  if (diffs) parts.push(`${diffs} diferença(s)`)
  else parts.push('Sem diferenças de campos')
  if (selected) parts.push(`${selected} campo(s) do absorvido serão aplicados`)
  if (equipes) parts.push(`${equipes} equipe(s) unidas`)
  return parts.join(' · ')
}
