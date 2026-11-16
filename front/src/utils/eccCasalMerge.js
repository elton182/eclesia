/**
 * Helpers de unificação de casais (SPEC-035).
 */

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
