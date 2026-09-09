const STORAGE_KEY = 'eclesia_igreja_id'

/**
 * @param {string|null|undefined} id
 */
export function persistIgrejaId(id) {
  if (id) {
    localStorage.setItem(STORAGE_KEY, id)
  } else {
    localStorage.removeItem(STORAGE_KEY)
  }
}

/**
 * @returns {string}
 */
export function loadIgrejaId() {
  return localStorage.getItem(STORAGE_KEY) || ''
}

/**
 * Resolve id atual: preferência salva se ainda existir na lista; senão a primeira.
 * @param {Array<{ id: string }>} igrejas
 * @param {string} preferredId
 * @returns {string}
 */
export function resolveCurrentIgrejaId(igrejas, preferredId = '') {
  const list = Array.isArray(igrejas) ? igrejas : []
  if (!list.length) return ''
  if (preferredId && list.some((i) => i.id === preferredId)) {
    return preferredId
  }
  const stored = loadIgrejaId()
  if (stored && list.some((i) => i.id === stored)) {
    return stored
  }
  return list[0].id
}

/**
 * Labels de tipo para UI.
 * @type {Record<string, string>}
 */
export const IGREJA_TIPO_LABELS = {
  paroquia: 'Paróquia',
  comunidade: 'Comunidade',
  outro: 'Outro',
}

/**
 * @param {string} tipo
 * @returns {string}
 */
export function igrejaTipoLabel(tipo) {
  return IGREJA_TIPO_LABELS[tipo] || tipo || '—'
}

export { STORAGE_KEY }
