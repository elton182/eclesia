/**
 * Filtra opções de select pesquisável pelo rótulo.
 * Se o termo for vazio ou igual ao rótulo já selecionado, devolve todas.
 *
 * @param {Array<{ value?: unknown, label?: string }>|null|undefined} options
 * @param {string} term
 * @param {string} [selectedLabel]
 * @returns {Array<{ value?: unknown, label?: string }>}
 */
export function filterOptionsByLabel(options, term, selectedLabel = '') {
  const list = Array.isArray(options) ? options : []
  const q = String(term ?? '').trim().toLowerCase()
  if (!q || (selectedLabel && String(term) === selectedLabel)) return list
  return list.filter((o) => String(o?.label ?? '')
    .toLowerCase()
    .includes(q))
}
