export const LIDER_RECADOS_LAST_SEEN_KEY = 'eclesia.lider.recados.lastSeen'

/**
 * @param {{ getItem: (key: string) => string|null }} storage
 * @returns {string|null} ISO datetime or null
 */
export function getRecadosLastSeen(storage) {
  const raw = storage.getItem(LIDER_RECADOS_LAST_SEEN_KEY)
  if (!raw || typeof raw !== 'string') return null
  const t = Date.parse(raw)
  return Number.isNaN(t) ? null : new Date(t).toISOString()
}

/**
 * @param {{ setItem: (key: string, value: string) => void }} storage
 * @param {Date|string} [when]
 */
export function markRecadosSeen(storage, when = new Date()) {
  const d = when instanceof Date ? when : new Date(when)
  storage.setItem(LIDER_RECADOS_LAST_SEEN_KEY, d.toISOString())
}

/**
 * Epoch remoto o bastante para contar tudo como “novo” na 1ª visita.
 * @returns {string}
 */
export function defaultRecadosDesde() {
  return new Date(0).toISOString()
}

/**
 * @param {number} count
 * @returns {string|null} null = sem badge; '.' = só ponto; senão número truncado
 */
export function formatRecadosBadge(count) {
  const n = Number(count) || 0
  if (n <= 0) return null
  if (n > 9) return '9+'
  return String(n)
}
