/**
 * Helpers do modal dual-panel de casais na equipe (SPEC-021).
 */

/**
 * @param {Iterable<string>} naEquipeIds
 * @param {Iterable<string>} selectedIds
 * @returns {Set<string>}
 */
export function addCasaisToEquipe(naEquipeIds, selectedIds) {
  const next = new Set([...naEquipeIds].map(String))
  for (const id of selectedIds) {
    next.add(String(id))
  }
  return next
}

/**
 * @param {Iterable<string>} naEquipeIds
 * @param {Iterable<string>} selectedIds
 * @returns {Set<string>}
 */
export function removeCasaisFromEquipe(naEquipeIds, selectedIds) {
  const next = new Set([...naEquipeIds].map(String))
  for (const id of selectedIds) {
    next.delete(String(id))
  }
  return next
}

/**
 * @param {Array<{ id: string|number, equipe_id?: string|null }>} casais
 * @param {string} equipeId
 * @returns {Set<string>}
 */
export function initialEquipeCasalIds(casais, equipeId) {
  const eq = String(equipeId || '')
  return new Set(
    (casais || [])
      .filter((c) => String(c.equipe_id || '') === eq)
      .map((c) => String(c.id)),
  )
}
