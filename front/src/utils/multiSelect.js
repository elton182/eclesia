/**
 * Helpers de multi-select com chips (lista de valores).
 */

/**
 * @param {Array<{ value?: unknown, label?: string }>|null|undefined} options
 * @param {Iterable<unknown>} selectedValues
 * @returns {Array<{ value: unknown, label: string }>}
 */
export function selectedOptionsFromValues(options, selectedValues) {
  const list = Array.isArray(options) ? options : []
  const selected = new Set([...selectedValues || []].map(String))
  return list.filter((o) => selected.has(String(o?.value)))
}

/**
 * @param {Iterable<unknown>} selectedValues
 * @param {unknown} value
 * @param {boolean} checked
 * @returns {string[]}
 */
export function toggleMultiSelectValue(selectedValues, value, checked) {
  const next = new Set([...selectedValues || []].map(String))
  const key = String(value)
  if (checked) next.add(key)
  else next.delete(key)
  return [...next]
}

/**
 * @param {Array<{ value?: unknown, label?: string }>|null|undefined} options
 * @param {string} term
 * @returns {Array<{ value?: unknown, label?: string }>}
 */
export function filterMultiSelectOptions(options, term) {
  const list = Array.isArray(options) ? options : []
  const q = String(term ?? '').trim().toLowerCase()
  if (!q) return list
  return list.filter((o) => String(o?.label ?? '').toLowerCase().includes(q))
}

/**
 * @param {number} count
 * @param {string} [singular='selecionado']
 * @param {string} [plural='selecionados']
 * @param {string} [noneLabel]
 */
export function multiSelectSummary(
  count,
  singular = 'selecionado',
  plural = 'selecionados',
  noneLabel = '',
) {
  const n = Number(count) || 0
  if (n <= 0) return noneLabel || `Nenhum ${singular}`
  if (n === 1) return `1 ${singular}`
  return `${n} ${plural}`
}
